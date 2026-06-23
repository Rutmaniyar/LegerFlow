<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

final class InvoiceService
{
    public function refreshStatus(int $invoiceId): void
    {
        $invoice = app()->db()->fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return;
        }

        $paid = (float) (app()->db()->fetch(
            "SELECT COALESCE(SUM(IF(type = 'refund', -amount, amount)), 0) AS paid FROM payments WHERE invoice_id = ?",
            [$invoiceId]
        )['paid'] ?? 0);
        $balance = max(0, (float) $invoice['total'] - $paid);
        $status = $invoice['status'];
        $latestPaymentDate = null;

        if ($status !== 'void') {
            if ($balance <= 0.0001 && (float) $invoice['total'] > 0) {
                $status = 'paid';
                $latestPaymentDate = app()->db()->fetch(
                    'SELECT payment_date FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC LIMIT 1',
                    [$invoiceId]
                )['payment_date'] ?? null;
            } elseif ($paid > 0) {
                $status = 'partial';
            } elseif (strtotime((string) $invoice['due_date']) < strtotime(date('Y-m-d')) && !in_array($status, ['draft', 'paid'], true)) {
                $status = 'overdue';
            }
        }

        $paidAt = $status === 'paid' ? ($invoice['paid_at'] ?? $latestPaymentDate ?? date('Y-m-d H:i:s')) : null;

        app()->db()->execute(
            'UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ?, paid_at = ?, updated_at = NOW() WHERE id = ?',
            [$paid, $balance, $status, $paidAt, $invoiceId]
        );
    }

    public function recordPayment(int $invoiceId, array $data): void
    {
        app()->db()->transaction(function () use ($invoiceId, $data): void {
            $invoice = app()->db()->fetch('SELECT currency FROM invoices WHERE id = ?', [$invoiceId]);
            if (!$invoice) {
                throw new \RuntimeException('Invoice not found.');
            }

            app()->db()->execute(
                'INSERT INTO payments (invoice_id, amount, currency, payment_date, method, reference, notes, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $invoiceId,
                    (float) $data['amount'],
                    $invoice['currency'],
                    $data['payment_date'],
                    $data['method'],
                    $data['reference'],
                    $data['notes'],
                    Auth::id(),
                ]
            );

            $this->refreshStatus($invoiceId);
        });

        AuditLogger::log('payment.recorded', 'invoice', $invoiceId, ['amount' => $data['amount']]);
    }

    public function recordRefund(int $invoiceId, array $data): void
    {
        app()->db()->transaction(function () use ($invoiceId, $data): void {
            $invoice = app()->db()->fetch('SELECT currency FROM invoices WHERE id = ?', [$invoiceId]);
            if (!$invoice) {
                throw new \RuntimeException('Invoice not found.');
            }

            app()->db()->execute(
                "INSERT INTO payments (invoice_id, amount, type, currency, payment_date, method, reference, notes, recorded_by)
                 VALUES (?, ?, 'refund', ?, ?, ?, ?, ?, ?)",
                [
                    $invoiceId,
                    (float) $data['amount'],
                    $invoice['currency'],
                    $data['payment_date'],
                    $data['method'] ?? 'refund',
                    $data['reference'] ?? null,
                    $data['notes'] ?? null,
                    Auth::id(),
                ]
            );

            $this->refreshStatus($invoiceId);
        });

        AuditLogger::log('payment.refunded', 'invoice', $invoiceId, ['amount' => $data['amount']]);
    }

    public function deletePayment(int $invoiceId, int $paymentId): void
    {
        app()->db()->transaction(function () use ($invoiceId, $paymentId): void {
            $deleted = app()->db()->execute('DELETE FROM payments WHERE id = ? AND invoice_id = ?', [$paymentId, $invoiceId]);
            if ($deleted === 0) {
                throw new \RuntimeException('Payment not found.');
            }

            $this->refreshStatus($invoiceId);
        });

        AuditLogger::log('payment.deleted', 'invoice', $invoiceId, ['payment_id' => $paymentId]);
    }

    public function markSent(int $invoiceId): void
    {
        app()->db()->execute(
            "UPDATE invoices SET status = IF(status = 'draft', 'sent', status), sent_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$invoiceId]
        );
        AuditLogger::log('invoice.sent', 'invoice', $invoiceId);
    }

    public function convertQuote(int $quoteId): int
    {
        return app()->db()->transaction(function () use ($quoteId): int {
            $quote = app()->db()->fetch('SELECT * FROM quotes WHERE id = ?', [$quoteId]);
            if (!$quote) {
                throw new \RuntimeException('Quote not found.');
            }

            $number = (new NumberGenerator())->nextInvoiceNumber();
            $dueDate = date('Y-m-d', strtotime('+14 days'));
            $invoiceId = app()->db()->insert(
                'INSERT INTO invoices
                 (client_id, quote_id, invoice_number, status, issue_date, due_date, currency, subtotal, discount_total, tax_total, total, balance_due, notes, terms, public_token, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $quote['client_id'],
                    $quoteId,
                    $number,
                    'draft',
                    date('Y-m-d'),
                    $dueDate,
                    $quote['currency'],
                    $quote['subtotal'],
                    $quote['discount_total'],
                    $quote['tax_total'],
                    $quote['total'],
                    $quote['total'],
                    $quote['notes'],
                    $quote['terms'],
                    hash('sha256', random_bytes(32)),
                    Auth::id(),
                ]
            );

            $items = app()->db()->fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order', [$quoteId]);
            foreach ($items as $item) {
                app()->db()->execute(
                    'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_rate, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$invoiceId, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_rate'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }

            app()->db()->execute(
                "UPDATE quotes SET status = 'converted', converted_invoice_id = ?, updated_at = NOW() WHERE id = ?",
                [$invoiceId, $quoteId]
            );

            AuditLogger::log('quote.converted', 'quote', $quoteId, ['invoice_id' => $invoiceId]);
            return $invoiceId;
        });
    }
}

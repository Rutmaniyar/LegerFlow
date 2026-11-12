<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    private const PAGE_WIDTH = 595.0;
    private const PAGE_HEIGHT = 842.0;
    private const MARGIN = 50.0;

    public function invoiceHtml(int $invoiceId): string
    {
        [$business, $document, $items, $payments, $defaultCurrency] = $this->loadInvoice($invoiceId);

        return $this->renderHtml($business, $document, $items, $payments, 'invoice_number', true, $defaultCurrency, false);
    }

    public function quoteHtml(int $quoteId): string
    {
        [$business, $document, $items, $defaultCurrency] = $this->loadQuote($quoteId);

        return $this->renderHtml($business, $document, $items, [], 'quote_number', false, $defaultCurrency, false);
    }

    public function invoicePdf(int $invoiceId): string
    {
        [$business, $document, $items, $payments, $defaultCurrency] = $this->loadInvoice($invoiceId);

        if (class_exists(Dompdf::class)) {
            $html = $this->renderHtml($business, $document, $items, $payments, 'invoice_number', true, $defaultCurrency, true);
            return $this->htmlToPdf($html);
        }

        return $this->legacyRender($business, $document, $items, $payments, 'invoice_number', true);
    }

    public function quotePdf(int $quoteId): string
    {
        [$business, $document, $items, $defaultCurrency] = $this->loadQuote($quoteId);

        if (class_exists(Dompdf::class)) {
            $html = $this->renderHtml($business, $document, $items, [], 'quote_number', false, $defaultCurrency, true);
            return $this->htmlToPdf($html);
        }

        return $this->legacyRender($business, $document, $items, [], 'quote_number', false);
    }

    public function expenseHtml(int $expenseId): string
    {
        [$business, $expense] = $this->loadExpense($expenseId);

        ob_start();
        $html = View::render('pdf/expense', ['business' => $business, 'expense' => $expense, 'forPdf' => false], '');
        ob_end_clean();

        return $html;
    }

    public function expensePdf(int $expenseId): string
    {
        [$business, $expense] = $this->loadExpense($expenseId);

        if (class_exists(Dompdf::class)) {
            ob_start();
            $html = View::render('pdf/expense', ['business' => $business, 'expense' => $expense, 'forPdf' => true], '');
            ob_end_clean();
            return $this->htmlToPdf($html);
        }

        return $this->legacyRenderExpense($business, $expense);
    }

    /** @return array{0: array, 1: array} */
    private function loadExpense(int $expenseId): array
    {
        $expense = app()->db()->fetch(
            'SELECT expenses.*, COALESCE(vendors.name, expenses.vendor) AS vendor_name, vendors.contact_name AS vendor_contact_name,
                    vendors.email AS vendor_email, vendors.phone AS vendor_phone, vendors.website AS vendor_website,
                    vendors.billing_address AS vendor_billing_address, vendors.tax_number AS vendor_tax_number
             FROM expenses LEFT JOIN vendors ON vendors.id = expenses.vendor_id WHERE expenses.id = ?',
            [$expenseId]
        );
        $business = (new SettingsService())->business();

        return [$business, $expense ?? []];
    }

    /** @return array{0: array, 1: array, 2: array<int, array>, 3: array<int, array>, 4: string} */
    private function loadInvoice(int $invoiceId): array
    {
        (new InvoiceService())->refreshStatus($invoiceId);
        $invoice = app()->db()->fetch(
            'SELECT invoices.*, clients.name AS client_name, clients.contact_name AS client_contact_name, clients.email AS client_email,
                    clients.phone AS client_phone, clients.website AS client_website, clients.billing_address, clients.shipping_address,
                    clients.tax_number AS client_tax_number
             FROM invoices INNER JOIN clients ON clients.id = invoices.client_id WHERE invoices.id = ?',
            [$invoiceId]
        );
        $items = app()->db()->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order', [$invoiceId]);
        $payments = app()->db()->fetchAll('SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date, id', [$invoiceId]);
        $business = (new SettingsService())->business();

        return [$business, $invoice, $items, $payments, (string) ($business['default_currency'] ?? '')];
    }

    /** @return array{0: array, 1: array, 2: array<int, array>, 3: string} */
    private function loadQuote(int $quoteId): array
    {
        $quote = app()->db()->fetch(
            'SELECT quotes.*, clients.name AS client_name, clients.contact_name AS client_contact_name, clients.email AS client_email,
                    clients.phone AS client_phone, clients.website AS client_website, clients.billing_address, clients.shipping_address,
                    clients.tax_number AS client_tax_number
             FROM quotes INNER JOIN clients ON clients.id = quotes.client_id WHERE quotes.id = ?',
            [$quoteId]
        );
        $items = app()->db()->fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order', [$quoteId]);
        $business = (new SettingsService())->business();

        return [$business, $quote, $items, (string) ($business['default_currency'] ?? '')];
    }

    /** @param array<int, array<string, mixed>> $items @param array<int, array<string, mixed>> $payments */
    private function renderHtml(
        array $business,
        array $document,
        array $items,
        array $payments,
        string $numberKey,
        bool $isInvoice,
        string $defaultCurrency,
        bool $forPdf
    ): string {
        // View::render() echoes immediately when given an empty layout (by design, for direct route output),
        // so the call is buffered here and only the returned string is kept - this is reused both for HTML
        // preview (which echoes once, deliberately) and for PDF generation (which must not echo at all).
        ob_start();
        $html = View::render('pdf/document', [
            'business' => $business,
            'document' => $document,
            'items' => $items,
            'payments' => $payments,
            'numberKey' => $numberKey,
            'isInvoice' => $isInvoice,
            'defaultCurrency' => $defaultCurrency,
            'forPdf' => $forPdf,
        ], '');
        ob_end_clean();

        return $html;
    }

    private function htmlToPdf(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setDefaultPaperSize('a4');
        // Dompdf's default chroot only allows reading files from its own vendor directory - the uploaded
        // logo lives under public/uploads, so the allowed root needs to be widened to the app root.
        $options->setChroot([ROOT_PATH]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    // --- Legacy zero-dependency PDF generator, used only when dompdf is not installed (composer install was not run). ---

    /** @param array<int, array<string, mixed>> $items @param array<int, array<string, mixed>> $payments */
    private function legacyRender(array $business, array $document, array $items, array $payments, string $numberKey, bool $isInvoice): string
    {
        $currency = (string) ($document['currency'] ?? 'USD');
        $taxLabel = $this->hasAmount($document['tax_total'] ?? 0) ? document_tax_label($items) : null;
        $showDiscount = $this->hasAmount($document['discount_total'] ?? 0);
        $isPaid = $isInvoice && ($document['status'] ?? '') === 'paid';
        $brandRgb = $this->hexToUnitRgb((string) ($business['brand_color'] ?? '#0ea394'));

        $logo = $this->loadLogo((string) ($business['logo_path'] ?? ''));

        $ops = [];
        $ops[] = $this->rect(0, 0, self::PAGE_WIDTH, 8, $brandRgb);

        $y = 40.0;
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;

        $headerTextX = $left;
        if ($logo !== null) {
            $boxSize = 56.0;
            $scale = min($boxSize / $logo['width'], $boxSize / $logo['height']);
            $drawW = $logo['width'] * $scale;
            $drawH = $logo['height'] * $scale;
            $ops[] = $this->image($left, self::PAGE_HEIGHT - $y - $drawH, $drawW, $drawH);
            $headerTextX = $left + $boxSize + 14;
        }

        $businessName = (string) ($business['business_name'] ?? 'Business');
        $ops[] = $this->text($headerTextX, $y + 14, 'F2', 15, $businessName, [0.07, 0.09, 0.15]);
        $addressLine = trim(($business['address_line1'] ?? '') . ' ' . ($business['city'] ?? '') . ' ' . ($business['country'] ?? ''));
        if ($addressLine !== '') {
            $ops[] = $this->text($headerTextX, $y + 30, 'F1', 9, $addressLine, [0.39, 0.45, 0.53]);
        }
        if (trim((string) ($business['tax_number'] ?? '')) !== '') {
            $ops[] = $this->text($headerTextX, $y + 43, 'F1', 9, 'Tax/VAT: ' . (string) $business['tax_number'], [0.39, 0.45, 0.53]);
        }
        $contactLine = trim((string) ($business['email'] ?? '') . ($business['phone'] ? ' · ' . $business['phone'] : ''));
        if ($contactLine !== '') {
            $ops[] = $this->text($headerTextX, $y + (trim((string) ($business['tax_number'] ?? '')) !== '' ? 56 : 43), 'F1', 9, $contactLine, [0.39, 0.45, 0.53]);
        }

        $docLabel = $isInvoice ? 'INVOICE' : 'QUOTE';
        $ops[] = $this->text($right - 160, $y, 'F2', 18, $docLabel, $brandRgb, true);
        $ops[] = $this->text($right - 160, $y + 20, 'F2', 11, '#' . (string) $document[$numberKey], [0.07, 0.09, 0.15], true);

        $metaY = $y + 38;
        $ops[] = $this->text($right - 160, $metaY, 'F1', 9, 'Issue date: ' . (string) ($document['issue_date'] ?? ''), [0.39, 0.45, 0.53], true);
        $metaY += 13;
        if ($isInvoice) {
            $ops[] = $this->text($right - 160, $metaY, 'F1', 9, 'Due date: ' . (string) ($document['due_date'] ?? ''), [0.39, 0.45, 0.53], true);
        } else {
            $ops[] = $this->text($right - 160, $metaY, 'F1', 9, 'Valid until: ' . (string) ($document['valid_until'] ?? ''), [0.39, 0.45, 0.53], true);
        }
        if ($isInvoice && $isPaid && !empty($document['paid_at'])) {
            $metaY += 13;
            $ops[] = $this->text($right - 160, $metaY, 'F2', 9, 'Paid on: ' . date('M j, Y', strtotime((string) $document['paid_at'])), [0.05, 0.45, 0.35], true);
        }

        $y = 110.0;
        $ops[] = $this->line($left, $y, $right, $y, [0.85, 0.88, 0.92], 0.75);

        $y += 22;
        $ops[] = $this->text($left, $y, 'F2', 9, 'BILL TO', [0.39, 0.45, 0.53]);
        $y += 16;
        $ops[] = $this->text($left, $y, 'F2', 11, (string) ($document['client_name'] ?? ''), [0.07, 0.09, 0.15]);
        foreach (['client_contact_name', 'client_email', 'client_phone', 'client_website'] as $field) {
            if (!empty($document[$field])) {
                $y += 14;
                $ops[] = $this->text($left, $y, 'F1', 9, (string) $document[$field], [0.39, 0.45, 0.53]);
            }
        }
        if (!empty($document['client_tax_number'])) {
            $y += 14;
            $ops[] = $this->text($left, $y, 'F1', 9, 'Tax/VAT: ' . (string) $document['client_tax_number'], [0.39, 0.45, 0.53]);
        }
        foreach ($this->wrapLines((string) ($document['billing_address'] ?? ''), 70) as $line) {
            $y += 13;
            $ops[] = $this->text($left, $y, 'F1', 9, $line, [0.39, 0.45, 0.53]);
        }
        foreach ($this->wrapLines((string) ($document['shipping_address'] ?? ''), 70) as $index => $line) {
            $y += 13;
            $ops[] = $this->text($left, $y, 'F1', 9, ($index === 0 ? 'Ship to: ' : '') . $line, [0.39, 0.45, 0.53]);
        }

        $y += 26;
        [$tableOps, $y] = $this->itemsTable($y, $items, $currency, $taxLabel, $brandRgb);
        $ops = array_merge($ops, $tableOps);

        $y += 14;
        [$totalsOps, $y] = $this->totalsBox($y, $document, $currency, $showDiscount, $taxLabel, $isInvoice);
        $ops = array_merge($ops, $totalsOps);

        if ($isInvoice && $payments !== []) {
            $y += 26;
            [$paymentOps, $y] = $this->paymentsTable($y, $payments, $currency);
            $ops = array_merge($ops, $paymentOps);
        }

        $notes = trim((string) ($document['notes'] ?? ''));
        if ($notes !== '') {
            $y += 24;
            $ops[] = $this->text($left, $y, 'F2', 9, 'NOTES', [0.39, 0.45, 0.53]);
            foreach ($this->wrapLines($notes, 95) as $line) {
                $y += 14;
                $ops[] = $this->text($left, $y, 'F1', 9, $line, [0.21, 0.24, 0.30]);
            }
        }

        $terms = trim((string) ($document['terms'] ?? ''));
        if ($terms !== '') {
            $y += 24;
            $ops[] = $this->text($left, $y, 'F2', 9, 'TERMS', [0.39, 0.45, 0.53]);
            foreach ($this->wrapLines($terms, 95) as $line) {
                $y += 14;
                $ops[] = $this->text($left, $y, 'F1', 9, $line, [0.21, 0.24, 0.30]);
            }
        }

        $footerTextY = self::PAGE_HEIGHT - 36;
        $ops[] = $this->line($left, $footerTextY - 12, $right, $footerTextY - 12, [0.85, 0.88, 0.92], 0.75);
        $ops[] = $this->text($left, $footerTextY, 'F1', 8, 'Thank you for your business.', [0.55, 0.59, 0.65]);

        if ($isPaid) {
            $ops[] = $this->paidStamp();
        }

        return $this->buildPdf(implode("\n", $ops), $logo);
    }

    private function legacyRenderExpense(array $business, array $expense): string
    {
        $currency = (string) ($expense['currency'] ?? 'USD');
        $brandRgb = $this->hexToUnitRgb((string) ($business['brand_color'] ?? '#0ea394'));
        $logo = $this->loadLogo((string) ($business['logo_path'] ?? ''));

        $ops = [];
        $ops[] = $this->rect(0, 0, self::PAGE_WIDTH, 8, $brandRgb);

        $y = 40.0;
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;

        $headerTextX = $left;
        if ($logo !== null) {
            $boxSize = 56.0;
            $scale = min($boxSize / $logo['width'], $boxSize / $logo['height']);
            $drawW = $logo['width'] * $scale;
            $drawH = $logo['height'] * $scale;
            $ops[] = $this->image($left, self::PAGE_HEIGHT - $y - $drawH, $drawW, $drawH);
            $headerTextX = $left + $boxSize + 14;
        }

        $businessName = (string) ($business['business_name'] ?? 'Business');
        $ops[] = $this->text($headerTextX, $y + 14, 'F2', 15, $businessName, [0.07, 0.09, 0.15]);
        $addressLine = trim(($business['address_line1'] ?? '') . ' ' . ($business['city'] ?? '') . ' ' . ($business['country'] ?? ''));
        if ($addressLine !== '') {
            $ops[] = $this->text($headerTextX, $y + 30, 'F1', 9, $addressLine, [0.39, 0.45, 0.53]);
        }
        if (trim((string) ($business['tax_number'] ?? '')) !== '') {
            $ops[] = $this->text($headerTextX, $y + 43, 'F1', 9, 'Tax/VAT: ' . (string) $business['tax_number'], [0.39, 0.45, 0.53]);
        }

        $ops[] = $this->text($right - 160, $y, 'F2', 18, 'EXPENSE RECEIPT', $brandRgb, true);
        $ops[] = $this->text($right - 160, $y + 20, 'F1', 9, 'Date: ' . (string) ($expense['expense_date'] ?? ''), [0.39, 0.45, 0.53], true);

        $y = 110.0;
        $ops[] = $this->line($left, $y, $right, $y, [0.85, 0.88, 0.92], 0.75);

        $y += 26;
        $rows = [
            ['Vendor', (string) ($expense['vendor_name'] ?? $expense['vendor'] ?? '')],
            ['Vendor contact', (string) ($expense['vendor_contact_name'] ?? '')],
            ['Vendor email', (string) ($expense['vendor_email'] ?? '')],
            ['Vendor phone', (string) ($expense['vendor_phone'] ?? '')],
            ['Vendor tax/VAT', (string) ($expense['vendor_tax_number'] ?? '')],
            ['Vendor address', (string) ($expense['vendor_billing_address'] ?? '')],
            ['Category', (string) ($expense['category'] ?? '')],
            ['Payment method', (string) ($expense['payment_method'] ?? '')],
            ['Amount', money($expense['amount'] ?? 0, $currency)],
        ];
        if ($this->hasAmount($expense['tax_amount'] ?? 0)) {
            $rows[] = ['Tax', money($expense['tax_amount'], $currency)];
        }
        $total = (float) ($expense['amount'] ?? 0) + (float) ($expense['tax_amount'] ?? 0);

        foreach ($rows as [$label, $value]) {
            if (trim($value) === '') {
                continue;
            }
            $ops[] = $this->text($left, $y, 'F2', 9, strtoupper($label), [0.39, 0.45, 0.53]);
            $ops[] = $this->text($right, $y, 'F1', 10, $value, [0.13, 0.15, 0.20], true);
            $y += 20;
        }

        $y += 6;
        $ops[] = $this->line($left, $y, $right, $y, [0.85, 0.88, 0.92], 0.75);
        $y += 20;
        $ops[] = $this->text($left, $y, 'F2', 12, 'TOTAL', [0.07, 0.09, 0.15]);
        $ops[] = $this->text($right, $y, 'F2', 12, money($total, $currency), [0.07, 0.09, 0.15], true);

        $notes = trim((string) ($expense['notes'] ?? ''));
        if ($notes !== '') {
            $y += 30;
            $ops[] = $this->text($left, $y, 'F2', 9, 'NOTES', [0.39, 0.45, 0.53]);
            foreach ($this->wrapLines($notes, 95) as $line) {
                $y += 14;
                $ops[] = $this->text($left, $y, 'F1', 9, $line, [0.21, 0.24, 0.30]);
            }
        }

        $footerTextY = self::PAGE_HEIGHT - 36;
        $ops[] = $this->line($left, $footerTextY - 12, $right, $footerTextY - 12, [0.85, 0.88, 0.92], 0.75);
        $ops[] = $this->text($left, $footerTextY, 'F1', 8, 'Recorded for bookkeeping purposes.', [0.55, 0.59, 0.65]);

        return $this->buildPdf(implode("\n", $ops), $logo);
    }

    /** @return array{0: array<int, string>, 1: float} */
    private function itemsTable(float $y, array $items, string $currency, ?string $taxLabel, array $brandRgb): array
    {
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;
        $descX = $left + 6;
        $qtyX = $left + 270;
        $priceX = $left + 320;
        $taxX = $left + 390;
        $totalX = $right - 6;
        $taxHeadingLines = $taxLabel !== null ? $this->wrapLines($taxLabel, 12) : [];
        $headerHeight = max(22, 12 + 10 * count($taxHeadingLines));

        $ops = [];
        $ops[] = $this->rect($left, $y, $right - $left, $headerHeight, [0.95, 0.97, 0.98]);
        $headerY = $y + 15;
        $ops[] = $this->text($descX, $headerY, 'F2', 8, 'DESCRIPTION', [0.39, 0.45, 0.53]);
        $ops[] = $this->text($qtyX, $headerY, 'F2', 8, 'QTY', [0.39, 0.45, 0.53]);
        $ops[] = $this->text($priceX, $headerY, 'F2', 8, 'UNIT PRICE', [0.39, 0.45, 0.53]);
        foreach ($taxHeadingLines as $index => $headingLine) {
            $ops[] = $this->text($taxX, $headerY + $index * 10, 'F2', 8, $headingLine, [0.39, 0.45, 0.53]);
        }
        $ops[] = $this->text($totalX, $headerY, 'F2', 8, 'TOTAL', [0.39, 0.45, 0.53], true);

        $y += $headerHeight;
        foreach ($items as $item) {
            $descLines = $this->wrapLines((string) $item['description'], 46);
            $rowHeight = max(20, 13 * count($descLines) + 7);
            $rowTop = $y;

            foreach ($descLines as $index => $line) {
                $ops[] = $this->text($descX, $rowTop + 14 + ($index * 13), 'F1', 9, $line, [0.13, 0.15, 0.20]);
            }
            $ops[] = $this->text($qtyX, $rowTop + 14, 'F1', 9, (string) $this->trimNumber((float) $item['quantity']), [0.13, 0.15, 0.20]);
            $ops[] = $this->text($priceX, $rowTop + 14, 'F1', 9, money($item['unit_price'], $currency), [0.13, 0.15, 0.20]);
            if ($taxLabel !== null) {
                $ops[] = $this->text($taxX, $rowTop + 14, 'F1', 9, percentage($item['tax_rate']), [0.13, 0.15, 0.20]);
            }
            $ops[] = $this->text($totalX, $rowTop + 14, 'F2', 9, money($item['line_total'], $currency), [0.13, 0.15, 0.20], true);

            $y += $rowHeight;
            $ops[] = $this->line($left, $y, $right, $y, [0.91, 0.93, 0.95], 0.5);
        }

        return [$ops, $y];
    }

    /** @return array{0: array<int, string>, 1: float} */
    private function totalsBox(float $y, array $document, string $currency, bool $showDiscount, ?string $taxLabel, bool $isInvoice): array
    {
        $right = self::PAGE_WIDTH - self::MARGIN;
        $labelX = $right - 180;
        $valueX = $right - 6;
        $ops = [];

        $rows = [['Subtotal', money($document['subtotal'], $currency), false]];
        if ($showDiscount) {
            $rows[] = ['Discount', '-' . money($document['discount_total'], $currency), false];
        }
        if ($taxLabel !== null) {
            $rows[] = [$taxLabel, money($document['tax_total'], $currency), false];
        }
        if ($isInvoice) {
            $rows[] = ['Paid', money(-(float) ($document['amount_paid'] ?? 0), $currency), false];
            $rows[] = ['Balance due', money($document['balance_due'] ?? $document['total'], $currency), true];
        } else {
            $rows[] = ['Total', money($document['total'], $currency), true];
        }

        foreach ($rows as [$label, $value, $emphasis]) {
            $y += 17;
            if ($emphasis) {
                $ops[] = $this->line($labelX - 10, $y - 13, $right, $y - 13, [0.85, 0.88, 0.92], 0.75);
            }
            $labelLines = $this->wrapLines($label, 18);
            foreach ($labelLines as $index => $labelLine) {
                $ops[] = $this->text($labelX, $y + $index * 12, $emphasis ? 'F2' : 'F1', $emphasis ? 11 : 9, $labelLine, [0.21, 0.24, 0.30]);
            }
            $ops[] = $this->text($valueX, $y, $emphasis ? 'F2' : 'F1', $emphasis ? 11 : 9, $value, [0.07, 0.09, 0.15], true);
            $y += (count($labelLines) - 1) * 12;
        }

        return [$ops, $y];
    }

    /** @return array{0: array<int, string>, 1: float} */
    private function paymentsTable(float $y, array $payments, string $currency): array
    {
        $left = self::MARGIN;
        $right = self::PAGE_WIDTH - self::MARGIN;
        $ops = [];

        $ops[] = $this->text($left, $y, 'F2', 9, 'PAYMENT HISTORY', [0.39, 0.45, 0.53]);
        $y += 16;
        $ops[] = $this->rect($left, $y, $right - $left, 20, [0.95, 0.97, 0.98]);
        $headerY = $y + 13;
        $dateX = $left + 6;
        $methodX = $left + 110;
        $refX = $left + 230;
        $amountX = $right - 6;
        $ops[] = $this->text($dateX, $headerY, 'F2', 8, 'DATE', [0.39, 0.45, 0.53]);
        $ops[] = $this->text($methodX, $headerY, 'F2', 8, 'METHOD', [0.39, 0.45, 0.53]);
        $ops[] = $this->text($refX, $headerY, 'F2', 8, 'REFERENCE', [0.39, 0.45, 0.53]);
        $ops[] = $this->text($amountX, $headerY, 'F2', 8, 'AMOUNT', [0.39, 0.45, 0.53], true);

        $y += 20;
        foreach ($payments as $payment) {
            $isRefund = ($payment['type'] ?? 'payment') === 'refund';
            $rowY = $y + 13;
            $ops[] = $this->text($dateX, $rowY, 'F1', 9, (string) $payment['payment_date'], [0.13, 0.15, 0.20]);
            $ops[] = $this->text($methodX, $rowY, 'F1', 9, (string) $payment['method'] . ($isRefund ? ' (refund)' : ''), [0.13, 0.15, 0.20]);
            $ops[] = $this->text($refX, $rowY, 'F1', 9, (string) ($payment['reference'] ?? ''), [0.13, 0.15, 0.20]);
            $amountLabel = ($isRefund ? '-' : '') . money($payment['amount'], $currency);
            $ops[] = $this->text($amountX, $rowY, 'F2', 9, $amountLabel, $isRefund ? [0.7, 0.15, 0.15] : [0.05, 0.45, 0.35], true);
            $y += 18;
            $ops[] = $this->line($left, $y, $right, $y, [0.93, 0.95, 0.96], 0.5);
        }

        return [$ops, $y];
    }

    private function paidStamp(): string
    {
        $angle = 22.0 * M_PI / 180;
        $cos = cos($angle);
        $sin = sin($angle);
        $centerX = self::PAGE_WIDTH / 2 + 10;
        $centerY = self::PAGE_HEIGHT / 2 + 40;

        $matrix = sprintf('%.4F %.4F %.4F %.4F %.2F %.2F', $cos, $sin, -$sin, $cos, $centerX, $centerY);

        return "q\n/GS1 gs\n0.04 0.56 0.36 RG\n3 w\n{$matrix} cm\n-130 -34 260 68 re S\n0.04 0.56 0.36 rg\nBT\n/F2 46 Tf\n-104 -16 Td\n(PAID) Tj\nET\nQ";
    }

    private function text(float $x, float $y, string $font, float $size, string $text, array $rgb = [0, 0, 0], bool $rightAlign = false): string
    {
        $pdfY = self::PAGE_HEIGHT - $y;
        $renderedText = $this->pdfText($text);
        $width = $rightAlign ? $this->estimateWidth($text, $size) : 0.0;
        $startX = $x - $width;

        $color = sprintf('%.3F %.3F %.3F rg', $rgb[0], $rgb[1], $rgb[2]);

        return "BT\n{$color}\n/{$font} " . $this->num($size) . " Tf\n" . $this->num($startX) . ' ' . $this->num($pdfY) . " Td\n({$renderedText}) Tj\nET";
    }

    private function rect(float $x, float $topY, float $width, float $height, array $rgb): string
    {
        $pdfY = self::PAGE_HEIGHT - $topY - $height;
        $color = sprintf('%.3F %.3F %.3F rg', $rgb[0], $rgb[1], $rgb[2]);

        return "{$color}\n" . $this->num($x) . ' ' . $this->num($pdfY) . ' ' . $this->num($width) . ' ' . $this->num($height) . ' re f';
    }

    /** $y is distance from the top of the page, matching text() and rect() - not raw PDF (bottom-up) coordinates. */
    private function line(float $x1, float $y, float $x2, float $y2, array $rgb, float $width): string
    {
        $pdfY1 = self::PAGE_HEIGHT - $y;
        $pdfY2 = self::PAGE_HEIGHT - $y2;
        $color = sprintf('%.3F %.3F %.3F RG', $rgb[0], $rgb[1], $rgb[2]);

        return "{$color}\n" . $this->num($width) . " w\n"
            . $this->num($x1) . ' ' . $this->num($pdfY1) . ' m '
            . $this->num($x2) . ' ' . $this->num($pdfY2) . " l S";
    }

    private function image(float $x, float $pdfY, float $width, float $height): string
    {
        return "q\n" . $this->num($width) . ' 0 0 ' . $this->num($height) . ' ' . $this->num($x) . ' ' . $this->num($pdfY) . " cm\n/Im1 Do\nQ";
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(sprintf('%.2F', $value), '0'), '.') ?: '0';
    }

    /** Average-width text estimate (no real font metrics available without an external library). */
    private function estimateWidth(string $text, float $size): float
    {
        return mb_strlen($text) * $size * 0.52;
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.') ?: '0';
    }

    /** @return array<int, string> */
    private function wrapLines(string $text, int $maxChars): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $paragraph = preg_replace('/(\S{' . $maxChars . '})(?=\S)/u', '$1 ', $paragraph) ?? $paragraph;
            $line = '';
            foreach (explode(' ', $paragraph) as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if (mb_strlen($candidate) > $maxChars && $line !== '') {
                    $out[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function hexToUnitRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '0ea394';
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /** Loads a logo via GD (re-encoded as JPEG for embedding) and degrades gracefully if GD or the file is unavailable. */
    private function loadLogo(string $logoPath): ?array
    {
        if ($logoPath === '' || !extension_loaded('gd')) {
            return null;
        }

        $absolute = PUBLIC_PATH . '/uploads/' . $logoPath;
        if (!is_file($absolute)) {
            return null;
        }

        $info = @getimagesize($absolute);
        if (!$info) {
            return null;
        }

        $source = match ($info['mime'] ?? '') {
            'image/jpeg' => @imagecreatefromjpeg($absolute),
            'image/png' => @imagecreatefrompng($absolute),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            default => false,
        };
        if (!$source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $flattened = imagecreatetruecolor($width, $height);
        imagefill($flattened, 0, 0, (int) imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($flattened, null, 85);
        $data = (string) ob_get_clean();
        imagedestroy($flattened);

        if ($data === '') {
            return null;
        }

        return ['data' => $data, 'width' => $width, 'height' => $height];
    }

    private function buildPdf(string $content, ?array $image): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';

        $resources = '/Font << /F1 4 0 R /F2 5 0 R >> /ExtGState << /GS1 6 0 R >>';
        if ($image !== null) {
            $resources .= ' /XObject << /Im1 7 0 R >>';
        }

        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << ' . $resources . ' >> /Contents 8 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Type /ExtGState /ca 0.14 >>';

        if ($image !== null) {
            $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width'] . ' /Height ' . $image['height']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "\nendstream";
        } else {
            $objects[] = '<< /Type /XObject /Subtype /Form /BBox [0 0 0 0] /Length 0 >>' . "\nstream\n\nendstream";
        }

        $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function hasAmount(mixed $amount): bool
    {
        return abs((float) $amount) > 0.00001;
    }
}

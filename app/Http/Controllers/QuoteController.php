<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\SignedOption;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceService;
use App\Services\MailerService;
use App\Services\NumberGenerator;
use App\Services\PdfService;
use App\Services\SettingsService;
use App\Support\ReferenceData;

final class QuoteController extends Controller
{
    public function index(): string
    {
        $quotes = app()->db()->fetchAll(
            'SELECT quotes.*, clients.name AS client_name
             FROM quotes INNER JOIN clients ON clients.id = quotes.client_id
             ORDER BY quotes.issue_date DESC, quotes.id DESC LIMIT 200'
        );

        return $this->view('quotes/index', ['title' => 'Quotes', 'quotes' => $quotes]);
    }

    public function create(): string
    {
        return $this->view('quotes/form', [
            'title' => 'New Quote',
            'mode' => 'create',
            'clients' => app()->db()->fetchAll('SELECT id, name, currency FROM clients WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes WHERE is_active = 1 ORDER BY name'),
            'business' => (new SettingsService())->business(),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('client_id', 'Client')
            ->required('issue_date', 'Issue date')
            ->date('issue_date', 'Issue date')
            ->date('valid_until', 'Valid until')
            ->required('currency', 'Currency')
            ->in('status', ['draft', 'sent'], 'Status')
            ->integer('client_id', 'Client');

        $calculated = (new InvoiceCalculator())->fromRequest($data);
        if ($calculated['errors'] !== [] || $calculated['items'] === []) {
            $errors = $validator->errors();
            $errors['items'] = $calculated['errors'] !== []
                ? implode(' ', $calculated['errors'])
                : 'At least one line item is required.';
            $this->backWithErrors($errors, $data);
        }

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $quoteId = app()->db()->transaction(function () use ($data, $calculated): int {
            $quoteId = app()->db()->insert(
                'INSERT INTO quotes
                 (client_id, quote_number, status, issue_date, valid_until, currency, subtotal, discount_total, tax_total, total, notes, terms, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $data['client_id'],
                    (new NumberGenerator())->nextQuoteNumber(),
                    $data['status'] ?? 'draft',
                    $data['issue_date'],
                    $data['valid_until'] ?: null,
                    $data['currency'],
                    $calculated['subtotal'],
                    $calculated['discount_total'],
                    $calculated['tax_total'],
                    $calculated['total'],
                    $data['notes'] ?? null,
                    $data['terms'] ?? null,
                    Auth::id(),
                ]
            );

            foreach ($calculated['items'] as $item) {
                app()->db()->execute(
                    'INSERT INTO quote_items (quote_id, description, quantity, unit_price, discount_rate, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$quoteId, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_rate'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }

            return $quoteId;
        });

        AuditLogger::log('quote.created', 'quote', $quoteId);
        Session::flash('success', 'Quote created.');
        $this->redirect('/quotes/' . $quoteId);
    }

    public function show(Request $request, string $id): string
    {
        $quote = app()->db()->fetch(
            'SELECT quotes.*, clients.name AS client_name, clients.email AS client_email, clients.billing_address
             FROM quotes INNER JOIN clients ON clients.id = quotes.client_id WHERE quotes.id = ?',
            [(int) $id]
        );

        if (!$quote) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Quote not found']);
        }

        return $this->view('quotes/show', [
            'title' => $quote['quote_number'],
            'quote' => $quote,
            'items' => app()->db()->fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order', [(int) $id]),
            'business' => (new SettingsService())->business(),
        ]);
    }

    private function findDraftOrRedirect(string $id): array
    {
        $quote = app()->db()->fetch('SELECT * FROM quotes WHERE id = ?', [(int) $id]);
        if (!$quote) {
            Session::flash('errors', ['Quote not found.']);
            $this->redirect('/quotes');
        }
        if ($quote['status'] !== 'draft') {
            Session::flash('errors', ['Only draft quotes can be edited or deleted.']);
            $this->redirect('/quotes/' . $id);
        }

        return $quote;
    }

    public function edit(Request $request, string $id): string
    {
        $quote = $this->findDraftOrRedirect($id);

        return $this->view('quotes/form', [
            'title' => 'Edit ' . $quote['quote_number'],
            'mode' => 'edit',
            'quote' => $quote,
            'items' => app()->db()->fetchAll('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order', [(int) $id]),
            'clients' => app()->db()->fetchAll('SELECT id, name, currency FROM clients WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes WHERE is_active = 1 ORDER BY name'),
            'business' => (new SettingsService())->business(),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->findDraftOrRedirect($id);
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('client_id', 'Client')
            ->integer('client_id', 'Client')
            ->required('issue_date', 'Issue date')
            ->date('issue_date', 'Issue date')
            ->date('valid_until', 'Valid until')
            ->required('currency', 'Currency')
            ->in('status', ['draft', 'sent'], 'Status');

        $calculated = (new InvoiceCalculator())->fromRequest($data);
        $errors = $validator->errors();
        if ($calculated['errors'] !== [] || $calculated['items'] === []) {
            $errors['items'] = $calculated['errors'] !== []
                ? implode(' ', $calculated['errors'])
                : 'At least one line item is required.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        app()->db()->transaction(function () use ($id, $data, $calculated): void {
            app()->db()->execute(
                'UPDATE quotes
                 SET client_id = ?, status = ?, issue_date = ?, valid_until = ?, currency = ?, subtotal = ?, discount_total = ?,
                     tax_total = ?, total = ?, notes = ?, terms = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    (int) $data['client_id'],
                    $data['status'] ?? 'draft',
                    $data['issue_date'],
                    $data['valid_until'] ?: null,
                    $data['currency'],
                    $calculated['subtotal'],
                    $calculated['discount_total'],
                    $calculated['tax_total'],
                    $calculated['total'],
                    $data['notes'] ?? null,
                    $data['terms'] ?? null,
                    (int) $id,
                ]
            );

            app()->db()->execute('DELETE FROM quote_items WHERE quote_id = ?', [(int) $id]);
            foreach ($calculated['items'] as $item) {
                app()->db()->execute(
                    'INSERT INTO quote_items (quote_id, description, quantity, unit_price, discount_rate, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $id, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_rate'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }
        });

        AuditLogger::log('quote.updated', 'quote', (int) $id);
        Session::flash('success', 'Quote updated.');
        $this->redirect('/quotes/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->findDraftOrRedirect($id);
        app()->db()->execute('DELETE FROM quotes WHERE id = ?', [(int) $id]);
        AuditLogger::log('quote.deleted', 'quote', (int) $id);
        Session::flash('success', 'Draft quote deleted.');
        $this->redirect('/quotes');
    }

    public function pdf(Request $request, string $id): never
    {
        if ((string) $request->input('preview', '') !== '') {
            header('Content-Type: text/html; charset=utf-8');
            echo (new PdfService())->quoteHtml((int) $id);
            exit;
        }

        $quote = app()->db()->fetch('SELECT quote_number FROM quotes WHERE id = ?', [(int) $id]);
        $pdf = (new PdfService())->quotePdf((int) $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ($quote['quote_number'] ?? 'quote') . '.pdf"');
        echo $pdf;
        exit;
    }

    public function send(Request $request, string $id): never
    {
        $quote = app()->db()->fetch(
            'SELECT quotes.*, clients.name AS client_name, clients.email AS client_email
             FROM quotes INNER JOIN clients ON clients.id = quotes.client_id WHERE quotes.id = ?',
            [(int) $id]
        );
        if (!$quote) {
            Session::flash('errors', ['Quote not found.']);
            $this->redirect('/quotes');
        }

        if (!$quote['client_email']) {
            Session::flash('errors', ['No email on file for this client. Quote not sent.']);
            $this->redirect('/quotes/' . $id);
        }

        [$subject, $body] = (new MailerService())->template('quote_send', [
            'quote_number' => $quote['quote_number'],
            'quote_total' => money($quote['total'], $quote['currency']),
            'client_name' => $quote['client_name'],
            'business_name' => (new SettingsService())->business()['business_name'] ?? config('app.name'),
        ]);
        $mailer = new MailerService();
        $sent = $mailer->send($quote['client_email'], $subject, $body, [[
            'name' => $quote['quote_number'] . '.pdf',
            'mime' => 'application/pdf',
            'content' => (new PdfService())->quotePdf((int) $id),
        ]]);

        if ($sent) {
            app()->db()->execute("UPDATE quotes SET status = IF(status = 'draft', 'sent', status), updated_at = NOW() WHERE id = ?", [(int) $id]);
            AuditLogger::log('quote.sent', 'quote', (int) $id);
            Session::flash('success', 'Quote emailed to ' . $quote['client_email'] . '.');
        } else {
            Session::flash('errors', ['Quote email not sent: ' . ($mailer->lastError() ?? 'the mail transport rejected the message.')]);
        }

        $this->redirect('/quotes/' . $id);
    }

    public function convert(Request $request, string $id): never
    {
        $invoiceId = (new InvoiceService())->convertQuote((int) $id);
        Session::flash('success', 'Quote converted to invoice.');
        $this->redirect('/invoices/' . $invoiceId);
    }
}

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
use App\Support\Paginator;
use App\Support\ReferenceData;
use App\Support\TableSort;

final class InvoiceController extends Controller
{
    public function index(Request $request): string
    {
        $status = (string) $request->input('status', '');
        $page = Paginator::page($request->input('page', 1));
        $sorting = TableSort::resolve($request->input('sort'), $request->input('direction'), [
            'invoice_number' => ['column' => 'invoices.invoice_number', 'default' => 'asc'],
            'client' => ['column' => 'clients.name', 'default' => 'asc'],
            'status' => ['column' => 'invoices.status', 'default' => 'asc'],
            'issue_date' => ['column' => 'invoices.issue_date', 'default' => 'desc'],
            'Pay_date' => ['column' => 'latest_payment.payment_date', 'default' => 'desc'],
            'amount_paid' => ['column' => 'invoices.amount_paid', 'default' => 'desc'],
            'balance_due' => ['column' => 'invoices.balance_due', 'default' => 'desc'],
        ], 'issue_date');
        $params = [];
        $where = '1 = 1';
        if ($status !== '') {
            $where .= ' AND invoices.status = ?';
            $params[] = $status;
        }

        $total = (int) (app()->db()->fetch("SELECT COUNT(*) AS count FROM invoices WHERE {$where}", $params)['count'] ?? 0);
        
        $invoices = app()->db()->fetchAll(
            "SELECT invoices.*,
                    clients.name AS client_name,
                    latest_payment.payment_date AS payment_date
            FROM invoices
            INNER JOIN clients
                ON clients.id = invoices.client_id
            LEFT JOIN (
                SELECT invoice_id,
                        MAX(payment_date) AS payment_date
                FROM payments
                WHERE type IS NULL OR type <> 'refund'
                GROUP BY invoice_id
            ) AS latest_payment
                ON latest_payment.invoice_id = invoices.id
            WHERE {$where}
            ORDER BY {$sorting['column']} {$sorting['direction']},
                    invoices.id {$sorting['direction']}
            LIMIT " . Paginator::perPage() .
            ' OFFSET ' . Paginator::offset($page),
            $params
        );


        return $this->view('invoices/index', [
            'title' => 'Invoices',
            'invoices' => $invoices,
            'status' => $status,
            'sort' => $sorting['key'],
            'direction' => $sorting['direction'],
            'pagination' => Paginator::meta($total, $page),
        ]);
    }

    public function create(): string
    {
        $settings = new SettingsService();
        return $this->view('invoices/form', [
            'title' => 'New Invoice',
            'mode' => 'create',
            'clients' => app()->db()->fetchAll('SELECT id, name, currency FROM clients WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes ORDER BY is_active DESC, name'),
            'defaultTax' => $settings->defaultTax(),
            'business' => $settings->business(),
            'defaultTerms' => $settings->get('default_invoice_terms', 'Payment is due by the due date shown on this invoice.'),
        ]);
    }

    private function findDraftOrRedirect(string $id): array
    {
        $invoice = app()->db()->fetch('SELECT * FROM invoices WHERE id = ?', [(int) $id]);
        if (!$invoice) {
            Session::flash('errors', ['Invoice not found.']);
            $this->redirect('/invoices');
        }
        if ($invoice['status'] !== 'draft') {
            Session::flash('errors', ['Only draft invoices can be edited or deleted.']);
            $this->redirect('/invoices/' . $id);
        }

        return $invoice;
    }

    public function edit(Request $request, string $id): string
    {
        $invoice = $this->findDraftOrRedirect($id);
        $settings = new SettingsService();

        return $this->view('invoices/form', [
            'title' => 'Edit ' . $invoice['invoice_number'],
            'mode' => 'edit',
            'invoice' => $invoice,
            'items' => app()->db()->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order', [(int) $id]),
            'clients' => app()->db()->fetchAll('SELECT id, name, currency FROM clients WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes ORDER BY is_active DESC, name'),
            'defaultTax' => $settings->defaultTax(),
            'business' => $settings->business(),
            'defaultTerms' => $settings->get('default_invoice_terms', 'Payment is due by the due date shown on this invoice.'),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $invoice = $this->findDraftOrRedirect($id);
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('client_id', 'Client')
            ->integer('client_id', 'Client')
            ->required('issue_date', 'Issue date')
            ->date('issue_date', 'Issue date')
            ->required('due_date', 'Due date')
            ->date('due_date', 'Due date')
            ->required('currency', 'Currency')
            ->in('status', ['draft', 'sent'], 'Status');

        $errors = $validator->errors();
        $client = app()->db()->fetch('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [(int) ($data['client_id'] ?? 0)]);
        if (!isset($errors['client_id']) && !$client) {
            $errors['client_id'] = 'Selected client was not found.';
        }

        $calculated = (new InvoiceCalculator())->fromRequest($data);
        if ($calculated['errors'] !== []) {
            $errors['items'] = implode(' ', $calculated['errors']);
        } elseif ($calculated['items'] === []) {
            $errors['items'] = 'At least one line item is required.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        app()->db()->transaction(function () use ($id, $data, $calculated): void {
            app()->db()->execute(
                'UPDATE invoices
                 SET client_id = ?, status = ?, issue_date = ?, due_date = ?, currency = ?, subtotal = ?, discount_total = ?,
                     tax_total = ?, total = ?, balance_due = ?, notes = ?, terms = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    (int) $data['client_id'],
                    $data['status'] ?? 'draft',
                    $data['issue_date'],
                    $data['due_date'],
                    $data['currency'],
                    $calculated['subtotal'],
                    $calculated['discount_total'],
                    $calculated['tax_total'],
                    $calculated['total'],
                    $calculated['total'],
                    $data['notes'] ?? null,
                    $data['terms'] ?? null,
                    (int) $id,
                ]
            );

            app()->db()->execute('DELETE FROM invoice_items WHERE invoice_id = ?', [(int) $id]);
            foreach ($calculated['items'] as $item) {
                app()->db()->execute(
                    'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_type, discount_value, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $id, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_type'], $item['discount_value'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }
        });

        AuditLogger::log('invoice.updated', 'invoice', (int) $id);
        Session::flash('success', 'Invoice updated.');
        $this->redirect('/invoices/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->findDraftOrRedirect($id);
        app()->db()->execute('DELETE FROM invoices WHERE id = ?', [(int) $id]);
        AuditLogger::log('invoice.deleted', 'invoice', (int) $id);
        Session::flash('success', 'Draft invoice deleted.');
        $this->redirect('/invoices');
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';
        $clientSelection = trim((string) ($data['client_id'] ?? ''));
        $createClient = $clientSelection === '__new__';
        $clientId = null;

        $validator = (new Validator($data))
            ->required('issue_date', 'Issue date')
            ->date('issue_date', 'Issue date')
            ->required('due_date', 'Due date')
            ->date('due_date', 'Due date')
            ->required('currency', 'Currency')
            ->in('status', ['draft', 'sent'], 'Status');

        $errors = $validator->errors();

        if ($createClient) {
            $clientValidator = (new Validator($data))
                ->required('new_client_name', 'New client name')
                ->max('new_client_name', 190, 'New client name')
                ->email('new_client_email', 'New client email');
            $errors = array_merge($errors, $clientValidator->errors());
            $data['client_id'] = '__new__';
        } elseif ($clientSelection === '' || filter_var($clientSelection, FILTER_VALIDATE_INT) === false) {
            $errors['client_id'] = 'Choose an existing client or create a new client.';
        } else {
            $client = app()->db()->fetch('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [(int) $clientSelection]);
            if (!$client) {
                $errors['client_id'] = 'Selected client was not found.';
            } else {
                $clientId = (int) $client['id'];
            }
        }

        $calculated = (new InvoiceCalculator())->fromRequest($data);
        if ($calculated['errors'] !== []) {
            $errors['items'] = implode(' ', $calculated['errors']);
        } elseif ($calculated['items'] === []) {
            $errors['items'] = 'At least one line item is required.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        $result = app()->db()->transaction(function () use ($data, $calculated, $createClient, $clientId): array {
            $reusedExistingClient = false;
            if ($createClient) {
                $existing = ClientController::findDuplicate(
                    trim((string) $data['new_client_name']),
                    (string) ($data['new_client_email'] ?? '')
                );
                if ($existing !== null) {
                    $clientId = (int) $existing['id'];
                    $reusedExistingClient = true;
                } else {
                    $clientId = app()->db()->insert(
                        'INSERT INTO clients (type, name, email, billing_address, currency, data_processing_basis)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            'business',
                            trim((string) $data['new_client_name']),
                            trim((string) ($data['new_client_email'] ?? '')) ?: null,
                            trim((string) ($data['new_client_billing_address'] ?? '')) ?: null,
                            $data['currency'],
                            'contract',
                        ]
                    );
                }
            }

            $invoiceId = app()->db()->insert(
                'INSERT INTO invoices
                 (client_id, invoice_number, status, issue_date, due_date, currency, subtotal, discount_total, tax_total, total, balance_due, notes, terms, public_token, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $clientId,
                    (new NumberGenerator())->nextInvoiceNumber(),
                    $data['status'] ?? 'draft',
                    $data['issue_date'],
                    $data['due_date'],
                    $data['currency'],
                    $calculated['subtotal'],
                    $calculated['discount_total'],
                    $calculated['tax_total'],
                    $calculated['total'],
                    $calculated['total'],
                    $data['notes'] ?? null,
                    $data['terms'] ?? null,
                    hash('sha256', random_bytes(32)),
                    Auth::id(),
                ]
            );

            foreach ($calculated['items'] as $item) {
                app()->db()->execute(
                    'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_type, discount_value, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$invoiceId, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_type'], $item['discount_value'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }

            return ['invoice_id' => $invoiceId, 'client_id' => (int) $clientId, 'created_client' => $createClient && !$reusedExistingClient];
        });

        if ($result['created_client']) {
            AuditLogger::log('client.created_from_invoice', 'client', $result['client_id']);
        }
        $invoiceId = (int) $result['invoice_id'];
        AuditLogger::log('invoice.created', 'invoice', $invoiceId);
        Session::flash('success', 'Invoice created.');
        $this->redirect('/invoices/' . $invoiceId);
    }

    public function show(Request $request, string $id): string
    {
        (new InvoiceService())->refreshStatus((int) $id);
        $invoice = app()->db()->fetch(
            'SELECT invoices.*, clients.name AS client_name, clients.contact_name AS client_contact_name, clients.email AS client_email,
                    clients.phone AS client_phone, clients.website AS client_website, clients.billing_address, clients.shipping_address,
                    clients.tax_number AS client_tax_number
             FROM invoices INNER JOIN clients ON clients.id = invoices.client_id WHERE invoices.id = ?',
            [(int) $id]
        );

        if (!$invoice) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Invoice not found']);
        }

        return $this->view('invoices/show', [
            'title' => $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => app()->db()->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order', [(int) $id]),
            'payments' => app()->db()->fetchAll('SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC', [(int) $id]),
            'overpaid' => max(0, (float) $invoice['amount_paid'] - (float) $invoice['total']),
            'business' => (new SettingsService())->business(),
            'paymentMethods' => preg_split('/\r\n|\r|\n/', (string) (new SettingsService())->get('payment_methods', 'Bank transfer')),
        ]);
    }

    public function recordPayment(Request $request, string $id): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('amount', 'Amount')
            ->numeric('amount', 'Amount')
            ->required('payment_date', 'Payment date')
            ->required('method', 'Payment method');

        if ($validator->fails() || (float) ($data['amount'] ?? 0) <= 0) {
            $errors = $validator->errors();
            if ((float) ($data['amount'] ?? 0) <= 0) {
                $errors['amount'] = 'Payment amount must be greater than zero.';
            }
            $this->backWithErrors($errors, $data);
        }

        (new InvoiceService())->recordPayment((int) $id, $data);
        Session::flash('success', 'Payment recorded.');
        $this->redirect('/invoices/' . $id);
    }

    public function reminder(Request $request, string $id): never
    {
        (new InvoiceService())->refreshStatus((int) $id);
        $invoice = app()->db()->fetch(
            'SELECT invoices.*, clients.name AS client_name, clients.email AS client_email
             FROM invoices INNER JOIN clients ON clients.id = invoices.client_id WHERE invoices.id = ?',
            [(int) $id]
        );

        if (!$invoice) {
            Session::flash('errors', ['Invoice not found.']);
            $this->redirect('/invoices');
        }

        $sendable = !in_array((string) $invoice['status'], ['draft', 'paid', 'void'], true)
            && (float) ($invoice['balance_due'] ?? 0) > 0;
        if (!$sendable) {
            Session::flash('errors', ['Reminder not sent because this invoice is not currently unpaid and sendable.']);
            $this->redirect('/invoices/' . $id);
        }

        if (trim((string) ($invoice['client_email'] ?? '')) === '' || !filter_var($invoice['client_email'], FILTER_VALIDATE_EMAIL)) {
            AuditLogger::log('invoice.reminder_not_sent_no_email', 'invoice', (int) $id);
            Session::flash('errors', ['Reminder not sent: no client email found in database.']);
            $this->redirect('/invoices/' . $id);
        }

        [$subject, $body] = (new MailerService())->template('payment_reminder', [
            'invoice_number' => $invoice['invoice_number'],
            'invoice_total' => money($invoice['total'], $invoice['currency']),
            'balance_due' => money($invoice['balance_due'], $invoice['currency']),
            'due_date' => $invoice['due_date'],
            'client_name' => $invoice['client_name'],
            'business_name' => (new SettingsService())->business()['business_name'] ?? config('app.name'),
        ]);

        $mailer = new MailerService();
        $sent = $mailer->send($invoice['client_email'], $subject, $body, [[
            'name' => $invoice['invoice_number'] . '.pdf',
            'mime' => 'application/pdf',
            'content' => (new PdfService())->invoicePdf((int) $id),
        ]]);

        if ($sent) {
            app()->db()->execute('UPDATE invoices SET last_reminder_sent_at = NOW(), updated_at = NOW() WHERE id = ?', [(int) $id]);
            AuditLogger::log('invoice.reminder_sent', 'invoice', (int) $id);
            Session::flash('success', 'Reminder sent to ' . $invoice['client_email'] . '.');
        } else {
            AuditLogger::log('invoice.reminder_failed', 'invoice', (int) $id, ['error' => $mailer->lastError()]);
            Session::flash('errors', ['Reminder not sent: ' . ($mailer->lastError() ?? 'the mail transport rejected the message.')]);
        }

        $this->redirect('/invoices/' . $id);
    }

    public function deletePayment(Request $request, string $id, string $paymentId): never
    {
        try {
            (new InvoiceService())->deletePayment((int) $id, (int) $paymentId);
            Session::flash('success', 'Payment deleted.');
        } catch (\Throwable $exception) {
            Session::flash('errors', [$exception->getMessage()]);
        }

        $this->redirect('/invoices/' . $id);
    }

    public function recordRefund(Request $request, string $id): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('amount', 'Amount')
            ->numeric('amount', 'Amount')
            ->required('payment_date', 'Refund date');

        if ($validator->fails() || (float) ($data['amount'] ?? 0) <= 0) {
            $errors = $validator->errors();
            if ((float) ($data['amount'] ?? 0) <= 0) {
                $errors['amount'] = 'Refund amount must be greater than zero.';
            }
            $this->backWithErrors($errors, $data);
        }

        (new InvoiceService())->recordRefund((int) $id, $data);
        Session::flash('success', 'Refund recorded.');
        $this->redirect('/invoices/' . $id);
    }

    public function pdf(Request $request, string $id): never
    {
        if ((string) $request->input('preview', '') !== '') {
            header('Content-Type: text/html; charset=utf-8');
            echo (new PdfService())->invoiceHtml((int) $id);
            exit;
        }

        $invoice = app()->db()->fetch('SELECT invoice_number FROM invoices WHERE id = ?', [(int) $id]);
        $pdf = (new PdfService())->invoicePdf((int) $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . ($invoice['invoice_number'] ?? 'invoice') . '.pdf"');
        echo $pdf;
        exit;
    }

    public function send(Request $request, string $id): never
    {
        $invoice = app()->db()->fetch(
            'SELECT invoices.*, clients.name AS client_name, clients.email AS client_email
             FROM invoices INNER JOIN clients ON clients.id = invoices.client_id WHERE invoices.id = ?',
            [(int) $id]
        );

        if (!$invoice) {
            Session::flash('errors', ['Invoice not found.']);
            $this->redirect('/invoices');
        }

        if (!$invoice['client_email']) {
            Session::flash('errors', ['No email on file for this client. Invoice not sent.']);
            $this->redirect('/invoices/' . $id);
        }

        [$subject, $body] = (new MailerService())->template('invoice_send', [
            'invoice_number' => $invoice['invoice_number'],
            'invoice_total' => money($invoice['total'], $invoice['currency']),
            'balance_due' => money($invoice['balance_due'], $invoice['currency']),
            'due_date' => $invoice['due_date'],
            'client_name' => $invoice['client_name'],
            'business_name' => (new SettingsService())->business()['business_name'] ?? config('app.name'),
        ]);
        $mailer = new MailerService();
        $sent = $mailer->send($invoice['client_email'], $subject, $body, [[
            'name' => $invoice['invoice_number'] . '.pdf',
            'mime' => 'application/pdf',
            'content' => (new PdfService())->invoicePdf((int) $id),
        ]]);

        if ($sent) {
            (new InvoiceService())->markSent((int) $id);
            Session::flash('success', 'Invoice emailed to ' . $invoice['client_email'] . '.');
        } else {
            Session::flash('errors', ['Invoice email not sent: ' . ($mailer->lastError() ?? 'the mail transport rejected the message.')]);
        }

        $this->redirect('/invoices/' . $id);
    }
}

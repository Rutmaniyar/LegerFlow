<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\SignedOption;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\InvoiceCalculator;
use App\Services\NumberGenerator;
use App\Services\SettingsService;
use App\Support\ReferenceData;

final class RecurringController extends Controller
{
    public function index(): string
    {
        $settings = new SettingsService();
        return $this->view('recurring/index', [
            'title' => 'Recurring invoices',
            'recurring' => app()->db()->fetchAll(
                'SELECT recurring_invoices.*, clients.name AS client_name
                 FROM recurring_invoices INNER JOIN clients ON clients.id = recurring_invoices.client_id
                 ORDER BY next_run_date ASC'
            ),
            'clients' => app()->db()->fetchAll('SELECT id, name, currency FROM clients WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes ORDER BY is_active DESC, name'),
            'defaultTax' => $settings->defaultTax(),
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
            ->integer('client_id', 'Client')
            ->required('frequency', 'Frequency')
            ->in('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'], 'Frequency')
            ->required('next_run_date', 'Next run date')
            ->date('next_run_date', 'Next run date')
            ->date('end_date', 'End date')
            ->required('currency', 'Currency');

        $calculated = (new InvoiceCalculator())->fromRequest($data);
        if ($validator->fails() || $calculated['errors'] !== [] || $calculated['items'] === []) {
            $errors = $validator->errors();
            if ($calculated['errors'] !== []) {
                $errors['items'] = implode(' ', $calculated['errors']);
            } elseif ($calculated['items'] === []) {
                $errors['items'] = 'At least one line item is required.';
            }
            $this->backWithErrors($errors, $data);
        }

        $id = app()->db()->transaction(function () use ($data, $calculated): int {
            $id = app()->db()->insert(
                'INSERT INTO recurring_invoices
                 (client_id, frequency, next_run_date, end_date, currency, subtotal, discount_total, tax_total, total, notes, terms, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [
                    (int) $data['client_id'],
                    $data['frequency'],
                    $data['next_run_date'],
                    $data['end_date'] ?: null,
                    $data['currency'],
                    $calculated['subtotal'],
                    $calculated['discount_total'],
                    $calculated['tax_total'],
                    $calculated['total'],
                    $data['notes'] ?? null,
                    $data['terms'] ?? null,
                ]
            );

            foreach ($calculated['items'] as $item) {
                app()->db()->execute(
                    'INSERT INTO recurring_invoice_items (recurring_invoice_id, description, quantity, unit_price, discount_type, discount_value, tax_rate, line_total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_type'], $item['discount_value'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                );
            }

            return $id;
        });

        AuditLogger::log('recurring_invoice.created', 'recurring_invoice', $id);
        Session::flash('success', 'Recurring invoice schedule created.');
        $this->redirect('/recurring');
    }

    public function run(Request $request): never
    {
        $expected = (string) (new \App\Services\SettingsService())->get('recurring_cron_token', '');
        if ($expected === '' || !hash_equals($expected, (string) $request->input('token', ''))) {
            Response::json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $created = 0;
        $rows = app()->db()->fetchAll(
            "SELECT * FROM recurring_invoices
             WHERE is_active = 1 AND next_run_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())"
        );

        foreach ($rows as $recurring) {
            app()->db()->transaction(function () use ($recurring, &$created): void {
                $invoiceId = app()->db()->insert(
                    'INSERT INTO invoices
                     (client_id, invoice_number, status, issue_date, due_date, currency, subtotal, discount_total, tax_total, total, balance_due, notes, terms, public_token)
                     VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $recurring['client_id'],
                        (new NumberGenerator())->nextInvoiceNumber(),
                        'draft',
                        $recurring['currency'],
                        $recurring['subtotal'],
                        $recurring['discount_total'],
                        $recurring['tax_total'],
                        $recurring['total'],
                        $recurring['total'],
                        $recurring['notes'],
                        $recurring['terms'],
                        hash('sha256', random_bytes(32)),
                    ]
                );

                $items = app()->db()->fetchAll('SELECT * FROM recurring_invoice_items WHERE recurring_invoice_id = ? ORDER BY sort_order', [$recurring['id']]);
                foreach ($items as $item) {
                    app()->db()->execute(
                        'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_type, discount_value, tax_rate, line_total, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$invoiceId, $item['description'], $item['quantity'], $item['unit_price'], $item['discount_type'], $item['discount_value'], $item['tax_rate'], $item['line_total'], $item['sort_order']]
                    );
                }

                $interval = match ($recurring['frequency']) {
                    'weekly' => '+1 week',
                    'quarterly' => '+3 months',
                    'yearly' => '+1 year',
                    default => '+1 month',
                };
                app()->db()->execute('UPDATE recurring_invoices SET next_run_date = ?, updated_at = NOW() WHERE id = ?', [date('Y-m-d', strtotime($interval)), $recurring['id']]);
                $created++;
            });
        }

        Response::json(['ok' => true, 'created' => $created]);
    }
}

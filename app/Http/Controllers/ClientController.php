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
use App\Services\PrivacyService;
use App\Support\Paginator;
use App\Support\ReferenceData;

final class ClientController extends Controller
{
    public function index(Request $request): string
    {
        $search = trim((string) $request->input('q', ''));
        $page = Paginator::page($request->input('page', 1));
        $params = [];
        $where = 'deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ? OR contact_name LIKE ?)';
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }

        $total = (int) (app()->db()->fetch("SELECT COUNT(*) AS count FROM clients WHERE {$where}", $params)['count'] ?? 0);
        $clients = app()->db()->fetchAll(
            "SELECT * FROM clients WHERE {$where} ORDER BY name LIMIT " . Paginator::perPage() . ' OFFSET ' . Paginator::offset($page),
            $params
        );

        return $this->view('clients/index', [
            'title' => 'Clients',
            'clients' => $clients,
            'search' => $search,
            'pagination' => Paginator::meta($total, $page),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function search(Request $request): never
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 190) {
            header('Cache-Control: private, no-store');
            Response::json(['results' => []]);
        }

        $results = app()->db()->fetchAll(
            'SELECT id, name, contact_name, email, currency
             FROM clients
             WHERE deleted_at IS NULL AND name LIKE ?
             ORDER BY CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END, name
             LIMIT 8',
            ['%' . $query . '%', $query, $query . '%']
        );

        header('Cache-Control: private, no-store');
        Response::json(['results' => array_map(static fn (array $client): array => [
            'id' => (int) $client['id'],
            'name' => $client['name'],
            'contact_name' => $client['contact_name'],
            'email' => $client['email'],
            'currency' => $client['currency'],
        ], $results)]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('name', 'Client name')
            ->required('currency', 'Currency')
            ->in('type', ['business', 'person'], 'Client type')
            ->in('data_processing_basis', ['contract', 'legal_obligation', 'legitimate_interest', 'consent'], 'Processing basis')
            ->email('email', 'Email')
            ->max('name', 190, 'Client name')
            ->max('contact_name', 190, 'Contact name')
            ->max('email', 190, 'Email')
            ->max('phone', 80, 'Phone')
            ->max('website', 190, 'Website')
            ->max('tax_number', 120, 'Tax number')
            ->max('billing_address', 5000, 'Billing address')
            ->max('shipping_address', 5000, 'Shipping address')
            ->max('notes', 5000, 'Notes');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $duplicate = self::findDuplicate(trim((string) $data['name']), (string) ($data['email'] ?? ''));
        if ($duplicate !== null) {
            Session::flash('success', 'A client named "' . $duplicate['name'] . '" already exists - opened the existing record instead of creating a duplicate.');
            $this->redirect('/clients/' . $duplicate['id']);
        }

        $id = app()->db()->insert(
            'INSERT INTO clients
             (type, name, contact_name, email, phone, website, billing_address, shipping_address, tax_number, currency, notes, data_processing_basis)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['type'] ?? 'business',
                trim((string) $data['name']),
                $data['contact_name'] ?? null,
                $data['email'] ?: null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['billing_address'] ?? null,
                $data['shipping_address'] ?? null,
                $data['tax_number'] ?? null,
                $data['currency'] ?? 'USD',
                $data['notes'] ?? null,
                $data['data_processing_basis'] ?? 'contract',
            ]
        );

        AuditLogger::log('client.created', 'client', $id);
        Session::flash('success', 'Client created.');
        $this->redirect('/clients/' . $id);
    }

    /**
     * Looks up an existing, non-deleted client with the same normalized name and email to avoid duplicate records.
     * Requires a matching email on both sides - matching by name alone risks merging two unrelated clients that
     * happen to share a common name (e.g. two different "John Smith" customers with no email on file).
     */
    public static function findDuplicate(string $name, string $email): ?array
    {
        $name = trim($name);
        $email = trim($email);
        if ($name === '' || $email === '') {
            return null;
        }

        return app()->db()->fetch(
            'SELECT id, name FROM clients WHERE deleted_at IS NULL AND LOWER(name) = LOWER(?) AND LOWER(email) = LOWER(?) LIMIT 1',
            [$name, $email]
        );
    }

    public function show(Request $request, string $id): string
    {
        $client = app()->db()->fetch('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$client) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Client not found']);
        }

        $invoices = app()->db()->fetchAll('SELECT * FROM invoices WHERE client_id = ? ORDER BY issue_date DESC', [(int) $id]);
        $quotes = app()->db()->fetchAll('SELECT * FROM quotes WHERE client_id = ? ORDER BY issue_date DESC', [(int) $id]);
        $payments = app()->db()->fetchAll(
            'SELECT payments.*, invoices.invoice_number
             FROM payments INNER JOIN invoices ON invoices.id = payments.invoice_id
             WHERE invoices.client_id = ? ORDER BY payments.payment_date DESC',
            [(int) $id]
        );

        return $this->view('clients/show', [
            'title' => $client['name'],
            'client' => $client,
            'invoices' => $invoices,
            'quotes' => $quotes,
            'payments' => $payments,
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('name', 'Client name')
            ->required('currency', 'Currency')
            ->in('type', ['business', 'person'], 'Client type')
            ->in('data_processing_basis', ['contract', 'legal_obligation', 'legitimate_interest', 'consent'], 'Processing basis')
            ->email('email', 'Email')
            ->max('name', 190, 'Client name')
            ->max('contact_name', 190, 'Contact name')
            ->max('email', 190, 'Email')
            ->max('phone', 80, 'Phone')
            ->max('website', 190, 'Website')
            ->max('tax_number', 120, 'Tax number')
            ->max('billing_address', 5000, 'Billing address')
            ->max('shipping_address', 5000, 'Shipping address')
            ->max('notes', 5000, 'Notes');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        app()->db()->execute(
            'UPDATE clients
             SET type = ?, name = ?, contact_name = ?, email = ?, phone = ?, website = ?, billing_address = ?, shipping_address = ?,
                 tax_number = ?, currency = ?, notes = ?, data_processing_basis = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['type'] ?? 'business',
                trim((string) $data['name']),
                $data['contact_name'] ?? null,
                $data['email'] ?: null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['billing_address'] ?? null,
                $data['shipping_address'] ?? null,
                $data['tax_number'] ?? null,
                $data['currency'] ?? 'USD',
                $data['notes'] ?? null,
                $data['data_processing_basis'] ?? 'contract',
                (int) $id,
            ]
        );

        AuditLogger::log('client.updated', 'client', (int) $id);
        Session::flash('success', 'Client updated.');
        $this->redirect('/clients/' . $id);
    }

    public function export(Request $request, string $id): never
    {
        $payload = (new PrivacyService())->exportClient((int) $id);
        AuditLogger::log('privacy.client_exported', 'client', (int) $id);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="client-' . (int) $id . '-export.json"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        exit;
    }

    public function anonymize(Request $request, string $id): never
    {
        (new PrivacyService())->anonymizeClient((int) $id);
        Session::flash('success', 'Client personal data anonymized.');
        $this->redirect('/clients');
    }
}

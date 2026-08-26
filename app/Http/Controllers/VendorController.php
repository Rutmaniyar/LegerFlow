<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Support\Paginator;

final class VendorController extends Controller
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

        $total = (int) (app()->db()->fetch("SELECT COUNT(*) AS count FROM vendors WHERE {$where}", $params)['count'] ?? 0);
        $vendors = app()->db()->fetchAll(
            "SELECT * FROM vendors WHERE {$where} ORDER BY name LIMIT " . Paginator::perPage() . ' OFFSET ' . Paginator::offset($page),
            $params
        );

        return $this->view('vendors/index', [
            'title' => 'Vendors',
            'vendors' => $vendors,
            'search' => $search,
            'pagination' => Paginator::meta($total, $page),
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
            'SELECT id, name, contact_name, email
             FROM vendors
             WHERE deleted_at IS NULL AND name LIKE ?
             ORDER BY CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END, name
             LIMIT 8',
            ['%' . $query . '%', $query, $query . '%']
        );

        header('Cache-Control: private, no-store');
        Response::json(['results' => array_map(static fn (array $vendor): array => [
            'id' => (int) $vendor['id'],
            'name' => $vendor['name'],
            'contact_name' => $vendor['contact_name'],
            'email' => $vendor['email'],
        ], $results)]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $validator = $this->validator($data);
        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $duplicate = self::findDuplicate((string) $data['name'], (string) ($data['email'] ?? ''));
        if ($duplicate !== null) {
            Session::flash('success', 'A vendor named "' . $duplicate['name'] . '" already exists - opened the existing record instead.');
            $this->redirect('/vendors/' . $duplicate['id']);
        }

        $id = app()->db()->insert(
            'INSERT INTO vendors (name, contact_name, email, phone, website, billing_address, tax_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                trim((string) $data['name']),
                $data['contact_name'] ?? null,
                ($data['email'] ?? '') !== '' ? $data['email'] : null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['billing_address'] ?? null,
                $data['tax_number'] ?? null,
                $data['notes'] ?? null,
            ]
        );

        AuditLogger::log('vendor.created', 'vendor', $id);
        Session::flash('success', 'Vendor created.');
        $this->redirect('/vendors/' . $id);
    }

    public function show(Request $request, string $id): string
    {
        $vendor = $this->findOr404($id);

        return $this->view('vendors/show', [
            'title' => $vendor['name'],
            'vendor' => $vendor,
            'expenses' => app()->db()->fetchAll('SELECT * FROM expenses WHERE vendor_id = ? ORDER BY expense_date DESC, id DESC LIMIT 50', [(int) $id]),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->findOr404($id);
        $data = $request->all();
        $validator = $this->validator($data);
        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        app()->db()->execute(
            'UPDATE vendors
             SET name = ?, contact_name = ?, email = ?, phone = ?, website = ?, billing_address = ?, tax_number = ?, notes = ?, updated_at = NOW()
             WHERE id = ?',
            [
                trim((string) $data['name']),
                $data['contact_name'] ?? null,
                ($data['email'] ?? '') !== '' ? $data['email'] : null,
                $data['phone'] ?? null,
                $data['website'] ?? null,
                $data['billing_address'] ?? null,
                $data['tax_number'] ?? null,
                $data['notes'] ?? null,
                (int) $id,
            ]
        );

        AuditLogger::log('vendor.updated', 'vendor', (int) $id);
        Session::flash('success', 'Vendor updated.');
        $this->redirect('/vendors/' . $id);
    }

    public static function findDuplicate(string $name, string $email): ?array
    {
        $name = trim($name);
        $email = trim($email);
        if ($name === '') {
            return null;
        }

        if ($email !== '') {
            return app()->db()->fetch(
                'SELECT id, name FROM vendors WHERE deleted_at IS NULL AND LOWER(name) = LOWER(?) AND LOWER(email) = LOWER(?) LIMIT 1',
                [$name, $email]
            );
        }

        return app()->db()->fetch(
            'SELECT id, name FROM vendors WHERE deleted_at IS NULL AND LOWER(name) = LOWER(?) AND email IS NULL LIMIT 1',
            [$name]
        );
    }

    private function validator(array $data): Validator
    {
        return (new Validator($data))
            ->required('name', 'Vendor name')
            ->max('name', 190, 'Vendor name')
            ->max('contact_name', 190, 'Contact name')
            ->email('email', 'Email')
            ->max('email', 190, 'Email')
            ->max('phone', 80, 'Phone')
            ->max('website', 190, 'Website')
            ->max('tax_number', 120, 'Tax number')
            ->max('billing_address', 5000, 'Billing address')
            ->max('notes', 5000, 'Notes');
    }

    private function findOr404(string $id): array
    {
        $vendor = app()->db()->fetch('SELECT * FROM vendors WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$vendor) {
            http_response_code(404);
            echo $this->view('errors/404', ['title' => 'Vendor not found']);
            exit;
        }

        return $vendor;
    }
}

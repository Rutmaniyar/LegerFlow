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
use App\Services\PdfService;
use App\Services\UploadService;
use App\Support\Paginator;
use App\Support\ReferenceData;
use App\Support\TableSort;

final class ExpenseController extends Controller
{
    public function index(Request $request): string
    {
        $page = Paginator::page($request->input('page', 1));
        $sorting = TableSort::resolve($request->input('sort'), $request->input('direction'), [
            'expense_date' => ['column' => 'expenses.expense_date', 'default' => 'desc'],
            'vendor' => ['column' => 'vendor_name', 'default' => 'asc'],
            'category' => ['column' => 'expenses.category', 'default' => 'asc'],
            'amount' => ['column' => 'expenses.amount', 'default' => 'desc'],
        ], 'expense_date');
        $total = (int) (app()->db()->fetch('SELECT COUNT(*) AS count FROM expenses')['count'] ?? 0);
        return $this->view('expenses/index', [
            'title' => 'Expenses',
            'expenses' => app()->db()->fetchAll(
                'SELECT expenses.*, COALESCE(vendors.name, expenses.vendor) AS vendor_name
                 FROM expenses LEFT JOIN vendors ON vendors.id = expenses.vendor_id
                 ORDER BY ' . $sorting['column'] . ' ' . $sorting['direction'] . ', expenses.id ' . $sorting['direction'] . ' LIMIT ' . Paginator::perPage() . ' OFFSET ' . Paginator::offset($page)
            ),
            'vendors' => app()->db()->fetchAll('SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
            'sort' => $sorting['key'],
            'direction' => $sorting['direction'],
            'pagination' => Paginator::meta($total, $page),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';
        $vendorSelection = trim((string) ($data['vendor_id'] ?? ''));
        $createVendor = $vendorSelection === '__new__';

        $validator = (new Validator($data))
            ->required('category', 'Category')
            ->required('expense_date', 'Expense date')
            ->date('expense_date', 'Expense date')
            ->required('amount', 'Amount')
            ->required('currency', 'Currency')
            ->numeric('amount', 'Amount')
            ->numeric('tax_amount', 'Tax amount')
            ->max('category', 120, 'Category')
            ->max('payment_method', 80, 'Payment method')
            ->max('notes', 5000, 'Notes');

        $errors = $validator->errors();
        $vendor = null;
        if ($createVendor) {
            $vendorValidator = (new Validator($data))
                ->required('new_vendor_name', 'New vendor name')
                ->max('new_vendor_name', 190, 'New vendor name')
                ->email('new_vendor_email', 'New vendor email')
                ->max('new_vendor_email', 190, 'New vendor email')
                ->max('new_vendor_phone', 80, 'New vendor phone')
                ->max('new_vendor_tax_number', 120, 'New vendor tax/VAT number')
                ->max('new_vendor_billing_address', 5000, 'New vendor billing address');
            $errors = array_merge($errors, $vendorValidator->errors());
            $data['vendor_id'] = '__new__';
        } elseif ($vendorSelection === '' || filter_var($vendorSelection, FILTER_VALIDATE_INT) === false) {
            $errors['vendor_id'] = 'Choose an existing vendor or create a new vendor.';
        } else {
            $vendor = app()->db()->fetch('SELECT id, name FROM vendors WHERE id = ? AND deleted_at IS NULL', [(int) $vendorSelection]);
        }
        if (!$createVendor && !isset($errors['vendor_id']) && !$vendor) {
            $errors['vendor_id'] = 'Selected vendor was not found.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        try {
            $receiptPath = (new UploadService())->store($request->file('receipt') ?? [], 'receipts');
        } catch (\Throwable $exception) {
            $this->backWithErrors(['receipt' => $exception->getMessage()], $data);
        }

        $id = app()->db()->transaction(function () use ($data, $receiptPath, $createVendor, $vendor): int {
            if ($createVendor) {
                $existing = VendorController::findDuplicate(
                    trim((string) $data['new_vendor_name']),
                    (string) ($data['new_vendor_email'] ?? '')
                );
                if ($existing !== null) {
                    $vendor = ['id' => (int) $existing['id'], 'name' => $existing['name']];
                } else {
                    $vendorId = app()->db()->insert(
                        'INSERT INTO vendors (name, email, phone, billing_address, tax_number)
                         VALUES (?, ?, ?, ?, ?)',
                        [
                            trim((string) $data['new_vendor_name']),
                            trim((string) ($data['new_vendor_email'] ?? '')) ?: null,
                            trim((string) ($data['new_vendor_phone'] ?? '')) ?: null,
                            trim((string) ($data['new_vendor_billing_address'] ?? '')) ?: null,
                            trim((string) ($data['new_vendor_tax_number'] ?? '')) ?: null,
                        ]
                    );
                    $vendor = ['id' => $vendorId, 'name' => trim((string) $data['new_vendor_name'])];
                    AuditLogger::log('vendor.created_from_expense', 'vendor', $vendorId);
                }
            }

            return app()->db()->insert(
                'INSERT INTO expenses (vendor_id, vendor, category, expense_date, amount, tax_amount, currency, payment_method, receipt_path, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $vendor['id'],
                    $vendor['name'],
                    $data['category'],
                    $data['expense_date'],
                    (float) $data['amount'],
                    (float) ($data['tax_amount'] ?? 0),
                    $data['currency'],
                    $data['payment_method'] ?? null,
                    $receiptPath,
                    $data['notes'] ?? null,
                    Auth::id(),
                ]
            );
        });

        AuditLogger::log('expense.created', 'expense', $id);
        Session::flash('success', 'Expense recorded.');
        $this->redirect('/expenses');
    }

    public function show(Request $request, string $id): string
    {
        $expense = app()->db()->fetch(
            'SELECT expenses.*, COALESCE(vendors.name, expenses.vendor) AS vendor_name, vendors.contact_name AS vendor_contact_name,
                    vendors.email AS vendor_email, vendors.phone AS vendor_phone, vendors.website AS vendor_website,
                    vendors.billing_address AS vendor_billing_address, vendors.tax_number AS vendor_tax_number
             FROM expenses LEFT JOIN vendors ON vendors.id = expenses.vendor_id WHERE expenses.id = ?',
            [(int) $id]
        );
        if (!$expense) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Expense not found']);
        }

        return $this->view('expenses/show', [
            'title' => $expense['vendor'],
            'expense' => $expense,
            'business' => (new \App\Services\SettingsService())->business(),
        ]);
    }

    public function edit(Request $request, string $id): string
    {
        $expense = $this->findOrRedirect($id);

        return $this->view('expenses/edit', [
            'title' => 'Edit expense',
            'expense' => $expense,
            'vendors' => app()->db()->fetchAll('SELECT id, name FROM vendors WHERE deleted_at IS NULL ORDER BY name'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $expense = $this->findOrRedirect($id);
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('vendor_id', 'Vendor')
            ->integer('vendor_id', 'Vendor')
            ->required('category', 'Category')
            ->required('expense_date', 'Expense date')
            ->date('expense_date', 'Expense date')
            ->required('amount', 'Amount')
            ->required('currency', 'Currency')
            ->numeric('amount', 'Amount')
            ->numeric('tax_amount', 'Tax amount')
            ->max('category', 120, 'Category')
            ->max('payment_method', 80, 'Payment method')
            ->max('notes', 5000, 'Notes');

        $errors = $validator->errors();
        $vendor = app()->db()->fetch('SELECT id, name FROM vendors WHERE id = ? AND deleted_at IS NULL', [(int) ($data['vendor_id'] ?? 0)]);
        if (!isset($errors['vendor_id']) && !$vendor) {
            $errors['vendor_id'] = 'Selected vendor was not found.';
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        $receiptPath = $expense['receipt_path'];
        try {
            $uploaded = (new UploadService())->store($request->file('receipt') ?? [], 'receipts');
            if ($uploaded) {
                $receiptPath = $uploaded;
            }
        } catch (\Throwable $exception) {
            $this->backWithErrors(['receipt' => $exception->getMessage()], $data);
        }

        app()->db()->execute(
            'UPDATE expenses SET vendor_id = ?, vendor = ?, category = ?, expense_date = ?, amount = ?, tax_amount = ?, currency = ?, payment_method = ?, receipt_path = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [
                (int) $vendor['id'],
                $vendor['name'],
                $data['category'],
                $data['expense_date'],
                (float) $data['amount'],
                (float) ($data['tax_amount'] ?? 0),
                $data['currency'],
                $data['payment_method'] ?? null,
                $receiptPath,
                $data['notes'] ?? null,
                (int) $id,
            ]
        );

        AuditLogger::log('expense.updated', 'expense', (int) $id);
        Session::flash('success', 'Expense updated.');
        $this->redirect('/expenses/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->findOrRedirect($id);
        app()->db()->execute('DELETE FROM expenses WHERE id = ?', [(int) $id]);
        AuditLogger::log('expense.deleted', 'expense', (int) $id);
        Session::flash('success', 'Expense deleted.');
        $this->redirect('/expenses');
    }

    public function pdf(Request $request, string $id): never
    {
        if ((string) $request->input('preview', '') !== '') {
            header('Content-Type: text/html; charset=utf-8');
            echo (new PdfService())->expenseHtml((int) $id);
            exit;
        }

        $expense = app()->db()->fetch(
            'SELECT COALESCE(vendors.name, expenses.vendor) AS vendor
             FROM expenses LEFT JOIN vendors ON vendors.id = expenses.vendor_id WHERE expenses.id = ?',
            [(int) $id]
        );
        $pdf = (new PdfService())->expensePdf((int) $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="expense-receipt-' . ($expense['vendor'] ?? 'expense') . '.pdf"');
        echo $pdf;
        exit;
    }

    private function findOrRedirect(string $id): array
    {
        $expense = app()->db()->fetch('SELECT * FROM expenses WHERE id = ?', [(int) $id]);
        if (!$expense) {
            Session::flash('errors', ['Expense not found.']);
            $this->redirect('/expenses');
        }

        return $expense;
    }
}

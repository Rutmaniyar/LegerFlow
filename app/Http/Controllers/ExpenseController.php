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
use App\Support\ReferenceData;

final class ExpenseController extends Controller
{
    public function index(): string
    {
        return $this->view('expenses/index', [
            'title' => 'Expenses',
            'expenses' => app()->db()->fetchAll('SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 250'),
            'currencies' => app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));
        $currency = SignedOption::verify('currency', $data['currency'] ?? '', $currencyCodes);
        $data['currency'] = $currency ?? '';

        $validator = (new Validator($data))
            ->required('vendor', 'Vendor')
            ->required('category', 'Category')
            ->required('expense_date', 'Expense date')
            ->date('expense_date', 'Expense date')
            ->required('amount', 'Amount')
            ->required('currency', 'Currency')
            ->numeric('amount', 'Amount')
            ->numeric('tax_amount', 'Tax amount')
            ->max('vendor', 190, 'Vendor')
            ->max('category', 120, 'Category')
            ->max('payment_method', 80, 'Payment method')
            ->max('notes', 5000, 'Notes');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        try {
            $receiptPath = (new UploadService())->store($request->file('receipt') ?? [], 'receipts');
        } catch (\Throwable $exception) {
            $this->backWithErrors(['receipt' => $exception->getMessage()], $data);
        }

        $id = app()->db()->insert(
            'INSERT INTO expenses (vendor, category, expense_date, amount, tax_amount, currency, payment_method, receipt_path, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['vendor'],
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

        AuditLogger::log('expense.created', 'expense', $id);
        Session::flash('success', 'Expense recorded.');
        $this->redirect('/expenses');
    }

    public function show(Request $request, string $id): string
    {
        $expense = app()->db()->fetch('SELECT * FROM expenses WHERE id = ?', [(int) $id]);
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
            ->required('vendor', 'Vendor')
            ->required('category', 'Category')
            ->required('expense_date', 'Expense date')
            ->date('expense_date', 'Expense date')
            ->required('amount', 'Amount')
            ->required('currency', 'Currency')
            ->numeric('amount', 'Amount')
            ->numeric('tax_amount', 'Tax amount')
            ->max('vendor', 190, 'Vendor')
            ->max('category', 120, 'Category')
            ->max('payment_method', 80, 'Payment method')
            ->max('notes', 5000, 'Notes');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
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
            'UPDATE expenses SET vendor = ?, category = ?, expense_date = ?, amount = ?, tax_amount = ?, currency = ?, payment_method = ?, receipt_path = ?, notes = ?, updated_at = NOW() WHERE id = ?',
            [
                $data['vendor'],
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

        $expense = app()->db()->fetch('SELECT vendor FROM expenses WHERE id = ?', [(int) $id]);
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

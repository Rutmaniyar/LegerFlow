<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\InvoiceService;
use App\Services\SettingsService;

final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        foreach (app()->db()->fetchAll("SELECT id FROM invoices WHERE status IN ('sent','viewed','partial') AND due_date < CURDATE()") as $row) {
            (new InvoiceService())->refreshStatus((int) $row['id']);
        }

        $stats = [
            'income_month' => app()->db()->fetch("SELECT COALESCE(SUM(amount), 0) AS value FROM payments WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['value'] ?? 0,
            'outstanding' => app()->db()->fetch("SELECT COALESCE(SUM(balance_due), 0) AS value FROM invoices WHERE status NOT IN ('paid','void','draft')")['value'] ?? 0,
            'overdue' => app()->db()->fetch("SELECT COALESCE(SUM(balance_due), 0) AS value FROM invoices WHERE status = 'overdue'")['value'] ?? 0,
            'clients' => app()->db()->fetch('SELECT COUNT(*) AS value FROM clients WHERE deleted_at IS NULL')['value'] ?? 0,
        ];

        $monthly = app()->db()->fetchAll(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month, COALESCE(SUM(amount), 0) AS income
             FROM payments
             WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
             ORDER BY month"
        );

        $status = app()->db()->fetchAll(
            "SELECT status, COUNT(*) AS count FROM invoices GROUP BY status ORDER BY count DESC"
        );

        $recentInvoices = app()->db()->fetchAll(
            'SELECT invoices.*, clients.name AS client_name
             FROM invoices INNER JOIN clients ON clients.id = invoices.client_id
             ORDER BY invoices.created_at DESC LIMIT 8'
        );

        $business = (new SettingsService())->business();
        $invoiceCount = (int) (app()->db()->fetch('SELECT COUNT(*) AS value FROM invoices')['value'] ?? 0);
        $userCount = (int) (app()->db()->fetch('SELECT COUNT(*) AS value FROM users WHERE deleted_at IS NULL')['value'] ?? 0);

        $checklist = [
            ['label' => 'Add your first client', 'done' => (int) $stats['clients'] > 0, 'href' => '/clients'],
            ['label' => 'Create your first invoice', 'done' => $invoiceCount > 0, 'href' => '/invoices/create'],
            ['label' => 'Invite a teammate', 'done' => $userCount > 1, 'href' => '/settings'],
            ['label' => 'Add your business logo', 'done' => !empty($business['logo_path']), 'href' => '/settings'],
        ];

        return $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'monthly' => $monthly,
            'status' => $status,
            'recentInvoices' => $recentInvoices,
            'business' => $business,
            'checklist' => $checklist,
            'isFirstTime' => (int) $stats['clients'] === 0 && $invoiceCount === 0,
        ]);
    }
}

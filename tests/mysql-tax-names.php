<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Run with LF_TEST_DB_PORT / LF_TEST_DB_USER / LF_TEST_DB_PASSWORD for a local MySQL server.
require __DIR__ . '/../bootstrap.php';

use App\Core\App;
use App\Services\InstallerService;
use App\Services\InvoiceService;

$originalConfig = app()->config();
$databaseConfig = [
    'host' => '127.0.0.1',
    'port' => (int) (getenv('LF_TEST_DB_PORT') ?: 3306),
    'user' => getenv('LF_TEST_DB_USER') ?: 'root',
    'password' => getenv('LF_TEST_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
$admin = new PDO('mysql:host=127.0.0.1;port=' . $databaseConfig['port'], $databaseConfig['user'], $databaseConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$installer = new InstallerService();
$splitSql = new ReflectionMethod($installer, 'splitSql');
$migration = '20261112_000001_line_tax_names.sql';

function check_tax_migration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['upgrade', 'partial', 'fresh'] as $scenario) {
    $databaseName = 'ledgerflow_tax_test_' . bin2hex(random_bytes(6));
    $admin->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
    try {
        new App(array_replace($originalConfig, ['database' => $databaseConfig + ['name' => $databaseName]]));
        $db = app()->db();
        if ($scenario !== 'fresh') {
            $files = glob(DATABASE_PATH . '/migrations/*.sql');
            sort($files);
            foreach ($files as $file) {
                if (basename($file) >= $migration) {
                    continue;
                }
                foreach ($splitSql->invoke($installer, (string) file_get_contents($file)) as $statement) {
                    $db->pdo()->exec($statement);
                }
                $db->execute('INSERT INTO migrations (migration) VALUES (?)', [basename($file)]);
            }
        } else {
            $installer->runMigrations($db);
        }

        $db->execute("INSERT IGNORE INTO currencies (code, name, symbol) VALUES ('GBP', 'British Pound', 'GBP')");
        $clientId = $db->insert("INSERT INTO clients (name, currency) VALUES ('Tax migration test', 'GBP')");
        $invoiceId = $db->insert("INSERT INTO invoices (client_id, invoice_number, issue_date, due_date, currency, subtotal, tax_total, total, balance_due, public_token) VALUES (?, 'INV-LEGACY', CURDATE(), CURDATE(), 'GBP', 8020, 1604, 9624, 9624, ?)", [$clientId, bin2hex(random_bytes(32))]);
        $quoteId = $db->insert("INSERT INTO quotes (client_id, quote_number, issue_date, currency, subtotal, tax_total, total) VALUES (?, 'QUO-LEGACY', CURDATE(), 'GBP', 8020, 1604, 9624)", [$clientId]);
        $recurringId = $db->insert("INSERT INTO recurring_invoices (client_id, next_run_date, currency, subtotal, tax_total, total) VALUES (?, CURDATE(), 'GBP', 8020, 1604, 9624)", [$clientId]);
        $tables = ['invoice_items' => ['invoice_id', $invoiceId], 'quote_items' => ['quote_id', $quoteId], 'recurring_invoice_items' => ['recurring_invoice_id', $recurringId]];
        $before = [];
        foreach ($tables as $table => [$key, $id]) {
            $db->execute("INSERT INTO {$table} ({$key}, description, quantity, unit_price, discount_type, discount_value, tax_rate, line_total) VALUES (?, 'Student recruitment', 1, 8020, 'percent', 0, 20, 9624)", [$id]);
            $before[$table] = $db->fetch("SELECT * FROM {$table}");
            unset($before[$table]['tax_name']);
        }

        if ($scenario === 'partial') {
            $db->execute('ALTER TABLE invoice_items ADD COLUMN tax_name VARCHAR(190) NULL AFTER tax_rate');
            $db->execute("UPDATE invoice_items SET tax_name = 'VAT'");
        }
        $installer->runMigrations($db);
        foreach ($tables as $table => [$key, $id]) {
            $after = $db->fetch("SELECT * FROM {$table}");
            $expectedName = $scenario === 'partial' && $table === 'invoice_items' ? 'VAT' : null;
            check_tax_migration(array_key_exists('tax_name', $after) && $after['tax_name'] === $expectedName, "{$scenario}: {$table} must preserve known and unknown tax names");
            unset($after['tax_name']);
            check_tax_migration($before[$table] === $after, "{$scenario}: {$table} changed historical data");
            $db->execute("UPDATE {$table} SET tax_name = 'VAT'");
        }
        $installer->runMigrations($db);
        check_tax_migration((int) $db->fetch('SELECT COUNT(*) AS n FROM migrations WHERE migration = ?', [$migration])['n'] === 1, 'Migration must run once');
        foreach ($tables as $table => [$key, $id]) {
            check_tax_migration($db->fetch("SELECT tax_name FROM {$table}")['tax_name'] === 'VAT', 'Rerunning migrations must preserve snapshots');
        }

        $convertedId = (new InvoiceService())->convertQuote($quoteId);
        $converted = $db->fetch('SELECT * FROM invoice_items WHERE invoice_id = ?', [$convertedId]);
        check_tax_migration($converted['tax_name'] === 'VAT' && $converted['tax_rate'] === '20.00' && $converted['line_total'] === '9624.00', 'Quote conversion must copy the tax snapshot and totals');
        echo "PASS {$scenario}: tax migration, historical values, repeat run, quote conversion\n";
    } finally {
        new App($originalConfig);
        $db = null;
        $admin->exec('DROP DATABASE `' . $databaseName . '`');
    }
}

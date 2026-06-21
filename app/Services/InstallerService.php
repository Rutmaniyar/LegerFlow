<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\ReferenceData;
use PDO;

final class InstallerService
{
    public function requirements(): array
    {
        $checks = [
            'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO extension' => extension_loaded('pdo'),
            'PDO MySQL extension' => extension_loaded('pdo_mysql') || in_array('mysql', PDO::getAvailableDrivers(), true),
            'OpenSSL extension' => extension_loaded('openssl'),
            'Mbstring extension' => extension_loaded('mbstring'),
            'Fileinfo extension' => extension_loaded('fileinfo'),
            'JSON extension' => extension_loaded('json'),
            'Zip extension' => extension_loaded('zip'),
            'Storage writable' => is_writable(STORAGE_PATH),
            'Config writable' => is_writable(CONFIG_PATH),
            'Uploads writable' => is_writable(PUBLIC_PATH . '/uploads'),
        ];

        return $checks;
    }

    public function canInstall(): bool
    {
        return !in_array(false, $this->requirements(), true);
    }

    public function testDatabase(array $database): bool
    {
        new Database($database);
        return true;
    }

    public function install(array $data): void
    {
        if (is_file(STORAGE_PATH . '/installed.lock')) {
            throw new \RuntimeException('Installer is locked.');
        }

        $databaseConfig = [
            'host' => $data['db_host'],
            'port' => (int) ($data['db_port'] ?: 3306),
            'name' => $data['db_name'],
            'user' => $data['db_user'],
            'password' => $data['db_password'],
            'charset' => 'utf8mb4',
        ];

        $db = new Database($databaseConfig);
        $this->runMigrations($db);
        $this->seed($db, $data);
        $this->writeConfig($data, $databaseConfig);
        file_put_contents(STORAGE_PATH . '/installed.lock', 'Installed at ' . date(DATE_ATOM));
    }

    public function runMigrations(Database $db): void
    {
        $files = glob(DATABASE_PATH . '/migrations/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            $alreadyRun = false;
            try {
                $alreadyRun = (bool) $db->fetch('SELECT id FROM migrations WHERE migration = ?', [$name]);
            } catch (\Throwable) {
                $alreadyRun = false;
            }

            if ($alreadyRun) {
                continue;
            }

            $sql = (string) file_get_contents($file);
            foreach ($this->splitSql($sql) as $statement) {
                if (trim($statement) !== '') {
                    $db->pdo()->exec($statement);
                }
            }

            $db->execute('INSERT INTO migrations (migration) VALUES (?)', [$name]);
        }
    }

    private function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $quote = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $previous = $i > 0 ? $sql[$i - 1] : '';

            if (($char === '"' || $char === "'") && $previous !== '\\') {
                if (!$inString) {
                    $inString = true;
                    $quote = $char;
                } elseif ($quote === $char) {
                    $inString = false;
                }
            }

            if ($char === ';' && !$inString) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function seed(Database $db, array $data): void
    {
        $permissions = [
            'owner' => ['*'],
            'manager' => ['dashboard.view', 'clients.manage', 'quotes.manage', 'invoices.manage', 'payments.manage', 'expenses.manage', 'reports.view', 'settings.manage'],
            'accountant' => ['dashboard.view', 'clients.view', 'quotes.view', 'invoices.manage', 'payments.manage', 'expenses.manage', 'reports.view'],
            'viewer' => ['dashboard.view', 'clients.view', 'quotes.view', 'invoices.view', 'reports.view'],
        ];

        foreach ($permissions as $role => $perms) {
            $db->execute(
                'INSERT IGNORE INTO roles (name, permissions) VALUES (?, ?)',
                [$role, json_encode($perms, JSON_THROW_ON_ERROR)]
            );
        }

        foreach (ReferenceData::currencies() as $currency) {
            $db->execute(
                'INSERT IGNORE INTO currencies (code, name, symbol, is_default) VALUES (?, ?, ?, ?)',
                [$currency['code'], $currency['name'], $currency['symbol'], $currency['code'] === ($data['currency'] ?? 'USD') ? 1 : 0]
            );
        }
        $db->execute('UPDATE currencies SET is_default = IF(code = ?, 1, 0)', [$data['currency'] ?? 'USD']);

        $role = $db->fetch('SELECT id FROM roles WHERE name = ?', ['owner']);
        $db->execute(
            'INSERT INTO users (role_id, name, email, password_hash, email_verified_at, consented_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())',
            [
                $role['id'],
                $data['admin_name'],
                mb_strtolower(trim($data['admin_email'])),
                password_hash($data['admin_password'], PASSWORD_DEFAULT),
            ]
        );

        $db->execute(
            'INSERT INTO business_settings
             (business_name, legal_name, email, phone, address_line1, city, region, postal_code, country, default_currency, default_payment_terms, privacy_policy)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 14, ?)',
            [
                $data['business_name'],
                $data['business_name'],
                $data['business_email'],
                $data['business_phone'] ?? null,
                $data['business_address'] ?? null,
                $data['business_city'] ?? null,
                $data['business_region'] ?? null,
                $data['business_postal_code'] ?? null,
                $data['business_country'] ?? null,
                $data['currency'] ?? 'USD',
                'This application processes client, invoice, payment, and business contact data for contract performance, accounting, and legal recordkeeping.',
            ]
        );

        $db->execute('INSERT IGNORE INTO taxes (name, rate, is_active) VALUES (?, ?, 1)', ['Standard Tax', (float) ($data['tax_rate'] ?? 0)]);

        $settings = [
            'invoice_prefix' => 'INV-',
            'quote_prefix' => 'QUO-',
            'next_invoice_number' => '1001',
            'next_quote_number' => '1001',
            'payment_methods' => "Bank transfer\nCash\nCard\nCheck",
            'reminder_days_before' => '3',
            'reminder_days_after' => '7',
            'recurring_cron_token' => bin2hex(random_bytes(24)),
            'cookie_banner_enabled' => '1',
            'data_retention_years' => '7',
        ];

        foreach ($settings as $key => $value) {
            $db->execute(
                'INSERT INTO settings (setting_key, setting_value, is_private) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value, $key === 'recurring_cron_token' ? 1 : 0]
            );
        }

        $db->execute(
            'INSERT IGNORE INTO email_templates (template_key, subject, body) VALUES
             (?, ?, ?),
             (?, ?, ?),
             (?, ?, ?)',
            [
                'invoice_send',
                'Invoice {{invoice_number}} from {{business_name}}',
                "Hello {{client_name}},\n\nPlease find invoice {{invoice_number}} for {{invoice_total}} attached.\n\nPayment is due on {{due_date}}.\n\nThank you,\n{{business_name}}",
                'quote_send',
                'Quote {{quote_number}} from {{business_name}}',
                "Hello {{client_name}},\n\nPlease find quote {{quote_number}} for {{quote_total}} attached.\n\nKind regards,\n{{business_name}}",
                'payment_reminder',
                'Reminder: invoice {{invoice_number}} is due',
                "Hello {{client_name}},\n\nThis is a reminder that invoice {{invoice_number}} has a remaining balance of {{balance_due}} due on {{due_date}}.\n\nThank you,\n{{business_name}}",
            ]
        );

        $db->execute(
            'INSERT IGNORE INTO invoice_templates (name, layout_key, settings, is_default) VALUES (?, ?, ?, 1)',
            ['Classic Modern', 'classic', json_encode(['show_logo' => true, 'show_tax' => true], JSON_THROW_ON_ERROR)]
        );
    }

    private function writeConfig(array $data, array $databaseConfig): void
    {
        $siteUrl = rtrim((string) $data['site_url'], '/');
        $config = [
            'installed' => true,
            'app' => [
                'name' => 'LedgerFlow',
                'url' => $siteUrl,
                'environment' => 'production',
                'debug' => false,
                'timezone' => $data['timezone'] ?: 'UTC',
                'key' => bin2hex(random_bytes(32)),
            ],
            'database' => $databaseConfig,
            'mail' => [
                'transport' => 'mail',
                'host' => '',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from_email' => $data['business_email'],
                'from_name' => $data['business_name'],
            ],
            'security' => [
                'session_name' => 'ledgerflow_session',
                'login_attempts' => 5,
                'login_decay_minutes' => 15,
                'max_upload_mb' => 2,
                'session_idle_minutes' => 120,
                'session_absolute_minutes' => 720,
                'trusted_proxies' => [],
            ],
        ];

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents(CONFIG_PATH . '/config.php', $content, LOCK_EX);
    }
}

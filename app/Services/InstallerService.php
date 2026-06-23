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
        try {
            new Database($database);
            return true;
        } catch (\Throwable $exception) {
            error_log('LedgerFlow install: database connection test failed - ' . $exception->getMessage());
            throw new \RuntimeException($this->describeDatabaseError($exception));
        }
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

        try {
            $db = new Database($databaseConfig);
        } catch (\Throwable $exception) {
            error_log('LedgerFlow install: database connection failed - ' . $exception->getMessage());
            throw new \RuntimeException($this->describeDatabaseError($exception));
        }

        try {
            $this->runMigrations($db);
            $this->seed($db, $data);
        } catch (\Throwable $exception) {
            error_log('LedgerFlow install: setup failed - ' . $exception->getMessage());
            throw new \RuntimeException('Could not finish setting up the database. Please try again, and contact support if this keeps happening.');
        }

        $this->writeConfig($data, $databaseConfig);
        file_put_contents(STORAGE_PATH . '/installed.lock', 'Installed at ' . date(DATE_ATOM));

        // Best-effort and never fatal: Composer dependencies (e.g. Dompdf) only improve PDF quality, the app
        // works without them via a built-in fallback, so a failure here must not block a fresh install.
        $dependencies = $this->installComposerDependencies();
        if (!$dependencies['ok']) {
            error_log('LedgerFlow install: Composer dependencies were not installed automatically - ' . $dependencies['message']);
        }
    }

    /**
     * Attempts to run `composer install` in the application root so optional Composer dependencies (Dompdf)
     * are available without the operator needing shell access. Many shared hosts disable shell execution,
     * so this degrades to a clear manual-instructions message rather than failing the caller.
     *
     * @return array{ok: bool, attempted: bool, message: string}
     */
    /**
     * Hard wall-clock budget for the Composer subprocess. Shared hosts commonly cap PHP's own
     * max_execution_time around 30s, and that limit kills the script without giving this method
     * a chance to return a friendly message - so this method enforces its own, shorter deadline
     * and kills the subprocess itself instead of letting the host's watchdog fatal the request.
     */
    private const COMPOSER_TIMEOUT_SECONDS = 20;

    public function installComposerDependencies(): array
    {
        try {
            return $this->runComposerInstall();
        } catch (\Throwable $exception) {
            error_log('LedgerFlow: composer install raised an unexpected error - ' . $exception->getMessage());
            return [
                'ok' => false,
                'attempted' => true,
                'message' => 'Dependencies could not be installed automatically. Please contact your developer.',
            ];
        }
    }

    private function runComposerInstall(): array
    {
        if (class_exists(\Dompdf\Dompdf::class)) {
            return ['ok' => true, 'attempted' => false, 'message' => 'Composer dependencies are already installed.'];
        }

        if (!is_file(ROOT_PATH . '/composer.json')) {
            return ['ok' => false, 'attempted' => false, 'message' => 'No composer.json found - nothing to install.'];
        }

        if (!function_exists('proc_open')) {
            return [
                'ok' => false,
                'attempted' => false,
                'message' => 'Shell execution is disabled on this server, so Composer dependencies cannot be installed automatically. '
                    . 'Run "composer install --no-dev" via SSH, or ask your host to do so.',
            ];
        }

        $composer = $this->locateComposerCommand();
        if ($composer === null) {
            return [
                'ok' => false,
                'attempted' => false,
                'message' => 'Composer was not found on this server. Run "composer install --no-dev" via SSH, or ask your host to install Composer.',
            ];
        }

        $command = $composer . ' install --no-dev --no-interaction --optimize-autoloader';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes, ROOT_PATH);
        if (!is_resource($process) || !isset($pipes[1], $pipes[2])) {
            return ['ok' => false, 'attempted' => true, 'message' => 'Could not start Composer on this server. Please contact your developer.'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = time() + self::COMPOSER_TIMEOUT_SECONDS;
        $timedOut = false;
        while (true) {
            $output .= (string) stream_get_contents($pipes[1]);
            $output .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (time() >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }

            usleep(200000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = $timedOut ? -1 : proc_close($process);
        if ($timedOut) {
            // proc_close() would block waiting for an already-terminated-but-not-yet-reaped process in
            // some environments, so it is intentionally skipped here in favour of returning immediately.
            error_log('LedgerFlow: composer install timed out after ' . self::COMPOSER_TIMEOUT_SECONDS . 's - ' . trim($output));
            return [
                'ok' => false,
                'attempted' => true,
                'message' => 'Composer is taking too long to run through the web server. Run "composer install --no-dev" via SSH instead.',
            ];
        }

        if ($exitCode !== 0) {
            error_log('LedgerFlow: composer install exited with code ' . $exitCode . ' - ' . trim($output));
            return ['ok' => false, 'attempted' => true, 'message' => 'Composer ran but reported an error. Check the server error log for details.'];
        }

        // The current process started before vendor/autoload.php existed, so it must be loaded explicitly
        // to confirm the new classes are actually available rather than just trusting the exit code.
        $autoload = ROOT_PATH . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require $autoload;
        }

        return [
            'ok' => class_exists(\Dompdf\Dompdf::class),
            'attempted' => true,
            'message' => 'Composer dependencies installed successfully.',
        ];
    }

    private function locateComposerCommand(): ?string
    {
        $candidates = ['composer', 'composer.phar'];
        foreach ($candidates as $candidate) {
            $check = @proc_open(
                escapeshellcmd($candidate) . ' --version',
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                ROOT_PATH
            );
            if (!is_resource($check) || !isset($pipes[1], $pipes[2])) {
                continue;
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($check) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    /** Translates a raw PDO/driver error into a short, actionable message - no SQLSTATE codes or driver internals. */
    private function describeDatabaseError(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'Access denied') => 'The database username or password is incorrect.',
            str_contains($message, 'Unknown database') => 'That database name does not exist. Check the spelling or create it first.',
            str_contains($message, 'getaddrinfo') || str_contains($message, 'Name or service not known')
                || str_contains($message, 'Connection refused') || str_contains($message, "Can't connect") => 'Could not reach the database server. Check the host and port.',
            default => 'Could not connect to the database. Check your database settings and try again.',
        };
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
                'transport' => $data['mail_transport'] ?? 'mail',
                'host' => $data['mail_host'] ?? '',
                'port' => (int) ($data['mail_port'] ?: 587),
                'username' => $data['mail_username'] ?? '',
                'password' => $data['mail_password'] ?? '',
                'encryption' => $data['mail_encryption'] ?? 'tls',
                'from_email' => ($data['mail_from_email'] ?? '') !== '' ? $data['mail_from_email'] : $data['business_email'],
                'from_name' => ($data['mail_from_name'] ?? '') !== '' ? $data['mail_from_name'] : $data['business_name'],
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

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
use App\Services\MailerService;
use App\Services\SettingsService;
use App\Services\UpdateService;
use App\Services\UploadService;
use App\Support\ReferenceData;

final class SettingsController extends Controller
{
    public function index(): string
    {
        $currencies = app()->db()->fetchAll('SELECT * FROM currencies ORDER BY code');
        $settingsService = new SettingsService();
        $updater = new UpdateService();
        $latestVersion = (string) $settingsService->get('update_latest_version', '');

        return $this->view('settings/index', [
            'title' => 'Settings',
            'business' => (new SettingsService())->business(),
            'canUpdate' => Auth::can('system.update'),
            'update' => [
                'current_version' => $updater->currentVersion(),
                'latest_version' => $latestVersion,
                'is_newer' => $updater->isNewer($latestVersion),
                'release_name' => $settingsService->get('update_release_name', ''),
                'release_notes' => $settingsService->get('update_release_notes', ''),
                'release_url' => $settingsService->get('update_release_url', ''),
                'checked_at' => $settingsService->get('update_checked_at', ''),
            ],
            'currencies' => $currencies,
            'countries' => ReferenceData::countries(),
            'taxes' => app()->db()->fetchAll('SELECT * FROM taxes ORDER BY is_active DESC, name'),
            'emailTemplates' => app()->db()->fetchAll('SELECT * FROM email_templates ORDER BY template_key'),
            'invoiceTemplates' => app()->db()->fetchAll('SELECT * FROM invoice_templates ORDER BY is_default DESC, name'),
            'users' => app()->db()->fetchAll('SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.deleted_at IS NULL ORDER BY users.name'),
            'roles' => app()->db()->fetchAll('SELECT * FROM roles ORDER BY name'),
            'auditLogs' => app()->db()->fetchAll('SELECT audit_logs.*, users.name AS user_name FROM audit_logs LEFT JOIN users ON users.id = audit_logs.user_id ORDER BY audit_logs.created_at DESC LIMIT 80'),
            'settings' => [
                'invoice_prefix' => (new SettingsService())->get('invoice_prefix', 'INV-'),
                'quote_prefix' => (new SettingsService())->get('quote_prefix', 'QUO-'),
                'payment_methods' => (new SettingsService())->get('payment_methods', ''),
                'data_retention_years' => (new SettingsService())->get('data_retention_years', '7'),
            ],
            'mail' => [
                'transport' => (new SettingsService())->get('mail_transport', config('mail.transport', 'mail')),
                'host' => (new SettingsService())->get('mail_host', config('mail.host', '')),
                'port' => (new SettingsService())->get('mail_port', (string) config('mail.port', 587)),
                'username' => (new SettingsService())->get('mail_username', config('mail.username', '')),
                'encryption' => (new SettingsService())->get('mail_encryption', config('mail.encryption', 'tls')),
                'from_email' => (new SettingsService())->get('mail_from_email', config('mail.from_email', '')),
                'from_name' => (new SettingsService())->get('mail_from_name', config('mail.from_name', '')),
            ],
        ]);
    }

    public function updateBusiness(Request $request): never
    {
        $data = $request->all();
        $optionErrors = [];
        $currencyCodes = ReferenceData::currencyCodes(app()->db()->fetchAll('SELECT * FROM currencies WHERE is_active = 1 ORDER BY code'));

        $currency = SignedOption::verify('currency', $data['default_currency'] ?? '', $currencyCodes);
        if ($currency === null) {
            $optionErrors['default_currency'] = 'Choose a valid currency.';
        }
        $data['default_currency'] = $currency ?? '';

        if (($data['country'] ?? '') !== '') {
            $country = SignedOption::verify('country', $data['country'], ReferenceData::countries());
            if ($country === null) {
                $optionErrors['country'] = 'Choose a valid country.';
            }
            $data['country'] = $country ?? '';
        }

        $validator = (new Validator($data))
            ->required('business_name', 'Business name')
            ->max('business_name', 190, 'Business name')
            ->required('email', 'Email')
            ->max('email', 190, 'Email')
            ->email('email', 'Email')
            ->required('default_currency', 'Default currency');

        if ($validator->fails() || $optionErrors !== []) {
            $this->backWithErrors(array_merge($validator->errors(), $optionErrors), $data);
        }

        try {
            $logoPath = (new UploadService())->store($request->file('logo') ?? [], 'logos');
            if ($logoPath) {
                app()->db()->execute('UPDATE business_settings SET logo_path = ?, updated_at = NOW() WHERE id = ?', [$logoPath, (int) $data['id']]);
            }
        } catch (\Throwable $exception) {
            $this->backWithErrors(['logo' => $exception->getMessage()], $data);
        }

        (new SettingsService())->updateBusiness($data);
        AuditLogger::log('settings.business_updated', 'business_settings', (int) $data['id']);
        Session::flash('success', 'Business settings updated.');
        $this->redirect('/settings');
    }

    public function updateGeneral(Request $request): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->integer('data_retention_years', 'Data retention years')
            ->max('invoice_prefix', 20, 'Invoice prefix')
            ->max('quote_prefix', 20, 'Quote prefix');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $settings = new SettingsService();
        foreach (['invoice_prefix', 'quote_prefix', 'payment_methods', 'data_retention_years'] as $key) {
            $settings->set($key, (string) $request->input($key, ''));
        }

        AuditLogger::log('settings.general_updated');
        Session::flash('success', 'General settings updated.');
        $this->redirect('/settings');
    }

    public function updateMail(Request $request): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('mail_transport', 'Mail transport')
            ->in('mail_transport', ['mail', 'smtp'], 'Mail transport')
            ->integer('mail_port', 'Mail port')
            ->in('mail_encryption', ['tls', 'ssl', ''], 'Mail encryption')
            ->required('mail_from_email', 'From email')
            ->max('mail_from_email', 190, 'From email')
            ->email('mail_from_email', 'From email');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $settings = new SettingsService();
        foreach (['mail_transport', 'mail_host', 'mail_port', 'mail_username', 'mail_encryption', 'mail_from_email', 'mail_from_name'] as $key) {
            $settings->set($key, (string) ($data[$key] ?? ''), str_contains($key, 'password') || str_contains($key, 'username'));
        }
        if (($data['mail_password'] ?? '') !== '') {
            $settings->set('mail_password', (string) $data['mail_password'], true);
        }

        AuditLogger::log('settings.mail_updated');
        Session::flash('success', 'Mail settings updated.');
        $this->redirect('/settings');
    }

    public function testMail(Request $request): never
    {
        $to = trim((string) $request->input('test_email', ''));
        if ($to === '') {
            $to = (string) (Auth::user()['email'] ?? '');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('errors', ['Enter a valid email address to send the test to.']);
            $this->redirect('/settings');
        }

        $mailer = new MailerService();
        $sent = $mailer->send(
            $to,
            'LedgerFlow test email',
            "This is a test email from LedgerFlow.\n\nIf you received this, your mail settings are working correctly.\n\nSent " . date(DATE_ATOM) . '.'
        );

        if ($sent) {
            AuditLogger::log('settings.mail_test_sent', null, null, ['to' => $to]);
            Session::flash('success', "Test email sent to {$to}. Check the inbox (and spam folder) to confirm delivery.");
        } else {
            AuditLogger::log('settings.mail_test_failed', null, null, ['to' => $to, 'error' => $mailer->lastError()]);
            Session::flash('errors', ['SMTP test failed: ' . ($mailer->lastError() ?? 'Unknown error.')]);
        }

        $this->redirect('/settings');
    }

    public function addTax(Request $request): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('name', 'Tax name')
            ->max('name', 120, 'Tax name')
            ->required('rate', 'Tax rate')
            ->numeric('rate', 'Tax rate');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        app()->db()->execute(
            'INSERT INTO taxes (name, rate, registration_number, is_compound, is_active) VALUES (?, ?, ?, ?, 1)',
            [$data['name'], (float) $data['rate'], $data['registration_number'] ?? null, isset($data['is_compound']) ? 1 : 0]
        );

        AuditLogger::log('settings.tax_created');
        Session::flash('success', 'Tax setting added.');
        $this->redirect('/settings');
    }

    public function addUser(Request $request): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('name', 'Name')
            ->max('name', 190, 'Name')
            ->required('email', 'Email')
            ->max('email', 190, 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password')
            ->required('role_id', 'Role')
            ->integer('role_id', 'Role');

        $errors = $validator->errors();
        if (strlen((string) $data['password']) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }

        if (!isset($errors['role_id'])) {
            $targetRole = app()->db()->fetch('SELECT name FROM roles WHERE id = ?', [(int) $data['role_id']]);
            if (!$targetRole) {
                $errors['role_id'] = 'Choose a valid role.';
            } elseif ($targetRole['name'] === 'owner' && (Auth::user()['role_name'] ?? '') !== 'owner') {
                $errors['role_id'] = 'Only an owner can grant the owner role.';
            }
        }

        if ($errors !== []) {
            $this->backWithErrors($errors, $data);
        }

        $id = app()->db()->insert(
            'INSERT INTO users (role_id, name, email, password_hash) VALUES (?, ?, ?, ?)',
            [(int) $data['role_id'], $data['name'], mb_strtolower(trim((string) $data['email'])), password_hash((string) $data['password'], PASSWORD_DEFAULT)]
        );

        $token = bin2hex(random_bytes(32));
        app()->db()->execute(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            [$id, hash('sha256', $token), date('Y-m-d H:i:s', time() + 86400)]
        );
        $link = base_url('/verify-email?token=' . $token . '&email=' . urlencode((string) $data['email']));
        (new MailerService())->send(
            (string) $data['email'],
            'Verify your LedgerFlow account',
            "Hello {$data['name']},\n\nVerify your LedgerFlow account using this link:\n{$link}\n\nThis link expires in 24 hours."
        );

        AuditLogger::log('settings.user_created', 'user', $id);
        Session::flash('success', 'User created and verification email sent.');
        $this->redirect('/settings');
    }
}

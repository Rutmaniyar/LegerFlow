<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\SignedOption;
use App\Core\Validator;
use App\Services\InstallerService;
use App\Services\MailerService;
use App\Support\ReferenceData;

final class InstallController extends Controller
{
    public function show(): string
    {
        if (app()->isInstalled()) {
            $this->redirect('/login');
        }

        $installer = new InstallerService();
        return $this->view('install', [
            'title' => 'Install LedgerFlow',
            'checks' => $installer->requirements(),
            'timezones' => ReferenceData::timezones(),
            'currencies' => ReferenceData::currencies(),
            'countries' => ReferenceData::countries(),
        ], 'layouts/guest');
    }

    public function store(Request $request): never
    {
        if (app()->isInstalled()) {
            $this->redirect('/login');
        }

        $data = $request->all();
        $optionErrors = [];

        $timezone = SignedOption::verify('timezone', $data['timezone'] ?? '', ReferenceData::timezones());
        if ($timezone === null) {
            $optionErrors['timezone'] = 'Choose a valid timezone.';
        }
        $data['timezone'] = $timezone ?? '';

        $currency = SignedOption::verify('currency', $data['currency'] ?? '', ReferenceData::currencyCodes(ReferenceData::currencies()));
        if ($currency === null) {
            $optionErrors['currency'] = 'Choose a valid currency.';
        }
        $data['currency'] = $currency ?? '';

        if (($data['business_country'] ?? '') !== '') {
            $country = SignedOption::verify('country', $data['business_country'], ReferenceData::countries());
            if ($country === null) {
                $optionErrors['business_country'] = 'Choose a valid country.';
            }
            $data['business_country'] = $country ?? '';
        }

        $validator = (new Validator($data))
            ->required('site_url', 'Site URL')
            ->max('site_url', 255, 'Site URL')
            ->required('timezone', 'Timezone')
            ->required('db_host', 'Database host')
            ->max('db_host', 191, 'Database host')
            ->integer('db_port', 'Database port')
            ->required('db_name', 'Database name')
            ->max('db_name', 64, 'Database name')
            ->required('db_user', 'Database username')
            ->max('db_user', 32, 'Database username')
            ->required('currency', 'Currency')
            ->required('business_name', 'Business name')
            ->max('business_name', 190, 'Business name')
            ->required('business_email', 'Business email')
            ->max('business_email', 190, 'Business email')
            ->email('business_email', 'Business email')
            ->max('business_phone', 80, 'Business phone')
            ->max('business_address', 190, 'Business address')
            ->max('business_city', 120, 'City')
            ->max('business_region', 120, 'Region')
            ->max('business_postal_code', 40, 'Postal code')
            ->numeric('tax_rate', 'Default tax rate')
            ->decimalPlaces('tax_rate', 2, 'Default tax rate')
            ->between('tax_rate', 0, 100, 'Default tax rate')
            ->required('admin_name', 'Admin name')
            ->max('admin_name', 160, 'Admin name')
            ->required('admin_email', 'Admin email')
            ->max('admin_email', 190, 'Admin email')
            ->email('admin_email', 'Admin email')
            ->required('admin_password', 'Admin password')
            ->in('mail_transport', ['mail', 'smtp'], 'Mail transport')
            ->integer('mail_port', 'SMTP port')
            ->in('mail_encryption', ['tls', 'ssl', ''], 'SMTP encryption')
            ->max('mail_host', 191, 'SMTP host')
            ->max('mail_from_email', 190, 'From email')
            ->email('mail_from_email', 'From email');

        if (($data['mail_transport'] ?? 'mail') === 'smtp') {
            $validator->required('mail_host', 'SMTP host')->required('mail_port', 'SMTP port');
        }

        if (($data['admin_password'] ?? '') !== ($data['admin_password_confirmation'] ?? '')) {
            $errors = array_merge($validator->errors(), $optionErrors);
            $errors['admin_password_confirmation'] = 'Password confirmation does not match.';
            Session::flash('errors', $errors);
            Session::flash('_old', $data);
            $this->redirect('/install');
        }

        if (strlen((string) ($data['admin_password'] ?? '')) < 10) {
            $errors = array_merge($validator->errors(), $optionErrors);
            $errors['admin_password'] = 'Admin password must be at least 10 characters.';
            Session::flash('errors', $errors);
            Session::flash('_old', $data);
            $this->redirect('/install');
        }

        if ($validator->fails() || $optionErrors !== []) {
            Session::flash('errors', array_merge($validator->errors(), $optionErrors));
            Session::flash('_old', $data);
            $this->redirect('/install');
        }

        try {
            (new InstallerService())->install($data);
        } catch (\Throwable $exception) {
            error_log('LedgerFlow install failed: ' . $exception->getMessage());
            Session::flash('errors', [$exception->getMessage()]);
            Session::flash('_old', $data);
            $this->redirect('/install');
        }

        Session::flash('success', 'Installation complete. Sign in with your administrator account.');
        $this->redirect('/login');
    }

    public function testDatabaseConnection(Request $request): never
    {
        if (app()->isInstalled()) {
            Response::json(['ok' => false, 'message' => 'LedgerFlow is already installed.'], 403);
        }

        $data = $request->all();

        try {
            (new InstallerService())->testDatabase([
                'host' => $data['db_host'] ?? '',
                'port' => (int) ($data['db_port'] ?: 3306),
                'name' => $data['db_name'] ?? '',
                'user' => $data['db_user'] ?? '',
                'password' => $data['db_password'] ?? '',
                'charset' => 'utf8mb4',
            ]);
            Response::json(['ok' => true, 'message' => 'Connected successfully. Database credentials are valid.']);
        } catch (\Throwable $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function testSmtpConnection(Request $request): never
    {
        if (app()->isInstalled()) {
            Response::json(['ok' => false, 'message' => 'LedgerFlow is already installed.'], 403);
        }

        $data = $request->all();
        $mailer = new MailerService();
        $ok = $mailer->testSmtpConnection([
            'host' => trim((string) ($data['mail_host'] ?? '')),
            'port' => (int) ($data['mail_port'] ?: 587),
            'username' => (string) ($data['mail_username'] ?? ''),
            'password' => (string) ($data['mail_password'] ?? ''),
            'encryption' => (string) ($data['mail_encryption'] ?? 'tls'),
        ]);

        Response::json([
            'ok' => $ok,
            'message' => $ok ? 'SMTP connection succeeded.' : ($mailer->lastError() ?? 'SMTP connection failed.'),
        ]);
    }
}

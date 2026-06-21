<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UpdateController;

$app = require dirname(__DIR__) . '/bootstrap.php';
$router = $app->router();

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'store'], ['csrf']);

if (!$app->isInstalled()) {
    $path = (new Request())->path();
    if ($path !== '/install') {
        Response::redirect('/install');
    }
}

$router->get('/', static fn () => Response::redirect('/dashboard'));

$router->get('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/login', [AuthController::class, 'authenticate'], ['guest', 'csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->get('/forgot-password', [AuthController::class, 'forgot'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'sendReset'], ['guest', 'throttle:5,15', 'csrf']);
$router->get('/reset-password', [AuthController::class, 'reset'], ['guest']);
$router->post('/reset-password', [AuthController::class, 'updatePassword'], ['guest', 'throttle:5,15', 'csrf']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail'], ['guest']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'can:dashboard.view']);

$router->get('/clients', [ClientController::class, 'index'], ['auth', 'can:clients.view']);
$router->post('/clients', [ClientController::class, 'store'], ['auth', 'can:clients.manage', 'throttle:60,1', 'csrf']);
$router->get('/clients/{id}', [ClientController::class, 'show'], ['auth', 'can:clients.view']);
$router->post('/clients/{id}', [ClientController::class, 'update'], ['auth', 'can:clients.manage', 'throttle:60,1', 'csrf']);
$router->post('/clients/{id}/anonymize', [ClientController::class, 'anonymize'], ['auth', 'can:clients.manage', 'throttle:10,5', 'csrf']);
$router->get('/clients/{id}/export', [ClientController::class, 'export'], ['auth', 'can:clients.view']);

$router->get('/quotes', [QuoteController::class, 'index'], ['auth', 'can:quotes.view']);
$router->get('/quotes/create', [QuoteController::class, 'create'], ['auth', 'can:quotes.manage']);
$router->post('/quotes', [QuoteController::class, 'store'], ['auth', 'can:quotes.manage', 'throttle:60,1', 'csrf']);
$router->get('/quotes/{id}', [QuoteController::class, 'show'], ['auth', 'can:quotes.view']);
$router->get('/quotes/{id}/pdf', [QuoteController::class, 'pdf'], ['auth', 'can:quotes.view']);
$router->post('/quotes/{id}/send', [QuoteController::class, 'send'], ['auth', 'can:quotes.manage', 'throttle:20,5', 'csrf']);
$router->post('/quotes/{id}/convert', [QuoteController::class, 'convert'], ['auth', 'can:quotes.manage', 'throttle:30,1', 'csrf']);

$router->get('/invoices', [InvoiceController::class, 'index'], ['auth', 'can:invoices.view']);
$router->get('/invoices/create', [InvoiceController::class, 'create'], ['auth', 'can:invoices.manage']);
$router->post('/invoices', [InvoiceController::class, 'store'], ['auth', 'can:invoices.manage', 'throttle:60,1', 'csrf']);
$router->get('/invoices/{id}', [InvoiceController::class, 'show'], ['auth', 'can:invoices.view']);
$router->get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf'], ['auth', 'can:invoices.view']);
$router->post('/invoices/{id}/send', [InvoiceController::class, 'send'], ['auth', 'can:invoices.manage', 'throttle:20,5', 'csrf']);
$router->post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment'], ['auth', 'can:payments.manage', 'throttle:60,1', 'csrf']);

$router->get('/recurring', [RecurringController::class, 'index'], ['auth', 'can:invoices.manage']);
$router->post('/recurring', [RecurringController::class, 'store'], ['auth', 'can:invoices.manage', 'throttle:30,1', 'csrf']);
$router->get('/expenses', [ExpenseController::class, 'index'], ['auth', 'can:expenses.manage']);
$router->post('/expenses', [ExpenseController::class, 'store'], ['auth', 'can:expenses.manage', 'throttle:60,1', 'csrf']);

$router->get('/reports', [ReportController::class, 'index'], ['auth', 'can:reports.view']);

$router->get('/settings', [SettingsController::class, 'index'], ['auth', 'can:settings.manage']);
$router->post('/settings/business', [SettingsController::class, 'updateBusiness'], ['auth', 'can:settings.manage', 'throttle:30,1', 'csrf']);
$router->post('/settings/general', [SettingsController::class, 'updateGeneral'], ['auth', 'can:settings.manage', 'throttle:30,1', 'csrf']);
$router->post('/settings/mail', [SettingsController::class, 'updateMail'], ['auth', 'can:settings.manage', 'throttle:20,5', 'csrf']);
$router->post('/settings/mail/test', [SettingsController::class, 'testMail'], ['auth', 'can:settings.manage', 'throttle:5,5', 'csrf']);
$router->post('/settings/taxes', [SettingsController::class, 'addTax'], ['auth', 'can:settings.manage', 'throttle:30,1', 'csrf']);
$router->post('/settings/users', [SettingsController::class, 'addUser'], ['auth', 'can:settings.manage', 'throttle:20,5', 'csrf']);

$router->post('/settings/update/check', [UpdateController::class, 'check'], ['auth', 'can:system.update', 'throttle:10,5', 'csrf']);
$router->post('/settings/update/apply', [UpdateController::class, 'apply'], ['auth', 'can:system.update', 'throttle:3,30', 'csrf']);

$router->get('/privacy', [PrivacyController::class, 'index'], ['auth', 'can:settings.manage']);
$router->post('/privacy', [PrivacyController::class, 'store'], ['auth', 'can:settings.manage', 'throttle:30,1', 'csrf']);
$router->post('/privacy/{id}', [PrivacyController::class, 'update'], ['auth', 'can:settings.manage', 'throttle:30,1', 'csrf']);

$router->get('/recurring/run', [RecurringController::class, 'run'], ['throttle:60,1']);

$router->dispatch(new Request());

<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\SignedOption;
use App\Core\Validator;
use App\Core\View;
use App\Services\InvoiceCalculator;
use App\Services\InstallerService;
use App\Services\PdfService;
use App\Support\ReferenceData;
use App\Support\TableSort;

$failures = 0;

function assert_same(mixed $expected, mixed $actual, string $label): void
{
    global $failures;
    if ($expected !== $actual) {
        $failures++;
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
    }
}

function assert_contains(string $needle, string $haystack, string $label): void
{
    global $failures;
    if (!str_contains($haystack, $needle)) {
        $failures++;
        fwrite(STDERR, "FAIL {$label}: expected content to contain {$needle}" . PHP_EOL);
    }
}

function assert_not_contains(string $needle, string $haystack, string $label): void
{
    global $failures;
    if (str_contains($haystack, $needle)) {
        $failures++;
        fwrite(STDERR, "FAIL {$label}: expected content not to contain {$needle}" . PHP_EOL);
    }
}

$calculator = new InvoiceCalculator();
$result = $calculator->fromRequest([
    'item_description' => ['Design', 'Hosting'],
    'item_quantity' => ['2', '1'],
    'item_unit_price' => ['100', '50'],
    'item_discount_type' => ['percent', 'percent'],
    'item_discount_value' => ['10', '0'],
    'item_tax_rate' => ['20', '0'],
]);

assert_same(250.0, $result['subtotal'], 'subtotal');
assert_same(20.0, $result['discount_total'], 'discount total');
assert_same(36.0, $result['tax_total'], 'tax total');
assert_same(266.0, $result['total'], 'grand total');
assert_same(2, count($result['items']), 'line item count');
assert_same('percent', $result['items'][0]['discount_type'], 'percentage discount type retained');
assert_same(10.0, $result['items'][0]['discount_value'], 'percentage discount value retained');

$amountDiscount = $calculator->fromRequest([
    'item_description' => ['Consulting'],
    'item_quantity' => ['2'],
    'item_unit_price' => ['100'],
    'item_discount_type' => ['amount'],
    'item_discount_value' => ['25'],
    'item_tax_rate' => ['20'],
]);
assert_same(200.0, $amountDiscount['subtotal'], 'amount discount subtotal');
assert_same(25.0, $amountDiscount['discount_total'], 'amount discount total');
assert_same(35.0, $amountDiscount['tax_total'], 'amount discount is applied before tax');
assert_same(210.0, $amountDiscount['total'], 'amount discount grand total');
assert_same('amount', $amountDiscount['items'][0]['discount_type'], 'amount discount type retained');

$invalidDiscounts = $calculator->fromRequest([
    'item_description' => ['Too precise', 'Too high', 'Too much'],
    'item_quantity' => ['1', '1', '1'],
    'item_unit_price' => ['100', '100', '10'],
    'item_discount_type' => ['percent', 'percent', 'amount'],
    'item_discount_value' => ['10.001', '101', '10.01'],
    'item_tax_rate' => ['20', '20.001', '20'],
]);
assert_same([], $invalidDiscounts['items'], 'invalid discount and tax precision is rejected');
assert_same(4, count($invalidDiscounts['errors']), 'discount validation reports each invalid rule');

$empty = $calculator->fromRequest([
    'item_description' => [''],
    'item_quantity' => ['1'],
    'item_unit_price' => ['100'],
]);
assert_same([], $empty['items'], 'empty lines ignored');
assert_same('20%', percentage('20.00'), 'whole percentage formatting');
assert_same('20.5%', percentage('20.50'), 'single-decimal percentage formatting');
assert_same('20.25%', percentage('20.254'), 'percentage formatting is limited to two decimals');

$sortColumns = [
    'issue_date' => ['column' => 'documents.issue_date', 'default' => 'desc'],
    'client' => ['column' => 'clients.name', 'default' => 'asc'],
    'valid_until' => ['column' => 'documents.valid_until', 'default' => 'asc', 'nulls_last' => true],
];
assert_same([
    'key' => 'client',
    'column' => 'clients.name',
    'direction' => 'desc',
    'nulls_last' => false,
], TableSort::resolve('client', 'desc', $sortColumns, 'issue_date'), 'valid sort parameters resolve through whitelist');
assert_same('issue_date', TableSort::resolve('DROP TABLE', 'asc', $sortColumns, 'issue_date')['key'], 'invalid sort key falls back to document date');
assert_same('desc', TableSort::resolve('DROP TABLE', 'asc', $sortColumns, 'issue_date')['direction'], 'invalid sort key restores default direction');
assert_same('issue_date', TableSort::resolve('client', 'sideways', $sortColumns, 'issue_date')['key'], 'invalid direction restores default sort');
assert_same(true, TableSort::resolve('valid_until', 'asc', $sortColumns, 'issue_date')['nulls_last'], 'nullable date sort retains nulls-last behavior');

$registerSortKeys = [
    'invoices' => ['invoice_number', 'client', 'status', 'issue_date', 'due_date', 'amount_paid', 'balance_due'],
    'quotes' => ['quote_number', 'client', 'status', 'issue_date', 'valid_until', 'total'],
    'expenses' => ['expense_date', 'vendor', 'category', 'amount'],
];
foreach ($registerSortKeys as $register => $keys) {
    $columns = array_fill_keys($keys, ['column' => 'safe_column', 'default' => 'asc']);
    foreach ($keys as $key) {
        foreach (['asc', 'desc'] as $direction) {
            $resolved = TableSort::resolve($key, $direction, $columns, $keys[0]);
            assert_same($key, $resolved['key'], "{$register} {$key} sort key");
            assert_same($direction, $resolved['direction'], "{$register} {$key} {$direction} direction");
        }
    }
}

$invoiceController = (string) file_get_contents(APP_PATH . '/Http/Controllers/InvoiceController.php');
$quoteController = (string) file_get_contents(APP_PATH . '/Http/Controllers/QuoteController.php');
$expenseController = (string) file_get_contents(APP_PATH . '/Http/Controllers/ExpenseController.php');
assert_contains("invoices.id {\$sorting['direction']}", $invoiceController, 'invoice sorting has a stable id tie-breaker');
assert_contains("quotes.id {\$sorting['direction']}", $quoteController, 'quote sorting has a stable id tie-breaker');
assert_contains("expenses.id ' . \$sorting['direction']", $expenseController, 'expense sorting has a stable id tie-breaker');
assert_contains('IS NULL ASC', $quoteController, 'undated quote validity values sort last');

ob_start();
View::partial('partials/sortable-heading', [
    'path' => '/invoices',
    'query' => ['status' => 'paid', 'page' => 3],
    'sort' => 'client',
    'direction' => 'asc',
    'key' => 'client',
    'defaultDirection' => 'asc',
    'label' => 'Client',
]);
$sortHeading = (string) ob_get_clean();
assert_contains('aria-sort="ascending"', $sortHeading, 'sortable heading exposes current direction');
assert_contains('status=paid&amp;sort=client&amp;direction=desc', $sortHeading, 'sortable heading preserves filters and reverses direction');
assert_not_contains('page=', $sortHeading, 'changing sort returns to page one');

$taxes = [
    ['id' => 1, 'name' => 'VAT', 'rate' => '20.00', 'is_active' => 1],
    ['id' => 2, 'name' => 'Reduced', 'rate' => '5.00', 'is_active' => 0],
];
ob_start();
View::partial('partials/line-item-row', ['taxes' => $taxes, 'defaultTax' => $taxes[0]]);
$defaultTaxLine = (string) ob_get_clean();
assert_contains('value="20.00" selected', $defaultTaxLine, 'new line item preselects the active default tax');
assert_not_contains('Reduced · 5% · Inactive', $defaultTaxLine, 'new line item excludes inactive taxes');

ob_start();
View::partial('partials/line-item-row', [
    'taxes' => $taxes,
    'defaultTax' => $taxes[0],
    'item' => ['discount_type' => 'amount', 'discount_value' => '12.5', 'tax_rate' => '5.00'],
]);
$historicTaxLine = (string) ob_get_clean();
assert_contains('value="amount" selected', $historicTaxLine, 'saved amount discount overrides the default type');
assert_contains('value="12.50" data-discount-value', $historicTaxLine, 'saved amount discount renders in document currency precision');
assert_contains('value="5.00" selected', $historicTaxLine, 'saved inactive tax overrides the current default');
assert_contains('Reduced · 5% · Inactive', $historicTaxLine, 'saved inactive tax stays visible on a draft');

$currencyToken = SignedOption::seal('currency', 'USD');
assert_same('USD', SignedOption::verify('currency', $currencyToken, ReferenceData::currencyCodes(ReferenceData::currencies())), 'signed currency verifies');
assert_same(null, SignedOption::verify('currency', $currencyToken . 'tampered', ReferenceData::currencyCodes(ReferenceData::currencies())), 'tampered currency rejected');
assert_same(null, SignedOption::verify('currency', SignedOption::seal('currency', 'XXX'), ReferenceData::currencyCodes(ReferenceData::currencies())), 'unknown currency rejected');
assert_same('UTC', SignedOption::verify('timezone', SignedOption::seal('timezone', 'UTC'), ReferenceData::timezones()), 'signed timezone verifies');
assert_same('United States', SignedOption::verify('country', SignedOption::seal('country', 'United States'), ReferenceData::countries()), 'signed country verifies');

$valid = (new Validator([
    'date' => '2026-05-25',
    'id' => '12',
    'status' => 'draft',
]))->date('date', 'Date')->integer('id', 'ID')->in('status', ['draft', 'sent'], 'Status');
assert_same(false, $valid->fails(), 'new validator rules accept valid values');

$invalid = (new Validator([
    'date' => '2026-02-31',
    'id' => '12.4',
    'status' => 'paid',
]))->date('date', 'Date')->integer('id', 'ID')->in('status', ['draft', 'sent'], 'Status');
assert_same(true, $invalid->fails(), 'new validator rules reject invalid values');

$validTax = (new Validator(['rate' => '20.25']))
    ->numeric('rate', 'Tax rate')
    ->decimalPlaces('rate', 2, 'Tax rate')
    ->between('rate', 0, 100, 'Tax rate');
assert_same(false, $validTax->fails(), 'tax rate accepts zero to one hundred with two decimals');

$invalidTax = (new Validator(['rate' => '100.001']))
    ->numeric('rate', 'Tax rate')
    ->decimalPlaces('rate', 2, 'Tax rate')
    ->between('rate', 0, 100, 'Tax rate');
assert_same(true, $invalidTax->fails(), 'tax rate rejects excessive precision and values over one hundred');

$migrationPath = DATABASE_PATH . '/migrations/20260826_000001_tax_discount_modes.sql';
$migrationSql = (string) file_get_contents($migrationPath);
$splitSql = (new ReflectionClass(InstallerService::class))->getMethod('splitSql');
$splitSql->setAccessible(true);
$migrationStatements = array_values(array_filter(array_map('trim', $splitSql->invoke(new InstallerService(), $migrationSql))));
assert_same(4, count($migrationStatements), 'tax and discount migration parses into four statements');
assert_contains('CHANGE COLUMN discount_rate discount_value', $migrationSql, 'upgrade migration retains existing discount data');
assert_contains('runMigrations(app()->db())', (string) file_get_contents(APP_PATH . '/Services/UpdateService.php'), 'installed-system updater runs new migrations');
assert_contains('discount_type, discount_value', (string) file_get_contents(APP_PATH . '/Services/InvoiceService.php'), 'quote conversion retains discount type and value');
assert_contains('discount_type, discount_value', (string) file_get_contents(APP_PATH . '/Http/Controllers/RecurringController.php'), 'recurring generation retains discount type and value');

$pdfReflection = new ReflectionClass(PdfService::class);
$render = $pdfReflection->getMethod('legacyRender');
$render->setAccessible(true);

$baseInvoice = [
    'invoice_number' => 'INV-001',
    'client_name' => 'Client',
    'client_email' => null,
    'billing_address' => null,
    'issue_date' => '2026-05-25',
    'due_date' => '2026-06-25',
    'status' => 'sent',
    'currency' => 'USD',
    'subtotal' => '100.00',
    'discount_total' => '0.00',
    'tax_total' => '0.00',
    'total' => '100.00',
    'amount_paid' => '0.00',
    'balance_due' => '100.00',
    'paid_at' => null,
    'notes' => null,
    'terms' => null,
];

$zeroAdjustmentPdf = $render->invoke(new PdfService(), ['business_name' => 'Acme'], $baseInvoice, [[
    'description' => 'Service',
    'quantity' => '1',
    'unit_price' => '100.00',
    'tax_rate' => '0.00',
    'line_total' => '100.00',
]], [], 'invoice_number', true);
assert_contains('%PDF-1.4', $zeroAdjustmentPdf, 'PDF has a valid header');
assert_not_contains('(Discount)', $zeroAdjustmentPdf, 'PDF omits zero discount row');
assert_not_contains('(Tax)', $zeroAdjustmentPdf, 'PDF omits zero tax row and item tax column');

$nonZeroAdjustmentPdf = $render->invoke(new PdfService(), ['business_name' => 'Acme'], array_merge($baseInvoice, [
    'invoice_number' => 'INV-002',
    'discount_total' => '5.00',
    'tax_total' => '9.50',
    'total' => '104.50',
    'balance_due' => '104.50',
]), [[
    'description' => 'Service',
    'quantity' => '1',
    'unit_price' => '100.00',
    'tax_rate' => '10.00',
    'line_total' => '104.50',
]], [], 'invoice_number', true);
assert_contains('(Discount)', $nonZeroAdjustmentPdf, 'PDF includes non-zero discount row');
assert_contains('(-5.00 USD)', $nonZeroAdjustmentPdf, 'PDF includes formatted discount amount');
assert_contains('(Tax)', $nonZeroAdjustmentPdf, 'PDF includes non-zero tax row');
assert_contains('(9.50 USD)', $nonZeroAdjustmentPdf, 'PDF includes formatted tax amount');
assert_contains('(10%)', $nonZeroAdjustmentPdf, 'PDF includes item tax percentage when document tax is non-zero');

$paidInvoicePdf = $render->invoke(new PdfService(), ['business_name' => 'Acme'], array_merge($baseInvoice, [
    'status' => 'paid',
    'paid_at' => '2026-06-01 10:00:00',
    'amount_paid' => '100.00',
    'balance_due' => '0.00',
]), [[
    'description' => 'Service',
    'quantity' => '1',
    'unit_price' => '100.00',
    'tax_rate' => '0.00',
    'line_total' => '100.00',
]], [[
    'payment_date' => '2026-06-01',
    'method' => 'Bank transfer',
    'reference' => 'TX-1',
    'amount' => '100.00',
]], 'invoice_number', true);
assert_contains('(PAID)', $paidInvoicePdf, 'PDF includes a PAID stamp for paid invoices');
assert_contains('(PAYMENT HISTORY)', $paidInvoicePdf, 'PDF includes payment history for invoices with payments');
assert_contains('(Bank transfer)', $paidInvoicePdf, 'PDF lists each recorded payment method');

$htmlBusiness = ['business_name' => 'Acme', 'brand_color' => '#0ea394', 'default_currency' => 'USD'];
$htmlInvoice = array_merge($baseInvoice, ['status' => 'paid', 'paid_at' => '2026-06-01 10:00:00', 'amount_paid' => '100.00', 'balance_due' => '0.00']);
ob_start();
$html = View::render('pdf/document', [
    'business' => $htmlBusiness,
    'document' => $htmlInvoice,
    'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00', 'tax_rate' => '0.00', 'line_total' => '100.00']],
    'payments' => [['payment_date' => '2026-06-01', 'method' => 'Bank transfer', 'reference' => 'TX-1', 'amount' => '100.00']],
    'numberKey' => 'invoice_number',
    'isInvoice' => true,
    'defaultCurrency' => 'USD',
    'forPdf' => false,
], '');
ob_end_clean();
assert_contains('<!doctype html>', $html, 'HTML template renders a full document');
assert_contains('PAID', $html, 'HTML template shows the PAID stamp for paid invoices');
assert_contains('PAYMENT HISTORY', $html, 'HTML template includes payment history');
assert_not_contains('Billed in', $html, 'HTML template omits the foreign-currency badge when currencies match');

ob_start();
$foreignCurrencyHtml = View::render('pdf/document', [
    'business' => $htmlBusiness,
    'document' => array_merge($baseInvoice, ['currency' => 'GBP']),
    'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00', 'tax_rate' => '0.00', 'line_total' => '100.00']],
    'payments' => [],
    'numberKey' => 'invoice_number',
    'isInvoice' => true,
    'defaultCurrency' => 'USD',
    'forPdf' => false,
], '');
ob_end_clean();
assert_contains('Billed in GBP', $foreignCurrencyHtml, 'HTML template flags an invoice currency different from the business default');

if ($failures > 0) {
    exit(1);
}

echo "All tests passed." . PHP_EOL;

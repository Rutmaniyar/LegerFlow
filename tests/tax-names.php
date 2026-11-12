<?php

declare(strict_types=1);

use App\Core\Session;
use App\Core\View;
use App\Services\InvoiceCalculator;
use App\Services\PdfService;

$taxDefinitions = [
    ['id' => 11, 'name' => 'VAT', 'rate' => '20.00', 'is_active' => 1],
    ['id' => 12, 'name' => 'GST', 'rate' => '20.00', 'is_active' => 1],
    ['id' => 13, 'name' => 'GST', 'rate' => '18.00', 'is_active' => 1],
    ['id' => 14, 'name' => 'Tax', 'rate' => '10.00', 'is_active' => 1],
    ['id' => 15, 'name' => 'VAT', 'rate' => '5.00', 'is_active' => 1],
    ['id' => 16, 'name' => 'Zero rated', 'rate' => '0.00', 'is_active' => 1],
    ['id' => 17, 'name' => 'Retired VAT', 'rate' => '20.00', 'is_active' => 0],
];
$taxRequest = [
    'item_description' => ['Consulting'],
    'item_quantity' => ['2'],
    'item_unit_price' => ['100'],
    'item_discount_type' => ['amount'],
    'item_discount_value' => ['25'],
    'item_tax_id' => ['11'],
    'item_tax_rate' => ['99'],
    'item_tax_name' => ['Untrusted name'],
];
$taxCalculator = new InvoiceCalculator();
foreach ([11 => ['VAT', 20.0], 12 => ['GST', 20.0], 13 => ['GST', 18.0], 14 => ['Tax', 10.0], 15 => ['VAT', 5.0], 16 => ['Zero rated', 0.0], 0 => [null, 0.0]] as $id => [$name, $rate]) {
    $calculated = $taxCalculator->fromRequest(array_replace($taxRequest, ['item_tax_id' => [(string) $id]]), $taxDefinitions);
    assert_same([], $calculated['errors'], "tax {$id} is accepted");
    assert_same($name, $calculated['items'][0]['tax_name'], "tax {$id} name comes from its definition");
    assert_same($rate, $calculated['items'][0]['tax_rate'], "tax {$id} rate comes from its definition");
    assert_same(round(175 * $rate / 100, 2), $calculated['tax_total'], "tax {$id} uses the discounted subtotal");
    assert_same($rate > 0 ? $name : 'Tax', document_tax_label($calculated['items']), "tax {$id} document label");
}

foreach (['', '17', '999', '11oops', '11.0', '1.1e1', '-11', 'saved:81', ['11']] as $selection) {
    $calculated = $taxCalculator->fromRequest(array_replace($taxRequest, ['item_tax_id' => [$selection]]), $taxDefinitions);
    assert_same([], $calculated['items'], 'invalid, inactive, malformed or foreign saved tax is rejected');
    assert_same(1, count($calculated['errors']), 'invalid tax produces one actionable error');
}
$oldForm = $taxRequest;
unset($oldForm['item_tax_id']);
assert_same([], $taxCalculator->fromRequest($oldForm, $taxDefinitions)['items'], 'outdated rate-only forms cannot silently remove tax');

$savedTaxItems = [
    ['id' => 81, 'tax_name' => null, 'tax_rate' => '20.00'],
    ['id' => 82, 'tax_name' => 'Retired VAT', 'tax_rate' => '20.00'],
    ['id' => 83, 'tax_name' => 'Deleted tax', 'tax_rate' => '7.25'],
    ['id' => 84, 'tax_name' => 'VAT', 'tax_rate' => '17.50'],
];
foreach ($savedTaxItems as $savedItem) {
    $calculated = $taxCalculator->fromRequest(array_replace($taxRequest, ['item_tax_id' => ['saved:' . $savedItem['id']]]), $taxDefinitions, $savedTaxItems);
    assert_same([], $calculated['errors'], 'historical selection from this document is accepted');
    assert_same($savedItem['tax_name'], $calculated['items'][0]['tax_name'], 'historical name is not inferred or overwritten');
    assert_same((float) $savedItem['tax_rate'], $calculated['items'][0]['tax_rate'], 'historical rate is preserved');
}
$duplicate = $taxCalculator->fromRequest(array_replace($taxRequest, [
    'item_description' => ['First line', 'Second line'],
    'item_tax_id' => ['saved:81', 'saved:81'],
]), $taxDefinitions, $savedTaxItems);
assert_same(1, count($duplicate['errors']), 'one historical line cannot authorize a second use of its tax');

assert_same('VAT', document_tax_label([['tax_name' => 'VAT', 'tax_rate' => 20], ['tax_name' => 'VAT', 'tax_rate' => 5], ['tax_name' => 'GST', 'tax_rate' => 0]]), 'same name at different rates shares a label; zero rates do not affect it');
assert_same('Tax', document_tax_label([['tax_name' => 'VAT', 'tax_rate' => 20], ['tax_name' => 'GST', 'tax_rate' => 20]]), 'mixed tax names use generic heading');
assert_same('Tax', document_tax_label([['tax_name' => 'VAT', 'tax_rate' => 20], ['tax_name' => null, 'tax_rate' => 20]]), 'unknown historical name prevents a misleading single-tax heading');

$renderTaxLine = static function (array $item) use ($taxDefinitions): string {
    ob_start();
    View::partial('partials/line-item-row', ['item' => $item, 'taxes' => $taxDefinitions, 'defaultTax' => $taxDefinitions[0]]);
    return (string) ob_get_clean();
};
assert_contains('value="12" selected', $renderTaxLine(['id' => 80, 'tax_name' => 'GST', 'tax_rate' => '20.00']), 'editing matches the name and rate, not the first same-rate tax');
assert_contains('value="16" selected', $renderTaxLine(['id' => 80, 'tax_name' => 'Zero rated', 'tax_rate' => '0.00']), 'a named zero tax remains distinct from no tax');
assert_contains('value="0" selected', $renderTaxLine(['id' => 80, 'tax_name' => null, 'tax_rate' => '0.00']), 'saved no-tax selection overrides the default');
foreach ($savedTaxItems as $savedItem) {
    assert_contains('value="saved:' . $savedItem['id'] . '" selected', $renderTaxLine($savedItem), 'historic selection renders without inventing a current tax');
}

Session::flash('_old', [
    'item_description' => ['Retained legacy line', 'Retained GST line'],
    'item_quantity' => ['2', '3'],
    'item_unit_price' => ['10', '20'],
    'item_discount_type' => ['amount', 'percent'],
    'item_discount_value' => ['1000', '101'],
    'item_tax_id' => ['saved:81', '12'],
]);
$oldTaxItems = old_line_items($savedTaxItems);
assert_same(2, count($oldTaxItems), 'validation errors retain every entered line');
assert_same('1000', $oldTaxItems[0]['discount_value'], 'invalid entered values remain available for correction');
assert_contains('value="saved:81" selected', $renderTaxLine($oldTaxItems[0]), 'legacy selection survives validation errors');
assert_contains('value="12" selected', $renderTaxLine($oldTaxItems[1]), 'same-rate GST selection survives validation errors');

$pdfRenderer = new ReflectionMethod(PdfService::class, 'legacyRender');
foreach (['VAT', 'GST', 'Tax', 'Sales & service tax', 'EnvironmentalLevy'] as $taxName) {
    $document = array_replace($baseInvoice, ['tax_total' => '20.00', 'total' => '120.00', 'balance_due' => '120.00']);
    $items = [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100.00', 'tax_rate' => '20.00', 'tax_name' => $taxName, 'line_total' => '120.00']];
    foreach ([true, false] as $isInvoice) {
        ob_start();
        $html = View::render('pdf/document', ['business' => $htmlBusiness, 'document' => $document, 'items' => $items, 'payments' => [], 'numberKey' => 'invoice_number', 'isInvoice' => $isInvoice, 'defaultCurrency' => 'USD', 'forPdf' => false], '');
        ob_end_clean();
        assert_contains('<th class="num">' . e($taxName) . '</th>', $html, 'printable tax column uses the escaped name');
        assert_contains('<td class="label">' . e($taxName) . '</td>', $html, 'printable tax total uses the escaped name');
        assert_not_contains('-0.00 USD', $html, 'printable documents show a zero payment without a minus sign');
        $pdf = $pdfRenderer->invoke(new PdfService(), $htmlBusiness, $document, $items, [], 'invoice_number', $isInvoice);
        if (strlen($taxName) <= 12) {
            assert_same(2, substr_count($pdf, '(' . $taxName . ')'), 'legacy PDF uses the same name for both headings');
        } elseif ($taxName === 'Sales & service tax') {
            assert_contains('(Sales &)', $pdf, 'legacy PDF wraps long tax headings');
            assert_contains('(service tax)', $pdf, 'legacy PDF retains the rest of a long tax heading');
        } else {
            assert_contains('(Environmenta)', $pdf, 'legacy PDF wraps a long single-word tax name');
            assert_contains('(lLevy)', $pdf, 'legacy PDF retains the rest of a single-word tax name');
        }
    }
}

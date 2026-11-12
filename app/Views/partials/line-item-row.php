<?php
$item = $item ?? [];
$defaultTax = $defaultTax ?? null;
$savedDiscountType = $item['discount_type'] ?? 'percent';
$discountType = in_array($savedDiscountType, ['percent', 'amount'], true) ? $savedDiscountType : 'percent';
$discountValue = (float) ($item['discount_value'] ?? $item['discount_rate'] ?? 0);
$discountDisplay = $discountType === 'amount'
    ? number_format($discountValue, 2, '.', '')
    : rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.');
$savedTaxRate = (float) ($item['tax_rate'] ?? 0);
$savedTaxName = trim((string) ($item['tax_name'] ?? ''));
$savedKey = isset($item['id']) && ($savedTaxRate > 0 || $savedTaxName !== '') ? 'saved:' . $item['id'] : null;
$savedLabel = ($savedTaxName !== '' ? $savedTaxName : 'Saved tax') . ' · ' . percentage($savedTaxRate);
$taxSelection = isset($item['tax_rate']) ? ($savedKey ?? '0') : (string) ($defaultTax['id'] ?? 0);
$taxOptions = ['0' => 'No tax · 0%'];
foreach ($taxes as $tax) {
    $matchesSnapshot = $savedKey !== null && $savedTaxName === $tax['name'] && $savedTaxRate === (float) $tax['rate'];
    if ($tax['is_active']) {
        $taxOptions[$tax['id']] = $tax['name'] . ' · ' . percentage($tax['rate']);
        if ($matchesSnapshot) {
            $taxSelection = (string) $tax['id'];
        }
    } elseif ($matchesSnapshot) {
        $savedLabel .= ' · Inactive';
    }
}
$taxSelection = $item['tax_selection'] ?? $taxSelection;
if ($savedKey !== null && $taxSelection === $savedKey) {
    $taxOptions[$savedKey] = $savedLabel;
} elseif (!array_key_exists($taxSelection, $taxOptions)) {
    $taxSelection = '';
    $taxOptions[''] = 'Unavailable tax — choose another';
}
?>
<div class="grid gap-3 rounded-lg border border-ink-100 bg-ink-50 p-3 md:grid-cols-[1.6fr_0.55fr_0.8fr_1.1fr_0.75fr_auto]" data-line-row>
    <label>
        <span class="label">Description</span>
        <input class="field" name="item_description[]" value="<?= e($item['description'] ?? '') ?>" required>
    </label>
    <label>
        <span class="label">Qty</span>
        <input class="field" name="item_quantity[]" type="number" step="0.01" min="0" value="<?= e($item['quantity'] ?? '1') ?>" required>
    </label>
    <label>
        <span class="label">Unit price</span>
        <input class="field" name="item_unit_price[]" type="number" step="0.01" min="0" value="<?= e($item['unit_price'] ?? '0.00') ?>" required>
    </label>
    <label>
        <span class="label">Discount</span>
        <span class="grid grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)] gap-2">
            <select class="field" name="item_discount_type[]" aria-label="Discount type" data-discount-type>
                <option value="percent" <?= $discountType === 'percent' ? 'selected' : '' ?>>Percent</option>
                <option value="amount" <?= $discountType === 'amount' ? 'selected' : '' ?>>Amount</option>
            </select>
            <input class="field" name="item_discount_value[]" aria-label="Discount value" type="number" step="0.01" min="0"<?= $discountType === 'percent' ? ' max="100"' : '' ?> value="<?= e($discountDisplay) ?>" data-discount-value>
        </span>
    </label>
    <label>
        <span class="label">Tax</span>
        <select class="field" name="item_tax_id[]" required>
            <?php foreach ($taxOptions as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $taxSelection === (string) $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="flex items-end">
        <button type="button" class="btn-secondary h-10 w-10 p-0" data-remove-line aria-label="Remove line item"><?= icon('trash') ?></button>
    </div>
</div>

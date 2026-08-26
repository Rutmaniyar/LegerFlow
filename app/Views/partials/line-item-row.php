<?php
$item = $item ?? [];
$defaultTax = $defaultTax ?? null;
$savedDiscountType = $item['discount_type'] ?? 'percent';
$discountType = in_array($savedDiscountType, ['percent', 'amount'], true) ? $savedDiscountType : 'percent';
$discountValue = (float) ($item['discount_value'] ?? $item['discount_rate'] ?? 0);
$discountDisplay = $discountType === 'amount'
    ? number_format($discountValue, 2, '.', '')
    : rtrim(rtrim(number_format($discountValue, 2, '.', ''), '0'), '.');
$hasSavedTax = array_key_exists('tax_rate', $item);
$savedTaxRate = (float) ($item['tax_rate'] ?? 0);
$taxSelected = false;
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
        <span class="label">Tax %</span>
        <select class="field" name="item_tax_rate[]">
            <option value="0" <?= ($hasSavedTax && $savedTaxRate === 0.0) || (!$hasSavedTax && !$defaultTax) ? 'selected' : '' ?>>0%</option>
            <?php foreach ($taxes as $tax): ?>
                <?php
                $matchesSavedTax = $hasSavedTax && !$taxSelected && $savedTaxRate === (float) $tax['rate'];
                if (!$tax['is_active'] && !$matchesSavedTax) {
                    continue;
                }
                $selected = $matchesSavedTax || (!$hasSavedTax && (int) ($defaultTax['id'] ?? 0) === (int) $tax['id']);
                if ($selected) {
                    $taxSelected = true;
                }
                ?>
                <option value="<?= e($tax['rate']) ?>" <?= $selected ? 'selected' : '' ?>><?= e($tax['name']) ?> · <?= percentage($tax['rate']) ?><?= $tax['is_active'] ? '' : ' · Inactive' ?></option>
            <?php endforeach; ?>
            <?php if ($hasSavedTax && $savedTaxRate !== 0.0 && !$taxSelected): ?>
                <option value="<?= e($savedTaxRate) ?>" selected>Saved tax · <?= percentage($savedTaxRate) ?></option>
            <?php endif; ?>
        </select>
    </label>
    <div class="flex items-end">
        <button type="button" class="btn-secondary h-10 w-10 p-0" data-remove-line aria-label="Remove line item"><?= icon('trash') ?></button>
    </div>
</div>

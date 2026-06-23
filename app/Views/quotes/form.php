<?php
$mode = $mode ?? 'create';
$quote = $quote ?? [];
$items = $items ?? [];
$isEdit = $mode === 'edit';
$selectedCurrency = (string) old('currency', $quote['currency'] ?? $business['default_currency'] ?? 'USD');
$formAction = $isEdit ? '/quotes/' . $quote['id'] : '/quotes';
$cancelHref = $isEdit ? '/quotes/' . $quote['id'] : '/quotes';
?>
<form method="post" action="<?= e($formAction) ?>" class="space-y-6" data-motion="fade-up" data-motion-stagger>
    <?= csrf_field() ?>
    <section class="card p-5">
        <div class="grid gap-4 md:grid-cols-4">
            <label class="md:col-span-2">
                <span class="label">Client *</span>
                <select class="field" name="client_id" required>
                    <option value="">Select client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e($client['id']) ?>" <?= (string) old('client_id', (string) ($quote['client_id'] ?? '')) === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="label">Issue date *</span>
                <input class="field" name="issue_date" type="date" required value="<?= e(old('issue_date', $quote['issue_date'] ?? date('Y-m-d'))) ?>">
            </label>
            <label>
                <span class="label">Valid until</span>
                <input class="field" name="valid_until" type="date" value="<?= e(old('valid_until', $quote['valid_until'] ?? date('Y-m-d', strtotime('+30 days')))) ?>">
            </label>
            <label>
                <span class="label">Currency *</span>
                <select class="field" name="currency" required>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name'] ?? $currency['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="label">Status</span>
                <select class="field" name="status">
                    <option value="draft" <?= (string) old('status', $quote['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="sent" <?= (string) old('status', $quote['status'] ?? 'draft') === 'sent' ? 'selected' : '' ?>>Sent</option>
                </select>
            </label>
        </div>
    </section>

    <section class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-black text-ink-900">Line items</h2>
            <button type="button" class="btn-secondary" data-add-line="#quote-lines" data-template="#quote-line-template"><?= icon('plus') ?> Add item</button>
        </div>
        <div id="quote-lines" class="space-y-3">
            <?php if ($items !== []): ?>
                <?php foreach ($items as $item): ?>
                    <?php \App\Core\View::partial('partials/line-item-row', ['taxes' => $taxes, 'item' => $item]); ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?php \App\Core\View::partial('partials/line-item-row', ['taxes' => $taxes]); ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <label class="card p-5">
            <span class="label">Notes</span>
            <textarea class="textarea" name="notes" rows="5"><?= e(old('notes', $quote['notes'] ?? '')) ?></textarea>
        </label>
        <label class="card p-5">
            <span class="label">Terms</span>
            <textarea class="textarea" name="terms" rows="5"><?= e(old('terms', $quote['terms'] ?? 'This quote is valid until the date shown above.')) ?></textarea>
        </label>
    </section>

    <div class="flex justify-end gap-3">
        <a href="<?= e($cancelHref) ?>" class="btn-secondary">Cancel</a>
        <button class="btn-primary"><?= icon('check') ?> <?= $isEdit ? 'Update quote' : 'Save quote' ?></button>
    </div>
</form>

<template id="quote-line-template">
    <?php \App\Core\View::partial('partials/line-item-row', ['taxes' => $taxes]); ?>
</template>

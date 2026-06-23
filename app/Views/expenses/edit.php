<?php $selectedCurrency = (string) old('currency', $expense['currency'] ?? 'USD'); ?>
<section class="mx-auto max-w-2xl" data-motion="fade-up">
    <form method="post" action="/expenses/<?= e($expense['id']) ?>" enctype="multipart/form-data" class="card p-5">
        <?= csrf_field() ?>
        <h2 class="text-lg font-black text-ink-900">Edit expense</h2>
        <div class="mt-5 space-y-4">
            <label><span class="label">Vendor *</span><input class="field" name="vendor" required value="<?= e(old('vendor', $expense['vendor'])) ?>"></label>
            <label><span class="label">Category *</span><input class="field" name="category" required list="expense-categories" value="<?= e(old('category', $expense['category'])) ?>"></label>
            <datalist id="expense-categories"><option value="Software"><option value="Office"><option value="Travel"><option value="Marketing"><option value="Professional services"></datalist>
            <label><span class="label">Expense date *</span><input class="field" name="expense_date" type="date" value="<?= e(old('expense_date', $expense['expense_date'])) ?>" required></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label><span class="label">Amount *</span><input class="field" name="amount" type="number" step="0.01" min="0" value="<?= e(old('amount', $expense['amount'])) ?>" required></label>
                <label><span class="label">Tax amount</span><input class="field" name="tax_amount" type="number" step="0.01" min="0" value="<?= e(old('tax_amount', $expense['tax_amount'])) ?>"></label>
            </div>
            <label>
                <span class="label">Currency</span>
                <select class="field" name="currency" required>
                    <?php foreach ($currencies as $currency): ?><option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name'] ?? $currency['code']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><span class="label">Payment method</span><input class="field" name="payment_method" value="<?= e(old('payment_method', $expense['payment_method'])) ?>"></label>
            <label>
                <span class="label">Receipt</span>
                <input class="field pt-2" name="receipt" type="file" accept=".pdf,image/png,image/jpeg,image/webp">
                <?php if (trim((string) ($expense['receipt_path'] ?? '')) !== ''): ?>
                    <p class="field-help">Current receipt: <a class="font-semibold text-brand-700 underline-offset-2 hover:underline break-all" href="<?= e(upload_url($expense['receipt_path'])) ?>" target="_blank" rel="noopener">view file</a>. Uploading a new one replaces it.</p>
                <?php endif; ?>
            </label>
            <label><span class="label">Notes</span><textarea class="textarea" name="notes" rows="3"><?= e(old('notes', $expense['notes'])) ?></textarea></label>
        </div>
        <div class="mt-5 flex flex-wrap gap-3">
            <button class="btn-primary"><?= icon('check') ?> Save changes</button>
            <a href="/expenses/<?= e($expense['id']) ?>" class="btn-secondary">Cancel</a>
        </div>
    </form>
</section>

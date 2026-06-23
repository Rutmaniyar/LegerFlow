<?php $selectedCurrency = (string) old('currency', 'USD'); ?>
<section class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-5 hover:shadow-soft">
        <div class="mb-5">
            <p class="eyebrow">Spend control</p>
            <h2 class="mt-1 text-lg font-black text-ink-900">Expenses</h2>
            <p class="text-sm text-ink-500">Track vendor costs, tax, payment method, and receipts.</p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th class="text-right">Amount</th><th class="text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= e($expense['expense_date']) ?></td>
                            <td class="font-bold"><a class="text-brand-700 hover:underline" href="/expenses/<?= e($expense['id']) ?>"><?= e($expense['vendor']) ?></a></td>
                            <td><?= e($expense['category']) ?></td>
                            <td class="text-right font-bold"><?= money($expense['amount'], $expense['currency']) ?></td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a class="btn-secondary h-8 px-2.5 text-xs" href="/expenses/<?= e($expense['id']) ?>"><?= icon('invoices', 'h-3.5 w-3.5') ?> View</a>
                                    <a class="btn-secondary h-8 px-2.5 text-xs" href="/expenses/<?= e($expense['id']) ?>/edit"><?= icon('edit', 'h-3.5 w-3.5') ?> Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$expenses): ?>
                        <tr><td colspan="5">
                            <?php empty_state([
                                'icon' => 'expenses',
                                'title' => 'No expenses recorded',
                                'description' => 'Track vendor costs, tax, and receipts here to see true net income in your reports.',
                                'primaryActionLabel' => 'Record an expense',
                                'primaryActionHref' => '#record-expense-form',
                            ]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <form method="post" action="/expenses" id="record-expense-form" enctype="multipart/form-data" class="card p-5 hover:shadow-soft">
        <?= csrf_field() ?>
        <h2 class="text-lg font-black text-ink-900">Record expense</h2>
        <div class="mt-5 space-y-4">
            <label><span class="label">Vendor *</span><input class="field" name="vendor" required></label>
            <label><span class="label">Category *</span><input class="field" name="category" required list="expense-categories"></label>
            <datalist id="expense-categories"><option value="Software"><option value="Office"><option value="Travel"><option value="Marketing"><option value="Professional services"></datalist>
            <label><span class="label">Expense date *</span><input class="field" name="expense_date" type="date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label><span class="label">Amount *</span><input class="field" name="amount" type="number" step="0.01" min="0" required></label>
                <label><span class="label">Tax amount</span><input class="field" name="tax_amount" type="number" step="0.01" min="0" value="0"></label>
            </div>
            <label>
                <span class="label">Currency</span>
                <select class="field" name="currency" required>
                    <?php foreach ($currencies as $currency): ?><option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name'] ?? $currency['code']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><span class="label">Payment method</span><input class="field" name="payment_method"></label>
            <label><span class="label">Receipt</span><input class="field pt-2" name="receipt" type="file" accept=".pdf,image/png,image/jpeg,image/webp"></label>
            <label><span class="label">Notes</span><textarea class="textarea" name="notes" rows="3"></textarea></label>
        </div>
        <button class="btn-primary mt-5 w-full"><?= icon('plus') ?> Save expense</button>
    </form>
</section>

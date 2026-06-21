<?php
$showDiscount = abs((float) ($invoice['discount_total'] ?? 0)) > 0.00001;
$showTax = abs((float) ($invoice['tax_total'] ?? 0)) > 0.00001;
?>
<section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
    <div class="card p-6">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-brand-700">Invoice</p>
                <h2 class="text-3xl font-black text-ink-900"><?= e($invoice['invoice_number']) ?></h2>
                <p class="mt-1 text-ink-500"><?= e($invoice['client_name']) ?> · <?= e($invoice['client_email']) ?></p>
            </div>
            <span class="badge <?= $invoice['status'] === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-700' ?>"><?= e($invoice['status']) ?></span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Issue</p>
                <p class="mt-1 font-black text-ink-900"><?= e($invoice['issue_date']) ?></p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Due</p>
                <p class="mt-1 font-black text-ink-900"><?= e($invoice['due_date']) ?></p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Balance</p>
                <p class="mt-1 font-black text-ink-900"><?= money($invoice['balance_due'], $invoice['currency']) ?></p>
            </div>
        </div>

        <div class="mt-6 table-wrap">
            <table class="data-table">
                <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><?php if ($showTax): ?><th>Tax</th><?php endif; ?><th class="text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="font-semibold"><?= e($item['description']) ?></td>
                            <td><?= e($item['quantity']) ?></td>
                            <td><?= money($item['unit_price'], $invoice['currency']) ?></td>
                            <?php if ($showTax): ?><td><?= percentage($item['tax_rate']) ?></td><?php endif; ?>
                            <td class="text-right font-bold"><?= money($item['line_total'], $invoice['currency']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="ml-auto mt-6 max-w-sm space-y-2">
            <div class="flex justify-between text-sm"><span>Subtotal</span><strong><?= money($invoice['subtotal'], $invoice['currency']) ?></strong></div>
            <?php if ($showDiscount): ?><div class="flex justify-between text-sm"><span>Discount</span><strong><?= money($invoice['discount_total'], $invoice['currency']) ?></strong></div><?php endif; ?>
            <?php if ($showTax): ?><div class="flex justify-between text-sm"><span>Tax</span><strong><?= money($invoice['tax_total'], $invoice['currency']) ?></strong></div><?php endif; ?>
            <div class="flex justify-between text-sm"><span>Paid</span><strong><?= money($invoice['amount_paid'], $invoice['currency']) ?></strong></div>
            <div class="flex justify-between border-t border-ink-200 pt-3 text-lg"><span class="font-black">Balance due</span><strong><?= money($invoice['balance_due'], $invoice['currency']) ?></strong></div>
        </div>
    </div>

    <aside class="space-y-4">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Actions</h2>
            <div class="mt-4 grid gap-3">
                <a href="/invoices/<?= e($invoice['id']) ?>/pdf" class="btn-secondary"><?= icon('download') ?> Download PDF</a>
                <form method="post" action="/invoices/<?= e($invoice['id']) ?>/send"><?= csrf_field() ?><button class="btn-secondary w-full"><?= icon('send') ?> Email invoice</button></form>
            </div>
        </div>

        <form method="post" action="/invoices/<?= e($invoice['id']) ?>/payments" class="card p-5">
            <?= csrf_field() ?>
            <h2 class="text-lg font-black text-ink-900">Record payment</h2>
            <div class="mt-4 space-y-4">
                <label>
                    <span class="label">Amount</span>
                    <input class="field" name="amount" type="number" step="0.01" min="0" value="<?= e($invoice['balance_due']) ?>" required>
                </label>
                <label>
                    <span class="label">Payment date</span>
                    <input class="field" name="payment_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                </label>
                <label>
                    <span class="label">Method</span>
                    <select class="field" name="method">
                        <?php foreach ($paymentMethods as $method): ?>
                            <?php if (trim((string) $method) !== ''): ?><option value="<?= e(trim((string) $method)) ?>"><?= e(trim((string) $method)) ?></option><?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="label">Reference</span>
                    <input class="field" name="reference">
                </label>
                <label>
                    <span class="label">Notes</span>
                    <textarea class="textarea" name="notes" rows="3"></textarea>
                </label>
            </div>
            <button class="btn-primary mt-5 w-full"><?= icon('payments') ?> Record payment</button>
        </form>

        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Payment history</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($payments as $payment): ?>
                    <div class="rounded-md border border-ink-100 bg-white px-3 py-2">
                        <div class="flex justify-between">
                            <span class="font-bold"><?= money($payment['amount'], $payment['currency']) ?></span>
                            <span class="text-sm text-ink-500"><?= e($payment['payment_date']) ?></span>
                        </div>
                        <p class="text-sm text-ink-500"><?= e($payment['method']) ?> <?= $payment['reference'] ? '· ' . e($payment['reference']) : '' ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$payments): ?>
                    <?php empty_state([
                        'compact' => true,
                        'icon' => 'payments',
                        'title' => 'No payments recorded',
                        'description' => 'Record a payment using the form above once this invoice is paid.',
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</section>

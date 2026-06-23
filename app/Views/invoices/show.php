<?php
$showDiscount = abs((float) ($invoice['discount_total'] ?? 0)) > 0.00001;
$showTax = abs((float) ($invoice['tax_total'] ?? 0)) > 0.00001;
$logoPath = trim((string) ($business['logo_path'] ?? ''));
?>
<section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-6">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= e(upload_url($logoPath)) ?>" alt="<?= e($business['business_name'] ?? '') ?>" class="h-14 w-14 rounded-lg border border-ink-100 object-cover">
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="text-sm font-bold uppercase tracking-wide text-brand-700">Invoice</p>
                    <h2 class="text-3xl font-black text-ink-900 break-words"><?= e($invoice['invoice_number']) ?></h2>
                    <p class="mt-1 break-words text-ink-500"><?= e($invoice['client_name']) ?> · <span class="break-all"><?= e($invoice['client_email']) ?></span></p>
                </div>
            </div>
            <div class="flex flex-col items-start gap-2 sm:items-end">
                <span class="badge <?= $invoice['status'] === 'overdue' ? 'bg-red-100 text-red-700' : ($invoice['status'] === 'paid' ? 'bg-brand-100 text-brand-700' : 'bg-ink-100 text-ink-700') ?>"><?= e($invoice['status']) ?></span>
                <?php if ($invoice['status'] === 'paid' && !empty($invoice['paid_at'])): ?>
                    <span class="text-xs font-bold uppercase tracking-wide text-brand-700">Paid <?= e(date('M j, Y', strtotime((string) $invoice['paid_at']))) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($overpaid > 0.0001): ?>
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4" role="alert">
                <p class="font-bold text-amber-900">Overpaid by <?= money($overpaid, $invoice['currency']) ?></p>
                <p class="mt-1 text-sm text-amber-800">Settle this by refunding the client, or delete a payment below if one was added by mistake.</p>
            </div>
        <?php endif; ?>

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

        <?php if (trim((string) ($invoice['notes'] ?? '')) !== ''): ?>
            <div class="mt-6 rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Notes</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink-700"><?= e($invoice['notes']) ?></p>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($invoice['terms'] ?? '')) !== ''): ?>
            <div class="mt-3 rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Terms</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink-700"><?= e($invoice['terms']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="space-y-4">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Actions</h2>
            <div class="mt-4 grid gap-3">
                <a href="/invoices/<?= e($invoice['id']) ?>/pdf" target="_blank" rel="noopener" class="btn-secondary"><?= icon('download') ?> Download PDF</a>
                <a href="/invoices/<?= e($invoice['id']) ?>/pdf?preview=1" target="_blank" rel="noopener" class="btn-secondary"><?= icon('invoices') ?> Preview</a>
                <form method="post" action="/invoices/<?= e($invoice['id']) ?>/send"><?= csrf_field() ?><button class="btn-secondary w-full"><?= icon('send') ?> Email invoice</button></form>
                <?php if ($invoice['status'] === 'draft'): ?>
                    <a href="/invoices/<?= e($invoice['id']) ?>/edit" class="btn-secondary"><?= icon('edit') ?> Edit draft</a>
                    <form method="post" action="/invoices/<?= e($invoice['id']) ?>/delete" onsubmit="return confirm('Delete this draft invoice? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <button class="btn-secondary w-full text-red-700"><?= icon('trash') ?> Delete draft</button>
                    </form>
                <?php endif; ?>
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

        <?php if ($overpaid > 0.0001): ?>
            <form method="post" action="/invoices/<?= e($invoice['id']) ?>/refunds" class="card p-5">
                <?= csrf_field() ?>
                <h2 class="text-lg font-black text-ink-900">Record refund</h2>
                <p class="mt-1 text-sm text-ink-500">Logs money returned to the client to settle the overpayment.</p>
                <div class="mt-4 space-y-4">
                    <label>
                        <span class="label">Amount</span>
                        <input class="field" name="amount" type="number" step="0.01" min="0.01" value="<?= e($overpaid) ?>" required>
                    </label>
                    <label>
                        <span class="label">Refund date</span>
                        <input class="field" name="payment_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                    </label>
                    <label>
                        <span class="label">Reference</span>
                        <input class="field" name="reference">
                    </label>
                </div>
                <button class="btn-secondary mt-5 w-full"><?= icon('payments') ?> Record refund</button>
            </form>
        <?php endif; ?>

        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Payment history</h2>
            <div class="mt-4 space-y-3">
                <?php foreach ($payments as $payment): ?>
                    <div class="rounded-md border border-ink-100 bg-white px-3 py-2">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <span class="font-bold <?= $payment['type'] === 'refund' ? 'text-red-700' : '' ?>">
                                <?= $payment['type'] === 'refund' ? '-' : '' ?><?= money($payment['amount'], $payment['currency']) ?>
                                <?php if ($payment['type'] === 'refund'): ?><span class="badge bg-red-100 text-red-700 ml-1">Refund</span><?php endif; ?>
                            </span>
                            <span class="text-sm text-ink-500"><?= e($payment['payment_date']) ?></span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                            <p class="min-w-0 break-words text-sm text-ink-500"><?= e($payment['method']) ?> <?= $payment['reference'] ? '· ' . e($payment['reference']) : '' ?></p>
                            <form method="post" action="/invoices/<?= e($invoice['id']) ?>/payments/<?= e($payment['id']) ?>/delete" onsubmit="return confirm('Delete this <?= $payment['type'] === 'refund' ? 'refund' : 'payment' ?> entry? This cannot be undone.')">
                                <?= csrf_field() ?>
                                <button class="text-xs font-bold uppercase tracking-wide text-red-700 hover:underline" aria-label="Delete entry"><?= icon('trash', 'h-3.5 w-3.5') ?> Delete</button>
                            </form>
                        </div>
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

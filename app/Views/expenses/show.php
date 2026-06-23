<?php
$total = (float) ($expense['amount'] ?? 0) + (float) ($expense['tax_amount'] ?? 0);
$receiptPath = trim((string) ($expense['receipt_path'] ?? ''));
?>
<section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-brand-700">Expense</p>
                <h2 class="text-2xl font-black text-ink-900 break-words"><?= e($expense['vendor']) ?></h2>
                <p class="mt-1 text-ink-500"><?= e($expense['category']) ?> · <?= e($expense['expense_date']) ?></p>
            </div>
            <span class="badge bg-ink-100 text-ink-700 self-start">Recorded</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Amount</p>
                <p class="mt-1 font-black text-ink-900"><?= money($expense['amount'], $expense['currency']) ?></p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Tax</p>
                <p class="mt-1 font-black text-ink-900"><?= money($expense['tax_amount'] ?? 0, $expense['currency']) ?></p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Total</p>
                <p class="mt-1 font-black text-ink-900"><?= money($total, $expense['currency']) ?></p>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Payment method</p>
                <p class="mt-1 font-semibold text-ink-900 break-words"><?= e($expense['payment_method'] ?: 'Not set') ?></p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Receipt</p>
                <?php if ($receiptPath !== ''): ?>
                    <a class="mt-1 inline-block font-semibold text-brand-700 underline-offset-2 hover:underline break-all" href="<?= e(upload_url($receiptPath)) ?>" target="_blank" rel="noopener">View uploaded receipt</a>
                <?php else: ?>
                    <p class="mt-1 font-semibold text-ink-500">No receipt uploaded</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (trim((string) ($expense['notes'] ?? '')) !== ''): ?>
            <div class="mt-6 rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Notes</p>
                <p class="mt-1 whitespace-pre-line break-words text-sm text-ink-700"><?= e($expense['notes']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="space-y-4">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Actions</h2>
            <div class="mt-4 grid gap-3">
                <a href="/expenses/<?= e($expense['id']) ?>/pdf" target="_blank" rel="noopener" class="btn-secondary"><?= icon('download') ?> Download receipt PDF</a>
                <a href="/expenses/<?= e($expense['id']) ?>/pdf?preview=1" target="_blank" rel="noopener" class="btn-secondary"><?= icon('invoices') ?> Preview</a>
                <a href="/expenses/<?= e($expense['id']) ?>/edit" class="btn-secondary"><?= icon('edit') ?> Edit expense</a>
                <form method="post" action="/expenses/<?= e($expense['id']) ?>/delete" onsubmit="return confirm('Delete this expense? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <button class="btn-secondary w-full text-red-700"><?= icon('trash') ?> Delete expense</button>
                </form>
                <a href="/expenses" class="btn-secondary">Back to expenses</a>
            </div>
        </div>
    </aside>
</section>

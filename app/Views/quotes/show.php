<?php
$showDiscount = abs((float) ($quote['discount_total'] ?? 0)) > 0.00001;
$showTax = abs((float) ($quote['tax_total'] ?? 0)) > 0.00001;
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
                    <p class="text-sm font-bold uppercase tracking-wide text-brand-700">Quote</p>
                    <h2 class="text-3xl font-black text-ink-900 break-words"><?= e($quote['quote_number']) ?></h2>
                    <p class="mt-1 break-words text-ink-500"><?= e($quote['client_name']) ?> · <span class="break-all"><?= e($quote['client_email']) ?></span></p>
                </div>
            </div>
            <span class="badge bg-ink-100 text-ink-700"><?= e($quote['status']) ?></span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><?php if ($showTax): ?><th>Tax</th><?php endif; ?><th class="text-right">Total</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="font-semibold"><?= e($item['description']) ?></td>
                            <td><?= e($item['quantity']) ?></td>
                            <td><?= money($item['unit_price'], $quote['currency']) ?></td>
                            <?php if ($showTax): ?><td><?= percentage($item['tax_rate']) ?></td><?php endif; ?>
                            <td class="text-right font-bold"><?= money($item['line_total'], $quote['currency']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="ml-auto mt-6 max-w-sm space-y-2">
            <div class="flex justify-between text-sm"><span>Subtotal</span><strong><?= money($quote['subtotal'], $quote['currency']) ?></strong></div>
            <?php if ($showDiscount): ?><div class="flex justify-between text-sm"><span>Discount</span><strong><?= money($quote['discount_total'], $quote['currency']) ?></strong></div><?php endif; ?>
            <?php if ($showTax): ?><div class="flex justify-between text-sm"><span>Tax</span><strong><?= money($quote['tax_total'], $quote['currency']) ?></strong></div><?php endif; ?>
            <div class="flex justify-between border-t border-ink-200 pt-3 text-lg"><span class="font-black">Total</span><strong><?= money($quote['total'], $quote['currency']) ?></strong></div>
        </div>

        <?php if (trim((string) ($quote['notes'] ?? '')) !== ''): ?>
            <div class="mt-6 rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Notes</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink-700"><?= e($quote['notes']) ?></p>
            </div>
        <?php endif; ?>
        <?php if (trim((string) ($quote['terms'] ?? '')) !== ''): ?>
            <div class="mt-3 rounded-lg border border-ink-100 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase text-ink-500">Terms</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink-700"><?= e($quote['terms']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="space-y-4">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Actions</h2>
            <div class="mt-4 grid gap-3">
                <a href="/quotes/<?= e($quote['id']) ?>/pdf" target="_blank" rel="noopener" class="btn-secondary"><?= icon('download') ?> Download PDF</a>
                <a href="/quotes/<?= e($quote['id']) ?>/pdf?preview=1" target="_blank" rel="noopener" class="btn-secondary"><?= icon('quotes') ?> Preview</a>
                <form method="post" action="/quotes/<?= e($quote['id']) ?>/send"><?= csrf_field() ?><button class="btn-secondary w-full"><?= icon('send') ?> Email quote</button></form>
                <?php if ($quote['status'] !== 'converted'): ?>
                    <form method="post" action="/quotes/<?= e($quote['id']) ?>/convert"><?= csrf_field() ?><button class="btn-primary w-full"><?= icon('invoices') ?> Convert to invoice</button></form>
                <?php endif; ?>
                <?php if ($quote['status'] === 'draft'): ?>
                    <a href="/quotes/<?= e($quote['id']) ?>/edit" class="btn-secondary"><?= icon('edit') ?> Edit draft</a>
                    <form method="post" action="/quotes/<?= e($quote['id']) ?>/delete" onsubmit="return confirm('Delete this draft quote? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <button class="btn-secondary w-full text-red-700"><?= icon('trash') ?> Delete draft</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Issue date</dt><dd class="font-bold"><?= e($quote['issue_date']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Valid until</dt><dd class="font-bold"><?= e($quote['valid_until']) ?></dd></div>
            </dl>
        </div>
    </aside>
</section>

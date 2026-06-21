<?php $selectedCurrency = (string) old('currency', $client['currency'] ?? 'USD'); ?>
<section class="grid gap-6 xl:grid-cols-[0.95fr_1.4fr]">
    <form method="post" action="/clients/<?= e($client['id']) ?>" class="card p-5">
        <?= csrf_field() ?>
        <div class="mb-5 flex items-start justify-between">
            <div>
                <h2 class="text-lg font-black text-ink-900">Client profile</h2>
                <p class="text-sm text-ink-500">Maintain accurate billing and privacy metadata.</p>
            </div>
            <a href="/clients/<?= e($client['id']) ?>/export" class="btn-secondary"><?= icon('download') ?> Export</a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label>
                <span class="label">Type</span>
                <select class="field" name="type">
                    <option value="business" <?= $client['type'] === 'business' ? 'selected' : '' ?>>Business</option>
                    <option value="person" <?= $client['type'] === 'person' ? 'selected' : '' ?>>Person</option>
                </select>
            </label>
            <label>
                <span class="label">Currency</span>
                <select class="field" name="currency" required>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name'] ?? $currency['code']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Client name *</span>
                <input class="field" name="name" required value="<?= e($client['name']) ?>">
            </label>
            <label>
                <span class="label">Contact</span>
                <input class="field" name="contact_name" value="<?= e($client['contact_name']) ?>">
            </label>
            <label>
                <span class="label">Email</span>
                <input class="field" name="email" type="email" value="<?= e($client['email']) ?>">
            </label>
            <label>
                <span class="label">Phone</span>
                <input class="field" name="phone" type="tel" value="<?= e($client['phone']) ?>">
            </label>
            <label>
                <span class="label">Website</span>
                <input class="field" name="website" type="url" value="<?= e($client['website']) ?>">
            </label>
            <label class="sm:col-span-2">
                <span class="label">Billing address</span>
                <textarea class="textarea" name="billing_address" rows="3"><?= e($client['billing_address']) ?></textarea>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Shipping address</span>
                <textarea class="textarea" name="shipping_address" rows="3"><?= e($client['shipping_address']) ?></textarea>
            </label>
            <label>
                <span class="label">Tax number</span>
                <input class="field" name="tax_number" value="<?= e($client['tax_number']) ?>">
            </label>
            <label>
                <span class="label">Processing basis</span>
                <select class="field" name="data_processing_basis">
                    <?php foreach (['contract', 'legal_obligation', 'legitimate_interest', 'consent'] as $basis): ?>
                        <option value="<?= e($basis) ?>" <?= $client['data_processing_basis'] === $basis ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $basis)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Notes</span>
                <textarea class="textarea" name="notes" rows="3"><?= e($client['notes']) ?></textarea>
            </label>
        </div>
        <div class="mt-5 flex flex-wrap gap-3">
            <button class="btn-primary"><?= icon('check') ?> Save changes</button>
        </div>
    </form>

    <div class="space-y-6">
        <div class="card p-5">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-black text-ink-900">Ledger</h2>
                <a class="btn-primary" href="/invoices/create"><?= icon('plus') ?> Invoice</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Status</th><th>Due</th><th class="text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><a class="font-bold text-brand-700" href="/invoices/<?= e($invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                                <td><span class="badge bg-ink-100 text-ink-700"><?= e($invoice['status']) ?></span></td>
                                <td><?= e($invoice['due_date']) ?></td>
                                <td class="text-right font-bold"><?= money($invoice['balance_due'], $invoice['currency']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$invoices): ?>
                            <tr><td colspan="4">
                                <?php empty_state([
                                    'compact' => true,
                                    'icon' => 'invoices',
                                    'title' => 'No invoices for this client yet',
                                    'description' => 'Invoices billed to this client will show up here with status and balance.',
                                    'primaryActionLabel' => 'Create invoice',
                                    'primaryActionHref' => '/invoices/create',
                                ]) ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card p-5">
                <h2 class="text-lg font-black text-ink-900">Quotes</h2>
                <div class="mt-4 space-y-3">
                    <?php foreach ($quotes as $quote): ?>
                        <a class="flex items-center justify-between rounded-md border border-ink-100 bg-white px-3 py-2" href="/quotes/<?= e($quote['id']) ?>">
                            <span class="font-bold text-brand-700"><?= e($quote['quote_number']) ?></span>
                            <span class="text-sm text-ink-600"><?= money($quote['total'], $quote['currency']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$quotes): ?>
                        <?php empty_state([
                            'compact' => true,
                            'icon' => 'quotes',
                            'title' => 'No quotes yet',
                            'description' => 'Estimates sent to this client will appear here.',
                            'primaryActionLabel' => 'Create quote',
                            'primaryActionHref' => '/quotes/create',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card p-5">
                <h2 class="text-lg font-black text-ink-900">Payment history</h2>
                <div class="mt-4 space-y-3">
                    <?php foreach ($payments as $payment): ?>
                        <div class="rounded-md border border-ink-100 bg-white px-3 py-2">
                            <div class="flex justify-between">
                                <span class="font-bold text-ink-800"><?= money($payment['amount'], $payment['currency']) ?></span>
                                <span class="text-sm text-ink-500"><?= e($payment['payment_date']) ?></span>
                            </div>
                            <p class="text-sm text-ink-500"><?= e($payment['invoice_number']) ?> · <?= e($payment['method']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?>
                        <?php empty_state([
                            'compact' => true,
                            'icon' => 'payments',
                            'title' => 'No payments recorded',
                            'description' => 'Payments collected against this client\'s invoices will appear here.',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="post" action="/clients/<?= e($client['id']) ?>/anonymize" class="rounded-lg border border-red-200 bg-red-50 p-5">
            <?= csrf_field() ?>
            <h2 class="font-black text-red-900">GDPR erasure support</h2>
            <p class="mt-2 text-sm leading-6 text-red-800">Anonymizes client contact fields while retaining financial records for legal and accounting obligations.</p>
            <button class="btn-danger mt-4" onclick="return confirm('Anonymize this client personal data?')"><?= icon('trash') ?> Anonymize client</button>
        </form>
    </div>
</section>

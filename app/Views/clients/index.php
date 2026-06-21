<?php $selectedCurrency = (string) old('currency', 'USD'); ?>
<section class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
    <div class="card p-5 hover:shadow-soft">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="eyebrow">Customer base</p>
                <h2 class="mt-1 text-lg font-black text-ink-900">Client directory</h2>
                <p class="text-sm text-ink-500">Customer details, billing contacts, and ledger history.</p>
            </div>
            <form method="get" action="/clients" class="flex gap-2">
                <label class="sr-only" for="client-search">Search clients</label>
                <input id="client-search" class="field" name="q" value="<?= e($search) ?>" placeholder="Search clients">
                <button class="btn-secondary h-10 w-10 p-0" aria-label="Search"><?= icon('search') ?></button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Currency</th>
                        <th>Basis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><a class="font-bold text-brand-700" href="/clients/<?= e($client['id']) ?>"><?= e($client['name']) ?></a></td>
                            <td>
                                <span class="block font-semibold text-ink-800"><?= e($client['contact_name'] ?: 'Primary contact') ?></span>
                                <span class="text-ink-500"><?= e($client['email'] ?: 'No email') ?></span>
                            </td>
                            <td><?= e($client['currency']) ?></td>
                            <td><span class="badge bg-brand-100 text-brand-700"><?= e($client['data_processing_basis']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clients && $search !== ''): ?>
                        <tr><td colspan="4">
                            <?php empty_state([
                                'variant' => 'no_search_results',
                                'searchTerm' => $search,
                                'description' => 'Check the spelling, or try just the first name, company, or email domain.',
                                'secondaryActionLabel' => 'Clear search',
                                'secondaryActionHref' => '/clients',
                            ]) ?>
                        </td></tr>
                    <?php elseif (!$clients): ?>
                        <tr><td colspan="4">
                            <?php empty_state([
                                'icon' => 'clients',
                                'title' => 'No clients yet',
                                'description' => 'This is where every customer you bill will show up, with contact details and ledger history.',
                                'primaryActionLabel' => 'Add your first client',
                                'primaryActionHref' => '#add-client-form',
                            ]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="table-footer">
                <span>Showing <?= e(count($clients)) ?> clients</span>
                <span class="hidden sm:inline">GDPR basis is tracked per customer.</span>
            </div>
        </div>
    </div>

    <form method="post" action="/clients" id="add-client-form" class="card p-5 hover:shadow-soft">
        <?= csrf_field() ?>
        <h2 class="text-lg font-black text-ink-900">Add client</h2>
        <div class="mt-5 space-y-4">
            <label>
                <span class="label">Type</span>
                <select class="field" name="type">
                    <option value="business">Business</option>
                    <option value="person">Person</option>
                </select>
            </label>
            <label>
                <span class="label">Client name *</span>
                <input class="field" name="name" required>
            </label>
            <label>
                <span class="label">Contact name</span>
                <input class="field" name="contact_name" autocomplete="name">
            </label>
            <label>
                <span class="label">Email</span>
                <input class="field" name="email" type="email" autocomplete="email">
            </label>
            <label>
                <span class="label">Currency</span>
                <select class="field" name="currency" required>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="label">Billing address</span>
                <textarea class="textarea" name="billing_address" rows="3"></textarea>
            </label>
            <label>
                <span class="label">Notes</span>
                <textarea class="textarea" name="notes" rows="3"></textarea>
            </label>
        </div>
        <button class="btn-primary mt-5 w-full"><?= icon('plus') ?> Create client</button>
    </form>
</section>

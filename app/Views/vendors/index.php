<section class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-5 hover:shadow-soft">
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Suppliers</p>
                <h2 class="mt-1 text-lg font-black text-ink-900">Vendors</h2>
                <p class="text-sm text-ink-500">Manage supplier records used for expenses.</p>
            </div>
            <form method="get" action="/vendors" class="flex gap-2">
                <input class="field" name="q" value="<?= e($search) ?>" placeholder="Search vendors">
                <button class="btn-secondary"><?= icon('search') ?> Search</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th class="text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td class="font-bold"><a class="text-brand-700 hover:underline" href="/vendors/<?= e($vendor['id']) ?>"><?= e($vendor['name']) ?></a></td>
                            <td><?= e($vendor['email'] ?? '') ?></td>
                            <td><?= e($vendor['phone'] ?? '') ?></td>
                            <td class="text-right"><a class="btn-secondary h-8 px-2.5 text-xs" href="/vendors/<?= e($vendor['id']) ?>"><?= icon('vendors', 'h-3.5 w-3.5') ?> View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vendors): ?>
                        <tr><td colspan="4">
                            <?php empty_state([
                                'icon' => 'vendors',
                                'title' => 'No vendors found',
                                'description' => 'Create vendors here, then select them when recording expenses.',
                                'primaryActionLabel' => 'Add vendor',
                                'primaryActionHref' => '#vendor-form',
                            ]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination, 'path' => '/vendors', 'query' => ['q' => $search]]); ?>
        </div>
    </div>

    <form method="post" action="/vendors" id="vendor-form" class="card p-5 hover:shadow-soft">
        <?= csrf_field() ?>
        <h2 class="text-lg font-black text-ink-900">Add vendor</h2>
        <div class="mt-5 space-y-4">
            <label><span class="label">Vendor name *</span><input class="field" name="name" required maxlength="190" value="<?= e(old('name')) ?>" autocomplete="organization" data-entity-name-lookup="vendor"></label>
            <label><span class="label">Contact name</span><input class="field" name="contact_name" maxlength="190" value="<?= e(old('contact_name')) ?>"></label>
            <label><span class="label">Email</span><input class="field" name="email" type="email" maxlength="190" value="<?= e(old('email')) ?>"></label>
            <label><span class="label">Phone</span><input class="field" name="phone" maxlength="80" value="<?= e(old('phone')) ?>"></label>
            <label><span class="label">Website</span><input class="field" name="website" type="url" maxlength="190" value="<?= e(old('website')) ?>"></label>
            <label><span class="label">Tax/VAT number</span><input class="field" name="tax_number" maxlength="120" value="<?= e(old('tax_number')) ?>"></label>
            <label><span class="label">Billing address</span><textarea class="textarea" name="billing_address" rows="3"><?= e(old('billing_address')) ?></textarea></label>
            <label><span class="label">Notes</span><textarea class="textarea" name="notes" rows="3"><?= e(old('notes')) ?></textarea></label>
        </div>
        <button class="btn-primary mt-5 w-full"><?= icon('plus') ?> Save vendor</button>
    </form>
</section>

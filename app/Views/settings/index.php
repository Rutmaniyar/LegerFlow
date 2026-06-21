<?php
$selectedDefaultCurrency = (string) old('default_currency', $business['default_currency'] ?? 'USD');
$selectedBusinessCountry = (string) old('country', $business['country'] ?? '');
?>
<section class="space-y-6">
    <div class="toolbar flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <p class="eyebrow">Control plane</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-ink-950">Settings</h2>
            <p class="page-kicker">Branding, tax, SMTP, permissions, templates, and audit controls.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-lg border border-ink-100 bg-white px-3 py-2">
                <p class="text-xs font-black uppercase tracking-wide text-ink-400">Taxes</p>
                <p class="text-sm font-bold text-ink-900"><?= e(count($taxes)) ?> configured</p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-white px-3 py-2">
                <p class="text-xs font-black uppercase tracking-wide text-ink-400">Users</p>
                <p class="text-sm font-bold text-ink-900"><?= e(count($users)) ?> accounts</p>
            </div>
            <div class="rounded-lg border border-ink-100 bg-white px-3 py-2">
                <p class="text-xs font-black uppercase tracking-wide text-ink-400">Mail</p>
                <p class="text-sm font-bold text-ink-900"><?= e(strtoupper((string) $mail['transport'])) ?></p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <form method="post" action="/settings/business" enctype="multipart/form-data" class="card p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($business['id']) ?>">
            <div class="mb-5">
                <h2 class="text-lg font-black text-ink-900">Business profile and branding</h2>
                <p class="text-sm text-ink-500">Used on invoices, quotes, PDFs, email templates, and privacy notices.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="label">Business name *</span><input class="field" name="business_name" required value="<?= e($business['business_name']) ?>"></label>
                <label><span class="label">Legal name</span><input class="field" name="legal_name" value="<?= e($business['legal_name']) ?>"></label>
                <label><span class="label">Email *</span><input class="field" name="email" type="email" required value="<?= e($business['email']) ?>"></label>
                <label><span class="label">Phone</span><input class="field" name="phone" value="<?= e($business['phone']) ?>"></label>
                <label><span class="label">Website</span><input class="field" name="website" type="url" value="<?= e($business['website']) ?>"></label>
                <label><span class="label">Tax number</span><input class="field" name="tax_number" value="<?= e($business['tax_number']) ?>"></label>
                <label class="md:col-span-2"><span class="label">Logo</span><input class="field pt-2" name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label>
                <label><span class="label">Brand color</span><input class="field p-1" name="brand_color" type="color" value="<?= e($business['brand_color']) ?>"></label>
                <label><span class="label">Accent color</span><input class="field p-1" name="accent_color" type="color" value="<?= e($business['accent_color']) ?>"></label>
                <label><span class="label">Default currency</span><select class="field" name="default_currency" required><?php foreach ($currencies as $currency): ?><option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedDefaultCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name'] ?? $currency['code']) ?></option><?php endforeach; ?></select></label>
                <label><span class="label">Payment terms days</span><input class="field" name="default_payment_terms" type="number" min="0" value="<?= e($business['default_payment_terms']) ?>"></label>
                <label><span class="label">Address line 1</span><input class="field" name="address_line1" value="<?= e($business['address_line1']) ?>"></label>
                <label><span class="label">Address line 2</span><input class="field" name="address_line2" value="<?= e($business['address_line2']) ?>"></label>
                <label><span class="label">City</span><input class="field" name="city" value="<?= e($business['city']) ?>"></label>
                <label><span class="label">Region</span><input class="field" name="region" value="<?= e($business['region']) ?>"></label>
                <label><span class="label">Postal code</span><input class="field" name="postal_code" value="<?= e($business['postal_code']) ?>"></label>
                <label><span class="label">Country</span><select class="field" name="country"><option value="">Select country</option><?php foreach ($countries as $country): ?><option value="<?= e(secure_option('country', $country)) ?>" <?= secure_option_selected('country', $selectedBusinessCountry, $country) ?>><?= e($country) ?></option><?php endforeach; ?></select></label>
                <label class="md:col-span-2"><span class="label">Privacy policy support text</span><textarea class="textarea" name="privacy_policy" rows="5"><?= e($business['privacy_policy']) ?></textarea></label>
            </div>
            <button class="btn-primary mt-5"><?= icon('check') ?> Save business profile</button>
        </form>

        <div class="space-y-6">
            <form method="post" action="/settings/general" class="card p-5">
                <?= csrf_field() ?>
                <h2 class="text-lg font-black text-ink-900">Numbering and retention</h2>
                <div class="mt-5 space-y-4">
                    <label><span class="label">Invoice prefix</span><input class="field" name="invoice_prefix" value="<?= e($settings['invoice_prefix']) ?>"></label>
                    <label><span class="label">Quote prefix</span><input class="field" name="quote_prefix" value="<?= e($settings['quote_prefix']) ?>"></label>
                    <label><span class="label">Payment methods</span><textarea class="textarea" name="payment_methods" rows="4"><?= e($settings['payment_methods']) ?></textarea></label>
                    <label><span class="label">Data retention years</span><input class="field" name="data_retention_years" type="number" min="1" value="<?= e($settings['data_retention_years']) ?>"></label>
                </div>
                <button class="btn-primary mt-5 w-full"><?= icon('check') ?> Save general settings</button>
            </form>

            <form method="post" action="/settings/taxes" class="card p-5">
                <?= csrf_field() ?>
                <h2 class="text-lg font-black text-ink-900">Add tax</h2>
                <div class="mt-5 space-y-4">
                    <label><span class="label">Name</span><input class="field" name="name" required></label>
                    <label><span class="label">Rate %</span><input class="field" name="rate" type="number" step="0.0001" min="0" required></label>
                    <label><span class="label">Registration number</span><input class="field" name="registration_number"></label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-ink-700"><input class="h-4 w-4 rounded border-ink-300 text-brand-600" name="is_compound" type="checkbox"> Compound tax</label>
                </div>
                <button class="btn-secondary mt-5 w-full"><?= icon('plus') ?> Add tax</button>
            </form>

            <form method="post" action="/settings/mail" class="card p-5">
                <?= csrf_field() ?>
                <h2 class="text-lg font-black text-ink-900">SMTP and email</h2>
                <div class="mt-5 space-y-4">
                    <label><span class="label">Transport</span><select class="field" name="mail_transport"><option value="mail" <?= $mail['transport'] === 'mail' ? 'selected' : '' ?>>PHP mail()</option><option value="smtp" <?= $mail['transport'] === 'smtp' ? 'selected' : '' ?>>SMTP</option></select></label>
                    <label><span class="label">SMTP host</span><input class="field" name="mail_host" value="<?= e($mail['host']) ?>"></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label><span class="label">Port</span><input class="field" name="mail_port" inputmode="numeric" value="<?= e($mail['port']) ?>"></label>
                        <label><span class="label">Encryption</span><select class="field" name="mail_encryption"><option value="tls" <?= $mail['encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= $mail['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="" <?= $mail['encryption'] === '' ? 'selected' : '' ?>>None</option></select></label>
                    </div>
                    <label><span class="label">Username</span><input class="field" name="mail_username" value="<?= e($mail['username']) ?>" autocomplete="username"></label>
                    <label><span class="label">Password</span><input class="field" name="mail_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
                    <label><span class="label">From email</span><input class="field" name="mail_from_email" type="email" value="<?= e($mail['from_email']) ?>" required></label>
                    <label><span class="label">From name</span><input class="field" name="mail_from_name" value="<?= e($mail['from_name']) ?>"></label>
                </div>
                <button class="btn-primary mt-5 w-full"><?= icon('send') ?> Save mail settings</button>
            </form>

            <form method="post" action="/settings/mail/test" class="card p-5">
                <?= csrf_field() ?>
                <h2 class="text-lg font-black text-ink-900">Test SMTP connection</h2>
                <p class="mt-1 text-sm text-ink-500">Sends a real email using your saved mail settings above, so you can confirm delivery actually works.</p>
                <label class="mt-4 block">
                    <span class="label">Send test to</span>
                    <input class="field" name="test_email" type="email" value="<?= e(\App\Core\Auth::user()['email'] ?? '') ?>" placeholder="you@example.com">
                </label>
                <button class="btn-secondary mt-4 w-full"><?= icon('send') ?> Send test email</button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Taxes</h2>
            <div class="mt-5 table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Rate</th><th>Status</th></tr></thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($taxes as $tax): ?><tr><td class="font-bold"><?= e($tax['name']) ?></td><td><?= percentage($tax['rate']) ?></td><td><?= $tax['is_active'] ? 'Active' : 'Inactive' ?></td></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table-footer"><span><?= e(count($taxes)) ?> tax rules</span><span>VAT/GST ready</span></div>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Users and roles</h2>
            <form method="post" action="/settings/users" class="mt-5 grid gap-3 md:grid-cols-2">
                <?= csrf_field() ?>
                <label><span class="label">Name</span><input class="field" name="name" required></label>
                <label><span class="label">Email</span><input class="field" name="email" type="email" required></label>
                <label><span class="label">Role</span><select class="field" name="role_id"><?php foreach ($roles as $role): ?><option value="<?= e($role['id']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
                <label><span class="label">Temporary password</span><input class="field" name="password" type="password" minlength="10" required></label>
                <div class="md:col-span-2"><button class="btn-secondary"><?= icon('plus') ?> Add user</button></div>
            </form>
            <div class="mt-5 table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($users as $user): ?><tr><td class="font-bold"><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['role_name']) ?></td></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table-footer"><span><?= e(count($users)) ?> users</span><span>Role-based access</span></div>
            </div>
        </div>
    </div>

    <?php if ($canUpdate): ?>
        <div class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-black text-ink-900">Software updates</h2>
                    <p class="text-sm text-ink-500">
                        Running v<?= e($update['current_version']) ?>.
                        <?php if ($update['checked_at']): ?>
                            Last checked <?= e($update['checked_at']) ?>.
                        <?php else: ?>
                            Never checked for updates.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="/settings/update/check">
                        <?= csrf_field() ?>
                        <button class="btn-secondary"><?= icon('search') ?> Check for updates</button>
                    </form>
                    <?php if ($update['is_newer']): ?>
                        <form method="post" action="/settings/update/apply" onsubmit="return confirm('This will download and install v<?= e($update['latest_version']) ?> over the current installation, with a backup saved to storage/backups/. Continue?');">
                            <?= csrf_field() ?>
                            <button class="btn-primary"><?= icon('download') ?> Update to v<?= e($update['latest_version']) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($update['is_newer']): ?>
                <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 p-4">
                    <p class="font-bold text-ink-900"><?= e($update['release_name'] ?: 'v' . $update['latest_version']) ?></p>
                    <?php if ($update['release_notes']): ?>
                        <pre class="mt-2 max-h-48 overflow-y-auto whitespace-pre-wrap text-sm text-ink-700"><?= e($update['release_notes']) ?></pre>
                    <?php endif; ?>
                    <?php if ($update['release_url']): ?>
                        <a class="mt-2 inline-block text-sm font-semibold text-brand-700 underline" href="<?= e($update['release_url']) ?>" target="_blank" rel="noopener">View release on GitHub</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Email templates</h2>
            <div class="mt-5 space-y-3">
                <?php foreach ($emailTemplates as $template): ?>
                    <details class="rounded-lg border border-ink-100 bg-white p-4">
                        <summary class="cursor-pointer font-bold text-ink-800"><?= e($template['template_key']) ?> · <?= e($template['subject']) ?></summary>
                        <pre class="mt-3 whitespace-pre-wrap rounded-md bg-ink-50 p-3 text-sm text-ink-700"><?= e($template['body']) ?></pre>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Audit log</h2>
            <div class="mt-5 max-h-[28rem] overflow-y-auto rounded-lg border border-ink-100">
                <?php foreach ($auditLogs as $log): ?>
                    <div class="border-b border-ink-100 bg-white px-4 py-3 last:border-b-0">
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-bold text-ink-800"><?= e($log['action']) ?></span>
                            <span class="text-xs text-ink-500"><?= e($log['created_at']) ?></span>
                        </div>
                        <p class="mt-1 text-sm text-ink-500"><?= e($log['user_name'] ?? 'System') ?> · <?= e($log['entity_type']) ?> #<?= e($log['entity_id']) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$auditLogs): ?>
                    <?php empty_state([
                        'compact' => true,
                        'icon' => 'shield',
                        'title' => 'No audit events yet',
                        'description' => 'Logins, financial actions, and privacy operations will be recorded here as your team uses LedgerFlow.',
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

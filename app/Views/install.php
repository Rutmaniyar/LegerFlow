<?php
/**
 * @var array<string,bool> $checks
 * @var string[] $timezones
 * @var array $currencies
 * @var string[] $countries
 */

$allGood = !in_array(false, $checks, true);
$selectedTimezone = (string) old('timezone', date_default_timezone_get());
$selectedCurrency = (string) old('currency', 'USD');
$selectedCountry = (string) old('business_country', '');
?>
<section class="grid gap-6 lg:grid-cols-[0.9fr_1.45fr]">
    <div class="overflow-hidden rounded-lg border border-ink-800 bg-ink-950 text-white shadow-soft">
        <div class="p-6">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-500 text-white">
                <?= icon('shield', 'h-6 w-6') ?>
            </div>
            <p class="text-xs font-black uppercase tracking-[0.16em] text-brand-300">Shared hosting installer</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-white">Install LedgerFlow</h1>
            <p class="mt-3 text-sm leading-6 text-ink-300">Checks PHP compatibility, writes protected configuration, runs MySQL migrations, seeds defaults, and locks setup after completion.</p>
        </div>

        <div class="space-y-2 border-t border-white/10 bg-white/5 p-4">
            <?php foreach ($checks as $label => $passed): ?>
                <div class="flex items-center justify-between rounded-md border border-white/10 bg-white/10 px-3 py-2">
                    <span class="text-sm font-semibold text-ink-100"><?= e($label) ?></span>
                    <span class="badge <?= $passed ? 'bg-brand-200 text-brand-900' : 'bg-red-200 text-red-900' ?>">
                        <?= $passed ? 'Ready' : 'Fix' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post" action="/install" class="card p-6 hover:shadow-soft">
        <?= csrf_field() ?>
        <div class="space-y-5">
            <div class="form-section">
                <div class="mb-4">
                    <p class="form-section-title"><span class="step-badge">1</span> Site and database</p>
                    <p class="section-copy">Use the database details from your cPanel, DirectAdmin, or shared-hosting control panel.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label>
                        <span class="label">Site URL *</span>
                        <input class="field" name="site_url" type="url" required value="<?= e(old('site_url', 'https://example.com')) ?>" autocomplete="url">
                    </label>
                    <label>
                        <span class="label">Timezone</span>
                        <select class="field" name="timezone" required>
                            <?php foreach ($timezones as $timezone): ?>
                                <option value="<?= e(secure_option('timezone', $timezone)) ?>" <?= secure_option_selected('timezone', $selectedTimezone, $timezone) ?>><?= e($timezone) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="label">Database host *</span>
                        <input class="field" name="db_host" required value="<?= e(old('db_host', 'localhost')) ?>">
                    </label>
                    <label>
                        <span class="label">Database port</span>
                        <input class="field" name="db_port" inputmode="numeric" value="<?= e(old('db_port', '3306')) ?>">
                    </label>
                    <label>
                        <span class="label">Database name *</span>
                        <input class="field" name="db_name" required value="<?= e(old('db_name')) ?>">
                    </label>
                    <label>
                        <span class="label">Database username *</span>
                        <input class="field" name="db_user" required value="<?= e(old('db_user')) ?>">
                    </label>
                    <label class="md:col-span-2">
                        <span class="label">Database password</span>
                        <input class="field" name="db_password" type="password" autocomplete="new-password">
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" data-test-connection="/install/test-db" data-test-fields="db_host,db_port,db_name,db_user,db_password" data-test-result="db-test-result">
                        <?= icon('check') ?> <span data-test-label>Test database connection</span>
                    </button>
                    <p id="db-test-result" class="text-sm font-semibold"></p>
                </div>
            </div>

            <div class="form-section">
                <div class="mb-4">
                    <p class="form-section-title"><span class="step-badge">2</span> Business profile</p>
                    <p class="section-copy">These details are seeded into invoice, quote, PDF, and email defaults.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label>
                        <span class="label">Business name *</span>
                        <input class="field" name="business_name" required value="<?= e(old('business_name')) ?>">
                    </label>
                    <label>
                        <span class="label">Business email *</span>
                        <input class="field" name="business_email" type="email" required value="<?= e(old('business_email')) ?>" autocomplete="email">
                    </label>
                    <label>
                        <span class="label">Business phone</span>
                        <input class="field" name="business_phone" type="tel" value="<?= e(old('business_phone')) ?>" autocomplete="tel">
                    </label>
                    <label>
                        <span class="label">Currency</span>
                        <select class="field" name="currency" required>
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= e(secure_option('currency', $currency['code'])) ?>" <?= secure_option_selected('currency', $selectedCurrency, $currency['code']) ?>><?= e($currency['code']) ?> - <?= e($currency['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="md:col-span-2">
                        <span class="label">Address</span>
                        <input class="field" name="business_address" value="<?= e(old('business_address')) ?>" autocomplete="street-address">
                    </label>
                    <label>
                        <span class="label">City</span>
                        <input class="field" name="business_city" value="<?= e(old('business_city')) ?>">
                    </label>
                    <label>
                        <span class="label">Country</span>
                        <select class="field" name="business_country">
                            <option value="">Select country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= e(secure_option('country', $country)) ?>" <?= secure_option_selected('country', $selectedCountry, $country) ?>><?= e($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="label">Region</span>
                        <input class="field" name="business_region" value="<?= e(old('business_region')) ?>">
                    </label>
                    <label>
                        <span class="label">Postal code</span>
                        <input class="field" name="business_postal_code" value="<?= e(old('business_postal_code')) ?>">
                    </label>
                    <label>
                        <span class="label">Default tax rate</span>
                        <input class="field" name="tax_rate" type="number" step="0.01" min="0" max="100" value="<?= e(old('tax_rate', '0')) ?>">
                    </label>
                </div>
            </div>

            <div class="form-section">
                <div class="mb-4">
                    <p class="form-section-title"><span class="step-badge">3</span> Mail / SMTP</p>
                    <p class="section-copy">Used for invoice emails, payment reminders, and password resets. Choose PHP mail() if your host supports it, or enter SMTP details and test the connection.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label>
                        <span class="label">Transport</span>
                        <select class="field" name="mail_transport">
                            <option value="mail" <?= old('mail_transport', 'mail') === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                            <option value="smtp" <?= old('mail_transport') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                        </select>
                    </label>
                    <label>
                        <span class="label">Encryption</span>
                        <select class="field" name="mail_encryption">
                            <option value="tls" <?= old('mail_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= old('mail_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= old('mail_encryption') === '' && old('mail_transport') !== '' ? 'selected' : '' ?>>None</option>
                        </select>
                    </label>
                    <label>
                        <span class="label">SMTP host</span>
                        <input class="field" name="mail_host" value="<?= e(old('mail_host')) ?>" placeholder="smtp.example.com">
                    </label>
                    <label>
                        <span class="label">SMTP port</span>
                        <input class="field" name="mail_port" inputmode="numeric" value="<?= e(old('mail_port', '587')) ?>">
                    </label>
                    <label>
                        <span class="label">SMTP username</span>
                        <input class="field" name="mail_username" value="<?= e(old('mail_username')) ?>" autocomplete="username">
                    </label>
                    <label>
                        <span class="label">SMTP password</span>
                        <input class="field" name="mail_password" type="password" autocomplete="new-password">
                    </label>
                    <label>
                        <span class="label">From email</span>
                        <input class="field" name="mail_from_email" type="email" value="<?= e(old('mail_from_email')) ?>" placeholder="Defaults to business email">
                    </label>
                    <label>
                        <span class="label">From name</span>
                        <input class="field" name="mail_from_name" value="<?= e(old('mail_from_name')) ?>" placeholder="Defaults to business name">
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" data-test-connection="/install/test-mail" data-test-fields="mail_host,mail_port,mail_username,mail_password,mail_encryption" data-test-result="mail-test-result">
                        <?= icon('send') ?> <span data-test-label>Test SMTP connection</span>
                    </button>
                    <p id="mail-test-result" class="text-sm font-semibold"></p>
                </div>
            </div>

            <div class="form-section">
                <div class="mb-4">
                    <p class="form-section-title"><span class="step-badge">4</span> Administrator</p>
                    <p class="section-copy">Create the first owner account. Passwords are hashed before storage.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label>
                        <span class="label">Name *</span>
                        <input class="field" name="admin_name" required value="<?= e(old('admin_name')) ?>" autocomplete="name">
                    </label>
                    <label>
                        <span class="label">Email *</span>
                        <input class="field" name="admin_email" type="email" required value="<?= e(old('admin_email')) ?>" autocomplete="email">
                    </label>
                    <label>
                        <span class="label">Password *</span>
                        <input class="field" name="admin_password" type="password" minlength="10" required autocomplete="new-password">
                    </label>
                    <label>
                        <span class="label">Confirm password *</span>
                        <input class="field" name="admin_password_confirmation" type="password" minlength="10" required autocomplete="new-password">
                    </label>
                </div>
            </div>
        </div>
        <button class="btn-primary mt-6 w-full" <?= $allGood ? '' : 'disabled aria-disabled="true"' ?>>
            <?= icon('check') ?> Install application
        </button>
    </form>
</section>

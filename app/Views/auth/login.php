<section class="mx-auto grid max-w-4xl gap-6 lg:grid-cols-[0.8fr_1.05fr]">
    <aside data-motion-guest-aside class="overflow-hidden rounded-lg border border-ink-800 bg-ink-950 text-white shadow-soft">
        <div class="p-6 sm:p-7">
            <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg bg-brand-500 text-white">
                <?= icon('shield', 'h-6 w-6') ?>
            </div>
            <p class="text-xs font-black uppercase tracking-[0.16em] text-brand-300">Finance control room</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-white">Run billing without losing the trail</h1>
        </div>
    </aside>

    <form method="post" action="/login" data-motion-guest-form class="auth-card p-0 hover:shadow-soft">
        <?= csrf_field() ?>
        <div class="border-b border-ink-100 bg-ink-50 p-6">
            <p class="eyebrow">Secure workspace</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-ink-950">Sign in</h2>
            <p class="mt-2 text-sm leading-6 text-ink-500">Access invoices, payments, reports, and client ledgers.</p>
        </div>

        <div class="space-y-4 p-6">
            <label>
                <span class="label">Email</span>
                <input class="field" name="email" type="email" required value="<?= e(old('email')) ?>" autocomplete="email" placeholder="you@example.com">
            </label>
            <label>
                <span class="label">Password</span>
                <input class="field" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
            </label>
        </div>

        <div class="space-y-3 px-6 pb-6">
            <button class="btn-primary mt-2 w-full"><?= icon('lock') ?> Sign in</button>
            <a href="/forgot-password" class="block text-center text-sm font-semibold text-brand-700 hover:text-brand-800">Forgot password?</a>
        </div>
    </form>
</section>

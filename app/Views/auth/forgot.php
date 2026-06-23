<section class="mx-auto max-w-md" data-motion="fade-up">
    <form method="post" action="/forgot-password" class="card p-6">
        <?= csrf_field() ?>
        <h1 class="text-2xl font-black tracking-tight text-ink-900">Reset password</h1>
        <p class="mt-2 text-sm leading-6 text-ink-600">Enter your account email and LedgerFlow will send a time-limited reset link.</p>
        <label class="mt-6 block">
            <span class="label">Email</span>
            <input class="field" name="email" type="email" required autocomplete="email">
        </label>
        <button class="btn-primary mt-6 w-full"><?= icon('send') ?> Send reset link</button>
        <a href="/login" class="mt-4 block text-center text-sm font-semibold text-brand-700">Back to sign in</a>
    </form>
</section>

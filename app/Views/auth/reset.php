<section class="mx-auto max-w-md" data-motion="fade-up">
    <form method="post" action="/reset-password" class="card p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <h1 class="text-2xl font-black tracking-tight text-ink-900">Choose a new password</h1>
        <div class="mt-6 space-y-4">
            <label>
                <span class="label">New password</span>
                <input class="field" name="password" type="password" required minlength="10" autocomplete="new-password">
            </label>
            <label>
                <span class="label">Confirm new password</span>
                <input class="field" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">
            </label>
        </div>
        <button class="btn-primary mt-6 w-full"><?= icon('check') ?> Update password</button>
    </form>
</section>

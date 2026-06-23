<?php
$success = flash('success');
$errors = flash('errors') ?? [];
?>
<?php if ($success): ?>
    <div data-flash="success" class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800" role="status">
        <?= e($success) ?>
    </div>
<?php endif; ?>
<?php if ($errors): ?>
    <div data-flash="errors" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <p class="font-bold">Please fix the highlighted issues.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/**
 * @var string $content Rendered by View::render() before this layout is required.
 * @var string|null $title
 * @var array|null $business
 */

use App\Services\SettingsService;

$business = $business ?? [];
if ($business === [] && app()->isInstalled()) {
    try {
        $business = (new SettingsService())->business();
    } catch (\Throwable) {
        $business = [];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <script>if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('motion-ready');}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? config('app.name', 'LedgerFlow')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e((defined('PUBLIC_URL_PREFIX') ? rtrim(PUBLIC_URL_PREFIX, '/') : '') . '/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php \App\Core\View::partial('partials/brand-theme', ['business' => $business]); ?>
</head>
<body class="app-shell">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-ink-900 focus:shadow-soft">Skip to main content</a>
    <main id="main" class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-5xl">
            <div class="mb-8 flex items-center justify-center">
                <?php \App\Core\View::partial('partials/brand', ['business' => $business, 'size' => 'lg']); ?>
            </div>

            <?php \App\Core\View::partial('partials/flash'); ?>
            <?= $content ?>
        </div>
    </main>
    <?php \App\Core\View::partial('partials/cookie-banner'); ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('js/motion-bundle.js')) ?>" defer></script>
</body>
</html>

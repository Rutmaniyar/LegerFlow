<?php
/**
 * @var string $content Rendered by View::render() before this layout is required.
 * @var string|null $title
 * @var array|null $business
 */

use App\Core\Auth;
use App\Services\SettingsService;

$user = Auth::user();
$business = $business ?? (new SettingsService())->business();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <script>if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('motion-ready');}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(($title ?? 'Dashboard') . ' · ' . config('app.name', 'LedgerFlow')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e((defined('PUBLIC_URL_PREFIX') ? rtrim(PUBLIC_URL_PREFIX, '/') : '') . '/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php \App\Core\View::partial('partials/brand-theme', ['business' => $business]); ?>
</head>
<body class="app-shell">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-ink-900 focus:shadow-soft">Skip to main content</a>
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-ink-800 bg-ink-950 px-4 py-5 text-white shadow-2xl transition duration-200 lg:static lg:translate-x-0 lg:shadow-none motion-reduce:transition-none">
            <div class="mb-8 flex items-center justify-between">
                <a href="/dashboard" class="flex items-center gap-3">
                    <?php \App\Core\View::partial('partials/brand', ['business' => $business, 'variant' => 'dark']); ?>
                </a>
                <button type="button" class="btn-secondary h-9 w-9 p-0 lg:hidden" data-sidebar-close aria-label="Close navigation"><?= icon('x') ?></button>
            </div>
            <?php \App\Core\View::partial('partials/sidebar'); ?>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-white/70 bg-ink-50/85 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="btn-secondary h-10 w-10 p-0 lg:hidden" data-sidebar-open aria-label="Open navigation"><?= icon('menu') ?></button>
                        <div>
                            <h1 class="text-xl font-black tracking-tight text-ink-900"><?= e($title ?? 'Dashboard') ?></h1>
                            <p class="hidden text-sm text-ink-500 sm:block">Operational billing, payments, expenses, and reporting.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <form action="/clients" method="get" class="hidden w-72 lg:block">
                            <label class="sr-only" for="global-search">Search clients</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400"><?= icon('search', 'h-4 w-4') ?></span>
                                <input id="global-search" name="q" class="field pl-9" placeholder="Search clients">
                            </div>
                        </form>
                        <a href="/invoices/create" class="btn-primary hidden sm:inline-flex"><?= icon('plus') ?> New invoice</a>
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-bold text-ink-800"><?= e($user['name'] ?? '') ?></p>
                            <p class="text-xs font-semibold text-ink-500"><?= e($user['role_name'] ?? '') ?></p>
                        </div>
                        <form method="post" action="/logout">
                            <?= csrf_field() ?>
                            <button class="btn-secondary h-10 w-10 p-0" aria-label="Sign out"><?= icon('lock') ?></button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="main" class="px-4 py-6 sm:px-6 lg:px-8">
                <?php \App\Core\View::partial('partials/flash'); ?>
                <?= $content ?>
            </main>
        </div>
    </div>
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-ink-900/30 lg:hidden" data-sidebar-close></div>
    <?php \App\Core\View::partial('partials/cookie-banner'); ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('js/motion-bundle.js')) ?>" defer></script>
</body>
</html>

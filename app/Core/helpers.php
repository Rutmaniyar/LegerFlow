<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\SignedOption;
use App\Core\View;

function app(): App
{
    return App::instance();
}

function config(string $key, mixed $default = null): mixed
{
    $value = app()->config();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    $path = '/' . ltrim($path, '/');

    return $base . ($path === '/' ? '' : $path);
}

function asset(string $path): string
{
    $prefix = defined('PUBLIC_URL_PREFIX') ? PUBLIC_URL_PREFIX : '';
    $relative = ltrim($path, '/');
    $url = rtrim($prefix, '/') . '/assets/' . $relative;

    $file = (defined('PUBLIC_PATH') ? PUBLIC_PATH : '') . '/assets/' . $relative;
    $version = is_file($file) ? filemtime($file) : false;

    return $version !== false ? $url . '?v=' . $version : $url;
}

function upload_url(string $path): string
{
    $prefix = defined('PUBLIC_URL_PREFIX') ? PUBLIC_URL_PREFIX : '';
    return rtrim($prefix, '/') . '/uploads/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $amount, string $currency = 'USD'): string
{
    return number_format((float) $amount, 2) . ' ' . e($currency);
}

function percentage(mixed $amount): string
{
    return rtrim(rtrim(number_format((float) $amount, 2), '0'), '.') . '%';
}

function currency_symbol(string $code): string
{
    foreach (\App\Support\ReferenceData::currencies() as $currency) {
        if ($currency['code'] === $code) {
            return $currency['symbol'];
        }
    }

    return $code;
}

/** Generates a 50-900 tint/shade ramp from a single base hex color, as "r g b" triples for CSS custom properties. */
function color_shades(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = '0ea394';
    }

    $base = [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];

    $tints = ['50' => 0.95, '100' => 0.88, '200' => 0.74, '300' => 0.58, '400' => 0.32];
    $shades = ['600' => 0.12, '700' => 0.26, '800' => 0.40, '900' => 0.55];

    $mix = static fn (int $channel, int $toward, float $ratio): int => (int) round($channel + ($toward - $channel) * $ratio);

    $ramp = [];
    foreach ($tints as $step => $ratio) {
        $ramp[$step] = implode(' ', array_map(static fn (int $c) => $mix($c, 255, $ratio), $base));
    }
    $ramp['500'] = implode(' ', $base);
    foreach ($shades as $step => $ratio) {
        $ramp[$step] = implode(' ', array_map(static fn (int $c) => $mix($c, 0, $ratio), $base));
    }

    ksort($ramp, SORT_NUMERIC);
    return $ramp;
}

function route_is(string $prefix): bool
{
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
    return $path === trim($prefix, '/') || str_starts_with($path, trim($prefix, '/') . '/');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function secure_option(string $scope, mixed $value): string
{
    return SignedOption::seal($scope, (string) $value);
}

function secure_option_selected(string $scope, mixed $current, mixed $value): string
{
    $resolved = SignedOption::displayValue($scope, $current);
    return hash_equals((string) $resolved, (string) $value) ? 'selected' : '';
}

function old(string $key, mixed $default = ''): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = Session::pull('_old', []);
    }

    return $cache[$key] ?? $default;
}

function old_line_items(array $fallback = []): array
{
    $descriptions = old('item_description', null);
    if (!is_array($descriptions)) {
        return $fallback;
    }

    $quantities = (array) old('item_quantity', []);
    $prices = (array) old('item_unit_price', []);
    $discountTypes = (array) old('item_discount_type', []);
    $discountValues = (array) old('item_discount_value', []);
    $taxRates = (array) old('item_tax_rate', []);
    $items = [];

    foreach (array_slice($descriptions, 0, 200, true) as $index => $description) {
        $items[] = [
            'description' => $description,
            'quantity' => $quantities[$index] ?? '1',
            'unit_price' => $prices[$index] ?? '0.00',
            'discount_type' => $discountTypes[$index] ?? 'percent',
            'discount_value' => $discountValues[$index] ?? '0',
            'tax_rate' => $taxRates[$index] ?? '0',
        ];
    }

    return $items;
}

function flash(string $key): mixed
{
    return Session::pull($key);
}

function empty_state(array $props): void
{
    View::partial('partials/empty-state', $props);
}

function icon(string $name, string $class = 'h-4 w-4'): string
{
    $icons = [
        'dashboard' => '<path d="M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z"/>',
        'clients' => '<path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'quotes' => '<path d="M7 3h8l4 4v14H7V3Z"/><path d="M15 3v5h5M10 13h6M10 17h4"/>',
        'invoices' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'payments' => '<path d="M3 7h18v10H3V7Z"/><path d="M3 10h18M7 15h4"/>',
        'expenses' => '<path d="M12 3v18M7 7h7.5a3.5 3.5 0 0 1 0 7H9a3 3 0 0 0 0 6h8"/>',
        'vendors' => '<path d="M4 7h16v12H4V7Z"/><path d="M8 7V5h8v2M8 12h8M8 16h5"/>',
        'reports' => '<path d="M4 19V5M9 19v-8M14 19V8M19 19v-5"/>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="m19.4 15 .6 2.2-2.1 1.2-1.6-1.1a7 7 0 0 1-1.9 1.1L14 21h-4l-.4-2.6a7 7 0 0 1-1.9-1.1l-1.6 1.1L4 17.2l.6-2.2A7.8 7.8 0 0 1 4 13l-2-1 1.2-3 2.2.2A8.4 8.4 0 0 1 7 7.6L6.8 5.4 9.8 4 11 6a7 7 0 0 1 2 0l1.2-2 3 1.4-.2 2.2a8.4 8.4 0 0 1 1.6 1.6l2.2-.2L22 12l-2 1a7.8 7.8 0 0 1-.6 2Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="m22 2-11 11"/>',
        'download' => '<path d="M12 3v12m0 0 5-5m-5 5-5-5"/><path d="M5 21h14"/>',
        'edit' => '<path d="M12 20h9"/><path d="m16.5 3.5 4 4L8 20H4v-4L16.5 3.5Z"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/>',
        'lock' => '<path d="M7 11V8a5 5 0 0 1 10 0v3"/><path d="M5 11h14v10H5V11Z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'check' => '<path d="m5 13 4 4L19 7"/>',
        'warning' => '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5M12 17h.01"/>',
        'search' => '<path d="m21 21-4.3-4.3"/><circle cx="11" cy="11" r="8"/>',
        'arrow-up' => '<path d="M12 19V5m0 0-5 5m5-5 5 5"/>',
        'arrow-down' => '<path d="M12 5v14m0 0 5-5m-5 5-5-5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'user' => '<path d="M19 21a7 7 0 0 0-14 0"/><circle cx="12" cy="7" r="4"/>',
        'spark' => '<path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/>',
    ];

    $body = $icons[$name] ?? $icons['spark'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

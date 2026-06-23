<?php
/**
 * Print template for expenses - rendered as plain HTML and either displayed directly
 * (preview) or fed into Dompdf to produce the "Expense Receipt" PDF. Mirrors the styling
 * of pdf/document.php (used for invoices/quotes) so printed documents look consistent.
 *
 * @var array $business
 * @var array $expense
 * @var bool $forPdf Whether this HTML is being fed to Dompdf (local filesystem image paths) or shown in a browser (public URLs).
 */

$currency = (string) ($expense['currency'] ?? 'USD');
$brandHex = (string) ($business['brand_color'] ?? '#0ea394');
$shades = color_shades($brandHex);
$rgb = static fn (string $shade): string => 'rgb(' . str_replace(' ', ', ', $shades[$shade]) . ')';

$logoPath = trim((string) ($business['logo_path'] ?? ''));
$logoSrc = null;
if ($logoPath !== '') {
    if (!empty($forPdf)) {
        $absolute = PUBLIC_PATH . '/uploads/' . $logoPath;
        $logoSrc = (extension_loaded('gd') && is_file($absolute)) ? str_replace('\\', '/', $absolute) : null;
    } else {
        $logoSrc = upload_url($logoPath);
    }
}

$total = (float) ($expense['amount'] ?? 0) + (float) ($expense['tax_amount'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e('Expense receipt - ' . ($expense['vendor'] ?? '')) ?></title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; line-height: 1.5; color: #111827; margin: 0; }
    .accent-bar { height: 6pt; background: <?= $rgb('500') ?>; }
    .content { padding: 28pt 40pt 42pt; }
    .header { width: 100%; }
    .header td { vertical-align: top; }
    .logo { width: 48pt; height: 48pt; object-fit: cover; border-radius: 4pt; margin-bottom: 8pt; }
    .business-name { font-size: 14pt; font-weight: bold; color: #111827; margin: 0 0 4pt; }
    .muted { color: #637487; }
    .doc-label { font-size: 17pt; font-weight: bold; color: <?= $rgb('700') ?>; text-align: right; margin: 0; letter-spacing: 0.5pt; }
    .meta-row { text-align: right; font-size: 9pt; color: #637487; }
    hr.divider { border: none; border-top: 1pt solid #d9e1ea; margin: 18pt 0; }
    table.facts { width: 100%; border-collapse: collapse; margin-top: 6pt; }
    table.facts td { padding: 7pt 0; border-bottom: 0.75pt solid #eef2f7; font-size: 9.5pt; }
    table.facts td.label { color: #637487; font-weight: bold; font-size: 8pt; letter-spacing: 0.4pt; text-transform: uppercase; width: 40%; }
    table.facts td.value { text-align: right; }
    .totals-row td { border-bottom: none; padding-top: 14pt; font-size: 12pt; font-weight: bold; }
    .notes-label { font-size: 8pt; font-weight: bold; letter-spacing: 0.4pt; color: #637487; margin: 24pt 0 4pt; }
    .footer { margin-top: 36pt; padding-top: 10pt; border-top: 0.75pt solid #d9e1ea; font-size: 8pt; color: #8799aa; }
</style>
</head>
<body>
<div class="accent-bar"></div>
<div class="content">
    <table class="header">
        <tr>
            <td style="width:60%">
                <?php if ($logoSrc): ?><img class="logo" src="<?= e($logoSrc) ?>" alt=""><?php endif; ?>
                <p class="business-name"><?= e($business['business_name'] ?? 'Business') ?></p>
                <p class="muted"><?= e(trim(($business['address_line1'] ?? '') . ' ' . ($business['city'] ?? '') . ' ' . ($business['country'] ?? ''))) ?></p>
            </td>
            <td style="width:40%">
                <p class="doc-label">EXPENSE RECEIPT</p>
                <p class="meta-row">Date: <strong><?= e($expense['expense_date'] ?? '') ?></strong></p>
                <?php if (!empty($expense['payment_method'])): ?>
                    <p class="meta-row">Paid via: <strong><?= e($expense['payment_method']) ?></strong></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <hr class="divider">

    <table class="facts">
        <tr><td class="label">Vendor</td><td class="value"><?= e($expense['vendor'] ?? '') ?></td></tr>
        <tr><td class="label">Category</td><td class="value"><?= e($expense['category'] ?? '') ?></td></tr>
        <tr><td class="label">Amount</td><td class="value"><?= money($expense['amount'] ?? 0, $currency) ?></td></tr>
        <?php if (abs((float) ($expense['tax_amount'] ?? 0)) > 0.00001): ?>
            <tr><td class="label">Tax</td><td class="value"><?= money($expense['tax_amount'], $currency) ?></td></tr>
        <?php endif; ?>
        <tr class="totals-row"><td>Total</td><td class="value"><?= money($total, $currency) ?></td></tr>
    </table>

    <?php $notes = trim((string) ($expense['notes'] ?? '')); ?>
    <?php if ($notes !== ''): ?>
        <p class="notes-label">NOTES</p>
        <p><?= nl2br(e($notes)) ?></p>
    <?php endif; ?>

    <div class="footer">Recorded for bookkeeping purposes.</div>
</div>
</body>
</html>

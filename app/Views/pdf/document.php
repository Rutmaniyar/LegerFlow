<?php
/**
 * Shared print template for invoices and quotes - rendered as plain HTML and either
 * displayed directly (preview) or fed into Dompdf to produce the PDF. Keeping this as
 * real HTML/CSS (rather than hand-rolled PDF drawing operators) makes the document easy
 * to design and check visually in a browser before it ever becomes a PDF.
 *
 * @var array $business
 * @var array $document
 * @var array $items
 * @var array $payments
 * @var string $numberKey
 * @var bool $isInvoice
 * @var string $defaultCurrency
 * @var bool $forPdf Whether this HTML is being fed to Dompdf (local filesystem image paths) or shown in a browser (public URLs).
 */

$showDiscount = abs((float) ($document['discount_total'] ?? 0)) > 0.00001;
$showTax = abs((float) ($document['tax_total'] ?? 0)) > 0.00001;
$isPaid = $isInvoice && ($document['status'] ?? '') === 'paid';
$currency = (string) ($document['currency'] ?? 'USD');
$isForeignCurrency = $defaultCurrency !== '' && $currency !== $defaultCurrency;

$brandHex = (string) ($business['brand_color'] ?? '#0ea394');
$shades = color_shades($brandHex);
$rgb = static fn (string $shade): string => 'rgb(' . str_replace(' ', ', ', $shades[$shade]) . ')';

$logoPath = trim((string) ($business['logo_path'] ?? ''));
$logoSrc = null;
if ($logoPath !== '') {
    if (!empty($forPdf)) {
        // Dompdf's PDF backend requires the GD extension to embed raster images at all - without it,
        // rendering throws rather than degrading gracefully, so the logo is skipped instead of crashing the PDF.
        $absolute = PUBLIC_PATH . '/uploads/' . $logoPath;
        $logoSrc = (extension_loaded('gd') && is_file($absolute)) ? str_replace('\\', '/', $absolute) : null;
    } else {
        $logoSrc = upload_url($logoPath);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(($isInvoice ? 'Invoice ' : 'Quote ') . $document[$numberKey]) ?></title>
<style>
    /*
     * @page margin is intentionally 0 - all spacing is controlled by .content's padding instead, so this
     * renders identically whether viewed in a browser (preview) or fed into Dompdf (PDF). @page margins are
     * a print-only concept browsers ignore on screen, which made the accent bar invisible in preview before.
     */
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 9.5pt;
        line-height: 1.5;
        color: #111827;
        margin: 0;
    }
    .accent-bar { height: 6pt; background: <?= $rgb('500') ?>; }
    .content { padding: 28pt 40pt 42pt; }
    .header { width: 100%; }
    .header td { vertical-align: top; }
    .logo { width: 48pt; height: 48pt; object-fit: cover; border-radius: 4pt; margin-bottom: 8pt; }
    .business-name { font-size: 14pt; font-weight: bold; color: #111827; margin: 0 0 4pt; }
    .muted { color: #637487; }
    .doc-label { font-size: 17pt; font-weight: bold; color: <?= $rgb('700') ?>; text-align: right; margin: 0; letter-spacing: 0.5pt; }
    .doc-number { font-size: 11pt; font-weight: bold; text-align: right; margin: 4pt 0 10pt; }
    .meta-row { text-align: right; font-size: 9pt; color: #637487; }
    .meta-row strong { color: #111827; }
    .paid-on { color: <?= $rgb('700') ?>; font-weight: bold; }
    .foreign-currency-badge {
        display: inline-block;
        margin-top: 6pt;
        padding: 3pt 8pt;
        font-size: 8pt;
        font-weight: bold;
        border-radius: 3pt;
        border: 1pt solid <?= $rgb('300') ?>;
        background: <?= $rgb('50') ?>;
        color: <?= $rgb('800') ?>;
    }
    hr.divider { border: none; border-top: 1pt solid #d9e1ea; margin: 18pt 0; }
    .bill-to-label { font-size: 8pt; font-weight: bold; letter-spacing: 0.5pt; color: #637487; margin: 0 0 4pt; }
    .bill-to-name { font-size: 11pt; font-weight: bold; margin: 0 0 2pt; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 20pt; }
    table.items thead th {
        background: #f8fafc;
        border-bottom: 1pt solid #d9e1ea;
        text-align: left;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.4pt;
        color: #637487;
        padding: 7pt 8pt;
    }
    table.items thead th.num { text-align: right; }
    table.items tbody td {
        border-bottom: 0.75pt solid #eef2f7;
        padding: 7pt 8pt;
        vertical-align: top;
    }
    table.items tbody td.num { text-align: right; white-space: nowrap; }
    table.items tbody td.desc strong { display: block; }
    .totals { width: 100%; margin-top: 14pt; }
    .totals-inner { width: 230pt; float: right; }
    .totals-inner table { width: 100%; border-collapse: collapse; }
    .totals-inner td { padding: 4pt 0; font-size: 9.5pt; }
    .totals-inner td.label { color: #637487; }
    .totals-inner td.value { text-align: right; font-weight: bold; }
    .totals-inner tr.grand td { border-top: 1.25pt solid #111827; padding-top: 8pt; font-size: 11.5pt; }
    .totals-inner tr.grand td.value { color: <?= $rgb('700') ?>; }
    .clearfix { clear: both; }
    .section-label { font-size: 8pt; font-weight: bold; letter-spacing: 0.5pt; color: #637487; margin: 22pt 0 6pt; }
    table.payments { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.payments thead th {
        background: #f8fafc;
        border-bottom: 1pt solid #d9e1ea;
        text-align: left;
        font-size: 8pt;
        font-weight: bold;
        color: #637487;
        padding: 6pt 8pt;
    }
    table.payments thead th.num { text-align: right; }
    table.payments tbody td { border-bottom: 0.75pt solid #eef2f7; padding: 6pt 8pt; font-size: 9pt; }
    table.payments tbody td.num { text-align: right; font-weight: bold; color: <?= $rgb('700') ?>; }
    .notes-grid { width: 100%; margin-top: 4pt; }
    .notes-grid td { width: 50%; vertical-align: top; padding-right: 16pt; font-size: 9pt; color: #344154; white-space: pre-line; }
    /* Fixed (not in normal flow) so a footer a few points too tall for the last line of content can never
       spill onto an otherwise-blank extra page - it repeats at the bottom of every page instead. */
    .footer {
        position: fixed;
        bottom: 14pt;
        left: 40pt;
        right: 40pt;
        padding-top: 10pt;
        border-top: 0.75pt solid #d9e1ea;
        font-size: 8pt;
        color: #8799aa;
        text-align: center;
    }
    .paid-stamp {
        position: absolute;
        top: 230pt;
        left: 170pt;
        width: 260pt;
        padding: 14pt 0;
        text-align: center;
        font-size: 30pt;
        font-weight: bold;
        letter-spacing: 4pt;
        color: #0d6761;
        border: 3pt solid #0d6761;
        border-radius: 6pt;
        opacity: 0.25;
        transform: rotate(-18deg);
    }
</style>
</head>
<body>
<div class="accent-bar"></div>
<div class="content">

<table class="header">
    <tr>
        <td style="width: 55%;">
            <?php if ($logoSrc !== null): ?>
                <img class="logo" src="<?= e($logoSrc) ?>" alt="">
            <?php endif; ?>
            <p class="business-name"><?= e($business['business_name'] ?? 'Business') ?></p>
            <?php $addressLine = trim(($business['address_line1'] ?? '') . ' ' . ($business['city'] ?? '') . ' ' . ($business['country'] ?? '')); ?>
            <?php if ($addressLine !== ''): ?><p class="muted" style="margin: 0;"><?= e($addressLine) ?></p><?php endif; ?>
            <?php $contactLine = trim((string) ($business['email'] ?? '') . ($business['phone'] ? ' · ' . $business['phone'] : '')); ?>
            <?php if ($contactLine !== ''): ?><p class="muted" style="margin: 0;"><?= e($contactLine) ?></p><?php endif; ?>
        </td>
        <td style="width: 45%;">
            <p class="doc-label"><?= $isInvoice ? 'INVOICE' : 'QUOTE' ?></p>
            <p class="doc-number">#<?= e($document[$numberKey]) ?></p>
            <p class="meta-row">Issue date: <strong><?= e($document['issue_date'] ?? '') ?></strong></p>
            <?php if ($isInvoice): ?>
                <p class="meta-row">Due date: <strong><?= e($document['due_date'] ?? '') ?></strong></p>
            <?php else: ?>
                <p class="meta-row">Valid until: <strong><?= e($document['valid_until'] ?? '') ?></strong></p>
            <?php endif; ?>
            <?php if ($isPaid && !empty($document['paid_at'])): ?>
                <p class="meta-row paid-on">Paid on <?= e(date('M j, Y', strtotime((string) $document['paid_at']))) ?></p>
            <?php endif; ?>
            <?php if ($isForeignCurrency): ?>
                <p style="text-align: right;"><span class="foreign-currency-badge">Billed in <?= e($currency) ?> &middot; your default is <?= e($defaultCurrency) ?></span></p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<hr class="divider">

<p class="bill-to-label">BILL TO</p>
<p class="bill-to-name"><?= e($document['client_name'] ?? '') ?></p>
<?php if (!empty($document['client_email'])): ?><p class="muted" style="margin: 0;"><?= e($document['client_email']) ?></p><?php endif; ?>
<?php $billingAddress = trim((string) ($document['billing_address'] ?? '')); ?>
<?php if ($billingAddress !== ''): ?><p class="muted" style="margin: 4pt 0 0; white-space: pre-line;"><?= e($billingAddress) ?></p><?php endif; ?>

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit price</th>
            <?php if ($showTax): ?><th class="num">Tax</th><?php endif; ?>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td class="desc"><strong><?= e($item['description']) ?></strong></td>
                <td class="num"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?: '0') ?></td>
                <td class="num"><?= money($item['unit_price'], $currency) ?></td>
                <?php if ($showTax): ?><td class="num"><?= percentage($item['tax_rate']) ?></td><?php endif; ?>
                <td class="num"><?= money($item['line_total'], $currency) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totals">
    <div class="totals-inner">
        <table>
            <tr><td class="label">Subtotal</td><td class="value"><?= money($document['subtotal'], $currency) ?></td></tr>
            <?php if ($showDiscount): ?><tr><td class="label">Discount</td><td class="value">-<?= money($document['discount_total'], $currency) ?></td></tr><?php endif; ?>
            <?php if ($showTax): ?><tr><td class="label">Tax</td><td class="value"><?= money($document['tax_total'], $currency) ?></td></tr><?php endif; ?>
            <?php if ($isInvoice): ?>
                <tr><td class="label">Paid</td><td class="value">-<?= money($document['amount_paid'] ?? 0, $currency) ?></td></tr>
                <tr class="grand"><td class="label">Balance due</td><td class="value"><?= money($document['balance_due'] ?? $document['total'], $currency) ?></td></tr>
            <?php else: ?>
                <tr class="grand"><td class="label">Total</td><td class="value"><?= money($document['total'], $currency) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="clearfix"></div>
</div>

<?php if ($isInvoice && $payments !== []): ?>
    <p class="section-label">PAYMENT HISTORY</p>
    <table class="payments">
        <thead>
            <tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['payment_date']) ?></td>
                    <td><?= e($payment['method']) ?><?= ($payment['type'] ?? 'payment') === 'refund' ? ' (refund)' : '' ?></td>
                    <td><?= e($payment['reference'] ?? '') ?></td>
                    <td class="num"><?= ($payment['type'] ?? 'payment') === 'refund' ? '-' : '' ?><?= money($payment['amount'], $currency) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php $notes = trim((string) ($document['notes'] ?? '')); $terms = trim((string) ($document['terms'] ?? '')); ?>
<?php if ($notes !== '' || $terms !== ''): ?>
    <table class="notes-grid">
        <tr>
            <td>
                <?php if ($notes !== ''): ?>
                    <p class="section-label" style="margin-top: 0;">NOTES</p>
                    <p style="margin: 0;"><?= e($notes) ?></p>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($terms !== ''): ?>
                    <p class="section-label" style="margin-top: 0;">TERMS</p>
                    <p style="margin: 0;"><?= e($terms) ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
<?php endif; ?>

<?php if ($isPaid): ?>
    <div class="paid-stamp">PAID</div>
<?php endif; ?>

<div class="footer">Thank you for your business.</div>
</div>
</body>
</html>

<?php
$currency = $business['default_currency'] ?? 'USD';
$chartLabels = array_map(static fn ($row) => $row['month'], $monthly);
$chartValues = array_map(static fn ($row) => (float) $row['income'], $monthly);
$cards = [
    ['Income this month', $stats['income_month'], 'payments', 'bg-brand-100 text-brand-700'],
    ['Outstanding', $stats['outstanding'], 'invoices', 'bg-accent-100 text-accent-700'],
    ['Overdue', $stats['overdue'], 'warning', 'bg-red-100 text-red-700'],
    ['Active clients', $stats['clients'], 'clients', 'bg-amber-100 text-amber-700'],
];
?>
<?php if ($isFirstTime): ?>
    <section class="glass-strip mb-6 overflow-hidden">
        <div class="p-6 sm:p-10">
            <p class="eyebrow">Welcome to LedgerFlow</p>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-ink-950 sm:text-3xl">Welcome! Let's get you started.</h2>
            <p class="page-kicker mt-2 max-w-xl">Create your first invoice to begin tracking and managing your billing. Everything you add here flows straight into your dashboard, reports, and client ledger.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="/invoices/create" class="btn-primary"><?= icon('plus') ?> Create your first invoice</a>
                <a href="/clients" class="btn-secondary"><?= icon('clients') ?> Add a client first</a>
            </div>
            <?php empty_state([
                'variant' => 'onboarding',
                'compact' => true,
                'title' => '',
                'checklist' => $checklist,
            ]) ?>
        </div>
    </section>
<?php endif; ?>
<?php if (!$isFirstTime): ?>
<section class="glass-strip mb-6 overflow-hidden">
    <div class="grid gap-0 lg:grid-cols-[1.4fr_0.9fr]">
        <div class="p-5 sm:p-6">
            <p class="eyebrow">Command center</p>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-ink-950 sm:text-3xl">Cash flow at a glance</h2>
                    <p class="page-kicker mt-2 max-w-2xl">Monitor collections, overdue exposure, client growth, and invoice activity from one shared-hosting friendly workspace.</p>
                </div>
                <div class="flex gap-2">
                    <a href="/quotes/create" class="btn-secondary"><?= icon('quotes') ?> Quote</a>
                    <a href="/invoices/create" class="btn-primary"><?= icon('plus') ?> Invoice</a>
                </div>
            </div>
        </div>
        <div class="border-t border-ink-100 bg-ink-950 p-5 text-white lg:border-l lg:border-t-0">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-brand-300">Collection health</p>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-lg bg-white/10 p-3">
                    <p class="text-xs font-semibold text-ink-300">Open balance</p>
                    <p class="mt-1 text-xl font-black"><?= money($stats['outstanding'], $currency) ?></p>
                </div>
                <div class="rounded-lg bg-white/10 p-3">
                    <p class="text-xs font-semibold text-ink-300">Risk overdue</p>
                    <p class="mt-1 text-xl font-black text-red-200"><?= money($stats['overdue'], $currency) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Billing shortcuts" data-motion="fade-up" data-motion-stagger>
    <a class="quick-action" data-tilt href="/clients">
        <span class="metric-icon bg-brand-100 text-brand-700"><?= icon('clients', 'h-5 w-5') ?></span>
        <span>
            <span class="block text-sm font-black text-ink-900">Client ledger</span>
            <span class="mt-1 block text-xs font-semibold leading-5 text-ink-500">Review contacts and payment history.</span>
        </span>
    </a>
    <a class="quick-action" data-tilt href="/quotes/create">
        <span class="metric-icon bg-accent-100 text-accent-700"><?= icon('quotes', 'h-5 w-5') ?></span>
        <span>
            <span class="block text-sm font-black text-ink-900">New estimate</span>
            <span class="mt-1 block text-xs font-semibold leading-5 text-ink-500">Build a quote and convert it later.</span>
        </span>
    </a>
    <a class="quick-action" data-tilt href="/invoices/create">
        <span class="metric-icon bg-amber-100 text-amber-700"><?= icon('invoices', 'h-5 w-5') ?></span>
        <span>
            <span class="block text-sm font-black text-ink-900">Issue invoice</span>
            <span class="mt-1 block text-xs font-semibold leading-5 text-ink-500">Create, email, and export PDF billing.</span>
        </span>
    </a>
    <a class="quick-action" data-tilt href="/reports">
        <span class="metric-icon bg-ink-100 text-ink-700"><?= icon('reports', 'h-5 w-5') ?></span>
        <span>
            <span class="block text-sm font-black text-ink-900">Run reports</span>
            <span class="mt-1 block text-xs font-semibold leading-5 text-ink-500">Analyze income and outstanding debt.</span>
        </span>
    </a>
</section>

<?php if (!$isFirstTime): ?>
<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" data-motion="fade-up" data-motion-stagger>
    <?php foreach ($cards as [$label, $value, $iconName, $tone]): ?>
        <?php $isMoney = is_numeric($value) && $label !== 'Active clients'; ?>
        <article class="metric-card" data-tilt>
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-ink-500"><?= e($label) ?></p>
                    <p class="mt-2 text-3xl font-black tracking-tight text-ink-900"<?= is_numeric($value) ? ' data-count-up data-count-value="' . e($value) . '" data-count-decimals="' . ($isMoney ? '2' : '0') . '"' : '' ?>>
                        <?= $isMoney ? money($value, $currency) : e($value) ?>
                    </p>
                    <p class="mt-3 text-xs font-bold uppercase tracking-wide text-ink-400">Updated now</p>
                </div>
                <div class="metric-icon <?= e($tone) ?>">
                    <?= icon($iconName, 'h-5 w-5') ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-5 hover:shadow-soft">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-black text-ink-900">Income trend</h2>
                <p class="text-sm text-ink-500">Payment income over the last 12 months</p>
            </div>
            <a class="btn-secondary" href="/reports"><?= icon('reports') ?> Reports</a>
        </div>
        <canvas class="h-72 w-full" data-chart data-labels='<?= e(json_encode($chartLabels, JSON_THROW_ON_ERROR)) ?>' data-values='<?= e(json_encode($chartValues, JSON_THROW_ON_ERROR)) ?>'></canvas>
    </div>

    <div class="card p-5 hover:shadow-soft">
        <h2 class="text-lg font-black text-ink-900">Invoice status</h2>
        <div class="mt-5 space-y-3" data-motion="fade-up" data-motion-stagger>
            <?php foreach ($status as $row): ?>
                <div class="flex items-center justify-between rounded-md border border-ink-100 bg-ink-50 px-3 py-2 transition hover:border-brand-200 hover:bg-brand-50/60">
                    <span class="flex items-center gap-2 text-sm font-bold capitalize text-ink-700"><span class="status-dot"></span><?= e($row['status']) ?></span>
                    <span class="badge bg-white text-ink-700"><?= e($row['count']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$status): ?>
                <?php empty_state([
                    'compact' => true,
                    'icon' => 'invoices',
                    'title' => 'No invoices yet',
                    'description' => 'Create an invoice to start tracking billing status.',
                    'primaryActionLabel' => 'Create invoice',
                    'primaryActionHref' => '/invoices/create',
                ]) ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mt-6 card p-5" data-motion="fade-up">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-black text-ink-900">Recent invoices</h2>
            <p class="text-sm text-ink-500">Latest invoice activity and collection status</p>
        </div>
        <a href="/invoices/create" class="btn-primary"><?= icon('plus') ?> Create invoice</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                <?php foreach ($recentInvoices as $invoice): ?>
                    <tr>
                        <td><a class="font-bold text-brand-700" href="/invoices/<?= e($invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                        <td><?= e($invoice['client_name']) ?></td>
                        <td><span class="badge bg-ink-100 text-ink-700"><?= e($invoice['status']) ?></span></td>
                        <td><?= e($invoice['due_date']) ?></td>
                        <td class="text-right font-bold"><?= money($invoice['balance_due'], $invoice['currency']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentInvoices): ?>
                    <tr><td colspan="5">
                        <?php empty_state([
                            'compact' => true,
                            'icon' => 'invoices',
                            'title' => 'No invoices yet',
                            'description' => 'Create your first invoice to start tracking revenue.',
                            'primaryActionLabel' => 'Create invoice',
                            'primaryActionHref' => '/invoices/create',
                        ]) ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="table-footer">
            <span>Showing <?= e(count($recentInvoices)) ?> recent invoices</span>
            <span>Updated now</span>
        </div>
    </div>
</section>
<?php endif; ?>

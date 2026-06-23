<section class="space-y-6" data-motion="fade-up" data-motion-stagger>
    <div class="toolbar flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="eyebrow">Accounting insight</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-ink-950">Reports</h2>
            <p class="page-kicker">Measure income, expense, profit, outstanding balance, and client exposure.</p>
        </div>
    </div>

    <form method="get" action="/reports" class="card grid gap-4 p-5 sm:grid-cols-[1fr_1fr_auto]">
        <label><span class="label">From</span><input class="field" name="from" type="date" value="<?= e($from) ?>"></label>
        <label><span class="label">To</span><input class="field" name="to" type="date" value="<?= e($to) ?>"></label>
        <div class="flex items-end"><button class="btn-primary w-full"><?= icon('reports') ?> Run report</button></div>
    </form>

    <p class="text-xs font-bold uppercase tracking-wide text-ink-500">Figures below are in your default currency, <?= e($currency) ?> - the currency your business is always settled in.</p>

    <div class="grid gap-4 md:grid-cols-4" data-motion="fade-up" data-motion-stagger>
        <div class="metric-card" data-tilt><p class="text-sm font-semibold text-ink-500">Income</p><p class="mt-2 text-2xl font-black" data-count-up data-count-value="<?= e($income) ?>" data-count-decimals="2"><?= money($income, $currency) ?></p></div>
        <div class="metric-card" data-tilt><p class="text-sm font-semibold text-ink-500">Expenses</p><p class="mt-2 text-2xl font-black" data-count-up data-count-value="<?= e($expenses) ?>" data-count-decimals="2"><?= money($expenses, $currency) ?></p></div>
        <div class="metric-card" data-tilt><p class="text-sm font-semibold text-ink-500">Net</p><p class="mt-2 text-2xl font-black" data-count-up data-count-value="<?= e($profit) ?>" data-count-decimals="2"><?= money($profit, $currency) ?></p></div>
        <div class="metric-card" data-tilt><p class="text-sm font-semibold text-ink-500">Outstanding</p><p class="mt-2 text-2xl font-black" data-count-up data-count-value="<?= e($outstanding) ?>" data-count-decimals="2"><?= money($outstanding, $currency) ?></p></div>
    </div>

    <div class="card p-5" data-motion="fade-up">
        <h2 class="text-lg font-black text-ink-900">Client ledger</h2>
        <p class="text-sm text-ink-500">Invoices billed in <?= e($currency) ?> only - see "Other currencies" below for everything else.</p>
        <div class="mt-5 table-wrap">
            <table class="data-table">
                <thead><tr><th>Client</th><th class="text-right">Invoiced</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($clientLedger as $row): ?>
                        <tr>
                            <td class="font-bold"><?= e($row['name']) ?></td>
                            <td class="text-right"><?= money($row['invoiced'], $currency) ?></td>
                            <td class="text-right"><?= money($row['paid'], $currency) ?></td>
                            <td class="text-right font-bold"><?= money($row['outstanding'], $currency) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clientLedger): ?>
                        <tr><td colspan="4">
                            <?php empty_state([
                                'icon' => 'clients',
                                'title' => 'No client ledger data yet',
                                'description' => 'Once you add clients and bill them, invoiced, paid, and outstanding totals per client will show up here.',
                                'primaryActionLabel' => 'Add a client',
                                'primaryActionHref' => '/clients',
                            ]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="table-footer"><span><?= e(count($clientLedger)) ?> ledger rows</span><span>Period filtered</span></div>
        </div>
    </div>

    <?php if ($otherCurrencyIncome || $otherCurrencyOutstanding || $otherCurrencyLedger): ?>
        <div class="card border-amber-200 bg-amber-50/40 p-5">
            <h2 class="text-lg font-black text-ink-900">Other currencies</h2>
            <p class="text-sm text-ink-500">Invoices, payments, and balances booked in a currency other than your default (<?= e($currency) ?>). Shown separately rather than blended into the totals above, since adding different currencies together isn't meaningful.</p>

            <?php if ($otherCurrencyIncome): ?>
                <div class="mt-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-500">Income received</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <?php foreach ($otherCurrencyIncome as $row): ?>
                            <span class="badge bg-white text-ink-800"><?= money($row['total'], $row['currency']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($otherCurrencyOutstanding): ?>
                <div class="mt-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-500">Outstanding balance</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <?php foreach ($otherCurrencyOutstanding as $row): ?>
                            <span class="badge bg-white text-ink-800"><?= money($row['total'], $row['currency']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($otherCurrencyLedger): ?>
                <div class="mt-5 table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Client</th><th>Currency</th><th class="text-right">Invoiced</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th></tr></thead>
                        <tbody class="divide-y divide-ink-100">
                            <?php foreach ($otherCurrencyLedger as $row): ?>
                                <tr>
                                    <td class="font-bold"><?= e($row['name']) ?></td>
                                    <td><span class="badge bg-ink-100 text-ink-700"><?= e($row['currency']) ?></span></td>
                                    <td class="text-right"><?= money($row['invoiced'], $row['currency']) ?></td>
                                    <td class="text-right"><?= money($row['paid'], $row['currency']) ?></td>
                                    <td class="text-right font-bold"><?= money($row['outstanding'], $row['currency']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

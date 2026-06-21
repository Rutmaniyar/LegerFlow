<section class="space-y-6">
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

    <div class="grid gap-4 md:grid-cols-4">
        <div class="metric-card"><p class="text-sm font-semibold text-ink-500">Income</p><p class="mt-2 text-2xl font-black"><?= money($income) ?></p></div>
        <div class="metric-card"><p class="text-sm font-semibold text-ink-500">Expenses</p><p class="mt-2 text-2xl font-black"><?= money($expenses) ?></p></div>
        <div class="metric-card"><p class="text-sm font-semibold text-ink-500">Net</p><p class="mt-2 text-2xl font-black"><?= money($profit) ?></p></div>
        <div class="metric-card"><p class="text-sm font-semibold text-ink-500">Outstanding</p><p class="mt-2 text-2xl font-black"><?= money($outstanding) ?></p></div>
    </div>

    <div class="card p-5">
        <h2 class="text-lg font-black text-ink-900">Client ledger</h2>
        <div class="mt-5 table-wrap">
            <table class="data-table">
                <thead><tr><th>Client</th><th class="text-right">Invoiced</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($clientLedger as $row): ?>
                        <tr>
                            <td class="font-bold"><?= e($row['name']) ?></td>
                            <td class="text-right"><?= money($row['invoiced']) ?></td>
                            <td class="text-right"><?= money($row['paid']) ?></td>
                            <td class="text-right font-bold"><?= money($row['outstanding']) ?></td>
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
</section>

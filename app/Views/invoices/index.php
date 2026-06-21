<section class="toolbar mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="eyebrow">Receivables</p>
        <h2 class="mt-1 text-2xl font-black tracking-tight text-ink-950">Invoices</h2>
        <p class="page-kicker">Track draft, sent, partial, paid, and overdue invoices.</p>
    </div>
    <div class="flex gap-2">
        <form method="get" action="/invoices">
            <label class="sr-only" for="status">Filter status</label>
            <select id="status" class="field" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['draft','sent','viewed','partial','paid','overdue','void'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/invoices/create" class="btn-primary"><?= icon('plus') ?> New invoice</a>
    </div>
</section>

<section class="card p-5 hover:shadow-soft">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-black text-ink-900">Invoice register</h2>
            <p class="text-sm text-ink-500">High-signal status view for collections and follow-up.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Invoice</th><th>Client</th><th>Status</th><th>Due</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead>
            <tbody class="divide-y divide-ink-100">
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><a class="font-bold text-brand-700" href="/invoices/<?= e($invoice['id']) ?>"><?= e($invoice['invoice_number']) ?></a></td>
                        <td><?= e($invoice['client_name']) ?></td>
                        <td><span class="badge <?= $invoice['status'] === 'overdue' ? 'bg-red-100 text-red-700' : ($invoice['status'] === 'paid' ? 'bg-brand-100 text-brand-700' : 'bg-ink-100 text-ink-700') ?>"><span class="status-dot mr-1.5"></span><?= e($invoice['status']) ?></span></td>
                        <td><?= e($invoice['due_date']) ?></td>
                        <td class="text-right"><?= money($invoice['amount_paid'], $invoice['currency']) ?></td>
                        <td class="text-right font-bold"><?= money($invoice['balance_due'], $invoice['currency']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$invoices && $status === 'overdue'): ?>
                    <tr><td colspan="6">
                        <?php empty_state([
                            'variant' => 'success',
                            'title' => 'No overdue invoices',
                            'description' => "Every invoice is current. Nothing needs chasing right now.",
                            'secondaryActionLabel' => 'View all invoices',
                            'secondaryActionHref' => '/invoices',
                        ]) ?>
                    </td></tr>
                <?php elseif (!$invoices && $status !== ''): ?>
                    <tr><td colspan="6">
                        <?php empty_state([
                            'variant' => 'filtered_results',
                            'title' => 'No ' . $status . ' invoices match this filter',
                            'description' => 'Try a different status, or clear the filter to see every invoice.',
                            'secondaryActionLabel' => 'Clear filter',
                            'secondaryActionHref' => '/invoices',
                        ]) ?>
                    </td></tr>
                <?php elseif (!$invoices): ?>
                    <tr><td colspan="6">
                        <?php empty_state([
                            'icon' => 'invoices',
                            'title' => 'No invoices yet',
                            'description' => 'This is where every invoice you create will appear, with status, due date, and balance at a glance.',
                            'primaryActionLabel' => 'Create your first invoice',
                            'primaryActionHref' => '/invoices/create',
                        ]) ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="table-footer">
            <span>Showing <?= e(count($invoices)) ?> invoices</span>
            <span class="hidden sm:inline">Filter by status to focus collections work.</span>
        </div>
    </div>
</section>

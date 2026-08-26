<section class="card p-5 hover:shadow-soft" data-motion="fade-up">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="eyebrow">Pipeline</p>
            <h2 class="mt-1 text-lg font-black text-ink-900">Quotes</h2>
            <p class="text-sm text-ink-500">Create estimates and convert accepted quotes into invoices.</p>
        </div>
        <a href="/quotes/create" class="btn-primary"><?= icon('plus') ?> New quote</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Quote', 'key' => 'quote_number', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'asc', 'path' => '/quotes']); ?>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Client', 'key' => 'client', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'asc', 'path' => '/quotes']); ?>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Status', 'key' => 'status', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'asc', 'path' => '/quotes']); ?>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Quote date', 'key' => 'issue_date', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'desc', 'path' => '/quotes']); ?>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Valid until', 'key' => 'valid_until', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'desc', 'path' => '/quotes']); ?>
                <?php \App\Core\View::partial('partials/sortable-heading', ['label' => 'Total', 'key' => 'total', 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'desc', 'path' => '/quotes', 'class' => 'text-right', 'align' => 'right']); ?>
                <th class="text-right">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-ink-100">
                <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><a class="font-bold text-brand-700" href="/quotes/<?= e($quote['id']) ?>"><?= e($quote['quote_number']) ?></a></td>
                        <td><?= e($quote['client_name']) ?></td>
                        <td><span class="badge bg-ink-100 text-ink-700"><?= e($quote['status']) ?></span></td>
                        <td><?= e($quote['issue_date']) ?></td>
                        <td><?= e($quote['valid_until']) ?></td>
                        <td class="text-right font-bold"><?= money($quote['total'], $quote['currency']) ?></td>
                        <td class="text-right">
                            <?php if ($quote['status'] === 'draft'): ?>
                                <div class="flex justify-end gap-2">
                                    <a class="btn-secondary h-8 px-2.5 text-xs" href="/quotes/<?= e($quote['id']) ?>/edit"><?= icon('edit', 'h-3.5 w-3.5') ?> Edit</a>
                                    <form method="post" action="/quotes/<?= e($quote['id']) ?>/delete" onsubmit="return confirm('Delete this draft quote? This cannot be undone.')">
                                        <?= csrf_field() ?>
                                        <button class="btn-secondary h-8 px-2.5 text-xs text-red-700"><?= icon('trash', 'h-3.5 w-3.5') ?> Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$quotes): ?>
                    <tr><td colspan="7">
                        <?php empty_state([
                            'icon' => 'quotes',
                            'title' => 'No quotes yet',
                            'description' => 'This is where every estimate you build will appear. Send one, then convert it to an invoice once accepted.',
                            'primaryActionLabel' => 'Create your first quote',
                            'primaryActionHref' => '/quotes/create',
                        ]) ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination, 'path' => '/quotes', 'query' => ['sort' => $sort, 'direction' => $direction]]); ?>
    </div>
</section>

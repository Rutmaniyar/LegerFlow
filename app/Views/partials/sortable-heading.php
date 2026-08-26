<?php
$active = $sort === $key;
$nextDirection = $active ? ($direction === 'asc' ? 'desc' : 'asc') : $defaultDirection;
$params = array_merge($query ?? [], ['sort' => $key, 'direction' => $nextDirection]);
unset($params['page']);
$params = array_filter($params, static fn (mixed $value): bool => $value !== '' && $value !== null);
$url = $path . '?' . http_build_query($params);
$ariaSort = $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
$nextLabel = $nextDirection === 'asc' ? 'ascending' : 'descending';
?>
<th scope="col" aria-sort="<?= e($ariaSort) ?>" class="<?= e($class ?? '') ?>">
    <a class="inline-flex items-center gap-1.5 font-bold text-ink-600 hover:text-brand-700 focus-visible:rounded-sm<?= ($align ?? '') === 'right' ? ' w-full justify-end' : '' ?>" href="<?= e($url) ?>">
        <span><?= e($label) ?></span>
        <span class="<?= $active ? 'text-brand-700' : 'text-ink-300' ?>"><?= icon(($active ? $direction : $defaultDirection) === 'asc' ? 'arrow-up' : 'arrow-down', 'h-3.5 w-3.5') ?></span>
        <span class="sr-only"><?= $active ? 'Currently sorted ' . e($ariaSort) . '. ' : '' ?>Activate to sort <?= e($nextLabel) ?>.</span>
    </a>
</th>

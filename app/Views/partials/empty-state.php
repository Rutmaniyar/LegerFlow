<?php
/**
 * Reusable empty-state block.
 *
 * Variants: onboarding | no_content | no_search_results | filtered_results | success | error_recovery
 *
 * Props (all optional unless noted):
 *   variant              string   default 'no_content'
 *   compact              bool     tighter, borderless rendering for asides/inline lists
 *   icon                 string   icon() key; defaults inferred from variant
 *   title                string   required for most variants
 *   description          string
 *   searchTerm           string   when set, renders 'No results found for "..."' as the title
 *   primaryActionLabel / primaryActionHref / primaryActionIcon
 *   secondaryActionLabel / secondaryActionHref
 *   extraActions         array<{label, href}>  rendered as lightweight text links
 *   checklist            array<{label, done, href}>  renders an onboarding progress list
 */
$variant = $variant ?? 'no_content';
$compact = $compact ?? false;
$title = $title ?? '';
$description = $description ?? '';
$searchTerm = $searchTerm ?? null;
$primaryActionLabel = $primaryActionLabel ?? null;
$primaryActionHref = $primaryActionHref ?? null;
$primaryActionIcon = $primaryActionIcon ?? null;
$secondaryActionLabel = $secondaryActionLabel ?? null;
$secondaryActionHref = $secondaryActionHref ?? null;
$extraActions = $extraActions ?? [];
$checklist = $checklist ?? null;

$icon = $icon ?? match ($variant) {
    'no_search_results', 'filtered_results' => 'search',
    'success' => 'check',
    'error_recovery' => 'warning',
    'onboarding' => 'spark',
    default => 'invoices',
};

$variantClass = match ($variant) {
    'success' => 'empty-state-success',
    'onboarding' => 'empty-state-onboarding',
    'error_recovery' => 'empty-state-error',
    default => '',
};

$iconToneClass = match ($variant) {
    'success' => 'bg-brand-100 text-brand-700',
    'error_recovery' => 'bg-red-100 text-red-700',
    'onboarding' => 'bg-brand-100 text-brand-700',
    default => 'bg-ink-100 text-ink-500',
};
?>
<div class="empty-state <?= e($variantClass) ?> <?= $compact ? 'empty-state-compact' : '' ?>" role="status">
    <?php if ($variant === 'success' && !$compact): ?>
        <span class="confetti-dot bg-brand-400" style="left:32%; animation-delay:0s"></span>
        <span class="confetti-dot bg-accent-400" style="left:50%; animation-delay:.15s"></span>
        <span class="confetti-dot bg-amber-400" style="left:66%; animation-delay:.3s"></span>
    <?php endif; ?>

    <div class="empty-state-icon <?= e($iconToneClass) ?>">
        <?= icon($icon, 'h-5 w-5') ?>
    </div>

    <p class="empty-state-title">
        <?php if ($searchTerm !== null && $searchTerm !== ''): ?>
            No results found for &ldquo;<?= e($searchTerm) ?>&rdquo;
        <?php else: ?>
            <?= e($title) ?>
        <?php endif; ?>
    </p>

    <?php if ($description !== ''): ?>
        <p class="empty-state-description"><?= e($description) ?></p>
    <?php endif; ?>

    <?php if ($primaryActionLabel || $secondaryActionLabel || $extraActions !== []): ?>
        <div class="empty-state-actions">
            <?php if ($primaryActionLabel && $primaryActionHref): ?>
                <a href="<?= e($primaryActionHref) ?>" class="btn-primary">
                    <?php if ($primaryActionIcon): ?><?= icon($primaryActionIcon) ?><?php endif; ?>
                    <?= e($primaryActionLabel) ?>
                </a>
            <?php endif; ?>
            <?php if ($secondaryActionLabel && $secondaryActionHref): ?>
                <a href="<?= e($secondaryActionHref) ?>" class="btn-secondary"><?= e($secondaryActionLabel) ?></a>
            <?php endif; ?>
            <?php foreach ($extraActions as $action): ?>
                <a href="<?= e($action['href']) ?>" class="empty-state-link"><?= e($action['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($checklist): ?>
        <?php
        $total = count($checklist);
        $done = count(array_filter($checklist, static fn ($item) => $item['done']));
        $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
        ?>
        <div class="empty-state-checklist">
            <div class="mb-2 flex items-center justify-between text-xs font-black uppercase tracking-wide text-ink-500">
                <span>Quick start</span>
                <span><?= e($done) ?>/<?= e($total) ?> completed</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-ink-100">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-300 motion-reduce:transition-none" style="width: <?= e($percent) ?>%"></div>
            </div>
            <ul class="mt-4 space-y-2 text-left">
                <?php foreach ($checklist as $item): ?>
                    <li>
                        <a href="<?= e($item['href'] ?? '#') ?>" class="flex items-center gap-3 rounded-md border border-ink-100 bg-white px-3 py-2.5 text-sm font-semibold text-ink-800 transition duration-150 hover:border-brand-200 hover:bg-brand-50/60 motion-reduce:transition-none">
                            <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full border <?= $item['done'] ? 'border-brand-500 bg-brand-500 text-white' : 'border-ink-300 text-transparent' ?>">
                                <?= icon('check', 'h-3 w-3') ?>
                            </span>
                            <span class="<?= $item['done'] ? 'text-ink-400 line-through' : '' ?>"><?= e($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($dash['actions'])): ?>
<div class="loka-card mb-6 border border-warning/30 bg-warning/5">
    <div class="px-5 py-4 flex flex-wrap items-center gap-2">
        <span class="font-semibold text-sm text-base-content mr-2">
            <i class="bi bi-lightning-charge-fill text-warning mr-1"></i>Action Needed
        </span>
        <?php foreach ($dash['actions'] as $action): ?>
        <a href="<?= e($action['href']) ?>" class="loka-filter-tab <?= ($action['tone'] ?? '') === 'warning' ? 'is-active' : '' ?>">
            <?= e($action['label']) ?>
            <?php if (isset($action['count']) && $action['count'] !== null): ?>
                <span>(<?= (int) $action['count'] ?>)</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

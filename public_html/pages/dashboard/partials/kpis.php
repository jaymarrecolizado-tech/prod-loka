<?php if (!empty($dash['kpis'])): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
    <?php foreach ($dash['kpis'] as $kpi):
        $tone = $kpi['tone'] ?? 'primary';
        $href = $kpi['href'] ?? '#';
        $icon = $kpi['icon'] ?? 'bi-circle';
    ?>
    <a href="<?= e($href) ?>" class="loka-stat-card no-underline hover:ring-2 hover:ring-primary/40 transition block">
        <div class="flex justify-between items-start">
            <div>
                <div class="loka-stat-value text-<?= e($tone) ?>"><?= (int) $kpi['value'] ?></div>
                <div class="loka-stat-label"><?= e($kpi['label']) ?></div>
            </div>
            <div class="loka-stat-icon bg-<?= e($tone) ?>/10 text-<?= e($tone) ?>">
                <i class="bi <?= e($icon) ?>"></i>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

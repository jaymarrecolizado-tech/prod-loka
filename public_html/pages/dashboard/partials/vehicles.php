<?php if (!empty($dash['vehicleStats'])): ?>
<div class="loka-card mb-6">
    <div class="border-b border-base-200 px-5 py-4">
        <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-pie-chart mr-2"></i>Vehicle Status Overview</h5>
    </div>
    <div class="px-5 py-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <?php foreach ($dash['vehicleStats'] as $stat): ?>
            <a href="<?= APP_URL ?>/?page=vehicles&status=<?= e(urlencode($stat->status)) ?>" class="py-3 no-underline hover:bg-base-200 rounded-xl transition">
                <div class="text-3xl font-bold mb-1 text-base-content"><?= (int) $stat->count ?></div>
                <div><?= vehicleStatusBadge($stat->status) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

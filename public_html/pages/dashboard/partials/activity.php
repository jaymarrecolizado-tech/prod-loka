<?php
$activity = $dash['activity'] ?? ['title' => 'Activity', 'total' => 0, 'rows' => []];
?>
<div class="loka-card h-full">
    <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
        <h5 class="font-semibold text-base-content mb-0">
            <i class="bi bi-clock-history mr-2"></i><?= e($activity['title']) ?>
        </h5>
        <span class="text-xs text-base-content/50"><?= (int) $activity['total'] ?> total</span>
    </div>
    <div>
        <?php if (empty($activity['rows'])): ?>
        <div class="loka-empty py-4">
            <i class="bi bi-inbox"></i>
            <p class="mb-0">No requests found</p>
        </div>
        <?php else: ?>
        <ul class="activity-feed px-3">
            <?php foreach ($activity['rows'] as $item): ?>
            <li>
                <div class="flex justify-between items-start gap-2">
                    <div class="min-w-0">
                        <div class="font-medium truncate">
                            <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= (int) $item->id ?>" class="no-underline text-primary hover:underline">
                                <?= e(truncate($item->purpose, 40)) ?>
                            </a>
                        </div>
                        <span class="text-xs text-base-content/50">
                            <?= e($item->requester_name) ?> • <?= e($item->department_name) ?>
                        </span>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <?= requestStatusBadge($item->status) ?>
                        <div class="activity-time"><?= formatDateTime($item->updated_at) ?></div>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php
$queue = $dash['queue'] ?? ['title' => 'Queue', 'href' => '#', 'rows' => []];
$rows = $queue['rows'] ?? [];
$isGas = str_contains((string) ($queue['href'] ?? ''), 'gas-vouchers');
?>
<div class="loka-card h-full">
    <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
        <h5 class="font-semibold text-base-content mb-0">
            <i class="bi bi-list-check mr-2"></i><?= e($queue['title']) ?>
        </h5>
        <a href="<?= e($queue['href']) ?>" class="loka-btn-secondary text-xs">View All</a>
    </div>
    <div>
        <?php if (empty($rows)): ?>
        <div class="loka-empty py-4">
            <i class="bi bi-inbox"></i>
            <p class="mb-0">Nothing waiting right now</p>
        </div>
        <?php else: ?>
        <ul class="activity-feed px-3">
            <?php foreach ($rows as $row):
                $id = (int) ($row->id ?? 0);
                $title = $row->title ?? ($row->purpose ?? 'Item');
                $meta = $row->meta ?? '';
                $status = (string) ($row->status ?? '');
                $updated = $row->updated_at ?? ($row->created_at ?? null);
                $link = $isGas
                    ? APP_URL . '/?page=gas-vouchers&action=view&id=' . $id
                    : APP_URL . '/?page=requests&action=view&id=' . $id;
            ?>
            <li>
                <div class="flex justify-between items-start gap-2">
                    <div class="min-w-0">
                        <div class="font-medium truncate">
                            <a href="<?= e($link) ?>" class="no-underline text-primary hover:underline"><?= e(truncate($title, 42)) ?></a>
                        </div>
                        <?php if ($meta !== ''): ?>
                        <span class="text-xs text-base-content/50"><?= e($meta) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <?php if ($status !== ''): ?>
                            <?php if ($isGas): ?>
                                <span class="loka-badge loka-status-warning"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
                            <?php else: ?>
                                <?= requestStatusBadge($status) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($updated): ?>
                        <div class="activity-time"><?= formatDateTime($updated) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

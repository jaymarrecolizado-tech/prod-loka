<?php
/**
 * LOKA - Refresh Notification List (AJAX)
 */

requireAuth();

$view = get('view', 'inbox');
$isArchive = $view === 'archive';
$isAll = $view === 'all';

$where = "user_id = ? AND deleted_at IS NULL";
$params = [userId()];

if ($isArchive) {
    $where .= " AND is_archived = 1";
} elseif (!$isAll) {
    $where .= " AND is_archived = 0 AND is_read = 0";
}

$notifications = db()->fetchAll(
    "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT 100",
    $params
);

$unread = 0;

// Build HTML
ob_start();

if (empty($notifications)): ?>
    <div class="py-12">
        <div class="text-center text-base-content/60">
            <i class="bi bi-bell-slash text-4xl mb-3 block"></i>
            <h5 class="text-base-content/60">No notifications in <?= $view ?></h5>
            <p class="text-sm text-base-content/40">You're all caught up!</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($notifications as $notif): ?>
        <?php if (!$notif->is_read) $unread++; ?>
        <div class="flex items-start justify-between px-4 py-3 hover:bg-base-200/50 transition-colors <?= $notif->is_read ? '' : 'bg-primary/5 border-l-4 border-primary' ?>">
            <div class="flex items-start flex-grow-1">
                <div class="p-2 rounded-full <?= $notif->is_read ? 'bg-base-200' : 'bg-primary/10' ?>">
                    <i class="bi bi-bell <?= $notif->is_read ? 'text-base-content/40' : 'text-primary' ?>"></i>
                </div>
                <div class="ml-3 flex-grow-1">
                    <div class="flex items-center justify-between mb-1">
                        <h6 class="text-sm <?= $notif->is_read ? '' : 'font-semibold' ?>">
                            <a href="<?= APP_URL ?>/?page=notifications&action=read&id=<?= $notif->id ?>"
                               class="hover:text-primary transition-colors no-underline text-base-content">
                                <?= e($notif->title) ?>
                            </a>
                        </h6>
                        <span class="text-xs text-base-content/40 ml-2 shrink-0"><?= formatDateTime($notif->created_at) ?></span>
                    </div>
                    <p class="text-sm text-base-content/60 mb-1"><?= e($notif->message) ?></p>
                    <div class="flex gap-3">
                        <?php if ($notif->link): ?>
                            <a href="<?= APP_URL ?>/?page=notifications&action=read&id=<?= $notif->id ?>"
                               class="text-xs text-primary hover:underline">
                                <i class="bi bi-box-arrow-up-right mr-1"></i>View Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Per-notification dropdown (DaisyUI) -->
            <div class="ml-3 dropdown dropdown-end">
                <div tabindex="0" role="button" class="loka-btn-ghost loka-btn-sm">
                    <i class="bi bi-three-dots-vertical"></i>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-44 p-2 shadow-lg">
                    <li>
                        <a href="<?= APP_URL ?>/?page=notifications&action=archive&id=<?= $notif->id ?>&view=<?= $view ?>">
                            <i class="bi <?= $isArchive ? 'bi-inbox' : 'bi-archive' ?> mr-2"></i>
                            <?= $isArchive ? 'Move to Inbox' : 'Archive' ?>
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="<?= APP_URL ?>/?page=notifications&action=delete&view=<?= $view ?>" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $notif->id ?>">
                            <button type="submit" class="text-error" onclick="return confirm('Delete this notification?')">
                                <i class="bi bi-trash mr-2"></i>Delete
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif;

$html = ob_get_clean();

jsonResponse(true, ['html' => $html, 'unread' => $unread]);

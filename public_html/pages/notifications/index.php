<?php
$pageTitle = 'Notifications';

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

require_once INCLUDES_PATH . '/header.php';
?>

<div class="loka-page">
    <div class="max-w-3xl mx-auto">
        <div class="loka-card">
            <div class="p-4 border-b border-base-200">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        <i class="bi bi-bell"></i>Notifications
                    </h2>
                    <div class="join">
                        <a href="<?= APP_URL ?>/?page=notifications&view=inbox"
                           class="loka-btn-primary loka-btn-sm join-item <?= $view === 'inbox' ? '' : 'loka-btn-outline-primary' ?>">
                            <i class="bi bi-inbox mr-1"></i>Inbox
                        </a>
                        <a href="<?= APP_URL ?>/?page=notifications&view=all"
                           class="loka-btn-primary loka-btn-sm join-item <?= $view === 'all' ? '' : 'loka-btn-outline-primary' ?>">
                            <i class="bi bi-list-check mr-1"></i>All
                        </a>
                        <a href="<?= APP_URL ?>/?page=notifications&view=archive"
                           class="loka-btn-primary loka-btn-sm join-item <?= $view === 'archive' ? '' : 'loka-btn-outline-primary' ?>">
                            <i class="bi bi-archive mr-1"></i>Archive
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <?php if (empty($notifications)): ?>
                <div class="py-12">
                    <div class="text-center text-base-content/60">
                        <i class="bi bi-bell-slash text-4xl mb-3 block"></i>
                        <h5 class="text-base-content/60">No notifications in <?= ucfirst($view) ?></h5>
                        <p class="text-sm text-base-content/40">You're all caught up!</p>
                    </div>
                </div>
                <?php else: ?>
                <div id="notificationsList">
                    <?php foreach ($notifications as $notif): ?>
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
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($notifications)): ?>
            <div class="p-4 border-t border-base-200 flex items-center justify-between">
                <small class="text-base-content/40"><?= count($notifications) ?> notification(s) displayed</small>
                <!-- Bulk Actions (DaisyUI dropdown) -->
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-sm btn-outline">
                        Bulk Actions <i class="bi bi-chevron-down ml-1 text-xs"></i>
                    </div>
                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow-lg">
                        <?php if ($view === 'inbox'): ?>
                        <li>
                            <a href="<?= APP_URL ?>/?page=notifications&action=read-all&view=<?= $view ?>">
                                <i class="bi bi-check2-all mr-2"></i>Mark All as Read
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?= APP_URL ?>/?page=notifications&action=archive-all&view=<?= $view ?>">
                                <i class="bi bi-archive mr-2"></i>Archive All
                            </a>
                        </li>
                        <li>
                            <a href="<?= APP_URL ?>/?page=notifications&action=delete-all&view=<?= $view ?>"
                               class="text-error"
                               onclick="return confirm('Delete all notifications in <?= $view ?>?')">
                                <i class="bi bi-trash mr-2"></i>Clear All
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$pageScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    let refreshInterval;
    
    function refreshNotifications() {
        fetch('<?= APP_URL ?>/?page=notifications&action=refresh-ajax&view=<?= $view ?>', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationsList = document.getElementById('notificationsList');
                if (notificationsList) {
                    notificationsList.innerHTML = data.html;
                }
                
                const badge = document.querySelector('.loka-navbar-notification-badge');
                if (badge && data.unread !== undefined) {
                    if (data.unread > 0) {
                        badge.textContent = data.unread > 9 ? '9+' : data.unread;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
            }
        })
        .catch(error => console.error('Refresh error:', error));
    }
    
    refreshInterval = setInterval(refreshNotifications, 30000);
});
</script>
HTML;

require_once INCLUDES_PATH . '/footer.php'; 
?>

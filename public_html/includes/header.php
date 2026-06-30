<!DOCTYPE html>
<html lang="en" data-theme="loka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="LOKA Fleet Management System">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= ASSETS_PATH ?>/css/style.css" rel="stylesheet">

    <!-- Modern UI (Tailwind + DaisyUI) -->
    <?= viteEntryCssTags('app') ?>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="loka-navbar">
        <!-- Sidebar Toggle -->
        <button class="loka-navbar-toggle" type="button" id="sidebarToggle" aria-label="Toggle navigation menu">
            <i class="bi bi-list text-xl"></i>
        </button>

        <!-- Brand -->
        <a class="loka-navbar-brand" href="<?= APP_URL ?>">
            <i class="bi bi-truck"></i><?= APP_NAME ?>
        </a>

        <!-- Right Side -->
        <div class="loka-navbar-actions">
            <!-- Notifications (DaisyUI dropdown) -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="loka-navbar-notification" id="notificationDropdown">
                    <i class="bi bi-bell text-lg"></i>
                    <?php $unreadCount = unreadNotificationCount(); ?>
                    <?php if ($unreadCount > 0): ?>
                    <span class="loka-navbar-notification-badge">
                        <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                    </span>
                    <?php endif; ?>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-80 p-2 shadow-lg">
                    <li class="menu-title">Notifications</li>
                    <?php
                    $notifications = db()->fetchAll(
                        "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND is_archived = 0 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5",
                        [userId()]
                    );
                    if (empty($notifications)):
                    ?>
                    <li class="text-base-content/50 text-sm py-2">No notifications</li>
                    <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <li>
                        <a href="<?= APP_URL ?>/?page=notifications&action=read&id=<?= $notif->id ?>" class="flex flex-col items-start py-2 <?= $notif->is_read ? '' : 'font-bold' ?>">
                            <small class="text-base-content/50"><?= formatDateTime($notif->created_at) ?></small>
                            <span><?= e($notif->title) ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <li class="border-t border-base-200 mt-1 pt-1">
                        <a href="<?= APP_URL ?>/?page=notifications" class="text-center text-sm">View All</a>
                    </li>
                </ul>
            </div>

            <!-- User Dropdown (DaisyUI dropdown) -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="loka-navbar-notification flex items-center gap-2">
                    <div class="loka-avatar bg-white/20">
                        <?= strtoupper(substr(currentUser()->name ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="hidden md:inline text-sm text-white"><?= e(currentUser()->name ?? 'User') ?></span>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-56 p-2 shadow-lg">
                    <li class="menu-title">
                        <span class="text-sm"><?= e(currentUser()->email ?? '') ?></span>
                    </li>
                    <li class="text-xs px-4 py-1"><?= roleBadge(userRole()) ?></li>
                    <li class="border-t border-base-200 mt-1 pt-1">
                        <a href="<?= APP_URL ?>/?page=profile"><i class="bi bi-person me-2"></i>Profile</a>
                    </li>
                    <li>
                        <a href="<?= APP_URL ?>/?page=logout" class="text-error"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Wrapper -->
    <div class="wrapper">
        <?php require_once INCLUDES_PATH . '/sidebar.php'; ?>

        <!-- Toast Container -->
        <div id="toast-container" class="fixed top-0 right-0 z-[1100] flex flex-col gap-2 p-3"></div>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <!-- Flash Messages -->
            <?php if ($flash = getFlash()): ?>
            <?php
            $flashTypeMap = ['success' => 'loka-alert-success', 'danger' => 'loka-alert-danger', 'warning' => 'loka-alert-warning', 'info' => 'loka-alert-info'];
            $lokaFlashClass = $flashTypeMap[$flash['type']] ?? 'loka-alert-info';
            ?>
            <div class="loka-alert <?= $lokaFlashClass ?>  mx-3 mt-3" role="alert" data-auto-dismiss="5000">
                <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                <span class="flex-1"><?= e($flash['message']) ?></span>
                <button type="button" class="btn-close ms-auto" onclick="this.closest('[role=alert]').remove()" aria-label="Close">×</button>
            </div>
            <?php endif; ?>

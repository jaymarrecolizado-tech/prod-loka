<!DOCTYPE html>
<html lang="en" data-theme="<?= $_COOKIE['loka_theme'] ?? 'loka' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="LOKA Fleet Management System">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>

    <!-- Apply saved theme immediately to prevent flash -->
    <script>
    (function() {
        var t = localStorage.getItem('loka_theme');
        if (t) document.documentElement.setAttribute('data-theme', t);
    })();
    </script>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <!-- Custom CSS (cache-busted) -->
    <link href="<?= ASSETS_PATH ?>/css/style.css?v=<?= is_file(BASE_PATH . '/assets/css/style.css') ? filemtime(BASE_PATH . '/assets/css/style.css') : time() ?>" rel="stylesheet">

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
            <?php if (isRealAllFather()): ?>
            <!-- View as role (All Father) -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="loka-btn-secondary loka-btn-sm gap-1" title="View as role">
                    <i class="bi bi-person-badge"></i>
                    <span class="hidden sm:inline"><?= isViewingAs() ? e(viewAsBannerLabel()) : 'View as' ?></span>
                    <i class="bi bi-chevron-down text-xs"></i>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-56 p-2 shadow-lg">
                    <li class="menu-title"><span>View as role</span></li>
                    <?php
                    $returnTo = $_SERVER['REQUEST_URI'] ?? '/?page=dashboard';
                    $returnTo = preg_replace('/^' . preg_quote(parse_url(APP_URL, PHP_URL_PATH) ?: '', '/') . '/', '', $returnTo) ?: $returnTo;
                    if ($returnTo === '' || $returnTo[0] !== '/') {
                        $returnTo = '/?page=dashboard';
                    }
                    ?>
                    <li>
                        <a href="<?= APP_URL ?>/?page=view-as&role=none&redirect=<?= urlencode($returnTo) ?>">
                            All Father (default)
                        </a>
                    </li>
                    <?php foreach (viewAsRoleOptions() as $roleKey => $roleLabel): ?>
                    <li>
                        <a href="<?= APP_URL ?>/?page=view-as&role=<?= e($roleKey) ?>&redirect=<?= urlencode($returnTo) ?>"
                           class="<?= getViewAsRole() === $roleKey ? 'active' : '' ?>">
                            <?= e($roleLabel) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Theme Toggle -->
            <button class="loka-navbar-notification" type="button" id="themeToggle" aria-label="Toggle theme" title="Toggle dark/light mode">
                <i class="bi bi-moon-stars-fill text-lg" id="themeIconDark"></i>
                <i class="bi bi-sun-fill text-lg hidden" id="themeIconLight"></i>
            </button>

            <!-- Notifications (DaisyUI dropdown) -->
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="loka-navbar-notification" id="notificationDropdown">
                    <i class="bi bi-bell text-lg"></i>
                    <?php $unreadCount = unreadNotificationCount(); ?>
                    <?php if ($unreadCount > 0): ?>
                    <span class="loka-navbar-notification-badge" title="<?= (int) $unreadCount ?> unread">
                        <?= $unreadCount > 99 ? '99+' : (int) $unreadCount ?>
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
                <div tabindex="0" role="button" class="loka-navbar-user" aria-label="User menu">
                    <div class="loka-avatar loka-navbar-user-avatar">
                        <?= strtoupper(substr(currentUser()->name ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="loka-navbar-user-name"><?= e(currentUser()->name ?? 'User') ?></span>
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-56 p-2 shadow-lg">
                    <li class="menu-title">
                        <span class="text-sm"><?= e(currentUser()->email ?? '') ?></span>
                    </li>
                    <li class="text-xs px-4 py-1">
                        <?php if (isViewingAs()): ?>
                            <span class="loka-badge bg-warning/20 text-warning"><?= e(viewAsBannerLabel()) ?></span>
                            <span class="text-warning block mt-1">View-as active</span>
                        <?php else: ?>
                            <?= roleBadge(realUserRole() ?? '') ?>
                        <?php endif; ?>
                    </li>
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
            <?php if (isViewingAs()): ?>
            <div class="loka-view-as-banner" role="status">
                <div class="loka-view-as-banner-inner">
                    <span>
                        <i class="bi bi-eye me-1"></i>
                        Viewing as <strong><?= e(viewAsBannerLabel()) ?></strong>
                        — actions run with that role’s permissions
                    </span>
                    <?php
                    $exitTo = $_SERVER['REQUEST_URI'] ?? '/?page=dashboard';
                    $exitTo = preg_replace('/^' . preg_quote(parse_url(APP_URL, PHP_URL_PATH) ?: '', '/') . '/', '', $exitTo) ?: $exitTo;
                    if ($exitTo === '' || $exitTo[0] !== '/') {
                        $exitTo = '/?page=dashboard';
                    }
                    ?>
                    <a class="loka-btn-secondary loka-btn-sm" href="<?= APP_URL ?>/?page=view-as&role=none&redirect=<?= urlencode($exitTo) ?>">
                        Exit View-as
                    </a>
                </div>
            </div>
            <?php endif; ?>

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

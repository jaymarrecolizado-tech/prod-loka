<?php
/**
 * LOKA - Role-aware Dashboard
 */

$pageTitle = 'Dashboard';

// Guards always use the Guard Ops dashboard
if (isGuard()) {
    redirect('/?page=guard');
}

$dash = dashboardStatsForUser();

require_once INCLUDES_PATH . '/header.php';
$partials = PAGES_PATH . '/dashboard/partials';
?>

<div class="loka-page">
    <div class="loka-page-header mb-6">
        <div>
            <h4 class="mb-1 text-xl font-bold text-base-content">Dashboard</h4>
            <p class="text-sm text-base-content/60 mb-0">
                Welcome back, <?= e(currentUser()->name) ?>!
                <span class="text-base-content/40">• <?= e(date('l, F j, Y')) ?></span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if (!empty($dash['isDriver'])): ?>
            <a href="<?= APP_URL ?>/?page=my-trips" class="loka-btn-secondary">
                <i class="bi bi-truck mr-1"></i>My Trips
            </a>
            <?php endif; ?>
            <?php if (isGuard()): ?>
            <a href="<?= APP_URL ?>/?page=guard" class="loka-btn-secondary">
                <i class="bi bi-shield-check mr-1"></i>Guard Ops
            </a>
            <?php endif; ?>
            <?php if (!empty($dash['showNewRequest'])): ?>
            <a href="<?= APP_URL ?>/?page=requests&action=create" class="loka-btn-primary">
                <i class="bi bi-plus-lg mr-1"></i>New Request
            </a>
            <?php endif; ?>
            <?php if (hasRole(ROLE_REQUESTER) || isAdmin()): ?>
            <a href="<?= APP_URL ?>/?page=gas-vouchers&action=create" class="loka-btn-secondary">
                <i class="bi bi-fuel-pump mr-1"></i>New Gas Voucher
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php require $partials . '/action_needed.php'; ?>
    <?php require $partials . '/kpis.php'; ?>
    <?php require $partials . '/charts.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="col-span-1">
            <?php require $partials . '/queue.php'; ?>
        </div>
        <div class="col-span-1">
            <?php
            if (isChiefAdminFinance() && !isAdmin() && !isMotorpool()) {
                require $partials . '/activity.php';
            } else {
                require $partials . '/upcoming.php';
            }
            ?>
        </div>
    </div>

    <?php if (!isChiefAdminFinance() || isAdmin() || isMotorpool() || isApprover()): ?>
    <div class="grid grid-cols-1 gap-4 mb-6">
        <?php require $partials . '/activity.php'; ?>
    </div>
    <?php endif; ?>

    <?php require $partials . '/vehicles.php'; ?>
    <?php require $partials . '/audit.php'; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

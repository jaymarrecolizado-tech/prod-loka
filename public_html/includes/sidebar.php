<!-- Sidebar -->
<nav class="loka-sidebar" id="sidebar" aria-label="Main navigation">
    <div class="loka-sidebar-content">
        <ul class="flex flex-col gap-0.5 px-3">
            <!-- Dashboard -->
            <li>
                <a class="loka-nav-link <?= activeMenu('dashboard') ?>" href="<?= APP_URL ?>/?page=dashboard">
                    <i class="bi bi-speedometer2 w-5 text-center text-base-content/60"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Requests -->
            <li>
                <a class="loka-nav-link <?= activeMenu('requests') ?>" href="<?= APP_URL ?>/?page=requests">
                    <i class="bi bi-file-earmark-text w-5 text-center text-base-content/60"></i>
                    <span>Requests</span>
                </a>
            </li>

            <!-- Completed Trips -->
            <li>
                <a class="loka-nav-link <?= activeMenu('completed-trips') ?>" href="<?= APP_URL ?>/?page=completed-trips">
                    <i class="bi bi-check-all w-5 text-center text-base-content/60"></i>
                    <span>Completed Trips</span>
                </a>
            </li>

            <!-- Gas Vouchers -->
            <?php if (hasRole(ROLE_REQUESTER) || isApprover() || isMotorpool() || isAdmin() || isChiefAdminFinance()): ?>
            <li>
                <a class="loka-nav-link <?= activeMenu('gas-vouchers') ?>" href="<?= APP_URL ?>/?page=gas-vouchers">
                    <i class="bi bi-fuel-pump w-5 text-center text-base-content/60"></i>
                    <span>Gas Vouchers</span>
                    <?php
                    $pendingGv = db()->fetchColumn(
                        "SELECT COUNT(*) FROM gas_vouchers WHERE status IN ('pending_review','pending_approval') AND deleted_at IS NULL"
                    );
                    if ($pendingGv > 0):
                    ?>
                    <span class="loka-badge bg-warning/20 text-warning ms-auto"><?= $pendingGv ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Schedule Calendar (All Users) -->
            <li>
                <a class="loka-nav-link <?= activeMenu('schedule') ?>" href="<?= APP_URL ?>/?page=schedule&action=calendar">
                    <i class="bi bi-calendar3 w-5 text-center text-base-content/60"></i>
                    <span>Availability</span>
                </a>
            </li>

            <?php if (isApprover()): ?>
            <!-- My Trip Tickets (Approvers Only) -->
            <li>
                <a class="loka-nav-link <?= activeMenu('my-trip-tickets') ?>" href="<?= APP_URL ?>/?page=my-trip-tickets">
                    <i class="bi bi-file-earmark-text w-5 text-center text-base-content/60"></i>
                    <span>My Trip Tickets</span>
                    <?php
                    $pendingTicketsCount = db()->fetchColumn(
                        "SELECT COUNT(*) FROM trip_tickets WHERE status = 'submitted' AND deleted_at IS NULL"
                    );
                    if ($pendingTicketsCount > 0):
                    ?>
                    <span class="loka-badge bg-warning/20 text-warning ms-auto"><?= $pendingTicketsCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isApprover()): ?>
            <!-- Approvals -->
            <li>
                <a class="loka-nav-link <?= activeMenu('approvals') ?>" href="<?= APP_URL ?>/?page=approvals">
                    <i class="bi bi-check-circle w-5 text-center text-base-content/60"></i>
                    <span>Approvals</span>
                    <?php
                    $pendingCount = 0;
                    if (isMotorpool()) {
                        $pendingCount = db()->count('requests', "status = 'pending_motorpool'");
                    } else {
                        $pendingCount = db()->count('requests', "status = 'pending' AND department_id = ?", [currentUser()->department_id]);
                    }
                    if ($pendingCount > 0):
                    ?>
                    <span class="loka-badge bg-warning/20 text-warning ms-auto"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <li class="loka-section-label mt-4">Fleet Management</li>

            <?php if (isApprover()): ?>
            <!-- Vehicles -->
            <li>
                <a class="loka-nav-link <?= activeMenu('vehicles') ?>" href="<?= APP_URL ?>/?page=vehicles">
                    <i class="bi bi-car-front w-5 text-center text-base-content/60"></i>
                    <span>Vehicles</span>
                </a>
            </li>

            <!-- Drivers -->
            <li>
                <a class="loka-nav-link <?= activeMenu('drivers') ?>" href="<?= APP_URL ?>/?page=drivers">
                    <i class="bi bi-person-badge w-5 text-center text-base-content/60"></i>
                    <span>Drivers</span>
                </a>
            </li>

            <?php if (isAdmin() || isMotorpool() || isApprover()): ?>
            <!-- Vehicle Types -->
            <li>
                <a class="loka-nav-link <?= activeMenu('vehicle_types') ?>" href="<?= APP_URL ?>/?page=vehicle_types">
                    <i class="bi bi-car-front w-5 text-center text-base-content/60"></i>
                    <span>Vehicle Types</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Maintenance -->
            <li>
                <a class="loka-nav-link <?= activeMenu('maintenance') ?>" href="<?= APP_URL ?>/?page=maintenance">
                    <i class="bi bi-wrench w-5 text-center text-base-content/60"></i>
                    <span>Maintenance</span>
                    <?php
                    $pendingMaintenance = db()->count('maintenance_requests', "status IN (?, ?) AND deleted_at IS NULL", [MAINTENANCE_STATUS_PENDING, MAINTENANCE_STATUS_SCHEDULED]);
                    if ($pendingMaintenance > 0):
                    ?>
                    <span class="loka-badge bg-warning/20 text-warning ms-auto"><?= $pendingMaintenance ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <!-- Maintenance Schedule -->
            <li>
                <a class="loka-nav-link <?= activeMenu('maintenance', 'schedule') ?>" href="<?= APP_URL ?>/?page=maintenance&action=schedule">
                    <i class="bi bi-calendar-check w-5 text-center text-base-content/60"></i>
                    <span>Schedule</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isApprover()): ?>
            <li class="loka-section-label mt-4">Reports</li>

            <!-- Reports -->
            <li>
                <a class="loka-nav-link <?= activeMenu('reports') ?>" href="<?= APP_URL ?>/?page=reports">
                    <i class="bi bi-bar-chart w-5 text-center text-base-content/60"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isMotorpool()): ?>
            <li class="loka-section-label mt-4">Administration</li>

            <!-- Users -->
            <li>
                <a class="loka-nav-link <?= activeMenu('users') ?>" href="<?= APP_URL ?>/?page=users">
                    <i class="bi bi-people w-5 text-center text-base-content/60"></i>
                    <span>Users</span>
                </a>
            </li>

            <!-- Departments -->
            <li>
                <a class="loka-nav-link <?= activeMenu('departments') ?>" href="<?= APP_URL ?>/?page=departments">
                    <i class="bi bi-building w-5 text-center text-base-content/60"></i>
                    <span>Departments</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <!-- Audit Logs -->
            <li>
                <a class="loka-nav-link <?= activeMenu('audit') ?>" href="<?= APP_URL ?>/?page=audit">
                    <i class="bi bi-journal-text w-5 text-center text-base-content/60"></i>
                    <span>Audit Logs</span>
                </a>
            </li>

            <!-- Settings -->
            <li>
                <a class="loka-nav-link <?= activeMenu('settings') ?>" href="<?= APP_URL ?>/?page=settings">
                    <i class="bi bi-gear w-5 text-center text-base-content/60"></i>
                    <span>Settings</span>
                </a>
            </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Sidebar Footer -->
    <div class="px-4 py-3">
        <small class="text-xs text-white/40">
            <?= APP_NAME ?> v<?= APP_VERSION ?>
        </small>
    </div>
</nav>

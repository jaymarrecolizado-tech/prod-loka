<!-- Sidebar -->
<nav class="loka-sidebar" id="sidebar" aria-label="Main navigation">
    <div class="loka-sidebar-content">
        <ul class="flex flex-col gap-0.5 px-3 py-2">
            <!-- Dashboard (guards are redirected to Guard Dashboard) -->
            <li>
                <a class="loka-nav-link <?= activeMenu('dashboard') ?>"
                   href="<?= APP_URL ?>/?page=<?= isGuard() ? 'guard' : 'dashboard' ?>">
                    <i class="bi bi-speedometer2 w-5 text-center flex-shrink-0"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <?php if (isDriver()): ?>
            <li>
                <a class="loka-nav-link <?= activeMenu('my-trips') ?>" href="<?= APP_URL ?>/?page=my-trips">
                    <i class="bi bi-truck w-5 text-center flex-shrink-0"></i>
                    <span>My Trips</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (!isGuard()): ?>
            <!-- Requests -->
            <li>
                <a class="loka-nav-link <?= activeMenu('requests') ?>" href="<?= APP_URL ?>/?page=requests">
                    <i class="bi bi-file-earmark-text w-5 text-center flex-shrink-0"></i>
                    <span>Requests</span>
                    <?= sidebarBadgeHtml(badgeCountRequestsNeedingRevision(), true) ?>
                </a>
            </li>

            <!-- Completed Trips -->
            <li>
                <a class="loka-nav-link <?= activeMenu('completed-trips') ?>" href="<?= APP_URL ?>/?page=completed-trips">
                    <i class="bi bi-check-all w-5 text-center flex-shrink-0"></i>
                    <span>Completed Trips</span>
                </a>
            </li>

            <?php endif; ?>

            <?php if (canAccessGasVouchers()): ?>
            <li>
                <a class="loka-nav-link <?= activeMenu('gas-vouchers') ?>" href="<?= APP_URL ?>/?page=gas-vouchers">
                    <i class="bi bi-fuel-pump w-5 text-center flex-shrink-0"></i>
                    <span>Gas Vouchers</span>
                    <?= sidebarBadgeHtml(badgeCountPendingGasVouchers()) ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Schedule Calendar -->
            <li>
                <a class="loka-nav-link <?= activeMenu('schedule') ?>" href="<?= APP_URL ?>/?page=schedule&action=calendar">
                    <i class="bi bi-calendar3 w-5 text-center flex-shrink-0"></i>
                    <span>Availability</span>
                </a>
            </li>

            <?php if (isGuard()): ?>
            <li>
                <a class="loka-nav-link <?= activeMenu('guard') ?>" href="<?= APP_URL ?>/?page=guard">
                    <i class="bi bi-shield-check w-5 text-center flex-shrink-0"></i>
                    <span>Guard Dashboard</span>
                    <?= sidebarBadgeHtml(badgeCountGuardOps(), true) ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isApprover()): ?>
            <!-- My Trip Tickets -->
            <li>
                <a class="loka-nav-link <?= activeMenu('my-trip-tickets') ?>" href="<?= APP_URL ?>/?page=my-trip-tickets">
                    <i class="bi bi-journal-check w-5 text-center flex-shrink-0"></i>
                    <span>My Trip Tickets</span>
                    <?= sidebarBadgeHtml(badgeCountSubmittedTripTickets()) ?>
                </a>
            </li>

            <!-- Approvals -->
            <li>
                <a class="loka-nav-link <?= activeMenu('approvals') ?>" href="<?= APP_URL ?>/?page=approvals">
                    <i class="bi bi-check-circle w-5 text-center flex-shrink-0"></i>
                    <span>Approvals</span>
                    <?= sidebarBadgeHtml(badgeCountPendingApprovals(), true) ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isApprover()): ?>
            <li class="loka-section-label mt-3">Fleet Management</li>

            <!-- Vehicles -->
            <li>
                <a class="loka-nav-link <?= activeMenu('vehicles') ?>" href="<?= APP_URL ?>/?page=vehicles">
                    <i class="bi bi-car-front w-5 text-center flex-shrink-0"></i>
                    <span>Vehicles</span>
                    <?= sidebarBadgeHtml(badgeCountVehiclesAttention()) ?>
                </a>
            </li>

            <!-- Drivers -->
            <li>
                <a class="loka-nav-link <?= activeMenu('drivers') ?>" href="<?= APP_URL ?>/?page=drivers">
                    <i class="bi bi-person-badge w-5 text-center flex-shrink-0"></i>
                    <span>Drivers</span>
                </a>
            </li>

            <?php if (isAdmin() || isMotorpool() || isApprover()): ?>
            <!-- Vehicle Types -->
            <li>
                <a class="loka-nav-link <?= activeMenu('vehicle_types') ?>" href="<?= APP_URL ?>/?page=vehicle_types">
                    <i class="bi bi-tag w-5 text-center flex-shrink-0"></i>
                    <span>Vehicle Types</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Maintenance -->
            <li>
                <a class="loka-nav-link <?= activeMenu('maintenance') ?>" href="<?= APP_URL ?>/?page=maintenance">
                    <i class="bi bi-wrench w-5 text-center flex-shrink-0"></i>
                    <span>Maintenance</span>
                    <?= sidebarBadgeHtml(badgeCountPendingMaintenance()) ?>
                </a>
            </li>
            <!-- Maintenance Schedule -->
            <li>
                <a class="loka-nav-link <?= activeMenu('maintenance', 'schedule') ?>" href="<?= APP_URL ?>/?page=maintenance&action=schedule">
                    <i class="bi bi-calendar-check w-5 text-center flex-shrink-0"></i>
                    <span>Maint. Schedule</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (canAccessDriverReports()): ?>
            <li class="loka-section-label mt-3">Reports</li>

            <li>
                <a class="loka-nav-link <?= activeMenu('reports') ?>" href="<?= APP_URL ?>/?page=reports">
                    <i class="bi bi-bar-chart w-5 text-center flex-shrink-0"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isMotorpool()): ?>
            <li class="loka-section-label mt-3">Administration</li>

            <!-- Users -->
            <li>
                <a class="loka-nav-link <?= activeMenu('users') ?>" href="<?= APP_URL ?>/?page=users">
                    <i class="bi bi-people w-5 text-center flex-shrink-0"></i>
                    <span>Users</span>
                </a>
            </li>

            <!-- Departments -->
            <li>
                <a class="loka-nav-link <?= activeMenu('departments') ?>" href="<?= APP_URL ?>/?page=departments">
                    <i class="bi bi-building w-5 text-center flex-shrink-0"></i>
                    <span>Departments</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <!-- Audit Logs -->
            <li>
                <a class="loka-nav-link <?= activeMenu('audit') ?>" href="<?= APP_URL ?>/?page=audit">
                    <i class="bi bi-journal-text w-5 text-center flex-shrink-0"></i>
                    <span>Audit Logs</span>
                </a>
            </li>

            <!-- Settings -->
            <li>
                <a class="loka-nav-link <?= activeMenu('settings') ?>" href="<?= APP_URL ?>/?page=settings">
                    <i class="bi bi-gear w-5 text-center flex-shrink-0"></i>
                    <span>Settings</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isAllFather()):
                $secAction = get('action', 'rate-limits');
                if ($secAction === 'index' || $secAction === '') {
                    $secAction = 'rate-limits';
                }
                $secOpen = get('page') === 'security';
            ?>
            <li class="loka-nav-group <?= $secOpen ? 'is-open' : '' ?>">
                <div class="loka-nav-link loka-nav-group-label <?= $secOpen ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock w-5 text-center flex-shrink-0"></i>
                    <span>System Control</span>
                </div>
                <ul class="loka-nav-submenu">
                    <li>
                        <a class="loka-nav-link loka-nav-sublink <?= ($secOpen && $secAction === 'rate-limits') ? 'active' : '' ?>"
                           href="<?= APP_URL ?>/?page=security&action=rate-limits">
                            <i class="bi bi-unlock w-5 text-center flex-shrink-0"></i>
                            <span>Lockouts</span>
                            <?= sidebarBadgeHtml(badgeCountSecurityLockouts(), true) ?>
                        </a>
                    </li>
                    <li>
                        <a class="loka-nav-link loka-nav-sublink <?= ($secOpen && $secAction === 'summary') ? 'active' : '' ?>"
                           href="<?= APP_URL ?>/?page=security&action=summary">
                            <i class="bi bi-bar-chart-line w-5 text-center flex-shrink-0"></i>
                            <span>Summary</span>
                        </a>
                    </li>
                    <li>
                        <a class="loka-nav-link loka-nav-sublink <?= ($secOpen && $secAction === 'sms') ? 'active' : '' ?>"
                           href="<?= APP_URL ?>/?page=security&action=sms">
                            <i class="bi bi-phone w-5 text-center flex-shrink-0"></i>
                            <span>SMS</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <li>
                <a class="loka-nav-link <?= activeMenu('patch-notes') ?>" href="<?= APP_URL ?>/?page=patch-notes">
                    <i class="bi bi-newspaper w-5 text-center flex-shrink-0"></i>
                    <span>Patch Notes</span>
                </a>
            </li>

        </ul>
    </div>

    <!-- Sidebar Footer -->
    <div class="loka-sidebar-footer">
        <?= APP_NAME ?> v<?= APP_VERSION ?>
    </div>
</nav>

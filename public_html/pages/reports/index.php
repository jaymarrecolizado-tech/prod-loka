<?php
/**
 * LOKA - Reports Page (Administrator / All Father only)
 */

if (!canAccessOpsReports()) {
    redirectWith('/?page=dashboard', 'danger', 'Administrator or All Father access required.');
}

$pageTitle = 'Reports';

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="mb-4">
        <h4 class="mb-1">Reports</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </nav>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <!-- Gas Voucher Report -->
        <div class="col-span-12 md:col-span-6 lg:col-span-4">
            <a href="<?= APP_URL ?>/?page=reports&action=gas-vouchers" class="no-underline">
                <div class="loka-card h-full">
                    <div class="loka-card-body text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-fuel-pump text-warning" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5>Gas Vouchers</h5>
                        <p class="text-base-content/60 mb-3">Approval and payment analytics with CSV &amp; PDF export</p>
                        <span class="loka-badge bg-warning text-dark">CSV & PDF + Charts</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Vehicle History -->
        <div class="col-span-12 md:col-span-6 lg:col-span-4">
            <a href="<?= APP_URL ?>/?page=reports&action=vehicle-history" class="no-underline">
                <div class="loka-card h-full">
                    <div class="loka-card-body text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-car-front text-primary" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5>Vehicle History</h5>
                        <p class="text-base-content/60 mb-3">View trip history and utilization for each vehicle with export options</p>
                        <span class="loka-badge bg-primary">PDF Export</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Driver Report -->
        <div class="col-span-12 md:col-span-6 lg:col-span-4">
            <a href="<?= APP_URL ?>/?page=reports&action=driver" class="no-underline">
                <div class="loka-card h-full">
                    <div class="loka-card-body text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-person-badge text-success" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5>Driver Report</h5>
                        <p class="text-base-content/60 mb-3">View trip history and statistics for each driver with export options</p>
                        <span class="loka-badge bg-success">PDF Export</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Trip Requests Report -->
        <div class="col-span-12 md:col-span-6 lg:col-span-4">
            <a href="<?= APP_URL ?>/?page=reports&action=trips" class="no-underline">
                <div class="loka-card h-full">
                    <div class="loka-card-body text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-journal-text text-info" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5>Trip Requests</h5>
                        <p class="text-base-content/60 mb-3">View all trip requests with filtering and export options</p>
                        <span class="loka-badge bg-info">CSV & PDF Export</span>
                    </div>
                </div>
            </a>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Admin Reports -->
        <div class="col-span-12 md:col-span-6 lg:col-span-4">
            <a href="<?= APP_URL ?>/?page=admin-reports" class="no-underline">
                <div class="loka-card h-full">
                    <div class="loka-card-body text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-bar-chart-fill text-warning" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5>Admin Reports</h5>
                        <p class="text-base-content/60 mb-3">Advanced reports: users, vehicles, departments, audit logs</p>
                        <span class="loka-badge bg-warning text-dark">Admin Only</span>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

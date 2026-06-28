<?php
/**
 * LOKA - Admin Export Reports Page
 *
 * Admin-only page for exporting system data in CSV and PDF formats
 */

requireRole(ROLE_ADMIN);

$pageTitle = 'Export Reports';
$type = get('type', 'requests');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));

$availableReports = [
    'requests' => [
        'title' => 'Vehicle Requests',
        'description' => 'All vehicle requests with details, status, and timeline',
        'icon' => 'bi-car-front',
    ],
    'users' => [
        'title' => 'Users',
        'description' => 'All users with roles, departments, and account status',
        'icon' => 'bi-people',
    ],
    'vehicles' => [
        'title' => 'Vehicles',
        'description' => 'All vehicles with types, status, and specifications',
        'icon' => 'bi-truck',
    ],
    'departments' => [
        'title' => 'Departments',
        'description' => 'All departments with heads and member counts',
        'icon' => 'bi-building',
    ],
    'maintenance' => [
        'title' => 'Maintenance Records',
        'description' => 'All maintenance requests and records',
        'icon' => 'bi-tools',
    ],
    'audit_logs' => [
        'title' => 'Audit Logs',
        'description' => 'System audit logs and activity history',
        'icon' => 'bi-journal-text',
    ],
    'driver_history' => [
        'title' => 'Driver Trip History',
        'description' => 'Driver performance and trip statistics',
        'icon' => 'bi-person-badge',
    ],
    'vehicle_history' => [
        'title' => 'Vehicle Trip History',
        'description' => 'Vehicle utilization and usage statistics',
        'icon' => 'bi-car-front-fill',
    ],
    'department_usage' => [
        'title' => 'Department Usage',
        'description' => 'Department request frequency and utilization',
        'icon' => 'bi-building-fill',
    ],
];

if (!isset($availableReports[$type])) {
    $type = 'requests';
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container mx-auto px-4 py-4">
    <div class="mb-4">
        <h4 class="text-xl font-semibold mb-1">Export Reports</h4>
        <div class="text-sm breadcrumbs">
            <ul>
                <li><a href="<?= APP_URL ?>">Dashboard</a></li>
                <li class="text-base-content/50">Export Reports</li>
            </ul>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-4">
        <div class="lg:w-1/3">
            <div class="bg-base-100 rounded-xl shadow">
                <div class="border-b border-base-200 p-4">
                    <h6 class="font-semibold m-0 flex items-center gap-2"><i class="bi bi-file-earmark-bar"></i> Select Report</h6>
                </div>
                <div class="flex flex-col">
                    <?php foreach ($availableReports as $reportType => $report): ?>
                        <a href="?page=admin-reports&type=<?= $reportType ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>"
                           class="flex items-center gap-3 p-3 hover:bg-base-200 transition-colors <?= $type === $reportType ? 'bg-primary text-primary-content' : '' ?>">
                            <i class="bi <?= $report['icon'] ?>"></i>
                            <div>
                                <div class="font-bold"><?= e($report['title']) ?></div>
                                <small class="<?= $type === $reportType ? 'text-primary-content/70' : 'text-base-content/50' ?>"><?= e($report['description']) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-base-100 rounded-xl shadow mt-3">
                <div class="border-b border-base-200 p-4">
                    <h6 class="font-semibold m-0 flex items-center gap-2"><i class="bi bi-calendar-range"></i> Date Range</h6>
                </div>
                <div class="p-4">
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="admin-reports">
                        <input type="hidden" name="type" value="<?= $type ?>">
                    <div class="mb-4">
                        <label class="loka-form-label">Start Date</label>
                        <input type="date" class="loka-form-input w-full" name="start_date" value="<?= e($startDate) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="loka-form-label">End Date</label>
                        <input type="date" class="loka-form-input w-full" name="end_date" value="<?= e($endDate) ?>" required>
                    </div>
                    <button type="submit" class="loka-btn-primary w-full">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:w-2/3">
            <div class="bg-base-100 rounded-xl shadow">
                <div class="border-b border-base-200 p-4">
                    <h6 class="font-semibold m-0 flex items-center gap-2">
                        <i class="bi <?= $availableReports[$type]['icon'] ?>"></i>
                        <?= e($availableReports[$type]['title']) ?>
                    </h6>
                </div>
                <div class="p-4">
                    <p class="text-base-content/50 mb-4"><?= e($availableReports[$type]['description']) ?></p>

                    <div class="loka-alert loka-alert-info mb-4">
                        <i class="bi bi-info-circle"></i>
                        <span>Date Range: <strong><?= e($startDate) ?></strong> to <strong><?= e($endDate) ?></strong></span>
                    </div>

                    <hr class="border-base-200 my-4">

                    <h6 class="font-semibold mb-3">Download Report</h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <a href="?page=export&format=csv&type=<?= $type ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>"
                               class="bg-success text-success-content hover:bg-success/90 text-base px-6 py-3 font-medium rounded-xl inline-flex items-center gap-2 transition-colors w-full">
                                <i class="bi bi-filetype-csv"></i> Download CSV
                            </a>
                            <small class="text-base-content/50 block text-center mt-1">Opens in Excel, Google Sheets</small>
                        </div>
                        <div>
                            <a href="?page=export&format=pdf&type=<?= $type ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>"
                               class="bg-error text-error-content hover:bg-error/90 text-base px-6 py-3 font-medium rounded-xl inline-flex items-center gap-2 transition-colors w-full">
                                <i class="bi bi-filetype-pdf"></i> Download PDF
                            </a>
                            <small class="text-base-content/50 block text-center mt-1">Best for printing and sharing</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $recentExports = db()->fetchAll(
                "SELECT action, entity_type, created_at, new_data as details
                 FROM audit_logs
                 WHERE action = 'data_export'
                 ORDER BY created_at DESC
                 LIMIT 5"
            );
            ?>

            <?php if (!empty($recentExports)): ?>
            <div class="bg-base-100 rounded-xl shadow mt-3">
                <div class="border-b border-base-200 p-4">
                    <h6 class="font-semibold m-0 flex items-center gap-2"><i class="bi bi-clock-history"></i> Recent Exports</h6>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm table-zebra m-0">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Report Type</th>
                                <th>Format</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExports as $export):
                                $details = json_decode($export->details, true) ?? [];
                            ?>
                                <tr>
                                    <td><?= e($export->created_at) ?></td>
                                    <td><?= e(ucfirst(str_replace('_', ' ', $export->entity_type))) ?></td>
                                    <td>
                                        <span class="badge <?= ($details['format'] ?? 'csv') === 'pdf' ? 'badge-error' : 'badge-success' ?>">
                                            <?= e(strtoupper($details['format'] ?? 'csv')) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

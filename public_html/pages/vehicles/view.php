<?php
/**
 * LOKA - View Vehicle Page
 */

requireRole(ROLE_APPROVER);

$vehicleId = (int) get('id');
$vehicle = db()->fetch(
    "SELECT v.*, vt.name as type_name, vt.passenger_capacity
     FROM vehicles v
     JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
     WHERE v.id = ? AND v.deleted_at IS NULL",
    [$vehicleId]
);

if (!$vehicle) redirectWith('/?page=vehicles', 'danger', 'Vehicle not found.');

// Get recent trips
$recentTrips = db()->fetchAll(
    "SELECT r.*, u.name as requester_name
     FROM requests r
     JOIN users u ON r.user_id = u.id
     WHERE r.vehicle_id = ? AND r.deleted_at IS NULL
     ORDER BY r.start_datetime DESC LIMIT 10",
    [$vehicleId]
);

$pageTitle = 'Vehicle: ' . $vehicle->plate_number;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h4 class="mb-1"><?= e($vehicle->plate_number) ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=vehicles">Vehicles</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </nav>
        </div>
        <?php if (isMotorpool()): ?>
        <a href="<?= APP_URL ?>/?page=vehicles&action=edit&id=<?= $vehicleId ?>" class="loka-btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="loka-card mb-4">
                <div class="px-6 py-4 border-b border-base-200"><h5 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle Details</h5></div>
                <div class="p-6">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 md:col-span-6">
                            <label class="text-base-content/60 small">Make & Model</label>
                            <div class="fw-bold"><?= e($vehicle->make . ' ' . $vehicle->model) ?></div>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="text-base-content/60 small">Year</label>
                            <div class="fw-bold"><?= e($vehicle->year) ?></div>
                        </div>
                        <div class="col-span-12 md:col-span-3">
                            <label class="text-base-content/60 small">Color</label>
                            <div class="fw-bold"><?= e($vehicle->color ?: '-') ?></div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="text-base-content/60 small">Type</label>
                            <div class="fw-bold"><?= e($vehicle->type_name) ?> (<?= $vehicle->passenger_capacity ?> seats)</div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="text-base-content/60 small">Fuel Type</label>
                            <div class="fw-bold"><?= ucfirst($vehicle->fuel_type) ?></div>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <label class="text-base-content/60 small">Transmission</label>
                            <div class="fw-bold"><?= ucfirst($vehicle->transmission) ?></div>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="text-base-content/60 small">Mileage</label>
                            <div class="fw-bold"><?= number_format($vehicle->mileage) ?> km</div>
                            <?php if (!empty($vehicle->odometer_broken)): ?>
                            <div class="text-warning text-sm mt-1"><i class="bi bi-exclamation-triangle"></i> Odometer broken / unreadable</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-12 md:col-span-6">
                            <label class="text-base-content/60 small">Status</label>
                            <div><?= vehicleStatusBadge($vehicle->status) ?></div>
                        </div>
                        <?php if ($vehicle->notes): ?>
                        <div class="col-span-12">
                            <label class="text-base-content/60 small">Notes</label>
                            <div><?= nl2br(e($vehicle->notes)) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Trips -->
            <div class="loka-card">
                <div class="px-6 py-4 border-b border-base-200"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Trips</h5></div>
                <div class="p-6">
                    <?php if (empty($recentTrips)): ?>
                    <div class="empty-state py-4"><p class="mb-0 text-base-content/60">No trips yet</p></div>
                    <?php else: ?>
                    <div class="loka-table-responsive">
                        <table class="loka-table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Requester</th><th>Destination</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentTrips as $trip): ?>
                                <tr>
                                    <td><?= formatDateTime($trip->start_datetime) ?></td>
                                    <td><?= e($trip->requester_name) ?></td>
                                    <td><?= truncate($trip->destination, 30) ?></td>
                                    <td><?= requestStatusBadge($trip->status) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-span-12 lg:col-span-4">
            <div class="loka-card">
                <div class="p-6 text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle inline-flex items-center justify-center mb-3" style="width:80px;height:80px">
                        <i class="bi bi-car-front text-primary fs-1"></i>
                    </div>
                    <h5><?= e($vehicle->plate_number) ?></h5>
                    <p class="text-base-content/60 mb-0"><?= e($vehicle->make . ' ' . $vehicle->model . ' ' . $vehicle->year) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

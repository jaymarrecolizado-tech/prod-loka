<?php
/**
 * LOKA - View Trip Ticket
 * 
 * View details of a trip ticket
 */

requireRole(ROLE_GUARD);

$ticketId = (int) get('id', 0);
if (!$ticketId) {
    redirectWith('/?page=trip-tickets', 'danger', 'Invalid ticket ID.');
}

// Get ticket with full details
$ticket = db()->fetch(
    "SELECT tt.*, 
            r.id as request_id, r.destination as trip_destination, r.purpose as trip_purpose,
            r.actual_dispatch_datetime, r.actual_arrival_datetime,
            d.license_number as driver_license, d.name as driver_name, d.phone as driver_phone,
            u_req.name as requester_name, u_req.email as requester_email, u_req.phone as requester_phone,
            dg.name as dispatch_guard, dg.phone as dispatch_guard_phone,
            ag.name as arrival_guard, ag.phone as arrival_guard_phone,
            u_rev.name as reviewed_by_name, u_rev.email as reviewed_by_email
     FROM trip_tickets tt
     JOIN requests r ON tt.request_id = r.id
     LEFT JOIN drivers d ON tt.driver_id = d.id
     LEFT JOIN users u_req ON r.user_id = u_req.id
     LEFT JOIN users dg ON tt.dispatch_guard_id = dg.id
     LEFT JOIN users ag ON tt.arrival_guard_id = ag.id
     LEFT JOIN users u_rev ON tt.reviewed_by = u_rev.id
     WHERE tt.id = ? AND tt.deleted_at IS NULL",
    [$ticketId]
);

if (!$ticket) {
    redirectWith('/?page=trip-tickets', 'danger', 'Ticket not found.');
}

$pageTitle = 'Trip Ticket Details';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/?page=trip-tickets">Trip Tickets</a></li>
            <li class="breadcrumb-item active" aria-current="page">#<?= $ticket->id ?></li>
        </ol>
    </nav>

    <!-- Ticket Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Trip Ticket #<?= $ticket->id ?>
                        <span class="badge bg-<?= $ticket->status === 'approved' ? 'success' : ($ticket->status === 'reviewed' ? 'info' : 'warning') ?> ms-2">
                            <?= ucfirst($ticket->status) ?>
                        </span>
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Trip Reference -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-link-45deg me-1"></i> Trip Reference
                        </h6>
                        <div class="p-3 bg-light rounded">
                            <strong>Request #<?= $ticket->request_id ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= e($ticket->trip_destination) ?>
                            </small>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-card-text me-1"></i>
                                <?= truncate($ticket->trip_purpose, 50) ?>
                            </small>
                        </div>
                    </div>

                    <!-- Driver Information -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-person me-1"></i> Driver Information
                        </h6>
                        <div class="p-3 bg-light rounded">
                            <strong><?= e($ticket->driver_name) ?></strong>
                            <?php if ($ticket->driver_phone): ?>
                                <small class="text-muted">
                                    <i class="bi bi-telephone me-1"></i>
                                    <?= e($ticket->driver_phone) ?>
                                </small>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-card-text me-1"></i>
                                License: <?= e($ticket->driver_license) ?>
                            </small>
                        </div>
                    </div>

                    <!-- Trip Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-calendar3 me-1"></i> Trip Type
                                </h6>
                                <span class="badge bg-<?= $ticket->trip_type === 'official' ? 'success' : ($ticket->trip_type === 'personal' ? 'info' : 'warning') ?>">
                                    <?= ucfirst($ticket->trip_type) ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-flag me-1"></i> Destination
                                </h6>
                                <p class="mb-0"><?= e($ticket->destination) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-card-text me-1"></i>
                                    <?= truncate($ticket->purpose, 100) ?>
                                </small>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-people me-1"></i> Passengers
                                </h6>
                                <span class="fw-bold"><?= $ticket->passengers ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-clock-history me-1"></i> Trip Duration
                                </h6>
                                <p class="mb-0">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    <?= formatDateTime($ticket->start_date) ?>
                                </p>
                                <p class="mb-0">
                                    <i class="bi bi-calendar-x me-1"></i>
                                    <?= formatDateTime($ticket->end_date) ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-truck me-1"></i> Actual Trip Duration
                                </h6>
                                <?php
                                $start = strtotime($ticket->start_date);
                                $end = strtotime($ticket->end_date);
                                $diff = $end - $start;
                                $hours = floor($diff / 3600);
                                $minutes = floor(($diff % 3600) / 60);
                                ?>
                                <span class="fw-bold fs-5">
                                    <?= $hours ?>h <?= $minutes ?>m
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Mileage & Fuel -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-speedometer2 me-1"></i> Mileage
                            </h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Start Odometer</td>
                                        <td><strong><?= $ticket->start_mileage ? number_format($ticket->start_mileage) . ' km' : '-' ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>End Odometer</td>
                                        <td><strong><?= $ticket->end_mileage ? number_format($ticket->end_mileage) . ' km' : '-' ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Distance Traveled</td>
                                        <td><strong><?= $ticket->distance_traveled ? number_format($ticket->distance_traveled) . ' km' : '-' ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-fuel-pump me-1"></i> Fuel
                            </h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Consumed</td>
                                        <td><strong><?= $ticket->fuel_consumed ? number_format($ticket->fuel_consumed, 2) . ' L' : '-' ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Cost</td>
                                        <td><strong><?= $ticket->fuel_cost ? 'PHP ' . number_format($ticket->fuel_cost, 2) : '-' ?></strong></td>
                                    </tr>
                                    <?php if ($ticket->fuel_consumed && $ticket->distance_traveled): ?>
                                        <tr>
                                            <td>Efficiency</td>
                                            <td>
                                                <span class="badge bg-<?= ($ticket->distance_traveled / $ticket->fuel_consumed) < 10 ? 'danger' : (($ticket->distance_traveled / $ticket->fuel_consumed) < 15 ? 'warning' : 'success') ?>">
                                                    <?= number_format($ticket->distance_traveled / $ticket->fuel_consumed, 2) ?> km/L
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Documents -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-files me-1"></i> Attached Documents
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-center p-3 <?= $ticket->travel_order_path ? 'bg-success' : 'bg-secondary' ?>">
                                    <i class="bi bi-file-earmark-text fs-2"></i>
                                    <div class="mt-2">
                                        <strong>Travel Order</strong>
                                        <br>
                                        <small><?= $ticket->travel_order_path ? 'Attached' : 'Not attached' ?></small>
                                        <?php if ($ticket->travel_order_path): ?>
                                            <br>
                                            <a href="<?= '/uploads/trip_tickets/' . $ticket->travel_order_path ?>" target="_blank" class="btn btn-sm btn-outline-light mt-1">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 <?= $ticket->ob_slip_path ? 'bg-primary' : 'bg-secondary' ?>">
                                    <i class="bi bi-card-checklist fs-2"></i>
                                    <div class="mt-2">
                                        <strong>OB Slip</strong>
                                        <br>
                                        <small><?= $ticket->ob_slip_path ? 'Attached' : 'Not attached' ?></small>
                                        <?php if ($ticket->ob_slip_path): ?>
                                            <br>
                                            <a href="<?= '/uploads/trip_tickets/' . $ticket->ob_slip_path ?>" target="_blank" class="btn btn-sm btn-outline-light mt-1">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 <?= $ticket->other_documents_path ? 'bg-info' : 'bg-secondary' ?>">
                                    <i class="bi bi-folder2-open fs-2"></i>
                                    <div class="mt-2">
                                        <strong>Other Docs</strong>
                                        <br>
                                        <small><?= $ticket->other_documents_path ? 'Attached' : 'Not attached' ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Issues -->
                    <?php if ($ticket->has_issues): ?>
                        <div class="alert alert-danger mb-4">
                            <h6 class="mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Issues Reported
                            </h6>
                            <p class="mb-2"><?= e($ticket->issues_description) ?></p>
                            <div>
                                <strong>Status:</strong>
                                <?php if ($ticket->resolved): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check me-1"></i> Resolved
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x me-1"></i> Unresolved
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($ticket->resolution_notes): ?>
                                <hr class="my-3">
                                <p class="mb-0">
                                    <strong>Resolution Notes:</strong><br>
                                    <?= e($ticket->resolution_notes) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Guard Verification -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-shield-check me-1"></i> Dispatch Verification
                            </h6>
                            <div class="p-3 bg-light rounded">
                                <strong><?= e($ticket->dispatch_guard) ?></strong>
                                <?php if ($ticket->dispatch_guard_phone): ?>
                                    <br><small class="text-muted"><i class="bi bi-telephone me-1"></i> <?= e($ticket->dispatch_guard_phone) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Arrival Verification
                            </h6>
                            <div class="p-3 bg-light rounded">
                                <strong><?= e($ticket->arrival_guard) ?></strong>
                                <?php if ($ticket->arrival_guard_phone): ?>
                                    <br><small class="text-muted"><i class="bi bi-telephone me-1"></i> <?= e($ticket->arrival_guard_phone) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Guard Notes -->
                    <?php if ($ticket->guard_notes): ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-chat-text me-1"></i> Guard Notes
                            </h6>
                            <div class="p-3 bg-info bg-opacity-10 rounded">
                                <div style="white-space: pre-wrap;"><?= nl2br(e($ticket->guard_notes)) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Review Status -->
                    <?php if ($ticket->reviewed_by_name): ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-clipboard-check me-1"></i> Review Status
                            </h6>
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <div>
                                    <strong>Reviewed by:</strong> <?= e($ticket->reviewed_by_name) ?>
                                </div>
                                <div>
                                    <strong>Reviewed at:</strong> <?= formatDateTime($ticket->reviewed_at) ?>
                                </div>
                                <?php if ($ticket->status === 'approved'): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i> Approved
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Audit Trail -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-clock-history me-1"></i> Audit Trail
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>User</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Ticket Created</td>
                                        <td>
                                            <?php if ($ticket->driver_name): ?>
                                                <?= e($ticket->driver_name) ?>
                                            <?php else: ?>
                                                <small class="text-muted">System</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDateTime($ticket->created_at) ?></td>
                                    </tr>
                                    <?php if ($ticket->actual_dispatch_datetime): ?>
                                        <tr>
                                            <td>Trip Dispatched</td>
                                            <td>
                                                <small><?= e($ticket->requester_name) ?></small>
                                            </td>
                                            <td><?= formatDateTime($ticket->actual_dispatch_datetime) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($ticket->actual_arrival_datetime): ?>
                                        <tr>
                                            <td>Trip Arrived</td>
                                            <td>
                                                <small><?= e($ticket->requester_name) ?></small>
                                            </td>
                                            <td><?= formatDateTime($ticket->actual_arrival_datetime) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($ticket->reviewed_at): ?>
                                        <tr>
                                            <td>Ticket Reviewed</td>
                                            <td><?= e($ticket->reviewed_by_name) ?></td>
                                            <td><?= formatDateTime($ticket->reviewed_at) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mb-3">
                        <a href="?page=trip-tickets" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Trip Tickets
                        </a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Print Ticket
                        </button>
                        <a href="?page=requests&action=view&id=<?= $ticket->request_id ?>" class="btn btn-info">
                            <i class="bi bi-eye me-1"></i>View Original Request
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requester Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-circle me-2"></i>
                    Requester
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <strong><?= e($ticket->requester_name) ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted">
                        <i class="bi bi-envelope me-1"></i>
                        <?= e($ticket->requester_email) ?>
                    </small>
                </div>
                <?php if ($ticket->requester_phone): ?>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="bi bi-telephone me-1"></i>
                            <?= e($ticket->requester_phone) ?>
                        </small>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="mb-3">
                    <a href="mailto:<?= e($ticket->requester_email) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-send me-1"></i>Contact Requester
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .breadcrumb, .btn {
        display: none;
    }
    .card {
        page-break-inside: avoid;
    }
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

<?php
/**
 * LOKA - Gas Voucher Create / Edit Page
 */

$isEdit  = (get('action') === 'edit');
$voucherId = (int) get('id', 0);
$voucher = null;
$errors  = [];

// Auto-generate voucher number: YYYY-MM-NNN
function generateVoucherNo(): string {
    $year  = date('Y');
    $month = date('m');
    $prefix = "{$year}-{$month}-";
    $last = db()->fetchColumn(
        "SELECT voucher_no FROM gas_vouchers WHERE voucher_no LIKE ? ORDER BY voucher_no DESC LIMIT 1",
        ["{$prefix}%"]
    );
    if ($last) {
        $parts = explode('-', $last);
        $seq = (int) end($parts) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

// Fetch for edit
if ($isEdit) {
    $voucher = db()->fetch("SELECT * FROM gas_vouchers WHERE id = ? AND deleted_at IS NULL", [$voucherId]);
    if (!$voucher) {
        redirectWith('/?page=gas-vouchers', 'danger', 'Voucher not found.');
    }
    // Rules: Owner/Admin edit 'draft'. Approver/Motorpool/Admin edit 'pending_review'/'pending_approval'.
    $canEdit = false;
    if ($voucher->status === 'draft') {
        if ($voucher->requested_by_user_id == userId() || isAdmin()) $canEdit = true;
    } elseif (in_array($voucher->status, ['pending_review', 'pending_approval'])) {
        if (isApprover() || isMotorpool() || isAdmin()) $canEdit = true;
    }

    if (!$canEdit) {
        if ($voucher->status !== 'draft') {
            redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'warning', 'Voucher cannot be edited at this stage.');
        } else {
            redirectWith('/?page=gas-vouchers', 'danger', 'Access denied.');
        }
    }
    $pageTitle = 'Edit Gas Voucher';
} else {
    $pageTitle = 'New Gas Voucher';
}

// Build vehicles list for datalist
$vehicles = db()->fetchAll("SELECT plate_number FROM vehicles WHERE deleted_at IS NULL ORDER BY plate_number ASC");

// Build drivers list for datalist
$driversList = db()->fetchAll("SELECT u.name FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.deleted_at IS NULL ORDER BY u.name ASC");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $action       = post('form_action', 'save_draft');
    $driverName   = postSafe('driver_name', '', 100);
    $vehiclePlate = postSafe('vehicle_plate', '', 30);
    $fuelType     = post('fuel_type', 'Diesel');
    $quantity     = (float) post('quantity', 0);
    $unit         = postSafe('unit', 'L', 20);
    $otherItems   = postSafe('other_items', '', 500);
    $otherQty     = post('other_qty', '');
    $otherUnit    = postSafe('other_unit', '', 20);
    $fundSource   = postSafe('fund_source', '', 100);
    $purpose      = postSafe('purpose', '', 1000);
    $chargeableAgainst = postSafe('chargeable_against', '', 100);
    $requestDate  = post('request_date', date('Y-m-d'));
    $totalCost    = post('total_cost', '');
    $saro         = postSafe('saro_no', '', 50);
    $dateWithdrawn = post('date_withdrawn', '');

    // Validation
    if (empty($driverName))   $errors[] = 'Driver name is required.';
    if (empty($vehiclePlate)) $errors[] = 'Vehicle plate number is required.';
    if (empty($fuelType) || !in_array($fuelType, ['Gasoline', 'Diesel'])) $errors[] = 'Invalid fuel type.';
    if ($quantity <= 0)       $errors[] = 'Quantity must be greater than 0.';
    if (empty($unit))         $errors[] = 'Unit is required.';
    if (empty($fundSource))   $errors[] = 'Fund source is required.';
    if (empty($purpose))      $errors[] = 'Purpose is required.';
    if (empty($requestDate))  $errors[] = 'Request date is required.';

    // Lookup vehicle_id if plate matches
    $vehicleRow = db()->fetch("SELECT id FROM vehicles WHERE plate_number = ? AND deleted_at IS NULL", [$vehiclePlate]);
    $vehicleId  = $vehicleRow ? $vehicleRow->id : null;

    if (empty($errors)) {
        if ($isEdit && in_array($voucher->status, ['pending_review', 'pending_approval'])) {
            $newStatus = $voucher->status; // Preserve status for approvers making corrections
        } else {
            $newStatus = ($action === 'submit') ? 'pending_review' : 'draft';
        }

        $data = [
            'driver_name'        => $driverName,
            'vehicle_plate'      => strtoupper($vehiclePlate),
            'vehicle_id'         => $vehicleId,
            'fuel_type'          => $fuelType,
            'quantity'           => $quantity,
            'unit'               => $unit,
            'other_items'        => $otherItems ?: null,
            'other_qty'          => ($otherQty !== '' && $otherQty !== null) ? (float) $otherQty : null,
            'other_unit'         => $otherUnit ?: null,
            'fund_source'        => $fundSource,
            'purpose'            => $purpose,
            'chargeable_against' => $chargeableAgainst ?: null,
            'request_date'       => $requestDate,
            'total_cost'         => ($totalCost !== '' && $totalCost !== null) ? (float) $totalCost : null,
            'saro_no'            => $saro ?: null,
            'date_withdrawn'     => $dateWithdrawn ?: null,
            'status'             => $newStatus,
            'updated_at'         => date(DATETIME_FORMAT),
        ];

        db()->beginTransaction();
        try {
            if ($isEdit) {
                db()->update('gas_vouchers', $data, 'id = ?', [$voucherId]);
                $message = $newStatus === 'pending_review'
                    ? 'Gas voucher submitted for review.'
                    : 'Gas voucher updated.';
                auditLog('update', 'gas_voucher', $voucherId);
                db()->commit();
                redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'success', $message);
            } else {
                $data['voucher_no']          = generateVoucherNo();
                $data['requested_by_user_id'] = userId();
                $data['payment_status']       = 'unpaid';
                $data['created_at']           = date(DATETIME_FORMAT);
                $id = db()->insert('gas_vouchers', $data);
                auditLog('create', 'gas_voucher', $id);
                db()->commit();
                $message = $newStatus === 'pending_review'
                    ? 'Gas voucher submitted for review.'
                    : 'Gas voucher saved as draft.';
                redirectWith('/?page=gas-vouchers&action=view&id=' . $id, 'success', $message);
            }
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Pre-fill defaults
$d = $voucher ?? null;
$defaultDate = date('Y-m-d');

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-fuel-pump me-2"></i><?= $pageTitle ?></h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/?page=gas-vouchers">Gas Vouchers</a></li>
                            <li class="breadcrumb-item active"><?= $pageTitle ?></li>
                        </ol>
                    </nav>
                </div>
                <a href="<?= APP_URL ?>/?page=gas-vouchers" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" id="voucherForm">
                <?= csrfField() ?>

                <!-- Voucher Info Card -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Voucher Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if ($isEdit): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Voucher No.</label>
                                <input type="text" class="form-control" value="<?= e($voucher->voucher_no) ?>" disabled>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-<?= $isEdit ? '6' : '4' ?>">
                                <label class="form-label fw-semibold">Request Date <span class="text-danger">*</span></label>
                                <input type="date" name="request_date" class="form-control"
                                       value="<?= e($d ? $d->request_date : $defaultDate) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle & Driver Card -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="bi bi-car-front me-2"></i>Vehicle & Driver</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Driver / Bearer <span class="text-danger">*</span></label>
                                <input type="text" name="driver_name" class="form-control"
                                       list="driverList"
                                       placeholder="Select or type full name"
                                       value="<?= e($d?->driver_name ?? '') ?>" required>
                                <datalist id="driverList">
                                    <?php foreach ($driversList as $drv): ?>
                                    <option value="<?= e($drv->name) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text">Select an existing driver or type a name manually if they're not listed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Vehicle Plate Number <span class="text-danger">*</span></label>
                                <input type="text" name="vehicle_plate" class="form-control"
                                       list="plateList"
                                       placeholder="e.g., SJN 940"
                                       value="<?= e($d?->vehicle_plate ?? '') ?>" required>
                                <datalist id="plateList">
                                    <?php foreach ($vehicles as $veh): ?>
                                    <option value="<?= e($veh->plate_number) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fuel/Items Card -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-fuel-pump me-2"></i>Fuel / Articles Requested</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fuel Type <span class="text-danger">*</span></label>
                                <select name="fuel_type" class="form-select" required>
                                    <option value="Gasoline" <?= ($d?->fuel_type ?? 'Diesel') === 'Gasoline' ? 'selected' : '' ?>>Gasoline</option>
                                    <option value="Diesel" <?= ($d?->fuel_type ?? 'Diesel') === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control"
                                       step="0.01" min="0.01"
                                       placeholder="e.g., 50"
                                       value="<?= e($d?->quantity ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Unit <span class="text-danger">*</span></label>
                                <input type="text" name="unit" class="form-control"
                                       list="unitList" placeholder="Liters"
                                       value="<?= e($d?->unit ?? 'Liters') ?>" required>
                                <datalist id="unitList">
                                    <option value="Liters">
                                    <option value="FULL TANK">
                                </datalist>
                            </div>
                            <div class="col-12 mt-3">
                                <hr>
                                <label class="form-label fw-semibold">Other Articles / Particulars (Optional)</label>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <input type="number" name="other_qty" class="form-control" step="0.01" min="0.01" placeholder="Qty" value="<?= e($d?->other_qty ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="other_unit" class="form-control" placeholder="Unit" value="<?= e($d?->other_unit ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="other_items" class="form-control" placeholder="e.g., Engine Oil, Brake Fluid" value="<?= e($d?->other_items ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-text">Specify any additional items needing separate quantity and unit on the voucher.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fund & Purpose Card -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Fund Source & Purpose</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Fund Source <span class="text-danger">*</span></label>
                                <input type="text" name="fund_source" class="form-control"
                                       list="fundList"
                                       placeholder="e.g., Free WiFi, GASS, ELGU"
                                       value="<?= e($d?->fund_source ?? '') ?>" required>
                                <datalist id="fundList">
                                    <option value="GASS">
                                    <option value="Free WiFi">
                                    <option value="ELGU">
                                    <option value="ILCDB">
                                    <option value="DRRM/GECS">
                                    <option value="DTC/Tech4ED">
                                    <option value="PNPKI">
                                    <option value="IIDB">
                                    <option value="NBP/GovNet">
                                    <option value="GENERAL FUNDS">
                                    <option value="Cybersecurity">
                                    <option value="eGOV">
                                    <option value="GOVNET">
                                </datalist>
                                <div class="form-text">The project/program the fuel is derived from.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                                <textarea name="purpose" class="form-control" rows="3"
                                          placeholder="Describe the purpose of this fuel request..."
                                          required><?= e($d?->purpose ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 justify-content-end mb-4">
                    <a href="<?= APP_URL ?>/?page=gas-vouchers<?= $isEdit ? '&action=view&id=' . $voucherId : '' ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </a>
                    <?php if ($isEdit && in_array($voucher->status, ['pending_review', 'pending_approval'])): ?>
                        <button type="submit" name="form_action" value="save" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Corrections
                        </button>
                    <?php else: ?>
                        <button type="submit" name="form_action" value="save_draft" class="btn btn-outline-primary">
                            <i class="bi bi-floppy me-1"></i>Save as Draft
                        </button>
                        <button type="submit" name="form_action" value="submit" class="btn btn-success">
                            <i class="bi bi-send me-1"></i>Submit for Review
                        </button>
                    <?php endif; ?>
                </div>
            </form>

        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

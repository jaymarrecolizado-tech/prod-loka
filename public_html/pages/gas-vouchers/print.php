<?php
/**
 * LOKA - Gas Voucher Print Page
 * Generates a printable gas voucher matching the official DICT RO2 format.
 */

$voucherId = (int) get('id', 0);
if (!$voucherId) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Invalid voucher.');
}

$voucher = db()->fetch(
    "SELECT gv.*,
            u.name AS requester_name,
            reviewer.name AS reviewer_name,
            approver.name AS approver_name_full
     FROM gas_vouchers gv
     JOIN users u ON gv.requested_by_user_id = u.id
     LEFT JOIN users reviewer ON gv.reviewed_by = reviewer.id
     LEFT JOIN users approver ON gv.approved_by = approver.id
     WHERE gv.id = ? AND gv.deleted_at IS NULL",
    [$voucherId]
);

if (!$voucher) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Voucher not found.');
}

if ($voucher->status !== 'approved') {
    redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'warning', 'Only approved vouchers can be printed.');
}

// Access control
if ($voucher->requested_by_user_id != userId() && !isAdmin() && !isApprover() && !isMotorpool() && !isChiefAdminFinance()) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Access denied.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Voucher <?= e($voucher->voucher_no) ?> - DICT RO2</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            background: #fff;
            color: #000;
        }

        .page {
            width: 210mm;
            min-height: 140mm;
            margin: 0 auto;
            padding: 15mm;
            position: relative;
        }

        /* Header block */
        .grid-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-logo {
            width: 100px;
            object-fit: contain;
            mix-blend-mode: multiply; /* Fixes transparency/white background issues on print */
        }
        .header-text {
            flex: 1;
            text-align: center;
        }
        .header-text .republic {
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .header-text .dept {
            font-size: 12pt;
            font-weight: bold;
            font-family: 'Times New Roman', Times, serif;
            text-transform: uppercase;
            margin: 3px 0;
        }
        .header-text .office {
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        
        .header-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            letter-spacing: 5px;
            margin: 25px 0 15px;
            font-family: 'Arial', sans-serif;
        }

        /* Form Grid Table */
        .voucher-grid {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            font-size: 10.5pt;
        }

        .voucher-grid td, .voucher-grid th {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }

        .voucher-grid .label-cell {
            background-color: #fce4d6; /* Very light orange/pink typical in sheets */
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            print-color-adjust: exact;
            font-weight: bold;
            width: 20%;
        }

        .voucher-grid .value-cell {
            width: 30%;
            font-weight: bold;
        }

        /* Authorization Row */
        .auth-row {
            text-align: center;
            font-style: italic;
            padding: 12px !important;
            font-size: 11pt;
        }

        /* Articles Table Headers */
        .articles-header th {
            background-color: #d9e1f2; /* Light blue typical in sheets */
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            print-color-adjust: exact;
            text-align: center;
            font-weight: bold;
            padding: 8px;
        }

        .center {
            text-align: center;
        }

        /* Signatures Grid */
        .signatures-grid {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            border-top: none;
            text-align: center;
        }

        .signatures-grid td {
            border: 1px solid #000;
            padding: 10px;
            width: 33.33%;
            vertical-align: top;
        }

        .sig-label {
            font-size: 9pt;
            color: #333;
            margin-bottom: 25px;
            text-align: left;
        }

        .sig-name {
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .sig-title {
            font-size: 9pt;
            margin-top: 3px;
        }

        .distribution {
            margin-top: 20px;
            font-size: 9pt;
            font-style: italic;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Print Controls -->
<div class="no-print" style="text-align:center;padding:12px;background:#f8f9fa;border-bottom:1px solid #ddd;margin-bottom:15px;font-family:sans-serif;">
    <button onclick="window.print()" style="background:#0d6efd;color:#fff;border:none;padding:8px 24px;border-radius:4px;cursor:pointer;font-size:14px;font-weight:bold;">
        🖨️ Print Voucher
    </button>
    <a href="javascript:history.back()" style="margin-left:15px;color:#666;text-decoration:none;">← Back to App</a>
</div>

<div class="page">

    <!-- Formal Header -->
    <div class="grid-header">
        <img src="<?= APP_URL ?>/assets/img/dict_logo.png" class="header-logo" alt="DICT Logo" onerror="this.style.display='none'" style="margin-right:15px;">
        <div class="header-text">
            <div class="republic">Republic of the Philippines</div>
            <div class="dept">Department of Information and Communications Technology</div>
            <div class="office">Regional Office 02, 02 Bagay Road, San Gabriel, Tuguegarao City, Cagayan 3500</div>
        </div>
        <img src="<?= APP_URL ?>/assets/img/bp_logo.png" class="header-logo" alt="BP Logo" onerror="this.style.display='none'" style="margin-left:15px;">
    </div>

    <div class="header-title">G A S &nbsp; V O U C H E R</div>

    <!-- Main Spreadsheet Grid -->
    <table class="voucher-grid">
        <tr>
            <td class="label-cell">Date:</td>
            <td class="value-cell"><?= e(date('F j, Y', strtotime($voucher->request_date))) ?></td>
            <td class="label-cell">Voucher No.:</td>
            <td class="value-cell" style="color:red;"><?= e($voucher->voucher_no) ?></td>
        </tr>
        <tr>
            <td class="label-cell">Driver:</td>
            <td class="value-cell"><?= e(strtoupper($voucher->driver_name)) ?></td>
            <td class="label-cell">Vehicle Plate No.:</td>
            <td class="value-cell"><?= e(strtoupper($voucher->vehicle_plate)) ?></td>
        </tr>
        
        <!-- Authorization Row -->
        <tr>
            <td colspan="4" class="auth-row" style="text-align: left; padding-left: 20px !important;">
                This is to authorize the bearer <strong><?= e(strtoupper($voucher->driver_name)) ?></strong> to withdraw and secure the following products and/or services charge to the Account of <strong style="font-style:normal;text-decoration:underline;">DICT Region 02</strong>.
            </td>
        </tr>

        <!-- Articles Headers -->
        <tr class="articles-header">
            <th style="width:15%">QTY</th>
            <th style="width:15%">UNIT</th>
            <th colspan="2">ARTICLES / PARTICULARS</th>
        </tr>
        
        <!-- Articles Data -->
        <tr>
            <td class="center" style="font-weight:bold; font-size:12pt; padding:12px;"><?= e($voucher->quantity) ?></td>
            <td class="center" style="font-weight:bold; font-size:12pt; padding:12px;"><?= e($voucher->unit) ?></td>
            <td colspan="2" style="font-weight:bold; font-size:11pt; padding:12px; vertical-align:middle; color:#000;">
                <?= e($voucher->fuel_type) ?>
            </td>
        </tr>
        <?php if ($voucher->other_items || $voucher->other_qty || $voucher->other_unit): ?>
        <tr>
            <td class="center" style="font-weight:bold; font-size:12pt; padding:12px;"><?= e($voucher->other_qty) ?></td>
            <td class="center" style="font-weight:bold; font-size:12pt; padding:12px;"><?= e($voucher->other_unit) ?></td>
            <td colspan="2" style="font-weight:bold; font-size:11pt; padding:12px; vertical-align:middle; color:#000;">
                <?= e($voucher->other_items) ?>
            </td>
        </tr>
        <?php endif; ?>

        <!-- Purpose & Charging -->
        <tr>
            <td class="label-cell">Purpose:</td>
            <td colspan="3" class="value-cell" style="font-weight:normal;"><?= e($voucher->purpose) ?></td>
        </tr>
        <tr>
            <td class="label-cell">Chargeable against:</td>
            <td colspan="3" class="value-cell"><?= e($voucher->chargeable_against ?: $voucher->fund_source) ?></td>
        </tr>
    </table>

    <!-- Signatures section matching the grid width -->
    <table class="signatures-grid">
        <tr>
            <td>
                <div class="sig-label">Requested by / Bearer:</div>
                <div class="sig-name"><?= e(strtoupper($voucher->driver_name)) ?></div>
                <div class="sig-title">Driver</div>
            </td>
            <td>
                <div class="sig-label">Reviewed by:</div>
                <div class="sig-name"><?= e(strtoupper($voucher->reviewer_name ?? 'ENGR. RONALD S. BARIUAN')) ?></div>
                <div class="sig-title">OIC, Motor Pool Unit</div>
            </td>
            <td>
                <div class="sig-label">Approved by:</div>
                <div class="sig-name"><?= e(strtoupper($voucher->approver_name_full ?? 'MINA FLOR T. VILLAFUERTE')) ?></div>
                <div class="sig-title">Chief, Admin. and Finance Division</div>
            </td>
        </tr>
    </table>

    <div class="distribution">
        <strong>Distribution:</strong> Prepare three (3) copies.<br>
        1) Original copy for gas station attached to charge invoice &nbsp;&nbsp;&nbsp;&nbsp; 
        2) Control file copy &nbsp;&nbsp;&nbsp;&nbsp;
        3) Accounting unit copy for reconciliation
    </div>

</div>

</body>
</html>

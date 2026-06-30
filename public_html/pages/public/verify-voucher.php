<?php
/**
 * LOKA - Public Gas Voucher Validation Page
 */

$id = (int) get('id', 0);
$hash = get('hash', '');

if (!$id || empty($hash)) {
    $error = 'Invalid verification link.';
} else {
    // Fetch voucher details
    $voucher = db()->fetch(
        "SELECT gv.*, 
                u.name AS requester_name,
                v.color as vehicle_color, v.make as vehicle_make, v.model as vehicle_model
         FROM gas_vouchers gv
         JOIN users u ON gv.requested_by_user_id = u.id
         LEFT JOIN vehicles v ON gv.vehicle_id = v.id
         WHERE gv.id = ? AND gv.deleted_at IS NULL",
        [$id]
    );

    if (!$voucher) {
        $error = 'Voucher not found or has been deleted.';
    } else {
        // Validate Hash
        $expectedHash = hash_hmac('sha256', $voucher->id . '-' . $voucher->voucher_no, getenv('APP_KEY') ?: 'LOKA_SECRET');
        if (!hash_equals($expectedHash, $hash)) {
            $error = 'Voucher authenticity could not be verified. This may be a fraudulent document.';
        }
    }
}

// Visual status
$isValid = (!isset($error) && $voucher->status === 'approved');
$statusData = STATUS_LABELS[$voucher->status ?? ''] ?? ['label' => 'Unknown', 'color' => 'secondary'];

if (isset($voucher) && $voucher->status !== 'approved') {
    $error = 'Notice: This voucher is currently in "' . strtoupper($statusData['label']) . '" status and is not valid for use.';
}

$pageTitle = 'Verify Gas Voucher';
?>
<!DOCTYPE html>
<html lang="en" data-theme="loka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Gas Voucher | <?= APP_NAME ?></title>
    <!-- Use Tailwind CSS and DaisyUI via CDN for public page consistency -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.0/dist/full.css" rel="stylesheet" type="text/css" />
    <style>
        body { background-color: #f3f4f6; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden mt-6 mb-6">
    <!-- Header -->
    <div class="bg-blue-800 text-white p-6 text-center">
        <h1 class="text-2xl font-bold tracking-wider">LOKA SYSTEM</h1>
        <p class="text-blue-200 text-sm opacity-90">Gas Voucher Authenticity Check</p>
    </div>

    <div class="p-6">
        <?php if (isset($error)): ?>
            <div class="loka-alert loka-alert-danger shadow-lg mb-6 text-white bg-red-600 rounded-lg p-4 flex flex-row items-center gap-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div>
                    <h3 class="font-bold text-lg">Verification Failed</h3>
                    <div class="text-sm mt-1"><?= e($error) ?></div>
                </div>
            </div>
        <?php else: ?>
            
            <div class="flex items-center justify-center mb-6">
                <div class="avatar placeholder mr-3">
                    <div class="bg-green-100 text-green-600 rounded-full w-16 h-16 ring ring-green-500 ring-offset-2 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 border-b-2 border-green-500 inline-block">VERIFIED</h2>
                </div>
            </div>

            <div class="space-y-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-xl border border-gray-100 dark:border-gray-600 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold mb-1">Voucher Number</p>
                        <p class="text-lg font-bold text-red-600"><?= e($voucher->voucher_no) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-semibold mb-1">Request Date</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100"><?= date('M d, Y', strtotime($voucher->request_date)) ?></p>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 bg-opacity-50 rounded-xl border border-blue-50 shadow-sm">
                    <h3 class="text-sm font-bold text-blue-900 mb-3 border-b border-blue-100 pb-2">Driver & Vehicle</h3>
                    
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Driver:</div>
                        <div class="text-sm font-bold text-gray-800 dark:text-gray-200"><?= e(strtoupper($voucher->driver_name)) ?></div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Plate No:</div>
                        <div class="text-sm font-bold text-gray-800 dark:text-gray-200"><?= e(strtoupper($voucher->vehicle_plate)) ?></div>
                    </div>
                </div>

                <div class="p-4 bg-orange-50 bg-opacity-50 rounded-xl border border-orange-50 shadow-sm">
                    <h3 class="text-sm font-bold text-orange-900 mb-3 border-b border-orange-100 pb-2">Fuel Details</h3>
                    <div class="flex items-end justify-between">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Fuel Type</div>
                            <div class="text-lg font-bold text-gray-800 dark:text-gray-200"><?= e(strtoupper($voucher->fuel_type)) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Quantity</div>
                            <div class="text-2xl font-black text-orange-600"><?= floatval($voucher->quantity) ?> <span class="text-sm font-bold"><?= e($voucher->unit) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-xs text-gray-400 dark:text-gray-500">
                <p>This is an officially generated verification page by DICT Region 02.</p>
                <p class="mt-1">Scanned on <?= date('M d, Y h:i A') ?></p>
            </div>
            
        <?php endif; ?>
    </div>
</div>

</body>
</html>

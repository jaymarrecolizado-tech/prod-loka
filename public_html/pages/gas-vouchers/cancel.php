<?php
/**
 * LOKA - Gas Voucher Cancel Handler
 */

$voucherId = (int) get('id', 0);
if (!$voucherId) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Invalid voucher.');
}

$voucher = db()->fetch("SELECT * FROM gas_vouchers WHERE id = ? AND deleted_at IS NULL", [$voucherId]);

if (!$voucher) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Voucher not found.');
}

if ($voucher->requested_by_user_id != userId() && !isAdmin() && !isChiefAdminFinance()) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Access denied.');
}

if (!in_array($voucher->status, ['draft', 'pending_review'])) {
    redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'warning', 'This voucher cannot be cancelled at this stage.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    db()->update('gas_vouchers', [
        'status'     => 'cancelled',
        'updated_at' => date(DATETIME_FORMAT),
    ], 'id = ?', [$voucherId]);

    auditLog('cancel', 'gas_voucher', $voucherId);

    // Notify approvers if it was pending
    if ($voucher->status === 'pending_review') {
        $approvers = db()->fetchAll(
            "SELECT id FROM users WHERE role IN (?, ?, ?) AND deleted_at IS NULL",
            [ROLE_APPROVER, ROLE_MOTORPOOL, ROLE_ADMIN]
        );
        $cancelledBy = currentUser()->name;
        foreach ($approvers as $approver) {
            notify($approver->id, 'gas_voucher_cancelled', 'Gas Voucher Cancelled', "Gas voucher {$voucher->voucher_no} has been cancelled by {$cancelledBy}.", '/?page=gas-vouchers', $voucherId);
        }
    }

    redirectWith('/?page=gas-vouchers', 'success', 'Gas voucher cancelled.');
}

redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'info', 'Nothing was changed.');

<?php
/**
 * LOKA - Gas Voucher Payment Status Update Handler (Admin only)
 */

requireRole(ROLE_ADMIN);

$voucherId = (int) get('id', 0);
if (!$voucherId) {
    redirectWith('/?page=gas-vouchers', 'danger', 'Invalid voucher.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $status = post('payment_status', '');
    if (!in_array($status, ['unpaid', 'paid', 'processed', 'cancelled'])) {
        redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'danger', 'Invalid payment status.');
    }

    db()->update('gas_vouchers', [
        'payment_status' => $status,
        'updated_at'     => date(DATETIME_FORMAT),
    ], 'id = ?', [$voucherId]);

    auditLog('update_payment', 'gas_voucher', $voucherId);
    redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'success', 'Payment status updated to: ' . ucfirst($status));
}

redirectWith('/?page=gas-vouchers&action=view&id=' . $voucherId, 'info', 'Nothing was changed.');

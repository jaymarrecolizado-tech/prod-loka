<?php
/**
 * Shared sidebar / dashboard badge counts.
 */

function badgeCountPendingApprovals(): int
{
    try {
        if (isAdmin()) {
            return (int) db()->count(
                'requests',
                "status IN ('pending','pending_motorpool','revision') AND deleted_at IS NULL"
            );
        }
        if (isMotorpool()) {
            return (int) db()->count(
                'requests',
                "(status = 'pending_motorpool' OR status = 'revision') AND motorpool_head_id = ? AND deleted_at IS NULL",
                [userId()]
            );
        }
        if (isApprover()) {
            $deptId = currentUser()->department_id ?? null;
            if (!$deptId) {
                return 0;
            }
            return (int) db()->count(
                'requests',
                "status = 'pending' AND department_id = ? AND deleted_at IS NULL",
                [$deptId]
            );
        }
    } catch (Throwable $e) {
        error_log('badgeCountPendingApprovals: ' . $e->getMessage());
    }
    return 0;
}

function badgeCountPendingGasVouchers(): int
{
    try {
        if (isChiefAdminFinance() || isAdmin()) {
            return (int) db()->fetchColumn(
                "SELECT COUNT(*) FROM gas_vouchers WHERE status = 'pending_approval' AND deleted_at IS NULL"
            );
        }
        if (isMotorpool() || isApprover()) {
            return (int) db()->fetchColumn(
                "SELECT COUNT(*) FROM gas_vouchers WHERE status IN ('pending_review','pending_approval') AND deleted_at IS NULL"
            );
        }
    } catch (Throwable $e) {
        error_log('badgeCountPendingGasVouchers: ' . $e->getMessage());
    }
    return 0;
}

function badgeCountSubmittedTripTickets(): int
{
    try {
        if (isApprover() || isMotorpool() || isAdmin()) {
            return (int) db()->fetchColumn(
                "SELECT COUNT(*) FROM trip_tickets WHERE status = 'submitted' AND deleted_at IS NULL"
            );
        }
    } catch (Throwable $e) {
        error_log('badgeCountSubmittedTripTickets: ' . $e->getMessage());
    }
    return 0;
}

function badgeCountPendingMaintenance(): int
{
    try {
        if (isApprover() || isMotorpool() || isAdmin()) {
            return (int) db()->count(
                'maintenance_requests',
                'status IN (?, ?) AND deleted_at IS NULL',
                [MAINTENANCE_STATUS_PENDING, MAINTENANCE_STATUS_SCHEDULED]
            );
        }
    } catch (Throwable $e) {
        error_log('badgeCountPendingMaintenance: ' . $e->getMessage());
    }
    return 0;
}

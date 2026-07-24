<?php
/**
 * "View as" role — session-based effective role for testing.
 * Available to All Father and Administrator.
 * Real identity is preserved (System Control, audit actor user_id).
 */

const VIEW_AS_SESSION_KEY = 'view_as_role';

/**
 * Roles that may use View-as.
 */
function canUseViewAs(): bool
{
    return isRealAllFather() || isRealAdministrator();
}

function isRealAdministrator(): bool
{
    return realUserRole() === ROLE_ADMIN;
}

/**
 * Roles All Father / Administrator may view/act as.
 *
 * @return array<string, string> role => label
 */
function viewAsRoleOptions(): array
{
    $options = [
        ROLE_REQUESTER => 'Requester',
        ROLE_GUARD => 'Guard',
        ROLE_APPROVER => 'Approver',
        ROLE_MOTORPOOL => 'Motorpool Head',
        ROLE_CHIEF_ADMIN_FINANCE => 'Chief Admin & Finance',
        ROLE_ADMIN => 'Administrator',
        'driver' => 'Driver',
    ];

    // Do not offer the actor's own real role as a View-as target
    $real = realUserRole();
    if ($real !== null && isset($options[$real])) {
        unset($options[$real]);
    }

    return $options;
}

/**
 * Label for exiting View-as (back to real role).
 */
function viewAsDefaultLabel(): string
{
    if (isRealAllFather()) {
        return 'All Father (default)';
    }
    if (isRealAdministrator()) {
        return 'Administrator (default)';
    }
    return 'Default role';
}

function realUserRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function isRealAllFather(): bool
{
    return realUserRole() === ROLE_ALL_FATHER;
}

function isViewingAs(): bool
{
    return canUseViewAs() && getViewAsRole() !== null;
}

function getViewAsRole(): ?string
{
    if (!canUseViewAs()) {
        return null;
    }
    $role = $_SESSION[VIEW_AS_SESSION_KEY] ?? null;
    if ($role === null || $role === '') {
        return null;
    }
    // Allow reading even if option was filtered for current role (session may retain)
    $all = [
        ROLE_REQUESTER => true,
        ROLE_GUARD => true,
        ROLE_APPROVER => true,
        ROLE_MOTORPOOL => true,
        ROLE_CHIEF_ADMIN_FINANCE => true,
        ROLE_ADMIN => true,
        'driver' => true,
    ];
    return isset($all[$role]) ? (string) $role : null;
}

/**
 * Effective role for gates/UI (View-as when active).
 */
function effectiveUserRole(): ?string
{
    $viewAs = getViewAsRole();
    if ($viewAs !== null) {
        // Driver is not a users.role — treat as requester-level for hasRole hierarchy
        if ($viewAs === 'driver') {
            return ROLE_REQUESTER;
        }
        return $viewAs;
    }
    return realUserRole();
}

function viewAsTestDriverId(): ?int
{
    $fromEnv = (int) (getenv('VIEW_AS_TEST_DRIVER_ID') ?: 0);
    if ($fromEnv > 0) {
        $row = db()->fetch(
            "SELECT id FROM drivers WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$fromEnv]
        );
        if ($row) {
            return (int) $row->id;
        }
    }

    static $fallback = false;
    if ($fallback === false) {
        $row = db()->fetch(
            "SELECT id FROM drivers WHERE deleted_at IS NULL AND status = 'available' ORDER BY id ASC LIMIT 1"
        );
        if (!$row) {
            $row = db()->fetch(
                "SELECT id FROM drivers WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1"
            );
        }
        $fallback = $row ? (int) $row->id : null;
    }

    return $fallback;
}

/**
 * Set or clear View-as. Returns false if not allowed / invalid role.
 */
function setViewAsRole(?string $role): bool
{
    if (!canUseViewAs()) {
        return false;
    }

    if ($role === null || $role === '' || $role === 'none' || $role === ROLE_ALL_FATHER) {
        $prev = getViewAsRole();
        unset($_SESSION[VIEW_AS_SESSION_KEY]);
        if ($prev !== null && function_exists('auditLog')) {
            auditLog('view_as_exit', 'session', userId(), ['role' => $prev], null);
        }
        return true;
    }

    if (!isset(viewAsRoleOptions()[$role])) {
        return false;
    }

    if ($role === 'driver' && viewAsTestDriverId() === null) {
        return false;
    }

    $_SESSION[VIEW_AS_SESSION_KEY] = $role;
    if (function_exists('auditLog')) {
        auditLog('view_as_enter', 'session', userId(), null, ['role' => $role]);
    }
    return true;
}

function viewAsBannerLabel(): ?string
{
    $role = getViewAsRole();
    if ($role === null) {
        return null;
    }
    $labels = [
        ROLE_REQUESTER => 'Requester',
        ROLE_GUARD => 'Guard',
        ROLE_APPROVER => 'Approver',
        ROLE_MOTORPOOL => 'Motorpool Head',
        ROLE_CHIEF_ADMIN_FINANCE => 'Chief Admin & Finance',
        ROLE_ADMIN => 'Administrator',
        'driver' => 'Driver',
    ];
    return $labels[$role] ?? $role;
}

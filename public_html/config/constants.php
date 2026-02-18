<?php
/**
 * LOKA - System Constants
 * 
 * SECURITY: SITE_URL must use HTTPS in production
 * Environment variables are REQUIRED in production mode
 */

define('APP_NAME', 'LOKA Fleet Management');
define('APP_VERSION', '1.0.0');

$isProduction = (getenv('APP_ENV') === 'production');

// URL Configuration
define('APP_URL', getenv('APP_URL') ?: ($isProduction ? die('ERROR: APP_URL environment variable required in production') : '/projects/LOKA'));

$siteUrl = getenv('SITE_URL') ?: getenv('APP_URL');
if ($isProduction) {
    if (!$siteUrl) {
        die('ERROR: SITE_URL environment variable required in production');
    }
    if (strpos($siteUrl, 'https://') !== 0) {
        die('ERROR: SITE_URL must use HTTPS in production (current: ' . $siteUrl . ')');
    }
    define('SITE_URL', $siteUrl);
} else {
    define('SITE_URL', $siteUrl ?: 'http://localhost/projects/LOKA');
}

// Paths (usually don't need to change)
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('CLASSES_PATH', BASE_PATH . '/classes');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('ASSETS_PATH', APP_URL . '/assets');

// User Roles
define('ROLE_REQUESTER', 'requester');
define('ROLE_APPROVER', 'approver');
define('ROLE_MOTORPOOL', 'motorpool_head');
define('ROLE_GUARD', 'guard');
define('ROLE_ADMIN', 'admin');

define('ROLE_LEVELS', [
    ROLE_REQUESTER => 1,
    ROLE_GUARD => 1,
    ROLE_APPROVER => 3,
    ROLE_MOTORPOOL => 4,
    ROLE_ADMIN => 5
]);

// Request Status
define('STATUS_DRAFT', 'draft');
define('STATUS_PENDING', 'pending');
define('STATUS_PENDING_MOTORPOOL', 'pending_motorpool');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_REVISION', 'revision'); // Request sent back for revision
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_COMPLETED', 'completed');
define('STATUS_MODIFIED', 'modified');

// Approval Action Types
define('APPROVAL_ACTION_APPROVE', 'approve');
define('APPROVAL_ACTION_REJECT', 'reject');
define('APPROVAL_ACTION_REVISION', 'revision');

// Vehicle Status
define('VEHICLE_AVAILABLE', 'available');
define('VEHICLE_IN_USE', 'in_use');
define('VEHICLE_MAINTENANCE', 'maintenance');
define('VEHICLE_OUT_OF_SERVICE', 'out_of_service');

// Driver Status
define('DRIVER_AVAILABLE', 'available');
define('DRIVER_ON_TRIP', 'on_trip');
define('DRIVER_ON_LEAVE', 'on_leave');
define('DRIVER_UNAVAILABLE', 'unavailable');

// User Status
define('USER_ACTIVE', 'active');
define('USER_INACTIVE', 'inactive');
define('USER_SUSPENDED', 'suspended');

// Session
define('SESSION_TIMEOUT', 7200);
define('REMEMBER_ME_DAYS', 30);

// Pagination
define('ITEMS_PER_PAGE', 15);

// Date Formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE', 'M d, Y');
define('DISPLAY_DATETIME', 'M d, Y h:i A');

// Status Labels & Colors
define('STATUS_LABELS', [
    STATUS_DRAFT => ['label' => 'Draft', 'color' => 'secondary'],
    STATUS_PENDING => ['label' => 'Pending Approval', 'color' => 'warning'],
    STATUS_PENDING_MOTORPOOL => ['label' => 'Pending Motorpool', 'color' => 'info'],
    STATUS_APPROVED => ['label' => 'Approved', 'color' => 'success'],
    STATUS_REJECTED => ['label' => 'Rejected', 'color' => 'danger'],
    STATUS_REVISION => ['label' => 'For Revision', 'color' => 'orange'],
    STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'dark'],
    STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'primary'],
    STATUS_MODIFIED => ['label' => 'Modified', 'color' => 'warning']
]);

define('VEHICLE_STATUS_LABELS', [
    VEHICLE_AVAILABLE => ['label' => 'Available', 'color' => 'success'],
    VEHICLE_IN_USE => ['label' => 'In Use', 'color' => 'primary'],
    VEHICLE_MAINTENANCE => ['label' => 'Maintenance', 'color' => 'warning'],
    VEHICLE_OUT_OF_SERVICE => ['label' => 'Out of Service', 'color' => 'danger']
]);

define('DRIVER_STATUS_LABELS', [
    DRIVER_AVAILABLE => ['label' => 'Available', 'color' => 'success'],
    DRIVER_ON_TRIP => ['label' => 'On Trip', 'color' => 'primary'],
    DRIVER_ON_LEAVE => ['label' => 'On Leave', 'color' => 'warning'],
    DRIVER_UNAVAILABLE => ['label' => 'Unavailable', 'color' => 'danger']
]);

define('ROLE_LABELS', [
    ROLE_REQUESTER => ['label' => 'Requester', 'color' => 'secondary'],
    ROLE_APPROVER => ['label' => 'Approver', 'color' => 'info'],
    ROLE_MOTORPOOL => ['label' => 'Motorpool Head', 'color' => 'primary'],
    ROLE_GUARD => ['label' => 'Guard', 'color' => 'warning'],
    ROLE_ADMIN => ['label' => 'Administrator', 'color' => 'danger']
]);

// Maintenance Types
define('MAINTENANCE_TYPE_PREVENTIVE', 'preventive');
define('MAINTENANCE_TYPE_CORRECTIVE', 'corrective');
define('MAINTENANCE_TYPE_EMERGENCY', 'emergency');

// Maintenance Priorities
define('MAINTENANCE_PRIORITY_LOW', 'low');
define('MAINTENANCE_PRIORITY_MEDIUM', 'medium');
define('MAINTENANCE_PRIORITY_HIGH', 'high');
define('MAINTENANCE_PRIORITY_CRITICAL', 'critical');

// Maintenance Statuses
define('MAINTENANCE_STATUS_PENDING', 'pending');
define('MAINTENANCE_STATUS_SCHEDULED', 'scheduled');
define('MAINTENANCE_STATUS_IN_PROGRESS', 'in_progress');
define('MAINTENANCE_STATUS_COMPLETED', 'completed');
define('MAINTENANCE_STATUS_CANCELLED', 'cancelled');

define('MAINTENANCE_TYPES', [
    MAINTENANCE_TYPE_PREVENTIVE => ['label' => 'Preventive', 'icon' => 'bi-calendar-check'],
    MAINTENANCE_TYPE_CORRECTIVE => ['label' => 'Corrective', 'icon' => 'bi-wrench'],
    MAINTENANCE_TYPE_EMERGENCY => ['label' => 'Emergency', 'icon' => 'bi-exclamation-triangle']
]);

define('MAINTENANCE_PRIORITIES', [
    MAINTENANCE_PRIORITY_LOW => ['label' => 'Low', 'color' => 'secondary'],
    MAINTENANCE_PRIORITY_MEDIUM => ['label' => 'Medium', 'color' => 'info'],
    MAINTENANCE_PRIORITY_HIGH => ['label' => 'High', 'color' => 'warning'],
    MAINTENANCE_PRIORITY_CRITICAL => ['label' => 'Critical', 'color' => 'danger']
]);

define('MAINTENANCE_STATUSES', [
    MAINTENANCE_STATUS_PENDING => ['label' => 'Pending', 'color' => 'warning'],
    MAINTENANCE_STATUS_SCHEDULED => ['label' => 'Scheduled', 'color' => 'info'],
    MAINTENANCE_STATUS_IN_PROGRESS => ['label' => 'In Progress', 'color' => 'primary'],
    MAINTENANCE_STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'success'],
    MAINTENANCE_STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'secondary']
]);

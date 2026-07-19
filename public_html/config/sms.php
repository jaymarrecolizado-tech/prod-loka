<?php
/**
 * LOKA - SMS notification defaults (overridden by settings / .env)
 */

define('SMS_DEFAULT_ALLOWLIST', [
    'driver_assigned',
    'driver_requested',
    'request_fully_approved',
    'trip_fully_approved',
    'vehicle_dispatched',
    'trip_started',
    'vehicle_arrived',
    'trip_completed',
    'request_rejected',
    'request_revision',
    'trip_rejected',
    'trip_revision',
    'request_cancelled',
    'trip_cancelled_driver',
    'gas_voucher_approved',
    'gas_voucher_rejected',
]);

/** API path presets for All Father UI */
define('SMS_API_PATH_LOCAL', '/message');
define('SMS_API_PATH_PRIVATE', '/api/3rdparty/v1/messages');

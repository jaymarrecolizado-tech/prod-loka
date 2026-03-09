<?php
/**
 * LOKA - Trip Ticket Helper Functions
 *
 * Helper functions for Type 1 and Type 2 trip tickets
 */

if (!defined('BASE_PATH')) {
    exit;
}

/**
 * Generate a Type 2 ticket number
 * Format: YEAR-PLATE-MONTH+WEEK (e.g., 2026-448SQB-0301)
 *
 * @param string $plateNumber Vehicle plate number
 * @param string $weekStart Week start date (Y-m-d)
 * @return string Formatted ticket number
 */
function generateType2TicketNumber(string $plateNumber, string $weekStart): string
{
    $year = date('Y', strtotime($weekStart));
    $month = date('m', strtotime($weekStart));
    $week = getWeekOfMonth($weekStart);
    return "{$year}-{$plateNumber}-{$month}+{$week}";
}

/**
 * Get the week number within a month (1-5)
 *
 * @param string $date Date in Y-m-d format
 * @return int Week number (1-5)
 */
function getWeekOfMonth(string $date): int
{
    $day = date('d', strtotime($date));
    return (int) ceil($day / 7);
}

/**
 * Get week start and end dates for a given date
 *
 * @param string $date Date in Y-m-d format
 * @param string $firstDayOfWeek Day to start week (default Monday)
 * @return array ['start' => Y-m-d, 'end' => Y-m-d, 'week_number' => int]
 */
function getWeekDates(string $date, string $firstDayOfWeek = 'Monday'): array
{
    $timestamp = strtotime($date);
    $dayOfWeek = date('w', $timestamp); // 0=Sunday, 1=Monday, etc.

    // Adjust for first day of week
    if ($firstDayOfWeek === 'Monday') {
        $offset = ($dayOfWeek + 6) % 7; // Monday=0, Tuesday=1, etc.
    } else {
        $offset = $dayOfWeek; // Sunday=0
    }

    $weekStart = date('Y-m-d', $timestamp - ($offset * 86400));
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    $weekNumber = getWeekOfMonth($weekStart);

    return [
        'start' => $weekStart,
        'end' => $weekEnd,
        'week_number' => $weekNumber
    ];
}

/**
 * Check if a Type 2 ticket already exists for a vehicle and week
 *
 * @param int $vehicleId Vehicle ID
 * @param string $weekStart Week start date
 * @return int|null Existing ticket ID or null
 */
function getExistingType2Ticket(int $vehicleId, string $weekStart): ?int
{
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    $existing = db()->fetch(
        "SELECT id FROM trip_tickets
         WHERE ticket_type = 'type2'
           AND vehicle_id = ?
           AND week_start = ?
           AND deleted_at IS NULL",
        [$vehicleId, $weekStart]
    );

    return $existing ? (int) $existing->id : null;
}

/**
 * Get trip ticket type label
 *
 * @param string $type Type 1 or Type 2
 * @return array Label info with color, icon, etc.
 */
function getTripTicketTypeInfo(string $type): array
{
    return TRIP_TICKET_TYPES[$type] ?? TRIP_TICKET_TYPES[TRIP_TICKET_TYPE_1];
}

/**
 * Get trip ticket status label
 *
 * @param string $status Status key
 * @return array Label info with color, icon, etc.
 */
function getTripTicketStatusInfo(string $status): array
{
    return TRIP_TICKET_STATUSES[$status] ?? [
        'label' => ucfirst($status),
        'color' => 'secondary'
    ];
}

/**
 * Render a trip ticket type badge
 *
 * @param string $type Type 1 or Type 2
 * @return string HTML badge
 */
function tripTicketTypeBadge(string $type): string
{
    $info = getTripTicketTypeInfo($type);
    return sprintf(
        '<span class="badge bg-%s"><i class="bi bi-%s me-1"></i>%s</span>',
        $info['color'],
        $info['icon'],
        $info['short_label']
    );
}

/**
 * Render a trip ticket status badge
 *
 * @param string $status Status
 * @return string HTML badge
 */
function tripTicketStatusBadge(string $status): string
{
    $info = getTripTicketStatusInfo($status);
    return sprintf(
        '<span class="badge bg-%s">%s</span>',
        $info['color'],
        $info['label']
    );
}

/**
 * Fetch completed trips for a vehicle within a date range
 *
 * @param int $vehicleId Vehicle ID
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return array Array of trip objects
 */
function fetchCompletedTripsForTicket(int $vehicleId, string $startDate, string $endDate): array
{
    $sql = "SELECT r.id as request_id,
                   r.destination,
                   r.purpose,
                   r.passenger_count,
                   COALESCE(r.actual_dispatch_datetime, r.start_datetime) as start_date,
                   COALESCE(r.actual_arrival_datetime, r.end_datetime) as end_date,
                   r.mileage_start as start_mileage,
                   r.mileage_end as end_mileage,
                   r.mileage_actual as distance_traveled,
                   tt.fuel_consumed,
                   tt.fuel_cost,
                   d.user_id as driver_user_id,
                   du.name as driver_name
            FROM requests r
            LEFT JOIN trip_tickets tt ON r.id = tt.request_id AND tt.deleted_at IS NULL
            LEFT JOIN drivers d ON r.driver_id = d.id
            LEFT JOIN users du ON d.user_id = du.id
            WHERE r.vehicle_id = ?
              AND r.status = 'completed'
              AND DATE(COALESCE(r.actual_dispatch_datetime, r.start_datetime)) >= ?
              AND DATE(COALESCE(r.actual_dispatch_datetime, r.start_datetime)) <= ?
            ORDER BY COALESCE(r.actual_dispatch_datetime, r.start_datetime) ASC";

    return db()->fetchAll($sql, [$vehicleId, $startDate, $endDate]);
}

/**
 * Fetch passengers for a request
 *
 * @param int $requestId Request ID
 * @return array Array of passenger objects
 */
function fetchRequestPassengers(int $requestId): array
{
    $sql = "SELECT
            CASE
                WHEN rp.user_id IS NOT NULL THEN u.name
                ELSE rp.guest_name
            END as name,
            CASE
                WHEN rp.user_id IS NOT NULL THEN 'user'
                ELSE 'guest'
            END as type
        FROM request_passengers rp
        LEFT JOIN users u ON rp.user_id = u.id
        WHERE rp.request_id = ?";

    return db()->fetchAll($sql, [$requestId]);
}

/**
 * Build all people list (driver + passengers) for a trip
 *
 * @param string $driverName Driver name
 * @param array $passengers Array of passengers
 * @return array Array of person objects with 'name' and 'role' keys
 */
function buildTripPeopleList(string $driverName, array $passengers): array
{
    $people = [];

    if ($driverName) {
        $people[] = ['name' => $driverName, 'role' => 'Driver'];
    }

    foreach ($passengers as $p) {
        $role = ($p->type === 'guest') ? 'Guest' : 'Passenger';
        $people[] = ['name' => $p->name, 'role' => $role];
    }

    return $people;
}

/**
 * Get weeks in a month as array of week start/end dates
 *
 * @param int $year Year
 * @param int $month Month (1-12)
 * @return array Array of weeks
 */
function getWeeksInMonth(int $year, int $month): array
{
    $weeks = [];
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $lastDay = mktime(0, 0, 0, $month + 1, 0, $year);

    $current = $firstDay;
    while ($current <= $lastDay) {
        $weekData = getWeekDates(date('Y-m-d', $current));
        $weeks[] = $weekData;

        // Move to next Monday or end of month
        $current = strtotime('+1 week', strtotime($weekData['start']));
    }

    return $weeks;
}

/**
 * Check if user can approve Type 2 tickets
 * Type 2 tickets can be approved by:
 * 1. Department Approver (primary)
 * 2. Motorpool Head (if Department Approver is not available)
 *
 * @return bool True if user can approve
 */
function canApproveType2Ticket(): bool
{
    return hasRole(ROLE_APPROVER) || hasRole(ROLE_MOTORPOOL) || isAdmin();
}

/**
 * Get the approval type for the current user
 *
 * @return string 'dept_approver' or 'motorpool_head'
 */
function getApprovalTypeForUser(): string
{
    if (hasRole(ROLE_MOTORPOOL) || isAdmin()) {
        return APPROVAL_BY_MOTORPOOL_HEAD;
    }
    return APPROVAL_BY_DEPT_APPROVER;
}

/**
 * Format fuel refill data for display/editing
 *
 * @param string|null $jsonData JSON data from fuel_refill_data column
 * @return array Array of fuel refill entries
 */
function parseFuelRefillData(?string $jsonData): array
{
    if (empty($jsonData)) {
        return [];
    }

    $data = json_decode($jsonData, true);
    return is_array($data) ? $data : [];
}

/**
 * Encode fuel refill data for storage
 *
 * @param array $entries Array of fuel refill entries
 * @return string JSON encoded data
 */
function encodeFuelRefillData(array $entries): string
{
    return json_encode($entries, JSON_UNESCAPED_UNICODE);
}

/**
 * Get vehicle caretaker (driver) info
 *
 * @param int $vehicleId Vehicle ID
 * @return object|null Vehicle info or null
 */
function getVehicleCaretaker(int $vehicleId): ?object
{
    // Check if vehicle has assigned driver
    $vehicle = db()->fetch(
        "SELECT v.*, d.id as driver_id, d.user_id as driver_user_id, u.name as driver_name
         FROM vehicles v
         LEFT JOIN drivers d ON v.driver_id = d.id AND d.deleted_at IS NULL
         LEFT JOIN users u ON d.user_id = u.id
         WHERE v.id = ? AND v.deleted_at IS NULL",
        [$vehicleId]
    );

    return $vehicle;
}

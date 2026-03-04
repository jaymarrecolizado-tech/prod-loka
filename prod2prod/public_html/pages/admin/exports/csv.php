<?php
/**
 * LOKA - CSV Export Handler
 */

requireRole(ROLE_ADMIN);

$type = get('type', 'requests');
$startDate = get('start_date', date('Y-m-01'));
$endDate = get('end_date', date('Y-m-t'));
$maxRows = 10000;

$allowedTypes = ['requests', 'users', 'vehicles', 'departments', 'maintenance', 'audit_logs'];
if (!in_array($type, $allowedTypes)) {
    redirectWith('/?page=admin-reports', 'danger', 'Invalid report type.');
}

$data = [];
$filename = '';
$headers = [];

switch ($type) {
    case 'requests':
        $filename = 'vehicle_requests_' . $startDate . '_to_' . $endDate;
        $headers = ['ID', 'Created', 'Start', 'End', 'Purpose', 'Destination', 'Passenger Count', 'Status', 'Requester', 'Department', 'Vehicle', 'Driver'];
        $data = db()->fetchAll(
            "SELECT r.id, r.created_at, r.start_datetime, r.end_datetime, r.purpose, r.destination,
                    r.passenger_count, r.status, u.name as requester, d.name as department,
                    v.plate_number as vehicle, dr.name as driver
             FROM requests r
             JOIN users u ON r.user_id = u.id
             LEFT JOIN departments d ON r.department_id = d.id
             LEFT JOIN vehicles v ON r.vehicle_id = v.id
             LEFT JOIN drivers dr ON r.driver_id = dr.id
             WHERE DATE(r.created_at) BETWEEN ? AND ?
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$startDate, $endDate, $maxRows]
        );
        break;

    case 'users':
        $filename = 'users_' . date('Y-m-d');
        $headers = ['ID', 'Name', 'Email', 'Role', 'Department', 'Status', 'Created', 'Last Login'];
        $data = db()->fetchAll(
            "SELECT u.id, u.name, u.email, u.role, d.name as department, u.status, u.created_at, u.last_login
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.deleted_at IS NULL
             ORDER BY u.created_at DESC
             LIMIT ?",
            [$maxRows]
        );
        break;

    case 'vehicles':
        $filename = 'vehicles_' . date('Y-m-d');
        $headers = ['ID', 'Plate Number', 'Make', 'Model', 'Year', 'Type', 'Status', 'Mileage', 'Created'];
        $data = db()->fetchAll(
            "SELECT v.id, v.plate_number, v.make, v.model, v.year, vt.name as type, v.status, v.mileage, v.created_at
             FROM vehicles v
             LEFT JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
             WHERE v.deleted_at IS NULL
             ORDER BY v.plate_number
             LIMIT ?",
            [$maxRows]
        );
        break;

    case 'departments':
        $filename = 'departments_' . date('Y-m-d');
        $headers = ['ID', 'Name', 'Description', 'Status', 'Created'];
        $data = db()->fetchAll(
            "SELECT d.id, d.name, d.description, d.status, d.created_at
             FROM departments d
             WHERE d.deleted_at IS NULL
             ORDER BY d.name
             LIMIT ?",
            [$maxRows]
        );
        break;

    case 'maintenance':
        $filename = 'maintenance_' . $startDate . '_to_' . $endDate;
        $headers = ['ID', 'Vehicle', 'Type', 'Status', 'Scheduled Date', 'Completed At', 'Cost', 'Created'];
        $data = db()->fetchAll(
            "SELECT mr.id, v.plate_number as vehicle, mr.maintenance_type as type, mr.status,
                    mr.scheduled_date, mr.completed_at, mr.cost, mr.created_at
             FROM maintenance_requests mr
             JOIN vehicles v ON mr.vehicle_id = v.id
             WHERE DATE(mr.created_at) BETWEEN ? AND ?
             ORDER BY mr.created_at DESC
             LIMIT ?",
            [$startDate, $endDate, $maxRows]
        );
        break;

    case 'audit_logs':
        $filename = 'audit_logs_' . $startDate . '_to_' . $endDate;
        $headers = ['ID', 'Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address'];
        $data = db()->fetchAll(
            "SELECT al.id, al.created_at as timestamp, u.name as user, al.action,
                    al.entity_type, al.entity_id, al.ip_address
             FROM audit_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE DATE(al.created_at) BETWEEN ? AND ?
             ORDER BY al.created_at DESC
             LIMIT ?",
            [$startDate, $endDate, $maxRows]
        );
        break;
}

auditLog('data_export', $type, null, null, ['format' => 'csv', 'rows' => count($data)]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, "\xEF\xBB\xBF");
fputcsv($output, $headers);

foreach ($data as $row) {
    $csvRow = [];
    $rowArray = (array) $row;
    foreach ($headers as $header) {
        $key = strtolower(str_replace(' ', '_', $header));
        $csvRow[] = $rowArray[$key] ?? '';
    }
    fputcsv($output, $csvRow);
}

fclose($output);
exit;

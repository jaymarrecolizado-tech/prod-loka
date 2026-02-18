<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOKA - Setup Complete</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #0d6efd;
            margin-top: 0;
            text-align: center;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 1.2em;
        }
        .success-box {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
            font-size: 1.1em;
        }
        .stats {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #dee2e6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #0d6efd;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px 0 0;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        .icon {
            font-size: 1.5em;
            margin-right: 10px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .section h2 {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 10px 0 10px 30px;
            position: relative;
        }
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
            font-size: 1.2em;
        }
        .credentials {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #ffc107;
            margin: 20px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-card {
            background: linear-gradient(135deg, #e7f3ff 0%, #cce5ff 100%);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #b8daff;
        }
        .info-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0d6efd;
            margin: 10px 0;
        }
        .info-card .label {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🚗</span>LOKA</h1>
        <p class="subtitle">Fleet Management System - Setup Complete!</p>

        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        try {
            require_once __DIR__ . '/config/database.php';

            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_NAME,
                    DB_CHARSET
                );
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]);

                echo '<div class="success-box">✓✓✓ Database Successfully Populated! ✓✓✓</div>';

                // Get statistics
                $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $departments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
                $vehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
                $drivers = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
                $requests = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
                $approvals = $pdo->query("SELECT COUNT(*) FROM approvals")->fetchColumn();
                $notifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

                echo '<div class="info-grid">';
                echo '<div class="info-card">';
                echo '<div class="number">' . number_format($users) . '</div>';
                echo '<div class="label">Users</div>';
                echo '</div>';
                echo '<div class="info-card">';
                echo '<div class="number">' . number_format($vehicles) . '</div>';
                echo '<div class="label">Vehicles</div>';
                echo '</div>';
                echo '<div class="info-card">';
                echo '<div class="number">' . number_format($drivers) . '</div>';
                echo '<div class="label">Drivers</div>';
                echo '</div>';
                echo '<div class="info-card">';
                echo '<div class="number">' . number_format($requests) . '</div>';
                echo '<div class="label">Requests</div>';
                echo '</div>';
                echo '</div>';

                echo '<div class="section">';
                echo '<h2>📊 Database Details</h2>';
                echo '<table>';
                echo '<tr><th>Table</th><th>Records</th></tr>';

                $tables = [
                    'users' => 'Users',
                    'departments' => 'Departments',
                    'vehicles' => 'Vehicles',
                    'drivers' => 'Drivers',
                    'vehicle_types' => 'Vehicle Types',
                    'requests' => 'Requests',
                    'approvals' => 'Approvals',
                    'approval_workflow' => 'Approval Workflow',
                    'notifications' => 'Notifications',
                    'audit_logs' => 'Audit Logs',
                    'email_queue' => 'Email Queue',
                    'request_passengers' => 'Request Passengers',
                    'fuel_records' => 'Fuel Records',
                    'maintenance' => 'Maintenance Records'
                ];

                foreach ($tables as $table => $label) {
                    try {
                        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                        echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . number_format($count) . '</td></tr>';
                    } catch (PDOException $e) {
                        echo '<tr><td>' . htmlspecialchars($label) . '</td><td style="color:#dc3545;">Not Available</td></tr>';
                    }
                }

                echo '</table>';
                echo '</div>';

            } catch (PDOException $e) {
                echo '<div class="credentials" style="background: #f8d7da; border-color: #dc3545;">';
                echo '✗ Database Error: ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="credentials" style="background: #f8d7da; border-color: #dc3545;">';
            echo '✗ Error: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>

        <div class="section">
            <h2>🎯 Key Features</h2>
            <ul class="feature-list">
                <li>Two-Stage Approval Workflow (Department → Motorpool)</li>
                <li>Role-Based Access Control (Requester, Approver, Motorpool Head, Admin)</li>
                <li>Vehicle Management with Availability Tracking</li>
                <li>Driver Management with License Tracking</li>
                <li>Request Management with Passenger Support</li>
                <li>In-App and Email Notifications</li>
                <li>Comprehensive Audit Logging</li>
                <li>Usage Reports and Statistics</li>
            </ul>
        </div>

        <div class="credentials">
            <h3>🔐 Default Login Credentials</h3>
            <table style="margin-top: 15px;">
                <tr>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Password</th>
                </tr>
                <tr>
                    <td><strong>Admin</strong></td>
                    <td>admin@fleet.local</td>
                    <td><code>password123</code></td>
                </tr>
                <tr>
                    <td><strong>Motorpool Head</strong></td>
                    <td>jay.galil619@gmail.com</td>
                    <td><code>password123</code></td>
                </tr>
                <tr>
                    <td><strong>Approver</strong></td>
                    <td>shawntibo94@gmail.com</td>
                    <td><code>password123</code></td>
                </tr>
                <tr>
                    <td><strong>Requester</strong></td>
                    <td>requester@fleet.local</td>
                    <td><code>password123</code></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>🚀 Get Started</h2>
            <p>Your LOKA Fleet Management System is ready to use! Here's what you can do:</p>
            <ol>
                <li><strong>Log in</strong> using any of the credentials above</li>
                <li><strong>Create requests</strong> as a Requester for vehicle trips</li>
                <li><strong>Approve requests</strong> as an Approver or Motorpool Head</li>
                <li><strong>Manage vehicles</strong> and <strong>drivers</strong> as Motorpool Head or Admin</li>
                <li><strong>View reports</strong> to analyze fleet utilization</li>
            </ol>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="index.php" class="btn">
                <span class="icon">🎯</span>Go to LOKA Dashboard
            </a>
            <a href="setup_database.php" class="btn btn-secondary">
                <span class="icon">🔄</span>Refresh Status
            </a>
        </div>

        <div style="text-align: center; color: #6c757d; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <p><strong>LOKA Fleet Management System</strong></p>
            <p>Lightweight Operational Kiosk Application</p>
            <p>Version 1.0.0 | PHP 8.1+ | MySQL 8.0+ | Bootstrap 5.3</p>
        </div>

    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOKA - Database Import</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0d6efd;
            margin-top: 0;
        }
        .status {
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #0d6efd;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px 0 0;
        }
        .btn:hover {
            background: #0b5ed7;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 LOKA Fleet Management System</h1>
        <h2>Database Setup & Information</h2>

        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        try {
            // Load configuration
            require_once __DIR__ . '/config/database.php';

            echo '<div class="status info">✓ Configuration loaded</div>';
            echo '<div class="stats">';
            echo '<strong>Database Configuration:</strong><br>';
            echo 'Host: ' . htmlspecialchars(DB_HOST) . '<br>';
            echo 'Database: ' . htmlspecialchars(DB_NAME) . '<br>';
            echo 'User: ' . htmlspecialchars(DB_USER) . '<br>';
            echo '</div>';

            // Try to connect
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

                echo '<div class="status success">✓ Successfully connected to database!</div>';

                // Get table list
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                if (empty($tables)) {
                    echo '<div class="status error">⚠ Database exists but has no tables!</div>';
                    echo '<p><a href="?action=import" class="btn">Import Database</a></p>';
                } else {
                    echo '<div class="status success">✓ Found ' . count($tables) . ' tables in database</div>';

                    echo '<div class="stats">';
                    echo '<h3>Database Statistics:</h3>';
                    echo '<table>';
                    echo '<tr><th>Table</th><th>Records</th></tr>';

                    foreach ($tables as $table) {
                        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                        echo '<tr><td>' . htmlspecialchars($table) . '</td><td>' . number_format($count) . '</td></tr>';
                    }

                    echo '</table>';
                    echo '</div>';

                    // Check for key tables
                    $requiredTables = ['users', 'departments', 'vehicles', 'drivers', 'requests'];
                    $missingTables = array_diff($requiredTables, $tables);

                    if (!empty($missingTables)) {
                        echo '<div class="status error">⚠ Missing required tables: ' . implode(', ', $missingTables) . '</div>';
                        echo '<p><a href="?action=import" class="btn">Import Database</a></p>';
                    }
                }

                // Display default credentials
                echo '<div class="stats">';
                echo '<h3>Default Login Credentials:</h3>';
                echo '<table>';
                echo '<tr><th>Role</th><th>Email</th><th>Password</th></tr>';
                echo '<tr><td>Admin</td><td>admin@fleet.local</td><td>password123</td></tr>';
                echo '<tr><td>Motorpool Head</td><td>jay.galil619@gmail.com</td><td>password123</td></tr>';
                echo '<tr><td>Approver</td><td>shawntibo94@gmail.com</td><td>password123</td></tr>';
                echo '<tr><td>Requester</td><td>requester@fleet.local</td><td>password123</td></tr>';
                echo '</table>';
                echo '</div>';

            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    echo '<div class="status error">⚠ Database "' . htmlspecialchars(DB_NAME) . '" does not exist!</div>';
                    echo '<p><strong>To create the database:</strong></p>';
                    echo '<pre>CREATE DATABASE fleet_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</pre>';
                    echo '<p><strong>Then click the button below to import the database:</strong></p>';
                    echo '<a href="?action=import" class="btn">Import Database</a>';
                } else {
                    throw $e;
                }
            }

        } catch (Exception $e) {
            echo '<div class="status error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        // Handle import action
        if (isset($_GET['action']) && $_GET['action'] === 'import') {
            echo '<hr>';
            echo '<h2>Importing Database...</h2>';

            try {
                $sqlFile = __DIR__ . '/waffles/deployment/fleet_management_final.sql';

                if (!file_exists($sqlFile)) {
                    throw new Exception("SQL file not found: " . $sqlFile);
                }

                echo '<div class="status info">✓ Reading SQL file...</div>';

                $sql = file_get_contents($sqlFile);
                $fileSize = filesize($sqlFile);
                echo '<div class="status info">✓ File size: ' . round($fileSize / 1024, 2) . ' KB</div>';

                // Connect without database first to create it
                $dsn = sprintf("mysql:host=%s;charset=%s", DB_HOST, DB_CHARSET);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                echo '<div class="status info">✓ Connected to MySQL server</div>';

                // Drop and recreate database
                $pdo->exec("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
                $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `" . DB_NAME . "`");

                echo '<div class="status info">✓ Database created</div>';

                // Process SQL
                $sql = preg_replace('/^CREATE DATABASE.*?;/m', '', $sql);
                $sql = preg_replace('/^USE.*?;/m', '', $sql);

                $statements = [];
                $buffer = '';
                $lines = explode("\n", $sql);

                foreach ($lines as $line) {
                    if (preg_match('/^(--|#|\/\*|\s*$)/', $line)) {
                        continue;
                    }
                    $buffer .= $line . "\n";
                    if (preg_match('/;\s*$/', trim($line))) {
                        $statements[] = $buffer;
                        $buffer = '';
                    }
                }

                echo '<div class="status info">✓ Executing ' . count($statements) . ' SQL statements...</div>';

                $successCount = 0;
                $errorCount = 0;
                $errors = [];

                foreach ($statements as $index => $statement) {
                    try {
                        $trimmed = trim($statement);
                        if (!empty($trimmed)) {
                            $pdo->exec($trimmed);
                            $successCount++;
                        }
                    } catch (PDOException $e) {
                        $errorCount++;
                        $errors[] = "Statement #" . ($index + 1) . ": " . $e->getMessage();
                    }
                }

                if ($errorCount === 0) {
                    echo '<div class="status success">✓ Database imported successfully! ' . $successCount . ' statements executed.</div>';
                    echo '<p><a href="setup_database.php" class="btn">Refresh to View Statistics</a></p>';
                } else {
                    echo '<div class="status error">⚠ Import completed with ' . $errorCount . ' errors (' . $successCount . ' successful)</div>';
                    if (!empty($errors)) {
                        echo '<h3>Errors:</h3>';
                        echo '<pre>' . htmlspecialchars(implode("\n", array_slice($errors, 0, 10))) . '</pre>';
                    }
                }

            } catch (Exception $e) {
                echo '<div class="status error">✗ Import failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>

        <hr>
        <h3>What is LOKA?</h3>
        <p><strong>LOKA</strong> (Lightweight Operational Kiosk Application) is a comprehensive Vehicle Fleet Management & Scheduling System built with PHP 8.1+ and MySQL 8.0+.</p>

        <h4>Key Features:</h4>
        <ul>
            <li><strong>Two-Stage Approval Workflow:</strong> Department approval → Motorpool approval</li>
            <li><strong>Role-Based Access Control:</strong> Requester, Approver, Motorpool Head, Administrator</li>
            <li><strong>Vehicle Management:</strong> Track vehicles, types, availability, and maintenance</li>
            <li><strong>Driver Management:</strong> Manage drivers, licenses, and schedules</li>
            <li><strong>Request Management:</strong> Create, track, and cancel vehicle requests</li>
            <li><strong>Notifications:</strong> In-app and email notifications for approvals</li>
            <li><strong>Audit Logging:</strong> Track all system activities</li>
            <li><strong>Reports:</strong> Vehicle utilization, department usage, approval statistics</li>
        </ul>

        <h4>User Roles:</h4>
        <ul>
            <li><strong>Requester:</strong> Create vehicle requests, view own requests</li>
            <li><strong>Approver:</strong> Approve/reject requests from their department</li>
            <li><strong>Motorpool Head:</strong> Final approval, assign vehicles and drivers</li>
            <li><strong>Administrator:</strong> Full system access, user management, settings</li>
        </ul>

        <p>
            <a href="index.php" class="btn">Go to LOKA Dashboard</a>
            <a href="setup_database.php" class="btn">Refresh This Page</a>
        </p>

    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOKA Fleet Management - Welcome</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .container {
            background: white;
            max-width: 800px;
            width: 100%;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .logo {
            font-size: 4em;
            margin-bottom: 20px;
        }
        h1 {
            color: #0d6efd;
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .subtitle {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 1.2em;
        }
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 5px solid #2196f3;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: left;
        }
        .info-box strong {
            color: #0d47a1;
        }
        .url-box {
            background: #f8f9fa;
            border: 2px solid #0d6efd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 1.1em;
            font-weight: bold;
            color: #0d6efd;
        }
        .btn {
            display: inline-block;
            padding: 15px 35px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            margin: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.5);
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .status-item .label {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .status-item .value {
            font-size: 1.5em;
            font-weight: bold;
            color: #0d6efd;
        }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚗</div>
        <h1>LOKA Fleet Management</h1>
        <p class="subtitle">Lightweight Operational Kiosk Application</p>

        <?php
        // Check database connection
        $dbConnected = false;
        $dbTables = 0;

        try {
            require_once __DIR__ . '/config/database.php';
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $dbConnected = true;

            // Count tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $dbTables = count($tables);
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
        ?>

        <div class="status-grid">
            <div class="status-item">
                <div class="label">Database</div>
                <div class="value <?php echo $dbConnected ? 'success' : 'warning'; ?>">
                    <?php echo $dbConnected ? '✓ Connected' : '✗ Failed'; ?>
                </div>
            </div>
            <div class="status-item">
                <div class="label">Tables</div>
                <div class="value"><?php echo $dbTables; ?></div>
            </div>
        </div>

        <?php if (!$dbConnected): ?>
        <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <strong>⚠ Database Connection Failed</strong><br>
            <?php echo htmlspecialchars($dbError ?? 'Unknown error'); ?><br><br>
            <a href="setup_database.php" class="btn">Setup Database</a>
        </div>
        <?php elseif ($dbTables < 10): ?>
        <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <strong>⚠ Database Not Fully Populated</strong><br>
            Only <?php echo $dbTables; ?> tables found. Expected 15+ tables.<br><br>
            <a href="setup_database.php?action=import" class="btn">Import Database</a>
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>✓✓✓ System Ready!</strong><br>
            Database is connected and fully populated. You can now access the application.
        </div>
        <?php endif; ?>

        <h3>🎯 Access Your Application</h3>

        <div class="info-box">
            <strong>Correct URL:</strong><br>
            <div class="url-box">http://localhost/projects/LOKA/</div>
            <p style="margin-top: 10px;">
                <strong>IMPORTANT:</strong> Use the URL above. Do not use <code>http://localhost/LOKA/</code>
            </p>
        </div>

        <div style="margin: 30px 0;">
            <a href="/projects/LOKA/" class="btn">
                🚀 Go to Dashboard
            </a>
            <a href="/projects/LOKA/?page=login" class="btn btn-secondary">
                🔐 Go to Login
            </a>
        </div>

        <h3>📋 Quick Links</h3>
        <div style="text-align: left; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>📊 Dashboard:</strong>
               <a href="/projects/LOKA/?page=dashboard" target="_blank">http://localhost/projects/LOKA/?page=dashboard</a>
            </p>
            <p><strong>📝 Login Page:</strong>
               <a href="/projects/LOKA/?page=login" target="_blank">http://localhost/projects/LOKA/?page=login</a>
            </p>
            <p><strong>🚗 Vehicle Requests:</strong>
               <a href="/projects/LOKA/?page=requests" target="_blank">http://localhost/projects/LOKA/?page=requests</a>
            </p>
            <p><strong>✅ Approvals:</strong>
               <a href="/projects/LOKA/?page=approvals" target="_blank">http://localhost/projects/LOKA/?page=approvals</a>
            </p>
            <p><strong>📋 Setup Status:</strong>
               <a href="/projects/LOKA/setup_complete.php" target="_blank">http://localhost/projects/LOKA/setup_complete.php</a>
            </p>
            <p><strong>🔧 Diagnostic Page:</strong>
               <a href="/projects/LOKA/diagnostic.php" target="_blank">http://localhost/projects/LOKA/diagnostic.php</a>
            </p>
        </div>

        <?php if ($dbConnected && $dbTables >= 10): ?>
        <h3>🔐 Default Login Credentials</h3>
        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; text-align: left; margin: 20px 0;">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none;"><strong>Admin:</strong></td>
                    <td style="border: none;">
                        admin@fleet.local / <code style="background: #fff; border: 1px solid #ddd; padding: 2px 8px;">password123</code>
                    </td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Motorpool:</strong></td>
                    <td style="border: none;">
                        jay.galil619@gmail.com / <code style="background: #fff; border: 1px solid #ddd; padding: 2px 8px;">password123</code>
                    </td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Approver:</strong></td>
                    <td style="border: none;">
                        shawntibo94@gmail.com / <code style="background: #fff; border: 1px solid #ddd; padding: 2px 8px;">password123</code>
                    </td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Requester:</strong></td>
                    <td style="border: none;">
                        requester@fleet.local / <code style="background: #fff; border: 1px solid #ddd; padding: 2px 8px;">password123</code>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <h3>📚 What is LOKA?</h3>
        <div style="text-align: left; background: #e8f5e9; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50; margin: 20px 0;">
            <ul style="line-height: 2;">
                <li><strong>Vehicle Fleet Management</strong> - Track vehicles, drivers, and availability</li>
                <li><strong>Request Management</strong> - Create and track vehicle requests</li>
                <li><strong>Two-Stage Approval</strong> - Department → Motorpool workflow</li>
                <li><strong>Role-Based Access</strong> - Requester, Approver, Motorpool, Admin</li>
                <li><strong>Notifications</strong> - In-app and email alerts</li>
                <li><strong>Audit Trail</strong> - Complete activity logging</li>
                <li><strong>Reports</strong> - Utilization and usage analytics</li>
            </ul>
        </div>

        <footer>
            <p><strong>LOKA Fleet Management System v1.0.0</strong></p>
            <p>PHP 8.1+ | MySQL 8.0+ | Bootstrap 5.3</p>
            <p style="margin-top: 10px;">
                <a href="/projects/LOKA/" style="color: #0d6efd;">Go to Dashboard →</a>
            </p>
        </footer>
    </div>
</body>
</html>

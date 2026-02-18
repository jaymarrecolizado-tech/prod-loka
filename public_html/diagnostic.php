<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOKA - URL Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        h1 { color: #0d6efd; }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #0d6efd;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
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
        }
        code {
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px 0 0;
            font-weight: bold;
        }
        .btn:hover { background: #0b5ed7; }
        .btn-large {
            padding: 20px 40px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 LOKA Fleet Management</h1>
        <h2>URL Diagnostic & Setup Information</h2>

        <?php
        echo '<div class="info">';
        echo '<strong>Current File Path:</strong><br>';
        echo '<code>' . __DIR__ . '</code><br><br>';
        echo '<strong>Server Document Root:</strong><br>';
        echo '<code>' . $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' . '</code><br><br>';
        echo '<strong>Request URI:</strong><br>';
        echo '<code>' . ($_SERVER['REQUEST_URI'] ?? 'Not set') . '</code><br><br>';
        echo '<strong>HTTP Host:</strong><br>';
        echo '<code>' . ($_SERVER['HTTP_HOST'] ?? 'Not set') . '</code>';
        echo '</div>';

        echo '<div class="warning">';
        echo '<h3>⚠️ You are seeing this page because:</h3>';
        echo '<p>The URL you accessed may not match the configured path.</p>';
        echo '</div>';

        echo '<h3>📊 Project Information:</h3>';
        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th></tr>';

        try {
            require_once __DIR__ . '/config/constants.php';
            echo '<tr><td>APP_NAME</td><td>' . APP_NAME . '</td></tr>';
            echo '<tr><td>APP_VERSION</td><td>' . APP_VERSION . '</td></tr>';
            echo '<tr><td>APP_URL</td><td><code>' . APP_URL . '</code></td></tr>';
            echo '<tr><td>SITE_URL</td><td><code>' . SITE_URL . '</code></td></tr>';
            echo '<tr><td>BASE_PATH</td><td><code>' . BASE_PATH . '</code></td></tr>';
        } catch (Exception $e) {
            echo '<tr><td colspan="2" style="color:red;">Error loading config: ' . $e->getMessage() . '</td></tr>';
        }

        echo '</table>';

        echo '<div class="success">';
        echo '<h3>✅ Correct URLs to Access LOKA:</h3>';
        echo '<p><strong>Try these URLs in your browser:</strong></p>';
        echo '<ul>';
        echo '<li><a href="/projects/LOKA/" target="_blank"><strong>http://localhost/projects/LOKA/</strong></a> (Main Dashboard)</li>';
        echo '<li><a href="/projects/LOKA/?page=login" target="_blank"><strong>http://localhost/projects/LOKA/?page=login</strong></a> (Login Page)</li>';
        echo '<li><a href="/projects/LOKA/setup_complete.php" target="_blank"><strong>http://localhost/projects/LOKA/setup_complete.php</strong></a> (Setup Status)</li>';
        echo '</ul>';
        echo '</div>';

        echo '<h3>🔧 If you still get 404 errors:</h3>';
        echo '<ol>';
        echo '<li>Check if XAMPP Apache is running (visit <code>http://localhost</code>)</li>';
        echo '<li>Verify project is in <code>C:\xampp\htdocs\projects\LOKA</code></li>';
        echo '<li>Make sure <code>.htaccess</code> file exists in project root</li>';
        echo '<li>Check Apache error logs at <code>C:\xampp\apache\logs\error.log</code></li>';
        echo '</ol>';

        echo '<h3>📁 File Structure Check:</h3>';
        echo '<ul>';
        $files = [
            'index.php' => 'Main router file',
            'config/constants.php' => 'System configuration',
            'config/database.php' => 'Database config',
            'pages/dashboard/index.php' => 'Dashboard page',
            'pages/auth/login.php' => 'Login page',
            'classes/Database.php' => 'Database class',
            'includes/functions.php' => 'Helper functions'
        ];

        foreach ($files as $file => $desc) {
            $path = __DIR__ . '/' . $file;
            $exists = file_exists($path);
            $status = $exists ? '✅' : '❌';
            echo "<li>$status <code>$file</code> - $desc</li>";
        }
        echo '</ul>';

        echo '<div style="text-align: center; margin: 30px 0;">';
        echo '<a href="/projects/LOKA/" class="btn btn-large">Go to LOKA Dashboard →</a>';
        echo '<br><br>';
        echo '<a href="/projects/LOKA/setup_complete.php" class="btn">View Setup Status</a>';
        echo '</div>';
        ?>

        <hr>
        <h3>📚 Quick Reference:</h3>
        <table>
            <tr>
                <th>Page</th>
                <th>URL</th>
            </tr>
            <tr>
                <td>Login</td>
                <td><a href="/projects/LOKA/?page=login" target="_blank">/projects/LOKA/?page=login</a></td>
            </tr>
            <tr>
                <td>Dashboard</td>
                <td><a href="/projects/LOKA/?page=dashboard" target="_blank">/projects/LOKA/?page=dashboard</a></td>
            </tr>
            <tr>
                <td>Requests</td>
                <td><a href="/projects/LOKA/?page=requests" target="_blank">/projects/LOKA/?page=requests</a></td>
            </tr>
            <tr>
                <td>Approvals</td>
                <td><a href="/projects/LOKA/?page=approvals" target="_blank">/projects/LOKA/?page=approvals</a></td>
            </tr>
        </table>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <p><strong>LOKA Fleet Management System v1.0.0</strong></p>
        </div>
    </div>
</body>
</html>

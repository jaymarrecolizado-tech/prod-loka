<?php
/**
 * LOKA - Database Connection Diagnostic & Fix
 * Upload this to your VPS root and access via browser to diagnose
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>LOKA Database Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0d6efd; margin-top: 0; }
        .status { padding: 15px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 0 0; border: none; cursor: pointer; }
        .btn:hover { background: #0b5ed7; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0d6efd; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 LOKA Database Diagnostic Tool</h1>
        
        <?php
        $errors = [];
        $warnings = [];
        $success = [];
        
        // Test 1: Check if .env file exists
        echo "<h2>1. Environment File Check</h2>";
        $envPaths = [
            __DIR__ . '/.env',
            __DIR__ . '/public_html/.env',
            __DIR__ . '/../.env',
        ];
        $envFound = false;
        foreach ($envPaths as $path) {
            if (file_exists($path)) {
                $success[] = ".env file found at: " . $path;
                $envFound = true;
                $envPath = $path;
                break;
            }
        }
        if (!$envFound) {
            $errors[] = ".env file NOT found! Checked locations:<br>" . implode("<br>", $envPaths);
        } else {
            echo "<div class='status success'>✓ .env file found at: <code>$envPath</code></div>";
            
            // Check if .env is readable
            if (is_readable($envPath)) {
                echo "<div class='status success'>✓ .env file is readable</div>";
            } else {
                $errors[] = ".env file exists but is NOT readable. Check permissions (should be 644 or 600)";
            }
        }
        
        // Test 2: Check PHP extensions
        echo "<h2>2. PHP Extensions Check</h2>";
        $requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'session'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                echo "<div class='status success'>✓ $ext extension loaded</div>";
            } else {
                $errors[] = "✗ $ext extension is NOT loaded!";
            }
        }
        
        // Test 3: Try to load config
        echo "<h2>3. Database Configuration</h2>";
        $dbConfig = [];
        if ($envFound) {
            $envContent = file_get_contents($envPath);
            preg_match('/DB_HOST=(.+)/', $envContent, $matches);
            $dbConfig['host'] = isset($matches[1]) ? trim($matches[1]) : 'not set';
            preg_match('/DB_DATABASE=(.+)/', $envContent, $matches);
            $dbConfig['database'] = isset($matches[1]) ? trim($matches[1]) : 'not set';
            preg_match('/DB_USERNAME=(.+)/', $envContent, $matches);
            $dbConfig['username'] = isset($matches[1]) ? trim($matches[1]) : 'not set';
            preg_match('/DB_PASSWORD=(.+)/', $envContent, $matches);
            $dbConfig['password'] = isset($matches[1]) ? trim($matches[1]) : 'not set';
            
            echo "<table>";
            echo "<tr><th>Setting</th><th>Value</th></tr>";
            echo "<tr><td>DB_HOST</td><td><code>{$dbConfig['host']}</code></td></tr>";
            echo "<tr><td>DB_DATABASE</td><td><code>{$dbConfig['database']}</code></td></tr>";
            echo "<tr><td>DB_USERNAME</td><td><code>{$dbConfig['username']}</code></td></tr>";
            echo "<tr><td>DB_PASSWORD</td><td><code>" . str_repeat('*', strlen($dbConfig['password'])) . "</code></td></tr>";
            echo "</table>";
        }
        
        // Test 4: Try database connection
        echo "<h2>4. Database Connection Test</h2>";
        if ($envFound && !empty($dbConfig['host'])) {
            try {
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]);
                echo "<div class='status success'>✓ Database connection SUCCESSFUL!</div>";
                
                // Test if tables exist
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (count($tables) > 0) {
                    echo "<div class='status success'>✓ Database has " . count($tables) . " tables</div>";
                    echo "<p>Tables found: " . implode(", ", array_slice($tables, 0, 10)) . (count($tables) > 10 ? "..." : "") . "</p>";
                } else {
                    $warnings[] = "Database exists but has NO tables! Run the schema SQL file.";
                }
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'Unknown database') !== false) {
                    $errors[] = "Database '<code>{$dbConfig['database']}</code>' does not exist!";
                    echo "<div class='status error'>✗ Database does not exist!</div>";
                    echo "<p><strong>Fix:</strong> Create the database using the SQL below:</p>";
                    echo "<pre>CREATE DATABASE {$dbConfig['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '{$dbConfig['username']}'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON {$dbConfig['database']}.* TO '{$dbConfig['username']}'@'localhost';
FLUSH PRIVILEGES;</pre>";
                } elseif (strpos($errorMsg, 'Access denied') !== false) {
                    $errors[] = "Access denied for user '<code>{$dbConfig['username']}</code>'!";
                    echo "<div class='status error'>✗ Access denied! Check username/password.</div>";
                } elseif (strpos($errorMsg, 'Connection refused') !== false || strpos($errorMsg, 'No such host') !== false) {
                    $errors[] = "Cannot connect to MySQL host '<code>{$dbConfig['host']}</code>'!";
                    echo "<div class='status error'>✗ Cannot connect to MySQL server!</div>";
                } else {
                    $errors[] = "Database error: " . $errorMsg;
                    echo "<div class='status error'>✗ Error: " . htmlspecialchars($errorMsg) . "</div>";
                }
            }
        } else {
            $errors[] = "Cannot test database connection - .env file not found or invalid";
        }
        
        // Test 5: Check file permissions
        echo "<h2>5. File Permissions Check</h2>";
        $cacheDir = __DIR__ . '/cache';
        $logsDir = __DIR__ . '/logs';
        
        if (is_dir($cacheDir)) {
            if (is_writable($cacheDir)) {
                echo "<div class='status success'>✓ cache/ directory is writable</div>";
            } else {
                $warnings[] = "cache/ directory is NOT writable! Run: <code>chmod 775 $cacheDir</code>";
            }
        } else {
            $warnings[] = "cache/ directory does not exist!";
        }
        
        if (is_dir($logsDir)) {
            if (is_writable($logsDir)) {
                echo "<div class='status success'>✓ logs/ directory is writable</div>";
            } else {
                $warnings[] = "logs/ directory is NOT writable! Run: <code>chmod 775 $logsDir</code>";
            }
        } else {
            $warnings[] = "logs/ directory does not exist!";
        }
        
        // Summary
        echo "<h2>Summary</h2>";
        if (count($errors) === 0 && count($warnings) === 0) {
            echo "<div class='status success'><strong>✓ All checks passed!</strong> Your database is properly configured.</div>";
            echo "<a href='/' class='btn'>Go to Application</a>";
        } else {
            if (count($errors) > 0) {
                echo "<div class='status error'><strong>Errors found:</strong><br>" . implode("<br>", $errors) . "</div>";
            }
            if (count($warnings) > 0) {
                echo "<div class='status warning'><strong>Warnings:</strong><br>" . implode("<br>", $warnings) . "</div>";
            }
        }
        ?>
        
        <h2>Quick Fix Commands</h2>
        <p>If you're having issues, run these commands on your VPS:</p>
        <pre># 1. Set proper permissions
chmod 600 /var/www/lokafleet/.env
chmod 755 /var/www/lokafleet
chmod -R 775 /var/www/lokafleet/cache
chmod -R 775 /var/www/lokafleet/logs

# 2. Create database manually
mysql -u root -p
CREATE DATABASE lokafleet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'loka_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON lokafleet.* TO 'loka_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 3. Import database
mysql -u loka_user -p lokafleet &lt; /var/www/lokafleet/migrations/consolidated_migration.sql

# 4. Edit .env file
nano /var/www/lokafleet/.env</pre>
        
        <p style="margin-top: 30px; text-align: center;">
            <a href="javascript:location.reload()" class="btn">🔄 Re-run Diagnostic</a>
        </p>
    </div>
</body>
</html>

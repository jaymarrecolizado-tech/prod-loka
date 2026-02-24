<?php
/**
 * LOKA Fleet Management - Production Readiness Verification
 *
 * Run this script after deployment to verify the system is properly configured.
 * Access: https://yourdomain.com/verify-production.php
 *
 * IMPORTANT: Delete this file after verification!
 */

// Set content type
header('Content-Type: text/plain; charset=utf-8');

echo "=== LOKA Fleet Management - Production Verification ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Track results
$passed = [];
$failed = [];
$warnings = [];

// Helper function to record result
function record($category, $check, $status, $message = '') {
    global $passed, $failed, $warnings;

    $icon = $status === 'pass' ? '✅' : ($status === 'fail' ? '❌' : '⚠️');
    echo "$icon $category: $check";

    if ($message) {
        echo " - $message";
    }

    echo "\n";

    if ($status === 'pass') {
        $passed[] = "$category: $check";
    } elseif ($status === 'fail') {
        $failed[] = "$category: $check";
    } else {
        $warnings[] = "$category: $check";
    }
}

// =============================================================================
// 1. PHP VERSION & EXTENSIONS
// =============================================================================

echo "\n--- PHP Environment ---\n";

$phpVersion = phpversion();
record('PHP', 'Version', version_compare($phpVersion, '8.3', '>=') ? 'pass' : 'fail', $phpVersion);

$requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'json', 'gd', 'zip'];
foreach ($requiredExtensions as $ext) {
    record('PHP Extension', $ext, extension_loaded($ext) ? 'pass' : 'fail');
}

// =============================================================================
// 2. FILE STRUCTURE
// =============================================================================

echo "\n--- File Structure ---\n";

$criticalFiles = [
    'config/bootstrap.php',
    'config/database.php',
    'config/constants.php',
    'config/session.php',
    'classes/Database.php',
    'classes/Auth.php',
    'classes/Security.php',
    'classes/Cache.php',
    'index.php',
    '.htaccess',
    '.env',
    'run-migrations.php'
];

foreach ($criticalFiles as $file) {
    record('File', $file, file_exists(__DIR__ . '/' . $file) ? 'pass' : 'fail');
}

// =============================================================================
// 3. DIRECTORY PERMISSIONS
// =============================================================================

echo "\n--- Directory Permissions ---\n";

$writableDirs = ['logs', 'logs/sessions', 'cache', 'cache/data'];
foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        record('Directory', $dir, 'fail', 'does not exist');
    } elseif (!is_writable($path)) {
        record('Directory', $dir, 'fail', 'not writable');
    } else {
        record('Directory', $dir, 'pass');
    }
}

// =============================================================================
// 4. ENVIRONMENT CONFIGURATION
// =============================================================================

echo "\n--- Environment Configuration ---\n";

if (file_exists(__DIR__ . '/.env')) {
    record('Config', '.env file', 'pass', 'exists');

    $envContent = file_get_contents(__DIR__ . '/.env');
    $envVars = [
        'APP_ENV' => '/^APP_ENV=(.*)$/m',
        'APP_URL' => '/^APP_URL=(.*)$/m',
        'DB_HOST' => '/^DB_HOST=(.*)$/m',
        'DB_DATABASE' => '/^DB_DATABASE=(.*)$/m',
        'DB_USERNAME' => '/^DB_USERNAME=(.*)$/m'
    ];

    foreach ($envVars as $var => $pattern) {
        if (preg_match($pattern, $envContent, $matches)) {
            $value = trim($matches[1]);
            $isEmpty = empty($value) || $value === 'CHANGE_THIS';
            record('Config', "$var set", $isEmpty ? 'fail' : 'pass', $isEmpty ? 'not set' : 'set');
        } else {
            record('Config', "$var set", 'fail', 'missing');
        }
    }

    // Check production mode
    if (preg_match('/^APP_ENV=(.*)$/m', $envContent, $matches)) {
        $env = trim($matches[1]);
        record('Config', 'Production mode', $env === 'production' ? 'pass' : 'warning', $env);
    }

    // Check debug mode
    if (preg_match('/^APP_DEBUG=(.*)$/m', $envContent, $matches)) {
        $debug = trim($matches[1]);
        $isDebug = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
        record('Config', 'Debug mode', !$isDebug ? 'pass' : 'warning', $isDebug ? 'enabled' : 'disabled');
    }
} else {
    record('Config', '.env file', 'fail', 'missing');
}

// =============================================================================
// 5. DATABASE CONNECTION
// =============================================================================

echo "\n--- Database Connection ---\n";

try {
    require_once __DIR__ . '/config/constants.php';

    // Try to load database config
    if (file_exists(__DIR__ . '/config/database.php')) {
        include_once __DIR__ . '/config/database.php';

        // Try mysqli connection
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($mysqli->connect_error) {
            record('Database', 'Connection', 'fail', $mysqli->connect_error);
        } else {
            record('Database', 'Connection', 'pass');
            record('Database', 'Database name', 'pass', DB_NAME);
            record('Database', 'Charset', 'pass', DB_CHARSET);

            // Check for critical tables
            $criticalTables = [
                'users', 'departments', 'vehicles', 'drivers', 'requests',
                'notifications', 'settings', 'schema_migrations'
            ];

            foreach ($criticalTables as $table) {
                $result = $mysqli->query("SHOW TABLES LIKE '$table'");
                $exists = $result && $result->num_rows > 0;
                record('Database Table', $table, $exists ? 'pass' : 'fail');
            }

            $mysqli->close();
        }
    } else {
        record('Database', 'Config file', 'fail', 'database.php not found');
    }
} catch (Exception $e) {
    record('Database', 'Connection', 'fail', $e->getMessage());
}

// =============================================================================
// 6. SECURITY CHECKS
// =============================================================================

echo "\n--- Security Checks ---\n";

// Check .env file permissions
$envPerms = substr(sprintf('%o', fileperms(__DIR__ . '/.env')), -4);
record('Security', '.env permissions', $envPerms <= '0600' ? 'pass' : 'warning', $envPerms);

// Check for exposed config files
$exposedFiles = glob(__DIR__ . '/*.php');
foreach ($exposedFiles as $file) {
    $basename = basename($file);
    if (strpos($basename, 'config') !== false && $basename !== 'index.php') {
        // This would be a security issue - config files should not be directly accessible
        // But since they're in a protected directory, this is just informational
    }
}

// Check .htaccess exists
record('Security', '.htaccess', file_exists(__DIR__ . '/.htaccess') ? 'pass' : 'fail');

// =============================================================================
// 7. COMPOSER DEPENDENCIES
// =============================================================================

echo "\n--- Composer Dependencies ---\n";

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    record('Dependencies', 'Vendor autoload', 'pass');

    // Check for TCPDF
    if (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf')) {
        record('Dependencies', 'TCPDF', 'pass');
    } else {
        record('Dependencies', 'TCPDF', 'fail', 'PDF generation may not work');
    }
} else {
    record('Dependencies', 'Vendor autoload', 'fail', 'run: composer install');
}

// =============================================================================
// 8. CACHE & LOGS
// =============================================================================

echo "\n--- Cache & Logs ---\n";

$cacheDir = __DIR__ . '/cache/data';
if (is_dir($cacheDir)) {
    $cacheFiles = glob($cacheDir . '/*.json');
    record('Cache', 'Cache directory', 'pass', count($cacheFiles) . ' files cached');
} else {
    record('Cache', 'Cache directory', 'warning', 'does not exist');
}

// =============================================================================
// 9. CRITICAL CONFIGURATION
// =============================================================================

echo "\n--- Critical Configuration ---\n";

// Check if URL is accessible
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $currentUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    record('URL', 'Accessible', 'pass', $currentUrl);

    // Check HTTPS
    record('URL', 'HTTPS enabled', $protocol === 'https' ? 'pass' : 'warning');
} else {
    record('URL', 'Accessible', 'warning', 'running from CLI');
}

// =============================================================================
// SUMMARY
// =============================================================================

echo "\n";
echo str_repeat('=', 50) . "\n";
echo "VERIFICATION SUMMARY\n";
echo str_repeat('=', 50) . "\n\n";

echo "Passed: " . count($passed) . "\n";
echo "Failed: " . count($failed) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (count($failed) > 0) {
    echo "❌ VERIFICATION FAILED\n\n";
    echo "The following issues must be resolved:\n";
    foreach ($failed as $issue) {
        echo "  - $issue\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS\n\n";
    echo "Consider addressing these warnings:\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
    echo "\n";
}

if (count($failed) === 0 && count($warnings) === 0) {
    echo "✅ ALL CHECKS PASSED\n\n";
    echo "Your LOKA Fleet Management System is ready for production!\n\n";
    echo "Next steps:\n";
    echo "1. Delete this file (verify-production.php)\n";
    echo "2. Login to: " . (isset($_SERVER['HTTP_HOST']) ? $protocol . '://' . $_SERVER['HTTP_HOST'] : 'your-domain') . "\n";
    echo "3. Change default admin password immediately!\n";
} elseif (count($failed) === 0) {
    echo "✅ VERIFICATION PASSED WITH WARNINGS\n\n";
    echo "System is functional but review warnings above.\n\n";
    echo "Next steps:\n";
    echo "1. Address warnings if possible\n";
    echo "2. Delete this file (verify-production.php)\n";
    echo "3. Login to: " . (isset($_SERVER['HTTP_HOST']) ? $protocol . '://' . $_SERVER['HTTP_HOST'] : 'your-domain') . "\n";
    echo "4. Change default admin password immediately!\n";
} else {
    echo "\n";
    echo "Please resolve the failed checks before using the system.\n";
    echo "For help, see DEPLOYMENT_GUIDE.md\n";
}

echo "\n" . str_repeat('=', 50) . "\n";

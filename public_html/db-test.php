<?php
/**
 * Database Connection Test Script
 * Run this to verify your database configuration
 */

echo "=== LOKA Database Connection Test ===\n\n";

// Check if config file exists
$configFile = __DIR__ . '/config/database.php';
if (!file_exists($configFile)) {
    die("❌ ERROR: config/database.php not found!\n");
}

// Check if .env file exists
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die("❌ ERROR: .env file not found!\n");
}

echo "✅ Config files exist\n\n";

// Load environment variables from .env
$envVars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
if ($envVars === false) {
    echo "⚠️  Warning: Could not parse .env file\n";
} else {
    echo "📋 Environment Variables from .env:\n";
    echo "  APP_ENV: " . (isset($envVars['APP_ENV']) ? $envVars['APP_ENV'] : 'NOT SET') . "\n";
    echo "  DB_HOST: " . (isset($envVars['DB_HOST']) ? $envVars['DB_HOST'] : 'NOT SET') . "\n";
    echo "  DB_DATABASE: " . (isset($envVars['DB_DATABASE']) ? $envVars['DB_DATABASE'] : 'NOT SET') . "\n";
    echo "  DB_USERNAME: " . (isset($envVars['DB_USERNAME']) ? $envVars['DB_USERNAME'] : 'NOT SET') . "\n";
    echo "  DB_PASSWORD: " . (isset($envVars['DB_PASSWORD']) ? '[SET]' : 'NOT SET') . "\n\n";
}

// Try to load the database configuration
echo "🔄 Loading database configuration...\n";
try {
    require_once $configFile;
    
    echo "✅ Database config loaded\n";
    echo "  DB_HOST: " . DB_HOST . "\n";
    echo "  DB_NAME: " . DB_NAME . "\n";
    echo "  DB_USER: " . DB_USER . "\n";
    echo "  DB_PASS: " . (DB_PASS ? '[SET]' : '[EMPTY]') . "\n\n";
} catch (Exception $e) {
    die("❌ ERROR loading config: " . $e->getMessage() . "\n");
}

// Try to connect to database
echo "🔄 Testing database connection...\n";
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
    
    echo "✅ Database connection SUCCESSFUL!\n\n";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $result = $stmt->fetch();
    echo "📊 Database statistics:\n";
    echo "  Total tables: " . $result->total . "\n";
    
    // Show some tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        echo "  Tables found:\n";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "    - $table\n";
        }
        if (count($tables) > 10) {
            echo "    ... and " . (count($tables) - 10) . " more\n";
        }
    }
    
} catch (PDOException $e) {
    echo "\n❌ DATABASE CONNECTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "🔧 Common fixes:\n";
    echo "1. Check DB_HOST - should be 'localhost' for same-server database\n";
    echo "2. Verify DB_DATABASE/DB_NAME matches your actual database name\n";
    echo "3. Verify DB_USERNAME/DB_USER and DB_PASSWORD/DB_PASS are correct\n";
    echo "4. Ensure MySQL/MariaDB service is running\n";
    echo "5. Check database user has proper privileges\n";
}

echo "\n=== End of Test ===\n";

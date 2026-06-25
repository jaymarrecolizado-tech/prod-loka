<?php
/**
 * Test bootstrap for LOKA PHPUnit tests
 * 
 * This file sets up the test environment and provides shared fixtures
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Test case base class with setup and teardown
abstract class TestCase extends PHPUnit\Framework\TestCase
{
    protected Database $db;
    
    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->setupTestData();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }
    
    // Override in subclasses to set up test data
    protected function setupTestData(): void
    {
    }
    
    // Override in subclasses to clean up test data
    protected function cleanupTestData(): void
    {
    }
}
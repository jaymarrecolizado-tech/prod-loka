<?php
/**
 * Unit test for database configuration
 * Tests basic database setup and connectivity
 */

class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDatabaseConnection(): void
    {
        // Test basic database connection
        $pdo = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testDatabaseQuery(): void
    {
        // Test database query functionality
        $result = $this->db->fetch("SELECT 1 as test_value");
        $this->assertIsObject($result);
        $this->assertEquals(1, $result->test_value);
    }

    public function testDatabaseInsert(): void
    {
        // Test database insert functionality
        $data = [
            'name' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('testpass123', PASSWORD_DEFAULT),
            'role' => 'requester',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $this->db->insert('users', $data);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        // Clean up
        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testDatabaseUpdate(): void
    {
        // Test database update functionality
        $data = [
            'name' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('testpass123', PASSWORD_DEFAULT),
            'role' => 'requester',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $this->db->insert('users', $data);

        // Update the record
        $updateData = ['name' => 'updatedtestuser'];
        $affected = $this->db->update('users', $updateData, 'id = ?', [$id]);
        $this->assertEquals(1, $affected);

        // Verify the update
        $result = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        $this->assertEquals('updatedtestuser', $result->name);

        // Clean up
        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testDatabaseDelete(): void
    {
        // Test database delete functionality
        $data = [
            'name' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('testpass123', PASSWORD_DEFAULT),
            'role' => 'requester',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $this->db->insert('users', $data);

        // Delete the record
        $affected = $this->db->delete('users', 'id = ?', [$id]);
        $this->assertEquals(1, $affected);

        // Verify deletion
        $result = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->db->delete('users', 'email = ?', ['test@example.com']);
        parent::tearDown();
    }
}
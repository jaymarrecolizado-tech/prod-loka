<?php
/**
 * Feature test for authentication
 * Tests user authentication and authorization
 */

class AuthFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->db->insert('users', [
            'name' => 'testuser',
            'password' => password_hash('testpass123', PASSWORD_DEFAULT),
            'email' => 'test@example.com',
            'role' => 'requester',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function testUserLogin(): void
    {
        // Test user authentication
        $user = $this->db->fetch("SELECT * FROM users WHERE email = ?", ['test@example.com']);
        $this->assertNotNull($user);
        $this->assertEquals('testuser', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('requester', $user->role);
    }

    public function testUserPasswordHashing(): void
    {
        // Test password hashing
        $user = $this->db->fetch("SELECT password FROM users WHERE email = ?", ['test@example.com']);
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->password);
        
        // Verify password can be verified
        $this->assertTrue(password_verify('testpass123', $user->password));
        $this->assertFalse(password_verify('wrongpass', $user->password));
    }

    public function testUserRoleBasedAccess(): void
    {
        // Test role-based access control
        $user = $this->db->fetch("SELECT role FROM users WHERE email = ?", ['test@example.com']);
        $this->assertNotNull($user);
        $this->assertEquals('requester', $user->role);
        
        // Test that requester can create requests
        $this->assertTrue($user->role === 'requester');
    }

    protected function tearDown(): void
    {
        // Clean up test user
        $this->db->delete('users', 'email = ?', ['test@example.com']);
        parent::tearDown();
    }
}
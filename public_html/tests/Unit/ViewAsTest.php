<?php

/**
 * Unit tests for All Father View-as helpers (no DB).
 */

$_SESSION = [];
$_SESSION['user_role'] = 'all_father';
$_SESSION['user_id'] = 1;

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/view_as.php';

class ViewAsTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [
            'user_role' => ROLE_ALL_FATHER,
            'user_id' => 1,
        ];
    }

    public function testRealAllFatherAndOptions(): void
    {
        $this->assertTrue(isRealAllFather());
        $this->assertTrue(canUseViewAs());
        $this->assertNull(getViewAsRole());
        $this->assertArrayHasKey(ROLE_GUARD, viewAsRoleOptions());
        $this->assertArrayHasKey('driver', viewAsRoleOptions());
    }

    public function testSetAndClearViewAsRoleWithoutDriverDb(): void
    {
        $this->assertTrue(setViewAsRole(ROLE_GUARD));
        $this->assertSame(ROLE_GUARD, getViewAsRole());
        $this->assertTrue(isViewingAs());
        $this->assertSame(ROLE_GUARD, effectiveUserRole());

        $this->assertTrue(setViewAsRole(null));
        $this->assertNull(getViewAsRole());
        $this->assertFalse(isViewingAs());
        $this->assertSame(ROLE_ALL_FATHER, effectiveUserRole());
    }

    public function testAdministratorCannotUseViewAs(): void
    {
        $_SESSION['user_role'] = ROLE_ADMIN;
        $this->assertFalse(canUseViewAs());
        $this->assertFalse(setViewAsRole(ROLE_GUARD));
        $this->assertNull(getViewAsRole());
    }

    public function testDriverViewAsMapsEffectiveRoleToRequester(): void
    {
        // Skip DB-backed driver id check by setting a fake role path:
        // setViewAsRole('driver') requires viewAsTestDriverId() which needs db().
        // Instead assert mapping helper behavior via session inject.
        $_SESSION[VIEW_AS_SESSION_KEY] = 'driver';
        $this->assertSame('driver', getViewAsRole());
        $this->assertSame(ROLE_REQUESTER, effectiveUserRole());
    }
}

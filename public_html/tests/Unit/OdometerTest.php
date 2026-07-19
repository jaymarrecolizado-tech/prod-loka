<?php

/**
 * Unit tests for odometer helpers (no DB required for these cases).
 */

require_once __DIR__ . '/../../includes/odometer.php';

class OdometerTest extends PHPUnit\Framework\TestCase
{
    public function testNormalizePlateNumberStripsSpaces(): void
    {
        $this->assertSame('SDF424', normalizePlateNumber('SDF 424'));
        $this->assertSame('SBY225', normalizePlateNumber('sby 225'));
    }

    public function testVehicleBrokenFromDbFlag(): void
    {
        $broken = (object) ['plate_number' => 'ABC 123', 'odometer_broken' => 1];
        $this->assertTrue(vehicleOdometerIsBroken($broken));

        $ok = (object) ['plate_number' => 'SDF 424', 'odometer_broken' => 0];
        $this->assertFalse(vehicleOdometerIsBroken($ok));
    }

    public function testDispatchRequiresReadingUnlessBroken(): void
    {
        $fail = guardResolveOdometerReading('dispatch', null, false, false, 1000);
        $this->assertFalse($fail['ok']);

        $ok = guardResolveOdometerReading('dispatch', 1050, false, false, 1000);
        $this->assertTrue($ok['ok']);
        $this->assertSame(1050, $ok['mileage']);

        $skip = guardResolveOdometerReading('dispatch', null, true, false, 1000);
        $this->assertTrue($skip['ok']);
        $this->assertTrue($skip['broken']);
        $this->assertNull($skip['mileage']);

        $known = guardResolveOdometerReading('dispatch', null, false, true, 1000);
        $this->assertTrue($known['ok']);
        $this->assertTrue($known['broken']);
    }

    public function testArrivalRejectsLowerThanStart(): void
    {
        $bad = guardResolveOdometerReading('arrival', 900, false, false, 1000);
        $this->assertFalse($bad['ok']);

        $good = guardResolveOdometerReading('arrival', 1100, false, false, 1000);
        $this->assertTrue($good['ok']);
        $this->assertSame(1100, $good['mileage']);
    }
}

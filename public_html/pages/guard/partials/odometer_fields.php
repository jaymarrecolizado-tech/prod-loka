<?php
/**
 * Guard odometer reminder + reading / broken bypass
 *
 * Expects:
 * - $trip (object with plate_number, mileage_start, vehicle_mileage optional)
 * - $odoPhase: 'dispatch' | 'arrival'
 * - $tripId: int
 */
$odoPhase = $odoPhase ?? 'dispatch';
$tripId = (int) ($tripId ?? ($trip->id ?? 0));
$plate = (string) ($trip->plate_number ?? '');
$knownBroken = vehicleOdometerIsBroken(
    (object) [
        'plate_number' => $plate,
        'odometer_broken' => $trip->odometer_broken ?? 0,
    ],
    $plate
);
$isDispatch = $odoPhase === 'dispatch';
$fieldName = $isDispatch ? 'mileage_start' : 'mileage_end';
$fieldLabel = $isDispatch ? 'Starting odometer (km)' : 'Ending odometer (km)';
$minMileage = $isDispatch
    ? (int) ($trip->vehicle_mileage ?? 0)
    : (int) ($trip->mileage_start ?? 0);
$driverName = trim((string) ($trip->driver_name ?? 'the driver'));
?>
<div class="rounded-lg border border-warning/40 bg-warning/10 p-3 space-y-3" data-odo-block="<?= $tripId ?>">
    <div class="text-sm text-base-content">
        <strong class="text-warning"><i class="bi bi-exclamation-triangle mr-1"></i>Remind the driver</strong>
        <p class="mb-0 mt-1 text-xs text-base-content/80">
            Please ask <strong><?= e($driverName) ?></strong> to read the vehicle odometer
            (<?= e($plate !== '' ? $plate : 'this vehicle') ?>) before you confirm
            <?= $isDispatch ? 'dispatch' : 'arrival' ?>.
        </p>
    </div>

    <?php if ($knownBroken): ?>
    <div class="loka-alert loka-alert-warning text-xs mb-0 py-2">
        This vehicle is marked with a <strong>broken / unreadable odometer</strong>.
        You may skip the numeric reading — check the box below to continue.
    </div>
    <?php endif; ?>

    <div class="flex flex-col gap-1.5" id="odoInputWrap<?= $tripId ?>">
        <label class="label py-0">
            <span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">
                <?= e($fieldLabel) ?>
                <?php if (!$knownBroken): ?><span class="text-error">*</span><?php endif; ?>
            </span>
        </label>
        <input type="number"
               class="input input-bordered input-sm bg-base-100"
               name="<?= e($fieldName) ?>"
               id="odoInput<?= $tripId ?>"
               min="<?= max(0, $minMileage) ?>"
               step="1"
               placeholder="Ask driver for current odometer reading"
               <?= $knownBroken ? '' : 'required' ?>
               <?= $knownBroken ? 'disabled' : '' ?>>
        <?php if ($minMileage > 0): ?>
        <span class="text-xs text-base-content/50">
            <?= $isDispatch ? 'Vehicle last recorded mileage' : 'Starting mileage' ?>:
            <strong><?= number_format($minMileage) ?> km</strong>
        </span>
        <?php endif; ?>
    </div>

    <label class="flex items-start gap-2 cursor-pointer rounded-lg border border-base-300 bg-base-100 p-2">
        <input type="checkbox"
               class="checkbox checkbox-sm checkbox-warning mt-0.5"
               name="odometer_broken"
               id="odoBroken<?= $tripId ?>"
               value="1"
               <?= $knownBroken ? 'checked' : '' ?>
               onchange="toggleGuardOdometerBroken(<?= $tripId ?>)">
        <span class="text-sm">
            <strong>Odometer broken / unreadable</strong>
            <span class="block text-xs text-base-content/60">
                Check this to continue without a reading (special / damaged units). Still remind the driver.
            </span>
        </span>
    </label>
</div>

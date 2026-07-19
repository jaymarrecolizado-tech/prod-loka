<?php
/**
 * LOKA - Guard Actions Handler
 *
 * Handles recording of dispatch and arrival times
 */

requireRole(ROLE_GUARD);
requireCsrf();

$action = get('action');
$requestId = postInt('request_id');

if (!$requestId) {
    redirectWith('/?page=guard', 'danger', 'Invalid request.');
}

// Verify the request exists and is approved
$request = db()->fetch(
    "SELECT r.*, u.name as requester_name, u.email as requester_email,
            v.plate_number, v.mileage as vehicle_mileage,
            COALESCE(v.odometer_broken, 0) as odometer_broken
     FROM requests r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN vehicles v ON r.vehicle_id = v.id AND v.deleted_at IS NULL
     WHERE r.id = ? AND r.status = 'approved' AND r.deleted_at IS NULL",
    [$requestId]
);

if (!$request) {
    redirectWith('/?page=guard', 'danger', 'Request not found or not approved.');
}

$now = date(DATETIME_FORMAT);

switch ($action) {
    case 'record_dispatch':
        if ($request->actual_dispatch_datetime) {
            redirectWith('/?page=guard', 'warning', 'This vehicle has already been dispatched.');
        }

        $dispatchTime = post('dispatch_time');
        $guardNotes = postSafe('guard_notes', '', 500);

        $hasTravelOrder = post('has_travel_order') ? 1 : 0;
        $hasObSlip = post('has_official_business_slip') ? 1 : 0;
        $travelOrderNumber = null;
        $obSlipNumber = null;

        if ($hasTravelOrder) {
            $travelOrderNumber = postSafe('travel_order_number', '', 50);
            if (empty($travelOrderNumber)) {
                redirectWith('/?page=guard', 'danger', 'Travel Order number is required when Travel Order checkbox is checked.');
            }
        }

        if ($hasObSlip) {
            $obSlipNumber = postSafe('ob_slip_number', '', 50);
            if (empty($obSlipNumber)) {
                redirectWith('/?page=guard', 'danger', 'OB Slip number is required when OB Slip checkbox is checked.');
            }
        }

        if (!$dispatchTime) {
            redirectWith('/?page=guard', 'danger', 'Dispatch time is required.');
        }

        $vehicleBroken = vehicleOdometerIsBroken(
            (object) [
                'plate_number' => $request->plate_number ?? '',
                'odometer_broken' => $request->odometer_broken ?? 0,
            ],
            $request->plate_number ?? null
        );
        $odo = guardResolveOdometerReading(
            'dispatch',
            guardPostedMileage('mileage_start'),
            (bool) post('odometer_broken'),
            $vehicleBroken,
            $request->vehicle_mileage !== null ? (int) $request->vehicle_mileage : null
        );
        if (!$odo['ok']) {
            redirectWith('/?page=guard', 'danger', $odo['error']);
        }

        $formattedDispatchTime = date('Y-m-d H:i:s', strtotime($dispatchTime));

        $obsResult = observationSaveForPhase(
            $requestId,
            $request->vehicle_id ? (int) $request->vehicle_id : null,
            'dispatch',
            (int) userId(),
            (string) post('overall_condition', ''),
            observationParseFlagsFromPost(),
            (string) postSafe('condition_notes', '', 1000),
            $odo['mileage'],
            $_FILES['observation_photos'] ?? []
        );
        if (!$obsResult['ok']) {
            redirectWith('/?page=guard', 'danger', $obsResult['error'] ?? 'Could not save vehicle observation.');
        }

        $notes = guardAppendNotes($guardNotes ?: null, $odo['note']);

        $updateData = [
            'actual_dispatch_datetime' => $formattedDispatchTime,
            'dispatch_guard_id' => userId(),
            'guard_notes' => $notes,
            'has_travel_order' => $hasTravelOrder,
            'has_official_business_slip' => $hasObSlip,
            'travel_order_number' => $travelOrderNumber,
            'ob_slip_number' => $obSlipNumber,
            'updated_at' => $now,
        ];
        if ($odo['mileage'] !== null) {
            $updateData['mileage_start'] = $odo['mileage'];
        }

        db()->update('requests', $updateData, 'id = ?', [$requestId]);

        if ($request->vehicle_id && $odo['mileage'] !== null) {
            db()->update('vehicles', [
                'mileage' => $odo['mileage'],
                'updated_at' => $now,
            ], 'id = ?', [$request->vehicle_id]);
        }

        auditLog(
            'vehicle_dispatched',
            'request',
            $requestId,
            null,
            [
                'dispatch_time' => $formattedDispatchTime,
                'guard_id' => userId(),
                'guard_notes' => $notes,
                'mileage_start' => $odo['mileage'],
                'odometer_broken' => $odo['broken'],
                'observation_id' => $obsResult['observation_id'] ?? null,
            ]
        );

        notify(
            $request->user_id,
            'vehicle_dispatched',
            'Vehicle Dispatched',
            "Your vehicle for request #{$requestId} to {$request->destination} has departed at " . formatDateTime($formattedDispatchTime) . ".",
            '/?page=requests&action=view&id=' . $requestId,
            $requestId
        );

        if ($request->driver_id) {
            notifyDriver(
                $request->driver_id,
                'trip_started',
                'Trip Started',
                "Trip for request #{$requestId} to {$request->destination} has officially started. Dispatch time: " . formatDateTime($formattedDispatchTime),
                '/?page=requests&action=view&id=' . $requestId
            );
        }

        $msg = $odo['broken']
            ? "Dispatch recorded for request #{$requestId} (odometer reading skipped — broken/unreadable)."
            : "Dispatch recorded for request #{$requestId}.";
        redirectWith('/?page=guard', 'success', $msg);
        break;

    case 'record_arrival':
        if (!$request->actual_dispatch_datetime) {
            redirectWith('/?page=guard', 'danger', 'Vehicle must be dispatched before recording arrival.');
        }

        if ($request->actual_arrival_datetime) {
            redirectWith('/?page=guard', 'warning', 'This vehicle has already returned.');
        }

        $arrivalTime = post('arrival_time');
        $guardNotes = postSafe('guard_notes', '', 500);

        if (!$arrivalTime) {
            redirectWith('/?page=guard', 'danger', 'Arrival time is required.');
        }

        $formattedArrivalTime = date('Y-m-d H:i:s', strtotime($arrivalTime));

        if (strtotime($formattedArrivalTime) <= strtotime($request->actual_dispatch_datetime)) {
            redirectWith('/?page=guard', 'danger', 'Arrival time must be after dispatch time.');
        }

        $vehicleBroken = vehicleOdometerIsBroken(
            (object) [
                'plate_number' => $request->plate_number ?? '',
                'odometer_broken' => $request->odometer_broken ?? 0,
            ],
            $request->plate_number ?? null
        );
        $baseline = $request->mileage_start !== null ? (int) $request->mileage_start : null;
        $odo = guardResolveOdometerReading(
            'arrival',
            guardPostedMileage('mileage_end'),
            (bool) post('odometer_broken'),
            $vehicleBroken,
            $baseline
        );
        if (!$odo['ok']) {
            redirectWith('/?page=guard', 'danger', $odo['error']);
        }

        $mileageEnd = $odo['mileage'];
        $mileageActual = null;
        if ($mileageEnd !== null && $baseline !== null) {
            $mileageActual = $mileageEnd - $baseline;
        }

        $overallCondition = (string) post('overall_condition', '');
        $conditionNotes = (string) postSafe('condition_notes', '', 1000);
        $obsResult = observationSaveForPhase(
            $requestId,
            $request->vehicle_id ? (int) $request->vehicle_id : null,
            'arrival',
            (int) userId(),
            $overallCondition,
            observationParseFlagsFromPost(),
            $conditionNotes,
            $mileageEnd,
            $_FILES['observation_photos'] ?? []
        );
        if (!$obsResult['ok']) {
            redirectWith('/?page=guard', 'danger', $obsResult['error'] ?? 'Could not save vehicle observation.');
        }
        observationNotifyDamage($request, $overallCondition, $conditionNotes);

        $arrivalNote = $guardNotes ? ('[Arrival] ' . $guardNotes) : null;
        $combinedNotes = guardAppendNotes($request->guard_notes, $arrivalNote);
        $combinedNotes = guardAppendNotes($combinedNotes, $odo['note']);

        $updateData = [
            'actual_arrival_datetime' => $formattedArrivalTime,
            'arrival_guard_id' => userId(),
            'status' => STATUS_COMPLETED,
            'guard_notes' => $combinedNotes,
            'updated_at' => $now,
        ];

        if ($mileageEnd !== null) {
            $updateData['mileage_end'] = $mileageEnd;
        }
        if ($mileageActual !== null) {
            $updateData['mileage_actual'] = $mileageActual;
        }

        db()->update('requests', $updateData, 'id = ?', [$requestId]);

        if ($request->vehicle_id) {
            $vehicleUpdateData = ['status' => 'available', 'updated_at' => $now];
            if ($mileageEnd !== null) {
                $vehicleUpdateData['mileage'] = $mileageEnd;
            }
            db()->update('vehicles', $vehicleUpdateData, 'id = ?', [$request->vehicle_id]);
        }
        if ($request->driver_id) {
            db()->update('drivers', ['status' => 'available', 'updated_at' => $now], 'id = ?', [$request->driver_id]);
        }

        auditLog(
            'vehicle_arrived',
            'request',
            $requestId,
            [
                'dispatch_time' => $request->actual_dispatch_datetime,
                'old_status' => $request->status,
            ],
            [
                'arrival_time' => $formattedArrivalTime,
                'guard_id' => userId(),
                'guard_notes' => $guardNotes,
                'mileage_end' => $mileageEnd,
                'mileage_actual' => $mileageActual,
                'odometer_broken' => $odo['broken'],
                'new_status' => STATUS_COMPLETED,
            ]
        );

        notify(
            $request->user_id,
            'vehicle_arrived',
            'Vehicle Returned - Trip Completed',
            "Your vehicle for request #{$requestId} to {$request->destination} has returned at " . formatDateTime($formattedArrivalTime) . ". Trip completed!",
            '/?page=requests&action=view&id=' . $requestId,
            $requestId
        );

        notifyPassengersBatch(
            $requestId,
            'trip_completed',
            'Trip Completed',
            "The trip to {$request->destination} has been completed. Vehicle returned at " . formatDateTime($formattedArrivalTime) . ".",
            '/?page=requests&action=view&id=' . $requestId
        );

        if ($request->driver_id) {
            notifyDriver(
                $request->driver_id,
                'trip_completed',
                'Trip Completed',
                "Trip for request #{$requestId} to {$request->destination} has been completed. Arrival time: " . formatDateTime($formattedArrivalTime),
                '/?page=requests&action=view&id=' . $requestId
            );
        }

        $msg = $odo['broken']
            ? "Arrival recorded for request #{$requestId}. Trip completed (odometer reading skipped — broken/unreadable)."
            : "Arrival recorded for request #{$requestId}. Trip marked as completed.";
        redirectWith('/?page=guard', 'success', $msg);
        break;

    default:
        redirectWith('/?page=guard', 'danger', 'Invalid action.');
}

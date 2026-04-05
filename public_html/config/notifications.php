<?php
/**
 * LOKA - Email Notification Templates
 *
 * Email templates for various system notifications
 */

class NotificationTemplate
{
    /**
     * Request Approved Template
     */
    public static function requestApproved($request, $approver)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $purpose = $request->purpose;
        $startDateTime = formatDateTime($request->start_datetime);
        $vehicle = $request->vehicle_plate ?? 'To be assigned';
        $approverName = $approver->name;

        return [
            'subject' => "[$appName] Request #$requestId Approved",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #0d6efd; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>✅ Request Approved</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear {$request->requester_name},</p>
                        <p>Your vehicle request has been <strong>approved</strong>.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #0d6efd;'>Request Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Purpose:</strong> $purpose</p>
                            <p><strong>Date & Time:</strong> $startDateTime</p>
                            <p><strong>Vehicle:</strong> $vehicle</p>
                            <p><strong>Approved by:</strong> $approverName</p>
                        </div>

                        <p>Please proceed to the motorpool office for vehicle assignment.</p>
                        <p><a href='" . APP_URL . "/?page=requests&action=view&id=$requestId' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Request</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Request Rejected Template
     */
    public static function requestRejected($request, $approver, $reason)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $purpose = $request->purpose;
        $approverName = $approver->name;

        return [
            'subject' => "[$appName] Request #$requestId Rejected",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #dc3545; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>❌ Request Rejected</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear {$request->requester_name},</p>
                        <p>Your vehicle request has been <strong>rejected</strong>.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #dc3545;'>Request Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Purpose:</strong> $purpose</p>
                            <p><strong>Rejected by:</strong> $approverName</p>
                            " . ($reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                        </div>

                        <p>You may submit a new request with the necessary adjustments.</p>
                        <p><a href='" . APP_URL . "/?page=requests' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Create New Request</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Vehicle Assignment Template
     */
    public static function vehicleAssigned($request, $vehicle, $driver)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $vehiclePlate = $vehicle->plate_number;
        $vehicleMake = $vehicle->make . ' ' . $vehicle->model;
        $driverName = $driver ? $driver->name : 'To be assigned';

        return [
            'subject' => "[$appName] Vehicle Assigned - Request #$requestId",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #198754; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>🚗 Vehicle Assigned</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear {$request->requester_name},</p>
                        <p>A vehicle has been assigned to your approved request.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #198754;'>Vehicle Information</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Vehicle:</strong> $vehiclePlate ($vehicleMake)</p>
                            <p><strong>Driver:</strong> $driverName</p>
                            <p><strong>Trip Date:</strong> " . formatDateTime($request->start_datetime) . "</p>
                        </div>

                        <p>Please be ready at the designated pickup time.</p>
                        <p><a href='" . APP_URL . "/?page=requests&action=view&id=$requestId' style='background: #198754; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Request</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Driver Assignment Template
     */
    public static function driverAssigned($trip, $driver)
    {
        $appName = APP_NAME;
        $requestId = $trip->id;
        $purpose = $trip->purpose;
        $startDateTime = formatDateTime($trip->start_datetime);
        $vehicle = $trip->vehicle_plate ?? 'To be assigned';

        return [
            'subject' => "[$appName] New Trip Assignment - Request #$requestId",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #0dcaf0; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>🚗 New Trip Assignment</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear {$driver->name},</p>
                        <p>You have been assigned to a trip.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #0dcaf0;'>Trip Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Purpose:</strong> $purpose</p>
                            <p><strong>Date & Time:</strong> $startDateTime</p>
                            <p><strong>Vehicle:</strong> $vehicle</p>
                            <p><strong>Requester:</strong> {$trip->requester_name}</p>
                        </div>

                        <p>Please confirm your availability and report to the motorpool office.</p>
                        <p><a href='" . APP_URL . "/?page=my-trips' style='background: #0dcaf0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View My Trips</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Request Cancelled Template
     */
    public static function requestCancelled($request, $cancelledBy)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $purpose = $request->purpose;
        $startDateTime = formatDateTime($request->start_datetime);

        return [
            'subject' => "[$appName] Request #$requestId Cancelled",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #6c757d; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>📋 Request Cancelled</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>A vehicle request has been cancelled.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #6c757d;'>Cancelled Request Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Requester:</strong> {$request->requester_name}</p>
                            <p><strong>Purpose:</strong> $purpose</p>
                            <p><strong>Original Date:</strong> $startDateTime</p>
                            <p><strong>Cancelled by:</strong> $cancelledBy</p>
                        </div>

                        <p>The vehicle and driver (if assigned) have been freed for other assignments.</p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Revision Request Template
     */
    public static function revisionRequested($request, $approver, $reason)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $purpose = $request->purpose;
        $approverName = $approver->name;

        return [
            'subject' => "[$appName] Revision Required - Request #$requestId",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #fd7e14; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>📝 Revision Required</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear {$request->requester_name},</p>
                        <p>Your vehicle request requires <strong>revision</strong> before it can be approved.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #fd7e14;'>Request Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Purpose:</strong> $purpose</p>
                            <p><strong>Requested by:</strong> $approverName</p>
                            " . ($reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                        </div>

                        <p>Please update your request with the necessary information.</p>
                        <p><a href='" . APP_URL . "/?page=requests&action=edit&id=$requestId' style='background: #fd7e14; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Update Request</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Trip Reminder Template
     */
    public static function tripReminder($request)
    {
        $appName = APP_NAME;
        $requestId = $request->id;
        $startDateTime = formatDateTime($request->start_datetime);
        $vehicle = $request->vehicle_plate ?? 'To be assigned';

        return [
            'subject' => "[$appName] Trip Reminder - Request #$requestId",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #0d6efd; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>⏰ Trip Reminder</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>This is a reminder about your upcoming trip.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #0d6efd;'>Trip Details</h3>
                            <p><strong>Request ID:</strong> #$requestId</p>
                            <p><strong>Date & Time:</strong> <strong style='color: #dc3545;'>$startDateTime</strong></p>
                            <p><strong>Purpose:</strong> {$request->purpose}</p>
                            <p><strong>Destination:</strong> {$request->destination}</p>
                            <p><strong>Vehicle:</strong> $vehicle</p>
                        </div>

                        <p>Please ensure you are ready on time. If you need to cancel, please do so through the system.</p>
                        <p><a href='" . APP_URL . "/?page=requests&action=view&id=$requestId' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Request</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Daily Digest Template (for Approvers)
     */
    public static function dailyDigest($approver, $pendingCount, $pendingList)
    {
        $appName = APP_NAME;
        $approverName = $approver->name;
        $today = formatDate(date('Y-m-d'));

        $pendingItems = '';
        foreach ($pendingList as $item) {
            $pendingItems .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>#{$item->id}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>" . htmlspecialchars($item->purpose) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>" . formatDateTime($item->start_datetime) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>{$item->requester_name}</td>
                </tr>
            ";
        }

        return [
            'subject' => "[$appName] Daily Approval Digest - $today",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;'>
                    <div style='background: #0d6efd; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>📋 Daily Approval Digest</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Dear $approverName,</p>
                        <p>You have <strong>$pendingCount pending request(s)</strong> requiring your attention.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <thead>
                                    <tr style='background: #0d6efd; color: white;'>
                                        <th style='padding: 10px; text-align: left;'>ID</th>
                                        <th style='padding: 10px; text-align: left;'>Purpose</th>
                                        <th style='padding: 10px; text-align: left;'>Date</th>
                                        <th style='padding: 10px; text-align: left;'>Requester</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $pendingItems
                                </tbody>
                            </table>
                        </div>

                        <p><a href='" . APP_URL . "/?page=approvals' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Review Pending Requests</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated daily digest from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Gas Voucher Submitted Template (for Approvers)
     */
    public static function gasVoucherSubmitted($voucher, $requester)
    {
        $appName = APP_NAME;
        $voucherId = $voucher->id;
        $requesterName = $requester->name ?? 'Unknown';

        return [
            'subject' => "[$appName] New Gas Voucher Request #$voucherId",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #ffc107; color: #333; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>⛽ New Gas Voucher Request</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>A new gas voucher request requires your review.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #ffc107;'>Voucher Details</h3>
                            <p><strong>Voucher ID:</strong> #$voucherId</p>
                            <p><strong>Requester:</strong> $requesterName</p>
                            <p><strong>Vehicle:</strong> " . ($voucher->vehicle_plate ?? 'N/A') . "</p>
                            <p><strong>Request Date:</strong> " . formatDate($voucher->request_date) . "</p>
                            <p><strong>Status:</strong> Pending Review</p>
                        </div>

                        <p><a href='" . APP_URL . "/?page=gas-vouchers&action=view&id=$voucherId' style='background: #ffc107; color: #333; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Review Voucher</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Gas Voucher Reviewed Template (for Requester)
     */
    public static function gasVoucherReviewed($voucher, $reviewer)
    {
        $appName = APP_NAME;
        $voucherId = $voucher->id;
        $reviewerName = $reviewer->name ?? 'Unknown';

        return [
            'subject' => "[$appName] Gas Voucher #$voucherId Reviewed",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #0dcaf0; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>📋 Gas Voucher Reviewed</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Your gas voucher request has been reviewed and is now awaiting final approval.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #0dcaf0;'>Voucher Details</h3>
                            <p><strong>Voucher ID:</strong> #$voucherId</p>
                            <p><strong>Reviewed by:</strong> $reviewerName</p>
                            <p><strong>Status:</strong> Pending Final Approval</p>
                        </div>

                        <p><a href='" . APP_URL . "/?page=gas-vouchers&action=view&id=$voucherId' style='background: #0dcaf0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Voucher</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Gas Voucher Approved Template (for Requester)
     */
    public static function gasVoucherApproved($voucher, $approver)
    {
        $appName = APP_NAME;
        $voucherId = $voucher->id;
        $approverName = $approver->name ?? 'Unknown';

        return [
            'subject' => "[$appName] Gas Voucher #$voucherId Approved",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #198754; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>✅ Gas Voucher Approved</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Great news! Your gas voucher request has been <strong>approved</strong>.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #198754;'>Voucher Details</h3>
                            <p><strong>Voucher ID:</strong> #$voucherId</p>
                            <p><strong>Approved by:</strong> $approverName</p>
                            <p><strong>Status:</strong> Approved</p>
                        </div>

                        <p>You may now print your approved gas voucher.</p>
                        <p><a href='" . APP_URL . "/?page=gas-vouchers&action=view&id=$voucherId' style='background: #198754; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View & Print Voucher</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Gas Voucher Rejected Template (for Requester)
     */
    public static function gasVoucherRejected($voucher, $rejector, $reason)
    {
        $appName = APP_NAME;
        $voucherId = $voucher->id;
        $rejectorName = $rejector->name ?? 'Unknown';

        return [
            'subject' => "[$appName] Gas Voucher #$voucherId Rejected",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #dc3545; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>❌ Gas Voucher Rejected</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>Your gas voucher request has been <strong>rejected</strong>.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #dc3545;'>Voucher Details</h3>
                            <p><strong>Voucher ID:</strong> #$voucherId</p>
                            <p><strong>Rejected by:</strong> $rejectorName</p>
                            " . ($reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                        </div>

                        <p>You may submit a new gas voucher request with the necessary adjustments.</p>
                        <p><a href='" . APP_URL . "/?page=gas-vouchers' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Create New Voucher</a></p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }

    /**
     * Gas Voucher Cancelled Template (for Approvers)
     */
    public static function gasVoucherCancelled($voucher, $cancelledBy)
    {
        $appName = APP_NAME;
        $voucherId = $voucher->id;

        return [
            'subject' => "[$appName] Gas Voucher #$voucherId Cancelled",
            'body' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #6c757d; color: white; padding: 20px; text-align: center;'>
                        <h1 style='margin: 0;'>📋 Gas Voucher Cancelled</h1>
                    </div>
                    <div style='padding: 20px; background: #f8f9fa;'>
                        <p>A gas voucher request has been cancelled.</p>

                        <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                            <h3 style='margin-top: 0; color: #6c757d;'>Cancelled Voucher Details</h3>
                            <p><strong>Voucher ID:</strong> #$voucherId</p>
                            <p><strong>Vehicle:</strong> " . ($voucher->vehicle_plate ?? 'N/A') . "</p>
                            <p><strong>Cancelled by:</strong> $cancelledBy</p>
                        </div>

                        <p>No further action is required.</p>
                    </div>
                    <div style='background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px;'>
                        <p style='margin: 0;'>This is an automated message from $appName Fleet Management System</p>
                    </div>
                </div>
            "
        ];
    }
}

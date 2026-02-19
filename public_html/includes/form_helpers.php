<?php
/**
 * LOKA - Form Helper Functions
 *
 * Shared functions for form handling, validation, and display
 * Reduces code duplication across create/edit pages
 */

// =============================================================================
// FORM RESULT CLASS
// =============================================================================

/**
 * FormResult - Encapsulates form submission results
 */
class FormResult
{
    public bool $success = false;
    public array $errors = [];
    public array $data = [];
    public ?int $id = null;
    public string $redirectUrl = '';
    public string $message = '';

    public function isSuccess(): bool
    {
        return $this->success && empty($this->errors);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function setRedirect(string $url): void
    {
        $this->redirectUrl = $url;
    }

    public function redirect(): void
    {
        if ($this->redirectUrl) {
            if ($this->success) {
                redirectWith($this->redirectUrl, 'success', $this->message);
            } else {
                redirect($this->redirectUrl);
            }
        }
    }
}

// =============================================================================
// CSRF HANDLING
// =============================================================================

// Note: csrfField() is defined in functions.php

/**
 * Validate CSRF token from POST data
 * @return bool True if token is valid
 */
function validateCsrfToken(): bool
{
    $token = post('csrf_token');
    $security = Security::getInstance();

    if (!$security->verifyCsrfToken($token)) {
        return false;
    }

    return true;
}

/**
 * Require valid CSRF token or show error
 */
function requireCsrfToken(): void
{
    if (!validateCsrfToken()) {
        http_response_code(403);
        die('Invalid security token. Please refresh the page and try again.');
    }
}

// =============================================================================
// ERROR DISPLAY HELPERS
// =============================================================================

/**
 * Display error messages if any exist
 * @param array $errors Array of error messages
 * @return string HTML for error display or empty string
 */
function showErrors(array $errors): string
{
    if (empty($errors)) {
        return '';
    }

    $html = '<div class="alert alert-danger">';
    $html .= '<strong>Please fix the following errors:</strong><br><br>';
    $html .= '<ul class="mb-0">';
    foreach ($errors as $error) {
        $html .= '<li>' . e($error) . '</li>';
    }
    $html .= '</ul></div>';

    return $html;
}

/**
 * Display a single error alert
 * @param string $message Error message
 * @return string HTML for error display
 */
function showError(string $message): string
{
    return '<div class="alert alert-danger">' . e($message) . '</div>';
}

/**
 * Display success message
 * @param string $message Success message
 * @return string HTML for success display
 */
function showSuccess(string $message): string
{
    return '<div class="alert alert-success">' . e($message) . '</div>';
}

/**
 * Display info message
 * @param string $message Info message
 * @return string HTML for info display
 */
function showInfo(string $message): string
{
    return '<div class="alert alert-info">' . e($message) . '</div>';
}

// =============================================================================
// FORM VALIDATION HELPERS
// =============================================================================

/**
 * Validate required field
 * @param mixed $value Value to check
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateRequired($value, string $fieldName, array &$errors): bool
{
    if (empty($value) || (is_string($value) && trim($value) === '')) {
        $errors[] = "{$fieldName} is required";
        return false;
    }
    return true;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateEmail(string $email, string $fieldName = 'Email', array &$errors = []): bool
{
    if (empty($email)) {
        $errors[] = "{$fieldName} is required";
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "{$fieldName} format is invalid";
        return false;
    }

    return true;
}

/**
 * Validate minimum length
 * @param string $value Value to check
 * @param int $minLength Minimum length
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateMinLength(string $value, int $minLength, string $fieldName, array &$errors): bool
{
    if (strlen($value) < $minLength) {
        $errors[] = "{$fieldName} must be at least {$minLength} characters";
        return false;
    }
    return true;
}

/**
 * Validate maximum length
 * @param string $value Value to check
 * @param int $maxLength Maximum length
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateMaxLength(string $value, int $maxLength, string $fieldName, array &$errors): bool
{
    if (strlen($value) > $maxLength) {
        $errors[] = "{$fieldName} must not exceed {$maxLength} characters";
        return false;
    }
    return true;
}

/**
 * Validate numeric value
 * @param mixed $value Value to check
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateNumeric($value, string $fieldName, array &$errors): bool
{
    if (!is_numeric($value)) {
        $errors[] = "{$fieldName} must be a number";
        return false;
    }
    return true;
}

/**
 * Validate value is in allowed list
 * @param mixed $value Value to check
 * @param array $allowed Allowed values
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid
 */
function validateAllowed($value, array $allowed, string $fieldName, array &$errors): bool
{
    if (!in_array($value, $allowed, true)) {
        $errors[] = "{$fieldName} contains an invalid value";
        return false;
    }
    return true;
}

/**
 * Validate unique field in database
 * @param string $table Table name
 * @param string $column Column name
 * @param mixed $value Value to check
 * @param int|null $excludeId ID to exclude (for edits)
 * @param string $fieldName Field name for error message
 * @param array $errors Errors array (passed by reference)
 * @return bool True if valid (unique or record is own)
 */
function validateUnique(string $table, string $column, $value, ?int $excludeId, string $fieldName, array &$errors): bool
{
    $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ?";
    $params = [$value];

    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }

    $existing = db()->fetch($sql, $params);
    if ($existing) {
        $errors[] = "{$fieldName} already exists";
        return false;
    }

    return true;
}

// =============================================================================
// POST DATA HELPERS
// =============================================================================

// Note: post(), postSafe(), and postInt() are defined in functions.php

/**
 * Get sanitized string from POST (wrapper for consistency)
 * @param string $key POST key
 * @param string $default Default value
 * @param int $maxLength Maximum length
 * @return string Sanitized value
 */
function postString(string $key, string $default = '', int $maxLength = 0): string
{
    return postSafe($key, $default, $maxLength);
}

/**
 * Get boolean from POST
 * @param string $key POST key
 * @param bool $default Default value
 * @return bool Boolean value
 */
function postBool(string $key, bool $default = false): bool
{
    $value = $_POST[$key] ?? null;
    if ($value === null) {
        return $default;
    }
    return in_array($value, [1, '1', 'on', 'yes', 'true'], true);
}

// =============================================================================
// FORM SUBMISSION HANDLERS
// =============================================================================

/**
 * Check if form was submitted
 * @param string $method HTTP method (default: POST)
 * @return bool True if form was submitted
 */
function isFormSubmitted(string $method = 'POST'): bool
{
    return $_SERVER['REQUEST_METHOD'] === $method;
}

/**
 * Begin form processing - returns early if not submitted
 * @param string $method HTTP method (default: POST)
 * @return bool True if form was submitted
 */
function beginFormProcessing(string $method = 'POST'): bool
{
    if (!isFormSubmitted($method)) {
        return false;
    }

    requireCsrfToken();
    return true;
}

/**
 * Handle form submission with callback
 * @param callable $processor Function that processes the form
 * @param callable $validator Optional validator function
 * @return FormResult Result of form processing
 */
function handleFormSubmission(callable $processor, ?callable $validator = null): FormResult
{
    $result = new FormResult();

    try {
        // Run validator if provided
        if ($validator !== null) {
            $validator($result);
        }

        // If no errors, process the form
        if (!$result->hasErrors()) {
            $processor($result);
        }
    } catch (Exception $e) {
        $result->addError('An error occurred. Please try again.');
        error_log("Form processing error: " . $e->getMessage());
    }

    return $result;
}

// =============================================================================
// DEPARTMENT FORM HELPERS
// =============================================================================

/**
 * Validate department form data
 * @param array $data Form data
 * @param int|null $excludeId ID to exclude for uniqueness check
 * @return array Array of errors (empty if valid)
 */
function validateDepartmentForm(array $data, ?int $excludeId = null): array
{
    $errors = [];

    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $headUserId = !empty($data['head_user_id']) ? (int) $data['head_user_id'] : null;
    $status = trim($data['status'] ?? 'active');

    // Validate name
    if (empty($name)) {
        $errors[] = 'Department name is required';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Department name must not exceed 100 characters';
    } else {
        // Check uniqueness
        validateUnique('departments', 'name', $name, $excludeId, 'Department name', $errors);
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Invalid status value';
    }

    // Validate head user if provided
    if ($headUserId !== null) {
        $headUser = db()->fetch("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL", [$headUserId]);
        if (!$headUser) {
            $errors[] = 'Selected department head does not exist';
        }
    }

    return $errors;
}

/**
 * Process department creation/update
 * @param array $data Form data
 * @param int|null $id ID for update, null for create
 * @return FormResult Result with ID set
 */
function processDepartmentForm(array $data, ?int $id = null): FormResult
{
    $result = new FormResult();

    try {
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $headUserId = !empty($data['head_user_id']) ? (int) $data['head_user_id'] : null;
        $status = trim($data['status'] ?? 'active');

        db()->beginTransaction();

        $recordData = [
            'name' => $name,
            'description' => $description,
            'head_user_id' => $headUserId,
            'status' => $status,
            'updated_at' => date(DATETIME_FORMAT)
        ];

        if ($id === null) {
            // Create
            $recordData['created_at'] = date(DATETIME_FORMAT);
            $id = db()->insert('departments', $recordData);
            auditLog('department_created', 'department', $id);
        } else {
            // Update
            db()->update('departments', $recordData, 'id = ?', [$id]);
            auditLog('department_updated', 'department', $id);
        }

        db()->commit();
        clearDepartmentCache();

        $result->success = true;
        $result->id = $id;
        $result->message = $id === null ? 'Department created successfully.' : 'Department updated successfully.';
        $result->redirectUrl = '/?page=departments';

    } catch (Exception $e) {
        db()->rollback();
        $result->addError('Failed to save department. Please try again.');
        error_log("Department form error: " . $e->getMessage());
    }

    return $result;
}

// =============================================================================
// USER FORM HELPERS
// =============================================================================

/**
 * Validate user form data
 * @param array $data Form data
 * @param int|null $excludeId ID to exclude for uniqueness check
 * @param bool $requirePassword Require password validation
 * @return array Array of errors (empty if valid)
 */
function validateUserForm(array $data, ?int $excludeId = null, bool $requirePassword = false): array
{
    $errors = [];

    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $phone = trim($data['phone'] ?? '');
    $role = trim($data['role'] ?? '');
    $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;

    // Validate name
    if (empty($name)) {
        $errors[] = 'Name is required';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Name must not exceed 100 characters';
    }

    // Validate email
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check uniqueness
        validateUnique('users', 'email', $email, $excludeId, 'Email', $errors);
    }

    // Validate password
    if ($requirePassword || !empty($password)) {
        $security = Security::getInstance();
        $passwordErrors = $security->validatePassword($password);
        if (!empty($passwordErrors)) {
            $errors = array_merge($errors, $passwordErrors);
        }
    }

    // Validate role
    $validRoles = array_keys(ROLE_LABELS);
    if (!in_array($role, $validRoles)) {
        $errors[] = 'Invalid role selected';
    }

    // Validate department if provided
    if ($departmentId !== null) {
        $dept = db()->fetch("SELECT id FROM departments WHERE id = ? AND deleted_at IS NULL", [$departmentId]);
        if (!$dept) {
            $errors[] = 'Selected department does not exist';
        }
    }

    return $errors;
}

/**
 * Process user creation/update
 * @param array $data Form data
 * @param int|null $id ID for update, null for create
 * @return FormResult Result with ID set
 */
function processUserForm(array $data, ?int $id = null): FormResult
{
    $result = new FormResult();

    try {
        $name = trim($data['name']);
        $email = trim($data['email']);
        $password = $data['password'] ?? '';
        $phone = trim($data['phone'] ?? '');
        $role = trim($data['role']);
        $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;

        db()->beginTransaction();

        $recordData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'department_id' => $departmentId,
            'status' => USER_ACTIVE,
            'updated_at' => date(DATETIME_FORMAT)
        ];

        if (!empty($password)) {
            $auth = new Auth();
            $recordData['password'] = $auth->hashPassword($password);
        }

        if ($id === null) {
            // Create
            $recordData['failed_login_attempts'] = 0;
            $recordData['created_at'] = date(DATETIME_FORMAT);
            $id = db()->insert('users', $recordData);
            auditLog('user_created', 'user', $id);
            $security = Security::getInstance();
            $security->logSecurityEvent('user_created', "New user: $email ($role)", userId());
        } else {
            // Update
            db()->update('users', $recordData, 'id = ?', [$id]);
            auditLog('user_updated', 'user', $id);
        }

        db()->commit();
        clearUserCache();

        $result->success = true;
        $result->id = $id;
        $result->message = $id === null ? 'User created successfully.' : 'User updated successfully.';
        $result->redirectUrl = '/?page=users';

    } catch (Exception $e) {
        db()->rollback();
        $result->addError('Failed to save user. Please try again.');
        error_log("User form error: " . $e->getMessage());
    }

    return $result;
}

// =============================================================================
// VEHICLE FORM HELPERS
// =============================================================================

/**
 * Validate vehicle form data
 * @param array $data Form data
 * @param int|null $excludeId ID to exclude for uniqueness check
 * @return array Array of errors (empty if valid)
 */
function validateVehicleForm(array $data, ?int $excludeId = null): array
{
    $errors = [];

    $plateNumber = trim($data['plate_number'] ?? '');
    $make = trim($data['make'] ?? '');
    $model = trim($data['model'] ?? '');
    $year = trim($data['year'] ?? '');
    $vehicleTypeId = (int) ($data['vehicle_type_id'] ?? 0);
    $color = trim($data['color'] ?? '');
    $fuelType = trim($data['fuel_type'] ?? '');
    $transmission = trim($data['transmission'] ?? '');
    $mileage = (int) ($data['mileage'] ?? 0);
    $status = trim($data['status'] ?? VEHICLE_AVAILABLE);

    // Validate required fields
    if (empty($plateNumber)) {
        $errors[] = 'Plate number is required';
    } elseif (strlen($plateNumber) > 50) {
        $errors[] = 'Plate number must not exceed 50 characters';
    } else {
        // Check uniqueness
        validateUnique('vehicles', 'plate_number', $plateNumber, $excludeId, 'Plate number', $errors);
    }

    if (empty($make)) {
        $errors[] = 'Make is required';
    }

    if (empty($model)) {
        $errors[] = 'Model is required';
    }

    if (empty($year) || !preg_match('/^\d{4}$/', $year)) {
        $errors[] = 'Valid year is required (4 digits)';
    }

    if ($vehicleTypeId <= 0) {
        $errors[] = 'Vehicle type is required';
    } else {
        // Check if vehicle type exists
        $typeExists = db()->fetch("SELECT id FROM vehicle_types WHERE id = ? AND deleted_at IS NULL", [$vehicleTypeId]);
        if (!$typeExists) {
            $errors[] = 'Selected vehicle type does not exist';
        }
    }

    // Validate status
    $validStatuses = [VEHICLE_AVAILABLE, VEHICLE_IN_USE, VEHICLE_MAINTENANCE, VEHICLE_OUT_OF_SERVICE];
    if (!in_array($status, $validStatuses)) {
        $errors[] = 'Invalid status value';
    }

    return $errors;
}

/**
 * Process vehicle creation/update
 * @param array $data Form data
 * @param int|null $id ID for update, null for create
 * @return FormResult Result with ID set
 */
function processVehicleForm(array $data, ?int $id = null): FormResult
{
    $result = new FormResult();

    try {
        $plateNumber = trim($data['plate_number']);
        $make = trim($data['make']);
        $model = trim($data['model']);
        $year = trim($data['year']);
        $vehicleTypeId = (int) $data['vehicle_type_id'];
        $color = trim($data['color'] ?? '');
        $fuelType = trim($data['fuel_type'] ?? '');
        $transmission = trim($data['transmission'] ?? '');
        $mileage = (int) ($data['mileage'] ?? 0);
        $notes = trim($data['notes'] ?? '');
        $status = trim($data['status'] ?? VEHICLE_AVAILABLE);

        db()->beginTransaction();

        $recordData = [
            'plate_number' => $plateNumber,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'vehicle_type_id' => $vehicleTypeId,
            'color' => $color,
            'fuel_type' => $fuelType,
            'transmission' => $transmission,
            'mileage' => $mileage,
            'status' => $status,
            'notes' => $notes,
            'updated_at' => date(DATETIME_FORMAT)
        ];

        if ($id === null) {
            // Create
            $recordData['created_at'] = date(DATETIME_FORMAT);
            $id = db()->insert('vehicles', $recordData);
            auditLog('vehicle_created', 'vehicle', $id);
        } else {
            // Update
            $oldData = db()->fetch("SELECT * FROM vehicles WHERE id = ?", [$id]);
            db()->update('vehicles', $recordData, 'id = ?', [$id]);
            auditLog('vehicle_updated', 'vehicle', $id, $oldData);
        }

        db()->commit();
        clearVehicleCache();

        $result->success = true;
        $result->id = $id;
        $result->message = $id === null ? 'Vehicle added successfully.' : 'Vehicle updated successfully.';
        $result->redirectUrl = '/?page=vehicles';

    } catch (Exception $e) {
        db()->rollback();
        $result->addError('Failed to save vehicle. Please try again.');
        error_log("Vehicle form error: " . $e->getMessage());
    }

    return $result;
}

// =============================================================================
// REQUEST FORM HELPERS
// =============================================================================

/**
 * Validate request form data
 * @param array $data Form data including start/end datetime, purpose, destination, etc.
 * @param int|null $requestId ID for existing request (for edits)
 * @return array Array of errors (empty if valid)
 */
function validateRequestForm(array $data, ?int $requestId = null): array
{
    $errors = [];

    $startDatetime = trim($data['start_datetime'] ?? '');
    $endDatetime = trim($data['end_datetime'] ?? '');
    $purpose = trim($data['purpose'] ?? '');
    $destination = trim($data['destination'] ?? '');
    $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
    $approverId = (int) ($data['approver_id'] ?? 0);
    $motorpoolHeadId = (int) ($data['motorpool_head_id'] ?? 0);
    $passengerCount = (int) ($data['passenger_count'] ?? 1);

    // Validate date/time
    if (empty($startDatetime)) {
        $errors[] = 'Start date/time is required';
    }
    if (empty($endDatetime)) {
        $errors[] = 'End date/time is required';
    }
    if ($startDatetime && $endDatetime) {
        try {
            $manilaTz = new DateTimeZone('Asia/Manila');
            $startDt = new DateTime($startDatetime, $manilaTz);
            $endDt = new DateTime($endDatetime, $manilaTz);
            if ($endDt <= $startDt) {
                $errors[] = 'End date/time must be after start date/time';
            }
        } catch (Exception $e) {
            $errors[] = 'Invalid date/time format';
        }
    }

    // Validate required fields
    if (empty($purpose)) {
        $errors[] = 'Purpose is required';
    }
    if (empty($destination)) {
        $errors[] = 'Destination is required';
    }
    if ($approverId <= 0) {
        $errors[] = 'Please select a department approver';
    }
    if ($motorpoolHeadId <= 0) {
        $errors[] = 'Please select a motorpool head';
    }

    // Validate vehicle capacity
    if ($vehicleId) {
        $vehicle = db()->fetch(
            "SELECT v.*, vt.passenger_capacity
             FROM vehicles v
             JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
             WHERE v.id = ? AND v.deleted_at IS NULL",
            [$vehicleId]
        );

        if (!$vehicle) {
            $errors[] = 'Selected vehicle does not exist';
        } elseif ($vehicle->passenger_capacity > 0 && $passengerCount > $vehicle->passenger_capacity) {
            $errors[] = "This vehicle can only accommodate {$vehicle->passenger_capacity} passengers, but you have {$passengerCount} passengers (including yourself). Please select a larger vehicle or reduce passengers.";
        }
    }

    return $errors;
}

/**
 * Sync passengers for a request
 * @param int $requestId Request ID
 * @param array $newPassengerIds New passenger identifiers (IDs or guest names)
 * @param array $currentPassengerIdentifiers Current passenger identifiers
 * @return array Array of notification data for passengers to notify
 */
function syncRequestPassengers(int $requestId, array $newPassengerIds, array $currentPassengerIdentifiers): array
{
    $notifications = [];
    $destination = post('destination', '');

    $newPassengerValues = array_map('trim', $newPassengerIds);
    $added = array_diff($newPassengerValues, $currentPassengerIdentifiers);
    $removed = array_diff($currentPassengerIdentifiers, $newPassengerValues);

    // Remove old passengers
    foreach ($removed as $identifier) {
        if (is_numeric($identifier)) {
            db()->delete('request_passengers', 'request_id = ? AND user_id = ?', [$requestId, (int) $identifier]);
            $notifications[] = [
                'user_id' => (int) $identifier,
                'type' => 'removed_from_request',
                'title' => 'Removed from Trip',
                'message' => 'You have been removed from a vehicle request by ' . currentUser()->name . '.',
                'link' => '/?page=requests&action=view&id=' . $requestId
            ];
        } else {
            db()->delete('request_passengers', 'request_id = ? AND guest_name = ?', [$requestId, $identifier]);
        }
    }

    // Add new passengers
    $startDatetime = post('start_datetime', '');
    foreach ($added as $val) {
        if (is_numeric($val)) {
            db()->insert('request_passengers', [
                'request_id' => $requestId,
                'user_id' => (int) $val,
                'created_at' => date(DATETIME_FORMAT)
            ]);
            $notifications[] = [
                'user_id' => (int) $val,
                'type' => 'added_to_request',
                'title' => 'Added to Vehicle Request',
                'message' => currentUser()->name . ' has added you as a passenger for a trip to ' . $destination . ' on ' . date('M j, Y', strtotime($startDatetime)) . '.',
                'link' => '/?page=requests&action=view&id=' . $requestId
            ];
        } else {
            db()->insert('request_passengers', [
                'request_id' => $requestId,
                'guest_name' => $val,
                'created_at' => date(DATETIME_FORMAT)
            ]);
        }
    }

    return $notifications;
}

/**
 * Get passenger identifiers from POST data
 * @return array Array of passenger identifiers (IDs and guest names)
 */
function getRequestPassengerIds(): array
{
    $passengerIds = $_POST['passengers'] ?? [];

    // Filter out empty values
    $passengerIds = array_filter($passengerIds, function($p) {
        return !empty(trim($p));
    });

    return $passengerIds;
}

/**
 * Calculate passenger count (passengers + requester)
 * @param array $passengerIds Selected passenger IDs
 * @return int Total passenger count including requester
 */
function calculatePassengerCount(array $passengerIds): int
{
    return count($passengerIds) + 1; // +1 for requester
}

/**
 * Process request creation
 * @param array $data Form data
 * @return FormResult Result with ID set
 */
function processRequestCreate(array $data): FormResult
{
    $result = new FormResult();

    try {
        $startDatetime = trim($data['start_datetime']);
        $endDatetime = trim($data['end_datetime']);
        $purpose = trim($data['purpose']);
        $destination = trim($data['destination']);
        $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        $notes = trim($data['notes'] ?? '');
        $approverId = (int) $data['approver_id'];
        $motorpoolHeadId = (int) $data['motorpool_head_id'];
        $requestedDriverId = !empty($data['requested_driver_id']) ? (int) $data['requested_driver_id'] : null;
        $passengerIds = $data['passengers_ids'] ?? [];
        $passengerCount = calculatePassengerCount($passengerIds);

        db()->beginTransaction();

        // Insert request
        $requestId = db()->insert('requests', [
            'user_id' => userId(),
            'department_id' => currentUser()->department_id,
            'approver_id' => $approverId,
            'motorpool_head_id' => $motorpoolHeadId,
            'requested_driver_id' => $requestedDriverId,
            'vehicle_id' => $vehicleId,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'purpose' => $purpose,
            'destination' => $destination,
            'passenger_count' => $passengerCount,
            'notes' => $notes,
            'status' => STATUS_PENDING,
            'created_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT)
        ]);

        // Insert passengers
        foreach ($passengerIds as $p) {
            if (is_numeric($p)) {
                db()->insert('request_passengers', [
                    'request_id' => $requestId,
                    'user_id' => (int) $p,
                    'created_at' => date(DATETIME_FORMAT)
                ]);
            } else {
                db()->insert('request_passengers', [
                    'request_id' => $requestId,
                    'guest_name' => trim($p),
                    'created_at' => date(DATETIME_FORMAT)
                ]);
            }
        }

        // Recalculate actual passenger count
        $actualPassengerCount = db()->fetch(
            "SELECT COUNT(*) + 1 as count FROM request_passengers WHERE request_id = ?",
            [$requestId]
        )->count;

        db()->update('requests', [
            'passenger_count' => $actualPassengerCount
        ], 'id = ?', [$requestId]);

        // Create approval workflow record
        db()->insert('approval_workflow', [
            'request_id' => $requestId,
            'department_id' => currentUser()->department_id,
            'step' => 'department',
            'status' => 'pending',
            'created_at' => date(DATETIME_FORMAT),
            'updated_at' => date(DATETIME_FORMAT)
        ]);

        // Audit log
        auditLog('request_created', 'request', $requestId, null, [
            'purpose' => $purpose,
            'destination' => $destination,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'passenger_count' => $passengerCount,
            'approver_id' => $approverId,
            'motorpool_head_id' => $motorpoolHeadId,
            'requested_driver_id' => $requestedDriverId
        ]);

        db()->commit();

        $result->success = true;
        $result->id = $requestId;
        $result->message = 'Request submitted successfully! Awaiting approval.';
        $result->redirectUrl = '/?page=requests';
        $result->data = [
            'request_id' => $requestId,
            'approver_id' => $approverId,
            'motorpool_head_id' => $motorpoolHeadId,
            'requested_driver_id' => $requestedDriverId,
            'destination' => $destination,
            'start_datetime' => $startDatetime
        ];

    } catch (Exception $e) {
        db()->rollback();
        $result->addError('Failed to submit request. Please try again.');
        error_log("Request creation error: " . $e->getMessage());
    }

    return $result;
}

/**
 * Process request update
 * @param int $requestId Request ID
 * @param array $data Form data
 * @param object $request Existing request object
 * @param array $currentPassengerIdentifiers Current passenger identifiers
 * @return FormResult Result with ID set
 */
function processRequestUpdate(int $requestId, array $data, $request, array $currentPassengerIdentifiers): FormResult
{
    $result = new FormResult();

    try {
        $startDatetime = trim($data['start_datetime']);
        $endDatetime = trim($data['end_datetime']);
        $purpose = trim($data['purpose']);
        $destination = trim($data['destination']);
        $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        $notes = trim($data['notes'] ?? '');
        $approverId = (int) $data['approver_id'];
        $motorpoolHeadId = (int) $data['motorpool_head_id'];
        $requestedDriverId = !empty($data['requested_driver_id']) ? (int) $data['requested_driver_id'] : null;
        $passengerIds = $data['passengers_ids'] ?? [];

        db()->beginTransaction();

        $oldData = (array) $request;
        $deferredNotifications = [];

        // If request was in revision status, reset to pending for resubmission
        $wasRevision = ($request->status === STATUS_REVISION);
        $updateData = [
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'purpose' => $purpose,
            'destination' => $destination,
            'notes' => $notes,
            'vehicle_id' => $vehicleId,
            'approver_id' => $approverId,
            'motorpool_head_id' => $motorpoolHeadId,
            'requested_driver_id' => $requestedDriverId,
            'updated_at' => date(DATETIME_FORMAT)
        ];

        if ($wasRevision) {
            $revisionApproval = db()->fetch(
                "SELECT approval_type FROM approvals WHERE request_id = ? AND status = 'revision' ORDER BY created_at DESC LIMIT 1",
                [$requestId]
            );

            if ($revisionApproval && $revisionApproval->approval_type === 'motorpool') {
                $updateData['status'] = STATUS_PENDING_MOTORPOOL;
            } else {
                $updateData['status'] = STATUS_PENDING;
            }
            $updateData['viewed_at'] = null;

            $notifyApproverId = ($revisionApproval && $revisionApproval->approval_type === 'motorpool')
                ? $request->motorpool_head_id
                : $request->approver_id;

            $approver = db()->fetch(
                "SELECT id, name, email FROM users WHERE id = ?",
                [$notifyApproverId]
            );

            if ($approver) {
                $deferredNotifications[] = [
                    'user_id' => $approver->id,
                    'type' => 'request_submitted',
                    'title' => 'Request Resubmitted for Approval',
                    'message' => currentUser()->name . " has resubmitted a vehicle request for {$destination} on " . date('M j, Y', strtotime($startDatetime)) . " after revision. Please review the updated request.",
                    'link' => '/?page=approvals&action=view&id=' . $requestId
                ];
            }
        }

        db()->update('requests', $updateData, 'id = ?', [$requestId]);

        // Sync passengers
        $passengerNotifications = syncRequestPassengers($requestId, $passengerIds, $currentPassengerIdentifiers);
        $deferredNotifications = array_merge($deferredNotifications, $passengerNotifications);

        // Recalculate actual passenger count
        $actualPassengerCount = db()->fetch(
            "SELECT COUNT(*) + 1 as count FROM request_passengers WHERE request_id = ?",
            [$requestId]
        )->count;

        db()->update('requests', [
            'passenger_count' => $actualPassengerCount
        ], 'id = ?', [$requestId]);

        // Check if details changed - notify existing passengers
        $detailsChanged = (
            $oldData['destination'] !== $destination ||
            $oldData['start_datetime'] !== $startDatetime ||
            $oldData['end_datetime'] !== $endDatetime
        );

        $unchanged = array_intersect($currentPassengerIdentifiers, array_map('trim', $passengerIds));
        if ($detailsChanged && !empty($unchanged)) {
            foreach ($unchanged as $id) {
                if (is_numeric($id)) {
                    $deferredNotifications[] = [
                        'user_id' => (int) $id,
                        'type' => 'request_modified',
                        'title' => 'Trip Details Updated',
                        'message' => 'A trip you are part of has been modified by ' . currentUser()->name . '.',
                        'link' => '/?page=requests&action=view&id=' . $requestId
                    ];
                }
            }
        }

        // Notify requester if details changed
        if (
            $oldData['destination'] !== $destination ||
            $oldData['start_datetime'] !== $startDatetime ||
            $oldData['end_datetime'] !== $endDatetime ||
            $oldData['purpose'] !== $purpose
        ) {
            $deferredNotifications[] = [
                'user_id' => $request->user_id,
                'type' => 'request_modified',
                'title' => 'Trip Details Updated',
                'message' => 'Your vehicle request has been modified. Please review the updated details.',
                'link' => '/?page=requests&action=view&id=' . $requestId
            ];
        }

        auditLog('request_updated', 'request', $requestId, $oldData, [
            'purpose' => $purpose,
            'destination' => $destination,
            'passenger_count' => count($passengerIds) + 1
        ]);

        db()->commit();

        $result->success = true;
        $result->id = $requestId;
        $result->message = $wasRevision
            ? 'Request resubmitted successfully. It will be reviewed again by the approver.'
            : 'Request updated successfully.';
        $result->redirectUrl = '/?page=requests&action=view&id=' . $requestId;
        $result->data = [
            'notifications' => $deferredNotifications,
            'was_revision' => $wasRevision,
            'details_changed' => $detailsChanged
        ];

    } catch (Exception $e) {
        db()->rollback();
        $result->addError('Failed to update request. Please try again.');
        error_log("Request update error: " . $e->getMessage());
    }

    return $result;
}

/**
 * Send notifications for request creation
 * @param FormResult $result Result from processRequestCreate
 */
function sendRequestCreationNotifications(FormResult $result): void
{
    if (!$result->isSuccess() || empty($result->data)) {
        return;
    }

    $data = $result->data;
    $requestId = $data['request_id'];

    // Queue requester confirmation
    notify(
        userId(),
        'request_confirmation',
        'Request Submitted Successfully',
        'Your vehicle request to ' . $data['destination'] . ' on ' . date('M j, Y g:i A', strtotime($data['start_datetime'])) . ' has been submitted and is awaiting approval.',
        '/?page=requests&action=view&id=' . $requestId
    );

    // Queue approver notification
    notify(
        $data['approver_id'],
        'request_submitted',
        'New Request Awaiting Your Approval',
        currentUser()->name . ' submitted a vehicle request for ' . $data['destination'] . ' on ' . date('M j, Y', strtotime($data['start_datetime'])) . '. You have been selected as the approver.',
        '/?page=approvals&action=view&id=' . $requestId
    );

    // Queue motorpool head notification
    notify(
        $data['motorpool_head_id'],
        'request_submitted_motorpool',
        'New Vehicle Request Submitted',
        currentUser()->name . ' submitted a vehicle request for ' . $data['destination'] . ' on ' . date('M j, Y', strtotime($data['start_datetime'])) . '. This request is now pending department approval.',
        '/?page=approvals&action=view&id=' . $requestId
    );

    // Notify passengers using batch function
    notifyPassengersBatch(
        $requestId,
        'added_to_request',
        'Added to Vehicle Request',
        currentUser()->name . ' has added you as a passenger for a trip to ' . $data['destination'] . ' on ' . date('M j, Y', strtotime($data['start_datetime'])) . '. The request is now awaiting approval.',
        '/?page=requests&action=view&id=' . $requestId
    );

    // Notify requested driver if specified
    if (!empty($data['requested_driver_id'])) {
        notifyDriver(
            $data['requested_driver_id'],
            'driver_requested',
            'You Have Been Requested as Driver',
            currentUser()->name . ' has requested you as the driver for a trip to ' . $data['destination'] . ' on ' . date('M j, Y g:i A', strtotime($data['start_datetime'])) . '. The request is pending approval and you will be notified once approved.',
            '/?page=requests&action=view&id=' . $requestId
        );
    }
}

/**
 * Send notifications for request update
 * @param FormResult $result Result from processRequestUpdate
 */
function sendRequestUpdateNotifications(FormResult $result): void
{
    if (!$result->isSuccess() || empty($result->data)) {
        return;
    }

    $data = $result->data;
    if (!empty($data['notifications'])) {
        foreach ($data['notifications'] as $notif) {
            notify($notif['user_id'], $notif['type'], $notif['title'], $notif['message'], $notif['link']);
        }
    }
}

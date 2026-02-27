<?php
/**
 * Mass Email Blast for User Password Reset
 * Standalone page - use once and delete
 */

// Database configuration - UPDATE THESE
$dbHost = 'localhost';
$dbName = 'lokaloka2';
$dbUser = 'root';
$dbPass = '';

// Default SMTP Settings
$smtpHost = 'smtp.gmail.com';
$smtpPort = '465';
$smtpEncryption = 'ssl';
$smtpUsername = 'jelite.demo@gmail.com';
$smtpPassword = 'jusa weqs eegz wxlg';
$smtpFromEmail = 'jelite.demo@gmail.com';
$smtpFromName = 'LOKA Fleet Management';

$defaultPassword = 'password123';
$loginUrl = 'https://lokafleet.dictr2.online/';

// Output variables
$message = '';
$error = '';
$successCount = 0;
$failedCount = 0;
$users = [];
$dbConnected = false;
$emailResults = [];
$testResult = '';
$debugLog = '';

// ========================================================================
// FUNCTION DEFINITIONS - Must be defined before POST handlers that use them
// ========================================================================

$maintenanceStart = 'February 27, 2026 at 9:00 AM';
$maintenanceEnd = 'March 1, 2026 at 11:59 PM';
$additionalMessage = 'We apologize for any inconvenience this may cause. Thank you for your understanding and patience.';

function buildEmailTemplate(string $userName, string $userEmail, string $fromName, string $loginUrl, string $maintenanceStart, string $maintenanceEnd, string $additionalMessage): string
{
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px;">
        <tr>
            <td align="center">
                <table width="650" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background:linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);padding:40px 30px;text-align:center;">
                            <div style="margin-bottom:10px;">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                </svg>
                            </div>
                            <h1 style="color:#ffffff;margin:0;font-size:28px;font-weight:bold;">System Maintenance Notice</h1>
                            <p style="color:#ffffff;margin:10px 0 0 0;font-size:14px;opacity:0.9;">LOKA Fleet Management</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 40px 20px 40px;">
                            <h2 style="color:#1a1a1a;margin:0 0 15px 0;font-size:22px;font-weight:600;">Scheduled System Maintenance</h2>
                            <p style="color:#555555;font-size:15px;line-height:1.7;margin:0;">
                                Dear <strong style="color:#dc3545;">' . htmlspecialchars($userName) . '</strong>,
                            </p>
                            <p style="color:#555555;font-size:15px;line-height:1.7;margin:15px 0;">
                                We would like to inform you that the <strong>LOKA Fleet Management System</strong> will undergo scheduled maintenance to improve system performance and reliability.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg, #fff3cd 0%, #ffc107 100%);border-radius:10px;border:1px solid #ffc107;">
                                <tr>
                                    <td style="padding:25px;text-align:center;">
                                        <h3 style="color:#856404;margin:0 0 15px 0;font-size:18px;font-weight:600;">Maintenance Schedule</h3>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid rgba(133, 100, 4, 0.3);text-align:center;">
                                                    <span style="color:#856404;font-size:13px;">START</span><br>
                                                    <strong style="color:#1a1a1a;font-size:18px;">' . htmlspecialchars($maintenanceStart) . '</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid rgba(133, 100, 4, 0.3);text-align:center;">
                                                    <span style="color:#856404;font-size:13px;">END</span><br>
                                                    <strong style="color:#1a1a1a;font-size:18px;">' . htmlspecialchars($maintenanceEnd) . '</strong>
                                                </td>
                                            </tr>
                                        </table>
                                        <div style="background:#ffffff;border-radius:8px;padding:15px;margin-top:15px;">
                                            <p style="margin:0;color:#666666;font-size:14px;line-height:1.6;">' . htmlspecialchars($additionalMessage) . '</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 20px 40px;">
                            <h3 style="color:#1a1a1a;margin:0 0 15px 0;font-size:16px;font-weight:600;">What You Can Expect:</h3>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr><td style="padding:10px 0;border-bottom:1px solid #eee;"><span style="color:#dc3545;margin-right:8px;">⚠</span>System will be temporarily unavailable</td></tr>
                                <tr><td style="padding:10px 0;border-bottom:1px solid #eee;"><span style="color:#dc3545;margin-right:8px;">⚠</span>No vehicle reservations can be processed</td></tr>
                                <tr><td style="padding:10px 0;border-bottom:1px solid #eee;"><span style="color:#dc3545;margin-right:8px;">⚠</span>All pending requests will be held until system restore</td></tr>
                                <tr><td style="padding:10px 0;"><span style="color:#198754;margin-right:8px;">✓</span>All data will be preserved safely</td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 20px 40px;background:#f8f9fa;border-radius:10px;margin:0 40px;">
                            <h4 style="color:#1a1a1a;margin:20px 0 10px 0;font-size:14px;font-weight:600;">Emergency Contact</h4>
                            <p style="color:#666666;font-size:13px;margin:0;line-height:1.6;">For urgent matters during the maintenance period, please contact <strong>JE LITE (System Administrator)</strong> at <strong>jelite.demo@gmail.com</strong> or call <strong>+63 992 631 6210</strong>.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 30px 40px;text-align:center;">
                            <p style="color:#999999;font-size:14px;margin:0;">We appreciate your patience and understanding.</p>
                            <p style="color:#1a1a1a;font-size:16px;margin:15px 0 0 0;font-weight:600;">Thank you!</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#1a1a1a;padding:20px 40px;text-align:center;">
                            <p style="color:#999999;font-size:12px;margin:0;">This is an automated message from <strong style="color:#ffffff;">LOKA Fleet Management System</strong></p>
                            <p style="color:#666666;font-size:11px;margin:8px 0 0 0;">Department of Information and Communications Technology</p>
                            <p style="color:#666666;font-size:11px;margin:8px 0 0 0;">Please do not reply to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

function sendEmailDebug($host, $port, $username, $password, $fromEmail, $fromName, $encryption, $to, $toName, $subject, $body, &$log) {
    $log = '';

    // Try using PHP mail() first (works on most hosting)
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "From: $fromName <$fromEmail>";
    $headers[] = "Reply-To: $fromEmail";

    $toFormatted = $toName ? "$toName <$to>" : $to;

    if (mail($toFormatted, $subject, $body, implode("\r\n", $headers))) {
        $log .= "Sent via PHP mail() successfully<br>";
        return ['success' => true, 'error' => '', 'log' => $log];
    }

    $log .= "PHP mail() failed, trying SMTP...<br>";

    // Fallback to SMTP
    $log .= "Connecting to $host:$port (encryption: $encryption)...<br>";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client(
        ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        $log .= "✗ Connection failed: $errstr ($errno)<br>";
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)", 'log' => $log];
    }

    $log .= "✓ Connected<br>";

    stream_set_timeout($socket, 30);

    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '220') {
        $log .= "✗ Server not ready<br>";
        fclose($socket);
        return ['success' => false, 'error' => "Server not ready: $response", 'log' => $log];
    }

    $log .= "C: EHLO " . gethostname() . "<br>";
    fwrite($socket, "EHLO " . gethostname() . "\r\n");

    // Read all lines of EHLO response (multi-line response ends with space after code)
    while (($response = fgets($socket, 515)) !== false) {
        $log .= "S: " . trim($response) . "<br>";
        if (substr($response, 3, 1) === ' ') break; // Last line has space after code
    }

    // Handle STARTTLS for TLS encryption
    if ($encryption === 'tls') {
        $log .= "C: STARTTLS<br>";
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        $log .= "S: $response<br>";

        if (substr($response, 0, 3) !== '220') {
            $log .= "✗ STARTTLS failed<br>";
            fclose($socket);
            return ['success' => false, 'error' => "STARTTLS failed: $response", 'log' => $log];
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            $log .= "✗ TLS encryption failed<br>";
            fclose($socket);
            return ['success' => false, 'error' => "TLS encryption failed", 'log' => $log];
        }

        $log .= "✓ TLS enabled<br>";

        // Send EHLO again after TLS
        $log .= "C: EHLO (after TLS)<br>";
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        while (($response = fgets($socket, 515)) !== false) {
            $log .= "S: " . trim($response) . "<br>";
            if (substr($response, 3, 1) === ' ') break;
        }
    }

    // Authenticate
    $authString = base64_encode("\0" . $username . "\0" . str_replace(' ', '', $password));
    $log .= "C: AUTH PLAIN [credentials]<br>";
    fwrite($socket, "AUTH PLAIN " . $authString . "\r\n");
    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '235') {
        $log .= "✗ Authentication failed<br>";
        fclose($socket);
        return ['success' => false, 'error' => "Authentication failed. Check username/password.", 'log' => $log];
    }

    $log .= "✓ Authenticated<br>";

    // Set sender
    $log .= "C: MAIL FROM:<$fromEmail><br>";
    fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '250') {
        $log .= "✗ MAIL FROM rejected<br>";
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM rejected: $response", 'log' => $log];
    }

    // Set recipient
    $log .= "C: RCPT TO:<$to><br>";
    fwrite($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '250') {
        $log .= "✗ RCPT TO rejected<br>";
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO rejected: $response", 'log' => $log];
    }

    // Send data
    $log .= "C: DATA<br>";
    fwrite($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '354') {
        $log .= "✗ DATA command failed<br>";
        fclose($socket);
        return ['success' => false, 'error' => "DATA command failed: $response", 'log' => $log];
    }

    // Build email headers and body
    $headers = "Date: " . date('r') . "\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    $headers .= "To: " . ($toName ? "$toName <$to>" : $to) . "\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: LOKA Fleet Management\r\n";

    $log .= "C: [Email headers and body]<br>";
    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $response = fgets($socket, 515);
    $log .= "S: $response<br>";

    if (substr($response, 0, 3) !== '250') {
        $log .= "✗ Message not accepted<br>";
        fclose($socket);
        return ['success' => false, 'error' => "Message not accepted: $response", 'log' => $log];
    }

    $log .= "✓ Message sent successfully<br>";

    // Quit
    $log .= "C: QUIT<br>";
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return ['success' => true, 'error' => '', 'log' => $log];
}

// ========================================================================
// DATABASE CONNECTION
// ========================================================================

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SELECT id, name, email, role FROM users WHERE status = 'active' ORDER BY name");
    $users = $stmt->fetchAll();
    $dbConnected = true;
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// ========================================================================
// POST HANDLERS
// ========================================================================

// AJAX send single email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_single_email') {
    // Clear any existing output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Start fresh output buffer
    ob_start();

    // Disable error display (log to file instead)
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    $response = ['success' => false, 'error' => 'Unknown error'];

    try {
        $userId = $_POST['user_id'] ?? 0;
        $smtpHost = $_POST['smtp_host'] ?? '';
        $smtpPort = $_POST['smtp_port'] ?? '587';
        $smtpUsername = $_POST['smtp_username'] ?? '';
        $smtpPassword = $_POST['smtp_password'] ?? '';
        $smtpFromEmail = $_POST['smtp_from_email'] ?? '';
        $smtpFromName = $_POST['smtp_from_name'] ?? 'LOKA Fleet Management';
        $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';

        // Find user
        $user = null;
        foreach ($users as $u) {
            if ($u['id'] == $userId) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            throw new Exception('User not found');
        }

        $subject = "LOKA Fleet Management - Scheduled System Maintenance Notice";
        $body = buildEmailTemplate($user['name'], $user['email'], $smtpFromName, $loginUrl, $maintenanceStart, $maintenanceEnd, $additionalMessage);

        $debugLog = '';
        $result = sendEmailDebug($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromEmail, $smtpFromName, $smtpEncryption, $user['email'], $user['name'], $subject, $body, $debugLog);

        // Clean log for JSON - remove any problematic characters
        $cleanLog = isset($result['log']) ? mb_convert_encoding($result['log'], 'UTF-8', 'UTF-8') : '';

        $response = [
            'success' => $result['success'],
            'error' => $result['error'] ?? '',
            'log' => $cleanLog,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ];
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    // Clear any output that might have been generated
    ob_end_clean();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

// Preview mode - works on localhost
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $selectedUsers = $_POST['selected_users'] ?? [];
    if (empty($selectedUsers)) {
        $error = "Please select at least one user";
    } else {
        $previewEmails = [];
        foreach ($users as $user) {
            if (in_array($user['id'], $selectedUsers)) {
                $previewEmails[] = [
                    'email' => $user['email'],
                    'name' => $user['name']
                ];
            }
        }

        // Create CSV content
        $csvContent = "Name,Email\n";
        foreach ($previewEmails as $p) {
            $csvContent .= '"' . $p['name'] . '","' . $p['email'] . '"' . "\n";
        }

        // Download CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="email_list.csv"');
        header('Content-Length: ' . strlen($csvContent));
        echo $csvContent;
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    $smtpHost = $_POST['smtp_host'] ?? '';
    $smtpPort = $_POST['smtp_port'] ?? '465';
    $smtpUsername = $_POST['smtp_username'] ?? '';
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $smtpFromEmail = $_POST['smtp_from_email'] ?? '';
    $smtpFromName = $_POST['smtp_from_name'] ?? 'LOKA Fleet Management';
    $smtpEncryption = $_POST['smtp_encryption'] ?? 'ssl';
    $testEmail = $_POST['test_email'] ?? '';

    if (empty($testEmail)) {
        $testResult = '<div class="alert alert-danger">Please enter your email address to test</div>';
    } elseif (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword) || empty($smtpFromEmail)) {
        $testResult = '<div class="alert alert-danger">Please fill in all SMTP fields first</div>';
    } else {
        $subject = "LOKA Fleet Management - Test Email (Maintenance Notice)";
        $body = buildEmailTemplate("User", $testEmail, $smtpFromName, $loginUrl, $maintenanceStart, $maintenanceEnd, $additionalMessage);

        $debugLog = "Connecting to $smtpHost:$smtpPort...<br>";

        $result = sendEmailDebug($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromEmail, $smtpFromName, $smtpEncryption, $testEmail, "User", $subject, $body, $debugLog);

        if ($result['success']) {
            $testResult = '<div class="alert alert-success">✓ Test email sent successfully to ' . htmlspecialchars($testEmail) . '! Check your inbox.</div>';
        } else {
            $testResult = '<div class="alert alert-danger">✗ Failed: ' . htmlspecialchars($result['error']) . '<br><br><strong>Debug Log:</strong><br><pre style="font-size:11px;max-height:200px;overflow:auto;">' . htmlspecialchars($result['log']) . '</pre></div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_emails') {
    $smtpHost = $_POST['smtp_host'] ?? '';
    $smtpPort = $_POST['smtp_port'] ?? '587';
    $smtpUsername = $_POST['smtp_username'] ?? '';
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $smtpFromEmail = $_POST['smtp_from_email'] ?? '';
    $smtpFromName = $_POST['smtp_from_name'] ?? 'LOKA Fleet Management';
    $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';

    if (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword) || empty($smtpFromEmail)) {
        $error = "Please fill in all SMTP fields";
    } else {
        $selectedUsers = $_POST['selected_users'] ?? [];

        if (empty($selectedUsers)) {
            $error = "Please select at least one user";
        } else {
            foreach ($selectedUsers as $userId) {
                $user = null;
                foreach ($users as $u) {
                    if ($u['id'] == $userId) {
                        $user = $u;
                        break;
                    }
                }

                if (!$user) continue;

                $subject = "LOKA Fleet Management - Scheduled System Maintenance Notice";
                $body = buildEmailTemplate($user['name'], $user['email'], $smtpFromName, $loginUrl, $maintenanceStart, $maintenanceEnd, $additionalMessage);

                $debugLog = '';
                $result = sendEmailDebug($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromEmail, $smtpFromName, $smtpEncryption, $user['email'], $user['name'], $subject, $body, $debugLog);

                if ($result['success']) {
                    $successCount++;
                    $emailResults[] = ['email' => $user['email'], 'name' => $user['name'], 'status' => 'success'];
                } else {
                    $failedCount++;
                    $emailResults[] = ['email' => $user['email'], 'name' => $user['name'], 'status' => 'failed', 'error' => $result['error']];
                }
            }

            if ($successCount > 0 || $failedCount > 0) {
                $message = "Emails sent: $successCount successful, $failedCount failed";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Email Blast - LOKA Fleet Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .card-header { background-color: #0d6efd; color: white; font-weight: 600; }
        .user-list { max-height: 400px; overflow-y: auto; }
        .user-item { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .user-item:hover { background-color: #f8f9fa; }
        .user-item:last-child { border-bottom: none; }
        .progress-item { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .progress-item:last-child { border-bottom: none; }
        .progress-item.sending { background-color: #fff3cd; }
        .progress-item.success { background-color: #d1e7dd; }
        .progress-item.failed { background-color: #f8d7da; }
        .progress-status { font-size: 11px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Mass Email Blast - System Maintenance Notice</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($emailResults)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-<?php echo $failedCount > 0 ? 'warning' : 'success'; ?>">
                                <h5 class="mb-0 text-white">Email Results</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="text-center p-3 bg-success text-white rounded">
                                            <h3><?php echo $successCount; ?></h3>
                                            <small>Successful</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 bg-danger text-white rounded">
                                            <h3><?php echo $failedCount; ?></h3>
                                            <small>Failed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 bg-primary text-white rounded">
                                            <h3><?php echo count($emailResults); ?></h3>
                                            <small>Total</small>
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-sm table-bordered" style="font-size:13px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emailResults as $result): ?>
                                        <tr class="<?php echo $result['status'] === 'success' ? 'table-success' : 'table-danger'; ?>">
                                            <td>
                                                <?php if ($result['status'] === 'success'): ?>
                                                    <span class="badge bg-success">✓ Sent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger" title="<?php echo isset($result['error']) ? htmlspecialchars($result['error']) : ''; ?>">✗ Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['email']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (!$dbConnected): ?>
                            <div class="alert alert-warning">
                                <h5>Database Not Connected</h5>
                                <p>Please edit this file and update the database configuration at the top:</p>
                                <pre style="background:#f8f9fa;padding:15px;border-radius:5px;">$dbHost = "localhost";
$dbName = "lokaloka2";
$dbUser = "root";
$dbPass = "";</pre>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <h5 class="mb-3">SMTP Configuration</h5>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Your Email (for testing)</label>
                                    <input type="email" name="test_email" class="form-control" value="<?php echo htmlspecialchars($smtpUsername); ?>">
                                    <small class="text-muted">Enter your email to test SMTP settings first</small>
                                </div>
                                <div class="col-md-6 mb-3 d-flex align-items-end gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="testEmail()">Test SMTP</button>
                                    <button type="button" class="btn btn-outline-info" onclick="previewEmail()">Preview Template</button>
                                </div>
                            </div>
                            <div id="testResult">
                                <?php if ($testResult): ?>
                                    <?php echo $testResult; ?>
                                <?php endif; ?>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtpHost); ?>" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtpPort); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Encryption</label>
                                    <select name="smtp_encryption" class="form-select">
                                        <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                        <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Username (Email)</label>
                                    <input type="email" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtpUsername); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($smtpPassword); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">From Email</label>
                                    <input type="email" name="smtp_from_email" class="form-control" value="<?php echo htmlspecialchars($smtpFromEmail); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" name="smtp_from_name" class="form-control" value="<?php echo htmlspecialchars($smtpFromName); ?>">
                                </div>
                            </div>

                            <hr>

                            <?php if ($dbConnected): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Select Users (<?php echo count($users); ?> total)</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="selectAll()">Select All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">Deselect All</button>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-body user-list">
                                    <?php if (empty($users)): ?>
                                        <p class="text-muted text-center py-3">No users found in database</p>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <div class="user-item">
                                                <div class="form-check">
                                                    <input class="form-check-input user-checkbox" type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" id="user_<?php echo $user['id']; ?>">
                                                    <label class="form-check-label" for="user_<?php echo $user['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                        <span class="text-muted"> - <?php echo htmlspecialchars($user['email']); ?></span>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <strong>Maintenance Period:</strong> <?php echo htmlspecialchars($maintenanceStart); ?> to <?php echo htmlspecialchars($maintenanceEnd); ?>
                                <br><strong>Additional Message:</strong> <?php echo htmlspecialchars($additionalMessage); ?>
                            </div>

                            <div class="d-flex gap-2 mb-3">
                                <button type="button" id="sendEmailsBtn" class="btn btn-primary btn-lg" onclick="startSendingEmails()">
                                    Send Emails (0 selected)
                                </button>
                                <button type="submit" name="action" value="preview" class="btn btn-outline-secondary btn-lg">
                                    Download Email List (CSV)
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <span id="progressTitle">Sending Emails...</span>
                        <span id="progressCounter" class="badge bg-light text-dark ms-2">0 / 0</span>
                    </h5>
                </div>
                <div class="modal-body">
                    <!-- Progress Bar -->
                    <div class="progress mb-3" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <!-- Stats -->
                    <div class="row mb-3">
                        <div class="col-4">
                            <div class="text-center p-2 bg-light rounded">
                                <h4 id="totalCount" class="mb-0">0</h4>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 bg-success text-white rounded">
                                <h4 id="sentCount" class="mb-0">0</h4>
                                <small>Sent</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 bg-danger text-white rounded">
                                <h4 id="failedCount" class="mb-0">0</h4>
                                <small>Failed</small>
                            </div>
                        </div>
                    </div>

                    <!-- Current Status -->
                    <div id="currentStatus" class="alert alert-info mb-3">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        <span id="statusText">Preparing to send...</span>
                    </div>

                    <!-- User Progress List -->
                    <div class="card">
                        <div class="card-header py-2">
                            <small class="mb-0"><strong>Email Sending Progress</strong></small>
                        </div>
                        <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                            <div id="progressList" class="list-group list-group-flush">
                                <!-- Progress items will be added here dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="closeProgressBtn" class="btn btn-secondary" data-bs-dismiss="modal" disabled>Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        }
        function deselectAll() {
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
            const btn = document.getElementById('sendEmailsBtn');
            if (btn) {
                btn.innerHTML = 'Send Emails (' + selectedCount + ' selected)';
                // Disable button if no users selected
                btn.disabled = selectedCount === 0;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Attach change event to all checkboxes
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
            // Initialize count on page load
            updateSelectedCount();
        });

        function testEmail() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.set('action', 'test_email');

            const testEmail = formData.get('test_email');
            if (!testEmail) {
                document.getElementById('testResult').innerHTML = '<div class="alert alert-warning">Please enter your email address first</div>';
                return;
            }

            document.getElementById('testResult').innerHTML = '<div class="alert alert-info">Sending test email...</div>';

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const result = doc.getElementById('testResult');
                if (result) {
                    document.getElementById('testResult').innerHTML = result.innerHTML;
                }
            })
            .catch(err => {
                document.getElementById('testResult').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
            });
        }

        function previewEmail() {
            const previewContent = `<?php echo preg_replace('/\s+/', ' ', buildEmailTemplate("John Doe User", "john.doe@example.com", $smtpFromName, $loginUrl, $maintenanceStart, $maintenanceEnd, $additionalMessage)); ?>`;
            const win = window.open('', 'Preview', 'width=700,height=800');
            win.document.write('<html><head><title>Email Preview</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body style="padding:20px;background:#f5f5f5;">' + previewContent + '</body></html>');
        }

        // Store all user data for sending
        const usersData = <?php echo json_encode($users); ?>;

        // Progress tracking
        let progressModal;
        let sentCount = 0;
        let failedCount = 0;
        let currentIndex = 0;
        let selectedUsers = [];

        async function startSendingEmails() {
            const form = document.querySelector('form');
            const formData = new FormData(form);

            // Get selected users
            selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => parseInt(cb.value));

            if (selectedUsers.length === 0) {
                alert('Please select at least one user');
                return;
            }

            // Validate SMTP settings
            const smtpHost = formData.get('smtp_host');
            const smtpUsername = formData.get('smtp_username');
            const smtpPassword = formData.get('smtp_password');
            const smtpFromEmail = formData.get('smtp_from_email');

            if (!smtpHost || !smtpUsername || !smtpPassword || !smtpFromEmail) {
                alert('Please fill in all SMTP fields');
                return;
            }

            // Reset counters
            sentCount = 0;
            failedCount = 0;
            currentIndex = 0;

            // Show modal
            progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
            progressModal.show();

            // Initialize progress
            updateProgressUI(selectedUsers.length);

            // Clear previous progress list
            document.getElementById('progressList').innerHTML = '';

            // Create progress items for each user
            selectedUsers.forEach(userId => {
                const user = usersData.find(u => u.id === userId);
                if (user) {
                    addProgressItem(user);
                }
            });

            // Send emails sequentially
            for (const userId of selectedUsers) {
                const user = usersData.find(u => u.id === userId);
                if (!user) continue;

                currentIndex++;
                updateStatus(user, 'sending');

                try {
                    const response = await sendSingleEmail(userId, formData);
                    if (response.success) {
                        sentCount++;
                        updateStatus(user, 'success');
                    } else {
                        failedCount++;
                        updateStatus(user, 'failed', response.error);
                    }
                } catch (error) {
                    failedCount++;
                    updateStatus(user, 'failed', error.message);
                }

                updateProgressUI(selectedUsers.length);
            }

            // All done
            document.getElementById('statusText').innerHTML = '<strong>Complete!</strong> ' + sentCount + ' sent, ' + failedCount + ' failed';
            document.getElementById('currentStatus').className = sentCount > failedCount ? 'alert alert-success mb-3' : 'alert alert-warning mb-3';
            document.getElementById('progressBar').classList.remove('progress-bar-animated');
            document.getElementById('closeProgressBtn').disabled = false;
        }

        function sendSingleEmail(userId, formData) {
            const sendFormData = new FormData();
            sendFormData.append('action', 'send_single_email');
            sendFormData.append('user_id', userId);
            sendFormData.append('smtp_host', formData.get('smtp_host'));
            sendFormData.append('smtp_port', formData.get('smtp_port'));
            sendFormData.append('smtp_username', formData.get('smtp_username'));
            sendFormData.append('smtp_password', formData.get('smtp_password'));
            sendFormData.append('smtp_from_email', formData.get('smtp_from_email'));
            sendFormData.append('smtp_from_name', formData.get('smtp_from_name'));
            sendFormData.append('smtp_encryption', formData.get('smtp_encryption'));

            return fetch('', {
                method: 'POST',
                body: sendFormData
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // Response is not JSON - return error with first 500 chars of response
                    const preview = text.substring(0, 500);
                    if (text.includes('<') && text.startsWith('<')) {
                        return {
                            success: false,
                            error: 'Server returned HTML instead of JSON. Check PHP error log.',
                            details: preview
                        };
                    }
                    return {
                        success: false,
                        error: 'Invalid response: ' + e.message,
                        details: preview
                    };
                }
            });
        }

        function updateProgressUI(total) {
            const completed = sentCount + failedCount;
            const percentage = Math.round((completed / total) * 100);

            document.getElementById('totalCount').textContent = total;
            document.getElementById('sentCount').textContent = sentCount;
            document.getElementById('failedCount').textContent = failedCount;
            document.getElementById('progressCounter').textContent = completed + ' / ' + total;
            document.getElementById('progressBar').style.width = percentage + '%';
            document.getElementById('progressBar').textContent = percentage + '%';
        }

        function updateStatus(user, status, error = '') {
            const item = document.getElementById('progress-item-' + user.id);
            if (!item) return;

            item.className = 'list-group-item progress-item ' + status;

            let icon = '';
            let text = '';

            switch (status) {
                case 'sending':
                    icon = '<i class="fas fa-spinner fa-spin text-warning"></i>';
                    text = 'Sending...';
                    break;
                case 'success':
                    icon = '<i class="fas fa-check-circle text-success"></i>';
                    text = '<span class="text-success">Sent successfully</span>';
                    break;
                case 'failed':
                    icon = '<i class="fas fa-times-circle text-danger"></i>';
                    text = '<span class="text-danger">Failed: ' + (error || 'Unknown error') + '</span>';
                    break;
            }

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${user.name}</strong>
                        <small class="text-muted d-block">${user.email}</small>
                    </div>
                    <div class="text-end">
                        ${icon}
                        <div class="progress-status">${text}</div>
                    </div>
                </div>
            `;

            // Update current status
            if (status === 'sending') {
                document.getElementById('statusText').innerHTML = 'Sending to <strong>' + user.name + '</strong> (' + user.email + ')...';
            }
        }

        function addProgressItem(user) {
            const list = document.getElementById('progressList');
            const item = document.createElement('div');
            item.id = 'progress-item-' + user.id;
            item.className = 'list-group-item progress-item';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${user.name}</strong>
                        <small class="text-muted d-block">${user.email}</small>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-clock text-muted"></i>
                        <div class="progress-status text-muted">Waiting...</div>
                    </div>
                </div>
            `;
            list.appendChild(item);
        }
    </script>
</body>
</html>

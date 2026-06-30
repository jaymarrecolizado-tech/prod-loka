<?php
/**
 * LOKA Fleet Management - Clear Requests & Notifications (Web Interface)
 *
 * Safe web interface to clear requests and notifications
 * Includes multiple confirmation steps and safety checks
 *
 * Author: Commander Valkyrie Chen
 * Date: January 28, 2026
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

// Security: Only admins can access this
session_start();
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    die('<h1>Access Denied</h1><p>You must be an admin to access this page.</p>');
}

// Production environment check
if (APP_ENV === 'production') {
    die('<h1>Access Denied</h1><p>This operation is disabled in production mode.</p>');
}

$db = Database::getInstance();
$step = getInt('step', 1);
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = postSafe('action', '', 20);

    try {
        if ($action === 'clear') {
            // Disable foreign key checks
            $db->query("SET FOREIGN_KEY_CHECKS = 0");

            // Clear notifications
            $db->query("DELETE FROM notifications");
            $db->query("ALTER TABLE notifications AUTO_INCREMENT = 1");

            // Clear requests (cascades to related tables)
            $db->query("DELETE FROM requests");
            $db->query("ALTER TABLE requests AUTO_INCREMENT = 1");

            // Clear email queue entries related to requests
            $db->query("DELETE FROM email_queue WHERE request_id IS NOT NULL");

            // Re-enable foreign key checks
            $db->query("SET FOREIGN_KEY_CHECKS = 1");

            $message = "Database clearance completed successfully!";
            $messageType = "success";
            $step = 4; // Show completion screen
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get current row counts
$counts = [
    'notifications' => $db->fetch("SELECT COUNT(*) as count FROM notifications")->count,
    'requests' => $db->fetch("SELECT COUNT(*) as count FROM requests")->count,
    'approval_workflow' => $db->fetch("SELECT COUNT(*) as count FROM approval_workflow")->count,
    'approvals' => $db->fetch("SELECT COUNT(*) as count FROM approvals")->count,
    'request_passengers' => $db->fetch("SELECT COUNT(*) as count FROM request_passengers")->count,
    'email_queue' => $db->fetch("SELECT COUNT(*) as count FROM email_queue")->count,
];

function isAdmin() {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'admin';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="loka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Requests & Notifications - LOKA Fleet Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-10 max-w-5xl">
        <div class="flex justify-center">
            <div class="w-full">
                    <div class="loka-card bg-base-100 shadow-xl p-6">
                    <div class="text-center mb-6">
                        <h1 class="text-3xl font-bold text-primary flex items-center justify-center gap-2">
                            <i class="bi bi-trash"></i> Clear Database
                        </h1>
                        <p class="text-base-content/50">Requests &amp; Notifications Management</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="loka-alert <?= $messageType === 'success' ? 'loka-alert-success' : 'loka-alert-danger' ?> mb-4">
                            <span><?= htmlspecialchars($message) ?></span>
                            <button type="button" class="loka-btn-ghost loka-btn-sm" onclick="this.closest('.loka-alert').remove()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <div class="bg-gradient-to-r from-pink-400 to-red-500 text-white text-center p-5 rounded-xl mb-6">
                            <h4 class="text-xl font-bold mb-2"><i class="bi bi-exclamation-triangle-fill"></i> DANGER ZONE</h4>
                            <p class="m-0">This operation will <strong>PERMANENTLY DELETE</strong> all requests and notifications from the database.</p>
                        </div>

                        <h5 class="text-lg font-semibold mb-4"><i class="bi bi-bar-chart"></i> Current Data Overview</h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="loka-card bg-base-200 text-center p-4">
                                <div class="text-4xl font-bold text-primary"><?= number_format($counts['requests']) ?></div>
                                <div class="text-base-content/50">Requests</div>
                            </div>
                            <div class="loka-card bg-base-200 text-center p-4">
                                <div class="text-4xl font-bold text-primary"><?= number_format($counts['notifications']) ?></div>
                                <div class="text-base-content/50">Notifications</div>
                            </div>
                            <div class="loka-card bg-base-200 text-center p-4">
                                <div class="text-4xl font-bold text-primary"><?= number_format($counts['email_queue']) ?></div>
                                <div class="text-base-content/50">Email Queue</div>
                            </div>
                        </div>

                        <h5 class="text-lg font-semibold mb-3"><i class="bi bi-folder2-open"></i> Tables That Will Be Cleared</h5>
                        <div class="loka-card bg-base-200 mb-6">
                            <div class="p-6">
                                <ul class="list-none m-0 p-0">
                                    <li class="mb-2"><i class="bi bi-check-circle text-error"></i> <strong>notifications</strong> - All notification records</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-error"></i> <strong>requests</strong> - All request records</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-error"></i> <strong>approval_workflow</strong> - Auto-cascade from requests</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-error"></i> <strong>approvals</strong> - Auto-cascade from requests</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-error"></i> <strong>request_passengers</strong> - Auto-cascade from requests</li>
                                    <li class="m-0"><i class="bi bi-check-circle text-error"></i> <strong>email_queue</strong> - Request-related emails</li>
                                </ul>
                            </div>
                        </div>

                        <div class="loka-alert loka-alert-warning mb-6">
                            <i class="bi bi-shield-exclamation"></i>
                            <span>
                                <strong>Recommendation:</strong> Create a database backup before proceeding.<br>
                                <code class="text-sm bg-base-200 px-2 py-1 rounded">mysqldump -u user -p fleet_management > backup.sql</code>
                            </span>
                        </div>

                        <div class="flex flex-col gap-2">
                            <a href="?step=2" class="bg-warning text-warning-content hover:bg-warning/90 text-base px-6 py-3 font-medium rounded-xl inline-flex items-center gap-2 transition-colors">
                                <i class="bi bi-arrow-right-circle"></i> I Understand the Risks - Continue
                            </a>
                            <a href="/" class="loka-btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel - Return to Dashboard
                            </a>
                        </div>

                    <?php elseif ($step === 2): ?>
                        <div class="loka-alert loka-alert-danger text-center mb-6">
                            <div>
                                <h4 class="font-bold"><i class="bi bi-exclamation-triangle-fill"></i> FINAL WARNING</h4>
                                <p class="m-0">This action cannot be undone. Once deleted, all data will be permanently lost.</p>
                            </div>
                        </div>

                        <div class="loka-card bg-base-200 mb-6">
                            <div class="p-6">
                                <h5 class="card-title">You are about to delete:</h5>
                                <ul class="list-none">
                                    <li class="mb-2"><strong><?= number_format($counts['requests']) ?></strong> request records</li>
                                    <li class="mb-2"><strong><?= number_format($counts['notifications']) ?></strong> notification records</li>
                                    <li class="mb-2"><strong><?= number_format($counts['approval_workflow']) ?></strong> approval workflow records</li>
                                    <li class="mb-2"><strong><?= number_format($counts['approvals']) ?></strong> approval records</li>
                                    <li class="mb-2"><strong><?= number_format($counts['request_passengers']) ?></strong> request passenger records</li>
                                    <li class="m-0"><strong><?= number_format($counts['email_queue']) ?></strong> email queue records</li>
                                </ul>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="clear">
                            <div class="flex flex-col gap-2">
                                <button type="submit" class="bg-error text-error-content hover:bg-error/90 text-base px-6 py-3 font-medium rounded-xl inline-flex items-center gap-2 transition-colors" onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                                    <i class="bi bi-trash-fill"></i> YES, Delete All Data
                                </button>
                                <a href="/" class="loka-btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </form>

                    <?php elseif ($step === 4): ?>
                        <div class="loka-alert loka-alert-success text-center mb-6">
                            <div>
                                <h4 class="font-bold"><i class="bi bi-check-circle-fill"></i> Clearance Complete</h4>
                                <p class="m-0">All requests and notifications have been successfully removed from the database.</p>
                            </div>
                        </div>

                        <div class="loka-card bg-base-200 mb-6">
                            <div class="p-6">
                                <h5 class="card-title">Summary of Changes:</h5>
                                <ul class="list-none">
                                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> All notification records deleted</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> All request records deleted</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Related approval workflows deleted</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Related approvals deleted</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Related request passengers deleted</li>
                                    <li class="m-0"><i class="bi bi-check-circle text-success"></i> Related email queue entries deleted</li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex">
                            <a href="/" class="loka-btn-primary text-base px-6 py-3 w-full">
                                <i class="bi bi-house"></i> Return to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

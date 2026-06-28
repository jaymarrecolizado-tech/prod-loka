<?php
/**
 * LOKA - Email Queue Management (Admin Only)
 * Enhanced with SMTP diagnostic panel and live test email capability
 */

requireRole(ROLE_ADMIN);

$queue  = new EmailQueue();
$stats  = $queue->getStats();
$messages = [];
$errors   = [];

// ─── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postSafe('action', '', 30);

    if ($action === 'process') {
        $results = $queue->process(50);
        $messages[] = "Queue processed: <strong>{$results['sent']}</strong> sent, "
                    . "<strong>{$results['failed']}</strong> failed, "
                    . "<strong>{$results['skipped']}</strong> skipped.";

    } elseif ($action === 'cleanup') {
        $cleaned = $queue->cleanup(30);
        $messages[] = "Cleaned up <strong>{$cleaned}</strong> old sent emails.";

    } elseif ($action === 'retry_failed') {
        $affected = db()->query(
            "UPDATE email_queue 
             SET status = 'pending', attempts = 0, scheduled_at = NULL, error_message = NULL, updated_at = NOW()
             WHERE status = 'failed'"
        )->rowCount();
        $messages[] = "Reset <strong>{$affected}</strong> failed email(s) back to pending. Click Process Queue to retry.";

    } elseif ($action === 'send_test') {
        $testEmail = postSafe('test_email', '', 200);
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid test email address.";
        } else {
            try {
                $mailer = new Mailer();
                $sent = $mailer->send(
                    $testEmail,
                    'LOKA Email Test - ' . date('Y-m-d H:i:s'),
                    '<h2>✅ Email is working!</h2>
                     <p>This is a test email from the LOKA Fleet Management System.</p>
                     <p><strong>Sent at:</strong> ' . date('Y-m-d H:i:s') . '</p>
                     <p>If you received this, your SMTP configuration is correct.</p>',
                    'Admin'
                );
                if ($sent) {
                    $messages[] = "✅ Test email sent successfully to <strong>" . e($testEmail) . "</strong>!";
                } else {
                    $errs = $mailer->getErrors();
                    $errors[] = "❌ Send failed: " . implode('; ', $errs);
                }
            } catch (Exception $e) {
                $errors[] = "❌ Exception: " . e($e->getMessage());
            }
        }
    }

    // Refresh stats
    $stats = $queue->getStats();
}

// ─── SMTP Config check ──────────────────────────────────────────────────────
$smtpConfigured = !empty(MAIL_HOST) && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD) && !empty(MAIL_FROM_ADDRESS);
$smtpStatus = [
    'MAIL_ENABLED'      => MAIL_ENABLED      ? ['ok', 'true']  : ['fail', 'false (emails disabled!)'],
    'MAIL_HOST'         => MAIL_HOST         ? ['ok', MAIL_HOST]           : ['fail', '(empty — check .env SMTP_HOST)'],
    'MAIL_PORT'         => MAIL_PORT         ? ['ok', MAIL_PORT]           : ['warn', '(empty — defaulting to 587)'],
    'MAIL_ENCRYPTION'   => MAIL_ENCRYPTION   ? ['ok', MAIL_ENCRYPTION]     : ['warn', '(empty — defaulting to tls)'],
    'MAIL_USERNAME'     => MAIL_USERNAME     ? ['ok', substr(MAIL_USERNAME,0,4).'****'] : ['fail', '(empty — check .env SMTP_USER)'],
    'MAIL_PASSWORD'     => MAIL_PASSWORD     ? ['ok', '****** (set)']      : ['fail', '(empty — check .env SMTP_PASSWORD)'],
    'MAIL_FROM_ADDRESS' => MAIL_FROM_ADDRESS ? ['ok', MAIL_FROM_ADDRESS]   : ['fail', '(empty — check .env SMTP_FROM_EMAIL)'],
    'MAIL_FROM_NAME'    => MAIL_FROM_NAME    ? ['ok', MAIL_FROM_NAME]      : ['warn', '(empty)'],
];

// ─── Recent queue items ─────────────────────────────────────────────────────
$recentEmails = db()->fetchAll(
    "SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 30"
);

$pageTitle = 'Email Queue & Diagnostics';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-envelope-paper me-2"></i>Email Queue &amp; Diagnostics
        </h1>
        <a href="<?= APP_URL ?>/?page=settings" class="loka-btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Settings
        </a>
    </div>

    <?php foreach ($messages as $msg): ?>
    <div class="loka-alert loka-alert-success">
        <?= $msg ?>
        <button type="button" class="btn-close" onclick="this.closest('.alert').remove()"></button>
    </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
    <div class="loka-alert loka-alert-danger">
        <?= $err ?>
        <button type="button" class="btn-close" onclick="this.closest('.alert').remove()"></button>
    </div>
    <?php endforeach; ?>

    <!-- ── SMTP Configuration Status ───────────────────────────────────── -->
    <div class="loka-card mb-4 border-<?= $smtpConfigured ? 'success' : 'danger' ?>">
        <div class="px-6 py-4 border-b border-base-200 bg-<?= $smtpConfigured ? 'success' : 'danger' ?> text-white flex justify-between">
            <h5 class="mb-0">
                <i class="bi bi-gear me-1"></i>SMTP Configuration Status
            </h5>
            <span class="loka-badge bg-white text-<?= $smtpConfigured ? 'success' : 'danger' ?> fs-6">
                <?= $smtpConfigured ? '✅ Configured' : '❌ Not Configured' ?>
            </span>
        </div>
        <div class="p-6">
            <?php if (!$smtpConfigured): ?>
            <div class="loka-alert loka-alert-warning">
                <strong>⚠️ Email is not configured.</strong> Edit your <code>.env</code> file and set:
                <ul class="mb-0 mt-2">
                    <li><code>SMTP_HOST</code> — e.g. <code>smtp.gmail.com</code></li>
                    <li><code>SMTP_PORT</code> — e.g. <code>587</code></li>
                    <li><code>SMTP_USER</code> — your Gmail address</li>
                    <li><code>SMTP_PASSWORD</code> — your Gmail App Password (16 chars, no spaces)</li>
                    <li><code>SMTP_FROM_EMAIL</code> — same as SMTP_USER</li>
                    <li><code>SMTP_FROM_NAME</code> — e.g. <code>LOKA Fleet Management</code></li>
                    <li><code>MAIL_ENABLED=true</code></li>
                </ul>
            </div>
            <?php endif; ?>
            <table class="loka-table table-sm mb-0">
                <thead><tr><th>Setting</th><th>Value</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($smtpStatus as $key => [$status, $val]): ?>
                <tr>
                    <td><code><?= $key ?></code></td>
                    <td><?= e($val) ?></td>
                    <td>
                        <?php if ($status === 'ok'): ?>
                            <span class="loka-badge bg-success">✓ OK</span>
                        <?php elseif ($status === 'warn'): ?>
                            <span class="loka-badge bg-warning text-dark">⚠ Warning</span>
                        <?php else: ?>
                            <span class="loka-badge bg-error">✗ Missing</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4 mb-4">
        <!-- ── Send Test Email ─────────────────────────────────────────── -->
        <div class="col-span-12 md:col-span-6">
            <div class="loka-card h-100">
                <div class="px-6 py-4 border-b border-base-200"><h5 class="mb-0"><i class="bi bi-send me-1"></i>Send Test Email</h5></div>
                <div class="p-6">
                    <?php if ($smtpConfigured): ?>
                    <form method="post">
                        <?= csrfField() ?>
                        <div class="flex items-center gap-2">
                            <input type="email" name="test_email" class="loka-form-input"
                                   placeholder="recipient@example.com" required
                                   value="<?= e(postSafe('test_email')) ?>">
                            <button type="submit" name="action" value="send_test" class="loka-btn-primary">
                                <i class="bi bi-send me-1"></i>Send Test
                            </button>
                        </div>
                        <small class="text-base-content/60">Bypasses queue — sends directly via SMTP to verify connectivity.</small>
                    </form>
                    <?php else: ?>
                    <div class="text-base-content/60">Configure SMTP above before sending test emails.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Queue Actions ───────────────────────────────────────────── -->
        <div class="col-span-12 md:col-span-6">
            <div class="loka-card h-100">
                <div class="px-6 py-4 border-b border-base-200"><h5 class="mb-0"><i class="bi bi-play-circle me-1"></i>Queue Actions</h5></div>
                <div class="p-6">
                    <form method="post" class="flex flex-wrap gap-2">
                        <?= csrfField() ?>
                        <button type="submit" name="action" value="process" class="loka-btn-primary" <?= !$smtpConfigured ? 'disabled' : '' ?>>
                            <i class="bi bi-play-fill me-1"></i>Process Queue Now
                        </button>
                        <button type="submit" name="action" value="retry_failed" class="bg-warning text-warning-content hover:bg-warning/90 px-4 py-2 text-sm font-medium rounded-xl inline-flex items-center gap-2 transition-colors">
                            <i class="bi bi-arrow-repeat me-1"></i>Retry Failed
                        </button>
                        <button type="submit" name="action" value="cleanup" class="loka-btn-secondary">
                            <i class="bi bi-trash me-1"></i>Cleanup Old
                        </button>
                    </form>
                    <small class="text-base-content/60 block mt-2">
                        On <strong>WAMP/local dev</strong>: click "Process Queue Now" manually after submitting requests.
                        On <strong>production</strong>: a cron job runs every 2 minutes automatically.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Stats Cards ─────────────────────────────────────────────────── -->
    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card bg-warning text-dark text-center p-3">
                <h2 class="mb-0"><?= $stats['pending'] ?></h2>
                <small>Pending</small>
            </div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card bg-info text-white text-center p-3">
                <h2 class="mb-0"><?= $stats['processing'] ?></h2>
                <small>Processing</small>
            </div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card bg-success text-white text-center p-3">
                <h2 class="mb-0"><?= $stats['sent'] ?></h2>
                <small>Sent</small>
            </div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card bg-danger text-white text-center p-3">
                <h2 class="mb-0"><?= $stats['failed'] ?></h2>
                <small>Failed</small>
            </div>
        </div>
    </div>

    <!-- ── Recent Queue Items ───────────────────────────────────────────── -->
    <div class="loka-card">
        <div class="px-6 py-4 border-b border-base-200 flex justify-between">
            <h5 class="mb-0">Recent Queue (Last 30)</h5>
            <button class="loka-btn-sm loka-btn-secondary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
        <div class="p-0">
            <div class="loka-table-responsive">
                <table class="loka-table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Created</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEmails as $email): ?>
                        <tr>
                            <td><?= $email->id ?></td>
                            <td title="<?= e($email->to_email) ?>"><?= truncate($email->to_email, 28) ?></td>
                            <td title="<?= e($email->subject) ?>"><?= truncate($email->subject, 35) ?></td>
                            <td><code><?= e($email->template ?: '-') ?></code></td>
                            <td>
                                <?php $colors = ['pending'=>'warning','processing'=>'info','sent'=>'success','failed'=>'danger']; ?>
                                <span class="loka-badge bg-<?= $colors[$email->status] ?? 'secondary' ?>">
                                    <?= $email->status ?>
                                </span>
                            </td>
                            <td><?= $email->attempts ?>/<?= $email->max_attempts ?></td>
                            <td><?= date('M j, g:i A', strtotime($email->created_at)) ?></td>
                            <td><?= $email->sent_at ? date('M j, g:i A', strtotime($email->sent_at)) : '-' ?></td>
                        </tr>
                        <?php if ($email->status === 'failed' && $email->error_message): ?>
                        <tr class="table-danger">
                            <td colspan="8">
                                <small><strong>Error:</strong> <?= e($email->error_message) ?></small>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($recentEmails)): ?>
                        <tr><td colspan="8" class="text-center text-base-content/60 py-4">No emails in queue</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Gmail App Password Guide ─────────────────────────────────────── -->
    <div class="loka-card mt-4">
        <div class="px-6 py-4 border-b border-base-200"><h5 class="mb-0"><i class="bi bi-question-circle me-1"></i>Gmail Setup Guide</h5></div>
        <div class="p-6">
            <p>To use Gmail as your SMTP server (recommended for testing):</p>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                <li>Enable <strong>2-Step Verification</strong> if not already on</li>
                <li>Search for <strong>"App Passwords"</strong> in your Google Account</li>
                <li>Create a new App Password → Select "Mail" → Select "Other" → name it "LOKA"</li>
                <li>Copy the 16-character password (no spaces) and paste it into <code>.env</code> as <code>SMTP_PASSWORD</code></li>
            </ol>
            <p>Your <code>.env</code> should look like:</p>
            <pre class="bg-base-200 p-3 rounded"><code>SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=your.email@gmail.com
SMTP_PASSWORD=abcdabcdabcdabcd
SMTP_FROM_EMAIL=your.email@gmail.com
SMTP_FROM_NAME=LOKA Fleet Management
MAIL_ENABLED=true</code></pre>
            <hr>
            <h6>Windows Task Scheduler (for production-like local dev):</h6>
            <ol>
                <li>Open <strong>Task Scheduler</strong> → Create Basic Task → Name: "LOKA Email Queue"</li>
                <li>Trigger: Daily → Repeat every <strong>2 minutes</strong></li>
                <li>Action: Start a program</li>
                <li>Program: <code><?= e(PHP_BINARY) ?></code></li>
                <li>Arguments: <code><?= e(realpath(__DIR__ . '/../../cron/process_queue.php')) ?></code></li>
            </ol>
            <h6>Linux/Production Cron:</h6>
            <pre class="bg-base-200 p-2 rounded"><code>*/2 * * * * <?= e(PHP_BINARY) ?> <?= e(realpath(__DIR__ . '/../../cron/process_queue.php')) ?> >> /var/log/loka_email.log 2>&1</code></pre>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
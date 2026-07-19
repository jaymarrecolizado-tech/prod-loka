<?php
/**
 * All Father — Email delivery mode & queue tools
 */

requireAllFather();

$pageTitle = 'Email Delivery';
$flash = null;
$queue = new EmailQueue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $op = post('op', '');

    try {
        if ($op === 'save_mode') {
            $mode = postSafe('email_delivery_mode', EMAIL_MODE_IMMEDIATE, 20);
            if (!in_array($mode, [EMAIL_MODE_IMMEDIATE, EMAIL_MODE_QUEUED, EMAIL_MODE_HYBRID], true)) {
                throw new InvalidArgumentException('Invalid delivery mode.');
            }
            emailSaveSetting('email_delivery_mode', $mode);
            auditLog('email_delivery_mode_updated', 'settings', null, null, ['mode' => $mode]);
            $flash = ['success', 'Email delivery mode saved: ' . $mode . '.'];
        } elseif ($op === 'rotate_cron_secret') {
            $secret = bin2hex(random_bytes(16));
            emailSaveSetting('cron_secret', $secret);
            auditLog('cron_secret_rotated', 'settings', null, null, ['rotated' => true]);
            $flash = ['success', 'Cron secret rotated. Update your Hostinger cron URL.'];
        } elseif ($op === 'process_queue') {
            $r = $queue->process(50);
            $flash = ['success', "Processed queue: sent {$r['sent']}, failed {$r['failed']}, skipped {$r['skipped']}."];
        }
    } catch (Throwable $e) {
        $flash = ['danger', $e->getMessage()];
    }
}

$mode = emailDeliveryMode();
$cronSecret = emailCronSecret();
$stats = $queue->getStats();
$cronEmailUrl = APP_URL . '/?page=cron&action=email&key=' . rawurlencode($cronSecret);
$cronSmsUrl = APP_URL . '/?page=cron&action=sms&key=' . rawurlencode($cronSecret);
$mailEnabled = defined('MAIL_ENABLED') && MAIL_ENABLED;

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="mb-2">
        <h4 class="mb-1">Email Delivery</h4>
        <p class="text-base-content/60 text-sm mb-0">
            Choose how LOKA sends email. Immediate needs no cron (pages may wait on SMTP).
        </p>
    </div>

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <?php if ($flash): ?>
        <div class="loka-alert loka-alert-<?= e($flash[0]) ?> mb-4"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$mailEnabled): ?>
        <div class="loka-alert loka-alert-warning mb-4">
            <code>MAIL_ENABLED</code> is false in <code>.env</code>. Enable it and configure SMTP before testing real delivery.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-lg font-semibold uppercase"><?= e($mode) ?></div>
                <div class="text-xs text-base-content/60">Delivery mode</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) ($stats['pending'] ?? 0) ?></div>
                <div class="text-xs text-base-content/60">Pending</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) ($stats['sent'] ?? 0) ?></div>
                <div class="text-xs text-base-content/60">Sent</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) ($stats['failed'] ?? 0) ?></div>
                <div class="text-xs text-base-content/60">Failed</div>
            </div></div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-6">
            <div class="loka-card mb-4">
                <div class="loka-card-body">
                    <h5 class="mb-3">Delivery mode</h5>
                    <form method="POST" class="space-y-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="op" value="save_mode">

                        <label class="flex items-start gap-2 p-3 rounded-lg border border-base-300 cursor-pointer">
                            <input type="radio" name="email_delivery_mode" value="immediate" class="radio mt-1"
                                <?= $mode === EMAIL_MODE_IMMEDIATE ? 'checked' : '' ?>>
                            <span>
                                <strong>Immediate</strong>
                                <span class="block text-sm text-base-content/60">Send in the same request (no cron). Best for VPS testing; UI may feel slower.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2 p-3 rounded-lg border border-base-300 cursor-pointer">
                            <input type="radio" name="email_delivery_mode" value="queued" class="radio mt-1"
                                <?= $mode === EMAIL_MODE_QUEUED ? 'checked' : '' ?>>
                            <span>
                                <strong>Queued</strong>
                                <span class="block text-sm text-base-content/60">Fast pages; drain with Process now, CLI cron, or HTTP cron URL below.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2 p-3 rounded-lg border border-base-300 cursor-pointer">
                            <input type="radio" name="email_delivery_mode" value="hybrid" class="radio mt-1"
                                <?= $mode === EMAIL_MODE_HYBRID ? 'checked' : '' ?>>
                            <span>
                                <strong>Hybrid</strong>
                                <span class="block text-sm text-base-content/60">Critical templates sync now; others wait in the queue.</span>
                            </span>
                        </label>

                        <button type="submit" class="loka-btn-primary">Save mode</button>
                    </form>

                    <form method="POST" class="mt-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="op" value="process_queue">
                        <button type="submit" class="loka-btn-secondary">Process email queue now</button>
                    </form>
                    <p class="text-xs text-base-content/50 mt-2 mb-0">
                        Admins can also use <a class="link" href="<?= APP_URL ?>/?page=settings&action=email-queue">Settings → Email Queue</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-6">
            <div class="loka-card mb-4">
                <div class="loka-card-body">
                    <h5 class="mb-3">HTTP cron (Hostinger-friendly)</h5>
                    <p class="text-sm text-base-content/60 mb-3">
                        Instead of CLI PHP, schedule a URL hit every 1–2 minutes when using Queued or Hybrid.
                    </p>
                    <label class="loka-form-label">Email queue URL</label>
                    <input type="text" class="loka-form-input font-mono text-xs mb-3" readonly value="<?= e($cronEmailUrl) ?>"
                           onclick="this.select()">
                    <label class="loka-form-label">SMS queue URL</label>
                    <input type="text" class="loka-form-input font-mono text-xs mb-3" readonly value="<?= e($cronSmsUrl) ?>"
                           onclick="this.select()">
                    <pre class="bg-base-200 p-2 rounded text-xs overflow-x-auto mb-3">curl -s "<?= e($cronEmailUrl) ?>"</pre>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="op" value="rotate_cron_secret">
                        <button type="submit" class="loka-btn-outline-error"
                                onclick="return confirm('Rotate cron secret? Update Hostinger cron afterward.');">
                            Rotate cron secret
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

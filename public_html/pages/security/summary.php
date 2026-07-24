<?php
/**
 * All Father — rate-limit / lockout summary report
 */

requireSystemControl();

$pageTitle = 'Security Summary';

$lockedNow = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM users
     WHERE deleted_at IS NULL
       AND locked_until IS NOT NULL AND locked_until > NOW()"
);

$highAttempts = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM users
     WHERE deleted_at IS NULL AND failed_login_attempts >= ?",
    [RATE_LIMIT_LOGIN_ATTEMPTS]
);

$hits24h = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM rate_limits
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);

$hits7d = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM rate_limits
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

$clears7d = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM security_logs
     WHERE event IN ('rate_limit_cleared', 'account_unlocked')
       AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

$locks7d = (int) db()->fetchColumn(
    "SELECT COUNT(*) FROM security_logs
     WHERE event IN ('account_locked', 'login_rate_limited')
       AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

$byAction24h = db()->fetchAll(
    "SELECT action, COUNT(*) AS hits
     FROM rate_limits
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY action
     ORDER BY hits DESC"
);

$topIdentifiers24h = db()->fetchAll(
    "SELECT action, identifier, COUNT(*) AS hits, MAX(created_at) AS last_hit
     FROM rate_limits
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY action, identifier
     ORDER BY hits DESC
     LIMIT 15"
);

$recentClears = db()->fetchAll(
    "SELECT event, details, ip_address, created_at
     FROM security_logs
     WHERE event IN ('rate_limit_cleared', 'account_unlocked')
     ORDER BY created_at DESC
     LIMIT 20"
);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 sm:px-6 lg:px-8">
    <div class="mb-2">
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Security Summary</h4>
        <p class="text-base-content/60 mb-0">Rate-limit and lockout overview (All Father)</p>
    </div>

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">Locked now</div>
            <div class="text-2xl font-bold"><?= $lockedNow ?></div>
        </div>
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">High attempts</div>
            <div class="text-2xl font-bold"><?= $highAttempts ?></div>
        </div>
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">Hits (24h)</div>
            <div class="text-2xl font-bold"><?= $hits24h ?></div>
        </div>
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">Hits (7d)</div>
            <div class="text-2xl font-bold"><?= $hits7d ?></div>
        </div>
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">Locks (7d)</div>
            <div class="text-2xl font-bold"><?= $locks7d ?></div>
        </div>
        <div class="loka-card p-4">
            <div class="text-xs text-base-content/60 uppercase font-semibold">Clears (7d)</div>
            <div class="text-2xl font-bold"><?= $clears7d ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div class="loka-card">
            <div class="px-4 py-3 border-b border-base-200 font-semibold">Hits by action (24h)</div>
            <div class="loka-table-responsive">
                <table class="loka-table mb-0">
                    <thead><tr><th>Action</th><th>Hits</th></tr></thead>
                    <tbody>
                    <?php if (empty($byAction24h)): ?>
                        <tr><td colspan="2" class="text-center text-base-content/60 py-4">No hits</td></tr>
                    <?php else: foreach ($byAction24h as $row): ?>
                        <tr>
                            <td><code><?= e($row->action) ?></code></td>
                            <td><?= (int) $row->hits ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="loka-card">
            <div class="px-4 py-3 border-b border-base-200 font-semibold">Top identifiers (24h)</div>
            <div class="loka-table-responsive">
                <table class="loka-table mb-0">
                    <thead><tr><th>Action</th><th>Identifier</th><th>Hits</th><th>Last</th></tr></thead>
                    <tbody>
                    <?php if (empty($topIdentifiers24h)): ?>
                        <tr><td colspan="4" class="text-center text-base-content/60 py-4">No hits</td></tr>
                    <?php else: foreach ($topIdentifiers24h as $row): ?>
                        <tr>
                            <td><code><?= e($row->action) ?></code></td>
                            <td class="text-sm break-all"><?= e($row->identifier) ?></td>
                            <td><?= (int) $row->hits ?></td>
                            <td class="text-xs whitespace-nowrap"><?= e($row->last_hit) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="loka-card">
        <div class="px-4 py-3 border-b border-base-200 font-semibold">Recent clears / unlocks</div>
        <div class="loka-table-responsive">
            <table class="loka-table mb-0">
                <thead><tr><th>When</th><th>Event</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (empty($recentClears)): ?>
                    <tr><td colspan="4" class="text-center text-base-content/60 py-4">None yet</td></tr>
                <?php else: foreach ($recentClears as $log): ?>
                    <tr>
                        <td class="text-sm whitespace-nowrap"><?= e($log->created_at) ?></td>
                        <td><code><?= e($log->event) ?></code></td>
                        <td class="text-sm"><?= e($log->details) ?></td>
                        <td class="text-sm"><?= e($log->ip_address) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

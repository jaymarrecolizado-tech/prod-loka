<?php
/**
 * All Father — SMS notifications settings & logs
 */

requireAllFather();

$pageTitle = 'SMS Notifications';
$flash = null;
$queue = new SmsQueue();

$selectableEvents = smsSelectableEvents();
$allowlistDefault = '*';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $op = post('op', '');

    try {
        if ($op === 'save_settings') {
            $enabled = post('sms_enabled', '0') === '1' ? '1' : '0';
            $url = trim(postSafe('sms_gateway_url', '', 255));
            $username = trim(postSafe('sms_gateway_username', '', 100));
            $password = (string) post('sms_gateway_password', '');
            $apiPath = trim(postSafe('sms_api_path', SMS_API_PATH_CLOUD, 120));
            $country = preg_replace('/\D+/', '', postSafe('sms_country_code', '63', 5)) ?: '63';
            $timeout = max(5, min(60, (int) post('sms_timeout_seconds', 15)));
            $maxLen = max(80, min(1600, (int) post('sms_max_length', 320)));

            $mirrorEmail = post('sms_mirror_email', '0') === '1';
            if ($mirrorEmail) {
                $allowlist = '*';
            } else {
                $selected = post('events', []);
                if (!is_array($selected)) {
                    $selected = [];
                }
                $selected = array_values(array_intersect($selected, $selectableEvents));
                $allowlist = !empty($selected) ? implode(',', $selected) : $allowlistDefault;
            }

            $allowedPaths = [SMS_API_PATH_LOCAL, SMS_API_PATH_PRIVATE, SMS_API_PATH_CLOUD];
            if (!in_array($apiPath, $allowedPaths, true)) {
                $apiPath = SMS_API_PATH_CLOUD;
            }

            smsSaveSetting('sms_enabled', $enabled, 'boolean');
            smsSaveSetting('sms_gateway_url', $url);
            smsSaveSetting('sms_gateway_username', $username);
            if ($password !== '') {
                smsSaveSetting('sms_gateway_password', $password);
            }
            smsSaveSetting('sms_api_path', $apiPath !== '' ? $apiPath : SMS_API_PATH_CLOUD);
            smsSaveSetting('sms_country_code', $country);
            smsSaveSetting('sms_timeout_seconds', (string) $timeout, 'integer');
            smsSaveSetting('sms_max_length', (string) $maxLen, 'integer');
            smsSaveSetting('sms_event_allowlist', $allowlist);
            smsConfigClearCache();

            auditLog('sms_settings_updated', 'settings', null, null, [
                'enabled' => $enabled,
                'gateway_url' => $url,
                'api_path' => $apiPath,
            ]);
            $flash = ['success', 'SMS settings saved.'];
        } elseif ($op === 'test_send') {
            if (!smsEnabled()) {
                throw new RuntimeException('Enable SMS notifications before sending a test.');
            }
            $phone = trim(postSafe('test_phone', '', 30));
            $msg = trim(postSafe('test_message', 'LOKA SMS test — ' . date('Y-m-d H:i'), 320));
            $id = $queue->queueTest($phone, $msg, userId());
            $processed = $queue->process(1);
            $row = $id ? db()->fetch("SELECT status, error_message FROM sms_logs WHERE id = ?", [$id]) : null;
            if ($row && $row->status === 'sent') {
                $flash = ['success', 'Test SMS sent successfully.'];
            } elseif ($row && $row->status === 'pending') {
                $flash = ['warning', 'Test queued (pending). Run Process queue or wait for cron. Sent=' . $processed['sent']];
            } else {
                $err = $row->error_message ?? 'Unknown error';
                $flash = ['danger', 'Test SMS failed: ' . $err];
            }
        } elseif ($op === 'process_queue') {
            $r = $queue->process(30);
            $flash = ['success', "Processed queue: sent {$r['sent']}, failed {$r['failed']}, skipped {$r['skipped']}."];
        } elseif ($op === 'health_check') {
            $gw = SmsGateway::fromConfig();
            if (!$gw) {
                $flash = ['danger', 'Gateway URL/username/password not configured.'];
            } else {
                $h = $gw->health();
                $flash = $h['ok']
                    ? ['success', 'Gateway health OK (HTTP ' . $h['http_code'] . ').']
                    : ['danger', 'Gateway health failed: ' . ($h['error'] ?: 'unknown')];
            }
        } elseif ($op === 'delete_log') {
            $logId = postInt('log_id');
            $row = $logId
                ? db()->fetch("SELECT id, phone, event_type, status FROM sms_logs WHERE id = ?", [$logId])
                : null;
            if (!$row) {
                throw new InvalidArgumentException('SMS log not found.');
            }
            db()->delete('sms_logs', 'id = ?', [$logId]);
            auditLog('sms_log_deleted', 'sms_log', $logId, (array) $row, null);
            $qs = http_build_query(array_filter([
                'page' => 'security',
                'action' => 'sms',
                'status' => postSafe('ret_status', '', 20),
                'q' => postSafe('ret_q', '', 100),
                'date_from' => postSafe('ret_date_from', '', 20),
                'date_to' => postSafe('ret_date_to', '', 20),
                'per_page' => postSafe('ret_per_page', '', 10),
                'p' => postSafe('ret_p', '', 10),
            ], static fn($v) => $v !== null && $v !== ''));
            redirectWith('/?' . $qs, 'success', "SMS log #{$logId} deleted.");
        }
    } catch (Throwable $e) {
        $flash = ['danger', $e->getMessage()];
    }
}

$enabled = smsEnabled();
$gatewayUrl = smsConfig('sms_gateway_url');
$gatewayUser = smsConfig('sms_gateway_username');
$hasPassword = smsConfig('sms_gateway_password') !== '';
$apiPath = smsConfig('sms_api_path', SMS_API_PATH_LOCAL);
$country = smsConfig('sms_country_code', '63');
$timeout = smsConfig('sms_timeout_seconds', '15');
$maxLen = smsConfig('sms_max_length', '320');
$allowRaw = trim(smsConfig('sms_event_allowlist', $allowlistDefault));
$mirrorEmail = ($allowRaw === '' || $allowRaw === '*');
$allowedEvents = $mirrorEmail
    ? $selectableEvents
    : array_filter(array_map('trim', explode(',', $allowRaw)));
$stats = $queue->getStats();

$logStatus = getSafe('status', '', 20);
$logSearch = getSafe('q', '', 100);
$logDateFrom = getSafe('date_from', '', 20);
$logDateTo = getSafe('date_to', '', 20);
$allowedLogStatuses = ['pending', 'processing', 'sent', 'failed'];
if ($logStatus !== '' && !in_array($logStatus, $allowedLogStatuses, true)) {
    $logStatus = '';
}

$logs = [];
$pag = listPaginationState(0);
$logBaseParams = [
    'page' => 'security',
    'action' => 'sms',
    'status' => $logStatus,
    'q' => $logSearch,
    'date_from' => $logDateFrom,
    'date_to' => $logDateTo,
];
try {
    $where = ['1=1'];
    $params = [];
    if ($logStatus !== '') {
        $where[] = 's.status = ?';
        $params[] = $logStatus;
    }
    if ($logSearch !== '') {
        $where[] = '(s.phone LIKE ? OR s.event_type LIKE ? OR s.message LIKE ? OR u.name LIKE ?)';
        $like = '%' . $logSearch . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($logDateFrom !== '') {
        $where[] = 's.created_at >= ?';
        $params[] = $logDateFrom . ' 00:00:00';
    }
    if ($logDateTo !== '') {
        $where[] = 's.created_at <= ?';
        $params[] = $logDateTo . ' 23:59:59';
    }
    $whereSql = implode(' AND ', $where);

    $countRow = db()->fetch(
        "SELECT COUNT(*) as c
         FROM sms_logs s
         LEFT JOIN users u ON u.id = s.user_id
         WHERE {$whereSql}",
        $params
    );
    $pag = listPaginationState((int) ($countRow->c ?? 0));
    $logBaseParams['per_page'] = $pag['perPage'];

    $logs = db()->fetchAll(
        "SELECT s.*, u.name AS user_name
         FROM sms_logs s
         LEFT JOIN users u ON u.id = s.user_id
         WHERE {$whereSql}
         ORDER BY s.id DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$pag['perPage'], $pag['offset']])
    );
} catch (Throwable $e) {
    $flash = $flash ?: ['danger', 'SMS tables missing. Run migration 023_sms_notifications.php'];
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="w-full px-4 py-4 sm:px-6 lg:px-8">
    <div class="mb-2">
        <h4 class="mb-1">SMS Notifications</h4>
        <p class="text-base-content/60 text-sm mb-0">Outbound notify-only for travel participants. All Father control.</p>
    </div>

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <?php if ($flash): ?>
        <div class="loka-alert loka-alert-<?= e($flash[0]) ?> mb-4"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-4 mb-4">
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= $enabled ? 'ON' : 'OFF' ?></div>
                <div class="text-xs text-base-content/60">SMS enabled</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) $stats['pending'] ?></div>
                <div class="text-xs text-base-content/60">Pending</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) $stats['sent'] ?></div>
                <div class="text-xs text-base-content/60">Sent</div>
            </div></div>
        </div>
        <div class="col-span-6 md:col-span-3">
            <div class="loka-card"><div class="loka-card-body py-3 text-center">
                <div class="text-2xl font-semibold"><?= (int) $stats['failed'] ?></div>
                <div class="text-xs text-base-content/60">Failed</div>
            </div></div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-5">
            <div class="loka-card mb-4">
                <div class="loka-card-body">
                    <h5 class="mb-3">Gateway settings</h5>
                    <form method="POST" class="space-y-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="op" value="save_settings">

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="sms_enabled" value="1" class="checkbox" <?= $enabled ? 'checked' : '' ?>>
                            <span>Enable SMS notifications</span>
                        </label>

                        <div>
                            <label class="loka-form-label">Gateway URL</label>
                            <input type="text" name="sms_gateway_url" class="loka-form-input"
                                   placeholder="http://192.168.x.x:8080 or https://sms.yourdomain.com"
                                   value="<?= e($gatewayUrl) ?>">
                            <p class="text-xs text-base-content/50 mt-1">Local phone server: device LAN IP + port 8080. Private server: HTTPS base URL.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="loka-form-label">Username</label>
                                <input type="text" name="sms_gateway_username" class="loka-form-input" value="<?= e($gatewayUser) ?>" autocomplete="off">
                            </div>
                            <div>
                                <label class="loka-form-label">Password</label>
                                <input type="password" name="sms_gateway_password" class="loka-form-input" value="" autocomplete="new-password"
                                       placeholder="<?= $hasPassword ? '•••••••• (unchanged if blank)' : 'Required' ?>">
                            </div>
                        </div>

                        <div>
                            <label class="loka-form-label">API path</label>
                            <select name="sms_api_path" class="loka-form-input">
                                <option value="<?= e(SMS_API_PATH_CLOUD) ?>" <?= $apiPath === SMS_API_PATH_CLOUD ? 'selected' : '' ?>>
                                    Public cloud sms-gate.app (<?= e(SMS_API_PATH_CLOUD) ?>)
                                </option>
                                <option value="<?= e(SMS_API_PATH_LOCAL) ?>" <?= $apiPath === SMS_API_PATH_LOCAL ? 'selected' : '' ?>>
                                    Local phone server (<?= e(SMS_API_PATH_LOCAL) ?>)
                                </option>
                                <option value="<?= e(SMS_API_PATH_PRIVATE) ?>" <?= $apiPath === SMS_API_PATH_PRIVATE ? 'selected' : '' ?>>
                                    Self-hosted private server (<?= e(SMS_API_PATH_PRIVATE) ?>)
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="loka-form-label">Country code</label>
                                <input type="text" name="sms_country_code" class="loka-form-input" value="<?= e($country) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Timeout (s)</label>
                                <input type="number" name="sms_timeout_seconds" class="loka-form-input" min="5" max="60" value="<?= e($timeout) ?>">
                            </div>
                            <div>
                                <label class="loka-form-label">Max length</label>
                                <input type="number" name="sms_max_length" class="loka-form-input" min="80" max="1600" value="<?= e($maxLen) ?>">
                            </div>
                        </div>

                        <div>
                            <label class="loka-form-label mb-2">Events that may SMS</label>
                            <label class="flex items-center gap-2 text-sm mb-2 p-2 rounded-lg border border-base-300">
                                <input type="checkbox" name="sms_mirror_email" value="1" class="checkbox checkbox-sm"
                                    <?= $mirrorEmail ? 'checked' : '' ?>
                                    onchange="document.getElementById('smsCustomEvents').classList.toggle('hidden', this.checked)">
                                <span><strong>Match all email events</strong> (recommended — same types as email notifications)</span>
                            </label>
                            <div id="smsCustomEvents" class="grid grid-cols-1 gap-1 max-h-48 overflow-y-auto border border-base-300 rounded-lg p-2 <?= $mirrorEmail ? 'hidden' : '' ?>">
                                <?php foreach ($selectableEvents as $ev): ?>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="events[]" value="<?= e($ev) ?>" class="checkbox checkbox-sm"
                                            <?= in_array($ev, $allowedEvents, true) ? 'checked' : '' ?>>
                                        <span><?= e($ev) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="loka-btn-primary">Save settings</button>
                    </form>
                </div>
            </div>

            <div class="loka-card mb-4">
                <div class="loka-card-body">
                    <h5 class="mb-3">Test & tools</h5>
                    <form method="POST" class="space-y-3 mb-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="op" value="test_send">
                        <div>
                            <label class="loka-form-label">Test phone</label>
                            <input type="text" name="test_phone" class="loka-form-input" placeholder="09XXXXXXXXX" required>
                        </div>
                        <div>
                            <label class="loka-form-label">Message</label>
                            <textarea name="test_message" class="loka-form-input" rows="2">LOKA SMS test — <?= e(date('Y-m-d H:i')) ?></textarea>
                        </div>
                        <button type="submit" class="loka-btn-outline-primary">Send test SMS</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST"><?= csrfField() ?><input type="hidden" name="op" value="process_queue">
                            <button type="submit" class="loka-btn-secondary">Process queue now</button>
                        </form>
                        <form method="POST"><?= csrfField() ?><input type="hidden" name="op" value="health_check">
                            <button type="submit" class="loka-btn-secondary">Gateway health</button>
                        </form>
                    </div>
                    <p class="text-xs text-base-content/50 mt-3 mb-0">
                        Local tip: enable Local Server in the SMS Gateway Android app, use API path “Local phone server”,
                        Gateway URL = <code>http://PHONE_LAN_IP:8080</code>, credentials shown in the app.
                        Outbound SMS is queued on submit (does not wait on the gateway); use <strong>Process queue now</strong>
                        or HTTP/CLI cron to send pending messages.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-7">
            <div class="loka-card">
                <div class="loka-card-body">
                    <h5 class="mb-3">Recent SMS logs</h5>

                    <form method="GET" class="flex flex-wrap items-end gap-2 mb-4">
                        <input type="hidden" name="page" value="security">
                        <input type="hidden" name="action" value="sms">
                        <div class="flex flex-col gap-1 min-w-[140px]">
                            <label class="label py-0"><span class="label-text text-xs font-semibold uppercase text-base-content/70">Status</span></label>
                            <select name="status" class="select select-bordered select-sm bg-base-100">
                                <option value="">All</option>
                                <?php foreach ($allowedLogStatuses as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $logStatus === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 flex-1 min-w-[160px]">
                            <label class="label py-0"><span class="label-text text-xs font-semibold uppercase text-base-content/70">Search</span></label>
                            <input type="search" name="q" value="<?= e($logSearch) ?>"
                                   class="input input-bordered input-sm bg-base-100"
                                   placeholder="Phone, name, event, message…">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="label py-0"><span class="label-text text-xs font-semibold uppercase text-base-content/70">From</span></label>
                            <input type="date" name="date_from" value="<?= e($logDateFrom) ?>" class="input input-bordered input-sm bg-base-100">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="label py-0"><span class="label-text text-xs font-semibold uppercase text-base-content/70">To</span></label>
                            <input type="date" name="date_to" value="<?= e($logDateTo) ?>" class="input input-bordered input-sm bg-base-100">
                        </div>
                        <?= perPageFieldHtml($pag['perPage']) ?>
                        <button type="submit" class="loka-btn-primary loka-btn-sm">Filter</button>
                        <a href="<?= APP_URL ?>/?page=security&action=sms" class="loka-btn-secondary loka-btn-sm">Reset</a>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="loka-table w-full text-sm sms-logs-table" id="smsLogsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>To</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th class="text-center w-16">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="6" class="text-center text-base-content/50 py-4">No SMS logs match your filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="sms-log-row" tabindex="0" data-log-id="<?= (int) $log->id ?>">
                                            <td><?= (int) $log->id ?></td>
                                            <td class="font-mono text-xs">
                                                <?= e($log->phone) ?>
                                                <?php if (!empty($log->user_name)): ?>
                                                    <div class="text-base-content/50"><?= e($log->user_name) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= e($log->event_type ?: '-') ?>
                                                <div class="text-xs text-base-content/50 max-w-xs truncate" title="<?= e($log->message) ?>"><?= e($log->message) ?></div>
                                                <?php if ($log->status === 'failed' && $log->error_message): ?>
                                                    <div class="text-xs text-error"><?= e($log->error_message) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($log->status === 'sent') {
                                                    $stClass = 'bg-success/20 text-success';
                                                } elseif ($log->status === 'failed') {
                                                    $stClass = 'bg-error/20 text-error';
                                                } elseif ($log->status === 'processing') {
                                                    $stClass = 'bg-info/20 text-info';
                                                } else {
                                                    $stClass = 'bg-warning/20 text-warning';
                                                }
                                                ?>
                                                <span class="loka-badge <?= $stClass ?>"><?= e($log->status) ?></span>
                                            </td>
                                            <td class="text-xs whitespace-nowrap"><?= e($log->created_at) ?></td>
                                            <td class="text-center" onclick="event.stopPropagation()">
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete SMS log #<?= (int) $log->id ?>? This cannot be undone.');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="op" value="delete_log">
                                                    <input type="hidden" name="log_id" value="<?= (int) $log->id ?>">
                                                    <input type="hidden" name="ret_status" value="<?= e($logStatus) ?>">
                                                    <input type="hidden" name="ret_q" value="<?= e($logSearch) ?>">
                                                    <input type="hidden" name="ret_date_from" value="<?= e($logDateFrom) ?>">
                                                    <input type="hidden" name="ret_date_to" value="<?= e($logDateTo) ?>">
                                                    <input type="hidden" name="ret_per_page" value="<?= (int) $pag['perPage'] ?>">
                                                    <input type="hidden" name="ret_p" value="<?= (int) $pag['page'] ?>">
                                                    <button type="submit" class="loka-btn-icon text-error hover:bg-error/10" title="Delete log">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= listPaginationFooter($pag, $logBaseParams) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const table = document.getElementById('smsLogsTable');
    if (!table) return;
    table.querySelectorAll('tr.sms-log-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('button, a, form, input, select, textarea, label')) return;
            const was = row.classList.contains('is-selected');
            table.querySelectorAll('tr.sms-log-row.is-selected').forEach(function (r) {
                r.classList.remove('is-selected');
            });
            if (!was) row.classList.add('is-selected');
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                row.click();
            }
        });
    });
})();
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

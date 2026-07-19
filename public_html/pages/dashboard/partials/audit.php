<?php if (!empty($dash['audit'])): ?>
<div class="loka-card mb-6">
    <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
        <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-journal-text mr-2"></i>Recent Audit</h5>
        <a href="<?= APP_URL ?>/?page=audit" class="loka-btn-secondary text-xs">View All</a>
    </div>
    <div class="loka-table-responsive">
        <table class="loka-table mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dash['audit'] as $log): ?>
                <tr>
                    <td class="text-xs"><?= formatDateTime($log->created_at) ?></td>
                    <td><?= e($log->user_name ?? 'System') ?></td>
                    <td><span class="loka-badge loka-status-info"><?= e($log->action ?? '-') ?></span></td>
                    <td class="text-xs"><?= e(($log->entity_type ?? '') . ($log->entity_id ? ' #' . $log->entity_id : '')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

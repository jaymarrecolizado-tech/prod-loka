<?php
/**
 * All Father security sub-navigation.
 */
$securityAction = get('action', 'rate-limits');
if ($securityAction === 'index' || $securityAction === '') {
    $securityAction = 'rate-limits';
}
?>
<div class="flex flex-wrap gap-2 mb-4">
    <a href="<?= APP_URL ?>/?page=security&action=rate-limits"
       class="loka-btn-sm <?= $securityAction === 'rate-limits' ? 'loka-btn-primary' : 'loka-btn-secondary' ?>">
        <i class="bi bi-unlock me-1"></i>Lockouts
    </a>
    <a href="<?= APP_URL ?>/?page=security&action=summary"
       class="loka-btn-sm <?= $securityAction === 'summary' ? 'loka-btn-primary' : 'loka-btn-secondary' ?>">
        <i class="bi bi-bar-chart-line me-1"></i>Summary
    </a>
</div>

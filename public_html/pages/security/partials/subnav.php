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
    <a href="<?= APP_URL ?>/?page=security&action=sms"
       class="loka-btn-sm <?= $securityAction === 'sms' ? 'loka-btn-primary' : 'loka-btn-secondary' ?>">
        <i class="bi bi-phone me-1"></i>SMS
    </a>
    <a href="<?= APP_URL ?>/?page=security&action=email"
       class="loka-btn-sm <?= $securityAction === 'email' ? 'loka-btn-primary' : 'loka-btn-secondary' ?>">
        <i class="bi bi-envelope me-1"></i>Email
    </a>
</div>

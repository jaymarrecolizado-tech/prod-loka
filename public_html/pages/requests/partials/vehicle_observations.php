<?php
/**
 * Vehicle condition observations for request view.
 * Expects: $request (object with id)
 */
$observations = observationGetForRequest((int) $request->id);
if (empty($observations)) {
    return;
}

$conditionBadge = [
    'good' => 'bg-success',
    'fair' => 'bg-warning',
    'poor' => 'bg-error',
    'damaged' => 'bg-error',
];
?>
<div class="loka-card border border-base-300 mt-4">
    <div class="p-4 border-b border-base-200">
        <h3 class="font-semibold flex items-center gap-2">
            <i class="bi bi-camera"></i>Vehicle condition (Guard)
        </h3>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($observations as $obs):
            $photos = observationPhotos((int) $obs->id);
            $flags = json_decode($obs->flags_json ?: '{}', true) ?: [];
            $flagLabels = array_keys(array_filter($flags));
            $phaseLabel = $obs->phase === 'arrival' ? 'Upon arrival' : 'Before dispatch';
            $badge = $conditionBadge[$obs->overall_condition] ?? 'bg-secondary';
        ?>
            <div class="rounded-xl border border-base-300 p-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="font-semibold"><?= e($phaseLabel) ?></div>
                    <span class="loka-badge <?= e($badge) ?>"><?= e(ucfirst($obs->overall_condition)) ?></span>
                </div>
                <div class="text-xs text-base-content/60">
                    <?= e(formatDateTime($obs->observed_at)) ?>
                    <?php if (!empty($obs->guard_name)): ?>
                        · <?= e($obs->guard_name) ?>
                    <?php endif; ?>
                </div>
                <?php if ($flagLabels): ?>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($flagLabels as $f): ?>
                            <span class="loka-badge bg-secondary text-xs"><?= e(str_replace('_', ' ', $f)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($obs->notes)): ?>
                    <p class="text-sm"><?= nl2br(e($obs->notes)) ?></p>
                <?php endif; ?>
                <?php if ($photos): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($photos as $photo):
                            $thumb = $photo->thumb_path ?: $photo->file_path;
                            $full = $photo->full_path ?: $photo->file_path;
                        ?>
                            <a href="<?= e(observationFileUrl($full)) ?>" target="_blank" rel="noopener" class="block">
                                <img src="<?= e(observationFileUrl($thumb)) ?>"
                                     alt="Vehicle photo"
                                     class="w-20 h-20 object-cover rounded-lg border border-base-300"
                                     loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

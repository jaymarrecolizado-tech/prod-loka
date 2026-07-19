<?php
/**
 * Shared condition + photo fields for dispatch/arrival modals.
 * Expects: $obsPhase ('dispatch'|'arrival'), $tripId (int)
 */
$obsPhase = $obsPhase ?? 'dispatch';
$tripId = (int) ($tripId ?? 0);
$prefix = $obsPhase . $tripId;
?>
<div class="rounded-lg border border-base-300 p-3 space-y-3 bg-base-200/40">
    <div class="text-xs font-semibold uppercase tracking-wide text-base-content/70">
        Vehicle condition (<?= $obsPhase === 'arrival' ? 'upon arrival' : 'before dispatch' ?>)
        <span class="text-error">*</span>
    </div>

    <div class="flex flex-col gap-1.5">
        <label class="label-text text-xs font-semibold">Overall condition <span class="text-error">*</span></label>
        <select name="overall_condition" class="select select-bordered select-sm bg-base-100" required>
            <option value="">Select...</option>
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="poor">Poor</option>
            <option value="damaged">Damaged</option>
        </select>
    </div>

    <div>
        <div class="label-text text-xs font-semibold mb-1">Checklist</div>
        <div class="grid grid-cols-2 gap-1.5 text-sm">
            <?php
            $labels = [
                'exterior_damage' => 'Exterior damage',
                'interior_damage' => 'Interior damage',
                'tire_issue' => 'Tire issue',
                'lights_issue' => 'Lights issue',
                'fuel_low' => 'Fuel low',
                'unclean' => 'Unclean',
                'missing_items' => 'Missing items',
                'other' => 'Other',
            ];
            foreach ($labels as $key => $label):
            ?>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" class="checkbox checkbox-sm" name="condition_flags[<?= e($key) ?>]" value="1">
                <span><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-col gap-1.5">
        <label class="label-text text-xs font-semibold">Condition notes <?= $obsPhase === 'arrival' ? '' : '' ?></label>
        <textarea class="textarea textarea-bordered textarea-sm bg-base-100 h-16"
                  name="condition_notes"
                  maxlength="1000"
                  placeholder="Describe what you see (required if damaged)"></textarea>
    </div>

    <div class="flex flex-col gap-1.5">
        <label class="label-text text-xs font-semibold">
            Photos <span class="text-error">*</span>
            <span class="font-normal text-base-content/50">(1–6, compressed automatically)</span>
        </label>
        <input type="file"
               class="file-input file-input-bordered file-input-sm bg-base-100 w-full obs-photo-input"
               name="observation_photos[]"
               accept="image/*"
               capture="environment"
               multiple
               required
               data-preview="obsPreview<?= e($prefix) ?>"
               data-size="obsSize<?= e($prefix) ?>">
        <div id="obsSize<?= e($prefix) ?>" class="text-xs text-base-content/60">No photos selected</div>
        <div id="obsPreview<?= e($prefix) ?>" class="flex flex-wrap gap-2 mt-1"></div>
    </div>
</div>

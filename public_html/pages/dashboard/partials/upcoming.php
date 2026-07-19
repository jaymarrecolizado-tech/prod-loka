<div class="loka-card h-full">
    <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
        <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-calendar-event mr-2"></i>Upcoming Trips</h5>
        <a href="<?= APP_URL ?>/?page=requests&status=approved" class="loka-btn-secondary text-xs">View All</a>
    </div>
    <div>
        <?php if (empty($dash['upcoming'])): ?>
        <div class="loka-empty py-4">
            <i class="bi bi-calendar-x"></i>
            <p class="mb-0">No upcoming trips</p>
        </div>
        <?php else: ?>
        <div class="loka-table-responsive">
            <table class="loka-table mb-0">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Requester</th>
                        <th>Vehicle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dash['upcoming'] as $trip): ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/?page=requests&action=view&id=<?= (int) $trip->id ?>" class="font-medium text-primary no-underline hover:underline">
                                <?= formatDateTime($trip->start_datetime) ?>
                            </a>
                        </td>
                        <td><?= e($trip->requester_name ?? '-') ?></td>
                        <td>
                            <?php if (!empty($trip->plate_number)): ?>
                            <span class="loka-badge loka-status-secondary"><?= e($trip->plate_number) ?></span>
                            <?php else: ?>
                            <span class="text-base-content/40">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

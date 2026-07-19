<?php if (!empty($dash['showCharts']) && !empty($dash['analytics'])): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2">
        <div class="loka-card">
            <div class="flex items-center justify-between border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-graph-up mr-2"></i>Trips (Last 7 Days)</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container"><canvas id="dailyTripsChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-span-1">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-pie-chart mr-2"></i>Status Distribution</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container-sm"><canvas id="statusChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <?php if (empty($dash['showUtilization'])): ?>
    <div class="col-span-1 lg:col-span-2">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-clock mr-2"></i>Peak Hours</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container"><canvas id="peakHoursChart"></canvas></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-span-1">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-building mr-2"></i>Trips by Department</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container"><canvas id="departmentChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-span-1">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-clock mr-2"></i>Peak Hours</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container"><canvas id="peakHoursChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-span-1">
        <div class="loka-card">
            <div class="border-b border-base-200 px-5 py-4">
                <h5 class="font-semibold text-base-content mb-0"><i class="bi bi-speedometer2 mr-2"></i>Vehicle Utilization</h5>
            </div>
            <div class="px-5 py-4">
                <div class="loka-chart-container"><canvas id="utilizationChart"></canvas></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    window.dashboardAnalytics = <?= json_encode($dash['analytics']) ?>;
</script>
<script src="<?= ASSETS_PATH ?>/js/charts/dashboard.js" defer></script>
<?php endif; ?>

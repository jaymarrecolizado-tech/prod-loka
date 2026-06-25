        </main>
        

    </div>
    
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <?php if (!UI_MODERN_ENABLED): ?>
    <!-- Bootstrap 5.3 JS (only when modern UI is disabled) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
    
    <!-- DataTables JS (must load before app.js initialization) -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <?php if (!UI_MODERN_ENABLED): ?>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <?php endif; ?>
    
    <!-- Chart.js (deferred) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" defer></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Tom Select JS (deferred - only needed on forms) -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" defer></script>
    
    <!-- Custom JS -->
    <?php if (UI_MODERN_ENABLED): ?>
    <?= viteEntryJsTags('app') ?>
    <?php else: ?>
    <script src="<?= ASSETS_PATH ?>/js/app.js"></script>
    <?php endif; ?>
    
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>

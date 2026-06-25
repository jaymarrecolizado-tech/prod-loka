        </main>
        

    </div>
    
    <!-- Chart.js (deferred) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" defer></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Tom Select JS (deferred - only needed on forms) -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" defer></script>
    
    <!-- Custom JS -->
    <?= viteEntryJsTags('app') ?>
    
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>

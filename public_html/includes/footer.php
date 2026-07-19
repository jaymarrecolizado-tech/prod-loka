        </main>
        

    </div>
    
    <!-- Chart.js then dashboard charts (order matters) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <?php if (!empty($GLOBALS['loka_dashboard_charts_js'])): ?>
    <script src="<?= APP_URL ?>/assets/js/charts/dashboard.js"></script>
    <?php endif; ?>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Tom Select JS (deferred - only needed on forms) -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" defer></script>
    
    <!-- Custom JS -->
    <?= viteEntryJsTags('app') ?>
    <script src="<?= APP_URL ?>/assets/js/submit-guard.js" defer></script>

    <!-- Theme Toggle Script -->
    <script>
    (function() {
        var btn = document.getElementById('themeToggle');
        var iconDark = document.getElementById('themeIconDark');
        var iconLight = document.getElementById('themeIconLight');
        var html = document.documentElement;

        function updateIcons(theme) {
            if (theme === 'loka-light') {
                iconDark.classList.add('hidden');
                iconLight.classList.remove('hidden');
            } else {
                iconDark.classList.remove('hidden');
                iconLight.classList.add('hidden');
            }
        }

        // Set initial icons
        updateIcons(html.getAttribute('data-theme'));

        btn.addEventListener('click', function() {
            var current = html.getAttribute('data-theme');
            var next = current === 'loka' ? 'loka-light' : 'loka';
            html.setAttribute('data-theme', next);
            localStorage.setItem('loka_theme', next);
            updateIcons(next);
        });
    })();
    </script>
    
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>

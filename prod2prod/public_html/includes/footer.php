        </main>
        
        <!-- Version Footer -->
        <footer class="version-footer mt-auto py-2 px-3 border-top bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <span class="fw-medium"><?= APP_NAME ?></span> 
                    <span class="badge bg-secondary ms-1">v<?= APP_VERSION ?></span>
                </small>
                <small class="text-muted">
                    <a href="<?= APP_URL ?>/?page=patch-notes" class="text-decoration-none text-muted">
                        <i class="bi bi-journal-text me-1"></i>Patch Notes
                    </a>
                </small>
            </div>
        </footer>
    </div>
    
    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= ASSETS_PATH ?>/js/app.js"></script>
    
    <?php if (isset($pageScripts)): ?>
    <?= $pageScripts ?>
    <?php endif; ?>
</body>
</html>

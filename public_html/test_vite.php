<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';

echo "CSS tags: " . viteEntryCssTags('app') . PHP_EOL;
echo "JS tags: " . viteEntryJsTags('app') . PHP_EOL;
echo "Admin JS: " . viteEntryJsTags('admin') . PHP_EOL;

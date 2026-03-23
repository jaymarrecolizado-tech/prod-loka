<?php
require 'public_html/includes/config.php';
require 'public_html/classes/Database.php';

$db = Database::getInstance();
$cols = $db->fetchAll('SHOW COLUMNS FROM requests');
print_r($cols);

$trips = $db->fetchAll("SELECT r.* FROM requests r WHERE r.status = 'completed' LIMIT 5");
print_r($trips);

<?php
// Fix path to include index.php env setup but not route
$_SERVER['HTTP_HOST'] = 'localhost';
require 'public_html/index.php';

$trips = db()->fetchAll("SELECT tt.*, r.id as req_id, r.destination as r_dest, r.purpose as r_purpose, r.vehicle_id FROM trip_tickets tt JOIN requests r ON tt.request_id = r.id LIMIT 5");
print_r($trips);

$requests = db()->fetchAll("SELECT * FROM requests WHERE status = 'completed' LIMIT 5");
print_r($requests);

<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$role = getUserRole();

// Route to the appropriate dashboard based on role
$dashboardFile = __DIR__ . "/dashboards/{$role}.php";

if (file_exists($dashboardFile)) {
    require_once $dashboardFile;
} else {
    // Fallback if dashboard file is missing
    die("Dashboard for role {$role} not found.");
}

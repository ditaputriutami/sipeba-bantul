<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

$role = getUserRole();

// Route to the appropriate dashboard based on role
// Map superadmin to admin dashboard
$dashboardRole = ($role === 'superadmin') ? 'admin' : $role;
$dashboardFile = __DIR__ . "/dashboards/{$dashboardRole}.php";

if (file_exists($dashboardFile)) {
    require_once $dashboardFile;
} else {
    // Fallback if dashboard file is missing
    die("Dashboard for role {$role} not found.");
}

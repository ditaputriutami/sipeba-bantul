<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();

// Redirect to unified dashboard
header("Location: ".BASE_URL."/dashboard.php");
exit;

<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireRole(['kepala','superadmin']);
// Kepala dashboard reuses pengurus dashboard logic
require_once __DIR__ . '/pengurus.php';

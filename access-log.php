<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireAdmin();

echo json_encode(dbGetAccessLog(20));

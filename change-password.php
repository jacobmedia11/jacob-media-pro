<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

requireAdmin();

$body        = json_decode(file_get_contents('php://input'), true);
$currentPass = $body['current'] ?? '';
$newPass     = $body['new']     ?? '';
$confirmPass = $body['confirm'] ?? '';

if (strlen($newPass) < 8) {
    echo json_encode(['ok' => false, 'error' => 'Naujas slaptažodis turi būti bent 8 simbolių.']);
    exit;
}

if ($newPass !== $confirmPass) {
    echo json_encode(['ok' => false, 'error' => 'Naujas slaptažodis ir patvirtinimas nesutampa.']);
    exit;
}

// Read current hash — prefer credentials.php, fall back to credentials.json
$phpFile  = __DIR__ . '/credentials.php';
$jsonFile = __DIR__ . '/credentials.json';

$currentHash = '';
if (file_exists($phpFile)) {
    $c           = include $phpFile;
    $currentHash = $c['hash'] ?? '';
} elseif (file_exists($jsonFile)) {
    $c           = json_decode(file_get_contents($jsonFile), true);
    $currentHash = $c['hash'] ?? '';
}

if ($currentHash && !password_verify($currentPass, $currentHash)) {
    echo json_encode(['ok' => false, 'error' => 'Dabartinis slaptažodis neteisingas.']);
    exit;
}

$newHash = password_hash($newPass, PASSWORD_DEFAULT);
// Store in credentials.php — PHP files are never served directly, unlike .json
file_put_contents($phpFile, "<?php return " . var_export(['hash' => $newHash], true) . ";\n");

auditLog('password_change', 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

echo json_encode(['ok' => true]);

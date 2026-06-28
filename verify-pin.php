<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// Brute force protection: max 3 attempts, 60s lockout
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockKey = 'pin_lock_' . $ip;
$cntKey  = 'pin_attempts_' . $ip;

$locked   = $_SESSION[$lockKey] ?? 0;
$attempts = $_SESSION[$cntKey]  ?? 0;

if ($locked && time() < $locked) {
    $wait = $locked - time();
    echo json_encode(['ok' => false, 'error' => "Per daug bandymų. Palaukite {$wait}s."]);
    exit;
}

if ($locked && time() >= $locked) {
    $_SESSION[$lockKey] = 0;
    $_SESSION[$cntKey]  = 0;
    $attempts = 0;
}

$body     = json_decode(file_get_contents('php://input'), true);
$clientId = trim($body['clientId'] ?? '');
$pin      = trim($body['pin']      ?? '');

$client = dbGetClient($clientId);

if (!$client) {
    echo json_encode(['ok' => false, 'error' => 'Nežinomas klientas.']);
    exit;
}

if ($pin === $client['pin']) {
    $_SESSION[$cntKey]              = 0;
    $_SESSION['pin_ok_' . $clientId] = true;
    dbLogAccess($clientId, $client['name'], $ip);
    echo json_encode(['ok' => true]);
} else {
    $attempts++;
    $_SESSION[$cntKey] = $attempts;
    if ($attempts >= 3) {
        $_SESSION[$lockKey] = time() + 60;
        echo json_encode(['ok' => false, 'error' => 'Per daug bandymų. Palaukite 60s.']);
    } else {
        $left = 3 - $attempts;
        echo json_encode(['ok' => false, 'error' => "Neteisingas PIN. Liko bandymų: {$left}."]);
    }
}

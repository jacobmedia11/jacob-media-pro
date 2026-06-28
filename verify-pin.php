<?php
session_start();
header('Content-Type: application/json');

// Brute force apsauga: max 3 bandymai, 60s blokavimas
$ip       = $_SERVER['REMOTE_ADDR'];
$lockKey  = 'pin_lock_' . $ip;
$cntKey   = 'pin_attempts_' . $ip;

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

// Load PINs from server-side config
$cfg     = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$clients = $cfg['clients'] ?? [];
$client  = null;
foreach ($clients as $c) {
  if ($c['id'] === $clientId) { $client = $c; break; }
}

if (!$client) {
  echo json_encode(['ok' => false, 'error' => 'Nežinomas klientas.']);
  exit;
}

if ($pin === $client['pin']) {
  $_SESSION[$cntKey]              = 0;
  $_SESSION['pin_ok_' . $clientId] = true;

  // Access log
  $log = [
    'client'  => $client['name'],
    'id'      => $clientId,
    'time'    => date('Y-m-d H:i:s'),
    'ip'      => $_SERVER['REMOTE_ADDR'],
  ];
  $logFile = __DIR__ . '/access-log.json';
  $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
  array_unshift($logs, $log);
  $logs = array_slice($logs, 0, 200); // max 200 įrašų
  file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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

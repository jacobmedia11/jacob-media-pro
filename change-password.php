<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['authed'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

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

$credFile    = __DIR__ . '/credentials.json';
$creds       = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];
$currentHash = $creds['hash'] ?? '';

// If no hash set yet (first-time setup), skip current password check
if ($currentHash && !password_verify($currentPass, $currentHash)) {
  echo json_encode(['ok' => false, 'error' => 'Dabartinis slaptažodis neteisingas.']);
  exit;
}

$newHash = password_hash($newPass, PASSWORD_DEFAULT);
file_put_contents($credFile, json_encode(['hash' => $newHash], JSON_PRETTY_PRINT));

echo json_encode(['ok' => true]);

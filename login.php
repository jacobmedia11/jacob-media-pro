<?php
session_start();
header('Content-Type: application/json');

define('VALID_USER', 'admin');

// Hash stored in credentials.json (blocked from web by .htaccess)
$_credFile = __DIR__ . '/credentials.json';
$_creds    = file_exists($_credFile) ? json_decode(file_get_contents($_credFile), true) : [];
define('VALID_PASS_HASH', $_creds['hash'] ?? '');

define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SEC',  60);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = 'attempts_' . md5($ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = json_decode(file_get_contents('php://input'), true);
  $user = trim($body['user'] ?? '');
  $pass = trim($body['pass'] ?? '');

  // Rate limiting
  $attempts = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];

  if ($attempts['until'] > time()) {
    $wait = $attempts['until'] - time();
    echo json_encode(['ok' => false, 'error' => "Užrakinta. Palaukite {$wait}s."]);
    exit;
  }

  if (!VALID_PASS_HASH) {
    echo json_encode(['ok' => false, 'error' => 'Slaptažodis dar nenustatytas. Prisijunkite prie hub.php ir nustatykite slaptažodį.']);
    exit;
  }

  if ($user === VALID_USER && password_verify($pass, VALID_PASS_HASH)) {
    $_SESSION[$key] = ['count' => 0, 'until' => 0];
    $_SESSION['authed'] = true;
    $_SESSION['authed_at'] = time();
    echo json_encode(['ok' => true]);
  } else {
    $attempts['count'] = ($attempts['count'] ?? 0) + 1;
    if ($attempts['count'] >= MAX_ATTEMPTS) {
      $attempts['until'] = time() + LOCKOUT_SEC;
      $attempts['count'] = 0;
    }
    $_SESSION[$key] = $attempts;
    $left = MAX_ATTEMPTS - $attempts['count'];
    echo json_encode(['ok' => false, 'error' => "Neteisingi duomenys. Liko bandymų: {$left}."]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'logout') {
  session_destroy();
  echo json_encode(['ok' => true]);
  exit;
}

echo json_encode(['ok' => false, 'error' => 'Bad request']);

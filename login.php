<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

define('VALID_USER', 'admin');

// credentials.php stores the hash as a PHP return value — never directly served.
// Falls back to legacy credentials.json during migration.
function _loadHash(): string {
    $phpFile  = __DIR__ . '/credentials.php';
    $jsonFile = __DIR__ . '/credentials.json';

    if (file_exists($phpFile)) {
        $c = include $phpFile;
        return $c['hash'] ?? '';
    }
    if (file_exists($jsonFile)) {
        $c = json_decode(file_get_contents($jsonFile), true);
        return $c['hash'] ?? '';
    }
    return '';
}

define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SEC',  60);

$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = 'attempts_' . md5($ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $user = trim($body['user'] ?? '');
    $pass = trim($body['pass'] ?? '');

    $attempts = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];

    if ($attempts['until'] > time()) {
        $wait = $attempts['until'] - time();
        echo json_encode(['ok' => false, 'error' => "Užrakinta. Palaukite {$wait}s."]);
        exit;
    }

    $hash = _loadHash();
    if (!$hash) {
        echo json_encode(['ok' => false, 'error' => 'Slaptažodis dar nenustatytas. Prisijunkite prie hub.php ir nustatykite slaptažodį.']);
        exit;
    }

    if ($user === VALID_USER && password_verify($pass, $hash)) {
        $_SESSION[$key]           = ['count' => 0, 'until' => 0];
        $_SESSION['authed']       = true;
        $_SESSION['authed_at']    = time();
        $_SESSION['last_activity'] = time();
        auditLog('admin_login', 'IP: ' . $ip);
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
    auditLog('admin_logout', 'IP: ' . $ip);
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Bad request']);

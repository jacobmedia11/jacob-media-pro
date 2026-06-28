<?php
// Returns client list WITHOUT pins — safe for browser
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['authed'])) {
  // Allow reading basic client info for PIN form (no sensitive data)
  $clientId = $_GET['id'] ?? '';
  $cfg = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
  if ($clientId) {
    $client = null;
    foreach ($cfg['clients'] as $c) {
      if ($c['id'] === $clientId) {
        $client = ['id' => $c['id'], 'name' => $c['name'], 'account' => $c['account'], 'excluded' => $c['excluded'] ?? []];
        break;
      }
    }
    echo json_encode($client ?: null);
  } else {
    echo json_encode(null);
  }
  exit;
}

$cfg = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$clients = array_map(fn($c) => [
  'id'            => $c['id'],
  'name'          => $c['name'],
  'account'       => $c['account'],
  'email'         => $c['email'],
  'excluded'      => $c['excluded'] ?? [],
  'token_error'   => $c['token_error']    ?? null,
  'token_error_at'=> $c['token_error_at'] ?? null,
], $cfg['clients']);

echo json_encode($clients);

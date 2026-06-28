<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// Unauthenticated: only serve minimal public info for the PIN form
if (empty($_SESSION['authed'])) {
    $clientId = $_GET['id'] ?? '';
    if ($clientId) {
        $c = dbGetClient($clientId);
        if ($c) {
            echo json_encode(['id' => $c['id'], 'name' => $c['name'], 'account' => $c['account'], 'excluded' => $c['excluded']]);
        } else {
            echo json_encode(null);
        }
    } else {
        echo json_encode(null);
    }
    exit;
}

$clients = dbGetClients();
echo json_encode(array_map(fn($c) => [
    'id'             => $c['id'],
    'name'           => $c['name'],
    'account'        => $c['account'],
    'email'          => $c['email'],
    'excluded'       => $c['excluded'],
    'token_error'    => $c['token_error']    ?? null,
    'token_error_at' => $c['token_error_at'] ?? null,
], $clients));

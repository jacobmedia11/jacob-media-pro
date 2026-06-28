<?php
session_start();

$clientId = preg_replace('/[^a-z0-9\-]/', '', $_GET['client']  ?? '');
$invId    = preg_replace('/[^0-9]/',       '', $_GET['inv']     ?? '');
$pinOk    = $clientId && !empty($_SESSION['pin_ok_' . $clientId]);

if (empty($_SESSION['authed']) && !$pinOk) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

if (!$clientId || !$invId) {
  http_response_code(400);
  echo 'Missing parameters';
  exit;
}

$cfg   = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$token = '';

foreach ($cfg['clients'] as $c) {
  if ($c['id'] === $clientId) {
    $token = $c['token'] ?? '';
    break;
  }
}

if (!$token) {
  http_response_code(404);
  echo 'Client not found';
  exit;
}

// Fetch the download_uri from Meta
$ch = curl_init(
  "https://graph.facebook.com/v19.0/{$invId}"
  . "?fields=download_uri"
  . "&access_token={$token}"
);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$res  = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$uri  = $data['download_uri'] ?? '';

if (!$uri) {
  http_response_code(404);
  echo 'Invoice PDF not available';
  exit;
}

// Proxy the PDF from Meta to the client
$ch = curl_init($uri);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$pdf      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$pdf) {
  http_response_code(502);
  echo 'Failed to fetch PDF from Meta';
  exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice-' . $invId . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;

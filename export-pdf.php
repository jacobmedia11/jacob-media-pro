<?php
session_start();

$account  = preg_replace('/[^0-9]/', '', $_GET['account'] ?? '');
$preset   = preg_replace('/[^a-z0-9_]/', '', $_GET['preset'] ?? 'last_30d');

// Auth: admin session OR client with verified PIN
$clientId = preg_replace('/[^a-z0-9\-]/', '', $_GET['client'] ?? '');
$pinOk    = $clientId && !empty($_SESSION['pin_ok_' . $clientId]);

if (empty($_SESSION['authed']) && !$pinOk) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

// Load token from clients-config.json
$cfg   = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$token = '';
$name  = '';
foreach ($cfg['clients'] as $c) {
  if ($c['account'] === $account) {
    $token = $c['token'] ?? '';
    $name  = $c['name'];
    break;
  }
}

if (!$token) { echo 'Token not found'; exit; }

require_once __DIR__ . '/email-builder.php';

$block    = buildBlock($name, $account, $token, $preset);
$subject  = 'Meta Ads ataskaita';
$html     = buildEmail($name, $block, $preset, $subject, '');
$filename = $name . '-' . date('Y-m-d') . '.pdf';
$pdf      = generatePdf($html, $filename);

if (!$pdf) {
  echo 'PDF generavimas nepavyko. Įdiekite DOMPDF: composer install';
  exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;

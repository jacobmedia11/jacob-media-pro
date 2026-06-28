<?php
require_once __DIR__ . '/smtp-config.php';
require_once __DIR__ . '/email-builder.php';

// Load clients from config
$cfg     = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$clients = $cfg['clients'] ?? [];

$week    = date('Y-m-d', strtotime('-7 days')) . ' – ' . date('Y-m-d');
$subject = 'Savaitinė Meta Ads ataskaita · ' . date('Y-m-d');
$preview = $_GET['preview'] ?? '';

// ?preview=owner  → all clients summary (for admin)
// ?preview=slug   → single client preview
// (no param)      → actually send emails
if ($preview === 'owner') {
  $allBlocks = '';
  foreach ($clients as $c) {
    if (empty($c['token'])) continue;
    $allBlocks .= buildBlock($c['name'], $c['account'], $c['token'], 'last_7d');
  }
  echo buildEmail('Visi klientai', $allBlocks, 'last_7d', $subject, '');
  exit;
}

if ($preview !== '') {
  foreach ($clients as $c) {
    $slug = strtolower(preg_replace('/[^a-z0-9]/i', '-', $c['name']));
    if ($slug === $preview || ($c['email'] ?? '') === $preview) {
      echo buildEmail($c['name'], buildBlock($c['name'], $c['account'], $c['token'], 'last_7d'), 'last_7d', $subject, '');
      exit;
    }
  }
  echo 'Client not found.';
  exit;
}

// Send emails
$log = date('Y-m-d H:i:s') . "\n";

// Summary to admin
$allBlocks = '';
foreach ($clients as $c) {
  if (empty($c['token'])) continue;
  $allBlocks .= buildBlock($c['name'], $c['account'], $c['token'], 'last_7d');
}
$r    = smtpSend(TO_ADMIN, $subject, buildEmail('Visi klientai', $allBlocks, 'last_7d', $subject, ''));
$log .= "  Summary → " . TO_ADMIN . ": $r\n";

// Individual emails to clients
foreach ($clients as $c) {
  if (empty($c['email']) || empty($c['token'])) continue;
  $schedule = $c['email_schedule'] ?? 'none';
  if ($schedule === 'none') continue;

  $block = buildBlock($c['name'], $c['account'], $c['token'], 'last_7d');
  $html  = buildEmail($c['name'], $block, 'last_7d', $subject, $c['email_intro'] ?? '');
  $r     = smtpSend($c['email'], $subject, $html);
  $log  .= "  {$c['name']} → {$c['email']}: $r\n";
}

file_put_contents(__DIR__ . '/report-log.txt', $log . "\n", FILE_APPEND);
echo "Done. Check report-log.txt for details.";

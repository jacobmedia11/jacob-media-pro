<?php
// Runs daily via Hostinger Cron Job: 0 8 * * *
// Checks schedule config and sends emails when needed

$CONFIG_FILE = __DIR__ . '/reports-config.json';
require_once __DIR__ . '/email-builder.php';

// Load tokens from clients-config.json (same source as api-proxy.php)
$clientsCfg = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$TOKENS = [];
foreach ($clientsCfg['clients'] as $c) {
  if (!empty($c['token'])) $TOKENS[$c['account']] = $c['token'];
}

function shouldSend($schedule) {
  $dow = (int)date('w'); // 0=Sun, 1=Mon
  $dom = (int)date('j'); // day of month
  return match($schedule) {
    'daily'   => true,
    'weekly'  => $dow === 1, // Monday
    'monthly' => $dom === 1, // 1st of month
    default   => false,
  };
}

function scheduleToPeriod($schedule) {
  return match($schedule) {
    'daily'   => 'yesterday',
    'monthly' => 'last_month',
    default   => 'last_7d',
  };
}

$cfg = json_decode(file_get_contents($CONFIG_FILE), true);
$log = date('Y-m-d H:i:s') . " [cron]\n";
$sent = false;

// Owner summary
if (!empty($cfg['owner_email']) && shouldSend($cfg['owner_schedule'] ?? 'weekly')) {
  $preset = scheduleToPeriod($cfg['owner_schedule']);
  $blocks = '';
  foreach ($cfg['clients'] as $c) {
    $token = $TOKENS[$c['account']] ?? '';
    $blocks .= buildBlock($c['name'], $c['account'], $token, $preset);
  }
  $html = buildEmail('Visi klientai', $blocks, $preset, $cfg['email_subject'], '');
  $r    = smtpSend($cfg['owner_email'], $cfg['email_subject'] . ' · ' . date('Y-m-d'), $html);
  $log .= "  Suvestinė → {$cfg['owner_email']}: $r\n";
  $cfg['last_sent']['owner'] = date('Y-m-d H:i:s');
  $sent = true;
}

// Per client
foreach ($cfg['clients'] as &$c) {
  if (empty($c['email'])) continue;
  if (!shouldSend($c['schedule'] ?? 'weekly')) continue;

  $token  = $TOKENS[$c['account']] ?? '';
  $preset = scheduleToPeriod($c['schedule']);
  $block  = buildBlock($c['name'], $c['account'], $token, $preset);
  $html   = buildEmail($c['name'], $block, $preset, $cfg['email_subject'], $c['email_intro'] ?? '');
  $r      = smtpSend($c['email'], $cfg['email_subject'] . ' · ' . date('Y-m-d'), $html);
  $log   .= "  {$c['name']} → {$c['email']}: $r\n";
  $c['last_sent'] = date('Y-m-d H:i:s');
  $cfg['last_sent'][$c['id']] = date('Y-m-d H:i:s');
  $sent = true;
}

if (!$sent) $log .= "  Nieko siųsti šiandien.\n";

file_put_contents($CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/report-log.txt', $log . "\n", FILE_APPEND);
echo $log;

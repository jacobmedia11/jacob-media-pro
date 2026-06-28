<?php
session_start();
header('Content-Type: application/json');

// Load tokens from clients-config.json
$cfg    = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$TOKENS = [];
foreach ($cfg['clients'] as $c) {
  if (!empty($c['token'])) $TOKENS[$c['account']] = $c['token'];
}

// Resolve account → client ID to check PIN session
$accountParam = preg_replace('/[^0-9]/', '', $_GET['account'] ?? '');
$clientId = '';
foreach ($cfg['clients'] as $c) {
  if ($c['account'] === $accountParam) { $clientId = $c['id']; break; }
}
$pinOk = $clientId && !empty($_SESSION['pin_ok_' . $clientId]);

// Require admin session OR verified PIN for this specific client
if (empty($_SESSION['authed']) && !$pinOk) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

// Rate limiting per IP
$ip  = md5($_SERVER['REMOTE_ADDR'] ?? 'x');
$key = 'proxy_rate_' . $ip;
$rate = $_SESSION[$key] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rate['reset']) {
  $rate = ['count' => 0, 'reset' => time() + 60];
}

$rate['count']++;
$_SESSION[$key] = $rate;

if ($rate['count'] > 60) {
  http_response_code(429);
  echo json_encode(['error' => 'Per daug užklausų. Bandykite po minutės.']);
  exit;
}

$account = preg_replace('/[^0-9]/', '', $_GET['account'] ?? '');
$preset  = preg_replace('/[^a-z0-9_]/', '', $_GET['preset'] ?? 'last_30d');
$type    = $_GET['type'] ?? 'campaigns';

if (!$account || !isset($TOKENS[$account])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid account']);
  exit;
}

$token = $TOKENS[$account];

// Previous period summary for week-over-week comparison
if (isset($_GET['prev'])) {
  $days = match($preset) {
    'yesterday' => 1, 'last_7d' => 7, 'last_14d' => 14,
    'last_30d'  => 30, 'last_90d' => 90, default => 7,
  };
  $until = date('Y-m-d', strtotime("-{$days} days"));
  $since = date('Y-m-d', strtotime('-' . ($days * 2) . ' days'));
  $fields = "id,name,insights{spend,impressions,clicks,actions}";
  $url = "https://graph.facebook.com/v19.0/act_{$account}/campaigns"
       . "?fields=" . urlencode($fields)
       . "&time_range=" . urlencode(json_encode(['since' => $since, 'until' => $until]))
       . "&limit=50&access_token=" . $token;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  $response = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($response, true);
  $ts = 0; $tc = 0; $ti = 0; $tl = 0;
  foreach ($data['data'] ?? [] as $c) {
    $ins = $c['insights']['data'][0] ?? null;
    if (!$ins) continue;
    $ts += (float)($ins['spend'] ?? 0);
    $tc += (int)($ins['clicks'] ?? 0);
    $ti += (int)($ins['impressions'] ?? 0);
    $actions = $ins['actions'] ?? [];
    foreach ($actions as $a) {
      if (in_array($a['action_type'], ['lead','onsite_conversion.lead_grouped'])) $tl += (int)$a['value'];
    }
  }
  echo json_encode(['spend' => $ts, 'clicks' => $tc, 'impressions' => $ti, 'leads' => $tl]);
  exit;
}

if ($type === 'quickstats') {
  $fields = "id,name,insights.date_preset({$preset}){spend,impressions,clicks,ctr,actions}";
} else {
  $fields = "id,name,status,insights.date_preset({$preset}){spend,impressions,clicks,ctr,cpc,reach,actions}";
}

$url = "https://graph.facebook.com/v19.0/act_{$account}/campaigns"
     . "?fields=" . urlencode($fields)
     . "&limit=50"
     . "&access_token=" . $token;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Detect token expiry / invalid token — notify admin by email (once per incident)
$data = json_decode($response, true);
$tokenErrorCodes = [190, 102, 4, 200, 10, 17];
if (isset($data['error']) && in_array($data['error']['code'] ?? 0, $tokenErrorCodes)) {
  $cfg2 = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
  $changed = false;
  $affectedName = '';
  foreach ($cfg2['clients'] as &$c) {
    if ($c['account'] === $account && empty($c['token_error'])) {
      $c['token_error']    = $data['error']['message'] ?? 'Token invalid';
      $c['token_error_at'] = date('Y-m-d H:i');
      $affectedName        = $c['name'];
      $changed = true;
      break;
    }
  }
  if ($changed) {
    file_put_contents(__DIR__ . '/clients-config.json', json_encode($cfg2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // Send one-time alert email to admin
    require_once __DIR__ . '/email-builder.php';
    $errMsg  = htmlspecialchars($data['error']['message'] ?? 'Token invalid');
    $errCode = (int)($data['error']['code'] ?? 0);
    $hubUrl  = 'https://klientams.jokubomokymai.lt/hub.php';
    $html = "<!DOCTYPE html><html><body style='background:#0a0a0a;margin:0;padding:32px;font-family:sans-serif'>
<table width='600' style='margin:0 auto;background:#111;border-radius:12px;padding:32px'>
  <tr><td>
    <div style='margin-bottom:20px'>
      <span style='background:#E8720A;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:900;color:#fff;margin-right:8px'>JMP</span>
      <span style='font-size:18px;font-weight:800;color:#fff'>Jacob<span style='color:#E8720A'>Media Pro</span></span>
    </div>
    <div style='background:rgba(248,113,113,0.1);border:1px solid #f87171;border-radius:8px;padding:16px 20px;margin-bottom:20px'>
      <div style='color:#f87171;font-size:15px;font-weight:700;margin-bottom:6px'>⚠️ Meta API tokenas nebegalioja</div>
      <div style='color:#aaa;font-size:13px'>Klientas: <strong style='color:#fff'>{$affectedName}</strong></div>
      <div style='color:#aaa;font-size:13px;margin-top:4px'>Paskyra: <code style='color:#888'>act_{$account}</code></div>
      <div style='color:#aaa;font-size:13px;margin-top:4px'>Klaida: {$errMsg} (kodas {$errCode})</div>
      <div style='color:#aaa;font-size:13px;margin-top:4px'>Laikas: " . date('Y-m-d H:i') . "</div>
    </div>
    <p style='color:#aaa;font-size:14px;margin-bottom:20px'>Reikia atnaujinti Meta API tokeną kliento redagavimo lange.</p>
    <a href='{$hubUrl}' style='display:inline-block;padding:12px 24px;background:#E8720A;color:#fff;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none'>→ Atidaryti Jacob Media Pro</a>
  </td></tr>
</table>
</body></html>";
    smtpSend('jokuubas11@gmail.com', "⚠️ Token klaida — {$affectedName} | Jacob Media Pro", $html);
    require_once __DIR__ . '/telegram.php';
    telegramSend("⚠️ <b>Meta token expired</b>\nClient: <b>{$affectedName}</b>\nAccount: act_{$account}\nError: {$errMsg}\nTime: " . date('Y-m-d H:i'));
  }
} elseif (!isset($data['error']) && $account) {
  // Clear previous error if API call succeeded
  $cfg2 = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
  $changed = false;
  foreach ($cfg2['clients'] as &$c) {
    if ($c['account'] === $account && !empty($c['token_error'])) {
      unset($c['token_error'], $c['token_error_at']);
      $changed = true;
      break;
    }
  }
  if ($changed) file_put_contents(__DIR__ . '/clients-config.json', json_encode($cfg2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

http_response_code($httpCode);
echo $response;

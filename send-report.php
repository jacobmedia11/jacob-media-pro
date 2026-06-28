<?php
// ── Config ────────────────────────────────────────────────────────────
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'noreply@klientams.jokubomokymai.lt');
define('SMTP_PASS', 'Jn0@vAH7Nb^');
define('FROM_NAME', 'Jacob Media Pro');
define('TO_EMAIL',  'jokuubas11@gmail.com');

$CLIENTS = [
  [
    'name'    => 'Žvaigždžių Slėnis',
    'account' => '924585781724581',
    'token'   => 'EAAYENePLFm4BR7NODrRAtViQNqspuiYmKcVVqBSA53zLTYTeijiQcPnnC9afcQYJUTL9ccwdwXZAkhXZCyEHAh2GbhKZBKA5aEVqOsaVnj42HzIW4loJIun7ZCoE8qZA3OGrXd5rR3pak5Qso7xwdwIjsrbggyIpGBsBevM2buHRNjKXXMdtsSLghjyrstGPO8NTBWL1vLAje9ZAkKwNy1ujkv0nvbFBKd5ZANH',
    'email'   => null,
  ],
  [
    'name'    => 'Mentalinė Aritmetika',
    'account' => '875024141622155',
    'token'   => 'EAAYENePLFm4BR1989NxPPtzsS7dvk3opzHIUOHoUl6zJSZBA9BtXT5PlC62VkFWqLryZBenMBo9WwkZAvZC7ZBIFXw7hyXhV0tkGerGP7coZCXvV7ZA5JhUbEU8R4R13cSjm0L3r5bhGYi8OW9v62PZBCw3p2AZCoTo65gZCmsY3QL1OoM1bgASonyBcTlTVXZAyHYAnwZDZD',
    'email'   => null,
  ],
  [
    'name'    => 'Sanforas MB',
    'account' => '742179047287233',
    'token'   => 'EAAYENePLFm4BR1989NxPPtzsS7dvk3opzHIUOHoUl6zJSZBA9BtXT5PlC62VkFWqLryZBenMBo9WwkZAvZC7ZBIFXw7hyXhV0tkGerGP7coZCXvV7ZA5JhUbEU8R4R13cSjm0L3r5bhGYi8OW9v62PZBCw3p2AZCoTo65gZCmsY3QL1OoM1bgASonyBcTlTVXZAyHYAnwZDZD',
    'email'   => 'info@sanforas.lt',
  ],
];

// ── Fetch Meta data ───────────────────────────────────────────────────
function fetchClient($account, $token, $preset = 'last_7d') {
  $fields = 'campaign_name,spend,impressions,clicks,ctr,cpc,actions';
  $url = "https://graph.facebook.com/v19.0/act_{$account}/insights"
       . "?date_preset={$preset}&fields={$fields}&level=campaign&limit=50"
       . "&access_token={$token}";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  $res = curl_exec($ch);
  curl_close($ch);
  return json_decode($res, true);
}

function getLeads($actions) {
  if (!$actions) return 0;
  foreach ($actions as $a) {
    if (in_array($a['action_type'], ['lead', 'onsite_conversion.lead_grouped'])) return (int)$a['value'];
  }
  return 0;
}

// ── Build client HTML block ───────────────────────────────────────────
function buildBlock($name, $account, $token) {
  $data = fetchClient($account, $token, 'last_7d');
  $campaigns = $data['data'] ?? [];

  $totalSpend = 0; $totalClicks = 0; $totalImpr = 0; $totalLeads = 0;
  $rows = '';
  foreach ($campaigns as $c) {
    $spend  = (float)($c['spend'] ?? 0);
    $clicks = (int)($c['clicks'] ?? 0);
    $impr   = (int)($c['impressions'] ?? 0);
    $ctr    = (float)($c['ctr'] ?? 0);
    $cpc    = (float)($c['cpc'] ?? 0);
    $leads  = getLeads($c['actions'] ?? []);
    if ($spend == 0) continue;

    $totalSpend  += $spend;
    $totalClicks += $clicks;
    $totalImpr   += $impr;
    $totalLeads  += $leads;

    $rows .= "<tr>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#f0f0f0;font-size:13px'>{$c['campaign_name']}</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#E8720A;font-weight:700;text-align:right'>€" . number_format($spend,2) . "</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#888;text-align:right'>" . number_format($impr) . "</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#888;text-align:right'>" . number_format($clicks) . "</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#34d399;text-align:right'>" . number_format($ctr,2) . "%</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#888;text-align:right'>€" . number_format($cpc,3) . "</td>
      <td style='padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#a78bfa;text-align:right'>" . ($leads ?: '—') . "</td>
    </tr>";
  }

  $avgCtr = $totalImpr > 0 ? ($totalClicks / $totalImpr * 100) : 0;
  $cpl    = $totalLeads > 0 ? '€' . number_format($totalSpend / $totalLeads, 2) : '—';

  return "
  <div style='margin-bottom:32px'>
    <h2 style='color:#f0f0f0;font-size:16px;margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #E8720A'>{$name}</h2>
    <div style='display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap'>
      <div style='background:#1e1e1e;border-radius:8px;padding:12px 18px;flex:1;min-width:100px'>
        <div style='color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>Išlaidos</div>
        <div style='color:#E8720A;font-size:20px;font-weight:700;margin-top:4px'>€" . number_format($totalSpend,2) . "</div>
      </div>
      <div style='background:#1e1e1e;border-radius:8px;padding:12px 18px;flex:1;min-width:100px'>
        <div style='color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>Paspaudimai</div>
        <div style='color:#f0f0f0;font-size:20px;font-weight:700;margin-top:4px'>" . number_format($totalClicks) . "</div>
      </div>
      <div style='background:#1e1e1e;border-radius:8px;padding:12px 18px;flex:1;min-width:100px'>
        <div style='color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>CTR</div>
        <div style='color:#34d399;font-size:20px;font-weight:700;margin-top:4px'>" . number_format($avgCtr,2) . "%</div>
      </div>
      <div style='background:#1e1e1e;border-radius:8px;padding:12px 18px;flex:1;min-width:100px'>
        <div style='color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>Potenc. klientai</div>
        <div style='color:#a78bfa;font-size:20px;font-weight:700;margin-top:4px'>" . ($totalLeads ?: '—') . "</div>
      </div>
      <div style='background:#1e1e1e;border-radius:8px;padding:12px 18px;flex:1;min-width:100px'>
        <div style='color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>CPL</div>
        <div style='color:#f0f0f0;font-size:20px;font-weight:700;margin-top:4px'>{$cpl}</div>
      </div>
    </div>
    <table style='width:100%;border-collapse:collapse;background:#141414;border-radius:8px;overflow:hidden'>
      <thead>
        <tr style='background:#1e1e1e'>
          <th style='padding:8px 12px;text-align:left;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>Kampanija</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>Išlaidos</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>Parodymai</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>Paspaudimai</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>CTR</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>CPC</th>
          <th style='padding:8px 12px;text-align:right;color:#888;font-size:10px;text-transform:uppercase'>Klientai</th>
        </tr>
      </thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>";
}

function buildEmail($title, $block, $week) {
  return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='background:#0a0a0a;color:#f0f0f0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;padding:32px;max-width:800px;margin:0 auto'>
  <div style='margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid #2a2a2a'>
    <div style='font-size:20px;font-weight:800;margin-bottom:4px'>Jacob<span style='color:#E8720A'>Media Pro</span></div>
    <div style='color:#888;font-size:13px'>Savaitinė ataskaita · {$week}</div>
  </div>
  {$block}
  <div style='margin-top:32px;padding-top:20px;border-top:1px solid #2a2a2a;color:#555;font-size:11px'>
    Ataskaita sugeneruota automatiškai · klientams.jokubomokymai.lt
  </div>
</body></html>";
}

// ── Send via SMTP SSL ─────────────────────────────────────────────────
function smtpSend($to, $subject, $html) {
  $host = SMTP_HOST; $port = SMTP_PORT;
  $user = SMTP_USER; $pass = SMTP_PASS;
  $from = SMTP_USER; $fromName = FROM_NAME;

  $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
  $sock = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
  if (!$sock) return "Connect error: $errstr ($errno)";

  function r($sock) { return fgets($sock, 512); }
  function w($sock, $cmd) { fwrite($sock, $cmd . "\r\n"); }

  r($sock);
  w($sock, "EHLO " . gethostname()); while(($l=r($sock)) && substr($l,3,1)=='-');
  w($sock, "AUTH LOGIN");           r($sock);
  w($sock, base64_encode($user));   r($sock);
  w($sock, base64_encode($pass));   $auth = r($sock);
  if (strpos($auth, '235') === false) { fclose($sock); return "Auth failed: $auth"; }

  $msg  = "Date: " . date('r') . "\r\n";
  $msg .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
  $msg .= "To: {$to}\r\n";
  $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
  $msg .= "MIME-Version: 1.0\r\n";
  $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
  $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
  $msg .= chunk_split(base64_encode($html));

  w($sock, "MAIL FROM:<{$from}>"); r($sock);
  w($sock, "RCPT TO:<{$to}>");    r($sock);
  w($sock, "DATA");               r($sock);
  w($sock, $msg . "\r\n.");       $res = r($sock);
  w($sock, "QUIT");
  fclose($sock);

  return strpos($res, '250') !== false ? 'OK' : "Send error: $res";
}

// ── Preview / Send ────────────────────────────────────────────────────
$week    = date('Y-m-d', strtotime('-7 days')) . ' – ' . date('Y-m-d');
$subject = 'Savaitinė Meta Ads ataskaita · ' . date('Y-m-d');
$preview = $_GET['preview'] ?? '';

// ?preview=owner  → suvestinė visiems klientams
// ?preview=sanforas-mb → tik tas klientas
// (be parametro) → siųsti iš tikrųjų
if ($preview === 'owner') {
  $allBlocks = '';
  foreach ($CLIENTS as $c) {
    $allBlocks .= buildBlock($c['name'], $c['account'], $c['token']);
  }
  echo buildEmail('Visi klientai', $allBlocks, $week);
  exit;
}

if ($preview !== '') {
  foreach ($CLIENTS as $c) {
    $slug = strtolower(preg_replace('/[^a-z0-9]/i', '-', $c['name']));
    if ($slug === $preview || $c['email'] === $preview) {
      $block = buildBlock($c['name'], $c['account'], $c['token']);
      echo buildEmail($c['name'], $block, $week);
      exit;
    }
  }
  echo "Klientas nerastas.";
  exit;
}

// Siųsti
$log = date('Y-m-d H:i:s') . "\n";

// Suvestinė → tau
$allBlocks = '';
foreach ($CLIENTS as $c) {
  $allBlocks .= buildBlock($c['name'], $c['account'], $c['token']);
}
$r = smtpSend(TO_EMAIL, $subject, buildEmail('Visi klientai', $allBlocks, $week));
$log .= "  Suvestinė → " . TO_EMAIL . ": $r\n";

// Atskiri laiškai klientams
foreach ($CLIENTS as $c) {
  if (empty($c['email'])) continue;
  $block = buildBlock($c['name'], $c['account'], $c['token']);
  $r     = smtpSend($c['email'], $subject, buildEmail($c['name'], $block, $week));
  $log  .= "  {$c['name']} → {$c['email']}: $r\n";
}

file_put_contents(__DIR__ . '/report-log.txt', $log . "\n", FILE_APPEND);
echo "✅ Ataskaitos išsiųstos.";

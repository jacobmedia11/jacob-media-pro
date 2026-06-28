<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email-builder.php';

$body     = json_decode(file_get_contents('php://input'), true);
$clientId = preg_replace('/[^a-z0-9\-]/', '', $body['clientId'] ?? '');

if (!$clientId) {
    echo json_encode(['ok' => false, 'error' => 'Nenurodyta paskyra.']);
    exit;
}

// Rate limit: max 3 resets per client per hour
$rateKey  = 'forgot_pin_' . $clientId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 3600];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 3600];
}

if ($rateData['count'] >= 3) {
    $wait = ceil(($rateData['reset'] - time()) / 60);
    echo json_encode(['ok' => false, 'error' => "Per daug bandymų. Bandykite po {$wait} min."]);
    exit;
}

$rateData['count']++;
$_SESSION[$rateKey] = $rateData;

$client = dbGetClient($clientId);

if (!$client) {
    echo json_encode(['ok' => false, 'error' => 'Paskyra nerasta.']);
    exit;
}

$clientEmail = $client['email'] ?? '';
if (!$clientEmail) {
    echo json_encode(['ok' => false, 'error' => 'Šiai paskyrai el. paštas nenurodytas. Susisiekite su administratoriumi.']);
    exit;
}

$pin        = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
$clientName = $client['name'];

dbUpdateClient($clientId, ['pin' => $pin]);

$url     = 'https://klientams.jokubomokymai.lt/hub.php?client=' . urlencode($clientId);
$subject = 'Jūsų naujas PIN kodas — Jacob Media Pro';
$html    = "<!DOCTYPE html><html><body style='background:#0a0a0a;margin:0;padding:32px;font-family:sans-serif'>
<table width='600' style='margin:0 auto;background:#111;border-radius:12px;padding:32px'>
  <tr><td>
    <div style='margin-bottom:24px'>
      <span style='background:#E8720A;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:900;color:#fff;margin-right:8px'>JMP</span>
      <span style='font-size:18px;font-weight:800;color:#fff'>Jacob<span style='color:#E8720A'>Media Pro</span></span>
    </div>
    <p style='color:#aaa;font-size:15px;line-height:1.6'>Sveiki, {$clientName},</p>
    <p style='color:#aaa;font-size:15px;line-height:1.6;margin-top:8px'>Gavome užklausą atstatyti PIN kodą. Žemiau rasite naują kodą.</p>
    <table width='100%' style='margin:24px 0'>
      <tr>
        <td style='background:#1a1a1a;border-radius:8px;padding:20px;text-align:center'>
          <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Naujas PIN kodas</div>
          <div style='color:#fff;font-size:40px;font-weight:900;letter-spacing:14px'>{$pin}</div>
        </td>
      </tr>
      <tr><td style='padding:8px'></td></tr>
      <tr>
        <td style='background:#1a1a1a;border-radius:8px;padding:16px'>
          <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Prisijungimo nuoroda</div>
          <a href='{$url}' style='color:#E8720A;font-size:14px;word-break:break-all'>{$url}</a>
        </td>
      </tr>
    </table>
    <p style='color:#555;font-size:12px'>Jei šios užklausos nesiunčiau — ignoruokite šį laišką. Senas PIN kodas nustojo galioti.</p>
  </td></tr>
</table>
</body></html>";

$result = smtpSend($clientEmail, $subject, $html);

if ($result === 'OK') {
    $parts  = explode('@', $clientEmail);
    $masked = substr($parts[0], 0, 2) . str_repeat('*', max(2, strlen($parts[0]) - 2)) . '@' . ($parts[1] ?? '');
    echo json_encode(['ok' => true, 'email' => $masked]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Nepavyko išsiųsti el. laiško. Susisiekite su administratoriumi.']);
}

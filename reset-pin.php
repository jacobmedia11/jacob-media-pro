<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['authed'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

require_once __DIR__ . '/email-builder.php';

$body     = json_decode(file_get_contents('php://input'), true);
$clientId = trim($body['clientId'] ?? '');

if (!$clientId) {
  echo json_encode(['ok' => false, 'error' => 'Trūksta kliento ID.']);
  exit;
}

$CONFIG = __DIR__ . '/clients-config.json';
$cfg    = json_decode(file_get_contents($CONFIG), true);

$found       = false;
$pin         = '';
$clientName  = '';
$clientEmail = '';

foreach ($cfg['clients'] as &$c) {
  if ($c['id'] === $clientId) {
    $pin         = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $c['pin']    = $pin;
    $clientName  = $c['name'];
    $clientEmail = $c['email'] ?? '';
    $found       = true;
    break;
  }
}

if (!$found) {
  echo json_encode(['ok' => false, 'error' => 'Klientas nerastas.']);
  exit;
}

file_put_contents($CONFIG, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Send new PIN by email if client has one
$emailSent = false;
if ($clientEmail) {
  $url     = 'https://klientams.jokubomokymai.lt/hub.php?client=' . urlencode($clientId);
  $subject = 'Jūsų naujas PIN kodas — Jacob Media Pro';
  $html    = "<!DOCTYPE html><html><body style='background:#0a0a0a;margin:0;padding:32px;font-family:sans-serif'>
  <table width='600' style='margin:0 auto;background:#111;border-radius:12px;padding:32px'>
    <tr><td>
      <div style='margin-bottom:24px'>
        <span style='background:#E8720A;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:900;color:#fff;margin-right:8px'>JMP</span>
        <span style='font-size:18px;font-weight:800;color:#fff'>Jacob<span style='color:#E8720A'>Media Pro</span></span>
      </div>
      <p style='color:#aaa;font-size:15px;line-height:1.6'>Sveiki,</p>
      <p style='color:#aaa;font-size:15px;line-height:1.6'>Jūsų PIN kodas buvo atnaujintas.</p>
      <table width='100%' style='margin:24px 0'>
        <tr>
          <td style='background:#1a1a1a;border-radius:8px;padding:20px;text-align:center'>
            <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Naujas PIN kodas</div>
            <div style='color:#fff;font-size:36px;font-weight:900;letter-spacing:12px'>{$pin}</div>
          </td>
        </tr>
        <tr><td style='padding:8px'></td></tr>
        <tr>
          <td style='background:#1a1a1a;border-radius:8px;padding:20px'>
            <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Prisijungimo nuoroda</div>
            <a href='{$url}' style='color:#E8720A;font-size:14px;word-break:break-all'>{$url}</a>
          </td>
        </tr>
      </table>
      <p style='color:#555;font-size:12px'>Laikykite PIN kodą saugiai. Nesidalinkite juo su kitais.</p>
    </td></tr>
  </table>
</body></html>";

  $result    = smtpSend($clientEmail, $subject, $html);
  $emailSent = ($result === 'OK');
}

echo json_encode(['ok' => true, 'pin' => $pin, 'emailSent' => $emailSent, 'email' => $clientEmail]);

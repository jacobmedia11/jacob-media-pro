<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email-builder.php';

requireAdmin();

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? 'add';

// ── Update client ──────────────────────────────────────────────────────────
if ($action === 'update') {
    $id       = trim($body['id']      ?? '');
    $name     = trim($body['name']    ?? '');
    $account  = trim($body['account'] ?? '');
    $email    = trim($body['email']   ?? '');
    $excluded = $body['excluded'] ?? [];

    $fields = [
        'name'     => $name,
        'account'  => $account,
        'email'    => $email,
        'excluded' => json_encode($excluded),
    ];
    if (!empty($body['token'])) {
        $fields['token']          = trim($body['token']);
        $fields['token_error']    = null;
        $fields['token_error_at'] = null;
    }

    dbUpdateClient($id, $fields);
    auditLog('client_update', "id={$id} name={$name}");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Delete client ──────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = trim($body['id'] ?? '');
    $c  = dbGetClient($id);
    dbDeleteClient($id);
    auditLog('client_delete', "id={$id} name=" . ($c['name'] ?? ''));
    echo json_encode(['ok' => true]);
    exit;
}

// ── Add client ─────────────────────────────────────────────────────────────
$name     = trim($body['name']    ?? '');
$account  = trim($body['account'] ?? '');
$token    = trim($body['token']   ?? '');
$email    = trim($body['email']   ?? '');
$excluded = $body['excluded'] ?? [];

if (!$name || !$account) {
    echo json_encode(['ok' => false, 'error' => 'Vardas ir paskyra būtini.']);
    exit;
}

function slugify(string $text): string {
    $map  = ['ą'=>'a','č'=>'c','ę'=>'e','ė'=>'e','į'=>'i','š'=>'s','ų'=>'u','ū'=>'u','ž'=>'z',
             'Ą'=>'a','Č'=>'c','Ę'=>'e','Ė'=>'e','Į'=>'i','Š'=>'s','Ų'=>'u','Ū'=>'u','Ž'=>'z'];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$id       = slugify($name);
$existing = array_column(dbGetClients(), 'id');
$base     = $id;
$i        = 2;
while (in_array($id, $existing)) { $id = $base . '-' . $i++; }

$pin = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

dbAddClient([
    'id'       => $id,
    'name'     => $name,
    'account'  => $account,
    'token'    => $token,
    'email'    => $email,
    'pin'      => $pin,
    'excluded' => $excluded,
]);

auditLog('client_add', "id={$id} name={$name}");

// Send PIN email to client
$emailSent = false;
if ($email) {
    $url     = 'https://klientams.jokubomokymai.lt/hub.php?client=' . urlencode($id);
    $subject = 'Jūsų prieiga prie ataskaitos sistemos';
    $html    = "<!DOCTYPE html><html><body style='background:#0a0a0a;margin:0;padding:32px;font-family:sans-serif'>
  <table width='600' style='margin:0 auto;background:#111;border-radius:12px;padding:32px'>
    <tr><td>
      <div style='margin-bottom:24px'>
        <span style='background:#E8720A;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:900;color:#fff;margin-right:8px'>JMP</span>
        <span style='font-size:18px;font-weight:800;color:#fff'>Jacob<span style='color:#E8720A'>Media Pro</span></span>
      </div>
      <p style='color:#aaa;font-size:15px;line-height:1.6'>Sveiki,</p>
      <p style='color:#aaa;font-size:15px;line-height:1.6'>Jūsų Meta Ads ataskaitos prieiga paruošta.</p>
      <table width='100%' style='margin:24px 0'>
        <tr>
          <td style='background:#1a1a1a;border-radius:8px;padding:20px'>
            <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>Nuoroda</div>
            <a href='{$url}' style='color:#E8720A;font-size:14px;word-break:break-all'>{$url}</a>
          </td>
        </tr>
        <tr><td style='padding:8px'></td></tr>
        <tr>
          <td style='background:#1a1a1a;border-radius:8px;padding:20px;text-align:center'>
            <div style='color:#555;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>PIN kodas</div>
            <div style='color:#fff;font-size:36px;font-weight:900;letter-spacing:12px'>{$pin}</div>
          </td>
        </tr>
      </table>
      <p style='color:#555;font-size:12px'>Laikykite PIN kodą saugiai. Nesidalinkite juo su kitais.</p>
    </td></tr>
  </table>
</body></html>";

    $result    = smtpSend($email, $subject, $html);
    $emailSent = ($result === 'OK');
}

echo json_encode(['ok' => true, 'id' => $id, 'pin' => $pin, 'emailSent' => $emailSent]);

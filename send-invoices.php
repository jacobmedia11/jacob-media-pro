<?php
/**
 * Monthly invoice sender — runs via Hostinger Cron on the 1st of each month.
 * Cron expression: 0 8 1 * *
 *
 * It reads clients.json, generates each invoice as HTML, attaches it inline,
 * and sends via SMTP to clients that have auto_send=true and an email set.
 *
 * No Playwright/Python needed on the server — invoice is sent as HTML email.
 * (PDF generation still works locally via generate_invoice.py --send)
 */

$CONFIG_FILE = __DIR__ . '/clients.json';
$cfg         = json_decode(file_get_contents($CONFIG_FILE), true);

$smtp   = $cfg['smtp'];
$issuer = $cfg['issuer'];

$today    = date('Y-m-d');
$due      = date('Y-m-d', strtotime('+14 days'));
$period   = date('Y-m');
$log      = date('Y-m-d H:i:s') . " [invoices]\n";

foreach ($cfg['clients'] as $client) {
    if (empty($client['auto_send']) || empty($client['email'])) {
        $log .= "  SKIP {$client['name']} (auto_send off or no email)\n";
        continue;
    }

    $invoice_number = $client['invoice_number_prefix'] . '-' . date('Ym');
    $services       = $client['services'] ?? [];
    $total          = 0.0;

    // Build line item rows
    $rows = '';
    foreach ($services as $svc) {
        $qty        = $svc['qty'] ?? 1;
        $price      = (float)($svc['price'] ?? 0);
        $line_total = $qty * $price;
        $total     += $line_total;
        $name       = htmlspecialchars($svc['name']);
        $desc       = htmlspecialchars($svc['description'] ?? '');
        $rows .= "
      <tr>
        <td style='padding:16px 14px;border-bottom:1px solid #2a3045;vertical-align:top'>
          <div style='font-weight:600;color:#e8eaf0'>{$name}</div>
          <div style='color:#7b8199;font-size:12px;margin-top:3px'>{$desc}</div>
        </td>
        <td style='padding:16px 14px;border-bottom:1px solid #2a3045;color:#e8eaf0'>{$period}</td>
        <td style='padding:16px 14px;border-bottom:1px solid #2a3045;color:#e8eaf0;text-align:right'>{$qty}</td>
        <td style='padding:16px 14px;border-bottom:1px solid #2a3045;color:#e8eaf0;text-align:right'>€" . number_format($price, 2) . "</td>
        <td style='padding:16px 14px;border-bottom:1px solid #2a3045;color:#e8eaf0;text-align:right'>€" . number_format($line_total, 2) . "</td>
      </tr>";
    }

    $total_fmt          = number_format($total, 2);
    $client_name        = htmlspecialchars($client['name']);
    $client_contact     = htmlspecialchars($client['contact'] ?? '');
    $client_email_disp  = htmlspecialchars($client['email']);
    $client_address     = htmlspecialchars($client['address'] ?? '');
    $issuer_name        = htmlspecialchars($issuer['name']);
    $issuer_email       = htmlspecialchars($issuer['email']);
    $issuer_country     = htmlspecialchars($issuer['country']);
    $issuer_iban        = htmlspecialchars($issuer['iban']);
    $issuer_bank        = htmlspecialchars($issuer['bank']);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="lt"><head><meta charset="UTF-8">
<title>Sąskaita-faktūra — {$invoice_number}</title></head>
<body style="background:#0d0f14;color:#e8eaf0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.6;padding:48px 56px;max-width:800px;margin:0 auto">

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:32px;border-bottom:1px solid #2a3045;margin-bottom:40px">
  <div>
    <div style="display:inline-block;background:linear-gradient(135deg,#1877f2,#42a5f5);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:0.5px;text-transform:uppercase;margin-bottom:6px">Meta Ads</div>
    <div style="font-size:22px;font-weight:700">{$issuer_name}</div>
    <div style="color:#7b8199;font-size:12px;margin-top:4px">Skaitmeninė rinkodara · Digital Marketing</div>
  </div>
  <div style="text-align:right">
    <div style="font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:4px">Sąskaita-faktūra</div>
    <div style="font-size:20px;font-weight:700;color:#4f8ef7">{$invoice_number}</div>
    <div style="color:#7b8199;font-size:13px;margin-top:6px">Išrašyta: {$today}</div>
    <div style="color:#7b8199;font-size:13px">Apmokėti iki: {$due}</div>
  </div>
</div>

<!-- Parties -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:40px">
  <div style="background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px">
    <div style="font-size:10px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:12px">Paslaugų teikėjas</div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">{$issuer_name}</div>
    <div style="color:#7b8199;font-size:13px;line-height:1.8">El. paštas: {$issuer_email}<br>{$issuer_country}</div>
  </div>
  <div style="background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px">
    <div style="font-size:10px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:12px">Klientas</div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">{$client_name}</div>
    <div style="color:#7b8199;font-size:13px;line-height:1.8">Kontaktinis asmuo: {$client_contact}<br>El. paštas: {$client_email_disp}<br>{$client_address}</div>
  </div>
</div>

<!-- Line items -->
<div style="background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:28px;margin-bottom:24px">
  <div style="font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:20px">Paslaugos</div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr>
        <th style="text-align:left;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;letter-spacing:0.6px;text-transform:uppercase">Aprašymas</th>
        <th style="text-align:left;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;letter-spacing:0.6px;text-transform:uppercase">Laikotarpis</th>
        <th style="text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;letter-spacing:0.6px;text-transform:uppercase">Kiekis</th>
        <th style="text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;letter-spacing:0.6px;text-transform:uppercase">Kaina</th>
        <th style="text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;letter-spacing:0.6px;text-transform:uppercase">Suma</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>

<!-- Totals -->
<div style="display:flex;justify-content:flex-end;margin-bottom:40px">
  <div style="background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px 32px;min-width:320px">
    <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#7b8199"><span>Tarpinė suma</span><span style="color:#e8eaf0;font-weight:600">€{$total_fmt}</span></div>
    <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#7b8199"><span>PVM (0%)</span><span style="color:#e8eaf0;font-weight:600">€0.00</span></div>
    <div style="display:flex;justify-content:space-between;padding:16px 0 8px;border-top:1px solid #2a3045;margin-top:8px;font-size:18px;font-weight:700;color:#34d399"><span>Iš viso mokėti</span><span>€{$total_fmt}</span></div>
  </div>
</div>

<!-- Payment -->
<div style="background:#161a24;border:1px solid #2a3045;border-left:3px solid #4f8ef7;border-radius:12px;padding:24px 28px;margin-bottom:40px">
  <div style="font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:16px">Mokėjimo informacija</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 32px">
    <div><div style="font-size:11px;color:#7b8199;margin-bottom:2px">Gavėjas</div><div style="font-size:14px;font-weight:600">{$issuer_name}</div></div>
    <div><div style="font-size:11px;color:#7b8199;margin-bottom:2px">IBAN</div><div style="font-size:14px;font-weight:600">{$issuer_iban}</div></div>
    <div><div style="font-size:11px;color:#7b8199;margin-bottom:2px">Bankas</div><div style="font-size:14px;font-weight:600">{$issuer_bank}</div></div>
    <div><div style="font-size:11px;color:#7b8199;margin-bottom:2px">Mokėjimo paskirtis</div><div style="font-size:14px;font-weight:600">{$invoice_number}</div></div>
  </div>
</div>

<div style="border-top:1px solid #2a3045;padding-top:24px;display:flex;justify-content:space-between;color:#7b8199;font-size:12px">
  <span>{$issuer_name} · Meta Ads paslaugos</span>
  <span>Sugeneruota {$today}</span>
</div>

</body></html>
HTML;

    $subject = "Sąskaita-faktūra {$invoice_number}";
    $r = smtpSend($smtp, $client['email'], $subject, $html);
    $log .= "  {$client['name']} → {$client['email']}: $r\n";
}

file_put_contents(__DIR__ . '/invoice-log.txt', $log . "\n", FILE_APPEND);
echo $log;

// ── SMTP sender (SSL) ─────────────────────────────────────────────────────────
function smtpSend($smtp, $to, $subject, $html) {
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $sock = stream_socket_client("ssl://{$smtp['host']}:{$smtp['port']}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) return "Connect error: $errstr ($errno)";

    function r($s) { return fgets($s, 512); }
    function w($s, $c) { fwrite($s, $c . "\r\n"); }

    r($sock);
    w($sock, "EHLO " . gethostname()); while (($l = r($sock)) && substr($l, 3, 1) == '-');
    w($sock, "AUTH LOGIN"); r($sock);
    w($sock, base64_encode($smtp['user'])); r($sock);
    w($sock, base64_encode($smtp['pass'])); $auth = r($sock);
    if (strpos($auth, '235') === false) { fclose($sock); return "Auth failed: $auth"; }

    $from = $smtp['user'];
    $name = $smtp['from_name'];

    $msg  = "Date: " . date('r') . "\r\n";
    $msg .= "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html));

    w($sock, "MAIL FROM:<{$from}>"); r($sock);
    w($sock, "RCPT TO:<{$to}>"); r($sock);
    w($sock, "DATA"); r($sock);
    w($sock, $msg . "\r\n."); $res = r($sock);
    w($sock, "QUIT");
    fclose($sock);

    return strpos($res, '250') !== false ? 'OK' : "Send error: $res";
}

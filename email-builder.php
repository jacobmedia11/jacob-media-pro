<?php
require_once __DIR__ . '/smtp-config.php';

function fetchMetaData($account, $token, $preset) {
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
    if (in_array($a['action_type'], ['lead', 'onsite_conversion.lead_grouped']))
      return (int)$a['value'];
  }
  return 0;
}

function periodLabel($preset) {
  $map = [
    'yesterday'  => 'Vakar',
    'last_7d'    => 'Paskutinės 7 dienos',
    'last_30d'   => 'Paskutinės 30 dienų',
    'last_month' => 'Praėjęs mėnuo',
    'this_month' => 'Šis mėnuo',
  ];
  return $map[$preset] ?? $preset;
}

function buildBlock($name, $account, $token, $preset = 'last_7d') {
  $data      = fetchMetaData($account, $token, $preset);
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

    $rows .= "
    <tr>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#eee;font-size:13px'>" . htmlspecialchars($c['campaign_name']) . "</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#E8720A;font-weight:700;text-align:right'>€" . number_format($spend, 2) . "</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#aaa;text-align:right'>" . number_format($impr) . "</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#aaa;text-align:right'>" . number_format($clicks) . "</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#34d399;text-align:right'>" . number_format($ctr, 2) . "%</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#aaa;text-align:right'>€" . number_format($cpc, 3) . "</td>
      <td style='padding:10px 14px;border-bottom:1px solid #222;color:#a78bfa;text-align:right;font-weight:700'>" . ($leads ?: '—') . "</td>
    </tr>";
  }

  if (!$rows) {
    $rows = "<tr><td colspan='7' style='padding:20px;text-align:center;color:#555'>Duomenų nerasta šiam laikotarpiui.</td></tr>";
  }

  $avgCtr = $totalImpr > 0 ? ($totalClicks / $totalImpr * 100) : 0;
  $cpl    = $totalLeads > 0 ? '€' . number_format($totalSpend / $totalLeads, 2) : '—';

  return "
  <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:32px'>
    <tr><td>
      <h2 style='color:#fff;font-size:17px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #E8720A'>" . htmlspecialchars($name) . "</h2>

      <!-- Stats row -->
      <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:16px'>
        <tr>
          <td width='20%' style='padding-right:8px'>
            <div style='background:#1a1a1a;border-radius:8px;padding:14px 16px'>
              <div style='color:#666;font-size:10px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px'>Išlaidos</div>
              <div style='color:#E8720A;font-size:22px;font-weight:700'>€" . number_format($totalSpend, 2) . "</div>
            </div>
          </td>
          <td width='20%' style='padding-right:8px'>
            <div style='background:#1a1a1a;border-radius:8px;padding:14px 16px'>
              <div style='color:#666;font-size:10px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px'>Paspaudimai</div>
              <div style='color:#eee;font-size:22px;font-weight:700'>" . number_format($totalClicks) . "</div>
            </div>
          </td>
          <td width='20%' style='padding-right:8px'>
            <div style='background:#1a1a1a;border-radius:8px;padding:14px 16px'>
              <div style='color:#666;font-size:10px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px'>CTR</div>
              <div style='color:#34d399;font-size:22px;font-weight:700'>" . number_format($avgCtr, 2) . "%</div>
            </div>
          </td>
          <td width='20%' style='padding-right:8px'>
            <div style='background:#1a1a1a;border-radius:8px;padding:14px 16px'>
              <div style='color:#666;font-size:10px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px'>Potenc. klientai</div>
              <div style='color:#a78bfa;font-size:22px;font-weight:700'>" . ($totalLeads ?: '—') . "</div>
            </div>
          </td>
          <td width='20%'>
            <div style='background:#1a1a1a;border-radius:8px;padding:14px 16px'>
              <div style='color:#666;font-size:10px;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px'>CPL</div>
              <div style='color:#eee;font-size:22px;font-weight:700'>{$cpl}</div>
            </div>
          </td>
        </tr>
      </table>

      <!-- Campaigns table -->
      <table width='100%' cellpadding='0' cellspacing='0' style='background:#111;border-radius:8px;overflow:hidden'>
        <thead>
          <tr style='background:#1a1a1a'>
            <th style='padding:10px 14px;text-align:left;color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.6px'>Kampanija</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>Išlaidos</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>Parodymai</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>Paspaudimai</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>CTR</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>CPC</th>
            <th style='padding:10px 14px;text-align:right;color:#555;font-size:10px;text-transform:uppercase'>Klientai</th>
          </tr>
        </thead>
        <tbody>{$rows}</tbody>
      </table>
    </td></tr>
  </table>";
}

function buildEmail($title, $block, $preset, $subject, $intro) {
  $period = periodLabel($preset);
  $date   = date('Y-m-d');
  $introHtml = $intro ? "<p style='color:#aaa;font-size:14px;line-height:1.6;margin:0 0 24px'>" . nl2br(htmlspecialchars($intro)) . "</p>" : '';

  return "<!DOCTYPE html>
<html lang='lt'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#0a0a0a'>
<table width='100%' cellpadding='0' cellspacing='0'>
  <tr><td align='center' style='padding:32px 16px'>
    <table width='700' cellpadding='0' cellspacing='0' style='max-width:700px;width:100%'>

      <!-- Header -->
      <tr><td style='padding-bottom:24px;border-bottom:1px solid #1e1e1e;margin-bottom:24px'>
        <table width='100%'><tr>
          <td>
            <span style='display:inline-block;background:#E8720A;border-radius:7px;padding:4px 10px;font-size:12px;font-weight:900;color:#fff;margin-right:10px'>JMP</span>
            <span style='font-size:18px;font-weight:800;color:#fff'>Jacob<span style='color:#E8720A'>Media Pro</span></span>
          </td>
          <td align='right'>
            <span style='color:#555;font-size:12px'>{$period} · {$date}</span>
          </td>
        </tr></table>
      </td></tr>

      <!-- Intro -->
      <tr><td style='padding-top:24px'>{$introHtml}{$block}</td></tr>

      <!-- Footer -->
      <tr><td style='padding-top:24px;border-top:1px solid #1e1e1e;text-align:center'>
        <p style='color:#333;font-size:11px;margin:0'>Ataskaita sugeneruota automatiškai · klientams.jokubomokymai.lt</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>";
}

function generatePdf($html, $filename) {
  $autoload = __DIR__ . '/vendor/autoload.php';
  if (!file_exists($autoload)) return null;
  require_once $autoload;
  $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  return $dompdf->output();
}

function smtpSend($to, $subject, $html, $pdfData = null, $pdfFilename = 'ataskaita.pdf') {
  $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
  $sock = stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
  if (!$sock) return "Connect error: $errstr";

  function _r($s) { return fgets($s, 512); }
  function _w($s, $c) { fwrite($s, $c . "\r\n"); }

  _r($sock);
  _w($sock, 'EHLO ' . gethostname()); while (($l = _r($sock)) && substr($l, 3, 1) === '-');
  _w($sock, 'AUTH LOGIN');              _r($sock);
  _w($sock, base64_encode(SMTP_USER));  _r($sock);
  _w($sock, base64_encode(SMTP_PASS));  $auth = _r($sock);
  if (strpos($auth, '235') === false) { fclose($sock); return "Auth failed: $auth"; }

  $boundary = '----=_Boundary_' . md5(uniqid());

  $msg  = "Date: "    . date('r') . "\r\n";
  $msg .= "From: =?UTF-8?B?" . base64_encode(FROM_NAME) . "?= <" . SMTP_USER . ">\r\n";
  $msg .= "To: $to\r\n";
  $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
  $msg .= "MIME-Version: 1.0\r\n";

  if ($pdfData) {
    $msg .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html)) . "\r\n";
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
    $msg .= chunk_split(base64_encode($pdfData)) . "\r\n";
    $msg .= "--{$boundary}--";
  } else {
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($html));
  }

  _w($sock, "MAIL FROM:<" . SMTP_USER . ">"); _r($sock);
  _w($sock, "RCPT TO:<$to>");                  _r($sock);
  _w($sock, "DATA");                            _r($sock);
  _w($sock, $msg . "\r\n.");                    $res = _r($sock);
  _w($sock, "QUIT");
  fclose($sock);

  return strpos($res, '250') !== false ? 'OK' : "Send error: $res";
}

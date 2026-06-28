<?php
session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';

requireAdmin();

// Verify CSRF for all state-changing actions
$_action = $_GET['action'] ?? 'list';
if (!in_array($_action, ['list', 'client', 'preview'])) {
    verifyCsrf();
}

$LOG_FILE = __DIR__ . '/invoices-log.json';

$allClients = dbGetClients();
$clients    = [];
foreach ($allClients as $c) {
    $clients[$c['id']] = $c;
}

$action = $_GET['action'] ?? 'list';
$input  = file_get_contents('php://input');
$data   = $input ? json_decode($input, true) : [];

if ($action === 'preview') {
  header('Content-Type: text/html; charset=UTF-8');
  echo buildInvoiceHtml($data);
  exit;
}

header('Content-Type: application/json; charset=UTF-8');

switch ($action) {
  case 'list':
    echo json_encode(loadLog($LOG_FILE));
    break;

  case 'client':
    $id     = $_GET['id'] ?? '';
    $client = $clients[$id] ?? null;
    if (!$client) { echo json_encode(['error' => 'Not found']); exit; }
    echo json_encode([
      'services' => $client['services'] ?? [],
      'prefix'   => 'INV-' . strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $client['name']), 0, 6)),
      'email'    => $client['email']    ?? '',
      'contact'  => $client['contact']  ?? '',
      'address'  => $client['address']  ?? '',
    ]);
    break;

  case 'save':
    $log   = loadLog($LOG_FILE);
    $entry = makeEntry($data, 'draft');
    $log   = array_values(array_filter($log, fn($i) => $i['id'] !== $entry['id']));
    $log[] = $entry;
    saveLog($LOG_FILE, $log);
    echo json_encode(['ok' => true, 'entry' => $entry]);
    break;

  case 'send':
    if (empty($data['client_email'])) {
      echo json_encode(['ok' => false, 'error' => 'Klientas neturi el. pašto adreso.']);
      exit;
    }
    require_once __DIR__ . '/email-builder.php';
    $html   = buildInvoiceHtml($data);
    $invNum = $data['invoice_number'];
    $r      = smtpSend($data['client_email'], "Sąskaita-faktūra {$invNum}", $html);
    $ok     = ($r === 'OK');
    $entry  = makeEntry($data, $ok ? 'sent' : 'draft');
    if ($ok) $entry['sent_at'] = date('Y-m-d H:i:s');
    $log    = loadLog($LOG_FILE);
    $log    = array_values(array_filter($log, fn($i) => $i['id'] !== $entry['id']));
    $log[]  = $entry;
    saveLog($LOG_FILE, $log);
    echo json_encode(['ok' => $ok, 'result' => $r]);
    break;

  case 'delete':
    $id  = $data['id'] ?? '';
    $log = array_values(array_filter(loadLog($LOG_FILE), fn($i) => $i['id'] !== $id));
    saveLog($LOG_FILE, $log);
    echo json_encode(['ok' => true]);
    break;

  default:
    echo json_encode(['error' => 'Unknown action']);
}

function loadLog($file) {
  if (!file_exists($file)) return [];
  return json_decode(file_get_contents($file), true) ?: [];
}

function saveLog($file, $log) {
  usort($log, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
  file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function makeEntry($data, $status) {
  return [
    'id'             => $data['invoice_number'],
    'client_id'      => $data['client_id']      ?? '',
    'client_name'    => $data['client_name']     ?? '',
    'client_email'   => $data['client_email']    ?? '',
    'client_contact' => $data['client_contact']  ?? '',
    'client_address' => $data['client_address']  ?? '',
    'period'         => $data['period']          ?? '',
    'total'          => $data['total']           ?? 0,
    'services'       => $data['services']        ?? [],
    'status'         => $status,
    'created_at'     => date('Y-m-d H:i:s'),
    'sent_at'        => null,
    'invoice_number' => $data['invoice_number'],
  ];
}

function buildInvoiceHtml($data) {
  $today  = date('Y-m-d');
  $due    = date('Y-m-d', strtotime('+14 days'));
  $invNum = htmlspecialchars($data['invoice_number'] ?? '');
  $period = htmlspecialchars($data['period']         ?? '');
  $cn     = htmlspecialchars($data['client_name']    ?? '');
  $cc     = htmlspecialchars($data['client_contact'] ?? '');
  $ce     = htmlspecialchars($data['client_email']   ?? '');
  $ca     = htmlspecialchars($data['client_address'] ?? '');

  $services = $data['services'] ?? [];
  $total    = 0.0;
  $rows     = '';
  foreach ($services as $svc) {
    $qty   = (int)($svc['qty']     ?? 1);
    $price = (float)($svc['price'] ?? 0);
    $line  = $qty * $price;
    $total += $line;
    $name  = htmlspecialchars($svc['name']        ?? '');
    $desc  = htmlspecialchars($svc['description'] ?? '');
    $rows .= "<tr>
      <td style='padding:14px;border-bottom:1px solid #2a3045;vertical-align:top'>
        <div style='font-weight:600;color:#e8eaf0'>{$name}</div>
        <div style='color:#7b8199;font-size:12px;margin-top:3px'>{$desc}</div>
      </td>
      <td style='padding:14px;border-bottom:1px solid #2a3045;color:#e8eaf0'>{$period}</td>
      <td style='padding:14px;border-bottom:1px solid #2a3045;color:#e8eaf0;text-align:right'>{$qty}</td>
      <td style='padding:14px;border-bottom:1px solid #2a3045;color:#e8eaf0;text-align:right'>€" . number_format($price,2) . "</td>
      <td style='padding:14px;border-bottom:1px solid #2a3045;color:#34d399;font-weight:600;text-align:right'>€" . number_format($line,2) . "</td>
    </tr>";
  }
  $tf = number_format($total, 2);

  return "<!DOCTYPE html><html lang='lt'><head><meta charset='UTF-8'>
<title>Sąskaita-faktūra — {$invNum}</title>
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}body{background:#0d0f14;color:#e8eaf0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.6;padding:48px 56px}@media print{body{padding:20px 30px}}</style>
</head><body>
<div style='display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:32px;border-bottom:1px solid #2a3045;margin-bottom:40px'>
  <div>
    <div style='display:inline-block;background:#E8720A;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:0.5px;margin-bottom:6px'>JMP</div>
    <div style='font-size:22px;font-weight:700'>Jacob Media Pro</div>
    <div style='color:#7b8199;font-size:12px;margin-top:4px'>Skaitmeninė rinkodara · Digital Marketing</div>
  </div>
  <div style='text-align:right'>
    <div style='font-size:11px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:4px'>Sąskaita-faktūra</div>
    <div style='font-size:20px;font-weight:700;color:#E8720A'>{$invNum}</div>
    <div style='color:#7b8199;font-size:13px;margin-top:6px'>Išrašyta: {$today}</div>
    <div style='color:#7b8199;font-size:13px'>Apmokėti iki: {$due}</div>
  </div>
</div>
<div style='display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:40px'>
  <div style='background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px'>
    <div style='font-size:10px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:12px'>Paslaugų teikėjas</div>
    <div style='font-size:16px;font-weight:700;margin-bottom:6px'>Jacob Media Pro</div>
  </div>
  <div style='background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px'>
    <div style='font-size:10px;font-weight:600;letter-spacing:0.8px;text-transform:uppercase;color:#7b8199;margin-bottom:12px'>Klientas</div>
    <div style='font-size:16px;font-weight:700;margin-bottom:6px'>{$cn}</div>
    <div style='color:#7b8199;font-size:13px;line-height:1.8'>Kontaktinis asmuo: {$cc}<br>El. paštas: {$ce}<br>{$ca}</div>
  </div>
</div>
<div style='background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:28px;margin-bottom:24px'>
  <table style='width:100%;border-collapse:collapse;font-size:13px'>
    <thead><tr>
      <th style='text-align:left;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;text-transform:uppercase'>Aprašymas</th>
      <th style='text-align:left;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;text-transform:uppercase'>Laikotarpis</th>
      <th style='text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;text-transform:uppercase'>Kiekis</th>
      <th style='text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;text-transform:uppercase'>Kaina</th>
      <th style='text-align:right;padding:10px 14px;border-bottom:1px solid #2a3045;color:#7b8199;font-size:11px;font-weight:600;text-transform:uppercase'>Suma</th>
    </tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
<div style='display:flex;justify-content:flex-end;margin-bottom:40px'>
  <div style='background:#161a24;border:1px solid #2a3045;border-radius:12px;padding:24px 32px;min-width:280px'>
    <div style='display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#7b8199'><span>Tarpinė suma</span><span style='color:#e8eaf0;font-weight:600'>€{$tf}</span></div>
    <div style='display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#7b8199'><span>PVM (0%)</span><span style='color:#e8eaf0;font-weight:600'>€0.00</span></div>
    <div style='display:flex;justify-content:space-between;padding:16px 0 8px;border-top:1px solid #2a3045;margin-top:8px;font-size:18px;font-weight:700;color:#34d399'><span>Iš viso mokėti</span><span>€{$tf}</span></div>
  </div>
</div>
<div style='border-top:1px solid #2a3045;padding-top:24px;display:flex;justify-content:space-between;color:#7b8199;font-size:12px'>
  <span>Jacob Media Pro · Meta Ads paslaugos</span>
  <span>Sugeneruota {$today}</span>
</div>
</body></html>";
}

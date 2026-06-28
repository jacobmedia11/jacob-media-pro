<?php
session_start();
header('Content-Type: application/json');

$clientId = preg_replace('/[^a-z0-9\-]/', '', $_GET['client'] ?? '');
$pinOk    = $clientId && !empty($_SESSION['pin_ok_' . $clientId]);

if (empty($_SESSION['authed']) && !$pinOk) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$cfg     = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$token   = '';
$account = '';

foreach ($cfg['clients'] as $c) {
  if ($c['id'] === $clientId) {
    $token   = $c['token']   ?? '';
    $account = $c['account'] ?? '';
    break;
  }
}

if (!$token || !$account) {
  echo json_encode(['error' => 'Paskyra nerasta.']);
  exit;
}

function metaGet($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  $r = curl_exec($ch);
  curl_close($ch);
  return json_decode($r, true);
}

// ── 1. Try billing_history (try v17.0 first — wider account support) ────────
$data = metaGet(
  "https://graph.facebook.com/v17.0/act_{$account}/billing_history"
  . "?fields=id,type,currency,amount,time,payment_method,status,vat_invoice_id"
  . "&limit=24&access_token={$token}"
);

// Fall back to v19.0 if v17 also fails
if (isset($data['error'])) {
  $data = metaGet(
    "https://graph.facebook.com/v19.0/act_{$account}/billing_history"
    . "?fields=id,type,currency,amount,time,payment_method,status,vat_invoice_id"
    . "&limit=24&access_token={$token}"
  );
}

if (!isset($data['error'])) {
  // billing_history worked — build invoice list
  $invoices = [];
  foreach ($data['data'] ?? [] as $item) {
    $inv = [
      'id'             => $item['id'],
      'type'           => $item['type']     ?? '',
      'amount'         => $item['amount']   ?? 0,
      'currency'       => $item['currency'] ?? 'EUR',
      'time'           => $item['time']     ?? '',
      'status'         => $item['status']   ?? '',
      'source'         => 'invoice',
      'download_uri'   => null,
      'billing_period' => null,
    ];
    if (!empty($item['vat_invoice_id'])) {
      $inv['vat_invoice_id'] = $item['vat_invoice_id'];
      $d = metaGet(
        "https://graph.facebook.com/v19.0/{$item['vat_invoice_id']}"
        . "?fields=invoice_id,status,amount,currency,billing_period,download_uri"
        . "&access_token={$token}"
      );
      if (!empty($d['download_uri']))   $inv['download_uri']   = $d['download_uri'];
      if (!empty($d['billing_period'])) $inv['billing_period'] = $d['billing_period'];
    }
    $invoices[] = $inv;
  }
  echo json_encode(['invoices' => $invoices, 'source' => 'invoice']);
  exit;
}

// ── 2. Fallback: transactions (credit card accounts) ──────────────────────
$errCode = $data['error']['code'] ?? 0;
$errMsg  = $data['error']['message'] ?? '';
$isBillingUnavailable = strpos($errMsg, 'Unknown path') !== false
                     || strpos($errMsg, 'does not exist') !== false
                     || $errCode === 100 || $errCode === 803;

if ($isBillingUnavailable) {
  $tx = metaGet(
    "https://graph.facebook.com/v19.0/act_{$account}/transactions"
    . "?fields=id,time_start,time_stop,amount,currency,status,payment_option"
    . "&limit=24&access_token={$token}"
  );

  if (!isset($tx['error'])) {
    $items = $tx['data'] ?? [];
    if (!empty($items)) {
      $invoices = [];
      foreach ($items as $item) {
        $invoices[] = [
          'id'             => $item['id'],
          'type'           => 'Mokestis',
          'amount'         => $item['amount']     ?? 0,
          'currency'       => $item['currency']   ?? 'EUR',
          'time'           => $item['time_start'] ?? '',
          'time_stop'      => $item['time_stop']  ?? '',
          'status'         => $item['status']     ?? '',
          'source'         => 'transaction',
          'download_uri'   => null,
          'billing_period' => null,
        ];
      }
      echo json_encode(['invoices' => $invoices, 'source' => 'transaction']);
      exit;
    }
    // transactions returned empty — likely no billing data yet
    echo json_encode(['unavailable' => true, 'account' => $account,
      'debug' => 'transactions endpoint OK but returned 0 records']);
    exit;
  }

  // transactions also failed — try business-level invoices
  $txErr  = $tx['error']['message'] ?? '';

  // Get the business ID from the ad account
  $acctInfo = metaGet(
    "https://graph.facebook.com/v19.0/act_{$account}"
    . "?fields=business"
    . "&access_token={$token}"
  );
  $businessId = $acctInfo['business']['id'] ?? '';

  if ($businessId) {
    $bizInv = metaGet(
      "https://graph.facebook.com/v19.0/{$businessId}/invoices"
      . "?fields=id,invoice_date,due_date,status,payment_status,amount,currency,billing_period,download_uri"
      . "&limit=24&access_token={$token}"
    );

    if (!isset($bizInv['error']) && !empty($bizInv['data'])) {
      $invoices = [];
      foreach ($bizInv['data'] as $item) {
        $invoices[] = [
          'id'             => $item['id'],
          'vat_invoice_id' => $item['id'],
          'type'           => 'PVM sąskaita',
          'amount'         => $item['amount']        ?? 0,
          'currency'       => $item['currency']      ?? 'EUR',
          'time'           => $item['invoice_date']  ?? '',
          'status'         => $item['payment_status'] ?? $item['status'] ?? '',
          'source'         => 'business_invoice',
          'download_uri'   => $item['download_uri']  ?? null,
          'billing_period' => $item['billing_period'] ?? null,
        ];
      }
      echo json_encode(['invoices' => $invoices, 'source' => 'business_invoice']);
      exit;
    }
    $bizErr = $bizInv['error']['message'] ?? 'no data';
    echo json_encode(['unavailable' => true, 'account' => $account,
      'debug' => "billing_history: {$errMsg} | transactions: {$txErr} | business invoices ({$businessId}): {$bizErr}"]);
    exit;
  }

  echo json_encode(['unavailable' => true, 'account' => $account,
    'debug' => "billing_history: {$errMsg} | transactions: {$txErr} | no business ID found"]);
}

// Other API error (e.g. expired token)
echo json_encode(['error' => $errMsg ?: 'Meta API klaida', 'code' => $errCode]);

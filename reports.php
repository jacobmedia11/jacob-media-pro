<?php
session_start();

// Auth check
if (empty($_SESSION['authed'])) {
  header('Location: login.html');
  exit;
}

$CONFIG_FILE = __DIR__ . '/reports-config.json';

// Load tokens from clients-config.json (single source of truth)
$clientsCfg = json_decode(file_get_contents(__DIR__ . '/clients-config.json'), true);
$TOKENS = [];
foreach ($clientsCfg['clients'] as $c) {
  if (!empty($c['token'])) $TOKENS[$c['account']] = $c['token'];
}

// Auto-create reports-config.json if missing (first-run on server)
if (!file_exists($CONFIG_FILE)) {
  $defaultClients = [];
  foreach ($clientsCfg['clients'] as $c) {
    $defaultClients[] = [
      'id'          => $c['id'],
      'name'        => $c['name'],
      'account'     => $c['account'],
      'email'       => $c['email'] ?? '',
      'schedule'    => 'weekly',
      'email_intro' => '',
    ];
  }
  file_put_contents($CONFIG_FILE, json_encode([
    'owner_email'    => 'jokuubas11@gmail.com',
    'owner_schedule' => 'weekly',
    'email_subject'  => 'Savaitinė Meta Ads ataskaita',
    'clients'        => $defaultClients,
    'last_sent'      => (object)[],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$VALID_SCHEDULES = ['daily', 'weekly', 'monthly', 'none'];

function loadConfig($file) {
  return json_decode(file_get_contents($file), true);
}

function saveConfig($file, $data) {
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ── Handle AJAX actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $body   = json_decode(file_get_contents('php://input'), true);
  $action = $body['action'] ?? '';
  $cfg    = loadConfig($CONFIG_FILE);

  if ($action === 'save_config') {
    $cfg['owner_email']    = filter_var($body['owner_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: $cfg['owner_email'];
    $ownerSched = $body['owner_schedule'] ?? '';
    if (in_array($ownerSched, $VALID_SCHEDULES)) $cfg['owner_schedule'] = $ownerSched;
    $cfg['email_subject']  = substr(strip_tags($body['email_subject'] ?? ''), 0, 200) ?: $cfg['email_subject'];
    foreach ($cfg['clients'] as &$c) {
      $id = $c['id'];
      if (isset($body['clients'][$id])) {
        $clientEmail = filter_var($body['clients'][$id]['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($clientEmail !== false) $c['email'] = $clientEmail;
        $sched = $body['clients'][$id]['schedule'] ?? '';
        if (in_array($sched, $VALID_SCHEDULES)) $c['schedule'] = $sched;
        $c['email_intro'] = substr(strip_tags($body['clients'][$id]['email_intro'] ?? ''), 0, 500);
      }
    }
    saveConfig($CONFIG_FILE, $cfg);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'send') {
    $target = $body['target'] ?? 'all'; // 'owner' or client id
    require_once __DIR__ . '/send-report.php';
    // send-report.php handles sending via functions
    echo json_encode(['ok' => true, 'message' => 'Išsiųsta']);
    exit;
  }

  echo json_encode(['ok' => false]);
  exit;
}

// ── Handle preview ────────────────────────────────────────────────────
if (isset($_GET['preview'])) {
  $cfg    = loadConfig($CONFIG_FILE);
  $target = $_GET['preview'];
  $preset = $_GET['preset'] ?? 'last_7d';

  require_once __DIR__ . '/email-builder.php';

  if ($target === 'owner') {
    $blocks = '';
    foreach ($cfg['clients'] as $c) {
      $token = $TOKENS[$c['account']] ?? '';
      $blocks .= buildBlock($c['name'], $c['account'], $token, $preset);
    }
    echo buildEmail('Visi klientai', $blocks, $preset, $cfg['email_subject'], '');
  } else {
    foreach ($cfg['clients'] as $c) {
      if ($c['id'] === $target) {
        $token = $TOKENS[$c['account']] ?? '';
        $block = buildBlock($c['name'], $c['account'], $token, $preset);
        echo buildEmail($c['name'], $block, $preset, $cfg['email_subject'], $c['email_intro']);
        break;
      }
    }
  }
  exit;
}

// ── Handle send now ───────────────────────────────────────────────────
if (isset($_GET['send'])) {
  $cfg    = loadConfig($CONFIG_FILE);
  $target = $_GET['send'];
  $preset = scheduleToPeriod($cfg['owner_schedule'] ?? 'weekly');

  require_once __DIR__ . '/email-builder.php';

  $results = [];

  if ($target === 'owner' || $target === 'all') {
    $blocks = '';
    foreach ($cfg['clients'] as $c) {
      $token = $TOKENS[$c['account']] ?? '';
      $blocks .= buildBlock($c['name'], $c['account'], $token, $preset);
    }
    $html    = buildEmail('Visi klientai', $blocks, $preset, $cfg['email_subject'], '');
    $pdf     = generatePdf($html, 'ataskaita-' . date('Y-m-d') . '.pdf');
    $r       = smtpSend($cfg['owner_email'], $cfg['email_subject'] . ' · ' . date('Y-m-d'), $html, $pdf, 'ataskaita-' . date('Y-m-d') . '.pdf');
    $results[] = "Suvestinė → {$cfg['owner_email']}: $r" . ($pdf ? ' (PDF)' : '');
    $cfg['last_sent']['owner'] = date('Y-m-d H:i:s');
  }

  if ($target === 'clients' || $target === 'all') {
    foreach ($cfg['clients'] as $c) {
      if (empty($c['email'])) continue;
      $token   = $TOKENS[$c['account']] ?? '';
      $cPreset = scheduleToPeriod($c['schedule'] ?? 'weekly');
      $block   = buildBlock($c['name'], $c['account'], $token, $cPreset);
      $html    = buildEmail($c['name'], $block, $cPreset, $cfg['email_subject'], $c['email_intro']);
      $pdf     = generatePdf($html, $c['id'] . '-' . date('Y-m-d') . '.pdf');
      $r       = smtpSend($c['email'], $cfg['email_subject'] . ' · ' . date('Y-m-d'), $html, $pdf, $c['id'] . '-' . date('Y-m-d') . '.pdf');
      $results[] = "{$c['name']} → {$c['email']}: $r" . ($pdf ? ' (PDF)' : '');
      $cfg['last_sent'][$c['id']] = date('Y-m-d H:i:s');
    }
  }

  saveConfig($CONFIG_FILE, $cfg);
  $log = date('Y-m-d H:i:s') . " [manual send: $target]\n" . implode("\n", $results) . "\n\n";
  file_put_contents(__DIR__ . '/report-log.txt', $log, FILE_APPEND);

  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'results' => $results]);
  exit;
}

function scheduleToPeriod($schedule) {
  return match($schedule) {
    'daily'   => 'yesterday',
    'monthly' => 'last_month',
    default   => 'last_7d',
  };
}

$cfg = loadConfig($CONFIG_FILE);
$log = file_exists(__DIR__ . '/report-log.txt') ? file_get_contents(__DIR__ . '/report-log.txt') : 'Nėra įrašų.';
$logLines = array_slice(array_filter(explode("\n", trim($log))), -30);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>El. pašto ataskaitos — Jacob Media Pro</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0a; --surface: #141414; --surface2: #1e1e1e;
  --border: #2a2a2a; --text: #f0f0f0; --muted: #888;
  --accent: #E8720A; --green: #34d399; --red: #f87171;
}
body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; min-height: 100vh; }

.topnav { display: flex; align-items: center; gap: 16px; padding: 16px 32px; border-bottom: 1px solid var(--border); background: var(--surface); position: sticky; top: 0; z-index: 100; }
.logo { font-size: 16px; font-weight: 800; }
.logo span { color: var(--accent); }
.logo-badge { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: var(--accent); border-radius: 7px; font-size: 11px; font-weight: 900; color: #fff; margin-right: 8px; }
.nav-actions { margin-left: auto; display: flex; gap: 10px; }

.btn { padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { opacity: 0.85; }
.btn-ghost { background: none; border: 1px solid var(--border); color: var(--muted); }
.btn-ghost:hover { border-color: var(--accent); color: var(--text); }
.btn-green { background: rgba(52,211,153,0.15); border: 1px solid var(--green); color: var(--green); }
.btn-green:hover { background: rgba(52,211,153,0.25); }
.btn-sm { padding: 5px 12px; font-size: 12px; }

.main { max-width: 1100px; margin: 0 auto; padding: 32px; display: flex; flex-direction: column; gap: 28px; }

.section { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 28px; }
.section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted); }
.form-group input, .form-group select, .form-group textarea {
  background: var(--surface2); border: 1px solid var(--border); border-radius: 8px;
  padding: 10px 14px; color: var(--text); font-size: 13px; font-family: inherit;
  outline: none; transition: border-color 0.15s; width: 100%;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--accent); }
.form-group textarea { resize: vertical; min-height: 60px; }
.form-group select option { background: var(--surface2); }

.client-block { border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 14px; }
.client-block:last-child { margin-bottom: 0; }
.client-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.client-name { font-size: 15px; font-weight: 700; }
.client-actions { display: flex; gap: 8px; }

.last-sent { font-size: 11px; color: var(--muted); margin-top: 4px; }
.last-sent span { color: var(--green); }

.preview-wrap { background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.preview-bar { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 12px; color: var(--muted); }
.preview-bar select { background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 4px 10px; border-radius: 6px; font-size: 12px; outline: none; cursor: pointer; }
iframe { width: 100%; height: 600px; border: none; display: block; }

.log-box { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 16px; font-family: monospace; font-size: 12px; color: var(--muted); max-height: 220px; overflow-y: auto; white-space: pre-wrap; }

.send-all-bar { display: flex; gap: 10px; flex-wrap: wrap; }

.toast { position: fixed; bottom: 24px; right: 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; font-size: 13px; font-weight: 600; z-index: 999; display: none; }
.toast.ok  { border-color: var(--green); color: var(--green); }
.toast.err { border-color: var(--red);   color: var(--red); }
</style>
</head>
<body>

<nav class="topnav">
  <div class="logo"><span class="logo-badge">JMP</span>Jacob<span>Media Pro</span></div>
  <span style="color:var(--muted);font-size:13px">El. pašto ataskaitos</span>
  <div class="nav-actions">
    <a href="hub.php" class="btn btn-ghost">← Atgal į hub</a>
  </div>
</nav>

<div class="main">

  <!-- Send All -->
  <div class="section">
    <div class="section-title">Siųsti dabar</div>
    <div class="send-all-bar">
      <button class="btn btn-primary" onclick="sendNow('all')">📤 Siųsti visiems</button>
      <button class="btn btn-ghost" onclick="sendNow('owner')">Siųsti tik sau (suvestinė)</button>
      <button class="btn btn-ghost" onclick="sendNow('clients')">Siųsti tik klientams</button>
    </div>
    <div id="send-result" style="margin-top:14px;font-size:12px;color:var(--muted)"></div>
  </div>

  <!-- Owner settings -->
  <div class="section">
    <div class="section-title">Jūsų nustatymai</div>
    <div class="form-row">
      <div class="form-group">
        <label>Jūsų el. paštas</label>
        <input type="email" id="owner-email" value="<?= htmlspecialchars($cfg['owner_email']) ?>">
      </div>
      <div class="form-group">
        <label>Tvarkaraštis (suvestinė)</label>
        <select id="owner-schedule">
          <option value="daily"   <?= $cfg['owner_schedule']==='daily'  ?'selected':'' ?>>Kasdien</option>
          <option value="weekly"  <?= $cfg['owner_schedule']==='weekly' ?'selected':'' ?>>Kas savaitę (pirmadienį)</option>
          <option value="monthly" <?= $cfg['owner_schedule']==='monthly'?'selected':'' ?>>Kas mėnesį (1-ą dieną)</option>
          <option value="none"    <?= $cfg['owner_schedule']==='none'   ?'selected':'' ?>>Nessiųsti</option>
        </select>
      </div>
    </div>
    <div class="form-group" style="margin-bottom:16px">
      <label>Laiško tema (subject)</label>
      <input type="text" id="email-subject" value="<?= htmlspecialchars($cfg['email_subject']) ?>">
    </div>
  </div>

  <!-- Clients -->
  <div class="section">
    <div class="section-title">Klientai</div>
    <?php foreach ($cfg['clients'] as $c): ?>
    <div class="client-block" id="client-<?= $c['id'] ?>">
      <div class="client-header">
        <div>
          <div class="client-name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="last-sent">
            <?php if (!empty($cfg['last_sent'][$c['id']])): ?>
              Paskutinis siuntimas: <span><?= $cfg['last_sent'][$c['id']] ?></span>
            <?php else: ?>
              Dar nesiųsta
            <?php endif; ?>
          </div>
        </div>
        <div class="client-actions">
          <button class="btn btn-ghost btn-sm" onclick="previewClient('<?= $c['id'] ?>')">👁 Peržiūra</button>
          <button class="btn btn-green btn-sm" onclick="sendClient('<?= $c['id'] ?>', '<?= htmlspecialchars($c['email']) ?>')">📤 Siųsti</button>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Kliento el. paštas</label>
          <input type="email" id="email-<?= $c['id'] ?>" value="<?= htmlspecialchars($c['email']) ?>" placeholder="klientas@example.com">
        </div>
        <div class="form-group">
          <label>Tvarkaraštis</label>
          <select id="schedule-<?= $c['id'] ?>">
            <option value="daily"   <?= $c['schedule']==='daily'  ?'selected':'' ?>>Kasdien</option>
            <option value="weekly"  <?= $c['schedule']==='weekly' ?'selected':'' ?>>Kas savaitę</option>
            <option value="monthly" <?= $c['schedule']==='monthly'?'selected':'' ?>>Kas mėnesį</option>
            <option value="none"    <?= $c['schedule']==='none'   ?'selected':'' ?>>Nessiųsti</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Laiško įžanga (rodoma kliento laiške)</label>
        <textarea id="intro-<?= $c['id'] ?>"><?= htmlspecialchars($c['email_intro']) ?></textarea>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Preview -->
  <div class="section" id="preview-section" style="display:none">
    <div class="section-title">
      Laiško peržiūra — <span id="preview-title"></span>
      <button class="btn btn-ghost btn-sm" onclick="document.getElementById('preview-section').style.display='none'">✕ Uždaryti</button>
    </div>
    <div class="preview-wrap">
      <div class="preview-bar">
        <span>Laikotarpis:</span>
        <select id="preview-period" onchange="reloadPreview()">
          <option value="yesterday">Vakar</option>
          <option value="last_7d" selected>Paskutinės 7 d.</option>
          <option value="last_30d">Paskutinės 30 d.</option>
          <option value="last_month">Praėjęs mėnuo</option>
          <option value="this_month">Šis mėnuo</option>
        </select>
      </div>
      <iframe id="preview-frame" src=""></iframe>
    </div>
  </div>

  <!-- Log -->
  <div class="section">
    <div class="section-title">Siuntimo istorija</div>
    <div class="log-box"><?= htmlspecialchars(implode("\n", array_reverse($logLines))) ?></div>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
let currentPreviewTarget = '';

function showToast(msg, type = 'ok') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + type;
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3000);
}

async function saveConfig() {
  const clients = {};
  <?php foreach ($cfg['clients'] as $c): ?>
  clients['<?= $c['id'] ?>'] = {
    email:       document.getElementById('email-<?= $c['id'] ?>').value,
    schedule:    document.getElementById('schedule-<?= $c['id'] ?>').value,
    email_intro: document.getElementById('intro-<?= $c['id'] ?>').value,
  };
  <?php endforeach; ?>

  const res = await fetch('reports.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({
      action:          'save_config',
      owner_email:     document.getElementById('owner-email').value,
      owner_schedule:  document.getElementById('owner-schedule').value,
      email_subject:   document.getElementById('email-subject').value,
      clients,
    })
  });
  const json = await res.json();
  if (json.ok) showToast('✅ Išsaugota');
  else showToast('❌ Klaida', 'err');
}

function previewClient(id) {
  currentPreviewTarget = id;
  document.getElementById('preview-title').textContent = id === 'owner' ? 'Suvestinė' : id;
  document.getElementById('preview-section').style.display = 'block';
  reloadPreview();
  document.getElementById('preview-section').scrollIntoView({ behavior: 'smooth' });
}

function reloadPreview() {
  const preset = document.getElementById('preview-period').value;
  document.getElementById('preview-frame').src = `reports.php?preview=${currentPreviewTarget}&preset=${preset}`;
}

async function sendNow(target) {
  const res = document.getElementById('send-result');
  res.textContent = 'Siunčiama…';
  const r = await fetch(`reports.php?send=${target}`, { credentials: 'same-origin' });
  const json = await r.json();
  if (json.ok) {
    res.innerHTML = json.results.map(l => `<div>${l}</div>`).join('');
    showToast('✅ Išsiųsta');
  } else {
    showToast('❌ Klaida', 'err');
  }
}

async function sendClient(id, email) {
  await saveConfig();
  const r = await fetch(`reports.php?send=${id}`, { credentials: 'same-origin' });
  const json = await r.json();
  showToast(json.ok ? `✅ Išsiųsta → ${email}` : '❌ Klaida', json.ok ? 'ok' : 'err');
}

// Auto-save on change
document.querySelectorAll('input, select, textarea').forEach(el => {
  el.addEventListener('change', saveConfig);
});
</script>
</body>
</html>

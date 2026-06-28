<?php
// FIRST-TIME SETUP TOOL — sets your admin password on a fresh install.
// Once you set a password here, this page locks itself (won't let you change it again).
// After setting the password, DELETE this file from your server.

$credFile = __DIR__ . '/credentials.json';
$creds    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];
$alreadySet = !empty($creds['hash']);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySet) {
  $pass    = $_POST['password']        ?? '';
  $confirm = $_POST['password_confirm'] ?? '';

  if (strlen($pass) < 8) {
    $message = 'Slaptažodis turi būti bent 8 simbolių.';
  } elseif ($pass !== $confirm) {
    $message = 'Slaptažodžiai nesutampa.';
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    file_put_contents($credFile, json_encode(['hash' => $hash], JSON_PRETTY_PRINT));
    $success = true;
  }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pradinis nustatymas — Jacob Media Pro</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0a0a0a; color: #f0f0f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .box { background: #141414; border: 1px solid #2a2a2a; border-radius: 18px; padding: 40px; width: 100%; max-width: 400px; }
  .logo { display: flex; align-items: center; font-size: 20px; font-weight: 800; margin-bottom: 6px; }
  .badge { background: #E8720A; border-radius: 7px; padding: 4px 10px; font-size: 11px; font-weight: 900; color: #fff; margin-right: 10px; }
  .logo span { color: #E8720A; }
  .sub { color: #666; font-size: 13px; margin-bottom: 28px; }
  label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #888; margin-bottom: 6px; }
  input { width: 100%; background: #1e1e1e; border: 1px solid #2a2a2a; border-radius: 8px; padding: 11px 14px; color: #f0f0f0; font-size: 14px; outline: none; margin-bottom: 14px; transition: border-color 0.15s; }
  input:focus { border-color: #E8720A; }
  button { width: 100%; padding: 12px; background: #E8720A; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 4px; }
  button:hover { background: #c45a00; }
  .msg-err  { background: rgba(248,113,113,0.1); border: 1px solid #f87171; border-radius: 8px; padding: 12px 14px; color: #f87171; font-size: 13px; margin-bottom: 16px; }
  .msg-ok   { background: rgba(52,211,153,0.1); border: 1px solid #34d399; border-radius: 8px; padding: 16px; color: #34d399; font-size: 14px; }
  .msg-ok strong { display: block; font-size: 16px; margin-bottom: 6px; }
  .msg-locked { background: rgba(248,113,113,0.08); border: 1px solid #444; border-radius: 8px; padding: 16px; color: #888; font-size: 13px; }
  a.btn-link { display: inline-block; margin-top: 16px; padding: 10px 20px; background: #E8720A; color: #fff; border-radius: 8px; font-weight: 700; font-size: 14px; text-decoration: none; text-align: center; width: 100%; }
</style>
</head>
<body>
<div class="box">
  <div class="logo"><span class="badge">JMP</span>Jacob<span>Media Pro</span></div>
  <div class="sub">Pradinis sistemos nustatymas</div>

  <?php if ($success): ?>
    <div class="msg-ok">
      <strong>✅ Slaptažodis nustatytas!</strong>
      Dabar galite prisijungti. Ištrinkite šį failą (<code>make-hash.php</code>) iš serverio.
    </div>
    <a href="login.html" class="btn-link">→ Eiti į prisijungimą</a>

  <?php elseif ($alreadySet): ?>
    <div class="msg-locked">
      🔒 Slaptažodis jau nustatytas. Šis puslapis išjungtas.<br><br>
      Norėdami keisti slaptažodį — prisijunkite ir naudokite nustatymų mygtuką.<br><br>
      <strong>Ištrinkite šį failą iš serverio.</strong>
    </div>
    <a href="login.html" class="btn-link" style="margin-top:16px">→ Eiti į prisijungimą</a>

  <?php else: ?>
    <?php if ($message): ?>
      <div class="msg-err"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
      <label>Naujas slaptažodis (min. 8 simboliai)</label>
      <input type="password" name="password" placeholder="••••••••••••" autofocus required minlength="8">
      <label>Pakartoti slaptažodį</label>
      <input type="password" name="password_confirm" placeholder="••••••••••••" required minlength="8">
      <button type="submit">Nustatyti slaptažodį</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>

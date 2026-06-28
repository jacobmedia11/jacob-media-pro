<?php
session_start();

require_once __DIR__ . '/auth.php';

// Allow public client PIN-access via ?client=xxx without admin session
$clientParam = $_GET['client'] ?? '';

if (!$clientParam) {
    checkAdminPage(); // enforces auth + idle timeout, redirects to login.html if expired
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jacob Media Pro — Klientų valdymas</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0a0a0a;
    --surface: #141414;
    --surface2: #1e1e1e;
    --border: #2a2a2a;
    --text: #f0f0f0;
    --muted: #888;
    --accent: #E8720A;
    --accent2: #C45A00;
    --green: #34d399;
    --yellow: #E8720A;
    --red: #f87171;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 14px;
    line-height: 1.6;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
  }

  /* ── Top nav ── */
  .topnav {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 32px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .logo {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text);
  }

  .logo span { color: var(--accent); }

  .logo-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: var(--accent);
    border-radius: 7px;
    font-size: 11px;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.5px;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .nav-back {
    display: none;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--muted);
    cursor: pointer;
    border: none;
    background: none;
    padding: 0;
  }

  .nav-back:hover { color: var(--text); }
  .nav-back.visible { display: flex; }

  .nav-client-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    display: none;
  }

  .nav-client-name.visible { display: block; }

  .nav-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; }

  .btn {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
  }

  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #c45a00; }

  .btn-ghost {
    background: none;
    border: 1px solid var(--border);
    color: var(--muted);
  }

  .btn-ghost:hover { border-color: var(--accent); color: var(--text); }

  .btn-danger {
    background: none;
    border: 1px solid var(--border);
    color: var(--red);
    font-size: 12px;
    padding: 5px 12px;
  }

  .btn-danger:hover { background: rgba(248,113,113,0.1); border-color: var(--red); }

  /* ── Hub view ── */
  #hub-view { padding: 32px; max-width: 1200px; margin: 0 auto; }

  .hub-header { margin-bottom: 28px; }
  .hub-header h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .hub-header p  { color: var(--muted); font-size: 13px; }

  .clients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
  }

  .client-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
  }

  .client-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }

  .client-card .cc-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 4px;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #E8720A, #C45A00);
    color: #fff;
  }

  .cc-token-warn { font-size: 11px; font-weight: 600; color: #f87171; background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3); border-radius: 5px; padding: 3px 8px; margin-bottom: 8px; display: inline-block; }
  .client-card .cc-name { font-size: 17px; font-weight: 700; margin-bottom: 6px; color: var(--text); }
  .client-card .cc-account { font-size: 11px; color: var(--muted); font-family: monospace; margin-bottom: 16px; }

  .client-card .cc-link {
    font-size: 11px; color: var(--accent); margin-bottom: 12px; cursor: pointer;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.8; transition: opacity 0.15s;
  }
  .client-card .cc-link:hover { opacity: 1; }
  .client-card .cc-link span { text-decoration: underline; }

  .client-card .cc-stats { display: flex; gap: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
  .client-card .cc-stat .label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
  .client-card .cc-stat .val   { font-size: 16px; font-weight: 700; color: var(--text); margin-top: 2px; }
  .client-card .cc-stat .val.green  { color: var(--green); }
  .client-card .cc-stat .val.yellow { color: var(--yellow); }
  .client-card .cc-stat .val.purple { color: var(--accent2); }

  .client-card .cc-actions {
    position: absolute; top: 16px; right: 16px;
    display: flex; gap: 6px; opacity: 0; transition: opacity 0.15s;
  }
  .client-card:hover .cc-actions { opacity: 1; }

  .cc-icon-btn {
    width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border);
    background: var(--surface2); color: var(--muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s;
  }
  .cc-icon-btn:hover { border-color: var(--accent); color: var(--text); }
  .cc-icon-btn.del:hover { border-color: var(--red); color: var(--red); }

  .add-card {
    background: none; border: 2px dashed var(--border); border-radius: 14px; padding: 24px;
    cursor: pointer; transition: all 0.15s; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 10px; min-height: 160px; color: var(--muted);
  }
  .add-card:hover { border-color: var(--accent); color: var(--accent); }
  .add-card .plus { font-size: 28px; line-height: 1; }
  .add-card .label { font-size: 13px; font-weight: 600; }

  /* ── Dashboard view ── */
  #dashboard-view { display: none; }

  .refresh-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 32px; background: var(--surface);
    border-bottom: 1px solid var(--border); font-size: 12px; color: var(--muted);
  }

  .refresh-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--green);
    animation: pulse 2s infinite; flex-shrink: 0;
  }

  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
  .refresh-dot.loading { background: var(--yellow); animation: none; }
  .refresh-dot.error   { background: var(--red);    animation: none; }

  .period-select {
    background: var(--surface2); border: 1px solid var(--border); color: var(--text);
    font-size: 12px; padding: 4px 10px; border-radius: 6px; cursor: pointer; outline: none; margin-left: auto;
  }
  .period-select:hover { border-color: var(--accent); }

  .filter-status-btn {
    background: none; border: 1px solid var(--border); color: #555;
    padding: 5px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; transition: all .15s;
  }
  .filter-status-btn:hover { border-color: var(--accent); color: var(--text); }
  .filter-status-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(232,114,10,.08); }

  .refresh-btn {
    background: none; border: 1px solid var(--border); color: var(--muted);
    font-size: 11px; padding: 4px 12px; border-radius: 6px; cursor: pointer; transition: all 0.15s;
  }
  .refresh-btn:hover { border-color: var(--accent); color: var(--text); }

  .dash-main { padding: 28px 32px; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }

  .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }

  .filter-tab {
    padding: 7px 16px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--surface); color: var(--muted); font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.15s; user-select: none;
  }
  .filter-tab:hover { border-color: var(--accent); color: var(--text); }
  .filter-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

  .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px 22px 18px;
    position: relative;
    overflow: hidden;
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.35); }
  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 14px 14px 0 0;
  }
  .card.accent-yellow::before { background: linear-gradient(90deg, #E8720A, #f59e0b); }
  .card.accent-blue::before   { background: linear-gradient(90deg, #3b82f6, #6366f1); }
  .card.accent-green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
  .card.accent-purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

  .card .label { font-size: 11px; font-weight: 600; letter-spacing: 0.6px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
  .card .value { font-size: 30px; font-weight: 800; letter-spacing: -1.5px; color: var(--text); line-height: 1.1; }
  .card .sub   { font-size: 11px; color: var(--muted); margin-top: 6px; display: flex; align-items: center; gap: 4px; }
  .card .sub .trend-up   { color: #34d399; font-weight: 600; }
  .card .sub .trend-down { color: #f87171; font-weight: 600; }

  .card.accent-yellow .value { color: #E8720A; }
  .card.accent-blue .value   { color: #60a5fa; }
  .card.accent-green .value  { color: var(--green); }
  .card.accent-purple .value { color: #a78bfa; }

  .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  .chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    transition: box-shadow 0.15s;
  }
  .chart-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
  .chart-card h2 { font-size: 11px; font-weight: 700; letter-spacing: 0.6px; color: var(--muted); text-transform: uppercase; margin-bottom: 20px; }
  .chart-wrap { position: relative; height: 220px; }

  .table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; overflow-x: auto; }
  .table-card h2 { font-size: 11px; font-weight: 700; letter-spacing: 0.6px; color: var(--muted); text-transform: uppercase; margin-bottom: 18px; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead th { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--border); color: var(--muted); font-size: 10px; font-weight: 600; letter-spacing: 0.6px; text-transform: uppercase; white-space: nowrap; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; cursor: pointer; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  tbody tr.selected { background: rgba(79,142,247,0.08); border-left: 3px solid var(--accent); }
  tbody td { padding: 12px 12px; vertical-align: middle; }

  .campaign-name { font-weight: 600; color: var(--text); max-width: 260px; }

  .status-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; letter-spacing: 0.4px; }
  .status-badge.active { background: rgba(52,211,153,0.12); color: var(--green); }
  .status-badge.paused { background: rgba(123,129,153,0.15); color: var(--muted); }
  .status-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }

  .num { text-align: right; font-variant-numeric: tabular-nums; }
  .spend-bar-cell { min-width: 130px; }
  .spend-bar-wrap { display: flex; align-items: center; gap: 10px; }
  .spend-bar-track { flex:1; height:5px; background:var(--border); border-radius:3px; overflow:hidden; }
  .spend-bar-fill { height:100%; background: linear-gradient(90deg,var(--accent),var(--accent2)); border-radius:3px; }
  .spend-val { font-weight:700; font-variant-numeric:tabular-nums; color:var(--text); white-space:nowrap; }

  .conv-pills { display:flex; flex-wrap:wrap; gap:5px; }
  .conv-pill { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:2px 7px; font-size:10px; color:var(--muted); white-space:nowrap; }
  .conv-pill strong { color:var(--text); }

  /* ── Modal ── */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 200; align-items: center; justify-content: center; padding: 20px; }
  .modal-overlay.open { display: flex; }
  .modal { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 480px; }
  .modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .modal .modal-sub { color: var(--muted); font-size: 13px; margin-bottom: 24px; }

  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.6px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
  .form-group input, .form-group textarea { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; transition: border-color 0.15s; }
  .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); }
  .form-group textarea { resize: vertical; min-height: 70px; }
  .form-group .hint { font-size: 11px; color: var(--muted); margin-top: 5px; }

  .modal-actions { display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end; }
  .edit-modal-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; }

  /* ── Responsive ── */
  @media (max-width: 768px) {
    .topnav { padding: 12px 16px; }
    #hub-view { padding: 16px; }
    .clients-grid { grid-template-columns: 1fr; }
    .dash-main { padding: 16px; gap: 16px; }
    .refresh-bar { padding: 8px 16px; font-size: 11px; }
    .summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .card { padding: 14px; }
    .card .value { font-size: 22px; }
    .charts-row { grid-template-columns: 1fr; }
    .chart-wrap { height: 180px; }
    .table-card { padding: 14px; }
    thead th { padding: 6px 8px; }
    tbody td { padding: 10px 8px; font-size: 12px; }
    .campaign-name { max-width: 130px; font-size: 12px; }
  }

  @media (max-width: 400px) {
    .summary-grid { grid-template-columns: 1fr; }
  }

  /* ── Invoices view ── */
  #invoices-view { display:none; padding:32px; max-width:1200px; margin:0 auto; }
  .inv-page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; gap:16px; }
  .inv-page-header h1 { font-size:20px; font-weight:700; margin-bottom:4px; }
  .inv-page-header p  { color:var(--muted); font-size:13px; }
  .inv-summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
  .inv-table-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:24px; overflow-x:auto; }
  .inv-table-card h2 { font-size:11px; font-weight:600; letter-spacing:0.4px; color:var(--muted); text-transform:uppercase; margin-bottom:16px; }
  .inv-status { font-size:11px; font-weight:500; padding:2px 8px; border-radius:20px; }
  .inv-status.paid { background:rgba(52,211,153,0.12); color:#34d399; }
  .inv-status.pending { background:rgba(250,204,21,0.12); color:#facc15; }
  .inv-status.other { background:rgba(255,255,255,0.06); color:var(--muted); }
  .inv-dl-btn { background:var(--accent); color:#fff; border:none; border-radius:6px; padding:4px 12px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
  .inv-dl-btn:hover { opacity:0.85; }
  .inv-status { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; letter-spacing:0.4px; }
  .inv-status.sent  { background:rgba(52,211,153,0.12); color:var(--green); }
  .inv-status.draft { background:rgba(136,136,136,0.12); color:var(--muted); }
  .inv-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
  .inv-action-btn { background:none; border:1px solid var(--border); color:var(--muted); font-size:11px; padding:4px 10px; border-radius:6px; cursor:pointer; transition:all .15s; margin-left:4px; }
  .inv-action-btn:hover { border-color:var(--accent); color:var(--text); }
  .inv-action-btn.del:hover { border-color:var(--red); color:var(--red); }
  .modal-wide { max-width:700px !important; }
  .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .svc-header { display:grid; grid-template-columns:1.8fr 1.8fr 56px 90px 28px; gap:6px; margin-bottom:6px; }
  .svc-header span { font-size:10px; font-weight:600; letter-spacing:0.6px; text-transform:uppercase; color:var(--muted); }
  .svc-header span.r { text-align:right; }
  .svc-row { display:grid; grid-template-columns:1.8fr 1.8fr 56px 90px 28px; gap:6px; margin-bottom:6px; align-items:center; }
  .svc-row input { background:var(--surface2); border:1px solid var(--border); border-radius:6px; padding:7px 10px; color:var(--text); font-size:12px; font-family:inherit; outline:none; width:100%; transition:border-color .15s; }
  .svc-row input:focus { border-color:var(--accent); }
  .svc-row input.r { text-align:right; }
  .svc-del { background:none; border:none; color:var(--muted); cursor:pointer; font-size:18px; line-height:1; padding:0; border-radius:4px; transition:color .1s; }
  .svc-del:hover { color:var(--red); }
  .add-svc-btn { background:none; border:1px dashed var(--border); border-radius:6px; color:var(--muted); font-size:12px; padding:6px 14px; cursor:pointer; width:100%; margin-top:4px; transition:all .15s; }
  .add-svc-btn:hover { border-color:var(--accent); color:var(--text); }
  .inv-total-row { display:flex; justify-content:flex-end; gap:16px; align-items:center; padding-top:12px; border-top:1px solid var(--border); margin-top:6px; }
  .inv-total-row .tl { color:var(--muted); font-size:13px; }
  .inv-total-row .tv { font-size:20px; font-weight:700; color:var(--green); font-variant-numeric:tabular-nums; }
  .inv-select { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; cursor:pointer; font-family:inherit; transition:border-color .15s; }
  .inv-select:focus { border-color:var(--accent); }
  .inv-month { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:13px; outline:none; font-family:inherit; cursor:pointer; transition:border-color .15s; }
  .inv-month:focus { border-color:var(--accent); }

  @media (max-width:768px) {
    #invoices-view { padding:16px; }
    .inv-summary-grid { grid-template-columns:1fr; }
    .modal-wide { max-width:100% !important; }
    .svc-row { grid-template-columns:1fr 1fr 50px 70px 24px; }
    .svc-header { grid-template-columns:1fr 1fr 50px 70px 24px; }
    .form-row-2 { grid-template-columns:1fr; }
  }
</style>
</head>
<body>

<!-- PIN view (public client access) -->
<div id="pin-view" style="display:none;min-height:100vh;align-items:center;justify-content:center;background:#080808;background-image:radial-gradient(ellipse at 50% 0%, rgba(232,114,10,0.08) 0%, transparent 70%)">
  <div style="width:360px;text-align:center;padding:20px">

    <!-- Logo -->
    <div style="display:inline-flex;align-items:center;gap:10px;margin-bottom:40px">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:linear-gradient(135deg,#E8720A,#c45a00);border-radius:10px;font-size:13px;font-weight:900;color:#fff;letter-spacing:-0.5px">JMP</span>
      <span style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px">Jacob<span style="color:#E8720A">Media</span></span>
    </div>

    <!-- Card -->
    <div style="background:#111;border:1px solid #1f1f1f;border-radius:20px;padding:36px 32px;box-shadow:0 24px 64px rgba(0,0,0,0.5)">
      <div id="pin-client-name" style="color:#E8720A;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px"></div>
      <div style="color:#fff;font-size:22px;font-weight:700;margin-bottom:4px;letter-spacing:-0.5px">Sveiki atvykę</div>
      <div style="color:#555;font-size:13px;margin-bottom:28px">Įveskite savo PIN kodą norėdami tęsti</div>

      <input id="pin-input" type="password" maxlength="6" inputmode="numeric"
        placeholder="• • • •"
        onkeydown="if(event.key==='Enter') checkPin()"
        style="width:100%;padding:16px;font-size:26px;letter-spacing:10px;text-align:center;background:#0d0d0d;border:1px solid #2a2a2a;border-radius:12px;color:#fff;outline:none;box-sizing:border-box;margin-bottom:8px;font-family:inherit;transition:border-color 0.2s"
        onfocus="this.style.borderColor='#E8720A'" onblur="this.style.borderColor='#2a2a2a'">

      <div id="pin-error" style="display:none;color:#f87171;font-size:12px;margin-bottom:12px;padding:8px 12px;background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2);border-radius:8px">Neteisingas PIN kodas</div>

      <button onclick="checkPin()" style="width:100%;padding:14px;background:linear-gradient(135deg,#E8720A,#c45a00);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity 0.15s;margin-bottom:20px" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">Prisijungti</button>

      <button onclick="forgotPin()" id="forgot-btn" style="background:none;border:none;color:#444;font-size:12px;cursor:pointer;font-family:inherit;transition:color 0.15s" onmouseover="this.style.color='#888'" onmouseout="this.style.color='#444'">Pamiršau PIN kodą</button>
      <div id="forgot-msg" style="display:none;font-size:12px;margin-top:12px;padding:10px 14px;border-radius:10px"></div>
    </div>

    <div style="color:#2a2a2a;font-size:11px;margin-top:24px">© Jacob Media Pro</div>
  </div>
</div>

<!-- Top nav -->
<nav class="topnav" id="topnav" style="display:none">
  <div class="logo"><span class="logo-badge">JMP</span>Jacob<span>Media Pro</span></div>
  <button class="nav-back" id="nav-back" onclick="showHub()">← Visi klientai</button>
  <span class="nav-client-name" id="nav-client-name"></span>
  <div class="nav-actions">
    <button class="btn btn-primary" id="nav-add-btn" onclick="openAddModal()">+ Naujas klientas</button>
    <a href="reports.php" class="btn btn-ghost" id="nav-reports-btn" style="display:none;text-decoration:none">✉ Ataskaitos</a>
    <button class="btn btn-ghost" id="nav-invoices-btn" onclick="showInvoices()" style="display:none">📄 Sąskaitos</button>
    <button class="btn btn-ghost" onclick="openPwModal()" title="Keisti slaptažodį">🔑</button>
    <button class="btn btn-ghost" onclick="doLogout()" title="Atsijungti">⏻</button>
    <button class="btn btn-ghost" id="nav-refresh-btn" style="display:none" onclick="loadDashboard()">↻ Atnaujinti</button>
    <button class="btn btn-ghost" id="nav-pdf-btn" style="display:none" onclick="exportPdf()">⬇ PDF</button>
  </div>
</nav>

<!-- Hub view -->
<div id="hub-view" style="display:none">
  <div class="hub-header">
    <h1>Klientų valdymas</h1>
    <p>Padedame verslams augti efektyviai — pasirinkite klientą arba pridėkite naują.</p>
  </div>
  <div class="clients-grid" id="clients-grid"></div>

  <div style="margin-top:48px">
    <h2 style="font-size:15px;font-weight:700;color:#aaa;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #1e1e1e">Klientų prisijungimai</h2>
    <div id="access-log-list" style="color:#555;font-size:13px">Kraunama…</div>
  </div>
</div>

<!-- Dashboard view -->
<div id="dashboard-view">
  <div class="refresh-bar">
    <div class="refresh-dot" id="refresh-dot"></div>
    <span id="refresh-status">Kraunama…</span>
    <div style="display:flex;gap:6px;margin-right:12px">
      <button class="filter-status-btn active" data-status="all" onclick="setStatusFilter('all')">Visos</button>
      <button class="filter-status-btn" data-status="ACTIVE" onclick="setStatusFilter('ACTIVE')">Aktyvios</button>
      <button class="filter-status-btn" data-status="PAUSED" onclick="setStatusFilter('PAUSED')">Sustabdytos</button>
    </div>
    <select class="period-select" id="period-select" onchange="loadDashboard()">
      <option value="today">Šiandien</option>
      <option value="yesterday">Vakar</option>
      <option value="last_7d">Paskutinės 7 d.</option>
      <option value="last_14d">Paskutinės 14 d.</option>
      <option value="last_30d" selected>Paskutinės 30 d.</option>
      <option value="last_90d">Paskutinės 90 d.</option>
      <option value="this_month">Šis mėnuo</option>
      <option value="last_month">Praėjęs mėnuo</option>
      <option value="this_year">Šie metai</option>
      <option value="maximum">Visų laikų</option>
    </select>
  </div>

  <div class="dash-main">
    <div class="filter-tabs" id="filter-tabs">
      <div class="filter-tab active" data-idx="-1">Visos kampanijos</div>
    </div>

    <div class="summary-grid">
      <div class="card accent-yellow">
        <div class="label">Išlaidos iš viso</div>
        <div class="value" id="val-spend">—</div>
        <div class="sub" id="sub-spend">—</div>
      </div>
      <div class="card accent-blue">
        <div class="label">Paspaudimai</div>
        <div class="value" id="val-clicks">—</div>
        <div class="sub" id="sub-clicks">—</div>
      </div>
      <div class="card accent-green">
        <div class="label">CTR</div>
        <div class="value" id="val-ctr">—</div>
        <div class="sub" id="sub-ctr">—</div>
      </div>
      <div class="card accent-purple">
        <div class="label">Potencialūs klientai</div>
        <div class="value" id="val-leads">—</div>
        <div class="sub" id="sub-leads">—</div>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card">
        <h2>Išlaidos pagal kampaniją</h2>
        <div class="chart-wrap"><canvas id="spendChart"></canvas></div>
      </div>
      <div class="chart-card">
        <h2>Paspaudimai ir potencialūs klientai</h2>
        <div class="chart-wrap"><canvas id="convChart"></canvas></div>
      </div>
    </div>

    <div class="table-card">
      <h2>Kampanijų apžvalga — paskutinės 30 dienų</h2>
      <table>
        <thead>
          <tr>
            <th>Kampanija</th>
            <th>Būsena</th>
            <th class="spend-bar-cell">Išlaidos</th>
            <th class="num">Parodymai</th>
            <th class="num">Pasiekti</th>
            <th class="num">Paspaudimai</th>
            <th class="num">CTR</th>
            <th class="num">CPC</th>
            <th>Konversijos</th>
          </tr>
        </thead>
        <tbody id="campaign-tbody">
          <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:32px">Kraunama…</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Meta invoices section — visible to client after PIN login -->
    <div class="table-card" id="meta-invoices-section" style="margin-top:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <h2 style="margin-bottom:0">Meta sąskaitos (Invoices)</h2>
        <button onclick="loadMetaInvoices()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 12px;color:var(--muted);font-size:12px;cursor:pointer">↻ Atnaujinti</button>
      </div>
      <div id="meta-invoices-body">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr>
              <th style="text-align:left;padding:8px 12px;color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Data</th>
              <th style="text-align:left;padding:8px 12px;color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Laikotarpis</th>
              <th style="text-align:left;padding:8px 12px;color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Tipas</th>
              <th style="text-align:right;padding:8px 12px;color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Suma</th>
              <th style="text-align:left;padding:8px 12px;color:var(--muted);font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border)">Statusas</th>
              <th style="text-align:right;padding:8px 12px;border-bottom:1px solid var(--border)"></th>
            </tr>
          </thead>
          <tbody id="meta-invoices-tbody">
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">Kraunama…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Invoices view -->
<div id="invoices-view">
  <div class="inv-page-header">
    <div>
      <h1>Sąskaitos-faktūros</h1>
      <p>Kurkite ir siųskite sąskaitas klientams. Automatinis siuntimas — kiekvieno mėnesio 1 d.</p>
    </div>
    <button class="btn btn-primary" onclick="openNewInvModal()">+ Nauja sąskaita</button>
  </div>

  <div class="inv-summary-grid">
    <div class="card accent-green">
      <div class="label">Šį mėnesį</div>
      <div class="value" id="inv-sum-month">—</div>
      <div class="sub" id="inv-sum-month-sub">—</div>
    </div>
    <div class="card accent-yellow">
      <div class="label">Išsiųsta iš viso</div>
      <div class="value" id="inv-sum-sent">—</div>
      <div class="sub">sąskaitų</div>
    </div>
    <div class="card">
      <div class="label">Juodraščiai</div>
      <div class="value" id="inv-sum-draft">—</div>
      <div class="sub">laukia siuntimo</div>
    </div>
  </div>

  <div class="inv-table-card">
    <h2>Sąskaitų istorija</h2>
    <table>
      <thead>
        <tr>
          <th>Numeris</th>
          <th>Klientas</th>
          <th>Laikotarpis</th>
          <th class="num">Suma</th>
          <th>Statusas</th>
          <th>Data</th>
          <th style="text-align:right">Veiksmai</th>
        </tr>
      </thead>
      <tbody id="inv-tbody">
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">Kraunama…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Invoice modal -->
<div class="modal-overlay" id="inv-modal-overlay">
  <div class="modal modal-wide">
    <h2>Nauja sąskaita-faktūra</h2>
    <p class="modal-sub">Pasirinkite klientą, pridėkite paslaugas ir išsiųskite.</p>

    <div class="form-row-2" style="margin-bottom:16px">
      <div class="form-group" style="margin-bottom:0">
        <label>Klientas</label>
        <select id="inv-f-client" class="inv-select" onchange="onInvClientChange()">
          <option value="">— Pasirinkite klientą —</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Laikotarpis</label>
        <input type="month" id="inv-f-period" class="inv-month" onchange="updateInvNumber()">
      </div>
    </div>

    <div class="form-group">
      <label>Sąskaitos numeris</label>
      <input type="text" id="inv-f-number" placeholder="pvz. INV-GRINDAI-202606">
    </div>

    <div class="form-group" style="margin-bottom:4px">
      <label>Paslaugos</label>
      <div class="svc-header">
        <span>Pavadinimas</span>
        <span>Aprašymas</span>
        <span class="r">Kiekis</span>
        <span class="r">Kaina €</span>
        <span></span>
      </div>
      <div id="inv-svc-rows"></div>
      <button class="add-svc-btn" onclick="addInvSvcRow()">+ Pridėti paslaugą</button>
    </div>

    <div class="inv-total-row">
      <span class="tl">Iš viso mokėti:</span>
      <span class="tv" id="inv-f-total">€0.00</span>
    </div>

    <div class="edit-modal-actions" style="margin-top:20px">
      <button class="btn btn-ghost" onclick="previewInvoice()" style="font-size:12px;padding:7px 14px">👁 Peržiūrėti</button>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost" onclick="closeInvModal()">Atšaukti</button>
        <button class="btn btn-ghost" id="inv-save-btn" onclick="saveInvDraft()">Išsaugoti</button>
        <button class="btn btn-primary" id="inv-send-btn" onclick="sendInvNow()">✉ Išsiųsti</button>
      </div>
    </div>
  </div>
</div>

<!-- Password Change Modal -->
<div class="modal-overlay" id="pw-modal-overlay">
  <div class="modal" style="max-width:400px">
    <h2>Keisti slaptažodį</h2>
    <p class="modal-sub">Įveskite dabartinį slaptažodį ir naują.</p>
    <div class="form-group">
      <label>Dabartinis slaptažodis</label>
      <input type="password" id="pw-current" placeholder="••••••••" onkeydown="if(event.key==='Enter') changePassword()">
    </div>
    <div class="form-group">
      <label>Naujas slaptažodis (min. 8 simboliai)</label>
      <input type="password" id="pw-new" placeholder="••••••••••••" onkeydown="if(event.key==='Enter') changePassword()">
    </div>
    <div class="form-group">
      <label>Pakartoti naują slaptažodį</label>
      <input type="password" id="pw-confirm" placeholder="••••••••••••" onkeydown="if(event.key==='Enter') changePassword()">
    </div>
    <div id="pw-error" style="color:var(--red);font-size:12px;margin-top:-8px;margin-bottom:4px;display:none"></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closePwModal()">Atšaukti</button>
      <button class="btn btn-primary" onclick="changePassword()">Išsaugoti</button>
    </div>
  </div>
</div>

<!-- Add/Edit Client Modal -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal">
    <h2 id="modal-title">Naujas klientas</h2>
    <p class="modal-sub">Įveskite kliento Meta Ads paskyros informaciją.</p>

    <div class="form-group">
      <label>Kliento pavadinimas</label>
      <input type="text" id="f-name" placeholder="pvz. Žvaigždžių Slėnis">
    </div>
    <div class="form-group">
      <label>Ad Account ID</label>
      <input type="text" id="f-account" placeholder="pvz. 924585781724581">
      <div class="hint">Be „act_" priešdėlio</div>
    </div>
    <div class="form-group">
      <label>Access Token</label>
      <textarea id="f-token" placeholder="EAAYENe…" style="font-size:11px;height:60px"></textarea>
      <div class="hint">Meta Graph API token (ads_read). Saugomas tik serveryje.</div>
    </div>
    <div class="form-group">
      <label>El. paštas (PIN bus išsiųstas automatiškai)</label>
      <input type="email" id="f-email" placeholder="pvz. klientas@email.lt">
    </div>
    <div class="form-group">
      <label>Pašalinti kampanijas (neprivaloma)</label>
      <input type="text" id="f-excluded" placeholder="pvz. Traffic Campaign, Test">
      <div class="hint">Kampanijų pavadinimai atskirti kableliu</div>
    </div>

    <div id="pin-reset-group" style="display:none;padding-top:16px;border-top:1px solid var(--border);margin-top:4px">
      <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:var(--muted);margin-bottom:10px">Kliento PIN kodas</div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <button class="btn btn-ghost" style="font-size:12px;padding:6px 14px" onclick="resetPin()">🔄 Generuoti naują PIN</button>
        <span id="pin-reset-msg" style="font-size:12px;color:var(--muted)"></span>
      </div>
    </div>

    <div class="edit-modal-actions">
      <button class="btn btn-danger" id="modal-delete-btn" onclick="deleteClient()" style="display:none">Ištrinti klientą</button>
      <div style="display:flex;gap:10px;margin-left:auto">
        <button class="btn btn-ghost" onclick="closeModal()">Atšaukti</button>
        <button class="btn btn-primary" onclick="saveClient()">Išsaugoti</button>
      </div>
    </div>
  </div>
</div>

<script>
  // ── Auth ──────────────────────────────────────────────────────────────
  async function forgotPin() {
    const clientId = new URLSearchParams(window.location.search).get('client');
    if (!clientId) return;

    const btn   = document.getElementById('forgot-btn');
    const msgEl = document.getElementById('forgot-msg');

    btn.disabled    = true;
    btn.textContent = '...';
    msgEl.style.display = 'none';

    try {
      const res  = await fetch('forgot-pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ clientId }),
        credentials: 'same-origin'
      });
      const json = await res.json();

      msgEl.style.display = 'block';
      if (json.ok) {
        msgEl.style.background = 'rgba(52,211,153,0.1)';
        msgEl.style.border     = '1px solid #34d399';
        msgEl.style.color      = '#34d399';
        msgEl.textContent      = '✅ Naujas PIN išsiųstas į ' + json.email;
      } else {
        msgEl.style.background = 'rgba(248,113,113,0.1)';
        msgEl.style.border     = '1px solid #f87171';
        msgEl.style.color      = '#f87171';
        msgEl.textContent      = json.error || 'Klaida. Bandykite dar kartą.';
      }
    } catch(e) {
      msgEl.style.display    = 'block';
      msgEl.style.background = 'rgba(248,113,113,0.1)';
      msgEl.style.border     = '1px solid #f87171';
      msgEl.style.color      = '#f87171';
      msgEl.textContent      = 'Ryšio klaida. Bandykite dar kartą.';
    }

    btn.disabled    = false;
    btn.textContent = 'Pamiršau PIN kodą';
  }

  async function doLogout() {
    await fetch('login.php?action=logout', { credentials: 'same-origin' });
    window.location.href = 'login.html';
  }

  function showClientDashboard(client) {
    document.getElementById('pin-view').style.display = 'none';
    document.getElementById('topnav').style.display = 'none';
    document.getElementById('hub-view').style.display = 'none';
    document.getElementById('dashboard-view').style.display = 'block';
    const bar = document.getElementById('refresh-status');
    bar.textContent = client.name + ' — Kraunama…';
    currentClient = client;
    window._publicMode = true;
    loadDashboard();
    loadMetaInvoices();
    setInterval(loadDashboard, 30 * 60 * 1000);
  }

  async function checkPin() {
    const entered  = document.getElementById('pin-input').value.trim();
    const clientId = new URLSearchParams(window.location.search).get('client');
    if (!clientId || !entered) return;
    const cr     = await fetch('clients-list.php?id=' + encodeURIComponent(clientId), { credentials: 'same-origin' });
    const client = await cr.json();
    if (!client) return;

    const btn = document.querySelector('#pin-view button');
    btn.disabled = true;
    btn.textContent = '...';

    try {
      const res  = await fetch('verify-pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ clientId, pin: entered }),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.ok) {
        document.getElementById('pin-error').style.display = 'none';
        showClientDashboard(client);
      } else {
        document.getElementById('pin-error').textContent = data.error || 'Neteisingas PIN kodas';
        document.getElementById('pin-error').style.display = 'block';
        document.getElementById('pin-input').value = '';
        document.getElementById('pin-input').focus();
        btn.disabled = false;
        btn.textContent = 'Prisijungti';
      }
    } catch (e) {
      document.getElementById('pin-error').textContent = 'Klaida. Bandykite dar kartą.';
      document.getElementById('pin-error').style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Prisijungti';
    }
  }

  // ── Chart state ────────────────────────────────────────────────────────
  let spendChart = null, convChart = null;
  let allCampaigns = [], selectedIdx = -1, currentClient = null, statusFilter = 'all';
  let prevTotals = null;

  function setStatusFilter(status) {
    statusFilter = status;
    document.querySelectorAll('.filter-status-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.status === status);
    });
    renderCampaigns();
  }

  const COLORS = [
    { bg:'rgba(79,142,247,0.75)',  border:'rgba(79,142,247,1)'  },
    { bg:'rgba(167,139,250,0.75)', border:'rgba(167,139,250,1)' },
    { bg:'rgba(251,191,36,0.75)',  border:'rgba(251,191,36,1)'  },
    { bg:'rgba(52,211,153,0.75)',  border:'rgba(52,211,153,1)'  },
    { bg:'rgba(248,113,113,0.75)', border:'rgba(248,113,113,1)' },
  ];

  const DG = { color:'rgba(255,255,255,0.05)' };
  const DT = { color:'#7b8199', font:{ size:11 } };

  Chart.defaults.color = '#7b8199';
  Chart.defaults.borderColor = '#2a3045';

  // ── Hub rendering ──────────────────────────────────────────────────────
  async function loadAccessLog() {
    const el = document.getElementById('access-log-list');
    if (!el) return;
    try {
      const r    = await fetch('access-log.php', { credentials: 'same-origin' });
      const logs = await r.json();
      if (!logs.length) { el.textContent = 'Dar nėra prisijungimų.'; return; }
      el.innerHTML = `<table style="width:100%;border-collapse:collapse">
        <thead><tr>
          <th style="text-align:left;padding:8px 12px;color:#444;font-size:11px;text-transform:uppercase">Klientas</th>
          <th style="text-align:left;padding:8px 12px;color:#444;font-size:11px;text-transform:uppercase">Laikas</th>
          <th style="text-align:left;padding:8px 12px;color:#444;font-size:11px;text-transform:uppercase">IP</th>
        </tr></thead>
        <tbody>${logs.slice(0,20).map(l => `<tr>
          <td style="padding:8px 12px;border-top:1px solid #1a1a1a;color:#ccc">${l.client}</td>
          <td style="padding:8px 12px;border-top:1px solid #1a1a1a;color:#555">${l.time}</td>
          <td style="padding:8px 12px;border-top:1px solid #1a1a1a;color:#333">${l.ip}</td>
        </tr>`).join('')}</tbody>
      </table>`;
    } catch(e) { el.textContent = 'Žurnalas nepasiekiamas.'; }
  }

  async function renderHub() {
    const grid = document.getElementById('clients-grid');
    grid.innerHTML = '<div style="color:#555;padding:20px">Kraunama…</div>';
    let clients = [];
    try {
      const r = await fetch('clients-list.php', { credentials: 'same-origin' });
      clients = await r.json();
    } catch(e) {}
    grid.innerHTML = '';
    loadAccessLog();

    clients.forEach(client => {
      const card = document.createElement('div');
      card.className = 'client-card';
      card.innerHTML = `
        <div class="cc-actions">
          <div class="cc-icon-btn" onclick="event.stopPropagation(); copyLink('${client.id}')" title="Kopijuoti nuorodą">🔗</div>
          <div class="cc-icon-btn" onclick="event.stopPropagation(); openEditModal('${client.id}')" title="Redaguoti">✎</div>
          <div class="cc-icon-btn del" onclick="event.stopPropagation(); confirmDelete('${client.id}')" title="Ištrinti">✕</div>
        </div>
        <div class="cc-badge">Meta Ads</div>
        ${client.token_error ? `<div class="cc-token-warn" title="${client.token_error_at || ''}">⚠️ Tokenas nebegalioja</div>` : ''}
        <div class="cc-name">${client.name}</div>
        <div class="cc-account">act_${client.account}</div>
        <div class="cc-stats">
          <div class="cc-stat"><div class="label">Išlaidos</div><div class="val yellow" id="stat-spend-${client.id}">—</div></div>
          <div class="cc-stat"><div class="label">Klientai</div><div class="val purple" id="stat-leads-${client.id}">—</div></div>
          <div class="cc-stat"><div class="label">CTR</div><div class="val green" id="stat-ctr-${client.id}">—</div></div>
        </div>
      `;
      card.addEventListener('click', () => openClient(client.id));
      grid.appendChild(card);
    });

    const addCard = document.createElement('div');
    addCard.className = 'add-card';
    addCard.innerHTML = '<div class="plus">+</div><div class="label">Pridėti klientą</div>';
    addCard.addEventListener('click', openAddModal);
    grid.appendChild(addCard);

    clients.forEach(c => loadQuickStats(c));
  }

  async function loadQuickStats(client) {
    try {
      const preset = document.getElementById('period-select')?.value || 'last_30d';
      const pub = window._publicMode ? '&public=1' : '';
      const url = `api-proxy.php?account=${client.account}&preset=${preset}&type=quickstats${pub}`;
      const res = await fetch(url);
      const json = await res.json();
      if (json.error) return;

      const excl = client.excluded || [];
      const campaigns = (json.data || [])
        .filter(c => !excl.includes(c.name))
        .filter(c => c.insights?.data?.[0] && parseFloat(c.insights.data[0].spend) > 0);

      const totalSpend  = campaigns.reduce((s, c) => s + parseFloat(c.insights.data[0].spend), 0);
      const totalImpr   = campaigns.reduce((s, c) => s + parseInt(c.insights.data[0].impressions), 0);
      const totalClicks = campaigns.reduce((s, c) => s + parseInt(c.insights.data[0].clicks), 0);
      const totalLeads  = campaigns.reduce((s, c) => s + getLeads(c.insights.data[0].actions), 0);
      const avgCtr      = totalImpr > 0 ? (totalClicks / totalImpr * 100) : 0;

      const spendEl = document.getElementById(`stat-spend-${client.id}`);
      const leadsEl = document.getElementById(`stat-leads-${client.id}`);
      const ctrEl   = document.getElementById(`stat-ctr-${client.id}`);

      if (spendEl) spendEl.textContent = '€' + totalSpend.toFixed(0);
      if (leadsEl) leadsEl.textContent = totalLeads || '—';
      if (ctrEl)   ctrEl.textContent   = avgCtr.toFixed(2) + '%';
    } catch (e) {}
  }

  // ── Open client dashboard ──────────────────────────────────────────────
  async function openClient(id) {
    const r = await fetch('clients-list.php', { credentials: 'same-origin' });
    const clients = await r.json();
    currentClient = clients.find(c => c.id === id);
    if (!currentClient) return;

    selectedIdx = -1;
    document.getElementById('hub-view').style.display = 'none';
    document.getElementById('dashboard-view').style.display = 'block';
    document.getElementById('nav-back').classList.add('visible');
    document.getElementById('nav-client-name').textContent = currentClient.name;
    document.getElementById('nav-client-name').classList.add('visible');
    document.getElementById('nav-add-btn').style.display = 'none';
    document.getElementById('nav-refresh-btn').style.display = 'inline-flex';
    document.getElementById('nav-pdf-btn').style.display = 'inline-flex';

    loadDashboard();
    loadMetaInvoices();
  }

  function exportPdf() {
    if (!currentClient) return;
    const preset = document.getElementById('period-select')?.value || 'last_30d';
    const pub    = window._publicMode ? '&public=1' : '';
    const client = window._publicMode ? '&client=' + (new URLSearchParams(location.search).get('client') || '') : '';
    window.open(`export-pdf.php?account=${currentClient.account}&preset=${preset}${pub}${client}`, '_blank');
  }

  function showHub() {
    document.getElementById('hub-view').style.display = 'block';
    document.getElementById('dashboard-view').style.display = 'none';
    document.getElementById('invoices-view').style.display = 'none';
    document.getElementById('nav-back').classList.remove('visible');
    document.getElementById('nav-client-name').classList.remove('visible');
    document.getElementById('nav-add-btn').style.display = 'inline-flex';
    document.getElementById('nav-refresh-btn').style.display = 'none';
    document.getElementById('nav-pdf-btn').style.display = 'none';
    currentClient = null;
    renderHub();
  }

  // ── Dashboard data ─────────────────────────────────────────────────────
  async function loadMetaInvoices() {
    if (!currentClient) return;
    const tbody = document.getElementById('meta-invoices-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">Kraunama…</td></tr>';

    try {
      const res  = await fetch(`meta-invoices.php?client=${currentClient.id}`, { credentials: 'same-origin' });
      const json = await res.json();

      if (json.unavailable) {
        document.getElementById('meta-invoices-body').innerHTML = `
          <div style="padding:40px 20px;text-align:center">
            <div style="font-size:32px;margin-bottom:16px">🧾</div>
            <div style="color:var(--text);font-size:15px;font-weight:600;margin-bottom:8px">View your Meta invoices</div>
            <div style="color:var(--muted);font-size:13px;margin-bottom:24px">Click below to open your billing page in Meta Business Manager</div>
            <a href="https://adsmanager.facebook.com/adsmanager/manage/payment/settings/?act=${currentClient.account}" target="_blank"
               style="display:inline-block;padding:12px 28px;background:var(--accent);color:#fff;border-radius:8px;font-size:14px;font-weight:700;text-decoration:none">
              Open Meta Billing →
            </a>
          </div>`;
        return;
      }
      if (json.error) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#f87171;padding:24px">${json.error}</td></tr>`;
        return;
      }

      const list = json.invoices || [];
      const src  = json.source || 'invoice';

      // Update section title based on data source
      document.querySelector('#meta-invoices-section h2').textContent =
        src === 'transaction' ? 'Meta mokėjimai (Transactions)' : 'Meta sąskaitos (Invoices)';

      if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">Įrašų nerasta.</td></tr>';
        return;
      }

      tbody.innerHTML = list.map(inv => {
        const date   = inv.time ? inv.time.slice(0, 10) : '—';
        const period = inv.billing_period
          ? inv.billing_period
          : (inv.time_stop ? `${date} – ${inv.time_stop.slice(0,10)}` : '—');
        const type   = inv.type === 'TAX_INVOICE' ? 'PVM sąskaita'
                     : inv.type === 'CHARGE'       ? 'Mokestis'
                     : (inv.type || '—');
        const amount    = inv.amount ? `${parseFloat(inv.amount).toFixed(2)} ${inv.currency}` : '—';
        const statusCls = inv.status === 'PAID' || inv.status === 'COMPLETED' ? 'paid'
                        : inv.status === 'PAYABLE' ? 'pending' : 'other';
        const statusLbl = inv.status === 'PAID' || inv.status === 'COMPLETED' ? 'Apmokėta'
                        : inv.status === 'PAYABLE' ? 'Laukiama' : (inv.status || '—');
        const dlBtn = inv.vat_invoice_id
          ? `<a href="download-invoice.php?client=${currentClient.id}&inv=${inv.vat_invoice_id}" class="inv-dl-btn">⬇ PDF</a>`
          : '<span style="color:var(--muted);font-size:12px">—</span>';
        return `<tr>
          <td style="padding:12px;border-bottom:1px solid var(--border);color:var(--text)">${date}</td>
          <td style="padding:12px;border-bottom:1px solid var(--border);color:var(--muted)">${period}</td>
          <td style="padding:12px;border-bottom:1px solid var(--border);color:var(--muted)">${type}</td>
          <td style="padding:12px;border-bottom:1px solid var(--border);color:var(--text);text-align:right;font-weight:600">${amount}</td>
          <td style="padding:12px;border-bottom:1px solid var(--border)"><span class="inv-status ${statusCls}">${statusLbl}</span></td>
          <td style="padding:12px;border-bottom:1px solid var(--border);text-align:right">${dlBtn}</td>
        </tr>`;
      }).join('');
    } catch(e) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#f87171;padding:24px">Ryšio klaida.</td></tr>';
    }
  }

  async function loadDashboard() {
    if (!currentClient) return;
    setStatus('loading', 'Atnaujinama…');

    const preset = document.getElementById('period-select')?.value || 'last_30d';
    const pub = window._publicMode ? '&public=1' : '';
    const url     = `api-proxy.php?account=${currentClient.account}&preset=${preset}&type=campaigns${pub}`;
    const prevUrl = `api-proxy.php?account=${currentClient.account}&preset=${preset}&prev=1${pub}`;

    try {
      const [res, prevRes] = await Promise.all([fetch(url), fetch(prevUrl)]);
      const json = await res.json();
      prevTotals = await prevRes.json().catch(() => null);

      if (json.error) { setStatus('error', 'Klaida: ' + json.error.message); return; }

      const excl = currentClient.excluded || [];
      allCampaigns = (json.data || [])
        .filter(c => !excl.includes(c.name))
        .filter(c => c.insights?.data?.[0] && parseFloat(c.insights.data[0].spend) > 0)
        .map((c, i) => {
          const ins = c.insights.data[0];
          return {
            id: c.id, name: c.name, status: c.status,
            spend: parseFloat(ins.spend),
            impressions: parseInt(ins.impressions),
            clicks: parseInt(ins.clicks),
            ctr: parseFloat(ins.ctr),
            cpc: parseFloat(ins.cpc),
            reach: parseInt(ins.reach),
            leads: getLeads(ins.actions),
            linkClicks: getLinkClicks(ins.actions),
            videoViews: getVideoViews(ins.actions),
            color: COLORS[i % COLORS.length],
          };
        })
        .sort((a, b) => b.spend - a.spend);

      renderAll();
      const now = new Date();
      setStatus('ok', `Atnaujinta ${now.toLocaleTimeString('lt', { hour:'2-digit', minute:'2-digit' })} · auto-atnaujinimas kas 30 min`);
    } catch (e) {
      setStatus('error', 'Ryšio klaida: ' + e.message);
    }
  }

  function getLeads(actions) {
    if (!actions) return 0;
    const a = actions.find(x => x.action_type === 'lead' || x.action_type === 'onsite_conversion.lead_grouped');
    return a ? parseInt(a.value) : 0;
  }

  function getLinkClicks(actions) {
    if (!actions) return 0;
    const a = actions.find(x => x.action_type === 'link_click');
    return a ? parseInt(a.value) : 0;
  }

  function getVideoViews(actions) {
    if (!actions) return 0;
    const a = actions.find(x => x.action_type === 'video_view');
    return a ? parseInt(a.value) : 0;
  }

  function setStatus(state, msg) {
    const dot = document.getElementById('refresh-dot');
    dot.className = 'refresh-dot' + (state !== 'ok' ? ' ' + state : '');
    document.getElementById('refresh-status').textContent = msg;
  }

  function fmt(n) { return Number(n).toLocaleString('lt'); }
  function eur(n) { return '€' + Number(n).toFixed(2); }

  // ── Dashboard render ───────────────────────────────────────────────────
  function renderCampaigns() {
    selectedIdx = -1;
    renderAll();
  }

  function renderAll() {
    const filtered = statusFilter === 'all'
      ? allCampaigns
      : allCampaigns.filter(c => c.status === statusFilter);
    renderTabs(filtered); renderCards(selectedIdx, filtered); renderCharts(selectedIdx, filtered); renderTable(selectedIdx, filtered);
  }

  function renderTabs(list = allCampaigns) {
    const container = document.getElementById('filter-tabs');
    container.innerHTML = '<div class="filter-tab active" data-idx="-1">Visos kampanijos</div>';
    list.forEach((c, i) => {
      const tab = document.createElement('div');
      tab.className = 'filter-tab';
      tab.dataset.idx = i;
      tab.textContent = c.name;
      container.appendChild(tab);
    });
    container.querySelectorAll('.filter-tab').forEach(tab => {
      tab.addEventListener('click', () => selectCampaign(parseInt(tab.dataset.idx)));
    });
    highlightTab(selectedIdx);
  }

  function highlightTab(idx) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.toggle('active', parseInt(t.dataset.idx) === idx));
  }

  function visible(idx, list = allCampaigns) { return idx === -1 ? list : [list[idx]]; }

  function trendHtml(current, prev) {
    if (!prev || prev === 0) return '';
    const pct = ((current - prev) / prev * 100);
    const cls  = pct >= 0 ? 'trend-up' : 'trend-down';
    const sign = pct >= 0 ? '↑' : '↓';
    return ` <span class="${cls}">${sign} ${Math.abs(pct).toFixed(1)}%</span>`;
  }

  function renderCards(idx, filtered = allCampaigns) {
    const list = visible(idx, filtered);
    const ts = list.reduce((s,c) => s+c.spend, 0);
    const tc = list.reduce((s,c) => s+c.clicks, 0);
    const ti = list.reduce((s,c) => s+c.impressions, 0);
    const tr = list.reduce((s,c) => s+c.reach, 0);
    const tl = list.reduce((s,c) => s+c.leads, 0);
    const ctr = ti > 0 ? (tc/ti*100) : 0;
    const cpc = tc > 0 ? (ts/tc) : 0;
    const cpl = tl > 0 ? (ts/tl).toFixed(2) : '—';
    const ac  = list.filter(c => c.status==='ACTIVE').length;

    const p    = prevTotals;
    const pCtr = p && p.impressions > 0 ? (p.clicks / p.impressions * 100) : 0;

    document.getElementById('val-spend').textContent = eur(ts);
    document.getElementById('sub-spend').innerHTML   = (p ? 'vs prev period' + trendHtml(ts, p.spend)
      : `${list.length} kamp. · ${ac} aktyvios`);

    document.getElementById('val-clicks').textContent = fmt(tc);
    document.getElementById('sub-clicks').innerHTML   = p
      ? 'vs prev period' + trendHtml(tc, p.clicks)
      : `${fmt(ti)} parodymų · ${fmt(tr)} pasiekti`;

    document.getElementById('val-ctr').textContent = ctr.toFixed(2)+'%';
    document.getElementById('sub-ctr').innerHTML   = p
      ? 'vs prev period' + trendHtml(ctr, pCtr)
      : `Vid. CPC €${cpc.toFixed(3)}`;

    document.getElementById('val-leads').textContent = tl || '—';
    document.getElementById('sub-leads').innerHTML   = p && p.leads > 0
      ? 'vs prev period' + trendHtml(tl, p.leads)
      : (tl ? `CPL €${cpl}` : 'Nėra konversijų');
  }

  function renderCharts(idx, filtered = allCampaigns) {
    const list = visible(idx, filtered);
    if (spendChart) spendChart.destroy();
    if (convChart)  convChart.destroy();

    const spendCtx = document.getElementById('spendChart');
    const spendGrad = spendCtx.getContext('2d').createLinearGradient(0, 0, 0, 220);
    spendGrad.addColorStop(0, 'rgba(232,114,10,0.85)');
    spendGrad.addColorStop(1, 'rgba(232,114,10,0.25)');

    spendChart = new Chart(spendCtx, {
      type:'bar',
      data:{ labels:list.map(c=>c.name.length>18?c.name.slice(0,18)+'…':c.name), datasets:[{
        data:list.map(c=>c.spend),
        backgroundColor: list.length === 1 ? spendGrad : list.map(c=>c.color.bg),
        borderColor: list.length === 1 ? '#E8720A' : list.map(c=>c.color.border),
        borderWidth:1.5, borderRadius:8, borderSkipped:false
      }]},
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#1a1a1a', borderColor:'#333', borderWidth:1, titleColor:'#aaa', bodyColor:'#fff', callbacks:{ label:ctx=>` €${ctx.parsed.y.toFixed(2)}` }}},
        scales:{ x:{grid:{color:'rgba(255,255,255,0.03)'}, ticks:{color:'#555',font:{size:11},maxRotation:20}}, y:{grid:{color:'rgba(255,255,255,0.03)'}, ticks:{color:'#555',font:{size:11},callback:v=>'€'+v}, beginAtZero:true, border:{display:false}} }
      }
    });

    convChart = new Chart(document.getElementById('convChart'), {
      type:'bar',
      data:{ labels:list.map(c=>c.name.length>18?c.name.slice(0,18)+'…':c.name), datasets:[
        { label:'Paspaudimai', data:list.map(c=>c.clicks), backgroundColor:'rgba(96,165,250,0.7)', borderColor:'rgba(96,165,250,1)', borderWidth:1.5, borderRadius:8, borderSkipped:false },
        { label:'Potenc. klientai', data:list.map(c=>c.leads), backgroundColor:'rgba(52,211,153,0.7)', borderColor:'rgba(52,211,153,1)', borderWidth:1.5, borderRadius:8, borderSkipped:false, yAxisID:'y2' }
      ]},
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:true, labels:{ color:'#666', font:{size:11,family:'Inter'}, boxWidth:10, boxHeight:10, borderRadius:3, padding:16, usePointStyle:true }},
          tooltip:{ backgroundColor:'#1a1a1a', borderColor:'#333', borderWidth:1, titleColor:'#aaa', bodyColor:'#fff' }},
        scales:{
          x:{grid:{color:'rgba(255,255,255,0.03)'}, ticks:{color:'#555',font:{size:11},maxRotation:20}, border:{display:false}},
          y:{grid:{color:'rgba(255,255,255,0.03)'}, ticks:{color:'#555',font:{size:11}}, beginAtZero:true, border:{display:false}},
          y2:{position:'right', grid:{display:false}, ticks:{color:'#34d399',font:{size:11}}, beginAtZero:true, border:{display:false}}
        }
      }
    });
  }

  function renderTable(idx, filtered = allCampaigns) {
    const tbody = document.getElementById('campaign-tbody');
    const maxSpend = Math.max(...filtered.map(c=>c.spend));
    const list = idx === -1 ? filtered : [filtered[idx]];

    tbody.innerHTML = list.map((c, ri) => {
      const bp = (c.spend/maxSpend*100).toFixed(1);
      const sel = selectedIdx===ri ? ' selected' : '';
      const active = c.status==='ACTIVE';
      return `<tr data-idx="${ri}" class="${sel}">
        <td><div class="campaign-name">${c.name}</div></td>
        <td><span class="status-badge ${active?'active':'paused'}">${active?'Aktyvi':'Pristabdyta'}</span></td>
        <td class="spend-bar-cell">
          <div class="spend-bar-wrap">
            <div class="spend-bar-track"><div class="spend-bar-fill" style="width:${bp}%"></div></div>
            <span class="spend-val">${eur(c.spend)}</span>
          </div>
        </td>
        <td class="num">${fmt(c.impressions)}</td>
        <td class="num">${fmt(c.reach)}</td>
        <td class="num">${fmt(c.clicks)}</td>
        <td class="num">${c.ctr.toFixed(2)}%</td>
        <td class="num">€${c.cpc.toFixed(3)}</td>
        <td><div class="conv-pills">
          ${c.leads      ? `<span class="conv-pill">Potenc. klientai <strong>${c.leads}</strong></span>` : ''}
          ${c.linkClicks ? `<span class="conv-pill">Nuorodos paspaud. <strong>${fmt(c.linkClicks)}</strong></span>` : ''}
          ${c.videoViews ? `<span class="conv-pill">Video peržiūros <strong>${fmt(c.videoViews)}</strong></span>` : ''}
        </div></td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('tr').forEach(row => {
      row.addEventListener('click', () => {
        const ri = parseInt(row.dataset.idx);
        selectCampaign(selectedIdx===ri ? -1 : ri);
      });
    });
  }

  function selectCampaign(idx) {
    selectedIdx = idx;
    highlightTab(idx);
    renderCards(idx);
    renderCharts(idx);
    renderTable(idx);
  }

  // ── Modal ──────────────────────────────────────────────────────────────
  let editingId = null;

  function openAddModal() {
    editingId = null;
    document.getElementById('modal-title').textContent = 'Naujas klientas';
    document.getElementById('f-name').value     = '';
    document.getElementById('f-account').value  = '';
    document.getElementById('f-token').value    = '';
    document.getElementById('f-email').value    = '';
    document.getElementById('f-excluded').value = '';
    document.getElementById('modal-delete-btn').style.display = 'none';
    document.getElementById('pin-reset-group').style.display = 'none';
    document.getElementById('pin-reset-msg').textContent = '';
    document.getElementById('modal-overlay').classList.add('open');
  }

  async function openEditModal(id) {
    const r = await fetch('clients-list.php', { credentials: 'same-origin' });
    const clients = await r.json();
    const client = clients.find(c => c.id === id);
    if (!client) return;
    editingId = id;
    document.getElementById('modal-title').textContent = 'Redaguoti klientą';
    document.getElementById('f-name').value     = client.name;
    document.getElementById('f-account').value  = client.account;
    document.getElementById('f-token').value    = '';
    document.getElementById('f-email').value    = client.email || '';
    document.getElementById('f-excluded').value = (client.excluded || []).join(', ');
    document.getElementById('modal-delete-btn').style.display = 'inline-flex';
    document.getElementById('pin-reset-group').style.display = 'block';
    document.getElementById('pin-reset-msg').textContent = '';
    document.getElementById('modal-overlay').classList.add('open');
  }

  function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
  }

  async function saveClient() {
    const name     = document.getElementById('f-name').value.trim();
    const account  = document.getElementById('f-account').value.trim().replace('act_', '');
    const token    = document.getElementById('f-token').value.trim();
    const email    = document.getElementById('f-email').value.trim();
    const excRaw   = document.getElementById('f-excluded').value.trim();
    const excluded = excRaw ? excRaw.split(',').map(s => s.trim()).filter(Boolean) : [];

    if (!name || !account) { alert('Vardas ir paskyra būtini.'); return; }
    if (!editingId && !token) { alert('Access Token būtinas naujam klientui.'); return; }

    const saveBtn = document.querySelector('.edit-modal-actions .btn-primary');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saugoma…';

    const res  = await fetch('save-client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: editingId ? 'update' : 'add', id: editingId, name, account, token, email, excluded }),
      credentials: 'same-origin'
    });
    const data = await res.json();

    saveBtn.disabled = false;
    saveBtn.textContent = 'Išsaugoti';

    if (!data.ok) { alert(data.error || 'Klaida.'); return; }

    closeModal();
    renderHub();

    if (!editingId && data.pin) {
      const msg = data.emailSent
        ? `✅ Klientas pridėtas!\n\nPIN kodas: ${data.pin}\nEl. laiškas išsiųstas į: ${email}`
        : `✅ Klientas pridėtas!\n\nPIN kodas: ${data.pin}\n⚠️ El. laiškas neišsiųstas — patikrinkite el. paštą.`;
      alert(msg);
    }
  }

  function copyLink(id) {
    const url = `${location.origin}${location.pathname}?client=${id}`;
    navigator.clipboard.writeText(url).then(() => {
      alert('Nuoroda nukopijuota:\n' + url);
    });
  }

  async function confirmDelete(id) {
    if (!confirm('Ar tikrai norite ištrinti šį klientą?')) return;
    await fetch('save-client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id }),
      credentials: 'same-origin'
    });
    renderHub();
  }

  function deleteClient() {
    if (!editingId) return;
    closeModal();
    confirmDelete(editingId);
  }

  // ── Password change ───────────────────────────────────────────────────
  function openPwModal() {
    document.getElementById('pw-current').value = '';
    document.getElementById('pw-new').value     = '';
    document.getElementById('pw-confirm').value = '';
    document.getElementById('pw-error').style.display = 'none';
    document.getElementById('pw-modal-overlay').classList.add('open');
    document.getElementById('pw-current').focus();
  }

  function closePwModal() {
    document.getElementById('pw-modal-overlay').classList.remove('open');
  }

  async function changePassword() {
    const errEl   = document.getElementById('pw-error');
    const current = document.getElementById('pw-current').value;
    const newPass = document.getElementById('pw-new').value;
    const confirm = document.getElementById('pw-confirm').value;

    errEl.style.display = 'none';

    if (!current || !newPass || !confirm) {
      errEl.textContent = 'Užpildykite visus laukus.';
      errEl.style.display = 'block';
      return;
    }
    if (newPass.length < 8) {
      errEl.textContent = 'Naujas slaptažodis turi būti bent 8 simbolių.';
      errEl.style.display = 'block';
      return;
    }
    if (newPass !== confirm) {
      errEl.textContent = 'Slaptažodžiai nesutampa.';
      errEl.style.display = 'block';
      return;
    }

    const btn = document.querySelector('#pw-modal-overlay .btn-primary');
    btn.disabled = true;
    btn.textContent = '...';

    try {
      const res  = await fetch('change-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current, new: newPass, confirm }),
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (json.ok) {
        closePwModal();
        alert('✅ Slaptažodis pakeistas sėkmingai!');
      } else {
        errEl.textContent = json.error || 'Klaida.';
        errEl.style.display = 'block';
      }
    } catch(e) {
      errEl.textContent = 'Ryšio klaida.';
      errEl.style.display = 'block';
    }

    btn.disabled = false;
    btn.textContent = 'Išsaugoti';
  }

  // ── PIN reset ──────────────────────────────────────────────────────────
  async function resetPin() {
    const msgEl = document.getElementById('pin-reset-msg');
    msgEl.style.color = 'var(--muted)';
    msgEl.textContent = 'Generuojama…';

    try {
      const res  = await fetch('reset-pin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ clientId: editingId }),
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (json.ok) {
        if (json.emailSent) {
          msgEl.style.color = 'var(--green)';
          msgEl.textContent = `✅ Naujas PIN: ${json.pin} — išsiųstas į ${json.email}`;
        } else if (json.email) {
          msgEl.style.color = 'var(--yellow)';
          msgEl.textContent = `PIN atnaujintas: ${json.pin} — ⚠️ laiškas neišsiųstas`;
        } else {
          msgEl.style.color = 'var(--text)';
          msgEl.textContent = `Naujas PIN: ${json.pin} (klientas neturi el. pašto)`;
        }
      } else {
        msgEl.style.color = 'var(--red)';
        msgEl.textContent = json.error || 'Klaida.';
      }
    } catch(e) {
      msgEl.style.color = 'var(--red)';
      msgEl.textContent = 'Ryšio klaida.';
    }
  }

  document.getElementById('modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
  document.getElementById('inv-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeInvModal();
  });
  document.getElementById('pw-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closePwModal();
  });

  // ── Invoices ──────────────────────────────────────────────────────────────
  let _invClients = [], _invList = [];

  function showInvoices() {
    document.getElementById('hub-view').style.display = 'none';
    document.getElementById('dashboard-view').style.display = 'none';
    document.getElementById('invoices-view').style.display = 'block';
    document.getElementById('nav-back').classList.add('visible');
    document.getElementById('nav-client-name').textContent = 'Sąskaitos';
    document.getElementById('nav-client-name').classList.add('visible');
    document.getElementById('nav-add-btn').style.display = 'none';
    document.getElementById('nav-refresh-btn').style.display = 'none';
    document.getElementById('nav-pdf-btn').style.display = 'none';
    currentClient = null;
    loadInvoices();
  }

  async function loadInvoices() {
    try {
      const r  = await fetch('invoices.php?action=list', { credentials: 'same-origin' });
      _invList = await r.json();
      renderInvStats(_invList);
      renderInvTable(_invList);
    } catch(e) {
      document.getElementById('inv-tbody').innerHTML =
        '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">Klaida kraunant sąskaitas.</td></tr>';
    }
  }

  function renderInvStats(list) {
    const thisMonth = new Date().toISOString().slice(0, 7);
    const month     = list.filter(i => i.period === thisMonth);
    const mTotal    = month.reduce((s, i) => s + parseFloat(i.total || 0), 0);
    const sent      = list.filter(i => i.status === 'sent').length;
    const draft     = list.filter(i => i.status === 'draft').length;
    document.getElementById('inv-sum-month').textContent     = '€' + mTotal.toFixed(0);
    document.getElementById('inv-sum-month-sub').textContent = month.length + ' sąskaita (-os)';
    document.getElementById('inv-sum-sent').textContent      = sent;
    document.getElementById('inv-sum-draft').textContent     = draft;
  }

  function renderInvTable(list) {
    const tbody = document.getElementById('inv-tbody');
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:48px">Sąskaitų dar nėra. Sukurkite pirmąją!</td></tr>';
      return;
    }
    tbody.innerHTML = list.map(inv => {
      const date  = (inv.sent_at || inv.created_at || '').slice(0, 10) || '—';
      const sc    = inv.status === 'sent' ? 'sent' : 'draft';
      const sl    = inv.status === 'sent' ? 'Išsiųsta' : 'Juodraštis';
      const resend = inv.status === 'sent'
        ? `<button class="inv-action-btn" title="Siųsti vėl" onclick="resendInvoice('${inv.id}')">↻</button>`
        : `<button class="inv-action-btn" title="Išsiųsti" onclick="resendInvoice('${inv.id}')">✉</button>`;
      return `<tr>
        <td style="font-family:monospace;font-size:12px;color:var(--accent)">${inv.id}</td>
        <td style="font-weight:600">${inv.client_name}</td>
        <td style="color:var(--muted)">${inv.period}</td>
        <td class="num" style="font-weight:700;color:var(--green)">€${parseFloat(inv.total).toFixed(2)}</td>
        <td><span class="inv-status ${sc}">${sl}</span></td>
        <td style="color:var(--muted);font-size:12px">${date}</td>
        <td style="text-align:right;white-space:nowrap">
          <button class="inv-action-btn" title="Peržiūrėti" onclick="previewExistingInvoice('${inv.id}')">👁</button>
          ${resend}
          <button class="inv-action-btn del" title="Ištrinti" onclick="deleteInvoice('${inv.id}')">✕</button>
        </td>
      </tr>`;
    }).join('');
  }

  async function openNewInvModal() {
    try {
      const r  = await fetch('clients-list.php', { credentials: 'same-origin' });
      _invClients = await r.json();
    } catch(e) { _invClients = []; }

    const sel = document.getElementById('inv-f-client');
    sel.innerHTML = '<option value="">— Pasirinkite klientą —</option>';
    _invClients.forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${c.name}</option>`;
    });

    const now = new Date();
    const ym  = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    document.getElementById('inv-f-period').value  = ym;
    document.getElementById('inv-f-number').value  = '';
    document.getElementById('inv-svc-rows').innerHTML = '';
    updateInvTotal();
    document.getElementById('inv-modal-overlay').classList.add('open');
  }

  function closeInvModal() {
    document.getElementById('inv-modal-overlay').classList.remove('open');
  }

  async function onInvClientChange() {
    updateInvNumber();
    const id = document.getElementById('inv-f-client').value;
    if (!id) return;
    try {
      const r    = await fetch('invoices.php?action=client&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      const data = await r.json();
      document.getElementById('inv-svc-rows').innerHTML = '';
      const svcs = data.services || [];
      if (svcs.length) svcs.forEach(s => addInvSvcRow(s));
      else addInvSvcRow();
    } catch(e) {
      document.getElementById('inv-svc-rows').innerHTML = '';
      addInvSvcRow();
    }
    updateInvTotal();
  }

  async function updateInvNumber() {
    const id     = document.getElementById('inv-f-client').value;
    const period = (document.getElementById('inv-f-period').value || '').replace('-', '');
    if (!id || !period) return;
    try {
      const r    = await fetch('invoices.php?action=client&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      const data = await r.json();
      document.getElementById('inv-f-number').value = (data.prefix || 'INV-' + id.toUpperCase()) + '-' + period;
    } catch(e) {}
  }

  function addInvSvcRow(svc = {}) {
    const container = document.getElementById('inv-svc-rows');
    const row = document.createElement('div');
    row.className = 'svc-row';
    row.innerHTML = `
      <input type="text"   placeholder="Paslaugos pavadinimas"   value="${svc.name        || ''}" oninput="updateInvTotal()">
      <input type="text"   placeholder="Aprašymas (neprivaloma)" value="${svc.description || ''}">
      <input type="number" placeholder="1"    value="${svc.qty   || 1}"    min="1"    class="r" oninput="updateInvTotal()">
      <input type="number" placeholder="0.00" value="${svc.price || ''}"   min="0" step="0.01" class="r" oninput="updateInvTotal()">
      <button class="svc-del" onclick="this.closest('.svc-row').remove();updateInvTotal()" title="Ištrinti">×</button>
    `;
    container.appendChild(row);
  }

  function getInvServices() {
    return Array.from(document.querySelectorAll('#inv-svc-rows .svc-row')).map(row => {
      const inp = row.querySelectorAll('input');
      return { name: inp[0].value.trim(), description: inp[1].value.trim(), qty: parseInt(inp[2].value) || 1, price: parseFloat(inp[3].value) || 0 };
    });
  }

  function updateInvTotal() {
    const total = getInvServices().reduce((s, sv) => s + sv.qty * sv.price, 0);
    document.getElementById('inv-f-total').textContent = '€' + total.toFixed(2);
  }

  function getInvFormData() {
    const id     = document.getElementById('inv-f-client').value;
    const client = _invClients.find(c => c.id === id) || {};
    const svcs   = getInvServices();
    return {
      client_id:       id,
      client_name:     client.name    || '',
      client_contact:  client.contact || '',
      client_email:    client.email   || '',
      client_address:  client.address || '',
      invoice_number:  document.getElementById('inv-f-number').value.trim(),
      period:          document.getElementById('inv-f-period').value,
      services:        svcs,
      total:           svcs.reduce((s, sv) => s + sv.qty * sv.price, 0),
    };
  }

  async function previewInvoice() {
    const data = getInvFormData();
    if (!data.invoice_number) { alert('Įveskite sąskaitos numerį.'); return; }
    const res  = await fetch('invoices.php?action=preview', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data), credentials: 'same-origin',
    });
    const html = await res.text();
    const w = window.open('', '_blank');
    w.document.write(html); w.document.close();
  }

  async function previewExistingInvoice(id) {
    const inv = _invList.find(i => i.id === id);
    if (!inv) return;
    const res  = await fetch('invoices.php?action=preview', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(inv), credentials: 'same-origin',
    });
    const html = await res.text();
    const w = window.open('', '_blank');
    w.document.write(html); w.document.close();
  }

  async function saveInvDraft() {
    const data = getInvFormData();
    if (!data.client_id)      { alert('Pasirinkite klientą.'); return; }
    if (!data.invoice_number) { alert('Įveskite sąskaitos numerį.'); return; }
    const btn = document.getElementById('inv-save-btn');
    btn.disabled = true; btn.textContent = 'Saugoma…';
    try {
      const res  = await fetch('invoices.php?action=save', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data), credentials: 'same-origin',
      });
      const json = await res.json();
      if (json.ok) { closeInvModal(); loadInvoices(); }
      else alert(json.error || 'Klaida.');
    } finally { btn.disabled = false; btn.textContent = 'Išsaugoti'; }
  }

  async function sendInvNow() {
    const data = getInvFormData();
    if (!data.client_id)      { alert('Pasirinkite klientą.'); return; }
    if (!data.invoice_number) { alert('Įveskite sąskaitos numerį.'); return; }
    if (!data.client_email)   { alert('Šis klientas neturi el. pašto adreso.\nPridėkite jį kliento kortelėje.'); return; }
    const btn = document.getElementById('inv-send-btn');
    btn.disabled = true; btn.textContent = 'Siunčiama…';
    try {
      const res  = await fetch('invoices.php?action=send', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data), credentials: 'same-origin',
      });
      const json = await res.json();
      if (json.ok) { closeInvModal(); loadInvoices(); alert('✅ Sąskaita išsiųsta į ' + data.client_email); }
      else alert('Klaida: ' + (json.error || json.result || 'Nežinoma klaida'));
    } finally { btn.disabled = false; btn.textContent = '✉ Išsiųsti'; }
  }

  async function resendInvoice(id) {
    const inv = _invList.find(i => i.id === id);
    if (!inv) return;
    if (!inv.client_email) { alert('Klientas neturi el. pašto adreso.'); return; }
    if (!confirm('Išsiųsti sąskaitą ' + id + ' → ' + inv.client_email + '?')) return;
    const res  = await fetch('invoices.php?action=send', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(inv), credentials: 'same-origin',
    });
    const json = await res.json();
    if (json.ok) { loadInvoices(); alert('✅ Sąskaita išsiųsta!'); }
    else alert('Klaida: ' + (json.result || 'Nežinoma'));
  }

  async function deleteInvoice(id) {
    if (!confirm('Ištrinti sąskaitą ' + id + '?')) return;
    await fetch('invoices.php?action=delete', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }), credentials: 'same-origin',
    });
    loadInvoices();
  }

  // Auto-refresh dashboard every 30 min
  setInterval(() => { if (currentClient) loadDashboard(); }, 30 * 60 * 1000);

  // ── Init ──────────────────────────────────────────────────────────────
  (async function init() {
    const clientId = new URLSearchParams(window.location.search).get('client');
    if (clientId) {
      // Public client PIN flow
      try {
        const r = await fetch('clients-list.php?id=' + encodeURIComponent(clientId), { credentials: 'same-origin' });
        const client = await r.json();
        if (client) {
          document.getElementById('pin-client-name').textContent = client.name;
          document.getElementById('pin-view').style.display = 'flex';
          document.getElementById('pin-input').focus();
          return;
        }
      } catch(e) {}
    }

    // Admin flow — PHP already verified session server-side
    document.getElementById('topnav').style.display = 'flex';
    document.getElementById('hub-view').style.display = 'block';
    document.getElementById('nav-reports-btn').style.display  = 'inline-flex';
    document.getElementById('nav-invoices-btn').style.display = 'inline-flex';
    renderHub();
  })();
</script>
</body>
</html>

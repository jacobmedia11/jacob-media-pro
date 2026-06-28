<?php
/**
 * SQLite database layer — replaces clients-config.json and access-log.json.
 * On first run, migrates existing JSON data automatically.
 */

define('DB_PATH', __DIR__ . '/data.sqlite');

function getDb(): PDO {
    static $db = null;
    if ($db) return $db;

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL');   // safe concurrent reads
    $db->exec('PRAGMA foreign_keys=ON');

    $db->exec("CREATE TABLE IF NOT EXISTS clients (
        id          TEXT PRIMARY KEY,
        name        TEXT NOT NULL,
        account     TEXT NOT NULL,
        token       TEXT NOT NULL DEFAULT '',
        email       TEXT NOT NULL DEFAULT '',
        pin         TEXT NOT NULL DEFAULT '',
        excluded    TEXT NOT NULL DEFAULT '[]',
        token_error     TEXT,
        token_error_at  TEXT,
        meta        TEXT NOT NULL DEFAULT '{}',
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS access_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id   TEXT NOT NULL DEFAULT '',
        client_name TEXT NOT NULL DEFAULT '',
        ip          TEXT NOT NULL DEFAULT '',
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        action      TEXT NOT NULL,
        detail      TEXT NOT NULL DEFAULT '',
        ip          TEXT NOT NULL DEFAULT '',
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    _dbMigrateJson($db);

    return $db;
}

function _dbMigrateJson(PDO $db): void {
    $count = (int) $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    if ($count > 0) return;

    $jsonFile = __DIR__ . '/clients-config.json';
    if (!file_exists($jsonFile)) return;

    $cfg = json_decode(file_get_contents($jsonFile), true);
    if (empty($cfg['clients'])) return;

    $stmt = $db->prepare("INSERT OR IGNORE INTO clients
        (id, name, account, token, email, pin, excluded, token_error, token_error_at, meta)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $db->beginTransaction();
    foreach ($cfg['clients'] as $c) {
        $meta = [];
        foreach (['services', 'contact', 'address', 'auto_send', 'invoice_number_prefix'] as $k) {
            if (isset($c[$k])) $meta[$k] = $c[$k];
        }
        $stmt->execute([
            $c['id'],
            $c['name'],
            $c['account'],
            $c['token'] ?? '',
            $c['email'] ?? '',
            $c['pin'] ?? '',
            json_encode($c['excluded'] ?? []),
            $c['token_error'] ?? null,
            $c['token_error_at'] ?? null,
            json_encode($meta),
        ]);
    }
    $db->commit();

    $logFile = __DIR__ . '/access-log.json';
    if (!file_exists($logFile)) return;

    $logs = json_decode(file_get_contents($logFile), true) ?? [];
    if (empty($logs)) return;

    $logStmt = $db->prepare("INSERT INTO access_log (client_id, client_name, ip, created_at) VALUES (?, ?, ?, ?)");
    $db->beginTransaction();
    foreach (array_slice($logs, 0, 200) as $log) {
        $logStmt->execute([
            $log['id']     ?? '',
            $log['client'] ?? '',
            $log['ip']     ?? 'unknown',
            $log['time']   ?? date('Y-m-d H:i:s'),
        ]);
    }
    $db->commit();
}

// ── Client helpers ─────────────────────────────────────────────────────────

function dbGetClients(): array {
    $rows = getDb()->query("SELECT * FROM clients ORDER BY name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
    return array_map('_dbDecodeClient', $rows);
}

function dbGetClient(string $id): ?array {
    $stmt = getDb()->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? _dbDecodeClient($row) : null;
}

function dbGetClientByAccount(string $account): ?array {
    $stmt = getDb()->prepare("SELECT * FROM clients WHERE account = ?");
    $stmt->execute([$account]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? _dbDecodeClient($row) : null;
}

function _dbDecodeClient(array $row): array {
    $row['excluded'] = json_decode($row['excluded'] ?? '[]', true) ?: [];
    $meta = json_decode($row['meta'] ?? '{}', true) ?: [];
    foreach ($meta as $k => $v) {
        if (!isset($row[$k])) $row[$k] = $v;
    }
    return $row;
}

function dbAddClient(array $c): void {
    $meta = [];
    foreach (['services', 'contact', 'address', 'auto_send', 'invoice_number_prefix'] as $k) {
        if (isset($c[$k])) $meta[$k] = $c[$k];
    }
    getDb()->prepare("INSERT INTO clients (id, name, account, token, email, pin, excluded, meta)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
        $c['id'],
        $c['name'],
        $c['account'],
        $c['token'] ?? '',
        $c['email'] ?? '',
        $c['pin'] ?? '',
        json_encode($c['excluded'] ?? []),
        json_encode($meta),
    ]);
}

function dbUpdateClient(string $id, array $fields): void {
    if (empty($fields)) return;
    $sets = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        $sets[] = "$k = ?";
        $vals[] = $v;
    }
    $vals[] = $id;
    getDb()->prepare("UPDATE clients SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
}

function dbDeleteClient(string $id): void {
    getDb()->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
}

// ── Access log ─────────────────────────────────────────────────────────────

function dbLogAccess(string $clientId, string $clientName, string $ip): void {
    $db = getDb();
    $db->prepare("INSERT INTO access_log (client_id, client_name, ip) VALUES (?, ?, ?)")
       ->execute([$clientId, $clientName, $ip]);
    // Keep only the latest 200 entries
    $db->exec("DELETE FROM access_log WHERE id NOT IN (SELECT id FROM access_log ORDER BY id DESC LIMIT 200)");
}

function dbGetAccessLog(int $limit = 20): array {
    return getDb()
        ->query("SELECT client_id AS id, client_name AS client, ip, created_at AS time
                 FROM access_log ORDER BY id DESC LIMIT $limit")
        ->fetchAll(PDO::FETCH_ASSOC);
}

// ── Audit log ──────────────────────────────────────────────────────────────

function auditLog(string $action, string $detail = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    getDb()->prepare("INSERT INTO audit_log (action, detail, ip) VALUES (?, ?, ?)")
           ->execute([$action, $detail, $ip]);
}

function dbGetAuditLog(int $limit = 50): array {
    return getDb()
        ->query("SELECT * FROM audit_log ORDER BY id DESC LIMIT $limit")
        ->fetchAll(PDO::FETCH_ASSOC);
}

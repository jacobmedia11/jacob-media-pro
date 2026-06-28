<?php
/**
 * Session validation + idle timeout for admin endpoints.
 * Call requireAdmin() at the top of every JSON API that needs admin auth.
 * For HTML pages (hub.php), call checkAdminPage() which redirects instead.
 */

const SESSION_TIMEOUT_SECS = 3600; // 1 hour idle

function requireAdmin(): void {
    if (empty($_SESSION['authed'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECS) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Sesija baigėsi. Prisijunkite iš naujo.', 'session_expired' => true]);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function checkAdminPage(): void {
    if (empty($_SESSION['authed'])) {
        header('Location: login.html');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECS) {
        session_unset();
        session_destroy();
        header('Location: login.html');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

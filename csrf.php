<?php
/**
 * CSRF token helpers.
 * generateCsrfToken() — called once per page to produce a token embedded in JS.
 * verifyCsrf()        — called at the top of every admin POST handler.
 */

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token']       ?? '';
    if (!$expected || !hash_equals($expected, $supplied)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

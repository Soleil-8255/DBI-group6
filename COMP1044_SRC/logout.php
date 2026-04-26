<?php
declare(strict_types=1);

/**
 * Secure session termination and redirect to public login.
 */

require_once __DIR__ . '/includes/functions.php';

ensure_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

session_destroy();

header('Location: ' . app_route('index.php'));
exit;

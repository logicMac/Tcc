<?php
/**
 * TCC RRS – Logout
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

if (isLoggedIn()) {
    logActivity('LOGOUT', 'Auth', 'User logged out.');
}

// Destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

redirect(BASE_URL . '/auth/login.php?logged_out=1');

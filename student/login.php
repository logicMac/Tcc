<?php
/**
 * TCC RRS – Student Login (redirect)
 *
 * The system now has a single unified sign-in page for all roles.
 * This file only exists so old links keep working — it forwards to
 * /auth/login.php, preserving the ?return= deep-link when present.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if (isStudentLoggedIn()) {
    redirect(BASE_URL . '/student/account.php');
}
redirectIfLoggedIn();

$return = get('return');
$qs = ($return && str_starts_with($return, '/') && !str_starts_with($return, '//'))
    ? '?return=' . urlencode($return)
    : '';
redirect(BASE_URL . '/auth/login.php' . $qs);

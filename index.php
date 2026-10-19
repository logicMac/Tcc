<?php
/**
 * TCC RRS – Root redirect
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

if (isLoggedIn()) {
    $role = currentRole();
    redirect(BASE_URL . '/' . $role . '/dashboard.php');
} else {
    redirect(BASE_URL . '/student/index.php');
}

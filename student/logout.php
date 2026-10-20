<?php
/**
 * TCC RRS – Student Logout (clears only the student session keys)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();

unset($_SESSION['student_id'], $_SESSION['student']);

flash('success', 'You have been signed out of your student account.');
redirect(BASE_URL . '/auth/login.php');

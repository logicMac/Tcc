<?php
/**
 * TCC Registration & Records System
 * Core Configuration File
 */

// ── Error Reporting (set to 0 in production) ──────────────────────────────────
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',       'TCC Registration & Records System');
define('APP_SHORT_NAME', 'TCC RRS');
define('APP_VERSION',    '1.0.0');

// ── Base URL (auto-detect) ─────────────────────────────────────────────────────
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script    = $_SERVER['SCRIPT_NAME'] ?? '';
// Walk up from the current file to find the app root
$appRoot   = str_replace('\\', '/', dirname(dirname(__FILE__)));
$docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$basePath  = trim(str_replace($docRoot, '', $appRoot), '/');
define('BASE_URL',  $protocol . '://' . $host . '/' . $basePath);
define('BASE_PATH', $appRoot);

// ── Database ───────────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'tcc_rrs');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ────────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'tcc_rrs_sess');
define('SESSION_LIFETIME', 7200);   // 2 hours
define('SESSION_PATH',     '/');

// ── Password hashing ──────────────────────────────────────────────────────────
define('BCRYPT_COST', 12);

// ── Tracking code prefixes ────────────────────────────────────────────────────
define('TRACKING_REG_PREFIX', 'TCC-REG');
define('TRACKING_DOC_PREFIX', 'TCC-DOC');
define('TRACKING_VRF_PREFIX', 'TCC-VRF');   // issued-document verification codes

// ── Upload paths ──────────────────────────────────────────────────────────────
define('UPLOAD_DIR',    BASE_PATH . '/uploads');
define('UPLOAD_URL',    BASE_URL  . '/uploads');
define('QRCODE_DIR',    UPLOAD_DIR . '/qrcodes');
define('QRCODE_URL',    UPLOAD_URL . '/qrcodes');
define('DOCS_DIR',      UPLOAD_DIR . '/documents');
define('DOCS_URL',      UPLOAD_URL . '/documents');

// ── Roles ─────────────────────────────────────────────────────────────────────
define('ROLE_REGISTRAR', 'registrar');
define('ROLE_STAFF',     'staff');

// ── Status definitions ────────────────────────────────────────────────────────
define('REG_STATUSES', [
    'Pending'          => ['label' => 'Pending',          'color' => 'secondary', 'icon' => 'clock'],
    'Under Review'     => ['label' => 'Under Review',     'color' => 'info',      'icon' => 'eye'],
    'Processing'       => ['label' => 'Processing',       'color' => 'primary',   'icon' => 'gear'],
    'Approved'         => ['label' => 'Approved',         'color' => 'success',   'icon' => 'check-circle'],
    'Needs Correction' => ['label' => 'Needs Correction', 'color' => 'warning',   'icon' => 'exclamation-triangle'],
    'Declined'         => ['label' => 'Declined',         'color' => 'danger',    'icon' => 'x-circle'],
]);

define('DOC_STATUSES', [
    'Pending'           => ['label' => 'Pending',           'color' => 'secondary', 'icon' => 'clock'],
    'Under Review'      => ['label' => 'Under Review',      'color' => 'info',      'icon' => 'eye'],
    'Processing'        => ['label' => 'Processing',        'color' => 'primary',   'icon' => 'gear'],
    'Approved'          => ['label' => 'Approved',          'color' => 'success',   'icon' => 'check-circle'],
    'Ready for Release' => ['label' => 'Ready for Release', 'color' => 'teal',      'icon' => 'bag-check'],
    'Released'          => ['label' => 'Released',          'color' => 'dark',      'icon' => 'box-seam'],
    'Needs Correction'  => ['label' => 'Needs Correction',  'color' => 'warning',   'icon' => 'exclamation-triangle'],
    'Declined'          => ['label' => 'Declined',          'color' => 'danger',    'icon' => 'x-circle'],
]);

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Security headers ──────────────────────────────────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

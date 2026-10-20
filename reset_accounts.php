<?php
/**
 * TCC RRS – Account Reset Utility (LOCALHOST ONLY)
 *
 * Run this in your browser once if you cannot log in:
 *     http://localhost/tcc_rrs/reset_accounts.php
 *
 * It resets every seed account back to the temporary password
 * "password123" and clears the forced password-change flag, so
 * login goes straight to the dashboard. It also prints a live
 * password_verify() check per account so you can see it worked.
 *
 * Refuses to run from a non-localhost address.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// ── Localhost guard ──────────────────────────────────────────────────────────
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden — this tool only runs from localhost.');
}

$TEMP_PASSWORD = 'password123';
$hash = password_hash($TEMP_PASSWORD, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
$results = [];

// ── Staff accounts (users table) ─────────────────────────────────────────────
$staffAccounts = [
    ['email' => 'registrar@tcc.edu.ph', 'employee_id' => 'TCC-REG-001', 'full_name' => 'System Registrar', 'role' => 'registrar'],
    ['email' => 'staff@tcc.edu.ph',     'employee_id' => 'TCC-STF-001', 'full_name' => 'Sample Staff',     'role' => 'staff'],
];
foreach ($staffAccounts as $a) {
    $existing = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$a['email']]);
    if ($existing) {
        db()->execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0, is_active = 1 WHERE email = ?',
            [$hash, $a['email']]
        );
        $action = 'password reset';
    } else {
        db()->insert(
            'INSERT INTO users (employee_id, full_name, email, password_hash, role, is_active, must_change_password)
             VALUES (?, ?, ?, ?, ?, 1, 0)',
            [$a['employee_id'], $a['full_name'], $a['email'], $hash, $a['role']]
        );
        $action = 'created';
    }
    $row = db()->fetchOne('SELECT password_hash FROM users WHERE email = ?', [$a['email']]);
    $results[] = [
        'portal' => 'Staff Login', 'role' => ucfirst($a['role']),
        'email' => $a['email'], 'action' => $action,
        'verified' => password_verify($TEMP_PASSWORD, $row['password_hash']),
    ];
}

// ── Student account ──────────────────────────────────────────────────────────
$studentEmail = 'student@tcc.edu.ph';
if (db()->fetchOne("SHOW TABLES LIKE 'students'")) {
    $existing = db()->fetchOne('SELECT id FROM students WHERE email = ?', [$studentEmail]);
    if ($existing) {
        db()->execute(
            'UPDATE students SET password_hash = ?, is_active = 1 WHERE email = ?',
            [$hash, $studentEmail]
        );
        $action = 'password reset';
    } else {
        db()->insert(
            "INSERT INTO students (student_no, first_name, middle_name, last_name, email, contact_number, password_hash, is_active)
             VALUES ('TCC-STD-001', 'Sample', 'D.', 'Student', ?, '09123456789', ?, 1)",
            [$studentEmail, $hash]
        );
        $action = 'created';
    }
    $row = db()->fetchOne('SELECT password_hash FROM students WHERE email = ?', [$studentEmail]);
    $results[] = [
        'portal' => 'Student Login', 'role' => 'Student',
        'email' => $studentEmail, 'action' => $action,
        'verified' => password_verify($TEMP_PASSWORD, $row['password_hash']),
    ];
}

// ── Clear any stale sessions so a fresh login isn't fighting an old one ──────
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$cleared = 0;
foreach (glob($sessionPath . '/sess_*') ?: [] as $f) {
    if (is_file($f) && @unlink($f)) $cleared++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Account Reset – TCC RRS</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer('assets/css/main.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:var(--surface-subtle);">
<div class="content-card" style="max-width:640px;width:100%;">
  <div class="content-card-header d-flex align-items-center gap-2">
    <i class="bi bi-shield-lock-fill" style="color:var(--tcc-gold-500);"></i>
    <span class="fw-bold">Account Reset Utility</span>
  </div>
  <div class="content-card-body">
    <p class="small text-muted">
      All seed accounts have been reset to <code><?= $TEMP_PASSWORD ?></code> and the forced
      password-change flag was cleared — you will land directly on the dashboard.
      <?= $cleared ?> stale session file<?= $cleared === 1 ? '' : 's' ?> cleared.
    </p>
    <table class="table table-sm align-middle">
      <thead>
        <tr><th>Portal</th><th>Role</th><th>Email</th><th>Result</th><th>Verified</th></tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
          <td class="small"><?= e($r['portal']) ?></td>
          <td><span class="badge badge-soft-primary"><?= e($r['role']) ?></span></td>
          <td class="small font-monospace"><?= e($r['email']) ?></td>
          <td class="small"><?= e($r['action']) ?></td>
          <td>
            <?php if ($r['verified']): ?>
            <span class="badge badge-soft-success"><i class="bi bi-check-lg me-1"></i>OK</span>
            <?php else: ?>
            <span class="badge badge-soft-danger">FAIL</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="d-flex gap-2 mt-3">
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary btn-sm">
        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
      </a>
    </div>
    <div class="alert alert-warning small mt-3 mb-0">
      <i class="bi bi-exclamation-triangle me-1"></i>
      Delete or restrict this file before deploying — it resets passwords to a known value.
    </div>
  </div>
</div>
</body>
</html>

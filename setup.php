<?php
/**
 * TCC RRS – One-time Setup Script
 *
 * Run once in the browser after importing database/tcc_rrs.sql.
 * Verifies the environment and (re)generates default password hashes
 * on THIS PHP version.
 *
 * ⚠ DELETE THIS FILE immediately after setup completes.
 */

// ── Localhost only ────────────────────────────────────────────────────────────
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden: setup.php can only be run from localhost.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$steps = [];

function step(string $label, bool $ok, string $detail = ''): void
{
    global $steps;
    $steps[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

// ── 1. PHP version ────────────────────────────────────────────────────────────
step(
    'PHP version ≥ 8.0',
    version_compare(PHP_VERSION, '8.0.0', '>='),
    'Running PHP ' . PHP_VERSION
);

// ── 2. Database connection ────────────────────────────────────────────────────
// Use a raw PDO here: db() terminates the script on connection failure.
$dbOk = false;
try {
    $dsn  = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo  = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbOk = (bool) $pdo->query('SELECT 1');
} catch (Throwable $e) {
    $dbOk = false;
}
step(
    'Database connection',
    $dbOk,
    $dbOk ? 'Connected to ' . DB_NAME . ' @ ' . DB_HOST : 'Check DB_* constants in includes/config.php and make sure MySQL is running.'
);

// ── 3. Required tables ────────────────────────────────────────────────────────
// Core tables must come from database/tcc_rrs.sql.
// v2 tables are created automatically by the migration step below when missing.
$requiredTables = [
    'users', 'programs', 'document_types', 'pre_registrations',
    'document_requests', 'status_history', 'notifications',
    'activity_logs', 'system_settings', 'tracking_sequences',
];
$v2Tables = ['students', 'issued_documents'];
$missingTables = [];
$existing = [];
if ($dbOk) {
    $existing = array_map(
        'strtolower',
        array_column(
            db()->fetchAll(
                "SELECT table_name AS tbl FROM information_schema.tables WHERE table_schema = ?",
                [DB_NAME]
            ),
            'tbl'
        )
    );
    $missingTables = array_diff($requiredTables, $existing);
}
step(
    'Required tables exist',
    $dbOk && empty($missingTables),
    $missingTables
        ? 'Missing: ' . implode(', ', $missingTables) . ' — import database/tcc_rrs.sql via phpMyAdmin.'
        : count($requiredTables) . ' tables found.'
);

// ── 3b. v2 schema (student accounts, archive flags, issued documents) ────────
// Applies database/migration_v2.sql automatically — it's fully idempotent.
$migNote = '';
$migOk   = true;
if ($dbOk && empty($missingTables)) {
    $missingV2 = array_diff($v2Tables, $existing);
    $needsCols = !in_array(
        'student_account_id',
        array_column(db()->fetchAll("SHOW COLUMNS FROM pre_registrations"), 'Field'),
        true
    );

    if ($missingV2 || $needsCols) {
        $migFile = __DIR__ . '/database/migration_v2.sql';
        if (is_file($migFile)) {
            try {
                $pdo->exec(file_get_contents($migFile));
                $migNote = 'migration_v2.sql applied.';
            } catch (Throwable $e) {
                $migOk   = false;
                $migNote = 'Migration failed: ' . $e->getMessage() . ' — run database/migration_v2.sql manually in phpMyAdmin.';
            }
        } else {
            $migOk   = false;
            $migNote = 'database/migration_v2.sql not found — import it manually.';
        }
    } else {
        $migNote = 'v2 schema already present.';
    }
    step('v2 schema (student accounts, archiving, QR-verified documents)', $migOk, $migNote);
}

// ── 4. Default accounts & password hashes ────────────────────────────────────
$defaultUsers = [
    ['employee_id' => 'TCC-REG-001', 'full_name' => 'System Registrar', 'email' => 'registrar@tcc.edu.ph', 'role' => 'registrar'],
    ['employee_id' => 'TCC-STF-001', 'full_name' => 'Sample Staff',     'email' => 'staff@tcc.edu.ph',     'role' => 'staff'],
];
$accountNotes = [];

if ($dbOk && empty($missingTables)) {
    $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    foreach ($defaultUsers as $u) {
        $existing = db()->fetchOne('SELECT * FROM users WHERE email = ?', [$u['email']]);

        if (!$existing) {
            db()->insert(
                "INSERT INTO users (employee_id, full_name, email, password_hash, role, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, 1, 1)",
                [$u['employee_id'], $u['full_name'], $u['email'], $hash, $u['role']]
            );
            $accountNotes[] = "{$u['email']} — created (password123).";
        } elseif ((int) $existing['must_change_password'] === 1) {
            // Still on the temporary password — regenerate the hash for this PHP build.
            db()->execute(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [$hash, $existing['id']]
            );
            $accountNotes[] = "{$u['email']} — password hash regenerated (password123).";
        } else {
            $accountNotes[] = "{$u['email']} — exists with a custom password, left unchanged.";
        }
    }
    // ── Default student account (one per role) ──────────────────────────
    $studentsTable = db()->fetchOne("SHOW TABLES LIKE 'students'");
    if ($studentsTable) {
        $existing = db()->fetchOne('SELECT * FROM students WHERE email = ?', ['student@tcc.edu.ph']);
        if (!$existing) {
            db()->insert(
                "INSERT INTO students (student_no, first_name, middle_name, last_name, email, contact_number, password_hash, is_active)
                 VALUES ('TCC-STD-001', 'Sample', 'D.', 'Student', 'student@tcc.edu.ph', '09123456789', ?, 1)",
                [$hash]
            );
            $accountNotes[] = "student@tcc.edu.ph — created (password123).";
        } else {
            $accountNotes[] = "student@tcc.edu.ph — exists, left unchanged.";
        }
    }
    step('Default accounts ready', true, implode(' ', $accountNotes));
} else {
    step('Default accounts ready', false, 'Skipped — database not ready.');
}

// ── 5. Upload directories ─────────────────────────────────────────────────────
$uploadDirs = [UPLOAD_DIR, QRCODE_DIR, DOCS_DIR];
$uploadNotes = [];
$uploadsOk   = true;
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $uploadsOk    = false;
        $uploadNotes[] = basename($dir) . ': create failed';
        continue;
    }
    $uploadNotes[] = basename($dir) . (is_writable($dir) ? ': writable' : ': NOT writable');
    if (!is_writable($dir)) $uploadsOk = false;
}
step('Upload directories writable', $uploadsOk, implode(' · ', $uploadNotes));

$allOk = !in_array(false, array_column($steps, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>TCC RRS – Setup</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
</head>
<body class="d-flex align-items-center" style="min-height:100vh;background:var(--bg-app);">
<div class="container py-5" style="max-width:640px;">
  <div class="text-center mb-3">
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal" class="brand-logo" style="width:56px;height:56px;">
  </div>
  <div class="content-card">
    <div class="content-card-header">
      <h5><i class="bi bi-tools me-2"></i>TCC RRS – Setup Checklist</h5>
    </div>
    <div class="content-card-body">
      <ul class="list-group list-group-flush mb-4">
        <?php foreach ($steps as $s): ?>
        <li class="list-group-item d-flex align-items-start gap-3 px-0">
          <i class="bi <?= $s['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> fs-5 mt-1"></i>
          <div>
            <div class="fw-semibold"><?= e($s['label']) ?></div>
            <?php if ($s['detail']): ?>
            <div class="text-muted small"><?= e($s['detail']) ?></div>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($allOk): ?>
      <div class="alert alert-success d-flex align-items-start gap-2">
        <i class="bi bi-check-circle-fill mt-1"></i>
        <div>
          <strong>Setup complete!</strong> You can log in with
          <code>registrar@tcc.edu.ph</code> or <code>staff@tcc.edu.ph</code>
          (password <code>password123</code>, change required on first login).
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div><strong>Setup incomplete.</strong> Fix the failed checks above and reload this page.</div>
      </div>
      <?php endif; ?>

      <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-shield-exclamation mt-1"></i>
        <div><strong>Delete <code>setup.php</code> now.</strong> This file must not remain on the server.</div>
      </div>

      <div class="d-flex gap-2">
        <?= ui_btn('Go to Login', ['icon'=>'box-arrow-in-right','href'=>BASE_URL . '/auth/login.php']) ?>
        <?= ui_btn('Student Portal', ['variant'=>'outline-secondary','icon'=>'house','href'=>BASE_URL . '/student/index.php']) ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>

<?php
/**
 * TCC RRS – Change Password
 * Forced on first login (must_change_password = 1) or accessible anytime from dashboard.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireAuth();   // must be logged in

$force   = (get('force') === '1');   // forced on first login
$errors  = [];
$success = false;
$user    = currentUser();

// If not forced, verify they are truly flagged (prevent URL skipping)
if (!$force) {
    $dbUser = db()->fetchOne("SELECT must_change_password FROM users WHERE id = ?", [$user['id']]);
    if ($dbUser && $dbUser['must_change_password']) {
        // They still must change – treat as forced
        $force = true;
    }
}

if (isPost()) {
    abortCsrf();

    $currentPassword = post('current_password');
    $newPassword     = post('new_password');
    $confirmPassword = post('confirm_password');

    // Fetch live record for verification
    $dbUser = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);

    // Validate current password
    if (empty($currentPassword)) {
        $errors[] = 'Current (temporary) password is required.';
    } elseif (!password_verify($currentPassword, $dbUser['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    // Validate new password
    if (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'New password must contain at least one letter and one number.';
    } elseif ($newPassword === $currentPassword) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (empty($newPassword) === false && $newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        db()->execute(
            "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
            [$newHash, $user['id']]
        );

        logActivity('PASSWORD_CHANGED', 'Auth', 'User changed their password.', '', $user['id'], $user['full_name'], currentRole());

        // Update session user data
        $_SESSION['user']['must_change_password'] = 0;

        flash('success', 'Password changed successfully! Welcome, ' . $user['full_name'] . '!');

        // Redirect to the correct dashboard
        if (currentRole() === ROLE_REGISTRAR) {
            redirect(BASE_URL . '/registrar/dashboard.php');
        } else {
            redirect(BASE_URL . '/staff/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password – TCC RRS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
  body {
    background: linear-gradient(135deg, #0f2a56 0%, #1a4a8a 60%, #2563eb 100%);
    min-height: 100vh;
  }
  .change-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    overflow: hidden;
  }
  .change-header {
    background: linear-gradient(135deg, #0f2a56, #1a4a8a);
    padding: 28px 32px;
    text-align: center;
    color: #fff;
  }
  .change-header .icon-wrap {
    width: 72px; height: 72px;
    background: rgba(255,255,255,.15);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
  }
  .change-body { padding: 32px; }
  .strength-bar { height: 6px; border-radius: 3px; transition: all .3s; }
  .requirement { font-size: .78rem; }
  .requirement .bi { font-size: .75rem; }
  .req-ok   { color: #15803d; }
  .req-fail { color: #94a3b8; }
</style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="change-card card">

        <!-- Header -->
        <div class="change-header">
          <div class="icon-wrap">
            <?php if ($force): ?>
              <i class="bi bi-shield-lock-fill fs-2 text-warning"></i>
            <?php else: ?>
              <i class="bi bi-key-fill fs-2" style="color: #c9a84c;"></i>
            <?php endif; ?>
          </div>
          <h5 class="fw-bold mb-1">
            <?= $force ? 'Set Your New Password' : 'Change Password' ?>
          </h5>
          <p class="mb-0 opacity-75 small">
            <?php if ($force): ?>
              You are using a temporary password.<br>Please set a permanent password to continue.
            <?php else: ?>
              Update your account password below.
            <?php endif; ?>
          </p>
        </div>

        <!-- Body -->
        <div class="change-body">

          <?php if ($force): ?>
          <!-- Forced change alert -->
          <div class="alert alert-warning d-flex gap-2 py-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div class="small">
              <strong>Action Required:</strong> Your account was created with a temporary password
              <code>password123</code>. You must change it before accessing the system.
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger d-flex gap-2 py-2 mb-4">
            <i class="bi bi-x-circle-fill flex-shrink-0 mt-1"></i>
            <div>
              <?php foreach ($errors as $err): ?>
                <div class="small"><?= e($err) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- User info strip -->
          <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background:#f0f4fb;">
            <div class="avatar-circle bg-primary text-white fw-bold" style="width:40px;height:40px;font-size:.9rem;">
              <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
            <div>
              <div class="fw-semibold small"><?= e($user['full_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= e($user['email']) ?> &middot; <span class="text-capitalize"><?= e(currentRole()) ?></span></div>
            </div>
          </div>

          <form method="POST" action="" id="change-form" novalidate>
            <?= csrfField() ?>
            <?php if ($force): ?><input type="hidden" name="force" value="1"><?php endif; ?>

            <!-- Current Password -->
            <div class="mb-3">
              <label class="form-label fw-semibold small">
                <?= $force ? 'Temporary Password' : 'Current Password' ?>
                <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="password" name="current_password" id="currentPwd"
                       class="form-control" required
                       placeholder="<?= $force ? 'Enter: password123' : 'Your current password' ?>">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('currentPwd', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <!-- New Password -->
            <div class="mb-3">
              <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" name="new_password" id="newPwd"
                       class="form-control" required minlength="8"
                       placeholder="Min. 8 characters"
                       oninput="checkStrength(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('newPwd', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <!-- Strength bar -->
              <div class="mt-2">
                <div class="progress" style="height:6px;border-radius:3px;">
                  <div id="strength-bar" class="progress-bar strength-bar" style="width:0%;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                  <span id="strength-label" class="text-muted" style="font-size:.7rem;"></span>
                </div>
              </div>
              <!-- Requirements -->
              <div class="mt-2 d-flex flex-wrap gap-2">
                <div class="requirement" id="req-length"><i class="bi bi-circle req-fail"></i> 8+ characters</div>
                <div class="requirement" id="req-letter"><i class="bi bi-circle req-fail"></i> Letter</div>
                <div class="requirement" id="req-number"><i class="bi bi-circle req-fail"></i> Number</div>
                <div class="requirement" id="req-diff"><i class="bi bi-circle req-fail"></i> Different from current</div>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
              <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" name="confirm_password" id="confirmPwd"
                       class="form-control" required
                       placeholder="Re-enter new password"
                       oninput="checkMatch()">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirmPwd', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div id="match-msg" class="mt-1" style="font-size:.75rem;"></div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="submitBtn">
              <i class="bi bi-shield-check me-2"></i>
              <?= $force ? 'Set Password & Continue' : 'Change Password' ?>
            </button>

            <?php if (!$force): ?>
            <div class="text-center mt-3">
              <a href="<?= BASE_URL ?>/<?= currentRole() ?>/dashboard.php"
                 class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
              </a>
            </div>
            <?php endif; ?>
          </form>

        </div><!-- /change-body -->
      </div><!-- /card -->

      <p class="text-center text-white-50 small mt-3">
        &copy; <?= date('Y') ?> Tampakan Community College
      </p>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Toggle password visibility ──────────────────────────────────────────
function togglePwd(id, btn) {
  const field = document.getElementById(id);
  const isText = field.type === 'text';
  field.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<i class="bi bi-eye"></i>'
    : '<i class="bi bi-eye-slash"></i>';
}

// ── Password strength checker ────────────────────────────────────────────
function checkStrength(val) {
  const bar    = document.getElementById('strength-bar');
  const label  = document.getElementById('strength-label');
  const reqLen = document.getElementById('req-length');
  const reqLet = document.getElementById('req-letter');
  const reqNum = document.getElementById('req-number');
  const reqDif = document.getElementById('req-diff');

  const hasLength = val.length >= 8;
  const hasLetter = /[A-Za-z]/.test(val);
  const hasNumber = /[0-9]/.test(val);
  const hasUpper  = /[A-Z]/.test(val);
  const hasSymbol = /[^A-Za-z0-9]/.test(val);
  const currentVal = document.getElementById('currentPwd').value;
  const isDiff    = val.length > 0 && val !== currentVal;

  // Update requirement icons
  setReq(reqLen, hasLength);
  setReq(reqLet, hasLetter);
  setReq(reqNum, hasNumber);
  setReq(reqDif, isDiff);

  // Strength score
  let score = 0;
  if (hasLength) score++;
  if (hasLetter) score++;
  if (hasNumber) score++;
  if (hasUpper)  score++;
  if (hasSymbol) score++;

  const levels = [
    { pct: 0,   color: '',          text: '' },
    { pct: 20,  color: '#dc2626',   text: 'Very Weak' },
    { pct: 40,  color: '#f59e0b',   text: 'Weak' },
    { pct: 60,  color: '#3b82f6',   text: 'Fair' },
    { pct: 80,  color: '#10b981',   text: 'Strong' },
    { pct: 100, color: '#15803d',   text: 'Very Strong' },
  ];
  const level = val.length === 0 ? levels[0] : levels[Math.min(score, 5)];
  bar.style.width = level.pct + '%';
  bar.style.background = level.color;
  label.textContent = level.text;
  label.style.color  = level.color;
}

function setReq(el, ok) {
  const icon = el.querySelector('i');
  if (ok) {
    icon.className = 'bi bi-check-circle-fill req-ok';
    el.className = 'requirement req-ok';
  } else {
    icon.className = 'bi bi-circle req-fail';
    el.className = 'requirement req-fail';
  }
}

// ── Match checker ─────────────────────────────────────────────────────────
function checkMatch() {
  const newPwd  = document.getElementById('newPwd').value;
  const confPwd = document.getElementById('confirmPwd').value;
  const msg     = document.getElementById('match-msg');
  if (confPwd.length === 0) { msg.textContent = ''; return; }
  if (newPwd === confPwd) {
    msg.innerHTML = '<span style="color:#15803d;"><i class="bi bi-check-circle-fill me-1"></i>Passwords match</span>';
  } else {
    msg.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
  }
}

// ── Submit guard ──────────────────────────────────────────────────────────
document.getElementById('change-form').addEventListener('submit', function(e) {
  const newPwd  = document.getElementById('newPwd').value;
  const confPwd = document.getElementById('confirmPwd').value;
  if (newPwd !== confPwd) {
    e.preventDefault();
    document.getElementById('match-msg').innerHTML =
      '<span style="color:#dc2626;"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
    document.getElementById('confirmPwd').focus();
    return;
  }
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').innerHTML =
    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
});
</script>
</body>
</html>

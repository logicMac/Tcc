<?php
/**
 * TCC RRS – Student: Change Password
 * Forced on first login for auto-created accounts (must_change_password = 1),
 * or accessible anytime from the portal.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
requireStudentAuth();

$force   = (get('force') === '1');
$errors  = [];
$student = currentStudent();

// If not flagged via URL, verify the DB flag — treat flagged accounts as forced.
if (!$force) {
    $flag = db()->fetchValue("SELECT must_change_password FROM students WHERE id = ?", [$student['id']]);
    if ($flag) $force = true;
}

if (isPost()) {
    abortCsrf();

    $currentPassword = post('current_password');
    $newPassword     = post('new_password');
    $confirmPassword = post('confirm_password');

    $dbStu = db()->fetchOne("SELECT * FROM students WHERE id = ?", [$student['id']]);

    if (empty($currentPassword)) {
        $errors[] = 'Current (temporary) password is required.';
    } elseif (!password_verify($currentPassword, $dbStu['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'New password must contain at least one letter and one number.';
    } elseif ($newPassword === $currentPassword) {
        $errors[] = 'New password must be different from your temporary password.';
    }

    if (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        db()->execute(
            "UPDATE students SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
            [password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $student['id']]
        );

        logActivity('PASSWORD_CHANGED', 'Student Portal',
            'Student changed their password.', '', null, $student['email'], 'student');

        flash('success', 'Password changed successfully! Welcome, ' . $student['first_name'] . '!');
        redirect(BASE_URL . '/student/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password – Student Portal</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
<style>
  .change-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(4,28,19,.3);
    overflow: hidden;
  }
  .change-body { padding: 32px; }
  .strength-bar { height: 6px; border-radius: 3px; transition: width .3s, background .3s; }
  .requirement { font-size: .78rem; }
  .requirement .bi { font-size: .75rem; }
</style>
</head>
<body>
<!-- Page loader -->
<div class="page-loader" id="pageLoader" aria-hidden="true">
  <div class="pl-ring">
    <svg class="pl-arc-svg" viewBox="0 0 100 100"><circle class="pl-track" cx="50" cy="50" r="44"/><circle class="pl-arc" cx="50" cy="50" r="44"/></svg>
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="" class="pl-logo">
  </div>
  <div class="pl-text">Tampakan <b>Community College</b></div>
</div>

<div class="auth-shell">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="change-card card">

        <!-- Header -->
        <div class="auth-brand">
          <div class="auth-brand-icon">
            <i class="bi bi-<?= $force ? 'shield-lock-fill text-warning' : 'key-fill text-gold' ?> fs-2"></i>
          </div>
          <h5 class="fw-bold mb-1">
            <?= $force ? 'Set Your New Password' : 'Change Password' ?>
          </h5>
          <p class="mb-0 opacity-75 small">
            <?php if ($force): ?>
              Your account was created automatically when you submitted your pre-registration.<br>
              Set a permanent password to continue.
            <?php else: ?>
              Update your account password below.
            <?php endif; ?>
          </p>
        </div>

        <!-- Body -->
        <div class="change-body">

          <?php if ($force): ?>
          <div class="alert alert-warning d-flex gap-2 py-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div class="small">
              <strong>Action required:</strong> sign in with the temporary password shown after
              your submission (also emailed to you), then choose your own password below.
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

          <!-- Student info strip -->
          <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-4" style="background:var(--tcc-green-50);">
            <?= ui_avatar($student['first_name'] . ' ' . $student['last_name'], 40) ?>
            <div>
              <div class="fw-semibold small"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= e($student['email']) ?> · Student</div>
            </div>
          </div>

          <form method="POST" action="" id="change-form" novalidate>
            <?= csrfField() ?>
            <?php if ($force): ?><input type="hidden" name="force" value="1"><?php endif; ?>

            <!-- Current / Temporary Password -->
            <div class="mb-3">
              <label class="form-label fw-semibold small">
                <?= $force ? 'Temporary Password' : 'Current Password' ?>
                <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="password" name="current_password" id="currentPwd"
                       class="form-control" required
                       placeholder="<?= $force ? 'From your submission confirmation or email' : 'Your current password' ?>">
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'currentPwd\', this)"']) ?>
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
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'newPwd\', this)"']) ?>
              </div>
              <div class="mt-2">
                <div class="progress" style="height:6px;border-radius:3px;">
                  <div id="strength-bar" class="progress-bar strength-bar" style="width:0%;"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                  <span id="strength-label" class="text-muted" style="font-size:.7rem;"></span>
                </div>
              </div>
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
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'confirmPwd\', this)"']) ?>
              </div>
              <div id="match-msg" class="mt-1" style="font-size:.75rem;"></div>
            </div>

            <?= ui_btn($force ? 'Set Password & Continue' : 'Change Password',
                       ['type'=>'submit','class'=>'w-100 py-2 fw-semibold','icon'=>'shield-check',
                        'attrs'=>'id="submitBtn"']) ?>

            <div class="text-center mt-3">
              <?php if ($force): ?>
              <a href="<?= BASE_URL ?>/student/logout.php" class="text-muted small">
                <i class="bi bi-box-arrow-right me-1"></i>Sign out instead
              </a>
              <?php else: ?>
              <a href="<?= BASE_URL ?>/student/dashboard.php" class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
              </a>
              <?php endif; ?>
            </div>
          </form>

        </div><!-- /change-body -->
      </div><!-- /card -->

      <p class="text-center text-muted small mt-3">
        &copy; <?= date('Y') ?> Tampakan Community College
      </p>

    </div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(id, btn) {
  const field = document.getElementById(id);
  const isText = field.type === 'text';
  field.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<i class="bi bi-eye"></i>'
    : '<i class="bi bi-eye-slash"></i>';
}

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

  setReq(reqLen, hasLength);
  setReq(reqLet, hasLetter);
  setReq(reqNum, hasNumber);
  setReq(reqDif, isDiff);

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
    { pct: 60,  color: '#a07d1a',   text: 'Fair' },
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

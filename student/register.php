<?php
/**
 * TCC RRS – Student Account Registration
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if (isStudentLoggedIn()) {
    redirect(BASE_URL . '/student/dashboard.php');
}

$schoolName = getSetting('school_name', 'Tampakan Community College');
$activePage = 'account';
$errors     = [];

if (isPost()) {
    abortCsrf();

    $firstName = post('first_name');
    $middleName = post('middle_name');
    $lastName = post('last_name');
    $suffix = post('suffix');
    $studentNo = post('student_no');
    $email = post('email');
    $contact = post('contact_number');
    $password = post('password');
    $confirm = post('confirm_password');

    if (empty($firstName))              $errors[] = 'First name is required.';
    if (empty($lastName))               $errors[] = 'Last name is required.';
    if (empty($email))                  $errors[] = 'Email address is required.';
    elseif (!validateEmail($email))     $errors[] = 'Please enter a valid email address.';
    if (empty($contact))                $errors[] = 'Contact number is required.';
    elseif (!validatePhone($contact))   $errors[] = 'Invalid contact number format.';
    if (strlen($password) < 8)          $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)         $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $exists = db()->fetchValue("SELECT id FROM students WHERE email = ?", [$email]);
        if ($exists) {
            $errors[] = 'An account with this email already exists. Try signing in instead.';
        } else {
            try {
                $id = (int) db()->insert(
                    "INSERT INTO students
                        (student_no, first_name, middle_name, last_name, suffix,
                         email, contact_number, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$studentNo ?: null, $firstName, $middleName, $lastName, $suffix,
                     $email, $contact, password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])]
                );
                // Attach any previous guest submissions made with this email
                claimSubmissionsForStudent($id, $email);
                logActivity('STUDENT_REGISTER', 'Student Portal', "Student account created: {$email}", '', null, "{$firstName} {$lastName}", 'student');

                $student = db()->fetchOne("SELECT * FROM students WHERE id = ?", [$id]);
                loginStudentSession($student);
                session_regenerate_id(true);

                flash('success', 'Account created! Past submissions under this email are now linked to your account.');
                redirect(BASE_URL . '/student/dashboard.php');
            } catch (Exception $e) {
                $errors[] = 'Registration failed. Please try again.';
                if (APP_DEBUG) $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account – TCC Registration &amp; Records System</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
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

<div class="auth-split">

  <!-- ── Showcase panel ── -->
  <div class="auth-show">
    <div class="auth-show-brand">
      <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal">
      <div>
        <div class="name"><?= e($schoolName) ?></div>
        <div class="sub">Student Portal</div>
      </div>
    </div>

    <div class="auth-show-copy">
        <h2>Create an account,<br><span class="accent">link your records.</span></h2>
        <p class="lede">One portal account keeps every registration, request, and
          verification code together — including submissions you already made.</p>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-link-45deg"></i></div>
          <div>
            <div class="t">Automatic linking</div>
            <div class="s">Register with the same email and past submissions attach instantly.</div>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="t">Track everything</div>
            <div class="s">Statuses, tracking codes, and history — all in one dashboard.</div>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-shield-check"></i></div>
          <div>
            <div class="t">Secure by design</div>
            <div class="s">Encrypted credentials and a verifiable record for every document.</div>
          </div>
        </div>
    </div>

    <div class="auth-show-foot">
      <span>&copy; <?= date('Y') ?> <?= e($schoolName) ?> &middot; Student access only</span>
      <span class="lock"><i class="bi bi-shield-lock-fill"></i> Secured session</span>
    </div>
  </div>

  <!-- ── Form panel ── -->
  <div class="auth-panel">
    <div class="auth-form">
      <div class="auth-mobile-brand">
        <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal">
        <div class="name"><?= e($schoolName) ?></div>
        <div class="sub">Student Portal</div>
      </div>
      <div class="eyebrow">Student Portal</div>
      <h1>Create your account</h1>
      <p class="sub">One account for all registrations and document requests.</p>

      <div class="alert alert-info d-flex gap-2 py-2 mb-3">
        <i class="bi bi-info-circle-fill flex-shrink-0"></i>
        <div class="small">
          Use the <strong>same email</strong> from past submissions to link them automatically.
        </div>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger d-flex gap-2 py-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <ul class="mb-0 ps-3 small">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <div class="auth-sec">Your name</div>
        <div class="row g-2">
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="first_name" name="first_name"
                     value="<?= e(post('first_name')) ?>" placeholder="First name" required maxlength="100">
              <label for="first_name">First Name <span class="required-mark">*</span></label>
            </div>
          </div>
          <div class="col-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="last_name" name="last_name"
                     value="<?= e(post('last_name')) ?>" placeholder="Last name" required maxlength="100">
              <label for="last_name">Last Name <span class="required-mark">*</span></label>
            </div>
          </div>
          <div class="col-8">
            <div class="form-floating">
              <input type="text" class="form-control" id="middle_name" name="middle_name"
                     value="<?= e(post('middle_name')) ?>" placeholder="Middle name" maxlength="100">
              <label for="middle_name">Middle Name <span class="text-muted">(optional)</span></label>
            </div>
          </div>
          <div class="col-4">
            <div class="form-floating">
              <input type="text" class="form-control" id="suffix" name="suffix"
                     value="<?= e(post('suffix')) ?>" placeholder="Suffix" maxlength="20">
              <label for="suffix">Suffix</label>
            </div>
          </div>
        </div>

        <div class="auth-sec">Contact &amp; account</div>
        <div class="form-floating mb-2">
          <input type="text" class="form-control" id="student_no" name="student_no"
                 value="<?= e(post('student_no')) ?>" placeholder="Student number" maxlength="30">
          <label for="student_no">Student Number <span class="text-muted">(if enrolled)</span></label>
        </div>
        <div class="form-floating mb-2">
          <input type="email" class="form-control" id="email" name="email"
                 value="<?= e(post('email')) ?>" placeholder="you@email.com" required maxlength="150">
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address <span class="required-mark">*</span></label>
        </div>
        <div class="form-floating">
          <input type="tel" class="form-control" id="contact_number" name="contact_number"
                 value="<?= e(post('contact_number')) ?>" placeholder="09xx xxx xxxx" required maxlength="20">
          <label for="contact_number"><i class="bi bi-phone me-1"></i>Contact Number <span class="required-mark">*</span></label>
        </div>

        <div class="auth-sec">Security</div>
        <div class="form-floating pwd-wrap mb-1">
          <input type="password" class="form-control" id="password" name="password"
                 style="padding-right:48px;" placeholder="Password"
                 required minlength="8" autocomplete="new-password">
          <label for="password"><i class="bi bi-lock me-1"></i>Password <span class="required-mark">*</span></label>
          <button type="button" class="pwd-toggle" data-pwd-toggle="#password"
                  aria-label="Show password" tabindex="-1">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="pw-meter"><i id="pwBar"></i></div>
        <div class="pw-meter-label" id="pwLabel"></div>
        <div class="form-floating pwd-wrap mt-2 mb-1">
          <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                 style="padding-right:48px;" placeholder="Confirm password"
                 required autocomplete="new-password">
          <label for="confirm_password"><i class="bi bi-shield-lock me-1"></i>Confirm Password <span class="required-mark">*</span></label>
          <button type="button" class="pwd-toggle" data-pwd-toggle="#confirm_password"
                  aria-label="Show password" tabindex="-1">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="pw-match" id="pwMatch"></div>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="agreeTerms" required>
          <label class="form-check-label small" for="agreeTerms">
            I agree that my information will be used to process my registrar transactions.
          </label>
        </div>

        <?= ui_btn('Create Account', ['type'=>'submit','class'=>'w-100 py-2 fw-bold rounded-3 mt-3','icon'=>'person-plus']) ?>
      </form>

      <div class="auth-alt">
        Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Sign in</a>
        <div class="mt-2">
          <a href="<?= BASE_URL ?>/student/index.php" class="text-muted fw-normal">
            <i class="bi bi-arrow-left me-1"></i>Back to Portal
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/lenis.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js<?= assetVer("assets/js/main.js") ?>"></script>
<script>
(function () {
  const pwd = document.getElementById('password');
  const cfm = document.getElementById('confirm_password');
  const bar = document.getElementById('pwBar');
  const lbl = document.getElementById('pwLabel');
  const msg = document.getElementById('pwMatch');
  const levels = [
    { pct: 0,   color: '',        text: '' },
    { pct: 20,  color: '#dc2626', text: 'Very weak' },
    { pct: 40,  color: '#f59e0b', text: 'Weak' },
    { pct: 60,  color: '#a07d1a', text: 'Fair' },
    { pct: 80,  color: '#10b981', text: 'Strong' },
    { pct: 100, color: '#15803d', text: 'Very strong' },
  ];
  pwd.addEventListener('input', () => {
    const v = pwd.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Za-z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const level = v.length === 0 ? levels[0] : levels[Math.min(score, 5)];
    bar.style.width = level.pct + '%';
    bar.style.background = level.color;
    lbl.textContent = level.text;
    lbl.style.color = level.color;
  });
  const checkMatch = () => {
    if (cfm.value.length === 0) { msg.textContent = ''; return; }
    msg.innerHTML = pwd.value === cfm.value
      ? '<span style="color:#15803d;"><i class="bi bi-check-circle-fill me-1"></i>Passwords match</span>'
      : '<span style="color:#dc2626;"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
  };
  pwd.addEventListener('input', checkMatch);
  cfm.addEventListener('input', checkMatch);
})();
</script>
</body>
</html>

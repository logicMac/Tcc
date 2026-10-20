<?php
/**
 * TCC RRS – Unified Login Page (Registrar, Staff & Students)
 *
 * One sign-in for the whole system. Looks up the users table first,
 * then students — and routes to the correct portal by account type.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
redirectIfLoggedIn();
if (isStudentLoggedIn()) {
    redirect(BASE_URL . '/student/dashboard.php');
}

$errors = [];
$email  = '';
$return = get('return');
// Only allow in-app redirects (open-redirect protection)
if ($return && (!str_starts_with($return, '/') || str_starts_with($return, '//'))) {
    $return = '';
}

if (isPost()) {
    abortCsrf();

    $email    = post('email');
    $password = post('password');

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    $return = post('return');
    if ($return && (!str_starts_with($return, '/') || str_starts_with($return, '//'))) {
        $return = '';
    }

    if (empty($errors)) {
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        // ── Staff / Registrar ────────────────────────────────────────────
        if ($user) {
            if (!password_verify($password, $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
                logActivity('LOGIN_FAILED', 'Auth', "Failed login attempt for: {$email}", '', null, $email, 'unknown');
            } elseif (!$user['is_active']) {
                $errors[] = 'Your account has been deactivated. Please contact the Registrar.';
            } else {
            // Set session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user']      = [
                'id'          => $user['id'],
                'employee_id' => $user['employee_id'],
                'full_name'   => $user['full_name'],
                'email'       => $user['email'],
                'role'        => $user['role'],
            ];
            session_regenerate_id(true);

            // Update last login
            db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
            logActivity('LOGIN', 'Auth', "User logged in successfully.", '', $user['id'], $user['full_name'], $user['role']);

            // Check if user must change password
            if ($user['must_change_password']) {
                flash('warning', 'You are using a temporary password. Please change it now for security.');
                redirect(BASE_URL . '/auth/change_password.php?force=1');
            }

            flash('success', 'Welcome back, ' . $user['full_name'] . '!');

            if ($user['role'] === ROLE_REGISTRAR) {
                redirect(BASE_URL . '/registrar/dashboard.php');
            } else {
                redirect(BASE_URL . '/staff/dashboard.php');
            }
            }

        // ── Student ──────────────────────────────────────────────────────
        } else {
            $student = db()->fetchOne(
                "SELECT * FROM students WHERE email = ? LIMIT 1",
                [$email]
            );

            if (!$student || !password_verify($password, $student['password_hash'])) {
                $errors[] = 'Invalid email or password.';
                logActivity('LOGIN_FAILED', 'Auth', "Failed login attempt for: {$email}", '', null, $email, 'unknown');
            } elseif (!$student['is_active']) {
                $errors[] = 'Your account has been deactivated. Please contact the Registrar\'s Office.';
            } else {
                loginStudentSession($student);
                session_regenerate_id(true);
                db()->execute("UPDATE students SET last_login = NOW() WHERE id = ?", [$student['id']]);
                claimSubmissionsForStudent((int) $student['id'], $student['email']);
                logActivity('LOGIN', 'Auth', "Student logged in successfully.", '', null, $student['email'], 'student');

                // Auto-created accounts must set a permanent password first
                if (!empty($student['must_change_password'])) {
                    flash('warning', 'For your security, please set a permanent password now.');
                    redirect(BASE_URL . '/student/change_password.php?force=1');
                }

                flash('success', 'Welcome back, ' . $student['first_name'] . '!');
                redirect($return ?: (BASE_URL . '/student/dashboard.php'));
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
<title>Sign In – TCC Registration & Records System</title>
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
        <div class="name">Tampakan Community College</div>
        <div class="sub">Registration &amp; Records System</div>
      </div>
    </div>
    <div class="auth-show-copy">
        <h2>One sign-in,<br><span class="accent">every record.</span></h2>
        <p class="lede">Staff manage registrations and issue documents; students track
          submissions — all from a single account.</p>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-person-lines-fill"></i></div>
          <div>
            <div class="t">Records management</div>
            <div class="s">Review, edit, and archive student records with a full audit trail.</div>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-patch-check"></i></div>
          <div>
            <div class="t">QR-verified issuance</div>
            <div class="s">Every released document carries a verifiable code and QR.</div>
          </div>
        </div>
        <div class="auth-feat">
          <div class="auth-feat-icon"><i class="bi bi-collection"></i></div>
          <div>
            <div class="t">Student portal</div>
            <div class="s">Submissions, tracking codes, and status history in one dashboard.</div>
          </div>
        </div>
    </div>

    <div class="auth-show-foot">
      <span>&copy; <?= date('Y') ?> Tampakan Community College &middot; Registrar &middot; Staff &middot; Student</span>
      <span class="lock"><i class="bi bi-shield-lock-fill"></i> Secured session</span>
    </div>
  </div>

  <!-- ── Form panel ── -->
  <div class="auth-panel">
    <div class="auth-form">
      <div class="auth-mobile-brand">
        <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal">
        <div class="name">Tampakan Community College</div>
        <div class="sub">Registration &amp; Records System</div>
      </div>
      <div class="eyebrow">Sign In</div>
      <h1>Welcome back</h1>
      <p class="sub">Sign in with your TCC account — staff or student.</p>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger d-flex align-items-start py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 mt-1 flex-shrink-0"></i>
        <div>
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?= renderFlash() ?>

      <form method="POST" action="<?= BASE_URL ?>/auth/login.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">

        <div class="form-floating mb-3">
          <input type="email" class="form-control <?= !empty($errors) ? 'is-invalid' : '' ?>"
                 id="email" name="email"
                 value="<?= e($email) ?>"
                 placeholder="name@tcc.edu.ph"
                 required autofocus>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>

        <div class="form-floating mb-4 pwd-wrap">
          <input type="password" class="form-control <?= !empty($errors) ? 'is-invalid' : '' ?>"
                 id="password" name="password" style="padding-right:48px;"
                 placeholder="Password"
                 required>
          <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
          <button type="button" class="pwd-toggle" data-pwd-toggle="#password"
                  aria-label="Show password" tabindex="-1">
            <i class="bi bi-eye"></i>
          </button>
        </div>

        <?= ui_btn('Sign In', ['type'=>'submit','class'=>'w-100 py-2 fw-bold rounded-3','icon'=>'box-arrow-in-right']) ?>
      </form>

      <div class="auth-alt">
        No student account yet?
        <a href="<?= BASE_URL ?>/student/register.php">Create one <i class="bi bi-arrow-right"></i></a>
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
</body>
</html>

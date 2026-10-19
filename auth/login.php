<?php
/**
 * TCC RRS – Login Page (Registrar & Staff only)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

startSession();
redirectIfLoggedIn();

$errors = [];
$email  = '';

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

    if (empty($errors)) {
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login – TCC Registration & Records System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
  body { background: linear-gradient(135deg, #0f2a56 0%, #1a4a8a 50%, #0f2a56 100%); min-height:100vh; }
  .login-card { border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.35); }
  .login-brand { background:linear-gradient(135deg,#0f2a56,#1a4a8a); border-radius:16px 16px 0 0; padding:32px; text-align:center; color:#fff; }
  .login-brand .school-seal { width:80px; height:80px; background:#fff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px; }
  .login-brand .school-seal i { font-size:2.5rem; color:#0f2a56; }
  .login-body { padding:32px; }
  .form-floating label { color:#6c757d; }
  .btn-login { background:linear-gradient(135deg,#0f2a56,#1a4a8a); border:none; padding:12px; font-size:1rem; font-weight:600; letter-spacing:.5px; transition:opacity .2s; }
  .btn-login:hover { opacity:.9; }
  .divider-text { position:relative; text-align:center; margin:16px 0; }
  .divider-text::before { content:''; position:absolute; top:50%; left:0; right:0; height:1px; background:#dee2e6; }
  .divider-text span { position:relative; background:#fff; padding:0 12px; color:#6c757d; font-size:.8rem; }
  .portal-link { background:#f8f9fa; border-radius:8px; padding:14px; text-align:center; }
</style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">

      <div class="card login-card">
        <!-- Brand Header -->
        <div class="login-brand">
          <div class="school-seal">
            <i class="bi bi-building"></i>
          </div>
          <h5 class="mb-1 fw-bold">Tampakan Community College</h5>
          <p class="mb-0 opacity-75 small">Registration & Records System</p>
        </div>

        <!-- Login Form -->
        <div class="login-body">
          <h6 class="text-center text-muted mb-4">Staff / Registrar Login</h6>

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

            <div class="form-floating mb-3">
              <input type="email" class="form-control <?= !empty($errors) ? 'is-invalid' : '' ?>"
                     id="email" name="email"
                     value="<?= e($email) ?>"
                     placeholder="name@tcc.edu.ph"
                     required autofocus>
              <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
            </div>

            <div class="form-floating mb-3">
              <input type="password" class="form-control <?= !empty($errors) ? 'is-invalid' : '' ?>"
                     id="password" name="password"
                     placeholder="Password"
                     required>
              <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100 text-white rounded-3 mt-2">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
          </form>

          <div class="divider-text mt-4"><span>or</span></div>

          <div class="portal-link">
            <p class="mb-2 small text-muted">Are you a student?</p>
            <a href="<?= BASE_URL ?>/student/index.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-person me-1"></i> Go to Student Portal
            </a>
          </div>
        </div>
      </div>

      <p class="text-center text-white-50 small mt-3">
        &copy; <?= date('Y') ?> Tampakan Community College. All rights reserved.
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

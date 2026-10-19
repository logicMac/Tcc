<?php
/**
 * TCC RRS – Student Login
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

if (isStudentLoggedIn()) {
    redirect(BASE_URL . '/student/account.php');
}

$schoolName = getSetting('school_name', 'Tampakan Community College');
$activePage = 'account';
$errors     = [];
$return     = get('return');
// Only allow in-app redirects (open-redirect protection)
if ($return && !str_starts_with($return, '/')) {
    $return = '';
}

if (isPost()) {
    abortCsrf();
    $email    = post('email');
    $password = post('password');
    $return   = post('return');
    // Re-validate: hidden field could be tampered into an external URL
    if ($return && !str_starts_with($return, '/')) {
        $return = '';
    }

    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } else {
        $student = db()->fetchOne("SELECT * FROM students WHERE email = ? LIMIT 1", [$email]);

        if (!$student || !password_verify($password, $student['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } elseif (!$student['is_active']) {
            $errors[] = 'Your account has been deactivated. Please contact the Registrar\'s Office.';
        } else {
            loginStudentSession($student);
            session_regenerate_id(true);
            db()->execute("UPDATE students SET last_login = NOW() WHERE id = ?", [$student['id']]);
            claimSubmissionsForStudent((int)$student['id'], $student['email']);

            flash('success', 'Welcome back, ' . $student['first_name'] . '!');
            redirect($return ?: (BASE_URL . '/student/account.php'));
        }
    }
}
?>
<?php
$pageTitle = 'Student Sign In';
include __DIR__ . '/../includes/partials/portal_head.php';
?>
<div class="auth-shell">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">

      <div class="auth-card">
        <div class="auth-brand">
          <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal" class="mb-2">
          <h5 class="fw-bold mb-1">Student Portal</h5>
          <p class="mb-0 small opacity-75"><?= e($schoolName) ?></p>
        </div>

        <div class="card-body p-4">
          <?= renderFlash() ?>

          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger py-2 small">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="return" value="<?= e($return) ?>">

            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= e(post('email')) ?>" placeholder="you@email.com" required autofocus>
              <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
            </div>
            <div class="form-floating mb-3">
              <input type="password" class="form-control" id="password" name="password"
                     placeholder="Password" required>
              <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
            </button>
          </form>

          <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">No account yet?</span>
            <a href="<?= BASE_URL ?>/student/register.php" class="small fw-semibold text-decoration-none ms-1">
              Create one
            </a>
          </div>
          <div class="text-center mt-2">
            <a href="<?= BASE_URL ?>/student/index.php" class="small text-muted text-decoration-none">
              <i class="bi bi-arrow-left me-1"></i>Back to Portal
            </a>
          </div>
        </div>
      </div>

      <p class="text-center text-muted small mt-3">
        &copy; <?= date('Y') ?> <?= e($schoolName) ?>
      </p>
    </div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

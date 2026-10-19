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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Sign In – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
  body { background: linear-gradient(135deg, #0f2a56 0%, #1a4a8a 50%, #0f2a56 100%); min-height:100vh; }
</style>
</head>
<body class="d-flex align-items-center py-5">

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">

      <div class="card border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
        <div class="text-center text-white" style="background:linear-gradient(135deg,#0f2a56,#1a4a8a);padding:28px 24px;">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white mb-2"
               style="width:64px;height:64px;">
            <i class="bi bi-mortarboard-fill" style="font-size:1.8rem;color:#0f2a56;"></i>
          </div>
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

      <p class="text-center text-white-50 small mt-3">
        &copy; <?= date('Y') ?> <?= e($schoolName) ?>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

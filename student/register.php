<?php
/**
 * TCC RRS – Student Account Registration
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
                redirect(BASE_URL . '/student/account.php');
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
<title>Create Account – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body style="background:var(--bg-app);">

<?php include __DIR__ . '/../includes/partials/portal_nav.php'; ?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
             style="width:56px;height:56px;background:#dbeafe;">
          <i class="bi bi-person-plus-fill text-primary fs-4"></i>
        </div>
        <h3 class="fw-bold mb-1">Create Your Student Account</h3>
        <p class="text-muted small mb-0">
          One account for all your registrations and document requests.
        </p>
      </div>

      <div class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-body p-4 p-md-5">

          <div class="alert alert-info d-flex gap-2 py-2 mb-4">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <div class="small">
              Registering with the <strong>same email</strong> you used on past submissions
              links them to your account automatically.
            </div>
          </div>

          <?php if (!empty($errors)): ?>
          <div class="alert alert-danger d-flex gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate>
            <?= csrfField() ?>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">First Name <span class="required-mark">*</span></label>
                <input type="text" name="first_name" class="form-control"
                       value="<?= e(post('first_name')) ?>" required maxlength="100">
              </div>
              <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control"
                       value="<?= e(post('middle_name')) ?>" maxlength="100">
              </div>
              <div class="col-md-4">
                <label class="form-label">Last Name <span class="required-mark">*</span></label>
                <input type="text" name="last_name" class="form-control"
                       value="<?= e(post('last_name')) ?>" required maxlength="100">
              </div>
              <div class="col-md-1">
                <label class="form-label">Suffix</label>
                <input type="text" name="suffix" class="form-control"
                       value="<?= e(post('suffix')) ?>" maxlength="20">
              </div>

              <div class="col-md-5">
                <label class="form-label">Student Number <small class="text-muted">(if enrolled)</small></label>
                <input type="text" name="student_no" class="form-control"
                       value="<?= e(post('student_no')) ?>" maxlength="30"
                       placeholder="e.g. 2024-0001">
              </div>
              <div class="col-md-7">
                <label class="form-label">Email Address <span class="required-mark">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                  <input type="email" name="email" class="form-control"
                         value="<?= e(post('email')) ?>" required maxlength="150">
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number <span class="required-mark">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="bi bi-phone text-muted"></i></span>
                  <input type="tel" name="contact_number" class="form-control"
                         value="<?= e(post('contact_number')) ?>" required maxlength="20">
                </div>
              </div>
              <div class="col-md-6"></div>

              <div class="col-md-6">
                <label class="form-label">Password <span class="required-mark">*</span></label>
                <input type="password" name="password" class="form-control"
                       required minlength="8" autocomplete="new-password">
                <div class="form-text">At least 8 characters.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password <span class="required-mark">*</span></label>
                <input type="password" name="confirm_password" class="form-control"
                       required autocomplete="new-password">
              </div>
            </div>

            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="agreeTerms" required>
              <label class="form-check-label small" for="agreeTerms">
                I agree that my information will be used to process my registrar transactions.
              </label>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-4 py-2">
              <i class="bi bi-person-plus me-1"></i>Create Account
            </button>
          </form>

          <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">Already have an account?</span>
            <a href="<?= BASE_URL ?>/student/login.php" class="small fw-semibold text-decoration-none ms-1">
              Sign in
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

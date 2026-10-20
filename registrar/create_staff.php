<?php
/**
 * TCC RRS – Registrar: Create Staff Account
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'Create Staff Account';
$errors    = [];
$data      = ['employee_id'=>'','full_name'=>'','email'=>'','phone'=>''];

if (isPost()) {
    abortCsrf();
    $data = [
        'employee_id' => post('employee_id'),
        'full_name'   => post('full_name'),
        'email'       => post('email'),
        'phone'       => post('phone'),
    ];
    $password = post('password');
    $confirm  = post('password_confirm');

    if (empty($data['employee_id'])) $errors[] = 'Employee ID is required.';
    if (empty($data['full_name']))   $errors[] = 'Full name is required.';
    if (empty($data['email']))       $errors[] = 'Email is required.';
    elseif (!validateEmail($data['email'])) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)       $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $exists = db()->fetchValue("SELECT id FROM users WHERE email=? OR employee_id=?",
            [$data['email'], $data['employee_id']]);
        if ($exists) $errors[] = 'An account with that email or employee ID already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        db()->insert(
            "INSERT INTO users (employee_id, full_name, email, password_hash, role, is_active, phone, created_by, must_change_password)
             VALUES (?,?,?,?,'staff',1,?,?,1)",
            [$data['employee_id'], $data['full_name'], $data['email'], $hash, $data['phone'], currentUserId()]
        );
        logActivity('CREATE_STAFF','Staff Management',"Created staff account: {$data['full_name']} ({$data['email']})");
        flash('success', 'Staff account for ' . $data['full_name'] . ' created successfully.');
        redirect(BASE_URL . '/registrar/staff.php');
    }
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Create Staff Account', 'Add a new staff member to the system.', 'person-plus',
    ui_back(BASE_URL . '/registrar/staff.php', 'Back')
) ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger d-flex gap-2">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <ul class="mb-0">
    <?php foreach ($errors as $e): ?>
      <li><?= e($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <?= ui_card_open('Staff Account Details', 'person-plus') ?>
      <div class="content-card-body">
        <form method="POST" data-validate>
          <?= csrfField() ?>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee ID <span class="required-mark">*</span></label>
              <input type="text" name="employee_id" class="form-control"
                     value="<?= e($data['employee_id']) ?>"
                     placeholder="e.g. TCC-STF-002" required maxlength="30">
              <div class="form-text">Must be unique across all users.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="required-mark">*</span></label>
              <input type="text" name="full_name" class="form-control"
                     value="<?= e($data['full_name']) ?>" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address <span class="required-mark">*</span></label>
              <input type="email" name="email" class="form-control"
                     value="<?= e($data['email']) ?>" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control"
                     value="<?= e($data['phone']) ?>" maxlength="20">
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-6">
              <label class="form-label">Password <span class="required-mark">*</span></label>
              <div class="input-group">
                <input type="password" name="password" id="pwd" class="form-control"
                       minlength="8" required placeholder="Min. 8 characters">
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'pwd\',this)"']) ?>
              </div>
              <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm Password <span class="required-mark">*</span></label>
              <div class="input-group">
                <input type="password" name="password_confirm" id="pwd2" class="form-control"
                       required placeholder="Re-enter password">
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'pwd2\',this)"']) ?>
              </div>
            </div>
          </div>

          <div class="mt-4 d-flex gap-2">
            <?= ui_btn('Create Account', ['type'=>'submit','class'=>'px-4','icon'=>'person-check']) ?>
            <?= ui_btn('Cancel', ['variant'=>'outline-secondary','href'=>BASE_URL . '/registrar/staff.php']) ?>
          </div>
          <div class="mt-2">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              The staff member will be required to change their password on first login.
            </small>
          </div>
        </form>
      </div>
    <?= ui_card_close() ?>
  </div>
</div>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script>
function togglePwd(id, btn) {
  const f = document.getElementById(id);
  const isText = f.type === 'text';
  f.type = isText ? 'password' : 'text';
  btn.innerHTML = isText ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
}
</script>

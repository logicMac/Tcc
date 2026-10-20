<?php
/**
 * TCC RRS – Registrar: Edit Staff Account
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$id = getInt('id');
if (!$id) { flash('danger','Invalid staff ID.'); redirect(BASE_URL.'/registrar/staff.php'); }

$staff = db()->fetchOne("SELECT * FROM users WHERE id=? AND role='staff'", [$id]);
if (!$staff) { flash('danger','Staff not found.'); redirect(BASE_URL.'/registrar/staff.php'); }

$pageTitle = 'Edit Staff – ' . $staff['full_name'];
$errors    = [];

if (isPost()) {
    abortCsrf();
    $data = [
        'employee_id' => post('employee_id'),
        'full_name'   => post('full_name'),
        'email'       => post('email'),
        'phone'       => post('phone'),
        'is_active'   => postInt('is_active'),
    ];
    $password = post('password');
    $confirm  = post('password_confirm');

    if (empty($data['employee_id'])) $errors[] = 'Employee ID is required.';
    if (empty($data['full_name']))   $errors[] = 'Full name is required.';
    if (empty($data['email']))       $errors[] = 'Email is required.';
    elseif (!validateEmail($data['email'])) $errors[] = 'Invalid email.';

    if ($password && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($password && $password !== $confirm)  $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check uniqueness excluding self
        $conflict = db()->fetchValue(
            "SELECT id FROM users WHERE (email=? OR employee_id=?) AND id != ?",
            [$data['email'], $data['employee_id'], $id]
        );
        if ($conflict) $errors[] = 'Another account already uses that email or employee ID.';
    }

    if (empty($errors)) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db()->execute(
                "UPDATE users SET employee_id=?, full_name=?, email=?, phone=?, is_active=?, password_hash=? WHERE id=?",
                [$data['employee_id'], $data['full_name'], $data['email'], $data['phone'], $data['is_active'], $hash, $id]
            );
        } else {
            db()->execute(
                "UPDATE users SET employee_id=?, full_name=?, email=?, phone=?, is_active=? WHERE id=?",
                [$data['employee_id'], $data['full_name'], $data['email'], $data['phone'], $data['is_active'], $id]
            );
        }
        logActivity('EDIT_STAFF','Staff Management',"Updated staff: {$data['full_name']}");
        flash('success', 'Staff account updated successfully.');
        redirect(BASE_URL . '/registrar/staff.php');
    }
    $staff = array_merge($staff, $data);
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Edit Staff Account', e($staff['full_name']), 'pencil-square',
    ui_back(BASE_URL . '/registrar/staff.php', 'Back')
) ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger d-flex gap-2">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
  <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <?= ui_card_open('Edit Staff Details', 'pencil') ?>
      <div class="content-card-body">
        <form method="POST" data-validate>
          <?= csrfField() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employee ID <span class="required-mark">*</span></label>
              <input type="text" name="employee_id" class="form-control"
                     value="<?= e($staff['employee_id']) ?>" required maxlength="30">
            </div>
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="required-mark">*</span></label>
              <input type="text" name="full_name" class="form-control"
                     value="<?= e($staff['full_name']) ?>" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address <span class="required-mark">*</span></label>
              <input type="email" name="email" class="form-control"
                     value="<?= e($staff['email']) ?>" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control"
                     value="<?= e($staff['phone'] ?? '') ?>" maxlength="20">
            </div>
            <div class="col-md-6">
              <label class="form-label">Account Status</label>
              <select name="is_active" class="form-select">
                <option value="1" <?= $staff['is_active'] ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= !$staff['is_active'] ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>
            <div class="col-12"><hr class="my-1"><p class="small text-muted mb-2">Leave password fields blank to keep current password.</p></div>
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <div class="input-group">
                <input type="password" name="password" id="pwd" class="form-control"
                       placeholder="Min. 8 characters">
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'pwd\',this)"']) ?>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm New Password</label>
              <div class="input-group">
                <input type="password" name="password_confirm" id="pwd2" class="form-control">
                <?= ui_btn('', ['variant'=>'outline-secondary','icon'=>'eye',
                           'attrs'=>'onclick="togglePwd(\'pwd2\',this)"']) ?>
              </div>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <?= ui_btn('Save Changes', ['type'=>'submit','class'=>'px-4','icon'=>'check-lg']) ?>
            <?= ui_btn('Cancel', ['variant'=>'outline-secondary','href'=>BASE_URL . '/registrar/staff.php']) ?>
          </div>
        </form>
      </div>
    <?= ui_card_close() ?>
  </div>
</div>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script>
function togglePwd(id,btn){
  const f=document.getElementById(id);
  const t=f.type==='text';
  f.type=t?'password':'text';
  btn.innerHTML=t?'<i class="bi bi-eye"></i>':'<i class="bi bi-eye-slash"></i>';
}
</script>

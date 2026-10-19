<?php
/**
 * TCC RRS – Registrar: System Settings
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'System Settings';

if (isPost()) {
    abortCsrf();
    $settingsToUpdate = [
        'school_name','school_short_name','school_address','school_email','school_phone','school_website',
        'smtp_host','smtp_port','smtp_user','smtp_from_name','smtp_encryption',
        'notifications_enabled','current_school_year','current_semester',
    ];
    // Handle password separately (don't overwrite if empty)
    foreach ($settingsToUpdate as $key) {
        $val = post($key);
        db()->execute(
            "UPDATE system_settings SET setting_value=?, updated_by=? WHERE setting_key=?",
            [$val, currentUserId(), $key]
        );
    }
    // SMTP password - only update if provided
    $smtpPass = post('smtp_pass');
    if (!empty($smtpPass)) {
        db()->execute(
            "UPDATE system_settings SET setting_value=?, updated_by=? WHERE setting_key='smtp_pass'",
            [$smtpPass, currentUserId()]
        );
    }
    logActivity('SETTINGS_UPDATE','System Settings','Updated system settings.');
    flash('success','System settings saved successfully.');
    redirect(BASE_URL . '/registrar/settings.php');
}

$s = getAllSettings();

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4>System Settings</h4>
    <p>Configure school information and notification settings.</p>
  </div>
</div>

<form method="POST" data-validate>
  <?= csrfField() ?>
  <div class="row g-3">

    <!-- School Information -->
    <div class="col-lg-6">
      <div class="content-card mb-3">
        <div class="content-card-header">
          <h5><i class="bi bi-building me-2"></i>School Information</h5>
        </div>
        <div class="content-card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">School Name</label>
              <input type="text" name="school_name" class="form-control"
                     value="<?= e($s['school_name'] ?? '') ?>" maxlength="200">
            </div>
            <div class="col-md-4">
              <label class="form-label">Short Name</label>
              <input type="text" name="school_short_name" class="form-control"
                     value="<?= e($s['school_short_name'] ?? '') ?>" maxlength="20">
            </div>
            <div class="col-md-8">
              <label class="form-label">School Email</label>
              <input type="email" name="school_email" class="form-control"
                     value="<?= e($s['school_email'] ?? '') ?>" maxlength="150">
            </div>
            <div class="col-12">
              <label class="form-label">School Address</label>
              <input type="text" name="school_address" class="form-control"
                     value="<?= e($s['school_address'] ?? '') ?>" maxlength="300">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="text" name="school_phone" class="form-control"
                     value="<?= e($s['school_phone'] ?? '') ?>" maxlength="30">
            </div>
            <div class="col-md-6">
              <label class="form-label">Website</label>
              <input type="url" name="school_website" class="form-control"
                     value="<?= e($s['school_website'] ?? '') ?>" maxlength="200">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Academic Settings -->
    <div class="col-lg-6">
      <div class="content-card mb-3">
        <div class="content-card-header">
          <h5><i class="bi bi-mortarboard me-2"></i>Academic Settings</h5>
        </div>
        <div class="content-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Current School Year</label>
              <input type="text" name="current_school_year" class="form-control"
                     value="<?= e($s['current_school_year'] ?? '') ?>"
                     placeholder="e.g. 2026-2027" maxlength="20">
            </div>
            <div class="col-md-6">
              <label class="form-label">Current Semester</label>
              <select name="current_semester" class="form-select">
                <?php foreach (['1st Semester','2nd Semester','Summer'] as $sem): ?>
                <option value="<?= e($sem) ?>" <?= ($s['current_semester'] ?? '') === $sem ? 'selected' : '' ?>>
                  <?= e($sem) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Email Notifications</label>
              <select name="notifications_enabled" class="form-select">
                <option value="1" <?= ($s['notifications_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                <option value="0" <?= ($s['notifications_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Disabled</option>
              </select>
              <div class="form-text">When enabled, students receive email updates on their submissions.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- SMTP Settings -->
      <div class="content-card">
        <div class="content-card-header">
          <h5><i class="bi bi-envelope-at me-2"></i>Email / SMTP Settings</h5>
        </div>
        <div class="content-card-body">
          <div class="alert alert-info py-2 small">
            Configure SMTP settings to enable reliable email notifications via Gmail or other providers.
          </div>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control"
                     value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="col-md-4">
              <label class="form-label">Port</label>
              <input type="number" name="smtp_port" class="form-control"
                     value="<?= e($s['smtp_port'] ?? '587') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP Username</label>
              <input type="email" name="smtp_user" class="form-control"
                     value="<?= e($s['smtp_user'] ?? '') ?>" placeholder="your@gmail.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">SMTP Password</label>
              <input type="password" name="smtp_pass" class="form-control"
                     placeholder="Leave blank to keep current">
              <div class="form-text">For Gmail, use an App Password.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">From Name</label>
              <input type="text" name="smtp_from_name" class="form-control"
                     value="<?= e($s['smtp_from_name'] ?? 'TCC Registrar') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Encryption</label>
              <select name="smtp_encryption" class="form-select">
                <option value="tls" <?= ($s['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recommended)</option>
                <option value="ssl" <?= ($s['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value=""    <?= ($s['smtp_encryption'] ?? '') === ''    ? 'selected' : '' ?>>None</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <button type="submit" class="btn btn-primary px-4">
      <i class="bi bi-save me-1"></i>Save Settings
    </button>
  </div>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

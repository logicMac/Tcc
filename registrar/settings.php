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

    // Test email: verify SMTP credentials without saving
    if (post('send_test') === '1') {
        require_once __DIR__ . '/../includes/notifications.php';
        $to = getSetting('smtp_user') ?: getSetting('school_email');
        if ($to === '') {
            flash('danger', 'No recipient configured — enter an SMTP Username or School Email first.');
        } else {
            $body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;padding:24px">'
                  . '<h3 style="color:#1a5c3a;margin:0 0 8px">SMTP Test Successful</h3>'
                  . '<p style="color:#475569;font-size:14px;margin:0 0 12px">This test email was sent from the TCC Registration &amp; Records System. Your SMTP settings are working correctly.</p>'
                  . '<p style="color:#94a3b8;font-size:12px;margin:0">Sent ' . date('M d, Y h:i A') . '</p></div>';
            $ok = sendEmail($to, '[TCC RRS] SMTP Test Email', $body, 'Registrar');
            if ($ok) {
                logActivity('SETTINGS_UPDATE', 'System Settings', 'Sent a test email to ' . $to);
                flash('success', 'Test email sent to ' . $to . ' — check the inbox (and spam folder).');
            } else {
                $err = $GLOBALS['smtp_last_error'] ?? '';
                flash('danger', 'Test email failed' . ($err ? ': ' . $err : '. Check host, port, username, and app password.') . ($err ? '' : ' If SMTP is not configured, the system fell back to PHP mail() which does not work on WAMP.'));
            }
        }
        redirect(BASE_URL . '/registrar/settings.php');
    }

    $settingsToUpdate = [
        'school_name','school_short_name','school_address','school_email','school_phone','school_website',
        'smtp_host','smtp_port','smtp_user','smtp_from_name','smtp_encryption',
        'notifications_enabled','current_school_year','current_semester',
        'ai_enabled','groq_model',
    ];
    // Handle password separately (don't overwrite if empty)
    foreach ($settingsToUpdate as $key) {
        $val = post($key);
        db()->execute(
            "INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
            [$key, $val, currentUserId()]
        );
    }
    // Secrets - only update if provided (never blank them out)
    foreach (['smtp_pass', 'groq_api_key'] as $secretKey) {
        $secretVal = post($secretKey);
        if (!empty($secretVal)) {
            db()->execute(
                "INSERT INTO system_settings (setting_key, setting_value, updated_by)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
                [$secretKey, $secretVal, currentUserId()]
            );
        }
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

<?= ui_page_header('System Settings', 'Configure school information and notification settings.', 'gear') ?>

<form method="POST" data-validate>
  <?= csrfField() ?>
  <div class="row g-3">

    <!-- School Information -->
    <div class="col-lg-6">
      <?= ui_card_open('School Information', 'building', '', 'mb-3') ?>
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
      <?= ui_card_close() ?>

      <!-- AI Assistant (Groq) -->
      <?= ui_card_open('AI Assistant (Groq)', 'stars') ?>
        <div class="content-card-body">
          <div class="alert alert-info py-2 small">
            TCC Assist appears on every page and can answer questions using live system data.
            Get an API key at <strong>console.groq.com</strong> → API Keys.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Enable Assistant</label>
              <select name="ai_enabled" class="form-select">
                <option value="1" <?= ($s['ai_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Enabled</option>
                <option value="0" <?= ($s['ai_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Disabled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Groq Model</label>
              <input type="text" name="groq_model" class="form-control"
                     value="<?= e($s['groq_model'] ?? 'openai/gpt-oss-120b') ?>"
                     placeholder="openai/gpt-oss-120b">
              <div class="form-text">e.g. <code>openai/gpt-oss-120b</code> or <code>openai/gpt-oss-20b</code></div>
            </div>
            <div class="col-12">
              <label class="form-label">Groq API Key</label>
              <input type="password" name="groq_api_key" class="form-control"
                     placeholder="<?= ($s['groq_api_key'] ?? '') !== '' ? 'Saved — enter a new key to replace' : 'gsk_...' ?>"
                     autocomplete="new-password">
              <div class="form-text">Stored server-side; never sent to browsers. Leave blank to keep the current key.</div>
            </div>
          </div>
        </div>
      <?= ui_card_close() ?>
    </div>

    <!-- Academic Settings -->
    <div class="col-lg-6">
      <?= ui_card_open('Academic Settings', 'mortarboard', '', 'mb-3') ?>
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
      <?= ui_card_close() ?>

      <!-- SMTP Settings -->
      <?= ui_card_open('Email / SMTP Settings', 'envelope-at') ?>
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
      <?= ui_card_close() ?>
    </div>
  </div>

  <div class="mt-3">
    <?= ui_btn('Save Settings', ['type'=>'submit','class'=>'px-4','icon'=>'save']) ?>
  </div>
</form>

<!-- Test email: verify SMTP credentials without saving -->
<form method="POST" class="mt-3 d-flex align-items-center gap-2">
  <?= csrfField() ?>
  <input type="hidden" name="send_test" value="1">
  <?= ui_btn('Send Test Email', ['type'=>'submit','variant'=>'outline-primary','icon'=>'send-check']) ?>
  <span class="text-muted small">Sends a test message to the SMTP username inbox to verify the credentials above.</span>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

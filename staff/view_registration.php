<?php
/**
 * TCC RRS – Staff: View & Process Pre-Registration
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$id  = getInt('id');
if (!$id) { flash('danger','Invalid registration.'); redirect(BASE_URL.'/staff/registrations.php'); }

$reg = db()->fetchOne(
    "SELECT pr.*, p.name AS prog_name, p.code AS prog_code, p.department
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.id = ?", [$id]
);
if (!$reg) { flash('danger','Registration not found.'); redirect(BASE_URL.'/staff/registrations.php'); }

$pageTitle = 'View Registration – ' . $reg['tracking_code'];

// Build back URL preserving any referrer filter state
$backUrl = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'registrations.php') !== false
    ? $_SERVER['HTTP_REFERER']
    : BASE_URL . '/staff/registrations.php';

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Pre-Registrations', 'url' => BASE_URL . '/staff/registrations.php'],
    ['label' => e($reg['tracking_code'])],
];

// ── Handle status update POST ──────────────────────────────────────────────
if (isPost()) {
    abortCsrf();
    $newStatus = post('new_status');
    $remarks   = post('remarks');
    $allowed   = array_keys(REG_STATUSES);

    if (!in_array($newStatus, $allowed)) {
        flash('danger','Invalid status.');
    } else {
        $oldStatus = $reg['status'];
        $processedAt = in_array($newStatus, ['Processing','Approved','Needs Correction','Declined'])
            ? date('Y-m-d H:i:s') : $reg['processed_at'];
        $approvedAt  = $newStatus === 'Approved' ? date('Y-m-d H:i:s') : $reg['approved_at'];

        db()->execute(
            "UPDATE pre_registrations
             SET status = ?, remarks = ?, processed_at = ?, approved_at = ?,
                 assigned_to = ?, updated_at = NOW()
             WHERE id = ?",
            [$newStatus, $remarks, $processedAt, $approvedAt, currentUserId(), $id]
        );

        addStatusHistory('registration', $id, $reg['tracking_code'], $oldStatus, $newStatus, $remarks);
        logActivity('STATUS_UPDATE','Pre-Registration',
            "Updated {$reg['tracking_code']} from {$oldStatus} to {$newStatus}",
            $reg['tracking_code']);

        // Reload and notify
        $reg = db()->fetchOne(
            "SELECT pr.*, p.name AS prog_name, p.code AS prog_code
             FROM pre_registrations pr LEFT JOIN programs p ON p.id = pr.program_id WHERE pr.id = ?",
            [$id]
        );
        if ($newStatus !== $oldStatus) {
            notifyRegistrationStatusUpdate($reg, $oldStatus);
        }

        flash('success', 'Status updated to ' . $newStatus . ' successfully.');
        redirect(BASE_URL . '/staff/view_registration.php?id=' . $id);
    }
}

$history     = getStatusHistory($reg['tracking_code']);
$trackingUrl = trackingUrl($reg['tracking_code']);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><?= e($reg['tracking_code']) ?></h4>
    <p>Pre-Registration Detail &mdash; Submitted <?= formatDateTime($reg['submitted_at']) ?></p>
  </div>
  <div class="page-header-actions">
    <a href="<?= e($backUrl) ?>" class="btn-back">
      <i class="bi bi-arrow-left"></i> Back to List
    </a>
    <a href="<?= e($trackingUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-eye me-1"></i>Student View
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<div class="row g-3">
  <!-- ── Left: Details ── -->
  <div class="col-lg-8">

    <!-- Status Banner -->
    <div class="content-card mb-3">
      <div class="content-card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-3">
            <div>
              <div class="text-muted small">Current Status</div>
              <div><?= statusBadge($reg['status']) ?></div>
            </div>
            <?php if ($reg['processed_at']): ?>
            <div>
              <div class="text-muted small">Processed</div>
              <div class="small fw-semibold"><?= formatDateTime($reg['processed_at']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($reg['approved_at']): ?>
            <div>
              <div class="text-muted small">Approved</div>
              <div class="small fw-semibold"><?= formatDateTime($reg['approved_at']) ?></div>
            </div>
            <?php endif; ?>
          </div>
          <div>
            <span class="font-monospace text-muted small"><?= e($reg['tracking_code']) ?></span>
          </div>
        </div>
        <?php if (!empty($reg['remarks'])): ?>
        <div class="mt-2 p-2 rounded bg-light border-start border-warning border-3 small">
          <strong>Remarks:</strong> <?= e($reg['remarks']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Personal Info -->
    <div class="content-card mb-3">
      <div class="content-card-header">
        <h5><i class="bi bi-person me-2"></i>Personal Information</h5>
      </div>
      <div class="content-card-body">
        <div class="detail-row">
          <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?= e(fullName($reg)) ?></div></div>
          <div class="detail-item"><div class="detail-label">Date of Birth</div><div class="detail-value"><?= formatDate($reg['date_of_birth']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Gender</div><div class="detail-value"><?= e($reg['gender']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Civil Status</div><div class="detail-value"><?= e($reg['civil_status']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Nationality</div><div class="detail-value"><?= e($reg['nationality']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Religion</div><div class="detail-value"><?= e($reg['religion'] ?: '—') ?></div></div>
          <?php if ($reg['lrn']): ?>
          <div class="detail-item"><div class="detail-label">LRN</div><div class="detail-value"><?= e($reg['lrn']) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Contact & Address -->
    <div class="content-card mb-3">
      <div class="content-card-header">
        <h5><i class="bi bi-geo-alt me-2"></i>Contact &amp; Address</h5>
      </div>
      <div class="content-card-body">
        <div class="detail-row">
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><?= e($reg['email']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Contact Number</div><div class="detail-value"><?= e($reg['contact_number']) ?></div></div>
          <div class="detail-item full">
            <div class="detail-label">Address</div>
            <div class="detail-value">
              <?= e($reg['house_no_street']) ?>, <?= e($reg['barangay']) ?>,
              <?= e($reg['municipality_city']) ?>, <?= e($reg['province']) ?>
              <?= $reg['zip_code'] ? ' ' . e($reg['zip_code']) : '' ?>
            </div>
          </div>
          <?php if ($reg['guardian_name']): ?>
          <div class="detail-item"><div class="detail-label">Guardian</div><div class="detail-value"><?= e($reg['guardian_name']) ?> (<?= e($reg['guardian_relation'] ?: '—') ?>)</div></div>
          <div class="detail-item"><div class="detail-label">Guardian Contact</div><div class="detail-value"><?= e($reg['guardian_contact'] ?: '—') ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Academic Info -->
    <div class="content-card mb-3">
      <div class="content-card-header">
        <h5><i class="bi bi-mortarboard me-2"></i>Academic Information</h5>
      </div>
      <div class="content-card-body">
        <div class="detail-row">
          <div class="detail-item full">
            <div class="detail-label">Program</div>
            <div class="detail-value"><?= e($reg['program_other'] ?: ($reg['prog_name'] ?? '—')) ?>
              <?php if ($reg['prog_code']): ?><span class="badge bg-light text-dark ms-1"><?= e($reg['prog_code']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="detail-item"><div class="detail-label">Year Level</div><div class="detail-value"><?= e($reg['year_level']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Enrollment Type</div><div class="detail-value"><?= e($reg['enrollment_type']) ?></div></div>
          <div class="detail-item"><div class="detail-label">School Year</div><div class="detail-value"><?= e($reg['school_year']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Semester</div><div class="detail-value"><?= e($reg['semester']) ?></div></div>
          <?php if ($reg['previous_school']): ?>
          <div class="detail-item full"><div class="detail-label">Previous School</div><div class="detail-value"><?= e($reg['previous_school']) ?> <?= $reg['previous_program'] ? '(' . e($reg['previous_program']) . ')' : '' ?></div></div>
          <?php endif; ?>
          <?php if ($reg['last_year_attended']): ?>
          <div class="detail-item"><div class="detail-label">Last Year Attended</div><div class="detail-value"><?= e($reg['last_year_attended']) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- ── Right: Actions + Timeline ── -->
  <div class="col-lg-4">

    <!-- Update Status -->
    <div class="content-card mb-3">
      <div class="content-card-header">
        <h5><i class="bi bi-arrow-repeat me-2"></i>Update Status</h5>
      </div>
      <div class="content-card-body">
        <form method="POST" action="">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Status</label>
            <select name="new_status" class="form-select" required>
              <?php foreach (REG_STATUSES as $s => $info): ?>
              <option value="<?= e($s) ?>" <?= $reg['status'] === $s ? 'selected' : '' ?>>
                <?= e($s) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Remarks / Instructions</label>
            <textarea name="remarks" class="form-control" rows="3"
                      placeholder="Add remarks visible to the student..."
                      data-max-chars="500"><?= e($reg['remarks']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="updateRegBtn">
            <i class="bi bi-check-lg me-1"></i>Update Status
          </button>
        </form>
      </div>
    </div>

    <!-- QR Code -->
    <div class="content-card mb-3">
      <div class="content-card-header"><h5><i class="bi bi-qr-code me-2"></i>QR Code</h5></div>
      <div class="content-card-body text-center">
        <div class="qr-container d-inline-block mb-2" id="qr-section">
          <div id="qrcode-staff"></div>
          <div class="small text-muted mt-1"><?= e($reg['tracking_code']) ?></div>
        </div>
        <div class="d-flex gap-2 justify-content-center mt-2">
          <button class="btn btn-sm btn-outline-primary" onclick="downloadQr('qr-section','<?= e($reg['tracking_code']) ?>.png')">
            <i class="bi bi-download"></i>
          </button>
          <button class="btn btn-sm btn-outline-secondary" onclick="printSection('qr-section')">
            <i class="bi bi-printer"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="content-card">
      <div class="content-card-header"><h5><i class="bi bi-clock-history me-2"></i>Status History</h5></div>
      <div class="content-card-body">
        <?= statusTimeline($history) ?>
      </div>
    </div>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  generateQrCode('qrcode-staff', '<?= e($trackingUrl) ?>', {width:160,height:160});
});
// Confirm before status update
document.getElementById('updateRegBtn').closest('form').addEventListener('submit', function(e){
  var sel = this.querySelector('[name="new_status"]');
  var remarks = this.querySelector('[name="remarks"]').value.trim();
  var current = '<?= e($reg['status']) ?>';
  if(sel.value === current && !remarks){
    if(!confirm('Status is already "' + sel.value + '" and no remarks were added. Save anyway?')){
      e.preventDefault(); return;
    }
  }
});
</script>

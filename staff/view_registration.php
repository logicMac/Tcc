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

// ── Handle POST actions ────────────────────────────────────────────────────
if (isPost()) {
    abortCsrf();
    $action = post('action', 'update_status');

    // ── Archive / restore ────────────────────────────────────────────────
    if ($action === 'archive' || $action === 'restore') {
        $toArchive = $action === 'archive';
        db()->execute(
            "UPDATE pre_registrations SET is_archived = ?, archived_at = ?, archived_by = ? WHERE id = ?",
            [$toArchive ? 1 : 0, $toArchive ? date('Y-m-d H:i:s') : null, $toArchive ? currentUserId() : null, $id]
        );
        addStatusHistory('registration', $id, $reg['tracking_code'], $reg['status'], $reg['status'],
            $toArchive ? 'Record archived.' : 'Record restored from archive.');
        logActivity($toArchive ? 'RECORD_ARCHIVE' : 'RECORD_RESTORE', 'Records Management',
            ($toArchive ? 'Archived' : 'Restored') . " registration {$reg['tracking_code']}", $reg['tracking_code']);
        flash('success', 'Record ' . ($toArchive ? 'archived' : 'restored') . '.');
        redirect(BASE_URL . '/staff/view_registration.php?id=' . $id);
    }

    // ── Status update ────────────────────────────────────────────────────
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

<?= ui_page_header(
    e($reg['tracking_code']),
    'Pre-Registration Detail — Submitted ' . formatDateTime($reg['submitted_at']),
    '',
    ui_back($backUrl)
    . '<a href="' . BASE_URL . '/staff/edit_registration.php?id=' . (int)$id . '" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit Record</a>'
    . '<a href="' . e($trackingUrl) . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Student View</a>'
    . '<button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>'
) ?>

<div class="row g-3">
  <!-- ── Left: Details ── -->
  <div class="col-lg-8">

    <!-- Status Banner -->
    <?= ui_card_open('', '', '', 'mb-3') ?>
      <div class="content-card-body py-3">
        <?php if ($reg['is_archived']): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 mb-3">
          <i class="bi bi-archive-fill"></i>
          <div class="small">This record is <strong>archived</strong>
            <?php if ($reg['archived_at']): ?>since <?= formatDateTime($reg['archived_at']) ?><?php endif; ?>.
            It is hidden from the default lists but remains fully retrievable.</div>
        </div>
        <?php endif; ?>
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
    <?= ui_card_close() ?>

    <!-- Personal Info -->
    <?= ui_card_open('Personal Information', 'person', '', 'mb-3') ?>
    <div class="content-card-body">
      <?php
      $cit = $reg['citizenship'] ?? '';
      if ($cit === 'Others') $cit .= ' – ' . ($reg['citizenship_specify'] ?? '');
      echo ui_details([
          ['label' => 'Full Name',      'value' => e(fullName($reg))],
          ['label' => 'Date of Birth',  'value' => formatDate($reg['date_of_birth'])],
          ['label' => 'Place of Birth', 'value' => e($reg['birth_place'] ?? '')],
          ['label' => 'Gender',         'value' => e($reg['gender'])],
          ['label' => 'Civil Status',   'value' => e($reg['civil_status'])],
          ['label' => 'Citizenship',    'value' => e($cit ?: ($reg['nationality'] ?? ''))],
          ['label' => 'Religion',       'value' => e($reg['religion'])],
          ['label' => 'LRN',            'value' => e($reg['lrn'])],
          ['label' => 'Spouse',         'value' => e($reg['spouse_name'] ?? '')],
      ]);
      ?>
    </div>
    <?= ui_card_close() ?>

    <!-- Contact & Address -->
    <?= ui_card_open('Contact &amp; Address', 'geo-alt', '', 'mb-3') ?>
    <div class="content-card-body">
      <?php
      $permAddr = e($reg['house_no_street']) . ', ' . e($reg['barangay']) . ', '
                . e($reg['municipality_city']) . ', ' . e($reg['province'])
                . ($reg['zip_code'] ? ' ' . e($reg['zip_code']) : '')
                . ($reg['district'] ?? '' ? ', District ' . e($reg['district']) : '')
                . ($reg['region'] ?? '' ? ', ' . e($reg['region']) : '');
      $mailAddr = !empty($reg['mail_same_as_permanent'])
          ? '<span class="text-muted small">Same as permanent address</span>'
          : e($reg['mail_house_no_street'] ?? '') . ', ' . e($reg['mail_barangay'] ?? '') . ', '
          . e($reg['mail_municipality_city'] ?? '') . ', ' . e($reg['mail_province'] ?? '')
          . (($reg['mail_district'] ?? '') ? ', District ' . e($reg['mail_district']) : '')
          . (($reg['mail_region'] ?? '') ? ', ' . e($reg['mail_region']) : '');
      echo ui_details([
          ['label' => 'Email',            'value' => e($reg['email'])],
          ['label' => 'Mobile',           'value' => e($reg['contact_number'])],
          ['label' => 'Telephone',        'value' => e($reg['telephone_number'] ?? '')],
          ['label' => 'Permanent Address','value' => $permAddr, 'full' => true],
          ['label' => 'Mailing Address',  'value' => $mailAddr, 'full' => true],
          ['label' => 'Father',           'value' => e($reg['father_name'] ?? '') . (($reg['father_status'] ?? '') ? ' <span class="text-muted small">(' . e($reg['father_status']) . ')</span>' : '')],
          ['label' => 'Father Contact',   'value' => e($reg['father_contact'] ?? '')],
          ['label' => 'Mother',           'value' => e($reg['mother_name'] ?? '') . (($reg['mother_status'] ?? '') ? ' <span class="text-muted small">(' . e($reg['mother_status']) . ')</span>' : '')],
          ['label' => 'Mother Contact',   'value' => e($reg['mother_contact'] ?? '')],
          ['label' => 'Guardian',         'value' => e($reg['guardian_name']) . ' <span class="text-muted small">(' . e($reg['guardian_relation'] ?: '—') . ')</span>'],
          ['label' => 'Guardian Contact', 'value' => e($reg['guardian_contact'])],
      ]);
      ?>
    </div>
    <?= ui_card_close() ?>

    <!-- Academic Info -->
    <?= ui_card_open('Academic Information', 'mortarboard', '', 'mb-3') ?>
    <div class="content-card-body">
      <?php
      echo ui_details([
          ['label' => 'Program', 'full' => true,
           'value' => e($reg['program_other'] ?: ($reg['prog_name'] ?? ''))
                    . ($reg['prog_code'] ? ' <span class="badge bg-light text-dark ms-1">' . e($reg['prog_code']) . '</span>' : '')],
          ['label' => 'Year Level',      'value' => e($reg['year_level'])],
          ['label' => 'Enrollment Type', 'value' => e($reg['enrollment_type'])],
          ['label' => 'School Year',     'value' => e($reg['school_year'])],
          ['label' => 'Semester',        'value' => e($reg['semester'])],
          ['label' => 'Elementary School',  'value' => e($reg['elementary_school'] ?? '') . (($reg['elementary_year_graduated'] ?? '') ? ' <span class="text-muted small">(' . e($reg['elementary_year_graduated']) . ')</span>' : '')],
          ['label' => 'Junior High School', 'value' => e($reg['jhs_school'] ?? '') . (($reg['jhs_year_completed'] ?? '') ? ' <span class="text-muted small">(' . e($reg['jhs_year_completed']) . ')</span>' : '')],
          ['label' => 'Senior High School', 'value' => e($reg['shs_school'] ?? '') . (($reg['shs_year_graduated'] ?? '') ? ' <span class="text-muted small">(' . e($reg['shs_year_graduated']) . ')</span>' : '')],
          ['label' => 'College / Previous School', 'value' => e($reg['college_school'] ?? $reg['previous_school'] ?? '')],
          ['label' => 'Previous Program',  'value' => e($reg['previous_program'])],
          ['label' => 'Last Year Attended','value' => e($reg['last_year_attended'])],
      ]);
      ?>
    </div>
    <?= ui_card_close() ?>

  </div><!-- /col-lg-8 -->

  <!-- ── Right: Actions + Timeline ── -->
  <div class="col-lg-4">

    <!-- Update Status -->
    <?= ui_card_open('Update Status', 'arrow-repeat', '', 'mb-3') ?>
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
    <?= ui_card_close() ?>

    <!-- QR Code -->
    <?= ui_card_open('QR Code', 'qr-code', '', 'mb-3') ?>
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
    <?= ui_card_close() ?>

    <!-- Timeline -->
    <?= ui_card_open('Status History', 'clock-history') ?>
      <div class="content-card-body">
        <?= statusTimeline($history) ?>
      </div>
    <?= ui_card_close() ?>

    <!-- Archive / Restore -->
    <?= ui_card_open('', '', '', 'mt-3') ?>
      <div class="content-card-body">
        <form method="POST" onsubmit="return confirm('<?= $reg['is_archived'] ? 'Restore this record from the archive?' : 'Archive this record? It will be hidden from default lists.' ?>')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="<?= $reg['is_archived'] ? 'restore' : 'archive' ?>">
          <?php if ($reg['is_archived']): ?>
          <button type="submit" class="btn btn-outline-success w-100">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore from Archive
          </button>
          <?php else: ?>
          <button type="submit" class="btn btn-outline-secondary w-100">
            <i class="bi bi-archive me-1"></i>Archive This Record
          </button>
          <div class="text-muted small mt-2 text-center">Archived records stay searchable and can be restored anytime.</div>
          <?php endif; ?>
        </form>
      </div>
    <?= ui_card_close() ?>

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

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
if (!$id) { flash('danger','Invalid registration.'); redirect(BASE_URL.'/'.currentRole().'/registrations.php'); }

$reg = db()->fetchOne(
    "SELECT pr.*, p.name AS prog_name, p.code AS prog_code, p.department
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.id = ?", [$id]
);
if (!$reg) { flash('danger','Registration not found.'); redirect(BASE_URL.'/'.currentRole().'/registrations.php'); }

$pageTitle = 'View Registration – ' . $reg['tracking_code'];

// Build back URL preserving any referrer filter state
$backUrl = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'registrations.php') !== false
    ? $_SERVER['HTTP_REFERER']
    : BASE_URL . '/' . currentRole() . '/registrations.php';

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Pre-Registrations', 'url' => BASE_URL . '/' . currentRole() . '/registrations.php'],
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
        redirect(BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . $id);
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
        redirect(BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . $id);
    }
}

$history     = getStatusHistory($reg['tracking_code']);
$trackingUrl = trackingUrl($reg['tracking_code']);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<!-- Print-only document header (long bond) -->
<div class="print-header d-none d-print-block">
  <div class="ph-brand">
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" class="ph-seal" alt="TCC seal">
    <div>
      <div class="ph-school">Tampakan Community College</div>
      <div class="ph-addr">Purok Mahunsay, Brgy. Poblacion, Tampakan, South Cotabato</div>
    </div>
    <div class="ph-meta">
      <?php if (!empty($reg['photo_path'])): ?>
      <img src="<?= UPLOAD_URL . '/' . e($reg['photo_path']) ?>" class="ph-photo" alt="Applicant photo">
      <?php endif; ?>
      <div class="ph-doc">PRE-REGISTRATION RECORD</div>
      <div class="ph-code"><?= e($reg['tracking_code']) ?></div>
      <div class="ph-date">Printed <?= date('F d, Y · h:i A') ?></div>
    </div>
  </div>
</div>

<?= ui_page_header(
    e($reg['tracking_code']),
    'Pre-Registration Detail — Submitted ' . formatDateTime($reg['submitted_at']),
    '',
    ui_back($backUrl)
    . ui_btn('Edit Record', ['size'=>'sm','icon'=>'pencil',
            'href'=>BASE_URL . '/' . currentRole() . '/edit_registration.php?id=' . (int)$id])
    . ui_btn('Student View', ['size'=>'sm','variant'=>'outline-primary','icon'=>'eye',
            'href'=>$trackingUrl, 'attrs'=>'target="_blank"'])
    . ui_btn('Print', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'printer','attrs'=>'onclick="window.print()"'])
) ?>

<div class="row g-3">
  <!-- ── Left: Details ── -->
  <div class="col-lg-8">

    <!-- Applicant Card: photo + identity + status -->
    <?php
    $photoUrl  = (!empty($reg['photo_path']) && is_file(UPLOAD_DIR . '/' . $reg['photo_path']))
        ? UPLOAD_URL . '/' . $reg['photo_path'] : '';
    $applicant = fullName($reg);
    $apInit    = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', trim($applicant)), 0, 2)));
    ?>
    <?= ui_card_open('', '', '', 'mb-3 applicant-card') ?>
      <div class="content-card-body py-3">
        <?php if ($reg['is_archived']): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 mb-3">
          <i class="bi bi-archive-fill"></i>
          <div class="small">This record is <strong>archived</strong>
            <?php if ($reg['archived_at']): ?>since <?= formatDateTime($reg['archived_at']) ?><?php endif; ?>.
            It is hidden from the default lists but remains fully retrievable.</div>
        </div>
        <?php endif; ?>
        <div class="applicant-row">
          <div class="applicant-photo">
            <?php if ($photoUrl): ?>
            <button type="button" class="doc-view ap-photo-btn"
                    data-url="<?= e($photoUrl) ?>"
                    data-label="Applicant Photo — <?= e($applicant) ?>"
                    title="Click to enlarge">
              <img src="<?= e($photoUrl) ?>" alt="2x2 applicant photo of <?= e($applicant) ?>">
            </button>
            <?php else: ?>
            <span class="ap-photo-empty" title="No photo submitted"><?= e($apInit) ?></span>
            <?php endif; ?>
          </div>
          <div class="applicant-main">
            <div class="ap-name"><?= e($applicant) ?></div>
            <div class="ap-prog">
              <span class="badge bg-light text-dark"><?= e($reg['prog_code'] ?: ($reg['program_other'] ?: '—')) ?></span>
              <span class="text-muted small"><?= e($reg['prog_name'] ?? '') ?> · <?= e($reg['year_level']) ?></span>
            </div>
            <div class="ap-meta">
              <span><i class="bi bi-envelope me-1"></i><?= e($reg['email']) ?></span>
              <span><i class="bi bi-phone me-1"></i><?= e($reg['contact_number']) ?></span>
              <?php if (!empty($reg['lrn'])): ?>
              <span><i class="bi bi-card-text me-1"></i>LRN <?= e($reg['lrn']) ?></span>
              <?php endif; ?>
              <span><?= ui_code($reg['tracking_code']) ?></span>
            </div>
          </div>
          <div class="applicant-status">
            <div class="ap-status-label">Current Status</div>
            <div><?= statusBadge($reg['status']) ?></div>
            <?php if ($reg['processed_at']): ?>
            <div class="ap-status-date">Processed <?= formatDateTime($reg['processed_at']) ?></div>
            <?php endif; ?>
            <?php if ($reg['approved_at']): ?>
            <div class="ap-status-date">Approved <?= formatDateTime($reg['approved_at']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($reg['remarks'])): ?>
        <div class="mt-3 p-2 rounded bg-light border-start border-warning border-3 small">
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

    <!-- School Credentials -->
    <?= ui_card_open('School Credentials', 'folder-check', '', 'mb-3') ?>
    <div class="content-card-body">
      <?php
      $submitted = json_decode($reg['credentials_submitted'] ?? '', true) ?: [];
      $presented = json_decode($reg['credentials_presented'] ?? '', true) ?: [];
      ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Document</th>
              <th>Attached Scan</th>
              <th class="text-center" style="width:120px;">Original<br>Presented</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (credentialCatalog() as $ck => $cred): ?>
            <?php if (!empty($cred['transferee']) && $reg['enrollment_type'] !== 'Transferee' && empty($submitted[$ck]) && empty($presented[$ck])) continue; ?>
            <tr>
              <td>
                <div class="small fw-semibold"><?= e($cred['label']) ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($cred['note']) ?></div>
              </td>
              <td>
                <?php if (!empty($submitted[$ck])): ?>
                <?= ui_btn('View', ['size'=>'sm','variant'=>'outline-primary','icon'=>'paperclip','class'=>'doc-view',
                           'attrs'=>'data-url="' . e(UPLOAD_URL . '/' . $submitted[$ck]) . '" data-label="' . e($cred['label']) . '"']) ?>
                <span class="d-none d-print-inline small">&#10003; Attached</span>
                <?php else: ?>
                <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if (!empty($presented[$ck])): ?>
                <span class="badge badge-soft-success"><i class="bi bi-check-lg me-1"></i>Yes</span>
                <?php else: ?>
                <span class="badge badge-soft-secondary">No</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted mt-2" style="font-size:.7rem;">
        Tick originals under <a href="<?= BASE_URL ?>/<?= currentRole() ?>/edit_registration.php?id=<?= (int)$id ?>">Edit Record</a>
        once the student presents them at the office.
      </div>
    </div>
    <?= ui_card_close() ?>

  </div><!-- /col-lg-8 -->

  <!-- ── Right: Actions + Timeline ── -->
  <div class="col-lg-4">

    <!-- Update Status -->
    <?= ui_card_open('Update Status', 'arrow-repeat', '', 'mb-3 d-print-none') ?>
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
          <?= ui_btn('Update Status', ['type'=>'submit','class'=>'w-100','icon'=>'check-lg',
                     'attrs'=>'id="updateRegBtn"']) ?>
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
          <?= ui_btn('', ['size'=>'sm','variant'=>'outline-primary','icon'=>'download',
                     'attrs'=>'onclick="downloadQr(\'qr-section\',\'' . e($reg['tracking_code']) . '.png\')"']) ?>
          <?= ui_btn('', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'printer',
                     'attrs'=>'onclick="printSection(\'qr-section\')"']) ?>
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
    <?= ui_card_open('', '', '', 'mt-3 d-print-none') ?>
      <div class="content-card-body">
        <form method="POST" onsubmit="return confirm('<?= $reg['is_archived'] ? 'Restore this record from the archive?' : 'Archive this record? It will be hidden from default lists.' ?>')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="<?= $reg['is_archived'] ? 'restore' : 'archive' ?>">
          <?php if ($reg['is_archived']): ?>
          <?= ui_btn('Restore from Archive', ['type'=>'submit','variant'=>'outline-success','class'=>'w-100','icon'=>'arrow-counterclockwise']) ?>
          <?php else: ?>
          <?= ui_btn('Archive This Record', ['type'=>'submit','variant'=>'outline-secondary','class'=>'w-100','icon'=>'archive']) ?>
          <div class="text-muted small mt-2 text-center">Archived records stay searchable and can be restored anytime.</div>
          <?php endif; ?>
        </form>
      </div>
    <?= ui_card_close() ?>

  </div><!-- /col-lg-4 -->
</div><!-- /row -->

<!-- Print-only signature block -->
<div class="print-footer d-none d-print-block">
  <div class="pf-row">
    <div class="pf-sig"><div class="pf-line"></div><span>Student's Signature over Printed Name / Date</span></div>
    <div class="pf-sig"><div class="pf-line"></div><span>Verified by — Registrar's Office / Date</span></div>
  </div>
  <div class="pf-note">
    Generated by the TCC Registration &amp; Records System &middot; Verify this record at
    <?= e(preg_replace('#^https?://#', '', $trackingUrl)) ?> &middot; <?= e($reg['tracking_code']) ?>
  </div>
</div>

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

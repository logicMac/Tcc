<?php
/**
 * TCC RRS – Staff: View & Process Document Request
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$id = getInt('id');
if (!$id) { flash('danger','Invalid request.'); redirect(BASE_URL.'/'.currentRole().'/document_requests.php'); }

$req = db()->fetchOne(
    "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code, dt.fee AS doc_fee, dt.processing_days
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.id = ?", [$id]
);
if (!$req) { flash('danger','Request not found.'); redirect(BASE_URL.'/'.currentRole().'/document_requests.php'); }

$pageTitle = 'View Document Request – ' . $req['tracking_code'];

$backUrl = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'document_requests.php') !== false
    ? $_SERVER['HTTP_REFERER']
    : BASE_URL . '/' . currentRole() . '/document_requests.php';

$breadcrumbs = [
    ['label' => 'Document Requests', 'url' => BASE_URL . '/' . currentRole() . '/document_requests.php'],
    ['label' => e($req['tracking_code'])],
];

if (isPost()) {
    abortCsrf();
    $action = post('action', 'update_status');

    // ── Archive / restore ────────────────────────────────────────────────
    if ($action === 'archive' || $action === 'restore') {
        $toArchive = $action === 'archive';
        db()->execute(
            "UPDATE document_requests SET is_archived = ?, archived_at = ?, archived_by = ? WHERE id = ?",
            [$toArchive ? 1 : 0, $toArchive ? date('Y-m-d H:i:s') : null, $toArchive ? currentUserId() : null, $id]
        );
        addStatusHistory('document_request', $id, $req['tracking_code'], $req['status'], $req['status'],
            $toArchive ? 'Record archived.' : 'Record restored from archive.');
        logActivity($toArchive ? 'RECORD_ARCHIVE' : 'RECORD_RESTORE', 'Records Management',
            ($toArchive ? 'Archived' : 'Restored') . " document request {$req['tracking_code']}", $req['tracking_code']);
        flash('success', 'Record ' . ($toArchive ? 'archived' : 'restored') . '.');
        redirect(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . $id);
    }

    // ── Clear pickup appointment ─────────────────────────────────────────
    if ($action === 'clear_pickup') {
        db()->execute(
            "UPDATE document_requests SET pickup_date = NULL, pickup_slot = NULL, pickup_booked_at = NULL WHERE id = ?",
            [$id]
        );
        addStatusHistory('document_request', $id, $req['tracking_code'], $req['status'], $req['status'],
            'Pickup appointment cleared by staff.');
        logActivity('PICKUP_CLEAR', 'Document Request',
            "Cleared pickup appointment for {$req['tracking_code']}", $req['tracking_code']);
        flash('success', 'Pickup appointment cleared.');
        redirect(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . $id);
    }

    // ── Issue official document (creates QR-verifiable issued record) ────
    if ($action === 'issue') {
        $existing = db()->fetchValue("SELECT id FROM issued_documents WHERE document_request_id = ?", [$id]);
        if ($existing) {
            flash('warning', 'A verification record already exists for this request.');
            redirect(BASE_URL . '/' . currentRole() . '/issue_document.php?id=' . $id);
        }
        $vCode    = generateVerificationCode();
        $typeName = $req['document_type_other'] ?: ($req['doc_name'] ?? 'Document');
        db()->insert(
            "INSERT INTO issued_documents
                (verification_code, document_request_id, tracking_code, document_type,
                 issued_to, student_id, copies, purpose, issued_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$vCode, $id, $req['tracking_code'], $typeName,
             fullName($req), $req['student_id'] ?: null, (int)$req['copies'], $req['purpose'],
             currentUserId()]
        );
        logActivity('DOC_ISSUE', 'QR Verification',
            "Issued verified document {$vCode} for {$req['tracking_code']}", $vCode);
        redirect(BASE_URL . '/' . currentRole() . '/issue_document.php?id=' . $id);
    }

    $newStatus = post('new_status');
    $remarks   = post('remarks');
    $allowed   = array_keys(DOC_STATUSES);

    if (!in_array($newStatus, $allowed)) {
        flash('danger','Invalid status.');
    } else {
        $oldStatus   = $req['status'];
        $processedAt = in_array($newStatus, ['Processing','Approved','Ready for Release','Needs Correction','Declined'])
            ? ($req['processed_at'] ?: date('Y-m-d H:i:s')) : $req['processed_at'];
        $releasedAt  = $newStatus === 'Released' ? date('Y-m-d H:i:s') : $req['released_at'];

        db()->execute(
            "UPDATE document_requests
             SET status = ?, remarks = ?, processed_at = ?, released_at = ?,
                 assigned_to = ?, updated_at = NOW()
             WHERE id = ?",
            [$newStatus, $remarks, $processedAt, $releasedAt, currentUserId(), $id]
        );

        addStatusHistory('document_request', $id, $req['tracking_code'], $oldStatus, $newStatus, $remarks);
        logActivity('STATUS_UPDATE','Document Request',
            "Updated {$req['tracking_code']} from {$oldStatus} to {$newStatus}",
            $req['tracking_code']);

        $req = db()->fetchOne(
            "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code, dt.fee AS doc_fee
             FROM document_requests dr LEFT JOIN document_types dt ON dt.id = dr.document_type_id WHERE dr.id = ?",
            [$id]
        );
        if ($newStatus !== $oldStatus) {
            notifyDocumentRequestStatusUpdate($req, $oldStatus);
        }

        flash('success', 'Status updated to ' . $newStatus . '.');
        redirect(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . $id);
    }
}

$history     = getStatusHistory($req['tracking_code']);
$trackingUrl = trackingUrl($req['tracking_code']);
$docTypeName = $req['document_type_other'] ?: ($req['doc_name'] ?? 'Document');

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    e($req['tracking_code']),
    'Document Request — Submitted ' . formatDateTime($req['submitted_at']),
    '',
    ui_back($backUrl)
    . ui_btn('Edit Record', ['size'=>'sm','icon'=>'pencil',
            'href'=>BASE_URL . '/' . currentRole() . '/edit_document.php?id=' . (int)$id])
    . ui_btn('Student View', ['size'=>'sm','variant'=>'outline-primary','icon'=>'eye',
            'href'=>$trackingUrl, 'attrs'=>'target="_blank"'])
    . ui_btn('Print', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'printer','attrs'=>'onclick="window.print()"'])
) ?>

<div class="row g-3">
  <!-- ── Left ── -->
  <div class="col-lg-8">

    <!-- Status Banner -->
    <?= ui_card_open('', '', '', 'mb-3') ?>
      <div class="content-card-body py-3">
        <?php if ($req['is_archived']): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 mb-3">
          <i class="bi bi-archive-fill"></i>
          <div class="small">This record is <strong>archived</strong>
            <?php if ($req['archived_at']): ?>since <?= formatDateTime($req['archived_at']) ?><?php endif; ?>.</div>
        </div>
        <?php endif; ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-4">
            <div>
              <div class="text-muted small">Current Status</div>
              <div><?= statusBadge($req['status'],'document_request') ?></div>
            </div>
            <?php if ($req['processed_at']): ?>
            <div>
              <div class="text-muted small">Processed</div>
              <div class="small fw-semibold"><?= formatDateTime($req['processed_at']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($req['released_at']): ?>
            <div>
              <div class="text-muted small">Released</div>
              <div class="small fw-semibold"><?= formatDateTime($req['released_at']) ?></div>
            </div>
            <?php endif; ?>
          </div>
          <?= ui_code($req['tracking_code']) ?>
        </div>
        <?php if (!empty($req['remarks'])): ?>
        <div class="mt-2 p-2 rounded bg-light border-start border-warning border-3 small">
          <strong>Remarks:</strong> <?= e($req['remarks']) ?>
        </div>
        <?php endif; ?>
      </div>
    <?= ui_card_close() ?>

    <!-- Student Info -->
    <?= ui_card_open('Student Information', 'person', '', 'mb-3') ?>
      <div class="content-card-body">
        <?= ui_details([
            ['label' => 'Full Name',      'value' => e(fullName($req))],
            ['label' => 'Student ID',     'value' => e($req['student_id'])],
            ['label' => 'Email',          'value' => e($req['email'])],
            ['label' => 'Contact Number', 'value' => e($req['contact_number'])],
            ['label' => 'Address',        'value' => e($req['address']), 'full' => true],
            ['label' => 'Program',        'value' => e($req['program'])],
            ['label' => 'Year Graduated', 'value' => e($req['year_graduated'])],
        ]) ?>
      </div>
    <?= ui_card_close() ?>

    <!-- Document Info -->
    <?= ui_card_open('Document Details', 'file-earmark-text', '', 'mb-3') ?>
      <div class="content-card-body">
        <?= ui_details([
            ['label' => 'Document Type', 'full' => true,
             'value' => '<span class="fw-bold">' . e($docTypeName) . '</span>'
                      . ($req['doc_code'] ? ' <span class="badge bg-success ms-1">' . e($req['doc_code']) . '</span>' : '')],
            ['label' => 'Copies',          'value' => (int)$req['copies']],
            ['label' => 'Fee',             'value' => formatCurrency((float)($req['doc_fee'] ?? 0))],
            ['label' => 'Purpose',         'value' => e($req['purpose']), 'full' => true],
            ['label' => 'Delivery Method', 'value' => e($req['delivery_method'])],
            ['label' => 'Scheduled Pickup', 'full' => true,
             'value' => !empty($req['pickup_date'])
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">'
                  . '<i class="bi bi-calendar-check me-1"></i>'
                  . e(formatDate($req['pickup_date'], 'D, M j, Y')) . ' · ' . e($req['pickup_slot'])
                  . '</span>'
                : '<span class="text-muted">—</span>'],
            ['label' => 'Date Needed',     'value' => $req['date_needed'] ? formatDate($req['date_needed']) : ''],
            ['label' => 'Additional Info', 'value' => e($req['additional_info']), 'full' => true],
        ]) ?>
      </div>
    <?= ui_card_close() ?>

  </div><!-- /col -->

  <!-- ── Right ── -->
  <div class="col-lg-4">

    <!-- Update Status -->
    <?= ui_card_open('Update Status', 'arrow-repeat', '', 'mb-3') ?>
      <div class="content-card-body">
        <form method="POST">
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Status</label>
            <select name="new_status" class="form-select" required>
              <?php foreach (DOC_STATUSES as $s => $info): ?>
              <option value="<?= e($s) ?>" <?= $req['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3"
                      placeholder="Visible to the student..."
                      data-max-chars="500"><?= e($req['remarks']) ?></textarea>
          </div>
          <?= ui_btn('Update Status', ['type'=>'submit','class'=>'w-100','icon'=>'check-lg',
                     'attrs'=>'id="updateDocBtn"']) ?>
        </form>
      </div>
    <?= ui_card_close() ?>

    <!-- QR Code -->
    <?= ui_card_open('QR Code', 'qr-code', '', 'mb-3') ?>
      <div class="content-card-body text-center">
        <div class="qr-container d-inline-block mb-2" id="qr-section">
          <div id="qrcode-doc"></div>
          <div class="small text-muted mt-1"><?= e($req['tracking_code']) ?></div>
        </div>
        <div class="d-flex gap-2 justify-content-center mt-2">
          <?= ui_btn('', ['size'=>'sm','variant'=>'outline-primary','icon'=>'download',
                     'attrs'=>'onclick="downloadQr(\'qr-section\',\'' . e($req['tracking_code']) . '.png\')"']) ?>
          <?= ui_btn('', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'printer',
                     'attrs'=>'onclick="printSection(\'qr-section\')"']) ?>
        </div>
      </div>
    <?= ui_card_close() ?>

    <!-- Pickup appointment -->
    <?php if (!empty($req['pickup_date'])): ?>
    <?= ui_card_open('Pickup Appointment', 'calendar-check', '', 'mb-3') ?>
      <div class="content-card-body">
        <div class="fw-semibold"><?= formatDate($req['pickup_date'], 'l, F j, Y') ?></div>
        <div class="text-muted small mb-2"><?= e($req['pickup_slot']) ?>
          <?php if ($req['pickup_booked_at']): ?>· booked <?= timeAgo($req['pickup_booked_at']) ?><?php endif; ?>
        </div>
        <form method="POST" onsubmit="return confirm('Clear this pickup appointment?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="clear_pickup">
          <?= ui_btn('Clear Appointment', ['type'=>'submit','size'=>'sm','variant'=>'outline-danger','class'=>'w-100','icon'=>'x-circle']) ?>
        </form>
      </div>
    <?= ui_card_close() ?>
    <?php endif; ?>

    <!-- Timeline -->
    <?= ui_card_open('Status History', 'clock-history') ?>
      <div class="content-card-body">
        <?= statusTimeline($history, 'document_request') ?>
      </div>
    <?= ui_card_close() ?>

    <!-- Issue verified document -->
    <?php
    $issued = db()->fetchOne("SELECT * FROM issued_documents WHERE document_request_id = ?", [$id]);
    $canIssue = !$issued && in_array($req['status'], ['Approved','Ready for Release','Released'], true);
    ?>
    <?php if ($issued || $canIssue): ?>
    <?= ui_card_open('Document Verification', 'patch-check', '', 'mt-3') ?>
      <div class="content-card-body">
        <?php if ($issued): ?>
        <div class="small mb-2">
          <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Document Issued</span>
        </div>
        <div class="mb-1"><?= ui_code($issued['verification_code']) ?></div>
        <div class="text-muted small mb-3">Issued <?= formatDateTime($issued['issued_at']) ?></div>
        <?= ui_btn('View Verification Slip', ['size'=>'sm','variant'=>'outline-primary','class'=>'w-100','icon'=>'qr-code',
                   'href'=>BASE_URL . '/' . currentRole() . '/issue_document.php?id=' . (int)$id]) ?>
        <?php else: ?>
        <p class="text-muted small mb-2">
          Generate a QR-verifiable release slip for this document. Recipients can verify
          authenticity at the public verification page.
        </p>
        <form method="POST" onsubmit="return confirm('Issue a verified document record for this request?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="issue">
          <?= ui_btn('Issue Verified Document', ['type'=>'submit','size'=>'sm','variant'=>'outline-success','class'=>'w-100','icon'=>'patch-check']) ?>
        </form>
        <?php endif; ?>
      </div>
    <?= ui_card_close() ?>
    <?php endif; ?>

    <!-- Archive / Restore -->
    <?= ui_card_open('', '', '', 'mt-3') ?>
      <div class="content-card-body">
        <form method="POST" onsubmit="return confirm('<?= $req['is_archived'] ? 'Restore this record from the archive?' : 'Archive this record? It will be hidden from default lists.' ?>')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="<?= $req['is_archived'] ? 'restore' : 'archive' ?>">
          <?php if ($req['is_archived']): ?>
          <?= ui_btn('Restore from Archive', ['type'=>'submit','variant'=>'outline-success','class'=>'w-100','icon'=>'arrow-counterclockwise']) ?>
          <?php else: ?>
          <?= ui_btn('Archive This Record', ['type'=>'submit','variant'=>'outline-secondary','class'=>'w-100','icon'=>'archive']) ?>
          <?php endif; ?>
        </form>
      </div>
    <?= ui_card_close() ?>

  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  generateQrCode('qrcode-doc', '<?= e($trackingUrl) ?>', {width:160,height:160});
});
document.getElementById('updateDocBtn').closest('form').addEventListener('submit', function(e){
  var sel = this.querySelector('[name="new_status"]');
  var remarks = this.querySelector('[name="remarks"]').value.trim();
  var current = '<?= e($req['status']) ?>';
  if(sel.value === current && !remarks){
    if(!confirm('Status is already "' + sel.value + '" and no remarks were added. Save anyway?')){
      e.preventDefault(); return;
    }
  }
});
</script>

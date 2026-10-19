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
if (!$id) { flash('danger','Invalid request.'); redirect(BASE_URL.'/staff/document_requests.php'); }

$req = db()->fetchOne(
    "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code, dt.fee AS doc_fee, dt.processing_days
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.id = ?", [$id]
);
if (!$req) { flash('danger','Request not found.'); redirect(BASE_URL.'/staff/document_requests.php'); }

$pageTitle = 'View Document Request – ' . $req['tracking_code'];

$backUrl = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'document_requests.php') !== false
    ? $_SERVER['HTTP_REFERER']
    : BASE_URL . '/staff/document_requests.php';

$breadcrumbs = [
    ['label' => 'Document Requests', 'url' => BASE_URL . '/staff/document_requests.php'],
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
        redirect(BASE_URL . '/staff/view_document.php?id=' . $id);
    }

    // ── Issue official document (creates QR-verifiable issued record) ────
    if ($action === 'issue') {
        $existing = db()->fetchValue("SELECT id FROM issued_documents WHERE document_request_id = ?", [$id]);
        if ($existing) {
            flash('warning', 'A verification record already exists for this request.');
            redirect(BASE_URL . '/staff/issue_document.php?id=' . $id);
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
        redirect(BASE_URL . '/staff/issue_document.php?id=' . $id);
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
        redirect(BASE_URL . '/staff/view_document.php?id=' . $id);
    }
}

$history     = getStatusHistory($req['tracking_code']);
$trackingUrl = trackingUrl($req['tracking_code']);
$docTypeName = $req['document_type_other'] ?: ($req['doc_name'] ?? 'Document');

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><?= e($req['tracking_code']) ?></h4>
    <p>Document Request – Submitted <?= formatDateTime($req['submitted_at']) ?></p>
  </div>
  <div class="page-header-actions">
    <a href="<?= e($backUrl) ?>" class="btn-back">
      <i class="bi bi-arrow-left"></i> Back to List
    </a>
    <a href="<?= BASE_URL ?>/staff/edit_document.php?id=<?= (int)$id ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-pencil me-1"></i>Edit Record
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
  <!-- ── Left ── -->
  <div class="col-lg-8">

    <!-- Status Banner -->
    <div class="content-card mb-3">
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
          <span class="font-monospace text-muted small"><?= e($req['tracking_code']) ?></span>
        </div>
        <?php if (!empty($req['remarks'])): ?>
        <div class="mt-2 p-2 rounded bg-light border-start border-warning border-3 small">
          <strong>Remarks:</strong> <?= e($req['remarks']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Student Info -->
    <div class="content-card mb-3">
      <div class="content-card-header"><h5><i class="bi bi-person me-2"></i>Student Information</h5></div>
      <div class="content-card-body">
        <div class="detail-row">
          <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?= e(fullName($req)) ?></div></div>
          <div class="detail-item"><div class="detail-label">Student ID</div><div class="detail-value"><?= e($req['student_id'] ?: '—') ?></div></div>
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><?= e($req['email']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Contact Number</div><div class="detail-value"><?= e($req['contact_number']) ?></div></div>
          <?php if ($req['address']): ?>
          <div class="detail-item full"><div class="detail-label">Address</div><div class="detail-value"><?= e($req['address']) ?></div></div>
          <?php endif; ?>
          <?php if ($req['program']): ?>
          <div class="detail-item"><div class="detail-label">Program</div><div class="detail-value"><?= e($req['program']) ?></div></div>
          <?php endif; ?>
          <?php if ($req['year_graduated']): ?>
          <div class="detail-item"><div class="detail-label">Year Graduated</div><div class="detail-value"><?= e($req['year_graduated']) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Document Info -->
    <div class="content-card mb-3">
      <div class="content-card-header"><h5><i class="bi bi-file-earmark-text me-2"></i>Document Details</h5></div>
      <div class="content-card-body">
        <div class="detail-row">
          <div class="detail-item full">
            <div class="detail-label">Document Type</div>
            <div class="detail-value fw-bold"><?= e($docTypeName) ?>
              <?php if ($req['doc_code']): ?>
                <span class="badge bg-success ms-1"><?= e($req['doc_code']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="detail-item"><div class="detail-label">Copies</div><div class="detail-value"><?= (int)$req['copies'] ?></div></div>
          <div class="detail-item"><div class="detail-label">Fee</div><div class="detail-value"><?= formatCurrency((float)($req['doc_fee']??0)) ?></div></div>
          <div class="detail-item full"><div class="detail-label">Purpose</div><div class="detail-value"><?= e($req['purpose']) ?></div></div>
          <div class="detail-item"><div class="detail-label">Delivery Method</div><div class="detail-value"><?= e($req['delivery_method']) ?></div></div>
          <?php if ($req['date_needed']): ?>
          <div class="detail-item"><div class="detail-label">Date Needed</div><div class="detail-value"><?= formatDate($req['date_needed']) ?></div></div>
          <?php endif; ?>
          <?php if ($req['additional_info']): ?>
          <div class="detail-item full"><div class="detail-label">Additional Info</div><div class="detail-value"><?= e($req['additional_info']) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /col -->

  <!-- ── Right ── -->
  <div class="col-lg-4">

    <!-- Update Status -->
    <div class="content-card mb-3">
      <div class="content-card-header"><h5><i class="bi bi-arrow-repeat me-2"></i>Update Status</h5></div>
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
          <button type="submit" class="btn btn-primary w-100" id="updateDocBtn">
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
          <div id="qrcode-doc"></div>
          <div class="small text-muted mt-1"><?= e($req['tracking_code']) ?></div>
        </div>
        <div class="d-flex gap-2 justify-content-center mt-2">
          <button class="btn btn-sm btn-outline-primary" onclick="downloadQr('qr-section','<?= e($req['tracking_code']) ?>.png')">
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
        <?= statusTimeline($history, 'document_request') ?>
      </div>
    </div>

    <!-- Issue verified document -->
    <?php
    $issued = db()->fetchOne("SELECT * FROM issued_documents WHERE document_request_id = ?", [$id]);
    $canIssue = !$issued && in_array($req['status'], ['Approved','Ready for Release','Released'], true);
    ?>
    <?php if ($issued || $canIssue): ?>
    <div class="content-card mt-3">
      <div class="content-card-header"><h5><i class="bi bi-patch-check me-2"></i>Document Verification</h5></div>
      <div class="content-card-body">
        <?php if ($issued): ?>
        <div class="small mb-2">
          <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Document Issued</span>
        </div>
        <div class="font-monospace small mb-1"><?= e($issued['verification_code']) ?></div>
        <div class="text-muted small mb-3">Issued <?= formatDateTime($issued['issued_at']) ?></div>
        <a href="<?= BASE_URL ?>/staff/issue_document.php?id=<?= (int)$id ?>" class="btn btn-outline-primary btn-sm w-100">
          <i class="bi bi-qr-code me-1"></i>View Verification Slip
        </a>
        <?php else: ?>
        <p class="text-muted small mb-2">
          Generate a QR-verifiable release slip for this document. Recipients can verify
          authenticity at the public verification page.
        </p>
        <form method="POST" onsubmit="return confirm('Issue a verified document record for this request?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="issue">
          <button type="submit" class="btn btn-outline-success btn-sm w-100">
            <i class="bi bi-patch-check me-1"></i>Issue Verified Document
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Archive / Restore -->
    <div class="content-card mt-3">
      <div class="content-card-body">
        <form method="POST" onsubmit="return confirm('<?= $req['is_archived'] ? 'Restore this record from the archive?' : 'Archive this record? It will be hidden from default lists.' ?>')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="<?= $req['is_archived'] ? 'restore' : 'archive' ?>">
          <?php if ($req['is_archived']): ?>
          <button type="submit" class="btn btn-outline-success w-100">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore from Archive
          </button>
          <?php else: ?>
          <button type="submit" class="btn btn-outline-secondary w-100">
            <i class="bi bi-archive me-1"></i>Archive This Record
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>

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

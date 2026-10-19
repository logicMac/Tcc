<?php
/**
 * TCC RRS – Staff: QR Code Verification Tool
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'QR Verification';
$code      = strtoupper(trim(get('code')));
$record    = null;
$type      = null;

if ($code) {
    $code = preg_replace('/[^A-Z0-9\-]/', '', $code);
    $reg  = db()->fetchOne(
        "SELECT pr.*, p.name AS prog_name FROM pre_registrations pr
         LEFT JOIN programs p ON p.id = pr.program_id WHERE pr.tracking_code = ?", [$code]
    );
    if ($reg) { $record = $reg; $type = 'registration'; }
    else {
        $doc = db()->fetchOne(
            "SELECT dr.*, dt.name AS doc_name FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id WHERE dr.tracking_code = ?", [$code]
        );
        if ($doc) { $record = $doc; $type = 'document_request'; }
        else {
            // Issued-document verification codes (TCC-VRF-*)
            $issued = getIssuedDocument($code);
            if ($issued) { $record = $issued; $type = 'issued_document'; }
        }
    }
    if ($record) {
        logActivity('QR_VERIFY', 'QR Verification', "Verified QR/code: {$code}", $code);
    }
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
  <div class="page-header-info">
    <h4>QR Code Verification</h4>
    <p>Scan a student's QR code or enter their tracking code to verify their submission.</p>
  </div>
</div>

<div class="row g-3">
  <!-- Scan / Input -->
  <div class="col-lg-5">
    <div class="content-card mb-3">
      <div class="content-card-header"><h5><i class="bi bi-qr-code-scan me-2"></i>Scan or Enter Code</h5></div>
      <div class="content-card-body">
        <form method="GET" class="mb-3">
          <label class="form-label small fw-semibold">Tracking Code</label>
          <div class="d-flex gap-2">
            <input type="text" name="code" class="form-control"
                   value="<?= e($code) ?>"
                   placeholder="TCC-REG-2026-000001"
                   style="text-transform:uppercase;"
                   id="codeInput" autofocus>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </form>

        <div class="text-center mb-3">
          <button class="btn btn-outline-secondary btn-sm" id="startScanBtn" onclick="startScan()">
            <i class="bi bi-camera me-1"></i>Start Camera Scan
          </button>
          <button class="btn btn-outline-danger btn-sm d-none" id="stopScanBtn" onclick="stopScan()">
            <i class="bi bi-stop-circle me-1"></i>Stop Scan
          </button>
        </div>

        <div id="qr-reader" class="rounded overflow-hidden" style="display:none;"></div>
        <p id="scan-status" class="text-muted small text-center mt-2"></p>
      </div>
    </div>

    <!-- Instructions -->
    <div class="content-card">
      <div class="content-card-header"><h5><i class="bi bi-info-circle me-2"></i>Instructions</h5></div>
      <div class="content-card-body">
        <ol class="small text-muted mb-0">
          <li class="mb-1">Ask the student to present their QR code from their submission receipt or email.</li>
          <li class="mb-1">Click <strong>Start Camera Scan</strong> and point camera at the QR code.</li>
          <li class="mb-1">Or manually type the tracking code in the input above.</li>
          <li class="mb-1">The system will display the student's submission details and current status.</li>
          <li>Use this to verify identity before releasing documents.</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Result -->
  <div class="col-lg-7">
    <?php if ($code && !$record): ?>
    <div class="alert alert-warning d-flex gap-2">
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
      <div>No record found for tracking code <strong><?= e($code) ?></strong>.</div>
    </div>
    <?php elseif ($record && $type === 'issued_document'): ?>

    <!-- Issued document verification result -->
    <div class="content-card mb-3 overflow-hidden">
      <div class="p-3 text-white" style="background:#065f46;">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-patch-check-fill fs-2"></i>
          <div>
            <div class="fw-bold fs-5">AUTHENTIC ISSUED DOCUMENT ✓</div>
            <div class="opacity-75 small"><?= e($code) ?></div>
          </div>
        </div>
      </div>
      <div class="content-card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="detail-label">Document Type</div>
            <div class="detail-value fw-bold"><?= e($record['document_type']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Issued To</div>
            <div class="detail-value"><?= e($record['issued_to']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Student ID</div>
            <div class="detail-value"><?= e($record['student_id'] ?: '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Request Reference</div>
            <div class="detail-value font-monospace"><?= e($record['tracking_code']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Issued By</div>
            <div class="detail-value"><?= e($record['issued_by_name'] ?? '—') ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Issued On</div>
            <div class="detail-value"><?= formatDateTime($record['issued_at']) ?></div>
          </div>
        </div>
        <div class="mt-3 pt-3 border-top d-flex gap-2">
          <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$record['document_request_id'] ?>"
             class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-right me-1"></i>Open Original Request
          </a>
          <a href="<?= BASE_URL ?>/staff/qr_verify.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-qr-code-scan me-1"></i>Verify Another
          </a>
        </div>
      </div>
    </div>

    <?php elseif ($record): ?>

    <?php
    $isReg  = $type === 'registration';
    $status = $record['status'];
    $isGood = in_array($status, ['Approved','Ready for Release','Released']);
    $isWarn = in_array($status, ['Needs Correction','Declined']);
    $headerBg = $isGood ? '#065f46' : ($isWarn ? '#7f1d1d' : '#1a4a8a');
    ?>

    <div class="content-card mb-3 overflow-hidden">
      <div class="p-3 text-white" style="background:<?= $headerBg ?>;">
        <div class="d-flex align-items-center gap-3">
          <div>
            <?php if ($isGood): ?>
              <i class="bi bi-patch-check-fill fs-2"></i>
            <?php elseif ($isWarn): ?>
              <i class="bi bi-x-circle-fill fs-2"></i>
            <?php else: ?>
              <i class="bi bi-shield-check fs-2"></i>
            <?php endif; ?>
          </div>
          <div>
            <div class="fw-bold fs-5"><?= $isGood ? 'VERIFIED ✓' : ($isWarn ? 'NOT CLEARED' : 'FOUND – ' . strtoupper($status)) ?></div>
            <div class="opacity-75 small"><?= e($code) ?></div>
          </div>
          <div class="ms-auto">
            <span class="badge fs-6 px-3 py-2" style="background:rgba(255,255,255,.25);">
              <?= e($status) ?>
            </span>
          </div>
        </div>
      </div>
      <div class="content-card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="detail-label">Full Name</div>
            <div class="detail-value fw-bold"><?= e(fullName($record)) ?></div>
          </div>
          <?php if (!$isReg): ?>
          <div class="col-sm-6">
            <div class="detail-label">Student ID</div>
            <div class="detail-value"><?= e($record['student_id'] ?: '—') ?></div>
          </div>
          <?php endif; ?>
          <div class="col-sm-6">
            <div class="detail-label">Type</div>
            <div class="detail-value"><?= $isReg ? 'Pre-Registration' : 'Document Request' ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label"><?= $isReg ? 'Program' : 'Document' ?></div>
            <div class="detail-value">
              <?= $isReg
                  ? e($record['program_other'] ?: ($record['prog_name'] ?? '—'))
                  : e($record['document_type_other'] ?: ($record['doc_name'] ?? '—')) ?>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Email</div>
            <div class="detail-value"><?= e($record['email']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Contact</div>
            <div class="detail-value"><?= e($record['contact_number']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="detail-label">Submitted</div>
            <div class="detail-value"><?= formatDateTime($record['submitted_at']) ?></div>
          </div>
          <?php if (!$isReg && $record['released_at']): ?>
          <div class="col-sm-6">
            <div class="detail-label">Released</div>
            <div class="detail-value"><?= formatDateTime($record['released_at']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($record['remarks'])): ?>
        <div class="alert alert-warning mt-3 py-2 small">
          <strong>Remarks:</strong> <?= e($record['remarks']) ?>
        </div>
        <?php endif; ?>

        <div class="mt-3 pt-3 border-top d-flex gap-2">
          <?php
          $viewUrl = $isReg
            ? BASE_URL . '/staff/view_registration.php?id=' . (int)$record['id']
            : BASE_URL . '/staff/view_document.php?id='     . (int)$record['id'];
          ?>
          <a href="<?= $viewUrl ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-right me-1"></i>Open Full Record
          </a>
          <a href="<?= BASE_URL ?>/staff/qr_verify.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-qr-code-scan me-1"></i>Verify Another
          </a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="content-card">
      <div class="content-card-body">
        <div class="empty-state">
          <i class="bi bi-qr-code text-muted"></i>
          <p class="mt-3 text-muted">Scan or enter a tracking code to verify a student's submission.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.getElementById('codeInput')?.addEventListener('input', function() {
  this.value = this.value.toUpperCase();
});

let qrScanner = null;
function startScan() {
  document.getElementById('qr-reader').style.display = 'block';
  document.getElementById('startScanBtn').classList.add('d-none');
  document.getElementById('stopScanBtn').classList.remove('d-none');
  document.getElementById('scan-status').textContent = 'Initializing camera…';

  qrScanner = new Html5Qrcode("qr-reader");
  qrScanner.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: { width: 250, height: 250 } },
    function(decodedText) {
      stopScan();
      let code = decodedText;
      const match = decodedText.match(/code=([A-Z0-9\-]+)/i);
      if (match) code = match[1];
      document.getElementById('codeInput').value = code.toUpperCase();
      document.querySelector('form[method="GET"]').submit();
    },
    function() {}
  ).then(() => {
    document.getElementById('scan-status').textContent = 'Camera active. Point at QR code.';
  }).catch(err => {
    document.getElementById('scan-status').textContent = 'Camera error: ' + err;
    document.getElementById('qr-reader').style.display = 'none';
  });
}
function stopScan() {
  qrScanner?.stop().catch(() => {});
  qrScanner = null;
  document.getElementById('qr-reader').style.display = 'none';
  document.getElementById('startScanBtn').classList.remove('d-none');
  document.getElementById('stopScanBtn').classList.add('d-none');
  document.getElementById('scan-status').textContent = '';
}
</script>

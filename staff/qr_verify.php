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

<?= ui_page_header('QR Code Verification', "Scan a student's QR code or enter their tracking code to verify their submission.", 'qr-code-scan') ?>

<div class="row g-3">
  <!-- Scan / Input -->
  <div class="col-lg-5">
    <?= ui_card_open('Scan or Enter Code', 'qr-code-scan', '', 'mb-3') ?>
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
    <?= ui_card_close() ?>

    <!-- Instructions -->
    <?= ui_card_open('Instructions', 'info-circle') ?>
      <div class="content-card-body">
        <ol class="small text-muted mb-0">
          <li class="mb-1">Ask the student to present their QR code from their submission receipt or email.</li>
          <li class="mb-1">Click <strong>Start Camera Scan</strong> and point camera at the QR code.</li>
          <li class="mb-1">Or manually type the tracking code in the input above.</li>
          <li class="mb-1">The system will display the student's submission details and current status.</li>
          <li>Use this to verify identity before releasing documents.</li>
        </ol>
      </div>
    <?= ui_card_close() ?>
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
      <div class="verify-banner good">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-patch-check-fill fs-2"></i>
          <div>
            <div class="verify-banner-title">AUTHENTIC ISSUED DOCUMENT</div>
            <div class="verify-banner-code"><?= e($code) ?></div>
          </div>
        </div>
      </div>
      <div class="content-card-body">
        <?= ui_details([
            ['label' => 'Document Type',     'value' => '<span class="fw-bold">' . e($record['document_type']) . '</span>'],
            ['label' => 'Issued To',         'value' => e($record['issued_to'])],
            ['label' => 'Student ID',        'value' => e($record['student_id'])],
            ['label' => 'Request Reference', 'value' => '<span class="font-monospace">' . e($record['tracking_code']) . '</span>'],
            ['label' => 'Issued By',         'value' => e($record['issued_by_name'] ?? '—')],
            ['label' => 'Issued On',         'value' => formatDateTime($record['issued_at'])],
        ]) ?>
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
    $banner = $isGood ? 'good' : ($isWarn ? 'bad' : 'info');
    ?>

    <div class="content-card mb-3 overflow-hidden">
      <div class="verify-banner <?= $banner ?>">
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
            <div class="verify-banner-title"><?= $isGood ? 'VERIFIED' : ($isWarn ? 'NOT CLEARED' : 'FOUND – ' . strtoupper($status)) ?></div>
            <div class="verify-banner-code"><?= e($code) ?></div>
          </div>
          <div class="ms-auto">
            <span class="badge fs-6 px-3 py-2"><?= e($status) ?></span>
          </div>
        </div>
      </div>
      <div class="content-card-body">
        <?php
        $dets = [
            ['label' => 'Full Name', 'value' => '<span class="fw-bold">' . e(fullName($record)) . '</span>'],
        ];
        if (!$isReg) {
            $dets[] = ['label' => 'Student ID', 'value' => e($record['student_id'])];
        }
        $dets[] = ['label' => 'Type', 'value' => $isReg ? 'Pre-Registration' : 'Document Request'];
        $dets[] = ['label' => $isReg ? 'Program' : 'Document',
                   'value' => $isReg
                       ? e($record['program_other'] ?: ($record['prog_name'] ?? '—'))
                       : e($record['document_type_other'] ?: ($record['doc_name'] ?? '—'))];
        $dets[] = ['label' => 'Email',     'value' => e($record['email'])];
        $dets[] = ['label' => 'Contact',   'value' => e($record['contact_number'])];
        $dets[] = ['label' => 'Submitted', 'value' => formatDateTime($record['submitted_at'])];
        if (!$isReg && $record['released_at']) {
            $dets[] = ['label' => 'Released', 'value' => formatDateTime($record['released_at'])];
        }
        echo ui_details($dets);
        ?>

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
        <?= ui_empty('qr-code', 'No code entered', "Scan or enter a tracking code to verify a student's submission.") ?>
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

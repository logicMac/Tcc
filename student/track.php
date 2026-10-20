<?php
/**
 * TCC RRS – Public Tracking Page
 * Students enter their tracking code (or arrive via QR scan) to view status.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

$schoolName   = getSetting('school_name', 'Tampakan Community College');
$activePage   = 'track';
$code         = trim(get('code'));
$result       = null;    // 'registration' | 'document_request' | null
$record       = null;
$history      = [];
$searchError  = '';

// ── Lookup ─────────────────────────────────────────────────────────────────
if ($code !== '') {
    $code = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $code));

    // Issued-document verification codes route to the verify page
    if (str_starts_with($code, TRACKING_VRF_PREFIX . '-')) {
        redirect(verificationUrl($code));
    }

    // Try pre_registrations first
    $reg = db()->fetchOne(
        "SELECT pr.*, p.name AS program_name
         FROM pre_registrations pr
         LEFT JOIN programs p ON p.id = pr.program_id
         WHERE pr.tracking_code = ?",
        [$code]
    );
    if ($reg) {
        $result  = 'registration';
        $record  = $reg;
        $history = getStatusHistory($code);
    } else {
        // Try document_requests
        $doc = db()->fetchOne(
            "SELECT dr.*, dt.name AS doc_type_name
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             WHERE dr.tracking_code = ?",
            [$code]
        );
        if ($doc) {
            $result  = 'document_request';
            $record  = $doc;
            $history = getStatusHistory($code);
            $issued  = db()->fetchOne(
                "SELECT verification_code, issued_at FROM issued_documents WHERE document_request_id = ?",
                [$doc['id']]
            );
            if ($issued) $record['verification_code'] = $issued['verification_code'];
        } else {
            $searchError = 'No submission found for tracking code <strong>' . e($code) . '</strong>. Please check your code and try again.';
        }
    }
}

// ── Pickup appointment booking ───────────────────────────────────────────────
// Students may book/reschedule a pickup slot once a request is Ready for Release.
if (isPost() && post('action') === 'book_pickup') {
    abortCsrf();
    $bookCode = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', trim(post('code'))));
    $doc = db()->fetchOne("SELECT * FROM document_requests WHERE tracking_code = ?", [$bookCode]);

    if (!$doc || $doc['status'] !== 'Ready for Release' || $doc['delivery_method'] !== 'Pick-up') {
        flash('danger', 'This request is not eligible for pickup scheduling.');
    } else {
        $pDate = post('pickup_date');
        $pSlot = post('pickup_slot');
        $valid = true;

        if (!validateDate($pDate)) {
            $valid = false;
        } else {
            $dow = (int) date('N', strtotime($pDate));
            if ($pDate < date('Y-m-d') || $dow > 5) {
                flash('danger', 'Pick a weekday (Mon–Fri), today or later.');
                $valid = false;
            }
        }
        if ($valid && !in_array($pSlot, pickupSlotOptions(), true)) {
            flash('danger', 'Invalid time slot.');
            $valid = false;
        }
        if ($valid && pickupSlotBookedCount($pDate, $pSlot, (int)$doc['id']) >= pickupSlotCapacity()) {
            flash('danger', 'That slot is fully booked — please choose another.');
            $valid = false;
        }
        if ($valid) {
            db()->execute(
                "UPDATE document_requests SET pickup_date = ?, pickup_slot = ?, pickup_booked_at = NOW() WHERE id = ?",
                [$pDate, $pSlot, $doc['id']]
            );
            addStatusHistory('document_request', (int)$doc['id'], $bookCode,
                $doc['status'], $doc['status'],
                "Student scheduled pickup for " . formatDate($pDate) . ", {$pSlot}.");
            flash('success', 'Pickup scheduled for ' . formatDate($pDate) . ', ' . e($pSlot) . '.');
        }
    }
    redirect(BASE_URL . '/student/track.php?code=' . urlencode($bookCode));
}

// ── Status timeline steps ───────────────────────────────────────────────────
$regSteps = ['Pending','Under Review','Processing','Approved'];
$docSteps = ['Pending','Under Review','Processing','Approved','Ready for Release','Released'];
$negativeStatuses = ['Needs Correction','Declined'];

function getStepIndex(array $steps, string $status): int {
    $idx = array_search($status, $steps);
    return $idx !== false ? (int)$idx : -1;
}

$currentStepIdx = -1;
if ($result === 'registration') {
    $currentStepIdx = getStepIndex($regSteps, $record['status']);
} elseif ($result === 'document_request') {
    $currentStepIdx = getStepIndex($docSteps, $record['status']);
}

$trackingUrl = $code ? trackingUrl($code) : '';
$pageTitle   = 'Track Status';
$inPortal    = isStudentLoggedIn();
if ($inPortal) {
    include __DIR__ . '/../includes/partials/head.php';
    include __DIR__ . '/../includes/partials/student_sidebar.php';
    echo '<div class="main-content">';
    include __DIR__ . '/../includes/partials/student_topbar.php';
    echo '<div class="page-content"><div class="container py-1">';
    ?>
    <?= ui_portal_header(
        'Track Status',
        'Enter your tracking code or scan your receipt QR.',
        'qr-code-scan',
        BASE_URL . '/student/dashboard.php',
        'Back to Dashboard'
    ) ?>
    <?php
} else {
    include __DIR__ . '/../includes/partials/portal_head.php';
    include __DIR__ . '/../includes/partials/portal_nav.php';
?>
<!-- breadcrumb bar -->
<div class="portal-crumbs">
  <div class="container py-2 d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/student/index.php" class="text-muted text-decoration-none">
      <i class="bi bi-house me-1"></i>Home
    </a>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <span class="fw-semibold text-dark">Track My Request</span>
    <?php if ($code && $result): ?>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <span class="text-dark"><?= e($code) ?></span>
    <?php endif; ?>
  </div>
</div>
<div class="container py-5">
<?php } ?>

  <!-- ── Search Bar ── -->
  <div class="row justify-content-center mb-5">
    <div class="col-lg-7 text-center">
      <?php if (!$inPortal): ?>
      <i class="bi bi-qr-code-scan fs-1 text-primary mb-3 d-block"></i>
      <h2 class="fw-bold mb-1">Track Your Request</h2>
      <p class="text-muted mb-4">Enter your tracking code to view the current status of your submission.</p>
      <?php else: ?>
      <div class="mb-4"></div>
      <?php endif; ?>
      <form action="<?= BASE_URL ?>/student/track.php" method="GET" id="track-form">
        <div class="d-flex gap-2">
          <input type="text" name="code" id="trackInput" class="form-control form-control-lg track-input"
                 value="<?= e($code) ?>"
                 placeholder="e.g. TCC-REG-2026-000001 or TCC-DOC-2026-000001"
                 autocomplete="off">
          <?= ui_btn('Track', ['type'=>'submit','size'=>'lg','class'=>'px-4 track-btn','icon'=>'search']) ?>
        </div>
      </form>
      <?php if ($searchError): ?>
      <div class="alert alert-warning mt-3 d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><?= $searchError ?></div>
      </div>
      <?php endif; ?>

      <!-- QR Scanner trigger -->
      <div class="mt-3">
        <?= ui_btn('Scan QR Code Instead', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'camera',
                   'attrs'=>'onclick="toggleScanner()"']) ?>
      </div>
    </div>
  </div>

  <!-- ── QR Scanner (camera) ── -->
  <div class="row justify-content-center mb-4 d-none" id="scanner-section">
    <div class="col-lg-5">
      <div class="scan-area">
        <h6 class="fw-bold mb-3"><i class="bi bi-camera me-2"></i>QR Code Scanner</h6>
        <div id="qr-reader" style="width:100%;max-width:340px;margin:0 auto;"></div>
        <?= ui_btn('Stop Scanner', ['size'=>'sm','variant'=>'outline-danger','icon'=>'x-circle','class'=>'mt-3',
                   'attrs'=>'onclick="stopScanner()"']) ?>
        <p class="text-muted small mt-2 mb-0">Point your camera at the QR code from your submission receipt.</p>
      </div>
    </div>
  </div>

  <?php if ($result && $record): ?>
  <!-- ════════════════════════════════════
       RESULT FOUND
  ════════════════════════════════════ -->
  <div class="row justify-content-center">
    <div class="col-lg-9">

      <?php
      $isReg  = $result === 'registration';
      $steps  = $isReg ? $regSteps : $docSteps;
      $status = $record['status'];
      $isNeg  = in_array($status, $negativeStatuses);
      ?>

      <!-- Status Header Card -->
      <?php
      $banner = 'info';
      if ($status === 'Approved' || $status === 'Released') $banner = 'good';
      if ($isNeg) $banner = 'bad';
      ?>
      <div class="card border-0 shadow mb-4" style="border-radius:16px;overflow:hidden;">
        <div class="verify-banner <?= $banner ?> p-4">
          <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
              <div class="small opacity-75 mb-1">
                <?= $isReg ? 'Pre-Registration for Enrollment' : 'Document Request' ?>
              </div>
              <h3 class="fw-bold mb-1 font-monospace"><?= e($code) ?></h3>
              <div class="opacity-85">
                <?= e(fullName($record)) ?>
                <?php if (!$isReg && !empty($record['student_id'])): ?>
                  &middot; ID: <?= e($record['student_id']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end">
              <?php if ($isNeg): ?>
                <div class="mb-1" style="font-size:2rem;">
                  <i class="bi bi-<?= $status === 'Declined' ? 'x-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
                </div>
              <?php else: ?>
                <div class="verify-banner-code mb-1">Current Status</div>
                <div class="badge fs-6 px-3 py-2 rounded-pill">
                  <?= e($status) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card-body p-4">

          <!-- Progress Steps (positive flow) -->
          <?php if (!$isNeg): ?>
          <h6 class="fw-bold mb-3 text-muted">Progress</h6>
          <div class="progress-steps mb-4">
            <?php foreach ($steps as $idx => $step): ?>
            <?php
              $stepClass = '';
              if ($idx < $currentStepIdx) $stepClass = 'done';
              elseif ($idx === $currentStepIdx) $stepClass = 'active';
            ?>
            <div class="prog-step <?= $stepClass ?>">
              <div class="prog-circle">
                <?= $idx < $currentStepIdx
                    ? '<i class="bi bi-check-lg"></i>'
                    : ($idx === $currentStepIdx ? '<i class="bi bi-circle-fill" style="font-size:.5rem;"></i>' : ($idx + 1))
                ?>
              </div>
              <div class="prog-label"><?= e($step) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <!-- Negative status -->
          <div class="alert <?= $status === 'Declined' ? 'alert-danger' : 'alert-warning' ?> d-flex gap-2 mb-4">
            <i class="bi bi-<?= $status === 'Declined' ? 'x-circle-fill' : 'exclamation-triangle-fill' ?> flex-shrink-0 mt-1 fs-5"></i>
            <div>
              <strong><?= e($status) ?></strong>
              <?php if (!empty($record['remarks'])): ?>
              <br><span class="small"><?= e($record['remarks']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($record['remarks']) && !$isNeg): ?>
          <div class="alert alert-info d-flex gap-2 mb-4">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <div><strong>Remarks from Registrar:</strong> <?= e($record['remarks']) ?></div>
          </div>
          <?php endif; ?>

          <?php
          // ── Pickup appointment (document requests only) ──
          $canBook = !$isNeg && !$isReg && $status === 'Ready for Release'
                  && $record['delivery_method'] === 'Pick-up';
          ?>
          <?php if ($canBook || (!$isReg && !empty($record['pickup_date']))): ?>
          <div class="pickup-card mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-calendar-check me-1"></i>Pickup Appointment</h6>
            <?php if (!empty($record['pickup_date'])): ?>
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div>
                <div class="fw-bold text-success"><?= formatDate($record['pickup_date'], 'l, F j, Y') ?></div>
                <div class="small text-muted"><?= e($record['pickup_slot']) ?>
                  · booked <?= timeAgo($record['pickup_booked_at']) ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($canBook): ?>
            <form method="POST" class="d-flex gap-2 flex-wrap align-items-end <?= !empty($record['pickup_date']) ? 'mt-3' : '' ?>">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="book_pickup">
              <input type="hidden" name="code" value="<?= e($code) ?>">
              <div>
                <label class="form-label small fw-semibold mb-1">Date</label>
                <input type="date" name="pickup_date" class="form-control" required
                       min="<?= date('Y-m-d') ?>" value="<?= e($record['pickup_date'] ?? '') ?>">
              </div>
              <div>
                <label class="form-label small fw-semibold mb-1">Time slot</label>
                <select name="pickup_slot" class="form-select" required>
                  <option value="">— Choose —</option>
                  <?php foreach (pickupSlotOptions() as $slot): ?>
                  <option value="<?= e($slot) ?>" <?= ($record['pickup_slot'] ?? '') === $slot ? 'selected' : '' ?>>
                    <?= e($slot) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?= ui_btn(!empty($record['pickup_date']) ? 'Reschedule' : 'Book Pickup',
                         ['type'=>'submit','icon'=>'calendar-plus']) ?>
            </form>
            <?php if (empty($record['pickup_date'])): ?>
            <p class="small text-muted mt-2 mb-0">Reserve a slot to skip the line — bring a valid ID and this tracking code.</p>
            <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="row g-4">
            <!-- Details -->
            <div class="col-md-7">
              <h6 class="fw-bold mb-3">Submission Details</h6>
              <table class="info-table w-100">
                <tbody>
                  <tr>
                    <td>Full Name</td>
                    <td><?= e(fullName($record)) ?></td>
                  </tr>
                  <?php if ($isReg): ?>
                  <tr>
                    <td>Program</td>
                    <td><?= e($record['program_other'] ?: ($record['program_name'] ?? '—')) ?></td>
                  </tr>
                  <tr>
                    <td>Year Level</td>
                    <td><?= e($record['year_level']) ?></td>
                  </tr>
                  <tr>
                    <td>Enrollment Type</td>
                    <td><?= e($record['enrollment_type']) ?></td>
                  </tr>
                  <tr>
                    <td>School Year</td>
                    <td><?= e($record['school_year']) ?> – <?= e($record['semester']) ?></td>
                  </tr>
                  <?php else: ?>
                  <tr>
                    <td>Document Type</td>
                    <td><?= e($record['document_type_other'] ?: ($record['doc_type_name'] ?? '—')) ?></td>
                  </tr>
                  <tr>
                    <td>Number of Copies</td>
                    <td><?= (int)$record['copies'] ?></td>
                  </tr>
                  <tr>
                    <td>Purpose</td>
                    <td><?= e($record['purpose']) ?></td>
                  </tr>
                  <tr>
                    <td>Delivery Method</td>
                    <td><?= e($record['delivery_method']) ?></td>
                  </tr>
                  <?php if (!empty($record['date_needed'])): ?>
                  <tr>
                    <td>Date Needed</td>
                    <td><?= formatDate($record['date_needed']) ?></td>
                  </tr>
                  <?php endif; ?>
                  <?php endif; ?>
                  <tr>
                    <td>Date Submitted</td>
                    <td><?= formatDateTime($record['submitted_at']) ?></td>
                  </tr>
                  <tr>
                    <td>Current Status</td>
                    <td><?= statusBadge($status, $result) ?></td>
                  </tr>
                  <?php if (!empty($record['processed_at'])): ?>
                  <tr>
                    <td>Processed On</td>
                    <td><?= formatDateTime($record['processed_at']) ?></td>
                  </tr>
                  <?php endif; ?>
                  <?php if (!$isReg && !empty($record['released_at'])): ?>
                  <tr>
                    <td>Released On</td>
                    <td><?= formatDateTime($record['released_at']) ?></td>
                  </tr>
                  <?php endif; ?>
                  <?php if (!$isReg && !empty($record['verification_code'])): ?>
                  <tr>
                    <td>Verification Code</td>
                    <td>
                      <a href="<?= e(verificationUrl($record['verification_code'])) ?>"
                         class="font-monospace text-decoration-none" target="_blank">
                        <i class="bi bi-patch-check me-1"></i><?= e($record['verification_code']) ?>
                      </a>
                    </td>
                  </tr>
                  <?php endif; ?>
                  <?php if (!$isReg && !empty($record['approved_at'])): ?>
                  <tr>
                    <td>Approved On</td>
                    <td><?= formatDateTime($record['approved_at']) ?></td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>

              <!-- QR re-display -->
              <div class="mt-4">
                <h6 class="fw-bold mb-2">Your QR Code</h6>
                <div class="d-flex align-items-center gap-3">
                  <div class="qr-container" id="print-section">
                    <div id="qrcode-track"></div>
                    <div class="small text-muted text-center mt-1"><?= e($code) ?></div>
                  </div>
                  <div class="d-flex flex-column gap-2">
                    <?= ui_btn('Download', ['size'=>'sm','variant'=>'outline-primary','icon'=>'download',
                               'attrs'=>'onclick="downloadQr(\'print-section\',\'' . e($code) . '.png\')"']) ?>
                    <?= ui_btn('Print', ['size'=>'sm','variant'=>'outline-secondary','icon'=>'printer',
                               'attrs'=>'onclick="printSection(\'print-section\')"']) ?>
                    <?= ui_btn('Copy Code', ['size'=>'sm','variant'=>'outline-dark','icon'=>'clipboard',
                               'attrs'=>'onclick="copyToClipboard(\'' . e($code) . '\', this)"']) ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Timeline -->
            <div class="col-md-5">
              <h6 class="fw-bold mb-3">Status Timeline</h6>
              <?php if (!empty($history)): ?>
              <div class="status-timeline">
                <?php foreach ($history as $h): ?>
                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <div class="d-flex justify-content-between align-items-start">
                      <strong class="small"><?= e($h['new_status']) ?></strong>
                      <small class="text-muted ms-2 text-nowrap" style="font-size:.7rem;">
                        <?= formatDateTime($h['changed_at']) ?>
                      </small>
                    </div>
                    <?php if (!empty($h['remarks'])): ?>
                    <div class="timeline-remarks text-muted small">
                      <?= e($h['remarks']) ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <p class="text-muted small">No history yet.</p>
              <?php endif; ?>

              <!-- What's next hint -->
              <div class="mt-4 p-3 rounded-3 border bg-light">
                <h6 class="fw-bold small mb-2"><i class="bi bi-lightbulb me-1 text-warning"></i>What happens next?</h6>
                <?php if ($isReg): ?>
                  <?php if ($status === 'Pending'): ?>
                  <p class="small text-muted mb-0">Your pre-registration is queued for review. The staff will process it shortly. You'll receive an email update.</p>
                  <?php elseif ($status === 'Under Review'): ?>
                  <p class="small text-muted mb-0">Staff is currently reviewing your information. This usually takes 1–2 business days.</p>
                  <?php elseif ($status === 'Processing'): ?>
                  <p class="small text-muted mb-0">Your registration is being processed. You'll be notified once a decision is made.</p>
                  <?php elseif ($status === 'Approved'): ?>
                  <p class="small text-muted mb-0">🎉 Your pre-registration is approved! Please visit the Registrar's Office with original documents to complete enrollment.</p>
                  <?php elseif ($status === 'Needs Correction'): ?>
                  <p class="small text-muted mb-0">Please review the remarks above and contact the Registrar's Office to provide the needed corrections.</p>
                  <?php elseif ($status === 'Declined'): ?>
                  <p class="small text-muted mb-0">Your pre-registration was declined. Please contact the Registrar's Office for assistance.</p>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ($status === 'Pending'): ?>
                  <p class="small text-muted mb-0">Your document request is queued. Staff will review it soon.</p>
                  <?php elseif ($status === 'Ready for Release'): ?>
                  <p class="small text-muted mb-0">📋 Your document is ready! Visit the Registrar's Office and present your QR code or tracking code to claim it.</p>
                  <?php elseif ($status === 'Released'): ?>
                  <p class="small text-muted mb-0">✅ Your document has been released. Thank you!</p>
                  <?php else: ?>
                  <p class="small text-muted mb-0">Your request is being processed. You'll receive an email when there's an update.</p>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div><!-- /row -->

          <div class="text-center mt-4 pt-3 border-top">
            <?= ui_btn('Track Another', ['variant'=>'outline-secondary','icon'=>'arrow-left','class'=>'me-2',
                       'href'=>BASE_URL . '/student/track.php']) ?>
            <?= ui_btn($inPortal ? 'Back to Dashboard' : 'Back to Portal',
                       ['icon'=>$inPortal ? 'grid-1x2' : 'house',
                        'href'=>BASE_URL . ($inPortal ? '/student/dashboard.php' : '/student/index.php')]) ?>
          </div>

        </div><!-- /card-body -->
      </div><!-- /card -->
    </div><!-- /col -->
  </div><!-- /row -->
  <?php endif; ?>

  <?php if (!$code): ?>
  <!-- Help section when no code entered -->
  <div class="row justify-content-center mt-2">
    <div class="col-lg-7">
      <div class="row g-3">
        <div class="col-md-6">
          <a href="<?= BASE_URL ?>/student/pre_registration.php" class="quick-tile track-help reveal">
            <div class="quick-tile-ico"><i class="bi bi-person-plus"></i></div>
            <div>
              <div class="quick-tile-label">Don't have a tracking code?</div>
              <div class="quick-tile-sub">Submit a pre-registration to receive your unique code →</div>
            </div>
          </a>
        </div>
        <div class="col-md-6">
          <a href="<?= BASE_URL ?>/student/document_request.php" class="quick-tile track-help reveal">
            <div class="quick-tile-ico"><i class="bi bi-file-earmark-text"></i></div>
            <div>
              <div class="quick-tile-label">Need a document?</div>
              <div class="quick-tile-sub">Request a TOR, COE, Good Moral, and more online →</div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
<?php if ($result && $record): ?>
document.addEventListener('DOMContentLoaded', function() {
  generateQrCode('qrcode-track', '<?= e($trackingUrl) ?>', { width:160, height:160 });
});
<?php endif; ?>

// Uppercase tracking input
document.getElementById('trackInput')?.addEventListener('input', function() {
  this.value = this.value.toUpperCase();
});

// QR Scanner
let html5QrCode = null;
function toggleScanner() {
  const sec = document.getElementById('scanner-section');
  if (sec.classList.contains('d-none')) {
    sec.classList.remove('d-none');
    startScanner();
  } else {
    stopScanner();
  }
}
function startScanner() {
  html5QrCode = new Html5Qrcode("qr-reader");
  html5QrCode.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: { width: 250, height: 250 } },
    function(decodedText) {
      stopScanner();
      // Extract code from URL or use raw
      let code = decodedText;
      const match = decodedText.match(/code=([A-Z0-9\-]+)/i);
      if (match) code = match[1];
      document.getElementById('trackInput').value = code.toUpperCase();
      document.getElementById('track-form').submit();
    },
    function(err) {}
  ).catch(function(err) {
    document.getElementById('qr-reader').innerHTML =
      '<p class="text-danger small">Camera access denied or not available.<br>' + err + '</p>';
  });
}
function stopScanner() {
  if (html5QrCode) {
    html5QrCode.stop().catch(() => {});
    html5QrCode = null;
  }
  document.getElementById('scanner-section').classList.add('d-none');
}
</script>
</div>
<?php if ($inPortal): ?>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<?php else: include __DIR__ . '/../includes/partials/portal_foot.php'; endif; ?>

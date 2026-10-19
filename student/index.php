<?php
/**
 * TCC RRS – Student Public Portal (Landing Page)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
$schoolName = getSetting('school_name', 'Tampakan Community College');
$activePage = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Portal – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body style="background:var(--bg-app);">

<?php include __DIR__ . '/../includes/partials/portal_nav.php'; ?>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="portal-hero">
  <div class="container text-center position-relative" style="z-index:2;">
    <span class="badge mb-3 d-inline-flex align-items-center gap-1"
          style="background:rgba(255,255,255,.18);color:#fff;font-size:.78rem;padding:6px 14px;border-radius:20px;">
      <i class="bi bi-mortarboard"></i>
      Academic Year <?= e(getSetting('current_school_year','2026-2027')) ?>
      &nbsp;&middot;&nbsp;
      <?= e(getSetting('current_semester','1st Semester')) ?>
    </span>
    <h1 class="display-5 fw-bold mb-3">Welcome to TCC Student Portal</h1>
    <p class="lead mb-5 opacity-90" style="max-width:560px;margin:0 auto;">
      Pre-register for enrollment, request official documents, and track your
      submissions — all without creating an account.
    </p>
    <div class="d-flex flex-wrap gap-3 justify-content-center">
      <a href="<?= BASE_URL ?>/student/pre_registration.php"
         class="btn btn-light btn-lg px-4 fw-bold shadow-sm">
        <i class="bi bi-person-plus me-2"></i>Pre-Register for Enrollment
      </a>
      <a href="<?= BASE_URL ?>/student/document_request.php"
         class="btn btn-outline-light btn-lg px-4">
        <i class="bi bi-file-earmark-plus me-2"></i>Request a Document
      </a>
    </div>
  </div>
</section>

<!-- ── Service Cards ─────────────────────────────────────── -->
<section class="py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">What can you do here?</h2>
      <p class="text-muted">Three core services — no account needed.</p>
    </div>
    <div class="row g-4 justify-content-center">

      <div class="col-md-4">
        <div class="portal-card card h-100 text-center p-4">
          <div class="portal-card-icon blue"><i class="bi bi-person-plus-fill"></i></div>
          <h5 class="fw-bold mb-2">Pre-Registration for Enrollment</h5>
          <p class="text-muted small mb-4">
            Submit your enrollment application online and receive a unique
            Tracking Code &amp; QR Code to monitor your status.
          </p>
          <a href="<?= BASE_URL ?>/student/pre_registration.php"
             class="btn btn-primary mt-auto w-100">
            <i class="bi bi-arrow-right-circle me-1"></i> Start Pre-Registration
          </a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="portal-card card h-100 text-center p-4">
          <div class="portal-card-icon green"><i class="bi bi-file-earmark-text-fill"></i></div>
          <h5 class="fw-bold mb-2">Request Registrar Documents</h5>
          <p class="text-muted small mb-4">
            Request your TOR, Certificate of Enrollment, Good Moral, Diploma,
            and other official registrar documents.
          </p>
          <a href="<?= BASE_URL ?>/student/document_request.php"
             class="btn btn-success mt-auto w-100">
            <i class="bi bi-arrow-right-circle me-1"></i> Request Document
          </a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="portal-card card h-100 text-center p-4">
          <div class="portal-card-icon purple"><i class="bi bi-qr-code-scan"></i></div>
          <h5 class="fw-bold mb-2">Track Your Request</h5>
          <p class="text-muted small mb-4">
            Enter your Tracking Code or scan your QR Code to view the real-time
            status and complete timeline of any submission.
          </p>
          <a href="<?= BASE_URL ?>/student/track.php"
             class="btn btn-outline-primary mt-auto w-100">
            <i class="bi bi-arrow-right-circle me-1"></i> Track My Status
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ── How It Works ──────────────────────────────────────── -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">How It Works</h2>
      <p class="text-muted">Four simple steps from submission to completion.</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php
      $steps = [
        ['num'=>'1','color'=>'#1a4a8a','icon'=>'bi-pencil-square','title'=>'Fill Out the Form',
         'text'=>'Complete your personal and academic information in the online form.'],
        ['num'=>'2','color'=>'#15803d','icon'=>'bi-send-check',   'title'=>'Submit &amp; Get Your Code',
         'text'=>'Receive a unique Tracking Code and QR Code immediately after submission.'],
        ['num'=>'3','color'=>'#6d28d9','icon'=>'bi-gear-wide-connected','title'=>'Staff Processes It',
         'text'=>'The Registrar\'s Office reviews and processes your submission.'],
        ['num'=>'4','color'=>'#c9a84c','icon'=>'bi-bell-fill',   'title'=>'Track &amp; Get Notified',
         'text'=>'Use your tracking code or QR to monitor progress and receive email updates.'],
      ];
      foreach ($steps as $s):
      ?>
      <div class="col-sm-6 col-md-3 text-center">
        <div class="rounded-circle text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm"
             style="width:60px;height:60px;font-size:1.4rem;background:<?= $s['color'] ?>;">
          <i class="bi <?= $s['icon'] ?>"></i>
        </div>
        <div class="rounded-pill text-white fw-bold d-inline-block mb-2 px-3 py-1"
             style="font-size:.7rem;background:<?= $s['color'] ?>;opacity:.7;">
          Step <?= $s['num'] ?>
        </div>
        <h6 class="fw-bold"><?= $s['title'] ?></h6>
        <p class="text-muted small mb-0"><?= $s['text'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Quick Track ────────────────────────────────────────── -->
<section class="py-5" style="background:linear-gradient(135deg,#eef2fb,#e8f0fe);">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-6 text-center">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
             style="width:56px;height:56px;background:#dbeafe;">
          <i class="bi bi-search text-primary fs-5"></i>
        </div>
        <h3 class="fw-bold mb-2">Already have a Tracking Code?</h3>
        <p class="text-muted mb-4">Enter it below to instantly check the status of your submission.</p>
        <form action="<?= BASE_URL ?>/student/track.php" method="GET">
          <div class="input-group input-group-lg shadow-sm">
            <input type="text" name="code" class="form-control"
                   placeholder="e.g. TCC-REG-2026-000001 or TCC-DOC-2026-000001"
                   required style="border-right:0;text-transform:uppercase;font-size:.9rem;">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-search me-1"></i>Track
            </button>
          </div>
          <p class="text-muted small mt-2 mb-0">
            <i class="bi bi-qr-code me-1"></i>
            Or <a href="<?= BASE_URL ?>/student/track.php" class="text-primary fw-semibold">go to the tracking page</a>
            to scan your QR Code with your camera.
          </p>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ── Available Documents ────────────────────────────────── -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold">Available Documents</h2>
      <p class="text-muted">Official documents you can request from the Registrar's Office.</p>
    </div>
    <?php $docTypes = getActiveDocumentTypes(); ?>
    <?php if (empty($docTypes)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-file-earmark-x d-block fs-1 mb-2 opacity-25"></i>
      No document types available at this time.
    </div>
    <?php else: ?>
    <div class="row g-3 justify-content-center">
      <?php foreach ($docTypes as $doc): ?>
      <?php if ($doc['code'] === 'OTHER') continue; ?>
      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="doc-item d-flex align-items-center gap-3 p-3 border rounded-3 h-100 bg-white">
          <div style="width:38px;height:38px;background:#dbeafe;border-radius:8px;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-file-earmark-check text-primary"></i>
          </div>
          <div>
            <div class="fw-semibold" style="font-size:.83rem;line-height:1.2;"><?= e($doc['name']) ?></div>
            <div class="text-muted mt-1" style="font-size:.7rem;">
              <i class="bi bi-currency-dollar"></i> <?= formatCurrency((float)$doc['fee']) ?>
              &nbsp;&middot;&nbsp;
              <i class="bi bi-clock"></i> ~<?= (int)$doc['processing_days'] ?> day<?= $doc['processing_days'] > 1 ? 's' : '' ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?= BASE_URL ?>/student/document_request.php" class="btn btn-primary btn-lg px-4">
        <i class="bi bi-file-earmark-plus me-1"></i> Request a Document
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer style="background:var(--tcc-blue);color:rgba(255,255,255,.7);">
  <div class="container py-4">
    <div class="row align-items-center g-3">
      <div class="col-md-6 text-center text-md-start">
        <div class="fw-bold text-white mb-1"><?= e($schoolName) ?></div>
        <div class="small"><?= e(getSetting('school_address','Tampakan, South Cotabato, Philippines')) ?></div>
        <div class="small"><?= e(getSetting('school_phone','')) ?></div>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <div class="d-flex gap-2 justify-content-center justify-content-md-end mb-2">
          <a href="<?= BASE_URL ?>/student/pre_registration.php"
             class="btn btn-sm btn-outline-light">Pre-Register</a>
          <a href="<?= BASE_URL ?>/student/document_request.php"
             class="btn btn-sm btn-outline-light">Documents</a>
          <a href="<?= BASE_URL ?>/student/track.php"
             class="btn btn-sm btn-outline-light">Track</a>
        </div>
        <div class="small">&copy; <?= date('Y') ?> <?= e($schoolName) ?>. All rights reserved.</div>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * TCC RRS – Public Document Verification
 * Anyone can verify an issued document's authenticity via its
 * verification code or the QR printed on release slips.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

$schoolName = getSetting('school_name', 'Tampakan Community College');
$code       = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', trim(get('code'))));
$issued     = $code ? getIssuedDocument($code) : null;
$searched   = $code !== '';
$pageTitle  = 'Verify Document';
include __DIR__ . '/includes/partials/portal_head.php';
include __DIR__ . '/includes/partials/portal_nav.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <div class="text-center mb-4">
        <i class="bi bi-patch-check fs-1 text-primary d-block mb-2"></i>
        <h3 class="fw-bold mb-1">Verify an Official Document</h3>
        <p class="text-muted small">
          Enter the verification code printed on the document or scan its QR code.
        </p>
      </div>

      <form method="GET" class="d-flex gap-2 mb-4">
        <input type="text" name="code" class="form-control form-control-lg font-monospace"
               value="<?= e($code) ?>" placeholder="TCC-VRF-2026-000001"
               style="text-transform:uppercase;" autofocus>
        <button type="submit" class="btn btn-primary btn-lg px-4">
          <i class="bi bi-search"></i>
        </button>
      </form>

      <?php if ($searched && $issued): ?>
      <!-- ── AUTHENTIC ── -->
      <div class="card border-0 shadow" style="border-radius:16px;overflow:hidden;">
        <div class="verify-banner good text-center p-4">
          <i class="bi bi-patch-check-fill" style="font-size:3rem;"></i>
          <h4 class="fw-bold mt-2 mb-0">AUTHENTIC DOCUMENT</h4>
          <div class="small opacity-75">This document was officially issued by <?= e($schoolName) ?></div>
        </div>
        <div class="card-body p-4">
          <table class="info-table w-100">
            <tbody>
              <tr><td>Verification Code</td><td><span class="font-monospace fw-bold"><?= e($issued['verification_code']) ?></span></td></tr>
              <tr><td>Document Type</td><td><strong><?= e($issued['document_type']) ?></strong></td></tr>
              <tr><td>Issued To</td><td><?= e($issued['issued_to']) ?></td></tr>
              <?php if ($issued['student_id']): ?>
              <tr><td>Student ID</td><td><?= e($issued['student_id']) ?></td></tr>
              <?php endif; ?>
              <tr><td>Request Reference</td><td><span class="font-monospace"><?= e($issued['tracking_code']) ?></span></td></tr>
              <tr><td>Issued By</td><td><?= e($issued['issued_by_name'] ?? '—') ?></td></tr>
              <tr><td>Issued On</td><td><?= formatDateTime($issued['issued_at']) ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-light text-center py-2">
          <span class="small text-muted">
            If the details above do not match the physical document, it may be a forgery —
            report it to the Registrar's Office.
          </span>
        </div>
      </div>

      <?php elseif ($searched): ?>
      <!-- ── NOT FOUND ── -->
      <div class="card border-0 shadow" style="border-radius:16px;overflow:hidden;">
        <div class="verify-banner bad text-center p-4">
          <i class="bi bi-x-octagon-fill" style="font-size:3rem;"></i>
          <h4 class="fw-bold mt-2 mb-0">NOT VERIFIED</h4>
          <div class="small opacity-75">No issued document matches this verification code</div>
        </div>
        <div class="card-body p-4 text-center">
          <p class="text-muted mb-3">
            The code <span class="font-monospace fw-bold"><?= e($code) ?></span> was not found
            in our records. Double-check the code, or contact the Registrar's Office —
            documents that cannot be verified should not be accepted as official.
          </p>
          <a href="?" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Try Another Code
          </a>
        </div>
      </div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="<?= BASE_URL ?>/student/index.php" class="small text-muted text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Back to Student Portal
        </a>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/partials/portal_foot.php'; ?>

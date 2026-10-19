<?php
/**
 * Shared footer + scripts for public/student pages.
 * Uses $schoolName if set (portal_head.php sets it).
 */
$schoolName = $schoolName ?? getSetting('school_name', 'Tampakan Community College');
?>
<footer class="portal-footer mt-auto">
  <div class="container py-4">
    <div class="row align-items-center g-3">
      <div class="col-md-6 text-center text-md-start">
        <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
          <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt=""
               class="brand-logo brand-logo-sm" style="width:30px;height:30px;">
          <div class="fw-bold text-white"><?= e($schoolName) ?></div>
        </div>
        <div class="small"><?= e(getSetting('school_address', 'Tampakan, South Cotabato, Philippines')) ?></div>
        <div class="small"><?= e(getSetting('school_phone', '')) ?></div>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <div class="d-flex gap-2 justify-content-center justify-content-md-end mb-2 flex-wrap">
          <a href="<?= BASE_URL ?>/student/pre_registration.php" class="btn btn-sm btn-outline-light">Pre-Register</a>
          <a href="<?= BASE_URL ?>/student/document_request.php" class="btn btn-sm btn-outline-light">Documents</a>
          <a href="<?= BASE_URL ?>/student/track.php" class="btn btn-sm btn-outline-light">Track</a>
          <a href="<?= BASE_URL ?>/verify.php" class="btn btn-sm btn-outline-light">Verify Doc</a>
          <a href="<?= BASE_URL ?>/student/login.php" class="btn btn-sm btn-outline-light">My Account</a>
        </div>
        <div class="small">&copy; <?= date('Y') ?> <?= e($schoolName) ?>. All rights reserved.</div>
      </div>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>

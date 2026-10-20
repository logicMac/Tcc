<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 Unauthorized – TCC RRS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
</head>
<body>
<!-- Page loader -->
<div class="page-loader" id="pageLoader" aria-hidden="true">
  <div class="pl-ring">
    <svg class="pl-arc-svg" viewBox="0 0 100 100"><circle class="pl-track" cx="50" cy="50" r="44"/><circle class="pl-arc" cx="50" cy="50" r="44"/></svg>
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="" class="pl-logo">
  </div>
  <div class="pl-text">Tampakan <b>Community College</b></div>
</div>

<div class="auth-shell">
<div class="container text-center py-5">
  <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal"
       class="brand-logo mb-3" style="width:64px;height:64px;">
  <div class="display-1 text-danger fw-bold mb-3">403</div>
  <h2 class="mb-3">Access Denied</h2>
  <p class="text-muted mb-4">You do not have permission to access this page.</p>
  <div class="d-flex gap-2 justify-content-center">
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Go Back
    </a>
    <?php if (isLoggedIn()): ?>
    <a href="<?= BASE_URL ?>/<?= currentRole() ?>/dashboard.php" class="btn btn-primary">
      <i class="bi bi-house me-1"></i> Dashboard
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-right me-1"></i> Login
    </a>
    <?php endif; ?>
  </div>
</div>
</div>
</body>
</html>

<?php
/**
 * Shared <head> – include in every dashboard page.
 * Expects $pageTitle to be set before include.
 */
$pageTitle = $pageTitle ?? 'TCC RRS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
  document.documentElement.classList.add('js');
  try {
    if (localStorage.getItem('tccSidebarCollapsed') === '1' && window.innerWidth >= 992) {
      document.documentElement.classList.add('sidebar-collapsed');
    }
    if (localStorage.getItem('tccTheme') === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  } catch (_) {}
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?> – TCC Registration &amp; Records System</title>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<!-- Inter font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- App CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
</head>
<body>
<!-- Page loader (content area only — sidebar & topbar stay visible) -->
<div class="page-loader dash-loader" id="pageLoader" aria-hidden="true">
  <div class="pl-ring">
    <svg class="pl-arc-svg" viewBox="0 0 100 100"><circle class="pl-track" cx="50" cy="50" r="44"/><circle class="pl-arc" cx="50" cy="50" r="44"/></svg>
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="" class="pl-logo">
  </div>
  <div class="pl-text">Tampakan <b>Community College</b></div>
</div>

<div class="dashboard-wrapper">
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

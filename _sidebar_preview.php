<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();
$_SESSION['user_id']   = 1;
$_SESSION['user_role'] = 'staff';
$_SESSION['user']      = ['full_name' => 'Maria Santos'];
$_SERVER['PHP_SELF']   = '/staff/dashboard.php';
$pageTitle = 'Preview';
include __DIR__ . '/includes/partials/head.php';
include __DIR__ . '/includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/includes/partials/topbar.php'; ?>
<div class="page-content">
<?= ui_page_header('Good afternoon, Maria!', 'Monday, October 20, 2025 — here\'s today\'s registrar activity.') ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="content-card p-3">KPI card</div></div>
  <div class="col-sm-6 col-xl-3"><div class="content-card p-3">KPI card</div></div>
</div>
<div class="content-card p-3 mb-4">Chart / table block</div>
<div class="content-card p-3">Another block</div>
</div>
<?php include __DIR__ . '/includes/partials/footer.php'; ?>

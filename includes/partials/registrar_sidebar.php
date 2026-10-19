<?php
/**
 * Registrar Sidebar Navigation
 */
$user        = currentUser();
$initials    = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', $user['full_name']), 0, 2)));
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>/registrar/dashboard.php" class="sidebar-brand" title="Registrar Dashboard">
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="TCC seal" class="brand-logo">
    <div class="brand-text">
      <div class="brand-name">Tampakan CC</div>
      <div class="brand-sub">Registrar Panel</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav" role="navigation" aria-label="Registrar navigation">

    <div class="sidebar-section-label">Main</div>
    <a href="<?= BASE_URL ?>/registrar/dashboard.php"
       class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <div class="sidebar-section-label">Management</div>
    <a href="<?= BASE_URL ?>/registrar/staff.php"
       class="nav-link <?= in_array($currentFile, ['staff.php','create_staff.php','edit_staff.php']) ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Staff Accounts
    </a>
    <a href="<?= BASE_URL ?>/registrar/students.php"
       class="nav-link <?= $currentFile === 'students.php' ? 'active' : '' ?>">
      <i class="bi bi-mortarboard"></i> Student Accounts
    </a>

    <div class="sidebar-section-label">Monitoring</div>
    <a href="<?= BASE_URL ?>/registrar/registrations.php"
       class="nav-link <?= $currentFile === 'registrations.php' ? 'active' : '' ?>">
      <i class="bi bi-person-lines-fill"></i> Pre-Registrations
    </a>
    <a href="<?= BASE_URL ?>/registrar/document_requests.php"
       class="nav-link <?= $currentFile === 'document_requests.php' ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-ruled"></i> Document Requests
    </a>
    <a href="<?= BASE_URL ?>/staff/master_list.php"
       class="nav-link">
      <i class="bi bi-list-check"></i> Student Master List
    </a>

    <div class="sidebar-section-label">System</div>
    <a href="<?= BASE_URL ?>/registrar/reports.php"
       class="nav-link <?= $currentFile === 'reports.php' ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line"></i> Reports
    </a>
    <a href="<?= BASE_URL ?>/registrar/activity_logs.php"
       class="nav-link <?= $currentFile === 'activity_logs.php' ? 'active' : '' ?>">
      <i class="bi bi-journal-text"></i> Activity Logs
    </a>
    <a href="<?= BASE_URL ?>/registrar/settings.php"
       class="nav-link <?= $currentFile === 'settings.php' ? 'active' : '' ?>">
      <i class="bi bi-gear"></i> System Settings
    </a>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= e($initials) ?></div>
      <div class="user-info">
        <div class="user-name" title="<?= e($user['full_name']) ?>"><?= e($user['full_name']) ?></div>
        <div class="user-role">Registrar</div>
      </div>
    </div>
    <div class="footer-actions">
      <a href="<?= BASE_URL ?>/auth/change_password.php"
         class="btn btn-sm btn-outline-light flex-fill"
         title="Change Password">
        <i class="bi bi-key me-1"></i><span class="d-none d-xl-inline">Password</span>
      </a>
      <a href="<?= BASE_URL ?>/auth/logout.php"
         class="btn btn-sm btn-outline-light flex-fill"
         title="Sign Out">
        <i class="bi bi-box-arrow-right me-1"></i><span>Sign Out</span>
      </a>
    </div>
  </div>

</aside>

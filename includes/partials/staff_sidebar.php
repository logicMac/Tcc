<?php
/**
 * Staff Sidebar Navigation
 */
$user        = currentUser();
$initials    = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', $user['full_name']), 0, 2)));
$currentFile = basename($_SERVER['PHP_SELF']);

// Pending badges
$pendingReg = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending' AND is_archived = 0");
$pendingDoc = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending' AND is_archived = 0");
?>
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>/staff/dashboard.php" class="sidebar-brand" title="Staff Dashboard">
    <div class="brand-icon"><i class="bi bi-building"></i></div>
    <div class="brand-text">
      <div class="brand-name">Tampakan CC</div>
      <div class="brand-sub">Staff Panel</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav" role="navigation" aria-label="Staff navigation">

    <div class="sidebar-section-label">Main</div>
    <a href="<?= BASE_URL ?>/staff/dashboard.php"
       class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <div class="sidebar-section-label">Enrollments</div>
    <a href="<?= BASE_URL ?>/staff/registrations.php"
       class="nav-link <?= in_array($currentFile, ['registrations.php','view_registration.php','edit_registration.php']) ? 'active' : '' ?>">
      <i class="bi bi-person-plus"></i>
      <span>Pre-Registrations</span>
      <?php if ($pendingReg > 0): ?>
        <span class="badge bg-warning text-dark ms-auto"><?= $pendingReg ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/staff/master_list.php"
       class="nav-link <?= $currentFile === 'master_list.php' ? 'active' : '' ?>">
      <i class="bi bi-people"></i>
      <span>Student Master List</span>
    </a>

    <div class="sidebar-section-label">Documents</div>
    <a href="<?= BASE_URL ?>/staff/document_requests.php"
       class="nav-link <?= in_array($currentFile, ['document_requests.php','view_document.php','edit_document.php','issue_document.php']) ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-text"></i>
      <span>Document Requests</span>
      <?php if ($pendingDoc > 0): ?>
        <span class="badge bg-warning text-dark ms-auto"><?= $pendingDoc ?></span>
      <?php endif; ?>
    </a>

    <div class="sidebar-section-label">Tools</div>
    <a href="<?= BASE_URL ?>/staff/qr_verify.php"
       class="nav-link <?= $currentFile === 'qr_verify.php' ? 'active' : '' ?>">
      <i class="bi bi-qr-code-scan"></i> QR Verification
    </a>
    <a href="<?= BASE_URL ?>/staff/reports.php"
       class="nav-link <?= $currentFile === 'reports.php' ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line"></i> Reports
    </a>

  </nav>

  <!-- Footer: user info + actions -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= e($initials) ?></div>
      <div class="user-info">
        <div class="user-name" title="<?= e($user['full_name']) ?>"><?= e($user['full_name']) ?></div>
        <div class="user-role">Staff</div>
      </div>
    </div>
    <div class="footer-actions">
      <a href="<?= BASE_URL ?>/auth/change_password.php"
         class="btn btn-sm btn-outline-light flex-fill"
         title="Change Password"
         data-bs-toggle="tooltip" data-bs-placement="top">
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

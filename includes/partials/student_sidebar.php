<?php
/**
 * Student Sidebar Navigation (authenticated student pages)
 */
$stu         = currentStudent();
$stuInitials = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', trim($stu['first_name'] . ' ' . $stu['last_name'])), 0, 2)));
$currentFile = basename($_SERVER['PHP_SELF']);
$tab         = get('tab', 'registrations');
?>
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <a href="<?= BASE_URL ?>/student/account.php" class="sidebar-brand" title="My Account">
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="TCC seal" class="brand-logo">
    <div class="brand-text">
      <div class="brand-name">Tampakan CC</div>
      <div class="brand-sub">Student Portal</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sidebar-nav" role="navigation" aria-label="Student navigation">

    <div class="sidebar-section-label">My Record</div>
    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i>
      <span>Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>/student/account.php"
       class="nav-link <?= $currentFile === 'account.php' && $tab !== 'security' ? 'active' : '' ?>">
      <i class="bi bi-person-vcard"></i>
      <span>My Account</span>
    </a>
    <a href="<?= BASE_URL ?>/student/track.php"
       class="nav-link <?= $currentFile === 'track.php' ? 'active' : '' ?>">
      <i class="bi bi-search"></i>
      <span>Track Submission</span>
    </a>
    <a href="<?= BASE_URL ?>/student/notifications.php"
       class="nav-link <?= $currentFile === 'notifications.php' ? 'active' : '' ?>">
      <i class="bi bi-bell"></i>
      <span>Notifications</span>
      <?php $sbUnread = unreadStudentNotifCount(); if ($sbUnread): ?>
      <span class="badge bg-danger ms-auto" style="font-size:.65rem;"><?= $sbUnread > 99 ? '99+' : $sbUnread ?></span>
      <?php endif; ?>
    </a>

    <div class="sidebar-section-label">Services</div>
    <a href="<?= BASE_URL ?>/student/pre_registration.php"
       class="nav-link <?= $currentFile === 'pre_registration.php' ? 'active' : '' ?>">
      <i class="bi bi-person-plus"></i>
      <span>Pre-Register</span>
    </a>
    <a href="<?= BASE_URL ?>/student/document_request.php"
       class="nav-link <?= $currentFile === 'document_request.php' ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-text"></i>
      <span>Request Document</span>
    </a>
    <a href="<?= BASE_URL ?>/verify.php"
       class="nav-link <?= $currentFile === 'verify.php' ? 'active' : '' ?>">
      <i class="bi bi-qr-code-scan"></i>
      <span>Verify Document</span>
    </a>

    <div class="sidebar-section-label">Site</div>
    <a href="<?= BASE_URL ?>/student/index.php"
       class="nav-link <?= $currentFile === 'index.php' ? 'active' : '' ?>">
      <i class="bi bi-house"></i>
      <span>Portal Home</span>
    </a>

  </nav>

  <!-- Footer: student info + actions -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= e($stuInitials) ?></div>
      <div class="user-info">
        <div class="user-name" title="<?= e($stu['first_name'] . ' ' . $stu['last_name']) ?>">
          <?= e($stu['first_name'] . ' ' . $stu['last_name']) ?>
        </div>
        <div class="user-role"><?= e($stu['student_no'] ?? 'Student') ?></div>
      </div>
    </div>
  </div>

</aside>

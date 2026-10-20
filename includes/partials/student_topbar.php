<?php
/**
 * Student Topbar – authenticated student pages.
 */
$stu         = currentStudent();
$pageTitle   = $pageTitle ?? '';
$studentId   = currentStudentId();
$stuUnread   = unreadStudentNotifCount();
$stuNotifs   = [];
try {
    $stuNotifs = db()->fetchAll(
        "SELECT id, title, message, link, is_read, created_at
         FROM student_notifications
         WHERE student_id = ?
         ORDER BY created_at DESC LIMIT 6",
        [$studentId]
    );
} catch (Throwable) {}
?>
<header class="topbar">

  <!-- Sidebar toggle (drawer on mobile, icon rail on desktop) -->
  <button class="topbar-icon-btn" id="sidebarToggle" aria-label="Toggle sidebar" title="Toggle sidebar">
    <i class="bi bi-layout-sidebar-inset"></i>
  </button>

  <!-- Page context -->
  <div class="stu-topbar-context">
    <div class="stu-topbar-title"><?= e($pageTitle) ?></div>
    <div class="stu-topbar-sub d-none d-md-block">
      <?= date('l, F j, Y') ?> · Hello, <?= e($stu['first_name']) ?>
    </div>
  </div>

  <!-- Quick tracking search -->
  <form class="topbar-search d-none d-md-flex" method="GET"
        action="<?= BASE_URL ?>/student/track.php" role="search">
    <i class="bi bi-search"></i>
    <input type="search" name="code" placeholder="Track a code — TCC-REG-… or TCC-DOC-…"
           aria-label="Track a submission" autocomplete="off">
  </form>

  <!-- Right actions -->
  <div class="topbar-actions">

    <!-- Theme switch -->
    <button class="theme-switch" id="themeToggle" role="switch" aria-checked="false"
            aria-label="Toggle dark mode" title="Toggle dark mode">
      <span class="ts-track"><span class="ts-knob"><i class="bi bi-sun-fill"></i></span></span>
    </button>

    <!-- Notification bell -->
    <div class="dropdown">
      <button class="topbar-icon-btn" data-bs-toggle="dropdown" aria-label="Notifications"
              data-bs-offset="0,10">
        <i class="bi bi-bell"></i>
        <?php if ($stuUnread > 0): ?>
          <span class="notif-dot pulse"><span class="visually-hidden">Unread notifications</span></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-menu">
        <div class="tb-menu-head">
          <span>Notifications</span>
          <span class="tb-count <?= $stuUnread > 0 ? 'has-items' : '' ?>"><?= $stuUnread ?></span>
        </div>
        <div class="tb-menu-body">
          <?php if (empty($stuNotifs)): ?>
          <div class="tb-empty">
            <i class="bi bi-check-circle"></i>
            All caught up
          </div>
          <?php else: ?>
          <?php foreach ($stuNotifs as $n): ?>
          <a class="tb-item <?= $n['is_read'] ? '' : 'unread' ?>"
             href="<?= e($n['link'] ?: BASE_URL . '/student/notifications.php') ?>">
            <span class="tb-item-ico <?= $n['is_read'] ? 'green' : 'info' ?>">
              <i class="bi bi-<?= $n['is_read'] ? 'check2' : 'bell-fill' ?>"></i>
            </span>
            <span class="tb-item-text">
              <?= e(mb_strimwidth($n['title'], 0, 46, '…')) ?>
              <div class="tb-item-sub"><?= timeAgo($n['created_at']) ?></div>
            </span>
            <?php if (!$n['is_read']): ?><span class="tb-item-n info">New</span><?php endif; ?>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <a class="tb-menu-foot" href="<?= BASE_URL ?>/student/notifications.php">
          <i class="bi bi-bell"></i> View all notifications
        </a>
      </div>
    </div>

    <!-- Student menu -->
    <div class="dropdown">
      <button class="topbar-avatar-btn" data-bs-toggle="dropdown" data-bs-offset="0,10"
              aria-label="Account menu">
        <?= ui_avatar($stu['first_name'] . ' ' . $stu['last_name'], 34) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end topbar-menu">
        <li>
          <div class="tb-menu-head user-head">
            <?= ui_avatar($stu['first_name'] . ' ' . $stu['last_name'], 38) ?>
            <div class="tb-user-detail">
              <div class="tb-user-name full"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></div>
              <div class="tb-user-mail"><?= e($stu['email']) ?></div>
              <span class="tb-role-badge">Student</span>
            </div>
          </div>
        </li>
        <li><a class="tb-item" href="<?= BASE_URL ?>/student/dashboard.php">
          <span class="tb-item-ico green"><i class="bi bi-grid"></i></span>
          <span class="tb-item-text">Dashboard</span>
        </a></li>
        <li><a class="tb-item" href="<?= BASE_URL ?>/student/account.php">
          <span class="tb-item-ico green"><i class="bi bi-person-vcard"></i></span>
          <span class="tb-item-text">My account</span>
        </a></li>
        <li><a class="tb-item" href="<?= BASE_URL ?>/student/account.php?tab=security">
          <span class="tb-item-ico gold"><i class="bi bi-shield-lock"></i></span>
          <span class="tb-item-text">Security</span>
        </a></li>
        <li><div class="tb-sep"></div></li>
        <li><a class="tb-item danger" href="<?= BASE_URL ?>/student/logout.php">
          <span class="tb-item-ico danger"><i class="bi bi-box-arrow-right"></i></span>
          <span class="tb-item-text">Sign out</span>
        </a></li>
      </ul>
    </div>

  </div><!-- /.topbar-actions -->
</header>

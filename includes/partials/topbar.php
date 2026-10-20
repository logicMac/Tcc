<?php
/**
 * Shared Topbar – Staff & Registrar dashboards.
 */
$user      = currentUser();
$dashUrl   = BASE_URL . '/' . currentRole() . '/dashboard.php';
$changePwdUrl = BASE_URL . '/auth/change_password.php';
$logoutUrl = BASE_URL . '/auth/logout.php';
$role      = ucfirst(currentRole());

// Pending counts for notification bell
$pendingReg   = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending' AND is_archived = 0");
$pendingDoc   = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending' AND is_archived = 0");
$pendingTotal = $pendingReg + $pendingDoc;

// Current term
$termSY  = getSetting('current_school_year');
$termSem = getSetting('current_semester');
$termLabel = trim('S.Y. ' . $termSY . ($termSem ? ' · ' . $termSem : ''));
?>
<header class="topbar">

  <!-- ── Sidebar toggle (drawer on mobile, icon rail on desktop) ── -->
  <button class="topbar-icon-btn" id="sidebarToggle" aria-label="Toggle sidebar" title="Toggle sidebar">
    <i class="bi bi-layout-sidebar-inset"></i>
  </button>

  <!-- ── Global search ── -->
  <form class="topbar-search d-none d-md-flex" method="GET"
        action="<?= BASE_URL ?>/<?= currentRole() ?>/registrations.php" role="search">
    <i class="bi bi-search"></i>
    <input type="search" name="search" placeholder="Type to search…"
           aria-label="Search registrations" autocomplete="off">
    <kbd>/</kbd>
  </form>

  <!-- ── Right actions ── -->
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
        <?php if ($pendingTotal > 0): ?>
          <span class="notif-dot pulse"><span class="visually-hidden">Pending items</span></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end topbar-menu">
        <div class="tb-menu-head">
          <span>Notifications</span>
          <span class="tb-count <?= $pendingTotal > 0 ? 'has-items' : '' ?>"><?= $pendingTotal ?></span>
        </div>
        <div class="tb-menu-body">
          <?php if ($pendingTotal === 0): ?>
          <div class="tb-empty">
            <i class="bi bi-check-circle"></i>
            All caught up
          </div>
          <?php else: ?>
          <?php if ($pendingReg > 0): ?>
          <a class="tb-item" href="<?= BASE_URL ?>/<?= currentRole() ?>/registrations.php?status=Pending">
            <span class="tb-item-ico warn"><i class="bi bi-person-plus"></i></span>
            <span class="tb-item-text">Pending enrollments</span>
            <span class="tb-item-n warn"><?= $pendingReg ?></span>
          </a>
          <?php endif; ?>
          <?php if ($pendingDoc > 0): ?>
          <a class="tb-item" href="<?= BASE_URL ?>/<?= currentRole() ?>/document_requests.php?status=Pending">
            <span class="tb-item-ico info"><i class="bi bi-file-earmark-text"></i></span>
            <span class="tb-item-text">Pending doc requests</span>
            <span class="tb-item-n info"><?= $pendingDoc ?></span>
          </a>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <a class="tb-menu-foot" href="<?= BASE_URL ?>/<?= currentRole() ?>/reports.php">
          <i class="bi bi-bar-chart"></i> View reports
        </a>
      </div>
    </div>

    <!-- Current term -->
    <?php if ($termSY || $termSem): ?>
    <span class="topbar-icon-btn d-none d-lg-inline-flex" role="img" aria-label="Current term"
          data-bs-toggle="tooltip" data-bs-placement="bottom" title="Current term — <?= e($termLabel) ?>">
      <i class="bi bi-calendar3"></i>
    </span>
    <?php endif; ?>

    <!-- Fullscreen -->
    <button class="topbar-icon-btn d-none d-md-inline-flex" id="fullscreenToggle"
            aria-label="Toggle fullscreen" title="Toggle fullscreen">
      <i class="bi bi-fullscreen"></i>
    </button>

    <!-- User -->
    <div class="dropdown">
      <button class="topbar-avatar-btn" data-bs-toggle="dropdown" data-bs-offset="0,10"
              aria-label="Account menu">
        <?= ui_avatar($user['full_name'], 34) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end topbar-menu">
        <li>
          <div class="tb-menu-head user-head">
            <?= ui_avatar($user['full_name'], 38) ?>
            <div class="tb-user-detail">
              <div class="tb-user-name full"><?= e($user['full_name']) ?></div>
              <div class="tb-user-mail"><?= e($user['email']) ?></div>
              <span class="tb-role-badge"><?= e($role) ?></span>
            </div>
          </div>
        </li>
        <li><a class="tb-item" href="<?= $dashUrl ?>">
          <span class="tb-item-ico green"><i class="bi bi-grid"></i></span>
          <span class="tb-item-text">Dashboard</span>
        </a></li>
        <li><a class="tb-item" href="<?= $changePwdUrl ?>">
          <span class="tb-item-ico gold"><i class="bi bi-key"></i></span>
          <span class="tb-item-text">Change password</span>
        </a></li>
        <li><div class="tb-sep"></div></li>
        <li><a class="tb-item danger" href="<?= $logoutUrl ?>">
          <span class="tb-item-ico danger"><i class="bi bi-box-arrow-right"></i></span>
          <span class="tb-item-text">Sign out</span>
        </a></li>
      </ul>
    </div>

  </div><!-- /.topbar-actions -->
</header>

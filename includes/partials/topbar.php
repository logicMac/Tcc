<?php
/**
 * Shared Topbar – Staff & Registrar dashboards.
 *
 * Pages can set before including:
 *   $pageTitle    – page heading shown in topbar
 *   $breadcrumbs  – array of ['label'=>'...','url'=>'...'] + last item is current page
 *                   e.g. [ ['label'=>'Enrollments','url'=>'.../registrations.php'],
 *                           ['label'=>'TCC-REG-2026-000001'] ]
 */
$user      = currentUser();
$initials  = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', $user['full_name']), 0, 2)));
$dashUrl   = BASE_URL . '/' . currentRole() . '/dashboard.php';
$changePwdUrl = BASE_URL . '/auth/change_password.php';
$logoutUrl = BASE_URL . '/auth/logout.php';
$role      = ucfirst(currentRole());

// Pending counts for notification bell
$pendingReg   = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending' AND is_archived = 0");
$pendingDoc   = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending' AND is_archived = 0");
$pendingTotal = $pendingReg + $pendingDoc;

$breadcrumbs = $breadcrumbs ?? [];
?>
<header class="topbar">

  <!-- ── Mobile sidebar toggle ── -->
  <button class="btn btn-sidebar-toggle topbar-icon-btn" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list" style="font-size:1.2rem;"></i>
  </button>

  <!-- ── Title + breadcrumb ── -->
  <div class="topbar-breadcrumb">
    <?php if (!empty($breadcrumbs)): ?>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
          <a href="<?= $dashUrl ?>"><i class="bi bi-house-door"></i></a>
        </li>
        <?php foreach ($breadcrumbs as $i => $bc): ?>
        <?php if ($i < count($breadcrumbs) - 1): ?>
          <li class="breadcrumb-item">
            <a href="<?= e($bc['url'] ?? '#') ?>"><?= e($bc['label']) ?></a>
          </li>
        <?php else: ?>
          <li class="breadcrumb-item active" aria-current="page"><?= e($bc['label']) ?></li>
        <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php endif; ?>
    <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
  </div>

  <!-- ── Right actions ── -->
  <div class="topbar-actions">

    <!-- Notification bell (always visible) -->
    <div class="dropdown">
      <button class="topbar-icon-btn" data-bs-toggle="dropdown" aria-label="Notifications"
              data-bs-offset="0,8">
        <i class="bi bi-bell" style="font-size:.95rem;"></i>
        <?php if ($pendingTotal > 0): ?>
          <span class="notif-dot"></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
           style="min-width:260px;border-radius:12px;overflow:hidden;">
        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center"
             style="background:#f8fafc;">
          <span class="fw-semibold small">Notifications</span>
          <?php if ($pendingTotal > 0): ?>
          <span class="badge bg-danger rounded-pill"><?= $pendingTotal ?></span>
          <?php else: ?>
          <span class="badge bg-light text-muted rounded-pill">0</span>
          <?php endif; ?>
        </div>
        <?php if ($pendingTotal === 0): ?>
        <div class="py-4 text-center text-muted small">
          <i class="bi bi-check-circle d-block mb-1 fs-5 text-success"></i>
          All caught up!
        </div>
        <?php else: ?>
        <?php if ($pendingReg > 0): ?>
        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 px-3"
           href="<?= BASE_URL ?>/<?= currentRole() ?>/registrations.php?status=Pending">
          <div>
            <i class="bi bi-person-plus text-warning me-2"></i>
            <span class="small">Pending Enrollments</span>
          </div>
          <span class="badge bg-warning text-dark"><?= $pendingReg ?></span>
        </a>
        <?php endif; ?>
        <?php if ($pendingDoc > 0): ?>
        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 px-3"
           href="<?= BASE_URL ?>/<?= currentRole() ?>/document_requests.php?status=Pending">
          <div>
            <i class="bi bi-file-earmark-text text-info me-2"></i>
            <span class="small">Pending Doc Requests</span>
          </div>
          <span class="badge bg-info text-dark"><?= $pendingDoc ?></span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
        <div class="px-3 py-2 border-top text-center" style="background:#f8fafc;">
          <a href="<?= BASE_URL ?>/<?= currentRole() ?>/reports.php"
             class="small text-muted text-decoration-none">
            <i class="bi bi-bar-chart me-1"></i>View Reports
          </a>
        </div>
      </div>
    </div>

    <!-- User menu -->
    <div class="dropdown">
      <button class="d-flex align-items-center gap-2 border rounded-pill px-2 py-1 bg-white"
              style="cursor:pointer;border-color:#e2e8f0!important;"
              data-bs-toggle="dropdown" data-bs-offset="0,8">
        <div class="avatar-circle bg-primary text-white"
             style="width:28px;height:28px;font-size:.7rem;flex-shrink:0;">
          <?= e($initials) ?>
        </div>
        <div class="d-none d-md-block text-start" style="line-height:1.1;">
          <div class="small fw-semibold" style="font-size:.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= e($user['full_name']) ?>
          </div>
          <div style="font-size:.65rem;color:#94a3b8;text-transform:capitalize;"><?= e(currentRole()) ?></div>
        </div>
        <i class="bi bi-chevron-down d-none d-md-block" style="font-size:.6rem;color:#94a3b8;"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
          style="min-width:220px;border-radius:12px;overflow:hidden;">
        <!-- User info header -->
        <li>
          <div class="px-3 pt-3 pb-2 border-bottom" style="background:#f8fafc;">
            <div class="d-flex align-items-center gap-2 mb-1">
              <div class="avatar-circle bg-primary text-white"
                   style="width:36px;height:36px;font-size:.82rem;">
                <?= e($initials) ?>
              </div>
              <div style="min-width:0;">
                <div class="fw-semibold small" style="line-height:1.1;"><?= e($user['full_name']) ?></div>
                <div style="font-size:.7rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px;">
                  <?= e($user['email']) ?>
                </div>
              </div>
            </div>
            <span class="badge bg-primary" style="font-size:.65rem;"><?= e($role) ?></span>
          </div>
        </li>
        <!-- Links -->
        <li>
          <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= $dashUrl ?>">
            <i class="bi bi-grid text-primary" style="width:16px;"></i>
            <span class="small">Dashboard</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= $changePwdUrl ?>">
            <i class="bi bi-key text-warning" style="width:16px;"></i>
            <span class="small">Change Password</span>
          </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= $logoutUrl ?>">
            <i class="bi bi-box-arrow-right text-danger" style="width:16px;"></i>
            <span class="small">Sign Out</span>
          </a>
        </li>
      </ul>
    </div>

  </div><!-- /.topbar-actions -->
</header>

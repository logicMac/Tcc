<?php
/**
 * TCC RRS – Registrar Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'Registrar Dashboard';

$stats = [
    'total_staff'       => db()->fetchValue("SELECT COUNT(*) FROM users WHERE role='staff'"),
    'active_staff'      => db()->fetchValue("SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1"),
    'total_reg'         => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived=0"),
    'pending_reg'       => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Pending' AND is_archived=0"),
    'approved_reg'      => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Approved' AND is_archived=0"),
    'total_doc'         => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived=0"),
    'pending_doc'       => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Pending' AND is_archived=0"),
    'released_doc'      => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Released' AND is_archived=0"),
    'today_reg'         => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE DATE(submitted_at)=CURDATE()"),
    'today_doc'         => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE DATE(submitted_at)=CURDATE()"),
    'logs_today'        => db()->fetchValue("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at)=CURDATE()"),
];

$recentLogs = db()->fetchAll(
    "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 8"
);

$staffList = db()->fetchAll(
    "SELECT u.*, 
        (SELECT COUNT(*) FROM pre_registrations pr WHERE pr.assigned_to=u.id) AS reg_count,
        (SELECT COUNT(*) FROM document_requests dr WHERE dr.assigned_to=u.id) AS doc_count
     FROM users u WHERE u.role='staff' ORDER BY u.full_name ASC LIMIT 10"
);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4>Registrar Dashboard</h4>
    <p>System overview &mdash; <?= date('F d, Y') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/registrar/staff.php" class="btn btn-primary btn-sm">
      <i class="bi bi-people me-1"></i>Manage Staff
    </a>
  </div>
</div>

<!-- Stats Row 1 -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-people"></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Staff</div>
        <div class="stat-value"><?= (int)$stats['active_staff'] ?></div>
        <div class="stat-sub"><?= (int)$stats['total_staff'] ?> total accounts</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-person-plus"></i></div>
      <div class="stat-info">
        <div class="stat-label">Enrollments Today</div>
        <div class="stat-value"><?= (int)$stats['today_reg'] ?></div>
        <div class="stat-sub"><?= (int)$stats['pending_reg'] ?> pending total</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info">
        <div class="stat-label">Doc Requests Today</div>
        <div class="stat-value"><?= (int)$stats['today_doc'] ?></div>
        <div class="stat-sub"><?= (int)$stats['pending_doc'] ?> pending total</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon purple"><i class="bi bi-journal-text"></i></div>
      <div class="stat-info">
        <div class="stat-label">Activity Logs Today</div>
        <div class="stat-value"><?= (int)$stats['logs_today'] ?></div>
        <div class="stat-sub"><a href="<?= BASE_URL ?>/registrar/activity_logs.php" class="text-muted">View all logs</a></div>
      </div>
    </div>
  </div>
</div>

<!-- Stats Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-collection"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Enrollments</div>
        <div class="stat-value"><?= (int)$stats['total_reg'] ?></div>
        <div class="stat-sub"><?= (int)$stats['approved_reg'] ?> approved</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-collection"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Doc Requests</div>
        <div class="stat-value"><?= (int)$stats['total_doc'] ?></div>
        <div class="stat-sub"><?= (int)$stats['released_doc'] ?> released</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <a href="<?= BASE_URL ?>/registrar/registrations.php?status=Pending" class="stat-card text-decoration-none">
      <div class="stat-icon yellow"><i class="bi bi-clock"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Enrollments</div>
        <div class="stat-value"><?= (int)$stats['pending_reg'] ?></div>
        <div class="stat-sub text-warning">Need attention</div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-md-3">
    <a href="<?= BASE_URL ?>/registrar/document_requests.php?status=Pending" class="stat-card text-decoration-none">
      <div class="stat-icon red"><i class="bi bi-clock"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Documents</div>
        <div class="stat-value"><?= (int)$stats['pending_doc'] ?></div>
        <div class="stat-sub text-danger">Need attention</div>
      </div>
    </a>
  </div>
</div>

<!-- Staff + Activity Logs -->
<div class="row g-3">
  <!-- Staff Overview -->
  <div class="col-lg-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5><i class="bi bi-people me-2"></i>Staff Overview</h5>
        <a href="<?= BASE_URL ?>/registrar/staff.php" class="btn btn-sm btn-outline-primary">Manage</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Name</th><th>Employee ID</th><th>Status</th><th>Last Login</th></tr></thead>
          <tbody>
          <?php if (empty($staffList)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No staff accounts.</td></tr>
          <?php else: foreach ($staffList as $s): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle <?= $s['is_active'] ? 'bg-primary' : 'bg-secondary' ?> text-white" style="font-size:.7rem;">
                  <?= strtoupper(substr($s['full_name'],0,1)) ?>
                </div>
                <div>
                  <div class="fw-semibold small"><?= e($s['full_name']) ?></div>
                  <div class="text-muted" style="font-size:.7rem;"><?= e($s['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="small font-monospace"><?= e($s['employee_id']) ?></span></td>
            <td>
              <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <span class="text-muted small">
                <?= $s['last_login'] ? timeAgo($s['last_login']) : 'Never' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5><i class="bi bi-journal-text me-2"></i>Recent Activity</h5>
        <a href="<?= BASE_URL ?>/registrar/activity_logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
      <div class="content-card-body p-0">
        <?php if (empty($recentLogs)): ?>
        <div class="p-4 text-center text-muted">No activity logs yet.</div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($recentLogs as $log): ?>
          <li class="list-group-item px-3 py-2">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="small fw-semibold"><?= e($log['action']) ?>
                  <span class="badge bg-light text-dark ms-1 fw-normal"><?= e($log['module']) ?></span>
                </div>
                <div class="text-muted" style="font-size:.75rem;">
                  <?= e($log['user_name'] ?? 'System') ?>
                  <?= $log['description'] ? ' – ' . e(substr($log['description'],0,60)) : '' ?>
                </div>
              </div>
              <div class="text-muted text-nowrap ms-2" style="font-size:.7rem;">
                <?= timeAgo($log['created_at']) ?>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

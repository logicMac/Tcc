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

<?= ui_page_header(
    'Registrar Dashboard',
    'System overview — ' . date('F d, Y'),
    'speedometer2',
    '<a href="' . BASE_URL . '/registrar/staff.php" class="btn btn-primary btn-sm"><i class="bi bi-people me-1"></i>Manage Staff</a>'
) ?>

<!-- Stats Row 1 -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('people', 'Active Staff', $stats['active_staff'], 'green', (int)$stats['total_staff'] . ' total accounts') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('person-plus', 'Enrollments Today', $stats['today_reg'], 'gold', (int)$stats['pending_reg'] . ' pending total') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('file-earmark-text', 'Doc Requests Today', $stats['today_doc'], 'teal', (int)$stats['pending_doc'] . ' pending total') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('journal-text', 'Activity Logs Today', $stats['logs_today'], 'purple', '', BASE_URL . '/registrar/activity_logs.php') ?>
  </div>
</div>

<!-- Stats Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('collection', 'Total Enrollments', $stats['total_reg'], 'blue', (int)$stats['approved_reg'] . ' approved') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('collection', 'Total Doc Requests', $stats['total_doc'], 'green', (int)$stats['released_doc'] . ' released') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('clock', 'Pending Enrollments', $stats['pending_reg'], 'yellow', 'Need attention', BASE_URL . '/registrar/registrations.php?status=Pending') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('clock', 'Pending Documents', $stats['pending_doc'], 'red', 'Need attention', BASE_URL . '/registrar/document_requests.php?status=Pending') ?>
  </div>
</div>

<!-- Staff + Activity Logs -->
<div class="row g-3">
  <!-- Staff Overview -->
  <div class="col-lg-6">
    <?= ui_card_open('Staff Overview', 'people', '<a href="' . BASE_URL . '/registrar/staff.php" class="btn btn-sm btn-outline-primary">Manage</a>') ?>
    <?php
    $rows = [];
    foreach ($staffList as $s) {
        $rows[] = [
            '<div class="d-flex align-items-center gap-2">'
                . ui_avatar($s['full_name'], 30, $s['is_active'] ? 'primary' : 'secondary')
                . '<div><div class="row-name">' . e($s['full_name']) . '</div><div class="row-sub">' . e($s['email']) . '</div></div>'
            . '</div>',
            '<span class="small font-monospace">' . e($s['employee_id']) . '</span>',
            '<span class="badge ' . ($s['is_active'] ? 'bg-success' : 'bg-secondary') . '">' . ($s['is_active'] ? 'Active' : 'Inactive') . '</span>',
            '<span class="text-muted small">' . ($s['last_login'] ? timeAgo($s['last_login']) : 'Never') . '</span>',
        ];
    }
    echo ui_table(['Name', 'Employee ID', 'Status', 'Last Login'], $rows,
                  ['empty_msg' => 'No staff accounts.', 'empty_icon' => 'people']);
    ?>
    <?= ui_card_close() ?>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-6">
    <?= ui_card_open('Recent Activity', 'journal-text', '<a href="' . BASE_URL . '/registrar/activity_logs.php" class="btn btn-sm btn-outline-secondary">View All</a>') ?>
      <div class="content-card-body p-0">
        <?php if (empty($recentLogs)): ?>
        <?= ui_empty('journal-text', 'No activity logs yet') ?>
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
    <?= ui_card_close() ?>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

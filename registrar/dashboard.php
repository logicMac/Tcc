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

// ── KPI stats ────────────────────────────────────────────────────────────────
$stats = [
    'total_staff'   => (int) db()->fetchValue("SELECT COUNT(*) FROM users WHERE role='staff'"),
    'active_staff'  => (int) db()->fetchValue("SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1"),
    'total_reg'     => (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived=0"),
    'pending_reg'   => (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Pending' AND is_archived=0"),
    'approved_reg'  => (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Approved' AND is_archived=0"),
    'total_doc'     => (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived=0"),
    'pending_doc'   => (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Pending' AND is_archived=0"),
    'released_doc'  => (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Released' AND is_archived=0"),
    'logs_today'    => (int) db()->fetchValue("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at)=CURDATE()"),
];

// Month-over-month deltas
$regThisMo = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived=0 AND YEAR(submitted_at)=YEAR(CURDATE()) AND MONTH(submitted_at)=MONTH(CURDATE())");
$regLastMo = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived=0 AND YEAR(submitted_at)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(submitted_at)=MONTH(CURDATE() - INTERVAL 1 MONTH)");
$docThisMo = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived=0 AND YEAR(submitted_at)=YEAR(CURDATE()) AND MONTH(submitted_at)=MONTH(CURDATE())");
$docLastMo = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived=0 AND YEAR(submitted_at)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(submitted_at)=MONTH(CURDATE() - INTERVAL 1 MONTH)");

$mom = fn(int $cur, int $prev): ?float =>
    $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : ($cur > 0 ? 100.0 : null);

$pendingAll   = $stats['pending_reg'] + $stats['pending_doc'];
$approvalRate = $stats['total_reg'] > 0 ? round($stats['approved_reg'] / $stats['total_reg'] * 100, 1) : 0;

// ── 90-day submission series ────────────────────────────────────────────────
$regSeries = $docSeries = [];
foreach (db()->fetchAll(
    "SELECT DATE(submitted_at) d, COUNT(*) c FROM pre_registrations
     WHERE submitted_at >= CURDATE() - INTERVAL 90 DAY GROUP BY DATE(submitted_at)"
) as $r) { $regSeries[$r['d']] = (int) $r['c']; }
foreach (db()->fetchAll(
    "SELECT DATE(submitted_at) d, COUNT(*) c FROM document_requests
     WHERE submitted_at >= CURDATE() - INTERVAL 90 DAY GROUP BY DATE(submitted_at)"
) as $r) { $docSeries[$r['d']] = (int) $r['c']; }

$chartLabels = $chartRegs = $chartDocs = [];
for ($i = 89; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M j', strtotime($d));
    $chartRegs[]   = $regSeries[$d] ?? 0;
    $chartDocs[]   = $docSeries[$d] ?? 0;
}

$recentLogs = db()->fetchAll("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
$staffList  = db()->fetchAll(
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
    'System overview — ' . date('l, F d, Y'),
    '',
    '<div class="dropdown">
       <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
         <i class="bi bi-plus-lg me-1"></i>Quick Create
       </button>
       <ul class="dropdown-menu dropdown-menu-end">
         <li><a class="dropdown-item" href="' . BASE_URL . '/registrar/create_staff.php"><i class="bi bi-person-plus me-2"></i>New Staff Account</a></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/registrar/registrations.php"><i class="bi bi-collection me-2"></i>View Registrations</a></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/registrar/document_requests.php"><i class="bi bi-file-earmark-text me-2"></i>Doc Requests</a></li>
         <li><hr class="dropdown-divider"></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/registrar/reports.php"><i class="bi bi-bar-chart me-2"></i>Reports</a></li>
       </ul>
     </div>'
) ?>

<!-- ── KPI cards ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Total Pre-Registrations', number_format($stats['total_reg']), $mom($regThisMo, $regLastMo),
               $regThisMo . ' submitted this month', BASE_URL . '/registrar/registrations.php') ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Document Requests', number_format($stats['total_doc']), $mom($docThisMo, $docLastMo),
               $docThisMo . ' submitted this month', BASE_URL . '/registrar/document_requests.php') ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Pending Items', number_format($pendingAll), null,
               $stats['pending_reg'] . ' enrollments · ' . $stats['pending_doc'] . ' documents',
               BASE_URL . '/registrar/registrations.php?status=Pending') ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Active Staff', number_format($stats['active_staff']), null,
               $stats['total_staff'] . ' accounts · ' . $stats['logs_today'] . ' actions today',
               BASE_URL . '/registrar/staff.php') ?>
  </div>
</div>

<!-- ── Submissions chart ── -->
<div class="chart-card content-card mb-4">
  <div class="content-card-header">
    <div>
      <h5 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Submissions</h5>
      <div class="text-muted" style="font-size:.72rem;">Pre-registrations &amp; document requests per day</div>
    </div>
    <div class="chart-tabs" id="chartTabs">
      <button type="button" data-range="90" class="active">Last 3 months</button>
      <button type="button" data-range="30">Last 30 days</button>
      <button type="button" data-range="7">Last 7 days</button>
    </div>
  </div>
  <div class="content-card-body">
    <div class="chart-wrap"><canvas id="submissionsChart"></canvas></div>
  </div>
</div>

<!-- ── Tabbed table: Staff / Activity ── -->
<div class="content-card">
  <div class="tbl-tabs" id="recentTabs">
    <button type="button" class="ttab active" data-pane="staff">
      Staff Overview <span class="count"><?= count($staffList) ?></span>
    </button>
    <button type="button" class="ttab" data-pane="logs">
      Recent Activity <span class="count"><?= count($recentLogs) ?></span>
    </button>
    <div class="ttab-spacer"></div>
    <a href="<?= BASE_URL ?>/registrar/staff.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-people me-1"></i>Manage Staff
    </a>
  </div>

  <div class="tbl-pane show" id="pane-staff">
    <?php
    $rows = [];
    foreach ($staffList as $s) {
        $rows[] = [
            '<div class="d-flex align-items-center gap-2">'
                . ui_avatar($s['full_name'], 30, $s['is_active'] ? 'primary' : 'secondary')
                . '<div><div class="row-name">' . e($s['full_name']) . '</div><div class="row-sub">' . e($s['email']) . '</div></div>'
            . '</div>',
            '<span class="small font-monospace">' . e($s['employee_id']) . '</span>',
            '<span class="badge badge-soft-' . ($s['is_active'] ? 'success' : 'secondary') . '">' . ($s['is_active'] ? 'Active' : 'Inactive') . '</span>',
            '<span class="num">' . (int)$s['reg_count'] . ' / ' . (int)$s['doc_count'] . '</span>',
            '<span class="text-muted small">' . ($s['last_login'] ? timeAgo($s['last_login']) : 'Never') . '</span>',
        ];
    }
    echo ui_table(['Name', 'Employee ID', 'Status', 'Regs / Docs', 'Last Login'], $rows,
                  ['empty_msg' => 'No staff accounts.', 'empty_icon' => 'people']);
    ?>
    <div class="tbl-foot">
      <span><?= count($staffList) ?> staff account<?= count($staffList) === 1 ? '' : 's' ?></span>
      <a href="<?= BASE_URL ?>/registrar/staff.php" class="text-decoration-none fw-semibold">Manage <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>

  <div class="tbl-pane" id="pane-logs">
    <?php if (empty($recentLogs)): ?>
    <?= ui_empty('journal-text', 'No activity logs yet') ?>
    <?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($recentLogs as $log): ?>
      <li class="list-group-item px-3 py-2">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="small fw-semibold"><?= e($log['action']) ?>
              <span class="badge bg-light text-dark border ms-1 fw-normal"><?= e($log['module']) ?></span>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
              <?= e($log['user_name'] ?? 'System') ?>
              <?= $log['description'] ? ' – ' . e(substr($log['description'],0,60)) : '' ?>
            </div>
          </div>
          <div class="text-muted text-nowrap ms-2" style="font-size:.7rem;"><?= timeAgo($log['created_at']) ?></div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="tbl-foot">
      <span>Latest <?= count($recentLogs) ?> activity entries</span>
      <a href="<?= BASE_URL ?>/registrar/activity_logs.php" class="text-decoration-none fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</div>

</div><!-- /page-content -->
<script src="<?= BASE_URL ?>/assets/js/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('#recentTabs .ttab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#recentTabs .ttab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tbl-pane').forEach(p => p.classList.remove('show'));
      btn.classList.add('active');
      document.getElementById('pane-' + btn.dataset.pane).classList.add('show');
    });
  });

  if (typeof Chart === 'undefined') return;
  const labels = <?= json_encode($chartLabels) ?>;
  const regs   = <?= json_encode($chartRegs) ?>;
  const docs   = <?= json_encode($chartDocs) ?>;

  const ctx   = document.getElementById('submissionsChart').getContext('2d');
  const green = getComputedStyle(document.documentElement).getPropertyValue('--tcc-green-600').trim() || '#2c7a4f';
  const gold  = getComputedStyle(document.documentElement).getPropertyValue('--tcc-gold-500').trim()  || '#c9a227';
  const gGrad = ctx.createLinearGradient(0, 0, 0, 300);
  gGrad.addColorStop(0, green + '55'); gGrad.addColorStop(1, green + '05');
  const yGrad = ctx.createLinearGradient(0, 0, 0, 300);
  yGrad.addColorStop(0, gold + '4d'); yGrad.addColorStop(1, gold + '05');

  const chart = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [
      { label: 'Pre-Registrations', data: regs, borderColor: green, backgroundColor: gGrad,
        fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
        pointHoverBackgroundColor: green, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
      { label: 'Document Requests', data: docs, borderColor: gold, backgroundColor: yGrad,
        fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
        pointHoverBackgroundColor: gold, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
    ]},
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#10281c', padding: 10, cornerRadius: 8,
                   titleFont: { weight: 700, size: 11 }, bodyFont: { size: 12 }, boxPadding: 4 },
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 }, color: '#8a998e' },
             border: { color: '#e2e8e3' } },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#8a998e' },
             grid: { color: '#eef2ee' }, border: { display: false } },
      },
    },
  });

  document.querySelectorAll('#chartTabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#chartTabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const n = parseInt(btn.dataset.range, 10);
      chart.data.labels = labels.slice(-n);
      chart.data.datasets[0].data = regs.slice(-n);
      chart.data.datasets[1].data = docs.slice(-n);
      chart.update();
    });
  });
});
</script>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

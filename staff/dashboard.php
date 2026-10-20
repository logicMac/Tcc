<?php
/**
 * TCC RRS – Staff Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'Dashboard';

// ── KPI stats ────────────────────────────────────────────────────────────────
$regTotal   = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 0");
$docTotal   = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived = 0");
$regReady   = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Ready for Release' AND is_archived = 0");
$regApproved= (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Approved' AND is_archived = 0");
$docReleased= (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Released' AND is_archived = 0");
$regPending = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending' AND is_archived = 0");
$docPending = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending' AND is_archived = 0");

// Month-over-month deltas
$regThisMo  = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 0 AND YEAR(submitted_at)=YEAR(CURDATE()) AND MONTH(submitted_at)=MONTH(CURDATE())");
$regLastMo  = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 0 AND YEAR(submitted_at)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(submitted_at)=MONTH(CURDATE() - INTERVAL 1 MONTH)");
$docThisMo  = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived = 0 AND YEAR(submitted_at)=YEAR(CURDATE()) AND MONTH(submitted_at)=MONTH(CURDATE())");
$docLastMo  = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived = 0 AND YEAR(submitted_at)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(submitted_at)=MONTH(CURDATE() - INTERVAL 1 MONTH)");

$mom = fn(int $cur, int $prev): ?float =>
    $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : ($cur > 0 ? 100.0 : null);

$approvalRate  = $regTotal > 0 ? round($regApproved / $regTotal * 100, 1) : 0;
$releaseRate   = $docTotal > 0 ? round($docReleased / $docTotal * 100, 1) : 0;

// ── 90-day submission series (for the chart) ────────────────────────────────
$regSeries = [];
foreach (db()->fetchAll(
    "SELECT DATE(submitted_at) d, COUNT(*) c FROM pre_registrations
     WHERE submitted_at >= CURDATE() - INTERVAL 90 DAY GROUP BY DATE(submitted_at)"
) as $r) { $regSeries[$r['d']] = (int) $r['c']; }

$docSeries = [];
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

// ── Recent submissions ─────────────────────────────────────────────────────
$recentRegs = db()->fetchAll(
    "SELECT pr.*, p.code AS prog_code
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.is_archived = 0
     ORDER BY pr.submitted_at DESC LIMIT 10"
);
$recentDocs = db()->fetchAll(
    "SELECT dr.*, dt.code AS doc_code
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.is_archived = 0
     ORDER BY dr.submitted_at DESC LIMIT 10"
);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= renderFlash() ?>

<?= ui_page_header(
    'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . ', '
        . e(explode(' ', currentUser()['full_name'])[0]) . '!',
    date('l, F d, Y') . ' — here\'s today\'s registrar activity.',
    '',
    '<div class="dropdown">
       <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
         <i class="bi bi-plus-lg me-1"></i>Quick Create
       </button>
       <ul class="dropdown-menu dropdown-menu-end">
         <li><a class="dropdown-item" href="' . BASE_URL . '/' . currentRole() . '/registrations.php"><i class="bi bi-person-plus me-2"></i>View Registrations</a></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/' . currentRole() . '/document_requests.php"><i class="bi bi-file-earmark-plus me-2"></i>View Doc Requests</a></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/' . currentRole() . '/issue_document.php"><i class="bi bi-patch-check me-2"></i>Issue Document</a></li>
         <li><hr class="dropdown-divider"></li>
         <li><a class="dropdown-item" href="' . BASE_URL . '/' . currentRole() . '/qr_verify.php"><i class="bi bi-qr-code-scan me-2"></i>QR Verify</a></li>
       </ul>
     </div>'
) ?>

<!-- ── KPI cards ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Total Pre-Registrations', number_format($regTotal), $mom($regThisMo, $regLastMo),
               $regThisMo . ' submitted this month',
               BASE_URL . '/' . currentRole() . '/registrations.php') ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Document Requests', number_format($docTotal), $mom($docThisMo, $docLastMo),
               $docThisMo . ' submitted this month',
               BASE_URL . '/' . currentRole() . '/document_requests.php') ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Ready for Release', number_format($regReady), null,
               $docReleased . ' released to date',
               BASE_URL . '/' . currentRole() . '/document_requests.php?status=' . urlencode('Ready for Release')) ?>
  </div>
  <div class="col-sm-6 col-xl-3">
    <?= ui_kpi('Approval Rate', $approvalRate . '%', null,
               $regApproved . ' of ' . $regTotal . ' registrations approved',
               BASE_URL . '/' . currentRole() . '/registrations.php?status=Approved') ?>
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

<!-- ── Tabbed recent table ── -->
<div class="content-card">
  <div class="tbl-tabs" id="recentTabs">
    <button type="button" class="ttab active" data-pane="regs">
      Pre-Registrations <span class="count"><?= count($recentRegs) ?></span>
    </button>
    <button type="button" class="ttab" data-pane="docs">
      Document Requests <span class="count"><?= count($recentDocs) ?></span>
    </button>
    <div class="ttab-spacer"></div>
    <a href="<?= BASE_URL ?>/<?= currentRole() ?>/reports.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-bar-chart me-1"></i>Reports
    </a>
  </div>

  <div class="tbl-pane show" id="pane-regs">
    <?php
    $regRows = [];
    foreach ($recentRegs as $r) {
        $regRows[] = [
            '<div class="row-name">' . e($r['last_name'] . ', ' . $r['first_name']) . '</div><div class="row-sub">' . e($r['tracking_code']) . '</div>',
            '<span class="badge bg-light text-dark small border">' . e($r['prog_code'] ?? '—') . '</span>',
            statusBadge($r['status']),
            '<span class="text-muted small">' . timeAgo($r['submitted_at']) . '</span>',
            '<a href="' . BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
        ];
    }
    echo ui_table(['Applicant', 'Program', 'Status', 'Submitted', ''], $regRows,
                  ['empty_title' => 'No registrations yet', 'empty_msg' => 'New submissions will appear here.']);
    ?>
    <div class="tbl-foot">
      <span>Showing latest <?= count($recentRegs) ?> of <?= number_format($regTotal) ?> registrations</span>
      <a href="<?= BASE_URL ?>/<?= currentRole() ?>/registrations.php" class="text-decoration-none fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>

  <div class="tbl-pane" id="pane-docs">
    <?php
    $docRows = [];
    foreach ($recentDocs as $d) {
        $docRows[] = [
            '<div class="row-name">' . e($d['last_name'] . ', ' . $d['first_name']) . '</div><div class="row-sub">' . e($d['tracking_code']) . '</div>',
            '<span class="badge bg-light text-dark small border">' . e($d['doc_code'] ?? '—') . '</span>',
            statusBadge($d['status'], 'document_request'),
            '<span class="text-muted small">' . timeAgo($d['submitted_at']) . '</span>',
            '<a href="' . BASE_URL . '/' . currentRole() . '/view_document.php?id=' . (int)$d['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
        ];
    }
    echo ui_table(['Requester', 'Document', 'Status', 'Submitted', ''], $docRows,
                  ['empty_title' => 'No requests yet', 'empty_msg' => 'New requests will appear here.']);
    ?>
    <div class="tbl-foot">
      <span>Showing latest <?= count($recentDocs) ?> of <?= number_format($docTotal) ?> requests</span>
      <a href="<?= BASE_URL ?>/<?= currentRole() ?>/document_requests.php" class="text-decoration-none fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</div>

</div><!-- /page-content -->
<script src="<?= BASE_URL ?>/assets/js/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ── Tabbed table ──
  document.querySelectorAll('#recentTabs .ttab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#recentTabs .ttab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tbl-pane').forEach(p => p.classList.remove('show'));
      btn.classList.add('active');
      document.getElementById('pane-' + btn.dataset.pane).classList.add('show');
    });
  });

  // ── Submissions area chart ──
  if (typeof Chart === 'undefined') return;
  const labels = <?= json_encode($chartLabels) ?>;
  const regs   = <?= json_encode($chartRegs) ?>;
  const docs   = <?= json_encode($chartDocs) ?>;

  const ctx = document.getElementById('submissionsChart').getContext('2d');
  const green = getComputedStyle(document.documentElement).getPropertyValue('--tcc-green-600').trim() || '#2c7a4f';
  const gold  = getComputedStyle(document.documentElement).getPropertyValue('--tcc-gold-500').trim()  || '#c9a227';

  const gGrad = ctx.createLinearGradient(0, 0, 0, 300);
  gGrad.addColorStop(0, green + '55'); gGrad.addColorStop(1, green + '05');
  const yGrad = ctx.createLinearGradient(0, 0, 0, 300);
  yGrad.addColorStop(0, gold + '4d'); yGrad.addColorStop(1, gold + '05');

  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Pre-Registrations', data: regs, borderColor: green, backgroundColor: gGrad,
          fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
          pointHoverBackgroundColor: green, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
        { label: 'Document Requests', data: docs, borderColor: gold, backgroundColor: yGrad,
          fill: true, tension: .35, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
          pointHoverBackgroundColor: gold, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#10281c', padding: 10, cornerRadius: 8,
          titleFont: { weight: 700, size: 11 }, bodyFont: { size: 12 },
          boxPadding: 4,
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 }, color: '#8a998e' },
             border: { color: '#e2e8e3' } },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#8a998e' },
             grid: { color: '#eef2ee' }, border: { display: false } },
      },
    },
  });

  // Range tabs — slice the last N days
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

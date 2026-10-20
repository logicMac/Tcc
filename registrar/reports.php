<?php
/**
 * TCC RRS – Registrar: Reports & Analytics
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'Reports';
$sy = getSetting('current_school_year','2026-2027');

$regByStatus = db()->fetchAll("SELECT status, COUNT(*) AS total FROM pre_registrations GROUP BY status ORDER BY total DESC");
$docByStatus = db()->fetchAll("SELECT status, COUNT(*) AS total FROM document_requests GROUP BY status ORDER BY total DESC");
$regByProgram = db()->fetchAll(
    "SELECT COALESCE(p.code, 'Other') AS prog, COUNT(*) AS total
     FROM pre_registrations pr LEFT JOIN programs p ON p.id = pr.program_id
     GROUP BY prog ORDER BY total DESC LIMIT 10"
);
$docByType = db()->fetchAll(
    "SELECT COALESCE(dt.name, pr.document_type_other,'Unknown') AS dtype, COUNT(*) AS total
     FROM document_requests pr LEFT JOIN document_types dt ON dt.id = pr.document_type_id
     GROUP BY dtype ORDER BY total DESC"
);
$staffActivity = db()->fetchAll(
    "SELECT u.full_name, COUNT(al.id) AS log_count, MAX(al.created_at) AS last_activity
     FROM users u LEFT JOIN activity_logs al ON al.user_id=u.id
     WHERE u.role='staff' GROUP BY u.id ORDER BY log_count DESC"
);

// Continuous 12-month trend series
$trendLabels = []; $trendRegs = []; $trendDocs = [];
$regMap = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS m, COUNT(*) AS t
     FROM pre_registrations WHERE submitted_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY m"
);
$docMap = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS m, COUNT(*) AS t
     FROM document_requests WHERE submitted_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY m"
);
$regMap = array_column($regMap, 't', 'm');
$docMap = array_column($docMap, 't', 'm');
for ($i = 11; $i >= 0; $i--) {
    $ts = strtotime("first day of -$i months");
    $key = date('Y-m', $ts);
    $trendLabels[] = date('M Y', $ts);
    $trendRegs[]   = (int)($regMap[$key] ?? 0);
    $trendDocs[]   = (int)($docMap[$key] ?? 0);
}

$totalReg    = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations");
$totalDoc    = db()->fetchValue("SELECT COUNT(*) FROM document_requests");
$approvedReg = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Approved'");
$releasedDoc = db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Released'");

// Status → chart color (green family = completed, gold = pending, amber = correction, red = declined)
$statusColors = [
    'Pending'           => '#c9a227',
    'Under Review'      => '#5b8db8',
    'Processing'        => '#3c9970',
    'Approved'          => '#176849',
    'Ready for Release' => '#1e7d56',
    'Released'          => '#115540',
    'Needs Correction'  => '#d97706',
    'Declined'          => '#c0362c',
];
$colorFor = static fn(string $s) => $statusColors[$s] ?? '#87958b';

$regStatusLabels = array_column($regByStatus, 'status');
$regStatusData   = array_map('intval', array_column($regByStatus, 'total'));
$regStatusColors = array_map($colorFor, $regStatusLabels);
$docStatusLabels = array_column($docByStatus, 'status');
$docStatusData   = array_map('intval', array_column($docByStatus, 'total'));
$docStatusColors = array_map($colorFor, $docStatusLabels);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= ui_page_header(
    'Reports &amp; Analytics', 'System-wide statistics for S.Y. ' . e($sy), 'bar-chart-line',
    '<button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i>Print</button>'
) ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('person-plus', 'Total Enrollments', $totalReg, 'green', (int)$approvedReg . ' approved') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('file-earmark-text', 'Total Doc Requests', $totalDoc, 'teal', (int)$releasedDoc . ' released') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('percent', 'Enrollment Approval Rate', ($totalReg > 0 ? round($approvedReg / $totalReg * 100) : 0) . '%', 'gold') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('graph-up', 'Doc Release Rate', ($totalDoc > 0 ? round($releasedDoc / $totalDoc * 100) : 0) . '%', 'blue') ?>
  </div>
</div>

<!-- Trend: chart + table tabs -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="content-card">
      <div class="content-card-header">
        <h5><i class="bi bi-graph-up-arrow me-1"></i>Submissions Trend</h5>
        <div class="content-card-header-right">
          <div class="chart-tabs" id="trendTabs">
            <button type="button" class="active" data-pane="chart">Chart</button>
            <button type="button" data-pane="table">Data</button>
          </div>
        </div>
      </div>
      <div class="tbl-pane show" id="pane-chart">
        <div class="content-card-body">
          <div class="d-flex gap-3 mb-2 small text-muted">
            <span><span class="legend-dot" style="background:var(--tcc-green-600)"></span>Pre-Registrations</span>
            <span><span class="legend-dot" style="background:var(--tcc-gold-500)"></span>Document Requests</span>
          </div>
          <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
        </div>
      </div>
      <div class="tbl-pane" id="pane-table">
        <?php
        $mRows = [];
        for ($i = 11; $i >= 0; $i--) {
            $mRows[] = [
                e($trendLabels[$i]),
                ['html' => (string)$trendRegs[$i], 'class' => 'text-end'],
                ['html' => (string)$trendDocs[$i], 'class' => 'text-end'],
                ['html' => '<span class="fw-semibold">' . ($trendRegs[$i] + $trendDocs[$i]) . '</span>', 'class' => 'text-end'],
            ];
        }
        echo ui_table(['Month', ['label' => 'Enrollments', 'class' => 'text-end'],
                       ['label' => 'Doc Requests', 'class' => 'text-end'], ['label' => 'Total', 'class' => 'text-end']],
                      $mRows, ['class' => 'table-sm', 'empty_msg' => 'No submissions in the last 12 months.']);
        ?>
      </div>
    </div>
  </div>
</div>

<!-- Status doughnuts -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <?= ui_card_open('Enrollments by Status', 'person-plus', '', 'h-100') ?>
    <div class="content-card-body">
      <?php if (!$totalReg): ?>
        <?= ui_empty('pie-chart', 'No enrollments yet') ?>
      <?php else: ?>
      <div class="row align-items-center g-3">
        <div class="col-5">
          <div class="chart-wrap" style="height:210px">
            <canvas id="regStatusChart"></canvas>
            <div class="donut-center"><strong><?= (int)$totalReg ?></strong><span>total</span></div>
          </div>
        </div>
        <div class="col-7">
          <ul class="chart-legend">
            <?php foreach ($regByStatus as $r): ?>
            <li>
              <span class="legend-dot" style="background:<?= e($colorFor($r['status'])) ?>"></span>
              <span class="legend-label"><?= e($r['status']) ?></span>
              <span class="legend-val"><?= (int)$r['total'] ?> <em><?= round($r['total']/$totalReg*100) ?>%</em></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?= ui_card_close() ?>
  </div>
  <div class="col-md-6">
    <?= ui_card_open('Document Requests by Status', 'file-earmark-text', '', 'h-100') ?>
    <div class="content-card-body">
      <?php if (!$totalDoc): ?>
        <?= ui_empty('pie-chart', 'No document requests yet') ?>
      <?php else: ?>
      <div class="row align-items-center g-3">
        <div class="col-5">
          <div class="chart-wrap" style="height:210px">
            <canvas id="docStatusChart"></canvas>
            <div class="donut-center"><strong><?= (int)$totalDoc ?></strong><span>total</span></div>
          </div>
        </div>
        <div class="col-7">
          <ul class="chart-legend">
            <?php foreach ($docByStatus as $d): ?>
            <li>
              <span class="legend-dot" style="background:<?= e($colorFor($d['status'])) ?>"></span>
              <span class="legend-label"><?= e($d['status']) ?></span>
              <span class="legend-val"><?= (int)$d['total'] ?> <em><?= round($d['total']/$totalDoc*100) ?>%</em></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

<!-- Category bars -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <?= ui_card_open('Top Programs', 'mortarboard', '', 'h-100') ?>
    <div class="content-card-body">
      <?php if (!$regByProgram): ?>
        <?= ui_empty('bar-chart', 'No data yet') ?>
      <?php else: ?>
        <div class="chart-wrap" style="height:<?= max(160, count($regByProgram) * 34) ?>px"><canvas id="programChart"></canvas></div>
      <?php endif; ?>
    </div>
    <?= ui_card_close() ?>
  </div>
  <div class="col-md-6">
    <?= ui_card_open('Documents by Type', 'file-earmark-text', '', 'h-100') ?>
    <div class="content-card-body">
      <?php if (!$docByType): ?>
        <?= ui_empty('bar-chart', 'No data yet') ?>
      <?php else: ?>
        <div class="chart-wrap" style="height:<?= max(160, count($docByType) * 34) ?>px"><canvas id="docTypeChart"></canvas></div>
      <?php endif; ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

<!-- Staff Activity -->
<div class="row g-3">
  <div class="col-12">
    <?= ui_card_open('Staff Activity Summary', 'activity') ?>
    <?php
    $rows = [];
    foreach ($staffActivity as $sa) {
        $rows[] = [
            '<span class="fw-semibold small">' . e($sa['full_name']) . '</span>',
            ['html' => (string)(int)$sa['log_count'], 'class' => 'text-end'],
            '<span class="text-muted small">' . ($sa['last_activity'] ? formatDateTime($sa['last_activity']) : 'No activity') . '</span>',
        ];
    }
    echo ui_table(['Staff Member', ['label' => 'Total Actions', 'class' => 'text-end'], 'Last Activity'],
                  $rows, ['class' => 'table-sm', 'empty_msg' => 'No staff accounts.']);
    ?>
    <?= ui_card_close() ?>
  </div>
</div>

</div><!-- /page-content -->
<script src="<?= BASE_URL ?>/assets/js/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('#trendTabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#trendTabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('pane-chart').classList.toggle('show', btn.dataset.pane === 'chart');
      document.getElementById('pane-table').classList.toggle('show', btn.dataset.pane === 'table');
    });
  });

  if (typeof Chart === 'undefined') return;
  const css = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  const green = css('--tcc-green-600') || '#176849';
  const gold  = css('--tcc-gold-500')  || '#c9a227';
  const grid  = '#eef2ee', tick = '#8a998e', border = '#e2e8e3';

  const tooltip = { backgroundColor: '#10281c', padding: 10, cornerRadius: 8,
                    titleFont: { weight: 700, size: 11 }, bodyFont: { size: 12 }, boxPadding: 4 };

  // ── Trend area chart ──
  const tCtx = document.getElementById('trendChart').getContext('2d');
  const gGrad = tCtx.createLinearGradient(0, 0, 0, 300);
  gGrad.addColorStop(0, green + '55'); gGrad.addColorStop(1, green + '05');
  const yGrad = tCtx.createLinearGradient(0, 0, 0, 300);
  yGrad.addColorStop(0, gold + '4d'); yGrad.addColorStop(1, gold + '05');
  new Chart(tCtx, {
    type: 'line',
    data: { labels: <?= json_encode($trendLabels) ?>, datasets: [
      { label: 'Pre-Registrations', data: <?= json_encode($trendRegs) ?>, borderColor: green,
        backgroundColor: gGrad, fill: true, tension: .35, borderWidth: 2,
        pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: green,
        pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
      { label: 'Document Requests', data: <?= json_encode($trendDocs) ?>, borderColor: gold,
        backgroundColor: yGrad, fill: true, tension: .35, borderWidth: 2,
        pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: gold,
        pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
    ]},
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, color: tick }, border: { color: border } },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: tick },
             grid: { color: grid }, border: { display: false } },
      },
    },
  });

  // ── Status doughnuts ──
  const donutOpts = {
    responsive: true, maintainAspectRatio: false, cutout: '68%',
    plugins: { legend: { display: false }, tooltip },
  };
  const donutData = (labels, data, colors) => ({
    labels, datasets: [{ data, backgroundColor: colors, borderColor: '#fff', borderWidth: 2, hoverOffset: 6 }],
  });
  const regEl = document.getElementById('regStatusChart');
  if (regEl) new Chart(regEl, { type: 'doughnut',
    data: donutData(<?= json_encode($regStatusLabels) ?>, <?= json_encode($regStatusData) ?>, <?= json_encode($regStatusColors) ?>),
    options: donutOpts });
  const docEl = document.getElementById('docStatusChart');
  if (docEl) new Chart(docEl, { type: 'doughnut',
    data: donutData(<?= json_encode($docStatusLabels) ?>, <?= json_encode($docStatusData) ?>, <?= json_encode($docStatusColors) ?>),
    options: donutOpts });

  // ── Horizontal bars ──
  const barOpts = color => ({
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip },
    scales: {
      x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: tick },
           grid: { color: grid }, border: { display: false } },
      y: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#4c5f52' }, border: { color: border } },
    },
  });
  const barData = (labels, data, color) => ({
    labels, datasets: [{ data, backgroundColor: color, borderRadius: 5, barThickness: 16, maxBarThickness: 18 }],
  });
  const progEl = document.getElementById('programChart');
  if (progEl) new Chart(progEl, { type: 'bar',
    data: barData(<?= json_encode(array_column($regByProgram, 'prog')) ?>,
                  <?= json_encode(array_map('intval', array_column($regByProgram, 'total'))) ?>, green),
    options: barOpts(green) });
  const dtEl = document.getElementById('docTypeChart');
  if (dtEl) new Chart(dtEl, { type: 'bar',
    data: barData(<?= json_encode(array_column($docByType, 'dtype')) ?>,
                  <?= json_encode(array_map('intval', array_column($docByType, 'total'))) ?>, gold),
    options: barOpts(gold) });
});
</script>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

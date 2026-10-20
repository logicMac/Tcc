<?php
/**
 * TCC RRS – Staff: Reports
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'Reports';
$sy = getSetting('current_school_year','2026-2027');

// ── Aggregate stats ────────────────────────────────────────────────────────
$regByStatus = db()->fetchAll(
    "SELECT status, COUNT(*) AS total FROM pre_registrations GROUP BY status ORDER BY total DESC"
);
$docByStatus = db()->fetchAll(
    "SELECT status, COUNT(*) AS total FROM document_requests GROUP BY status ORDER BY total DESC"
);
$regByProgram = db()->fetchAll(
    "SELECT COALESCE(p.code, CONCAT('Other: ',SUBSTR(pr.program_other,1,15))) AS prog,
            COUNT(*) AS total
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     GROUP BY prog ORDER BY total DESC LIMIT 10"
);
$docByType = db()->fetchAll(
    "SELECT COALESCE(dt.name, pr.document_type_other) AS dtype, COUNT(*) AS total
     FROM document_requests pr
     LEFT JOIN document_types dt ON dt.id = pr.document_type_id
     GROUP BY dtype ORDER BY total DESC"
);
$regByMonth = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month,
            DATE_FORMAT(submitted_at,'%b %Y') AS label,
            COUNT(*) AS total
     FROM pre_registrations
     WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY month ORDER BY month ASC"
);
$docByMonth = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month,
            DATE_FORMAT(submitted_at,'%b %Y') AS label,
            COUNT(*) AS total
     FROM document_requests
     WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY month ORDER BY month ASC"
);

$totalReg = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations");
$totalDoc = db()->fetchValue("SELECT COUNT(*) FROM document_requests");
$approvedReg = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Approved'");
$releasedDoc = db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Released'");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= ui_page_header(
    'Reports', 'System-wide statistics for ' . e($sy) . '.', 'bar-chart-line',
    ui_btn('Print Report', ['size'=>'sm','variant'=>'outline-dark','icon'=>'printer','attrs'=>'onclick="window.print()"'])
) ?>

<!-- Summary -->
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

<div class="row g-3 mb-4">
  <!-- Enrollment by Status -->
  <div class="col-md-6">
    <?= ui_card_open('Enrollments by Status', 'person-plus', '', 'h-100') ?>
    <div class="content-card-body">
      <?php
      $rows = [];
      foreach ($regByStatus as $r) {
          $rows[] = [
              statusBadge($r['status']),
              ['html' => '<span class="fw-bold">' . (int)$r['total'] . '</span>', 'class' => 'text-end'],
              ['html' => '<span class="text-muted small">' . ($totalReg > 0 ? round($r['total'] / $totalReg * 100) : 0) . '%</span>', 'class' => 'text-end'],
          ];
      }
      echo ui_table(['Status', ['label' => 'Count', 'class' => 'text-end'], ['label' => '%', 'class' => 'text-end']],
                    $rows, ['class' => 'table-sm', 'empty_msg' => 'No data yet.']);
      ?>
    </div>
    <?= ui_card_close() ?>
  </div>

  <!-- Doc Requests by Status -->
  <div class="col-md-6">
    <?= ui_card_open('Document Requests by Status', 'file-earmark-text', '', 'h-100') ?>
    <div class="content-card-body">
      <?php
      $rows = [];
      foreach ($docByStatus as $d) {
          $rows[] = [
              statusBadge($d['status'], 'document_request'),
              ['html' => '<span class="fw-bold">' . (int)$d['total'] . '</span>', 'class' => 'text-end'],
              ['html' => '<span class="text-muted small">' . ($totalDoc > 0 ? round($d['total'] / $totalDoc * 100) : 0) . '%</span>', 'class' => 'text-end'],
          ];
      }
      echo ui_table(['Status', ['label' => 'Count', 'class' => 'text-end'], ['label' => '%', 'class' => 'text-end']],
                    $rows, ['class' => 'table-sm', 'empty_msg' => 'No data yet.']);
      ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Enrollment by Program -->
  <div class="col-md-6">
    <?= ui_card_open('Top Programs', 'mortarboard') ?>
    <div class="content-card-body">
      <?php
      $maxP = !empty($regByProgram) ? max(array_column($regByProgram, 'total')) : 1;
      $progItems = [];
      foreach ($regByProgram as $p) {
          $progItems[$p['prog']] = ['val' => (int)$p['total'], 'color' => 'var(--tcc-green-600)'];
      }
      echo ui_progress_rows($progItems, 90) ?: ui_empty('bar-chart', 'No data yet');
      ?>
    </div>
    <?= ui_card_close() ?>
  </div>

  <!-- Doc by Type -->
  <div class="col-md-6">
    <?= ui_card_open('Documents by Type', 'file-earmark-text') ?>
    <div class="content-card-body">
      <?php
      $maxD = !empty($docByType) ? max(array_column($docByType, 'total')) : 1;
      $docItems = [];
      foreach ($docByType as $d) {
          $docItems[$d['dtype'] ?? '—'] = ['val' => (int)$d['total'], 'color' => 'var(--tcc-gold-600)'];
      }
      echo ui_progress_rows($docItems, 140) ?: ui_empty('bar-chart', 'No data yet');
      ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

<!-- Monthly trend -->
<div class="row g-3">
  <div class="col-12">
    <?= ui_card_open('Monthly Submissions (Last 12 Months)', 'calendar3') ?>
    <div class="content-card-body">
      <?php
      $regMonthMap = array_column($regByMonth, 'total', 'month');
      $docMonthMap = array_column($docByMonth, 'total', 'month');
      $allMonths = array_unique(array_merge(array_column($regByMonth, 'month'), array_column($docByMonth, 'month')));
      rsort($allMonths);
      $labels = array_column($regByMonth, 'label', 'month') + array_column($docByMonth, 'label', 'month');
      $mRows = [];
      foreach ($allMonths as $m) {
          $rC = (int)($regMonthMap[$m] ?? 0);
          $dC = (int)($docMonthMap[$m] ?? 0);
          $mRows[] = [
              e($labels[$m] ?? $m),
              ['html' => (string)$rC, 'class' => 'text-end'],
              ['html' => (string)$dC, 'class' => 'text-end'],
              ['html' => '<span class="fw-semibold">' . ($rC + $dC) . '</span>', 'class' => 'text-end'],
          ];
      }
      echo ui_table(['Month', ['label' => 'Enrollments', 'class' => 'text-end'],
                     ['label' => 'Doc Requests', 'class' => 'text-end'], ['label' => 'Total', 'class' => 'text-end']],
                    $mRows, ['class' => 'table-sm', 'empty_msg' => 'No submissions in the last 12 months.']);
      ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

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
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
  <div class="page-header-info">
    <h4>Reports</h4>
    <p>System-wide statistics for <?= e($sy) ?>.</p>
  </div>
  <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
    <i class="bi bi-printer me-1"></i>Print Report
  </button>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-person-plus"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Enrollments</div>
        <div class="stat-value"><?= (int)$totalReg ?></div>
        <div class="stat-sub"><?= (int)$approvedReg ?> approved</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Doc Requests</div>
        <div class="stat-value"><?= (int)$totalDoc ?></div>
        <div class="stat-sub"><?= (int)$releasedDoc ?> released</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-percent"></i></div>
      <div class="stat-info">
        <div class="stat-label">Enrollment Approval Rate</div>
        <div class="stat-value"><?= $totalReg > 0 ? round($approvedReg / $totalReg * 100) : 0 ?>%</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon teal"><i class="bi bi-graph-up"></i></div>
      <div class="stat-info">
        <div class="stat-label">Doc Release Rate</div>
        <div class="stat-value"><?= $totalDoc > 0 ? round($releasedDoc / $totalDoc * 100) : 0 ?>%</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Enrollment by Status -->
  <div class="col-md-6">
    <div class="content-card h-100">
      <div class="content-card-header"><h5>Enrollments by Status</h5></div>
      <div class="content-card-body">
        <table class="table table-sm">
          <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">%</th></tr></thead>
          <tbody>
          <?php foreach ($regByStatus as $r): ?>
          <tr>
            <td><?= statusBadge($r['status']) ?></td>
            <td class="text-end fw-bold"><?= (int)$r['total'] ?></td>
            <td class="text-end text-muted small"><?= $totalReg > 0 ? round($r['total']/$totalReg*100) : 0 ?>%</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Doc Requests by Status -->
  <div class="col-md-6">
    <div class="content-card h-100">
      <div class="content-card-header"><h5>Document Requests by Status</h5></div>
      <div class="content-card-body">
        <table class="table table-sm">
          <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">%</th></tr></thead>
          <tbody>
          <?php foreach ($docByStatus as $d): ?>
          <tr>
            <td><?= statusBadge($d['status'],'document_request') ?></td>
            <td class="text-end fw-bold"><?= (int)$d['total'] ?></td>
            <td class="text-end text-muted small"><?= $totalDoc > 0 ? round($d['total']/$totalDoc*100) : 0 ?>%</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Enrollment by Program -->
  <div class="col-md-6">
    <div class="content-card">
      <div class="content-card-header"><h5>Top Programs</h5></div>
      <div class="content-card-body">
        <?php $maxP = !empty($regByProgram) ? max(array_column($regByProgram,'total')) : 1; ?>
        <?php foreach ($regByProgram as $p): ?>
        <div class="d-flex align-items-center mb-2">
          <div style="width:80px;font-size:.78rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($p['prog']) ?></div>
          <div class="flex-grow-1 mx-2">
            <div class="progress" style="height:10px;border-radius:5px;">
              <div class="progress-bar bg-primary" style="width:<?= round($p['total']/$maxP*100) ?>%;border-radius:5px;"></div>
            </div>
          </div>
          <div style="width:30px;text-align:right;font-size:.8rem;font-weight:600;"><?= (int)$p['total'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Doc by Type -->
  <div class="col-md-6">
    <div class="content-card">
      <div class="content-card-header"><h5>Documents by Type</h5></div>
      <div class="content-card-body">
        <?php $maxD = !empty($docByType) ? max(array_column($docByType,'total')) : 1; ?>
        <?php foreach ($docByType as $d): ?>
        <div class="d-flex align-items-center mb-2">
          <div style="width:120px;font-size:.78rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['dtype'] ?? '—') ?></div>
          <div class="flex-grow-1 mx-2">
            <div class="progress" style="height:10px;border-radius:5px;">
              <div class="progress-bar bg-success" style="width:<?= round($d['total']/$maxD*100) ?>%;border-radius:5px;"></div>
            </div>
          </div>
          <div style="width:30px;text-align:right;font-size:.8rem;font-weight:600;"><?= (int)$d['total'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Monthly trend -->
<div class="row g-3">
  <div class="col-12">
    <div class="content-card">
      <div class="content-card-header"><h5>Monthly Submissions (Last 12 Months)</h5></div>
      <div class="content-card-body">
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>Month</th><th class="text-end">Enrollments</th><th class="text-end">Doc Requests</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php
            $regMonthMap = array_column($regByMonth, 'total', 'month');
            $docMonthMap = array_column($docByMonth, 'total', 'month');
            // Merge all months
            $allMonths = array_unique(array_merge(array_column($regByMonth,'month'), array_column($docByMonth,'month')));
            rsort($allMonths);
            $labels = array_column($regByMonth,'label','month') + array_column($docByMonth,'label','month');
            foreach ($allMonths as $m):
              $rC = (int)($regMonthMap[$m] ?? 0);
              $dC = (int)($docMonthMap[$m] ?? 0);
            ?>
            <tr>
              <td><?= e($labels[$m] ?? $m) ?></td>
              <td class="text-end"><?= $rC ?></td>
              <td class="text-end"><?= $dC ?></td>
              <td class="text-end fw-semibold"><?= $rC + $dC ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

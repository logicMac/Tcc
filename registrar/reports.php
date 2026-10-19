<?php
/**
 * TCC RRS – Registrar: Reports (delegates to staff reports view with registrar access)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

// Registrar has the same reports as staff; we just swap the sidebar
$pageTitle = 'Reports';
$sy = getSetting('current_school_year','2026-2027');

$regByStatus = db()->fetchAll("SELECT status, COUNT(*) AS total FROM pre_registrations GROUP BY status ORDER BY total DESC");
$docByStatus = db()->fetchAll("SELECT status, COUNT(*) AS total FROM document_requests GROUP BY status ORDER BY total DESC");
$regByProgram = db()->fetchAll(
    "SELECT COALESCE(p.code, CONCAT('Other')) AS prog, COUNT(*) AS total
     FROM pre_registrations pr LEFT JOIN programs p ON p.id = pr.program_id
     GROUP BY prog ORDER BY total DESC LIMIT 10"
);
$docByType = db()->fetchAll(
    "SELECT COALESCE(dt.name, pr.document_type_other,'Unknown') AS dtype, COUNT(*) AS total
     FROM document_requests pr LEFT JOIN document_types dt ON dt.id = pr.document_type_id
     GROUP BY dtype ORDER BY total DESC"
);
$regByMonth = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month, DATE_FORMAT(submitted_at,'%b %Y') AS label, COUNT(*) AS total
     FROM pre_registrations WHERE submitted_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY month ORDER BY month ASC"
);
$docByMonth = db()->fetchAll(
    "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month, DATE_FORMAT(submitted_at,'%b %Y') AS label, COUNT(*) AS total
     FROM document_requests WHERE submitted_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY month ORDER BY month ASC"
);
$staffActivity = db()->fetchAll(
    "SELECT u.full_name, COUNT(al.id) AS log_count, MAX(al.created_at) AS last_activity
     FROM users u LEFT JOIN activity_logs al ON al.user_id=u.id
     WHERE u.role='staff' GROUP BY u.id ORDER BY log_count DESC"
);

$totalReg    = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations");
$totalDoc    = db()->fetchValue("SELECT COUNT(*) FROM document_requests");
$approvedReg = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status='Approved'");
$releasedDoc = db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status='Released'");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
  <div class="page-header-info">
    <h4>Reports &amp; Analytics</h4>
    <p>System-wide statistics for <?= e($sy) ?></p>
  </div>
  <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-person-plus"></i></div>
      <div class="stat-info"><div class="stat-label">Total Enrollments</div><div class="stat-value"><?= (int)$totalReg ?></div><div class="stat-sub"><?= (int)$approvedReg ?> approved</div></div></div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card"><div class="stat-icon green"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info"><div class="stat-label">Total Doc Requests</div><div class="stat-value"><?= (int)$totalDoc ?></div><div class="stat-sub"><?= (int)$releasedDoc ?> released</div></div></div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card"><div class="stat-icon yellow"><i class="bi bi-percent"></i></div>
      <div class="stat-info"><div class="stat-label">Enrollment Approval Rate</div><div class="stat-value"><?= $totalReg>0?round($approvedReg/$totalReg*100):0 ?>%</div></div></div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card"><div class="stat-icon teal"><i class="bi bi-graph-up"></i></div>
      <div class="stat-info"><div class="stat-label">Doc Release Rate</div><div class="stat-value"><?= $totalDoc>0?round($releasedDoc/$totalDoc*100):0 ?>%</div></div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="content-card h-100">
      <div class="content-card-header"><h5>Enrollments by Status</h5></div>
      <div class="content-card-body">
        <table class="table table-sm">
          <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">%</th></tr></thead>
          <tbody><?php foreach ($regByStatus as $r): ?>
          <tr><td><?= statusBadge($r['status']) ?></td>
            <td class="text-end fw-bold"><?= (int)$r['total'] ?></td>
            <td class="text-end text-muted small"><?= $totalReg>0?round($r['total']/$totalReg*100):0 ?>%</td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="content-card h-100">
      <div class="content-card-header"><h5>Document Requests by Status</h5></div>
      <div class="content-card-body">
        <table class="table table-sm">
          <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">%</th></tr></thead>
          <tbody><?php foreach ($docByStatus as $d): ?>
          <tr><td><?= statusBadge($d['status'],'document_request') ?></td>
            <td class="text-end fw-bold"><?= (int)$d['total'] ?></td>
            <td class="text-end text-muted small"><?= $totalDoc>0?round($d['total']/$totalDoc*100):0 ?>%</td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Staff Activity -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="content-card">
      <div class="content-card-header"><h5>Staff Activity Summary</h5></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Staff Member</th><th class="text-end">Total Actions</th><th>Last Activity</th></tr></thead>
          <tbody>
          <?php foreach ($staffActivity as $sa): ?>
          <tr>
            <td class="fw-semibold small"><?= e($sa['full_name']) ?></td>
            <td class="text-end"><?= (int)$sa['log_count'] ?></td>
            <td class="text-muted small"><?= $sa['last_activity'] ? formatDateTime($sa['last_activity']) : 'No activity' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
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
            $regMap = array_column($regByMonth,'total','month');
            $docMap = array_column($docByMonth,'total','month');
            $months = array_unique(array_merge(array_column($regByMonth,'month'),array_column($docByMonth,'month')));
            rsort($months);
            $lbls = array_column($regByMonth,'label','month') + array_column($docByMonth,'label','month');
            foreach ($months as $m):
              $r=(int)($regMap[$m]??0); $d=(int)($docMap[$m]??0);
            ?>
            <tr>
              <td><?= e($lbls[$m]??$m) ?></td>
              <td class="text-end"><?= $r ?></td>
              <td class="text-end"><?= $d ?></td>
              <td class="text-end fw-semibold"><?= $r+$d ?></td>
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

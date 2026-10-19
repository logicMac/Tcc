<?php
/**
 * TCC RRS – Registrar: Monitor Pre-Registrations (read-only overview)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle     = 'Monitor Enrollments';
$filterStatus  = get('status');
$filterSearch  = get('search');
$page          = max(1, getInt('page', 1));
$perPage       = 25;

$where  = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'pr.status = ?'; $params[] = $filterStatus; }
if ($filterSearch) {
    $where[] = "(pr.tracking_code LIKE ? OR pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.email LIKE ?)";
    $q = '%'.$filterSearch.'%';
    $params = array_merge($params, [$q,$q,$q,$q]);
}
$whereStr = implode(' AND ', $where);
$sql = "SELECT pr.*, p.code AS prog_code, u.full_name AS assigned_name
        FROM pre_registrations pr
        LEFT JOIN programs p ON p.id = pr.program_id
        LEFT JOIN users u ON u.id = pr.assigned_to
        WHERE {$whereStr}
        ORDER BY pr.submitted_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);
$qBase  = array_filter([$filterStatus?'status='.urlencode($filterStatus):'', $filterSearch?'search='.urlencode($filterSearch):'']);
$baseUrl = BASE_URL.'/registrar/registrations.php?'.implode('&',$qBase);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>
<div class="page-header">
  <div class="page-header-info">
    <h4>Monitor Pre-Registrations</h4>
    <p>Overview of all student enrollment submissions.</p>
  </div>
  <a href="<?= BASE_URL ?>/registrar/reports.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-bar-chart me-1"></i>View Reports
  </a>
</div>

<!-- Status tiles -->
<div class="row g-2 mb-4">
  <?php foreach (REG_STATUSES as $s => $info): ?>
  <?php $cnt = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status=?",[$s]); ?>
  <div class="col-6 col-sm-4 col-md-2">
    <a href="<?= BASE_URL ?>/registrar/registrations.php?status=<?= urlencode($s) ?>"
       class="stat-card text-decoration-none py-2 px-3 <?= $filterStatus===$s?'border border-primary':'' ?>">
      <div class="stat-info">
        <div class="stat-label" style="font-size:.65rem;"><?= e($s) ?></div>
        <div class="stat-value" style="font-size:1.4rem;"><?= (int)$cnt ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-5"><input type="text" name="search" class="form-control form-control-sm" value="<?= e($filterSearch) ?>" placeholder="Name, code, email..."></div>
    <div class="col-sm-3">
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (array_keys(REG_STATUSES) as $s): ?>
        <option value="<?= e($s) ?>" <?= $filterStatus===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="<?= BASE_URL ?>/registrar/registrations.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="content-card">
  <div class="content-card-header">
    <h5>Pre-Registrations <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>Tracking Code</th><th>Name</th><th>Program</th><th>Type</th>
        <th>Status</th><th>Assigned To</th><th>Submitted</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records.</p></div></td></tr>
      <?php else: foreach ($result['data'] as $r): ?>
      <tr>
        <td><span class="font-monospace small"><?= e($r['tracking_code']) ?></span></td>
        <td>
          <div class="fw-semibold small"><?= e(fullName($r,true)) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= e($r['email']) ?></div>
        </td>
        <td><span class="badge bg-light text-dark small"><?= e($r['prog_code'] ?? '—') ?></span></td>
        <td><span class="small"><?= e($r['enrollment_type']) ?></span></td>
        <td><?= statusBadge($r['status']) ?></td>
        <td><span class="text-muted small"><?= e($r['assigned_name'] ?? 'Unassigned') ?></span></td>
        <td><span class="text-muted small"><?= formatDate($r['submitted_at']) ?></span></td>
        <td>
          <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$r['id'] ?>"
             class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($result['pages'] > 1): ?>
  <div class="px-3 py-2 border-top d-flex justify-content-end">
    <?= paginationLinks($page, $result['pages'], $baseUrl) ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

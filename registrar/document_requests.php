<?php
/**
 * TCC RRS – Registrar: Monitor Document Requests
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle    = 'Monitor Document Requests';
$filterStatus = get('status');
$filterSearch = get('search');
$page         = max(1, getInt('page', 1));
$perPage      = 25;

$where  = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'dr.status = ?'; $params[] = $filterStatus; }
if ($filterSearch) {
    $where[] = "(dr.tracking_code LIKE ? OR dr.first_name LIKE ? OR dr.last_name LIKE ? OR dr.email LIKE ? OR dr.student_id LIKE ?)";
    $q = '%'.$filterSearch.'%';
    $params = array_merge($params,[$q,$q,$q,$q,$q]);
}
$sql = "SELECT dr.*, dt.code AS doc_code, u.full_name AS assigned_name
        FROM document_requests dr
        LEFT JOIN document_types dt ON dt.id = dr.document_type_id
        LEFT JOIN users u ON u.id = dr.assigned_to
        WHERE ".implode(' AND ',$where)."
        ORDER BY dr.submitted_at DESC";

$result  = db()->paginate($sql, $params, $page, $perPage);
$qBase   = array_filter([$filterStatus?'status='.urlencode($filterStatus):'', $filterSearch?'search='.urlencode($filterSearch):'']);
$baseUrl = BASE_URL.'/registrar/document_requests.php?'.implode('&',$qBase);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>
<div class="page-header">
  <div class="page-header-info">
    <h4>Monitor Document Requests</h4>
    <p>Overview of all student document request submissions.</p>
  </div>
</div>

<!-- Status tiles -->
<div class="row g-2 mb-4">
  <?php foreach (DOC_STATUSES as $s => $info): ?>
  <?php $cnt = db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status=?",[$s]); ?>
  <div class="col-6 col-sm-4 col-md-2">
    <a href="<?= BASE_URL ?>/registrar/document_requests.php?status=<?= urlencode($s) ?>"
       class="stat-card text-decoration-none py-2 px-3 <?= $filterStatus===$s?'border border-success':'' ?>">
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
    <div class="col-sm-5"><input type="text" name="search" class="form-control form-control-sm" value="<?= e($filterSearch) ?>" placeholder="Name, code, student ID..."></div>
    <div class="col-sm-3">
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (array_keys(DOC_STATUSES) as $s): ?>
        <option value="<?= e($s) ?>" <?= $filterStatus===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="<?= BASE_URL ?>/registrar/document_requests.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="content-card">
  <div class="content-card-header">
    <h5>Document Requests <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>Tracking Code</th><th>Name</th><th>Student ID</th><th>Document</th>
        <th>Status</th><th>Assigned To</th><th>Submitted</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records.</p></div></td></tr>
      <?php else: foreach ($result['data'] as $d): ?>
      <tr>
        <td><span class="font-monospace small"><?= e($d['tracking_code']) ?></span></td>
        <td>
          <div class="fw-semibold small"><?= e(fullName($d,true)) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= e($d['email']) ?></div>
        </td>
        <td><span class="small"><?= e($d['student_id'] ?: '—') ?></span></td>
        <td><span class="badge bg-light text-dark small"><?= e($d['doc_code'] ?? '—') ?></span></td>
        <td><?= statusBadge($d['status'],'document_request') ?></td>
        <td><span class="text-muted small"><?= e($d['assigned_name'] ?? 'Unassigned') ?></span></td>
        <td><span class="text-muted small"><?= formatDate($d['submitted_at']) ?></span></td>
        <td>
          <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$d['id'] ?>"
             class="btn btn-sm btn-outline-success py-0 px-2">View</a>
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

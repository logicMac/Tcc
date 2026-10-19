<?php
/**
 * TCC RRS – Staff: Document Requests List
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'Document Requests';

$filterStatus  = get('status');
$filterDocType = getInt('doc_type');
$filterSearch  = get('search');
$showArchived  = get('archived') === '1';
$page          = max(1, getInt('page', 1));
$perPage       = 20;

$where  = ['dr.is_archived = ?'];
$params = [$showArchived ? 1 : 0];

if ($filterStatus) {
    $where[]  = 'dr.status = ?';
    $params[] = $filterStatus;
}
if ($filterDocType) {
    $where[]  = 'dr.document_type_id = ?';
    $params[] = $filterDocType;
}
if ($filterSearch) {
    $where[]  = "(dr.tracking_code LIKE ? OR dr.first_name LIKE ? OR dr.last_name LIKE ? OR dr.email LIKE ? OR dr.student_id LIKE ?)";
    $q        = '%' . $filterSearch . '%';
    $params   = array_merge($params, [$q, $q, $q, $q, $q]);
}

$whereStr = implode(' AND ', $where);
$sql = "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code
        FROM document_requests dr
        LEFT JOIN document_types dt ON dt.id = dr.document_type_id
        WHERE {$whereStr}
        ORDER BY dr.submitted_at DESC";

$result   = db()->paginate($sql, $params, $page, $perPage);
$docTypes = getActiveDocumentTypes();

$queryParts = array_filter([
    $filterStatus  ? 'status='   . urlencode($filterStatus) : '',
    $filterDocType ? 'doc_type=' . $filterDocType           : '',
    $filterSearch  ? 'search='   . urlencode($filterSearch) : '',
    $showArchived  ? 'archived=1'                           : '',
]);
$baseUrl = BASE_URL . '/staff/document_requests.php?' . implode('&', $queryParts);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4>Document Requests</h4>
    <p>Review and process student document requests.</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <div class="col-sm-4 col-md-3">
      <label class="form-label small text-muted mb-1">Search</label>
      <input type="text" name="search" class="form-control form-control-sm"
             value="<?= e($filterSearch) ?>" placeholder="Name, code, student ID...">
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label small text-muted mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (array_keys(DOC_STATUSES) as $s): ?>
        <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-3">
      <label class="form-label small text-muted mb-1">Document Type</label>
      <select name="doc_type" class="form-select form-select-sm">
        <option value="">All Types</option>
        <?php foreach ($docTypes as $dt): ?>
        <option value="<?= (int)$dt['id'] ?>" <?= $filterDocType === (int)$dt['id'] ? 'selected' : '' ?>>
          <?= e($dt['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-search me-1"></i>Filter
      </button>
      <a href="<?= BASE_URL ?>/staff/document_requests.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
    <div class="col-auto ms-auto">
      <a href="<?= BASE_URL ?>/staff/document_requests.php?archived=1"
         class="btn btn-sm <?= $showArchived ? 'btn-dark' : 'btn-outline-secondary' ?>">
        <i class="bi bi-archive me-1"></i><?= $showArchived ? 'Viewing Archived' : 'Archived' ?>
      </a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="content-card">
  <div class="content-card-header">
    <h5>Document Requests <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
    <span class="text-muted small">
      Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$result['total'])) ?>
      of <?= number_format($result['total']) ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>#</th><th>Tracking Code</th><th>Name</th><th>Student ID</th>
        <th>Document</th><th>Copies</th><th>Status</th><th>Submitted</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="9">
          <div class="empty-state"><i class="bi bi-inbox"></i><p>No document requests found.</p></div>
        </td></tr>
      <?php else: foreach ($result['data'] as $i => $d): ?>
      <tr>
        <td class="text-muted small"><?= ($page-1)*$perPage + $i + 1 ?></td>
        <td><span class="font-monospace small fw-semibold"><?= e($d['tracking_code']) ?></span></td>
        <td>
          <div class="fw-semibold small"><?= e(fullName($d, true)) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= e($d['email']) ?></div>
        </td>
        <td><span class="small"><?= e($d['student_id'] ?: '—') ?></span></td>
        <td>
          <span class="badge bg-light text-dark small"><?= e($d['doc_code'] ?? '—') ?></span>
          <?php if ($d['document_type_other']): ?>
            <div class="text-muted" style="font-size:.7rem;"><?= e(substr($d['document_type_other'],0,25)) ?></div>
          <?php endif; ?>
        </td>
        <td class="text-center small"><?= (int)$d['copies'] ?></td>
        <td><?= statusBadge($d['status'],'document_request') ?></td>
        <td><span class="text-muted small"><?= formatDate($d['submitted_at']) ?></span></td>
        <td>
          <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$d['id'] ?>"
             class="btn btn-sm btn-outline-success py-0 px-2">
            <i class="bi bi-eye"></i> View
          </a>
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

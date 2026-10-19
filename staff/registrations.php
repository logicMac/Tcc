<?php
/**
 * TCC RRS – Staff: Pre-Registrations List
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle   = 'Pre-Registrations';
$breadcrumbs = [['label' => 'Pre-Registrations']];

$filterStatus  = get('status');
$filterProgram = getInt('program_id');
$filterType    = get('enrollment_type');
$filterSearch  = get('search');
$page          = max(1, getInt('page', 1));
$perPage       = 20;

$where  = ['1=1'];
$params = [];
if ($filterStatus)  { $where[] = 'pr.status = ?';          $params[] = $filterStatus; }
if ($filterProgram) { $where[] = 'pr.program_id = ?';       $params[] = $filterProgram; }
if ($filterType)    { $where[] = 'pr.enrollment_type = ?';  $params[] = $filterType; }
if ($filterSearch)  {
    $where[]  = "(pr.tracking_code LIKE ? OR pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.email LIKE ?)";
    $q        = '%' . $filterSearch . '%';
    $params   = array_merge($params, [$q, $q, $q, $q]);
}

$whereStr = implode(' AND ', $where);
$sql      = "SELECT pr.*, p.code AS prog_code, p.name AS prog_name
             FROM pre_registrations pr
             LEFT JOIN programs p ON p.id = pr.program_id
             WHERE {$whereStr}
             ORDER BY pr.submitted_at DESC";

$result   = db()->paginate($sql, $params, $page, $perPage);
$programs = getActivePrograms();

$qParts  = array_filter([
    $filterStatus  ? 'status='          . urlencode($filterStatus)  : '',
    $filterProgram ? 'program_id='      . $filterProgram            : '',
    $filterType    ? 'enrollment_type=' . urlencode($filterType)    : '',
    $filterSearch  ? 'search='          . urlencode($filterSearch)  : '',
]);
$baseUrl = BASE_URL . '/staff/registrations.php?' . implode('&', $qParts);

// Per-status counts for quick tiles
$statusCounts = [];
foreach (array_keys(REG_STATUSES) as $s) {
    $statusCounts[$s] = (int) db()->fetchValue(
        "SELECT COUNT(*) FROM pre_registrations WHERE status = ?", [$s]
    );
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><i class="bi bi-person-plus me-2 text-primary"></i>Pre-Registrations</h4>
    <p>Review and process student enrollment pre-registrations.</p>
  </div>
</div>

<!-- Status Quick Tiles -->
<div class="row g-2 mb-3">
  <div class="col-auto">
    <a href="<?= BASE_URL ?>/staff/registrations.php"
       class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : 'btn-outline-secondary' ?>">
      All <span class="badge bg-white text-dark ms-1"><?= number_format(array_sum($statusCounts)) ?></span>
    </a>
  </div>
  <?php foreach (REG_STATUSES as $s => $info): ?>
  <div class="col-auto">
    <a href="<?= BASE_URL ?>/staff/registrations.php?status=<?= urlencode($s) ?><?= $filterSearch ? '&search='.urlencode($filterSearch) : '' ?>"
       class="btn btn-sm <?= $filterStatus === $s ? 'btn-'.$info['color'].' text-white' : 'btn-outline-secondary' ?>">
      <?= e($s) ?>
      <?php if ($statusCounts[$s] > 0): ?>
        <span class="badge <?= $filterStatus === $s ? 'bg-white text-dark' : 'bg-secondary' ?> ms-1">
          <?= $statusCounts[$s] ?>
        </span>
      <?php endif; ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <?php if ($filterStatus): ?>
      <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
    <?php endif; ?>
    <div class="col-sm-4 col-md-3">
      <label class="form-label">Search</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
        <input type="text" name="search" class="form-control"
               value="<?= e($filterSearch) ?>" placeholder="Name, code, email…">
      </div>
    </div>
    <?php if (!$filterStatus): ?>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (array_keys(REG_STATUSES) as $s): ?>
        <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Program</label>
      <select name="program_id" class="form-select form-select-sm">
        <option value="">All Programs</option>
        <?php foreach ($programs as $prog): ?>
        <option value="<?= (int)$prog['id'] ?>"
                <?= $filterProgram === (int)$prog['id'] ? 'selected' : '' ?>
                title="<?= e($prog['name']) ?>">
          <?= e($prog['code']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Enrollment Type</label>
      <select name="enrollment_type" class="form-select form-select-sm">
        <option value="">All Types</option>
        <?php foreach (['New Student','Transferee','Returnee','Cross-Enrollee'] as $et): ?>
        <option value="<?= e($et) ?>" <?= $filterType === $et ? 'selected' : '' ?>><?= e($et) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-search me-1"></i>Filter
      </button>
      <a href="<?= BASE_URL ?>/staff/registrations.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-lg"></i>
      </a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="content-card">
  <div class="content-card-header">
    <h5>
      <?php if ($filterStatus): ?>
        <?= statusBadge($filterStatus) ?>
      <?php else: ?>
        <i class="bi bi-list-ul me-1 text-muted"></i> All Pre-Registrations
      <?php endif; ?>
      <span class="badge bg-light text-dark ms-1 fw-normal"><?= number_format($result['total']) ?></span>
    </h5>
    <span class="text-muted small">
      <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage, $result['total'])) ?>
      of <?= number_format($result['total']) ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Tracking Code</th>
          <th>Student Name</th>
          <th>Program</th>
          <th>Type</th>
          <th>Year</th>
          <th>Status</th>
          <th>Submitted</th>
          <th style="width:80px">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($result['data'])): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h6>No pre-registrations found</h6>
            <p><?= ($filterStatus || $filterSearch) ? 'Try adjusting your filters.' : 'No student registrations have been submitted yet.' ?></p>
          </div>
        </td></tr>
        <?php else: foreach ($result['data'] as $i => $r): ?>
        <tr>
          <td class="text-muted small"><?= ($page-1)*$perPage + $i + 1 ?></td>
          <td>
            <span class="font-monospace fw-semibold" style="font-size:.78rem;">
              <?= e($r['tracking_code']) ?>
            </span>
          </td>
          <td>
            <div class="fw-semibold small"><?= e(fullName($r, true)) ?></div>
            <div class="text-muted" style="font-size:.7rem;"><?= e($r['email']) ?></div>
          </td>
          <td>
            <span class="badge bg-light text-dark"
                  title="<?= e($r['prog_name'] ?? $r['program_other'] ?? '') ?>"
                  data-bs-toggle="tooltip">
              <?= e($r['prog_code'] ?? ($r['program_other'] ? 'Other' : '—')) ?>
            </span>
          </td>
          <td><span class="small"><?= e($r['enrollment_type']) ?></span></td>
          <td><span class="small text-muted"><?= e($r['year_level']) ?></span></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td>
            <span class="small text-muted"><?= formatDate($r['submitted_at']) ?></span>
            <div class="text-muted" style="font-size:.65rem;"><?= timeAgo($r['submitted_at']) ?></div>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$r['id'] ?>"
               class="btn btn-sm btn-outline-primary"
               data-bs-toggle="tooltip" title="View & Process">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($result['pages'] > 1): ?>
  <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
    <span class="text-muted small">
      Page <?= $page ?> of <?= $result['pages'] ?>
    </span>
    <?= paginationLinks($page, $result['pages'], $baseUrl) ?>
  </div>
  <?php endif; ?>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

<?php
/**
 * TCC RRS – Registrar: Activity Logs
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'Activity Logs';

$filterUser   = getInt('user_id');
$filterModule = get('module');
$filterDate   = get('date');
$filterSearch = get('search');
$page         = max(1, getInt('page', 1));
$perPage      = 30;

$where  = ['1=1'];
$params = [];

if ($filterUser) {
    $where[]  = 'al.user_id = ?';
    $params[] = $filterUser;
}
if ($filterModule) {
    $where[]  = 'al.module = ?';
    $params[] = $filterModule;
}
if ($filterDate) {
    $where[]  = 'DATE(al.created_at) = ?';
    $params[] = $filterDate;
}
if ($filterSearch) {
    $where[]  = "(al.action LIKE ? OR al.description LIKE ? OR al.user_name LIKE ? OR al.reference LIKE ?)";
    $q        = '%'.$filterSearch.'%';
    $params   = array_merge($params, [$q,$q,$q,$q]);
}

$whereStr = implode(' AND ', $where);
$sql = "SELECT al.*, u.full_name AS actor
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE {$whereStr}
        ORDER BY al.created_at DESC";

$result  = db()->paginate($sql, $params, $page, $perPage);
$users   = db()->fetchAll("SELECT id, full_name FROM users ORDER BY full_name");
$modules = db()->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module");

$qParts = array_filter([
    $filterUser   ? 'user_id='.  $filterUser            : '',
    $filterModule ? 'module='.   urlencode($filterModule): '',
    $filterDate   ? 'date='.     urlencode($filterDate)  : '',
    $filterSearch ? 'search='.   urlencode($filterSearch): '',
]);
$baseUrl = BASE_URL . '/registrar/activity_logs.php?' . implode('&', $qParts);

$actionColors = [
    'LOGIN'           => 'success',
    'LOGOUT'          => 'secondary',
    'LOGIN_FAILED'    => 'danger',
    'STATUS_UPDATE'   => 'primary',
    'CREATE_STAFF'    => 'info',
    'EDIT_STAFF'      => 'info',
    'ACTIVATE_STAFF'  => 'success',
    'DEACTIVATE_STAFF'=> 'warning',
    'QR_VERIFY'       => 'dark',
    'SETTINGS_UPDATE' => 'purple',
];

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<div class="page-header">
  <div class="page-header-info">
    <h4>Activity Logs</h4>
    <p>Monitor all system activity performed by staff and registrar accounts.</p>
  </div>
</div>

<!-- Filter -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-4 col-md-3">
      <label class="form-label small text-muted mb-1">Search</label>
      <input type="text" name="search" class="form-control form-control-sm"
             value="<?= e($filterSearch) ?>" placeholder="Action, description...">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label small text-muted mb-1">User</label>
      <select name="user_id" class="form-select form-select-sm">
        <option value="">All Users</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : '' ?>>
          <?= e($u['full_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label small text-muted mb-1">Module</label>
      <select name="module" class="form-select form-select-sm">
        <option value="">All Modules</option>
        <?php foreach ($modules as $m): ?>
        <option value="<?= e($m['module']) ?>" <?= $filterModule === $m['module'] ? 'selected' : '' ?>>
          <?= e($m['module']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label small text-muted mb-1">Date</label>
      <input type="date" name="date" class="form-control form-control-sm"
             value="<?= e($filterDate) ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="<?= BASE_URL ?>/registrar/activity_logs.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="content-card">
  <div class="content-card-header">
    <h5>Activity Logs <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
    <span class="text-muted small">
      <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$result['total'])) ?>
      of <?= number_format($result['total']) ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>Date & Time</th><th>User</th><th>Role</th>
        <th>Action</th><th>Module</th><th>Description</th><th>Reference</th><th>IP</th>
      </tr></thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-journal-x"></i><p>No logs found.</p></div></td></tr>
      <?php else: foreach ($result['data'] as $log): ?>
      <tr>
        <td><span class="text-muted small text-nowrap"><?= formatDateTime($log['created_at']) ?></span></td>
        <td>
          <div class="fw-semibold small"><?= e($log['user_name'] ?? 'System') ?></div>
        </td>
        <td>
          <span class="badge <?= $log['user_role'] === 'registrar' ? 'bg-primary' : 'bg-info text-dark' ?>">
            <?= e(ucfirst($log['user_role'] ?? 'system')) ?>
          </span>
        </td>
        <td>
          <?php $ac = $actionColors[$log['action']] ?? 'secondary'; ?>
          <span class="badge bg-<?= $ac ?>"><?= e($log['action']) ?></span>
        </td>
        <td><span class="badge bg-light text-dark small"><?= e($log['module']) ?></span></td>
        <td><span class="text-muted small"><?= e(substr($log['description'] ?? '',0,80)) ?></span></td>
        <td>
          <?php if ($log['reference']): ?>
          <span class="font-monospace" style="font-size:.7rem;"><?= e($log['reference']) ?></span>
          <?php endif; ?>
        </td>
        <td><span class="text-muted" style="font-size:.7rem;"><?= e($log['ip_address'] ?? '') ?></span></td>
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

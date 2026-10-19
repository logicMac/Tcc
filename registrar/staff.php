<?php
/**
 * TCC RRS – Registrar: Staff Account Management
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle = 'Staff Accounts';

// ── Handle quick actions ───────────────────────────────────────────────────
if (isPost() && post('action')) {
    abortCsrf();
    $targetId = postInt('user_id');
    $action   = post('action');

    if ($targetId && $targetId !== currentUserId()) {
        $target = db()->fetchOne("SELECT * FROM users WHERE id=? AND role='staff'", [$targetId]);
        if ($target) {
            if ($action === 'activate') {
                db()->execute("UPDATE users SET is_active=1 WHERE id=?", [$targetId]);
                flash('success', $target['full_name'] . ' has been activated.');
                logActivity('ACTIVATE_STAFF','Staff Management',"Activated staff: {$target['full_name']}");
            } elseif ($action === 'deactivate') {
                db()->execute("UPDATE users SET is_active=0 WHERE id=?", [$targetId]);
                flash('success', $target['full_name'] . ' has been deactivated.');
                logActivity('DEACTIVATE_STAFF','Staff Management',"Deactivated staff: {$target['full_name']}");
            }
        }
    }
    redirect(BASE_URL . '/registrar/staff.php');
}

$search   = get('search');
$filter   = get('filter');
$page     = max(1, getInt('page', 1));
$perPage  = 15;

$where  = ["role = 'staff'"];
$params = [];
if ($search) {
    $where[]  = "(full_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
    $q        = '%'.$search.'%';
    $params   = [$q,$q,$q];
}
if ($filter === 'active')   { $where[] = 'is_active = 1'; }
if ($filter === 'inactive') { $where[] = 'is_active = 0'; }

$sql    = "SELECT * FROM users WHERE " . implode(' AND ',$where) . " ORDER BY full_name ASC";
$result = db()->paginate($sql, $params, $page, $perPage);

$qBase  = array_filter([$search ? 'search='.urlencode($search) : '', $filter ? 'filter='.$filter : '']);
$baseUrl = BASE_URL . '/registrar/staff.php?' . implode('&', $qBase);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4>Staff Accounts</h4>
    <p>Create, activate, and manage staff members.</p>
  </div>
  <a href="<?= BASE_URL ?>/registrar/create_staff.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add New Staff
  </a>
</div>

<!-- Filter -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-5 col-md-4">
      <label class="form-label small text-muted mb-1">Search</label>
      <input type="text" name="search" class="form-control form-control-sm"
             value="<?= e($search) ?>" placeholder="Name, email, employee ID...">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label small text-muted mb-1">Status</label>
      <select name="filter" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="active"   <?= $filter === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
      <a href="<?= BASE_URL ?>/registrar/staff.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="content-card">
  <div class="content-card-header">
    <h5>Staff Members <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr>
        <th>Name</th><th>Employee ID</th><th>Email</th>
        <th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><p>No staff accounts found.</p></div></td></tr>
      <?php else: foreach ($result['data'] as $s): ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="avatar-circle <?= $s['is_active'] ? 'bg-primary' : 'bg-secondary' ?> text-white" style="font-size:.75rem;width:36px;height:36px;">
              <?= strtoupper(substr($s['full_name'],0,2)) ?>
            </div>
            <div>
              <div class="fw-semibold small"><?= e($s['full_name']) ?></div>
              <?php if ($s['phone']): ?>
              <div class="text-muted" style="font-size:.7rem;"><?= e($s['phone']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td><span class="font-monospace small"><?= e($s['employee_id']) ?></span></td>
        <td><span class="small"><?= e($s['email']) ?></span></td>
        <td>
          <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
            <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        </td>
        <td><span class="text-muted small"><?= $s['last_login'] ? formatDateTime($s['last_login']) : 'Never' ?></span></td>
        <td><span class="text-muted small"><?= formatDate($s['created_at']) ?></span></td>
        <td>
          <div class="d-flex gap-1">
            <a href="<?= BASE_URL ?>/registrar/edit_staff.php?id=<?= (int)$s['id'] ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2">
              <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
              <?php if ($s['is_active']): ?>
              <input type="hidden" name="action" value="deactivate">
              <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2"
                      data-confirm="Deactivate <?= e($s['full_name']) ?>?">
                <i class="bi bi-pause-circle"></i>
              </button>
              <?php else: ?>
              <input type="hidden" name="action" value="activate">
              <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2">
                <i class="bi bi-play-circle"></i>
              </button>
              <?php endif; ?>
            </form>
          </div>
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

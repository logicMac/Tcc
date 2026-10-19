<?php
/**
 * TCC RRS – Registrar: Manage Student Portal Accounts
 * User Role Management: view, activate, deactivate student accounts.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle   = 'Student Accounts';
$breadcrumbs = [['label' => 'Student Accounts']];

// ── Toggle account status ────────────────────────────────────────────────────
if (isPost()) {
    abortCsrf();
    $sid    = postInt('student_id');
    $action = post('action');

    $stu = db()->fetchOne("SELECT * FROM students WHERE id = ?", [$sid]);
    if ($stu && in_array($action, ['activate','deactivate'], true)) {
        $newState = $action === 'activate' ? 1 : 0;
        db()->execute("UPDATE students SET is_active = ? WHERE id = ?", [$newState, $sid]);
        logActivity('STUDENT_' . strtoupper($action), 'User Management',
            ucfirst($action) . "d student account {$stu['email']}", $stu['email']);
        flash('success', "Account for {$stu['email']} has been {$action}d.");
    }
    redirect(BASE_URL . '/registrar/students.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ── Filters + pagination ─────────────────────────────────────────────────────
$filterSearch  = get('search');
$filterState   = get('state');
$page          = max(1, getInt('page', 1));
$perPage       = 20;

$where  = ['1=1'];
$params = [];
if ($filterSearch) {
    $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.student_no LIKE ?)";
    $q       = '%' . $filterSearch . '%';
    $params  = array_merge($params, [$q,$q,$q,$q]);
}
if ($filterState === 'active')   { $where[] = 's.is_active = 1'; }
if ($filterState === 'inactive') { $where[] = 's.is_active = 0'; }

$sql = "SELECT s.*,
            (SELECT COUNT(*) FROM pre_registrations pr WHERE pr.student_account_id = s.id) AS reg_count,
            (SELECT COUNT(*) FROM document_requests dr WHERE dr.student_account_id = s.id) AS doc_count
        FROM students s
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.created_at DESC";

$result = db()->paginate($sql, $params, $page, $perPage);

$totalStudents   = (int) db()->fetchValue("SELECT COUNT(*) FROM students");
$activeStudents  = (int) db()->fetchValue("SELECT COUNT(*) FROM students WHERE is_active = 1");

$qParts  = array_filter([
    $filterSearch ? 'search='.urlencode($filterSearch) : '',
    $filterState  ? 'state='.urlencode($filterState) : '',
]);
$baseUrl = BASE_URL . '/registrar/students.php?' . implode('&', $qParts);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><i class="bi bi-mortarboard me-2 text-primary"></i>Student Accounts</h4>
    <p>Manage student portal accounts — <?= $activeStudents ?> of <?= $totalStudents ?> active.</p>
  </div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-5">
      <input type="text" name="search" class="form-control form-control-sm"
             value="<?= e($filterSearch) ?>" placeholder="Name, email, student number…">
    </div>
    <div class="col-sm-3">
      <select name="state" class="form-select form-select-sm">
        <option value="">All States</option>
        <option value="active" <?= $filterState==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $filterState==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="<?= BASE_URL ?>/registrar/students.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
    </div>
  </form>
</div>

<div class="content-card">
  <div class="content-card-header">
    <h5>Accounts <span class="badge bg-light text-dark ms-1"><?= number_format($result['total']) ?></span></h5>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Student</th><th>Student No.</th><th>Submissions</th>
          <th>Last Login</th><th>Registered</th><th>Status</th><th style="width:120px">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($result['data'])): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><p>No student accounts found.</p></div></td></tr>
      <?php else: foreach ($result['data'] as $s): ?>
      <tr>
        <td>
          <div class="fw-semibold small"><?= e(fullName($s, true)) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= e($s['email']) ?></div>
        </td>
        <td><span class="small font-monospace"><?= e($s['student_no'] ?: '—') ?></span></td>
        <td>
          <span class="badge bg-light text-dark"><?= (int)$s['reg_count'] ?> reg</span>
          <span class="badge bg-light text-dark"><?= (int)$s['doc_count'] ?> doc</span>
        </td>
        <td><span class="small text-muted"><?= $s['last_login'] ? timeAgo($s['last_login']) : 'Never' ?></span></td>
        <td><span class="small text-muted"><?= formatDate($s['created_at']) ?></span></td>
        <td>
          <?php if ($s['is_active']): ?>
            <span class="badge bg-success">Active</span>
          <?php else: ?>
            <span class="badge bg-danger">Deactivated</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" class="d-inline"
                onsubmit="return confirm('<?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?> this student account?')">
            <?= csrfField() ?>
            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="action" value="<?= $s['is_active'] ? 'deactivate' : 'activate' ?>">
            <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
              <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($result['pages'] > 1): ?>
  <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
    <span class="text-muted small">Page <?= $page ?> of <?= $result['pages'] ?></span>
    <?= paginationLinks($page, $result['pages'], $baseUrl) ?>
  </div>
  <?php endif; ?>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

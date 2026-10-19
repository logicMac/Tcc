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

<?= ui_page_header(
    'Student Accounts',
    "Manage student portal accounts — {$activeStudents} of {$totalStudents} active.",
    'mortarboard'
) ?>

<?php
echo ui_filter_bar(
    BASE_URL . '/registrar/students.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Name, email, student number…', 'col' => 'col-sm-5'],
        ['type' => 'select', 'name' => 'state', 'label' => 'Status', 'value' => $filterState,
         'options' => ['' => 'All States', 'active' => 'Active', 'inactive' => 'Inactive'], 'col' => 'col-sm-3'],
    ],
    [],
    BASE_URL . '/registrar/students.php'
);

$rows = [];
foreach ($result['data'] as $s) {
    $btnClass = $s['is_active'] ? 'btn-outline-danger' : 'btn-outline-success';
    $action   = $s['is_active'] ? 'deactivate' : 'activate';
    $rows[] = [
        '<div class="row-name">' . e(fullName($s, true)) . '</div><div class="row-sub">' . e($s['email']) . '</div>',
        '<span class="small font-monospace">' . e($s['student_no'] ?: '—') . '</span>',
        '<span class="badge bg-light text-dark">' . (int)$s['reg_count'] . ' reg</span> '
            . '<span class="badge bg-light text-dark">' . (int)$s['doc_count'] . ' doc</span>',
        '<span class="small text-muted">' . ($s['last_login'] ? timeAgo($s['last_login']) : 'Never') . '</span>',
        '<span class="small text-muted">' . formatDate($s['created_at']) . '</span>',
        '<span class="badge ' . ($s['is_active'] ? 'bg-success' : 'bg-danger') . '">'
            . ($s['is_active'] ? 'Active' : 'Deactivated') . '</span>',
        '<form method="POST" class="d-inline" onsubmit="return confirm(\''
            . ($s['is_active'] ? 'Deactivate' : 'Reactivate') . ' this student account?\')">'
            . csrfField()
            . '<input type="hidden" name="student_id" value="' . (int)$s['id'] . '">'
            . '<input type="hidden" name="action" value="' . $action . '">'
            . '<button type="submit" class="btn btn-sm ' . $btnClass . '">'
            . ($s['is_active'] ? 'Deactivate' : 'Activate') . '</button>'
        . '</form>',
    ];
}

echo ui_card_open(
    'Accounts <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    'mortarboard'
);
echo ui_table(
    ['Student', 'Student No.', 'Submissions', 'Last Login', 'Registered', 'Status',
     ['label' => 'Action', 'style' => 'width:120px']],
    $rows,
    ['empty_icon' => 'people', 'empty_msg' => 'No student accounts found.']
);
echo ui_list_footer($page, $result['pages'], $result['total'], $perPage, $baseUrl);
echo ui_card_close();
?>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

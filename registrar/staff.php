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

<?= ui_page_header(
    'Staff Accounts', 'Create, activate, and manage staff members.', 'people',
    '<a href="' . BASE_URL . '/registrar/create_staff.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add New Staff</a>'
) ?>

<?php
echo ui_filter_bar(
    BASE_URL . '/registrar/staff.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $search,
         'placeholder' => 'Name, email, employee ID…', 'col' => 'col-md-4'],
        ['type' => 'select', 'name' => 'filter', 'label' => 'Status', 'value' => $filter,
         'options' => ['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'], 'col' => 'col-md-2'],
    ],
    [],
    BASE_URL . '/registrar/staff.php',
    'Search'
);

$rows = [];
foreach ($result['data'] as $s) {
    $toggleForm = '<form method="POST" style="display:inline;">' . csrfField()
        . '<input type="hidden" name="user_id" value="' . (int)$s['id'] . '">';
    if ($s['is_active']) {
        $toggleForm .= '<input type="hidden" name="action" value="deactivate">'
            . '<button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" data-confirm="Deactivate ' . e($s['full_name']) . '?">'
            . '<i class="bi bi-pause-circle"></i></button>';
    } else {
        $toggleForm .= '<input type="hidden" name="action" value="activate">'
            . '<button type="submit" class="btn btn-sm btn-outline-success py-0 px-2">'
            . '<i class="bi bi-play-circle"></i></button>';
    }
    $toggleForm .= '</form>';

    $rows[] = [
        '<div class="d-flex align-items-center gap-2">'
            . ui_avatar($s['full_name'], 36, $s['is_active'] ? 'primary' : 'secondary')
            . '<div><div class="row-name">' . e($s['full_name']) . '</div>'
            . ($s['phone'] ? '<div class="row-sub">' . e($s['phone']) . '</div>' : '')
            . '</div></div>',
        '<span class="font-monospace small">' . e($s['employee_id']) . '</span>',
        '<span class="small">' . e($s['email']) . '</span>',
        '<span class="badge ' . ($s['is_active'] ? 'bg-success' : 'bg-secondary') . '">' . ($s['is_active'] ? 'Active' : 'Inactive') . '</span>',
        '<span class="text-muted small">' . ($s['last_login'] ? formatDateTime($s['last_login']) : 'Never') . '</span>',
        '<span class="text-muted small">' . formatDate($s['created_at']) . '</span>',
        '<div class="d-flex gap-1">'
            . '<a href="' . BASE_URL . '/registrar/edit_staff.php?id=' . (int)$s['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-pencil"></i></a>'
            . $toggleForm
        . '</div>',
    ];
}

echo ui_card_open(
    'Staff Members <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    'people'
);
echo ui_table(['Name', 'Employee ID', 'Email', 'Status', 'Last Login', 'Created', 'Actions'],
              $rows, ['empty_icon' => 'people', 'empty_msg' => 'No staff accounts found.',
              'pagination' => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]);
echo ui_card_close();
?>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

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

<?= ui_page_header('Activity Logs', 'Monitor all system activity performed by staff and registrar accounts.', 'journal-text') ?>

<?php
echo ui_filter_bar(
    BASE_URL . '/registrar/activity_logs.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Action, description…', 'col' => 'col-md-3'],
        ['type' => 'select', 'name' => 'user_id', 'label' => 'User', 'value' => $filterUser,
         'options' => ['' => 'All Users'] + array_column($users, 'full_name', 'id'), 'col' => 'col-md-2'],
        ['type' => 'select', 'name' => 'module', 'label' => 'Module', 'value' => $filterModule,
         'options' => ['' => 'All Modules'] + array_column($modules, 'module', 'module'), 'col' => 'col-md-2'],
        ['type' => 'date', 'name' => 'date', 'label' => 'Date', 'value' => $filterDate, 'col' => 'col-md-2', 'icon' => ''],
    ],
    [],
    BASE_URL . '/registrar/activity_logs.php'
);

$rows = [];
foreach ($result['data'] as $log) {
    $ac = $actionColors[$log['action']] ?? 'secondary';
    $rows[] = [
        '<span class="text-muted small text-nowrap">' . formatDateTime($log['created_at']) . '</span>',
        '<span class="fw-semibold small">' . e($log['user_name'] ?? 'System') . '</span>',
        '<span class="badge ' . ($log['user_role'] === 'registrar' ? 'bg-primary' : 'bg-info text-dark') . '">'
            . e(ucfirst($log['user_role'] ?? 'system')) . '</span>',
        '<span class="badge bg-' . $ac . '">' . e($log['action']) . '</span>',
        '<span class="badge bg-light text-dark small">' . e($log['module']) . '</span>',
        '<span class="text-muted small">' . e(substr($log['description'] ?? '', 0, 80)) . '</span>',
        $log['reference'] ? '<span class="font-monospace" style="font-size:.7rem;">' . e($log['reference']) . '</span>' : '',
        '<span class="text-muted" style="font-size:.7rem;">' . e($log['ip_address'] ?? '') . '</span>',
    ];
}

echo ui_card_open(
    'Activity Logs <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    'journal-text',
    '<span class="text-muted small">'
        . number_format($result['total'] > 0 ? ($page - 1) * $perPage + 1 : 0) . '–'
        . number_format(min($page * $perPage, $result['total']))
        . ' of ' . number_format($result['total']) . '</span>'
);
echo ui_table(['Date & Time', 'User', 'Role', 'Action', 'Module', 'Description', 'Reference', 'IP'],
              $rows, ['empty_icon' => 'journal-x', 'empty_msg' => 'No logs found.',
              'pagination' => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]);
echo ui_card_close();
?>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

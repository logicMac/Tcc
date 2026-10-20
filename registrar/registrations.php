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
$showArchived  = get('archived') === '1';
$page          = max(1, getInt('page', 1));
$perPage       = 25;

$where  = ['pr.is_archived = ?'];
$params = [$showArchived ? 1 : 0];
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
$qBase  = array_filter([$filterStatus?'status='.urlencode($filterStatus):'', $filterSearch?'search='.urlencode($filterSearch):'', $showArchived?'archived=1':'']);
$baseUrl = BASE_URL.'/registrar/registrations.php?'.implode('&',$qBase);

$statusCounts = [];
foreach (array_keys(REG_STATUSES) as $s) {
    $statusCounts[$s] = (int) db()->fetchValue(
        "SELECT COUNT(*) FROM pre_registrations WHERE status = ? AND is_archived = 0", [$s]
    );
}
$archivedCount = (int) db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 1");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Monitor Pre-Registrations', 'Overview of all student enrollment submissions.', 'person-plus',
    '<a href="' . BASE_URL . '/registrar/reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i>View Reports</a>'
) ?>

<?php
$tabs = [[
    'label' => 'All', 'url' => BASE_URL . '/registrar/registrations.php',
    'count' => array_sum($statusCounts), 'active' => !$filterStatus && !$showArchived, 'variant' => 'primary',
]];
foreach (REG_STATUSES as $s => $info) {
    $tabs[] = [
        'label'   => $s,
        'url'     => BASE_URL . '/registrar/registrations.php?status=' . urlencode($s) . ($filterSearch ? '&search=' . urlencode($filterSearch) : ''),
        'count'   => $statusCounts[$s] ?: null,
        'active'  => $filterStatus === $s && !$showArchived,
        'variant' => $info['color'],
    ];
}
$tabs[] = ['spacer' => true];
$tabs[] = [
    'label' => 'Archived', 'url' => BASE_URL . '/registrar/registrations.php?archived=1',
    'count' => $archivedCount ?: null, 'active' => $showArchived,
    'variant' => 'dark', 'icon' => 'archive',
];
echo ui_status_tabs($tabs);

echo ui_filter_bar(
    BASE_URL . '/registrar/registrations.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Name, code, email…', 'col' => 'col-sm-5'],
        ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $filterStatus,
         'options' => ['' => 'All Statuses'] + array_combine(array_keys(REG_STATUSES), array_keys(REG_STATUSES)), 'col' => 'col-sm-3'],
    ],
    ['archived' => $showArchived ? '1' : null],
    BASE_URL . '/registrar/registrations.php'
);

$rows = [];
foreach ($result['data'] as $r) {
    $rows[] = [
        ui_code($r['tracking_code']),
        '<div class="row-name">' . e(fullName($r, true)) . '</div><div class="row-sub">' . e($r['email']) . '</div>',
        '<span class="badge bg-light text-dark small">' . e($r['prog_code'] ?? '—') . '</span>',
        '<span class="small">' . e($r['enrollment_type']) . '</span>',
        statusBadge($r['status']),
        '<span class="text-muted small">' . e($r['assigned_name'] ?? 'Unassigned') . '</span>',
        '<span class="text-muted small">' . formatDate($r['submitted_at']) . '</span>',
        '<a href="' . BASE_URL . '/registrar/view_registration.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
    ];
}

$cardTitle = $filterStatus ? statusBadge($filterStatus) : 'Pre-Registrations';
echo ui_card_open(
    $cardTitle . ' <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    $filterStatus ? '' : 'list-ul'
);
echo ui_table(['Tracking Code', 'Name', 'Program', 'Type', 'Status', 'Assigned To', 'Submitted', ['label' => '', 'style' => 'width:70px']],
              $rows, ['empty_msg' => 'No records.', 'empty_icon' => 'inbox',
              'pagination' => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]);
echo ui_card_close();
?>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

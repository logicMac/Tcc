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
$showArchived  = get('archived') === '1';
$page          = max(1, getInt('page', 1));
$perPage       = 20;

$where  = ['pr.is_archived = ?'];
$params = [$showArchived ? 1 : 0];
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
    $showArchived  ? 'archived=1'                                     : '',
]);
$baseUrl = BASE_URL . '/' . currentRole() . '/registrations.php?' . implode('&', $qParts);

// Per-status counts for quick tiles
$statusCounts = [];
foreach (array_keys(REG_STATUSES) as $s) {
    $statusCounts[$s] = (int) db()->fetchValue(
        "SELECT COUNT(*) FROM pre_registrations WHERE status = ? AND is_archived = 0", [$s]
    );
}
$archivedCount = (int) db()->fetchValue(
    "SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 1"
);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header('Pre-Registrations', 'Review and process student enrollment pre-registrations.', 'person-plus') ?>

<?php
// ── Status quick tabs ──
$tabs = [[
    'label' => 'All', 'url' => BASE_URL . '/' . currentRole() . '/registrations.php',
    'count' => array_sum($statusCounts), 'active' => !$filterStatus && !$showArchived, 'variant' => 'primary',
]];
foreach (REG_STATUSES as $s => $info) {
    $tabs[] = [
        'label'   => $s,
        'url'     => BASE_URL . '/' . currentRole() . '/registrations.php?status=' . urlencode($s) . ($filterSearch ? '&search=' . urlencode($filterSearch) : ''),
        'count'   => $statusCounts[$s] ?: null,
        'active'  => $filterStatus === $s && !$showArchived,
        'variant' => $info['color'],
    ];
}
$tabs[] = ['spacer' => true];
$tabs[] = [
    'label' => 'Archived', 'url' => BASE_URL . '/' . currentRole() . '/registrations.php?archived=1',
    'count' => $archivedCount ?: null, 'active' => $showArchived,
    'variant' => 'dark', 'icon' => 'archive',
];
echo ui_status_tabs($tabs);

// ── Filters ──
$fields = [
    ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
     'placeholder' => 'Name, code, email…', 'col' => 'col-md-3'],
];
if (!$filterStatus) {
    $fields[] = ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $filterStatus,
                 'options' => ['' => 'All Statuses'] + array_combine(array_keys(REG_STATUSES), array_keys(REG_STATUSES))];
}
$fields[] = ['type' => 'select', 'name' => 'program_id', 'label' => 'Program', 'value' => $filterProgram,
             'options' => ['' => 'All Programs'] + array_column($programs, 'code', 'id')];
$fields[] = ['type' => 'select', 'name' => 'enrollment_type', 'label' => 'Enrollment Type', 'value' => $filterType,
             'options' => ['' => 'All Types'] + array_combine(ENROLLMENT_TYPES, ENROLLMENT_TYPES)];
echo ui_filter_bar(
    BASE_URL . '/' . currentRole() . '/registrations.php', $fields,
    ['status' => $filterStatus ?: null, 'archived' => $showArchived ? '1' : null],
    BASE_URL . '/' . currentRole() . '/registrations.php'
);

// ── Table rows ──
$rows = [];
foreach ($result['data'] as $i => $r) {
    $rows[] = [
        '<span class="text-muted small">' . (($page - 1) * $perPage + $i + 1) . '</span>',
        ui_code($r['tracking_code']),
        '<div class="row-name">' . e(fullName($r, true)) . '</div><div class="row-sub">' . e($r['email']) . '</div>',
        '<span class="badge bg-light text-dark" title="' . e($r['prog_name'] ?? $r['program_other'] ?? '') . '" data-bs-toggle="tooltip">'
            . e($r['prog_code'] ?? ($r['program_other'] ? 'Other' : '—')) . '</span>',
        '<span class="small">' . e($r['enrollment_type']) . '</span>',
        '<span class="small text-muted">' . e($r['year_level']) . '</span>',
        statusBadge($r['status']),
        '<span class="small text-muted">' . formatDate($r['submitted_at']) . '</span><div class="row-sub">' . timeAgo($r['submitted_at']) . '</div>',
        '<a href="' . BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View &amp; Process"><i class="bi bi-eye"></i></a>',
    ];
}

$cardTitle = $filterStatus ? statusBadge($filterStatus) : 'All Pre-Registrations';
echo ui_card_open(
    $cardTitle . ' <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    $filterStatus ? '' : 'list-ul'
);
echo ui_table(
    [
        ['label' => '#', 'style' => 'width:40px'], 'Tracking Code', 'Student Name', 'Program',
        'Type', 'Year', 'Status', 'Submitted', ['label' => 'Action', 'style' => 'width:80px'],
    ],
    $rows,
    ['empty_title' => 'No pre-registrations found',
     'empty_msg'   => ($filterStatus || $filterSearch) ? 'Try adjusting your filters.' : 'No student registrations have been submitted yet.',
     'pagination'  => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]
);
echo ui_card_close();
?>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

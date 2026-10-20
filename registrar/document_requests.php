<?php
/**
 * TCC RRS – Registrar: Monitor Document Requests
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle    = 'Monitor Document Requests';
$filterStatus = get('status');
$filterSearch = get('search');
$showArchived = get('archived') === '1';
$page         = max(1, getInt('page', 1));
$perPage      = 25;

$where  = ['dr.is_archived = ?'];
$params = [$showArchived ? 1 : 0];
if ($filterStatus) { $where[] = 'dr.status = ?'; $params[] = $filterStatus; }
if ($filterSearch) {
    $where[] = "(dr.tracking_code LIKE ? OR dr.first_name LIKE ? OR dr.last_name LIKE ? OR dr.email LIKE ? OR dr.student_id LIKE ?)";
    $q = '%'.$filterSearch.'%';
    $params = array_merge($params,[$q,$q,$q,$q,$q]);
}
$sql = "SELECT dr.*, dt.code AS doc_code, u.full_name AS assigned_name
        FROM document_requests dr
        LEFT JOIN document_types dt ON dt.id = dr.document_type_id
        LEFT JOIN users u ON u.id = dr.assigned_to
        WHERE ".implode(' AND ',$where)."
        ORDER BY dr.submitted_at DESC";

$result  = db()->paginate($sql, $params, $page, $perPage);
$qBase   = array_filter([$filterStatus?'status='.urlencode($filterStatus):'', $filterSearch?'search='.urlencode($filterSearch):'', $showArchived?'archived=1':'']);
$baseUrl = BASE_URL.'/registrar/document_requests.php?'.implode('&',$qBase);

$statusCounts = [];
foreach (array_keys(DOC_STATUSES) as $s) {
    $statusCounts[$s] = (int) db()->fetchValue(
        "SELECT COUNT(*) FROM document_requests WHERE status = ? AND is_archived = 0", [$s]
    );
}
$archivedCount = (int) db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived = 1");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header('Monitor Document Requests', 'Overview of all student document request submissions.', 'file-earmark-text') ?>

<?php
$tabs = [[
    'label' => 'All', 'url' => BASE_URL . '/registrar/document_requests.php',
    'count' => array_sum($statusCounts), 'active' => !$filterStatus && !$showArchived, 'variant' => 'primary',
]];
foreach (DOC_STATUSES as $s => $info) {
    $tabs[] = [
        'label'   => $s,
        'url'     => BASE_URL . '/registrar/document_requests.php?status=' . urlencode($s) . ($filterSearch ? '&search=' . urlencode($filterSearch) : ''),
        'count'   => $statusCounts[$s] ?: null,
        'active'  => $filterStatus === $s && !$showArchived,
        'variant' => $info['color'],
    ];
}
$tabs[] = ['spacer' => true];
$tabs[] = [
    'label' => 'Archived', 'url' => BASE_URL . '/registrar/document_requests.php?archived=1',
    'count' => $archivedCount ?: null, 'active' => $showArchived,
    'variant' => 'dark', 'icon' => 'archive',
];
echo ui_status_tabs($tabs);

echo ui_filter_bar(
    BASE_URL . '/registrar/document_requests.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Name, code, student ID…', 'col' => 'col-sm-5'],
        ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $filterStatus,
         'options' => ['' => 'All Statuses'] + array_combine(array_keys(DOC_STATUSES), array_keys(DOC_STATUSES)), 'col' => 'col-sm-3'],
    ],
    ['archived' => $showArchived ? '1' : null],
    BASE_URL . '/registrar/document_requests.php'
);

$rows = [];
foreach ($result['data'] as $d) {
    $rows[] = [
        ui_code($d['tracking_code']),
        '<div class="row-name">' . e(fullName($d, true)) . '</div><div class="row-sub">' . e($d['email']) . '</div>',
        '<span class="small">' . e($d['student_id'] ?: '—') . '</span>',
        '<span class="badge bg-light text-dark small">' . e($d['doc_code'] ?? '—') . '</span>',
        statusBadge($d['status'], 'document_request'),
        '<span class="text-muted small">' . e($d['assigned_name'] ?? 'Unassigned') . '</span>',
        '<span class="text-muted small">' . formatDate($d['submitted_at']) . '</span>',
        '<a href="' . BASE_URL . '/registrar/view_document.php?id=' . (int)$d['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
    ];
}

$cardTitle = $filterStatus ? statusBadge($filterStatus, 'document_request') : 'Document Requests';
echo ui_card_open(
    $cardTitle . ' <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    $filterStatus ? '' : 'file-earmark-text'
);
echo ui_table(['Tracking Code', 'Name', 'Student ID', 'Document', 'Status', 'Assigned To', 'Submitted', ['label' => '', 'style' => 'width:70px']],
              $rows, ['empty_msg' => 'No records.', 'empty_icon' => 'inbox',
              'pagination' => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]);
echo ui_card_close();
?>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

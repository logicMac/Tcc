<?php
/**
 * TCC RRS – Staff: Document Requests List
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'Document Requests';

$filterStatus  = get('status');
$filterDocType = getInt('doc_type');
$filterSearch  = get('search');
$showArchived  = get('archived') === '1';
$page          = max(1, getInt('page', 1));
$perPage       = 20;

$where  = ['dr.is_archived = ?'];
$params = [$showArchived ? 1 : 0];

if ($filterStatus) {
    $where[]  = 'dr.status = ?';
    $params[] = $filterStatus;
}
if ($filterDocType) {
    $where[]  = 'dr.document_type_id = ?';
    $params[] = $filterDocType;
}
if ($filterSearch) {
    $where[]  = "(dr.tracking_code LIKE ? OR dr.first_name LIKE ? OR dr.last_name LIKE ? OR dr.email LIKE ? OR dr.student_id LIKE ?)";
    $q        = '%' . $filterSearch . '%';
    $params   = array_merge($params, [$q, $q, $q, $q, $q]);
}

$whereStr = implode(' AND ', $where);
$sql = "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code
        FROM document_requests dr
        LEFT JOIN document_types dt ON dt.id = dr.document_type_id
        WHERE {$whereStr}
        ORDER BY dr.submitted_at DESC";

$result   = db()->paginate($sql, $params, $page, $perPage);
$docTypes = getActiveDocumentTypes();

$queryParts = array_filter([
    $filterStatus  ? 'status='   . urlencode($filterStatus) : '',
    $filterDocType ? 'doc_type=' . $filterDocType           : '',
    $filterSearch  ? 'search='   . urlencode($filterSearch) : '',
    $showArchived  ? 'archived=1'                           : '',
]);
$baseUrl = BASE_URL . '/' . currentRole() . '/document_requests.php?' . implode('&', $queryParts);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header('Document Requests', 'Review and process student document requests.', 'file-earmark-text') ?>

<?php
// ── Status quick tabs ──
$tabs = [[
    'label' => 'All', 'url' => BASE_URL . '/' . currentRole() . '/document_requests.php',
    'active' => !$filterStatus && !$showArchived, 'variant' => 'primary',
]];
foreach (DOC_STATUSES as $s => $info) {
    $tabs[] = [
        'label'   => $s,
        'url'     => BASE_URL . '/' . currentRole() . '/document_requests.php?status=' . urlencode($s) . ($filterSearch ? '&search=' . urlencode($filterSearch) : ''),
        'active'  => $filterStatus === $s && !$showArchived,
        'variant' => $info['color'],
    ];
}
$tabs[] = ['spacer' => true];
$tabs[] = [
    'label' => 'Archived', 'url' => BASE_URL . '/' . currentRole() . '/document_requests.php?archived=1',
    'active' => $showArchived, 'variant' => 'dark', 'icon' => 'archive',
];
echo ui_status_tabs($tabs);

// ── Filters ──
echo ui_filter_bar(
    BASE_URL . '/' . currentRole() . '/document_requests.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Name, code, student ID…', 'col' => 'col-md-3'],
        ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $filterStatus,
         'options' => ['' => 'All Statuses'] + array_combine(array_keys(DOC_STATUSES), array_keys(DOC_STATUSES))],
        ['type' => 'select', 'name' => 'doc_type', 'label' => 'Document Type', 'value' => $filterDocType,
         'options' => ['' => 'All Types'] + array_column($docTypes, 'name', 'id'), 'col' => 'col-md-3'],
    ],
    ['archived' => $showArchived ? '1' : null],
    BASE_URL . '/' . currentRole() . '/document_requests.php'
);

// ── Table rows ──
$rows = [];
foreach ($result['data'] as $i => $d) {
    $rows[] = [
        '<span class="text-muted small">' . (($page - 1) * $perPage + $i + 1) . '</span>',
        ui_code($d['tracking_code']),
        '<div class="row-name">' . e(fullName($d, true)) . '</div><div class="row-sub">' . e($d['email']) . '</div>',
        '<span class="small">' . e($d['student_id'] ?: '—') . '</span>',
        '<span class="badge bg-light text-dark">' . e($d['doc_code'] ?? '—') . '</span>'
            . ($d['document_type_other'] ? '<div class="row-sub">' . e(mb_strimwidth($d['document_type_other'], 0, 25, '…')) . '</div>' : ''),
        ['html' => '<span class="small">' . (int)$d['copies'] . '</span>', 'class' => 'text-center'],
        statusBadge($d['status'], 'document_request'),
        '<span class="small text-muted">' . formatDate($d['submitted_at']) . '</span><div class="row-sub">' . timeAgo($d['submitted_at']) . '</div>',
        '<a href="' . BASE_URL . '/' . currentRole() . '/view_document.php?id=' . (int)$d['id'] . '" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View &amp; Process"><i class="bi bi-eye"></i></a>',
    ];
}

echo ui_card_open(
    'Document Requests <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format($result['total']) . '</span>',
    'list-ul'
);
echo ui_table(
    [
        ['label' => '#', 'style' => 'width:40px'], 'Tracking Code', 'Name', 'Student ID',
        'Document', ['label' => 'Copies', 'class' => 'text-center'], 'Status', 'Submitted',
        ['label' => 'Action', 'style' => 'width:80px'],
    ],
    $rows,
    ['empty_title' => 'No document requests found',
     'empty_msg'   => ($filterStatus || $filterSearch) ? 'Try adjusting your filters.' : 'No student document requests have been submitted yet.',
     'pagination'  => ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total'], 'per_page' => $perPage, 'base_url' => $baseUrl]]
);
echo ui_card_close();
?>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

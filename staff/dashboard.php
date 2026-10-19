<?php
/**
 * TCC RRS – Staff Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$pageTitle = 'Staff Dashboard';

// ── Stats ──────────────────────────────────────────────────────────────────
$stats = [
    'reg_pending'    => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Pending' AND is_archived = 0"),
    'reg_review'     => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Under Review' AND is_archived = 0"),
    'reg_processing' => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Processing' AND is_archived = 0"),
    'reg_approved'   => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE status = 'Approved' AND is_archived = 0"),
    'reg_total'      => db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE is_archived = 0"),
    'doc_pending'    => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Pending' AND is_archived = 0"),
    'doc_processing' => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Processing' AND is_archived = 0"),
    'doc_ready'      => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Ready for Release' AND is_archived = 0"),
    'doc_released'   => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE status = 'Released' AND is_archived = 0"),
    'doc_total'      => db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE is_archived = 0"),
];

// ── Recent submissions ─────────────────────────────────────────────────────
$recentRegs = db()->fetchAll(
    "SELECT pr.*, p.code AS prog_code
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.is_archived = 0
     ORDER BY pr.submitted_at DESC LIMIT 5"
);
$recentDocs = db()->fetchAll(
    "SELECT dr.*, dt.code AS doc_code
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.is_archived = 0
     ORDER BY dr.submitted_at DESC LIMIT 5"
);

// ── Today counts ──────────────────────────────────────────────────────────
$todayReg = db()->fetchValue("SELECT COUNT(*) FROM pre_registrations WHERE DATE(submitted_at) = CURDATE()");
$todayDoc = db()->fetchValue("SELECT COUNT(*) FROM document_requests WHERE DATE(submitted_at) = CURDATE()");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= renderFlash() ?>

<?= ui_page_header(
    'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) . ', '
        . e(explode(' ', currentUser()['full_name'])[0]) . '!',
    'Here\'s what\'s happening today – ' . date('F d, Y'),
    '',
    '<a href="' . BASE_URL . '/staff/registrations.php?status=Pending" class="btn btn-primary btn-sm"><i class="bi bi-inbox me-1"></i> View Pending</a>'
) ?>

<!-- ── Today Summary ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('clock', 'Pending Enrollments', $stats['reg_pending'], 'gold',
                     $todayReg . ' submitted today', BASE_URL . '/staff/registrations.php?status=Pending') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('file-earmark-clock', 'Pending Documents', $stats['doc_pending'], 'blue',
                     $todayDoc . ' submitted today', BASE_URL . '/staff/document_requests.php?status=Pending') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('check-circle', 'Approved Enrollments', $stats['reg_approved'], 'green',
                     'of ' . (int)$stats['reg_total'] . ' total', BASE_URL . '/staff/registrations.php?status=Approved') ?>
  </div>
  <div class="col-sm-6 col-md-3">
    <?= ui_stat_card('bag-check', 'Ready for Release', $stats['doc_ready'], 'teal',
                     (int)$stats['doc_released'] . ' released', BASE_URL . '/staff/document_requests.php?status=' . urlencode('Ready for Release')) ?>
  </div>
</div>

<!-- ── Status breakdown ── -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <?= ui_card_open('Enrollment Pre-Registrations', 'person-plus',
        '<a href="' . BASE_URL . '/staff/registrations.php" class="btn btn-sm btn-outline-primary">View All</a>') ?>
    <div class="content-card-body">
      <?= ui_progress_rows([
          'Pending'      => ['val' => (int)$stats['reg_pending'],    'color' => '#d97706'],
          'Under Review' => ['val' => (int)$stats['reg_review'],     'color' => '#1768a8'],
          'Processing'   => ['val' => (int)$stats['reg_processing'], 'color' => '#115540'],
          'Approved'     => ['val' => (int)$stats['reg_approved'],   'color' => '#177a4c'],
      ], 120) ?>
    </div>
    <?= ui_card_close() ?>
  </div>
  <div class="col-md-6">
    <?= ui_card_open('Document Requests', 'file-earmark-text',
        '<a href="' . BASE_URL . '/staff/document_requests.php" class="btn btn-sm btn-outline-primary">View All</a>') ?>
    <div class="content-card-body">
      <?= ui_progress_rows([
          'Pending'           => ['val' => (int)$stats['doc_pending'],    'color' => '#d97706'],
          'Processing'        => ['val' => (int)$stats['doc_processing'], 'color' => '#115540'],
          'Ready for Release' => ['val' => (int)$stats['doc_ready'],      'color' => '#1768a8'],
          'Released'          => ['val' => (int)$stats['doc_released'],   'color' => '#177a4c'],
      ], 140) ?>
    </div>
    <?= ui_card_close() ?>
  </div>
</div>

<!-- ── Recent tables ── -->
<div class="row g-3">
  <div class="col-lg-6">
    <?= ui_card_open('Recent Pre-Registrations', 'clock-history',
        '<a href="' . BASE_URL . '/staff/registrations.php" class="btn btn-sm btn-outline-secondary">All</a>') ?>
    <?php
    $regRows = [];
    foreach ($recentRegs as $r) {
        $regRows[] = [
            '<div class="row-name">' . e($r['last_name'] . ', ' . $r['first_name']) . '</div><div class="row-sub">' . e($r['tracking_code']) . '</div>',
            '<span class="badge bg-light text-dark small">' . e($r['prog_code'] ?? '—') . '</span>',
            statusBadge($r['status']),
            '<span class="text-muted small">' . timeAgo($r['submitted_at']) . '</span>',
            '<a href="' . BASE_URL . '/staff/view_registration.php?id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
        ];
    }
    echo ui_table(['Name', 'Program', 'Status', 'Submitted', ''], $regRows,
                  ['empty_title' => 'No registrations yet', 'empty_msg' => 'New submissions will appear here.']);
    ?>
    <?= ui_card_close() ?>
  </div>
  <div class="col-lg-6">
    <?= ui_card_open('Recent Document Requests', 'clock-history',
        '<a href="' . BASE_URL . '/staff/document_requests.php" class="btn btn-sm btn-outline-secondary">All</a>') ?>
    <?php
    $docRows = [];
    foreach ($recentDocs as $d) {
        $docRows[] = [
            '<div class="row-name">' . e($d['last_name'] . ', ' . $d['first_name']) . '</div><div class="row-sub">' . e($d['tracking_code']) . '</div>',
            '<span class="badge bg-light text-dark small">' . e($d['doc_code'] ?? '—') . '</span>',
            statusBadge($d['status'], 'document_request'),
            '<span class="text-muted small">' . timeAgo($d['submitted_at']) . '</span>',
            '<a href="' . BASE_URL . '/staff/view_document.php?id=' . (int)$d['id'] . '" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>',
        ];
    }
    echo ui_table(['Name', 'Document', 'Status', 'Submitted', ''], $docRows,
                  ['empty_title' => 'No requests yet', 'empty_msg' => 'New requests will appear here.']);
    ?>
    <?= ui_card_close() ?>
  </div>
</div>

</div><!-- /page-content -->
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

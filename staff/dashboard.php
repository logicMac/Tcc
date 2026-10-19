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

<!-- ── Page Header ── -->
<div class="page-header">
  <div class="page-header-info">
    <h4>Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
      <?= e(explode(' ', currentUser()['full_name'])[0]) ?>! 👋</h4>
    <p>Here's what's happening today – <?= date('F d, Y') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/staff/registrations.php?status=Pending" class="btn btn-primary btn-sm">
      <i class="bi bi-inbox me-1"></i> View Pending
    </a>
  </div>
</div>

<!-- ── Today Summary ── -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-clock"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Enrollments</div>
        <div class="stat-value"><?= (int)$stats['reg_pending'] ?></div>
        <div class="stat-sub"><?= (int)$todayReg ?> submitted today</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-file-earmark-clock"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Documents</div>
        <div class="stat-value"><?= (int)$stats['doc_pending'] ?></div>
        <div class="stat-sub"><?= (int)$todayDoc ?> submitted today</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-info">
        <div class="stat-label">Approved Enrollments</div>
        <div class="stat-value"><?= (int)$stats['reg_approved'] ?></div>
        <div class="stat-sub">of <?= (int)$stats['reg_total'] ?> total</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon teal"><i class="bi bi-bag-check"></i></div>
      <div class="stat-info">
        <div class="stat-label">Ready for Release</div>
        <div class="stat-value"><?= (int)$stats['doc_ready'] ?></div>
        <div class="stat-sub"><?= (int)$stats['doc_released'] ?> released</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Status breakdown ── -->
<div class="row g-3 mb-4">
  <!-- Enrollment status bar -->
  <div class="col-md-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5><i class="bi bi-person-plus me-2 text-primary"></i>Enrollment Pre-Registrations</h5>
        <a href="<?= BASE_URL ?>/staff/registrations.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="content-card-body">
        <?php
        $regBreakdown = [
            'Pending'          => ['val' => (int)$stats['reg_pending'],    'color' => '#f59e0b'],
            'Under Review'     => ['val' => (int)$stats['reg_review'],     'color' => '#3b82f6'],
            'Processing'       => ['val' => (int)$stats['reg_processing'], 'color' => '#8b5cf6'],
            'Approved'         => ['val' => (int)$stats['reg_approved'],   'color' => '#10b981'],
        ];
        $regTotal = max(1, array_sum(array_column($regBreakdown, 'val')));
        foreach ($regBreakdown as $lbl => $b): ?>
        <div class="d-flex align-items-center mb-2">
          <div style="width:120px;font-size:.8rem;color:#475569;"><?= e($lbl) ?></div>
          <div class="flex-grow-1 mx-2">
            <div class="progress" style="height:8px;border-radius:4px;">
              <div class="progress-bar" style="width:<?= round($b['val']/$regTotal*100) ?>%;background:<?= $b['color'] ?>;border-radius:4px;"></div>
            </div>
          </div>
          <div style="width:30px;text-align:right;font-size:.8rem;font-weight:600;"><?= $b['val'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Document status bar -->
  <div class="col-md-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5><i class="bi bi-file-earmark-text me-2 text-success"></i>Document Requests</h5>
        <a href="<?= BASE_URL ?>/staff/document_requests.php" class="btn btn-sm btn-outline-success">View All</a>
      </div>
      <div class="content-card-body">
        <?php
        $docBreakdown = [
            'Pending'           => ['val' => (int)$stats['doc_pending'],    'color' => '#f59e0b'],
            'Processing'        => ['val' => (int)$stats['doc_processing'], 'color' => '#8b5cf6'],
            'Ready for Release' => ['val' => (int)$stats['doc_ready'],      'color' => '#0ea5e9'],
            'Released'          => ['val' => (int)$stats['doc_released'],   'color' => '#10b981'],
        ];
        $docTotal = max(1, array_sum(array_column($docBreakdown, 'val')));
        foreach ($docBreakdown as $lbl => $b): ?>
        <div class="d-flex align-items-center mb-2">
          <div style="width:140px;font-size:.8rem;color:#475569;"><?= e($lbl) ?></div>
          <div class="flex-grow-1 mx-2">
            <div class="progress" style="height:8px;border-radius:4px;">
              <div class="progress-bar" style="width:<?= round($b['val']/$docTotal*100) ?>%;background:<?= $b['color'] ?>;border-radius:4px;"></div>
            </div>
          </div>
          <div style="width:30px;text-align:right;font-size:.8rem;font-weight:600;"><?= $b['val'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent tables ── -->
<div class="row g-3">
  <!-- Recent Registrations -->
  <div class="col-lg-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5>Recent Pre-Registrations</h5>
        <a href="<?= BASE_URL ?>/staff/registrations.php" class="btn btn-sm btn-outline-secondary">All</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th>Name</th><th>Program</th><th>Status</th><th>Submitted</th><th></th>
          </tr></thead>
          <tbody>
          <?php if (empty($recentRegs)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No registrations yet.</td></tr>
          <?php else: foreach ($recentRegs as $r): ?>
          <tr>
            <td>
              <div class="fw-semibold small"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div>
              <div class="text-muted" style="font-size:.7rem;"><?= e($r['tracking_code']) ?></div>
            </td>
            <td><span class="badge bg-light text-dark small"><?= e($r['prog_code'] ?? '—') ?></span></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td><span class="text-muted small"><?= timeAgo($r['submitted_at']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$r['id'] ?>"
                 class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">View</a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Document Requests -->
  <div class="col-lg-6">
    <div class="content-card">
      <div class="content-card-header">
        <h5>Recent Document Requests</h5>
        <a href="<?= BASE_URL ?>/staff/document_requests.php" class="btn btn-sm btn-outline-secondary">All</a>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr>
            <th>Name</th><th>Document</th><th>Status</th><th>Submitted</th><th></th>
          </tr></thead>
          <tbody>
          <?php if (empty($recentDocs)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No requests yet.</td></tr>
          <?php else: foreach ($recentDocs as $d): ?>
          <tr>
            <td>
              <div class="fw-semibold small"><?= e($d['last_name'] . ', ' . $d['first_name']) ?></div>
              <div class="text-muted" style="font-size:.7rem;"><?= e($d['tracking_code']) ?></div>
            </td>
            <td><span class="badge bg-light text-dark small"><?= e($d['doc_code'] ?? '—') ?></span></td>
            <td><?= statusBadge($d['status'], 'document_request') ?></td>
            <td><span class="text-muted small"><?= timeAgo($d['submitted_at']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$d['id'] ?>"
                 class="btn btn-sm btn-outline-success py-0 px-2">View</a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div><!-- /page-content -->
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

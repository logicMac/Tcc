<?php
/**
 * TCC RRS – Student Portal Dashboard
 * Authenticated landing for students: quick actions + recent submissions.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireStudentAuth();

$student   = currentStudent();
$studentId = currentStudentId();

$registrations = db()->fetchAll(
    "SELECT pr.*, p.code AS prog_code, p.name AS prog_name
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.student_account_id = ?
     ORDER BY pr.submitted_at DESC LIMIT 4",
    [$studentId]
);
$docRequests = db()->fetchAll(
    "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code, i.verification_code
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     LEFT JOIN issued_documents i ON i.document_request_id = dr.id
     WHERE dr.student_account_id = ?
     ORDER BY dr.submitted_at DESC LIMIT 4",
    [$studentId]
);

$total   = count($registrations) + count($docRequests);
$pending = count(array_filter($registrations, fn($r) => $r['status'] === 'Pending'))
         + count(array_filter($docRequests,  fn($d) => $d['status'] === 'Pending'));
$done    = count(array_filter($registrations, fn($r) => in_array($r['status'], ['Approved','Released'])))
         + count(array_filter($docRequests,  fn($d) => in_array($d['status'], ['Ready for Release','Released'])));

$firstName = e($student['first_name']);
$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/student_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/student_topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<!-- Welcome strip -->
<div class="stu-hero reveal">
  <div>
    <div class="stu-hero-label">Student Portal</div>
    <h1 class="stu-hero-name">Welcome back, <?= $firstName ?></h1>
    <div class="stu-hero-meta">
      <?= $total ?> submission<?= $total === 1 ? '' : 's' ?> on file
      <?php if ($pending): ?> · <?= $pending ?> pending<?php endif; ?>
      <?php if ($done): ?> · <?= $done ?> completed<?php endif; ?>
    </div>
  </div>
  <div class="stu-hero-no font-monospace d-none d-md-block">
    <?= e($student['student_no'] ?? '') ?>
  </div>
</div>

<!-- Quick actions -->
<div class="row g-3 mb-4">
  <?php
  $actions = [
      ['href' => 'pre_registration.php', 'icon' => 'person-plus',      'label' => 'Pre-Register',      'sub' => 'Enroll for the semester'],
      ['href' => 'document_request.php', 'icon' => 'file-earmark-text','label' => 'Request Document',  'sub' => 'TOR, certificates & more'],
      ['href' => 'track.php',            'icon' => 'search',           'label' => 'Track Submission',  'sub' => 'Check a tracking code'],
      ['href' => BASE_URL . '/verify.php','icon' => 'qr-code-scan',    'label' => 'Verify Document',   'sub' => 'Confirm authenticity'],
  ];
  foreach ($actions as $i => $a): ?>
  <div class="col-6 col-lg-3 reveal" style="--rd:<?= $i * .06 ?>s">
    <a href="<?= strpos($a['href'], '://') === false ? BASE_URL . '/student/' . $a['href'] : $a['href'] ?>"
       class="quick-tile">
      <div class="quick-tile-ico"><i class="bi bi-<?= $a['icon'] ?>"></i></div>
      <div>
        <div class="quick-tile-label"><?= e($a['label']) ?></div>
        <div class="quick-tile-sub"><?= e($a['sub']) ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Recent registrations -->
  <div class="col-lg-6 reveal" style="--rd:.15s">
    <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
      <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Recent Registrations</h6>
        <?= ui_btn('View all', ['size'=>'sm','variant'=>'outline-primary','href'=>'account.php']) ?>
      </div>
      <div class="card-body px-2 px-sm-3 py-3">
        <?php if (empty($registrations)): ?>
        <div class="empty-state py-4">
          <i class="bi bi-inbox"></i>
          <h6>No registrations yet</h6>
          <p class="text-muted small">Start your enrollment pre-registration.</p>
          <?= ui_btn('Pre-Register Now', ['size'=>'sm','href'=>'pre_registration.php']) ?>
        </div>
        <?php else: ?>
        <div class="slip-list">
          <?php foreach ($registrations as $r): ?>
          <a class="slip" href="<?= e(trackingUrl($r['tracking_code'])) ?>">
            <div class="slip-head">
              <span class="slip-code"><?= e($r['tracking_code']) ?></span>
              <span class="slip-date"><i class="bi bi-calendar3 me-1"></i><?= formatDate($r['submitted_at']) ?></span>
            </div>
            <div class="slip-body">
              <div class="slip-main">
                <div class="slip-title">
                  <span class="badge bg-light text-dark"><?= e($r['prog_code'] ?? 'Other') ?></span>
                  <?= e($r['year_level']) ?>
                </div>
                <div class="slip-sub"><?= e($r['school_year']) ?> · <?= e($r['semester']) ?></div>
              </div>
              <div class="slip-status"><?= statusBadge($r['status']) ?></div>
              <i class="bi bi-chevron-right slip-arrow"></i>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent document requests -->
  <div class="col-lg-6 reveal" style="--rd:.2s">
    <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
      <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Recent Document Requests</h6>
        <?= ui_btn('View all', ['size'=>'sm','variant'=>'outline-primary','href'=>'account.php?tab=documents']) ?>
      </div>
      <div class="card-body px-2 px-sm-3 py-3">
        <?php if (empty($docRequests)): ?>
        <div class="empty-state py-4">
          <i class="bi bi-file-earmark-x"></i>
          <h6>No requests yet</h6>
          <p class="text-muted small">Request registrar-issued documents online.</p>
          <?= ui_btn('Request a Document', ['size'=>'sm','href'=>'document_request.php']) ?>
        </div>
        <?php else: ?>
        <div class="slip-list">
          <?php foreach ($docRequests as $d): ?>
          <a class="slip" href="<?= e(trackingUrl($d['tracking_code'])) ?>">
            <div class="slip-head">
              <span class="slip-code"><?= e($d['tracking_code']) ?></span>
              <span class="slip-date"><i class="bi bi-calendar3 me-1"></i><?= formatDate($d['submitted_at']) ?></span>
            </div>
            <div class="slip-body">
              <div class="slip-main">
                <div class="slip-title"><?= e($d['document_type_other'] ?: ($d['doc_name'] ?? '—')) ?></div>
                <div class="slip-sub"><?= (int)$d['copies'] ?> cop<?= (int)$d['copies'] > 1 ? 'ies' : 'y' ?>
                  <?php if ($d['verification_code']): ?>
                  · <i class="bi bi-patch-check text-success"></i>
                    <span class="font-monospace"><?= e($d['verification_code']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="slip-status"><?= statusBadge($d['status'], 'document_request') ?></div>
              <i class="bi bi-chevron-right slip-arrow"></i>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

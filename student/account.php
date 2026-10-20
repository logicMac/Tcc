<?php
/**
 * TCC RRS – Student Account Dashboard
 * Authenticated view of the student's registrations & document requests.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireStudentAuth();

$student    = currentStudent();
$studentId  = currentStudentId();
$student['created_at'] = db()->fetchOne(
    "SELECT created_at FROM students WHERE id = ?", [$studentId]
)['created_at'] ?? null;
$schoolName = getSetting('school_name', 'Tampakan Community College');
$activePage = 'account';
$tab        = get('tab', 'registrations');

// ── Change password ─────────────────────────────────────────────────────────
if (isPost() && post('action') === 'change_password') {
    abortCsrf();
    $current = post('current_password');
    $new     = post('new_password');
    $confirm = post('confirm_password');

    $row = db()->fetchOne("SELECT password_hash FROM students WHERE id = ?", [$studentId]);

    if (!$row || !password_verify($current, $row['password_hash'])) {
        flash('danger', 'Current password is incorrect.');
    } elseif (strlen($new) < 8) {
        flash('danger', 'New password must be at least 8 characters.');
    } elseif ($new !== $confirm) {
        flash('danger', 'New passwords do not match.');
    } else {
        db()->execute(
            "UPDATE students SET password_hash = ? WHERE id = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $studentId]
        );
        flash('success', 'Password updated successfully.');
    }
    redirect(BASE_URL . '/student/account.php?tab=security');
}

// ── Fetch linked submissions ────────────────────────────────────────────────
$registrations = db()->fetchAll(
    "SELECT pr.*, p.code AS prog_code, p.name AS prog_name
     FROM pre_registrations pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.student_account_id = ?
     ORDER BY pr.submitted_at DESC",
    [$studentId]
);

$docRequests = db()->fetchAll(
    "SELECT dr.*, dt.name AS doc_name, dt.code AS doc_code,
            i.verification_code
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     LEFT JOIN issued_documents i ON i.document_request_id = dr.id
     WHERE dr.student_account_id = ?
     ORDER BY dr.submitted_at DESC",
    [$studentId]
);

$counts = [
    'registrations' => count($registrations),
    'documents'     => count($docRequests),
    'pending'       => count(array_filter($registrations, fn($r) => $r['status'] === 'Pending'))
                     + count(array_filter($docRequests, fn($d) => $d['status'] === 'Pending')),
];

$pageTitle = 'My Account';
include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/student_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/student_topbar.php'; ?>
<div class="page-content">
<div class="container-fluid py-2 px-1 px-sm-2">

  <?= renderFlash() ?>

  <!-- ── Student ID card ── -->
  <div class="acct-card mb-4 reveal">
    <div class="acct-id">
      <div class="acct-id-label">Student Record</div>
      <div class="acct-id-row">
        <div class="acct-mono"><?= e(strtoupper($student['first_name'][0] . $student['last_name'][0])) ?></div>
        <div class="acct-id-main">
          <h1 class="acct-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h1>
          <div class="acct-meta">
            <span><i class="bi bi-envelope me-1"></i><?= e($student['email']) ?></span>
            <?php if ($student['student_no']): ?>
            <span><i class="bi bi-person-badge me-1"></i><?= e($student['student_no']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="acct-stats">
          <div class="acct-stat">
            <span class="acct-stat-n"><?= $counts['registrations'] ?></span>
            <span class="acct-stat-l">Registration<?= $counts['registrations'] === 1 ? '' : 's' ?></span>
          </div>
          <div class="acct-stat">
            <span class="acct-stat-n"><?= $counts['documents'] ?></span>
            <span class="acct-stat-l">Document<?= $counts['documents'] === 1 ? '' : 's' ?></span>
          </div>
          <div class="acct-stat">
            <span class="acct-stat-n"><?= $counts['pending'] ?></span>
            <span class="acct-stat-l">Pending</span>
          </div>
        </div>
      </div>
    </div>
    <!-- Tabs -->
    <div class="acct-tabs" role="tablist">
      <a class="acct-tab <?= $tab === 'registrations' ? 'active' : '' ?>"
         href="?tab=registrations"><i class="bi bi-person-plus"></i>Registrations</a>
      <a class="acct-tab <?= $tab === 'documents' ? 'active' : '' ?>"
         href="?tab=documents"><i class="bi bi-file-earmark-text"></i>Document Requests</a>
      <a class="acct-tab acct-tab-end <?= $tab === 'security' ? 'active' : '' ?>"
         href="?tab=security"><i class="bi bi-shield-lock"></i>Security</a>
    </div>
  </div>

  <?php if ($tab === 'security'): ?>
  <!-- ── Security tab ── -->
  <div class="row g-4 justify-content-center">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm reveal" style="border-radius:14px;--rd:.12s">
        <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
          <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <span class="section-num"><i class="bi bi-shield-lock"></i></span>
            Change Password
          </h5>
          <hr class="mt-3 mb-0">
        </div>
        <div class="card-body p-4">
          <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" required minlength="8">
              <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <?= ui_btn('Update Password', ['type'=>'submit','class'=>'w-100','icon'=>'key']) ?>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm reveal" style="border-radius:14px;--rd:.2s">
        <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
          <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <span class="section-num"><i class="bi bi-person-badge"></i></span>
            Account Details
          </h5>
          <hr class="mt-3 mb-0">
        </div>
        <div class="card-body p-4">
          <table class="info-table w-100">
            <tbody>
              <tr><td>Full Name</td><td><?= e(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'] . ' ' . ($student['suffix'] ?? ''))) ?></td></tr>
              <tr><td>Email</td><td><?= e($student['email']) ?></td></tr>
              <?php if (!empty($student['student_no'])): ?>
              <tr><td>Student No.</td><td><span class="font-monospace"><?= e($student['student_no']) ?></span></td></tr>
              <?php endif; ?>
              <?php if (!empty($student['contact_number'])): ?>
              <tr><td>Contact</td><td><?= e($student['contact_number']) ?></td></tr>
              <?php endif; ?>
              <?php if (!empty($student['created_at'])): ?>
              <tr><td>Member Since</td><td><?= formatDate($student['created_at']) ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <div class="alert alert-light border small text-muted d-flex gap-2 mb-0 mt-3">
            <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
            <div>Keep your password private. Registrations and requests submitted under this email are linked to this account.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'documents'): ?>
  <!-- ── Document requests tab ── -->
  <div class="card border-0 shadow-sm reveal" style="border-radius:14px;--rd:.12s">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="fw-bold mb-0">My Document Requests</h5>
      <?= ui_btn('New Request', ['size'=>'sm','icon'=>'plus-lg',
                 'href'=>BASE_URL . '/student/document_request.php']) ?>
    </div>
    <div class="card-body px-2 px-sm-3 py-3">
      <?php if (empty($docRequests)): ?>
      <div class="empty-state py-5">
        <i class="bi bi-file-earmark-x"></i>
        <h6>No document requests yet</h6>
        <p class="text-muted small">Requests you submit while signed in (or made with this email) appear here.</p>
        <?= ui_btn('Request a Document', ['size'=>'sm','href'=>BASE_URL . '/student/document_request.php']) ?>
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

  <?php else: ?>
  <!-- ── Registrations tab ── -->
  <div class="card border-0 shadow-sm reveal" style="border-radius:14px;--rd:.12s">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h5 class="fw-bold mb-0">My Pre-Registrations</h5>
      <?= ui_btn('New Registration', ['size'=>'sm','icon'=>'plus-lg',
                 'href'=>BASE_URL . '/student/pre_registration.php']) ?>
    </div>
    <div class="card-body px-2 px-sm-3 py-3">
      <?php if (empty($registrations)): ?>
      <div class="empty-state py-5">
        <i class="bi bi-inbox"></i>
        <h6>No registrations yet</h6>
        <p class="text-muted small">Submissions made while signed in (or with this email) appear here.</p>
        <?= ui_btn('Pre-Register Now', ['size'=>'sm','href'=>BASE_URL . '/student/pre_registration.php']) ?>
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
  <?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

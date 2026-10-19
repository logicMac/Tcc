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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Account – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body style="background:var(--bg-app);">

<?php include __DIR__ . '/../includes/partials/portal_nav.php'; ?>

<div class="container py-4">

  <?= renderFlash() ?>

  <!-- ── Profile header ── -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
    <div class="d-flex flex-wrap align-items-center gap-3 p-4 text-white"
         style="background:linear-gradient(135deg,#0f2a56,#1a4a8a);">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white fw-bold flex-shrink-0"
           style="width:64px;height:64px;font-size:1.4rem;color:#0f2a56;">
        <?= e(strtoupper($student['first_name'][0] . $student['last_name'][0])) ?>
      </div>
      <div class="flex-grow-1">
        <h4 class="fw-bold mb-1"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h4>
        <div class="small opacity-75">
          <?= e($student['email']) ?>
          <?php if ($student['student_no']): ?> · Student No. <?= e($student['student_no']) ?><?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-2">
        <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,.18);">
          <?= $counts['registrations'] ?> registration<?= $counts['registrations'] === 1 ? '' : 's' ?>
        </span>
        <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,.18);">
          <?= $counts['documents'] ?> document<?= $counts['documents'] === 1 ? '' : 's' ?>
        </span>
      </div>
    </div>
    <!-- Tabs -->
    <ul class="nav nav-pills px-3 pt-3 pb-0" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'registrations' ? 'active' : '' ?>"
           href="?tab=registrations"><i class="bi bi-person-plus me-1"></i>My Registrations</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'documents' ? 'active' : '' ?>"
           href="?tab=documents"><i class="bi bi-file-earmark-text me-1"></i>My Document Requests</a>
      </li>
      <li class="nav-item ms-auto">
        <a class="nav-link <?= $tab === 'security' ? 'active' : '' ?>"
           href="?tab=security"><i class="bi bi-shield-lock me-1"></i>Security</a>
      </li>
    </ul>
  </div>

  <?php if ($tab === 'security'): ?>
  <!-- ── Security tab ── -->
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm" style="border-radius:14px;">
        <div class="card-body p-4">
          <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password</h5>
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
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-key me-1"></i>Update Password
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'documents'): ?>
  <!-- ── Document requests tab ── -->
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0">My Document Requests</h5>
      <a href="<?= BASE_URL ?>/student/document_request.php" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Request
      </a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($docRequests)): ?>
      <div class="empty-state py-5">
        <i class="bi bi-file-earmark-x"></i>
        <h6>No document requests yet</h6>
        <p class="text-muted small">Requests you submit while signed in (or made with this email) appear here.</p>
        <a href="<?= BASE_URL ?>/student/document_request.php" class="btn btn-success btn-sm">Request a Document</a>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Tracking Code</th><th>Document</th><th>Status</th>
              <th>Verification</th><th>Submitted</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($docRequests as $d): ?>
            <tr>
              <td><span class="font-monospace small fw-semibold"><?= e($d['tracking_code']) ?></span></td>
              <td>
                <div class="small fw-semibold"><?= e($d['document_type_other'] ?: ($d['doc_name'] ?? '—')) ?></div>
                <div class="text-muted" style="font-size:.7rem;"><?= (int)$d['copies'] ?> cop<?= (int)$d['copies'] > 1 ? 'ies' : 'y' ?></div>
              </td>
              <td><?= statusBadge($d['status'], 'document_request') ?></td>
              <td>
                <?php if ($d['verification_code']): ?>
                  <a href="<?= e(verificationUrl($d['verification_code'])) ?>" target="_blank"
                     class="font-monospace small text-decoration-none">
                    <i class="bi bi-patch-check me-1"></i><?= e($d['verification_code']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td><span class="small text-muted"><?= formatDate($d['submitted_at']) ?></span></td>
              <td>
                <a href="<?= e(trackingUrl($d['tracking_code'])) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Registrations tab ── -->
  <div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
      <h5 class="fw-bold mb-0">My Pre-Registrations</h5>
      <a href="<?= BASE_URL ?>/student/pre_registration.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Registration
      </a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($registrations)): ?>
      <div class="empty-state py-5">
        <i class="bi bi-inbox"></i>
        <h6>No registrations yet</h6>
        <p class="text-muted small">Submissions made while signed in (or with this email) appear here.</p>
        <a href="<?= BASE_URL ?>/student/pre_registration.php" class="btn btn-primary btn-sm">Pre-Register Now</a>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Tracking Code</th><th>Program</th><th>School Year</th>
              <th>Status</th><th>Submitted</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($registrations as $r): ?>
            <tr>
              <td><span class="font-monospace small fw-semibold"><?= e($r['tracking_code']) ?></span></td>
              <td>
                <span class="badge bg-light text-dark"><?= e($r['prog_code'] ?? 'Other') ?></span>
                <div class="text-muted" style="font-size:.7rem;"><?= e($r['year_level']) ?></div>
              </td>
              <td><span class="small"><?= e($r['school_year']) ?> · <?= e($r['semester']) ?></span></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td><span class="small text-muted"><?= formatDate($r['submitted_at']) ?></span></td>
              <td>
                <a href="<?= e(trackingUrl($r['tracking_code'])) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

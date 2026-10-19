<?php
/**
 * TCC RRS – Student Master List
 * Records Management / Report Generation: filterable master list
 * of all registered students, with print + CSV export.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$filterProgram = getInt('program_id');
$filterYear    = get('year_level');
$filterType    = get('enrollment_type');
$filterStatus  = get('status');
$filterSY      = get('school_year');
$filterSem     = get('semester');
$filterSearch  = get('search');
$export        = get('export') === 'csv';

$where  = ['pr.is_archived = 0'];
$params = [];
if ($filterProgram) { $where[] = 'pr.program_id = ?';      $params[] = $filterProgram; }
if ($filterYear)    { $where[] = 'pr.year_level = ?';      $params[] = $filterYear; }
if ($filterType)    { $where[] = 'pr.enrollment_type = ?'; $params[] = $filterType; }
if ($filterStatus)  { $where[] = 'pr.status = ?';          $params[] = $filterStatus; }
if ($filterSY)      { $where[] = 'pr.school_year = ?';     $params[] = $filterSY; }
if ($filterSem)     { $where[] = 'pr.semester = ?';        $params[] = $filterSem; }
if ($filterSearch)  {
    $where[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.email LIKE ? OR pr.lrn LIKE ? OR pr.tracking_code LIKE ?)";
    $q       = '%' . $filterSearch . '%';
    $params  = array_merge($params, [$q,$q,$q,$q,$q]);
}

$sql = "SELECT pr.*, p.code AS prog_code, p.name AS prog_name
        FROM pre_registrations pr
        LEFT JOIN programs p ON p.id = pr.program_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pr.last_name ASC, pr.first_name ASC";

$rows = db()->fetchAll($sql, $params);

// ── CSV export ───────────────────────────────────────────────────────────────
if ($export) {
    $filename = 'student-master-list-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, [
        'Tracking Code','Last Name','First Name','Middle Name','Suffix',
        'LRN','Date of Birth','Gender','Email','Contact Number',
        'Address','Program','Year Level','Enrollment Type',
        'School Year','Semester','Status','Submitted'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['tracking_code'], $r['last_name'], $r['first_name'],
            $r['middle_name'], $r['suffix'], $r['lrn'],
            $r['date_of_birth'], $r['gender'], $r['email'], $r['contact_number'],
            trim($r['house_no_street'].' '.$r['barangay'].' '.$r['municipality_city'].' '.$r['province']),
            $r['program_other'] ?: ($r['prog_name'] ?? ''), $r['year_level'],
            $r['enrollment_type'], $r['school_year'], $r['semester'],
            $r['status'], $r['submitted_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Page render ──────────────────────────────────────────────────────────────
$pageTitle   = 'Student Master List';
$breadcrumbs = [['label' => 'Student Master List']];
$programs    = getActivePrograms();
$schoolYears = db()->fetchAll(
    "SELECT DISTINCT school_year FROM pre_registrations ORDER BY school_year DESC"
);

$qParts  = array_filter([
    $filterProgram ? 'program_id='.$filterProgram : '',
    $filterYear    ? 'year_level='.urlencode($filterYear) : '',
    $filterType    ? 'enrollment_type='.urlencode($filterType) : '',
    $filterStatus  ? 'status='.urlencode($filterStatus) : '',
    $filterSY      ? 'school_year='.urlencode($filterSY) : '',
    $filterSem     ? 'semester='.urlencode($filterSem) : '',
    $filterSearch  ? 'search='.urlencode($filterSearch) : '',
]);
$qs       = implode('&', $qParts);
$exportUrl = BASE_URL . '/staff/master_list.php' . ($qs ? '?' . $qs . '&export=csv' : '?export=csv');

include __DIR__ . '/../includes/partials/head.php';
include isRegistrar()
    ? __DIR__ . '/../includes/partials/registrar_sidebar.php'
    : __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><i class="bi bi-people me-2 text-primary"></i>Student Master List</h4>
    <p>Master list of all registered students — filter, print, or export to CSV.</p>
  </div>
  <div class="page-header-actions">
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <a href="<?= e($exportUrl) ?>" class="btn btn-sm btn-success">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
    </a>
  </div>
</div>

<!-- Filters -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Search</label>
      <input type="text" name="search" class="form-control form-control-sm"
             value="<?= e($filterSearch) ?>" placeholder="Name, email, LRN…">
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Program</label>
      <select name="program_id" class="form-select form-select-sm">
        <option value="">All Programs</option>
        <?php foreach ($programs as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $filterProgram===(int)$p['id']?'selected':'' ?>>
          <?= e($p['code']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Year Level</label>
      <select name="year_level" class="form-select form-select-sm">
        <option value="">All Years</option>
        <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $yl): ?>
        <option value="<?= e($yl) ?>" <?= $filterYear===$yl?'selected':'' ?>><?= e($yl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Enrollment Type</label>
      <select name="enrollment_type" class="form-select form-select-sm">
        <option value="">All Types</option>
        <?php foreach (['New Student','Transferee','Returnee','Cross-Enrollee'] as $et): ?>
        <option value="<?= e($et) ?>" <?= $filterType===$et?'selected':'' ?>><?= e($et) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">School Year</label>
      <select name="school_year" class="form-select form-select-sm">
        <option value="">All Years</option>
        <?php foreach ($schoolYears as $sy): ?>
        <option value="<?= e($sy['school_year']) ?>" <?= $filterSY===$sy['school_year']?'selected':'' ?>>
          <?= e($sy['school_year']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-1">
      <label class="form-label">Semester</label>
      <select name="semester" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach (['1st Semester','2nd Semester','Summer'] as $sm): ?>
        <option value="<?= e($sm) ?>" <?= $filterSem===$sm?'selected':'' ?>><?= e($sm) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4 col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (array_keys(REG_STATUSES) as $s): ?>
        <option value="<?= e($s) ?>" <?= $filterStatus===$s?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
      <a href="<?= BASE_URL ?>/staff/master_list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- Table -->
<div class="content-card">
  <div class="content-card-header">
    <h5>
      <i class="bi bi-list-ul me-1 text-muted"></i>Students
      <span class="badge bg-light text-dark ms-1 fw-normal"><?= number_format(count($rows)) ?></span>
    </h5>
    <span class="text-muted small"><?= e(getSetting('school_name','Tampakan Community College')) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Student Name</th>
          <th>LRN</th>
          <th>Program</th>
          <th>Year</th>
          <th>Type</th>
          <th>S.Y. / Sem</th>
          <th>Status</th>
          <th class="d-print-none">Record</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h6>No students found</h6>
            <p>Adjust your filters or wait for new registrations.</p>
          </div>
        </td></tr>
        <?php else: foreach ($rows as $i => $r): ?>
        <tr>
          <td class="text-muted small"><?= $i + 1 ?></td>
          <td>
            <div class="fw-semibold small"><?= e(fullName($r, true)) ?></div>
            <div class="text-muted" style="font-size:.7rem;"><?= e($r['email']) ?></div>
          </td>
          <td><span class="small font-monospace"><?= e($r['lrn'] ?: '—') ?></span></td>
          <td>
            <span class="badge bg-light text-dark" title="<?= e($r['prog_name'] ?? $r['program_other'] ?? '') ?>">
              <?= e($r['prog_code'] ?? ($r['program_other'] ? 'Other' : '—')) ?>
            </span>
          </td>
          <td><span class="small"><?= e($r['year_level']) ?></span></td>
          <td><span class="small"><?= e($r['enrollment_type']) ?></span></td>
          <td><span class="small text-muted"><?= e($r['school_year']) ?><br><?= e($r['semester']) ?></span></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td class="d-print-none">
            <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$r['id'] ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="px-3 py-2 border-top text-muted small">
    Generated <?= formatDateTime(date('Y-m-d H:i:s')) ?> ·
    <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

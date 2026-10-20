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
$exportUrl = BASE_URL . '/' . currentRole() . '/master_list.php' . ($qs ? '?' . $qs . '&export=csv' : '?export=csv');

include __DIR__ . '/../includes/partials/head.php';
include isRegistrar()
    ? __DIR__ . '/../includes/partials/registrar_sidebar.php'
    : __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Student Master List',
    'Master list of all registered students — filter, print, or export to CSV.',
    'people',
    ui_btn('Print', ['size'=>'sm','variant'=>'outline-dark','icon'=>'printer','attrs'=>'onclick="window.print()"'])
    . ui_btn('Export CSV', ['size'=>'sm','variant'=>'success','icon'=>'file-earmark-spreadsheet','href'=>$exportUrl])
) ?>

<?php
echo ui_filter_bar(
    BASE_URL . '/' . currentRole() . '/master_list.php',
    [
        ['type' => 'search', 'name' => 'search', 'label' => 'Search', 'value' => $filterSearch,
         'placeholder' => 'Name, email, LRN…'],
        ['type' => 'select', 'name' => 'program_id', 'label' => 'Program', 'value' => $filterProgram,
         'options' => ['' => 'All Programs'] + array_column($programs, 'code', 'id')],
        ['type' => 'select', 'name' => 'year_level', 'label' => 'Year Level', 'value' => $filterYear,
         'options' => ['' => 'All Years'] + array_combine(['1st Year','2nd Year','3rd Year','4th Year','5th Year'], ['1st Year','2nd Year','3rd Year','4th Year','5th Year'])],
        ['type' => 'select', 'name' => 'enrollment_type', 'label' => 'Enrollment Type', 'value' => $filterType,
         'options' => ['' => 'All Types'] + array_combine(ENROLLMENT_TYPES, ENROLLMENT_TYPES)],
        ['type' => 'select', 'name' => 'school_year', 'label' => 'School Year', 'value' => $filterSY,
         'options' => ['' => 'All Years'] + array_column($schoolYears, 'school_year', 'school_year'), 'col' => 'col-md-1'],
        ['type' => 'select', 'name' => 'semester', 'label' => 'Semester', 'value' => $filterSem,
         'options' => ['' => 'All'] + array_combine(['1st Semester','2nd Semester','Summer'], ['1st Semester','2nd Semester','Summer']), 'col' => 'col-md-1'],
        ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'value' => $filterStatus,
         'options' => ['' => 'All Statuses'] + array_combine(array_keys(REG_STATUSES), array_keys(REG_STATUSES))],
    ],
    [],
    BASE_URL . '/' . currentRole() . '/master_list.php'
);

$tableRows = [];
foreach ($rows as $i => $r) {
    $tableRows[] = [
        '<span class="text-muted small">' . ($i + 1) . '</span>',
        '<div class="row-name">' . e(fullName($r, true)) . '</div><div class="row-sub">' . e($r['email']) . '</div>',
        '<span class="small font-monospace">' . e($r['lrn'] ?: '—') . '</span>',
        '<span class="badge bg-light text-dark" title="' . e($r['prog_name'] ?? $r['program_other'] ?? '') . '">'
            . e($r['prog_code'] ?? ($r['program_other'] ? 'Other' : '—')) . '</span>',
        '<span class="small">' . e($r['year_level']) . '</span>',
        '<span class="small">' . e($r['enrollment_type']) . '</span>',
        '<span class="small text-muted">' . e($r['school_year']) . '<br>' . e($r['semester']) . '</span>',
        statusBadge($r['status']),
        ['html' => ui_btn('View', ['size'=>'sm','variant'=>'outline-primary','class'=>'py-0 px-2',
                   'href'=>BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . (int)$r['id']]),
         'class' => 'd-print-none'],
    ];
}

echo '<div class="d-print-none">';
echo ui_card_open(
    'Students <span class="badge bg-light text-dark ms-1 fw-normal">' . number_format(count($rows)) . '</span>',
    'list-ul',
    '<span class="text-muted small">' . e(getSetting('school_name', 'Tampakan Community College')) . '</span>'
);
echo ui_table(
    [
        ['label' => '#', 'style' => 'width:40px'], 'Student Name', 'LRN', 'Program',
        'Year', 'Type', 'S.Y. / Sem', 'Status', ['label' => 'Record', 'class' => 'd-print-none'],
    ],
    $tableRows,
    ['empty_title' => 'No students found', 'empty_msg' => 'Adjust your filters or wait for new registrations.']
);
echo '<div class="list-footer"><span class="list-footer-range">Generated ' . formatDateTime(date('Y-m-d H:i:s'))
   . ' · ' . count($rows) . ' record' . (count($rows) === 1 ? '' : 's') . '</span></div>';
echo ui_card_close();
echo '</div>';
?>

<!-- ── Print-only tabular report ── -->
<div class="d-none d-print-block">
  <div class="print-header">
    <div class="ph-brand">
      <img class="ph-seal" src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="">
      <div>
        <div class="ph-school"><?= e(getSetting('school_name', 'Tampakan Community College')) ?></div>
        <div class="ph-addr">Registration &amp; Records System</div>
      </div>
      <div class="ph-meta">
        <div class="ph-doc">STUDENT MASTER LIST</div>
        <div class="ph-date">Generated <?= formatDateTime(date('Y-m-d H:i:s')) ?> · <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></div>
      </div>
    </div>
  </div>

  <?php
  $filterDesc = [];
  if ($filterSearch)  $filterDesc[] = 'Search: ' . $filterSearch;
  if ($filterProgram) {
      $progName = array_column($programs, 'name', 'id')[$filterProgram] ?? '';
      $filterDesc[] = 'Program: ' . $progName;
  }
  if ($filterYear)    $filterDesc[] = 'Year Level: ' . $filterYear;
  if ($filterType)    $filterDesc[] = 'Type: ' . $filterType;
  if ($filterSY)      $filterDesc[] = 'S.Y.: ' . $filterSY;
  if ($filterSem)     $filterDesc[] = 'Semester: ' . $filterSem;
  if ($filterStatus)  $filterDesc[] = 'Status: ' . $filterStatus;
  ?>
  <?php if ($filterDesc): ?>
  <div class="report-filters">Filters: <?= e(implode(' · ', $filterDesc)) ?></div>
  <?php endif; ?>

  <table class="report-table">
    <thead>
      <tr>
        <th style="width:28px;">#</th>
        <th>Student Name</th>
        <th>Email</th>
        <th>LRN</th>
        <th>Program</th>
        <th>Year</th>
        <th>Type</th>
        <th>S.Y.</th>
        <th>Semester</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><?= e(fullName($r, true)) ?></td>
        <td><?= e($r['email']) ?></td>
        <td class="mono"><?= e($r['lrn'] ?: '—') ?></td>
        <td><?= e($r['program_other'] ?: ($r['prog_code'] ?? '—')) ?></td>
        <td><?= e($r['year_level']) ?></td>
        <td><?= e($r['enrollment_type']) ?></td>
        <td><?= e($r['school_year']) ?></td>
        <td><?= e($r['semester']) ?></td>
        <td><?= e($r['status']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="10" style="text-align:center;color:#666;">No records matched the selected filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="print-footer">
    <div class="pf-note">End of report — <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> · <?= e(getSetting('school_name', 'Tampakan Community College')) ?></div>
  </div>
</div>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

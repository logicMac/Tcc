<?php
/**
 * TCC RRS – Staff: Edit Pre-Registration Record
 * Records Management: update a student's submitted information.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$id  = getInt('id');
if (!$id) { flash('danger','Invalid registration.'); redirect(BASE_URL.'/staff/registrations.php'); }

$reg = db()->fetchOne("SELECT * FROM pre_registrations WHERE id = ?", [$id]);
if (!$reg) { flash('danger','Registration not found.'); redirect(BASE_URL.'/staff/registrations.php'); }

$pageTitle   = 'Edit Registration – ' . $reg['tracking_code'];
$breadcrumbs = [
    ['label' => 'Pre-Registrations', 'url' => BASE_URL . '/staff/registrations.php'],
    ['label' => e($reg['tracking_code'])],
];
$programs = getActivePrograms();
$errors   = [];

if (isPost()) {
    abortCsrf();

    $data = [
        'first_name'        => post('first_name'),
        'middle_name'       => post('middle_name'),
        'last_name'         => post('last_name'),
        'suffix'            => post('suffix'),
        'date_of_birth'     => post('date_of_birth'),
        'gender'            => post('gender'),
        'civil_status'      => post('civil_status'),
        'nationality'       => post('nationality') ?: 'Filipino',
        'religion'          => post('religion'),
        'email'             => post('email'),
        'contact_number'    => post('contact_number'),
        'house_no_street'   => post('house_no_street'),
        'barangay'          => post('barangay'),
        'municipality_city' => post('municipality_city'),
        'province'          => post('province'),
        'zip_code'          => post('zip_code'),
        'program_id'        => postInt('program_id') ?: null,
        'program_other'     => post('program_other'),
        'year_level'        => post('year_level'),
        'enrollment_type'   => post('enrollment_type'),
        'school_year'       => post('school_year'),
        'semester'          => post('semester'),
        'previous_school'   => post('previous_school'),
        'previous_program'  => post('previous_program'),
        'last_year_attended'=> post('last_year_attended'),
        'lrn'               => post('lrn'),
        'guardian_name'     => post('guardian_name'),
        'guardian_relation' => post('guardian_relation'),
        'guardian_contact'  => post('guardian_contact'),
        'edit_note'         => post('edit_note'),
    ];

    if (empty($data['first_name']))        $errors[] = 'First name is required.';
    if (empty($data['last_name']))         $errors[] = 'Last name is required.';
    if (!validateDate($data['date_of_birth'])) $errors[] = 'Valid date of birth is required.';
    if (empty($data['gender']))            $errors[] = 'Gender is required.';
    if (!validateEmail($data['email']))    $errors[] = 'Valid email address is required.';
    if (empty($data['contact_number']))    $errors[] = 'Contact number is required.';
    if (empty($data['year_level']))        $errors[] = 'Year level is required.';
    if (empty($data['enrollment_type']))   $errors[] = 'Enrollment type is required.';
    if (empty($data['semester']))          $errors[] = 'Semester is required.';
    if (empty($data['school_year']))       $errors[] = 'School year is required.';
    if (!$data['program_id'] && empty($data['program_other'])) {
        $errors[] = 'Program / Course is required.';
    }

    if (empty($errors)) {
        db()->execute(
            "UPDATE pre_registrations SET
                first_name=?, middle_name=?, last_name=?, suffix=?,
                date_of_birth=?, gender=?, civil_status=?, nationality=?, religion=?,
                email=?, contact_number=?,
                house_no_street=?, barangay=?, municipality_city=?, province=?, zip_code=?,
                program_id=?, program_other=?, year_level=?, enrollment_type=?,
                school_year=?, semester=?, previous_school=?, previous_program=?,
                last_year_attended=?, lrn=?,
                guardian_name=?, guardian_relation=?, guardian_contact=?,
                updated_at=NOW()
             WHERE id=?",
            [$data['first_name'],$data['middle_name'],$data['last_name'],$data['suffix'],
             $data['date_of_birth'],$data['gender'],$data['civil_status'],$data['nationality'],$data['religion'],
             $data['email'],$data['contact_number'],
             $data['house_no_street'],$data['barangay'],$data['municipality_city'],$data['province'],$data['zip_code'],
             $data['program_id'],$data['program_other'],$data['year_level'],$data['enrollment_type'],
             $data['school_year'],$data['semester'],$data['previous_school'],$data['previous_program'],
             $data['last_year_attended'],$data['lrn'],
             $data['guardian_name'],$data['guardian_relation'],$data['guardian_contact'],
             $id]
        );

        addStatusHistory('registration', $id, $reg['tracking_code'], $reg['status'], $reg['status'],
            'Record details updated by staff.' . ($data['edit_note'] ? ' — ' . $data['edit_note'] : ''));
        logActivity('RECORD_EDIT', 'Records Management',
            "Edited registration record {$reg['tracking_code']}", $reg['tracking_code']);

        flash('success', 'Record updated successfully.');
        redirect(BASE_URL . '/staff/view_registration.php?id=' . $id);
    }
    // Re-render with posted values on error
    $reg = array_merge($reg, $data);
}

function f(string $key, array $reg): string {
    return e($_POST[$key] ?? $reg[$key] ?? '');
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/staff_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<div class="page-header">
  <div class="page-header-info">
    <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Record – <?= e($reg['tracking_code']) ?></h4>
    <p>Update the student's submitted information. Changes are logged.</p>
  </div>
  <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$id ?>" class="btn-back">
    <i class="bi bi-arrow-left"></i> Cancel &amp; Back
  </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="">
  <?= csrfField() ?>

  <!-- Personal -->
  <div class="content-card mb-3">
    <div class="content-card-header"><h5><i class="bi bi-person me-2"></i>Personal Information</h5></div>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">First Name <span class="required-mark">*</span></label>
          <input type="text" name="first_name" class="form-control" value="<?= f('first_name',$reg) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="<?= f('middle_name',$reg) ?>"></div>
        <div class="col-md-3"><label class="form-label">Last Name <span class="required-mark">*</span></label>
          <input type="text" name="last_name" class="form-control" value="<?= f('last_name',$reg) ?>" required></div>
        <div class="col-md-1"><label class="form-label">Suffix</label>
          <input type="text" name="suffix" class="form-control" value="<?= f('suffix',$reg) ?>"></div>
        <div class="col-md-2"><label class="form-label">LRN</label>
          <input type="text" name="lrn" class="form-control" value="<?= f('lrn',$reg) ?>"></div>

        <div class="col-md-3"><label class="form-label">Date of Birth <span class="required-mark">*</span></label>
          <input type="date" name="date_of_birth" class="form-control" value="<?= f('date_of_birth',$reg) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Gender <span class="required-mark">*</span></label>
          <select name="gender" class="form-select" required>
            <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?= e($g) ?>" <?= f('gender',$reg)===$g?'selected':'' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label">Civil Status</label>
          <select name="civil_status" class="form-select">
            <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
            <option value="<?= e($cs) ?>" <?= f('civil_status',$reg)===$cs?'selected':'' ?>><?= e($cs) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label">Nationality</label>
          <input type="text" name="nationality" class="form-control" value="<?= f('nationality',$reg) ?>"></div>
        <div class="col-md-3"><label class="form-label">Religion</label>
          <input type="text" name="religion" class="form-control" value="<?= f('religion',$reg) ?>"></div>
      </div>
    </div>
  </div>

  <!-- Contact & Address -->
  <div class="content-card mb-3">
    <div class="content-card-header"><h5><i class="bi bi-geo-alt me-2"></i>Contact &amp; Address</h5></div>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Email <span class="required-mark">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= f('email',$reg) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Contact Number <span class="required-mark">*</span></label>
          <input type="text" name="contact_number" class="form-control" value="<?= f('contact_number',$reg) ?>" required></div>
        <div class="col-md-6"><label class="form-label">House No. / Street <span class="required-mark">*</span></label>
          <input type="text" name="house_no_street" class="form-control" value="<?= f('house_no_street',$reg) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Barangay <span class="required-mark">*</span></label>
          <input type="text" name="barangay" class="form-control" value="<?= f('barangay',$reg) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Municipality / City <span class="required-mark">*</span></label>
          <input type="text" name="municipality_city" class="form-control" value="<?= f('municipality_city',$reg) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Province <span class="required-mark">*</span></label>
          <input type="text" name="province" class="form-control" value="<?= f('province',$reg) ?>" required></div>
        <div class="col-md-4"><label class="form-label">ZIP Code</label>
          <input type="text" name="zip_code" class="form-control" value="<?= f('zip_code',$reg) ?>"></div>
        <div class="col-md-4"><label class="form-label">Guardian Name</label>
          <input type="text" name="guardian_name" class="form-control" value="<?= f('guardian_name',$reg) ?>"></div>
        <div class="col-md-4"><label class="form-label">Guardian Relation</label>
          <input type="text" name="guardian_relation" class="form-control" value="<?= f('guardian_relation',$reg) ?>"></div>
        <div class="col-md-4"><label class="form-label">Guardian Contact</label>
          <input type="text" name="guardian_contact" class="form-control" value="<?= f('guardian_contact',$reg) ?>"></div>
      </div>
    </div>
  </div>

  <!-- Academic -->
  <div class="content-card mb-3">
    <div class="content-card-header"><h5><i class="bi bi-mortarboard me-2"></i>Academic Information</h5></div>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Program <span class="required-mark">*</span></label>
          <select name="program_id" class="form-select">
            <option value="">— Select program —</option>
            <?php foreach ($programs as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)f('program_id',$reg)===(int)$p['id']?'selected':'' ?>>
              <?= e($p['code'] . ' – ' . $p['name']) ?>
            </option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-6"><label class="form-label">Other Program <small class="text-muted">(if not listed)</small></label>
          <input type="text" name="program_other" class="form-control" value="<?= f('program_other',$reg) ?>"></div>
        <div class="col-md-3"><label class="form-label">Year Level <span class="required-mark">*</span></label>
          <select name="year_level" class="form-select" required>
            <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $yl): ?>
            <option value="<?= e($yl) ?>" <?= f('year_level',$reg)===$yl?'selected':'' ?>><?= e($yl) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label">Enrollment Type <span class="required-mark">*</span></label>
          <select name="enrollment_type" class="form-select" required>
            <?php foreach (['New Student','Transferee','Returnee','Cross-Enrollee'] as $et): ?>
            <option value="<?= e($et) ?>" <?= f('enrollment_type',$reg)===$et?'selected':'' ?>><?= e($et) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label">School Year <span class="required-mark">*</span></label>
          <input type="text" name="school_year" class="form-control" value="<?= f('school_year',$reg) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Semester <span class="required-mark">*</span></label>
          <select name="semester" class="form-select" required>
            <?php foreach (['1st Semester','2nd Semester','Summer'] as $sm): ?>
            <option value="<?= e($sm) ?>" <?= f('semester',$reg)===$sm?'selected':'' ?>><?= e($sm) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-4"><label class="form-label">Previous School</label>
          <input type="text" name="previous_school" class="form-control" value="<?= f('previous_school',$reg) ?>"></div>
        <div class="col-md-4"><label class="form-label">Previous Program</label>
          <input type="text" name="previous_program" class="form-control" value="<?= f('previous_program',$reg) ?>"></div>
        <div class="col-md-4"><label class="form-label">Last Year Attended</label>
          <input type="text" name="last_year_attended" class="form-control" value="<?= f('last_year_attended',$reg) ?>"></div>
      </div>
    </div>
  </div>

  <!-- Edit note + submit -->
  <div class="content-card mb-3">
    <div class="content-card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label">Edit Note <small class="text-muted">(optional — shown in record history)</small></label>
          <input type="text" name="edit_note" class="form-control"
                 placeholder="e.g. Corrected spelling per student request">
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-check-lg me-1"></i>Save Changes
          </button>
          <a href="<?= BASE_URL ?>/staff/view_registration.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>
      </div>
    </div>
  </div>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

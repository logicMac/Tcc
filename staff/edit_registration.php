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
if (!$id) { flash('danger','Invalid registration.'); redirect(BASE_URL.'/'.currentRole().'/registrations.php'); }

$reg = db()->fetchOne("SELECT * FROM pre_registrations WHERE id = ?", [$id]);
if (!$reg) { flash('danger','Registration not found.'); redirect(BASE_URL.'/'.currentRole().'/registrations.php'); }

$pageTitle   = 'Edit Registration – ' . $reg['tracking_code'];
$breadcrumbs = [
    ['label' => 'Pre-Registrations', 'url' => BASE_URL . '/' . currentRole() . '/registrations.php'],
    ['label' => e($reg['tracking_code'])],
];
$programs = getActivePrograms();
$errors   = [];

$textFields = [
    'first_name','middle_name','last_name','suffix','date_of_birth','gender','civil_status',
    'religion','email','contact_number','telephone_number','birth_place',
    'citizenship','citizenship_specify',
    'house_no_street','barangay','municipality_city','province','district','region','zip_code',
    'mail_house_no_street','mail_barangay','mail_municipality_city','mail_province',
    'mail_district','mail_region',
    'father_name','father_status','father_contact',
    'mother_name','mother_status','mother_contact',
    'guardian_name','guardian_relation','guardian_contact','guardian_status','spouse_name',
    'elementary_school','elementary_year_graduated','jhs_school','jhs_year_completed',
    'shs_school','shs_year_graduated','college_school','college_year_attended',
    'program_other','year_level','enrollment_type','school_year','semester',
    'previous_school','previous_program','last_year_attended','lrn','edit_note',
];

if (isPost()) {
    abortCsrf();

    $data = [];
    foreach ($textFields as $k) { $data[$k] = post($k); }
    $data['nationality']           = post('nationality') ?: 'Filipino';
    $data['program_id']            = postInt('program_id') ?: null;
    $data['mail_same_as_permanent']= post('mail_same_as_permanent') ? 1 : 0;

    // Originals presented at the office — checklist of credential keys
    $presented = [];
    foreach ((array) post('cred_presented', []) as $ck => $v) {
        if (isset(credentialCatalog()[$ck])) $presented[$ck] = 1;
    }
    $data['credentials_presented'] = $presented ? json_encode($presented) : null;

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
        $set   = [];
        $binds = [];
        foreach ($textFields as $k) {
            if ($k === 'edit_note') continue;
            $set[]   = "$k = ?";
            $binds[] = $data[$k] === '' ? null : $data[$k];
        }
        $set[]   = 'nationality = ?';           $binds[] = $data['nationality'];
        $set[]   = 'program_id = ?';            $binds[] = $data['program_id'];
        $set[]   = 'mail_same_as_permanent = ?';$binds[] = $data['mail_same_as_permanent'];
        $set[]   = 'credentials_presented = ?'; $binds[] = $data['credentials_presented'];
        $set[]   = 'updated_at = NOW()';
        $binds[] = $id;

        db()->execute(
            "UPDATE pre_registrations SET " . implode(', ', $set) . " WHERE id = ?",
            $binds
        );

        $changes = describeRecordChanges($reg, $data);
        addStatusHistory('registration', $id, $reg['tracking_code'], $reg['status'], $reg['status'],
            'Record details updated by staff.'
            . ($data['edit_note'] ? ' — ' . $data['edit_note'] : '')
            . ($changes ? ' Changed: ' . $changes : ''));
        logActivity('RECORD_EDIT', 'Records Management',
            "Edited registration record {$reg['tracking_code']}"
            . ($changes ? ' — ' . $changes : ' — no field changes'),
            $reg['tracking_code']);

        flash('success', 'Record updated successfully.');
        redirect(BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . $id);
    }
    $reg = array_merge($reg, $data);
}

function fv(string $key, array $reg) {
    return $_POST[$key] ?? $reg[$key] ?? '';
}

$opts = static fn(array $list, string $first = '') =>
    ($first !== '' ? ['' => $first] : []) + array_combine($list, $list);

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Edit Record — ' . e($reg['tracking_code']),
    "Update the student's submitted information. Changes are logged.",
    'pencil-square',
    ui_back(BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . (int)$id, 'Cancel & Back')
) ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="">
  <?= csrfField() ?>

  <!-- Personal -->
  <?= ui_card_open('Personal Information', 'person', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <?= ui_field(['name'=>'first_name','label'=>'First Name','value'=>fv('first_name',$reg),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'middle_name','label'=>'Middle Name','value'=>fv('middle_name',$reg),'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'last_name','label'=>'Last Name','value'=>fv('last_name',$reg),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'suffix','label'=>'Suffix','value'=>fv('suffix',$reg),'col'=>'col-md-1']) ?>
        <?= ui_field(['name'=>'lrn','label'=>'LRN','value'=>fv('lrn',$reg),'col'=>'col-md-2']) ?>

        <?= ui_field(['type'=>'date','name'=>'date_of_birth','label'=>'Date of Birth','value'=>fv('date_of_birth',$reg),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'birth_place','label'=>'Birth Place','value'=>fv('birth_place',$reg),'col'=>'col-md-3']) ?>
        <?= ui_field(['type'=>'select','name'=>'gender','label'=>'Sex','value'=>fv('gender',$reg),'required'=>true,'col'=>'col-md-3','options'=>$opts(['Male','Female','Other'])]) ?>
        <?= ui_field(['type'=>'select','name'=>'civil_status','label'=>'Civil Status','value'=>fv('civil_status',$reg),'col'=>'col-md-3','options'=>$opts(['Single','Married','Widowed','Separated','Legal Separated','Solo Parent'])]) ?>

        <?= ui_field(['type'=>'select','name'=>'citizenship','label'=>'Citizenship','value'=>fv('citizenship',$reg) ?: 'Filipino','col'=>'col-md-3','options'=>$opts(['Filipino','Dual','Others'])]) ?>
        <?= ui_field(['name'=>'citizenship_specify','label'=>'Citizenship (specify)','value'=>fv('citizenship_specify',$reg),'col'=>'col-md-3','placeholder'=>'If Dual / Others']) ?>
        <?= ui_field(['name'=>'nationality','label'=>'Nationality','value'=>fv('nationality',$reg),'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'religion','label'=>'Religion','value'=>fv('religion',$reg),'col'=>'col-md-3']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Contact & Permanent Address -->
  <?= ui_card_open('Contact &amp; Permanent Address', 'geo-alt', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <?= ui_field(['type'=>'email','name'=>'email','label'=>'Email','value'=>fv('email',$reg),'required'=>true,'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'contact_number','label'=>'Mobile Number','value'=>fv('contact_number',$reg),'required'=>true,'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'telephone_number','label'=>'Telephone Number','value'=>fv('telephone_number',$reg),'col'=>'col-md-4']) ?>

        <?= ui_field(['name'=>'house_no_street','label'=>'House No. / Street','value'=>fv('house_no_street',$reg),'required'=>true,'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'barangay','label'=>'Barangay','value'=>fv('barangay',$reg),'required'=>true,'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'municipality_city','label'=>'Municipality / City','value'=>fv('municipality_city',$reg),'required'=>true,'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'province','label'=>'Province','value'=>fv('province',$reg),'required'=>true,'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'zip_code','label'=>'ZIP Code','value'=>fv('zip_code',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'district','label'=>'District','value'=>fv('district',$reg),'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'region','label'=>'Region','value'=>fv('region',$reg),'col'=>'col-md-6']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Mailing Address -->
  <?= ui_card_open('Mailing Address', 'mailbox', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="mail_same_as_permanent" id="f-mail_same_as_permanent" value="1"
               <?= fv('mail_same_as_permanent',$reg) ? 'checked' : '' ?>>
        <label class="form-check-label" for="f-mail_same_as_permanent">Same as permanent address</label>
      </div>
      <div class="row g-3">
        <?= ui_field(['name'=>'mail_house_no_street','label'=>'House No. / Street','value'=>fv('mail_house_no_street',$reg),'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'mail_barangay','label'=>'Barangay','value'=>fv('mail_barangay',$reg),'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'mail_municipality_city','label'=>'Municipality / City','value'=>fv('mail_municipality_city',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'mail_province','label'=>'Province','value'=>fv('mail_province',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'mail_district','label'=>'District','value'=>fv('mail_district',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'mail_region','label'=>'Region','value'=>fv('mail_region',$reg),'col'=>'col-md-6']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Family -->
  <?= ui_card_open('Family Information', 'people', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <?= ui_field(['name'=>'father_name','label'=>"Father's Name",'value'=>fv('father_name',$reg),'col'=>'col-md-5']) ?>
        <?= ui_field(['type'=>'select','name'=>'father_status','label'=>'Status','value'=>fv('father_status',$reg),'col'=>'col-md-3','options'=>$opts(['Living','Deceased'],'— Select —')]) ?>
        <?= ui_field(['name'=>'father_contact','label'=>'Contact','value'=>fv('father_contact',$reg),'col'=>'col-md-4']) ?>

        <?= ui_field(['name'=>'mother_name','label'=>"Mother's Name",'value'=>fv('mother_name',$reg),'col'=>'col-md-5']) ?>
        <?= ui_field(['type'=>'select','name'=>'mother_status','label'=>'Status','value'=>fv('mother_status',$reg),'col'=>'col-md-3','options'=>$opts(['Living','Deceased'],'— Select —')]) ?>
        <?= ui_field(['name'=>'mother_contact','label'=>'Contact','value'=>fv('mother_contact',$reg),'col'=>'col-md-4']) ?>

        <?= ui_field(['name'=>'guardian_name','label'=>'Guardian Name','value'=>fv('guardian_name',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'guardian_relation','label'=>'Relation','value'=>fv('guardian_relation',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'guardian_contact','label'=>'Guardian Contact','value'=>fv('guardian_contact',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['type'=>'select','name'=>'guardian_status','label'=>'Guardian Status','value'=>fv('guardian_status',$reg),'col'=>'col-md-4','options'=>$opts(['Living','Deceased'],'— Select —')]) ?>
        <?= ui_field(['name'=>'spouse_name','label'=>'Spouse (if married)','value'=>fv('spouse_name',$reg),'col'=>'col-md-8']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Schools Attended -->
  <?= ui_card_open('Schools Attended', 'building', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <?= ui_field(['name'=>'elementary_school','label'=>'Elementary School','value'=>fv('elementary_school',$reg),'col'=>'col-md-8']) ?>
        <?= ui_field(['name'=>'elementary_year_graduated','label'=>'Year Graduated','value'=>fv('elementary_year_graduated',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'jhs_school','label'=>'Junior High School','value'=>fv('jhs_school',$reg),'col'=>'col-md-8']) ?>
        <?= ui_field(['name'=>'jhs_year_completed','label'=>'Year Completed','value'=>fv('jhs_year_completed',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'shs_school','label'=>'Senior High School','value'=>fv('shs_school',$reg),'col'=>'col-md-8']) ?>
        <?= ui_field(['name'=>'shs_year_graduated','label'=>'Year Graduated','value'=>fv('shs_year_graduated',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'college_school','label'=>'College (last attended)','value'=>fv('college_school',$reg),'col'=>'col-md-8']) ?>
        <?= ui_field(['name'=>'college_year_attended','label'=>'Year Attended','value'=>fv('college_year_attended',$reg),'col'=>'col-md-4']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Academic -->
  <?= ui_card_open('Academic Information', 'mortarboard', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="f-program_id">Program <span class="required-mark">*</span></label>
          <select name="program_id" id="f-program_id" class="form-select">
            <option value="">— Select program —</option>
            <?php foreach ($programs as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)fv('program_id',$reg)===(int)$p['id']?'selected':'' ?>>
              <?= e($p['code'] . ' – ' . $p['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?= ui_field(['name'=>'program_other','label'=>'Other Program (if not listed)','value'=>fv('program_other',$reg),'col'=>'col-md-6']) ?>
        <?= ui_field(['type'=>'select','name'=>'year_level','label'=>'Year Level','value'=>fv('year_level',$reg),'required'=>true,'col'=>'col-md-3','options'=>$opts(['1st Year','2nd Year','3rd Year','4th Year','5th Year'])]) ?>
        <?= ui_field(['type'=>'select','name'=>'enrollment_type','label'=>'Enrollment Type','value'=>fv('enrollment_type',$reg),'required'=>true,'col'=>'col-md-3','options'=>$opts(ENROLLMENT_TYPES)]) ?>
        <?= ui_field(['name'=>'school_year','label'=>'School Year','value'=>fv('school_year',$reg),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['type'=>'select','name'=>'semester','label'=>'Semester','value'=>fv('semester',$reg),'required'=>true,'col'=>'col-md-3','options'=>$opts(['1st Semester','2nd Semester','Summer'])]) ?>
        <?= ui_field(['name'=>'previous_school','label'=>'Previous School','value'=>fv('previous_school',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'previous_program','label'=>'Previous Program','value'=>fv('previous_program',$reg),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'last_year_attended','label'=>'Last Year Attended','value'=>fv('last_year_attended',$reg),'col'=>'col-md-4']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- School Credentials Presented -->
  <?= ui_card_open('School Credentials Presented', 'folder-check', '', 'mb-3') ?>
    <div class="content-card-body">
      <?php
      $submitted = json_decode($reg['credentials_submitted'] ?? '', true) ?: [];
      $presented = json_decode($reg['credentials_presented'] ?? '', true) ?: [];
      ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Document</th>
              <th style="width:140px;">Attached Scan</th>
              <th class="text-center" style="width:130px;">Original<br>Presented</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (credentialCatalog() as $ck => $cred): ?>
            <?php if (!empty($cred['transferee']) && fv('enrollment_type',$reg) !== 'Transferee' && empty($submitted[$ck]) && empty($presented[$ck])) continue; ?>
            <tr>
              <td>
                <div class="small fw-semibold"><?= e($cred['label']) ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($cred['note']) ?></div>
              </td>
              <td>
                <?php if (!empty($submitted[$ck])): ?>
                <?= ui_btn('View', ['size'=>'sm','variant'=>'outline-primary','icon'=>'paperclip','class'=>'doc-view',
                           'attrs'=>'data-url="' . e(UPLOAD_URL . '/' . $submitted[$ck]) . '" data-label="' . e($cred['label']) . '"']) ?>
                <?php else: ?>
                <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <input class="form-check-input" type="checkbox" name="cred_presented[<?= $ck ?>]" value="1"
                       <?= !empty($presented[$ck]) ? 'checked' : '' ?>>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Edit note + submit -->
  <?= ui_card_open('', '', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label" for="f-edit_note">Edit Note <small class="text-muted">(optional — shown in record history)</small></label>
          <input type="text" name="edit_note" id="f-edit_note" class="form-control"
                 placeholder="e.g. Corrected spelling per student request">
        </div>
        <div class="col-md-4 d-flex gap-2">
          <?= ui_btn('Save Changes', ['type'=>'submit','class'=>'flex-fill','icon'=>'check-lg']) ?>
          <?= ui_btn('Cancel', ['variant'=>'outline-secondary',
                     'href'=>BASE_URL . '/' . currentRole() . '/view_registration.php?id=' . (int)$id]) ?>
        </div>
      </div>
    </div>
  <?= ui_card_close() ?>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

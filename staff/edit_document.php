<?php
/**
 * TCC RRS – Staff: Edit Document Request Record
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$id = getInt('id');
if (!$id) { flash('danger','Invalid request.'); redirect(BASE_URL.'/'.currentRole().'/document_requests.php'); }

$req = db()->fetchOne("SELECT * FROM document_requests WHERE id = ?", [$id]);
if (!$req) { flash('danger','Request not found.'); redirect(BASE_URL.'/'.currentRole().'/document_requests.php'); }

$pageTitle   = 'Edit Document Request – ' . $req['tracking_code'];
$breadcrumbs = [
    ['label' => 'Document Requests', 'url' => BASE_URL . '/' . currentRole() . '/document_requests.php'],
    ['label' => e($req['tracking_code'])],
];
$docTypes = getActiveDocumentTypes();
$errors   = [];

if (isPost()) {
    abortCsrf();

    $data = [
        'first_name'          => post('first_name'),
        'middle_name'         => post('middle_name'),
        'last_name'           => post('last_name'),
        'suffix'              => post('suffix'),
        'student_id'          => post('student_id'),
        'email'               => post('email'),
        'contact_number'      => post('contact_number'),
        'address'             => post('address'),
        'document_type_id'    => postInt('document_type_id') ?: null,
        'document_type_other' => post('document_type_other'),
        'copies'              => max(1, postInt('copies', 1)),
        'purpose'             => post('purpose'),
        'date_needed'         => post('date_needed') ?: null,
        'delivery_method'     => post('delivery_method'),
        'program'             => post('program'),
        'year_graduated'      => post('year_graduated'),
        'last_year_attended'  => post('last_year_attended'),
        'additional_info'     => post('additional_info'),
        'edit_note'           => post('edit_note'),
    ];

    if (empty($data['first_name']))         $errors[] = 'First name is required.';
    if (empty($data['last_name']))          $errors[] = 'Last name is required.';
    if (!validateEmail($data['email']))     $errors[] = 'Valid email address is required.';
    if (empty($data['contact_number']))     $errors[] = 'Contact number is required.';
    if (!$data['document_type_id'] && empty($data['document_type_other'])) {
        $errors[] = 'Document type is required.';
    }
    if (empty($data['purpose']))            $errors[] = 'Purpose is required.';
    if (!in_array($data['delivery_method'], ['Pick-up','Email'], true)) $data['delivery_method'] = 'Pick-up';
    if ($data['date_needed'] && !validateDate($data['date_needed'])) $errors[] = 'Invalid date needed.';

    if (empty($errors)) {
        db()->execute(
            "UPDATE document_requests SET
                first_name=?, middle_name=?, last_name=?, suffix=?, student_id=?,
                email=?, contact_number=?, address=?,
                document_type_id=?, document_type_other=?, copies=?, purpose=?,
                date_needed=?, delivery_method=?, program=?, year_graduated=?,
                last_year_attended=?, additional_info=?, updated_at=NOW()
             WHERE id=?",
            [$data['first_name'],$data['middle_name'],$data['last_name'],$data['suffix'],$data['student_id'],
             $data['email'],$data['contact_number'],$data['address'],
             $data['document_type_id'],$data['document_type_other'],$data['copies'],$data['purpose'],
             $data['date_needed'],$data['delivery_method'],$data['program'],$data['year_graduated'],
             $data['last_year_attended'],$data['additional_info'],$id]
        );

        addStatusHistory('document_request', $id, $req['tracking_code'], $req['status'], $req['status'],
            'Record details updated by staff.' . ($data['edit_note'] ? ' — ' . $data['edit_note'] : ''));
        logActivity('RECORD_EDIT', 'Records Management',
            "Edited document request {$req['tracking_code']}", $req['tracking_code']);

        flash('success', 'Record updated successfully.');
        redirect(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . $id);
    }
    $req = array_merge($req, $data);
}

function fv(string $key, array $req) {
    return $_POST[$key] ?? $req[$key] ?? '';
}

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Edit Request — ' . e($req['tracking_code']),
    'Update the request details. Changes are logged.',
    'pencil-square',
    ui_back(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . (int)$id, 'Cancel & Back')
) ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="">
  <?= csrfField() ?>

  <!-- Student info -->
  <?= ui_card_open('Student Information', 'person', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <?= ui_field(['name'=>'first_name','label'=>'First Name','value'=>fv('first_name',$req),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'middle_name','label'=>'Middle Name','value'=>fv('middle_name',$req),'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'last_name','label'=>'Last Name','value'=>fv('last_name',$req),'required'=>true,'col'=>'col-md-3']) ?>
        <?= ui_field(['name'=>'suffix','label'=>'Suffix','value'=>fv('suffix',$req),'col'=>'col-md-1']) ?>
        <?= ui_field(['name'=>'student_id','label'=>'Student ID','value'=>fv('student_id',$req),'col'=>'col-md-2']) ?>
        <?= ui_field(['type'=>'email','name'=>'email','label'=>'Email','value'=>fv('email',$req),'required'=>true,'col'=>'col-md-6']) ?>
        <?= ui_field(['name'=>'contact_number','label'=>'Contact Number','value'=>fv('contact_number',$req),'required'=>true,'col'=>'col-md-6']) ?>
        <?= ui_field(['type'=>'textarea','name'=>'address','label'=>'Address','value'=>fv('address',$req),'rows'=>2,'col'=>'col-12']) ?>
        <?= ui_field(['name'=>'program','label'=>'Program','value'=>fv('program',$req),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'year_graduated','label'=>'Year Graduated','value'=>fv('year_graduated',$req),'col'=>'col-md-4']) ?>
        <?= ui_field(['name'=>'last_year_attended','label'=>'Last Year Attended','value'=>fv('last_year_attended',$req),'col'=>'col-md-4']) ?>
      </div>
    </div>
  <?= ui_card_close() ?>

  <!-- Document details -->
  <?= ui_card_open('Document Details', 'file-earmark-text', '', 'mb-3') ?>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="f-document_type_id">Document Type <span class="required-mark">*</span></label>
          <select name="document_type_id" id="f-document_type_id" class="form-select">
            <option value="">— Select document —</option>
            <?php foreach ($docTypes as $dt): ?>
            <option value="<?= (int)$dt['id'] ?>" <?= (int)fv('document_type_id',$req)===(int)$dt['id']?'selected':'' ?>>
              <?= e($dt['name']) ?> (<?= formatCurrency((float)$dt['fee']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?= ui_field(['name'=>'document_type_other','label'=>'Other Document (if not listed)','value'=>fv('document_type_other',$req),'col'=>'col-md-6']) ?>
        <?= ui_field(['type'=>'number','name'=>'copies','label'=>'Copies','value'=>fv('copies',$req),'col'=>'col-md-2','attrs'=>'min="1" max="10"']) ?>
        <?= ui_field(['name'=>'purpose','label'=>'Purpose','value'=>fv('purpose',$req),'required'=>true,'col'=>'col-md-4']) ?>
        <?= ui_field(['type'=>'date','name'=>'date_needed','label'=>'Date Needed','value'=>fv('date_needed',$req),'col'=>'col-md-3']) ?>
        <?= ui_field(['type'=>'select','name'=>'delivery_method','label'=>'Delivery Method','value'=>fv('delivery_method',$req),'col'=>'col-md-3','options'=>array_combine(['Pick-up','Email'],['Pick-up','Email'])]) ?>
        <?= ui_field(['type'=>'textarea','name'=>'additional_info','label'=>'Additional Info','value'=>fv('additional_info',$req),'rows'=>2,'col'=>'col-12']) ?>
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
                 placeholder="e.g. Updated per student request via phone call">
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-check-lg me-1"></i>Save Changes
          </button>
          <a href="<?= BASE_URL ?>/<?= currentRole() ?>/view_document.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>
      </div>
    </div>
  <?= ui_card_close() ?>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

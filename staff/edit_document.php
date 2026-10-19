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
if (!$id) { flash('danger','Invalid request.'); redirect(BASE_URL.'/staff/document_requests.php'); }

$req = db()->fetchOne("SELECT * FROM document_requests WHERE id = ?", [$id]);
if (!$req) { flash('danger','Request not found.'); redirect(BASE_URL.'/staff/document_requests.php'); }

$pageTitle   = 'Edit Document Request – ' . $req['tracking_code'];
$breadcrumbs = [
    ['label' => 'Document Requests', 'url' => BASE_URL . '/staff/document_requests.php'],
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
        redirect(BASE_URL . '/staff/view_document.php?id=' . $id);
    }
    $req = array_merge($req, $data);
}

function f(string $key, array $req): string {
    return e($_POST[$key] ?? $req[$key] ?? '');
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
    <h4><i class="bi bi-pencil-square me-2 text-success"></i>Edit Request – <?= e($req['tracking_code']) ?></h4>
    <p>Update the request details. Changes are logged.</p>
  </div>
  <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$id ?>" class="btn-back">
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

  <!-- Student info -->
  <div class="content-card mb-3">
    <div class="content-card-header"><h5><i class="bi bi-person me-2"></i>Student Information</h5></div>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">First Name <span class="required-mark">*</span></label>
          <input type="text" name="first_name" class="form-control" value="<?= f('first_name',$req) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control" value="<?= f('middle_name',$req) ?>"></div>
        <div class="col-md-3"><label class="form-label">Last Name <span class="required-mark">*</span></label>
          <input type="text" name="last_name" class="form-control" value="<?= f('last_name',$req) ?>" required></div>
        <div class="col-md-1"><label class="form-label">Suffix</label>
          <input type="text" name="suffix" class="form-control" value="<?= f('suffix',$req) ?>"></div>
        <div class="col-md-2"><label class="form-label">Student ID</label>
          <input type="text" name="student_id" class="form-control" value="<?= f('student_id',$req) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email <span class="required-mark">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= f('email',$req) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Contact Number <span class="required-mark">*</span></label>
          <input type="text" name="contact_number" class="form-control" value="<?= f('contact_number',$req) ?>" required></div>
        <div class="col-12"><label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= f('address',$req) ?></textarea></div>
        <div class="col-md-4"><label class="form-label">Program</label>
          <input type="text" name="program" class="form-control" value="<?= f('program',$req) ?>"></div>
        <div class="col-md-4"><label class="form-label">Year Graduated</label>
          <input type="text" name="year_graduated" class="form-control" value="<?= f('year_graduated',$req) ?>"></div>
        <div class="col-md-4"><label class="form-label">Last Year Attended</label>
          <input type="text" name="last_year_attended" class="form-control" value="<?= f('last_year_attended',$req) ?>"></div>
      </div>
    </div>
  </div>

  <!-- Document details -->
  <div class="content-card mb-3">
    <div class="content-card-header"><h5><i class="bi bi-file-earmark-text me-2"></i>Document Details</h5></div>
    <div class="content-card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Document Type <span class="required-mark">*</span></label>
          <select name="document_type_id" class="form-select">
            <option value="">— Select document —</option>
            <?php foreach ($docTypes as $dt): ?>
            <option value="<?= (int)$dt['id'] ?>" <?= (int)f('document_type_id',$req)===(int)$dt['id']?'selected':'' ?>>
              <?= e($dt['name']) ?> (<?= formatCurrency((float)$dt['fee']) ?>)
            </option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-6"><label class="form-label">Other Document <small class="text-muted">(if not listed)</small></label>
          <input type="text" name="document_type_other" class="form-control" value="<?= f('document_type_other',$req) ?>"></div>
        <div class="col-md-2"><label class="form-label">Copies</label>
          <input type="number" name="copies" class="form-control" min="1" max="10" value="<?= f('copies',$req) ?>"></div>
        <div class="col-md-4"><label class="form-label">Purpose <span class="required-mark">*</span></label>
          <input type="text" name="purpose" class="form-control" value="<?= f('purpose',$req) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Date Needed</label>
          <input type="date" name="date_needed" class="form-control" value="<?= f('date_needed',$req) ?>"></div>
        <div class="col-md-3"><label class="form-label">Delivery Method</label>
          <select name="delivery_method" class="form-select">
            <?php foreach (['Pick-up','Email'] as $dm): ?>
            <option value="<?= e($dm) ?>" <?= f('delivery_method',$req)===$dm?'selected':'' ?>><?= e($dm) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-12"><label class="form-label">Additional Info</label>
          <textarea name="additional_info" class="form-control" rows="2"><?= f('additional_info',$req) ?></textarea></div>
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
                 placeholder="e.g. Updated per student request via phone call">
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-success flex-fill">
            <i class="bi bi-check-lg me-1"></i>Save Changes
          </button>
          <a href="<?= BASE_URL ?>/staff/view_document.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>
      </div>
    </div>
  </div>
</form>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

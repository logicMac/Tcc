<?php
/**
 * TCC RRS – Student Document Pre-Request
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
startSession();

$schoolName   = getSetting('school_name', 'Tampakan Community College');
$docTypes     = getActiveDocumentTypes();
$activePage   = 'document';
$errors       = [];
$submitted    = false;
$trackingCode = '';
$reqData      = [];
$student      = currentStudent();

// Prefill from the student's account when signed in
$pre = [
    'first_name'     => $student['first_name']     ?? '',
    'middle_name'    => $student['middle_name']    ?? '',
    'last_name'      => $student['last_name']      ?? '',
    'suffix'         => $student['suffix']         ?? '',
    'student_id'     => $student['student_no']     ?? '',
    'email'          => $student['email']          ?? '',
    'contact_number' => $student['contact_number'] ?? '',
];

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
    ];

    if (empty($data['first_name']))         $errors[] = 'First name is required.';
    if (empty($data['last_name']))          $errors[] = 'Last name is required.';
    if (empty($data['email']))              $errors[] = 'Email address is required.';
    elseif (!validateEmail($data['email']))  $errors[] = 'Please enter a valid email address.';
    if (empty($data['contact_number']))     $errors[] = 'Contact number is required.';
    elseif (!validatePhone($data['contact_number'])) $errors[] = 'Invalid contact number format.';
    if (!$data['document_type_id'] && empty($data['document_type_other'])) {
        $errors[] = 'Please select or specify the document type.';
    }
    if (empty($data['purpose']))            $errors[] = 'Purpose of request is required.';
    if (!in_array($data['delivery_method'], ['Pick-up','Email'])) $data['delivery_method'] = 'Pick-up';
    if ($data['date_needed'] && !validateDate($data['date_needed'])) $errors[] = 'Invalid date needed.';

    if (empty($errors)) {
        db()->beginTransaction();
        try {
            $trackingCode = generateTrackingCode(TRACKING_DOC_PREFIX);
            db()->insert(
                "INSERT INTO document_requests
                    (tracking_code,first_name,middle_name,last_name,suffix,
                     student_id,email,contact_number,address,
                     document_type_id,document_type_other,copies,purpose,
                     date_needed,delivery_method,program,year_graduated,
                     last_year_attended,additional_info,status,ip_address)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$trackingCode,$data['first_name'],$data['middle_name'],$data['last_name'],
                 $data['suffix'],$data['student_id'],$data['email'],$data['contact_number'],
                 $data['address'],$data['document_type_id'],$data['document_type_other'],
                 $data['copies'],$data['purpose'],$data['date_needed'],$data['delivery_method'],
                 $data['program'],$data['year_graduated'],$data['last_year_attended'],
                 $data['additional_info'],'Pending',getClientIp()]
            );
            $reqData = db()->fetchOne(
                "SELECT dr.*, dt.name AS doc_type_name
                 FROM document_requests dr
                 LEFT JOIN document_types dt ON dt.id = dr.document_type_id
                 WHERE dr.tracking_code = ?", [$trackingCode]
            );
            linkSubmissionToStudent('document_requests', (int)$reqData['id'], currentStudentId());
            addStatusHistory('document_request',(int)$reqData['id'],$trackingCode,null,'Pending',
                'Document request submitted by student.');
            db()->commit();
            notifyDocumentRequestReceived($reqData);
            $submitted = true;
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'An error occurred. Please try again.';
            if (APP_DEBUG) $errors[] = $e->getMessage();
        }
    }
}

$trackingUrl = $submitted ? trackingUrl($trackingCode) : '';
$docTypeName = '';
if ($submitted) {
    $docTypeName = $reqData['document_type_other'] ?: ($reqData['doc_type_name'] ?? 'Document');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Document Request – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body style="background:var(--bg-app);">

<?php include __DIR__ . '/../includes/partials/portal_nav.php'; ?>

<!-- breadcrumb bar -->
<div style="background:#fff;border-bottom:1px solid #e2e8f0;">
  <div class="container py-2 d-flex align-items-center gap-2" style="font-size:.8rem;color:#64748b;">
    <a href="<?= BASE_URL ?>/student/index.php" class="text-muted text-decoration-none">
      <i class="bi bi-house me-1"></i>Home
    </a>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <span class="fw-semibold text-dark">Request a Document</span>
  </div>
</div>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

<?php if ($submitted): ?>
<!-- ════ SUCCESS ════ -->
<div class="card border-0 shadow" style="border-radius:16px;overflow:hidden;">
  <div class="text-center p-4 text-white"
       style="background:linear-gradient(135deg,#065f46,#059669);">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white mb-3"
         style="width:72px;height:72px;">
      <i class="bi bi-check-lg text-success fs-1"></i>
    </div>
    <h3 class="fw-bold mb-1">Document Request Submitted!</h3>
    <p class="opacity-75 mb-0">Your request has been received by the Registrar's Office.</p>
  </div>
  <div class="card-body p-4">
    <div class="row g-4 align-items-center">
      <div class="col-md-7">
        <h5 class="fw-bold mb-3">Your Tracking Information</h5>
        <div class="tracking-code-display mb-3">
          <div class="small opacity-75 mb-1">Tracking Code</div>
          <div class="code"><?= e($trackingCode) ?></div>
          <div class="small opacity-75 mt-2">
            <?= e(fullName($reqData)) ?> &middot; <?= e($docTypeName) ?>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-sm btn-outline-secondary"
                  onclick="copyToClipboard('<?= e($trackingCode) ?>',this)">
            <i class="bi bi-clipboard me-1"></i>Copy Code
          </button>
          <a href="<?= e($trackingUrl) ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-eye me-1"></i>View Status
          </a>
          <button class="btn btn-sm btn-outline-info"
                  onclick="downloadQr('qr-success','<?= e($trackingCode) ?>.png')">
            <i class="bi bi-download me-1"></i>Download QR
          </button>
          <button class="btn btn-sm btn-outline-dark"
                  onclick="printSection('qr-success')">
            <i class="bi bi-printer me-1"></i>Print QR
          </button>
        </div>
        <div class="alert alert-info d-flex gap-2 py-2 mb-0">
          <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
          <div class="small">Confirmation sent to <strong><?= e($reqData['email']) ?></strong>.
            Use your tracking code to monitor without an account.</div>
        </div>
      </div>
      <div class="col-md-5 text-center">
        <div class="qr-container d-inline-block" id="qr-success">
          <div id="qrcode-doc-out"></div>
          <div class="small text-muted mt-2 fw-semibold"><?= e($trackingCode) ?></div>
          <div class="text-muted" style="font-size:.68rem;"><?= e($schoolName) ?></div>
        </div>
      </div>
    </div>
    <div class="row g-2 mt-3 p-3 rounded-3 border bg-light">
      <div class="col-sm-3 text-center border-end">
        <div class="text-muted" style="font-size:.7rem;">DOCUMENT</div>
        <div class="fw-semibold small"><?= e($docTypeName) ?></div>
      </div>
      <div class="col-sm-3 text-center border-end">
        <div class="text-muted" style="font-size:.7rem;">COPIES</div>
        <div class="fw-semibold small"><?= (int)$reqData['copies'] ?></div>
      </div>
      <div class="col-sm-3 text-center border-end">
        <div class="text-muted" style="font-size:.7rem;">STATUS</div>
        <div><?= statusBadge('Pending','document_request') ?></div>
      </div>
      <div class="col-sm-3 text-center">
        <div class="text-muted" style="font-size:.7rem;">SUBMITTED</div>
        <div class="fw-semibold small"><?= formatDateTime($reqData['submitted_at']) ?></div>
      </div>
    </div>
    <div class="text-center mt-4 d-flex gap-2 justify-content-center">
      <a href="<?= BASE_URL ?>/student/document_request.php" class="btn btn-outline-secondary">
        <i class="bi bi-plus me-1"></i>New Request
      </a>
      <a href="<?= BASE_URL ?>/student/index.php" class="btn btn-success">
        <i class="bi bi-house me-1"></i>Back to Portal
      </a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>document.addEventListener('DOMContentLoaded',()=>generateQrCode('qrcode-doc-out','<?= e($trackingUrl) ?>'));</script>
</body></html>
<?php exit; endif; ?>

<!-- ════ FORM ════ -->
<div class="d-flex align-items-center gap-3 mb-4">
  <div class="d-inline-flex align-items-center justify-content-center rounded-3"
       style="width:48px;height:48px;background:#dcfce7;flex-shrink:0;">
    <i class="bi bi-file-earmark-text-fill text-success fs-5"></i>
  </div>
  <div>
    <h3 class="fw-bold mb-0">Request a Registrar Document</h3>
    <p class="text-muted mb-0 small">Fields marked <span class="text-danger fw-bold">*</span> are required.</p>
  </div>
  <a href="<?= BASE_URL ?>/student/index.php" class="btn-back ms-auto">
    <i class="bi bi-arrow-left"></i> Back to Portal
  </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger d-flex gap-2 mb-4">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-5"></i>
  <div>
    <strong class="d-block mb-1">Please fix the following errors:</strong>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="" id="doc-form" novalidate>
  <?= csrfField() ?>

  <!-- Student Information -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#059669;flex-shrink:0;">1</span>
        Student Information
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">First Name <span class="required-mark">*</span></label>
          <input type="text" name="first_name" class="form-control"
                 value="<?= e(post('first_name', $pre['first_name'])) ?>" required maxlength="100">
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control"
                 value="<?= e(post('middle_name', $pre['middle_name'])) ?>" maxlength="100">
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name <span class="required-mark">*</span></label>
          <input type="text" name="last_name" class="form-control"
                 value="<?= e(post('last_name', $pre['last_name'])) ?>" required maxlength="100">
        </div>
        <div class="col-md-1">
          <label class="form-label">Suffix</label>
          <input type="text" name="suffix" class="form-control"
                 value="<?= e(post('suffix', $pre['suffix'])) ?>" maxlength="20">
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label">Student ID Number</label>
          <input type="text" name="student_id" class="form-control"
                 value="<?= e(post('student_id', $pre['student_id'])) ?>" maxlength="50"
                 placeholder="Leave blank if not yet enrolled">
        </div>
        <div class="col-md-4">
          <label class="form-label">Email Address <span class="required-mark">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control"
                   value="<?= e(post('email', $pre['email'])) ?>" required maxlength="150">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Number <span class="required-mark">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-phone text-muted"></i></span>
            <input type="tel" name="contact_number" class="form-control"
                   value="<?= e(post('contact_number', $pre['contact_number'])) ?>" required maxlength="20">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Complete Address</label>
          <textarea name="address" class="form-control" rows="2" maxlength="500"><?= e(post('address')) ?></textarea>
        </div>
        <div class="col-md-3">
          <label class="form-label">Program / Course</label>
          <input type="text" name="program" class="form-control"
                 value="<?= e(post('program')) ?>" maxlength="200">
        </div>
        <div class="col-md-3">
          <label class="form-label">Year Graduated <small class="text-muted">(or last attended)</small></label>
          <input type="text" name="year_graduated" class="form-control"
                 value="<?= e(post('year_graduated')) ?>" maxlength="10" placeholder="e.g. 2025">
        </div>
      </div>
    </div>
  </div>

  <!-- Document Details -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#059669;flex-shrink:0;">2</span>
        Document Details
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <label class="form-label fw-semibold">Select Document Type <span class="required-mark">*</span></label>
      <div class="row g-2 mb-3" id="doc-type-grid">
        <?php foreach ($docTypes as $dt): ?>
        <div class="col-sm-6 col-md-4">
          <label class="d-block h-100" style="cursor:pointer;">
            <input type="radio" name="document_type_id" value="<?= (int)$dt['id'] ?>"
                   class="doc-type-radio visually-hidden"
                   data-code="<?= e($dt['code']) ?>"
                   <?= postInt('document_type_id')===(int)$dt['id']?'checked':'' ?>>
            <div class="doc-type-card h-100 border rounded-3 p-3 text-center"
                 style="transition:all .2s;font-size:.82rem;">
              <div class="fw-semibold mb-1"><?= e($dt['name']) ?></div>
              <div class="text-muted" style="font-size:.7rem;">
                Fee: <?= formatCurrency((float)$dt['fee']) ?> &middot; ~<?= (int)$dt['processing_days'] ?>d
              </div>
            </div>
          </label>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3 mb-1" id="other-doc-wrap" style="display:none;">
        <div class="col-md-8">
          <label class="form-label">Specify Document <span class="required-mark">*</span></label>
          <input type="text" name="document_type_other" class="form-control"
                 value="<?= e(post('document_type_other')) ?>" maxlength="200"
                 placeholder="Describe the document you need">
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-2">
          <label class="form-label">Copies</label>
          <input type="number" name="copies" class="form-control"
                 value="<?= e(post('copies','1')) ?>" min="1" max="10">
        </div>
        <div class="col-md-5">
          <label class="form-label">Purpose <span class="required-mark">*</span></label>
          <input type="text" name="purpose" class="form-control"
                 value="<?= e(post('purpose')) ?>" required maxlength="500"
                 placeholder="Employment, Scholarship, Transfer, etc.">
        </div>
        <div class="col-md-2">
          <label class="form-label">Date Needed</label>
          <input type="date" name="date_needed" class="form-control"
                 value="<?= e(post('date_needed')) ?>"
                 min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Delivery Method</label>
          <select name="delivery_method" class="form-select">
            <option value="Pick-up" <?= post('delivery_method','Pick-up')==='Pick-up'?'selected':'' ?>>
              <i class="bi bi-building"></i> Pick-up at Office
            </option>
            <option value="Email" <?= post('delivery_method')==='Email'?'selected':'' ?>>
              Send via Email
            </option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Additional Information</label>
          <textarea name="additional_info" class="form-control" rows="2"
                    maxlength="1000" data-max-chars="1000"
                    placeholder="Any additional details or special instructions..."><?= e(post('additional_info')) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Submit -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body p-4">
      <div class="alert alert-info d-flex gap-2 mb-3">
        <i class="bi bi-info-circle-fill flex-shrink-0"></i>
        <div class="small">
          A confirmation email will be sent to your email address with your Tracking Code and QR Code.
          Present your QR Code or Tracking Code at the Registrar's Office when your document is ready.
        </div>
      </div>
      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="agreePrivacy" required>
        <label class="form-check-label small" for="agreePrivacy">
          I confirm that all information provided is accurate. I understand that providing false
          information may result in rejection of my request. <span class="required-mark">*</span>
        </label>
      </div>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <a href="<?= BASE_URL ?>/student/index.php" class="btn-back">
          <i class="bi bi-arrow-left"></i> Back to Portal
        </a>
        <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
          <i class="bi bi-send me-2"></i>Submit Document Request
        </button>
      </div>
    </div>
  </div>

</form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Document type card highlight
document.querySelectorAll('.doc-type-radio').forEach(function(radio){
  radio.addEventListener('change',function(){
    document.querySelectorAll('.doc-type-card').forEach(c=>{
      c.style.background='';c.style.borderColor='';c.style.color='';
    });
    if(this.checked){
      const card=this.nextElementSibling;
      card.style.background='#dcfce7';card.style.borderColor='#059669';card.style.color='#065f46';
      document.getElementById('other-doc-wrap').style.display=
        this.dataset.code==='OTHER'?'block':'none';
    }
  });
  if(radio.checked){
    radio.nextElementSibling.style.background='#dcfce7';
    radio.nextElementSibling.style.borderColor='#059669';
    radio.nextElementSibling.style.color='#065f46';
    if(radio.dataset.code==='OTHER') document.getElementById('other-doc-wrap').style.display='block';
  }
});

document.getElementById('doc-form').addEventListener('submit',function(e){
  if(!document.getElementById('agreePrivacy').checked){
    e.preventDefault(); alert('Please confirm the accuracy of your information.'); return;
  }
  if(!document.querySelector('.doc-type-radio:checked')){
    e.preventDefault(); alert('Please select a document type.'); return;
  }
  var btn=document.getElementById('submitBtn');
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
});
</script>
</body>
</html>

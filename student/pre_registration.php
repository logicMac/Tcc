<?php
/**
 * TCC RRS – Student Pre-Registration for Enrollment
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
startSession();

$schoolName   = getSetting('school_name', 'Tampakan Community College');
$programs     = getActivePrograms();
$activePage   = 'register';
$errors       = [];
$submitted    = false;
$trackingCode = '';
$regData      = [];

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
        'school_year'       => getSetting('current_school_year','2026-2027'),
        'semester'          => post('semester'),
        'previous_school'   => post('previous_school'),
        'previous_program'  => post('previous_program'),
        'last_year_attended'=> post('last_year_attended'),
        'lrn'               => post('lrn'),
        'guardian_name'     => post('guardian_name'),
        'guardian_relation' => post('guardian_relation'),
        'guardian_contact'  => post('guardian_contact'),
    ];

    if (empty($data['first_name']))        $errors[] = 'First name is required.';
    if (empty($data['last_name']))         $errors[] = 'Last name is required.';
    if (empty($data['date_of_birth']))     $errors[] = 'Date of birth is required.';
    elseif (!validateDate($data['date_of_birth'])) $errors[] = 'Invalid date of birth.';
    if (empty($data['gender']))            $errors[] = 'Gender is required.';
    if (empty($data['email']))             $errors[] = 'Email address is required.';
    elseif (!validateEmail($data['email'])) $errors[] = 'Please enter a valid email address.';
    if (empty($data['contact_number']))    $errors[] = 'Contact number is required.';
    elseif (!validatePhone($data['contact_number'])) $errors[] = 'Invalid contact number format.';
    if (empty($data['house_no_street']))   $errors[] = 'Street / house number is required.';
    if (empty($data['barangay']))          $errors[] = 'Barangay is required.';
    if (empty($data['municipality_city'])) $errors[] = 'Municipality / City is required.';
    if (empty($data['province']))          $errors[] = 'Province is required.';
    if (empty($data['year_level']))        $errors[] = 'Year level is required.';
    if (empty($data['enrollment_type']))   $errors[] = 'Enrollment type is required.';
    if (empty($data['semester']))          $errors[] = 'Semester is required.';
    if (!$data['program_id'] && empty($data['program_other'])) {
        $errors[] = 'Program / Course is required.';
    }

    if (empty($errors)) {
        db()->beginTransaction();
        try {
            $trackingCode = generateTrackingCode(TRACKING_REG_PREFIX);
            db()->insert(
                "INSERT INTO pre_registrations
                    (tracking_code,first_name,middle_name,last_name,suffix,
                     date_of_birth,gender,civil_status,nationality,religion,
                     email,contact_number,house_no_street,barangay,municipality_city,
                     province,zip_code,program_id,program_other,year_level,
                     enrollment_type,school_year,semester,previous_school,
                     previous_program,last_year_attended,lrn,
                     guardian_name,guardian_relation,guardian_contact,
                     status,ip_address)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$trackingCode,$data['first_name'],$data['middle_name'],$data['last_name'],
                 $data['suffix'],$data['date_of_birth'],$data['gender'],$data['civil_status'],
                 $data['nationality'],$data['religion'],$data['email'],$data['contact_number'],
                 $data['house_no_street'],$data['barangay'],$data['municipality_city'],
                 $data['province'],$data['zip_code'],$data['program_id'],$data['program_other'],
                 $data['year_level'],$data['enrollment_type'],$data['school_year'],
                 $data['semester'],$data['previous_school'],$data['previous_program'],
                 $data['last_year_attended'],$data['lrn'],$data['guardian_name'],
                 $data['guardian_relation'],$data['guardian_contact'],'Pending',getClientIp()]
            );
            $regData = db()->fetchOne(
                "SELECT * FROM pre_registrations WHERE tracking_code = ?", [$trackingCode]
            );
            addStatusHistory('registration',(int)$regData['id'],$trackingCode,null,'Pending',
                'Pre-registration submitted by student.');
            db()->commit();
            notifyRegistrationReceived($regData);
            $submitted = true;
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'An error occurred while saving your registration. Please try again.';
            if (APP_DEBUG) $errors[] = $e->getMessage();
        }
    }
}

$trackingUrl = $submitted ? trackingUrl($trackingCode) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pre-Registration – <?= e($schoolName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body style="background:var(--bg-app);">

<?php include __DIR__ . '/../includes/partials/portal_nav.php'; ?>

<!-- ── Page breadcrumb bar ── -->
<div style="background:#fff;border-bottom:1px solid #e2e8f0;">
  <div class="container py-2 d-flex align-items-center gap-2" style="font-size:.8rem;color:#64748b;">
    <a href="<?= BASE_URL ?>/student/index.php" class="text-muted text-decoration-none">
      <i class="bi bi-house me-1"></i>Home
    </a>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <span class="fw-semibold text-dark">Pre-Registration for Enrollment</span>
  </div>
</div>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">

<?php if ($submitted): ?>
<!-- ════ SUCCESS ════ -->
<div class="card border-0 shadow" style="border-radius:16px;overflow:hidden;">
  <div class="text-center p-4 text-white"
       style="background:linear-gradient(135deg,#0f2a56,#1a4a8a);">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success mb-3"
         style="width:72px;height:72px;">
      <i class="bi bi-check-lg fs-1"></i>
    </div>
    <h3 class="fw-bold mb-1">Pre-Registration Submitted!</h3>
    <p class="opacity-75 mb-0">Your enrollment pre-registration has been received successfully.</p>
  </div>
  <div class="card-body p-4">
    <div class="row g-4 align-items-center">
      <div class="col-md-7">
        <h5 class="fw-bold mb-3">Your Tracking Information</h5>
        <div class="tracking-code-display mb-3">
          <div class="small opacity-75 mb-1">Tracking Code</div>
          <div class="code"><?= e($trackingCode) ?></div>
          <div class="small opacity-75 mt-2">
            <?= e(fullName($regData)) ?> &middot;
            <?= e(getSetting('current_school_year')) ?> &middot;
            <?= e($regData['semester']) ?>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-sm btn-outline-secondary"
                  onclick="copyToClipboard('<?= e($trackingCode) ?>',this)">
            <i class="bi bi-clipboard me-1"></i>Copy Code
          </button>
          <a href="<?= e($trackingUrl) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye me-1"></i>View Status
          </a>
          <button class="btn btn-sm btn-outline-success"
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
          <div class="small">
            Save your tracking code. A confirmation has been sent to
            <strong><?= e($regData['email']) ?></strong>.
            No account needed to track your status.
          </div>
        </div>
      </div>
      <div class="col-md-5 text-center">
        <div class="qr-container d-inline-block" id="qr-success">
          <div id="qrcode-output"></div>
          <div class="small text-muted mt-2 fw-semibold"><?= e($trackingCode) ?></div>
          <div class="text-muted" style="font-size:.68rem;"><?= e($schoolName) ?></div>
        </div>
      </div>
    </div>
    <!-- Summary strip -->
    <div class="row g-2 mt-3 p-3 rounded-3 border bg-light">
      <div class="col-sm-4 text-center border-end">
        <div class="text-muted" style="font-size:.7rem;">NAME</div>
        <div class="fw-semibold small"><?= e(fullName($regData)) ?></div>
      </div>
      <div class="col-sm-4 text-center border-end">
        <div class="text-muted" style="font-size:.7rem;">STATUS</div>
        <div><?= statusBadge('Pending') ?></div>
      </div>
      <div class="col-sm-4 text-center">
        <div class="text-muted" style="font-size:.7rem;">SUBMITTED</div>
        <div class="fw-semibold small"><?= formatDateTime($regData['submitted_at']) ?></div>
      </div>
    </div>
    <div class="text-center mt-4 d-flex gap-2 justify-content-center">
      <a href="<?= BASE_URL ?>/student/pre_registration.php" class="btn btn-outline-secondary">
        <i class="bi bi-plus me-1"></i>New Registration
      </a>
      <a href="<?= BASE_URL ?>/student/index.php" class="btn btn-primary">
        <i class="bi bi-house me-1"></i>Back to Portal
      </a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>document.addEventListener('DOMContentLoaded',()=>generateQrCode('qrcode-output','<?= e($trackingUrl) ?>'));</script>
</body></html>
<?php exit; endif; ?>

<!-- ════ FORM ════ -->
<!-- Page heading -->
<div class="d-flex align-items-center gap-3 mb-4">
  <div class="d-inline-flex align-items-center justify-content-center rounded-3"
       style="width:48px;height:48px;background:#dbeafe;flex-shrink:0;">
    <i class="bi bi-person-plus-fill text-primary fs-5"></i>
  </div>
  <div>
    <h3 class="fw-bold mb-0">Pre-Registration for Enrollment</h3>
    <p class="text-muted mb-0 small">Fill out all required fields marked with
      <span class="text-danger fw-bold">*</span>
    </p>
  </div>
  <a href="<?= BASE_URL ?>/student/index.php" class="btn-back ms-auto">
    <i class="bi bi-arrow-left"></i> Back to Portal
  </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger d-flex gap-2 mb-4" id="error-box">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-5"></i>
  <div>
    <strong class="d-block mb-1">Please fix the following errors:</strong>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="" id="reg-form" novalidate>
  <?= csrfField() ?>

  <!-- ── Section 1: Personal Information ── -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#1a4a8a;flex-shrink:0;">1</span>
        Personal Information
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">First Name <span class="required-mark">*</span></label>
          <input type="text" name="first_name" class="form-control"
                 value="<?= e(post('first_name')) ?>" required maxlength="100"
                 placeholder="Juan">
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control"
                 value="<?= e(post('middle_name')) ?>" maxlength="100" placeholder="(optional)">
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name <span class="required-mark">*</span></label>
          <input type="text" name="last_name" class="form-control"
                 value="<?= e(post('last_name')) ?>" required maxlength="100"
                 placeholder="Dela Cruz">
        </div>
        <div class="col-md-1">
          <label class="form-label">Suffix</label>
          <input type="text" name="suffix" class="form-control"
                 value="<?= e(post('suffix')) ?>" maxlength="20" placeholder="Jr.">
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-3">
          <label class="form-label">Date of Birth <span class="required-mark">*</span></label>
          <input type="date" name="date_of_birth" class="form-control"
                 value="<?= e(post('date_of_birth')) ?>"
                 max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Gender <span class="required-mark">*</span></label>
          <select name="gender" class="form-select" required>
            <option value="">Select…</option>
            <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?= e($g) ?>" <?= post('gender')===$g?'selected':'' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Civil Status</label>
          <select name="civil_status" class="form-select">
            <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
            <option value="<?= e($cs) ?>" <?= post('civil_status','Single')===$cs?'selected':'' ?>><?= e($cs) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nationality</label>
          <input type="text" name="nationality" class="form-control"
                 value="<?= e(post('nationality','Filipino')) ?>" maxlength="80">
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label">Religion</label>
          <input type="text" name="religion" class="form-control"
                 value="<?= e(post('religion')) ?>" maxlength="80">
        </div>
        <div class="col-md-4">
          <label class="form-label">LRN
            <span class="text-muted fw-normal small">(Learner Reference Number)</span>
          </label>
          <input type="text" name="lrn" class="form-control"
                 value="<?= e(post('lrn')) ?>" maxlength="30"
                 placeholder="12-digit LRN (if applicable)">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Section 2: Contact & Address ── -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#1a4a8a;flex-shrink:0;">2</span>
        Contact Information &amp; Address
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Email Address <span class="required-mark">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control"
                   value="<?= e(post('email')) ?>" required maxlength="150"
                   placeholder="you@email.com">
          </div>
          <div class="form-text">Status updates will be sent here.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Number <span class="required-mark">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-phone text-muted"></i></span>
            <input type="tel" name="contact_number" class="form-control"
                   value="<?= e(post('contact_number')) ?>" required maxlength="20"
                   placeholder="09XX-XXX-XXXX">
          </div>
        </div>
      </div>
      <div class="form-section-title mt-3">Present Address</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">House No. / Street <span class="required-mark">*</span></label>
          <input type="text" name="house_no_street" class="form-control"
                 value="<?= e(post('house_no_street')) ?>" required maxlength="200">
        </div>
        <div class="col-md-6">
          <label class="form-label">Barangay <span class="required-mark">*</span></label>
          <input type="text" name="barangay" class="form-control"
                 value="<?= e(post('barangay')) ?>" required maxlength="100">
        </div>
        <div class="col-md-5">
          <label class="form-label">Municipality / City <span class="required-mark">*</span></label>
          <input type="text" name="municipality_city" class="form-control"
                 value="<?= e(post('municipality_city')) ?>" required maxlength="100">
        </div>
        <div class="col-md-5">
          <label class="form-label">Province <span class="required-mark">*</span></label>
          <input type="text" name="province" class="form-control"
                 value="<?= e(post('province')) ?>" required maxlength="100">
        </div>
        <div class="col-md-2">
          <label class="form-label">ZIP Code</label>
          <input type="text" name="zip_code" class="form-control"
                 value="<?= e(post('zip_code')) ?>" maxlength="10">
        </div>
      </div>
      <div class="form-section-title mt-3">Parent / Guardian Information</div>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Guardian Name</label>
          <input type="text" name="guardian_name" class="form-control"
                 value="<?= e(post('guardian_name')) ?>" maxlength="150">
        </div>
        <div class="col-md-3">
          <label class="form-label">Relationship</label>
          <input type="text" name="guardian_relation" class="form-control"
                 value="<?= e(post('guardian_relation')) ?>" maxlength="80" placeholder="Mother">
        </div>
        <div class="col-md-4">
          <label class="form-label">Guardian Contact</label>
          <input type="tel" name="guardian_contact" class="form-control"
                 value="<?= e(post('guardian_contact')) ?>" maxlength="20">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Section 3: Academic Information ── -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#1a4a8a;flex-shrink:0;">3</span>
        Academic Information
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Program / Course <span class="required-mark">*</span></label>
          <select name="program_id" class="form-select" id="program_select">
            <option value="">Select Program…</option>
            <?php foreach ($programs as $prog): ?>
            <option value="<?= (int)$prog['id'] ?>"
                    <?= postInt('program_id')===(int)$prog['id']?'selected':'' ?>>
              <?= e($prog['code']) ?> – <?= e($prog['name']) ?>
            </option>
            <?php endforeach; ?>
            <option value="0" <?= postInt('program_id')===0&&post('program_other')?'selected':'' ?>>
              Other (specify below)
            </option>
          </select>
        </div>
        <div class="col-md-6" id="program_other_wrap" style="display:none;">
          <label class="form-label">Specify Program <span class="required-mark">*</span></label>
          <input type="text" name="program_other" class="form-control"
                 value="<?= e(post('program_other')) ?>" maxlength="200">
        </div>
        <div class="col-md-3">
          <label class="form-label">Year Level <span class="required-mark">*</span></label>
          <select name="year_level" class="form-select" required>
            <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $yl): ?>
            <option value="<?= e($yl) ?>" <?= post('year_level','1st Year')===$yl?'selected':'' ?>><?= e($yl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Enrollment Type <span class="required-mark">*</span></label>
          <select name="enrollment_type" class="form-select" required>
            <?php foreach (['New Student','Transferee','Returnee','Cross-Enrollee'] as $et): ?>
            <option value="<?= e($et) ?>" <?= post('enrollment_type','New Student')===$et?'selected':'' ?>><?= e($et) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Semester <span class="required-mark">*</span></label>
          <select name="semester" class="form-select" required>
            <?php foreach (['1st Semester','2nd Semester','Summer'] as $sem): ?>
            <option value="<?= e($sem) ?>" <?= post('semester','1st Semester')===$sem?'selected':'' ?>><?= e($sem) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">School Year</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-lock text-muted" style="font-size:.8rem;"></i></span>
            <input type="text" class="form-control bg-light"
                   value="<?= e(getSetting('current_school_year','2026-2027')) ?>" readonly>
          </div>
          <div class="form-text">Set by the Registrar</div>
        </div>
      </div>
      <div class="form-section-title mt-3">Previous School Information
        <span class="text-muted fw-normal" style="font-size:.75rem;text-transform:none;letter-spacing:0;">
          (for transferees &amp; returning students)
        </span>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Previous School</label>
          <input type="text" name="previous_school" class="form-control"
                 value="<?= e(post('previous_school')) ?>" maxlength="200">
        </div>
        <div class="col-md-3">
          <label class="form-label">Previous Program</label>
          <input type="text" name="previous_program" class="form-control"
                 value="<?= e(post('previous_program')) ?>" maxlength="200">
        </div>
        <div class="col-md-3">
          <label class="form-label">Last Year Attended</label>
          <input type="text" name="last_year_attended" class="form-control"
                 value="<?= e(post('last_year_attended')) ?>" maxlength="10" placeholder="2024-2025">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Section 4: Terms & Submit ── -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-header border-0 bg-white pt-4 px-4 pb-0">
      <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
              style="width:26px;height:26px;font-size:.8rem;background:#1a4a8a;flex-shrink:0;">4</span>
        Review &amp; Submit
      </h5>
      <hr class="mt-3 mb-0">
    </div>
    <div class="card-body p-4">
      <div class="alert alert-warning d-flex gap-2 mb-4">
        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
        <div class="small">
          <strong>Important:</strong> This pre-registration is for initial processing only.
          You will be required to visit the Registrar's Office with original documents upon approval.
          Submitting this form does not guarantee enrollment until fully processed.
        </div>
      </div>
      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="agreeTerms" required>
          <label class="form-check-label small" for="agreeTerms">
            I confirm that all information I have provided is accurate and complete.
            I understand that providing false information may result in the rejection of my registration.
            <span class="required-mark">*</span>
          </label>
        </div>
      </div>
      <div class="mb-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="agreePrivacy" required>
          <label class="form-check-label small" for="agreePrivacy">
            I agree that my personal information will be used solely for enrollment processing
            and status notification purposes by Tampakan Community College.
            <span class="required-mark">*</span>
          </label>
        </div>
      </div>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <a href="<?= BASE_URL ?>/student/index.php" class="btn-back">
          <i class="bi bi-arrow-left"></i> Back to Portal
        </a>
        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
          <i class="bi bi-send me-2"></i>Submit Pre-Registration
        </button>
      </div>
    </div>
  </div>

</form>
    </div><!-- /col -->
  </div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Show/hide Other program field
document.getElementById('program_select').addEventListener('change',function(){
  document.getElementById('program_other_wrap').style.display=this.value==='0'?'block':'none';
});
(function(){
  if(document.getElementById('program_select').value==='0')
    document.getElementById('program_other_wrap').style.display='block';
})();

// Submit guard
document.getElementById('reg-form').addEventListener('submit',function(e){
  if(!document.getElementById('agreeTerms').checked||!document.getElementById('agreePrivacy').checked){
    e.preventDefault();
    alert('Please agree to both checkboxes before submitting.');
    return;
  }
  var btn=document.getElementById('submitBtn');
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
});
</script>
</body>
</html>

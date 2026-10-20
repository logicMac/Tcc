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
$student      = currentStudent();   // non-null when signed in — prefill + auto-link

// Prefill form fields from the student's account on first load
$pre = [
    'first_name'     => $student['first_name']     ?? '',
    'middle_name'    => $student['middle_name']    ?? '',
    'last_name'      => $student['last_name']      ?? '',
    'suffix'         => $student['suffix']         ?? '',
    'email'          => $student['email']          ?? '',
    'contact_number' => $student['contact_number'] ?? '',
];

if (isPost()) {
    abortCsrf();
    $data = [
        'first_name'        => post('first_name'),
        'middle_name'       => post('middle_name'),
        'last_name'         => post('last_name'),
        'suffix'            => post('suffix'),
        'date_of_birth'     => post('date_of_birth'),
        'birth_place'       => post('birth_place'),
        'gender'            => post('gender'),
        'civil_status'      => post('civil_status'),
        'nationality'       => 'Filipino',
        'citizenship'       => post('citizenship') ?: 'Filipino',
        'citizenship_specify' => post('citizenship_specify'),
        'religion'          => post('religion'),
        'email'             => post('email'),
        'contact_number'    => post('contact_number'),
        'telephone_number'  => post('telephone_number'),
        'house_no_street'   => post('house_no_street'),
        'barangay'          => post('barangay'),
        'municipality_city' => post('municipality_city'),
        'province'          => post('province'),
        'district'          => post('district'),
        'region'            => post('region'),
        'zip_code'          => post('zip_code'),
        'program_id'        => postInt('program_id') ?: null,
        'program_other'     => post('program_other'),
        'year_level'        => post('year_level'),
        'enrollment_type'   => post('enrollment_type'),
        'school_year'       => getSetting('current_school_year','2026-2027'),
        'semester'          => post('semester'),
        'previous_school'   => post('college_school'),
        'previous_program'  => post('previous_program'),
        'last_year_attended'=> post('college_year_attended'),
        'lrn'               => post('lrn'),
        'guardian_name'     => post('guardian_name'),
        'guardian_relation' => post('guardian_relation'),
        'guardian_contact'  => post('guardian_contact'),
        'father_name'       => post('father_name'),
        'father_status'     => post('father_status') ?: null,
        'father_contact'    => post('father_contact'),
        'mother_name'       => post('mother_name'),
        'mother_status'     => post('mother_status') ?: null,
        'mother_contact'    => post('mother_contact'),
        'guardian_status'   => post('guardian_status') ?: null,
        'spouse_name'       => post('spouse_name'),
        'elementary_school' => post('elementary_school'),
        'elementary_year_graduated' => post('elementary_year_graduated'),
        'jhs_school'        => post('jhs_school'),
        'jhs_year_completed'=> post('jhs_year_completed'),
        'shs_school'        => post('shs_school'),
        'shs_year_graduated'=> post('shs_year_graduated'),
        'college_school'    => post('college_school'),
        'college_year_attended' => post('college_year_attended'),
    ];

    // Mailing address — copy permanent when "same as above" is checked
    $sameMail = (bool) post('mail_same');
    $data['mail_same_as_permanent'] = $sameMail ? 1 : 0;
    foreach (['house_no_street','barangay','municipality_city','province','district','region'] as $f) {
        $data['mail_' . $f] = $sameMail ? $data[$f] : post('mail_' . $f);
    }

    // 2x2 photo upload (optional)
    $photoPath = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed. Please try again.';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Photo must be 2MB or smaller.';
        } else {
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png'])) {
                $errors[] = 'Photo must be a JPG or PNG image.';
            } else {
                $dir = UPLOAD_DIR . '/photos';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext  = $mime === 'image/png' ? '.png' : '.jpg';
                $name = 'REG-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . $ext;
                $dest = $dir . '/' . $name;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $photoPath = 'photos/' . $name;
                } else {
                    $errors[] = 'Could not save the uploaded photo.';
                }
            }
        }
    }

    // School credential uploads — optional scans/photos (JPG/PNG/PDF, 5MB max each)
    $credentials = [];
    foreach (credentialCatalog() as $ck => $cred) {
        $f = $_FILES['cred_' . $ck] ?? null;
        if (!$f || empty($f['name']) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for "' . $cred['label'] . '". Please try again.';
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $errors[] = '"' . $cred['label'] . '" must be 5MB or smaller.';
        } else {
            $mime = mime_content_type($f['tmp_name']);
            $ext  = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'application/pdf' => '.pdf'][$mime] ?? null;
            if (!$ext) {
                $errors[] = '"' . $cred['label'] . '" must be a JPG, PNG or PDF file.';
            } else {
                $dir  = UPLOAD_DIR . '/credentials';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $name = 'CRED-' . strtoupper($ck) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                    $credentials[$ck] = 'credentials/' . $name;
                } else {
                    $errors[] = 'Could not save "' . $cred['label'] . '".';
                }
            }
        }
    }

    if (empty($data['first_name']))        $errors[] = 'First name is required.';
    if (empty($data['last_name']))         $errors[] = 'Last name is required.';
    if (empty($data['date_of_birth']))     $errors[] = 'Date of birth is required.';
    elseif (!validateDate($data['date_of_birth'])) $errors[] = 'Invalid date of birth.';
    if (empty($data['gender']))            $errors[] = 'Sex is required.';
    if (empty($data['email']))             $errors[] = 'Email address is required.';
    elseif (!validateEmail($data['email'])) $errors[] = 'Please enter a valid email address.';
    if (empty($data['contact_number']))    $errors[] = 'Mobile number is required.';
    elseif (!validatePhone($data['contact_number'])) $errors[] = 'Invalid mobile number format.';
    if (empty($data['house_no_street']))   $errors[] = 'Permanent address: street / house number is required.';
    if (empty($data['barangay']))          $errors[] = 'Permanent address: barangay is required.';
    if (empty($data['municipality_city'])) $errors[] = 'Permanent address: municipality / city is required.';
    if (empty($data['province']) && stripos($data['region'], 'National Capital') === false)
        $errors[] = 'Permanent address: province is required.';
    if (empty($data['year_level']))        $errors[] = 'Year level is required.';
    if (empty($data['enrollment_type']))   $errors[] = 'Admission type is required.';
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
                     date_of_birth,birth_place,gender,civil_status,nationality,
                     citizenship,citizenship_specify,religion,
                     email,contact_number,telephone_number,
                     house_no_street,barangay,municipality_city,province,district,region,zip_code,
                     mail_same_as_permanent,mail_house_no_street,mail_barangay,
                     mail_municipality_city,mail_province,mail_district,mail_region,
                     program_id,program_other,year_level,enrollment_type,school_year,semester,
                     previous_school,previous_program,last_year_attended,lrn,
                     guardian_name,guardian_relation,guardian_contact,
                     father_name,father_status,father_contact,
                     mother_name,mother_status,mother_contact,
                     guardian_status,spouse_name,
                     elementary_school,elementary_year_graduated,
                     jhs_school,jhs_year_completed,
                     shs_school,shs_year_graduated,
                     college_school,college_year_attended,
                     photo_path,credentials_submitted,status,ip_address)
                 VALUES (" . str_repeat('?,', 62) . "?)",
                [$trackingCode,$data['first_name'],$data['middle_name'],$data['last_name'],
                 $data['suffix'],$data['date_of_birth'],$data['birth_place'],$data['gender'],
                 $data['civil_status'],$data['nationality'],$data['citizenship'],
                 $data['citizenship_specify'],$data['religion'],
                 $data['email'],$data['contact_number'],$data['telephone_number'],
                 $data['house_no_street'],$data['barangay'],$data['municipality_city'],
                 $data['province'],$data['district'],$data['region'],$data['zip_code'],
                 $data['mail_same_as_permanent'],$data['mail_house_no_street'],$data['mail_barangay'],
                 $data['mail_municipality_city'],$data['mail_province'],$data['mail_district'],$data['mail_region'],
                 $data['program_id'],$data['program_other'],$data['year_level'],
                 $data['enrollment_type'],$data['school_year'],$data['semester'],
                 $data['previous_school'],$data['previous_program'],$data['last_year_attended'],$data['lrn'],
                 $data['guardian_name'],$data['guardian_relation'],$data['guardian_contact'],
                 $data['father_name'],$data['father_status'],$data['father_contact'],
                 $data['mother_name'],$data['mother_status'],$data['mother_contact'],
                 $data['guardian_status'],$data['spouse_name'],
                 $data['elementary_school'],$data['elementary_year_graduated'],
                 $data['jhs_school'],$data['jhs_year_completed'],
                 $data['shs_school'],$data['shs_year_graduated'],
                 $data['college_school'],$data['college_year_attended'],
                 $photoPath,$credentials ? json_encode($credentials) : null,
                 'Pending',getClientIp()]
            );
            $regData = db()->fetchOne(
                "SELECT * FROM pre_registrations WHERE tracking_code = ?", [$trackingCode]
            );
            linkSubmissionToStudent('pre_registrations', (int)$regData['id'], currentStudentId());
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
$pageTitle   = 'Pre-Registration';
include __DIR__ . '/../includes/partials/portal_head.php';
include __DIR__ . '/../includes/partials/portal_nav.php';
?>

<!-- ── Page breadcrumb bar ── -->
<div class="portal-crumbs">
  <div class="container py-2 d-flex align-items-center gap-2">
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
  <div class="verify-banner good text-center p-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
         style="width:72px;height:72px;background:rgba(255,255,255,.2);">
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
<script src="<?= BASE_URL ?>/assets/js/vendor/lenis.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js<?= assetVer("assets/js/main.js") ?>"></script>
<script>document.addEventListener('DOMContentLoaded',()=>generateQrCode('qrcode-output','<?= e($trackingUrl) ?>'));</script>
</body></html>
<?php exit; endif; ?>

<!-- ════ FORM — TCC-RRMO-FORM-001 layout ════ -->
<div class="d-flex align-items-center gap-3 mb-4">
  <div class="portal-card-icon" style="width:48px;height:48px;flex-shrink:0;">
    <i class="bi bi-person-plus-fill fs-5"></i>
  </div>
  <div>
    <h3 class="fw-bold mb-0">Application for Admission</h3>
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

<form method="POST" action="" id="reg-form" enctype="multipart/form-data" novalidate>
  <?= csrfField() ?>

  <div class="paper-form">
    <!-- ── Form header ── -->
    <div class="pf-head">
      <div class="pf-code">TCC-RRMO-FORM-001</div>
      <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal" class="pf-seal">
      <div class="pf-school"><?= e($schoolName) ?></div>
      <div class="pf-addr">Purok Malinusgan, Brgy. Poblacion, Tampakan, South Cotabato</div>
      <div class="pf-title">APPLICATION FOR ADMISSION <span class="fw-semibold" style="letter-spacing:0;">(College)</span></div>
      <div class="adm-type justify-content-center">
        <span class="fw-semibold me-1" style="font-size:.8rem;">Admission Application is made as:</span>
        <?php foreach (['New Student'=>'New Student','Transferee'=>'Transfer Student','2nd Undergraduate'=>'2nd Undergraduate Seeking Student'] as $v => $lbl): ?>
        <label class="form-check form-check-inline m-0">
          <input class="form-check-input" type="radio" name="enrollment_type" value="<?= e($v) ?>"
                 <?= post('enrollment_type','New Student')===$v?'checked':'' ?> required>
          <span class="form-check-label"><?= e($lbl) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="pf-body">
      <!-- ── LEGAL NAME ── -->
      <div class="sec-bar">Legal Name <span class="sec-bar-sub">(Name in Birth Certificate)</span></div>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Last Name <span class="required-mark">*</span></label>
          <input type="text" name="last_name" class="form-control"
                 value="<?= e(post('last_name', $pre['last_name'])) ?>" required maxlength="100" placeholder="Dela Cruz">
        </div>
        <div class="col-md-3">
          <label class="form-label">First Name <span class="required-mark">*</span></label>
          <input type="text" name="first_name" class="form-control"
                 value="<?= e(post('first_name', $pre['first_name'])) ?>" required maxlength="100" placeholder="Juan">
        </div>
        <div class="col-md-3">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-control"
                 value="<?= e(post('middle_name', $pre['middle_name'])) ?>" maxlength="100" placeholder="(optional)">
        </div>
        <div class="col-md-3">
          <label class="form-label">Extension <span class="text-muted fw-normal">(Jr., Sr., III, I, II)</span></label>
          <input type="text" name="suffix" class="form-control"
                 value="<?= e(post('suffix', $pre['suffix'])) ?>" maxlength="20">
        </div>
      </div>

      <div class="instruct">
        <b>A.</b> Read all instructions very carefully.
        <b>B.</b> Fill out this form carefully. Only application forms correctly filled out will be accepted. Write N/A if the information does not apply to you.
        <b>C.</b> Incomplete application information will not be processed.
        <b>D.</b> Submit this form together with the required documents upon enrolment.
        <b>E.</b> Those applying for a scholarship must ask for a Scholarship Form. Submit the completed form together with the application form for enrolment.
      </div>

      <!-- ── PERSONAL INFORMATION ── -->
      <div class="sec-bar">Personal Information</div>

      <div class="f-label"><span class="fnum">1.</span> Permanent Address</div>
      <div class="row g-3 geo-cascade" id="perm-addr">
        <div class="col-md-4">
          <label class="form-label">Street Number <span class="required-mark">*</span></label>
          <input type="text" name="house_no_street" class="form-control"
                 value="<?= e(post('house_no_street')) ?>" required maxlength="200">
        </div>
        <div class="col-md-4">
          <label class="form-label">Region <span class="required-mark">*</span></label>
          <select name="region" class="form-select geo-select" data-geo="region"
                  data-old="<?= e(post('region')) ?>" required>
            <option value="">Loading regions…</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Province <span class="required-mark">*</span></label>
          <select name="province" class="form-select geo-select" data-geo="province"
                  data-old="<?= e(post('province')) ?>" required disabled>
            <option value="">Select a region first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">City / Municipality <span class="required-mark">*</span></label>
          <select name="municipality_city" class="form-select geo-select" data-geo="city"
                  data-old="<?= e(post('municipality_city')) ?>" required disabled>
            <option value="">Select a province first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Barangay <span class="required-mark">*</span></label>
          <select name="barangay" class="form-select geo-select" data-geo="barangay"
                  data-old="<?= e(post('barangay')) ?>" required disabled>
            <option value="">Select a city / municipality first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">District</label>
          <input type="text" name="district" class="form-control"
                 value="<?= e(post('district')) ?>" maxlength="100">
        </div>
      </div>

      <div class="f-label mt-4 d-flex align-items-center gap-3 flex-wrap">
        <span><span class="fnum">2.</span> Mailing Address <span class="text-muted fw-normal">(if not the same as above)</span></span>
        <label class="form-check m-0">
          <input class="form-check-input" type="checkbox" id="mail_same" name="mail_same" value="1"
                 <?= post('mail_same') ? 'checked' : '' ?>>
          <span class="form-check-label" style="font-size:.74rem;">Same as permanent address</span>
        </label>
      </div>
      <div class="row g-3 geo-cascade" id="mail-fields">
        <div class="col-md-4">
          <label class="form-label">Street Number</label>
          <input type="text" name="mail_house_no_street" class="form-control"
                 value="<?= e(post('mail_house_no_street')) ?>" maxlength="200">
        </div>
        <div class="col-md-4">
          <label class="form-label">Region</label>
          <select name="mail_region" class="form-select geo-select" data-geo="region"
                  data-old="<?= e(post('mail_region')) ?>">
            <option value="">Loading regions…</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Province</label>
          <select name="mail_province" class="form-select geo-select" data-geo="province"
                  data-old="<?= e(post('mail_province')) ?>" disabled>
            <option value="">Select a region first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">City / Municipality</label>
          <select name="mail_municipality_city" class="form-select geo-select" data-geo="city"
                  data-old="<?= e(post('mail_municipality_city')) ?>" disabled>
            <option value="">Select a province first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Barangay</label>
          <select name="mail_barangay" class="form-select geo-select" data-geo="barangay"
                  data-old="<?= e(post('mail_barangay')) ?>" disabled>
            <option value="">Select a city / municipality first</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">District</label>
          <input type="text" name="mail_district" class="form-control"
                 value="<?= e(post('mail_district')) ?>" maxlength="100">
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label"><span class="fnum">3.</span> Telephone Number</label>
          <input type="tel" name="telephone_number" class="form-control"
                 value="<?= e(post('telephone_number')) ?>" maxlength="20">
        </div>
        <div class="col-md-4">
          <label class="form-label"><span class="fnum">4.</span> Mobile Number <span class="required-mark">*</span></label>
          <input type="tel" name="contact_number" class="form-control"
                 value="<?= e(post('contact_number', $pre['contact_number'])) ?>" required maxlength="20"
                 placeholder="09XX-XXX-XXXX">
        </div>
        <div class="col-md-4">
          <label class="form-label"><span class="fnum">5.</span> Email Address <span class="required-mark">*</span></label>
          <input type="email" name="email" class="form-control"
                 value="<?= e(post('email', $pre['email'])) ?>" required maxlength="150"
                 placeholder="you@email.com">
        </div>
      </div>

      <div class="row g-3 mt-1 align-items-end">
        <div class="col-md-6">
          <div class="f-label"><span class="fnum">6.</span> Civil Status</div>
          <div class="d-flex gap-3 flex-wrap pt-1">
            <?php foreach (['Single','Married','Widowed','Separated'=>'Legal Separated'] as $v => $lbl):
              if (is_int($v)) { $v = $lbl; } ?>
            <label class="form-check m-0">
              <input class="form-check-input" type="radio" name="civil_status" value="<?= e($v) ?>"
                     <?= post('civil_status','Single')===$v?'checked':'' ?>>
              <span class="form-check-label"><?= e($lbl) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label"><span class="fnum">7.</span> Religion</label>
          <input type="text" name="religion" class="form-control"
                 value="<?= e(post('religion')) ?>" maxlength="80">
        </div>
      </div>

      <div class="row g-3 mt-1 align-items-end">
        <div class="col-md-3">
          <label class="form-label"><span class="fnum">8.</span> Birth Date <span class="required-mark">*</span></label>
          <input type="date" name="date_of_birth" class="form-control"
                 value="<?= e(post('date_of_birth')) ?>"
                 max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label"><span class="fnum">9.</span> Birth Place</label>
          <input type="text" name="birth_place" class="form-control"
                 value="<?= e(post('birth_place')) ?>" maxlength="150"
                 placeholder="City / Municipality, Province">
        </div>
        <div class="col-md-5">
          <div class="f-label"><span class="fnum">10.</span> Sex <span class="required-mark">*</span></div>
          <div class="d-flex gap-4 pt-1">
            <?php foreach (['Male','Female'] as $g): ?>
            <label class="form-check m-0">
              <input class="form-check-input" type="radio" name="gender" value="<?= e($g) ?>"
                     <?= post('gender')===$g?'checked':'' ?> required>
              <span class="form-check-label"><?= e($g) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1 align-items-end">
        <div class="col-md-7">
          <div class="f-label"><span class="fnum">11.</span> Citizenship</div>
          <div class="d-flex gap-3 flex-wrap pt-1">
            <?php foreach (['Filipino','Dual','Others'] as $cz): ?>
            <label class="form-check m-0">
              <input class="form-check-input" type="radio" name="citizenship" value="<?= e($cz) ?>"
                     <?= post('citizenship','Filipino')===$cz?'checked':'' ?>>
              <span class="form-check-label"><?= e($cz) ?><?= $cz!=='Filipino' ? ' <span class="text-muted">(specify)</span>' : '' ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-5" id="citizenship_wrap" style="display:none;">
          <label class="form-label">Specify Citizenship</label>
          <input type="text" name="citizenship_specify" class="form-control"
                 value="<?= e(post('citizenship_specify')) ?>" maxlength="100">
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label">LRN <span class="text-muted fw-normal">(Learner Reference No.)</span></label>
          <input type="text" name="lrn" class="form-control"
                 value="<?= e(post('lrn')) ?>" maxlength="30" placeholder="12-digit LRN">
        </div>
      </div>

      <!-- ── FAMILY INFORMATION ── -->
      <div class="sec-bar">Family Information</div>
      <div class="f-label"><span class="fnum">12.</span> Parents / Guardian / Spouse</div>
      <div class="table-responsive">
        <table class="fam-grid">
          <thead>
            <tr><th></th><th>Name</th><th style="width:200px;">Contact Number</th></tr>
          </thead>
          <tbody>
            <?php
            $fam = [
                ['Father','father'],
                ['Mother','mother'],
                ['Guardian','guardian'],
            ];
            foreach ($fam as [$lbl,$k]): ?>
            <tr>
              <td>
                <div class="fw-semibold" style="font-size:.78rem;"><?= $lbl ?></div>
                <div class="d-flex gap-2 mt-1">
                  <?php foreach (['Living','Deceased'] as $ls): ?>
                  <label class="life-check">
                    <input class="form-check-input m-0" type="radio" name="<?= $k ?>_status" value="<?= $ls ?>"
                           <?= post($k.'_status')===$ls?'checked':'' ?>>
                    <?= $ls ?>
                  </label>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <input type="text" name="<?= $k ?>_name" class="form-control form-control-sm"
                       value="<?= e(post($k.'_name')) ?>" maxlength="150">
                <?php if ($k==='guardian'): ?>
                <input type="text" name="guardian_relation" class="form-control form-control-sm mt-1"
                       value="<?= e(post('guardian_relation')) ?>" maxlength="80" placeholder="Relationship (e.g. Aunt)">
                <?php endif; ?>
              </td>
              <td>
                <input type="tel" name="<?= $k ?>_contact" class="form-control form-control-sm"
                       value="<?= e(post($k.'_contact')) ?>" maxlength="20">
              </td>
            </tr>
            <?php endforeach; ?>
            <tr>
              <td><div class="fw-semibold" style="font-size:.78rem;">Spouse <span class="text-muted fw-normal">(if married)</span></div></td>
              <td>
                <input type="text" name="spouse_name" class="form-control form-control-sm"
                       value="<?= e(post('spouse_name')) ?>" maxlength="150">
              </td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── ACADEMIC INFORMATION ── -->
      <div class="sec-bar">Academic Information</div>

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
          <label class="form-label">Semester <span class="required-mark">*</span></label>
          <select name="semester" class="form-select" required>
            <?php foreach (['1st Semester','2nd Semester','Summer'] as $sem): ?>
            <option value="<?= e($sem) ?>" <?= post('semester','1st Semester')===$sem?'selected':'' ?>><?= e($sem) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">School Year</label>
          <input type="text" class="form-control bg-light"
                 value="<?= e(getSetting('current_school_year','2026-2027')) ?>" readonly>
          <div class="form-text">Set by the Registrar</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">ZIP Code</label>
          <input type="text" name="zip_code" class="form-control"
                 value="<?= e(post('zip_code')) ?>" maxlength="10">
        </div>
      </div>

      <div class="f-label mt-4"><span class="fnum">13.</span> Schools Attended
        <span class="text-muted fw-normal">(list all schools attended beginning from elementary)</span>
      </div>
      <div class="table-responsive">
        <table class="fam-grid">
          <thead>
            <tr><th style="width:170px;">Level</th><th>School</th><th style="width:160px;">Year</th></tr>
          </thead>
          <tbody>
            <?php
            $lvls = [
                ['Elementary','elementary_school','elementary_year_graduated','Year Graduated'],
                ['Junior HS','jhs_school','jhs_year_completed','Year Completed'],
                ['Senior HS','shs_school','shs_year_graduated','Year Graduated'],
                ['College <span class="text-muted fw-normal" style="font-size:.66rem;">(for transferees)</span>','college_school','college_year_attended','Year Attended'],
            ];
            foreach ($lvls as [$lbl,$sf,$yf,$yl]): ?>
            <tr>
              <td class="fw-semibold" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;"><?= $lbl ?></td>
              <td>
                <input type="text" name="<?= $sf ?>" class="form-control form-control-sm"
                       value="<?= e(post($sf)) ?>" maxlength="200">
              </td>
              <td>
                <input type="text" name="<?= $yf ?>" class="form-control form-control-sm"
                       value="<?= e(post($yf)) ?>" maxlength="10" placeholder="<?= $yl ?>">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label">Previous Program / Course <span class="text-muted fw-normal">(transferees)</span></label>
          <input type="text" name="previous_program" class="form-control"
                 value="<?= e(post('previous_program')) ?>" maxlength="200">
        </div>
      </div>

      <!-- ── School Credentials (TCC-RRMO-FORM-001 checklist) ── -->
      <div class="sec-bar">School Credentials
        <span class="sec-bar-sub">— attach a scan or clear photo of each document you have</span>
      </div>
      <div class="table-responsive">
        <table class="fam-grid">
          <thead>
            <tr>
              <th style="width:52%">Document</th>
              <th>Upload <span class="fw-normal text-lowercase">(JPG / PNG / PDF · max 5MB)</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (credentialCatalog() as $ck => $cred): ?>
            <tr <?= !empty($cred['transferee']) ? 'id="cred-tor-row"' : '' ?>>
              <td>
                <span class="small fw-semibold"><?= e($cred['label']) ?></span>
                <span class="text-muted" style="font-size:.68rem;"> · <?= e($cred['note']) ?></span>
                <?php if (!empty($cred['transferee'])): ?>
                <span class="badge-soft-warning" style="font-size:.6rem;">Transferees only</span>
                <?php endif; ?>
              </td>
              <td>
                <input type="file" name="cred_<?= $ck ?>" class="form-control form-control-sm cred-file"
                       accept=".jpg,.jpeg,.png,.pdf">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted mt-2" style="font-size:.7rem;">
        <i class="bi bi-info-circle me-1"></i>Optional — you may also present the original documents
        in person at the Registrar's Office during enrollment.
      </div>

      <!-- ── Certification + Photo ── -->
      <div class="pf-foot">
        <div class="row g-4">
          <div class="col-md-8">
            <p class="mb-3" style="font-size:.76rem;line-height:1.6;">
              I hereby certify that all information written in this application form is complete and accurate.
              I agree that my enrolment, matriculation, and graduation are subject to the rules and
              regulations of the Tampakan Community College.
            </p>
            <div class="alert alert-warning d-flex gap-2 py-2 mb-3">
              <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
              <div class="small">
                <strong>Important:</strong> Pre-registration is for initial processing only.
                You will be required to visit the Registrar's Office with original documents upon approval.
              </div>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="agreeTerms" required>
              <label class="form-check-label small" for="agreeTerms">
                I confirm that all information provided is accurate and complete.
                <span class="required-mark">*</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="agreePrivacy" required>
              <label class="form-check-label small" for="agreePrivacy">
                I agree that my personal information will be used solely for enrollment processing
                and status notification by Tampakan Community College.
                <span class="required-mark">*</span>
              </label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="photo-box w-100" for="photo" id="photoBox">
              <img id="photoPreview" alt="" style="display:none;">
              <i class="bi bi-person-bounding-box fs-2" id="photoIcon"></i>
              <span id="photoLabel">Photo of Applicant (2×2)<br><small>Click to upload · JPG/PNG · max 2MB</small></span>
              <input type="file" name="photo" id="photo" accept="image/jpeg,image/png" class="d-none">
            </label>
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3">
          <a href="<?= BASE_URL ?>/student/index.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Portal
          </a>
          <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
            <i class="bi bi-send me-2"></i>Submit Application
          </button>
        </div>
      </div>
    </div>
  </div>

</form>
    </div><!-- /col -->
  </div><!-- /row -->
</div><!-- /container -->

<script>
// Show/hide Other program field
document.getElementById('program_select').addEventListener('change',function(){
  document.getElementById('program_other_wrap').style.display=this.value==='0'?'block':'none';
});
(function(){
  if(document.getElementById('program_select').value==='0')
    document.getElementById('program_other_wrap').style.display='block';
})();

// Transferee-only credential row — dim unless "Transfer Student" is selected
(function(){
  const row = document.getElementById('cred-tor-row');
  const radios = document.querySelectorAll('input[name="enrollment_type"]');
  const sync = () => {
    const isTransfer = [...radios].some(r => r.checked && r.value === 'Transferee');
    row.style.opacity = isTransfer ? '1' : '.45';
    row.querySelector('input[type="file"]').disabled = !isTransfer;
  };
  radios.forEach(r => r.addEventListener('change', sync));
  sync();
})();

// Mailing address — disable fields when "same as permanent" is checked
(function(){
  const chk = document.getElementById('mail_same');
  const box = document.getElementById('mail-fields');
  const inputs = box.querySelectorAll('input, select');
  const sync = () => {
    const same = chk.checked;
    box.style.opacity = same ? '.45' : '1';
    box.style.pointerEvents = same ? 'none' : '';
    inputs.forEach(i => i.disabled = same || (i.dataset.geo && i.dataset.geoDisabled === '1'));
  };
  chk.addEventListener('change', sync);
  sync();
})();

// Citizenship "specify" field
(function(){
  const wrap = document.getElementById('citizenship_wrap');
  document.querySelectorAll('input[name="citizenship"]').forEach(r =>
    r.addEventListener('change', () => wrap.style.display = r.value === 'Filipino' ? 'none' : 'block')
  );
  const sel = document.querySelector('input[name="citizenship"]:checked');
  if (sel) wrap.style.display = sel.value === 'Filipino' ? 'none' : 'block';
})();

// Photo preview
document.getElementById('photo').addEventListener('change', function(){
  const f = this.files[0];
  if (!f) return;
  const img = document.getElementById('photoPreview');
  img.src = URL.createObjectURL(f);
  img.style.display = 'block';
  document.getElementById('photoIcon').style.display = 'none';
  document.getElementById('photoLabel').innerHTML = f.name;
});

// Philippine address cascade — Region → Province → City/Municipality → Barangay (PSGC data)
(function(){
  const API   = '<?= BASE_URL ?>/api/geo.php';
  const ORDER = ['region','province','city','barangay'];
  const HINT  = {
    region:   'Select region',
    province: 'Select a region first',
    city:     'Select a province first',
    barangay: 'Select a city / municipality first'
  };

  function fill(sel, items, hint){
    sel.innerHTML = '<option value="">' + hint + '</option>';
    items.forEach(it => {
      const o = document.createElement('option');
      o.value = it.name;               // names are stored, not codes
      o.textContent = it.label;
      o.dataset.code = it.code;
      sel.appendChild(o);
    });
    sel.disabled = false;
    sel.dataset.geoDisabled = '0';
    if (sel.dataset.req === '1') sel.required = true;
    if (sel.dataset.old) {
      const opt = [...sel.options].find(o => o.value === sel.dataset.old);
      delete sel.dataset.old;
      if (opt) { sel.value = opt.value; return true; }
    }
    return false;
  }

  function resetDownstream(wrap, fromLevel){
    const i = ORDER.indexOf(fromLevel);
    ORDER.slice(i + 1).forEach(lv => {
      const s = wrap.querySelector('[data-geo="' + lv + '"]');
      if (!s) return;
      s.innerHTML = '<option value="">' + HINT[lv] + '</option>';
      s.disabled = true;
      s.dataset.geoDisabled = '1';
      delete s.dataset.old;
    });
  }

  async function childrenOf(code){
    const r = await fetch(API + '?parent=' + encodeURIComponent(code));
    if (!r.ok) throw new Error('geo fetch failed');
    return r.json();
  }

  function fallbackToText(wrap){
    wrap.querySelectorAll('select.geo-select').forEach(s => {
      const inp = document.createElement('input');
      inp.type = 'text'; inp.name = s.name; inp.className = 'form-control';
      inp.value = s.dataset.old || ''; inp.maxLength = 100;
      if (s.required) inp.required = true;
      s.replaceWith(inp);
    });
  }

  function bind(wrap){
    const sels = {};
    ORDER.forEach(lv => sels[lv] = wrap.querySelector('[data-geo="' + lv + '"]'));
    if (!sels.region) return;
    ORDER.forEach(lv => { if (sels[lv]?.required) sels[lv].dataset.req = '1'; });

    sels.region.addEventListener('change', async function(){
      const code = this.selectedOptions[0]?.dataset.code;
      resetDownstream(wrap, 'region');
      if (!code) return;
      const res = await childrenOf(code);
      if (res.kind === 'provinces') {
        fill(sels.province, res.items, 'Select province');
      } else {
        // Region without provinces (e.g. NCR) — cities hang off the region
        sels.province.innerHTML = '<option value="">Not applicable</option>';
        sels.province.disabled = true;
        sels.province.dataset.geoDisabled = '1';
        sels.province.required = false;
        fill(sels.city, res.items, 'Select city');
      }
      if (sels.province.value) sels.province.dispatchEvent(new Event('change'));
      if (sels.city.value)     sels.city.dispatchEvent(new Event('change'));
    });
    sels.province.addEventListener('change', async function(){
      const code = this.selectedOptions[0]?.dataset.code;
      resetDownstream(wrap, 'province');
      if (!code) return;
      const res = await childrenOf(code);
      fill(sels.city, res.items, 'Select city / municipality');
      if (sels.city.value) sels.city.dispatchEvent(new Event('change'));
    });
    sels.city.addEventListener('change', async function(){
      const code = this.selectedOptions[0]?.dataset.code;
      resetDownstream(wrap, 'city');
      if (!code) return;
      const res = await childrenOf(code);
      fill(sels.barangay, res.items, 'Select barangay');
    });

    fetch(API + '?level=regions')
      .then(r => { if (!r.ok) throw new Error(); return r.json(); })
      .then(res => {
        const restored = fill(sels.region, res.items, 'Select region');
        if (restored) sels.region.dispatchEvent(new Event('change'));
      })
      .catch(() => fallbackToText(wrap));
  }

  document.querySelectorAll('.geo-cascade').forEach(bind);
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
<?php include __DIR__ . '/../includes/partials/portal_foot.php'; ?>

<?php
/**
 * TCC RRS – Staff: Issued Document Verification Slip
 * Printable slip released with official documents.
 * The QR code resolves to the public verify.php page.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_STAFF, ROLE_REGISTRAR);

$id = getInt('id');
if (!$id) { flash('danger','Invalid request.'); redirect(BASE_URL . '/' . currentRole() . '/document_requests.php'); }

$req = db()->fetchOne(
    "SELECT dr.*, dt.name AS doc_name
     FROM document_requests dr
     LEFT JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.id = ?", [$id]
);
if (!$req) { flash('danger','Request not found.'); redirect(BASE_URL . '/' . currentRole() . '/document_requests.php'); }

$issued = db()->fetchOne(
    "SELECT i.*, u.full_name AS issued_by_name
     FROM issued_documents i LEFT JOIN users u ON u.id = i.issued_by
     WHERE i.document_request_id = ?", [$id]
);
if (!$issued) {
    flash('warning','No verified document has been issued for this request yet.');
    redirect(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . $id);
}

$verifyUrl  = verificationUrl($issued['verification_code']);
$schoolName = getSetting('school_name', 'Tampakan Community College');
$printMode  = get('print') === '1';
?>
<?php if ($printMode): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verification Slip – <?= e($issued['verification_code']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
<style>body{background:#fff;padding:24px;}@media print{body{padding:0;}}</style>
</head>
<body>
<?php else: ?>
<?php
$pageTitle   = 'Verification Slip – ' . $issued['verification_code'];
$breadcrumbs = [
    ['label' => 'Document Requests', 'url' => BASE_URL . '/' . currentRole() . '/document_requests.php'],
    ['label' => e($issued['verification_code'])],
];
include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/' . currentRole() . '_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">

<?= ui_page_header(
    'Verification Slip',
    'Attach this slip to the released document — its QR code verifies authenticity.',
    'patch-check',
    ui_back(BASE_URL . '/' . currentRole() . '/view_document.php?id=' . (int)$id, 'Back to Request')
    . ui_btn('Print Slip', ['size'=>'sm','variant'=>'outline-dark','icon'=>'printer',
            'href'=>'?id=' . (int)$id . '&amp;print=1', 'attrs'=>'target="_blank"'])
) ?>
<?php endif; ?>

<!-- ── The slip itself ── -->
<div class="verify-slip">
  <div class="card border-0 shadow overflow-hidden">
    <div class="verify-slip-head">
      <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="School seal" class="verify-slip-seal">
      <div class="fw-bold" style="font-size:1.1rem;"><?= e($schoolName) ?></div>
      <div class="small opacity-75">Office of the Registrar — Document Verification</div>
    </div>
    <div class="card-body p-4">
      <div class="row g-4 align-items-center">
        <div class="col-sm-7">
          <div class="mb-3">
            <div class="detail-label">Verification Code</div>
            <div class="font-monospace fw-bold" style="font-size:1.05rem;">
              <?= e($issued['verification_code']) ?>
            </div>
          </div>
          <table class="info-table w-100">
            <tbody>
              <tr><td>Document</td><td><strong><?= e($issued['document_type']) ?></strong></td></tr>
              <tr><td>Issued To</td><td><?= e($issued['issued_to']) ?></td></tr>
              <tr><td>Reference</td><td><span class="font-monospace"><?= e($issued['tracking_code']) ?></span></td></tr>
              <tr><td>Copies</td><td><?= (int)$issued['copies'] ?></td></tr>
              <tr><td>Issued By</td><td><?= e($issued['issued_by_name'] ?? '—') ?></td></tr>
              <tr><td>Issued On</td><td><?= formatDateTime($issued['issued_at']) ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="col-sm-5 text-center">
          <div class="qr-container d-inline-block">
            <div id="qrcode-slip"></div>
            <div class="small text-muted mt-2">Scan to verify authenticity</div>
          </div>
        </div>
      </div>
      <hr>
      <div class="small text-muted text-center">
        This slip certifies that the attached document was officially issued by the
        <?= e($schoolName) ?> Registrar's Office.<br>
        Verify at <span class="font-monospace"><?= e($verifyUrl) ?></span>
      </div>
    </div>
  </div>
</div>

<?php if ($printMode): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/vendor/lenis.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js<?= assetVer("assets/js/main.js") ?>"></script>
<script>
generateQrCode('qrcode-slip', '<?= e($verifyUrl) ?>', {width:150,height:150});
window.onload = () => setTimeout(() => window.print(), 400);
</script>
</body>
</html>
<?php exit; endif; ?>

</div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  generateQrCode('qrcode-slip', '<?= e($verifyUrl) ?>', {width:150,height:150});
});
</script>

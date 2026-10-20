<?php
/**
 * TCC RRS – Registrar: Catalog Management
 * Manage academic programs (courses) and document types.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
requireAuth(ROLE_REGISTRAR);

$pageTitle   = 'Programs & Documents';
$breadcrumbs = [['label' => 'Catalog']];
$errors      = [];

// ── POST actions ─────────────────────────────────────────────────────────────
if (isPost()) {
    abortCsrf();
    $action = post('action');

    if ($action === 'save_program') {
        $id    = postInt('id');
        $code  = strtoupper(post('code'));
        $name  = post('name');
        $dept  = post('department');
        if (!$code || !$name) {
            $errors[] = 'Program code and name are required.';
        } else {
            $dup = db()->fetchOne("SELECT id FROM programs WHERE code = ? AND id != ?", [$code, $id]);
            if ($dup) {
                $errors[] = "Program code {$code} already exists.";
            } elseif ($id) {
                db()->execute("UPDATE programs SET code=?, name=?, department=? WHERE id=?", [$code,$name,$dept,$id]);
                logActivity('CATALOG_EDIT','Catalog',"Updated program {$code}");
                flash('success','Program updated.');
            } else {
                db()->insert("INSERT INTO programs (code,name,department,is_active) VALUES (?,?,?,1)", [$code,$name,$dept]);
                logActivity('CATALOG_ADD','Catalog',"Added program {$code}");
                flash('success','Program added.');
            }
        }
    } elseif ($action === 'toggle_program') {
        $id = postInt('id');
        db()->execute("UPDATE programs SET is_active = 1 - is_active WHERE id = ?", [$id]);
        logActivity('CATALOG_TOGGLE','Catalog',"Toggled program #$id");
        flash('success','Program status updated.');
    } elseif ($action === 'save_doctype') {
        $id    = postInt('id');
        $code  = strtoupper(post('code'));
        $name  = post('name');
        $desc  = post('description');
        $fee   = (float) post('fee', 0);
        $days  = postInt('processing_days') ?: 1;
        if (!$code || !$name) {
            $errors[] = 'Document code and name are required.';
        } else {
            $dup = db()->fetchOne("SELECT id FROM document_types WHERE code = ? AND id != ?", [$code, $id]);
            if ($dup) {
                $errors[] = "Document code {$code} already exists.";
            } elseif ($id) {
                db()->execute("UPDATE document_types SET code=?, name=?, description=?, fee=?, processing_days=? WHERE id=?",
                    [$code,$name,$desc,$fee,$days,$id]);
                logActivity('CATALOG_EDIT','Catalog',"Updated document type {$code}");
                flash('success','Document type updated.');
            } else {
                db()->insert("INSERT INTO document_types (code,name,description,fee,processing_days,is_active) VALUES (?,?,?,?,?,1)",
                    [$code,$name,$desc,$fee,$days]);
                logActivity('CATALOG_ADD','Catalog',"Added document type {$code}");
                flash('success','Document type added.');
            }
        }
    } elseif ($action === 'toggle_doctype') {
        $id = postInt('id');
        db()->execute("UPDATE document_types SET is_active = 1 - is_active WHERE id = ?", [$id]);
        logActivity('CATALOG_TOGGLE','Catalog',"Toggled document type #$id");
        flash('success','Document type status updated.');
    }

    if (empty($errors)) redirect(BASE_URL . '/registrar/catalog.php');
}

$programs = db()->fetchAll("SELECT * FROM programs ORDER BY code");
$docTypes = db()->fetchAll("SELECT * FROM document_types ORDER BY code");

include __DIR__ . '/../includes/partials/head.php';
include __DIR__ . '/../includes/partials/registrar_sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../includes/partials/topbar.php'; ?>
<div class="page-content">
<?= renderFlash() ?>

<?= ui_page_header(
    'Catalog',
    'Manage the courses and document types offered across the system.',
    'collection',
    '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#programModal" data-mode="add">
       <i class="bi bi-plus-lg me-1"></i>Add Program
     </button>
     <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#doctypeModal" data-mode="add">
       <i class="bi bi-plus-lg me-1"></i>Add Document Type
     </button>'
) ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="content-card">
  <div class="tbl-tabs" id="catTabs">
    <button type="button" class="ttab active" data-pane="programs">
      Programs <span class="count"><?= count($programs) ?></span>
    </button>
    <button type="button" class="ttab" data-pane="doctypes">
      Document Types <span class="count"><?= count($docTypes) ?></span>
    </button>
    <div class="ttab-spacer"></div>
    <span class="text-muted small">Inactive items stay on record but are hidden from student forms</span>
  </div>

  <!-- ── Programs ── -->
  <div class="tbl-pane show" id="pane-programs">
    <?php
    $rows = [];
    foreach ($programs as $p) {
        $rows[] = [
            '<span class="badge badge-soft-primary font-monospace">' . e($p['code']) . '</span>',
            '<div class="row-name">' . e($p['name']) . '</div><div class="row-sub">' . e($p['department'] ?? '') . '</div>',
            '<span class="badge badge-soft-' . ($p['is_active'] ? 'success' : 'secondary') . '">' . ($p['is_active'] ? 'Active' : 'Inactive') . '</span>',
            '<button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" data-bs-toggle="modal" data-bs-target="#programModal"'
              . ' data-mode="edit" data-id="' . (int)$p['id'] . '" data-code="' . e($p['code']) . '"'
              . ' data-name="' . e($p['name']) . '" data-department="' . e($p['department'] ?? '') . '">Edit</button>'
            . '<form method="POST" class="d-inline">' . csrfField()
              . '<input type="hidden" name="action" value="toggle_program"><input type="hidden" name="id" value="' . (int)$p['id'] . '">'
              . '<button class="btn btn-sm btn-outline-' . ($p['is_active'] ? 'secondary' : 'success') . ' py-0 px-2">'
              . ($p['is_active'] ? 'Deactivate' : 'Activate') . '</button></form>',
        ];
    }
    echo ui_table(['Code', 'Program', 'Status', ''], $rows,
                  ['empty_title' => 'No programs yet', 'empty_msg' => 'Add your first course/program.']);
    ?>
  </div>

  <!-- ── Document Types ── -->
  <div class="tbl-pane" id="pane-doctypes">
    <?php
    $rows = [];
    foreach ($docTypes as $d) {
        $rows[] = [
            '<span class="badge badge-soft-primary font-monospace">' . e($d['code']) . '</span>',
            '<div class="row-name">' . e($d['name']) . '</div><div class="row-sub">' . e($d['description'] ?? '') . '</div>',
            '<span class="num">' . formatCurrency((float)$d['fee']) . '</span>',
            '<span class="num">' . (int)$d['processing_days'] . 'd</span>',
            '<span class="badge badge-soft-' . ($d['is_active'] ? 'success' : 'secondary') . '">' . ($d['is_active'] ? 'Active' : 'Inactive') . '</span>',
            '<button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" data-bs-toggle="modal" data-bs-target="#doctypeModal"'
              . ' data-mode="edit" data-id="' . (int)$d['id'] . '" data-code="' . e($d['code']) . '"'
              . ' data-name="' . e($d['name']) . '" data-description="' . e($d['description'] ?? '') . '"'
              . ' data-fee="' . e($d['fee']) . '" data-days="' . (int)$d['processing_days'] . '">Edit</button>'
            . '<form method="POST" class="d-inline">' . csrfField()
              . '<input type="hidden" name="action" value="toggle_doctype"><input type="hidden" name="id" value="' . (int)$d['id'] . '">'
              . '<button class="btn btn-sm btn-outline-' . ($d['is_active'] ? 'secondary' : 'success') . ' py-0 px-2">'
              . ($d['is_active'] ? 'Deactivate' : 'Activate') . '</button></form>',
        ];
    }
    echo ui_table(['Code', 'Document', 'Fee', 'Est. Days', 'Status', ''], $rows,
                  ['empty_title' => 'No document types yet', 'empty_msg' => 'Add a document students can request.']);
    ?>
  </div>
</div>

</div><!-- /page-content -->

<!-- ── Program modal ── -->
<div class="modal fade" id="programModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_program">
      <input type="hidden" name="id" id="pm-id">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="pm-title">Add Program</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Code <span class="required-mark">*</span></label>
          <input type="text" name="code" id="pm-code" class="form-control font-monospace" placeholder="e.g. BSIT" maxlength="20" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Program Name <span class="required-mark">*</span></label>
          <input type="text" name="name" id="pm-name" class="form-control" placeholder="e.g. Bachelor of Science in Information Technology" maxlength="200" required>
        </div>
        <div>
          <label class="form-label">Department / College</label>
          <input type="text" name="department" id="pm-dept" class="form-control" placeholder="e.g. College of Computing and Technology" maxlength="200">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Document type modal ── -->
<div class="modal fade" id="doctypeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_doctype">
      <input type="hidden" name="id" id="dm-id">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="dm-title">Add Document Type</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-5">
            <label class="form-label">Code <span class="required-mark">*</span></label>
            <input type="text" name="code" id="dm-code" class="form-control font-monospace" placeholder="e.g. TOR" maxlength="30" required>
          </div>
          <div class="col-7">
            <label class="form-label">Document Name <span class="required-mark">*</span></label>
            <input type="text" name="name" id="dm-name" class="form-control" placeholder="e.g. Transcript of Records" maxlength="200" required>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <input type="text" name="description" id="dm-desc" class="form-control" maxlength="500">
          </div>
          <div class="col-6">
            <label class="form-label">Fee (₱)</label>
            <input type="number" name="fee" id="dm-fee" class="form-control" min="0" step="0.01" value="0">
          </div>
          <div class="col-6">
            <label class="form-label">Processing Days</label>
            <input type="number" name="processing_days" id="dm-days" class="form-control" min="1" value="3">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Tab switching
  document.querySelectorAll('#catTabs .ttab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#catTabs .ttab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tbl-pane').forEach(p => p.classList.remove('show'));
      btn.classList.add('active');
      document.getElementById('pane-' + btn.dataset.pane).classList.add('show');
    });
  });
  // Modal prefill
  document.getElementById('programModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('pm-title').textContent = b.dataset.mode === 'edit' ? 'Edit Program' : 'Add Program';
    document.getElementById('pm-id').value   = b.dataset.id ?? '';
    document.getElementById('pm-code').value = b.dataset.code ?? '';
    document.getElementById('pm-name').value = b.dataset.name ?? '';
    document.getElementById('pm-dept').value = b.dataset.department ?? '';
  });
  document.getElementById('doctypeModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('dm-title').textContent = b.dataset.mode === 'edit' ? 'Edit Document Type' : 'Add Document Type';
    document.getElementById('dm-id').value   = b.dataset.id ?? '';
    document.getElementById('dm-code').value = b.dataset.code ?? '';
    document.getElementById('dm-name').value = b.dataset.name ?? '';
    document.getElementById('dm-desc').value = b.dataset.description ?? '';
    document.getElementById('dm-fee').value  = b.dataset.fee ?? '0';
    document.getElementById('dm-days').value = b.dataset.days ?? '3';
  });
});
</script>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

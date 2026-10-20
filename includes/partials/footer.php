<!-- Dashboard Footer / Scripts -->
<footer class="py-3 px-4 border-top mt-auto bg-white">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="text-muted small">
      &copy; <?= date('Y') ?> <?= e(getSetting('school_name','Tampakan Community College')) ?> – Registration &amp; Records System
    </span>
    <span class="text-muted small">v<?= APP_VERSION ?></span>
  </div>
</footer>
</div><!-- /.main-content -->
</div><!-- /.dashboard-wrapper -->

<!-- Document viewer modal (used by .doc-view buttons across dashboard pages) -->
<div class="modal fade" id="docViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold text-truncate" id="docViewTitle">Document</h6>
        <a id="docViewOpen" href="#" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-secondary ms-auto me-2 flex-shrink-0">
          <i class="bi bi-box-arrow-up-right me-1"></i>Open in new tab
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="docViewBody" style="min-height:60vh;background:var(--surface-subtle);"></div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('docViewModal');
  if (!modalEl) return;
  var body    = document.getElementById('docViewBody');
  var title   = document.getElementById('docViewTitle');
  var openLnk = document.getElementById('docViewOpen');
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.doc-view');
    if (!btn) return;
    var url   = btn.dataset.url;
    var label = btn.dataset.label || 'Document';
    if (!url) return;
    ev.preventDefault();
    title.textContent = label;
    openLnk.href = url;
    var ext = url.split('?')[0].split('.').pop().toLowerCase();
    if (ext === 'pdf') {
      body.innerHTML = '<embed src="' + url + '" type="application/pdf" style="width:100%;height:70vh;border:0;display:block;">';
    } else {
      body.innerHTML = '<div class="text-center p-3"><img src="' + url + '" alt="' + label +
        '" class="img-fluid rounded" style="max-height:72vh;box-shadow:0 8px 24px rgba(10,30,20,.18);"></div>';
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
  modalEl.addEventListener('hidden.bs.modal', function () { body.innerHTML = ''; });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- QR Code library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/vendor/lenis.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js<?= assetVer("assets/js/main.js") ?>"></script>
</body>
</html>

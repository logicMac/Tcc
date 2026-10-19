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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- QR Code library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>

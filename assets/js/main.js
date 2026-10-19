/**
 * TCC RRS – Main JavaScript
 */

// ── Sidebar toggle (mobile) ─────────────────────────────────────────────────
(function () {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');

  function openSidebar() {
    sidebar?.classList.add('show');
    overlay?.classList.add('show');
  }
  function closeSidebar() {
    sidebar?.classList.remove('show');
    overlay?.classList.remove('show');
  }

  toggleBtn?.addEventListener('click', openSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // Close on nav-link click (mobile)
  sidebar?.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 992) closeSidebar();
    });
  });
})();

// ── Auto-dismiss alerts after 5 s ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 6000);
  });
});

// ── Confirm before delete/deactivate actions ────────────────────────────────
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  const msg = btn.dataset.confirm || 'Are you sure?';
  if (!confirm(msg)) e.preventDefault();
});

// ── Form validation feedback ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });
});

// ── QR Code Generation (requires qrcode.min.js) ─────────────────────────────
function generateQrCode(targetElement, url, options = {}) {
  if (typeof QRCode === 'undefined') return;
  const defaults = {
    text:         url,
    width:        200,
    height:       200,
    colorDark:    '#0f2a56',
    colorLight:   '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
  };
  const target = typeof targetElement === 'string'
    ? document.getElementById(targetElement)
    : targetElement;
  if (!target) return;
  target.innerHTML = '';
  new QRCode(target, { ...defaults, ...options });
}

// ── Download QR Code ────────────────────────────────────────────────────────
function downloadQr(containerId, filename) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const canvas = container.querySelector('canvas');
  const img    = container.querySelector('img');
  let src;
  if (canvas) {
    src = canvas.toDataURL('image/png');
  } else if (img) {
    src = img.src;
  } else {
    alert('QR code not ready yet.');
    return;
  }
  const a  = document.createElement('a');
  a.href   = src;
  a.download = filename || 'qrcode.png';
  a.click();
}

// ── Print QR section ────────────────────────────────────────────────────────
function printSection(sectionId) {
  const section = document.getElementById(sectionId);
  if (!section) return;
  const win = window.open('', '_blank');
  win.document.write(`
    <!DOCTYPE html><html><head>
    <title>Print</title>
    <style>
      body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }
      img, canvas { max-width: 250px; display: block; margin: 16px auto; }
    </style>
    </head><body>` + section.innerHTML + `</body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); win.close(); }, 400);
}

// ── Character counter ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-max-chars]').forEach(el => {
    const max     = parseInt(el.dataset.maxChars);
    const counter = document.createElement('div');
    counter.className = 'form-text text-end';
    counter.textContent = `0 / ${max}`;
    el.parentNode.insertBefore(counter, el.nextSibling);
    el.addEventListener('input', () => {
      const len = el.value.length;
      counter.textContent = `${len} / ${max}`;
      counter.classList.toggle('text-danger', len > max);
    });
  });
});

// ── Search/filter table rows ─────────────────────────────────────────────────
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Tooltip initialization ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });
});

// ── Copy to clipboard ────────────────────────────────────────────────────────
function copyToClipboard(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copied!';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');
    setTimeout(() => {
      btn.innerHTML = original;
      btn.classList.remove('btn-success');
      btn.classList.add('btn-outline-secondary');
    }, 2000);
  });
}

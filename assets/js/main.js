/**
 * TCC RRS – Main JavaScript
 */
document.documentElement.classList.add('js');

// ── Page loader: seal + spinning arc, dismissed once the page loads ──────────
(function () {
  const loader = document.getElementById('pageLoader');
  const root   = document.documentElement;
  // .loaded releases the staggered page entrance — failsafe so a slow asset
  // or a script error can never leave content invisible.
  const failsafe = setTimeout(() => root.classList.add('loaded'), 3000);
  const markLoaded = () => { clearTimeout(failsafe); root.classList.add('loaded'); };
  if (!loader) { markLoaded(); return; }
  const t0 = performance.now();
  const MIN = 900;                                   // show the seal spinner ~1s
  const hide = () => {
    const wait = Math.max(0, MIN - (performance.now() - t0));
    setTimeout(() => {
      loader.classList.add('done');
      markLoaded();
      setTimeout(() => loader.remove(), 600);
    }, wait);
  };
  if (document.readyState === 'complete') hide();
  else window.addEventListener('load', hide, { once: true });
})();

// ── Lenis smooth scroll (all pages; skipped under reduced-motion) ──────────
// Idempotent — some pages may load this file twice via partials.
if (typeof Lenis !== 'undefined' && !window.__tccLenis
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.__tccLenis = new Lenis({
        duration: 1.1,
        easing: t => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smoothWheel: true,
    });
    (function raf(time) {
        window.__tccLenis.raf(time);
        requestAnimationFrame(raf);
    })(performance.now());
    // Keep in-page anchor links working through the smoothed scroll
    document.addEventListener('click', e => {
        const a = e.target.closest('a[href^="#"]');
        if (!a) return;
        const target = document.querySelector(a.getAttribute('href'));
        if (!target) return;
        e.preventDefault();
        window.__tccLenis.scrollTo(target, { offset: -80 });
    });
}

// ── Sidebar toggle: mobile drawer / desktop collapse-to-rail ─────────────────
(function () {
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');
  const root      = document.documentElement;
  const KEY       = 'tccSidebarCollapsed';

  function openSidebar() {
    sidebar?.classList.add('show');
    overlay?.classList.add('show');
  }
  function closeSidebar() {
    sidebar?.classList.remove('show');
    overlay?.classList.remove('show');
  }

  toggleBtn?.addEventListener('click', function () {
    if (window.innerWidth < 992) {
      openSidebar();
    } else {
      root.classList.toggle('sidebar-collapsed');
      try {
        localStorage.setItem(KEY, root.classList.contains('sidebar-collapsed') ? '1' : '0');
      } catch (_) {}
    }
  });
  overlay?.addEventListener('click', closeSidebar);

  // Icon-rail tooltips: stash link text for CSS ::after bubbles
  sidebar?.querySelectorAll('.nav-link').forEach(link => {
    link.dataset.tip = link.textContent.trim();
    link.addEventListener('click', () => {
      if (window.innerWidth < 992) closeSidebar();
    });
  });
})();

// ── Theme switch + fullscreen ────────────────────────────────────────────────
(function () {
  const root = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  const fsBtn    = document.getElementById('fullscreenToggle');
  const KEY = 'tccTheme';

  function syncThemeBtn() {
    const dark = root.getAttribute('data-theme') === 'dark';
    themeBtn?.setAttribute('aria-checked', dark ? 'true' : 'false');
    const ico = themeBtn?.querySelector('.ts-knob i');
    if (ico) ico.className = 'bi ' + (dark ? 'bi-moon-fill' : 'bi-sun-fill');
  }
  syncThemeBtn();

  themeBtn?.addEventListener('click', function () {
    const dark = root.getAttribute('data-theme') !== 'dark';
    if (dark) root.setAttribute('data-theme', 'dark');
    else root.removeAttribute('data-theme');
    try { localStorage.setItem(KEY, dark ? 'dark' : 'light'); } catch (_) {}
    syncThemeBtn();
  });

  fsBtn?.addEventListener('click', function () {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen?.().catch(() => {});
    } else {
      document.exitFullscreen?.();
    }
  });
  document.addEventListener('fullscreenchange', function () {
    const ico = fsBtn?.querySelector('i');
    if (ico) ico.className = 'bi ' + (document.fullscreenElement ? 'bi-fullscreen-exit' : 'bi-fullscreen');
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
    colorDark:    '#0a3524',
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

// ── Password visibility toggle: <button data-pwd-toggle="#fieldId"> ─────────
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-pwd-toggle]');
  if (!btn) return;
  const field = document.querySelector(btn.dataset.pwdToggle);
  if (!field) return;
  const show = field.type === 'password';
  field.type = show ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});

// ── Code chips: click-to-copy ────────────────────────────────────────────────
function uiCodeCopy(el) {
  const code = el.dataset.copy;
  if (!code || !navigator.clipboard) return;
  navigator.clipboard.writeText(code).then(() => {
    const icon = el.querySelector('i');
    el.classList.add('copied');
    if (icon) icon.className = 'bi bi-check-lg';
    setTimeout(() => {
      el.classList.remove('copied');
      if (icon) icon.className = 'bi bi-copy';
    }, 1400);
  });
}
document.addEventListener('click', e => {
  const el = e.target.closest('.ui-code[data-copy]');
  if (el) uiCodeCopy(el);
});
document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('ui-code')) {
    uiCodeCopy(e.target);
  }
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

// ── Portal nav: shadow on scroll ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const nav = document.querySelector('.portal-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // ── Scroll reveal (landing page) ─────────────────────────────────────────
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length && 'IntersectionObserver' in window
      && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => io.observe(el));
  } else {
    revealEls.forEach(el => el.classList.add('in'));
  }

  // ── Stat count-up (landing page) ─────────────────────────────────────────
  const counters = document.querySelectorAll('.lp-stat-num[data-count]');
  if (counters.length && 'IntersectionObserver' in window
      && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const animateCount = (el) => {
      const target = parseInt(el.dataset.count, 10) || 0;
      const dur = 900;
      const t0 = performance.now();
      const tick = (t) => {
        const p = Math.min((t - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.childNodes[0].nodeValue = Math.round(target * eased).toLocaleString();
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };
    const cio = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const el = entry.target;
          if (el.dataset.plus === '1' && !el.querySelector('.plus')) {
            el.insertAdjacentHTML('beforeend', '<span class="plus">+</span>');
          }
          animateCount(el);
          cio.unobserve(el);
        }
      });
    }, { threshold: 0.4 });
    counters.forEach(el => {
      el.textContent = '0';
      cio.observe(el);
    });
  } else {
    counters.forEach(el => {
      el.textContent = (parseInt(el.dataset.count, 10) || 0).toLocaleString();
      if (el.dataset.plus === '1') el.insertAdjacentHTML('beforeend', '<span class="plus">+</span>');
    });
  }
});

/* ── Topbar search: "/" focuses the field ── */
document.addEventListener('keydown', function (e) {
  if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
  const t = e.target;
  if (t.matches('input, textarea, select, [contenteditable]')) return;
  const s = document.querySelector('.topbar-search input');
  if (s) { e.preventDefault(); s.focus(); }
});

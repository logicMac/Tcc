<?php
/**
 * Floating AI assistant widget (Groq). Included by footer partials.
 * Renders only when the assistant is enabled + keyed in System Settings.
 */
if (!function_exists('aiEnabled')) {
    require_once __DIR__ . '/../ai.php';
}
if (!aiEnabled()) return;
?>
<div class="ai-rail" id="aiFab" role="button" tabindex="0" aria-label="Open AI assistant">
  <span class="ai-rail-tip">Chat with TCC Assist</span>
  <svg class="ai-owl" viewBox="0 0 64 64" aria-hidden="true">
    <ellipse cx="32" cy="36" rx="20" ry="22" fill="#e8c04b"/>
    <path d="M15 22 11 8l13 8zM49 22 53 8l-13 8z" fill="#d4a832"/>
    <ellipse cx="32" cy="45" rx="12" ry="13" fill="#f4d97e"/>
    <path d="M13 34c-3 8 1 16 6 19-6-8-5-15-6-19zM51 34c3 8-1 16-6 19 6-8 5-15 6-19z" fill="#d4a832"/>
    <circle cx="24" cy="28" r="9" fill="#fff"/>
    <circle cx="40" cy="28" r="9" fill="#fff"/>
    <circle cx="24" cy="28" r="4" fill="#123f2a" class="ai-pupil"/>
    <circle cx="40" cy="28" r="4" fill="#123f2a" class="ai-pupil"/>
    <circle cx="25.5" cy="26.5" r="1.3" fill="#fff"/>
    <circle cx="41.5" cy="26.5" r="1.3" fill="#fff"/>
    <path d="M32 33l-4.5 6h9z" fill="#123f2a"/>
    <path d="M22 58l-2 4M32 59v5M42 58l2 4" stroke="#123f2a" stroke-width="2.5" stroke-linecap="round" fill="none"/>
  </svg>
  <span class="ai-rail-label">AI Assist</span>
</div>
<div class="ai-panel d-none" id="aiPanel" aria-live="polite">
  <div class="ai-head">
    <div class="ai-head-id">
      <span class="ai-dot ai-dot-owl">
        <svg viewBox="0 0 64 64" width="20" height="20" aria-hidden="true">
          <ellipse cx="32" cy="36" rx="20" ry="22" fill="#e8c04b"/>
          <circle cx="24" cy="28" r="9" fill="#fff"/><circle cx="40" cy="28" r="9" fill="#fff"/>
          <circle cx="24" cy="28" r="4" fill="#123f2a"/><circle cx="40" cy="28" r="4" fill="#123f2a"/>
          <path d="M32 33l-4.5 6h9z" fill="#123f2a"/>
        </svg>
      </span>
      <div>
        <div class="ai-title">TCC Assist</div>
        <div class="ai-sub">Ask about registrations, documents &amp; tracking</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <button type="button" class="ai-voice-toggle" id="aiVoiceToggle" aria-label="Toggle spoken replies" title="Spoken replies">
        <i class="bi bi-volume-up"></i>
      </button>
      <button type="button" class="ai-close" id="aiClose" aria-label="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
  <div class="ai-msgs" id="aiMsgs"></div>
  <form class="ai-form" id="aiForm">
    <button type="button" class="ai-mic d-none" id="aiMic" aria-label="Voice input" title="Speak">
      <i class="bi bi-mic-fill"></i>
    </button>
    <input type="text" id="aiInput" class="ai-input" maxlength="1000"
           placeholder="Ask me anything…" autocomplete="off">
    <button type="submit" class="ai-send" aria-label="Send">
      <i class="bi bi-send-fill"></i>
    </button>
  </form>
</div>
<script>
(function () {
  var fab   = document.getElementById('aiFab'),
      panel = document.getElementById('aiPanel'),
      close = document.getElementById('aiClose'),
      form  = document.getElementById('aiForm'),
      input = document.getElementById('aiInput'),
      msgs  = document.getElementById('aiMsgs'),
      KEY   = 'tcc_ai_history',
      hist  = [];

  try { hist = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { hist = []; }

  function save() { try { sessionStorage.setItem(KEY, JSON.stringify(hist.slice(-20))); } catch (e) {} }

  /* Lightweight markdown → safe HTML (escaped first, then formatted) */
  function fmt(text) {
    var esc = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    esc = esc.replace(/```([\s\S]*?)```/g, function (m, c) { return '<pre>' + c.trim() + '</pre>'; });
    esc = esc.replace(/`([^`\n]+)`/g, '<code>$1</code>')
             .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
             .replace(/(^|\s)\*([^*\n]+)\*/g, '$1<em>$2</em>');
    var lines = esc.split(/\n/), out = [], inUl = false, inOl = false;
    function closeLists() {
      if (inUl) { out.push('</ul>'); inUl = false; }
      if (inOl) { out.push('</ol>'); inOl = false; }
    }
    lines.forEach(function (l) {
      var m;
      if ((m = l.match(/^\s*[-•*]\s+(.*)/))) {
        if (!inUl) { closeLists(); out.push('<ul>'); inUl = true; }
        out.push('<li>' + m[1] + '</li>');
      } else if ((m = l.match(/^\s*\d+[.)]\s+(.*)/))) {
        if (!inOl) { closeLists(); out.push('<ol>'); inOl = true; }
        out.push('<li>' + m[1] + '</li>');
      } else if ((m = l.match(/^#{1,4}\s*(.+)/))) {
        closeLists(); out.push('<div class="ai-h">' + m[1] + '</div>');
      } else if (l.trim() === '') {
        closeLists(); out.push('<div class="ai-gap"></div>');
      } else {
        closeLists(); out.push('<div>' + l + '</div>');
      }
    });
    closeLists();
    return out.join('');
  }

  function addMsg(role, text) {
    var d = document.createElement('div');
    d.className = 'ai-msg ai-' + role;
    if (role === 'assistant') d.innerHTML = fmt(text);
    else d.textContent = text;
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function greet() {
    if (!msgs.children.length) {
      addMsg('assistant', 'Hi! I\'m TCC Assist. Ask me about registrations, document requests, tracking codes, or anything on this page.');
    }
  }

  hist.forEach(function (m) { addMsg(m.role, m.content); });
  greet();

  function toggle(open) {
    panel.classList.toggle('d-none', !open);
    if (open) { greet(); input.focus(); }
  }

  /* ── Draggable rail (vertical, right edge) ── */
  var dragStart = null, dragging = false, railPct = null;
  function setRailTop(pct) {
    railPct = Math.min(90, Math.max(10, pct));
    fab.style.top = railPct + '%';
    fab.style.transform = 'translateY(-50%)';
  }
  var savedTop = parseFloat(localStorage.getItem('tcc_ai_rail_top'));
  if (!isNaN(savedTop)) setRailTop(savedTop);

  fab.addEventListener('pointerdown', function (e) {
    dragStart = e.clientY; dragging = false;
    fab.setPointerCapture && fab.setPointerCapture(e.pointerId);
  });
  fab.addEventListener('pointermove', function (e) {
    if (dragStart === null) return;
    if (Math.abs(e.clientY - dragStart) > 6) {
      dragging = true;
      fab.classList.add('dragging');
    }
    if (dragging) setRailTop(e.clientY / window.innerHeight * 100);
  });
  fab.addEventListener('pointerup', function () {
    if (dragStart === null) return;
    if (dragging) {
      localStorage.setItem('tcc_ai_rail_top', railPct);
    } else {
      toggle(panel.classList.contains('d-none'));
    }
    dragStart = null; dragging = false;
    fab.classList.remove('dragging');
  });
  fab.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(true); } });
  close.addEventListener('click', function () { toggle(false); });

  /* ── Voice: spoken replies toggle + mic input ── */
  var voiceToggle = document.getElementById('aiVoiceToggle'),
      mic         = document.getElementById('aiMic'),
      speakOn     = false;

  function speak(text) {
    if (!speakOn || !('speechSynthesis' in window)) return;
    speechSynthesis.cancel();
    var u = new SpeechSynthesisUtterance(text.replace(/[*_`#]/g, ''));
    u.rate = 1; u.pitch = 1;
    speechSynthesis.speak(u);
  }

  voiceToggle.addEventListener('click', function () {
    speakOn = !speakOn;
    voiceToggle.classList.toggle('on', speakOn);
    if (!speakOn && 'speechSynthesis' in window) speechSynthesis.cancel();
  });
  if (!('speechSynthesis' in window)) voiceToggle.classList.add('d-none');

  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SR) {
    mic.classList.remove('d-none');
    var rec = new SR(), listening = false;
    rec.lang = 'en-US';
    rec.interimResults = true;
    rec.onresult = function (e) {
      var t = '';
      for (var i = 0; i < e.results.length; i++) t += e.results[i][0].transcript;
      input.value = t;
      if (e.results[e.results.length - 1].isFinal) stopRec(true);
    };
    rec.onend = function () { mic.classList.remove('listening'); listening = false; };
    rec.onerror = function () { stopRec(false); };
    function stopRec(submit) {
      if (!listening) return;
      listening = false;
      try { rec.stop(); } catch (e) {}
      mic.classList.remove('listening');
      if (submit && input.value.trim()) form.requestSubmit();
    }
    mic.addEventListener('click', function () {
      if (listening) { stopRec(true); return; }
      listening = true;
      mic.classList.add('listening');
      input.value = '';
      try { rec.start(); } catch (e) { stopRec(false); }
    });
  }
  /* ── /Voice ── */

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    input.value = '';
    addMsg('user', text);
    hist.push({ role: 'user', content: text }); save();

    var thinking = document.createElement('div');
    thinking.className = 'ai-msg ai-assistant ai-thinking';
    thinking.textContent = 'Thinking…';
    msgs.appendChild(thinking);
    msgs.scrollTop = msgs.scrollHeight;

    fetch('<?= BASE_URL ?>/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: text,
        page:    location.pathname + ' — ' + document.title,
        history: hist.slice(-8)
      })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        thinking.remove();
        var reply = res.j.reply || res.j.error || 'Something went wrong.';
        addMsg('assistant', reply);
        hist.push({ role: 'assistant', content: reply }); save();
        speak(reply);
      })
      .catch(function () {
        thinking.remove();
        addMsg('assistant', 'Connection error — please try again.');
      });
  });
})();
</script>

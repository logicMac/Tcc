<?php
/**
 * TCC RRS – Student Public Portal (Landing Page)
 * Direction: the page is the object — the registrar's case-file stack.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();
$schoolName = getSetting('school_name', 'Tampakan Community College');
$schoolYear = getSetting('current_school_year', '2026-2027');
$semester   = getSetting('current_semester', '1st Semester');
$activePage = 'home';
$pageTitle  = 'Student Portal';

// ── Live data ────────────────────────────────────────────────────────────────
$programs   = db()->fetchAll("SELECT name FROM programs WHERE is_active = 1 ORDER BY name");
$docTypes   = getActiveDocumentTypes();
$docByCode  = [];
foreach ($docTypes as $d) { $docByCode[$d['code']] = $d; }
$servedCount = (int)(db()->fetchValue(
    "SELECT (SELECT COUNT(*) FROM pre_registrations) + (SELECT COUNT(*) FROM document_requests) AS c"
) ?? 0);

// Fee board: bars grouped the way the registrar posts the price list
$feeGroups = [
    'Records & transcripts'    => ['TOR', 'FORM137', 'FORM138'],
    'Enrollment certificates'  => ['COE', 'COR'],
    'Character & completion'   => ['COG', 'CERTGRAD'],
    'Moving on'                => ['DIPLOMA', 'HONORABLE'],
    'Copies & other requests'  => ['AUTHCOPY', 'OTHER'],
];

// Menu rows (services) — each carries a live preview of the real page
$menuGroups = [
    'Enroll' => [
        ['title' => 'Pre-registration', 'value' => 'Free',
         'desc' => 'The admission form, online — about five minutes.',
         'link' => BASE_URL . '/student/pre_registration.php', 'preview' => 'enroll'],
    ],
    'Documents' => [
        ['title' => 'Document request', 'value' => '₱50–300',
         'desc' => 'TOR, certificates, records — pay when you claim.',
         'link' => BASE_URL . '/student/document_request.php', 'preview' => 'docs'],
    ],
    'Follow up' => [
        ['title' => 'Status tracking', 'value' => '24/7',
         'desc' => 'Type the code or scan the QR on your slip.',
         'link' => BASE_URL . '/student/track.php', 'preview' => 'track'],
        ['title' => 'Verify a document', 'value' => 'Free',
         'desc' => 'Confirm a released document is authentic.',
         'link' => BASE_URL . '/verify.php', 'preview' => 'verify'],
    ],
];

$journey = [
    ['cap' => 'The form',
     'txt' => 'Personal details, contact, program — answered online, not at a counter.',
     'img' => 'columbia-university-school-of-library-service-student.jpg',
     'alt' => 'Vintage student record documents laid out on a desk', 'gap' => 0],
    ['cap' => 'The slip',
     'txt' => 'A tracking code and QR are issued the moment you submit.',
     'img' => 'manila-folder.jpg',
     'alt' => 'A manila folder held closed by a paperclip', 'gap' => 84],
    ['cap' => 'The file',
     'txt' => 'Your request lands in the registrar\u2019s queue — the cabinet, now digital.',
     'img' => 'thirty-six-file-u-s-document-cabinet.jpg',
     'alt' => 'An 1892 engraving of a document filing cabinet in use', 'gap' => 36],
    ['cap' => 'The check',
     'txt' => 'Staff review, stamp, and annotate. Every change is logged.',
     'img' => 'hand-in-filing-cabinet.jpg',
     'alt' => 'A hand pulling a card from a filing cabinet index', 'gap' => 110],
    ['cap' => 'The wait, shortened',
     'txt' => 'Watch the status move from Pending to Ready — no standing in line.',
     'img' => 'manila-envelopes.png',
     'alt' => 'A stack of orange manila envelopes', 'gap' => 24],
    ['cap' => 'The counter, once',
     'txt' => 'One trip to the office: show your code, pay, claim.',
     'img' => 'basic-high-school-and-henderson-nevada-school.jpg',
     'alt' => 'A 1951 photograph of a school registrar\u2019s office door', 'gap' => 72],
];

$faqs = [
    ['tab' => 'Account',    'tone' => 'green',
     'q' => 'Do I need an account?',
     'a' => 'No. You can submit a pre-registration or document request and track it as a guest. An account simply gathers all your submissions in one place.'],
    ['tab' => 'Tracking',   'tone' => 'gold',
     'q' => 'How do I check my status?',
     'a' => 'Enter the tracking code printed on your slip (TCC-REG-… or TCC-DOC-…) on the Track page, or scan its QR code — it works from any phone, no sign-in needed.'],
    ['tab' => 'Fees',       'tone' => 'paper',
     'q' => 'How much do documents cost?',
     'a' => '₱50 to ₱300 depending on the document — the full schedule is on this page under Fees. You pay at the Registrar\'s Office when you claim. Pre-registration is free.'],
    ['tab' => 'Claiming',   'tone' => 'green',
     'q' => 'When do I come to the office?',
     'a' => 'Only once — when your status reads Ready for Release. Bring a valid ID and your tracking code or QR.'],
    ['tab' => 'Corrections','tone' => 'gold',
     'q' => 'What if I made a mistake?',
     'a' => 'If your submission is marked Needs Correction, the staff remarks say exactly what to fix. Resubmit with the same email and it still links to your account.'],
    ['tab' => 'Authenticity','tone' => 'paper',
     'q' => 'Can a document be faked?',
     'a' => 'Every released document carries a TCC-VRF verification code and QR. Anyone can confirm it against registrar records on the Verify page.'],
];

// Zigzag clip-path for the menu sheet's torn edges (generated, not guessed)
function zigzagClip(int $teeth = 26, float $depthPct = 0.9): string {
    $pts = [];
    $step = 100 / $teeth;
    for ($i = 0; $i <= $teeth; $i++) {
        $x = $i * $step;
        $pts[] = sprintf('%.2f%% %.2f%%', $x, $i % 2 ? $depthPct : 0);
    }
    for ($i = $teeth; $i >= 0; $i--) {
        $x = $i * $step;
        $pts[] = sprintf('%.2f%% %.2f%%', $x, $i % 2 ? 100 - $depthPct : 100);
    }
    return 'polygon(' . implode(',', $pts) . ')';
}
$sheetClip = zigzagClip();

include __DIR__ . '/../includes/partials/portal_head.php';
include __DIR__ . '/../includes/partials/portal_nav.php';
?>

<!-- ══ Hero: the registrar's case-file stack ══════════════════════════════ -->
<section class="doss-hero">
  <div class="doss-top container">
    <h1 class="doss-title">Skip the line.</h1>
    <p class="doss-sub">Enroll, request documents, and track every submission —
      the <?= e($schoolName) ?> registrar, online for A.Y. <?= e($schoolYear) ?> · <?= e($semester) ?>.</p>
  </div>

  <div class="doss-stack">
    <?php
    $folders = [
      ['tone' => 'g800', 'tab' => 'Services',  'href' => '#services', 'pos' => '6%'],
      ['tone' => 'gold', 'tab' => 'Fees',      'href' => '#fees',     'pos' => 'min(30%, calc(100% - 210px))'],
      ['tone' => 'g600', 'tab' => 'The journey','href' => '#journey',  'pos' => 'min(56%, calc(100% - 210px))'],
      ['tone' => 'gld2', 'tab' => 'Questions', 'href' => '#questions','pos' => 'min(80%, calc(100% - 145px))'],
    ];
    foreach ($folders as $f): ?>
    <a class="doss-folder <?= $f['tone'] ?>" href="<?= $f['href'] ?>">
      <span class="doss-tab" style="left: <?= $f['pos'] ?>"><?= e($f['tab']) ?></span>
    </a>
    <?php endforeach; ?>

    <!-- Front folder: the live slip -->
    <div class="doss-folder front" id="track">
      <div class="container doss-front-in">
        <div class="doss-front-grid">
          <div class="doss-front-copy">
            <b>Already submitted?</b>
            <p>Type the code on your slip — or scan its QR on the Track page —
              and watch the status move.</p>
          </div>
          <form action="<?= BASE_URL ?>/student/track.php" method="GET" class="doss-track">
            <input type="text" name="code" required
                   placeholder="TCC-REG-<?= e(substr($schoolYear, 0, 4)) ?>-000001"
                   autocomplete="off" aria-label="Tracking code">
            <button type="submit">Track status</button>
          </form>
          <div class="doss-actions">
            <a href="<?= BASE_URL ?>/student/pre_registration.php">Pre-register</a>
            <a href="<?= BASE_URL ?>/student/document_request.php">Request a document</a>
            <a href="<?= BASE_URL ?>/verify.php">Verify a document</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ Proof: the printed register ════════════════════════════════════════ -->
<section class="pg-reg">
  <div class="reg-card reveal">
    <h2 class="reg-title reveal" style="--rd:.08s">Programs on file</h2>
    <img src="<?= BASE_URL ?>/assets/an-education-volunteer-with-his-high-school.jpg"
         width="1920" height="1275" alt="Students gathered with a teacher in a Philippine classroom"
         class="reg-photo reveal" style="--rd:.14s" loading="lazy">
    <p class="reg-names reveal" style="--rd:.2s">
      <?php foreach ($programs as $i => $p): ?>
        <?= e($p['name']) ?><?= $i < count($programs) - 1 ? ' · ' : '' ?>
      <?php endforeach; ?>
    </p>
    <p class="reg-foot reveal" style="--rd:.26s">
      <?= number_format($servedCount) ?> submissions on file ·
      <?= count($docTypes) ?> documents in the catalogue · open online, always
    </p>
  </div>
</section>

<!-- ══ Services: capabilities as a menu ═══════════════════════════════════ -->
<section class="pg-menu" id="services">
  <div class="container menu-grid">
    <div class="menu-sheet reveal" style="clip-path: <?= $sheetClip ?>">
      <h2 class="reveal" style="--rd:.08s">Services</h2>
      <?php $gi = 0; foreach ($menuGroups as $group => $items): $gi++; ?>
      <div class="menu-group reveal" style="--rd:<?= .1 + $gi * .07 ?>s">
        <h3><?= e($group) ?></h3>
        <?php foreach ($items as $it): ?>
        <a class="menu-row" href="<?= e($it['link']) ?>" data-preview="<?= e($it['preview']) ?>">
          <span class="menu-item">
            <b><?= e($it['title']) ?></b>
            <span class="menu-desc"><?= e($it['desc']) ?></span>
          </span>
          <span class="menu-leader" aria-hidden="true"></span>
          <span class="menu-value"><?= e($it['value']) ?></span>
          <img class="menu-inline" loading="lazy" alt=""
               src="<?= BASE_URL ?>/assets/previews/<?= e($it['preview']) ?>.png">
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <aside class="menu-preview" id="menuPreview" aria-hidden="true">
      <?php foreach (['enroll','docs','track','verify'] as $pv): ?>
      <img data-key="<?= $pv ?>" src="<?= BASE_URL ?>/assets/previews/<?= $pv ?>.png"
           alt="Preview of the <?= $pv ?> page" loading="lazy">
      <?php endforeach; ?>
    </aside>
  </div>
</section>

<!-- ══ Fees: the posted price list ════════════════════════════════════════ -->
<section class="pg-fees" id="fees">
  <div class="container fees-col">
    <h2 class="fees-word reveal" aria-label="Fees">FEES</h2>
    <div class="fees-bars reveal">
      <?php $first = true; $fi = 0; foreach ($feeGroups as $label => $codes): $fi++; ?>
      <details class="fee-bar reveal" style="--rd:<?= .08 + $fi * .06 ?>s" <?= $first ? 'open' : '' ?>>
        <summary><span><?= e($label) ?></span><i class="bi bi-plus"></i></summary>
        <ul class="fee-items">
          <?php foreach ($codes as $code): if (!isset($docByCode[$code])) continue; $d = $docByCode[$code]; ?>
          <li>
            <span class="fee-name"><?= e($d['name']) ?></span>
            <span class="fee-meta"><?= (int)$d['processing_days'] ?> day<?= $d['processing_days'] == 1 ? '' : 's' ?></span>
            <span class="fee-price"><?= $d['fee'] > 0 ? '₱' . number_format($d['fee'], 0) : 'Free' ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php $first = false; endforeach; ?>
      <details class="fee-bar reveal" style="--rd:<?= .08 + ($fi + 1) * .06 ?>s">
        <summary><span>Enrollment</span><i class="bi bi-plus"></i></summary>
        <ul class="fee-items">
          <li>
            <span class="fee-name">Pre-registration for enrollment</span>
            <span class="fee-meta">online</span>
            <span class="fee-price">Free</span>
          </li>
          <li class="fee-cta"><a href="<?= BASE_URL ?>/student/pre_registration.php">Start a pre-registration →</a></li>
        </ul>
      </details>
    </div>
    <p class="fees-note reveal" style="--rd:.2s">Paid at the Registrar's Office when you claim — nothing to pay online.</p>
  </div>
</section>

<!-- ══ The journey: a drag row of moments ═════════════════════════════════ -->
<section class="pg-journey" id="journey">
  <div class="container jrny-head reveal">
    <h2>From form to file</h2>
    <div class="jrny-btns">
      <button type="button" id="jrnyPrev" aria-label="Scroll back"><i class="bi bi-arrow-left"></i></button>
      <button type="button" id="jrnyNext" aria-label="Scroll forward"><i class="bi bi-arrow-right"></i></button>
    </div>
  </div>
  <div class="jrny-progress reveal" style="--rd:.1s"><span id="jrnyBar"></span></div>
  <div class="jrny-row" id="jrnyRow">
    <?php foreach ($journey as $ji => $m): ?>
    <figure class="jrny-col reveal" style="--rd:<?= $ji * .07 ?>s">
      <figcaption>
        <b><?= e($m['cap']) ?></b>
        <p><?= e($m['txt']) ?></p>
      </figcaption>
      <div style="height: <?= (int)$m['gap'] ?>px" aria-hidden="true"></div>
      <img src="<?= BASE_URL ?>/assets/<?= e($m['img']) ?>" alt="<?= e($m['alt']) ?>" loading="lazy">
    </figure>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══ Questions: tabbed index cards ══════════════════════════════════════ -->
<section class="pg-faq" id="questions">
  <div class="container faq-wrap">
    <h2 class="reveal">Common questions</h2>
    <div class="faq-stack" id="faqStack">
      <?php foreach ($faqs as $i => $f): ?>
      <div class="faq-card <?= $f['tone'] ?> <?= $i === 0 ? 'open' : '' ?> reveal"
           style="--tab-x: <?= $i * 120 ?>px; --rd:<?= .08 + $i * .07 ?>s">
        <button type="button" class="faq-tab" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
          <span class="faq-tab-name"><?= e($f['tab']) ?></span>
          <span class="faq-q"><?= e($f['q']) ?></span>
        </button>
        <div class="faq-a"><p><?= e($f['a']) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ CTA: the reply card ════════════════════════════════════════════════ -->
<section class="pg-cta">
  <div class="container cta-grid">
    <div class="cta-aside">
      <h2 class="reveal">Come to the office once.</h2>
      <p class="reveal" style="--rd:.1s">Submit tonight, track tomorrow, claim when it's ready — the Registrar's Office, online.</p>
    </div>
    <div class="reply-card reveal" style="--rd:.16s">
      <h2>Ready when you are</h2>
      <p>Start a submission now — the Registrar takes it from there.</p>
      <a class="reply-btn" href="<?= BASE_URL ?>/student/pre_registration.php">Begin a submission</a>
      <div class="reply-foot">
        <div id="replyQr" class="reply-qr"></div>
        <a class="reply-alt" href="<?= BASE_URL ?>/student/document_request.php">Or request a document instead</a>
      </div>
    </div>
  </div>
  <p class="pg-credits container reveal" style="--rd:.2s">
    Archival photographs: Wikimedia Commons &amp; Openverse, public domain / CC — see assets/credits.json.
    Page previews are live screenshots of this system.
  </p>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  generateQrCode('replyQr', '<?= BASE_URL ?>/student/track.php', { width: 72, height: 72 });

  /* Journey row: buttons + progress bar */
  var row = document.getElementById('jrnyRow'),
      bar = document.getElementById('jrnyBar');
  function jrnyProgress() {
    var max = row.scrollWidth - row.clientWidth;
    bar.style.width = (max > 0 ? (row.scrollLeft / max) * 100 : 100) + '%';
  }
  row.addEventListener('scroll', jrnyProgress, { passive: true });
  jrnyProgress();
  document.getElementById('jrnyPrev').addEventListener('click', function () {
    row.scrollBy({ left: -320, behavior: 'smooth' });
  });
  document.getElementById('jrnyNext').addEventListener('click', function () {
    row.scrollBy({ left: 320, behavior: 'smooth' });
  });

  /* Index cards: one open at a time */
  var cards = document.querySelectorAll('#faqStack .faq-card');
  cards.forEach(function (card) {
    card.querySelector('.faq-tab').addEventListener('click', function () {
      var wasOpen = card.classList.contains('open');
      cards.forEach(function (c) {
        c.classList.remove('open');
        c.querySelector('.faq-tab').setAttribute('aria-expanded', 'false');
      });
      if (!wasOpen) {
        card.classList.add('open');
        card.querySelector('.faq-tab').setAttribute('aria-expanded', 'true');
      }
    });
  });

  /* Menu: index-with-preview on fine pointers */
  var preview = document.getElementById('menuPreview');
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches && preview) {
    var imgs = preview.querySelectorAll('img');
    var rows = document.querySelectorAll('.menu-row');
    rows.forEach(function (r) {
      r.addEventListener('mouseenter', function () {
        var key = r.dataset.preview;
        imgs.forEach(function (im) { im.classList.toggle('on', im.dataset.key === key); });
        preview.classList.add('show');
        rows.forEach(function (x) { x.classList.toggle('dim', x !== r); });
      });
      r.addEventListener('mouseleave', function () {
        preview.classList.remove('show');
        rows.forEach(function (x) { x.classList.remove('dim'); });
      });
    });
  }
});
</script>
<?php include __DIR__ . '/../includes/partials/portal_foot.php'; ?>

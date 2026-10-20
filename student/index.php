<?php
/**
 * TCC RRS – Student Public Portal (Landing Page)
 * Direction: full-bleed photo hero with a live tracking slip.
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

// ── Hero carousel photos ─────────────────────────────────────────────────────
// To add/replace photos: drop the file in assets/images/ and add its filename
// here. First slide shows first; they autoplay in order.
$heroPhotos = [
    '763907380_122117045967389150_4219642318318615129_n.jpg',
    '764593185_122117144415389150_5605248598061137387_n.jpg',
    '765671674_122117144301389150_978875098484972382_n.jpg',
    '766169334_122117145867389150_6577674959028172378_n.jpg',
    '774469259_122119886589389150_898085851488283558_n.jpg',
];

// ── Live data ────────────────────────────────────────────────────────────────
$docTypes   = getActiveDocumentTypes();
$docByCode  = [];
foreach ($docTypes as $d) { $docByCode[$d['code']] = $d; }

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

include __DIR__ . '/../includes/partials/portal_head.php';
include __DIR__ . '/../includes/partials/portal_nav.php';
?>

<!-- ══ Hero: autoplay photo carousel + actions ═══════════════════════════ -->
<section class="doss-hero">
  <div class="hero-carousel" id="heroCarousel" aria-hidden="true">
    <?php foreach ($heroPhotos as $hi => $photo): ?>
    <div class="hc-slide<?= $hi === 0 ? ' on' : '' ?>"
         style="background-image: url('<?= BASE_URL ?>/assets/images/<?= e($photo) ?>')"></div>
    <?php endforeach; ?>
    <div class="hc-scrim"></div>
  </div>

  <div class="doss-top container">
    <h1 class="doss-title">Skip the line.</h1>
    <p class="doss-sub">Enroll, request documents, and track every submission —
      the <?= e($schoolName) ?> registrar, online for A.Y. <?= e($schoolYear) ?> · <?= e($semester) ?>.</p>

  </div>

  <div class="doss-foot container">
    <?php if (count($heroPhotos) > 1): ?>
    <div class="hc-ui">
      <button type="button" class="hc-arrow" id="hcPrev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
      <div class="hc-dots" id="hcDots">
        <?php foreach ($heroPhotos as $hi => $photo): ?>
        <button type="button" class="hc-dot<?= $hi === 0 ? ' on' : '' ?>" data-i="<?= $hi ?>"
                aria-label="Photo <?= $hi + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
      <button type="button" class="hc-arrow" id="hcNext" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ══ Services ══════════════════════════════════════════════════════════ -->
<section class="lp-sec" id="services">
  <div class="container">
    <div class="lp-head reveal">
      <h2>Services</h2>
      <p>Everything the Registrar's Office does — now online, no queue.</p>
    </div>
    <div class="lp-cards">
      <?php $ci = 0; foreach ($menuGroups as $group => $items): foreach ($items as $it): $ci++; ?>
      <a class="lp-card reveal" style="--rd:<?= .05 + $ci * .06 ?>s" href="<?= e($it['link']) ?>">
        <span class="lp-card-tag"><?= e($group) ?></span>
        <b><?= e($it['title']) ?></b>
        <span class="lp-card-desc"><?= e($it['desc']) ?></span>
        <span class="lp-card-foot">
          <span class="lp-card-val"><?= e($it['value']) ?></span>
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </span>
      </a>
      <?php endforeach; endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ Fees ══════════════════════════════════════════════════════════════ -->
<section class="lp-sec lp-alt" id="fees">
  <div class="container lp-narrow">
    <div class="lp-head reveal">
      <h2>Fees</h2>
      <p>Paid at the Registrar's Office when you claim — nothing to pay online.</p>
    </div>
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
  </div>
</section>

<!-- ══ The journey: a drag row of moments ═════════════════════════════════ -->
<section class="lp-sec lp-journey" id="journey">
  <div class="container jrny-head reveal">
    <div class="lp-head jrny-head-in">
      <h2>From form to file</h2>
      <p>What happens to your submission, step by step.</p>
    </div>
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

<!-- ══ Questions ══════════════════════════════════════════════════════════ -->
<section class="lp-sec lp-alt" id="questions">
  <div class="container lp-narrow">
    <div class="lp-head reveal">
      <h2>Common questions</h2>
      <p>Short answers, straight from the registrar's desk.</p>
    </div>
    <div class="lp-acc reveal">
      <?php foreach ($faqs as $i => $f): ?>
      <details class="fee-bar reveal" style="--rd:<?= .06 + $i * .05 ?>s" <?= $i === 0 ? 'open' : '' ?>>
        <summary>
          <span><?= e($f['q']) ?></span>
          <i class="bi bi-plus"></i>
        </summary>
        <div class="lp-acc-a"><p><?= e($f['a']) ?></p></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ CTA ═══════════════════════════════════════════════════════════════ -->
<section class="lp-cta">
  <div class="container">
    <div class="lp-cta-panel reveal">
      <div class="lp-cta-copy">
        <h2>Come to the office once.</h2>
        <p>Submit tonight, track tomorrow, claim when it's ready — the Registrar's Office, online.</p>
      </div>
      <div class="lp-cta-side">
        <a class="lp-cta-btn" href="<?= BASE_URL ?>/student/pre_registration.php">Begin a submission</a>
        <a class="lp-cta-alt" href="<?= BASE_URL ?>/student/document_request.php">Or request a document instead</a>
        <div id="replyQr" class="reply-qr"></div>
      </div>
    </div>
  </div>
  <p class="pg-credits container reveal" style="--rd:.2s">
    Archival photographs: Wikimedia Commons &amp; Openverse, public domain / CC — see assets/credits.json.
  </p>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  generateQrCode('replyQr', '<?= BASE_URL ?>/student/track.php', { width: 72, height: 72 });

  /* Hero carousel: autoplay, dots, arrows, pause on hover */
  var hc = document.getElementById('heroCarousel');
  if (hc) {
    var slides = hc.querySelectorAll('.hc-slide'),
        dots   = document.querySelectorAll('.hc-dot'),
        cur = 0, timer = null;
    function go(i) {
      slides[cur].classList.remove('on');
      if (dots[cur]) dots[cur].classList.remove('on');
      cur = (i + slides.length) % slides.length;
      slides[cur].classList.add('on');
      if (dots[cur]) dots[cur].classList.add('on');
    }
    function play() {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = setInterval(function () { go(cur + 1); }, 5500);
    }
    function reset() { clearInterval(timer); play(); }
    document.getElementById('hcPrev')?.addEventListener('click', function () { go(cur - 1); reset(); });
    document.getElementById('hcNext')?.addEventListener('click', function () { go(cur + 1); reset(); });
    dots.forEach(function (d) {
      d.addEventListener('click', function () { go(+d.dataset.i); reset(); });
    });
    var hero = hc.closest('.doss-hero');
    hero.addEventListener('mouseenter', function () { clearInterval(timer); });
    hero.addEventListener('mouseleave', play);
    play();
  }

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

});
</script>
<?php include __DIR__ . '/../includes/partials/portal_foot.php'; ?>

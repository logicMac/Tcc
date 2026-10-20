<?php
/**
 * Reusable Public Portal Navbar
 * Set $activePage = 'home' | 'register' | 'document' | 'track' | 'account' before including.
 */
$schoolName  = getSetting('school_name', 'Tampakan Community College');
$schoolShort = getSetting('school_short_name', 'TCC');
$activePage  = $activePage ?? '';
$navLinks = [
    ['key' => 'home',     'href' => BASE_URL . '/student/index.php',            'label' => 'Home'],
    ['key' => 'register', 'href' => BASE_URL . '/student/pre_registration.php', 'label' => 'Pre-Register'],
    ['key' => 'document', 'href' => BASE_URL . '/student/document_request.php', 'label' => 'Documents'],
    ['key' => 'track',    'href' => BASE_URL . '/student/track.php',            'label' => 'Track'],
    ['key' => 'verify',   'href' => BASE_URL . '/verify.php',                   'label' => 'Verify'],
];
?>
<nav class="navbar navbar-expand-lg portal-nav sticky-top">
  <div class="container">
    <!-- Brand -->
    <a class="navbar-brand portal-brand" href="<?= BASE_URL ?>/student/index.php">
      <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="TCC seal"
           class="brand-logo brand-logo-sm">
      <span class="portal-brand-text">
        <span class="portal-brand-name"><?= e($schoolName) ?></span>
        <span class="portal-brand-sub">Registration &amp; Records System</span>
      </span>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#portalNavMenu"
            aria-controls="portalNavMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Links + actions -->
    <div class="collapse navbar-collapse" id="portalNavMenu">
      <ul class="navbar-nav mx-lg-auto align-items-lg-center py-2 py-lg-0">
        <?php foreach ($navLinks as $l): ?>
        <li class="nav-item">
          <a class="nav-link <?= $activePage === $l['key'] ? 'active-page' : '' ?>"
             href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="portal-nav-actions d-flex align-items-lg-center gap-2 pb-3 pb-lg-0">
        <?php if (isStudentLoggedIn()): ?>
          <a class="nav-ghost <?= $activePage === 'account' ? 'active-page' : '' ?>"
             href="<?= BASE_URL ?>/student/account.php">
            <i class="bi bi-person-circle me-1"></i>My Account
          </a>
          <a class="btn btn-portal btn-sm px-3" href="<?= BASE_URL ?>/student/logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Sign Out
          </a>
        <?php else: ?>
          <a class="nav-ghost" href="<?= BASE_URL ?>/auth/login.php">Sign In</a>
          <a class="btn btn-portal btn-sm px-3" href="<?= BASE_URL ?>/student/register.php">
            Get Started
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

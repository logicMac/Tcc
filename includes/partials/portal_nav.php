<?php
/**
 * Reusable Public Portal Navbar
 * Set $activePage = 'home' | 'register' | 'document' | 'track' before including.
 */
$schoolName  = getSetting('school_name', 'Tampakan Community College');
$activePage  = $activePage ?? '';
?>
<nav class="navbar navbar-expand-lg portal-nav sticky-top">
  <div class="container">
    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/student/index.php">
      <div style="width:34px;height:34px;background:rgba(255,255,255,.18);border-radius:8px;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-building" style="color:#fff;font-size:.95rem;"></i>
      </div>
      <div style="line-height:1.15;">
        <div class="fw-bold" style="font-size:.85rem;color:#fff;">Tampakan Community College</div>
        <div style="font-size:.6rem;color:rgba(255,255,255,.6);">Registration &amp; Records System</div>
      </div>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#portalNavMenu"
            aria-controls="portalNavMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Links -->
    <div class="collapse navbar-collapse" id="portalNavMenu">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-2 py-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'home'     ? 'active-page' : '' ?>"
             href="<?= BASE_URL ?>/student/index.php">
            <i class="bi bi-house me-1"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'register' ? 'active-page' : '' ?>"
             href="<?= BASE_URL ?>/student/pre_registration.php">
            <i class="bi bi-person-plus me-1"></i>Pre-Register
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'document' ? 'active-page' : '' ?>"
             href="<?= BASE_URL ?>/student/document_request.php">
            <i class="bi bi-file-earmark-plus me-1"></i>Request Document
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $activePage === 'track'    ? 'active-page' : '' ?>"
             href="<?= BASE_URL ?>/student/track.php">
            <i class="bi bi-search me-1"></i>Track Status
          </a>
        </li>
        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
          <a class="btn btn-portal btn-sm px-3" href="<?= BASE_URL ?>/auth/login.php">
            <i class="bi bi-lock-fill me-1"></i>Staff Login
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

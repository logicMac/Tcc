<?php
/**
 * Shared <head> for public/student pages – sets fonts, CSS, favicon.
 * Set $pageTitle before including.
 */
$pageTitle = $pageTitle ?? 'Student Portal';
$schoolName = getSetting('school_name', 'Tampakan Community College');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> – <?= e($schoolName) ?></title>
<script>document.documentElement.classList.add('js');</script>
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css<?= assetVer("assets/css/main.css") ?>">
</head>
<body class="page-shell">
<!-- Page loader -->
<div class="page-loader" id="pageLoader" aria-hidden="true">
  <div class="pl-ring">
    <svg class="pl-arc-svg" viewBox="0 0 100 100"><circle class="pl-track" cx="50" cy="50" r="44"/><circle class="pl-arc" cx="50" cy="50" r="44"/></svg>
    <img src="<?= BASE_URL ?>/assets/images/school_logo.png" alt="" class="pl-logo">
  </div>
  <div class="pl-text">Tampakan <b>Community College</b></div>
</div>


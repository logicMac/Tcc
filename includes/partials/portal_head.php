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
<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/school_logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body class="page-shell">

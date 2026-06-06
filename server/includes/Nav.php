<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/Nav.php
 * Purpose: Shared navigation partial — reusable header with auth-aware links
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$prefix   = str_repeat('../', $depth ?? 0);
$loggedIn = isset($_SESSION['user_id']);
$isAdmin  = ($loggedIn && ($_SESSION['role'] ?? '') === 'admin');
$userName = htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8');

$links = [
    'home'     => ['label' => 'Home',     'href' => $prefix . 'index.html'],
    'services' => ['label' => 'About Us', 'href' => $prefix . 'pages/services.html'],
    'schedule' => ['label' => 'Shipping Schedule', 'href' => $prefix . 'pages/schedule.php'],
    'search'   => ['label' => 'Search Shipments', 'href' => $prefix . 'pages/search.php'],
    'upload'   => ['label' => 'Upload Documents', 'href' => $prefix . 'pages/upload.html'],
    'feedback' => ['label' => 'Share Feedback', 'href' => $prefix . 'pages/feedback.html'],
];
?>
<header class="site-header">
  <div class="container header-inner">

    <!-- Brand -->
    <a class="brand" href="<?= $prefix ?>index.html" aria-label="ShipSmart Home">
      <img class="brand-logo" src="<?= $prefix ?>images/shipsmart-logo-3.svg" alt="ShipSmart logo">
      <span class="brand-name">ShipSmart</span>
    </a>

    <!-- Navigation -->
    <nav class="nav" aria-label="Main navigation">
      <ul class="nav-list">
        <?php foreach ($links as $key => $link): ?>
        <li>
          <a class="nav-link <?= ($activeNav ?? '') === $key ? 'is-active' : '' ?>"
             href="<?= $link['href'] ?>">
            <?= $link['label'] ?>
          </a>
        </li>
        <?php endforeach; ?>

        <!-- Admin Dashboard link (admin only) -->
        <?php if ($isAdmin): ?>
        <li>
          <a class="nav-link" href="<?= $prefix ?>admin/dashboard.php"
             style="color:var(--accent);font-weight:900">
            Admin Dashboard
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Auth button -->
      <div class="nav-auth-wrap">
        <?php if ($loggedIn): ?>
          <span class="nav-auth-user">Hi, <?= $userName ?></span>
          <a class="nav-auth nav-auth-logout"
             href="<?= $prefix ?>api/logout.php">Sign Out</a>
        <?php else: ?>
          <a class="nav-auth nav-auth-login"
             href="<?= $prefix ?>login.php">Login</a>
        <?php endif; ?>
      </div>
    </nav>

    <!-- Mobile toggle -->
    <button class="nav-toggle" type="button"
            aria-label="Open menu" aria-expanded="false">☰</button>
  </div>
</header>
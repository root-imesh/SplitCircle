<?php
declare(strict_types=1);
require_once __DIR__ . "/includes/bootstrap.php";
require_login();
header("Cache-Control: no-store, no-cache, must-revalidate");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f6f9fd">
  <meta name="description" content="SplitCircle — real group debt management with mutual approval.">
  <title>SplitCircle | Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka+One&amp;family=Roboto:wght@400;500;600;700;800&amp;family=Roboto+Mono:wght@400;500;600;700&amp;display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="<?= e(url('assets/css/classic.css') . '?v=8') ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/typography.css') . '?v=1') ?>">
  <script>window.SPLITCIRCLE_CSRF = <?= json_encode(csrf_token(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>; window.SPLITCIRCLE_API = <?= json_encode(url('api.php')) ?>;</script><script defer src="<?= e(url('assets/js/classic.js') . '?v=8') ?>"></script>
</head>
<body>
  <div class="app-shell" id="appShell">
    <!-- Left navigation -->
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
      <a href="#dashboard" data-page="dashboard" class="brand" id="brandLink" aria-label="SplitCircle dashboard">
        <span class="brand-mark" aria-hidden="true"><span class="brand-loop brand-loop-left"></span><span class="brand-loop brand-loop-right"></span></span>
        <span>Split<span class="brand-strong">Circle</span></span>
      </a>
      <nav class="sidebar-nav" id="sidebarNav" aria-label="Primary">
        <button class="nav-item active" data-page="dashboard" type="button"><span data-icon="layout-dashboard"></span><span>Dashboard</span></button>
        <button class="nav-item" data-page="groups" type="button"><span data-icon="users-round"></span><span>My Groups</span></button>
        <button class="nav-item" data-page="requests" type="button"><span data-icon="bell"></span><span>Requests</span><span class="nav-count" id="navRequestsCount">3</span></button>
        <button class="nav-item" data-page="debts" type="button"><span data-icon="coins"></span><span>Debts</span></button>
        <button class="nav-item" data-page="history" type="button"><span data-icon="clock"></span><span>History</span></button>
      </nav>
      <nav class="sidebar-bottom" aria-label="Account navigation">
        <button class="nav-item" data-page="profile" type="button"><span data-icon="user-round"></span><span>Profile</span></button>
        <button class="nav-item" data-page="settings" type="button"><span data-icon="settings"></span><span>Settings</span></button>
      </nav>
    </aside>

    <!-- Top navigation -->
    <div class="app-main">
      <header class="topbar">
        <button class="menu-toggle icon-button" type="button" id="menuToggle" aria-label="Open navigation"><span data-icon="menu"></span></button>
        <div class="topbar-actions">
          <button type="button" class="notification-button icon-button" data-page="requests" aria-label="View pending requests"><span data-icon="bell"></span><span class="notification-dot"></span></button>
          <button type="button" class="user-menu" data-page="profile" aria-label="Open profile">
            <span class="top-user-name" id="topUserName">Loading…</span><span class="top-account-symbol" data-icon="user-round"></span><span data-icon="chevron-down"></span>
          </button>
        </div>
      </header>
      <main id="mainContent" class="content" tabindex="-1" aria-live="polite"></main>
    </div>
  </div>
  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <!-- Shared modal container for forms and confirmations -->
  <div id="modalRoot"></div>
  <div class="toast-region" id="toastRegion" role="status" aria-live="polite"></div>

</body>
</html>

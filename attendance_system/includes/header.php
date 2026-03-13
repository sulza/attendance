<?php
$flash = getFlash();
$user = getCurrentUser();
$unread = $user ? countUnread($user['id']) : 0;
$notifications = $user ? getUnreadNotifications($user['id']) : [];
$roleDashboard = [
    'admin'    => BASE_URL . 'admin/dashboard.php',
    'hod'      => BASE_URL . 'hod/dashboard.php',
    'lecturer' => BASE_URL . 'lecturer/dashboard.php',
];
$dashLink = $roleDashboard[$_SESSION['role'] ?? ''] ?? BASE_URL . 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' | ' : '' ?>VLA System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
    <div class="brand-text">
      <span class="brand-name">VLA</span>
      <span class="brand-sub">Attendance System</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php if (isset($sidebarItems) && is_array($sidebarItems)): ?>
      <?php foreach ($sidebarItems as $item): ?>
        <?php if (isset($item['divider'])): ?>
          <div class="nav-divider"><?= clean($item['divider']) ?></div>
        <?php else: ?>
          <a href="<?= BASE_URL . $item['url'] ?>" class="nav-item <?= (isset($item['active']) && $item['active']) ? 'active' : '' ?>">
            <i class="<?= $item['icon'] ?>"></i>
            <span><?= clean($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?>
              <span class="nav-badge"><?= $item['badge'] ?></span>
            <?php endif ?>
          </a>
        <?php endif ?>
      <?php endforeach ?>
    <?php endif ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">
        <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="user-info">
        <span class="user-name"><?= clean(explode(' ', $user['full_name'] ?? 'User')[0]) ?></span>
        <span class="user-role"><?= ucfirst($_SESSION['role'] ?? '') ?></span>
      </div>
      <a href="<?= BASE_URL ?>logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</aside>

<!-- TOPBAR -->
<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title">
      <h1 class="page-title"><?= isset($pageTitle) ? clean($pageTitle) : 'Dashboard' ?></h1>
      <?php if (isset($breadcrumb)): ?>
        <nav class="breadcrumb-nav">
          <a href="<?= $dashLink ?>">Home</a>
          <?php foreach ($breadcrumb as $crumb): ?>
            <span class="sep">/</span>
            <?php if (isset($crumb['url'])): ?>
              <a href="<?= BASE_URL . $crumb['url'] ?>"><?= clean($crumb['label']) ?></a>
            <?php else: ?>
              <span><?= clean($crumb['label']) ?></span>
            <?php endif ?>
          <?php endforeach ?>
        </nav>
      <?php endif ?>
    </div>
    <div class="topbar-actions">
      <!-- Notifications -->
      <div class="notif-wrapper">
        <button class="icon-btn notif-btn" id="notifBtn">
          <i class="fas fa-bell"></i>
          <?php if ($unread > 0): ?>
            <span class="notif-count"><?= $unread > 9 ? '9+' : $unread ?></span>
          <?php endif ?>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">
            <span>Notifications</span>
            <?php if ($unread > 0): ?>
              <a href="<?= BASE_URL ?>includes/mark_read.php" class="mark-all">Mark all read</a>
            <?php endif ?>
          </div>
          <div class="notif-body">
            <?php if (empty($notifications)): ?>
              <div class="notif-empty"><i class="fas fa-check-circle"></i><span>All caught up!</span></div>
            <?php else: ?>
              <?php foreach ($notifications as $n): ?>
                <div class="notif-item notif-<?= $n['type'] ?>">
                  <i class="fas fa-<?= $n['type'] === 'success' ? 'check' : ($n['type'] === 'danger' ? 'exclamation' : ($n['type'] === 'warning' ? 'exclamation-triangle' : 'info')) ?>-circle"></i>
                  <div>
                    <strong><?= clean($n['title']) ?></strong>
                    <p><?= clean($n['message']) ?></p>
                    <small><?= date('d M, g:ia', strtotime($n['created_at'])) ?></small>
                  </div>
                </div>
              <?php endforeach ?>
            <?php endif ?>
          </div>
        </div>
      </div>
      <!-- Profile -->
      <div class="profile-wrapper">
        <button class="icon-btn profile-btn" id="profileBtn">
          <div class="profile-avatar"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
          <span class="profile-name"><?= clean(explode(' ', $user['full_name'] ?? 'User')[0]) ?></span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-info">
            <strong><?= clean($user['full_name'] ?? '') ?></strong>
            <span><?= clean($user['email'] ?? '') ?></span>
          </div>
          <div class="profile-menu">
            <a href="<?= BASE_URL . strtolower($_SESSION['role'] ?? 'lecturer') ?>/profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="<?= BASE_URL . strtolower($_SESSION['role'] ?? 'lecturer') ?>/change_password.php"><i class="fas fa-lock"></i> Change Password</a>
            <a href="<?= BASE_URL ?>logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="content-area">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> alert-dismissible">
        <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'times-circle' : 'info-circle') ?>"></i>
        <span><?= clean($flash['message']) ?></span>
        <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
      </div>
    <?php endif ?>

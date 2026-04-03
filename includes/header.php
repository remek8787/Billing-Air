<?php
$flash = getFlash();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="light">
<div class="app-loader" id="appLoader">
  <div class="spinner-border text-primary" role="status"></div>
  <div class="mt-2">Loading dashboard...</div>
</div>

<div class="app-layout" id="appLayout">
  <aside class="app-sidebar" id="appSidebar">
    <div class="mb-4">
      <h1 class="app-title">Billing AIR Denta</h1>
      <div class="app-subtitle">Abonemen Rp 30.000 + Rp 2.500 / m³</div>
    </div>

    <?php if ($user): ?>
      <nav class="side-nav">
        <a class="side-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>

        <?php if (in_array($user['role'], ['admin', 'collector'], true)): ?>
          <a class="side-link <?= $currentPage === 'customers.php' ? 'active' : '' ?>" href="customers.php"><i class="bi bi-people me-2"></i>Pelanggan</a>
          <a class="side-link <?= $currentPage === 'readings.php' ? 'active' : '' ?>" href="readings.php"><i class="bi bi-pencil-square me-2"></i>Input Meter</a>
          <a class="side-link <?= $currentPage === 'bills.php' ? 'active' : '' ?>" href="bills.php"><i class="bi bi-receipt-cutoff me-2"></i>Tagihan</a>
        <?php endif; ?>

        <?php if ($user['role'] === 'admin'): ?>
          <a class="side-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="bi bi-person-gear me-2"></i>Users</a>
          <a class="side-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php"><i class="bi bi-sliders2 me-2"></i>Pengaturan</a>
        <?php endif; ?>

        <?php if ($user['role'] === 'customer'): ?>
          <a class="side-link <?= $currentPage === 'bills.php' ? 'active' : '' ?>" href="bills.php"><i class="bi bi-wallet2 me-2"></i>Tagihan Saya</a>
        <?php endif; ?>

        <a class="side-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profil</a>
      </nav>
    <?php endif; ?>
  </aside>

  <div class="app-content-wrap">
    <header class="app-topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" type="button" title="Hide/Show Sidebar"><i class="bi bi-layout-sidebar"></i></button>
        <span class="small text-secondary">Swadaya AIR Denta</span>
      </div>

      <?php if ($user): ?>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button">🌙 Dark</button>
          <span class="badge text-bg-success"><?= e($user['full_name']) ?> (<?= e($user['role']) ?>)</span>
          <a class="btn btn-sm btn-outline-primary" href="profile.php">Profil</a>
          <a class="btn btn-sm btn-danger" href="logout.php">Logout</a>
        </div>
      <?php endif; ?>
    </header>

    <main class="app-main">
      <?php if ($flash): ?>
        <div class="mb-4 px-4 py-3 rounded text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
          <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

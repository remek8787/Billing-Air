<?php
$flash = getFlash();
$user = currentUser();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-slate-100 min-h-screen">
<nav class="bg-slate-900 text-white px-4 py-3">
  <div class="max-w-7xl mx-auto flex flex-wrap gap-3 items-center justify-between">
    <div>
      <p class="font-bold text-lg leading-tight">Billing Swadaya AIR Denta</p>
      <p class="text-xs text-slate-300">Abonemen Rp 30.000 + Rp 2.500 / m³</p>
    </div>
    <?php if ($user): ?>
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="dashboard.php">Dashboard</a>
        <?php if (in_array($user['role'], ['admin', 'collector'], true)): ?>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="customers.php">Pelanggan</a>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="readings.php">Input Meter</a>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="bills.php">Tagihan</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="users.php">User</a>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="settings.php">Pengaturan</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'customer'): ?>
          <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="bills.php">Tagihan Saya</a>
        <?php endif; ?>
        <a class="px-3 py-1 rounded bg-slate-700 hover:bg-slate-600" href="profile.php">Profil</a>
        <span class="px-3 py-1 rounded bg-emerald-700"><?= e($user['full_name']) ?> (<?= e($user['role']) ?>)</span>
        <a class="px-3 py-1 rounded bg-red-700 hover:bg-red-600" href="logout.php">Logout</a>
      </div>
    <?php endif; ?>
  </div>
</nav>

<main class="max-w-7xl mx-auto p-4">
  <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endif; ?>

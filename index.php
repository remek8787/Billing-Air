<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (login($username, $password)) {
        flash('success', 'Login berhasil.');
        header('Location: dashboard.php');
        exit;
    }

    flash('error', 'Username atau password salah.');
    header('Location: index.php');
    exit;
}

$flash = getFlash();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="DENTA TIRTA">
  <title>Login - <?= e(APP_NAME) ?></title>
  <link rel="manifest" href="manifest.json">
  <link rel="icon" href="assets/app-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/app-icon-192.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="light" class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
    <div class="text-center mb-4">
      <img src="assets/app-logo.svg" alt="DENTA TIRTA" class="login-brand-logo mx-auto mb-3">
    </div>
    <h1 class="text-2xl font-bold mb-2">DENTA TIRTA</h1>
    <p class="text-sm text-slate-600 mb-5">Login sebagai <b>admin</b>, <b>collector</b>, atau <b>pelanggan</b>.</p>

    <?php if ($flash): ?>
      <div class="mb-4 px-4 py-3 rounded text-sm <?= $flash['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' ?>">
        <?= e($flash['message']) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-3">
      <div>
        <label class="text-sm font-medium">Username</label>
        <input name="username" required class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: admin">
      </div>
      <div>
        <label class="text-sm font-medium">Password</label>
        <input name="password" type="password" required class="mt-1 w-full border rounded px-3 py-2" placeholder="••••••••">
      </div>
      <button class="w-full bg-slate-900 text-white rounded py-2 hover:bg-slate-700">Masuk</button>
    </form>

    <div class="mt-5 text-xs text-slate-500 leading-5 bg-slate-50 border rounded p-3">
      <p><b>Default akun awal:</b></p>
      <p>Admin: <code>admin / admin123</code></p>
      <p>Collector: <code>collector / collector123</code></p>
    </div>
  </div>


  <script src="assets/app.js"></script>
</body>
</html>

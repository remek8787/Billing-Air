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
  <title>Login - <?= e(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
    <h1 class="text-2xl font-bold mb-2">Billing Swadaya AIR Denta</h1>
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
</body>
</html>

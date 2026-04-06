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
  <meta name="apple-mobile-web-app-title" content="Billing AIR Denta">
  <title>Login - <?= e(APP_NAME) ?></title>
  <link rel="manifest" href="manifest.json">
  <link rel="icon" href="assets/app-icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/app-icon.svg">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="light" class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
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

  <div class="install-popup-backdrop" id="installPromptBackdrop" hidden>
    <div class="install-popup-card" role="dialog" aria-modal="true" aria-labelledby="installPromptTitle">
      <div class="install-popup-head">
        <div class="install-popup-brand">
          <div class="install-popup-icon-wrap">
            <img src="assets/app-icon.svg" alt="Billing AIR Denta" class="install-popup-icon">
          </div>
          <div>
            <div class="install-popup-title" id="installPromptTitle">Install Aplikasi</div>
            <div class="install-popup-subtitle">Billing AIR Denta</div>
          </div>
        </div>
        <button type="button" class="install-popup-close" id="installPromptClose" aria-label="Tutup popup install">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="install-popup-body">
        <p class="install-popup-text">
          Install aplikasi ini di layar utama HP Anda untuk akses lebih cepat,
          tampilan lebih full screen, dan terasa seperti aplikasi beneran.
        </p>

        <div class="install-popup-note" id="installPromptNote">
          <b>Note:</b> kalau tombol install otomatis tidak muncul, tenang — Anda masih bisa install manual dari menu browser.
        </div>

        <div class="install-steps" id="installPromptSteps">
          <div class="install-step">
            <div class="install-step-icon"><i class="bi bi-three-dots-vertical"></i></div>
            <div>
              <div class="install-step-title">1. Buka menu browser</div>
              <div class="install-step-text">Tekan <b>Menu / Titik Tiga</b> di pojok kanan atas browser Anda.</div>
            </div>
          </div>
          <div class="install-step">
            <div class="install-step-icon"><i class="bi bi-phone"></i></div>
            <div>
              <div class="install-step-title">2. Pilih Install App</div>
              <div class="install-step-text">Lalu pilih <b>Install App</b>, <b>Tambahkan ke Layar Utama</b>, atau <b>Add to Home Screen</b>.</div>
            </div>
          </div>
        </div>

        <div class="install-popup-actions">
          <button type="button" class="btn btn-primary install-main-btn" id="installPromptInstallBtn">Install Sekarang</button>
          <button type="button" class="btn btn-outline-secondary" id="installPromptLaterBtn">Nanti Saja</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/app.js"></script>
</body>
</html>

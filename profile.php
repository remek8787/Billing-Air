<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = (string)($_POST['old_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
        flash('error', 'Password lama salah.');
        header('Location: profile.php');
        exit;
    }

    if (strlen($newPassword) < 6) {
        flash('error', 'Password baru minimal 6 karakter.');
        header('Location: profile.php');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        flash('error', 'Konfirmasi password tidak sama.');
        header('Location: profile.php');
        exit;
    }

    $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $update->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => (int)$user['id'],
    ]);

    flash('success', 'Password berhasil diubah.');
    header('Location: profile.php');
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<section class="bg-white rounded-xl shadow p-4 max-w-xl">
  <h2 class="font-semibold mb-3">Profil & Ganti Password</h2>
  <p class="text-sm text-slate-600 mb-4">User: <b><?= e($user['username']) ?></b> • Role: <b><?= e($user['role']) ?></b></p>

  <form method="post" class="space-y-3">
    <div>
      <label class="text-sm">Password Lama</label>
      <input name="old_password" type="password" required class="mt-1 w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm">Password Baru</label>
      <input name="new_password" type="password" required class="mt-1 w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm">Konfirmasi Password Baru</label>
      <input name="confirm_password" type="password" required class="mt-1 w-full border rounded px-3 py-2">
    </div>
    <button class="bg-slate-900 text-white rounded px-4 py-2">Ubah Password</button>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

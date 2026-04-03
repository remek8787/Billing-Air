<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_tariff') {
        $baseFee = (int)($_POST['base_fee'] ?? 0);
        $pricePerM3 = (int)($_POST['price_per_m3'] ?? 0);

        if ($baseFee < 0 || $pricePerM3 < 0) {
            flash('error', 'Tarif tidak valid.');
            header('Location: settings.php');
            exit;
        }

        updateSetting('base_fee', $baseFee);
        updateSetting('price_per_m3', $pricePerM3);

        flash('success', 'Tarif berhasil diperbarui.');
        header('Location: settings.php');
        exit;
    }

    if ($action === 'change_password') {
        $oldPassword = (string)($_POST['old_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
            flash('error', 'Password lama salah.');
            header('Location: settings.php');
            exit;
        }

        if (strlen($newPassword) < 6) {
            flash('error', 'Password baru minimal 6 karakter.');
            header('Location: settings.php');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'Konfirmasi password tidak sama.');
            header('Location: settings.php');
            exit;
        }

        $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $update->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int)$user['id'],
        ]);

        flash('success', 'Password berhasil diubah.');
        header('Location: settings.php');
        exit;
    }
}

$baseFee = appSetting('base_fee', DEFAULT_BASE_FEE);
$pricePerM3 = appSetting('price_per_m3', DEFAULT_PRICE_PER_M3);

require __DIR__ . '/includes/header.php';
?>

<div class="grid md:grid-cols-2 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Pengaturan Tarif Billing</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="update_tariff">
      <div>
        <label class="text-sm">Biaya Kewajiban / Abonemen</label>
        <input type="number" min="0" name="base_fee" value="<?= (int)$baseFee ?>" class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="text-sm">Tarif per m³</label>
        <input type="number" min="0" name="price_per_m3" value="<?= (int)$pricePerM3 ?>" class="mt-1 w-full border rounded px-3 py-2">
      </div>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan Tarif</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Ganti Password Admin</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="change_password">
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
      <button class="bg-emerald-700 text-white rounded px-4 py-2">Ubah Password</button>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

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

    if ($action === 'save_announcement') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $audience = trim((string)($_POST['audience'] ?? 'all'));
        $level = trim((string)($_POST['level'] ?? 'info'));
        $isPopup = isset($_POST['is_popup']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $message === '') {
            flash('error', 'Judul dan isi pengumuman wajib diisi.');
            header('Location: settings.php');
            exit;
        }

        if (!in_array($audience, ['all', 'customer', 'staff'], true)) {
            $audience = 'all';
        }

        if (!in_array($level, ['info', 'success', 'warning', 'danger'], true)) {
            $level = 'info';
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE announcements SET
                title = :title,
                message = :message,
                audience = :audience,
                level = :level,
                is_popup = :is_popup,
                is_active = :is_active,
                updated_at = :updated_at
                WHERE id = :id');
            $stmt->execute([
                ':title' => $title,
                ':message' => $message,
                ':audience' => $audience,
                ':level' => $level,
                ':is_popup' => $isPopup,
                ':is_active' => $isActive,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
            flash('success', 'Pengumuman berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO announcements(
                title, message, audience, level, is_popup, is_active, created_by, updated_at
            ) VALUES(
                :title, :message, :audience, :level, :is_popup, :is_active, :created_by, :updated_at
            )');
            $stmt->execute([
                ':title' => $title,
                ':message' => $message,
                ':audience' => $audience,
                ':level' => $level,
                ':is_popup' => $isPopup,
                ':is_active' => $isActive,
                ':created_by' => (int)$user['id'],
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);
            flash('success', 'Pengumuman baru berhasil dibuat.');
        }

        header('Location: settings.php');
        exit;
    }

    if ($action === 'delete_announcement') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM announcements WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Pengumuman dihapus.');
        }
        header('Location: settings.php');
        exit;
    }
}

$baseFee = appSetting('base_fee', DEFAULT_BASE_FEE);
$pricePerM3 = appSetting('price_per_m3', DEFAULT_PRICE_PER_M3);
$editAnnouncementId = (int)($_GET['edit_announcement'] ?? 0);
$editAnnouncement = null;
if ($editAnnouncementId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editAnnouncementId]);
    $editAnnouncement = $stmt->fetch() ?: null;
}
$announcements = $pdo->query('SELECT a.*, u.full_name AS creator_name
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.id DESC, a.updated_at DESC
    LIMIT 50')->fetchAll();

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

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <div class="grid lg:grid-cols-2 gap-4">
    <div>
      <h2 class="font-semibold mb-3"><?= $editAnnouncement ? 'Edit Pengumuman' : 'Buat Pengumuman Baru' ?></h2>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="save_announcement">
        <input type="hidden" name="id" value="<?= (int)($editAnnouncement['id'] ?? 0) ?>">
        <div>
          <label class="text-sm">Judul</label>
          <input name="title" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e((string)($editAnnouncement['title'] ?? '')) ?>" placeholder="Contoh: Cara Login Pelanggan">
        </div>
        <div>
          <label class="text-sm">Isi Pengumuman</label>
          <textarea name="message" rows="5" required class="mt-1 w-full border rounded px-3 py-2" placeholder="Tulis cara penggunaan, update aplikasi, atau info penting lainnya"><?= e((string)($editAnnouncement['message'] ?? '')) ?></textarea>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="text-sm">Ditampilkan ke</label>
            <select name="audience" class="mt-1 w-full border rounded px-3 py-2">
              <?php $audienceValue = (string)($editAnnouncement['audience'] ?? 'all'); ?>
              <option value="all" <?= $audienceValue === 'all' ? 'selected' : '' ?>>Semua User</option>
              <option value="customer" <?= $audienceValue === 'customer' ? 'selected' : '' ?>>Pelanggan</option>
              <option value="staff" <?= $audienceValue === 'staff' ? 'selected' : '' ?>>Admin + Collector</option>
            </select>
          </div>
          <div>
            <label class="text-sm">Warna / Level</label>
            <?php $levelValue = (string)($editAnnouncement['level'] ?? 'info'); ?>
            <select name="level" class="mt-1 w-full border rounded px-3 py-2">
              <option value="info" <?= $levelValue === 'info' ? 'selected' : '' ?>>Info</option>
              <option value="success" <?= $levelValue === 'success' ? 'selected' : '' ?>>Success</option>
              <option value="warning" <?= $levelValue === 'warning' ? 'selected' : '' ?>>Warning</option>
              <option value="danger" <?= $levelValue === 'danger' ? 'selected' : '' ?>>Urgent</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_popup" value="1" <?= !isset($editAnnouncement['is_popup']) || (int)$editAnnouncement['is_popup'] === 1 ? 'checked' : '' ?>>
            Tampilkan sebagai popup
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" <?= !isset($editAnnouncement['is_active']) || (int)$editAnnouncement['is_active'] === 1 ? 'checked' : '' ?>>
            Aktifkan sekarang
          </label>
        </div>
        <div class="text-xs text-slate-500">Pengumuman aktif akan tampil sebagai banner, dan jika opsi popup dicentang maka akan muncul sebagai popup ke user saat aplikasi dibuka.</div>
        <div class="flex flex-wrap gap-2 mobile-form-actions">
          <button class="bg-slate-900 text-white rounded px-4 py-2"><?= $editAnnouncement ? 'Update Pengumuman' : 'Simpan Pengumuman' ?></button>
          <?php if ($editAnnouncement): ?>
            <a class="btn btn-outline-secondary" href="settings.php">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div>
      <h2 class="font-semibold mb-3">Daftar Pengumuman</h2>
      <div class="space-y-3">
        <?php foreach ($announcements as $announcement): ?>
          <div class="announcement-admin-card <?= e(announcementLevelClass((string)($announcement['level'] ?? 'info'))) ?>">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <div>
                <div class="announcement-admin-title"><?= e((string)$announcement['title']) ?></div>
                <div class="announcement-admin-meta">
                  <?= e(announcementAudienceLabel((string)($announcement['audience'] ?? 'all'))) ?>
                  • <?= (int)($announcement['is_popup'] ?? 0) === 1 ? 'Popup' : 'Banner saja' ?>
                  • <?= (int)($announcement['is_active'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif' ?>
                </div>
              </div>
              <div class="announcement-admin-date"><?= e(formatDateTimeId((string)($announcement['updated_at'] ?? ''), '-')) ?></div>
            </div>
            <div class="announcement-admin-message"><?= nl2br(e((string)$announcement['message'])) ?></div>
            <div class="announcement-admin-footer">
              <span class="text-xs text-slate-500">Dibuat oleh: <?= e((string)($announcement['creator_name'] ?? 'Admin')) ?></span>
              <div class="flex flex-wrap gap-2">
                <a class="px-3 py-2 rounded bg-slate-200 text-sm font-semibold" href="settings.php?edit_announcement=<?= (int)$announcement['id'] ?>">Edit</a>
                <form method="post" class="inline" onsubmit="return confirm('Hapus pengumuman ini?')">
                  <input type="hidden" name="action" value="delete_announcement">
                  <input type="hidden" name="id" value="<?= (int)$announcement['id'] ?>">
                  <button class="px-3 py-2 rounded bg-red-100 text-sm font-semibold">Hapus</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$announcements): ?>
          <div class="text-sm text-slate-500">Belum ada pengumuman. Buat pengumuman pertama untuk panduan login, penggunaan aplikasi, atau info update.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

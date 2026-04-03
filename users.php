<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $fullName === '' || !in_array($role, ['admin', 'collector'], true)) {
            flash('error', 'Data user tidak valid.');
            header('Location: users.php');
            exit;
        }

        if ($id > 0) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role, password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':role' => $role,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':role' => $role,
                    ':id' => $id,
                ]);
            }
            flash('success', 'User berhasil diperbarui.');
        } else {
            if ($password === '') {
                flash('error', 'Password wajib untuk user baru.');
                header('Location: users.php');
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO users(username, password_hash, role, full_name) VALUES(:username, :password_hash, :role, :full_name)');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':full_name' => $fullName,
            ]);
            flash('success', 'User baru berhasil ditambahkan.');
        }

        header('Location: users.php');
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int)currentUser()['id']) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND role IN ("admin", "collector")');
            $stmt->execute([':id' => $id]);
            flash('success', 'User dihapus.');
        }
        header('Location: users.php');
        exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query('SELECT id, username, full_name, role, created_at FROM users
    WHERE role IN ("admin", "collector") ORDER BY id ASC')->fetchAll();

$customerUsers = $pdo->query('SELECT u.id, u.username, u.full_name, c.name AS customer_name
    FROM users u LEFT JOIN customers c ON c.id = u.customer_id
    WHERE u.role = "customer" ORDER BY u.id DESC LIMIT 30')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editUser ? 'Edit User Admin/Collector' : 'Tambah User Admin/Collector' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int)($editUser['id'] ?? 0) ?>">

      <div>
        <label class="text-sm">Nama Lengkap</label>
        <input name="full_name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editUser['full_name'] ?? '') ?>">
      </div>
      <div>
        <label class="text-sm">Username</label>
        <input name="username" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editUser['username'] ?? '') ?>">
      </div>
      <div>
        <label class="text-sm">Role</label>
        <select name="role" class="mt-1 w-full border rounded px-3 py-2">
          <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>admin</option>
          <option value="collector" <?= ($editUser['role'] ?? '') === 'collector' ? 'selected' : '' ?>>collector</option>
        </select>
      </div>
      <div>
        <label class="text-sm">Password <?= $editUser ? '(kosongkan jika tidak diubah)' : '' ?></label>
        <input name="password" type="password" class="mt-1 w-full border rounded px-3 py-2">
      </div>

      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan User</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2">
    <h2 class="font-semibold mb-3">User Admin / Collector</h2>
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2 pr-3">ID</th>
            <th class="py-2 pr-3">Nama</th>
            <th class="py-2 pr-3">Username</th>
            <th class="py-2 pr-3">Role</th>
            <th class="py-2 pr-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr class="border-b">
              <td class="py-2 pr-3"><?= (int)$u['id'] ?></td>
              <td class="py-2 pr-3"><?= e($u['full_name']) ?></td>
              <td class="py-2 pr-3"><?= e($u['username']) ?></td>
              <td class="py-2 pr-3"><?= e($u['role']) ?></td>
              <td class="py-2 pr-3">
                <a class="px-2 py-1 rounded bg-slate-200" href="users.php?edit=<?= (int)$u['id'] ?>">Edit</a>
                <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Hapus user ini?')">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <h2 class="font-semibold mb-3">Daftar Login Pelanggan (30 Terakhir)</h2>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3">ID</th><th class="py-2 pr-3">Username</th><th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Pelanggan</th></tr></thead>
      <tbody>
      <?php foreach ($customerUsers as $cu): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= (int)$cu['id'] ?></td>
          <td class="py-2 pr-3"><?= e($cu['username']) ?></td>
          <td class="py-2 pr-3"><?= e($cu['full_name']) ?></td>
          <td class="py-2 pr-3"><?= e($cu['customer_name'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customerUsers): ?>
        <tr><td colspan="4" class="py-4 text-slate-500">Belum ada login pelanggan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

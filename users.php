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
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role, password_hash = :password_hash WHERE id = :id AND role IN ("admin", "collector")');
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $fullName,
                    ':role' => $role,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = :username, full_name = :full_name, role = :role WHERE id = :id AND role IN ("admin", "collector")');
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

    if ($action === 'update_customer_login') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim((string)($_POST['password'] ?? ''));

        if ($id <= 0 || $username === '') {
            flash('error', 'ID customer login / username tidak valid.');
            header('Location: users.php');
            exit;
        }

        $target = $pdo->prepare('SELECT id, customer_id FROM users WHERE id = :id AND role = "customer" LIMIT 1');
        $target->execute([':id' => $id]);
        $customerUser = $target->fetch();

        if (!$customerUser) {
            flash('error', 'Akun pelanggan tidak ditemukan.');
            header('Location: users.php');
            exit;
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1');
        $check->execute([':username' => $username, ':id' => $id]);
        if ($check->fetch()) {
            flash('error', 'Username sudah dipakai akun lain.');
            header('Location: users.php');
            exit;
        }

        $pdo->prepare('UPDATE users SET username = :username WHERE id = :id AND role = "customer"')
            ->execute([':username' => $username, ':id' => $id]);

        if ($password !== '') {
            if (!preg_match('/^DSA\d{4}$/', $password)) {
                flash('error', 'Format password pelanggan wajib DSA + 4 digit (contoh DSA0001).');
                header('Location: users.php');
                exit;
            }

            $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND role = "customer"')
                ->execute([':password_hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
            saveCustomerLoginSecret($id, $password);

            flash('success', 'Login pelanggan diperbarui. Password baru: ' . $password);
        } else {
            flash('success', 'Username login pelanggan diperbarui.');
        }

        header('Location: users.php');
        exit;
    }

    if ($action === 'reset_customer_password') {
        $id = (int)($_POST['id'] ?? 0);

        $target = $pdo->prepare('SELECT id, customer_id FROM users WHERE id = :id AND role = "customer" LIMIT 1');
        $target->execute([':id' => $id]);
        $customerUser = $target->fetch();

        if (!$customerUser) {
            flash('error', 'Akun pelanggan tidak ditemukan.');
            header('Location: users.php');
            exit;
        }

        $newPassword = defaultCustomerPasswordById((int)$customerUser['customer_id']);
        $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')
            ->execute([':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $id]);
        saveCustomerLoginSecret($id, $newPassword);

        flash('success', 'Password pelanggan di-reset ke: ' . $newPassword);
        header('Location: users.php');
        exit;
    }

    if ($action === 'delete_customer_login') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM users WHERE id = :id AND role = "customer"')->execute([':id' => $id]);
            flash('success', 'Login pelanggan dihapus.');
        }
        header('Location: users.php');
        exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND role IN ("admin", "collector") LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query('SELECT id, username, full_name, role, created_at FROM users
    WHERE role IN ("admin", "collector") ORDER BY id ASC')->fetchAll();

$customerUsers = $pdo->query('SELECT u.id, u.username, u.full_name, u.customer_id,
        c.name AS customer_name, c.address,
        cls.password_plain, cls.updated_at AS password_updated_at
    FROM users u
    LEFT JOIN customers c ON c.id = u.customer_id
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
    WHERE u.role = "customer"
    ORDER BY u.id DESC LIMIT 100')->fetchAll();

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
      <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
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
  <h2 class="font-semibold mb-3">Login Pelanggan (lihat password + edit + hapus)</h2>
  <p class="text-xs text-slate-500 mb-3">Password hanya ditampilkan untuk kebutuhan operasional admin. Disarankan ganti berkala.</p>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">User ID</th>
          <th class="py-2 pr-3">ID Pelanggan</th>
          <th class="py-2 pr-3">Pelanggan</th>
          <th class="py-2 pr-3">Username</th>
          <th class="py-2 pr-3">Password Tercatat</th>
          <th class="py-2 pr-3">Update</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customerUsers as $cu): ?>
        <tr class="border-b align-top">
          <td class="py-2 pr-3"><?= (int)$cu['id'] ?></td>
          <td class="py-2 pr-3">
            <?php $defaultId = defaultCustomerPasswordById((int)($cu['customer_id'] ?? 0)); ?>
            <span class="id-pill"><?= e(!empty($cu['password_plain']) ? (string)$cu['password_plain'] : $defaultId) ?></span>
          </td>
          <td class="py-2 pr-3">
            <div class="name-cell"><?= e($cu['customer_name'] ?? $cu['full_name']) ?></div>
            <div class="address-cell" title="<?= e((string)($cu['address'] ?? '-')) ?>"><?= e($cu['address'] ?? '-') ?></div>
          </td>
          <td class="py-2 pr-3"><?= e($cu['username']) ?></td>
          <td class="py-2 pr-3">
            <?php if (!empty($cu['password_plain'])): ?>
              <span class="id-pill"><?= e($cu['password_plain']) ?></span>
            <?php else: ?>
              <span class="text-xs text-slate-500">(Belum tercatat, klik Reset Default)</span>
            <?php endif; ?>
          </td>
          <td class="py-2 pr-3 text-xs text-slate-500"><?= e($cu['password_updated_at'] ?? '-') ?></td>
          <td class="py-2 pr-3">
            <form method="post" class="space-y-2 mb-2 mobile-form-actions">
              <input type="hidden" name="action" value="update_customer_login">
              <input type="hidden" name="id" value="<?= (int)$cu['id'] ?>">
              <input name="username" value="<?= e($cu['username']) ?>" class="border rounded px-2 py-1 w-full md:w-44" required>
              <input name="password" placeholder="opsional: DSA0001" class="border rounded px-2 py-1 w-full md:w-40">
              <button class="px-2 py-1 rounded bg-slate-200">Simpan</button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('Reset password ke default DSA+4 digit?')">
              <input type="hidden" name="action" value="reset_customer_password">
              <input type="hidden" name="id" value="<?= (int)$cu['id'] ?>">
              <button class="px-2 py-1 rounded bg-amber-100 text-amber-800">Reset Default</button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('Hapus login pelanggan ini?')">
              <input type="hidden" name="action" value="delete_customer_login">
              <input type="hidden" name="id" value="<?= (int)$cu['id'] ?>">
              <button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customerUsers): ?>
        <tr><td colspan="7" class="py-4 text-slate-500">Belum ada login pelanggan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

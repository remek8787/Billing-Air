<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'collector']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_customer') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            flash('error', 'Nama pelanggan wajib diisi.');
            header('Location: customers.php');
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE customers SET name = :name, address = :address, phone = :phone WHERE id = :id');
            $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone, ':id' => $id]);
            flash('success', 'Data pelanggan diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers(name, address, phone) VALUES(:name, :address, :phone)');
            $stmt->execute([':name' => $name, ':address' => $address, ':phone' => $phone]);
            flash('success', 'Pelanggan baru ditambahkan.');
        }

        header('Location: customers.php');
        exit;
    }

    if ($action === 'delete_customer' && $user['role'] === 'admin') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
            $stmt->execute([':id' => $id]);
            flash('success', 'Pelanggan dihapus.');
        }
        header('Location: customers.php');
        exit;
    }

    if ($action === 'create_customer_login') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($customerId <= 0 || $username === '' || $password === '') {
            flash('error', 'customer_id, username, password wajib diisi.');
            header('Location: customers.php');
            exit;
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $check->execute([':username' => $username]);
        if ($check->fetch()) {
            flash('error', 'Username sudah dipakai.');
            header('Location: customers.php');
            exit;
        }

        $cust = $pdo->prepare('SELECT name FROM customers WHERE id = :id LIMIT 1');
        $cust->execute([':id' => $customerId]);
        $row = $cust->fetch();

        if (!$row) {
            flash('error', 'Pelanggan tidak ditemukan.');
            header('Location: customers.php');
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO users(username, password_hash, role, full_name, customer_id)
            VALUES(:username, :password_hash, "customer", :full_name, :customer_id)');
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':full_name' => $row['name'],
            ':customer_id' => $customerId,
        ]);

        flash('success', 'Login pelanggan berhasil dibuat.');
        header('Location: customers.php');
        exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editCustomer = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editCustomer = $stmt->fetch();
}

$customers = $pdo->query('SELECT c.*, u.username AS customer_username FROM customers c
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    ORDER BY c.id ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" value="<?= (int)($editCustomer['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">Nama</label>
        <input name="name" required class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['name'] ?? '') ?>">
      </div>
      <div>
        <label class="text-sm">Alamat</label>
        <textarea name="address" rows="2" class="mt-1 w-full border rounded px-3 py-2"><?= e($editCustomer['address'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="text-sm">No HP</label>
        <input name="phone" class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['phone'] ?? '') ?>">
      </div>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Buat Login Pelanggan</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="create_customer_login">
      <div>
        <label class="text-sm">Pelanggan</label>
        <select name="customer_id" required class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm">Username Login</label>
        <input name="username" required class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: yanuk01">
      </div>
      <div>
        <label class="text-sm">Password Awal</label>
        <input name="password" required class="mt-1 w-full border rounded px-3 py-2" placeholder="misal: pelanggan123">
      </div>
      <button class="bg-emerald-700 text-white rounded px-4 py-2">Buat Akun Pelanggan</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Tarif Aktif</h2>
    <p class="text-sm text-slate-600">Abonemen: <b><?= e(rupiah(appSetting('base_fee', DEFAULT_BASE_FEE))) ?></b></p>
    <p class="text-sm text-slate-600">Per m³: <b><?= e(rupiah(appSetting('price_per_m3', DEFAULT_PRICE_PER_M3))) ?></b></p>
    <p class="text-xs text-slate-500 mt-3">Admin bisa ubah tarif di menu Pengaturan.</p>
  </section>
</div>

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <h2 class="font-semibold mb-3">Daftar Pelanggan</h2>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Nama</th>
          <th class="py-2 pr-3">Alamat</th>
          <th class="py-2 pr-3">HP</th>
          <th class="py-2 pr-3">Login Pelanggan</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= (int)$c['id'] ?></td>
          <td class="py-2 pr-3"><?= e($c['name']) ?></td>
          <td class="py-2 pr-3"><?= e($c['address']) ?></td>
          <td class="py-2 pr-3"><?= e($c['phone']) ?></td>
          <td class="py-2 pr-3"><?= e($c['customer_username'] ?? '-') ?></td>
          <td class="py-2 pr-3">
            <a class="px-2 py-1 rounded bg-slate-200" href="customers.php?edit=<?= (int)$c['id'] ?>">Edit</a>
            <?php if ($user['role'] === 'admin'): ?>
              <form method="post" class="inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                <input type="hidden" name="action" value="delete_customer">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?>
        <tr><td colspan="6" class="py-4 text-slate-500">Belum ada pelanggan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'collector']);

$pdo = db();
$user = currentUser();

function normalizeUsernamePart(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($ascii !== false) {
        $text = $ascii;
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '', $text) ?? '';

    return $text;
}

function autoCustomerUsername(PDO $pdo, int $customerId, string $name, string $address): string
{
    $base = normalizeUsernamePart($name . $address);
    if ($base === '') {
        $base = 'pelanggan' . $customerId;
    }

    $base = substr($base, 0, 18);
    if ($base === '') {
        $base = 'cust' . $customerId;
    }

    $candidate = $base;
    $suffix = 1;

    while (true) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $check->execute([':username' => $candidate]);

        if (!$check->fetch()) {
            return $candidate;
        }

        $candidate = substr($base, 0, 15) . str_pad((string)$suffix, 3, '0', STR_PAD_LEFT);
        $suffix++;
    }
}

function autoCustomerPassword(int $customerId): string
{
    return defaultCustomerPasswordById($customerId);
}

function nextCustomerNumber(PDO $pdo): int
{
    $rows = $pdo->query('SELECT customer_no FROM customers WHERE customer_no IS NOT NULL AND customer_no > 0 ORDER BY customer_no ASC')->fetchAll();
    $used = [];
    foreach ($rows as $row) {
        $no = (int)($row['customer_no'] ?? 0);
        if ($no > 0) {
            $used[$no] = true;
        }
    }

    $n = 1;
    while (isset($used[$n])) {
        $n++;
    }

    return $n;
}

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

            $newCustomerId = (int)$pdo->lastInsertId();
            $newCustomerNo = nextCustomerNumber($pdo);
            $pdo->prepare('UPDATE customers SET customer_no = :customer_no WHERE id = :id')
                ->execute([':customer_no' => $newCustomerNo, ':id' => $newCustomerId]);

            $autoUsername = autoCustomerUsername($pdo, $newCustomerId, $name, $address);
            $autoPassword = autoCustomerPassword($newCustomerId);

            $loginStmt = $pdo->prepare('INSERT INTO users(username, password_hash, role, full_name, customer_id)
                VALUES(:username, :password_hash, "customer", :full_name, :customer_id)');
            $loginStmt->execute([
                ':username' => $autoUsername,
                ':password_hash' => password_hash($autoPassword, PASSWORD_DEFAULT),
                ':full_name' => $name,
                ':customer_id' => $newCustomerId,
            ]);

            $newUserId = (int)$pdo->lastInsertId();
            saveCustomerLoginSecret($newUserId, $autoPassword);

            flash('success', 'Pelanggan baru ditambahkan + login otomatis dibuat. Username: ' . $autoUsername . ' | ID/Password: ' . $autoPassword);
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
        $password = trim((string)($_POST['password'] ?? ''));

        if ($customerId <= 0) {
            flash('error', 'Silakan pilih pelanggan dulu.');
            header('Location: customers.php');
            exit;
        }

        $cust = $pdo->prepare('SELECT id, name, address FROM customers WHERE id = :id LIMIT 1');
        $cust->execute([':id' => $customerId]);
        $row = $cust->fetch();

        if (!$row) {
            flash('error', 'Pelanggan tidak ditemukan.');
            header('Location: customers.php');
            exit;
        }

        $existingCustomerLogin = $pdo->prepare('SELECT id, username FROM users WHERE customer_id = :customer_id AND role = "customer" LIMIT 1');
        $existingCustomerLogin->execute([':customer_id' => $customerId]);
        $exists = $existingCustomerLogin->fetch();
        if ($exists) {
            flash('error', 'Pelanggan ini sudah punya akun login: ' . $exists['username']);
            header('Location: customers.php');
            exit;
        }

        if ($username === '') {
            $username = autoCustomerUsername($pdo, $customerId, (string)$row['name'], (string)($row['address'] ?? ''));
        }

        if ($password === '') {
            $password = autoCustomerPassword($customerId);
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $check->execute([':username' => $username]);
        if ($check->fetch()) {
            flash('error', 'Username sudah dipakai, silakan ubah sedikit username-nya.');
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

        $newUserId = (int)$pdo->lastInsertId();
        saveCustomerLoginSecret($newUserId, $password);

        flash('success', 'Login pelanggan berhasil dibuat. Username: ' . $username . ' | Password awal: ' . $password);
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

$customers = $pdo->query('SELECT c.*, u.username AS customer_username,
        cls.password_plain AS customer_login_id
    FROM customers c
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
    ORDER BY CASE WHEN c.customer_no IS NULL OR c.customer_no <= 0 THEN 999999 ELSE c.customer_no END ASC, c.id ASC')->fetchAll();

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
    <p class="text-xs text-slate-500 mb-2">Catatan: saat tambah pelanggan baru (form kiri), login pelanggan sekarang dibuat otomatis. Form ini untuk kasus manual khusus.</p>
    <form method="post" class="space-y-3" id="customer-login-form">
      <input type="hidden" name="action" value="create_customer_login">
      <div>
        <label class="text-sm">Pelanggan (ketik nama / alamat / ID pelanggan)</label>
        <select name="customer_id" id="customer_id" required class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($customers as $c): ?>
            <?php $loginId = !empty($c['customer_login_id']) ? (string)$c['customer_login_id'] : defaultCustomerPasswordById((int)$c['id']); ?>
            <option value="<?= (int)$c['id'] ?>"
                    data-name="<?= e((string)$c['name']) ?>"
                    data-address="<?= e((string)($c['address'] ?? '')) ?>"
                    data-login-id="<?= e($loginId) ?>">
              <?= e($loginId . ' • ' . $c['name']) ?><?= !empty($c['address']) ? ' — ' . e((string)$c['address']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm">Username Login (otomatis dari nama+alamat)</label>
        <input id="username" name="username" class="mt-1 w-full border rounded px-3 py-2" placeholder="otomatis saat pelanggan dipilih">
      </div>
      <div>
        <label class="text-sm">ID Pelanggan / Password Awal (format DSA + 4 digit)</label>
        <input id="password" name="password" class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: DSA0001 (ID pelanggan)">
      </div>
      <p class="text-xs text-slate-500">Default otomatis: username dari nama+alamat, ID pelanggan = password awal = DSA + 4 digit ID pelanggan.</p>
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
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Nama</th>
          <th class="py-2 pr-3">Alamat</th>
          <th class="py-2 pr-3">HP</th>
          <th class="py-2 pr-3">Login Pelanggan</th>
          <th class="py-2 pr-3">ID Pelanggan</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= (int)(($c['customer_no'] ?? 0) > 0 ? $c['customer_no'] : $c['id']) ?></td>
          <td class="py-2 pr-3"><div class="name-cell"><?= e($c['name']) ?></div></td>
          <td class="py-2 pr-3"><div class="address-cell" title="<?= e((string)$c['address']) ?>"><?= e($c['address']) ?></div></td>
          <td class="py-2 pr-3"><?= e($c['phone']) ?></td>
          <td class="py-2 pr-3"><?= e($c['customer_username'] ?? '-') ?></td>
          <td class="py-2 pr-3">
            <?php if (!empty($c['customer_login_id'])): ?>
              <span class="id-pill"><?= e((string)$c['customer_login_id']) ?></span>
            <?php else: ?>
              <span class="id-pill"><?= e(defaultCustomerPasswordById((int)$c['id'])) ?></span>
              <div class="text-xs text-slate-500 mt-1">default (jika akun login belum dibuat)</div>
            <?php endif; ?>
          </td>
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
        <tr><td colspan="7" class="py-4 text-slate-500">Belum ada pelanggan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const initCustomerPicker = () => {
    const select = document.getElementById('customer_id');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');

    if (!select || select.dataset.pickerInit === '1') return;
    select.dataset.pickerInit = '1';

    const toSlug = (text) => {
      return (text || '')
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '');
    };

    const generateUser = (name, address) => {
      const base = toSlug((name || '') + (address || ''));
      return (base || 'pelanggan').slice(0, 18);
    };

    const generatePass = (id) => {
      const digits = String(Number(id || 0) % 10000).padStart(4, '0');
      return `DSA${digits}`;
    };

    const applyAutoByValue = (value) => {
      if (!value) return;
      const opt = Array.from(select.options).find((o) => o.value === String(value));
      if (!opt) return;

      const name = opt.dataset.name || '';
      const address = opt.dataset.address || '';

      if (usernameInput && usernameInput.value.trim() === '') {
        usernameInput.value = generateUser(name, address);
      }

      if (passwordInput && passwordInput.value.trim() === '') {
        passwordInput.value = generatePass(value);
      }
    };

    if (window.TomSelect) {
      const ts = new TomSelect(select, {
        create: false,
        maxItems: 1,
        valueField: 'value',
        labelField: 'text',
        searchField: ['text'],
        placeholder: 'Ketik ID / nama / alamat pelanggan...',
        render: {
          option: function(data, escape) {
            return `<div>${escape(data.text)}</div>`;
          }
        },
        onChange(value) {
          if (usernameInput) usernameInput.value = '';
          if (passwordInput) passwordInput.value = '';
          applyAutoByValue(value);
        }
      });

      applyAutoByValue(ts.getValue());
      return;
    }

    select.addEventListener('change', () => {
      if (usernameInput) usernameInput.value = '';
      if (passwordInput) passwordInput.value = '';
      applyAutoByValue(select.value);
    });
  };

  if (document.readyState === 'complete') {
    initCustomerPicker();
  } else {
    window.addEventListener('load', initCustomerPicker, { once: true });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

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

function redirectCustomers(string $suffix = ''): void
{
    header('Location: customers.php' . $suffix);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_region') {
        $id = (int)($_POST['region_id'] ?? 0);
        $serviceType = trim((string)($_POST['service_type'] ?? ''));
        $village = trim((string)($_POST['village'] ?? ''));
        $rw = trim((string)($_POST['rw'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $regency = trim((string)($_POST['regency'] ?? ''));

        if (!in_array($serviceType, ['swadaya', 'distribusi'], true)) {
            flash('error', 'Jenis layanan wilayah harus Swadaya atau Distribusi.');
            redirectCustomers('#master-wilayah');
        }

        if ($village === '') {
            flash('error', 'Desa wilayah wajib diisi.');
            redirectCustomers('#master-wilayah');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE service_regions
                SET service_type = :service_type,
                    village = :village,
                    rw = :rw,
                    district = :district,
                    regency = :regency
                WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':service_type' => $serviceType,
                ':village' => $village,
                ':rw' => $rw,
                ':district' => $district,
                ':regency' => $regency,
            ]);
            flash('success', 'Master wilayah berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO service_regions(service_type, village, rw, district, regency)
                VALUES(:service_type, :village, :rw, :district, :regency)');
            $stmt->execute([
                ':service_type' => $serviceType,
                ':village' => $village,
                ':rw' => $rw,
                ':district' => $district,
                ':regency' => $regency,
            ]);
            flash('success', 'Master wilayah berhasil ditambahkan.');
        }

        redirectCustomers('#master-wilayah');
    }

    if ($action === 'delete_region' && $user['role'] === 'admin') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM service_regions WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Master wilayah dihapus.');
        }
        redirectCustomers('#master-wilayah');
    }

    if ($action === 'save_customer') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $installationDate = normalizeDateInput($_POST['installation_date'] ?? '');
        $serviceType = trim((string)($_POST['service_type'] ?? ''));
        $village = trim((string)($_POST['village'] ?? ''));
        $rw = trim((string)($_POST['rw'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $regency = trim((string)($_POST['regency'] ?? ''));

        if (!in_array($serviceType, ['', 'swadaya', 'distribusi'], true)) {
            $serviceType = '';
        }

        if ($name === '') {
            flash('error', 'Nama pelanggan wajib diisi.');
            header('Location: customers.php');
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE customers SET name = :name, address = :address, phone = :phone,
                installation_date = :installation_date, service_type = :service_type,
                village = :village, rw = :rw, district = :district, regency = :regency WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone,
                ':installation_date' => $installationDate,
                ':service_type' => $serviceType,
                ':village' => $village,
                ':rw' => $rw,
                ':district' => $district,
                ':regency' => $regency,
                ':id' => $id,
            ]);
            flash('success', 'Data pelanggan diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO customers(name, address, phone, installation_date, service_type, village, rw, district, regency)
                VALUES(:name, :address, :phone, :installation_date, :service_type, :village, :rw, :district, :regency)');
            $stmt->execute([
                ':name' => $name,
                ':address' => $address,
                ':phone' => $phone,
                ':installation_date' => $installationDate,
                ':service_type' => $serviceType,
                ':village' => $village,
                ':rw' => $rw,
                ':district' => $district,
                ':regency' => $regency,
            ]);

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

$editRegionId = (int)($_GET['edit_region'] ?? 0);
$editRegion = null;
if ($editRegionId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM service_regions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editRegionId]);
    $editRegion = $stmt->fetch() ?: null;
}

$serviceRegions = $pdo->query('SELECT * FROM service_regions
    ORDER BY service_type ASC, village ASC,
        CASE WHEN rw IS NULL OR rw = "" THEN "999" ELSE rw END ASC,
        district ASC, regency ASC, id DESC')->fetchAll();

$serviceRegionMap = [];
foreach ($serviceRegions as $region) {
    $serviceRegionMap[(int)$region['id']] = $region;
}

$filterRegionId = (int)($_GET['region'] ?? 0);
$selectedRegionFilter = $filterRegionId > 0 ? ($serviceRegionMap[$filterRegionId] ?? null) : null;
$selectedRegionKey = $selectedRegionFilter ? customerRegionKey($selectedRegionFilter) : '';

$customers = $pdo->query('SELECT c.*, u.username AS customer_username,
        cls.password_plain AS customer_login_id
    FROM customers c
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
    ORDER BY CASE WHEN c.customer_no IS NULL OR c.customer_no <= 0 THEN 999999 ELSE c.customer_no END ASC, c.id ASC')->fetchAll();

if ($selectedRegionKey !== '') {
    $customers = array_values(array_filter($customers, static function (array $customer) use ($selectedRegionKey): bool {
        return customerRegionKey($customer) === $selectedRegionKey;
    }));
}

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editCustomer ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?></h2>
    <form method="post" class="space-y-3" id="customer-form">
      <input type="hidden" name="action" value="save_customer">
      <input type="hidden" name="id" value="<?= (int)($editCustomer['id'] ?? 0) ?>">
      <div>
        <label class="text-sm">Pilih Master Wilayah (opsional)</label>
        <select id="region_template_id" class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih template wilayah</option>
          <?php foreach ($serviceRegions as $region): ?>
            <option value="<?= (int)$region['id'] ?>"
                    data-service-type="<?= e((string)$region['service_type']) ?>"
                    data-village="<?= e((string)($region['village'] ?? '')) ?>"
                    data-rw="<?= e((string)($region['rw'] ?? '')) ?>"
                    data-district="<?= e((string)($region['district'] ?? '')) ?>"
                    data-regency="<?= e((string)($region['regency'] ?? '')) ?>">
              <?= e(customerRegionLabel($region)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="text-xs text-slate-500 mt-1">Pilih template supaya field wilayah pelanggan terisi otomatis.</div>
      </div>
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
      <div>
        <label class="text-sm">Jenis Layanan</label>
        <select id="customer_service_type" name="service_type" class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih jenis layanan</option>
          <option value="swadaya" <?= ($editCustomer['service_type'] ?? '') === 'swadaya' ? 'selected' : '' ?>>Swadaya Air</option>
          <option value="distribusi" <?= ($editCustomer['service_type'] ?? '') === 'distribusi' ? 'selected' : '' ?>>Distribusi Air</option>
        </select>
      </div>
      <div>
        <label class="text-sm">Desa</label>
        <input id="customer_village" name="village" class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['village'] ?? '') ?>" placeholder="contoh: Sumbermanjing Kulon">
      </div>
      <div>
        <label class="text-sm">RW</label>
        <input id="customer_rw" name="rw" class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['rw'] ?? '') ?>" placeholder="contoh: 09">
      </div>
      <div>
        <label class="text-sm">Kecamatan</label>
        <input id="customer_district" name="district" class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['district'] ?? '') ?>" placeholder="contoh: Pagak">
      </div>
      <div>
        <label class="text-sm">Kabupaten</label>
        <input id="customer_regency" name="regency" class="mt-1 w-full border rounded px-3 py-2" value="<?= e($editCustomer['regency'] ?? '') ?>" placeholder="contoh: Malang">
      </div>
      <div>
        <label class="text-sm">Tanggal Pemasangan</label>
        <input type="date" name="installation_date" class="mt-1 w-full border rounded px-3 py-2" value="<?= e(dateInputValue($editCustomer['installation_date'] ?? '')) ?>">
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

<section class="bg-white rounded-xl shadow p-4 mt-4" id="master-wilayah">
  <div class="grid lg:grid-cols-2 gap-4">
    <div>
      <h2 class="font-semibold mb-3"><?= $editRegion ? 'Edit Master Wilayah Layanan' : 'Master Wilayah Layanan' ?></h2>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="save_region">
        <input type="hidden" name="region_id" value="<?= (int)($editRegion['id'] ?? 0) ?>">
        <div>
          <label class="text-sm">Jenis Layanan</label>
          <select name="service_type" class="mt-1 w-full border rounded px-3 py-2" required>
            <option value="swadaya" <?= ($editRegion['service_type'] ?? 'swadaya') === 'swadaya' ? 'selected' : '' ?>>Swadaya Air</option>
            <option value="distribusi" <?= ($editRegion['service_type'] ?? '') === 'distribusi' ? 'selected' : '' ?>>Distribusi Air</option>
          </select>
        </div>
        <div>
          <label class="text-sm">Desa</label>
          <input name="village" class="mt-1 w-full border rounded px-3 py-2" placeholder="contoh: Sumbermanjing Kulon" value="<?= e((string)($editRegion['village'] ?? '')) ?>" required>
        </div>
        <div class="grid md:grid-cols-3 gap-3">
          <div>
            <label class="text-sm">RW</label>
            <input name="rw" class="mt-1 w-full border rounded px-3 py-2" placeholder="09" value="<?= e((string)($editRegion['rw'] ?? '')) ?>">
          </div>
          <div>
            <label class="text-sm">Kecamatan</label>
            <input name="district" class="mt-1 w-full border rounded px-3 py-2" placeholder="Pagak" value="<?= e((string)($editRegion['district'] ?? '')) ?>">
          </div>
          <div>
            <label class="text-sm">Kabupaten</label>
            <input name="regency" class="mt-1 w-full border rounded px-3 py-2" placeholder="Malang" value="<?= e((string)($editRegion['regency'] ?? '')) ?>">
          </div>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button class="bg-indigo-700 text-white rounded px-4 py-2"><?= $editRegion ? 'Update Master Wilayah' : 'Tambah Master Wilayah' ?></button>
          <?php if ($editRegion): ?>
            <a href="customers.php#master-wilayah" class="px-4 py-2 rounded bg-slate-200 text-slate-800">Batal Edit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div>
      <h2 class="font-semibold mb-3">Daftar Master Wilayah</h2>
      <div class="overflow-auto table-wrap">
        <table class="min-w-full text-sm js-data-table table-soft" data-page-size="5">
          <thead>
            <tr class="text-left border-b">
              <th class="py-2 pr-3">Jenis</th>
              <th class="py-2 pr-3">Wilayah</th>
              <th class="py-2 pr-3">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($serviceRegions as $region): ?>
              <tr class="border-b">
                <td class="py-2 pr-3"><?= e(customerServiceTypeLabel((string)$region['service_type'])) ?></td>
                <td class="py-2 pr-3"><div class="address-cell" title="<?= e(customerRegionLabel($region)) ?>"><?= e(customerRegionLabel($region)) ?></div></td>
                <td class="py-2 pr-3">
                  <a class="px-2 py-1 rounded bg-slate-200" href="customers.php?edit_region=<?= (int)$region['id'] ?>#master-wilayah">Edit</a>
                  <?php if ($user['role'] === 'admin'): ?>
                    <form method="post" class="inline" onsubmit="return confirm('Hapus master wilayah ini?')">
                      <input type="hidden" name="action" value="delete_region">
                      <input type="hidden" name="id" value="<?= (int)$region['id'] ?>">
                      <button class="px-2 py-1 rounded bg-red-100 text-red-700">Hapus</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$serviceRegions): ?>
              <tr><td colspan="3" class="py-4 text-slate-500">Belum ada master wilayah. Tambahkan dari form sebelah kiri.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <div class="flex justify-between gap-3 flex-wrap items-center mb-3">
    <h2 class="font-semibold">Daftar Pelanggan</h2>
    <form method="get" class="flex gap-2 flex-wrap items-center text-sm">
      <select name="region" class="border rounded px-3 py-2">
        <option value="0">Semua Wilayah</option>
        <?php foreach ($serviceRegions as $region): ?>
          <option value="<?= (int)$region['id'] ?>" <?= $filterRegionId === (int)$region['id'] ? 'selected' : '' ?>><?= e(customerRegionLabel($region)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="bg-slate-900 text-white rounded px-4 py-2">Filter</button>
      <?php if ($filterRegionId > 0): ?>
        <a href="customers.php" class="px-4 py-2 rounded bg-slate-200 text-slate-800">Reset</a>
      <?php endif; ?>
    </form>
  </div>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">ID</th>
          <th class="py-2 pr-3">Nama</th>
          <th class="py-2 pr-3">Alamat</th>
          <th class="py-2 pr-3">HP</th>
          <th class="py-2 pr-3">Wilayah</th>
          <th class="py-2 pr-3">Tgl Pasang</th>
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
          <td class="py-2 pr-3"><div class="address-cell" title="<?= e(customerRegionLabel($c)) ?>"><?= e(customerRegionLabel($c)) ?></div></td>
          <td class="py-2 pr-3"><?= e(formatDateId((string)($c['installation_date'] ?? ''), '-')) ?></td>
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
        <tr><td colspan="9" class="py-4 text-slate-500">Belum ada pelanggan.</td></tr>
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

  const initRegionTemplate = () => {
    const select = document.getElementById('region_template_id');
    const serviceType = document.getElementById('customer_service_type');
    const village = document.getElementById('customer_village');
    const rw = document.getElementById('customer_rw');
    const district = document.getElementById('customer_district');
    const regency = document.getElementById('customer_regency');

    if (!select || select.dataset.regionInit === '1') return;
    select.dataset.regionInit = '1';

    const applyRegion = (value) => {
      if (!value) return;
      const opt = Array.from(select.options).find((o) => o.value === String(value));
      if (!opt) return;

      if (serviceType) serviceType.value = opt.dataset.serviceType || '';
      if (village) village.value = opt.dataset.village || '';
      if (rw) rw.value = opt.dataset.rw || '';
      if (district) district.value = opt.dataset.district || '';
      if (regency) regency.value = opt.dataset.regency || '';
    };

    select.addEventListener('change', () => applyRegion(select.value));
  };

  const boot = () => {
    initCustomerPicker();
    initRegionTemplate();
  };

  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot, { once: true });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

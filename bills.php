<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth(['admin', 'collector']);

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    $billStmt = $pdo->prepare('SELECT * FROM meter_readings WHERE id = :id LIMIT 1');
    $billStmt->execute([':id' => $id]);
    $currentBill = $billStmt->fetch() ?: null;

    if ($id > 0 && $currentBill && $action === 'mark_paid') {
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cash'));
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
        $paymentDate = paymentDateToDateTime($_POST['payment_date'] ?? null);
        $discountAmount = min(
            normalizeCurrencyInput($_POST['discount_amount'] ?? 0),
            (int)$currentBill['amount_total']
        );

        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            $paymentMethod = 'cash';
        }

        $stmt = $pdo->prepare('UPDATE meter_readings
            SET status = "paid", paid_at = :paid_at, payment_method = :payment_method,
                payment_note = :payment_note, discount_amount = :discount_amount
            WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':paid_at' => $paymentDate,
            ':payment_method' => $paymentMethod,
            ':payment_note' => $paymentNote,
            ':discount_amount' => $discountAmount,
        ]);
        flash('success', 'Tagihan ditandai lunas (' . strtoupper($paymentMethod) . ').');
    }

    if ($id > 0 && $currentBill && $action === 'mark_unpaid') {
        $stmt = $pdo->prepare('UPDATE meter_readings
            SET status = "unpaid", paid_at = NULL, payment_method = NULL,
                payment_note = NULL, discount_amount = 0
            WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Tagihan dikembalikan ke belum lunas.');
    }

    if ($id > 0 && $currentBill && $action === 'update_payment') {
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
        $paymentDate = paymentDateToDateTime($_POST['payment_date'] ?? null, (string)($currentBill['paid_at'] ?? ''));
        $discountAmount = min(
            normalizeCurrencyInput($_POST['discount_amount'] ?? 0),
            (int)$currentBill['amount_total']
        );

        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            flash('error', 'Metode pembayaran harus cash atau transfer.');
            header('Location: bills.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE meter_readings
            SET payment_method = :payment_method, payment_note = :payment_note,
                paid_at = :paid_at, discount_amount = :discount_amount
            WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':payment_method' => $paymentMethod,
            ':payment_note' => $paymentNote,
            ':paid_at' => $paymentDate,
            ':discount_amount' => $discountAmount,
        ]);
        flash('success', 'Pembayaran berhasil diperbarui.');
    }

    header('Location: bills.php');
    exit;
}

$filterStatus = trim($_GET['status'] ?? '');
$filterYear = (int)($_GET['year'] ?? 0);
$filterMonth = (int)($_GET['month'] ?? 0);

$where = [];
$params = [];

if ($user['role'] === 'customer') {
    $where[] = 'mr.customer_id = :customer_id';
    $params[':customer_id'] = (int)$user['customer_id'];
}

if (in_array($filterStatus, ['paid', 'unpaid'], true)) {
    $where[] = 'mr.status = :status';
    $params[':status'] = $filterStatus;
}

if ($filterYear > 0) {
    $where[] = 'mr.period_year = :year';
    $params[':year'] = $filterYear;
}

if ($filterMonth >= 1 && $filterMonth <= 12) {
    $where[] = 'mr.period_month = :month';
    $params[':month'] = $filterMonth;
}

$sql = 'SELECT mr.*, c.name AS customer_name, c.address AS customer_address,
        c.installation_date, c.service_type, c.village, c.rw, c.district, c.regency,
        u.username AS customer_username,
        cls.password_plain AS customer_login_id
    FROM meter_readings mr
    JOIN customers c ON c.id = mr.customer_id
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY mr.period_year DESC, mr.period_month DESC, c.name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

$customerIdentity = null;
if ($user['role'] === 'customer' && !empty($user['customer_id'])) {
    $identityStmt = $pdo->prepare('SELECT c.name, c.address, u.username, cls.password_plain
        FROM customers c
        LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
        LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
        WHERE c.id = :customer_id
        LIMIT 1');
    $identityStmt->execute([':customer_id' => (int)$user['customer_id']]);
    $customerIdentity = $identityStmt->fetch() ?: null;
}

$emptyColspan = 14;

$totalAmount = 0;
$totalUnpaid = 0;
$visiblePaidCount = 0;
$visibleUnpaidCount = 0;
$customerStatusMap = [];
foreach ($bills as $bill) {
    $finalAmount = billNetAmount($bill);
    $totalAmount += $finalAmount;
    if ($bill['status'] === 'unpaid') {
        $totalUnpaid += $finalAmount;
        $visibleUnpaidCount++;
    } else {
        $visiblePaidCount++;
    }

    $customerId = (int)($bill['customer_id'] ?? 0);
    if ($customerId > 0) {
        if (($bill['status'] ?? '') === 'unpaid') {
            $customerStatusMap[$customerId] = 'unpaid';
        } elseif (!isset($customerStatusMap[$customerId])) {
            $customerStatusMap[$customerId] = 'paid';
        }
    }
}

$visiblePaidCustomerCount = count(array_filter($customerStatusMap, static fn ($status) => $status === 'paid'));
$visibleUnpaidCustomerCount = count(array_filter($customerStatusMap, static fn ($status) => $status === 'unpaid'));

require __DIR__ . '/includes/header.php';
?>

<section class="bg-white rounded-xl shadow p-4 mb-4">
  <h2 class="font-semibold mb-3"><?= $user['role'] === 'customer' ? 'Tagihan Air Saya' : 'Daftar Tagihan Air' ?></h2>
  <form class="grid md:grid-cols-4 gap-2 text-sm">
    <select name="status" class="border rounded px-3 py-2">
      <option value="">Semua Status</option>
      <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Lunas</option>
      <option value="unpaid" <?= $filterStatus === 'unpaid' ? 'selected' : '' ?>>Belum Lunas</option>
    </select>
    <input type="number" name="year" value="<?= $filterYear ?: '' ?>" placeholder="Tahun (contoh 2026)" class="border rounded px-3 py-2">
    <select name="month" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= e(monthName($m)) ?></option>
      <?php endfor; ?>
    </select>
    <button class="bg-slate-900 text-white rounded px-4 py-2">Filter</button>
  </form>

  <?php if ($user['role'] !== 'customer'): ?>
    <div class="mt-3 p-3 rounded bg-sky-100 text-sm">
      <b>Info diskon:</b> diskon diberikan dari menu <b>Tagihan</b>. Tinggal isi nominal diskon pada form pembayaran, lalu simpan / tandai lunas.
    </div>
  <?php endif; ?>

  <div class="mt-4 stats-grid-4 text-sm">
    <div class="info-card">
      <div class="info-label">Total Tagihan Tampil</div>
      <div class="info-value"><?= e(rupiah($totalAmount)) ?></div>
      <div class="info-note"><?= count($bills) ?> tagihan</div>
    </div>
    <div class="info-card">
      <div class="info-label">Total Belum Lunas</div>
      <div class="info-value text-amber-600"><?= e(rupiah($totalUnpaid)) ?></div>
      <div class="info-note"><?= $visibleUnpaidCount ?> tagihan belum lunas</div>
    </div>
    <div class="info-card">
      <div class="info-label"><?= $user['role'] === 'customer' ? 'Tagihan Lunas Tampil' : 'Pelanggan Lunas' ?></div>
      <div class="info-value text-emerald-600"><?= $user['role'] === 'customer' ? $visiblePaidCount : $visiblePaidCustomerCount ?></div>
      <div class="info-note"><?= $user['role'] === 'customer' ? 'berdasarkan filter saat ini' : 'unik per pelanggan di hasil filter' ?></div>
    </div>
    <div class="info-card">
      <div class="info-label"><?= $user['role'] === 'customer' ? 'Tagihan Belum Lunas Tampil' : 'Pelanggan Belum Lunas' ?></div>
      <div class="info-value text-amber-600"><?= $user['role'] === 'customer' ? $visibleUnpaidCount : $visibleUnpaidCustomerCount ?></div>
      <div class="info-note"><?= $user['role'] === 'customer' ? 'berdasarkan filter saat ini' : 'unik per pelanggan di hasil filter' ?></div>
    </div>
  </div>

  <?php if ($user['role'] === 'customer' && $customerIdentity): ?>
    <div class="mt-3 p-3 rounded bg-emerald-50 text-emerald-900 text-sm">
      <div><b>Alamat:</b> <?= e((string)($customerIdentity['address'] ?? '-')) ?></div>
      <div><b>ID Pelanggan:</b> <?= e((string)($customerIdentity['password_plain'] ?? '-')) ?> <span class="text-xs text-emerald-700">(ID = Password)</span></div>
    </div>
  <?php endif; ?>
</section>

<section class="bg-white rounded-xl shadow p-4">
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft table-card-mode bill-layout-table" data-page-size="5">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Pelanggan</th>
          <th class="py-2 pr-3">Lokasi</th>
          <th class="py-2 pr-3">Meter</th>
          <th class="py-2 pr-3">Tagihan</th>
          <th class="py-2 pr-3">Pembayaran</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bills as $bill): ?>
        <tr class="border-b">
          <td class="py-2 pr-3" data-label="Periode">
            <div class="bill-meta">
              <div class="font-semibold"><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></div>
              <div class="bill-subline">Jatuh tempo: <?= e($bill['due_date'] ?: '-') ?></div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Pelanggan">
            <?php $idPelanggan = customerLoginId((string)($bill['customer_login_id'] ?? ''), (int)$bill['customer_id']); ?>
            <div class="bill-meta">
              <span class="id-pill"><?= e($idPelanggan) ?></span>
              <div class="name-cell"><?= e((string)$bill['customer_name']) ?></div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Lokasi">
            <div class="bill-meta">
              <div class="address-cell" title="<?= e((string)($bill['customer_address'] ?? '-')) ?>"><?= e((string)($bill['customer_address'] ?? '-')) ?></div>
              <div class="bill-subline"><b>Wilayah:</b> <?= e(customerRegionLabel($bill)) ?></div>
              <div class="bill-subline">Tgl pasang: <?= e(formatDateId((string)($bill['installation_date'] ?? ''), '-')) ?></div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Meter">
            <div class="bill-meter">
              <div><b>Awal:</b> <?= (int)$bill['meter_awal'] ?></div>
              <div><b>Akhir:</b> <?= (int)$bill['meter_akhir'] ?></div>
              <div class="bill-subline">Pemakaian: <?= (int)$bill['usage_m3'] ?> m³</div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Tagihan">
            <div class="bill-charge">
              <div class="font-semibold"><?= e(rupiah((int)$bill['amount_total'])) ?></div>
              <div class="bill-subline">Abonemen: <?= e(rupiah((int)$bill['base_fee'])) ?></div>
              <div class="bill-subline">Tarif: <?= e(rupiah((int)$bill['price_per_m3'])) ?>/m³</div>
              <div class="bill-subline">Diskon: <?= e(rupiah(billDiscountAmount($bill))) ?></div>
              <div class="bill-highlight">Total bayar: <?= e(rupiah(billNetAmount($bill))) ?></div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Pembayaran">
            <div class="bill-payment">
              <div><b>Tgl bayar:</b> <?= e(formatDateId((string)($bill['paid_at'] ?? ''), '-')) ?></div>
              <div><b>Metode:</b> <?= !empty($bill['payment_method']) ? e(strtoupper((string)$bill['payment_method'])) : '-' ?></div>
              <div class="bill-subline"><?= !empty($bill['payment_note']) ? e((string)$bill['payment_note']) : 'Tidak ada catatan pembayaran' ?></div>
            </div>
          </td>
          <td class="py-2 pr-3" data-label="Status">
            <span class="status-pill <?= $bill['status'] === 'paid' ? 'paid' : 'unpaid' ?>">
              <?= $bill['status'] === 'paid' ? 'Lunas' : 'Belum Lunas' ?>
            </span>
          </td>
          <td class="py-2 pr-3" data-label="Aksi">
            <?php if ($user['role'] !== 'customer'): ?>
              <?php if ($bill['status'] === 'unpaid'): ?>
                <div class="bill-actions">
                  <form method="post" class="bill-action-form">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                    <select name="payment_method" class="border rounded px-2 py-2 text-xs">
                      <option value="cash">Cash</option>
                      <option value="transfer">Transfer</option>
                    </select>
                    <input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" class="border rounded px-2 py-2 text-xs">
                    <input type="number" min="0" max="<?= (int)$bill['amount_total'] ?>" name="discount_amount" value="0" class="border rounded px-2 py-2 text-xs" placeholder="diskon opsional">
                    <div class="bill-inline-note">Ketik nominal diskon di sini. Contoh: 5000</div>
                    <input name="payment_note" class="border rounded px-2 py-2 text-xs" placeholder="catatan opsional">
                    <button class="px-3 py-2 rounded bg-emerald-100 text-emerald-700 text-xs font-semibold">Tandai Lunas</button>
                  </form>
                  <div class="bill-action-links">
                    <a class="px-3 py-2 rounded bg-sky-100 text-sky-700 text-xs font-semibold" href="bill_print.php?id=<?= (int)$bill['id'] ?>" target="_blank" rel="noopener">Cetak Tagihan</a>
                  </div>
                </div>
              <?php else: ?>
                <div class="bill-actions">
                  <form method="post" class="bill-action-form">
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                    <select name="payment_method" class="border rounded px-2 py-2 text-xs">
                      <option value="cash" <?= ($bill['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                      <option value="transfer" <?= ($bill['payment_method'] ?? '') === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                    </select>
                    <input type="date" name="payment_date" value="<?= e(dateInputValue((string)($bill['paid_at'] ?? ''))) ?>" class="border rounded px-2 py-2 text-xs">
                    <input type="number" min="0" max="<?= (int)$bill['amount_total'] ?>" name="discount_amount" value="<?= billDiscountAmount($bill) ?>" class="border rounded px-2 py-2 text-xs" placeholder="diskon opsional">
                    <div class="bill-inline-note">Kalau mau ubah diskon, tinggal ganti nominalnya lalu klik update.</div>
                    <input name="payment_note" value="<?= e((string)($bill['payment_note'] ?? '')) ?>" class="border rounded px-2 py-2 text-xs" placeholder="catatan opsional">
                    <button class="px-3 py-2 rounded bg-slate-100 text-slate-700 text-xs font-semibold">Update Bayar</button>
                  </form>
                  <div class="bill-action-links">
                    <a class="px-3 py-2 rounded bg-sky-100 text-sky-700 text-xs font-semibold" href="bill_print.php?id=<?= (int)$bill['id'] ?>" target="_blank" rel="noopener">Cetak Tagihan</a>
                    <a class="px-3 py-2 rounded bg-cyan-100 text-cyan-700 text-xs font-semibold" href="receipt.php?id=<?= (int)$bill['id'] ?>" target="_blank" rel="noopener">Kwitansi</a>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="mark_unpaid">
                      <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                      <button class="px-3 py-2 rounded bg-amber-100 text-amber-700 text-xs font-semibold">Batalkan Lunas</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="bill-actions">
                <div class="bill-action-links">
                  <a class="px-3 py-2 rounded bg-sky-100 text-sky-700 text-xs font-semibold" href="bill_print.php?id=<?= (int)$bill['id'] ?>" target="_blank" rel="noopener">Cetak Tagihan</a>
                  <?php if ($bill['status'] === 'paid'): ?>
                    <a class="px-3 py-2 rounded bg-cyan-100 text-cyan-700 text-xs font-semibold" href="receipt.php?id=<?= (int)$bill['id'] ?>" target="_blank" rel="noopener">Kwitansi</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bills): ?>
        <tr><td colspan="<?= $emptyColspan ?>" class="py-4 text-slate-500">Tidak ada tagihan untuk filter ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

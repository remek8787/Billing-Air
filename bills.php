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

    if ($id > 0 && $action === 'mark_paid') {
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cash'));
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));

        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            $paymentMethod = 'cash';
        }

        $stmt = $pdo->prepare('UPDATE meter_readings
            SET status = "paid", paid_at = :paid_at, payment_method = :payment_method, payment_note = :payment_note
            WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':paid_at' => date('Y-m-d H:i:s'),
            ':payment_method' => $paymentMethod,
            ':payment_note' => $paymentNote,
        ]);
        flash('success', 'Tagihan ditandai lunas (' . strtoupper($paymentMethod) . ').');
    }

    if ($id > 0 && $action === 'mark_unpaid') {
        $stmt = $pdo->prepare('UPDATE meter_readings
            SET status = "unpaid", paid_at = NULL, payment_method = NULL, payment_note = NULL
            WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Tagihan dikembalikan ke belum lunas.');
    }

    if ($id > 0 && $action === 'update_payment') {
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));

        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            flash('error', 'Metode pembayaran harus cash atau transfer.');
            header('Location: bills.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE meter_readings
            SET payment_method = :payment_method, payment_note = :payment_note
            WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':payment_method' => $paymentMethod,
            ':payment_note' => $paymentNote,
        ]);
        flash('success', 'Metode pembayaran berhasil diperbarui.');
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

$emptyColspan = $user['role'] === 'customer' ? 13 : 14;

$totalAmount = 0;
$totalUnpaid = 0;
foreach ($bills as $bill) {
    $totalAmount += (int)$bill['amount_total'];
    if ($bill['status'] === 'unpaid') {
        $totalUnpaid += (int)$bill['amount_total'];
    }
}

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

  <div class="mt-4 grid md:grid-cols-2 gap-3 text-sm">
    <div class="p-3 rounded bg-slate-100">Total Tagihan Tampil: <b><?= e(rupiah($totalAmount)) ?></b></div>
    <div class="p-3 rounded bg-amber-100 text-amber-900">Total Belum Lunas: <b><?= e(rupiah($totalUnpaid)) ?></b></div>
  </div>

  <?php if ($user['role'] === 'customer' && $customerIdentity): ?>
    <div class="mt-3 p-3 rounded bg-emerald-50 text-emerald-900 text-sm">
      <div><b>Alamat:</b> <?= e((string)($customerIdentity['address'] ?? '-')) ?></div>
      <div><b>ID Pelanggan:</b> <?= e((string)($customerIdentity['password_plain'] ?? '-')) ?> <span class="text-xs text-emerald-700">(ID = Password)</span></div>
    </div>
  <?php endif; ?>
</section>

<section class="bg-white rounded-xl shadow p-4">
  <div class="overflow-auto">
    <table class="min-w-full text-sm js-data-table" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <?php if ($user['role'] !== 'customer'): ?><th class="py-2 pr-3">Pelanggan</th><?php endif; ?>
          <th class="py-2 pr-3">Alamat</th>
          <th class="py-2 pr-3">ID Pelanggan</th>
          <th class="py-2 pr-3">Awal</th>
          <th class="py-2 pr-3">Akhir</th>
          <th class="py-2 pr-3">Pemakaian</th>
          <th class="py-2 pr-3">Abonemen</th>
          <th class="py-2 pr-3">Tarif/m³</th>
          <th class="py-2 pr-3">Total</th>
          <th class="py-2 pr-3">Status</th>
          <th class="py-2 pr-3">Metode Bayar</th>
          <th class="py-2 pr-3">Jatuh Tempo</th>
          <th class="py-2 pr-3">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bills as $bill): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></td>
          <?php if ($user['role'] !== 'customer'): ?><td class="py-2 pr-3"><?= e($bill['customer_name']) ?></td><?php endif; ?>
          <td class="py-2 pr-3"><?= e((string)($bill['customer_address'] ?? '-')) ?></td>
          <td class="py-2 pr-3">
            <?php if (!empty($bill['customer_login_id'])): ?>
              <code class="px-2 py-1 rounded bg-slate-100 text-slate-800"><?= e((string)$bill['customer_login_id']) ?></code>
            <?php else: ?>
              <span class="text-xs text-slate-500">-</span>
            <?php endif; ?>
          </td>
          <td class="py-2 pr-3"><?= (int)$bill['meter_awal'] ?></td>
          <td class="py-2 pr-3"><?= (int)$bill['meter_akhir'] ?></td>
          <td class="py-2 pr-3"><?= (int)$bill['usage_m3'] ?> m³</td>
          <td class="py-2 pr-3"><?= e(rupiah((int)$bill['base_fee'])) ?></td>
          <td class="py-2 pr-3"><?= e(rupiah((int)$bill['price_per_m3'])) ?></td>
          <td class="py-2 pr-3 font-semibold"><?= e(rupiah((int)$bill['amount_total'])) ?></td>
          <td class="py-2 pr-3">
            <span class="px-2 py-1 rounded text-xs <?= $bill['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
              <?= $bill['status'] === 'paid' ? 'Lunas' : 'Belum Lunas' ?>
            </span>
          </td>
          <td class="py-2 pr-3">
            <?php if (!empty($bill['payment_method'])): ?>
              <span class="badge text-bg-info"><?= e(strtoupper((string)$bill['payment_method'])) ?></span>
              <?php if (!empty($bill['payment_note'])): ?>
                <div class="text-xs text-slate-500 mt-1"><?= e((string)$bill['payment_note']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-xs text-slate-500">-</span>
            <?php endif; ?>
          </td>
          <td class="py-2 pr-3"><?= e($bill['due_date'] ?: '-') ?></td>
          <td class="py-2 pr-3">
            <?php if ($user['role'] !== 'customer'): ?>
              <?php if ($bill['status'] === 'unpaid'): ?>
                <form method="post" class="space-y-1">
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                  <select name="payment_method" class="border rounded px-2 py-1 text-xs">
                    <option value="cash">Cash</option>
                    <option value="transfer">Transfer</option>
                  </select>
                  <input name="payment_note" class="border rounded px-2 py-1 text-xs w-36" placeholder="catatan opsional">
                  <button class="px-2 py-1 rounded bg-emerald-100 text-emerald-700 text-xs">Tandai Lunas</button>
                </form>
              <?php else: ?>
                <form method="post" class="space-y-1 mb-1">
                  <input type="hidden" name="action" value="update_payment">
                  <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                  <select name="payment_method" class="border rounded px-2 py-1 text-xs">
                    <option value="cash" <?= ($bill['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="transfer" <?= ($bill['payment_method'] ?? '') === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                  </select>
                  <input name="payment_note" value="<?= e((string)($bill['payment_note'] ?? '')) ?>" class="border rounded px-2 py-1 text-xs w-36" placeholder="catatan opsional">
                  <button class="px-2 py-1 rounded bg-slate-100 text-slate-700 text-xs">Update Bayar</button>
                </form>
                <form method="post" class="inline">
                  <input type="hidden" name="action" value="mark_unpaid">
                  <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
                  <button class="px-2 py-1 rounded bg-amber-100 text-amber-700 text-xs">Batalkan Lunas</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <?= !empty($bill['payment_method']) ? e(strtoupper((string)$bill['payment_method'])) : '-' ?>
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

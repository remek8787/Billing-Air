<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$user = currentUser();
$pdo = db();

$customerCount = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$unpaidCount = (int)$pdo->query("SELECT COUNT(*) FROM meter_readings WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int)$pdo->query("SELECT COALESCE(SUM(amount_total),0) FROM meter_readings WHERE status = 'unpaid'")->fetchColumn();
$unpaidCustomerCount = (int)$pdo->query("SELECT COUNT(DISTINCT customer_id) FROM meter_readings WHERE status = 'unpaid'")->fetchColumn();
$paidCustomerCount = max(0, $customerCount - $unpaidCustomerCount);

if ($user['role'] === 'customer' && !empty($user['customer_id'])) {
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total_rows,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_rows,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_rows,
            COALESCE(SUM(CASE WHEN status = 'unpaid' THEN amount_total ELSE 0 END), 0) AS unpaid_amount
        FROM meter_readings WHERE customer_id = :customer_id");
    $stmt->execute([':customer_id' => (int)$user['customer_id']]);
    $mine = $stmt->fetch() ?: [];

    $customerCount = (int)($mine['total_rows'] ?? 0);
    $paidCustomerCount = (int)($mine['paid_rows'] ?? 0);
    $unpaidCustomerCount = (int)($mine['unpaid_rows'] ?? 0);
    $unpaidCount = $unpaidCustomerCount;
    $unpaidTotal = (int)($mine['unpaid_amount'] ?? 0);
}

$recentBills = [];
if ($user['role'] === 'customer') {
    $stmt = $pdo->prepare('SELECT mr.*, c.name AS customer_name, c.address AS customer_address,
            cls.password_plain AS customer_login_id
        FROM meter_readings mr
        JOIN customers c ON c.id = mr.customer_id
        LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
        LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
        WHERE mr.customer_id = :customer_id
        ORDER BY mr.period_year DESC, mr.period_month DESC
        LIMIT 50');
    $stmt->execute([':customer_id' => $user['customer_id']]);
    $recentBills = $stmt->fetchAll();
} else {
    $recentBills = $pdo->query('SELECT mr.*, c.name AS customer_name, c.address AS customer_address,
            cls.password_plain AS customer_login_id
        FROM meter_readings mr
        JOIN customers c ON c.id = mr.customer_id
        LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
        LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
        ORDER BY mr.period_year DESC, mr.period_month DESC,
            CASE WHEN c.customer_no IS NULL OR c.customer_no <= 0 THEN 999999 ELSE c.customer_no END ASC,
            c.name ASC')->fetchAll();
}

$emptyColspan = 9;

require __DIR__ . '/includes/header.php';
?>

<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500"><?= $user['role'] === 'customer' ? 'Total Tagihan Saya' : 'Semua Pelanggan' ?></p>
    <p class="text-3xl font-bold"><?= $customerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500"><?= $user['role'] === 'customer' ? 'Tagihan Lunas Saya' : 'Pelanggan Lunas' ?></p>
    <p class="text-3xl font-bold text-emerald-600"><?= $paidCustomerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500"><?= $user['role'] === 'customer' ? 'Tagihan Belum Lunas Saya' : 'Pelanggan Belum Lunas' ?></p>
    <p class="text-3xl font-bold text-amber-600"><?= $unpaidCustomerCount ?></p>
  </div>
  <div class="stat-card p-4">
    <p class="text-sm text-slate-500">Total Piutang</p>
    <p class="text-3xl font-bold"><?= e(rupiah($unpaidTotal)) ?></p>
    <div class="text-xs text-slate-500 mt-1">Tagihan belum lunas: <?= $unpaidCount ?></div>
  </div>
</div>

<div class="bg-white rounded-xl shadow p-4">
  <h2 class="text-lg font-semibold mb-3"><?= $user['role'] === 'customer' ? 'Riwayat Tagihan Saya' : 'Daftar Tagihan Terdata' ?></h2>
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">ID Pelanggan</th>
          <th class="py-2 pr-3">Nama</th>
          <th class="py-2 pr-3">Alamat</th>
          <th class="py-2 pr-3">Meter Awal</th>
          <th class="py-2 pr-3">Meter Akhir</th>
          <th class="py-2 pr-3">Pemakaian</th>
          <th class="py-2 pr-3">Total</th>
          <th class="py-2 pr-3">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentBills as $bill): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></td>
            <td class="py-2 pr-3">
              <?php
                $idPelanggan = (string)($bill['customer_login_id'] ?? '');
                if ($idPelanggan === '' && !empty($bill['customer_id'])) {
                    $idPelanggan = defaultCustomerPasswordById((int)$bill['customer_id']);
                }
              ?>
              <span class="id-pill"><?= e($idPelanggan !== '' ? $idPelanggan : '-') ?></span>
            </td>
            <td class="py-2 pr-3"><div class="name-cell"><?= e((string)($bill['customer_name'] ?? '-')) ?></div></td>
            <td class="py-2 pr-3"><div class="address-cell" title="<?= e((string)($bill['customer_address'] ?? '-')) ?>"><?= e((string)($bill['customer_address'] ?? '-')) ?></div></td>
            <td class="py-2 pr-3"><?= (int)$bill['meter_awal'] ?></td>
            <td class="py-2 pr-3"><?= (int)$bill['meter_akhir'] ?></td>
            <td class="py-2 pr-3"><?= (int)$bill['usage_m3'] ?> m³</td>
            <td class="py-2 pr-3"><?= e(rupiah((int)$bill['amount_total'])) ?></td>
            <td class="py-2 pr-3">
              <span class="status-pill <?= $bill['status'] === 'paid' ? 'paid' : 'unpaid' ?>">
                <?= $bill['status'] === 'paid' ? 'Lunas' : 'Belum Lunas' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentBills): ?>
          <tr><td colspan="<?= $emptyColspan ?>" class="py-4 text-slate-500">Belum ada data tagihan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

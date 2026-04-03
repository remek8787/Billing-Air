<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$user = currentUser();
$pdo = db();

$customerCount = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$unpaidCount = (int)$pdo->query("SELECT COUNT(*) FROM meter_readings WHERE status = 'unpaid'")->fetchColumn();
$unpaidTotal = (int)$pdo->query("SELECT COALESCE(SUM(amount_total),0) FROM meter_readings WHERE status = 'unpaid'")->fetchColumn();

$recentBills = [];
if ($user['role'] === 'customer') {
    $stmt = $pdo->prepare('SELECT mr.*, c.name FROM meter_readings mr JOIN customers c ON c.id = mr.customer_id
        WHERE mr.customer_id = :customer_id ORDER BY period_year DESC, period_month DESC LIMIT 6');
    $stmt->execute([':customer_id' => $user['customer_id']]);
    $recentBills = $stmt->fetchAll();
} else {
    $recentBills = $pdo->query('SELECT mr.*, c.name FROM meter_readings mr JOIN customers c ON c.id = mr.customer_id
        ORDER BY mr.created_at DESC LIMIT 10')->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>

<div class="grid md:grid-cols-3 gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4">
    <p class="text-sm text-slate-500">Jumlah Pelanggan</p>
    <p class="text-3xl font-bold"><?= $customerCount ?></p>
  </div>
  <div class="bg-white rounded-xl shadow p-4">
    <p class="text-sm text-slate-500">Tagihan Belum Lunas</p>
    <p class="text-3xl font-bold"><?= $unpaidCount ?></p>
  </div>
  <div class="bg-white rounded-xl shadow p-4">
    <p class="text-sm text-slate-500">Total Piutang</p>
    <p class="text-3xl font-bold"><?= e(rupiah($unpaidTotal)) ?></p>
  </div>
</div>

<div class="bg-white rounded-xl shadow p-4">
  <h2 class="text-lg font-semibold mb-3"><?= $user['role'] === 'customer' ? 'Riwayat Tagihan Saya' : 'Input/Tagihan Terbaru' ?></h2>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <?php if ($user['role'] !== 'customer'): ?><th class="py-2 pr-3">Pelanggan</th><?php endif; ?>
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
            <?php if ($user['role'] !== 'customer'): ?><td class="py-2 pr-3"><?= e($bill['name']) ?></td><?php endif; ?>
            <td class="py-2 pr-3"><?= (int)$bill['meter_awal'] ?></td>
            <td class="py-2 pr-3"><?= (int)$bill['meter_akhir'] ?></td>
            <td class="py-2 pr-3"><?= (int)$bill['usage_m3'] ?> m³</td>
            <td class="py-2 pr-3"><?= e(rupiah((int)$bill['amount_total'])) ?></td>
            <td class="py-2 pr-3">
              <span class="px-2 py-1 rounded text-xs <?= $bill['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                <?= e($bill['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentBills): ?>
          <tr><td colspan="7" class="py-4 text-slate-500">Belum ada data tagihan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

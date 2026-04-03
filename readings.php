<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'collector']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $month = (int)($_POST['period_month'] ?? 0);
    $year = (int)($_POST['period_year'] ?? 0);
    $meterAkhir = (int)($_POST['meter_akhir'] ?? 0);

    if ($customerId <= 0 || $month < 1 || $month > 12 || $year < 2020 || $meterAkhir < 0) {
        flash('error', 'Data input meter tidak valid.');
        header('Location: readings.php');
        exit;
    }

    upsertReading($customerId, $month, $year, $meterAkhir, (int)$user['id']);
    flash('success', 'Input meter berhasil disimpan.');
    header('Location: readings.php');
    exit;
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name ASC')->fetchAll();

$latest = $pdo->query('SELECT mr.*, c.name AS customer_name, u.full_name AS input_name
    FROM meter_readings mr
    JOIN customers c ON c.id = mr.customer_id
    LEFT JOIN users u ON u.id = mr.input_by
    ORDER BY mr.period_year DESC, mr.period_month DESC, mr.customer_id ASC
    LIMIT 40')->fetchAll();

$nowMonth = (int)date('n');
$nowYear = (int)date('Y');

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3">Input Meter Bulanan</h2>
    <form method="post" class="space-y-3">
      <div>
        <label class="text-sm">Pelanggan</label>
        <select name="customer_id" required class="mt-1 w-full border rounded px-3 py-2">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="text-sm">Bulan</label>
          <select name="period_month" required class="mt-1 w-full border rounded px-3 py-2">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m === $nowMonth ? 'selected' : '' ?>><?= e(monthName($m)) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="text-sm">Tahun</label>
          <input type="number" name="period_year" required class="mt-1 w-full border rounded px-3 py-2" value="<?= $nowYear ?>">
        </div>
      </div>

      <div>
        <label class="text-sm">Meter Akhir Bulan Ini</label>
        <input type="number" min="0" name="meter_akhir" required class="mt-1 w-full border rounded px-3 py-2" placeholder="Contoh: 1250">
      </div>

      <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan Input Meter</button>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-4 lg:col-span-2">
    <h2 class="font-semibold mb-3">Cara Hitung</h2>
    <ul class="text-sm text-slate-700 space-y-1 list-disc pl-5">
      <li>Meter awal otomatis diambil dari meter akhir periode sebelumnya.</li>
      <li>Pemakaian (m³) = Meter Akhir - Meter Awal.</li>
      <li>Total = Abonemen + (Pemakaian × Tarif per m³).</li>
      <li>Default saat ini: Abonemen <b><?= e(rupiah(appSetting('base_fee', DEFAULT_BASE_FEE))) ?></b>, Tarif m³ <b><?= e(rupiah(appSetting('price_per_m3', DEFAULT_PRICE_PER_M3))) ?></b>.</li>
    </ul>
  </section>
</div>

<section class="bg-white rounded-xl shadow p-4 mt-4">
  <h2 class="font-semibold mb-3">Data Meter Terbaru</h2>
  <div class="overflow-auto">
    <table class="min-w-full text-sm js-data-table" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">Pelanggan</th>
          <th class="py-2 pr-3">Awal</th>
          <th class="py-2 pr-3">Akhir</th>
          <th class="py-2 pr-3">Pemakaian</th>
          <th class="py-2 pr-3">Total</th>
          <th class="py-2 pr-3">Input By</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($latest as $r): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= e(periodLabel((int)$r['period_month'], (int)$r['period_year'])) ?></td>
          <td class="py-2 pr-3"><?= e($r['customer_name']) ?></td>
          <td class="py-2 pr-3"><?= (int)$r['meter_awal'] ?></td>
          <td class="py-2 pr-3"><?= (int)$r['meter_akhir'] ?></td>
          <td class="py-2 pr-3"><?= (int)$r['usage_m3'] ?> m³</td>
          <td class="py-2 pr-3"><?= e(rupiah((int)$r['amount_total'])) ?></td>
          <td class="py-2 pr-3"><?= e($r['input_name'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$latest): ?>
        <tr><td colspan="7" class="py-4 text-slate-500">Belum ada data meter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

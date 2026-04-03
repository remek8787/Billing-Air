<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth(['admin', 'collector']);

$pdo = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_reading';

    if ($action === 'delete_reading') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM meter_readings WHERE id = :id');
            $stmt->execute([':id' => $id]);
            flash('success', 'Data meter berhasil dihapus.');
        }
        header('Location: readings.php');
        exit;
    }

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
    flash('success', 'Input meter berhasil disimpan / diperbarui.');
    header('Location: readings.php');
    exit;
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name ASC')->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editReading = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM meter_readings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editReading = $stmt->fetch();
}

$latest = $pdo->query('SELECT mr.*, c.name AS customer_name, u.full_name AS input_name
    FROM meter_readings mr
    JOIN customers c ON c.id = mr.customer_id
    LEFT JOIN users u ON u.id = mr.input_by
    ORDER BY mr.period_year DESC, mr.period_month DESC, mr.customer_id ASC
    LIMIT 40')->fetchAll();

$nowMonth = $editReading ? (int)$editReading['period_month'] : (int)date('n');
$nowYear = $editReading ? (int)$editReading['period_year'] : (int)date('Y');
$selectedCustomerId = $editReading ? (int)$editReading['customer_id'] : 0;
$meterAkhirValue = $editReading ? (int)$editReading['meter_akhir'] : 0;

require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-4">
  <section class="bg-white rounded-xl shadow p-4">
    <h2 class="font-semibold mb-3"><?= $editReading ? 'Edit Input Meter' : 'Input Meter Bulanan' ?></h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="save_reading">
      <div>
        <label class="text-sm">Pelanggan</label>
        <select name="customer_id" required class="mt-1 w-full border rounded px-3 py-2" id="reading_customer_id">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $selectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
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
        <input type="number" min="0" name="meter_akhir" required class="mt-1 w-full border rounded px-3 py-2" placeholder="Contoh: 1250" value="<?= $meterAkhirValue > 0 ? $meterAkhirValue : '' ?>">
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <button class="bg-slate-900 text-white rounded px-4 py-2"><?= $editReading ? 'Update Meter' : 'Simpan Input Meter' ?></button>
        <?php if ($editReading): ?>
          <a class="btn btn-outline-secondary btn-sm" href="readings.php">Batal Edit</a>
        <?php endif; ?>
      </div>
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
          <th class="py-2 pr-3">Aksi</th>
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
          <td class="py-2 pr-3">
            <a class="btn btn-sm btn-outline-primary" href="readings.php?edit=<?= (int)$r['id'] ?>">Edit</a>
            <form method="post" class="inline" onsubmit="return confirm('Hapus data meter ini?')">
              <input type="hidden" name="action" value="delete_reading">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$latest): ?>
        <tr><td colspan="8" class="py-4 text-slate-500">Belum ada data meter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const select = document.getElementById('reading_customer_id');
  if (select && window.TomSelect) {
    new TomSelect(select, {
      create: false,
      maxItems: 1,
      searchField: ['text'],
      placeholder: 'Pilih / ketik nama pelanggan...'
    });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

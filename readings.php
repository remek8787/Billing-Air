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

$serviceRegions = $pdo->query('SELECT * FROM service_regions
    ORDER BY service_type ASC, village ASC,
        CASE WHEN rw IS NULL OR rw = "" THEN "999" ELSE rw END ASC,
        district ASC, regency ASC, id DESC')->fetchAll();

$customers = $pdo->query('SELECT c.id, c.name, c.address, c.service_type, c.village, c.rw, c.district, c.regency,
        cls.password_plain AS customer_login_id
    FROM customers c
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
    ORDER BY c.name ASC')->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editReading = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM meter_readings WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editReading = $stmt->fetch();
}

$latest = $pdo->query('SELECT mr.*, c.name AS customer_name, c.address AS customer_address,
        c.service_type, c.village, c.rw, c.district, c.regency,
        cls.password_plain AS customer_login_id,
        u.full_name AS input_name
    FROM meter_readings mr
    JOIN customers c ON c.id = mr.customer_id
    LEFT JOIN users cu ON cu.customer_id = c.id AND cu.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = cu.id
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
        <label class="text-sm">Wilayah</label>
        <select class="mt-1 w-full border rounded px-3 py-2" id="reading_region_id">
          <option value="">Semua wilayah</option>
          <?php foreach ($serviceRegions as $region): ?>
            <option value="<?= (int)$region['id'] ?>" data-region-key="<?= e(customerRegionKey($region)) ?>"><?= e(customerRegionLabel($region)) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-500 mt-1">Pilih wilayah dulu supaya daftar pelanggan lebih rapi.</p>
      </div>
      <div>
        <label class="text-sm">Pelanggan</label>
        <select name="customer_id" required class="mt-1 w-full border rounded px-3 py-2" id="reading_customer_id">
          <option value="">Pilih pelanggan</option>
          <?php foreach ($customers as $c): ?>
            <?php
              $defaultId = !empty($c['customer_login_id']) ? (string)$c['customer_login_id'] : defaultCustomerPasswordById((int)$c['id']);
              $regionLabel = customerRegionLabel($c);
            ?>
            <option value="<?= (int)$c['id'] ?>"
                    data-region-key="<?= e(customerRegionKey($c)) ?>"
                    <?= $selectedCustomerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($defaultId . ' • ' . $c['name'] . ' • ' . $regionLabel) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-500 mt-1">Format tampil: ID Pelanggan • Nama • Wilayah</p>
      </div>

      <div class="grid md:grid-cols-2 gap-2">
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

      <div class="d-flex gap-2 flex-wrap mobile-form-actions">
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
  <div class="overflow-auto table-wrap">
    <table class="min-w-full text-sm js-data-table table-soft" data-page-size="10">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Periode</th>
          <th class="py-2 pr-3">ID Pelanggan</th>
          <th class="py-2 pr-3">Nama</th>
          <th class="py-2 pr-3">Alamat</th>
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
          <td class="py-2 pr-3">
            <?php
              $idPelanggan = (string)($r['customer_login_id'] ?? '');
              if ($idPelanggan === '') {
                  $idPelanggan = defaultCustomerPasswordById((int)$r['customer_id']);
              }
            ?>
            <span class="id-pill"><?= e($idPelanggan) ?></span>
          </td>
          <td class="py-2 pr-3"><div class="name-cell"><?= e($r['customer_name']) ?></div></td>
          <td class="py-2 pr-3">
            <div class="address-cell" title="<?= e((string)($r['customer_address'] ?? '-')) ?>"><?= e((string)($r['customer_address'] ?? '-')) ?></div>
            <div class="bill-subline"><b>Wilayah:</b> <?= e(customerRegionLabel($r)) ?></div>
          </td>
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
        <tr><td colspan="10" class="py-4 text-slate-500">Belum ada data meter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const initReadingPicker = () => {
    const select = document.getElementById('reading_customer_id');
    const regionSelect = document.getElementById('reading_region_id');
    if (!select || select.dataset.pickerInit === '1') return;
    select.dataset.pickerInit = '1';

    const baseOptions = Array.from(select.options).map((opt) => ({
      value: opt.value,
      text: opt.text,
      selected: opt.selected,
      regionKey: opt.dataset.regionKey || ''
    }));

    let tom = null;
    const selectedValue = () => {
      if (tom) {
        return tom.getValue();
      }
      return select.value;
    };

    const renderOptions = (regionKey = '') => {
      const keepValue = selectedValue();
      const filtered = baseOptions.filter((opt) => {
        if (opt.value === '') return true;
        return regionKey === '' || opt.regionKey === regionKey;
      });

      if (tom) {
        tom.clear(true);
        tom.clearOptions();
        tom.addOptions(filtered.map((opt) => ({ value: opt.value, text: opt.text })));
        tom.refreshOptions(false);
        const stillExists = filtered.some((opt) => opt.value === keepValue);
        if (stillExists && keepValue !== '') {
          tom.setValue(keepValue, true);
        }
        return;
      }

      select.innerHTML = '';
      filtered.forEach((opt) => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.text;
        option.dataset.regionKey = opt.regionKey;
        if (opt.value === keepValue || (keepValue === '' && opt.selected)) {
          option.selected = true;
        }
        select.appendChild(option);
      });
    };

    if (window.TomSelect) {
      tom = new TomSelect(select, {
        create: false,
        maxItems: 1,
        searchField: ['text'],
        placeholder: 'Ketik ID / nama pelanggan / wilayah...'
      });
    }

    const applyRegion = () => {
      const selected = regionSelect ? regionSelect.options[regionSelect.selectedIndex] : null;
      const regionKey = selected ? (selected.dataset.regionKey || '') : '';
      renderOptions(regionKey);
    };

    if (regionSelect) {
      regionSelect.addEventListener('change', applyRegion);
    }

    applyRegion();
  };

  if (document.readyState === 'complete') {
    initReadingPicker();
  } else {
    window.addEventListener('load', initReadingPicker, { once: true });
  }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>

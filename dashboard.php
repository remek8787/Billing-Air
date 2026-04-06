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
$paidCustomerCount = (int)$pdo->query("SELECT COUNT(*) FROM customers c
    WHERE EXISTS (
        SELECT 1 FROM meter_readings mr_paid
        WHERE mr_paid.customer_id = c.id AND mr_paid.status = 'paid'
    )
    AND NOT EXISTS (
        SELECT 1 FROM meter_readings mr_unpaid
        WHERE mr_unpaid.customer_id = c.id AND mr_unpaid.status = 'unpaid'
    )")->fetchColumn();
$noBillCustomerCount = max(0, $customerCount - $paidCustomerCount - $unpaidCustomerCount);

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

<section class="dashboard-hero-card mb-4">
  <div class="dashboard-hero-grid">
    <div>
      <div class="dashboard-hero-kicker">Dashboard Aplikasi</div>
      <h2 class="dashboard-hero-title">DENTA TIRTA</h2>
      <p class="dashboard-hero-text">
        Logo, pengumuman penting, tutorial penggunaan, dan langkah instalasi sekarang difokuskan di dashboard biar lebih rapi dan gampang dicari.
      </p>
      <div class="dashboard-hero-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tutorialModal">
          <i class="bi bi-journal-text me-2"></i>Tutorial
        </button>
        <button type="button" class="btn btn-outline-primary" data-open-install-guide="1">
          <i class="bi bi-phone me-2"></i>Langkah Instalasi
        </button>
        <?php if ($user['role'] !== 'customer'): ?>
          <a href="bills.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt-cutoff me-2"></i>Lihat Tagihan
          </a>
        <?php else: ?>
          <a href="bills.php" class="btn btn-outline-secondary">
            <i class="bi bi-wallet2 me-2"></i>Lihat Tagihan Saya
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="dashboard-hero-logo-wrap">
      <img src="assets/app-logo.svg" alt="Logo DENTA TIRTA" class="dashboard-hero-logo">
    </div>
  </div>
</section>

<section class="grid lg:grid-cols-2 gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card">
    <div class="dashboard-section-kicker">Update Terbaru</div>
    <div class="dashboard-section-head">
      <div>
        <h3 class="dashboard-section-title">Improve kecil yang sudah aktif</h3>
        <p class="dashboard-section-text">Ringkasan perubahan terbaru untuk memudahkan admin, collector, dan pelanggan saat pakai aplikasi.</p>
      </div>
      <span class="dashboard-update-badge">Live</span>
    </div>
    <div class="dashboard-feature-grid">
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-calendar2-check"></i></div>
        <div>
          <div class="dashboard-feature-title">Tanggal pemasangan & pembayaran</div>
          <div class="dashboard-feature-text">Data pelanggan dan riwayat pembayaran sekarang lebih jelas dibaca.</div>
        </div>
      </div>
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-percent"></i></div>
        <div>
          <div class="dashboard-feature-title">Diskon pembayaran opsional</div>
          <div class="dashboard-feature-text">Bisa isi nominal diskon langsung saat tandai lunas atau update pembayaran.</div>
        </div>
      </div>
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
          <div class="dashboard-feature-title">Nota tagihan & kwitansi lunas</div>
          <div class="dashboard-feature-text">Admin dan pelanggan bisa cetak tagihan, lalu cetak kwitansi jika status sudah lunas.</div>
        </div>
      </div>
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-person-vcard"></i></div>
        <div>
          <div class="dashboard-feature-title">ID pelanggan makin konsisten</div>
          <div class="dashboard-feature-text">ID Pelanggan tampil lebih luas, dan tetap mengikuti format DSA + 4 digit.</div>
        </div>
      </div>
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-search"></i></div>
        <div>
          <div class="dashboard-feature-title">Pencarian data lebih cepat</div>
          <div class="dashboard-feature-text">Tabel dan pemilihan pelanggan sudah mendukung pencarian supaya input tidak ribet.</div>
        </div>
      </div>
      <div class="dashboard-feature-item">
        <div class="dashboard-feature-icon"><i class="bi bi-phone"></i></div>
        <div>
          <div class="dashboard-feature-title">Dashboard fokus + install guide</div>
          <div class="dashboard-feature-text">Tutorial, info fitur, dan panduan pasang ke layar utama sekarang dipusatkan di dashboard.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card">
    <div class="dashboard-section-kicker">Cara Pakai</div>
    <div class="dashboard-section-head">
      <div>
        <h3 class="dashboard-section-title">Alur penggunaan cepat</h3>
        <p class="dashboard-section-text">Urutan singkat biar kerja admin/collector lebih rapi dan pelanggan juga gampang cek tagihan.</p>
      </div>
    </div>
    <div class="dashboard-flow-list">
      <div class="dashboard-flow-item">
        <span class="dashboard-flow-no">1</span>
        <div>
          <div class="dashboard-flow-title">Tambah pelanggan</div>
          <div class="dashboard-flow-text">Isi nama, alamat, no HP, dan tanggal pemasangan di menu <b>Pelanggan</b>.</div>
        </div>
      </div>
      <div class="dashboard-flow-item">
        <span class="dashboard-flow-no">2</span>
        <div>
          <div class="dashboard-flow-title">Input meter bulanan</div>
          <div class="dashboard-flow-text">Masuk menu <b>Input Meter</b>, pilih pelanggan, lalu isi meter akhir periode berjalan.</div>
        </div>
      </div>
      <div class="dashboard-flow-item">
        <span class="dashboard-flow-no">3</span>
        <div>
          <div class="dashboard-flow-title">Cek dan proses tagihan</div>
          <div class="dashboard-flow-text">Di menu <b>Tagihan</b>, cek total, isi tanggal bayar, metode bayar, dan diskon opsional kalau ada.</div>
        </div>
      </div>
      <div class="dashboard-flow-item">
        <span class="dashboard-flow-no">4</span>
        <div>
          <div class="dashboard-flow-title">Cetak dokumen pembayaran</div>
          <div class="dashboard-flow-text">Gunakan tombol <b>Cetak Tagihan</b> untuk nota dan <b>Kwitansi</b> untuk pembayaran yang sudah lunas.</div>
        </div>
      </div>
      <div class="dashboard-flow-item">
        <span class="dashboard-flow-no">5</span>
        <div>
          <div class="dashboard-flow-title">Pelanggan tinggal login</div>
          <div class="dashboard-flow-text">Pelanggan bisa cek riwayat pemakaian, status bayar, dan cetak dokumen sendiri dari akun masing-masing.</div>
        </div>
      </div>
    </div>

    <div class="dashboard-shortcuts">
      <?php if ($user['role'] !== 'customer'): ?>
        <a href="customers.php" class="dashboard-shortcut-card">
          <i class="bi bi-people"></i>
          <span>Kelola Pelanggan</span>
        </a>
        <a href="readings.php" class="dashboard-shortcut-card">
          <i class="bi bi-speedometer"></i>
          <span>Input Meter</span>
        </a>
      <?php endif; ?>
      <a href="bills.php" class="dashboard-shortcut-card">
        <i class="bi bi-receipt"></i>
        <span><?= $user['role'] === 'customer' ? 'Lihat Tagihan Saya' : 'Kelola Tagihan' ?></span>
      </a>
      <button type="button" class="dashboard-shortcut-card" data-bs-toggle="modal" data-bs-target="#tutorialModal">
        <i class="bi bi-journal-richtext"></i>
        <span>Buka Tutorial</span>
      </button>
    </div>
  </div>
</section>

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
    <div class="text-xs text-slate-500 mt-1">
      Tagihan belum lunas: <?= $unpaidCount ?>
      <?php if ($user['role'] !== 'customer' && $noBillCustomerCount > 0): ?>
        • Belum ada tagihan: <?= $noBillCustomerCount ?> pelanggan
      <?php endif; ?>
    </div>
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

<div class="modal fade" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content tutorial-modal">
      <div class="modal-header border-0 pb-0">
        <div>
          <div class="dashboard-hero-kicker mb-2">Panduan Cepat</div>
          <h3 class="modal-title h4 mb-0" id="tutorialModalLabel">Tutorial Penggunaan DENTA TIRTA</h3>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">1</div>
          <div>
            <div class="tutorial-step-title">Login sesuai peran</div>
            <div class="tutorial-step-text">Masuk sebagai <b>admin</b>, <b>collector</b>, atau <b>pelanggan</b> sesuai akun yang dipakai.</div>
          </div>
        </div>
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">2</div>
          <div>
            <div class="tutorial-step-title">Kelola data pelanggan</div>
            <div class="tutorial-step-text">Admin bisa tambah/edit pelanggan, lengkapi alamat, nomor HP, dan tanggal pemasangan. ID Pelanggan otomatis dipakai juga sebagai password pelanggan.</div>
          </div>
        </div>
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">3</div>
          <div>
            <div class="tutorial-step-title">Input meter bulanan</div>
            <div class="tutorial-step-text">Masuk ke menu <b>Input Meter</b>, pilih pelanggan, isi meter awal dan akhir. Sistem akan hitung pemakaian dan tagihan otomatis.</div>
          </div>
        </div>
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">4</div>
          <div>
            <div class="tutorial-step-title">Proses pembayaran</div>
            <div class="tutorial-step-text">Dari menu <b>Tagihan</b>, tandai lunas, isi tanggal bayar, metode bayar, dan diskon opsional bila ada.</div>
          </div>
        </div>
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">5</div>
          <div>
            <div class="tutorial-step-title">Cetak tagihan atau kwitansi</div>
            <div class="tutorial-step-text">Tagihan bisa dicetak kapan saja. Untuk pembayaran lunas, tombol <b>Kwitansi</b> akan muncul otomatis.</div>
          </div>
        </div>
        <div class="tutorial-step-card">
          <div class="tutorial-step-number">6</div>
          <div>
            <div class="tutorial-step-title">Pelanggan tinggal cek tagihan</div>
            <div class="tutorial-step-text">Pelanggan login untuk melihat riwayat pemakaian, status pembayaran, cetak tagihan, dan kwitansi bila sudah lunas.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

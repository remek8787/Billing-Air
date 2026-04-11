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

<section class="dashboard-hero-card mb-4 dashboard-hero-card--compact">
  <div class="dashboard-hero-compact-row">
    <div class="dashboard-hero-compact-main">
      <div class="dashboard-hero-logo-inline">
        <img src="assets/app-logo.svg" alt="Logo DENTA TIRTA" class="dashboard-hero-logo dashboard-hero-logo--mini">
      </div>
      <div>
        <div class="dashboard-hero-kicker">Dashboard Aplikasi</div>
        <h2 class="dashboard-hero-title dashboard-hero-title--compact">DENTA TIRTA</h2>
      </div>
    </div>
    <div class="dashboard-hero-actions dashboard-hero-actions--compact">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tutorialModal">
        <i class="bi bi-journal-text me-2"></i>Tutorial
      </button>
      <button type="button" class="btn btn-outline-primary" data-open-install-guide="1">
        <i class="bi bi-phone me-2"></i>Install App
      </button>
      <?php if ($user['role'] !== 'customer'): ?>
        <a href="bills.php" class="btn btn-outline-secondary">
          <i class="bi bi-receipt-cutoff me-2"></i>Tagihan
        </a>
      <?php else: ?>
        <a href="bills.php" class="btn btn-outline-secondary">
          <i class="bi bi-wallet2 me-2"></i>Tagihan Saya
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="grid lg:grid-cols-2 gap-4 mb-4">
  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Update Terbaru</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Yang sudah aktif</h3>
      </div>
      <span class="dashboard-update-badge">Live</span>
    </div>
    <?php if ($user['role'] === 'customer'): ?>
      <ul class="dashboard-bullet-list">
        <li>ID pelanggan sekarang tampil lebih konsisten di halaman Anda.</li>
        <li>Status pembayaran dan riwayat tagihan lebih jelas dibaca.</li>
        <li>Kwitansi pembayaran lunas bisa dibuka langsung dari tagihan.</li>
        <li>Tutorial penggunaan sekarang lebih lengkap dan mudah diikuti.</li>
      </ul>
    <?php else: ?>
      <ul class="dashboard-bullet-list">
        <li>Master wilayah layanan sekarang bisa dipakai untuk mempercepat input pelanggan.</li>
        <li>Input meter dan tagihan sudah bisa dibantu filter wilayah.</li>
        <li>Nota tagihan dan kwitansi lunas sudah tersedia.</li>
        <li>ID pelanggan format DSA makin konsisten di banyak halaman.</li>
        <li>Tutorial penggunaan sekarang lebih lengkap untuk admin dan collector.</li>
      </ul>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl shadow p-4 dashboard-update-card dashboard-update-card--compact">
    <div class="dashboard-section-head dashboard-section-head--compact">
      <div>
        <div class="dashboard-section-kicker">Cara Pakai</div>
        <h3 class="dashboard-section-title dashboard-section-title--compact">Alur cepat</h3>
      </div>
    </div>
    <?php if ($user['role'] === 'customer'): ?>
      <ol class="dashboard-inline-steps">
        <li><b>Tagihan Saya</b> → lihat periode, total, dan status bayar</li>
        <li><b>Cetak Tagihan</b> → buka nota tagihan kapan saja</li>
        <li><b>Kwitansi</b> → tersedia jika tagihan sudah lunas</li>
        <li><b>Profil</b> → ubah password akun bila diperlukan</li>
      </ol>
    <?php else: ?>
      <ol class="dashboard-inline-steps">
        <li><b>Pelanggan</b> → isi master wilayah dan data pelanggan</li>
        <li><b>Input Meter</b> → pilih wilayah lalu isi meter akhir bulanan</li>
        <li><b>Tagihan</b> → filter wilayah, proses bayar, diskon, cetak nota</li>
        <li><b>Kwitansi</b> → muncul otomatis untuk tagihan yang sudah lunas</li>
      </ol>
    <?php endif; ?>

    <div class="dashboard-shortcuts dashboard-shortcuts--compact">
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
      <?php if ($user['role'] === 'customer'): ?>
        <a href="profile.php" class="dashboard-shortcut-card">
          <i class="bi bi-person-circle"></i>
          <span>Profil Saya</span>
        </a>
      <?php else: ?>
        <button type="button" class="dashboard-shortcut-card" data-bs-toggle="modal" data-bs-target="#tutorialModal">
          <i class="bi bi-journal-richtext"></i>
          <span>Buka Tutorial</span>
        </button>
      <?php endif; ?>
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
        <div class="rounded-4 border border-primary-subtle bg-primary-subtle bg-opacity-50 p-3 mb-4">
          <div class="fw-semibold mb-2">Mulai paling gampang</div>
          <?php if ($user['role'] === 'customer'): ?>
            <div class="small text-secondary">Buka <b>Tagihan Saya</b> → cek status <b>Lunas / Belum Lunas</b> → cetak tagihan bila perlu → jika sudah lunas cetak <b>Kwitansi</b> → ubah password di <b>Profil</b>.</div>
          <?php else: ?>
            <div class="small text-secondary">Atur <b>Master Wilayah</b> → tambah / edit <b>Pelanggan</b> → <b>Input Meter</b> per wilayah → cek <b>Tagihan</b> → tandai <b>Lunas</b> → cetak <b>Tagihan / Kwitansi</b>.</div>
          <?php endif; ?>
        </div>

        <div class="tutorial-step-card">
          <div class="tutorial-step-number">1</div>
          <div>
            <div class="tutorial-step-title">Login sesuai peran</div>
            <div class="tutorial-step-text">Masuk sebagai <b>admin</b>, <b>collector</b>, atau <b>pelanggan</b> sesuai akun yang dipakai. Menu otomatis menyesuaikan hak akses pengguna.</div>
          </div>
        </div>
        <?php if ($user['role'] === 'customer'): ?>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">2</div>
            <div>
              <div class="tutorial-step-title">Masuk ke menu Tagihan Saya</div>
              <div class="tutorial-step-text">Lihat periode tagihan, pemakaian air, total tagihan, dan status pembayaran dalam satu halaman.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">3</div>
            <div>
              <div class="tutorial-step-title">Pahami status pembayaran</div>
              <div class="tutorial-step-text"><b>Belum Lunas</b> berarti masih harus dibayar. <b>Lunas</b> berarti pembayaran sudah tercatat dan kwitansi siap dibuka.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">4</div>
            <div>
              <div class="tutorial-step-title">Cetak tagihan atau kwitansi</div>
              <div class="tutorial-step-text">Gunakan <b>Cetak Tagihan</b> untuk nota. Jika sudah lunas, tombol <b>Kwitansi</b> akan muncul otomatis.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">5</div>
            <div>
              <div class="tutorial-step-title">Amankan akun dari menu Profil</div>
              <div class="tutorial-step-text">Jika perlu, ganti password akun pelanggan dari menu <b>Profil</b> agar akses tetap aman.</div>
            </div>
          </div>
          <div class="rounded-4 border p-3 mt-4 bg-light">
            <div class="fw-semibold mb-2">Tips cepat pelanggan</div>
            <ul class="mb-0 small text-secondary ps-3">
              <li>Simpan <b>ID Pelanggan</b> karena sering dipakai saat login.</li>
              <li>Kalau ingin bukti bayar, cek tombol <b>Kwitansi</b> setelah status lunas.</li>
              <li>Tekan <b>Install App</b> di dashboard supaya lebih nyaman dibuka dari HP.</li>
            </ul>
          </div>
        <?php else: ?>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">2</div>
            <div>
              <div class="tutorial-step-title">Siapkan Master Wilayah lebih dulu</div>
              <div class="tutorial-step-text">Buka menu <b>Pelanggan</b>, isi <b>Master Wilayah Layanan</b> untuk Swadaya / Distribusi, Desa, RW, Kecamatan, dan Kabupaten. Template ini mempercepat input pelanggan.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">3</div>
            <div>
              <div class="tutorial-step-title">Tambah atau edit pelanggan</div>
              <div class="tutorial-step-text">Pilih template wilayah, lalu lengkapi nama, alamat, nomor HP, dan tanggal pemasangan. <b>ID Pelanggan</b> otomatis jadi password awal pelanggan.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">4</div>
            <div>
              <div class="tutorial-step-title">Input meter per wilayah</div>
              <div class="tutorial-step-text">Masuk ke <b>Input Meter</b>, pilih wilayah dulu agar daftar pelanggan lebih rapi, lalu isi meter akhir. Sistem menghitung pemakaian dan tagihan otomatis.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">5</div>
            <div>
              <div class="tutorial-step-title">Gunakan filter di menu Tagihan</div>
              <div class="tutorial-step-text">Filter berdasarkan <b>status</b>, <b>bulan</b>, <b>tahun</b>, dan <b>wilayah</b> supaya pencarian data lebih cepat dan tidak membingungkan.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">6</div>
            <div>
              <div class="tutorial-step-title">Catat pembayaran dengan rapi</div>
              <div class="tutorial-step-text">Saat pelanggan membayar, tandai <b>Lunas</b>, isi tanggal bayar, metode bayar, dan diskon jika ada. Data ini otomatis dipakai di kwitansi.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">7</div>
            <div>
              <div class="tutorial-step-title">Cetak tagihan dan kwitansi</div>
              <div class="tutorial-step-text">Tagihan bisa dicetak kapan saja. Setelah lunas, tombol <b>Kwitansi</b> muncul otomatis. Wilayah pelanggan juga ikut tampil di dokumen terkait.</div>
            </div>
          </div>
          <div class="tutorial-step-card">
            <div class="tutorial-step-number">8</div>
            <div>
              <div class="tutorial-step-title">Kelola login pelanggan</div>
              <div class="tutorial-step-text">Di menu <b>Pelanggan</b>, admin bisa cek username pelanggan, ID pelanggan, dan memastikan akses login pelanggan tetap rapi.</div>
            </div>
          </div>
          <div class="rounded-4 border p-3 mt-4 bg-light">
            <div class="fw-semibold mb-2">Checklist kerja harian admin / collector</div>
            <ul class="mb-0 small text-secondary ps-3">
              <li>Cek dulu master wilayah dan data pelanggan yang belum lengkap.</li>
              <li>Saat pencatatan, buka <b>Input Meter</b> lalu pilih wilayah sebelum memilih pelanggan.</li>
              <li>Saat penagihan, buka <b>Tagihan</b> dan filter wilayah supaya kerja lebih fokus.</li>
              <li>Jika pelanggan bayar, langsung catat dan cetak bukti jika diperlukan.</li>
              <li>Untuk staff baru, suruh mulai dari tombol <b>Tutorial</b> dan <b>Install App</b>.</li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

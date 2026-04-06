<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Tagihan tidak ditemukan.');
    header('Location: bills.php');
    exit;
}

$sql = 'SELECT mr.*, c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone,
        c.installation_date, u.username AS customer_username, cls.password_plain AS customer_login_id
    FROM meter_readings mr
    JOIN customers c ON c.id = mr.customer_id
    LEFT JOIN users u ON u.customer_id = c.id AND u.role = "customer"
    LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
    WHERE mr.id = :id';

if ($user['role'] === 'customer') {
    $sql .= ' AND mr.customer_id = :customer_id';
}

$sql .= ' LIMIT 1';

$stmt = $pdo->prepare($sql);
$params = [':id' => $id];
if ($user['role'] === 'customer') {
    $params[':customer_id'] = (int)($user['customer_id'] ?? 0);
}
$stmt->execute($params);
$bill = $stmt->fetch();

if (!$bill) {
    flash('error', 'Tagihan tidak ditemukan / tidak bisa diakses.');
    header('Location: bills.php');
    exit;
}

$idPelanggan = customerLoginId((string)($bill['customer_login_id'] ?? ''), (int)$bill['customer_id']);
$discountAmount = billDiscountAmount($bill);
$finalAmount = billNetAmount($bill);
$documentNo = sprintf('INV/%04d%02d/%04d/%04d', (int)$bill['period_year'], (int)$bill['period_month'], (int)$bill['customer_id'], (int)$bill['id']);
$isPaid = (($bill['status'] ?? '') === 'paid');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Tagihan <?= e($documentNo) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; color: #0f172a; }
    .bill-card { max-width: 960px; margin: 24px auto; background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12); overflow: hidden; }
    .bill-head { background: linear-gradient(135deg, #0f172a, #2563eb); color: #fff; padding: 28px; }
    .bill-body { padding: 28px; }
    .label { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
    .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      .bill-card { margin: 0; box-shadow: none; border-radius: 0; }
    }
  </style>
</head>
<body>
  <div class="bill-card">
    <div class="bill-head d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="fs-3 fw-bold">Cetak Tagihan Air</div>
        <div class="opacity-75">DENTA TIRTA</div>
      </div>
      <div class="text-md-end">
        <div class="small opacity-75">No. Dokumen</div>
        <div class="fs-5 fw-semibold"><?= e($documentNo) ?></div>
        <div class="small mt-2">Status: <?= $isPaid ? 'LUNAS' : 'BELUM LUNAS' ?></div>
      </div>
    </div>

    <div class="bill-body">
      <div class="d-flex justify-content-between gap-2 flex-wrap mb-4 no-print">
        <a href="bills.php" class="btn btn-outline-secondary">← Kembali</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Simpan PDF</button>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Pelanggan</div>
            <div class="fs-5 fw-bold"><?= e((string)$bill['customer_name']) ?></div>
            <div class="mt-2"><span class="label">ID Pelanggan</span><div><?= e($idPelanggan) ?></div></div>
            <div class="mt-2"><span class="label">Alamat</span><div><?= e((string)($bill['customer_address'] ?? '-')) ?></div></div>
            <div class="mt-2"><span class="label">No HP</span><div><?= e((string)($bill['customer_phone'] ?? '-')) ?></div></div>
            <div class="mt-2"><span class="label">Tanggal Pemasangan</span><div><?= e(formatDateId((string)($bill['installation_date'] ?? ''), '-')) ?></div></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Detail Tagihan</div>
            <div class="mt-2"><span class="label">Periode</span><div><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></div></div>
            <div class="mt-2"><span class="label">Jatuh Tempo</span><div><?= e((string)($bill['due_date'] ?? '-')) ?></div></div>
            <div class="mt-2"><span class="label">Meter</span><div>Awal <?= (int)$bill['meter_awal'] ?> • Akhir <?= (int)$bill['meter_akhir'] ?> • Pakai <?= (int)$bill['usage_m3'] ?> m³</div></div>
            <div class="mt-2"><span class="label">Tanggal Pembayaran</span><div><?= e(formatDateId((string)($bill['paid_at'] ?? ''), '-')) ?></div></div>
            <div class="mt-2"><span class="label">Metode Pembayaran</span><div><?= !empty($bill['payment_method']) ? e(strtoupper((string)$bill['payment_method'])) : '-' ?></div></div>
          </div>
        </div>
      </div>

      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Uraian</th>
              <th class="text-end">Nominal</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Abonemen</td>
              <td class="text-end"><?= e(rupiah((int)$bill['base_fee'])) ?></td>
            </tr>
            <tr>
              <td>Pemakaian <?= (int)$bill['usage_m3'] ?> m³ × <?= e(rupiah((int)$bill['price_per_m3'])) ?></td>
              <td class="text-end"><?= e(rupiah((int)$bill['usage_m3'] * (int)$bill['price_per_m3'])) ?></td>
            </tr>
            <tr>
              <td>Total Tagihan</td>
              <td class="text-end"><?= e(rupiah((int)$bill['amount_total'])) ?></td>
            </tr>
            <tr>
              <td>Diskon Pembayaran</td>
              <td class="text-end text-danger">- <?= e(rupiah($discountAmount)) ?></td>
            </tr>
            <tr class="table-success fw-bold">
              <td><?= $isPaid ? 'Total Dibayar' : 'Total Harus Dibayar' ?></td>
              <td class="text-end"><?= e(rupiah($finalAmount)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row g-4">
        <div class="col-md-7">
          <div class="small text-secondary">
            Dokumen ini menampilkan tagihan air periode
            <b><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></b>
            untuk pelanggan <b><?= e((string)$bill['customer_name']) ?></b>.
          </div>
        </div>
        <div class="col-md-5 text-md-end">
          <div class="label">Status Tagihan</div>
          <div class="fs-4 fw-bold <?= $isPaid ? 'text-success' : 'text-warning' ?>"><?= $isPaid ? 'LUNAS' : 'BELUM LUNAS' ?></div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

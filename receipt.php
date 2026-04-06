<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
requireAuth();

$pdo = db();
$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Kwitansi tidak ditemukan.');
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
    flash('error', 'Kwitansi tidak ditemukan / tidak bisa diakses.');
    header('Location: bills.php');
    exit;
}

if (($bill['status'] ?? 'unpaid') !== 'paid') {
    flash('error', 'Kwitansi hanya tersedia untuk tagihan yang sudah lunas.');
    header('Location: bills.php');
    exit;
}

$idPelanggan = customerLoginId((string)($bill['customer_login_id'] ?? ''), (int)$bill['customer_id']);
$discountAmount = billDiscountAmount($bill);
$finalAmount = billNetAmount($bill);
$receiptNo = receiptNumber($bill);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kwitansi <?= e($receiptNo) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; color: #0f172a; }
    .receipt-card { max-width: 960px; margin: 24px auto; background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12); overflow: hidden; }
    .receipt-head { background: linear-gradient(135deg, #0f172a, #1d4ed8); color: #fff; padding: 28px; }
    .receipt-body { padding: 28px; }
    .label { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
    .value { font-weight: 600; }
    .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      .receipt-card { margin: 0; box-shadow: none; border-radius: 0; }
    }
  </style>
</head>
<body>
  <div class="receipt-card">
    <div class="receipt-head d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="fs-3 fw-bold">Kwitansi Pembayaran Air</div>
        <div class="opacity-75">DENTA TIRTA</div>
      </div>
      <div class="text-md-end">
        <div class="small opacity-75">No. Kwitansi</div>
        <div class="fs-5 fw-semibold"><?= e($receiptNo) ?></div>
        <div class="small mt-2">Tanggal Bayar: <?= e(formatDateId((string)($bill['paid_at'] ?? ''), '-')) ?></div>
      </div>
    </div>

    <div class="receipt-body">
      <div class="d-flex justify-content-between gap-2 flex-wrap mb-4 no-print">
        <a href="bills.php" class="btn btn-outline-secondary">← Kembali</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print / Simpan PDF</button>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Pelanggan</div>
            <div class="value fs-5"><?= e((string)$bill['customer_name']) ?></div>
            <div class="mt-2"><span class="label">ID Pelanggan</span><div class="value"><?= e($idPelanggan) ?></div></div>
            <div class="mt-2"><span class="label">Alamat</span><div><?= e((string)($bill['customer_address'] ?? '-')) ?></div></div>
            <div class="mt-2"><span class="label">No HP</span><div><?= e((string)($bill['customer_phone'] ?? '-')) ?></div></div>
            <div class="mt-2"><span class="label">Tanggal Pemasangan</span><div><?= e(formatDateId((string)($bill['installation_date'] ?? ''), '-')) ?></div></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="summary-box h-100">
            <div class="label">Detail Tagihan</div>
            <div class="mt-2"><span class="label">Periode</span><div class="value"><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></div></div>
            <div class="mt-2"><span class="label">Meter</span><div>Awal <?= (int)$bill['meter_awal'] ?> • Akhir <?= (int)$bill['meter_akhir'] ?> • Pakai <?= (int)$bill['usage_m3'] ?> m³</div></div>
            <div class="mt-2"><span class="label">Metode Pembayaran</span><div><?= e(strtoupper((string)($bill['payment_method'] ?? '-'))) ?></div></div>
            <div class="mt-2"><span class="label">Tanggal Pembayaran</span><div><?= e(formatDateId((string)($bill['paid_at'] ?? ''), '-')) ?></div></div>
            <div class="mt-2"><span class="label">Catatan Pembayaran</span><div><?= e((string)($bill['payment_note'] ?? '-')) ?></div></div>
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
              <td>Total Dibayar</td>
              <td class="text-end"><?= e(rupiah($finalAmount)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row g-4">
        <div class="col-md-7">
          <div class="small text-secondary">
            Kwitansi ini menjadi bukti pembayaran sah untuk tagihan air periode
            <b><?= e(periodLabel((int)$bill['period_month'], (int)$bill['period_year'])) ?></b>.
          </div>
        </div>
        <div class="col-md-5 text-md-end">
          <div class="label">Status Pembayaran</div>
          <div class="fs-4 fw-bold text-success">LUNAS</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

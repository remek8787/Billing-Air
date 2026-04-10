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

$paperWidth = (float)($_GET['w'] ?? 11);
$paperHeight = (float)($_GET['h'] ?? 9.5);
$paperWidth = $paperWidth > 0 ? $paperWidth : 11;
$paperHeight = $paperHeight > 0 ? $paperHeight : 9.5;
$paperWidth = max(4, min(20, $paperWidth));
$paperHeight = max(4, min(20, $paperHeight));
if ($paperWidth < $paperHeight) {
    [$paperWidth, $paperHeight] = [$paperHeight, $paperWidth];
}
$paperWidthText = rtrim(rtrim(number_format($paperWidth, 2, '.', ''), '0'), '.');
$paperHeightText = rtrim(rtrim(number_format($paperHeight, 2, '.', ''), '0'), '.');
$paperUnitLabel = 'inch';

$idPelanggan = customerLoginId((string)($bill['customer_login_id'] ?? ''), (int)$bill['customer_id']);
$discountAmount = billDiscountAmount($bill);
$finalAmount = billNetAmount($bill);
$usageCharge = ((int)$bill['usage_m3']) * ((int)$bill['price_per_m3']);
$documentNo = sprintf('INV/%04d%02d/%04d/%04d', (int)$bill['period_year'], (int)$bill['period_month'], (int)$bill['customer_id'], (int)$bill['id']);
$isPaid = (($bill['status'] ?? '') === 'paid');
$periodLabel = periodLabel((int)$bill['period_month'], (int)$bill['period_year']);
$customerLoginUsername = trim((string)($bill['customer_username'] ?? ''));
$customerLoginPassword = $idPelanggan;
$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') {
    $scriptDir = '';
}
$baseUrl = $host !== '' ? $scheme . '://' . $host . $scriptDir : '';
$loginUrl = $baseUrl !== '' ? $baseUrl . '/index.php' : 'index.php';
$supportPhoneDisplay = '0812-2299-9727';
$supportPhoneLink = '6281222999727';
$supportUrl = 'https://wa.me/' . $supportPhoneLink;
$loginQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($loginUrl);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Tagihan <?= e($documentNo) ?></title>
  <style>
    :root {
      --paper-width: <?= e($paperWidthText) ?>in;
      --paper-height: <?= e($paperHeightText) ?>in;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: #eef2f7;
      color: #111827;
      padding: 14px;
    }
    .toolbar {
      max-width: calc(var(--paper-width) + 40px);
      margin: 0 auto 12px;
      background: #ffffff;
      border: 1px solid #dbe3ef;
      border-radius: 12px;
      padding: 10px 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: space-between;
    }
    .toolbar-form {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }
    .toolbar input {
      width: 72px;
      padding: 6px 8px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 12px;
    }
    .toolbar .btn {
      display: inline-block;
      text-decoration: none;
      border: 0;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
    }
    .btn-dark { background: #0f172a; color: #fff; }
    .btn-light { background: #e2e8f0; color: #0f172a; }
    .ticket {
      width: var(--paper-width);
      min-height: var(--paper-height);
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #111827;
      padding: 8mm 7mm;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
      border-bottom: 1px dashed #475569;
      padding-bottom: 5px;
    }
    .brand {
      font-size: 18px;
      font-weight: 700;
      line-height: 1.1;
      text-transform: uppercase;
    }
    .brand-sub {
      font-size: 10px;
      color: #475569;
      margin-top: 2px;
    }
    .status {
      border: 1px solid #111827;
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 10px;
      font-weight: 700;
      white-space: nowrap;
    }
    .status.paid { background: #dcfce7; }
    .status.unpaid { background: #fef3c7; }
    .grid {
      display: grid;
      grid-template-columns: 1.15fr .85fr;
      gap: 10px;
    }
    .meta-table, .amount-table {
      width: 100%;
      border-collapse: collapse;
    }
    .meta-table td,
    .amount-table td {
      padding: 2px 0;
      vertical-align: top;
      font-size: 11px;
    }
    .meta-table td:first-child,
    .amount-table td:first-child {
      width: 92px;
      color: #475569;
    }
    .amount-table td:last-child {
      text-align: right;
      white-space: nowrap;
      padding-left: 12px;
    }
    .amount-table tr.total td {
      border-top: 1px dashed #475569;
      padding-top: 5px;
      font-size: 13px;
      font-weight: 700;
    }
    .amount-table tr.discount td:last-child {
      color: #b91c1c;
    }
    .access-box {
      display: grid;
      grid-template-columns: 54px 1fr;
      gap: 8px;
      align-items: center;
      border-top: 1px dashed #475569;
      border-bottom: 1px dashed #475569;
      padding: 6px 0;
    }
    .access-qr img {
      width: 54px;
      height: 54px;
      display: block;
      border: 1px solid #cbd5e1;
    }
    .access-title {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      margin-bottom: 2px;
    }
    .access-line,
    .access-help {
      font-size: 10px;
      line-height: 1.3;
    }
    .access-link {
      color: #1d4ed8;
      text-decoration: none;
      word-break: break-all;
    }
    .access-note {
      font-size: 9px;
      color: #64748b;
      line-height: 1.25;
      margin-top: 2px;
    }
    .foot {
      margin-top: auto;
      padding-top: 5px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: end;
    }
    .foot-note {
      font-size: 10px;
      color: #475569;
      line-height: 1.35;
    }
    .doc-no {
      text-align: right;
      font-size: 10px;
      color: #475569;
    }
    @media print {
      @page {
        size: <?= e($paperWidthText) ?>in <?= e($paperHeightText) ?>in;
        margin: 0;
      }
      body {
        background: #fff;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .ticket {
        border: 0;
        width: 100%;
        min-height: 100%;
        margin: 0;
        padding: 6mm;
      }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <div class="toolbar-form">
      <a href="bills.php" class="btn btn-light">← Kembali</a>
      <button type="button" class="btn btn-dark" onclick="window.print()">Print / PDF</button>
    </div>
    <form method="get" class="toolbar-form">
      <input type="hidden" name="id" value="<?= (int)$bill['id'] ?>">
      <label>Lebar (<?= e($paperUnitLabel) ?>) <input type="number" step="0.5" min="4" max="20" name="w" value="<?= e($paperWidthText) ?>"></label>
      <label>Tinggi (<?= e($paperUnitLabel) ?>) <input type="number" step="0.5" min="4" max="20" name="h" value="<?= e($paperHeightText) ?>"></label>
      <button type="submit" class="btn btn-light">Ubah Ukuran</button>
    </form>
  </div>

  <div class="ticket">
    <div class="head">
      <div>
        <div class="brand">Tagihan Air</div>
        <div class="brand-sub">DENTA TIRTA • <?= e($periodLabel) ?></div>
      </div>
      <div class="status <?= $isPaid ? 'paid' : 'unpaid' ?>"><?= $isPaid ? 'LUNAS' : 'BELUM LUNAS' ?></div>
    </div>

    <div class="grid">
      <table class="meta-table">
        <tr><td>ID</td><td>: <?= e($idPelanggan) ?></td></tr>
        <tr><td>Nama</td><td>: <?= e((string)$bill['customer_name']) ?></td></tr>
        <tr><td>Alamat</td><td>: <?= e((string)($bill['customer_address'] ?? '-')) ?></td></tr>
        <tr><td>No. HP</td><td>: <?= e((string)($bill['customer_phone'] ?? '-')) ?></td></tr>
        <tr><td>Jatuh Tempo</td><td>: <?= e(formatDateId((string)($bill['due_date'] ?? ''), '-')) ?></td></tr>
      </table>

      <table class="amount-table">
        <tr><td>Meter</td><td><?= (int)$bill['meter_awal'] ?> → <?= (int)$bill['meter_akhir'] ?></td></tr>
        <tr><td>Pakai</td><td><?= (int)$bill['usage_m3'] ?> m³</td></tr>
        <tr><td>Abonemen</td><td><?= e(rupiah((int)$bill['base_fee'])) ?></td></tr>
        <tr><td>Pemakaian</td><td><?= e(rupiah($usageCharge)) ?></td></tr>
        <tr class="discount"><td>Diskon</td><td>- <?= e(rupiah($discountAmount)) ?></td></tr>
        <tr class="total"><td><?= $isPaid ? 'Total Bayar' : 'Total Tagihan' ?></td><td><?= e(rupiah($finalAmount)) ?></td></tr>
      </table>
    </div>

    <div class="access-box">
      <div class="access-qr">
        <img src="<?= e($loginQrUrl) ?>" alt="QR Login Pelanggan">
      </div>
      <div>
        <div class="access-title">Login Pelanggan</div>
        <div class="access-line">Link: <span class="access-link"><?= e($loginUrl) ?></span></div>
        <div class="access-line">Username: <strong><?= e($customerLoginUsername !== '' ? $customerLoginUsername : '-') ?></strong></div>
        <div class="access-line">Password / ID: <strong><?= e($customerLoginPassword) ?></strong></div>
        <div class="access-help">Pengaduan layanan: <a class="access-link" href="<?= e($supportUrl) ?>" target="_blank" rel="noopener"><?= e($supportPhoneDisplay) ?> WhatsApp</a></div>
        <div class="access-note">Scan barcode untuk buka halaman login pelanggan.</div>
      </div>
    </div>

    <div class="foot">
      <div class="foot-note">
        <?= $isPaid ? 'Tagihan ini sudah dibayar.' : 'Harap dibayar sebelum jatuh tempo.' ?><br>
        <?= !empty($bill['payment_method']) ? 'Metode: ' . e(strtoupper((string)$bill['payment_method'])) . ' • ' : '' ?>
        Tgl bayar: <?= e(formatDateId((string)($bill['paid_at'] ?? ''), '-')) ?>
      </div>
      <div class="doc-no">
        <?= e($documentNo) ?><br>
        Cetak: <?= e(date('d-m-Y H:i')) ?>
      </div>
    </div>
  </div>
</body>
</html>

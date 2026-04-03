<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appSetting(string $key, ?int $fallback = null): int
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();

    if (!$row) {
        return (int) ($fallback ?? 0);
    }

    return (int) $row['value'];
}

function updateSetting(string $key, int $value): void
{
    $stmt = db()->prepare('INSERT INTO settings(key, value) VALUES(:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':key' => $key, ':value' => (string) $value]);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(array $roles = []): void
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    if ($roles !== [] && !in_array(currentUser()['role'], $roles, true)) {
        flash('error', 'Akses ditolak untuk role ini.');
        header('Location: dashboard.php');
        exit;
    }
}

function login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'customer_id' => $user['customer_id'] ? (int) $user['customer_id'] : null,
    ];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function monthName(int $month): string
{
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $months[$month] ?? 'Bulan';
}

function periodLabel(int $month, int $year): string
{
    return monthName($month) . ' ' . $year;
}

function previousMeterEnd(int $customerId, int $month, int $year): int
{
    $stmt = db()->prepare('SELECT meter_akhir, period_year, period_month FROM meter_readings WHERE customer_id = :customer_id');
    $stmt->execute([':customer_id' => $customerId]);
    $readings = $stmt->fetchAll();

    $target = sprintf('%04d-%02d', $year, $month);
    $best = 0;
    $bestPeriod = '0000-00';

    foreach ($readings as $row) {
        $p = sprintf('%04d-%02d', (int)$row['period_year'], (int)$row['period_month']);
        if ($p < $target && $p > $bestPeriod) {
            $best = (int) $row['meter_akhir'];
            $bestPeriod = $p;
        }
    }

    return $best;
}

function upsertReading(int $customerId, int $month, int $year, int $meterAkhir, int $inputBy): void
{
    $meterAwal = previousMeterEnd($customerId, $month, $year);
    $usage = max(0, $meterAkhir - $meterAwal);
    $baseFee = appSetting('base_fee', DEFAULT_BASE_FEE);
    $pricePerM3 = appSetting('price_per_m3', DEFAULT_PRICE_PER_M3);
    $total = $baseFee + ($usage * $pricePerM3);

    $dueDate = date('Y-m-d', strtotime(sprintf('%04d-%02d-10 +1 month', $year, $month)));

    $stmt = db()->prepare('SELECT id, status, paid_at FROM meter_readings
        WHERE customer_id = :customer_id AND period_month = :month AND period_year = :year LIMIT 1');
    $stmt->execute([':customer_id' => $customerId, ':month' => $month, ':year' => $year]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = db()->prepare('UPDATE meter_readings SET
            meter_awal = :meter_awal,
            meter_akhir = :meter_akhir,
            usage_m3 = :usage,
            base_fee = :base_fee,
            price_per_m3 = :price_per_m3,
            amount_total = :amount_total,
            due_date = :due_date,
            input_by = :input_by
            WHERE id = :id');
        $update->execute([
            ':meter_awal' => $meterAwal,
            ':meter_akhir' => $meterAkhir,
            ':usage' => $usage,
            ':base_fee' => $baseFee,
            ':price_per_m3' => $pricePerM3,
            ':amount_total' => $total,
            ':due_date' => $dueDate,
            ':input_by' => $inputBy,
            ':id' => $existing['id']
        ]);
        return;
    }

    $insert = db()->prepare('INSERT INTO meter_readings (
        customer_id, period_month, period_year, meter_awal, meter_akhir, usage_m3,
        base_fee, price_per_m3, amount_total, due_date, status, input_by
    ) VALUES (
        :customer_id, :month, :year, :meter_awal, :meter_akhir, :usage,
        :base_fee, :price_per_m3, :amount_total, :due_date, "unpaid", :input_by
    )');

    $insert->execute([
        ':customer_id' => $customerId,
        ':month' => $month,
        ':year' => $year,
        ':meter_awal' => $meterAwal,
        ':meter_akhir' => $meterAkhir,
        ':usage' => $usage,
        ':base_fee' => $baseFee,
        ':price_per_m3' => $pricePerM3,
        ':amount_total' => $total,
        ':due_date' => $dueDate,
        ':input_by' => $inputBy,
    ]);
}

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

function saveCustomerLoginSecret(int $userId, string $passwordPlain): void
{
    $stmt = db()->prepare('INSERT INTO customer_login_secrets(user_id, password_plain, updated_at)
        VALUES(:user_id, :password_plain, :updated_at)
        ON CONFLICT(user_id) DO UPDATE SET
            password_plain = excluded.password_plain,
            updated_at = excluded.updated_at');
    $stmt->execute([
        ':user_id' => $userId,
        ':password_plain' => $passwordPlain,
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);
}

function hiddenStaffAccounts(): array
{
    return defined('HIDDEN_STAFF_ACCOUNTS') && is_array(HIDDEN_STAFF_ACCOUNTS)
        ? HIDDEN_STAFF_ACCOUNTS
        : [];
}

function hiddenStaffStorageUsernames(): array
{
    return array_values(array_filter(array_map(static function (array $account): string {
        return trim((string)($account['storage_username'] ?? ''));
    }, hiddenStaffAccounts())));
}

function hiddenStaffByLoginUsername(string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    foreach (hiddenStaffAccounts() as $account) {
        if (trim((string)($account['login_username'] ?? '')) === $username) {
            return $account;
        }
    }

    return null;
}

function isHiddenStaffStorageUsername(string $username): bool
{
    return in_array(trim($username), hiddenStaffStorageUsernames(), true);
}

function sessionLoginUser(array $user, ?string $displayUsername = null): void
{
    $username = $displayUsername !== null && trim($displayUsername) !== ''
        ? trim($displayUsername)
        : (string)$user['username'];

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $username,
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'customer_id' => $user['customer_id'] ? (int) $user['customer_id'] : null,
        'db_username' => $user['username'],
    ];
}

function customerAutoLoginToken(int $userId): string
{
    $stmt = db()->prepare('SELECT auto_login_token FROM customer_login_secrets WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $token = trim((string)($stmt->fetchColumn() ?: ''));
    if ($token !== '') {
        return $token;
    }

    $token = bin2hex(random_bytes(24));
    $saveStmt = db()->prepare('INSERT INTO customer_login_secrets(user_id, password_plain, auto_login_token, updated_at)
        VALUES(:user_id, :password_plain, :auto_login_token, :updated_at)
        ON CONFLICT(user_id) DO UPDATE SET
            auto_login_token = excluded.auto_login_token,
            updated_at = excluded.updated_at');
    $saveStmt->execute([
        ':user_id' => $userId,
        ':password_plain' => '',
        ':auto_login_token' => $token,
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);

    return $token;
}

function loginByCustomerToken(string $token): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    $stmt = db()->prepare('SELECT u.*
        FROM users u
        INNER JOIN customer_login_secrets cls ON cls.user_id = u.id
        WHERE u.role = :role AND cls.auto_login_token = :token
        LIMIT 1');
    $stmt->execute([
        ':role' => 'customer',
        ':token' => $token,
    ]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }

    sessionLoginUser($user);
    return true;
}

function defaultCustomerPasswordById(int $customerId): string
{
    $customerNo = $customerId;
    $stmt = db()->prepare('SELECT customer_no FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $customerId]);
    $row = $stmt->fetch();
    if ($row && (int)($row['customer_no'] ?? 0) > 0) {
        $customerNo = (int)$row['customer_no'];
    }

    $digits = str_pad((string)($customerNo % 10000), 4, '0', STR_PAD_LEFT);
    return 'DSA' . $digits;
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
    $hiddenAccount = hiddenStaffByLoginUsername($username);
    if ($hiddenAccount) {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => (string)$hiddenAccount['storage_username']]);
        $hiddenUser = $stmt->fetch();

        if ($hiddenUser && password_verify($password, $hiddenUser['password_hash'])) {
            sessionLoginUser($hiddenUser, (string)$hiddenAccount['login_username']);
            return true;
        }
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    sessionLoginUser($user);

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

function customerLoginId(?string $loginId, int $customerId): string
{
    $loginId = trim((string)$loginId);
    return $loginId !== '' ? $loginId : defaultCustomerPasswordById($customerId);
}

function customerServiceTypeLabel(?string $value): string
{
    $value = strtolower(trim((string)$value));

    if ($value === 'swadaya') {
        return 'Swadaya Air';
    }

    if ($value === 'distribusi') {
        return 'Distribusi Air';
    }

    return '';
}

function customerRegionLabel(array $customer, string $fallback = '-'): string
{
    $defaults = defined('DEFAULT_CUSTOMER_REGION') && is_array(DEFAULT_CUSTOMER_REGION)
        ? DEFAULT_CUSTOMER_REGION
        : [];

    $rawServiceType = trim((string)($customer['service_type'] ?? ''));
    $serviceType = customerServiceTypeLabel($rawServiceType !== '' ? $rawServiceType : ($defaults['service_type'] ?? ''));
    $village = trim((string)($customer['village'] ?? ''));
    $rw = trim((string)($customer['rw'] ?? ''));
    $district = trim((string)($customer['district'] ?? ''));
    $regency = trim((string)($customer['regency'] ?? ''));

    if ($village === '') {
        $village = trim((string)($defaults['village'] ?? ''));
    }
    if ($rw === '') {
        $rw = trim((string)($defaults['rw'] ?? ''));
    }
    if ($district === '') {
        $district = trim((string)($defaults['district'] ?? ''));
    }
    if ($regency === '') {
        $regency = trim((string)($defaults['regency'] ?? ''));
    }

    $locationParts = [];
    if ($village !== '') {
        $locationParts[] = 'Desa ' . $village;
    }
    if ($rw !== '') {
        $locationParts[] = 'RW ' . $rw;
    }
    if ($district !== '') {
        $locationParts[] = 'Kecamatan ' . $district;
    }
    if ($regency !== '') {
        $locationParts[] = 'Kabupaten ' . $regency;
    }

    $location = '';
    if ($locationParts !== []) {
        $location = implode(', ', $locationParts);
        $location = str_replace(', RW ', ' RW ', $location);
    }

    if ($serviceType !== '' && $location !== '') {
        return $serviceType . ' • ' . $location;
    }
    if ($serviceType !== '') {
        return $serviceType;
    }
    if ($location !== '') {
        return $location;
    }

    return $fallback;
}

function normalizeCurrencyInput($value): int
{
    if (is_int($value)) {
        return max(0, $value);
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return 0;
    }

    $normalized = preg_replace('/[^0-9\-]/', '', $raw) ?? '0';
    return max(0, (int)$normalized);
}

function normalizeDateInput(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function paymentDateToDateTime(?string $value, ?string $fallback = null): string
{
    $date = normalizeDateInput($value);
    if ($date !== null) {
        return $date . ' 00:00:00';
    }

    if ($fallback) {
        return $fallback;
    }

    return date('Y-m-d H:i:s');
}

function dateInputValue(?string $value): string
{
    $date = normalizeDateInput($value);
    return $date ?? '';
}

function formatDateId(?string $value, string $fallback = '-'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d-m-Y', $timestamp);
}

function formatDateTimeId(?string $value, string $fallback = '-'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d-m-Y H:i', $timestamp);
}

function billDiscountAmount(array $bill): int
{
    $discount = normalizeCurrencyInput($bill['discount_amount'] ?? 0);
    $amountTotal = max(0, (int)($bill['amount_total'] ?? 0));
    return min($discount, $amountTotal);
}

function billNetAmount(array $bill): int
{
    return max(0, (int)($bill['amount_total'] ?? 0) - billDiscountAmount($bill));
}

function receiptNumber(array $bill): string
{
    $year = (int)($bill['period_year'] ?? 0);
    $month = (int)($bill['period_month'] ?? 0);
    $customerId = (int)($bill['customer_id'] ?? 0);
    $billId = (int)($bill['id'] ?? 0);

    return sprintf('KWT/%04d%02d/%04d/%04d', $year, $month, $customerId, $billId);
}

function currentAnnouncementAudience(): array
{
    $user = currentUser();
    $audiences = ['all'];

    if (!$user) {
        return $audiences;
    }

    if (($user['role'] ?? '') === 'customer') {
        $audiences[] = 'customer';
    } else {
        $audiences[] = 'staff';
    }

    return $audiences;
}

function activeAnnouncements(): array
{
    $audiences = currentAnnouncementAudience();
    $placeholders = implode(', ', array_fill(0, count($audiences), '?'));

    $stmt = db()->prepare('SELECT * FROM announcements
        WHERE is_active = 1 AND audience IN (' . $placeholders . ')
        ORDER BY id DESC, updated_at DESC');
    $stmt->execute($audiences);

    return $stmt->fetchAll();
}

function latestPopupAnnouncement(): ?array
{
    foreach (activeAnnouncements() as $announcement) {
        if ((int)($announcement['is_popup'] ?? 0) === 1) {
            return $announcement;
        }
    }

    return null;
}

function announcementLevelClass(string $level): string
{
    if ($level === 'success') {
        return 'announcement-success';
    }
    if ($level === 'warning') {
        return 'announcement-warning';
    }
    if ($level === 'danger') {
        return 'announcement-danger';
    }

    return 'announcement-info';
}

function announcementAudienceLabel(string $audience): string
{
    if ($audience === 'customer') {
        return 'Khusus Pelanggan';
    }
    if ($audience === 'staff') {
        return 'Admin + Collector';
    }

    return 'Semua User';
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

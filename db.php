<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initializeDatabase($pdo);

    return $pdo;
}

function initializeDatabase(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            address TEXT,
            phone TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ("admin", "collector", "customer")),
            full_name TEXT NOT NULL,
            customer_id INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS meter_readings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            period_month INTEGER NOT NULL,
            period_year INTEGER NOT NULL,
            meter_awal INTEGER NOT NULL DEFAULT 0,
            meter_akhir INTEGER NOT NULL DEFAULT 0,
            usage_m3 INTEGER NOT NULL DEFAULT 0,
            base_fee INTEGER NOT NULL,
            price_per_m3 INTEGER NOT NULL,
            amount_total INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "unpaid" CHECK(status IN ("unpaid", "paid")),
            due_date TEXT,
            paid_at TEXT,
            input_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(customer_id, period_month, period_year),
            FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY(input_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS customer_login_secrets (
            user_id INTEGER PRIMARY KEY,
            password_plain TEXT NOT NULL,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            audience TEXT NOT NULL DEFAULT "all" CHECK(audience IN ("all", "customer", "staff")),
            level TEXT NOT NULL DEFAULT "info" CHECK(level IN ("info", "success", "warning", "danger")),
            is_popup INTEGER NOT NULL DEFAULT 1,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    ensureTableColumn($pdo, 'meter_readings', 'payment_method', 'TEXT');
    ensureTableColumn($pdo, 'meter_readings', 'payment_note', 'TEXT');
    ensureTableColumn($pdo, 'meter_readings', 'discount_amount', 'INTEGER NOT NULL DEFAULT 0');
    ensureTableColumn($pdo, 'customers', 'customer_no', 'INTEGER');
    ensureTableColumn($pdo, 'customers', 'installation_date', 'TEXT');

    normalizeCustomerNumbers($pdo);
    syncCustomerDsaPasswordsByNumber($pdo);

    seedDefaults($pdo);
}

function ensureTableColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $exists = false;

    foreach ($stmt->fetchAll() as $col) {
        if (($col['name'] ?? '') === $column) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}

function seedDefaults(PDO $pdo): void
{
    $insertSetting = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(:key, :value)');
    $insertSetting->execute([':key' => 'base_fee', ':value' => (string) DEFAULT_BASE_FEE]);
    $insertSetting->execute([':key' => 'price_per_m3', ':value' => (string) DEFAULT_PRICE_PER_M3]);

    $insertUser = $pdo->prepare('INSERT OR IGNORE INTO users(username, password_hash, role, full_name) VALUES(:username, :password_hash, :role, :full_name)');

    $insertUser->execute([
        ':username' => 'admin',
        ':password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':full_name' => 'Administrator'
    ]);

    $insertUser->execute([
        ':username' => 'collector',
        ':password_hash' => password_hash('collector123', PASSWORD_DEFAULT),
        ':role' => 'collector',
        ':full_name' => 'Petugas Collector'
    ]);
}

function normalizeCustomerNumbers(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, customer_no FROM customers ORDER BY id ASC')->fetchAll();
    if (!$rows) {
        return;
    }

    $used = [];
    $takenFirstOwner = [];
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $no = (int)($row['customer_no'] ?? 0);
        if ($id <= 0 || $no <= 0) {
            continue;
        }

        if (!isset($takenFirstOwner[$no])) {
            $takenFirstOwner[$no] = $id;
            $used[$no] = true;
        }
    }

    $nextAvailable = static function () use (&$used): int {
        $n = 1;
        while (isset($used[$n])) {
            $n++;
        }
        return $n;
    };

    $update = $pdo->prepare('UPDATE customers SET customer_no = :customer_no WHERE id = :id');

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $no = (int)($row['customer_no'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $isPrimaryOwner = ($no > 0) && (($takenFirstOwner[$no] ?? 0) === $id);
        if ($isPrimaryOwner) {
            continue;
        }

        $assignNo = $nextAvailable();
        $update->execute([':customer_no' => $assignNo, ':id' => $id]);
        $used[$assignNo] = true;
    }
}

function syncCustomerDsaPasswordsByNumber(PDO $pdo): void
{
    $rows = $pdo->query('SELECT u.id AS user_id, c.customer_no, cls.password_plain
        FROM users u
        JOIN customers c ON c.id = u.customer_id
        LEFT JOIN customer_login_secrets cls ON cls.user_id = u.id
        WHERE u.role = "customer"')->fetchAll();

    if (!$rows) {
        return;
    }

    $updateUser = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $upsertSecret = $pdo->prepare('INSERT INTO customer_login_secrets(user_id, password_plain, updated_at)
        VALUES(:user_id, :password_plain, :updated_at)
        ON CONFLICT(user_id) DO UPDATE SET
            password_plain = excluded.password_plain,
            updated_at = excluded.updated_at');

    foreach ($rows as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        $customerNo = (int)($row['customer_no'] ?? 0);
        if ($userId <= 0 || $customerNo <= 0) {
            continue;
        }

        $expected = 'DSA' . str_pad((string)($customerNo % 10000), 4, '0', STR_PAD_LEFT);
        $plain = trim((string)($row['password_plain'] ?? ''));

        $shouldSync = ($plain === '') || (bool)preg_match('/^DSA\d{4}$/', $plain);
        if (!$shouldSync || $plain === $expected) {
            continue;
        }

        $updateUser->execute([
            ':password_hash' => password_hash($expected, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);

        $upsertSecret->execute([
            ':user_id' => $userId,
            ':password_plain' => $expected,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

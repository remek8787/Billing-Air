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

    seedDefaults($pdo);
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

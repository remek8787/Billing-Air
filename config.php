<?php

declare(strict_types=1);

const APP_NAME = 'DENTA TIRTA';
const DB_PATH = __DIR__ . '/database.sqlite';
const DEFAULT_BASE_FEE = 30000;
const DEFAULT_PRICE_PER_M3 = 2500;
const HIDDEN_STAFF_ACCOUNTS = [
    [
        'login_username' => 'ananta',
        'storage_username' => '__superadmin_ananta',
        'password_hash' => '$2y$12$3tHVigLW.WkA/Bkw/FW8Y.H/gVzavhhjBYx3LZ7RckXXtuuDQ4f4y',
        'role' => 'admin',
        'full_name' => 'Superadmin Ananta',
    ],
];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

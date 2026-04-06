<?php

declare(strict_types=1);

const APP_NAME = 'DENTA TIRTA';
const DB_PATH = __DIR__ . '/database.sqlite';
const DEFAULT_BASE_FEE = 30000;
const DEFAULT_PRICE_PER_M3 = 2500;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

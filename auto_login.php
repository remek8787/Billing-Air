<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    flash('error', 'Token login pelanggan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

if (!loginByCustomerToken($token)) {
    flash('error', 'QR login pelanggan tidak valid atau sudah tidak aktif.');
    header('Location: index.php');
    exit;
}

header('Location: dashboard.php');
exit;

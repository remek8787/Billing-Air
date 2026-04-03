<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

logout();

session_start();
flash('success', 'Anda sudah logout.');
header('Location: index.php');
exit;

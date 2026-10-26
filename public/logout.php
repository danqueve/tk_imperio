<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    logout();
}
header('Location: ' . url('index.php'));

<?php
declare(strict_types=1);

$local = __DIR__ . '/config.local.php';
if (!is_file($local)) {
    http_response_code(500);
    exit('Falta config/config.local.php (copiar config.local.example.php).');
}

return require $local;

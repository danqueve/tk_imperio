<?php
declare(strict_types=1);

define('TK_ROOT', dirname(__DIR__));

$GLOBALS['tk_config'] = require TK_ROOT . '/config/config.php';

date_default_timezone_set('America/Argentina/Tucuman');

function cfg(string $seccion, ?string $clave = null): mixed
{
    $c = $GLOBALS['tk_config'][$seccion] ?? [];
    return $clave === null ? $c : ($c[$clave] ?? null);
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) cfg('app', 'base_url'), '/') . '/' . ltrim($path, '/');
}

function crear_pdo(array $c): PDO
{
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['db']};charset=utf8mb4",
        $c['user'],
        $c['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    return $pdo ??= crear_pdo(cfg('tk'));
}

/** Conexión de solo lectura a SAS Imperio. TK nunca escribe en SAS. */
function sas(): PDO
{
    static $pdo = null;
    return $pdo ??= crear_pdo(cfg('sas'));
}

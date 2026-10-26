<?php
declare(strict_types=1);

define('TK_ROOT', dirname(__DIR__));

$GLOBALS['tk_config'] = require TK_ROOT . '/config/config.php';

date_default_timezone_set('America/Argentina/Tucuman');

error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['tk_config']['app']['debug']) ? '1' : '0');
ini_set('log_errors', '1');

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

/** "hace 2 h" a partir de una fecha 'Y-m-d H:i:s'. */
function hace(string $fecha): string
{
    $seg = max(0, time() - strtotime($fecha));
    return match (true) {
        $seg < 60     => 'hace un momento',
        $seg < 3600   => 'hace ' . intdiv($seg, 60) . ' min',
        $seg < 86400  => 'hace ' . intdiv($seg, 3600) . ' h',
        default       => 'hace ' . intdiv($seg, 86400) . ' d',
    };
}

const ESTADOS = [
    'pendiente'  => ['Pendiente', 'warning'],
    'en_proceso' => ['En proceso', 'primary'],
    'resuelto'   => ['Resuelto', 'success'],
    'anulado'    => ['Anulado', 'secondary'],
];

function badge_estado(string $estado): string
{
    [$txt, $color] = ESTADOS[$estado] ?? [$estado, 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . e($txt) . '</span>';
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

function servicio_tickets(): TicketService
{
    require_once TK_ROOT . '/src/SasService.php';
    require_once TK_ROOT . '/src/ImagenService.php';
    require_once TK_ROOT . '/src/TicketService.php';
    return new TicketService(db(), new ImagenService(db(), TK_ROOT . '/uploads'));
}

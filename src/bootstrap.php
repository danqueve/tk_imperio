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
    'pendiente'  => 'Pendiente',
    'en_proceso' => 'En proceso',
    'resuelto'   => 'Resuelto',
    'anulado'    => 'Anulado',
];

/** Chip pastel del estado (clases en assets/css/app.css). */
function badge_estado(string $estado): string
{
    $txt = ESTADOS[$estado] ?? $estado;
    $cls = isset(ESTADOS[$estado]) ? $estado : 'neutro';
    return '<span class="tk-chip tk-chip-' . $cls . '">' . e($txt) . '</span>';
}

/** Chip genérico: tipo = alta | tarea | neutro | resuelto … */
function chip(string $tipo, string $texto, bool $chico = false): string
{
    return '<span class="tk-chip tk-chip-' . e($tipo) . ($chico ? ' tk-chip-sm' : '') . '">' . e($texto) . '</span>';
}

/** Iniciales para el avatar: "Juan Pablo Bicego" → "JP". */
function iniciales(?string $nombre): string
{
    $partes = preg_split('/\s+/u', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

/** Color de usuario seguro para usar dentro de style="--c: …". */
function color_seguro(?string $c, string $defecto = '#B9B6CC'): string
{
    return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $defecto;
}

/** Persona asignada con su punto de color, o "Sin asignar". */
function persona(?string $nombre, ?string $color = null): string
{
    if ($nombre === null || $nombre === '') {
        return '<span class="tk-sin">Sin asignar</span>';
    }
    return '<span class="tk-persona"><span class="tk-dot" style="--c:' . color_seguro($color) . '"></span>' . e($nombre) . '</span>';
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

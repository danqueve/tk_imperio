<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';

$u = requiere_login();
$tickets = servicio_tickets();
$imagenes = new ImagenService(db(), TK_ROOT . '/uploads');

$img = $imagenes->obtener((int) ($_GET['id'] ?? 0));
$t = $img ? $tickets->obtener((int) $img['ticket_id']) : null;
$ruta = $img ? $imagenes->rutaArchivo($img) : '';

if (!$img || !$t || !$tickets->puedeVer($t, $u) || !is_file($ruta)) {
    http_response_code(404);
    exit('No encontrada.');
}

header('Content-Type: ' . $img['mime']);
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($ruta);

<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/SasService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!usuario_actual()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'sin_sesion']);
    exit;
}

try {
    $clientes = (new SasService(sas()))->buscarPorDni((string) ($_GET['dni'] ?? ''));
    echo json_encode(['ok' => true, 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    // SAS caído: el formulario deja escribir el nombre a mano.
    echo json_encode(['ok' => false, 'error' => 'sas_no_disponible', 'clientes' => []]);
}

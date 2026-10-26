<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require TK_ROOT . '/src/PantallaService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $p = new PantallaService(db());
    if (!$p->tokenValido((string) ($_GET['t'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'token']);
        exit;
    }
    echo json_encode([
        'ok'        => true,
        'ahora'     => time(),
        'intervalo' => $p->intervalo(),
        'columnas'  => $p->datos(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'servidor']);
}

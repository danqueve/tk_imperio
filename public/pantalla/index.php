<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require TK_ROOT . '/src/PantallaService.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');

$p = new PantallaService(db());
$token = (string) ($_GET['t'] ?? '');
if (!$p->tokenValido($token)) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}
$empresa = $p->config('empresa', 'TK Imperio');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Tickets abiertos · <?= e($empresa) ?></title>
    <link href="<?= e(url('assets/css/pantalla.css')) ?>" rel="stylesheet">
</head>
<body>
<header id="cab">
    <h1><?= e($empresa) ?> · Tickets abiertos</h1>
    <div id="estado">
        <span id="sinconexion" hidden>Sin conexión</span>
        <span id="actualizado">Cargando…</span>
        <button id="pantalla-completa" type="button" title="Pantalla completa">⛶</button>
    </div>
</header>
<main id="cols" aria-live="polite"></main>
<script>
window.PANTALLA = {
    api: <?= json_encode(url('api/pantalla.php?t=' . rawurlencode($token))) ?>,
    reintento: 60,
    recargaHora: 6
};
</script>
<script src="<?= e(url('assets/js/pantalla.js')) ?>"></script>
</body>
</html>

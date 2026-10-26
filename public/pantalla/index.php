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
    <meta name="theme-color" content="#F3F2F9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(url('assets/css/pantalla.css')) ?>?v=2" rel="stylesheet">
</head>
<body>
<header id="cab">
    <div class="marca">
        <span class="logo" aria-hidden="true">TK</span>
        <div>
            <h1>Tickets abiertos</h1>
            <div class="sub"><?= e($empresa) ?> · <span id="total">…</span></div>
        </div>
    </div>
    <div id="estado">
        <span id="sinconexion" class="pastilla" hidden><span class="luz"></span>Sin conexión</span>
        <span class="pastilla"><span class="luz"></span><span id="actualizado">Cargando…</span></span>
        <button id="pantalla-completa" type="button" title="Pantalla completa" aria-label="Pantalla completa">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
        </button>
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
<script src="<?= e(url('assets/js/pantalla.js')) ?>?v=2"></script>
</body>
</html>

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';

$u = requiere_login();

layout_inicio('Bandeja', 'bandeja.php', $u);
?>
<h1 class="h4">Bandeja</h1>
<p class="text-muted">Hola, <?= e($u['nombre']) ?>. Los tickets llegan en la fase 3.</p>
<?php layout_fin('bandeja.php', $u);

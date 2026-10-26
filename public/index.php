<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/layout.php';

$estado = ['TK' => false, 'SAS' => false];
foreach (['TK' => 'db', 'SAS' => 'sas'] as $nombre => $fn) {
    try {
        $fn()->query('SELECT 1');
        $estado[$nombre] = true;
    } catch (Throwable $ex) {
        $estado[$nombre] = false;
    }
}

layout_inicio('Inicio', 'index.php');
?>
<h1 class="h4">Base del proyecto</h1>
<p class="text-muted">Fase 1: estructura, configuración y layout. El login llega en la fase 2.</p>
<ul class="list-group" style="max-width: 420px">
    <?php foreach ($estado as $nombre => $ok): ?>
        <li class="list-group-item d-flex justify-content-between">
            Conexión <?= e($nombre) ?>
            <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $ok ? 'OK' : 'Sin conexión' ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<?php layout_fin('index.php');

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';

$u = requiere_login();
$svc = servicio_tickets();
$t = $svc->obtener((int) ($_GET['id'] ?? 0));

if (!$t || !$svc->puedeVer($t, $u)) {
    http_response_code(404);
    exit('El ticket no existe o no tenés acceso.');
}

$imagenes = (new ImagenService(db(), TK_ROOT . '/uploads'))->listar((int) $t['id']);
$eventos = $svc->eventos((int) $t['id']);
$titulo = $t['tipo'] === 'tarea' ? $t['titulo'] : $t['cliente'];
$vencido = $t['compromiso_fecha'] && strtotime($t['compromiso_fecha']) < time()
    && in_array($t['estado'], ['pendiente', 'en_proceso'], true);

layout_inicio('#' . $t['id'], 'bandeja.php', $u);
?>
<div class="mb-3"><a href="<?= e(url('bandeja.php')) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Bandeja</a></div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <span class="badge text-bg-dark"><?= $t['tipo'] === 'tarea' ? 'Tarea del día' : 'Ticket' ?> #<?= (int) $t['id'] ?></span>
                <?= badge_estado($t['estado']) ?>
                <?php if ($t['prioridad'] === 'alta'): ?><span class="badge text-bg-danger">Prioridad alta</span><?php endif; ?>
            </div>
            <h1 class="h4"><?= e($titulo) ?></h1>
            <dl class="row mb-0">
                <?php if ($t['tipo'] === 'ticket'): ?>
                    <dt class="col-sm-4">DNI</dt><dd class="col-sm-8"><?= e($t['dni']) ?></dd>
                    <dt class="col-sm-4">Cliente SAS</dt><dd class="col-sm-8"><?= $t['sas_cliente_id'] ? '#' . (int) $t['sas_cliente_id'] : 'No está en SAS' ?></dd>
                <?php else: ?>
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(date('d/m/Y', strtotime($t['fecha_tarea']))) ?></dd>
                    <?php if ($t['compromiso_fecha']): ?>
                        <dt class="col-sm-4">Compromiso</dt>
                        <dd class="col-sm-8 <?= $vencido ? 'text-danger fw-bold' : '' ?>">
                            <?= e(date('d/m/Y H:i', strtotime($t['compromiso_fecha']))) ?>
                            <?= $t['compromiso_detalle'] ? '· ' . e($t['compromiso_detalle']) : '' ?>
                            <?= $vencido ? '(vencido)' : '' ?>
                        </dd>
                    <?php endif; ?>
                <?php endif; ?>
                <dt class="col-sm-4">Asignado a</dt><dd class="col-sm-8"><?= $t['asignado_nombre'] ? e($t['asignado_nombre']) : '<em>Sin asignar</em>' ?></dd>
                <dt class="col-sm-4">Cargado por</dt><dd class="col-sm-8"><?= e($t['creador_nombre']) ?> · <?= e(date('d/m/Y H:i', strtotime($t['creado_en']))) ?> (<?= e(hace($t['creado_en'])) ?>)</dd>
            </dl>
            <?php if ($t['problematica']): ?>
                <hr><div style="white-space: pre-wrap"><?= e($t['problematica']) ?></div>
            <?php endif; ?>
            <?php if ($t['nota_resolucion']): ?>
                <hr><strong>Resolución</strong> (<?= e((string) $t['resolvio_nombre']) ?>)
                <div style="white-space: pre-wrap"><?= e($t['nota_resolucion']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($imagenes): ?>
        <div class="d-flex gap-2 flex-wrap mt-3">
            <?php foreach ($imagenes as $im): $src = url('imagen.php?id=' . (int) $im['id']); ?>
                <a href="#" data-bs-toggle="modal" data-bs-target="#visor" data-src="<?= e($src) ?>">
                    <img src="<?= e($src) ?>" alt="Imagen <?= (int) $im['orden'] ?>" class="rounded border"
                         style="height:110px;width:110px;object-fit:cover" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
        <div class="modal fade" id="visor" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
                <div class="modal-body p-1 text-center"><img id="visor-img" class="img-fluid" alt="Imagen ampliada"></div>
            </div></div>
        </div>
        <script>
            document.getElementById('visor').addEventListener('show.bs.modal', function (ev) {
                document.getElementById('visor-img').src = ev.relatedTarget.dataset.src;
            });
        </script>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card card-body">
            <h2 class="h6">Historial</h2>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($eventos as $ev): ?>
                    <li class="mb-2">
                        <strong><?= e(ucfirst($ev['accion'])) ?></strong> · <?= e($ev['usuario_nombre']) ?>
                        <span class="text-muted">· <?= e(date('d/m/Y H:i', strtotime($ev['creado_en']))) ?></span>
                        <?php if ($ev['detalle']): ?><div><?= e($ev['detalle']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php layout_fin('bandeja.php', $u);

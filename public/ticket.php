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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = (string) ($_POST['accion'] ?? '');
    $idT = (int) $t['id'];
    try {
        switch ($accion) {
            case 'tomar':
                $svc->tomar($idT, $u);
                flash('success', 'Ticket tomado: queda En proceso.');
                break;
            case 'resolver':
                $svc->resolver($idT, (string) ($_POST['nota'] ?? ''), $u);
                flash('success', 'Resuelto. Sale de la pantalla principal.');
                break;
            case 'reasignar':
                $dest = ($_POST['asignado_a'] ?? '') === '' ? null : (int) $_POST['asignado_a'];
                $svc->reasignar($idT, $dest, $u);
                flash('success', 'Reasignado: vuelve a Pendiente.');
                break;
            case 'anular':
                $svc->anular($idT, (string) ($_POST['motivo'] ?? ''), $u);
                flash('success', 'Anulado.');
                break;
            default:
                flash('danger', 'Acción desconocida.');
        }
    } catch (DomainException $ex) {
        flash('danger', $ex->getMessage());
    }
    header('Location: ' . url('ticket.php?id=' . $idT));
    exit;
}

$acciones = $svc->acciones($t, $u);
$asignables = in_array('reasignar', $acciones, true) ? $svc->asignables($u) : [];

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

        <?php if ($acciones): ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if (in_array('tomar', $acciones, true)): ?>
                <form method="post" class="m-0"><?= csrf_campo() ?><input type="hidden" name="accion" value="tomar">
                    <button class="btn btn-primary btn-lg"><i class="bi bi-hand-index-thumb"></i> Tomar</button></form>
            <?php endif; ?>
            <?php if (in_array('resolver', $acciones, true)): ?>
                <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#m-resolver"><i class="bi bi-check-circle"></i> Resolver</button>
            <?php endif; ?>
            <?php if (in_array('reasignar', $acciones, true)): ?>
                <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#m-reasignar"><i class="bi bi-arrow-left-right"></i> Reasignar</button>
            <?php endif; ?>
            <?php if (in_array('anular', $acciones, true)): ?>
                <button class="btn btn-outline-danger btn-lg" data-bs-toggle="modal" data-bs-target="#m-anular"><i class="bi bi-x-circle"></i> Anular</button>
            <?php endif; ?>
        </div>

        <?php if (in_array('resolver', $acciones, true)): ?>
        <div class="modal fade" id="m-resolver" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content"><?= csrf_campo() ?><input type="hidden" name="accion" value="resolver">
                <div class="modal-header"><h2 class="modal-title h5">Resolver</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><label class="form-label" for="nota">Nota de solución (obligatoria)</label>
                    <textarea class="form-control" id="nota" name="nota" rows="4" required></textarea></div>
                <div class="modal-footer"><button class="btn btn-success">Marcar como resuelto</button></div>
            </form></div></div>
        <?php endif; ?>

        <?php if (in_array('reasignar', $acciones, true)): ?>
        <div class="modal fade" id="m-reasignar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content"><?= csrf_campo() ?><input type="hidden" name="accion" value="reasignar">
                <div class="modal-header"><h2 class="modal-title h5">Reasignar</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><label class="form-label" for="asignado_a">Asignar a</label>
                    <select class="form-select" id="asignado_a" name="asignado_a">
                        <option value="">Sin asignar</option>
                        <?php foreach ($asignables as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) $t['asignado_a'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">El ticket vuelve a Pendiente y cambia de columna en la pantalla.</div></div>
                <div class="modal-footer"><button class="btn btn-primary">Reasignar</button></div>
            </form></div></div>
        <?php endif; ?>

        <?php if (in_array('anular', $acciones, true)): ?>
        <div class="modal fade" id="m-anular" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content"><?= csrf_campo() ?><input type="hidden" name="accion" value="anular">
                <div class="modal-header"><h2 class="modal-title h5">Anular</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><label class="form-label" for="motivo">Motivo (obligatorio)</label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea></div>
                <div class="modal-footer"><button class="btn btn-danger">Anular ticket</button></div>
            </form></div></div>
        <?php endif; ?>
        <?php endif; ?>

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

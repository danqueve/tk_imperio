<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';

$u = requiere_login();
$svc = servicio_tickets();
$super = es_supervisor($u);

$f = [
    'usuario' => (string) ($_GET['usuario'] ?? ''),
    'estado'  => (string) ($_GET['estado'] ?? ''),
    'tipo'    => (string) ($_GET['tipo'] ?? ''),
];
$filas = $svc->bandeja($u, $f);
$usuarios = $super ? $svc->asignables($u) : [];

function fila_titulo(array $t): string
{
    return $t['tipo'] === 'tarea' ? (string) $t['titulo'] : (string) $t['cliente'];
}

layout_inicio('Bandeja', 'bandeja.php', $u);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Bandeja <span class="badge text-bg-secondary"><?= count($filas) ?></span></h1>
    <a class="btn btn-primary d-none d-md-inline-block" href="<?= e(url('ticket_nuevo.php')) ?>"><i class="bi bi-plus-lg"></i> Nuevo</a>
</div>

<form method="get" class="row g-2 mb-3">
    <?php if ($super): ?>
    <div class="col-6 col-md-3">
        <select class="form-select" name="usuario" onchange="this.form.submit()" aria-label="Usuario">
            <option value="">Todos los usuarios</option>
            <option value="sin" <?= $f['usuario'] === 'sin' ? 'selected' : '' ?>>Sin asignar</option>
            <?php foreach ($usuarios as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $f['usuario'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-3">
        <select class="form-select" name="estado" onchange="this.form.submit()" aria-label="Estado">
            <option value="">Pendientes y en proceso</option>
            <option value="pendiente" <?= $f['estado'] === 'pendiente' ? 'selected' : '' ?>>Solo pendientes</option>
            <option value="en_proceso" <?= $f['estado'] === 'en_proceso' ? 'selected' : '' ?>>Solo en proceso</option>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" name="tipo" onchange="this.form.submit()" aria-label="Tipo">
            <option value="">Tickets y tareas</option>
            <option value="ticket" <?= $f['tipo'] === 'ticket' ? 'selected' : '' ?>>Tickets</option>
            <option value="tarea" <?= $f['tipo'] === 'tarea' ? 'selected' : '' ?>>Tareas del día</option>
        </select>
    </div>
</form>

<?php if (!$filas): ?>
    <div class="alert alert-light border">No hay nada abierto. 🎉</div>
<?php else: ?>

<div class="table-responsive d-none d-md-block">
<table class="table table-hover align-middle bg-white">
    <thead><tr><th>#</th><th>Cliente / Tarea</th><th>DNI</th><th>Asignado a</th><th>Estado</th><th>Cargado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($filas as $t): ?>
        <tr class="<?= $t['prioridad'] === 'alta' ? 'table-danger' : '' ?>">
            <td><?= (int) $t['id'] ?></td>
            <td>
                <a class="fw-semibold text-decoration-none" href="<?= e(url('ticket.php?id=' . (int) $t['id'])) ?>"><?= e(fila_titulo($t)) ?></a>
                <?= $t['tipo'] === 'tarea' ? '<span class="badge text-bg-light border">Tarea</span>' : '' ?>
                <?= $t['n_imagenes'] ? '<i class="bi bi-image text-muted"></i>' : '' ?>
            </td>
            <td><?= e($t['dni'] ?? '—') ?></td>
            <td><?= $t['asignado_nombre'] ? e($t['asignado_nombre']) : '<em class="text-muted">Sin asignar</em>' ?></td>
            <td><?= badge_estado($t['estado']) ?></td>
            <td class="text-nowrap"><?= e(hace($t['creado_en'])) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('ticket.php?id=' . (int) $t['id'])) ?>">Abrir</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="d-md-none d-grid gap-2">
    <?php foreach ($filas as $t): ?>
        <a class="card text-decoration-none text-body <?= $t['prioridad'] === 'alta' ? 'border-danger border-2' : '' ?>"
           href="<?= e(url('ticket.php?id=' . (int) $t['id'])) ?>">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between">
                    <strong><?= e(fila_titulo($t)) ?></strong><?= badge_estado($t['estado']) ?>
                </div>
                <div class="small text-muted">
                    <?= $t['tipo'] === 'tarea' ? 'Tarea del día' : 'DNI ' . e($t['dni']) ?>
                    · <?= e(hace($t['creado_en'])) ?>
                    <?= $t['n_imagenes'] ? '· <i class="bi bi-image"></i>' : '' ?>
                </div>
                <div class="small"><?= $t['asignado_nombre'] ? e($t['asignado_nombre']) : '<em>Sin asignar</em>' ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_fin('bandeja.php', $u);

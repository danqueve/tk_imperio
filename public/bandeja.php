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

$n = ['pendiente' => 0, 'en_proceso' => 0, 'alta' => 0, 'sin' => 0, 'tarea' => 0];
foreach ($filas as $t) {
    $n[$t['estado']] = ($n[$t['estado']] ?? 0) + 1;
    $n['alta'] += $t['prioridad'] === 'alta' ? 1 : 0;
    $n['sin'] += $t['asignado_a'] === null ? 1 : 0;
    $n['tarea'] += $t['tipo'] === 'tarea' ? 1 : 0;
}

/** URL de la bandeja con los filtros actuales cambiando algunos. */
$link = fn(array $cambios) => url('bandeja.php') . (($q = http_build_query(array_filter($cambios + $f, fn($v) => $v !== ''))) ? '?' . $q : '');

layout_inicio('Bandeja', 'bandeja.php', $u);
?>
<div class="tk-head">
    <div>
        <h1>Bandeja <span class="tk-count"><?= count($filas) ?> abiertos</span></h1>
        <p><?= $super ? 'Tickets y tareas del día pendientes o en proceso' : 'Tus tickets y tareas del día pendientes o en proceso' ?></p>
    </div>
    <a class="btn btn-primary btn-lg d-none d-md-inline-flex" href="<?= e(url('ticket_nuevo.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo ticket</a>
</div>

<div class="tk-stats">
    <a class="tk-stat butter" href="<?= e($link(['estado' => 'pendiente'])) ?>"><span>Pendientes</span><strong><?= $n['pendiente'] ?></strong></a>
    <a class="tk-stat sky" href="<?= e($link(['estado' => 'en_proceso'])) ?>"><span>En proceso</span><strong><?= $n['en_proceso'] ?></strong></a>
    <div class="tk-stat rose"><span>Prioridad alta</span><strong><?= $n['alta'] ?></strong></div>
    <?php if ($super): ?>
        <a class="tk-stat lilac" href="<?= e($link(['usuario' => 'sin'])) ?>"><span>Sin asignar</span><strong><?= $n['sin'] ?></strong></a>
    <?php else: ?>
        <a class="tk-stat lilac" href="<?= e($link(['tipo' => 'tarea'])) ?>"><span>Tareas del día</span><strong><?= $n['tarea'] ?></strong></a>
    <?php endif; ?>
</div>

<form method="get" class="tk-filtros">
    <?php if ($f['tipo'] !== ''): ?><input type="hidden" name="tipo" value="<?= e($f['tipo']) ?>"><?php endif; ?>
    <?php if ($super): ?>
        <select class="form-select" name="usuario" onchange="this.form.submit()" aria-label="Usuario">
            <option value="">Todos los usuarios</option>
            <option value="sin" <?= $f['usuario'] === 'sin' ? 'selected' : '' ?>>Sin asignar</option>
            <?php foreach ($usuarios as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $f['usuario'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select class="form-select" name="estado" onchange="this.form.submit()" aria-label="Estado">
        <option value="">Pendientes y en proceso</option>
        <option value="pendiente" <?= $f['estado'] === 'pendiente' ? 'selected' : '' ?>>Solo pendientes</option>
        <option value="en_proceso" <?= $f['estado'] === 'en_proceso' ? 'selected' : '' ?>>Solo en proceso</option>
    </select>
    <div class="tk-seg" role="group" aria-label="Tipo">
        <?php foreach (['' => 'Todo', 'ticket' => 'Tickets', 'tarea' => 'Tareas del día'] as $val => $txt): ?>
            <a href="<?= e($link(['tipo' => $val])) ?>" class="<?= $f['tipo'] === $val ? 'active' : '' ?>"
               <?= $f['tipo'] === $val ? 'aria-current="true"' : '' ?>><?= e($txt) ?></a>
        <?php endforeach; ?>
    </div>
    <noscript><button class="btn btn-outline-secondary">Filtrar</button></noscript>
</form>

<?php if (!$filas): ?>
    <div class="tk-empty">
        <i class="bi bi-check2" aria-hidden="true"></i>
        <strong>No hay nada abierto</strong>
        <p>Todo resuelto con estos filtros.</p>
    </div>
<?php else: ?>

<div class="tk-table-wrap d-none d-md-block">
<table class="table table-hover align-middle">
    <thead><tr><th>#</th><th>Cliente / tarea</th><th>DNI</th><th>Asignado a</th><th>Estado</th><th>Cargado</th><th><span class="visually-hidden">Acciones</span></th></tr></thead>
    <tbody>
    <?php foreach ($filas as $t): $href = url('ticket.php?id=' . (int) $t['id']); ?>
        <tr>
            <td class="tk-id"><?= (int) $t['id'] ?></td>
            <td>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="tk-row-title" href="<?= e($href) ?>"><?= e(fila_titulo($t)) ?></a>
                    <?= $t['tipo'] === 'tarea' ? chip('tarea', 'Tarea', true) : '' ?>
                    <?= $t['prioridad'] === 'alta' ? chip('alta', 'Alta', true) : '' ?>
                    <?= $t['n_imagenes'] ? '<i class="bi bi-image text-muted" title="' . (int) $t['n_imagenes'] . ' imagen(es)"></i>' : '' ?>
                </div>
                <?php if (!empty($t['problematica'])): ?>
                    <div class="tk-row-sub"><?= e($t['problematica']) ?></div>
                <?php endif; ?>
            </td>
            <td class="tk-num text-muted"><?= e($t['dni'] ?? '—') ?></td>
            <td><?= persona($t['asignado_nombre'], $t['asignado_color'] ?? null) ?></td>
            <td><?= badge_estado($t['estado']) ?></td>
            <td class="text-nowrap text-muted"><?= e(hace($t['creado_en'])) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-soft" href="<?= e($href) ?>">Abrir <i class="bi bi-chevron-right" aria-hidden="true"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="tk-cards d-md-none">
    <?php foreach ($filas as $t): ?>
        <a class="tk-card <?= $t['prioridad'] === 'alta' ? 'alta' : '' ?>" href="<?= e(url('ticket.php?id=' . (int) $t['id'])) ?>">
            <div class="tk-card-top">
                <strong><?= e(fila_titulo($t)) ?></strong><?= badge_estado($t['estado']) ?>
            </div>
            <div class="tk-card-meta">
                <?= $t['tipo'] === 'tarea' ? 'Tarea del día' : 'DNI ' . e($t['dni']) ?>
                · <?= e(hace($t['creado_en'])) ?>
                <?= $t['n_imagenes'] ? '· <i class="bi bi-image" aria-label="con imágenes"></i>' : '' ?>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2 small">
                <?= persona($t['asignado_nombre'], $t['asignado_color'] ?? null) ?>
                <?= $t['prioridad'] === 'alta' ? chip('alta', 'Alta', true) : '' ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_fin('bandeja.php', $u);

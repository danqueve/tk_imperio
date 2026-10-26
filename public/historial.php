<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';
require TK_ROOT . '/src/SasService.php';
require TK_ROOT . '/src/HistorialService.php';

$u = requiere_login();
$super = es_supervisor($u);
$h = new HistorialService(db());

$f = [];
foreach (['q', 'estado', 'tipo', 'usuario', 'desde', 'hasta'] as $k) {
    $f[$k] = trim((string) ($_GET[$k] ?? ''));
}

if (($_GET['csv'] ?? '') === '1') {
    $filas = $h->todos($u, $f);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tk_historial_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
    // Evita que Excel interprete una celda como fórmula.
    $celda = static fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    fputcsv($out, ['ID', 'Tipo', 'Estado', 'Prioridad', 'DNI', 'Cliente / Tarea', 'Problemática', 'Asignado a', 'Cargado por',
        'Cargado', 'Tomado', 'Cerrado', 'Cerrado por', 'Minutos hasta cerrar', 'Nota / motivo'], ';');
    foreach ($filas as $t) {
        fputcsv($out, array_map($celda, [
            $t['id'], $t['tipo'], $t['estado'], $t['prioridad'], $t['dni'],
            $t['tipo'] === 'tarea' ? $t['titulo'] : $t['cliente'], $t['problematica'],
            $t['asignado_nombre'], $t['creador_nombre'], $t['creado_en'], $t['tomado_en'], $t['resuelto_en'],
            $t['resolvio_nombre'], $t['minutos'], $t['nota_resolucion'],
        ]), ';');
    }
    fclose($out);
    exit;
}

$pagina = max(1, (int) ($_GET['p'] ?? 1));
$total = $h->contar($u, $f);
$paginas = max(1, (int) ceil($total / HistorialService::POR_PAGINA));
$pagina = min($pagina, $paginas);
$filas = $h->buscar($u, $f, $pagina);
$promedios = $h->promedios($u, $f);
$usuarios = $super ? servicio_tickets()->asignables($u) : [];

$qs = fn(array $extra = []) => http_build_query(array_filter($extra + $f, fn($v) => $v !== '' && $v !== null));

layout_inicio('Historial', 'historial.php', $u);
?>
<div class="tk-head">
    <div>
        <h1>Historial <span class="tk-count"><?= $total ?></span></h1>
        <p>Tickets y tareas resueltos o anulados</p>
    </div>
    <a class="btn btn-outline-success" href="?<?= e($qs(['csv' => '1'])) ?>"><i class="bi bi-download" aria-hidden="true"></i> Exportar CSV</a>
</div>

<form method="get" class="card card-body mb-4">
    <div class="row g-2">
        <div class="col-12 col-md-4"><label class="form-label" for="q">DNI o cliente</label>
            <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar…"></div>
        <?php if ($super): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="usuario">Usuario</label>
            <select class="form-select" id="usuario" name="usuario"><option value="">Todos</option>
                <?php foreach ($usuarios as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= $f['usuario'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                <?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado"><option value="">Resuelto y anulado</option>
                <option value="resuelto" <?= $f['estado'] === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                <option value="anulado" <?= $f['estado'] === 'anulado' ? 'selected' : '' ?>>Anulado</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo"><option value="">Todos</option>
                <option value="ticket" <?= $f['tipo'] === 'ticket' ? 'selected' : '' ?>>Tickets</option>
                <option value="tarea" <?= $f['tipo'] === 'tarea' ? 'selected' : '' ?>>Tareas</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="desde">Cerrado desde</label>
            <input class="form-control" type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="hasta">Hasta</label>
            <input class="form-control" type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
        <div class="col-12 col-md-auto d-flex align-items-end gap-2">
            <button class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
            <a class="btn btn-link" href="<?= e(url('historial.php')) ?>">Limpiar</a></div>
    </div>
</form>

<?php if ($promedios): ?>
<div class="tk-table-wrap mb-4">
    <h2 class="h6 px-4 pt-3 mb-2">Tiempo promedio de resolución <span class="text-muted fw-normal small">(solo resueltos del filtro)</span></h2>
    <div>
    <table class="table table-sm mb-0">
        <thead><tr><th>Resuelto por</th><th class="text-end">Resueltos</th><th class="text-end">Desde la carga</th><th class="text-end">Desde que lo tomó</th></tr></thead>
        <tbody>
        <?php foreach ($promedios as $r): ?>
            <tr><td><?= e($r['nombre']) ?></td><td class="text-end"><?= (int) $r['cantidad'] ?></td>
                <td class="text-end"><?= e(duracion($r['min_desde_carga'] !== null ? (float) $r['min_desde_carga'] : null)) ?></td>
                <td class="text-end"><?= e(duracion($r['min_desde_toma'] !== null ? (float) $r['min_desde_toma'] : null)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$filas): ?>
    <div class="tk-empty">
        <i class="bi bi-search" aria-hidden="true"></i>
        <strong>Sin resultados</strong>
        <p>Probá con otros filtros o limpiá la búsqueda.</p>
    </div>
<?php else: ?>
<div class="tk-table-wrap">
<table class="table table-sm table-hover align-middle">
    <thead><tr><th>#</th><th>Cliente / Tarea</th><th>DNI</th><th>Estado</th><th>Asignado a</th><th>Cerrado</th><th>Tardó</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $t): ?>
        <tr>
            <td class="tk-id"><?= (int) $t['id'] ?></td>
            <td><a class="tk-row-title" href="<?= e(url('ticket.php?id=' . (int) $t['id'])) ?>">
                <?= e($t['tipo'] === 'tarea' ? $t['titulo'] : $t['cliente']) ?></a>
                <?= $t['tipo'] === 'tarea' ? chip('tarea', 'Tarea', true) : '' ?></td>
            <td><?= e($t['dni'] ?? '—') ?></td>
            <td><?= badge_estado($t['estado']) ?></td>
            <td><?= e((string) ($t['asignado_nombre'] ?? '—')) ?></td>
            <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($t['resuelto_en']))) ?></td>
            <td class="text-nowrap"><?= e(duracion($t['minutos'] !== null ? (float) $t['minutos'] : null)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($paginas > 1): ?>
<nav aria-label="Páginas"><ul class="pagination justify-content-center">
    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e($qs(['p' => $pagina - 1])) ?>">Anterior</a></li>
    <li class="page-item disabled"><span class="page-link"><?= $pagina ?> / <?= $paginas ?></span></li>
    <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e($qs(['p' => $pagina + 1])) ?>">Siguiente</a></li>
</ul></nav>
<?php endif; ?>
<?php endif; ?>
<?php layout_fin('historial.php', $u);

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';

$u = requiere_login();
$svc = servicio_tickets();
$asignables = $svc->asignables($u);
$v = $_POST; // valores para repoblar el formulario si hay error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    try {
        $id = $svc->crear($_POST, $_FILES['imagenes'] ?? [], $u);
        flash('success', 'Cargado correctamente (#' . $id . ').');
        header('Location: ' . url('ticket.php?id=' . $id));
        exit;
    } catch (DomainException $ex) {
        flash('danger', $ex->getMessage());
    }
}

$tipo = ($v['tipo'] ?? '') === 'tarea' ? 'tarea' : 'ticket';
$asigSel = $v['asignado_a'] ?? ($u['rol'] === 'usuario' ? (string) $u['id'] : '');

layout_inicio('Nuevo', 'ticket_nuevo.php', $u);
?>
<h1 class="h4 mb-3">Nuevo</h1>
<form method="post" enctype="multipart/form-data" id="form-nuevo" class="card card-body" style="max-width: 760px" novalidate>
    <?= csrf_campo() ?>

    <div class="btn-group mb-3" role="group">
        <input type="radio" class="btn-check" name="tipo" id="t-ticket" value="ticket" <?= $tipo === 'ticket' ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary" for="t-ticket"><i class="bi bi-ticket-detailed"></i> Ticket</label>
        <input type="radio" class="btn-check" name="tipo" id="t-tarea" value="tarea" <?= $tipo === 'tarea' ? 'checked' : '' ?>>
        <label class="btn btn-outline-primary" for="t-tarea"><i class="bi bi-check2-square"></i> Tarea del día</label>
    </div>

    <section data-solo="ticket">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label" for="dni">DNI</label>
                <input class="form-control form-control-lg" id="dni" name="dni" inputmode="numeric" autocomplete="off"
                       maxlength="14" value="<?= e($v['dni'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="cliente">Cliente</label>
                <input class="form-control form-control-lg" id="cliente" name="cliente" maxlength="200"
                       placeholder="Se completa al buscar el DNI" value="<?= e($v['cliente'] ?? '') ?>">
                <input type="hidden" name="sas_cliente_id" id="sas_cliente_id" value="<?= e($v['sas_cliente_id'] ?? '') ?>">
            </div>
        </div>
        <div id="dni-estado" class="form-text mb-2" aria-live="polite"></div>
        <div id="dni-lista" class="list-group mb-3 d-none"></div>

        <label class="form-label" for="problematica">Problemática</label>
        <textarea class="form-control mb-3" id="problematica" name="problematica" rows="4"><?= e($v['problematica'] ?? '') ?></textarea>
    </section>

    <section data-solo="tarea" class="d-none">
        <label class="form-label" for="titulo">Título</label>
        <input class="form-control form-control-lg mb-3" id="titulo" name="titulo" maxlength="200" value="<?= e($v['titulo'] ?? '') ?>">
        <label class="form-label" for="descripcion">Descripción</label>
        <textarea class="form-control mb-3" id="descripcion" name="problematica" rows="3"><?= e($v['problematica'] ?? '') ?></textarea>
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="fecha_tarea">Fecha</label>
                <input class="form-control" type="date" id="fecha_tarea" name="fecha_tarea" value="<?= e($v['fecha_tarea'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="compromiso_fecha">Compromiso (fecha y hora límite, opcional)</label>
                <input class="form-control" type="datetime-local" id="compromiso_fecha" name="compromiso_fecha" value="<?= e($v['compromiso_fecha'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="compromiso_detalle">Detalle del compromiso (opcional)</label>
                <input class="form-control" id="compromiso_detalle" name="compromiso_detalle" maxlength="255" value="<?= e($v['compromiso_detalle'] ?? '') ?>">
            </div>
        </div>
    </section>

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-6">
            <label class="form-label" for="asignado_a">Asignado a</label>
            <select class="form-select" id="asignado_a" name="asignado_a">
                <option value="">Sin asignar</option>
                <?php foreach ($asignables as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= (string) $asigSel === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label d-block">Prioridad</label>
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="prioridad" id="p-normal" value="normal" <?= ($v['prioridad'] ?? 'normal') !== 'alta' ? 'checked' : '' ?>>
                <label class="btn btn-outline-secondary" for="p-normal">Normal</label>
                <input type="radio" class="btn-check" name="prioridad" id="p-alta" value="alta" <?= ($v['prioridad'] ?? '') === 'alta' ? 'checked' : '' ?>>
                <label class="btn btn-outline-danger" for="p-alta">Alta</label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="imagenes">Imágenes (hasta 3)</label>
        <input class="form-control" type="file" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div id="previas" class="d-flex gap-2 mt-2 flex-wrap"></div>
        <div class="form-text">Desde el celular podés sacar la foto o elegirla de la galería. Se reducen antes de subirlas.</div>
    </div>

    <div>
        <button class="btn btn-primary btn-lg" id="btn-enviar">Guardar</button>
        <a class="btn btn-link" href="<?= e(url('bandeja.php')) ?>">Cancelar</a>
    </div>
</form>
<script>window.TK = { urlCliente: <?= json_encode(url('api/cliente.php')) ?> };</script>
<script src="<?= e(url('assets/js/ticket_nuevo.js')) ?>"></script>
<?php layout_fin('ticket_nuevo.php', $u);

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';
require TK_ROOT . '/src/UsuarioService.php';

$yo = requiere_rol('admin');
$svc = new UsuarioService(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch ($accion) {
            case 'crear':
                $svc->crear($_POST);
                flash('success', 'Usuario creado.');
                break;
            case 'editar':
                $svc->editar($id, $_POST, (int) $yo['id']);
                flash('success', 'Usuario actualizado.');
                break;
            case 'password':
                $svc->resetPassword($id, (string) ($_POST['password'] ?? ''));
                flash('success', 'Contraseña actualizada.');
                break;
            default:
                flash('danger', 'Acción desconocida.');
        }
    } catch (DomainException $ex) {
        flash('danger', $ex->getMessage());
    }
    header('Location: ' . url('usuarios.php'));
    exit;
}

$usuarios = $svc->listar();
$editando = isset($_GET['editar']) ? $svc->obtener((int) $_GET['editar']) : null;

layout_inicio('Usuarios', 'usuarios.php', $yo);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Usuarios</h1>
    <a class="btn btn-primary" href="#nuevo" data-bs-toggle="collapse" data-bs-target="#nuevo"><i class="bi bi-plus-lg"></i> Nuevo</a>
</div>

<div class="collapse mb-3" id="nuevo">
    <form method="post" class="card card-body">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="crear">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required maxlength="120"></div>
            <div class="col-md-3"><label class="form-label">Usuario</label><input class="form-control" name="usuario" required maxlength="60" autocapitalize="none"></div>
            <div class="col-md-3"><label class="form-label">Contraseña (mín. 8)</label><input class="form-control" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="col-6 col-md-2"><label class="form-label">Rol</label>
                <select class="form-select" name="rol"><option value="usuario">Usuario</option><option value="supervisor">Supervisor</option></select></div>
            <div class="col-6 col-md-1"><label class="form-label">Color</label><input class="form-control form-control-color w-100" type="color" name="color" value="#0d6efd"></div>
        </div>
        <div class="mt-3"><button class="btn btn-success">Crear usuario</button></div>
    </form>
</div>

<?php if ($editando): ?>
<div class="card card-body mb-3 border-primary">
    <h2 class="h6">Editar: <?= e($editando['usuario']) ?> <span class="badge text-bg-secondary"><?= e($editando['origen']) ?></span></h2>
    <form method="post" class="row g-2 mb-3">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
        <div class="col-md-4"><label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required maxlength="120" value="<?= e($editando['nombre']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label">Rol</label>
            <?php if ($editando['rol'] === 'admin'): ?>
                <input class="form-control" value="admin" disabled>
            <?php else: ?>
                <select class="form-select" name="rol">
                    <?php foreach (['usuario', 'supervisor'] as $r): ?>
                        <option value="<?= $r ?>" <?= $editando['rol'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?></div>
        <div class="col-3 col-md-1"><label class="form-label">Color</label>
            <input class="form-control form-control-color w-100" type="color" name="color" value="<?= e($editando['color']) ?>"></div>
        <div class="col-3 col-md-2"><label class="form-label d-block">Activo</label>
            <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="activo" value="1" <?= $editando['activo'] ? 'checked' : '' ?>></div></div>
        <div class="col-12"><button class="btn btn-primary">Guardar</button>
            <a class="btn btn-link" href="<?= e(url('usuarios.php')) ?>">Cancelar</a></div>
    </form>
    <?php if ($editando['origen'] === 'local'): ?>
    <form method="post" class="row g-2 border-top pt-3">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="password">
        <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
        <div class="col-md-4"><label class="form-label">Nueva contraseña (mín. 8)</label>
            <input class="form-control" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-secondary">Resetear contraseña</button></div>
    </form>
    <?php else: ?>
        <p class="small text-muted m-0">Este usuario es administrador de SAS Imperio: su contraseña y su rol se manejan allá.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-sm table-hover align-middle bg-white">
    <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Origen</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $r): ?>
        <tr class="<?= $r['activo'] ? '' : 'text-muted' ?>">
            <td><span class="badge" style="background:<?= e($r['color']) ?>">&nbsp;</span> <?= e($r['usuario']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><?= e($r['rol']) ?></td>
            <td><?= e($r['origen']) ?></td>
            <td><?= $r['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($r['ultimo_login']))) : '—' ?></td>
            <td><?= $r['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('usuarios.php?editar=' . (int) $r['id'])) ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php layout_fin('usuarios.php', $yo);

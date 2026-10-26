<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';
require TK_ROOT . '/src/PantallaService.php';

$u = requiere_rol('admin');
$p = new PantallaService(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    switch ($_POST['accion'] ?? '') {
        case 'token':
            $p->regenerarToken();
            flash('warning', 'Token regenerado. Hay que abrir la pantalla de pared con la nueva dirección.');
            break;
        case 'ajustes':
            $seg = (int) ($_POST['intervalo'] ?? 300);
            if ($seg < 60 || $seg > 3600) {
                flash('danger', 'El intervalo va de 60 a 3600 segundos.');
                break;
            }
            $empresa = trim((string) ($_POST['empresa'] ?? ''));
            $p->guardarConfig('pantalla_intervalo', (string) $seg);
            if ($empresa !== '') {
                $p->guardarConfig('empresa', mb_substr($empresa, 0, 80));
            }
            flash('success', 'Configuración guardada.');
            break;
    }
    header('Location: ' . url('configuracion.php'));
    exit;
}

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urlPantalla = $esquema . '://' . $_SERVER['HTTP_HOST'] . url('pantalla/?t=' . $p->token());

layout_inicio('Configuración', 'configuracion.php', $u);
?>
<h1 class="h4 mb-3">Configuración de la pantalla</h1>

<div class="card card-body mb-3" style="max-width: 760px">
    <h2 class="h6">Dirección de la pantalla de pared</h2>
    <p class="small text-muted">No pide usuario ni contraseña: quien tenga esta dirección la ve. Si se filtra, regenerá el token.</p>
    <div class="input-group mb-2">
        <input class="form-control" id="url-pantalla" readonly value="<?= e($urlPantalla) ?>">
        <button class="btn btn-outline-secondary" type="button" id="copiar">Copiar</button>
        <a class="btn btn-outline-primary" href="<?= e($urlPantalla) ?>" target="_blank" rel="noopener">Abrir</a>
    </div>
    <form method="post" class="m-0" onsubmit="return confirm('La dirección actual dejará de funcionar. ¿Regenerar el token?')">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="token">
        <button class="btn btn-sm btn-outline-danger">Regenerar token</button>
    </form>
</div>

<form method="post" class="card card-body" style="max-width: 760px">
    <?= csrf_campo() ?><input type="hidden" name="accion" value="ajustes">
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label" for="empresa">Nombre en la pantalla</label>
            <input class="form-control" id="empresa" name="empresa" maxlength="80" value="<?= e($p->config('empresa', 'TK Imperio')) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="intervalo">Actualizar cada (segundos, 60–3600)</label>
            <input class="form-control" id="intervalo" name="intervalo" type="number" min="60" max="3600" value="<?= $p->intervalo() ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Guardar</button></div>
</form>
<script>
document.getElementById('copiar').addEventListener('click', function () {
    const i = document.getElementById('url-pantalla');
    i.select();
    (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.resolve(document.execCommand('copy')))
        .then(function () { document.getElementById('copiar').textContent = 'Copiado'; });
});
</script>
<?php layout_fin('configuracion.php', $u);

<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';
require TK_ROOT . '/src/layout.php';

if (usuario_actual()) {
    header('Location: ' . url('bandeja.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $usuario  = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (login_bloqueado($usuario)) {
        $error = 'Demasiados intentos. Esperá ' . BLOQUEO_MIN . ' minutos y reintentá.';
    } elseif ($usuario === '' || $password === '') {
        $error = 'Completá usuario y contraseña.';
    } elseif (($uid = login_validar($usuario, $password)) !== null) {
        login_limpiar_fallos($usuario);
        login_iniciar_sesion($uid);
        header('Location: ' . url('bandeja.php'));
        exit;
    } else {
        login_registrar_fallo($usuario);
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <?php layout_head('Ingresar'); ?>
</head>
<body>
<div class="tk-login">
    <section class="tk-login-art" aria-hidden="true">
        <span class="forma f1"></span><span class="forma f2"></span><span class="forma f3"></span>
        <div class="tk-brand"><span class="tk-logo">TK</span>TK Imperio</div>
        <div>
            <h2>Cada problema, con dueño y a la vista.</h2>
            <p>Tickets y tareas del día de Imperio Comercial, asignados y seguidos en la pantalla de la oficina.</p>
        </div>
    </section>

    <main class="tk-login-form">
        <form method="post" autocomplete="on">
            <?= csrf_campo() ?>
            <div class="tk-brand d-lg-none mb-2"><span class="tk-logo" aria-hidden="true">TK</span>TK Imperio</div>
            <div>
                <h1>Hola de nuevo</h1>
                <p class="text-muted m-0">Ingresá con tu usuario para ver tu bandeja.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-danger py-2 m-0" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div>
                <label class="form-label" for="usuario">Usuario</label>
                <input class="form-control form-control-lg" id="usuario" name="usuario" required autofocus
                       autocomplete="username" autocapitalize="none" value="<?= e($_POST['usuario'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control form-control-lg" id="password" name="password" type="password" required
                       autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-lg w-100">Ingresar</button>
            <p class="tk-login-nota"><i class="bi bi-info-circle" aria-hidden="true"></i> Los administradores entran con su cuenta de SAS Imperio.</p>
        </form>
    </main>
</div>
</body>
</html>

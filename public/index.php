<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require TK_ROOT . '/src/auth.php';

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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · TK Imperio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center" style="min-height:100vh">
<main class="container" style="max-width:380px">
    <h1 class="h3 text-center mb-4">TK Imperio</h1>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="card card-body shadow-sm" autocomplete="on">
        <?= csrf_campo() ?>
        <label class="form-label" for="usuario">Usuario</label>
        <input class="form-control form-control-lg mb-3" id="usuario" name="usuario" required autofocus
               autocomplete="username" value="<?= e($_POST['usuario'] ?? '') ?>">
        <label class="form-label" for="password">Contraseña</label>
        <input class="form-control form-control-lg mb-3" id="password" name="password" type="password" required
               autocomplete="current-password">
        <button class="btn btn-primary btn-lg">Ingresar</button>
        <div class="form-text mt-2">Los administradores entran con su cuenta de SAS Imperio.</div>
    </form>
</main>
</body>
</html>

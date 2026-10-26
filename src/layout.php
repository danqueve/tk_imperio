<?php
declare(strict_types=1);

/** [archivo, etiqueta, ícono, roles permitidos] */
const NAV_ITEMS = [
    ['bandeja.php',      'Bandeja',   'bi-inbox',         ['admin', 'supervisor', 'usuario']],
    ['ticket_nuevo.php', 'Nuevo',     'bi-plus-circle',   ['admin', 'supervisor', 'usuario']],
    ['historial.php',    'Historial', 'bi-clock-history', ['admin', 'supervisor', 'usuario']],
    ['usuarios.php',     'Usuarios',  'bi-people',        ['admin']],
    ['configuracion.php', 'Pantalla', 'bi-tv',            ['admin']],
];

function nav_visible(?array $u): array
{
    if (!$u) {
        return [];
    }
    return array_values(array_filter(NAV_ITEMS, fn($i) => in_array($u['rol'], $i[3], true)));
}

function layout_inicio(string $titulo, string $activo = '', ?array $u = null): void
{
    $empresa = 'TK Imperio';
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?> · <?= e($empresa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand navbar-dark bg-dark tk-topbar d-none d-md-flex">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= e(url('bandeja.php')) ?>"><?= e($empresa) ?></a>
        <ul class="navbar-nav me-auto">
            <?php foreach (nav_visible($u) as [$href, $label, $icon]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activo === $href ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
                        <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($u): ?>
            <form method="post" action="<?= e(url('logout.php')) ?>" class="d-flex align-items-center gap-2 text-white">
                <?= csrf_campo() ?>
                <span class="small"><i class="bi bi-person-circle"></i> <?= e($u['nombre']) ?> · <?= e($u['rol']) ?></span>
                <button class="btn btn-sm btn-outline-light">Salir</button>
            </form>
        <?php endif; ?>
    </div>
</nav>
<header class="tk-mobilebar d-md-none bg-dark text-white px-3 py-2 d-flex justify-content-between align-items-center">
    <strong><?= e($empresa) ?></strong>
    <?php if ($u): ?>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
            <?= csrf_campo() ?>
            <button class="btn btn-sm btn-outline-light">Salir</button>
        </form>
    <?php endif; ?>
</header>

<main class="container-fluid py-3 tk-main">
<?php
    flash_mostrar();
}

function layout_fin(string $activo = '', ?array $u = null): void
{
    ?>
</main>

<nav class="tk-bottombar d-md-none bg-dark">
    <?php foreach (nav_visible($u) as [$href, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $activo === $href ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i>
            <span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
}

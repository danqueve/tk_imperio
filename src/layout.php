<?php
declare(strict_types=1);

const NAV_ITEMS = [
    ['bandeja.php',      'Bandeja',  'bi-inbox'],
    ['ticket_nuevo.php', 'Nuevo',    'bi-plus-circle'],
    ['historial.php',    'Historial', 'bi-clock-history'],
    ['usuarios.php',     'Usuarios', 'bi-people'],
];

function layout_inicio(string $titulo, string $activo = ''): void
{
    $empresa = 'TK Imperio';
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?> · <?= e($empresa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand navbar-dark bg-dark tk-topbar d-none d-md-flex">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= e(url('bandeja.php')) ?>"><?= e($empresa) ?></a>
        <ul class="navbar-nav me-auto">
            <?php foreach (NAV_ITEMS as [$href, $label, $icon]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activo === $href ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
                        <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
<header class="tk-mobilebar d-md-none bg-dark text-white px-3 py-2 fw-bold"><?= e($empresa) ?></header>

<main class="container-fluid py-3 tk-main">
<?php
}

function layout_fin(string $activo = ''): void
{
    ?>
</main>

<nav class="tk-bottombar d-md-none bg-dark">
    <?php foreach (NAV_ITEMS as [$href, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $activo === $href ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i>
            <span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

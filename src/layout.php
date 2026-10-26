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

const ROL_ETIQUETA = [
    'admin'      => 'Administrador',
    'supervisor' => 'Supervisor',
    'usuario'    => 'Usuario',
];

function nav_visible(?array $u): array
{
    if (!$u) {
        return [];
    }
    return array_values(array_filter(NAV_ITEMS, fn($i) => in_array($u['rol'], $i[3], true)));
}

/** <head> compartido por todas las páginas (también el ingreso). */
function layout_head(string $titulo): void
{
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F5F4FA">
    <title><?= e($titulo) ?> · TK Imperio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?= e(url('assets/css/app.css')) ?>?v=2" rel="stylesheet">
    <?php
}

function layout_inicio(string $titulo, string $activo = '', ?array $u = null): void
{
    $empresa = 'TK Imperio';
    ?>
<!doctype html>
<html lang="es">
<head>
    <?php layout_head($titulo); ?>
</head>
<body>
<header class="tk-topbar d-none d-md-block">
    <div class="tk-topbar-in">
        <a class="tk-brand" href="<?= e(url('bandeja.php')) ?>">
            <span class="tk-logo" aria-hidden="true">TK</span><?= e($empresa) ?>
        </a>
        <nav aria-label="Principal">
            <ul class="tk-nav">
                <?php foreach (nav_visible($u) as [$href, $label, $icon]): ?>
                    <li>
                        <a class="<?= $activo === $href ? 'active' : '' ?>" href="<?= e(url($href)) ?>"
                           <?= $activo === $href ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php if ($u): ?>
            <form method="post" action="<?= e(url('logout.php')) ?>" class="tk-user ms-auto">
                <?= csrf_campo() ?>
                <span class="tk-avatar" style="--c:<?= color_seguro($u['color'] ?? null, '#8B7CF6') ?>" aria-hidden="true"><?= e(iniciales($u['nombre'])) ?></span>
                <span class="tk-user-txt">
                    <strong><?= e($u['nombre']) ?></strong>
                    <span><?= e(ROL_ETIQUETA[$u['rol']] ?? $u['rol']) ?></span>
                </span>
                <button class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Salir</button>
            </form>
        <?php endif; ?>
    </div>
</header>
<header class="tk-mobilebar d-md-none">
    <a class="tk-brand" href="<?= e(url('bandeja.php')) ?>">
        <span class="tk-logo" aria-hidden="true">TK</span><?= e($empresa) ?>
    </a>
    <?php if ($u): ?>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0 d-flex align-items-center gap-2">
            <?= csrf_campo() ?>
            <span class="tk-avatar" style="--c:<?= color_seguro($u['color'] ?? null, '#8B7CF6') ?>" title="<?= e($u['nombre']) ?>" aria-hidden="true"><?= e(iniciales($u['nombre'])) ?></span>
            <button class="btn btn-sm btn-outline-secondary" aria-label="Salir"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button>
        </form>
    <?php endif; ?>
</header>

<main class="tk-main">
<?php
    flash_mostrar();
}

function layout_fin(string $activo = '', ?array $u = null): void
{
    ?>
</main>

<nav class="tk-bottombar d-md-none" aria-label="Principal">
    <?php foreach (nav_visible($u) as [$href, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $activo === $href ? 'active' : '' ?>" <?= $activo === $href ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= $icon ?>" aria-hidden="true"></i>
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

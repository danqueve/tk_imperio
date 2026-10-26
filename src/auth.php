<?php
declare(strict_types=1);

const MAX_INTENTOS = 5;
const BLOQUEO_MIN  = 15;

function sesion_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('tk_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (bool) cfg('app', 'cookie_secure'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    sesion_iniciar();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    sesion_iniciar();
    $enviado = (string) ($_POST['csrf'] ?? '');
    if ($enviado === '' || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(403);
        exit('Sesión vencida o formulario inválido. Volvé atrás y reintentá.');
    }
}

/* ---------- Usuario actual y roles ---------- */

function usuario_actual(): ?array
{
    sesion_iniciar();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        return null;
    }
    static $cache = null;
    if ($cache && $cache['id'] === $id) {
        return $cache;
    }
    $st = db()->prepare('SELECT id, nombre, usuario, rol, origen, color FROM tk_usuarios WHERE id = ? AND activo = 1');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) {
        unset($_SESSION['uid']);
        return null;
    }
    return $cache = $u;
}

function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        header('Location: ' . url('index.php'));
        exit;
    }
    return $u;
}

/** @param string ...$roles roles permitidos */
function requiere_rol(string ...$roles): array
{
    $u = requiere_login();
    if (!in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        exit('No tenés permiso para ver esta pantalla.');
    }
    return $u;
}

function es_supervisor(array $u): bool
{
    return in_array($u['rol'], ['admin', 'supervisor'], true);
}

/* ---------- Bloqueo por intentos fallidos ---------- */

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function login_bloqueado(string $usuario): bool
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM tk_login_intentos
         WHERE (usuario = ? OR ip = ?) AND creado_en > (NOW() - INTERVAL ' . BLOQUEO_MIN . ' MINUTE)'
    );
    $st->execute([$usuario, ip_cliente()]);
    return (int) $st->fetchColumn() >= MAX_INTENTOS;
}

function login_registrar_fallo(string $usuario): void
{
    db()->exec('DELETE FROM tk_login_intentos WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
    $usuario = mb_substr($usuario, 0, 60);
    db()->prepare('INSERT INTO tk_login_intentos (usuario, ip) VALUES (?, ?)')
        ->execute([$usuario, ip_cliente()]);
}

function login_limpiar_fallos(string $usuario): void
{
    db()->prepare('DELETE FROM tk_login_intentos WHERE usuario = ? OR ip = ?')
        ->execute([$usuario, ip_cliente()]);
}

/* ---------- Login ---------- */

/** Devuelve el id de tk_usuarios si las credenciales son válidas, o null. */
function login_validar(string $usuario, string $password): ?int
{
    $st = db()->prepare('SELECT * FROM tk_usuarios WHERE usuario = ?');
    $st->execute([$usuario]);
    $local = $st->fetch();

    if ($local && $local['origen'] === 'local') {
        if ($local['activo'] && $local['password_hash'] !== ''
            && password_verify($password, $local['password_hash'])) {
            return (int) $local['id'];
        }
        return null;
    }

    return login_sas($usuario, $password, $local ?: null);
}

function login_sas(string $usuario, string $password, ?array $local): ?int
{
    try {
        $st = sas()->prepare(
            "SELECT id, nombre, apellido, password_hash FROM ic_usuarios
             WHERE usuario = ? AND rol = 'admin' AND activo = 1 LIMIT 1"
        );
        $st->execute([$usuario]);
        $s = $st->fetch();
    } catch (Throwable $ex) {
        return null;
    }

    if (!$s || !password_verify($password, (string) $s['password_hash'])) {
        // Si dejó de ser admin en SAS, pierde el acceso acá.
        if ($local && !$s) {
            db()->prepare('UPDATE tk_usuarios SET activo = 0 WHERE id = ?')->execute([$local['id']]);
        }
        return null;
    }

    $nombre = trim($s['nombre'] . ' ' . $s['apellido']);

    if ($local) {
        db()->prepare("UPDATE tk_usuarios SET nombre = ?, rol = 'admin', activo = 1, sas_usuario_id = ? WHERE id = ?")
            ->execute([$nombre, $s['id'], $local['id']]);
        return (int) $local['id'];
    }

    db()->prepare(
        "INSERT INTO tk_usuarios (nombre, usuario, password_hash, origen, sas_usuario_id, rol)
         VALUES (?, ?, '', 'sas', ?, 'admin')"
    )->execute([$nombre, $usuario, $s['id']]);
    return (int) db()->lastInsertId();
}

function login_iniciar_sesion(int $uid): void
{
    sesion_iniciar();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    db()->prepare('UPDATE tk_usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$uid]);
}

function logout(): void
{
    sesion_iniciar();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Flash ---------- */

function flash(string $tipo, string $msg): void
{
    sesion_iniciar();
    $_SESSION['flash'][] = [$tipo, $msg];
}

function flash_mostrar(): void
{
    sesion_iniciar();
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) {
        echo '<div class="alert alert-' . e($tipo) . ' py-2">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

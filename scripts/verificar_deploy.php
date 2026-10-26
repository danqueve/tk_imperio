<?php
declare(strict_types=1);
// Uso (CLI): php scripts/verificar_deploy.php
// Revisa que el entorno esté listo para producción. Sale con código 1 si hay algún error.
if (PHP_SAPI !== 'cli') {
    exit('Solo por consola.');
}

$errores = 0;
$avisos = 0;
function ok(string $m): void { echo "  [OK]    $m\n"; }
function aviso(string $m): void { global $avisos; $avisos++; echo "  [AVISO] $m\n"; }
function error(string $m): void { global $errores; $errores++; echo "  [ERROR] $m\n"; }

echo "Entorno\n";
version_compare(PHP_VERSION, '8.1.0', '>=') ? ok('PHP ' . PHP_VERSION) : error('Se necesita PHP 8.1 o superior.');
foreach (['pdo_mysql', 'fileinfo', 'mbstring'] as $ext) {
    extension_loaded($ext) ? ok("extensión $ext") : error("falta la extensión $ext");
}
echo '  upload_max_filesize=' . ini_get('upload_max_filesize') . ' post_max_size=' . ini_get('post_max_size') . "\n";
if ((int) ini_get('post_max_size') < 8) {
    aviso('post_max_size menor a 8M: 3 fotos de celular reducidas entran, pero conviene subirlo.');
}

echo "Configuración\n";
$raiz = dirname(__DIR__);
if (!is_file($raiz . '/config/config.local.php')) {
    error('Falta config/config.local.php.');
    exit(1);
}
require $raiz . '/src/bootstrap.php';
ok('config.local.php presente');

$app = cfg('app');
!empty($app['cookie_secure']) ? ok('cookie_secure activado') : error("cookie_secure debe ser true en producción (HTTPS).");
empty($app['debug']) ? ok('debug apagado') : error("debug debe ser false en producción.");
($app['base_url'] ?? '') === '' ? ok("base_url vacío (subdominio en la raíz)") : aviso("base_url = '{$app['base_url']}': en el subdominio debería ser ''.");

echo "Carpeta uploads\n";
$up = $raiz . '/uploads';
is_dir($up) && is_writable($up) ? ok('uploads/ existe y es escribible') : error('uploads/ no existe o no es escribible.');
is_file($up . '/.htaccess') ? ok('uploads/.htaccess presente (Deny all)') : error('Falta uploads/.htaccess.');

echo "Base TK\n";
try {
    $n = (int) db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'tk\\_%'")->fetchColumn();
    $n >= 6 ? ok("$n tablas tk_*") : error("Solo $n tablas tk_*: importar sql/schema.sql.");
    $dev = db()->query("SELECT usuario FROM tk_usuarios WHERE usuario IN ('admin_dev','ana','beto','carla')")->fetchAll(PDO::FETCH_COLUMN);
    $dev ? aviso('Usuarios de prueba presentes: ' . implode(', ', $dev)) : ok('sin usuarios de prueba');
} catch (Throwable $ex) {
    error('No conecta a la base TK: ' . $ex->getMessage());
}

echo "SAS Imperio (solo lectura)\n";
try {
    $sas = sas();
    $sas->query('SELECT 1 FROM ic_usuarios LIMIT 1');
    $sas->query('SELECT 1 FROM ic_clientes LIMIT 1');
    ok('SELECT sobre ic_usuarios e ic_clientes');
    $grants = implode(' ', $sas->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN));
    if (preg_match('/\b(ALL PRIVILEGES|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE)\b/i', $grants)) {
        error('El usuario de SAS tiene permisos de escritura. Debe tener solo SELECT sobre ic_usuarios e ic_clientes.');
    } else {
        ok('el usuario de SAS no tiene permisos de escritura');
    }
} catch (Throwable $ex) {
    error('Conexión a SAS: ' . $ex->getMessage());
}

echo "\n" . ($errores ? "$errores error(es), $avisos aviso(s).\n" : "Todo en orden ($avisos aviso(s)).\n");
exit($errores ? 1 : 0);

<?php
declare(strict_types=1);
// Uso (CLI): php scripts/crear_admin_local.php <usuario> <contraseña> "<Nombre>"
// Crea un admin LOCAL de emergencia (útil si SAS no está disponible). Los admin normales entran con su cuenta de SAS.
if (PHP_SAPI !== 'cli') {
    exit('Solo por consola.');
}
require __DIR__ . '/../src/bootstrap.php';

[$_, $usuario, $pass, $nombre] = $argv + [null, null, null, 'Administrador'];
if (!$usuario || !$pass || strlen($pass) < 8) {
    fwrite(STDERR, "Uso: php scripts/crear_admin_local.php <usuario> <contraseña(8+)> \"<Nombre>\"\n");
    exit(1);
}

$st = db()->prepare('SELECT id FROM tk_usuarios WHERE usuario = ?');
$st->execute([$usuario]);
if ($st->fetchColumn()) {
    fwrite(STDERR, "Ya existe el usuario '$usuario'.\n");
    exit(1);
}

db()->prepare("INSERT INTO tk_usuarios (nombre, usuario, password_hash, origen, rol) VALUES (?, ?, ?, 'local', 'admin')")
    ->execute([$nombre, $usuario, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Admin local '$usuario' creado.\n";

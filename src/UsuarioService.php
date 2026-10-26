<?php
declare(strict_types=1);

final class UsuarioService
{
    private const ROLES_LOCALES = ['usuario', 'supervisor'];

    public function __construct(private PDO $db) {}

    public function listar(): array
    {
        return $this->db->query(
            'SELECT id, nombre, usuario, origen, rol, color, activo, ultimo_login
             FROM tk_usuarios ORDER BY activo DESC, nombre'
        )->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM tk_usuarios WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function crear(array $d): int
    {
        $nombre  = trim((string) ($d['nombre'] ?? ''));
        $usuario = trim((string) ($d['usuario'] ?? ''));
        $rol     = (string) ($d['rol'] ?? 'usuario');
        $pass    = (string) ($d['password'] ?? '');

        if ($nombre === '' || $usuario === '') {
            throw new DomainException('Nombre y usuario son obligatorios.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
            throw new DomainException('El usuario admite letras, números, punto, guion y guion bajo (3 a 60).');
        }
        if (!in_array($rol, self::ROLES_LOCALES, true)) {
            throw new DomainException('Rol inválido. Los administradores se toman de SAS Imperio.');
        }
        $this->validarPassword($pass);

        $st = $this->db->prepare('SELECT 1 FROM tk_usuarios WHERE usuario = ?');
        $st->execute([$usuario]);
        if ($st->fetchColumn()) {
            throw new DomainException('Ya existe un usuario con ese nombre de usuario.');
        }

        $this->db->prepare(
            "INSERT INTO tk_usuarios (nombre, usuario, password_hash, origen, rol, color)
             VALUES (?, ?, ?, 'local', ?, ?)"
        )->execute([$nombre, $usuario, password_hash($pass, PASSWORD_DEFAULT), $rol, $this->color($d)]);

        return (int) $this->db->lastInsertId();
    }

    public function editar(int $id, array $d, int $idActor): void
    {
        $u = $this->obtener($id) ?? throw new DomainException('El usuario no existe.');
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $activo = !empty($d['activo']) ? 1 : 0;
        if ($nombre === '') {
            throw new DomainException('El nombre es obligatorio.');
        }
        if ($id === $idActor && !$activo) {
            throw new DomainException('No podés desactivarte a vos mismo.');
        }

        $rol = $u['rol'];
        if ($u['origen'] === 'local' && $u['rol'] !== 'admin') {
            $rol = (string) ($d['rol'] ?? $u['rol']);
            if (!in_array($rol, self::ROLES_LOCALES, true)) {
                throw new DomainException('Rol inválido.');
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE tk_usuarios SET nombre = ?, rol = ?, color = ?, activo = ? WHERE id = ?')
                ->execute([$nombre, $rol, $this->color($d), $activo, $id]);

            if ($u['activo'] && !$activo) {
                // Los tickets abiertos quedan sin asignar; los cerrados conservan su historial.
                $this->db->prepare(
                    "UPDATE tk_tickets SET asignado_a = NULL, estado = 'pendiente'
                     WHERE asignado_a = ? AND estado IN ('pendiente','en_proceso')"
                )->execute([$id]);
            }
            $this->db->commit();
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
    }

    public function resetPassword(int $id, string $pass): void
    {
        $u = $this->obtener($id) ?? throw new DomainException('El usuario no existe.');
        if ($u['origen'] !== 'local') {
            throw new DomainException('La contraseña de un admin de SAS se cambia en SAS Imperio.');
        }
        $this->validarPassword($pass);
        $this->db->prepare('UPDATE tk_usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
    }

    private function validarPassword(string $pass): void
    {
        if (strlen($pass) < 8) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }
    }

    private function color(array $d): string
    {
        $c = (string) ($d['color'] ?? '');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#8B7CF6';
    }
}

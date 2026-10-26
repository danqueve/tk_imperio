<?php
declare(strict_types=1);

final class PantallaService
{
    public function __construct(private PDO $db) {}

    public function config(string $clave, string $defecto = ''): string
    {
        $st = $this->db->prepare('SELECT valor FROM tk_config WHERE clave = ?');
        $st->execute([$clave]);
        $v = $st->fetchColumn();
        return $v === false ? $defecto : (string) $v;
    }

    public function guardarConfig(string $clave, string $valor): void
    {
        $this->db->prepare(
            'INSERT INTO tk_config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute([$clave, $valor]);
    }

    /** Token secreto de la pantalla; se crea la primera vez que se pide. */
    public function token(): string
    {
        $t = $this->config('pantalla_token');
        if ($t === '') {
            $t = $this->regenerarToken();
        }
        return $t;
    }

    public function regenerarToken(): string
    {
        $t = bin2hex(random_bytes(24));
        $this->guardarConfig('pantalla_token', $t);
        return $t;
    }

    public function tokenValido(string $enviado): bool
    {
        $t = $this->config('pantalla_token');
        return $t !== '' && $enviado !== '' && hash_equals($t, $enviado);
    }

    public function intervalo(): int
    {
        return max(60, min(3600, (int) $this->config('pantalla_intervalo', '300')));
    }

    /**
     * Una columna por usuario con algo abierto, "Sin asignar" primero.
     * Dentro de cada columna: del más antiguo al más nuevo.
     */
    public function datos(): array
    {
        $filas = $this->db->query(
            "SELECT t.id, t.tipo, t.dni, t.cliente, t.titulo, t.estado, t.prioridad, t.asignado_a,
                    UNIX_TIMESTAMP(t.creado_en) AS creado_ts,
                    UNIX_TIMESTAMP(t.compromiso_fecha) AS compromiso_ts,
                    t.compromiso_detalle,
                    u.nombre AS u_nombre, u.color AS u_color,
                    (SELECT COUNT(*) FROM tk_ticket_imagenes i WHERE i.ticket_id = t.id) AS imagenes
             FROM tk_tickets t LEFT JOIN tk_usuarios u ON u.id = t.asignado_a
             WHERE t.estado IN ('pendiente','en_proceso')
             ORDER BY t.creado_en ASC, t.id ASC"
        )->fetchAll();

        $cols = [];
        foreach ($filas as $r) {
            $k = $r['asignado_a'] === null ? 0 : (int) $r['asignado_a'];
            $cols[$k] ??= [
                'id'      => $k,
                'nombre'  => $k === 0 ? 'Sin asignar' : $r['u_nombre'],
                'color'   => $k === 0 ? '#9C98B5' : $r['u_color'],
                'tickets' => [],
            ];
            $cols[$k]['tickets'][] = [
                'id'        => (int) $r['id'],
                'tipo'      => $r['tipo'],
                'titulo'    => $r['tipo'] === 'tarea' ? $r['titulo'] : $r['cliente'],
                'dni'       => $r['dni'],
                'estado'    => $r['estado'],
                'alta'      => $r['prioridad'] === 'alta',
                'creado'    => (int) $r['creado_ts'],
                'compromiso' => $r['compromiso_ts'] !== null ? (int) $r['compromiso_ts'] : null,
                'detalle'   => $r['compromiso_detalle'],
                'imagenes'  => (int) $r['imagenes'],
            ];
        }

        ksort($cols);
        return array_values($cols);
    }
}

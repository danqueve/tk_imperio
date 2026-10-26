<?php
declare(strict_types=1);

final class TicketService
{
    public function __construct(private PDO $db, private ImagenService $imagenes) {}

    /** Usuarios activos a los que el actor puede asignar. Un usuario común solo se tiene a sí mismo. */
    public function asignables(array $actor): array
    {
        if (!es_supervisor($actor)) {
            return [['id' => $actor['id'], 'nombre' => $actor['nombre']]];
        }
        return $this->db->query('SELECT id, nombre FROM tk_usuarios WHERE activo = 1 ORDER BY nombre')->fetchAll();
    }

    public function crear(array $d, array $files, array $actor): int
    {
        $tipo = ($d['tipo'] ?? 'ticket') === 'tarea' ? 'tarea' : 'ticket';
        $prioridad = ($d['prioridad'] ?? 'normal') === 'alta' ? 'alta' : 'normal';

        $asignado = ($d['asignado_a'] ?? '') === '' ? null : (int) $d['asignado_a'];
        if ($asignado !== null) {
            if (!es_supervisor($actor) && $asignado !== (int) $actor['id']) {
                throw new DomainException('Solo podés asignarte a vos mismo o dejarlo sin asignar.');
            }
            $st = $this->db->prepare('SELECT 1 FROM tk_usuarios WHERE id = ? AND activo = 1');
            $st->execute([$asignado]);
            if (!$st->fetchColumn()) {
                throw new DomainException('El usuario asignado no existe o está inactivo.');
            }
        }

        $dni = $cliente = $sasId = $titulo = $fechaTarea = $compFecha = $compDetalle = null;
        $problematica = trim((string) ($d['problematica'] ?? ''));

        if ($tipo === 'ticket') {
            $dni = SasService::normalizarDni((string) ($d['dni'] ?? ''));
            $cliente = trim((string) ($d['cliente'] ?? ''));
            if ($dni === '' || $cliente === '') {
                throw new DomainException('El DNI y el nombre del cliente son obligatorios.');
            }
            if ($problematica === '') {
                throw new DomainException('Describí la problemática.');
            }
            $sasId = ($d['sas_cliente_id'] ?? '') !== '' ? (int) $d['sas_cliente_id'] : null;
        } else {
            $titulo = trim((string) ($d['titulo'] ?? ''));
            if ($titulo === '') {
                throw new DomainException('El título de la tarea es obligatorio.');
            }
            $fechaTarea = $this->fecha((string) ($d['fecha_tarea'] ?? '')) ?? date('Y-m-d');
            $compFecha = $this->fechaHora((string) ($d['compromiso_fecha'] ?? ''));
            $compDetalle = trim((string) ($d['compromiso_detalle'] ?? '')) ?: null;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO tk_tickets (tipo, dni, cliente, sas_cliente_id, titulo, problematica, prioridad,
                    asignado_a, creado_por, fecha_tarea, compromiso_fecha, compromiso_detalle)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tipo, $dni, $cliente, $sasId, $titulo, $problematica ?: null, $prioridad,
                $asignado, $actor['id'], $fechaTarea, $compFecha, $compDetalle,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->evento($id, (int) $actor['id'], 'creado', null);
            $this->imagenes->guardar($id, $files);
            $this->db->commit();
            return $id;
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
    }

    public function evento(int $ticketId, int $usuarioId, string $accion, ?string $detalle): void
    {
        $this->db->prepare('INSERT INTO tk_ticket_eventos (ticket_id, usuario_id, accion, detalle) VALUES (?, ?, ?, ?)')
            ->execute([$ticketId, $usuarioId, $accion, $detalle]);
    }

    /** Un usuario común ve los tickets que tiene asignados o que cargó él. */
    public function puedeVer(array $ticket, array $actor): bool
    {
        return es_supervisor($actor)
            || (int) $ticket['asignado_a'] === (int) $actor['id']
            || (int) $ticket['creado_por'] === (int) $actor['id'];
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT t.*, ua.nombre AS asignado_nombre, uc.nombre AS creador_nombre, ur.nombre AS resolvio_nombre
             FROM tk_tickets t
             LEFT JOIN tk_usuarios ua ON ua.id = t.asignado_a
             JOIN tk_usuarios uc ON uc.id = t.creado_por
             LEFT JOIN tk_usuarios ur ON ur.id = t.resuelto_por
             WHERE t.id = ?'
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function eventos(int $ticketId): array
    {
        $st = $this->db->prepare(
            'SELECT e.*, u.nombre AS usuario_nombre FROM tk_ticket_eventos e
             JOIN tk_usuarios u ON u.id = e.usuario_id WHERE e.ticket_id = ? ORDER BY e.creado_en, e.id'
        );
        $st->execute([$ticketId]);
        return $st->fetchAll();
    }

    /**
     * Bandeja: abiertos (pendiente/en_proceso) salvo que se filtre otro estado.
     * Del más antiguo al más nuevo.
     */
    public function bandeja(array $actor, array $f): array
    {
        $where = [];
        $p = [];

        if (!es_supervisor($actor)) {
            $where[] = '(t.asignado_a = ? OR t.creado_por = ?)';
            array_push($p, $actor['id'], $actor['id']);
        } elseif (($f['usuario'] ?? '') === 'sin') {
            $where[] = 't.asignado_a IS NULL';
        } elseif (($f['usuario'] ?? '') !== '') {
            $where[] = 't.asignado_a = ?';
            $p[] = (int) $f['usuario'];
        }

        $estado = (string) ($f['estado'] ?? '');
        if (in_array($estado, ['pendiente', 'en_proceso'], true)) {
            $where[] = 't.estado = ?';
            $p[] = $estado;
        } else {
            $where[] = "t.estado IN ('pendiente','en_proceso')";
        }

        if (in_array($f['tipo'] ?? '', ['ticket', 'tarea'], true)) {
            $where[] = 't.tipo = ?';
            $p[] = $f['tipo'];
        }

        $st = $this->db->prepare(
            'SELECT t.*, ua.nombre AS asignado_nombre,
                    (SELECT COUNT(*) FROM tk_ticket_imagenes i WHERE i.ticket_id = t.id) AS n_imagenes
             FROM tk_tickets t LEFT JOIN tk_usuarios ua ON ua.id = t.asignado_a
             WHERE ' . implode(' AND ', $where) . ' ORDER BY t.creado_en ASC, t.id ASC LIMIT 500'
        );
        $st->execute($p);
        return $st->fetchAll();
    }

    /* ---------- Flujo de estados ---------- */

    private const ABIERTOS = ['pendiente', 'en_proceso'];

    /** Acciones que el actor puede hacer sobre el ticket en su estado actual. */
    public function acciones(array $t, array $actor): array
    {
        $a = [];
        $super = es_supervisor($actor);
        $suyo = (int) $t['asignado_a'] === (int) $actor['id'];
        if ($t['estado'] === 'pendiente' && ($super || $suyo)) {
            $a[] = 'tomar';
        }
        if ($t['estado'] === 'en_proceso' && ($super || $suyo)) {
            $a[] = 'resolver';
        }
        if ($super && in_array($t['estado'], self::ABIERTOS, true)) {
            $a[] = 'reasignar';
            $a[] = 'anular';
        }
        return $a;
    }

    public function tomar(int $id, array $actor): void
    {
        $this->transaccion($id, $actor, 'tomar', function (array $t) use ($actor) {
            $this->actualizar($id = (int) $t['id'], 'pendiente',
                "estado = 'en_proceso', tomado_en = NOW(), asignado_a = COALESCE(asignado_a, ?)", [$actor['id']]);
            $this->evento($id, (int) $actor['id'], 'tomado', null);
        });
    }

    public function resolver(int $id, string $nota, array $actor): void
    {
        $nota = trim($nota);
        if ($nota === '') {
            throw new DomainException('La nota de solución es obligatoria.');
        }
        $this->transaccion($id, $actor, 'resolver', function (array $t) use ($nota, $actor) {
            $id = (int) $t['id'];
            $this->actualizar($id, 'en_proceso',
                "estado = 'resuelto', resuelto_en = NOW(), resuelto_por = ?, nota_resolucion = ?", [$actor['id'], $nota]);
            $this->evento($id, (int) $actor['id'], 'resuelto', $nota);
        });
    }

    /** $nuevo = null deja el ticket sin asignar. */
    public function reasignar(int $id, ?int $nuevo, array $actor): void
    {
        $nombreNuevo = 'Sin asignar';
        if ($nuevo !== null) {
            $st = $this->db->prepare('SELECT nombre FROM tk_usuarios WHERE id = ? AND activo = 1');
            $st->execute([$nuevo]);
            $nombreNuevo = $st->fetchColumn() ?: throw new DomainException('El usuario no existe o está inactivo.');
        }
        $this->transaccion($id, $actor, 'reasignar', function (array $t) use ($nuevo, $nombreNuevo, $actor) {
            $id = (int) $t['id'];
            if ($t['estado'] === 'pendiente' && ($t['asignado_a'] === null ? null : (int) $t['asignado_a']) === $nuevo) {
                throw new DomainException('El ticket ya está asignado a esa persona.');
            }
            $antes = $t['asignado_a'] ? $this->nombreUsuario((int) $t['asignado_a']) : 'Sin asignar';
            $this->actualizar($id, $t['estado'], "estado = 'pendiente', tomado_en = NULL, asignado_a = ?", [$nuevo]);
            $this->evento($id, (int) $actor['id'], 'reasignado', $antes . ' → ' . $nombreNuevo);
        });
    }

    public function anular(int $id, string $motivo, array $actor): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new DomainException('Indicá el motivo de la anulación.');
        }
        $this->transaccion($id, $actor, 'anular', function (array $t) use ($motivo, $actor) {
            $id = (int) $t['id'];
            $this->actualizar($id, $t['estado'], "estado = 'anulado', resuelto_en = NOW()", []);
            $this->evento($id, (int) $actor['id'], 'anulado', $motivo);
        });
    }

    /** Bloquea la fila, verifica que el actor pueda hacer la acción y ejecuta el cambio atómicamente. */
    private function transaccion(int $id, array $actor, string $accion, callable $cambio): void
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT * FROM tk_tickets WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) {
                throw new DomainException('El ticket no existe.');
            }
            if (!in_array($accion, $this->acciones($t, $actor), true)) {
                throw new DomainException('No podés hacer esa acción sobre este ticket en su estado actual.');
            }
            $cambio($t);
            $this->db->commit();
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
    }

    /** UPDATE condicionado al estado esperado: evita pisar un cambio simultáneo. */
    private function actualizar(int $id, string $estadoEsperado, string $set, array $params): void
    {
        $st = $this->db->prepare("UPDATE tk_tickets SET $set WHERE id = ? AND estado = ?");
        $st->execute([...$params, $id, $estadoEsperado]);
        if ($st->rowCount() !== 1) {
            throw new DomainException('El ticket cambió mientras lo editabas. Recargá la página.');
        }
    }

    private function nombreUsuario(int $id): string
    {
        $st = $this->db->prepare('SELECT nombre FROM tk_usuarios WHERE id = ?');
        $st->execute([$id]);
        return (string) ($st->fetchColumn() ?: '—');
    }

    private function fecha(string $s): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d && $d->format('Y-m-d') === $s ? $s : null;
    }

    private function fechaHora(string $s): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $s);
        return $d ? $d->format('Y-m-d H:i:00') : null;
    }
}

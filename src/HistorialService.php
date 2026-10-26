<?php
declare(strict_types=1);

final class HistorialService
{
    public const POR_PAGINA = 50;
    private const MAX_CSV = 20000;

    public function __construct(private PDO $db) {}

    /** @return array{0:string,1:array} cláusula WHERE y parámetros */
    private function filtro(array $actor, array $f): array
    {
        $w = ["t.estado IN ('resuelto','anulado')"];
        $p = [];

        // Un usuario común ve solo lo suyo (asignado a él o cargado por él).
        if (!es_supervisor($actor)) {
            $w[] = '(t.asignado_a = ? OR t.creado_por = ?)';
            array_push($p, $actor['id'], $actor['id']);
        } elseif (($f['usuario'] ?? '') !== '') {
            $w[] = '(t.resuelto_por = ? OR t.asignado_a = ?)';
            array_push($p, (int) $f['usuario'], (int) $f['usuario']);
        }

        if (in_array($f['estado'] ?? '', ['resuelto', 'anulado'], true)) {
            $w[] = 't.estado = ?';
            $p[] = $f['estado'];
        }
        if (in_array($f['tipo'] ?? '', ['ticket', 'tarea'], true)) {
            $w[] = 't.tipo = ?';
            $p[] = $f['tipo'];
        }
        if ($this->esFecha($f['desde'] ?? '')) {
            $w[] = 't.resuelto_en >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        if ($this->esFecha($f['hasta'] ?? '')) {
            $w[] = 't.resuelto_en <= ?';
            $p[] = $f['hasta'] . ' 23:59:59';
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '\\%_') . '%';
            $dni = SasService::normalizarDni($q);
            if ($dni !== '') {
                $w[] = '(t.dni LIKE ? OR t.cliente LIKE ? OR t.titulo LIKE ?)';
                array_push($p, '%' . $dni . '%', $like, $like);
            } else {
                $w[] = '(t.cliente LIKE ? OR t.titulo LIKE ?)';
                array_push($p, $like, $like);
            }
        }
        return [implode(' AND ', $w), $p];
    }

    public function contar(array $actor, array $f): int
    {
        [$w, $p] = $this->filtro($actor, $f);
        $st = $this->db->prepare("SELECT COUNT(*) FROM tk_tickets t WHERE $w");
        $st->execute($p);
        return (int) $st->fetchColumn();
    }

    public function buscar(array $actor, array $f, int $pagina): array
    {
        [$w, $p] = $this->filtro($actor, $f);
        $off = max(0, $pagina - 1) * self::POR_PAGINA;
        $st = $this->db->prepare(
            $this->select() . " WHERE $w ORDER BY t.resuelto_en DESC, t.id DESC LIMIT " . self::POR_PAGINA . " OFFSET $off"
        );
        $st->execute($p);
        return $st->fetchAll();
    }

    /** Para CSV: mismo filtro, sin paginar (con tope de seguridad). */
    public function todos(array $actor, array $f): array
    {
        [$w, $p] = $this->filtro($actor, $f);
        $st = $this->db->prepare(
            $this->select() . " WHERE $w ORDER BY t.resuelto_en DESC, t.id DESC LIMIT " . self::MAX_CSV
        );
        $st->execute($p);
        return $st->fetchAll();
    }

    /** Tiempo promedio de resolución por usuario que resolvió (solo estado resuelto). */
    public function promedios(array $actor, array $f): array
    {
        $f['estado'] = 'resuelto';
        [$w, $p] = $this->filtro($actor, $f);
        $st = $this->db->prepare(
            "SELECT t.resuelto_por AS usuario_id, ur.nombre, COUNT(*) AS cantidad,
                    AVG(TIMESTAMPDIFF(MINUTE, t.creado_en, t.resuelto_en)) AS min_desde_carga,
                    AVG(CASE WHEN t.tomado_en IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, t.tomado_en, t.resuelto_en) END) AS min_desde_toma
             FROM tk_tickets t JOIN tk_usuarios ur ON ur.id = t.resuelto_por
             WHERE $w GROUP BY t.resuelto_por, ur.nombre ORDER BY cantidad DESC"
        );
        $st->execute($p);
        return $st->fetchAll();
    }

    private function select(): string
    {
        return "SELECT t.*, ua.nombre AS asignado_nombre, ur.nombre AS resolvio_nombre, uc.nombre AS creador_nombre,
                       TIMESTAMPDIFF(MINUTE, t.creado_en, t.resuelto_en) AS minutos
                FROM tk_tickets t
                LEFT JOIN tk_usuarios ua ON ua.id = t.asignado_a
                LEFT JOIN tk_usuarios ur ON ur.id = t.resuelto_por
                JOIN tk_usuarios uc ON uc.id = t.creado_por";
    }

    private function esFecha(string $s): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d && $d->format('Y-m-d') === $s;
    }
}

/** "2 h 15 min", "3 d 4 h", "45 min". */
function duracion(?float $min): string
{
    if ($min === null) {
        return '—';
    }
    $m = (int) round($min);
    if ($m < 60) {
        return $m . ' min';
    }
    if ($m < 1440) {
        return intdiv($m, 60) . ' h ' . ($m % 60) . ' min';
    }
    return intdiv($m, 1440) . ' d ' . intdiv($m % 1440, 60) . ' h';
}

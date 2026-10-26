<?php
declare(strict_types=1);

/** Consultas de SOLO LECTURA a SAS Imperio. */
final class SasService
{
    public function __construct(private PDO $sas) {}

    public static function normalizarDni(string $dni): string
    {
        return preg_replace('/\D+/', '', $dni) ?? '';
    }

    /** @return list<array{id:int,nombre:string,dni:string}> */
    public function buscarPorDni(string $dni): array
    {
        $dni = self::normalizarDni($dni);
        if (strlen($dni) < 6 || strlen($dni) > 12) {
            return [];
        }
        $st = $this->sas->prepare(
            "SELECT id, nombres, apellidos, dni FROM ic_clientes
             WHERE REPLACE(REPLACE(REPLACE(dni, '.', ''), ' ', ''), '-', '') = ?
             ORDER BY apellidos, nombres LIMIT 10"
        );
        $st->execute([$dni]);
        return array_map(fn($r) => [
            'id'     => (int) $r['id'],
            'nombre' => trim($r['apellidos'] . ', ' . $r['nombres'], ', '),
            'dni'    => $dni,
        ], $st->fetchAll());
    }
}

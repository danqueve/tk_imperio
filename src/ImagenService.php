<?php
declare(strict_types=1);

final class ImagenService
{
    public const MAX_IMAGENES = 3;
    public const MAX_BYTES    = 5 * 1024 * 1024;
    private const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private PDO $db, private string $dir) {}

    public function cantidad(int $ticketId): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM tk_ticket_imagenes WHERE ticket_id = ?');
        $st->execute([$ticketId]);
        return (int) $st->fetchColumn();
    }

    public function listar(int $ticketId): array
    {
        $st = $this->db->prepare('SELECT id, mime, tamano, orden FROM tk_ticket_imagenes WHERE ticket_id = ? ORDER BY orden');
        $st->execute([$ticketId]);
        return $st->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM tk_ticket_imagenes WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function rutaArchivo(array $img): string
    {
        return $this->dir . '/' . $img['archivo'];
    }

    /**
     * Valida y guarda las imágenes subidas en $_FILES['imagenes'].
     * Debe llamarse dentro de la transacción del ticket; si algo falla, borra lo ya movido y lanza DomainException.
     */
    public function guardar(int $ticketId, array $files): void
    {
        $subidas = $this->normalizar($files);
        if (!$subidas) {
            return;
        }
        $existentes = $this->cantidad($ticketId);
        if ($existentes + count($subidas) > self::MAX_IMAGENES) {
            throw new DomainException('Cada ticket admite hasta ' . self::MAX_IMAGENES . ' imágenes.');
        }

        $movidos = [];
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $orden = $existentes;
            foreach ($subidas as $f) {
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    throw new DomainException('No se pudo subir una de las imágenes (error ' . $f['error'] . ').');
                }
                if ($f['size'] > self::MAX_BYTES) {
                    throw new DomainException('Cada imagen puede pesar hasta 5 MB.');
                }
                $mime = (string) $finfo->file($f['tmp_name']);
                if (!isset(self::TIPOS[$mime]) || @getimagesize($f['tmp_name']) === false) {
                    throw new DomainException('Solo se aceptan imágenes JPG, PNG o WEBP.');
                }
                $nombre = bin2hex(random_bytes(16)) . '.' . self::TIPOS[$mime];
                if (!move_uploaded_file($f['tmp_name'], $this->dir . '/' . $nombre)) {
                    throw new DomainException('No se pudo guardar la imagen en el servidor.');
                }
                $movidos[] = $nombre;
                $orden++;
                $this->db->prepare(
                    'INSERT INTO tk_ticket_imagenes (ticket_id, archivo, mime, tamano, orden) VALUES (?, ?, ?, ?, ?)'
                )->execute([$ticketId, $nombre, $mime, $f['size'], $orden]);
            }
        } catch (Throwable $ex) {
            foreach ($movidos as $n) {
                @unlink($this->dir . '/' . $n);
            }
            throw $ex;
        }
    }

    /** Convierte la estructura de $_FILES de múltiples archivos en una lista, ignorando campos vacíos. */
    private function normalizar(array $files): array
    {
        $out = [];
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $out;
        }
        foreach ($files['name'] as $i => $_) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'tmp_name' => $files['tmp_name'][$i],
                'size'     => (int) $files['size'][$i],
                'error'    => (int) $files['error'][$i],
            ];
        }
        return $out;
    }
}

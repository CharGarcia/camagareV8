<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use PDO;
use PDOException;

/**
 * Evidencia de entrega (GPS + firma) de una Consignación en venta.
 * Idempotente por (id_empresa, uuid_cliente): un reenvío del celular por timeout
 * de red no crea una fila duplicada — devuelve la ya existente.
 */
class ConsignacionVentaEntregaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /**
     * @param array $data claves: id_empresa, id_consignacion, uuid_cliente, latitud,
     *   longitud, precision_m, firma_path, capturado_en, dispositivo_id, canal,
     *   observaciones, created_by
     * @return array{id:int, creada:bool} creada=false si ya existía (idempotencia).
     */
    public function crear(array $data): array
    {
        $existente = $this->buscarPorUuid((int) $data['id_empresa'], (string) $data['uuid_cliente']);
        if ($existente) {
            return ['id' => (int) $existente['id'], 'creada' => false];
        }

        $sql = "INSERT INTO consignaciones_ventas_entregas
                    (id_empresa, id_consignacion, uuid_cliente, latitud, longitud, precision_m,
                     firma_path, capturado_en, dispositivo_id, canal, observaciones, created_by, updated_by)
                VALUES
                    (:id_empresa, :id_consignacion, :uuid_cliente, :latitud, :longitud, :precision_m,
                     :firma_path, :capturado_en, :dispositivo_id, :canal, :observaciones, :created_by, :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        try {
            $st->execute([
                ':id_empresa'      => $data['id_empresa'],
                ':id_consignacion' => $data['id_consignacion'],
                ':uuid_cliente'    => $data['uuid_cliente'],
                ':latitud'         => $data['latitud'] ?? null,
                ':longitud'        => $data['longitud'] ?? null,
                ':precision_m'     => $data['precision_m'] ?? null,
                ':firma_path'      => $data['firma_path'] ?? null,
                ':capturado_en'    => $data['capturado_en'],
                ':dispositivo_id'  => $data['dispositivo_id'] ?? null,
                ':canal'           => $data['canal'] ?? 'movil',
                ':observaciones'   => $data['observaciones'] ?? null,
                ':created_by'      => $data['created_by'],
            ]);
            return ['id' => (int) $st->fetchColumn(), 'creada' => true];
        } catch (PDOException $e) {
            // 23505 = unique_violation: dos requests casi simultáneas con el mismo uuid
            // (reintento de red). Ganó la otra: devolver la que ya quedó guardada.
            if (($e->errorInfo[0] ?? '') === '23505') {
                $existente = $this->buscarPorUuid((int) $data['id_empresa'], (string) $data['uuid_cliente']);
                if ($existente) {
                    return ['id' => (int) $existente['id'], 'creada' => false];
                }
            }
            throw $e;
        }
    }

    public function buscarPorUuid(int $idEmpresa, string $uuid): ?array
    {
        $sql = "SELECT * FROM consignaciones_ventas_entregas
                WHERE id_empresa = :e AND uuid_cliente = :u AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':u' => $uuid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM consignaciones_ventas_entregas WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Evidencias de entrega registradas para una consignación (la más reciente primero). */
    public function getPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT e.*, u.nombre AS registrado_por
                FROM consignaciones_ventas_entregas e
                LEFT JOIN usuarios u ON u.id = e.created_by
                WHERE e.id_consignacion = :idc AND e.id_empresa = :e AND e.eliminado = false
                ORDER BY e.capturado_en DESC, e.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

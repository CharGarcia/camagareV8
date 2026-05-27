<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

class SriDescargaAutoLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function insertar(array $data): int
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_descarga_auto_log
                (id_empresa, fecha_desde, fecha_hasta, tipos_documento,
                 total_encontrados, total_nuevos, total_existentes,
                 total_ignorados, total_errores, estado, detalle_json,
                 duracion_seg, origen, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             RETURNING id"
        );
        $st->execute([
            $data['id_empresa'],
            $data['fecha_desde'],
            $data['fecha_hasta'],
            $data['tipos_documento']   ?? 'todos',
            $data['total_encontrados'] ?? 0,
            $data['total_nuevos']      ?? 0,
            $data['total_existentes']  ?? 0,
            $data['total_ignorados']   ?? 0,
            $data['total_errores']     ?? 0,
            $data['estado']            ?? 'completado',
            $data['detalle_json']      ?? null,
            $data['duracion_seg']      ?? null,
            $data['origen']            ?? 'cron',
            $data['created_by']        ?? 0,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT *, TO_CHAR(fecha_proceso, 'DD-MM-YYYY HH24:MI:SS') AS fecha_proceso_fmt
             FROM sri_descarga_auto_log
             WHERE id = ? AND id_empresa = ?"
        );
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function getHistorial(int $idEmpresa, int $limite = 20): array
    {
        $st = $this->db->prepare(
            "SELECT id, fecha_desde, fecha_hasta, tipos_documento,
                    total_encontrados, total_nuevos, total_existentes,
                    total_ignorados, total_errores, estado, duracion_seg, origen,
                    TO_CHAR(fecha_proceso, 'DD-MM-YYYY HH24:MI:SS') AS fecha_proceso
             FROM sri_descarga_auto_log
             WHERE id_empresa = ?
             ORDER BY fecha_proceso DESC
             LIMIT ?"
        );
        $st->execute([$idEmpresa, $limite]);
        return $st->fetchAll();
    }
}

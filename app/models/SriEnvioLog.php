<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;

class SriEnvioLog
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Inserta un registro de log SRI. */
    public function registrar(array $data): void
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_envio_log
                (id_empresa, tipo_comprobante, id_comprobante, clave_acceso, tipo_ambiente,
                 accion, estado_sri, mensaje, detalle_json, numero_autorizacion, fecha_autorizacion, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $data['id_empresa'],
            $data['tipo_comprobante']    ?? 'factura_venta',
            $data['id_comprobante'],
            $data['clave_acceso']        ?? null,
            $data['tipo_ambiente']       ?? '1',
            $data['accion'],
            $data['estado_sri']          ?? null,
            $data['mensaje']             ?? null,
            $data['detalle_json']        ?? null,
            $data['numero_autorizacion'] ?? null,
            $data['fecha_autorizacion']  ?? null,
            $data['created_by']          ?? 0,
        ]);

        // Invalidar la caché de contadores del navbar: cualquier acción SRI cambia
        // las "novedades" (y a veces los borradores). Nunca debe romper el registro.
        try {
            \App\Services\ContadoresNavbarService::invalidarPorTabla('sri_envio_log', (int) ($data['id_empresa'] ?? 0));
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /** Devuelve todos los logs de un comprobante, ordenados del más reciente al más antiguo. */
    public function getPorComprobante(string $tipo, int $idComprobante, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, id_empresa, tipo_comprobante, id_comprobante, clave_acceso, tipo_ambiente,
                    accion, estado_sri, mensaje, detalle_json, numero_autorizacion,
                    fecha_autorizacion, created_by,
                    TO_CHAR(created_at, 'DD-MM-YYYY HH24:MI:SS') AS created_at
             FROM sri_envio_log
             WHERE tipo_comprobante = ? AND id_comprobante = ? AND id_empresa = ?
             ORDER BY id DESC"
        );
        $st->execute([$tipo, $idComprobante, $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Devuelve un registro por ID validando la empresa. */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM sri_envio_log WHERE id = ? AND id_empresa = ?"
        );
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Elimina un log físicamente.
     * Solo se permite para tipo_ambiente = '1' (pruebas).
     * El controlador valida el ambiente antes de llamar a este método.
     */
    public function eliminar(int $id, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "DELETE FROM sri_envio_log WHERE id = ? AND id_empresa = ? AND tipo_ambiente = '1'"
        );
        $st->execute([$id, $idEmpresa]);
        return $st->rowCount() > 0;
    }
}

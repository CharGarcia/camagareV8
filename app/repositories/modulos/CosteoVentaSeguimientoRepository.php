<?php

declare(strict_types=1);

namespace App\repositories\modulos;

/**
 * Registra si un documento de venta (Factura/Recibo/Nota de Crédito) necesitaba y recibió
 * su bloque de Costo de Ventas/Inventario. Ver database/ventas_costeo_seguimiento.sql para
 * el porqué de esta tabla (reemplaza una heurística ciega a cuentas por Cliente/Producto/
 * Categoría/Marca en SincronizadorAsientosService).
 */
class CosteoVentaSeguimientoRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /**
     * Upsert del resultado real de costeo para un documento. Se llama SIEMPRE que
     * AsientoBuilderService termina de resolver la cascada de Costo/Inventario para un
     * documento real (nunca en modo preview, sin id_documento).
     */
    public function registrar(
        int $idEmpresa,
        string $tipoDocumento,
        int $idDocumento,
        bool $requiereCosto,
        bool $costoGenerado,
        float $montoCosto,
        ?string $motivoPendiente,
        ?int $idUsuario = null
    ): void {
        if ($idEmpresa <= 0 || $idDocumento <= 0) {
            return;
        }

        $sql = "INSERT INTO ventas_costeo_seguimiento
                    (id_empresa, tipo_documento, id_documento, requiere_costo, costo_generado,
                     monto_costo, motivo_pendiente, created_by, updated_by)
                VALUES
                    (:id_empresa, :tipo_documento, :id_documento, :requiere_costo, :costo_generado,
                     :monto_costo, :motivo_pendiente, :id_usuario, :id_usuario2)
                ON CONFLICT (id_empresa, tipo_documento, id_documento) DO UPDATE SET
                    requiere_costo   = EXCLUDED.requiere_costo,
                    costo_generado   = EXCLUDED.costo_generado,
                    monto_costo      = EXCLUDED.monto_costo,
                    motivo_pendiente = EXCLUDED.motivo_pendiente,
                    updated_by       = EXCLUDED.updated_by,
                    updated_at       = CURRENT_TIMESTAMP,
                    eliminado        = FALSE,
                    deleted_at       = NULL,
                    deleted_by       = NULL";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $idEmpresa,
            ':tipo_documento'   => $tipoDocumento,
            ':id_documento'     => $idDocumento,
            ':requiere_costo'   => $requiereCosto ? 'true' : 'false',
            ':costo_generado'   => $costoGenerado ? 'true' : 'false',
            ':monto_costo'      => round($montoCosto, 2),
            ':motivo_pendiente' => $motivoPendiente,
            ':id_usuario'       => $idUsuario,
            ':id_usuario2'      => $idUsuario,
        ]);
    }

    /**
     * Marca la fila de seguimiento como eliminada cuando se anula o se elimina el documento
     * dueño (factura/recibo/NC): evita que quede reportando "pendiente" un documento que ya
     * no está vigente. No falla si no existe fila (documento que nunca requirió costo).
     */
    public function eliminar(int $idEmpresa, string $tipoDocumento, int $idDocumento, ?int $idUsuario = null): void
    {
        if ($idEmpresa <= 0 || $idDocumento <= 0) {
            return;
        }

        $sql = "UPDATE ventas_costeo_seguimiento
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :id_usuario,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :id_usuario2
                WHERE id_empresa = :id_empresa AND tipo_documento = :tipo_documento
                  AND id_documento = :id_documento AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'     => $idEmpresa,
            ':tipo_documento' => $tipoDocumento,
            ':id_documento'   => $idDocumento,
            ':id_usuario'     => $idUsuario,
            ':id_usuario2'    => $idUsuario,
        ]);
    }

    /**
     * IDs de documentos que necesitaban costeo y no lo tienen todavía (para que
     * SincronizadorAsientosService sepa a quién reprocesar, sin adivinar cuentas).
     */
    public function getPendientes(int $idEmpresa, string $tipoDocumento): array
    {
        $sql = "SELECT id_documento
                FROM ventas_costeo_seguimiento
                WHERE id_empresa = :id_empresa AND tipo_documento = :tipo_documento
                  AND requiere_costo = TRUE AND costo_generado = FALSE AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':tipo_documento' => $tipoDocumento]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Cuenta pendientes por motivo, para mensajes de aviso precisos (reemplaza los
     * COUNT(*) heurísticos de verificarCosteoVentasPendiente()).
     */
    public function contarPendientesPorMotivo(int $idEmpresa, string $tipoDocumento): array
    {
        $sql = "SELECT COALESCE(motivo_pendiente, 'desconocido') AS motivo, COUNT(*) AS total
                FROM ventas_costeo_seguimiento
                WHERE id_empresa = :id_empresa AND tipo_documento = :tipo_documento
                  AND requiere_costo = TRUE AND costo_generado = FALSE AND eliminado = FALSE
                GROUP BY motivo_pendiente";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':tipo_documento' => $tipoDocumento]);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[$row['motivo']] = (int) $row['total'];
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Cronograma de devengo de suscripciones (`suscripciones_devengos`): la porción mensual del
 * ingreso de cada línea de servicio que se reconoce mes a mes (NIIF 15 / Sección 23).
 *
 * Tabla operativa: toda consulta filtra por id_empresa y eliminado = false.
 * SQL: database/migrations/20261004_suscripciones_devengo.sql.
 */
class SuscripcionDevengoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('suscripciones_devengos');
    }

    /** ¿Ya se aplicó el SQL del devengo? Mientras no, el cronograma no se genera ni se consulta. */
    public function disponible(): bool
    {
        return $this->tablaExiste('suscripciones_devengos');
    }

    /**
     * Líneas de un documento generado por la suscripción, con el tipo del producto
     * ('01' bien, '02' servicio). La base es el precio total sin impuestos (ya con descuento).
     *
     * @return array<int, array{id:int, id_producto:?int, descripcion:string, base:string, tipo_produccion:?string}>
     */
    public function getLineasDocumento(string $tipoDocumento, int $idDocumento, int $idEmpresa): array
    {
        if ($tipoDocumento === 'factura') {
            $sql = "SELECT d.id, d.id_producto, d.descripcion, d.precio_total_sin_impuesto AS base,
                           p.tipo_produccion
                    FROM ventas_detalle d
                    JOIN ventas_cabecera v ON v.id = d.id_venta
                    LEFT JOIN productos p ON p.id = d.id_producto
                    WHERE d.id_venta = :id AND v.id_empresa = :id_empresa
                    ORDER BY d.id";
        } elseif ($tipoDocumento === 'recibo') {
            $sql = "SELECT d.id, d.id_producto, d.descripcion, d.precio_total_sin_impuesto AS base,
                           p.tipo_produccion
                    FROM recibos_venta_detalle d
                    JOIN recibos_venta_cabecera r ON r.id = d.id_recibo
                    LEFT JOIN productos p ON p.id = d.id_producto
                    WHERE d.id_recibo = :id AND r.id_empresa = :id_empresa
                    ORDER BY d.id";
        } else {
            return [];
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Candado transaccional (CLAUDE.md §8): se libera solo al COMMIT/ROLLBACK. Llamarlo dentro
     * de la transacción y antes de leer lo que se va a escribir.
     */
    public function bloquear(string $clave): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext(:clave))")->execute([':clave' => $clave]);
    }

    /** ¿El documento ya tiene cronograma vivo? Evita duplicarlo si se reintenta la generación. */
    public function existeCronogramaDocumento(string $tipoDocumento, int $idDocumento, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND tipo_documento = :tipo AND id_documento = :id
               AND eliminado = false AND estado <> 'anulado'
             LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipoDocumento, ':id' => $idDocumento]);
        return (bool) $st->fetchColumn();
    }

    public function insertar(array $d): int
    {
        $st = $this->db->prepare(
            "INSERT INTO suscripciones_devengos
                (id_empresa, id_suscripcion, id_suscripcion_pago, tipo, tipo_documento, id_documento,
                 id_documento_detalle, id_producto, descripcion, periodo, monto, estado,
                 created_at, created_by, eliminado)
             VALUES
                (:id_empresa, :id_suscripcion, :id_suscripcion_pago, :tipo, :tipo_documento, :id_documento,
                 :id_documento_detalle, :id_producto, :descripcion, :periodo, :monto, :estado,
                 CURRENT_TIMESTAMP, :created_by, false)
             RETURNING id"
        );
        $st->execute([
            ':id_empresa'           => $d['id_empresa'],
            ':id_suscripcion'       => $d['id_suscripcion'],
            ':id_suscripcion_pago'  => $d['id_suscripcion_pago'] ?? null,
            ':tipo'                 => $d['tipo'],
            ':tipo_documento'       => $d['tipo_documento'] ?? null,
            ':id_documento'         => $d['id_documento'] ?? null,
            ':id_documento_detalle' => $d['id_documento_detalle'] ?? null,
            ':id_producto'          => $d['id_producto'] ?? null,
            ':descripcion'          => mb_substr((string) ($d['descripcion'] ?? ''), 0, 300),
            ':periodo'              => $d['periodo'],
            ':monto'                => $d['monto'],
            ':estado'               => $d['estado'] ?? 'pendiente',
            ':created_by'           => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Cronograma de una suscripción para la pestaña «Devengo»: una fila por mes y documento,
     * con el número del documento que la originó.
     */
    public function getPorSuscripcion(int $idSuscripcion, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT d.id, d.tipo, d.tipo_documento, d.id_documento, d.descripcion,
                    d.periodo, d.monto, d.estado, d.id_asiento,
                    CASE d.tipo_documento
                        WHEN 'factura' THEN v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial
                        WHEN 'recibo'  THEN r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial
                    END AS numero_documento,
                    ac.numero_comprobante AS numero_asiento
             FROM suscripciones_devengos d
             LEFT JOIN ventas_cabecera v
                    ON d.tipo_documento = 'factura' AND v.id = d.id_documento AND v.id_empresa = d.id_empresa
             LEFT JOIN recibos_venta_cabecera r
                    ON d.tipo_documento = 'recibo' AND r.id = d.id_documento AND r.id_empresa = d.id_empresa
             LEFT JOIN asientos_contables_cabecera ac
                    ON ac.id = d.id_asiento AND ac.id_empresa = d.id_empresa
             WHERE d.id_empresa = :id_empresa AND d.id_suscripcion = :id AND d.eliminado = false
             ORDER BY d.periodo, d.id"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id' => $idSuscripcion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

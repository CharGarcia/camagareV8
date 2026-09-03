<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CajaSesionRepository extends BaseRepository
{
    protected string $table = 'caja_sesiones';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAbiertaPorPuntoEmision(int $idEmpresa, int $idPuntoEmision): ?array
    {
        // El punto de emisión (código y establecimiento) viaja con el turno: el
        // salón puede tener varios y el mesero necesita ver por cuál trabaja.
        $sql = "SELECT cs.*, u.nombre AS cajero_nombre,
                       pe.codigo_punto, pe.id_establecimiento,
                       est.codigo AS codigo_establecimiento
                FROM {$this->table} cs
                LEFT JOIN usuarios u ON u.id = cs.id_usuario
                LEFT JOIN empresa_punto_emision pe ON pe.id = cs.id_punto_emision
                LEFT JOIN empresa_establecimiento est ON est.id = pe.id_establecimiento
                WHERE cs.id_empresa = :id_empresa
                  AND cs.id_punto_emision = :id_punto_emision
                  AND cs.estado = 'abierta'
                  AND cs.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_punto_emision' => $idPuntoEmision,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cualquier turno abierto de la empresa, sin importar el punto de emisión
     * — usado por el portal público QR (el cliente que escanea no elige punto
     * de emisión, solo necesita que el restaurante esté operando) y como último
     * recurso al cobrar comandas antiguas que quedaron sin turno.
     *
     * NO es la vía del salón: ahí cada usuario elige su punto de emisión al
     * abrir el turno en Cajas y la comanda se ata a ESE turno (ver
     * ComandasController::abrirAjax). Una empresa puede tener varios puntos
     * abiertos a la vez, así que aquí el orden es explícito —el turno abierto
     * más reciente— y no lo que devuelva el motor: sin ORDER BY, dos llamadas
     * seguidas podían resolver puntos de emisión distintos.
     */
    public function getAbiertaPorEmpresa(int $idEmpresa): ?array
    {
        $sql = "SELECT cs.*, u.nombre AS cajero_nombre,
                       pe.codigo_punto, pe.id_establecimiento,
                       est.codigo AS codigo_establecimiento
                FROM {$this->table} cs
                LEFT JOIN usuarios u ON u.id = cs.id_usuario
                LEFT JOIN empresa_punto_emision pe ON pe.id = cs.id_punto_emision
                LEFT JOIN empresa_establecimiento est ON est.id = pe.id_establecimiento
                WHERE cs.id_empresa = :id_empresa
                  AND cs.estado = 'abierta'
                  AND cs.eliminado = false
                ORDER BY cs.fecha_apertura DESC, cs.id DESC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ¿Ese turno existe, sigue abierto y es de esta empresa? Lo usa quien recibe
     * un id_caja_sesion de afuera (abrir comanda) antes de atarle un documento:
     * de ese turno sale el punto de emisión con el que se factura.
     */
    public function esAbiertaDeEmpresa(int $id, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id = :id
                  AND id_empresa = :id_empresa
                  AND estado = 'abierta'
                  AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_punto_emision, id_usuario, fondo_inicial,
                    estado, created_by, eliminado
                ) VALUES (
                    :id_empresa, :id_punto_emision, :id_usuario, :fondo_inicial,
                    'abierta', :created_by, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_punto_emision' => $data['id_punto_emision'],
            ':id_usuario' => $data['id_usuario'],
            ':fondo_inicial' => $data['fondo_inicial'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) $this->lastInsertId();
    }

    /**
     * Efectivo cobrado durante el turno (Facturas + Recibos del POS enlazados
     * a esta sesión, forma_pago = '01' = efectivo). Es la base del arqueo:
     * monto_esperado = fondo_inicial + este valor. Protegido: si la columna
     * id_caja_sesion aún no existe (migración pendiente), devuelve 0 en vez
     * de romper el cierre de caja.
     */
    public function getEfectivoCobradoEnTurno(int $idCajaSesion): float
    {
        try {
            $stF = $this->db->prepare(
                "SELECT COALESCE(SUM(vp.total), 0)
                 FROM ventas_pagos vp
                 JOIN ventas_cabecera v ON v.id = vp.id_venta
                 WHERE v.id_caja_sesion = :id AND v.eliminado = false
                   AND v.estado != 'anulado' AND vp.forma_pago = '01'"
            );
            $stF->execute([':id' => $idCajaSesion]);
            $totalFacturas = (float) $stF->fetchColumn();

            $stR = $this->db->prepare(
                "SELECT COALESCE(SUM(rp.total), 0)
                 FROM recibos_venta_pagos rp
                 JOIN recibos_venta_cabecera r ON r.id = rp.id_recibo
                 WHERE r.id_caja_sesion = :id AND r.eliminado = false
                   AND r.estado != 'anulado' AND rp.forma_pago = '01'"
            );
            $stR->execute([':id' => $idCajaSesion]);
            $totalRecibos = (float) $stR->fetchColumn();

            return round($totalFacturas + $totalRecibos, 2);
        } catch (\Throwable $e) {
            error_log('[CajaSesion] No se pudo calcular el efectivo cobrado del turno (¿migración id_caja_sesion pendiente?): ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Cobros del turno agrupados por forma de pago de la empresa (Efectivo, un
     * banco, Payphone…), que es lo que eligió quien cobró.
     *
     * El documento NO guarda esa forma —en `forma_pago` lleva el código SRI—,
     * así que se llega por el Ingreso que generó cada cobro. Va como
     * LEFT JOIN LATERAL ... LIMIT 1 y no como JOIN llano: un ingreso puede tener
     * varias filas de pago y un documento varios ingresos (cobros parciales), y
     * un JOIN duplicaría los importes del arqueo.
     *
     * Los documentos sin Ingreso registrado caen en "Sin forma de pago
     * registrada": no se pierden del total, pero se ven aparte porque son los
     * que hay que corregir en el módulo Ingresos.
     *
     * @return list<array{id_forma_pago:int, nombre:string, tipo:string, documentos:int, total:float}>
     */
    public function getCobrosPorFormaPagoEnTurno(int $idCajaSesion): array
    {
        $sql = "
            SELECT COALESCE(fp.id, 0)                                  AS id_forma_pago,
                   COALESCE(fp.nombre, 'Sin forma de pago registrada') AS nombre,
                   COALESCE(fp.tipo, '')                               AS tipo,
                   COUNT(*)                                            AS documentos,
                   COALESCE(SUM(t.total), 0)                           AS total
            FROM (
                SELECT v.id AS id_doc, 'FACTURA' AS tipo_doc, v.importe_total AS total
                  FROM ventas_cabecera v
                 WHERE v.id_caja_sesion = :id1 AND v.eliminado = false AND v.estado <> 'anulado'
                UNION ALL
                SELECT r.id, 'RECIBO', r.importe_total
                  FROM recibos_venta_cabecera r
                 WHERE r.id_caja_sesion = :id2 AND r.eliminado = false AND r.estado <> 'anulado'
            ) t
            LEFT JOIN LATERAL (
                SELECT ip.id_forma_cobro
                  FROM ingresos_detalle idet
                  JOIN ingresos_cabecera ic ON ic.id = idet.id_ingreso
                                           AND ic.eliminado = false
                                           AND ic.estado <> 'anulado'
                  JOIN ingresos_pagos ip ON ip.id_ingreso = ic.id
                 WHERE idet.id_referencia_documento = t.id_doc
                   AND idet.tipo_documento = t.tipo_doc
                 ORDER BY ic.id DESC, ip.id ASC
                 LIMIT 1
            ) f ON true
            LEFT JOIN empresa_formas_pago fp ON fp.id = f.id_forma_cobro
            GROUP BY COALESCE(fp.id, 0), COALESCE(fp.nombre, 'Sin forma de pago registrada'), COALESCE(fp.tipo, '')
            ORDER BY total DESC
        ";

        try {
            $st = $this->db->prepare($sql);
            $st->execute([':id1' => $idCajaSesion, ':id2' => $idCajaSesion]);
            return array_map(static fn(array $r): array => [
                'id_forma_pago' => (int) $r['id_forma_pago'],
                'nombre'        => (string) $r['nombre'],
                'tipo'          => (string) $r['tipo'],
                'documentos'    => (int) $r['documentos'],
                'total'         => round((float) $r['total'], 2),
            ], $st->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            // Mismo criterio defensivo que getEfectivoCobradoEnTurno(): si falta
            // alguna columna por una migración pendiente, el cierre sigue
            // funcionando sin el desglose en vez de romperse.
            error_log('[CajaSesion] No se pudo calcular el desglose por forma de pago del turno: ' . $e->getMessage());
            return [];
        }
    }

    public function cerrar(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    estado = 'cerrada',
                    fecha_cierre = CURRENT_TIMESTAMP,
                    monto_esperado = :monto_esperado,
                    monto_contado = :monto_contado,
                    diferencia = :diferencia,
                    observaciones_cierre = :observaciones_cierre,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false AND estado = 'abierta'";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':monto_esperado' => $data['monto_esperado'],
            ':monto_contado' => $data['monto_contado'],
            ':diferencia' => $data['diferencia'],
            ':observaciones_cierre' => $data['observaciones_cierre'],
            ':updated_by' => $data['updated_by'],
            ':id' => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }
}

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

    /** ¿suscripciones ya tiene modalidad_cobro/reconocimiento? (SQL 20261004_suscripciones_devengo.sql) */
    public function tieneColumnasSuscripcion(): bool
    {
        return $this->columnaExiste('suscripciones', 'reconocimiento');
    }

    /** ¿Ya se aplicó 20261004_suscripciones_devengo_apertura.sql (columna origen)? */
    public function tieneOrigen(): bool
    {
        return $this->columnaExiste('suscripciones_devengos', 'origen');
    }

    public function insertar(array $d): int
    {
        // Columnas de la apertura: solo cuando vienen y la columna existe.
        $extraCols = '';
        $extraVals = '';
        $extra     = [];
        if (isset($d['origen']) && $this->tieneOrigen()) {
            $extraCols .= ', origen, id_asiento_apertura';
            $extraVals .= ', :origen, :id_asiento_apertura';
            $extra[':origen'] = $d['origen'];
            $extra[':id_asiento_apertura'] = $d['id_asiento_apertura'] ?? null;
        }
        if (array_key_exists('id_cuenta_ingreso', $d)) {
            $extraCols .= ', id_cuenta_ingreso';
            $extraVals .= ', :id_cuenta_ingreso';
            $extra[':id_cuenta_ingreso'] = $d['id_cuenta_ingreso'];
        }
        $st = $this->db->prepare(
            "INSERT INTO suscripciones_devengos
                (id_empresa, id_suscripcion, id_suscripcion_pago, tipo, tipo_documento, id_documento,
                 id_documento_detalle, id_producto, descripcion, periodo, monto, estado, id_nota_credito,
                 created_at, created_by, eliminado{$extraCols})
             VALUES
                (:id_empresa, :id_suscripcion, :id_suscripcion_pago, :tipo, :tipo_documento, :id_documento,
                 :id_documento_detalle, :id_producto, :descripcion, :periodo, :monto, :estado, :id_nota_credito,
                 CURRENT_TIMESTAMP, :created_by, false{$extraVals})
             RETURNING id"
        );
        $st->execute($extra + [
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
            ':id_nota_credito'      => $d['id_nota_credito'] ?? null,
            ':created_by'           => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    /** ¿Hay una transacción abierta en la conexión? (para no confirmar la de quien llama). */
    public function enTransaccion(): bool
    {
        return $this->db->inTransaction();
    }

    // ── Paso 5: notas de crédito, anulación y edición del documento ─────────

    /**
     * Pago de suscripción que generó el documento, con la suscripción y su periodicidad.
     * Null si el documento no lo generó una suscripción.
     */
    public function getOrigenDocumento(string $tipoDocumento, int $idDocumento, int $idEmpresa): ?array
    {
        $col = $tipoDocumento === 'recibo' ? 'id_recibo' : 'id_factura';
        $st = $this->db->prepare(
            "SELECT sp.id AS id_pago, sp.servicio_desde,
                    s.id, s.id_cliente, s.modalidad_cobro, s.reconocimiento, s.fecha_inicio, s.fecha_fin,
                    per.meses AS periodicidad_meses, per.codigo AS periodicidad_codigo
             FROM suscripciones_pagos sp
             JOIN suscripciones s ON s.id = sp.id_suscripcion
             JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
             WHERE sp.{$col} = :id AND sp.id_empresa = :id_empresa AND sp.eliminado = false
             ORDER BY sp.id DESC
             LIMIT 1"
        );
        $st->execute([':id' => $idDocumento, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFechaEmisionDocumento(string $tipoDocumento, int $idDocumento, int $idEmpresa): ?string
    {
        $tabla = $tipoDocumento === 'recibo' ? 'recibos_venta_cabecera' : 'ventas_cabecera';
        $st = $this->db->prepare("SELECT fecha_emision FROM {$tabla} WHERE id = :id AND id_empresa = :id_empresa");
        $st->execute([':id' => $idDocumento, ':id_empresa' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? substr((string) $v, 0, 10) : null;
    }

    /** Filas vivas de un documento por tipo/estado, bloqueadas (FOR UPDATE). */
    public function getFilasDocumento(string $tipoDocumento, int $idDocumento, int $idEmpresa, string $tipo, array $estados): array
    {
        $ph = [];
        $params = [':id_empresa' => $idEmpresa, ':tipo_doc' => $tipoDocumento, ':id' => $idDocumento, ':tipo' => $tipo];
        foreach (array_values($estados) as $i => $e) {
            $ph[] = ":e{$i}";
            $params[":e{$i}"] = $e;
        }
        $st = $this->db->prepare(
            "SELECT * FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND tipo_documento = :tipo_doc AND id_documento = :id
               AND tipo = :tipo AND eliminado = false AND estado IN (" . implode(', ', $ph) . ")
             ORDER BY periodo, id
             FOR UPDATE"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cambia el estado de una fila (y, si se da, la NC que la anuló). */
    public function cambiarEstado(int $id, string $estado, int $idUsuario, ?int $idNotaCredito = null): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET estado = :estado, id_nota_credito = :nc, updated_at = CURRENT_TIMESTAMP, updated_by = :u
             WHERE id = :id"
        )->execute([':estado' => $estado, ':nc' => $idNotaCredito, ':u' => $idUsuario, ':id' => $id]);
    }

    public function cambiarMonto(int $id, float $monto, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos SET monto = :m, updated_at = CURRENT_TIMESTAMP, updated_by = :u WHERE id = :id"
        )->execute([':m' => round($monto, 2), ':u' => $idUsuario, ':id' => $id]);
    }

    public function darDeBaja(int $id, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u WHERE id = :id"
        )->execute([':u' => $idUsuario, ':id' => $id]);
    }

    /** Provisión cancelada por un documento que se anula/edita: vuelve a «por facturar». */
    public function desvincularProvision(int $id, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET estado = 'devengado', tipo_documento = NULL, id_documento = NULL, id_documento_detalle = NULL,
                 updated_at = CURRENT_TIMESTAMP, updated_by = :u
             WHERE id = :id AND tipo = 'provision' AND estado = 'facturado'"
        )->execute([':u' => $idUsuario, ':id' => $id]);
    }

    /**
     * Líneas de una nota de crédito que devuelven líneas de factura, agrupadas por la línea de
     * la factura (base sin impuestos).
     */
    public function getLineasNotaCredito(int $idNotaCredito, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT d.id_venta_detalle, SUM(d.precio_total_sin_impuesto) AS base
             FROM notas_credito_detalle d
             JOIN notas_credito_cabecera nc ON nc.id = d.id_nota_credito
             WHERE d.id_nota_credito = :id AND nc.id_empresa = :id_empresa AND d.id_venta_detalle IS NOT NULL
             GROUP BY d.id_venta_detalle"
        );
        $st->execute([':id' => $idNotaCredito, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Filas diferidas por devengar de una línea de factura, del último mes al primero (FOR UPDATE). */
    public function getPendientesLineaFactura(int $idVentaDetalle, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND tipo_documento = 'factura' AND id_documento_detalle = :det
               AND tipo = 'diferido' AND estado = 'pendiente' AND eliminado = false
             ORDER BY periodo DESC, id DESC
             FOR UPDATE"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':det' => $idVentaDetalle]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Filas que anuló una nota de crédito (FOR UPDATE). */
    public function getAnuladasPorNotaCredito(int $idNotaCredito, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND id_nota_credito = :nc AND estado = 'anulado' AND eliminado = false
             FOR UPDATE"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':nc' => $idNotaCredito]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** La fila por devengar de la misma línea y mes (para devolverle lo que una NC le quitó). */
    public function getPendienteLineaMes(string $tipoDocumento, int $idDetalle, string $periodo, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND tipo_documento = :tipo AND id_documento_detalle = :det
               AND periodo = :periodo AND tipo = 'diferido' AND estado = 'pendiente' AND eliminado = false
             LIMIT 1
             FOR UPDATE"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipoDocumento, ':det' => $idDetalle, ':periodo' => $periodo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Apertura (documentos emitidos antes de activar el devengado) ────────

    /**
     * Facturas/recibos generados por suscripciones que HOY reconocen durante el período (por
     * adelantado, periodicidad por meses), vivos, con su asiento ya registrado y SIN ninguna fila
     * de cronograma: su asiento acreditó todo al ingreso. Trae lo necesario para calcular su
     * período de servicio (servicio_desde, o la fecha de emisión en los anteriores al devengado).
     */
    public function getCandidatosApertura(int $idEmpresa): array
    {
        $rama = function (string $tipo, string $tabla, string $colPago): string {
            return "SELECT '{$tipo}' AS tipo_documento, doc.id AS id_documento, doc.fecha_emision,
                           doc.establecimiento || '-' || doc.punto_emision || '-' || doc.secuencial AS numero,
                           doc.id_cliente, c.nombre AS cliente,
                           sp.id AS id_pago, sp.servicio_desde,
                           s.id AS id_suscripcion, s.tipo_comprobante,
                           per.meses AS periodicidad_meses, per.codigo AS periodicidad_codigo
                    FROM suscripciones_pagos sp
                    JOIN suscripciones s ON s.id = sp.id_suscripcion AND s.id_empresa = sp.id_empresa
                    JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                    JOIN {$tabla} doc ON doc.id = sp.{$colPago} AND doc.id_empresa = sp.id_empresa
                    JOIN asientos_contables_cabecera ac ON ac.id = doc.id_asiento_contable AND ac.estado <> 'anulado'
                    LEFT JOIN clientes c ON c.id = doc.id_cliente
                    WHERE sp.id_empresa = :e_{$tipo} AND sp.eliminado = false AND s.eliminado = false
                      AND s.reconocimiento = 'diferido' AND s.modalidad_cobro = 'anticipado'
                      AND per.codigo NOT IN ('DIARIO', 'SEMANAL', 'QUINCENAL')
                      AND doc.eliminado = false AND doc.estado <> 'anulado'
                      AND NOT EXISTS (SELECT 1 FROM suscripciones_devengos d
                                      WHERE d.id_empresa = sp.id_empresa AND d.tipo_documento = '{$tipo}'
                                        AND d.id_documento = doc.id AND d.eliminado = false)";
        };
        $st = $this->db->prepare(
            $rama('factura', 'ventas_cabecera', 'id_factura') . ' UNION ALL ' .
            $rama('recibo', 'recibos_venta_cabecera', 'id_recibo') .
            ' ORDER BY fecha_emision, id_documento'
        );
        $st->execute([':e_factura' => $idEmpresa, ':e_recibo' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Asientos vivos de apertura de un mes (para avisar en la vista previa). */
    public function getAsientosApertura(int $idEmpresa, int $anioMes): array
    {
        $st = $this->db->prepare(
            "SELECT id, numero_comprobante, fecha_asiento, total_debe
             FROM asientos_contables_cabecera
             WHERE id_empresa = :id_empresa AND modulo_origen = 'suscripcion_devengo_apertura'
               AND id_referencia_origen = :ref AND estado <> 'anulado'
             ORDER BY id"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':ref' => $anioMes]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Filas vivas creadas por un asiento de apertura (FOR UPDATE). */
    public function getFilasApertura(int $idAsientoApertura, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM suscripciones_devengos
             WHERE id_empresa = :e AND id_asiento_apertura = :a AND origen = 'apertura' AND eliminado = false
             FOR UPDATE"
        );
        $st->execute([':e' => $idEmpresa, ':a' => $idAsientoApertura]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarAsientoApertura(int $id, int $idAsiento): void
    {
        $this->db->prepare("UPDATE suscripciones_devengos SET id_asiento_apertura = :a WHERE id = :id")
            ->execute([':a' => $idAsiento, ':id' => $id]);
    }

    // ── Reporte de ingresos diferidos ───────────────────────────────────────

    /**
     * Filas que forman el saldo del cronograma al cierre del día $fechaCorte (último día del
     * mes elegido), reconstruido con las FECHAS de los asientos, no con el estado de hoy:
     *   - diferido: el asiento que creó el pasivo (factura, o apertura) es ≤ corte, y a esa fecha
     *     aún no se había devengado (pendiente, o devengado con asiento posterior al corte) ni lo
     *     había tomado una NC (NC posterior al corte).
     *   - provisión: provisionada con asiento ≤ corte y sin facturar a esa fecha.
     * Se excluyen documentos anulados/eliminados: su asiento anulado tampoco está en el mayor.
     */
    public function getSaldosAlCorte(int $idEmpresa, string $fechaCorte, ?int $idCliente = null, ?int $idUsuarioFiltro = null): array
    {
        // Filtros opcionales del reporte: un cliente, y registros propios (§6: sin acceso total,
        // solo las suscripciones que registró el usuario).
        $extra = '';
        $paramsExtra = [];
        if ($idCliente) {
            $extra .= ' AND s.id_cliente = :id_cliente';
            $paramsExtra[':id_cliente'] = $idCliente;
        }
        if ($idUsuarioFiltro !== null) {
            $extra .= ' AND s.created_by = :id_usuario_filtro';
            $paramsExtra[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $origen  = $this->tieneOrigen();
        $joinAp  = $origen ? 'LEFT JOIN asientos_contables_cabecera aap ON aap.id = d.id_asiento_apertura AND aap.estado <> \'anulado\'' : '';
        $fechaPasivo = $origen
            ? "CASE WHEN d.origen = 'apertura' THEN aap.fecha_asiento ELSE COALESCE(v.fecha_emision, r.fecha_emision) END"
            : 'COALESCE(v.fecha_emision, r.fecha_emision)';
        $docConAsiento = $origen
            ? "(d.origen = 'apertura' AND aap.id IS NOT NULL OR d.origen = 'documento' AND COALESCE(v.id_asiento_contable, r.id_asiento_contable) IS NOT NULL)"
            : 'COALESCE(v.id_asiento_contable, r.id_asiento_contable) IS NOT NULL';

        $st = $this->db->prepare(
            "SELECT d.tipo, d.id_suscripcion, d.tipo_documento, d.id_documento, d.periodo, d.monto,
                    CASE d.tipo_documento
                        WHEN 'factura' THEN v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial
                        WHEN 'recibo'  THEN r.establecimiento || '-' || r.punto_emision || '-' || r.secuencial
                    END AS numero,
                    COALESCE(v.fecha_emision, r.fecha_emision) AS fecha_documento,
                    c.nombre AS cliente, c.identificacion, s.id_cliente
             FROM suscripciones_devengos d
             JOIN suscripciones s ON s.id = d.id_suscripcion AND s.id_empresa = d.id_empresa
             LEFT JOIN clientes c ON c.id = s.id_cliente
             LEFT JOIN ventas_cabecera v
                    ON d.tipo_documento = 'factura' AND v.id = d.id_documento AND v.id_empresa = d.id_empresa
             LEFT JOIN recibos_venta_cabecera r
                    ON d.tipo_documento = 'recibo' AND r.id = d.id_documento AND r.id_empresa = d.id_empresa
             LEFT JOIN asientos_contables_cabecera adev ON adev.id = d.id_asiento
             LEFT JOIN notas_credito_cabecera nc ON nc.id = d.id_nota_credito
             {$joinAp}
             WHERE d.id_empresa = :e AND d.eliminado = false
               AND (
                    (d.tipo = 'diferido'
                     AND COALESCE(v.eliminado, r.eliminado) = false
                     AND COALESCE(v.estado, r.estado) <> 'anulado'
                     AND {$docConAsiento}
                     AND {$fechaPasivo} <= :f1
                     AND (d.estado = 'pendiente'
                          OR (d.estado = 'devengado' AND adev.fecha_asiento > :f2)
                          OR (d.estado = 'anulado' AND d.id_nota_credito IS NOT NULL AND nc.estado <> 'anulado' AND nc.fecha_emision > :f3)))
                 OR (d.tipo = 'provision'
                     AND adev.estado <> 'anulado' AND adev.fecha_asiento <= :f4
                     AND (d.estado = 'devengado'
                          OR (d.estado = 'facturado' AND COALESCE(v.fecha_emision, r.fecha_emision) > :f5)))
               ){$extra}
             ORDER BY c.nombre, d.id_suscripcion, d.tipo_documento, d.id_documento, d.periodo"
        );
        $st->execute($paramsExtra + [':e' => $idEmpresa, ':f1' => $fechaCorte, ':f2' => $fechaCorte, ':f3' => $fechaCorte, ':f4' => $fechaCorte, ':f5' => $fechaCorte]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Clientes con alguna suscripción (buscador del reporte), por nombre o identificación. */
    public function buscarClientesConSuscripcion(int $idEmpresa, string $q, ?int $idUsuarioFiltro = null, int $limite = 20): array
    {
        $params = [':e' => $idEmpresa, ':q' => '%' . $q . '%', ':q2' => '%' . $q . '%'];
        $propios = '';
        if ($idUsuarioFiltro !== null) {
            $propios = ' AND s.created_by = :u';
            $params[':u'] = $idUsuarioFiltro;
        }
        $st = $this->db->prepare(
            "SELECT DISTINCT c.id, c.nombre, c.identificacion
             FROM suscripciones s
             JOIN clientes c ON c.id = s.id_cliente
             WHERE s.id_empresa = :e AND s.eliminado = false {$propios}
               AND (c.nombre ILIKE :q OR c.identificacion ILIKE :q2)
             ORDER BY c.nombre
             LIMIT " . max(1, min(50, $limite))
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo DEUDOR (debe − haber) de una cuenta en el mayor al cierre de $fechaCorte: asientos
     * vivos de la empresa en su ambiente activo, igual que el resto de reportes contables.
     */
    public function getSaldoMayor(int $idEmpresa, int $idCuenta, string $fechaCorte): float
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(det.debe - det.haber), 0)
             FROM asientos_contables_detalle det
             JOIN asientos_contables_cabecera a ON a.id = det.id_asiento
             WHERE a.id_empresa = :e AND a.eliminado = false AND a.estado <> 'anulado'
               AND det.eliminado = false AND det.id_cuenta_contable = :c AND a.fecha_asiento <= :f
               AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e2)"
        );
        $st->execute([':e' => $idEmpresa, ':c' => $idCuenta, ':f' => $fechaCorte, ':e2' => $idEmpresa]);
        return round((float) $st->fetchColumn(), 2);
    }

    // ── Auditoría Contable ──────────────────────────────────────────────────

    /**
     * Por cada factura/recibo con cronograma: lo que su asiento DEBERÍA acreditar a Ingresos
     * diferidos y a por facturar (mismas filas que usa AsientoBuilderService::devengoSuscripcionDocumento)
     * y lo que su asiento vivo REALMENTE acredita a esas cuentas. Documentos vivos con asiento del
     * ambiente dado; $desde/$hasta acotan por fecha de emisión.
     */
    public function auditoriaAsientoVsCronograma(int $idEmpresa, string $ambiente, int $idCuentaDif, int $idCuentaPf, ?string $desde, ?string $hasta): array
    {
        $soloDoc = $this->tieneOrigen() ? "AND d.origen = 'documento'" : '';
        $ramas = [];
        $params = [':e' => $idEmpresa, ':amb' => $ambiente, ':cdif' => $idCuentaDif, ':cpf' => $idCuentaPf];
        foreach (['factura' => 'ventas_cabecera', 'recibo' => 'recibos_venta_cabecera'] as $tipo => $tabla) {
            $rango = '';
            if ($desde) { $rango .= " AND doc.fecha_emision >= :desde_{$tipo}"; $params[":desde_{$tipo}"] = $desde; }
            if ($hasta) { $rango .= " AND doc.fecha_emision <= :hasta_{$tipo}"; $params[":hasta_{$tipo}"] = $hasta; }
            $ramas[] = "SELECT '{$tipo}' AS tipo_documento, doc.id AS id_documento, doc.id_asiento_contable AS id_asiento,
                               doc.fecha_emision,
                               doc.establecimiento || '-' || doc.punto_emision || '-' || doc.secuencial AS numero,
                               c.nombre AS cliente, esp.dif AS esperado_dif, esp.pf AS esperado_pf,
                               COALESCE((SELECT SUM(det.haber - det.debe) FROM asientos_contables_detalle det
                                         WHERE det.id_asiento = ac.id AND det.eliminado = false AND det.id_cuenta_contable = :cdif), 0) AS asiento_dif,
                               COALESCE((SELECT SUM(det.haber - det.debe) FROM asientos_contables_detalle det
                                         WHERE det.id_asiento = ac.id AND det.eliminado = false AND det.id_cuenta_contable = :cpf), 0) AS asiento_pf
                        FROM (SELECT d.id_documento,
                                     SUM(CASE WHEN d.tipo = 'diferido'  THEN d.monto ELSE 0 END) AS dif,
                                     SUM(CASE WHEN d.tipo = 'provision' THEN d.monto ELSE 0 END) AS pf
                              FROM suscripciones_devengos d
                              WHERE d.id_empresa = :e AND d.eliminado = false AND d.tipo_documento = '{$tipo}'
                                AND ((d.tipo = 'diferido' AND (d.estado <> 'anulado' OR d.id_nota_credito IS NOT NULL))
                                  OR (d.tipo = 'provision' AND d.estado = 'facturado'))
                                {$soloDoc}
                              GROUP BY d.id_documento) esp
                        JOIN {$tabla} doc ON doc.id = esp.id_documento AND doc.id_empresa = :e
                        JOIN asientos_contables_cabecera ac ON ac.id = doc.id_asiento_contable
                             AND ac.estado <> 'anulado' AND ac.tipo_ambiente = :amb
                        LEFT JOIN clientes c ON c.id = doc.id_cliente
                        WHERE doc.eliminado = false AND doc.estado <> 'anulado' {$rango}";
        }
        $st = $this->db->prepare(implode(' UNION ALL ', $ramas));
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Documentos con meses YA cumplidos (anteriores a $periodoLimite) que siguen por devengar
     * aunque su documento tiene asiento: se olvidó correr «Devengar mes».
     */
    public function auditoriaMesesSinDevengar(int $idEmpresa, string $ambiente, string $periodoLimite, ?string $desde, ?string $hasta): array
    {
        $ramas = [];
        $params = [':e' => $idEmpresa, ':amb' => $ambiente, ':lim' => $periodoLimite];
        foreach (['factura' => 'ventas_cabecera', 'recibo' => 'recibos_venta_cabecera'] as $tipo => $tabla) {
            $rango = '';
            if ($desde) { $rango .= " AND doc.fecha_emision >= :desde_{$tipo}"; $params[":desde_{$tipo}"] = $desde; }
            if ($hasta) { $rango .= " AND doc.fecha_emision <= :hasta_{$tipo}"; $params[":hasta_{$tipo}"] = $hasta; }
            $ramas[] = "SELECT '{$tipo}' AS tipo_documento, doc.id AS id_documento, doc.fecha_emision,
                               doc.establecimiento || '-' || doc.punto_emision || '-' || doc.secuencial AS numero,
                               c.nombre AS cliente, COUNT(DISTINCT d.periodo) AS meses, SUM(d.monto) AS monto,
                               MIN(d.periodo) AS desde, MAX(d.periodo) AS hasta
                        FROM suscripciones_devengos d
                        JOIN {$tabla} doc ON doc.id = d.id_documento AND doc.id_empresa = d.id_empresa
                        JOIN asientos_contables_cabecera ac ON ac.id = doc.id_asiento_contable
                             AND ac.estado <> 'anulado' AND ac.tipo_ambiente = :amb
                        LEFT JOIN clientes c ON c.id = doc.id_cliente
                        WHERE d.id_empresa = :e AND d.eliminado = false AND d.tipo_documento = '{$tipo}'
                          AND d.tipo = 'diferido' AND d.estado = 'pendiente' AND d.periodo < :lim
                          AND doc.eliminado = false AND doc.estado <> 'anulado' {$rango}
                        GROUP BY doc.id, doc.fecha_emision, doc.establecimiento, doc.punto_emision, doc.secuencial, c.nombre";
        }
        $st = $this->db->prepare(implode(' UNION ALL ', $ramas));
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Proceso mensual ─────────────────────────────────────────────────────

    /**
     * Filas diferidas por devengar hasta el mes $periodo (incluye meses anteriores que quedaron
     * pendientes). Solo de documentos vivos que YA tienen su asiento: el asiento de la factura es
     * el que acreditó Ingresos diferidos; devengar antes (p. ej. una factura aún en borrador)
     * debitaría un pasivo que todavía no existe. Bloquea las filas (FOR UPDATE).
     */
    public function getDiferidosPorDevengar(int $idEmpresa, string $periodo): array
    {
        $st = $this->db->prepare(
            "SELECT d.id, d.tipo, d.id_producto, d.descripcion, d.monto, d.periodo,
                    d.tipo_documento, d.id_documento,
                    COALESCE(v.id_cliente, r.id_cliente) AS id_cliente
             FROM suscripciones_devengos d
             LEFT JOIN ventas_cabecera v
                    ON d.tipo_documento = 'factura' AND v.id = d.id_documento AND v.id_empresa = d.id_empresa
             LEFT JOIN recibos_venta_cabecera r
                    ON d.tipo_documento = 'recibo' AND r.id = d.id_documento AND r.id_empresa = d.id_empresa
             JOIN asientos_contables_cabecera ac
                    ON ac.id = COALESCE(v.id_asiento_contable, r.id_asiento_contable)
                   AND ac.id_empresa = d.id_empresa AND ac.estado <> 'anulado'
             WHERE d.id_empresa = :id_empresa AND d.eliminado = false
               AND d.tipo = 'diferido' AND d.estado = 'pendiente' AND d.periodo <= :periodo
               AND COALESCE(v.eliminado, r.eliminado) = false
               AND COALESCE(v.estado, r.estado) <> 'anulado'
             ORDER BY d.periodo, d.id
             FOR UPDATE OF d"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':periodo' => $periodo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filas diferidas de meses ≤ $periodo que NO se pueden devengar todavía porque su documento
     * aún no tiene asiento (borrador sin autorizar o asiento pendiente). Para avisar en la vista previa.
     */
    public function contarDiferidosSinAsiento(int $idEmpresa, string $periodo): array
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) AS filas, COALESCE(SUM(d.monto), 0) AS monto
             FROM suscripciones_devengos d
             LEFT JOIN ventas_cabecera v
                    ON d.tipo_documento = 'factura' AND v.id = d.id_documento AND v.id_empresa = d.id_empresa
             LEFT JOIN recibos_venta_cabecera r
                    ON d.tipo_documento = 'recibo' AND r.id = d.id_documento AND r.id_empresa = d.id_empresa
             LEFT JOIN asientos_contables_cabecera ac
                    ON ac.id = COALESCE(v.id_asiento_contable, r.id_asiento_contable)
                   AND ac.id_empresa = d.id_empresa AND ac.estado <> 'anulado'
             WHERE d.id_empresa = :id_empresa AND d.eliminado = false
               AND d.tipo = 'diferido' AND d.estado = 'pendiente' AND d.periodo <= :periodo
               AND COALESCE(v.eliminado, r.eliminado) = false
               AND COALESCE(v.estado, r.estado) <> 'anulado'
               AND ac.id IS NULL"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':periodo' => $periodo]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['filas' => 0, 'monto' => 0];
    }

    /**
     * Empresas activas con algo que devengar automáticamente: filas diferidas pendientes de un
     * mes ya cumplido, o suscripciones activas de mes caído que reconocen durante el período
     * (cuyo cierre de mes se provisiona). Para el devengo diario del cron (sin configurar nada).
     */
    public function getEmpresasConDevengo(): array
    {
        $st = $this->db->query(
            "SELECT x.id_empresa FROM (
                 SELECT DISTINCT d.id_empresa FROM suscripciones_devengos d
                 WHERE d.eliminado = false AND d.tipo = 'diferido' AND d.estado = 'pendiente'
                   AND d.periodo < date_trunc('month', CURRENT_DATE)
                 UNION
                 SELECT DISTINCT s.id_empresa FROM suscripciones s
                 WHERE s.eliminado = false AND s.estado = 'activo'
                   AND s.modalidad_cobro = 'vencido' AND s.reconocimiento = 'diferido'
             ) x
             JOIN empresas e ON e.id = x.id_empresa AND e.estado = '1' AND e.eliminado = false
             ORDER BY x.id_empresa"
        );
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Mes más antiguo con filas diferidas pendientes (para ponerse al día), o null. */
    public function getPrimerMesPendiente(int $idEmpresa): ?string
    {
        $st = $this->db->prepare(
            "SELECT MIN(periodo) FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND eliminado = false AND tipo = 'diferido' AND estado = 'pendiente'"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /**
     * Suscripciones de mes caído que reconocen el ingreso durante el período (candidatas a la
     * provisión del cierre). Solo activas y de periodicidad por meses.
     */
    public function getSuscripcionesMesCaido(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT s.id, s.id_cliente, s.tipo_comprobante, s.proximo_cobro, s.fecha_inicio, s.fecha_fin,
                    s.modalidad_cobro, s.reconocimiento,
                    per.meses AS periodicidad_meses, per.codigo AS periodicidad_codigo
             FROM suscripciones s
             JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
             WHERE s.id_empresa = :id_empresa AND s.eliminado = false AND s.estado = 'activo'
               AND s.modalidad_cobro = 'vencido' AND s.reconocimiento = 'diferido'
               AND per.codigo NOT IN ('DIARIO', 'SEMANAL', 'QUINCENAL')
             ORDER BY s.id"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿La suscripción ya tiene provisión viva para ese mes? */
    public function existeProvision(int $idSuscripcion, string $periodo, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND id_suscripcion = :id AND periodo = :periodo
               AND tipo = 'provision' AND eliminado = false AND estado <> 'anulado'
             LIMIT 1"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':id' => $idSuscripcion, ':periodo' => $periodo]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Provisiones de mes caído aún sin facturar de una suscripción en esos meses: las que cancela
     * la factura del período (ver SuscripcionDevengoService::vincularProvisiones()).
     */
    public function getProvisionesSinFacturar(int $idSuscripcion, array $periodos, int $idEmpresa): array
    {
        if (!$periodos) {
            return [];
        }
        $ph = [];
        $params = [':id_empresa' => $idEmpresa, ':id' => $idSuscripcion];
        foreach (array_values($periodos) as $i => $p) {
            $ph[] = ":p{$i}";
            $params[":p{$i}"] = $p;
        }
        $st = $this->db->prepare(
            "SELECT id, id_producto, monto, periodo FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND id_suscripcion = :id AND tipo = 'provision'
               AND estado = 'devengado' AND eliminado = false AND id_documento IS NULL
               AND periodo IN (" . implode(', ', $ph) . ")
             ORDER BY periodo, id
             FOR UPDATE"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** La factura/recibo del período canceló la provisión: queda enlazada a su línea. */
    public function marcarProvisionFacturada(int $id, string $tipoDocumento, int $idDocumento, int $idDetalle, ?int $idPago, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET estado = 'facturado', tipo_documento = :tipo, id_documento = :id_doc, id_documento_detalle = :id_det,
                 id_suscripcion_pago = COALESCE(:id_pago, id_suscripcion_pago),
                 updated_at = CURRENT_TIMESTAMP, updated_by = :u
             WHERE id = :id AND tipo = 'provision' AND estado = 'devengado'"
        )->execute([':tipo' => $tipoDocumento, ':id_doc' => $idDocumento, ':id_det' => $idDetalle, ':id_pago' => $idPago, ':u' => $idUsuario, ':id' => $id]);
    }

    /** Fila devengada por el asiento mensual (diferido → devengado). */
    public function marcarDevengado(int $id, int $idAsiento, ?int $idCuentaIngreso, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET estado = 'devengado', id_asiento = :a, id_cuenta_ingreso = :c,
                 devengado_at = CURRENT_TIMESTAMP, devengado_by = :u, updated_at = CURRENT_TIMESTAMP, updated_by = :u2
             WHERE id = :id"
        )->execute([':a' => $idAsiento, ':c' => $idCuentaIngreso, ':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id]);
    }

    /** Asientos vivos del devengo de un mes (el proceso puede correr más de una vez en el mes). */
    public function getAsientosMes(int $idEmpresa, int $anioMes): array
    {
        $st = $this->db->prepare(
            "SELECT id, numero_comprobante, fecha_asiento, total_debe
             FROM asientos_contables_cabecera
             WHERE id_empresa = :id_empresa AND modulo_origen = 'suscripcion_devengo'
               AND id_referencia_origen = :ref AND estado <> 'anulado'
             ORDER BY id"
        );
        $st->execute([':id_empresa' => $idEmpresa, ':ref' => $anioMes]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Alguna provisión de estos asientos ya la canceló una factura? (no se puede revertir). */
    public function contarProvisionesFacturadas(array $idsAsiento, int $idEmpresa): int
    {
        if (!$idsAsiento) {
            return 0;
        }
        $ids = implode(',', array_map('intval', $idsAsiento));
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM suscripciones_devengos
             WHERE id_empresa = :id_empresa AND eliminado = false AND tipo = 'provision'
               AND estado = 'facturado' AND id_asiento IN ({$ids})"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /**
     * Deshace el devengo de un asiento: las filas diferidas vuelven a pendiente y las provisiones
     * (aún sin facturar) se dan de baja. Devuelve cuántas filas tocó.
     */
    public function revertirPorAsiento(int $idAsiento, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET estado = 'pendiente', id_asiento = NULL, id_cuenta_ingreso = NULL,
                 devengado_at = NULL, devengado_by = NULL, updated_at = CURRENT_TIMESTAMP, updated_by = :u
             WHERE id_empresa = :id_empresa AND id_asiento = :a AND tipo = 'diferido'
               AND estado = 'devengado' AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id_empresa' => $idEmpresa, ':a' => $idAsiento]);
        $n = $st->rowCount();

        $st = $this->db->prepare(
            "UPDATE suscripciones_devengos
             SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
             WHERE id_empresa = :id_empresa AND id_asiento = :a AND tipo = 'provision'
               AND estado = 'devengado' AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':id_empresa' => $idEmpresa, ':a' => $idAsiento]);
        return $n + $st->rowCount();
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

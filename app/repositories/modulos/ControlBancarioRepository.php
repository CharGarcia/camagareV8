<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Acceso a datos de Control Bancario: movimientos de cuentas bancarias
 * (empresa_formas_pago con id_banco + id_cuenta_contable) resueltos desde
 * asientos_contables_detalle, enriquecidos con la clasificación opcional
 * de control_bancario_movimientos y, cuando no hay clasificación manual,
 * con los datos ya existentes en ingresos_pagos/egresos_pagos.
 *
 * Reporte de empresa (como MayoresRepository) para el listado de movimientos:
 * no filtra por "registros propios", el acceso se controla por permiso de
 * módulo, no por dueño. Extiende BaseRepository solo para reusar
 * beginTransaction/commit/rollBack sobre las escrituras en
 * control_bancario_movimientos (la tabla propia de este módulo).
 */
class ControlBancarioRepository extends BaseRepository
{
    private const COLUMNAS_ORDEN = [
        'fecha_asiento', 'fecha_banco', 'fecha_cheque', 'tipo_transaccion',
        'nombre_entidad', 'numero_comprobante', 'debe', 'haber',
    ];

    public function __construct()
    {
        parent::__construct('control_bancario_movimientos');
    }

    /**
     * Cuentas bancarias de la empresa: formas de pago con banco + cuenta contable asignados.
     */
    public function getFormasBancarias(int $idEmpresa): array
    {
        $sql = "SELECT fp.id, fp.nombre, fp.tipo, fp.tipo_cuenta, fp.numero_cuenta,
                       fp.id_cuenta_contable, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       b.nombre_banco
                FROM empresa_formas_pago fp
                INNER JOIN plan_cuentas pc ON pc.id = fp.id_cuenta_contable
                LEFT JOIN bancos_ecuador b ON b.id = fp.id_banco
                WHERE fp.id_empresa = :id_empresa
                  AND fp.eliminado = FALSE
                  AND fp.activo = TRUE
                  AND fp.id_banco IS NOT NULL
                  AND fp.id_cuenta_contable IS NOT NULL
                ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFormaBancaria(int $idFormaPago, int $idEmpresa): ?array
    {
        $sql = "SELECT fp.id, fp.nombre, fp.id_cuenta_contable, fp.id_banco
                FROM empresa_formas_pago fp
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE
                  AND fp.id_banco IS NOT NULL AND fp.id_cuenta_contable IS NOT NULL";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idFormaPago, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSaldoInicial(int $idEmpresa, int $idFormaPago): float
    {
        $sql = "SELECT saldo_inicial FROM saldos_iniciales_bancos
                WHERE id_empresa = :id_empresa AND id_forma_pago = :id_forma_pago AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_forma_pago' => $idFormaPago]);
        $val = $st->fetchColumn();
        return $val !== false ? (float) $val : 0.0;
    }

    /**
     * Fragmento SQL común: deriva tipo/dirección/número de cheque/fechas desde la
     * clasificación manual (control_bancario_movimientos) o, si no existe, desde
     * ingresos_pagos/egresos_pagos (vía modulo_origen + id_referencia_origen del asiento).
     */
    private function selectDerivado(): string
    {
        return "
            COALESCE(cbm.tipo_transaccion,
                     CASE WHEN ip.id IS NOT NULL THEN COALESCE(NULLIF(ip.tipo_operacion_bancaria, ''), 'OTRO')
                          ELSE 'OTRO' END) AS tipo_transaccion,
            COALESCE(cbm.cheque_direccion,
                     CASE WHEN ip.id IS NOT NULL THEN 'RECIBIDO'
                          WHEN ep.id IS NOT NULL THEN 'EMITIDO'
                          ELSE NULL END) AS cheque_direccion,
            COALESCE(cbm.numero_cheque, ip.numero_cheque, ep.referencia) AS numero_cheque,
            COALESCE(cbm.fecha_cheque, ip.fecha_cobro) AS fecha_cheque,
            COALESCE(cbm.fecha_banco, ac.fecha_asiento) AS fecha_banco,
            cbm.id AS id_clasificacion,
            cbm.observacion AS observacion";
    }

    private function joinsDerivado(string $aliasForma = ':id_forma_pago'): string
    {
        return "
            LEFT JOIN control_bancario_movimientos cbm ON cbm.id_asiento_detalle = ad.id AND cbm.eliminado = FALSE
            LEFT JOIN ingresos_pagos ip ON ac.modulo_origen = 'ingreso' AND ac.id_referencia_origen = ip.id_ingreso AND ip.id_forma_cobro = fp.id
            LEFT JOIN egresos_pagos ep ON ac.modulo_origen = 'egreso' AND ac.id_referencia_origen = ep.id_egreso AND ep.id_forma_pago = fp.id AND ep.eliminado = FALSE";
    }

    /**
     * Movimientos de una cuenta bancaria. El saldo acumulado (saldo_inicial + suma
     * cronológica de debe-haber) se calcula con una función de ventana sobre TODO el
     * histórico de la cuenta (sin importar filtros de tipo/fecha mostrados), para que
     * siempre refleje el saldo real del banco en cada línea; los filtros de fecha/tipo
     * se aplican después, como un WHERE externo.
     */
    public function getMovimientos(
        int $idEmpresa,
        int $idFormaPago,
        int $idCuentaContable,
        float $saldoInicial,
        array $filtros,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'fecha_asiento';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $cte = "WITH base AS (
                    SELECT
                        ad.id AS id_asiento_detalle,
                        ac.id AS id_asiento,
                        ac.fecha_asiento,
                        ac.numero_comprobante,
                        ac.concepto,
                        ad.referencia_detalle,
                        ad.documento_referencia,
                        ad.debe,
                        ad.haber,
                        ad.tipo_entidad,
                        ad.id_entidad,
                        COALESCE(cli.nombre, prov.razon_social, emp.nombres_apellidos) AS nombre_entidad,
                        {$this->selectDerivado()},
                        (:saldo_inicial + SUM(ad.debe - ad.haber) OVER (
                            ORDER BY ac.fecha_asiento, ac.id, ad.id
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        )) AS saldo_acumulado
                    FROM asientos_contables_detalle ad
                    INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                    INNER JOIN empresa_formas_pago fp ON fp.id = :id_forma_pago
                    LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                    LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                    LEFT JOIN empleados emp ON ad.tipo_entidad = 'empleado' AND ad.id_entidad = emp.id
                    {$this->joinsDerivado()}
                    WHERE ac.id_empresa = :id_empresa
                      AND ac.estado = 'contabilizado'
                      AND ac.eliminado = FALSE
                      AND ad.eliminado = FALSE
                      AND ad.id_cuenta_contable = :id_cuenta_contable
                      AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                )";

        $whereSql = "WHERE 1=1";
        $params = [
            ':id_empresa' => $idEmpresa,
            ':id_forma_pago' => $idFormaPago,
            ':id_cuenta_contable' => $idCuentaContable,
            ':saldo_inicial' => $saldoInicial,
        ];

        if (!empty($filtros['fecha_inicio'])) {
            $whereSql .= " AND fecha_asiento >= :f_ini";
            $params[':f_ini'] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            $whereSql .= " AND fecha_asiento <= :f_fin";
            $params[':f_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['buscar'])) {
            $parsed = FiltrosBusqueda::parsear($filtros['buscar']);
            if ($parsed['texto_libre'] !== '') {
                $whereSql .= " AND (numero_comprobante ILIKE :tl OR concepto ILIKE :tl OR referencia_detalle ILIKE :tl
                                    OR documento_referencia ILIKE :tl OR nombre_entidad ILIKE :tl OR numero_cheque ILIKE :tl)";
                $params[':tl'] = '%' . $parsed['texto_libre'] . '%';
            }
            $mapas = [
                'texto' => [
                    'numero_cheque' => 'numero_cheque',
                    'concepto' => 'concepto',
                    'documento' => 'documento_referencia',
                    'tercero' => 'nombre_entidad',
                    'observacion' => 'observacion',
                    'glosa' => 'referencia_detalle',
                    // tipo/direccion van por ILIKE (no 'exacto'): se guardan en mayúsculas
                    // (DEPOSITO, CHEQUE, EMITIDO...) pero el usuario escribe en minúsculas
                    // en el buscador; ILIKE es case-insensitive, '=' de 'exacto' no lo es.
                    'tipo' => 'tipo_transaccion',
                    'direccion' => 'cheque_direccion',
                ],
                'fecha' => [
                    'fecha' => 'fecha_asiento',
                    'fecha_banco' => 'fecha_banco',
                    'fecha_cheque' => 'fecha_cheque',
                ],
                'numerico' => [
                    'debe' => 'debe',
                    'haber' => 'haber',
                ],
            ];
            FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], $mapas);
        }

        // Count
        $sqlCount = "{$cte} SELECT COUNT(*) FROM base {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Rows
        $offset = ($page - 1) * $perPage;
        $sqlRows = "{$cte} SELECT * FROM base {$whereSql} ORDER BY {$ordenCol} {$dir}, id_asiento_detalle {$dir} LIMIT :limit OFFSET :offset";
        $stRows = $this->db->prepare($sqlRows);
        foreach ($params as $key => $val) {
            $stRows->bindValue($key, $val);
        }
        $stRows->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stRows->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stRows->execute();

        return [
            'total' => $total,
            'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /**
     * Cheques posfechados (fecha_cheque > hoy), recibidos o emitidos, de todas las
     * cuentas bancarias de la empresa o de una en particular.
     */
    public function getChequesPosfechados(int $idEmpresa, ?int $idFormaPago, string $direccion): array
    {
        $sql = "SELECT
                    ad.id AS id_asiento_detalle,
                    ac.id AS id_asiento,
                    ac.fecha_asiento,
                    ac.numero_comprobante,
                    ac.concepto,
                    ad.referencia_detalle,
                    ad.documento_referencia,
                    ad.debe,
                    ad.haber,
                    COALESCE(cli.nombre, prov.razon_social) AS nombre_entidad,
                    fp.id AS id_forma_pago,
                    fp.nombre AS forma_pago_nombre,
                    {$this->selectDerivado()}
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                INNER JOIN empresa_formas_pago fp ON fp.id_cuenta_contable = ad.id_cuenta_contable
                    AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE AND fp.id_banco IS NOT NULL
                LEFT JOIN clientes cli ON ad.tipo_entidad = 'cliente' AND ad.id_entidad = cli.id
                LEFT JOIN proveedores prov ON ad.tipo_entidad = 'proveedor' AND ad.id_entidad = prov.id
                {$this->joinsDerivado()}
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($idFormaPago)) {
            $sql .= " AND fp.id = :id_forma_pago";
            $params[':id_forma_pago'] = $idFormaPago;
        }

        $direccion = strtoupper($direccion);
        if (in_array($direccion, ['EMITIDO', 'RECIBIDO'], true)) {
            $sql .= " AND COALESCE(cbm.cheque_direccion,
                        CASE WHEN ip.id IS NOT NULL THEN 'RECIBIDO' WHEN ep.id IS NOT NULL THEN 'EMITIDO' ELSE NULL END) = :direccion";
            $params[':direccion'] = $direccion;
        }

        $sqlFull = "SELECT * FROM ({$sql}) x
                     WHERE x.tipo_transaccion = 'CHEQUE' AND x.fecha_cheque > CURRENT_DATE
                     ORDER BY x.fecha_cheque ASC";

        $st = $this->db->prepare($sqlFull);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getClasificacionPorAsientoDetalle(int $idAsientoDetalle, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM control_bancario_movimientos
                WHERE id_asiento_detalle = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Verifica que la línea de asiento pertenezca a la empresa y a la cuenta contable de la forma indicada. */
    public function validarAsientoDetalle(int $idAsientoDetalle, int $idEmpresa, int $idCuentaContable): bool
    {
        $sql = "SELECT COUNT(*) FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ad.id = :id AND ac.id_empresa = :id_empresa AND ad.id_cuenta_contable = :id_cuenta
                  AND ad.eliminado = FALSE AND ac.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuentaContable]);
        return (int) $st->fetchColumn() > 0;
    }

    public function upsertClasificacion(array $data): int
    {
        $sql = "INSERT INTO control_bancario_movimientos (
                    id_empresa, id_asiento_detalle, id_forma_pago, tipo_transaccion,
                    cheque_direccion, numero_cheque, fecha_cheque, fecha_banco, observacion,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_asiento_detalle, :id_forma_pago, :tipo_transaccion,
                    :cheque_direccion, :numero_cheque, :fecha_cheque, :fecha_banco, :observacion,
                    :usuario, :usuario
                )
                ON CONFLICT (id_asiento_detalle) DO UPDATE SET
                    tipo_transaccion = EXCLUDED.tipo_transaccion,
                    cheque_direccion = EXCLUDED.cheque_direccion,
                    numero_cheque = EXCLUDED.numero_cheque,
                    fecha_cheque = EXCLUDED.fecha_cheque,
                    fecha_banco = EXCLUDED.fecha_banco,
                    observacion = EXCLUDED.observacion,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = CURRENT_TIMESTAMP,
                    eliminado = FALSE,
                    deleted_at = NULL,
                    deleted_by = NULL
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_asiento_detalle' => $data['id_asiento_detalle'],
            ':id_forma_pago' => $data['id_forma_pago'],
            ':tipo_transaccion' => $data['tipo_transaccion'],
            ':cheque_direccion' => $data['cheque_direccion'] ?? null,
            ':numero_cheque' => $data['numero_cheque'] ?? null,
            ':fecha_cheque' => $data['fecha_cheque'] ?? null,
            ':fecha_banco' => $data['fecha_banco'] ?? null,
            ':observacion' => $data['observacion'] ?? null,
            ':usuario' => $data['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function quitarClasificacion(int $idAsientoDetalle, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE control_bancario_movimientos SET
                    eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usuario
                WHERE id_asiento_detalle = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoDetalle, ':id_empresa' => $idEmpresa, ':usuario' => $idUsuario]);
        return $st->rowCount() > 0;
    }

    public function getSaldoActual(int $idEmpresa, int $idCuentaContable, float $saldoInicial): float
    {
        $sql = "SELECT COALESCE(SUM(ad.debe - ad.haber), 0)
                FROM asientos_contables_detalle ad
                INNER JOIN asientos_contables_cabecera ac ON ad.id_asiento = ac.id
                WHERE ac.id_empresa = :id_empresa
                  AND ac.estado = 'contabilizado'
                  AND ac.eliminado = FALSE
                  AND ad.eliminado = FALSE
                  AND ad.id_cuenta_contable = :id_cuenta
                  AND ac.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuentaContable]);
        return $saldoInicial + (float) $st->fetchColumn();
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT extract(year from fecha_asiento) as anio
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

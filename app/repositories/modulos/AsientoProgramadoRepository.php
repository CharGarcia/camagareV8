<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class AsientoProgramadoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('asientos_programados');
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS asientos_preferencia_empresa (
                id SERIAL PRIMARY KEY,
                id_empresa INTEGER NOT NULL,
                tipo_asiento VARCHAR(100) NOT NULL,
                metodo VARCHAR(50) DEFAULT 'general' NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER,
                updated_by INTEGER,
                eliminado BOOLEAN DEFAULT FALSE,
                deleted_at TIMESTAMP,
                deleted_by INTEGER
            )");
        } catch (\Throwable $e) {
            // Catch exceptions silently
        }
    }

    /**
     * Obtiene el listado de asientos programados con filtros y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $whitelist = ['id', 'asiento_tipo_codigo', 'cuenta_codigo', 'tipo_referencia'];
        if (!in_array($ordenCol, $whitelist)) {
            $ordenCol = 'id';
        }

        $params = [':id_empresa' => $idEmpresa];
        $whereSql = "WHERE ap.id_empresa = :id_empresa AND ap.eliminado = false";

        if ($buscar !== '') {
            $whereSql .= " AND (at.codigo ILIKE :buscar OR pc.codigo ILIKE :buscar OR pc.nombre ILIKE :buscar OR ap.tipo_referencia ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) 
                     FROM {$this->table} ap
                     INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                     INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $orderExpr = match($ordenCol) {
            'asiento_tipo_codigo' => 'at.codigo',
            'cuenta_codigo'       => 'pc.codigo',
            'tipo_referencia'     => 'ap.tipo_referencia',
            default               => 'ap.id'
        };

        $sqlRows = "SELECT ap.*, 
                           at.codigo AS asiento_tipo_codigo, 
                           at.tipo_asiento AS asiento_tipo_concepto,
                           at.referencia AS asiento_tipo_referencia,
                           pc.codigo AS cuenta_codigo, 
                           pc.nombre AS cuenta_nombre,
                           c.nombre AS cliente_nombre,
                           p.razon_social AS proveedor_nombre
                    FROM {$this->table} ap
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    LEFT JOIN clientes c ON c.id = ap.id_referencia AND ap.tipo_referencia = 'cliente'
                    LEFT JOIN proveedores p ON p.id = ap.id_referencia AND ap.tipo_referencia = 'proveedor'
                    {$whereSql}
                    ORDER BY {$orderExpr} {$ordenDir}";

        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /**
     * Busca un asiento programado por ID.
     */
    public function findByIdAndEmpresa(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT ap.*, 
                       at.codigo AS asiento_tipo_codigo,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM {$this->table} ap
                INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE ap.id = :id AND ap.id_empresa = :id_empresa AND ap.eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crea un nuevo asiento programado en la empresa.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, id_asiento_tipo, id_cuenta, id_referencia, tipo_referencia,
                    referencia_texto, created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :id_asiento_tipo, :id_cuenta, :id_referencia, :tipo_referencia,
                    :referencia_texto, :created_by, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
            ':referencia_texto' => !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null,
            ':created_by'       => $data['created_by']
        ]);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un asiento programado.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_asiento_tipo = :id_asiento_tipo,
                    id_cuenta = :id_cuenta,
                    id_referencia = :id_referencia,
                    tipo_referencia = :tipo_referencia,
                    referencia_texto = :referencia_texto,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
            ':referencia_texto' => !empty($data['referencia_texto']) ? trim((string) $data['referencia_texto']) : null,
            ':updated_by'       => $data['updated_by'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica de un asiento programado.
     */
    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                    eliminado = true, 
                    deleted_by = :deleted_by,
                    deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'           => $id, 
            ':id_empresa'   => $idEmpresa,
            ':deleted_by'   => $idUsuario
        ]);
    }

    /**
     * Verifica si ya existe una regla para el mismo Asiento Tipo y misma Referencia (evitar duplicaciones).
     */
    public function existeRegla(int $idEmpresa, int $idAsientoTipo, ?int $idReferencia, ?string $tipoReferencia, ?int $idExcluir = null, ?string $referenciaTexto = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND id_asiento_tipo = :id_asiento_tipo
                  AND eliminado = false";

        $params = [
            ':id_empresa' => $idEmpresa,
            ':id_asiento_tipo' => $idAsientoTipo
        ];

        // Reglas con clave de TEXTO (p. ej. 'item_compra'): se identifican por tipo + referencia_texto.
        if ($referenciaTexto !== null && trim($referenciaTexto) !== '') {
            $sql .= " AND tipo_referencia = :tipo_ref AND TRIM(referencia_texto) = :ref_txt";
            $params[':tipo_ref'] = $tipoReferencia;
            $params[':ref_txt'] = trim($referenciaTexto);
            if ($idExcluir !== null && $idExcluir > 0) {
                $sql .= " AND id != :id_exc";
                $params[':id_exc'] = $idExcluir;
            }
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return ((int) $st->fetchColumn()) > 0;
        }

        if ($idReferencia !== null && $idReferencia > 0) {
            if ($tipoReferencia !== 'cliente' && $tipoReferencia !== 'proveedor' && $tipoReferencia !== 'producto' && $tipoReferencia !== 'categoria' && $tipoReferencia !== 'marca' && $tipoReferencia !== 'iva' && $tipoReferencia !== 'empleado') {
                // For general rules, check both new and old reference types to prevent duplicates
                $sql .= " AND id_referencia = :id_ref AND (tipo_referencia = :tipo_ref OR tipo_referencia = 'asientos tipo')";
            } else {
                $sql .= " AND id_referencia = :id_ref AND tipo_referencia = :tipo_ref";
            }
            $params[':id_ref'] = $idReferencia;
            $params[':tipo_ref'] = $tipoReferencia;
        } else {
            $sql .= " AND id_referencia IS NULL";
        }

        if ($idExcluir !== null && $idExcluir > 0) {
            $sql .= " AND id != :id_exc";
            $params[':id_exc'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    /**
     * Obtiene todos los asientos tipo de un concepto y su homólogo programado a nivel general de empresa.
     */
    /** Overrides por empleado con datos de la cuenta: [id_asiento_tipo => [id_cuenta,codigo,nombre]]. */
    public function getReglasEmpleadoConCuenta(int $idEmpresa, int $idEmpleado): array
    {
        $st = $this->db->prepare("SELECT ap.id_asiento_tipo, ap.id_cuenta, pc.codigo, pc.nombre
                                  FROM {$this->table} ap JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                                  WHERE ap.id_empresa = :e AND ap.tipo_referencia = 'empleado' AND ap.id_referencia = :id AND ap.eliminado = false");
        $st->execute([':e' => $idEmpresa, ':id' => $idEmpleado]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_asiento_tipo']] = ['id_cuenta' => (int) $r['id_cuenta'], 'codigo' => $r['codigo'], 'nombre' => $r['nombre']];
        }
        return $map;
    }

    /** Overrides de cuenta por empleado: [id_asiento_tipo => id_cuenta]. */
    public function getReglasEmpleado(int $idEmpresa, int $idEmpleado): array
    {
        $st = $this->db->prepare("SELECT id_asiento_tipo, id_cuenta FROM {$this->table}
                                  WHERE id_empresa = :e AND tipo_referencia = 'empleado' AND id_referencia = :id
                                    AND eliminado = false AND id_cuenta IS NOT NULL");
        $st->execute([':e' => $idEmpresa, ':id' => $idEmpleado]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id_asiento_tipo']] = (int) $r['id_cuenta'];
        }
        return $map;
    }

    public function getReglasGeneralesPorConcepto(int $idEmpresa, string $tipoAsiento): array
    {
        $sql = "SELECT at.id AS id_asiento_tipo,
                       at.tipo_asiento,
                       at.referencia AS concepto,
                       at.detalle,
                       at.codigo,
                       at.tipo_cuenta,
                       at.debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM asientos_tipo at
                LEFT JOIN {$this->table} ap ON ap.id_asiento_tipo = at.id 
                                           AND ap.id_empresa = :id_empresa 
                                           AND ap.id_referencia = at.id 
                                           AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento) 
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE at.tipo_asiento = :tipo_asiento AND at.eliminado = false
                ORDER BY at.codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':tipo_asiento'  => $tipoAsiento
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las opciones de Ingresos/Egresos (módulo empresa_opciones_ingreso_egreso) activas
     * que aplican a la naturaleza indicada, cruzadas con su cuenta contable programada.
     * La cuenta se toma del asiento programado si existe; en su defecto, de la cuenta asignada
     * en el propio módulo de opciones (id_cuenta_contable).
     *
     * @param string $naturaleza 'ingreso' | 'egreso'
     */
    public function getReglasOpcionesIngresoEgreso(int $idEmpresa, string $naturaleza): array
    {
        $col     = $naturaleza === 'ingreso' ? 'aplica_ingresos' : 'aplica_egresos';
        $tipoRef = $naturaleza === 'ingreso' ? 'opcion_ingreso'  : 'opcion_egreso';

        $sql = "SELECT o.id AS id_opcion,
                       o.nombre AS concepto,
                       o.comportamiento,
                       ap.id AS id_programado,
                       COALESCE(ap.id_cuenta, o.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso o
                LEFT JOIN {$this->table} ap ON ap.id_referencia = o.id
                                           AND ap.tipo_referencia = :tipo_ref
                                           AND ap.id_empresa = :id_empresa_ap
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, o.id_cuenta_contable)
                WHERE o.id_empresa = :id_empresa
                  AND o.{$col} = TRUE
                  AND UPPER(o.estado) = 'ACTIVO'
                  AND o.eliminado = FALSE
                ORDER BY o.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ap' => $idEmpresa,
            ':tipo_ref'      => $tipoRef
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las formas de cobro/pago (módulo empresa_formas_pago) activas que aplican al flujo
     * indicado, cruzadas con su cuenta contable programada. La cuenta se toma del asiento
     * programado si existe; en su defecto, de la cuenta asignada en el propio módulo de formas.
     *
     * @param string $flujo 'cobro' | 'pago'
     */
    public function getReglasFormasCobrosPagos(int $idEmpresa, string $flujo): array
    {
        $aplica  = $flujo === 'cobro' ? 'INGRESO'     : 'EGRESO';
        $tipoRef = $flujo === 'cobro' ? 'forma_cobro' : 'forma_pago';

        $sql = "SELECT f.id AS id_forma,
                       f.nombre AS concepto,
                       f.aplica_en,
                       ap.id AS id_programado,
                       COALESCE(ap.id_cuenta, f.id_cuenta_contable) AS id_cuenta,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM empresa_formas_pago f
                LEFT JOIN {$this->table} ap ON ap.id_referencia = f.id
                                           AND ap.tipo_referencia = :tipo_ref
                                           AND ap.id_empresa = :id_empresa_ap
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = COALESCE(ap.id_cuenta, f.id_cuenta_contable)
                WHERE f.id_empresa = :id_empresa
                  AND f.activo = TRUE
                  AND f.eliminado = FALSE
                  AND (f.aplica_en = 'AMBAS' OR f.aplica_en = :aplica)
                ORDER BY f.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ap' => $idEmpresa,
            ':tipo_ref'      => $tipoRef,
            ':aplica'        => $aplica
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la regla (asiento programado) asociada a una referencia concreta
     * (opción de Ingreso/Egreso, forma de cobro/pago, etc.) por su tipo de referencia.
     */
    public function getReglaPorReferencia(int $idEmpresa, int $idReferencia, string $tipoReferencia): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND id_referencia = :id_referencia
                  AND tipo_referencia = :tipo_referencia
                  AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_referencia'   => $idReferencia,
            ':tipo_referencia' => $tipoReferencia
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene una regla general específica por empresa y asiento tipo.
     */
    public function getReglaGeneralPorAsientoTipo(int $idEmpresa, int $idAsientoTipo): ?array
    {
        $sql = "SELECT ap.*, at.tipo_asiento FROM {$this->table} ap
                INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                WHERE ap.id_empresa = :id_empresa 
                  AND ap.id_asiento_tipo = :id_asiento_tipo 
                  AND ap.id_referencia = :id_asiento_tipo 
                  AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento) 
                  AND ap.eliminado = false 
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'      => $idEmpresa,
            ':id_asiento_tipo' => $idAsientoTipo
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene el nombre del tipo de asiento de la tabla asientos_tipo.
     */
    public function getTipoAsientoNombre(int $idAsientoTipo): ?string
    {
        $sql = "SELECT tipo_asiento FROM asientos_tipo WHERE id = :id AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idAsientoTipo]);
        return $st->fetchColumn() ?: null;
    }

    /**
     * Obtiene la preferencia de método de contabilización de la empresa para un tipo de asiento.
     */
    public function getMetodoPreferencia(int $idEmpresa, string $tipoAsiento): string
    {
        $sql = "SELECT metodo FROM asientos_preferencia_empresa 
                WHERE id_empresa = :id_empresa AND tipo_asiento = :tipo_asiento AND eliminado = false 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':tipo_asiento' => $tipoAsiento
        ]);
        $val = $stmt->fetchColumn();
        return $val ?: 'general';
    }

    /**
     * Guarda o actualiza la preferencia de método de contabilización de la empresa.
     */
    public function guardarMetodoPreferencia(int $idEmpresa, string $tipoAsiento, string $metodo, int $idUsuario): void
    {
        $sqlCheck = "SELECT id FROM asientos_preferencia_empresa 
                     WHERE id_empresa = :id_empresa AND tipo_asiento = :tipo_asiento AND eliminado = false";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':id_empresa' => $idEmpresa,
            ':tipo_asiento' => $tipoAsiento
        ]);
        $id = $stmtCheck->fetchColumn();

        if ($id) {
            $sql = "UPDATE asientos_preferencia_empresa 
                    SET metodo = :metodo, updated_at = NOW(), updated_by = :usuario 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':metodo' => $metodo,
                ':usuario' => $idUsuario,
                ':id' => $id
            ]);
        } else {
            $sql = "INSERT INTO asientos_preferencia_empresa 
                    (id_empresa, tipo_asiento, metodo, created_by) 
                    VALUES (:id_empresa, :tipo_asiento, :metodo, :usuario)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':tipo_asiento' => $tipoAsiento,
                ':metodo' => $metodo,
                ':usuario' => $idUsuario
            ]);
        }
    }
    /**
     * Obtiene las reglas específicas para tarifas de IVA (ventas) de la empresa.
     */
    public function getReglasIvaVentas(int $idEmpresa): array
    {
        $sql = "SELECT 0 AS id_asiento_tipo,
                       'ventas_factura' AS tipo_asiento,
                       'Tarifa iva ' || t.tarifa AS concepto,
                       'Tarifa de iva en ventas ' || t.tarifa AS detalle,
                       'IVA-' || t.codigo AS codigo,
                       'pasivo' AS tipo_cuenta,
                       'haber' AS debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       CAST(t.codigo AS INTEGER) AS id_referencia,
                       'iva_ventas_factura' AS tipo_referencia,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM tarifa_iva t
                LEFT JOIN {$this->table} ap ON ap.id_referencia = CAST(t.codigo AS INTEGER)
                                           AND ap.tipo_referencia = 'iva_ventas_factura'
                                           AND ap.id_empresa = :id_empresa 
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE t.porcentaje_iva > 0
                ORDER BY t.tarifa ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reglas de IVA por tarifa para COMPRAS (crédito tributario).
     * Espejo de getReglasIvaVentas, pero la cuenta es de naturaleza ACTIVO (IVA crédito
     * tributario) y va al DEBE. tipo_referencia = 'iva_compras_factura'.
     */
    public function getReglasIvaCompras(int $idEmpresa): array
    {
        $sql = "SELECT 0 AS id_asiento_tipo,
                       'adquisiciones_compras' AS tipo_asiento,
                       'Tarifa iva ' || t.tarifa AS concepto,
                       'IVA crédito tributario tarifa ' || t.tarifa AS detalle,
                       'IVA-' || t.codigo AS codigo,
                       'activo' AS tipo_cuenta,
                       'debe' AS debe_haber,
                       ap.id AS id_programado,
                       ap.id_cuenta,
                       CAST(t.codigo AS INTEGER) AS id_referencia,
                       'iva_compras_factura' AS tipo_referencia,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM tarifa_iva t
                LEFT JOIN {$this->table} ap ON ap.id_referencia = CAST(t.codigo AS INTEGER)
                                           AND ap.tipo_referencia = 'iva_compras_factura'
                                           AND ap.id_empresa = :id_empresa
                                           AND ap.eliminado = false
                LEFT JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                WHERE t.porcentaje_iva > 0
                ORDER BY t.tarifa ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una regla específica de Tarifa IVA para ventas.
     */
    public function getReglaGeneralIva(int $idEmpresa, int $idTarifa): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND id_referencia = :id_ref 
                  AND tipo_referencia = 'iva_ventas_factura' 
                  AND eliminado = false 
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':id_ref'     => $idTarifa
        ]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene el listado de retenciones SRI aplicadas en ventas que le hayan hecho a la empresa,
     * cruzando con su homóloga programada en asientos_programados (Debe) y con la cuenta de cobrar facturas
     * de venta (Haber - PORCOBRARFACTURAVENTA).
     */
    public function getReglasRetencionesVenta(int $idEmpresa): array
    {
        // 1. Obtener la cuenta de cuentas por cobrar para ventas (PORCOBRARFACTURAVENTA)
        $sqlHaber = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                     FROM asientos_programados ap
                     INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                     INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                     WHERE ap.id_empresa = :id_empresa 
                       AND at.codigo = 'PORCOBRARFACTURAVENTA'
                       AND ap.eliminado = false
                       AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = 'ventas_factura' OR ap.tipo_referencia = at.tipo_asiento)
                     LIMIT 1";
        $stHaber = $this->db->prepare($sqlHaber);
        $stHaber->execute([':id_empresa' => $idEmpresa]);
        $haberDefecto = $stHaber->fetch(PDO::FETCH_ASSOC) ?: [
            'id_cuenta' => null,
            'cuenta_codigo' => '',
            'cuenta_nombre' => 'No Configurada'
        ];

        // 2. Obtener los conceptos de retenciones sri que han sido usados en retenciones en venta de esta empresa en su ambiente actual (únicos por código de retención)
        $sqlConceptos = "SELECT DISTINCT ON (rs.codigo_ret) rs.id, rs.codigo_ret, rs.concepto_ret, rs.impuesto_ret
                         FROM retencion_venta_detalle d
                         INNER JOIN retencion_venta_cabecera c ON c.id = d.id_retencion
                         INNER JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                         WHERE c.id_empresa = :id_empresa 
                           AND c.eliminado = false
                           AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                         ORDER BY rs.codigo_ret ASC, rs.id DESC";
        $stConceptos = $this->db->prepare($sqlConceptos);
        $stConceptos->execute([':id_empresa' => $idEmpresa]);
        $conceptos = $stConceptos->fetchAll(PDO::FETCH_ASSOC);

        $reglas = [];
        foreach ($conceptos as $c) {
            // Buscar la cuenta Debe configurada en asientos_programados para esta retención
            // Buscamos 'retenciones_venta_debe' o 'retenciones_venta' (por retrocompatibilidad)
            $sqlDebe = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                        FROM asientos_programados ap
                        INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                        WHERE ap.id_empresa = :id_empresa
                          AND (ap.tipo_referencia = 'retenciones_venta_debe' OR ap.tipo_referencia = 'retenciones_venta')
                          AND ap.id_referencia = :id_referencia
                          AND ap.eliminado = false
                        ORDER BY ap.tipo_referencia DESC, ap.id DESC LIMIT 1";
            $stDebe = $this->db->prepare($sqlDebe);
            $stDebe->execute([
                ':id_empresa' => $idEmpresa,
                ':id_referencia' => $c['id']
            ]);
            $debeRow = $stDebe->fetch(PDO::FETCH_ASSOC) ?: [
                'id_programado' => null,
                'id_cuenta' => null,
                'cuenta_codigo' => '',
                'cuenta_nombre' => ''
            ];

            // Buscar la cuenta Haber configurada específicamente para esta retención
            $sqlHaberEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                            FROM asientos_programados ap
                            INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                            WHERE ap.id_empresa = :id_empresa
                              AND ap.tipo_referencia = 'retenciones_venta_haber'
                              AND ap.id_referencia = :id_referencia
                              AND ap.eliminado = false
                            LIMIT 1";
            $stHaberEsp = $this->db->prepare($sqlHaberEsp);
            $stHaberEsp->execute([
                ':id_empresa' => $idEmpresa,
                ':id_referencia' => $c['id']
            ]);
            $haberEspRow = $stHaberEsp->fetch(PDO::FETCH_ASSOC);

            // Si hay cuenta Haber específica guardada, la usamos; si no, usamos la de autocompletado por defecto
            $haberId = $haberEspRow ? $haberEspRow['id_cuenta'] : $haberDefecto['id_cuenta'];
            $haberCodigo = $haberEspRow ? $haberEspRow['cuenta_codigo'] : $haberDefecto['cuenta_codigo'];
            $haberNombre = $haberEspRow ? $haberEspRow['cuenta_nombre'] : $haberDefecto['cuenta_nombre'];
            $haberProgramadoId = $haberEspRow ? $haberEspRow['id_programado'] : null;

            $reglas[] = [
                'id_asiento_tipo'   => 0,
                'tipo_asiento'      => 'retenciones_venta',
                'concepto'          => $c['concepto_ret'],
                'detalle'           => $c['codigo_ret'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_ret'],
                'tipo_cuenta'       => 'activo',
                'debe_haber'        => 'debe',

                // Datos del Debe
                'id_programado'     => $debeRow['id_programado'],
                'id_cuenta'         => $debeRow['id_cuenta'],
                'cuenta_codigo'     => $debeRow['cuenta_codigo'],
                'cuenta_nombre'     => $debeRow['cuenta_nombre'],
                'id_referencia'     => $c['id'],
                'tipo_referencia'   => 'retenciones_venta_debe',

                // Datos del Haber
                'haber_id_programado'=> $haberProgramadoId,
                'haber_id_cuenta'   => $haberId,
                'haber_cuenta_codigo'=> $haberCodigo,
                'haber_cuenta_nombre'=> $haberNombre,
                'haber_is_custom'    => $haberEspRow ? true : false
            ];
        }

        return $reglas;
    }

    /**
     * Retenciones SRI que la empresa EFECTÚA en compras (al proveedor), cruzando con su homóloga
     * programada. En compras la retención es un PASIVO, por lo que se invierten los lados respecto a
     * ventas: Debe = Cuentas por Pagar (contraparte, por defecto PORPAGARFACTURACOMPRA) y Haber =
     * Retención por pagar (cuenta específica por concepto de retención).
     */
    public function getReglasRetencionesCompra(int $idEmpresa): array
    {
        // 1. Cuenta por pagar por defecto (PORPAGARFACTURACOMPRA) → contraparte del lado Debe.
        $sqlDebe = "SELECT ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                    FROM asientos_programados ap
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    WHERE ap.id_empresa = :id_empresa
                      AND at.codigo = 'PORPAGARFACTURACOMPRA'
                      AND ap.eliminado = false
                      AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = 'adquisiciones_compras' OR ap.tipo_referencia = at.tipo_asiento)
                    LIMIT 1";
        $stDebe = $this->db->prepare($sqlDebe);
        $stDebe->execute([':id_empresa' => $idEmpresa]);
        $debeDefecto = $stDebe->fetch(PDO::FETCH_ASSOC) ?: [
            'id_cuenta' => null,
            'cuenta_codigo' => '',
            'cuenta_nombre' => 'No Configurada'
        ];

        // 2. Conceptos de retención usados en compras de esta empresa (ambiente actual, únicos por código).
        $sqlConceptos = "SELECT DISTINCT ON (rs.codigo_ret) rs.id, rs.codigo_ret, rs.concepto_ret, rs.impuesto_ret
                         FROM retencion_compra_detalle d
                         INNER JOIN retencion_compra_cabecera c ON c.id = d.id_retencion
                         INNER JOIN retenciones_sri rs ON rs.codigo_ret = d.codigo_retencion
                         WHERE c.id_empresa = :id_empresa
                           AND c.eliminado = false
                           AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                         ORDER BY rs.codigo_ret ASC, rs.id DESC";
        $stConceptos = $this->db->prepare($sqlConceptos);
        $stConceptos->execute([':id_empresa' => $idEmpresa]);
        $conceptos = $stConceptos->fetchAll(PDO::FETCH_ASSOC);

        $reglas = [];
        foreach ($conceptos as $c) {
            // HABER: cuenta de la retención por pagar (específica por concepto).
            $sqlHaberEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                            FROM asientos_programados ap
                            INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                            WHERE ap.id_empresa = :id_empresa
                              AND ap.tipo_referencia = 'retenciones_compra_haber'
                              AND ap.id_referencia = :id_referencia
                              AND ap.eliminado = false
                            ORDER BY ap.id DESC LIMIT 1";
            $stHaberEsp = $this->db->prepare($sqlHaberEsp);
            $stHaberEsp->execute([':id_empresa' => $idEmpresa, ':id_referencia' => $c['id']]);
            $haberRow = $stHaberEsp->fetch(PDO::FETCH_ASSOC) ?: [
                'id_programado' => null,
                'id_cuenta' => null,
                'cuenta_codigo' => '',
                'cuenta_nombre' => ''
            ];

            // DEBE: cuenta por pagar. Específica por concepto si existe; si no, el default.
            $sqlDebeEsp = "SELECT ap.id AS id_programado, ap.id_cuenta, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                           FROM asientos_programados ap
                           INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                           WHERE ap.id_empresa = :id_empresa
                             AND ap.tipo_referencia = 'retenciones_compra_debe'
                             AND ap.id_referencia = :id_referencia
                             AND ap.eliminado = false
                           LIMIT 1";
            $stDebeEsp = $this->db->prepare($sqlDebeEsp);
            $stDebeEsp->execute([':id_empresa' => $idEmpresa, ':id_referencia' => $c['id']]);
            $debeEspRow = $stDebeEsp->fetch(PDO::FETCH_ASSOC);

            $debeId     = $debeEspRow ? $debeEspRow['id_cuenta'] : $debeDefecto['id_cuenta'];
            $debeCodigo = $debeEspRow ? $debeEspRow['cuenta_codigo'] : $debeDefecto['cuenta_codigo'];
            $debeNombre = $debeEspRow ? $debeEspRow['cuenta_nombre'] : $debeDefecto['cuenta_nombre'];
            $debeProgId = $debeEspRow ? $debeEspRow['id_programado'] : null;

            $reglas[] = [
                'id_asiento_tipo'   => 0,
                'tipo_asiento'      => 'retenciones_compra',
                'concepto'          => $c['concepto_ret'],
                'detalle'           => $c['codigo_ret'] . ' - ' . $c['impuesto_ret'],
                'codigo'            => $c['codigo_ret'],
                'tipo_cuenta'       => 'pasivo',
                'debe_haber'        => 'haber',

                // Datos del Debe (Cuentas por Pagar proveedores)
                'id_programado'     => $debeProgId,
                'id_cuenta'         => $debeId,
                'cuenta_codigo'     => $debeCodigo,
                'cuenta_nombre'     => $debeNombre,
                'id_referencia'     => $c['id'],
                'tipo_referencia'   => 'retenciones_compra_debe',
                'debe_is_custom'    => $debeEspRow ? true : false,

                // Datos del Haber (Retención por pagar)
                'haber_id_programado'=> $haberRow['id_programado'],
                'haber_id_cuenta'   => $haberRow['id_cuenta'],
                'haber_cuenta_codigo'=> $haberRow['cuenta_codigo'],
                'haber_cuenta_nombre'=> $haberRow['cuenta_nombre'],
                'haber_is_custom'    => $haberRow['id_programado'] ? true : false
            ];
        }

        return $reglas;
    }
}

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
                    created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :id_asiento_tipo, :id_cuenta, :id_referencia, :tipo_referencia,
                    :created_by, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
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
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_asiento_tipo'  => $data['id_asiento_tipo'],
            ':id_cuenta'        => $data['id_cuenta'],
            ':id_referencia'    => $data['id_referencia'] ?: null,
            ':tipo_referencia'  => $data['tipo_referencia'] ?: null,
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
    public function existeRegla(int $idEmpresa, int $idAsientoTipo, ?int $idReferencia, ?string $tipoReferencia, ?int $idExcluir = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND id_asiento_tipo = :id_asiento_tipo 
                  AND eliminado = false";
                  
        $params = [
            ':id_empresa' => $idEmpresa,
            ':id_asiento_tipo' => $idAsientoTipo
        ];

        if ($idReferencia !== null && $idReferencia > 0) {
            if ($tipoReferencia !== 'cliente' && $tipoReferencia !== 'proveedor' && $tipoReferencia !== 'producto' && $tipoReferencia !== 'categoria' && $tipoReferencia !== 'marca' && $tipoReferencia !== 'iva') {
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
}

<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\models\AsientoContableCabecera;
use App\models\AsientoContableDetalle;

class AsientoContableRepository
{
    private AsientoContableCabecera $modelCabecera;
    private AsientoContableDetalle $modelDetalle;

    public function __construct()
    {
        $this->modelCabecera = new AsientoContableCabecera();
        $this->modelDetalle = new AsientoContableDetalle();
        try {
            $pdo = \App\core\Database::getConnection();
            $pdo->exec("ALTER TABLE asientos_contables_cabecera ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) DEFAULT '1'");
        } catch (\Throwable $e) {}
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT id, fecha_asiento, tipo_comprobante, numero_comprobante, concepto, estado, modulo_origen, total_debe, total_haber 
                FROM asientos_contables_cabecera 
                WHERE id_empresa = :id_empresa AND eliminado = false 
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
                
        $params = [':id_empresa' => $idEmpresa];

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $sql .= " AND (numero_comprobante ILIKE :b OR concepto ILIKE :b OR modulo_origen ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($sql, $params, $parsed['filtros'], [
            'texto'    => [
                'concepto' => 'concepto',
                'numero'   => 'numero_comprobante',
                'origen'   => 'modulo_origen',
            ],
            'exacto'   => [
                'estado' => 'estado',
                'tipo'   => 'tipo_comprobante',
            ],
            'fecha'    => [
                'fecha' => 'fecha_asiento',
            ],
            'numerico' => [
                'total' => 'total_debe',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $pdo = \App\core\Database::getConnection();
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $ordenColPermitidas = ['fecha_asiento', 'numero_comprobante', 'concepto', 'estado', 'total_debe'];
        $ordenCol = in_array($ordenCol, $ordenColPermitidas, true) ? $ordenCol : 'fecha_asiento';
        $ordenDir = in_array(strtoupper($ordenDir), ['ASC', 'DESC'], true) ? strtoupper($ordenDir) : 'DESC';

        $sql .= " ORDER BY {$ordenCol} {$ordenDir}";
        
        if ($perPage > 0) {
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }

        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getDetalleAsiento(int $idAsiento, int $idEmpresa): array
    {
        $sql = "SELECT a.* 
                FROM asientos_contables_cabecera a 
                WHERE a.id = :id AND a.id_empresa = :id_empresa AND a.eliminado = false
                AND a.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsiento, ':id_empresa' => $idEmpresa]);
        $cabecera = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cabecera) {
            return [];
        }

        // Obtener detalles con la cuenta contable
        $sqlDet = "SELECT d.*, c.codigo as codigo_cuenta, c.nombre as nombre_cuenta 
                   FROM asientos_contables_detalle d
                   LEFT JOIN plan_cuentas c ON d.id_cuenta_contable = c.id
                   WHERE d.id_asiento = :id_asiento AND d.eliminado = false
                   ORDER BY CASE WHEN d.debe > 0 THEN 1 ELSE 2 END ASC, d.id ASC";
        $stmtDet = $pdo->prepare($sqlDet);
        $stmtDet->execute([':id_asiento' => $idAsiento]);
        $cabecera['detalles'] = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);

        return $cabecera;
    }

    public function getAsientoPorOrigen(string $moduloOrigen, int $idReferenciaOrigen, int $idEmpresa): ?array
    {
        $sql = "SELECT id FROM asientos_contables_cabecera
                WHERE modulo_origen = :modulo AND id_referencia_origen = :id_ref
                AND id_empresa = :id_empresa AND eliminado = false AND estado != 'anulado'
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                ORDER BY id DESC LIMIT 1";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':modulo' => $moduloOrigen,
            ':id_ref' => $idReferenciaOrigen,
            ':id_empresa' => $idEmpresa
        ]);
        
        $id = $stmt->fetchColumn();
        if ($id) {
            return $this->getDetalleAsiento((int)$id, $idEmpresa);
        }
        return null;
    }

    public function generarNumeroComprobante(int $idEmpresa, string $tipoComprobante): string
    {
        $tipoComprobante = strtolower(trim($tipoComprobante));
        $prefijos = [
            'diario'              => 'DI',
            'apertura'            => 'AP',
            'cierre'              => 'CI',
            'adquisiciones'       => 'AD',
            'egresos'             => 'EG',
            'ingresos'            => 'IN',
            'ventas'              => 'VE',
            'retenciones_ventas'  => 'RV',
            'retenciones_compras' => 'RC',
            'nomina'              => 'NO',
        ];
        $prefijo = $prefijos[$tipoComprobante] ?? 'DI';

        $sql = "SELECT numero_comprobante FROM asientos_contables_cabecera 
                WHERE id_empresa = :id_empresa AND tipo_comprobante = :tipo
                ORDER BY id DESC LIMIT 1";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa, ':tipo' => $tipoComprobante]);
        $ultimo = $stmt->fetchColumn();

        $siguiente = 1;
        if ($ultimo) {
            // Asume formato PREFIJO-0000001
            $partes = explode('-', $ultimo);
            if (count($partes) === 2) {
                $siguiente = (int)$partes[1] + 1;
            }
        }

        return $prefijo . '-' . str_pad((string)$siguiente, 6, '0', STR_PAD_LEFT);
    }

    public function insertCabecera(array $data): int
    {
        $sql = "INSERT INTO asientos_contables_cabecera (
            id_empresa, fecha_asiento, tipo_comprobante, numero_comprobante, 
            concepto, estado, modulo_origen, id_referencia_origen, total_debe, total_haber, observaciones, created_by, tipo_ambiente
        ) VALUES (
            :id_empresa, :fecha_asiento, :tipo_comprobante, :numero_comprobante, 
            :concepto, :estado, :modulo_origen, :id_referencia_origen, :total_debe, :total_haber, :observaciones, :created_by,
            (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
        ) RETURNING id";
        
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $data['id_empresa'],
            ':fecha_asiento' => $data['fecha_asiento'],
            ':tipo_comprobante' => $data['tipo_comprobante'],
            ':numero_comprobante' => $data['numero_comprobante'],
            ':concepto' => $data['concepto'],
            ':estado' => $data['estado'],
            ':modulo_origen' => $data['modulo_origen'],
            ':id_referencia_origen' => $data['id_referencia_origen'],
            ':total_debe' => $data['total_debe'],
            ':total_haber' => $data['total_haber'],
            ':observaciones' => $data['observaciones'],
            ':created_by' => $data['created_by'] ?? null
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function updateCabecera(int $id, array $data): void
    {
        $sql = "UPDATE asientos_contables_cabecera SET 
            fecha_asiento = :fecha_asiento, tipo_comprobante = :tipo_comprobante,
            concepto = :concepto, estado = :estado, modulo_origen = :modulo_origen,
            id_referencia_origen = :id_referencia_origen, total_debe = :total_debe,
            total_haber = :total_haber, observaciones = :observaciones,
            updated_by = :updated_by, updated_at = :updated_at,
            tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = (SELECT id_empresa FROM asientos_contables_cabecera WHERE id = :id))
            WHERE id = :id";
            
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':fecha_asiento' => $data['fecha_asiento'],
            ':tipo_comprobante' => $data['tipo_comprobante'],
            ':concepto' => $data['concepto'],
            ':estado' => $data['estado'],
            ':modulo_origen' => $data['modulo_origen'],
            ':id_referencia_origen' => $data['id_referencia_origen'],
            ':total_debe' => $data['total_debe'],
            ':total_haber' => $data['total_haber'],
            ':observaciones' => $data['observaciones'],
            ':updated_by' => $data['updated_by'] ?? null,
            ':updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    public function deleteDetalles(int $idAsiento): void
    {
        $sql = "DELETE FROM asientos_contables_detalle WHERE id_asiento = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idAsiento]);
    }

    public function insertDetalle(array $data): void
    {
        $sql = "INSERT INTO asientos_contables_detalle (
            id_empresa, id_asiento, id_cuenta_contable, id_centro_costo, id_proyecto,
            debe, haber, referencia_detalle, documento_referencia, id_entidad, tipo_entidad, created_by
        ) VALUES (
            :id_empresa, :id_asiento, :id_cuenta_contable, :id_centro_costo, :id_proyecto,
            :debe, :haber, :referencia_detalle, :documento_referencia, :id_entidad, :tipo_entidad, :created_by
        )";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_asiento' => $data['id_asiento'],
            ':id_cuenta_contable' => $data['id_cuenta_contable'],
            ':id_centro_costo' => $data['id_centro_costo'],
            ':id_proyecto' => $data['id_proyecto'],
            ':debe' => $data['debe'],
            ':haber' => $data['haber'],
            ':referencia_detalle' => $data['referencia_detalle'],
            ':documento_referencia' => $data['documento_referencia'],
            ':id_entidad' => $data['id_entidad'],
            ':tipo_entidad' => $data['tipo_entidad'],
            ':created_by' => $data['created_by'] ?? null
        ]);
    }

    public function updateEstado(int $idAsiento, string $estado, int $updatedBy): void
    {
        $sql = "UPDATE asientos_contables_cabecera SET estado = :estado, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idAsiento,
            ':estado' => $estado,
            ':updated_by' => $updatedBy
        ]);
    }

    /**
     * Desvincula el asiento contable de una factura de venta (pone id_asiento_contable = NULL).
     */
    public function desvincularAsientoVenta(int $idVenta): void
    {
        $sql = "UPDATE ventas_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idVenta]);
    }

    public function desvincularAsientoRetencionVenta(int $idRetencion): void
    {
        $sql = "UPDATE retencion_venta_cabecera SET id_asiento_contable = NULL WHERE id = :id";
        $pdo = \App\core\Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idRetencion]);
    }
}

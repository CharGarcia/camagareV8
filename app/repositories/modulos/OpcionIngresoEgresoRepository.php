<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;
use Exception;

class OpcionIngresoEgresoRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('empresa_opciones_ingreso_egreso');
        $this->runMigrations();
    }

    private function runMigrations(): void
    {
        try {
            $q = $this->db->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'empresa_opciones_ingreso_egreso'");
            if (!$q->fetch()) {
                $sql = "
                    CREATE TABLE empresa_opciones_ingreso_egreso (
                        id SERIAL PRIMARY KEY,
                        id_empresa INT NOT NULL,
                        nombre VARCHAR(200) NOT NULL,
                        aplica_ingresos BOOLEAN DEFAULT FALSE,
                        aplica_egresos BOOLEAN DEFAULT FALSE,
                        comportamiento VARCHAR(50) DEFAULT 'GENERAL',
                        id_cuenta_contable INT NULL REFERENCES plan_cuentas(id) ON DELETE SET NULL,
                        estado VARCHAR(20) DEFAULT 'ACTIVO',
                        
                        eliminado BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        deleted_at TIMESTAMP NULL,
                        created_by INT NULL,
                        updated_by INT NULL,
                        deleted_by INT NULL
                    );
                    CREATE INDEX idx_opciones_ingreso_egreso_empresa ON empresa_opciones_ingreso_egreso(id_empresa, eliminado);
                ";
                $this->db->exec($sql);
            } else {
                // Inyectar columna comportamineto si no existe
                $check = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'empresa_opciones_ingreso_egreso' AND column_name = 'comportamiento'");
                if (!$check->fetch()) {
                    $this->db->exec("ALTER TABLE empresa_opciones_ingreso_egreso ADD COLUMN comportamiento VARCHAR(50) DEFAULT 'GENERAL'");
                }
            }
        } catch (Exception $e) {
            // Silent catch for runtime safety
        }
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE t.id_empresa = :id_empresa AND t.eliminado = FALSE";
        if ($buscar !== '') {
            $where .= " AND (t.nombre ILIKE :buscar)";
            $params[':buscar'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM empresa_opciones_ingreso_egreso t $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $validCols = ['id', 'nombre', 'aplica_ingresos', 'aplica_egresos', 'estado', 'created_at'];
        if (!in_array($ordenCol, $validCols)) {
            $ordenCol = 'nombre';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT t.*, 
                       pc.codigo AS cuenta_codigo, 
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso t
                LEFT JOIN plan_cuentas pc ON t.id_cuenta_contable = pc.id
                $where
                ORDER BY t.$ordenCol $ordenDir
                LIMIT :limit OFFSET :offset";
        
        $st = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $st->bindValue($key, $val);
        }
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT t.*, 
                       pc.codigo AS cuenta_codigo, 
                       pc.nombre AS cuenta_nombre
                FROM empresa_opciones_ingreso_egreso t
                LEFT JOIN plan_cuentas pc ON t.id_cuenta_contable = pc.id
                WHERE t.id = :id AND t.id_empresa = :id_empresa AND t.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $res = $st->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO empresa_opciones_ingreso_egreso (
                    id_empresa, nombre, aplica_ingresos, aplica_egresos, comportamiento, id_cuenta_contable, estado, created_by
                ) VALUES (
                    :id_empresa, :nombre, :ingresos, :egresos, :comp, :id_cuenta, :estado, :usr
                ) RETURNING id";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => (int) $data['id_empresa'],
            ':nombre'     => trim($data['nombre']),
            ':ingresos'   => !empty($data['aplica_ingresos']) ? 'true' : 'false',
            ':egresos'    => !empty($data['aplica_egresos']) ? 'true' : 'false',
            ':comp'       => $data['comportamiento'] ?? 'GENERAL',
            ':id_cuenta'  => !empty($data['id_cuenta_contable']) ? (int)$data['id_cuenta_contable'] : null,
            ':estado'     => $data['estado'] ?? 'ACTIVO',
            ':usr'        => (int)$data['id_usuario']
        ]);
        return (int)$st->fetchColumn();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE empresa_opciones_ingreso_egreso SET
                    nombre = :nombre,
                    aplica_ingresos = :ingresos,
                    aplica_egresos = :egresos,
                    comportamiento = :comp,
                    id_cuenta_contable = :id_cuenta,
                    estado = :estado,
                    updated_by = :usr,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'     => trim($data['nombre']),
            ':ingresos'   => !empty($data['aplica_ingresos']) ? 'true' : 'false',
            ':egresos'    => !empty($data['aplica_egresos']) ? 'true' : 'false',
            ':comp'       => $data['comportamiento'] ?? 'GENERAL',
            ':id_cuenta'  => !empty($data['id_cuenta_contable']) ? (int)$data['id_cuenta_contable'] : null,
            ':estado'     => $data['estado'] ?? 'ACTIVO',
            ':usr'        => (int)$data['id_usuario'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    /**
     * Actualiza únicamente la cuenta contable asignada a una opción.
     * Usado desde Configuración Contable para sincronizar la cuenta de la opción.
     */
    public function updateCuentaContable(int $id, int $idEmpresa, ?int $idCuenta, int $idUsuario): bool
    {
        $sql = "UPDATE empresa_opciones_ingreso_egreso SET
                    id_cuenta_contable = :id_cuenta,
                    updated_by = :usr,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_cuenta'  => $idCuenta !== null && $idCuenta > 0 ? $idCuenta : null,
            ':usr'        => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    public function logicalDelete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE empresa_opciones_ingreso_egreso SET
                    eliminado = TRUE,
                    deleted_by = :usr,
                    deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':usr' => $idUsuario,
            ':id' => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    public function estaUsado(int $id, int $idEmpresa): bool
    {
        // 1. Verificar en ingresos_cabecera
        $sqlIng = "SELECT COUNT(*) FROM ingresos_cabecera 
                   WHERE id_ingreso_concepto = :id 
                     AND id_empresa = :id_empresa 
                     AND eliminado = FALSE";
        $stIng = $this->db->prepare($sqlIng);
        $stIng->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        if ((int)$stIng->fetchColumn() > 0) {
            return true;
        }

        // 2. Verificar en egresos_cabecera
        $sqlEgr = "SELECT COUNT(*) FROM egresos_cabecera 
                   WHERE id_egreso_concepto = :id 
                     AND id_empresa = :id_empresa 
                     AND eliminado = FALSE";
        $stEgr = $this->db->prepare($sqlEgr);
        $stEgr->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        if ((int)$stEgr->fetchColumn() > 0) {
            return true;
        }

        return false;
    }
}

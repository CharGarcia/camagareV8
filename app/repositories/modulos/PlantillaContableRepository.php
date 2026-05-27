<?php
declare(strict_types=1);

namespace App\repositories\modulos;

class PlantillaContableRepository
{
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT id, modulo_origen, nombre, tipo_comprobante, concepto_predeterminado, status 
                FROM asientos_plantillas_cabecera 
                WHERE id_empresa = :id_empresa AND eliminado = false";
                
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $sql .= " AND (nombre ILIKE :buscar OR modulo_origen ILIKE :buscar)";
            $params[':buscar'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM ($sql) as sub";
        $pdo = \App\core\Database::getConnection();
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $ordenColPermitidas = ['modulo_origen', 'nombre', 'tipo_comprobante', 'status'];
        $ordenCol = in_array($ordenCol, $ordenColPermitidas, true) ? $ordenCol : 'nombre';
        $ordenDir = in_array(strtoupper($ordenDir), ['ASC', 'DESC'], true) ? strtoupper($ordenDir) : 'ASC';

        $sql .= " ORDER BY {$ordenCol} {$ordenDir}";
        
        if ($perPage > 0) {
            $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPlantilla(int $id, int $idEmpresa): array
    {
        $pdo = \App\core\Database::getConnection();
        
        $sqlCab = "SELECT * FROM asientos_plantillas_cabecera WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $stmtCab = $pdo->prepare($sqlCab);
        $stmtCab->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $cabecera = $stmtCab->fetch(\PDO::FETCH_ASSOC);

        if (!$cabecera) return [];

        $sqlDet = "SELECT d.*, c.codigo as cuenta_codigo, c.nombre as cuenta_nombre 
                   FROM asientos_plantillas_detalle d
                   LEFT JOIN plan_cuentas c ON d.id_cuenta_defecto = c.id
                   WHERE d.id_plantilla = :id_plantilla AND d.eliminado = false
                   ORDER BY d.id ASC";
        $stmtDet = $pdo->prepare($sqlDet);
        $stmtDet->execute([':id_plantilla' => $id]);
        
        $cabecera['detalles'] = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);
        return $cabecera;
    }

    public function guardarPlantilla(array $cabecera, array $detalles, int $idEmpresa, int $idUsuario): int
    {
        $pdo = \App\core\Database::getConnection();
        
        try {
            $pdo->beginTransaction();

            $idPlantilla = $cabecera['id'] ?? 0;
            $isUpdate = $idPlantilla > 0;

            if ($isUpdate) {
                $sqlCab = "UPDATE asientos_plantillas_cabecera 
                           SET modulo_origen = :modulo, nombre = :nombre, tipo_comprobante = :tipo_comprobante, 
                               concepto_predeterminado = :concepto, status = :status, 
                               updated_by = :usuario, updated_at = NOW()
                           WHERE id = :id AND id_empresa = :id_empresa";
                $stmt = $pdo->prepare($sqlCab);
                $stmt->execute([
                    ':modulo' => $cabecera['modulo_origen'],
                    ':nombre' => $cabecera['nombre'],
                    ':tipo_comprobante' => $cabecera['tipo_comprobante'],
                    ':concepto' => $cabecera['concepto_predeterminado'],
                    ':status' => $cabecera['status'],
                    ':usuario' => $idUsuario,
                    ':id' => $idPlantilla,
                    ':id_empresa' => $idEmpresa
                ]);
            } else {
                $sqlCab = "INSERT INTO asientos_plantillas_cabecera 
                           (id_empresa, modulo_origen, nombre, tipo_comprobante, concepto_predeterminado, status, created_by) 
                           VALUES (:id_empresa, :modulo, :nombre, :tipo_comprobante, :concepto, :status, :usuario) RETURNING id";
                $stmt = $pdo->prepare($sqlCab);
                $stmt->execute([
                    ':id_empresa' => $idEmpresa,
                    ':modulo' => $cabecera['modulo_origen'],
                    ':nombre' => $cabecera['nombre'],
                    ':tipo_comprobante' => $cabecera['tipo_comprobante'],
                    ':concepto' => $cabecera['concepto_predeterminado'],
                    ':status' => $cabecera['status'],
                    ':usuario' => $idUsuario
                ]);
                $idPlantilla = (int)$stmt->fetchColumn();
            }

            // Manejar detalles (eliminación física para mantenerlo simple y evitar basura, como en los asientos contables manuales)
            if ($isUpdate) {
                $sqlDel = "DELETE FROM asientos_plantillas_detalle WHERE id_plantilla = :id_plantilla";
                $stmtDel = $pdo->prepare($sqlDel);
                $stmtDel->execute([':id_plantilla' => $idPlantilla]);
            }

            $sqlInsDet = "INSERT INTO asientos_plantillas_detalle 
                          (id_empresa, id_plantilla, tipo_linea, naturaleza, id_cuenta_defecto, origen_dinamico, agrupar_por, valor_origen, created_by)
                          VALUES (:id_empresa, :id_plantilla, :tipo_linea, :naturaleza, :id_cuenta_defecto, :origen_dinamico, :agrupar_por, :valor_origen, :usuario)";
            $stmtInsDet = $pdo->prepare($sqlInsDet);

            foreach ($detalles as $det) {
                $stmtInsDet->execute([
                    ':id_empresa' => $idEmpresa,
                    ':id_plantilla' => $idPlantilla,
                    ':tipo_linea' => $det['tipo_linea'],
                    ':naturaleza' => $det['naturaleza'],
                    ':id_cuenta_defecto' => $det['id_cuenta_defecto'] ?: null,
                    ':origen_dinamico' => $det['origen_dinamico'] ? 'true' : 'false',
                    ':agrupar_por' => $det['agrupar_por'],
                    ':valor_origen' => $det['valor_origen'],
                    ':usuario' => $idUsuario
                ]);
            }

            $pdo->commit();
            return $idPlantilla;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function eliminarPlantilla(int $id, int $idEmpresa, int $idUsuario): void
    {
        $pdo = \App\core\Database::getConnection();
        $sql = "UPDATE asientos_plantillas_cabecera 
                SET eliminado = true, deleted_by = :usuario, deleted_at = NOW() 
                WHERE id = :id AND id_empresa = :id_empresa";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
    }
}

<?php
/**
 * Modelo WhatsappPlantilla
 */

declare(strict_types=1);

namespace App\models;

use PDO;
use Exception;

class WhatsappPlantilla extends BaseModel
{
    protected string $table = 'whatsapp_plantillas';

    /**
     * Sincroniza (upsert) una plantilla proveniente de Meta.
     */
    public function upsertPlantilla(int $idEmpresa, array $data, int $idUsuario): bool
    {
        $metaId = $data['id'] ?? null;
        $nombre = $data['name'] ?? '';
        $categoria = $data['category'] ?? '';
        $idioma = $data['language'] ?? '';
        $estado = $data['status'] ?? '';
        $componentes = json_encode($data['components'] ?? []);

        if (empty($nombre)) {
            return false;
        }

        // Buscar si ya existe por nombre e id_empresa (y preferiblemente idioma)
        $sqlBusqueda = "SELECT id FROM {$this->table} WHERE id_empresa = :id_empresa AND nombre = :nombre AND idioma = :idioma";
        $stmtBusqueda = $this->db->prepare($sqlBusqueda);
        $stmtBusqueda->execute([
            ':id_empresa' => $idEmpresa,
            ':nombre' => $nombre,
            ':idioma' => $idioma
        ]);
        
        $existente = $stmtBusqueda->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            // Actualizar
            $sql = "UPDATE {$this->table} SET 
                        meta_id = :meta_id,
                        categoria = :categoria,
                        estado_meta = :estado,
                        componentes = :componentes,
                        updated_at = CURRENT_TIMESTAMP,
                        updated_by = :updated_by,
                        eliminado = FALSE,
                        status = TRUE
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':meta_id' => $metaId,
                ':categoria' => $categoria,
                ':estado' => $estado,
                ':componentes' => $componentes,
                ':updated_by' => $idUsuario,
                ':id' => $existente['id']
            ]);
        } else {
            // Insertar
            $sql = "INSERT INTO {$this->table} (id_empresa, meta_id, nombre, categoria, idioma, estado_meta, componentes, created_by)
                    VALUES (:id_empresa, :meta_id, :nombre, :categoria, :idioma, :estado, :componentes, :created_by)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':meta_id' => $metaId,
                ':nombre' => $nombre,
                ':categoria' => $categoria,
                ':idioma' => $idioma,
                ':estado' => $estado,
                ':componentes' => $componentes,
                ':created_by' => $idUsuario
            ]);
        }
    }

    /**
     * Obtiene las plantillas de una empresa, aplicando filtros.
     */
    public function getFiltradas(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        
        $where = "WHERE id_empresa = :id_empresa AND eliminado = FALSE";
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= " AND (nombre ILIKE :buscar OR categoria ILIKE :buscar)";
            $params[':buscar'] = "%$buscar%";
        }

        $columnasPermitidas = ['nombre', 'categoria', 'idioma', 'estado_meta'];
        $ordenColFinal = in_array($ordenCol, $columnasPermitidas) ? $ordenCol : 'nombre';
        $ordenDirFinal = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        // Total
        $sqlTotal = "SELECT COUNT(*) FROM {$this->table} $where";
        $stmtTotal = $this->db->prepare($sqlTotal);
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        // Filas
        $sqlFilas = "SELECT * FROM {$this->table} 
                     $where 
                     ORDER BY $ordenColFinal $ordenDirFinal";
                     
        if ($perPage > 0) {
            $sqlFilas .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stmtFilas = $this->db->prepare($sqlFilas);
        $stmtFilas->execute($params);
        $rows = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows' => $rows
        ];
    }

    /**
     * Obtiene las plantillas aprobadas por Meta para usar en otros módulos.
     */
    public function getPlantillasAprobadas(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, categoria, idioma, componentes 
                FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND estado_meta = 'APPROVED' 
                  AND eliminado = FALSE 
                ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

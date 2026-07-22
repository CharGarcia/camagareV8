<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class BodegaRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre', 'status'];

    public function __construct()
    {
        parent::__construct('bodegas');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'b', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND b.nombre ILIKE :b";
            $params[':b'] = '%' . $buscar . '%';
        }

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} b {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT b.*
                    FROM {$this->table} b
                    {$whereSql}
                    ORDER BY b.{$ordenCol} {$dir}";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, nombre,
                    status, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :nombre,
                    :status, :eliminado, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $data['id_empresa'],
            ':id_usuario'   => $data['id_usuario'],
            ':created_by'   => $data['created_by'],
            ':nombre'       => $data['nombre'],
            ':status'       => $data['status'] ? 'true' : 'false',
            ':eliminado'    => 'false'
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                nombre = :nombre,
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'     => $data['nombre'],
            ':status'     => $data['status'] ? 'true' : 'false',
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT b.*,
                       u1.nombre AS creado_por_nombre,
                       u2.nombre AS actualizado_por_nombre
                FROM {$this->table} b
                LEFT JOIN usuarios u1 ON u1.id = b.created_by
                LEFT JOIN usuarios u2 ON u2.id = b.updated_by
                WHERE b.id = :id AND b.id_empresa = :id_empresa AND b.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function contarProductos(int $idBodega, int $idEmpresa): int
    {
        // Esta consulta atrapará excepciones en caso de que la tabla intermedia o inventario
        // aún no exista, retornando 0 para no quebrar el entorno hasta que se consolide el Kardex.
        try {
            $sql = "SELECT COUNT(DISTINCT id_producto) FROM productos_bodegas 
                    WHERE id_bodega = :id_bodega AND id_empresa = :id_empresa";
            $st = $this->db->prepare($sql);
            $st->execute([':id_bodega' => $idBodega, ':id_empresa' => $idEmpresa]);
            return (int) $st->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_by = :id_u,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'         => $id, 
            ':id_empresa' => $idEmpresa,
            ':id_u'       => $idUsuario
        ]);
    }

    /**
     * Obtiene todos los usuarios de la empresa y cruza con sus permisos de acceso a una bodega.
     */
    public function getUsuariosAcceso(int $idBodega, int $idEmpresa): array
    {
        // El acceso es por defecto: solo NO lo tiene quien esté marcado como
        // denegado explícitamente (modelo "lista de denegados").
        $sql = "SELECT u.id, u.nombre, u.mail,
                       (CASE WHEN COALESCE(ub.denegado, false) = true THEN false ELSE true END) AS tiene_acceso,
                       COALESCE(ub.es_default, false) AS es_default
                FROM usuarios u
                INNER JOIN empresa_asignada ea ON u.id = ea.id_usuario
                LEFT JOIN usuarios_bodegas ub ON u.id = ub.id_usuario AND ub.id_bodega = :id_bodega
                     AND ub.id_empresa = :id_empresa AND ub.eliminado = false
                WHERE ea.id_empresa = :id_empresa AND u.eliminado = false
                  AND COALESCE(u.nivel, 1) < 3
                ORDER BY u.nombre ASC";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_bodega'  => $idBodega,
            ':id_empresa' => $idEmpresa
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sincroniza los accesos de usuarios a una bodega específica.
     */
    public function saveUsuariosAcceso(int $idBodega, int $idEmpresa, array $accesos, int $idUsuarioLogueado): void
    {
        // Modelo "lista de denegados": el acceso a las bodegas es por defecto.
        // La UI envía SOLO los usuarios marcados (con acceso), así que hay que
        // registrar explícitamente como denegados a los usuarios de la empresa
        // que NO vinieron en la lista.
        $concedidos = [];
        $defaults   = [];
        foreach ($accesos as $acc) {
            if (empty($acc['id_usuario'])) continue;
            $uid = (int)$acc['id_usuario'];
            $concedidos[$uid] = true;
            $defaults[$uid]   = !empty($acc['es_default']);
        }

        // Universo de usuarios de la empresa (debe coincidir con getUsuariosAcceso:
        // el superadmin no se lista ni se gestiona aquí, siempre ve todas las bodegas).
        $stUsers = $this->db->prepare(
            "SELECT DISTINCT u.id
               FROM usuarios u
               INNER JOIN empresa_asignada ea ON u.id = ea.id_usuario
              WHERE ea.id_empresa = :id_e AND u.eliminado = false
                AND COALESCE(u.nivel, 1) < 3"
        );
        $stUsers->execute([':id_e' => $idEmpresa]);
        $todosUsuarios = $stUsers->fetchAll(PDO::FETCH_COLUMN);

        $sqlUpsert = "INSERT INTO usuarios_bodegas
                          (id_empresa, id_usuario, id_bodega, es_default, denegado, created_by, updated_by, eliminado)
                      VALUES (:id_e, :id_u, :id_b, :def, :den, :creBy, :creBy, false)
                      ON CONFLICT (id_empresa, id_usuario, id_bodega)
                      DO UPDATE SET
                        es_default = EXCLUDED.es_default,
                        denegado   = EXCLUDED.denegado,
                        eliminado  = false,
                        deleted_at = NULL,
                        deleted_by = NULL,
                        updated_by = EXCLUDED.updated_by,
                        updated_at = CURRENT_TIMESTAMP";

        $stUpsert = $this->db->prepare($sqlUpsert);
        foreach ($todosUsuarios as $uid) {
            $uid         = (int)$uid;
            $tieneAcceso = isset($concedidos[$uid]);
            $stUpsert->execute([
                ':id_e'  => $idEmpresa,
                ':id_u'  => $uid,
                ':id_b'  => $idBodega,
                ':def'   => ($tieneAcceso && !empty($defaults[$uid])) ? 'true' : 'false',
                ':den'   => $tieneAcceso ? 'false' : 'true',
                ':creBy' => $idUsuarioLogueado
            ]);
        }
    }

    /**
     * Limpia la marca de 'es_default' para un usuario en todas las bodegas de una empresa.
     * Útil antes de asignar un nuevo default.
     */
    public function clearDefaultForUser(int $idUsuario, int $idEmpresa): void
    {
        $sql = "UPDATE usuarios_bodegas SET es_default = false 
                WHERE id_usuario = :u AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':e' => $idEmpresa]);
    }

    /**
     * Retorna las bodegas permitidas para un usuario según su nivel y empresa.
     */
    public function getBodegasPermitidas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        if ($nivel >= 2) {
            // Admin y SuperAdmin ven todas
            $sql = "SELECT b.id, b.nombre, 
                           COALESCE(ub.es_default, false) as es_default
                    FROM bodegas b
                    LEFT JOIN usuarios_bodegas ub ON b.id = ub.id_bodega 
                         AND ub.id_usuario = :id_u AND ub.id_empresa = :id_e AND ub.eliminado = false
                    WHERE b.id_empresa = :id_e AND b.status = true AND b.eliminado = false
                    ORDER BY b.nombre ASC";
        } else {
            // Usuarios Nivel 1: por defecto ven TODAS las bodegas de la empresa.
            // Solo se excluyen aquellas con el acceso revocado explícitamente
            // (usuarios_bodegas.denegado = true). Sin registro = con acceso.
            $sql = "SELECT b.id, b.nombre,
                           COALESCE(ub.es_default, false) as es_default
                    FROM bodegas b
                    LEFT JOIN usuarios_bodegas ub ON b.id = ub.id_bodega
                         AND ub.id_usuario = :id_u AND ub.id_empresa = :id_e AND ub.eliminado = false
                    WHERE b.id_empresa = :id_e AND b.status = true AND b.eliminado = false
                      AND COALESCE(ub.denegado, false) = false
                    ORDER BY b.nombre ASC";
        }

        $st = $this->db->prepare($sql);
        $st->execute([':id_u' => $idUsuario, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

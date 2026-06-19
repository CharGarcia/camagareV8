<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class VendedorRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre', 'identificacion', 'correo', 'telefono', 'direccion', 'status'];

    public function __construct()
    {
        parent::__construct('vendedores');
    }

    /**
     * Obtiene solo los vendedores activos permitidos.
     */
    public function getVendedoresActivos(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        $where .= " AND v.status = 1"; // Fixed integer = boolean error
        
        $sql = "SELECT v.id, v.nombre 
                FROM {$this->table} v 
                $where 
                ORDER BY v.nombre ASC";
                
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de vendedores con filtros y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $where = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (v.nombre ILIKE :buscar OR v.identificacion ILIKE :buscar OR v.correo ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'         => 'v.nombre',
                'vendedor'       => 'v.nombre',
                'identificacion' => 'v.identificacion',
                'ci'             => 'v.identificacion',
                'email'          => 'v.correo',
                'correo'         => 'v.correo',
                'telefono'       => 'v.telefono',
                'direccion'      => 'v.direccion',
            ],
            'exacto' => [ 'estado' => 'v.estado' ],
        ]);

        // Obtener total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} v $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $limitOffset = "";
            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $limitOffset = " LIMIT $perPage OFFSET $offset";
            }
            $sql = "SELECT v.* 
                    FROM {$this->table} v
                    $where
                    ORDER BY v.{$ordenCol} $ordenDir
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Verifica si una identificación ya existe en la empresa.
     */
    public function existeIdentificacion(int $idEmpresa, string $identificacion, ?int $idExcluir = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND identificacion = :identificacion 
                  AND eliminado = false";
        $params = [
            ':id_empresa'    => $idEmpresa,
            ':identificacion' => $identificacion
        ];

        if ($idExcluir !== null) {
            $sql .= " AND id <> :id_exc";
            $params[':id_exc'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    /**
     * Inserta un nuevo vendedor con campos de auditoría.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, nombre, identificacion, telefono, correo, 
                    direccion, status, created_by, created_at, eliminado
                ) VALUES (
                    :id_empresa, :id_usuario, :nombre, :identificacion, :telefono, :correo, 
                    :direccion, :status, :id_u, CURRENT_TIMESTAMP, false
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':nombre'           => $data['nombre'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'] ?? null,
            ':correo'           => $data['correo'] ?? null,
            ':direccion'        => $data['direccion'] ?? null,
            ':status'           => $data['status'] ?? 1,
            ':id_u'             => $data['id_usuario']
        ]);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un vendedor con campos de auditoría.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                nombre = :nombre,
                identificacion = :identificacion,
                telefono = :telefono,
                correo = :correo,
                direccion = :direccion,
                status = :status,
                updated_by = :id_u,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'           => $data['nombre'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'] ?? null,
            ':correo'           => $data['correo'] ?? null,
            ':direccion'        => $data['direccion'] ?? null,
            ':status'           => $data['status'] ?? 1,
            ':id_u'             => $data['id_usuario'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica con campos de auditoría.
     */
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
     * Obtiene el detalle de un vendedor incluyendo nombres de auditoría.
     */
    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT v.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} v
                LEFT JOIN usuarios u_crea ON u_crea.id = v.created_by
                LEFT JOIN usuarios u_act ON u_act.id = v.updated_by
                WHERE v.id = :id AND v.id_empresa = :id_empresa AND v.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cuenta cuántos clientes activos tiene asignados el vendedor.
     */
    public function contarClientesAsignados(int $id, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM clientes 
                WHERE id_vendedor = :id 
                  AND id_empresa = :id_empresa 
                  AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }
}

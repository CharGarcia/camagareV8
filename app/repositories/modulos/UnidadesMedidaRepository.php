<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class UnidadesMedidaRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN_TIPOS    = ['codigo', 'nombre', 'status'];
    public const COLUMNAS_ORDEN_UNIDADES = ['codigo', 'nombre', 'abreviatura', 'factor_base', 'es_base', 'status'];

    public function __construct()
    {
        parent::__construct('tipo_medida');
    }

    // ─── TIPOS DE MEDIDA ────────────────────────────────────────────────────

    public function getTiposListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN_TIPOS, true) ? $ordenCol : 'nombre';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $where  = 'WHERE tm.id_empresa = :id_empresa AND tm.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= ' AND tm.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $where .= ' AND (tm.nombre ILIKE :b OR tm.codigo ILIKE :b)';
            $params[':b'] = '%' . $buscar . '%';
        }

        $sqlCount = "SELECT COUNT(*) FROM tipo_medida tm {$where}";
        $st = $this->db->prepare($sqlCount);
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT tm.id, tm.codigo, tm.nombre, tm.status,
                           tm.created_at, tm.updated_at, tm.created_by, tm.updated_by,
                           u_crea.nombre AS creado_por_nombre,
                           (SELECT COUNT(*) FROM unidades_medida um
                            WHERE um.id_tipo = tm.id
                              AND um.id_empresa = tm.id_empresa
                              AND um.eliminado = false) AS total_unidades
                    FROM tipo_medida tm
                    LEFT JOIN usuarios u_crea ON u_crea.id = tm.created_by
                    {$where}
                    ORDER BY tm.{$col} {$dir}";

        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getTipoById(int $id, int $idEmpresa): ?array
    {
        $sql = 'SELECT * FROM tipo_medida WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false';
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalleTipo(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT tm.*,
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre  AS actualizado_por_nombre,
                       (SELECT COUNT(*) FROM unidades_medida um
                        WHERE um.id_tipo = tm.id
                          AND um.id_empresa = tm.id_empresa
                          AND um.eliminado = false) AS total_unidades
                FROM tipo_medida tm
                LEFT JOIN usuarios u_crea ON u_crea.id = tm.created_by
                LEFT JOIN usuarios u_act  ON u_act.id  = tm.updated_by
                WHERE tm.id = :id AND tm.id_empresa = :id_empresa AND tm.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeNombreTipo(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT 1 FROM tipo_medida WHERE id_empresa = :id_empresa AND UPPER(nombre) = UPPER(:nombre) AND eliminado = false';
        $params = [':id_empresa' => $idEmpresa, ':nombre' => $nombre];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function tieneUnidades(int $idTipo, int $idEmpresa): bool
    {
        $sql = 'SELECT 1 FROM unidades_medida WHERE id_tipo = :id_tipo AND id_empresa = :id_empresa AND eliminado = false LIMIT 1';
        $st  = $this->db->prepare($sql);
        $st->execute([':id_tipo' => $idTipo, ':id_empresa' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    public function createTipo(array $data): int
    {
        $sql = "INSERT INTO tipo_medida
                    (id_empresa, id_usuario, codigo, nombre, status, eliminado, created_by, created_at)
                VALUES
                    (:id_empresa, :id_usuario, :codigo, :nombre, :status, false, :created_by, CURRENT_TIMESTAMP)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => $data['id_empresa'],
            ':id_usuario'  => $data['id_usuario'],
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':status'      => $data['status'] ? 'true' : 'false',
            ':created_by'  => $data['created_by'],
        ]);
        return $this->lastInsertId();
    }

    public function updateTipo(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE tipo_medida SET
                    codigo     = :codigo,
                    nombre     = :nombre,
                    status     = :status,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':status'      => $data['status'] ? 'true' : 'false',
            ':updated_by'  => $data['updated_by'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa,
        ]);
    }

    public function deleteTipo(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE tipo_medida SET
                    eliminado  = true,
                    deleted_by = :id_u,
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    // ─── UNIDADES DE MEDIDA ─────────────────────────────────────────────────

    public function getUnidadesListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $filtroTipo = null,
        ?int $idUsuarioFiltro = null
    ): array {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN_UNIDADES, true) ? $ordenCol : 'nombre';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $where  = 'WHERE um.id_empresa = :id_empresa AND um.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= ' AND um.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($filtroTipo !== null && $filtroTipo > 0) {
            $where .= ' AND um.id_tipo = :filtro_tipo';
            $params[':filtro_tipo'] = $filtroTipo;
        }

        if ($buscar !== '') {
            $where .= ' AND (um.nombre ILIKE :b OR um.codigo ILIKE :b OR um.abreviatura ILIKE :b OR tm.nombre ILIKE :b)';
            $params[':b'] = '%' . $buscar . '%';
        }

        $sqlCount = "SELECT COUNT(*) FROM unidades_medida um
                     LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo {$where}";
        $st = $this->db->prepare($sqlCount);
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $orderExpr = match($col) {
            'factor_base' => 'um.factor_base',
            default       => "um.{$col}",
        };

        $sqlRows = "SELECT um.id, um.id_tipo, um.codigo, um.nombre, um.abreviatura,
                           um.factor_base, um.es_base, um.status,
                           um.created_at, um.updated_at, um.created_by, um.updated_by,
                           tm.nombre AS tipo_nombre, tm.codigo AS tipo_codigo,
                           u_crea.nombre AS creado_por_nombre
                    FROM unidades_medida um
                    LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo
                    LEFT JOIN usuarios u_crea ON u_crea.id = um.created_by
                    {$where}
                    ORDER BY {$orderExpr} {$dir}";

        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getUnidadById(int $id, int $idEmpresa): ?array
    {
        $sql = 'SELECT * FROM unidades_medida WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false';
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalleUnidad(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT um.*,
                       tm.nombre AS tipo_nombre,
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre  AS actualizado_por_nombre
                FROM unidades_medida um
                LEFT JOIN tipo_medida tm ON tm.id = um.id_tipo
                LEFT JOIN usuarios u_crea ON u_crea.id = um.created_by
                LEFT JOIN usuarios u_act  ON u_act.id  = um.updated_by
                WHERE um.id = :id AND um.id_empresa = :id_empresa AND um.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeNombreUnidad(int $idEmpresa, string $nombre, int $idTipo, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT 1 FROM unidades_medida WHERE id_empresa = :id_empresa AND id_tipo = :id_tipo AND UPPER(nombre) = UPPER(:nombre) AND eliminado = false';
        $params = [':id_empresa' => $idEmpresa, ':id_tipo' => $idTipo, ':nombre' => $nombre];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function tieneBaseEnTipo(int $idTipo, int $idEmpresa, ?int $excluirId = null): bool
    {
        $sql = 'SELECT 1 FROM unidades_medida
                WHERE id_tipo = :id_tipo AND id_empresa = :id_empresa
                  AND es_base = true AND eliminado = false';
        $params = [':id_tipo' => $idTipo, ':id_empresa' => $idEmpresa];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function createUnidad(array $data): int
    {
        $sql = "INSERT INTO unidades_medida
                    (id_empresa, id_tipo, codigo, nombre, abreviatura, factor_base, es_base, status, eliminado, created_by, created_at)
                VALUES
                    (:id_empresa, :id_tipo, :codigo, :nombre, :abreviatura, :factor_base, :es_base, :status, false, :created_by, CURRENT_TIMESTAMP)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'  => $data['id_empresa'],
            ':id_tipo'     => $data['id_tipo'],
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':abreviatura' => $data['abreviatura'],
            ':factor_base' => $data['factor_base'],
            ':es_base'     => $data['es_base'] ? 'true' : 'false',
            ':status'      => $data['status'] ? 'true' : 'false',
            ':created_by'  => $data['created_by'],
        ]);
        return $this->lastInsertId();
    }

    public function updateUnidad(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE unidades_medida SET
                    id_tipo     = :id_tipo,
                    codigo      = :codigo,
                    nombre      = :nombre,
                    abreviatura = :abreviatura,
                    factor_base = :factor_base,
                    es_base     = :es_base,
                    status      = :status,
                    updated_by  = :updated_by,
                    updated_at  = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_tipo'     => $data['id_tipo'],
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':abreviatura' => $data['abreviatura'],
            ':factor_base' => $data['factor_base'],
            ':es_base'     => $data['es_base'] ? 'true' : 'false',
            ':status'      => $data['status'] ? 'true' : 'false',
            ':updated_by'  => $data['updated_by'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa,
        ]);
    }

    public function deleteUnidad(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE unidades_medida SET
                    eliminado  = true,
                    deleted_by = :id_u,
                    deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    /**
     * Verifica si la unidad de medida está en uso en productos o componentes.
     */
    public function estaEnUso(int $id, int $idEmpresa): bool
    {
        // 1. Verificar en productos
        $sqlP = "SELECT 1 FROM productos WHERE id_medida = :id AND id_empresa = :id_e AND eliminado = false LIMIT 1";
        $stP = $this->db->prepare($sqlP);
        $stP->execute([':id' => $id, ':id_e' => $idEmpresa]);
        if ($stP->fetchColumn()) return true;

        // 2. Verificar en componentes
        $sqlC = "SELECT 1 FROM productos_componentes WHERE id_medida = :id AND id_empresa = :id_e AND eliminado = false LIMIT 1";
        $stC = $this->db->prepare($sqlC);
        $stC->execute([':id' => $id, ':id_e' => $idEmpresa]);
        if ($stC->fetchColumn()) return true;

        return false;
    }

    // ─── SELECTORES / AUXILIARES ────────────────────────────────────────────

    /**
     * Tipos activos para dropdowns (modal de unidades, filtro de listado).
     */
    public function getTiposActivos(int $idEmpresa): array
    {
        $sql = 'SELECT id, codigo, nombre FROM tipo_medida
                WHERE id_empresa = :id_empresa AND status = true AND eliminado = false
                ORDER BY nombre ASC';
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CONVERSIÓN ─────────────────────────────────────────────────────────

    /**
     * Devuelve todas las unidades activas del mismo tipo que la unidad dada.
     * Usado por el módulo de ventas para el selector de unidades al facturar.
     */
    public function getUnidadesMismoTipo(int $idUnidad, int $idEmpresa): array
    {
        $sql = "SELECT um.id, um.nombre, um.abreviatura, um.factor_base, um.es_base
                FROM unidades_medida um
                WHERE um.id_tipo = (
                    SELECT id_tipo FROM unidades_medida WHERE id = :id_unidad AND id_empresa = :id_empresa AND eliminado = false
                )
                AND um.id_empresa = :id_empresa
                AND um.status = true
                AND um.eliminado = false
                ORDER BY um.es_base DESC, um.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_unidad' => $idUnidad, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los factores de conversión de dos unidades para calcular precio.
     * Retorna null si alguna unidad no existe o no pertenece a la empresa.
     */
    public function getFactoresConversion(int $idUnidadOrigen, int $idUnidadDestino, int $idEmpresa): ?array
    {
        $sql = "SELECT id, factor_base, es_base, nombre, abreviatura
                FROM unidades_medida
                WHERE id IN (:id_origen, :id_destino)
                  AND id_empresa = :id_empresa
                  AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_origen' => $idUnidadOrigen, ':id_destino' => $idUnidadDestino, ':id_empresa' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $origen  = null;
        $destino = null;
        foreach ($rows as $r) {
            if ((int)$r['id'] === $idUnidadOrigen)  $origen  = $r;
            if ((int)$r['id'] === $idUnidadDestino) $destino = $r;
        }

        if (!$origen || !$destino) return null;

        return ['origen' => $origen, 'destino' => $destino];
    }

    /**
     * Verifica si dos unidades pertenecen al mismo tipo de medida dentro de la empresa.
     */
    public function mismoTipo(int $idUnidadA, int $idUnidadB, int $idEmpresa): bool
    {
        $sql = "SELECT COUNT(DISTINCT id_tipo) AS tipos
                FROM unidades_medida
                WHERE id IN (:id_a, :id_b)
                AND id_empresa = :id_empresa
                AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_a' => $idUnidadA, ':id_b' => $idUnidadB, ':id_empresa' => $idEmpresa]);
        $tipos = (int) $st->fetchColumn();
        // Si ambas pertenecen al mismo tipo, COUNT(DISTINCT id_tipo) = 1
        return $tipos === 1;
    }

    public function getActive(int $idEmpresa): array
    {
        $sql = "SELECT id, id_tipo, nombre, abreviatura 
                FROM unidades_medida 
                WHERE id_empresa = :e AND eliminado = false AND status = true 
                ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

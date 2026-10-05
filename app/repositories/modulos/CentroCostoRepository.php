<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CentroCostoRepository extends BaseRepository
{
    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL. Es la whitelist del ORDER BY y el mapa que necesita
     * `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'codigo'      => 'cc.codigo',
        'nombre'      => 'cc.nombre',
        'descripcion' => 'cc.descripcion',
        'estado'      => 'cc.estado',
    ];

    /** @deprecated Usar MAPA_ORDEN; se conserva por compatibilidad. */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'estado'];

    public function __construct()
    {
        parent::__construct('centro_costos');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null,
        array $ordenMulti = []
    ): array {
        // Una o varias columnas (Shift+clic), validadas contra MAPA_ORDEN, con cc.id
        // como desempate para que las filas empatadas no bailen entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'cc.nombre', 'cc.id DESC');

        $whereSql = $this->getBaseWhere($idEmpresa, 'cc', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre sobre las columnas visibles (por palabras, sin tildes). Estado
            // queda fuera: se filtra desde el modal (estado:activo).
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto([
                'cc.codigo',
                'cc.nombre',
                'cc.descripcion',
            ], $parsed['texto_libre'], $params, 'cc_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'  => [
                'codigo'      => 'cc.codigo',
                'nombre'      => 'cc.nombre',
                'descripcion' => 'cc.descripcion',
            ],
            'exacto' => [
                'estado'  => 'cc.estado',
                'usuario' => 'cc.created_by',
            ],
            'fecha'  => [
                'registro'   => 'cc.created_at',
                'created_at' => 'cc.created_at',
            ],
        ]);

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} cc {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;

        $sqlRows = "SELECT cc.* FROM {$this->table} cc {$whereSql} {$orderBy}";

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

    /**
     * Opciones de los selects del modal de filtros: solo los usuarios que realmente
     * registraron centros de costo en la empresa.
     *
     * @return array{usuarios: array}
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT u.id, u.nombre
               FROM {$this->table} cc
               JOIN usuarios u ON u.id = cc.created_by
              WHERE cc.id_empresa = :id_empresa AND cc.eliminado = false
              ORDER BY u.nombre"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return ['usuarios' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, codigo, nombre, descripcion, estado,
                    eliminado, created_by, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :codigo, :nombre, :descripcion, :estado,
                    :eliminado, :created_by, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $data['id_empresa'],
            ':id_usuario'   => $data['id_usuario'],
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
            ':eliminado'    => 'false',
            ':created_by'   => $data['created_by']
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                estado = :estado,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
            ':updated_by'   => $data['updated_by'],
            ':id'           => $id,
            ':id_empresa'   => $idEmpresa
        ]);
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

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT cc.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} cc
                LEFT JOIN usuarios u_crea ON u_crea.id = cc.created_by
                LEFT JOIN usuarios u_act ON u_act.id = cc.updated_by
                WHERE cc.id = :id AND cc.id_empresa = :id_empresa AND cc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

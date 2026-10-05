<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProyectoRepository extends BaseRepository
{
    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL. Es la whitelist del ORDER BY y el mapa que necesita
     * `OrdenListado::clausula()` para encadenar varias columnas.
     */
    public const MAPA_ORDEN = [
        'codigo'               => 'p.codigo',
        'nombre'               => 'p.nombre',
        'descripcion'          => 'p.descripcion',
        'estado'               => 'p.estado',
        'presupuesto'          => 'p.presupuesto',
        'porcentaje_ejecucion' => 'p.porcentaje_ejecucion',
        'fecha_inicio'         => 'p.fecha_inicio',
        'fecha_fin'            => 'p.fecha_fin',
        // Columna que viene de un JOIN: se prefija la tabla correcta.
        'cliente_nombre'       => 'cl.nombre',
    ];

    /** @deprecated Usar MAPA_ORDEN; se conserva por compatibilidad. */
    public const COLUMNAS_ORDEN = [
        'codigo', 'nombre', 'estado', 'cliente_nombre', 'presupuesto',
        'porcentaje_ejecucion', 'fecha_inicio', 'fecha_fin'
    ];

    public function __construct()
    {
        parent::__construct('proyectos');
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
        // Una o varias columnas (Shift+clic), validadas contra MAPA_ORDEN, con p.id
        // como desempate para que las filas empatadas no bailen entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'p.nombre', 'p.id DESC');

        $whereSql = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // El JOIN del cliente participa del WHERE (texto libre y filtro cliente:), así
        // que va igual en el COUNT y en el SELECT de filas.
        $joinsFiltro = "LEFT JOIN clientes cl ON cl.id = p.id_cliente";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre sobre las columnas visibles (por palabras, sin tildes). Estado
            // queda fuera: se filtra desde el modal (estado:activo).
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto([
                'p.codigo',
                'p.nombre',
                'p.descripcion',
                'cl.nombre',
                'cl.identificacion',
            ], $parsed['texto_libre'], $params, 'pr_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'    => [
                'codigo'      => 'p.codigo',
                'nombre'      => 'p.nombre',
                'descripcion' => 'p.descripcion',
                'cliente'     => 'cl.nombre',
            ],
            'exacto'   => [
                'estado'     => 'p.estado',
                'id_cliente' => 'p.id_cliente',
                'usuario'    => 'p.created_by',
            ],
            'fecha'    => [
                'inicio'     => 'p.fecha_inicio',
                'fin'        => 'p.fecha_fin',
                'registro'   => 'p.created_at',
                'created_at' => 'p.created_at',
            ],
            'numerico' => [
                'presupuesto' => 'p.presupuesto',
                'ejecucion'   => 'p.porcentaje_ejecucion',
            ],
        ]);

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} p {$joinsFiltro} {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;

        $sqlRows = "SELECT p.*, cl.nombre AS cliente_nombre
                    FROM {$this->table} p
                    {$joinsFiltro}
                    {$whereSql}
                    {$orderBy}";

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
     * Opciones de los selects del modal de filtros: solo los clientes y usuarios que
     * realmente aparecen en los proyectos de la empresa.
     *
     * @return array{clientes: array, usuarios: array}
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $p = [':id_empresa' => $idEmpresa];
        $base = "FROM {$this->table} p %s WHERE p.id_empresa = :id_empresa AND p.eliminado = false";
        $leer = function (string $select, string $join, string $orden) use ($p, $base): array {
            $st = $this->db->prepare("SELECT DISTINCT {$select} " . sprintf($base, $join) . " ORDER BY {$orden}");
            $st->execute($p);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };

        return [
            'clientes' => $leer('cl.id, cl.nombre', 'JOIN clientes cl ON cl.id = p.id_cliente', 'cl.nombre'),
            'usuarios' => $leer('u.id, u.nombre', 'JOIN usuarios u ON u.id = p.created_by', 'u.nombre'),
        ];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, codigo, nombre, descripcion, estado,
                    eliminado, created_by, created_at, id_cliente, presupuesto, 
                    fecha_inicio, fecha_fin, porcentaje_ejecucion
                ) VALUES (
                    :id_empresa, :id_usuario, :codigo, :nombre, :descripcion, :estado,
                    :eliminado, :created_by, CURRENT_TIMESTAMP, :id_cliente, :presupuesto,
                    :fecha_inicio, :fecha_fin, :porc_ejec
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
            ':created_by'   => $data['created_by'],
            ':id_cliente'   => $data['id_cliente'] ?: null,
            ':presupuesto'  => $data['presupuesto'] ?? 0,
            ':fecha_inicio' => $data['fecha_inicio'] ?: null,
            ':fecha_fin'    => $data['fecha_fin'] ?: null,
            ':porc_ejec'    => $data['porcentaje_ejecucion'] ?? 0
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
                id_cliente = :id_cliente,
                presupuesto = :presupuesto,
                fecha_inicio = :fecha_inicio,
                fecha_fin = :fecha_fin,
                porcentaje_ejecucion = :porc_ejec,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
            ':id_cliente'   => $data['id_cliente'] ?: null,
            ':presupuesto'  => $data['presupuesto'] ?? 0,
            ':fecha_inicio' => $data['fecha_inicio'] ?: null,
            ':fecha_fin'    => $data['fecha_fin'] ?: null,
            ':porc_ejec'    => $data['porcentaje_ejecucion'] ?? 0,
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
        $sql = "SELECT p.*, cl.nombre AS cliente_nombre,
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} p
                LEFT JOIN clientes cl ON cl.id = p.id_cliente
                LEFT JOIN usuarios u_crea ON u_crea.id = p.created_by
                LEFT JOIN usuarios u_act ON u_act.id = p.updated_by
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

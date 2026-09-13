<?php
/**
 * Responsables de traslado: la persona que lleva físicamente la mercadería
 * (chofer / repartidor / asesor). Lo consumen Pedidos, Consignaciones de Venta,
 * Retornos, Cambios de Producto y el vínculo con usuarios de la app móvil
 * (usuarios_responsables_traslado).
 */

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

class ResponsableTrasladoRepository extends BaseRepository
{
    protected string $table = 'responsables_traslado';

    /** Tablas que consumen un responsable: se validan antes de eliminar. */
    private const TABLAS_EN_USO = [
        ['tabla' => 'pedidos_cabecera',               'columna' => 'id_responsable_entrega',  'etiqueta' => 'pedido(s)'],
        ['tabla' => 'consignaciones_ventas',          'columna' => 'id_responsable_traslado', 'etiqueta' => 'consignación(es) de venta'],
        ['tabla' => 'retornos_cv',                    'columna' => 'id_responsable_traslado', 'etiqueta' => 'retorno(s) de consignación'],
        ['tabla' => 'cambios_producto_cv',            'columna' => 'id_responsable_traslado', 'etiqueta' => 'cambio(s) de producto'],
        ['tabla' => 'usuarios_responsables_traslado', 'columna' => 'id_responsable_traslado', 'etiqueta' => 'usuario(s) vinculado(s)'],
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Responsables activos de la empresa. La usan los selects de Pedidos y
     * Consignaciones — NO cambiar la forma del resultado sin revisarlos.
     */
    public function listarPorEmpresa(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, identificacion, telefono, email
                  FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                 ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado del módulo. $perPage = 0 devuelve todo (exportaciones).
     *
     * @return array{total:int, rows:array<int,array<string,mixed>>}
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $where  = $this->getBaseWhere($idEmpresa, 'r', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = FiltrosBusqueda::condicionTexto(
                ['r.nombre', 'r.identificacion', 'r.telefono', 'r.email'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($cond !== '') {
                $where .= " AND {$cond}";
            }
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'nombre'         => 'r.nombre',
                'identificacion' => 'r.identificacion',
                'telefono'       => 'r.telefono',
                'email'          => 'r.email',
            ],
            'exacto' => ['estado' => 'r.estado'],
            'fecha'  => ['creado' => 'r.created_at'],
        ]);

        $cols = [
            'nombre'         => 'r.nombre',
            'identificacion' => 'r.identificacion',
            'telefono'       => 'r.telefono',
            'email'          => 'r.email',
            'estado'         => 'r.estado',
            'created_at'     => 'r.created_at',
        ];
        $col = $cols[$ordenCol] ?? 'r.nombre';
        $dir = (strtoupper($ordenDir) === 'DESC') ? 'DESC' : 'ASC';

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} r {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitSql = '';
        if ($perPage > 0) {
            $offset   = max(0, ($page - 1) * $perPage);
            $limitSql = " LIMIT {$perPage} OFFSET {$offset}";
        }

        // Usuarios vinculados: subconsulta escalar (no un JOIN + GROUP BY, que
        // obligaría a agrupar por todas las columnas). Va por el índice de
        // id_responsable_traslado; si la tabla del vínculo aún no existe en esta
        // instalación, se devuelve 0 en vez de romper el listado entero.
        $colUsuarios = $this->tablaExiste('usuarios_responsables_traslado')
            ? "(SELECT COUNT(*) FROM usuarios_responsables_traslado ur
                 WHERE ur.id_responsable_traslado = r.id
                   AND ur.id_empresa = r.id_empresa
                   AND ur.eliminado = false)"
            : '0';

        $sql = "SELECT r.id, r.nombre, r.identificacion, r.telefono, r.email, r.estado,
                       r.created_at, r.updated_at,
                       {$colUsuarios} AS usuarios_vinculados
                  FROM {$this->table} r
                  {$where}
                 ORDER BY {$col} {$dir}, r.id DESC
                 {$limitSql}";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                 WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (id_empresa, nombre, identificacion, telefono, email, estado,
                     created_by, updated_by, created_at, updated_at, eliminado)
                VALUES
                    (:id_empresa, :nombre, :identificacion, :telefono, :email, :estado,
                     :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'     => (int) $data['id_empresa'],
            ':nombre'         => $data['nombre'],
            ':identificacion' => $data['identificacion'] ?? null,
            ':telefono'       => $data['telefono'] ?? null,
            ':email'          => $data['email'] ?? null,
            ':estado'         => $data['estado'] ?? 'activo',
            ':id_usuario'     => (int) $data['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                   SET nombre = :nombre, identificacion = :identificacion,
                       telefono = :telefono, email = :email, estado = :estado,
                       updated_by = :id_usuario, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':nombre'         => $data['nombre'],
            ':identificacion' => $data['identificacion'] ?? null,
            ':telefono'       => $data['telefono'] ?? null,
            ':email'          => $data['email'] ?? null,
            ':estado'         => $data['estado'] ?? 'activo',
            ':id_usuario'     => (int) $data['id_usuario'],
            ':id'             => $id,
            ':id_empresa'     => (int) $data['id_empresa'],
        ]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica (§5): el registro nunca se borra físicamente. */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :id_usuario,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :id_usuario
                 WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_usuario' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function existeIdentificacion(int $idEmpresa, string $identificacion, ?int $excluirId = null): bool
    {
        if (trim($identificacion) === '') {
            return false;
        }
        $sql = "SELECT COUNT(*) FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND identificacion = :identificacion
                   AND eliminado = false"
             . ($excluirId !== null ? " AND id <> :excluir" : '');
        $params = [':id_empresa' => $idEmpresa, ':identificacion' => trim($identificacion)];
        if ($excluirId !== null) {
            $params[':excluir'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                 WHERE id_empresa = :id_empresa AND UPPER(TRIM(nombre)) = UPPER(:nombre)
                   AND eliminado = false"
             . ($excluirId !== null ? " AND id <> :excluir" : '');
        $params = [':id_empresa' => $idEmpresa, ':nombre' => trim($nombre)];
        if ($excluirId !== null) {
            $params[':excluir'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    /**
     * Documentos que ya usan este responsable, por tabla. Devuelve solo las que
     * tienen registros, en la forma ['3 pedido(s)', '1 usuario(s) vinculado(s)'].
     *
     * Las tablas se consultan con to_regclass (tablaExiste) porque no todas las
     * instalaciones tienen todos los módulos: preguntar por una tabla ausente
     * dentro de una transacción la abortaría entera (25P02).
     *
     * @return string[]
     */
    public function getUsos(int $id, int $idEmpresa): array
    {
        $usos = [];
        foreach (self::TABLAS_EN_USO as $ref) {
            if (!$this->tablaExiste($ref['tabla'])) {
                continue;
            }
            $sql = "SELECT COUNT(*) FROM {$ref['tabla']}
                     WHERE {$ref['columna']} = :id AND id_empresa = :id_empresa AND eliminado = false";
            $st = $this->db->prepare($sql);
            $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $usos[] = "{$n} {$ref['etiqueta']}";
            }
        }
        return $usos;
    }

    /** Conteo de documentos por responsable, para la columna "En uso" del listado. */
    public function contarUsos(int $id, int $idEmpresa): int
    {
        $total = 0;
        foreach (self::TABLAS_EN_USO as $ref) {
            if (!$this->tablaExiste($ref['tabla'])) {
                continue;
            }
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM {$ref['tabla']}
                  WHERE {$ref['columna']} = :id AND id_empresa = :id_empresa AND eliminado = false"
            );
            $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
            $total += (int) $st->fetchColumn();
        }
        return $total;
    }
}

<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class AlumnoNivelRepository extends BaseRepository
{
    protected string $table = 'alumnos_niveles';

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL. Es la whitelist del ORDER BY y el mapa de `OrdenListado::clausula()`.
     */
    public const MAPA_ORDEN = [
        'orden'   => 'n.orden',
        'nombre'  => 'n.nombre',
        'alumnos' => 'cnt.total',
        'estado'  => 'n.estado',
    ];

    /**
     * Alumnos con matrícula vigente (período abierto) en cada nivel. LATERAL para
     * contar solo los niveles de la página, apoyado en idx_alumnos_periodos_nivel.
     */
    private const JOIN_CONTEO = "LEFT JOIN LATERAL (
                SELECT COUNT(DISTINCT ap.id_alumno) AS total
                  FROM alumnos_periodos ap
                  JOIN alumnos a ON a.id = ap.id_alumno AND a.eliminado = false
                 WHERE ap.id_nivel = n.id AND ap.id_empresa = n.id_empresa
                   AND ap.eliminado = false AND ap.fecha_salida IS NULL
            ) cnt ON true";

    public function __construct()
    {
        parent::__construct($this->table);
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
        // Una o varias columnas (Shift+clic), validadas contra MAPA_ORDEN, con n.id
        // como desempate para que las filas empatadas no bailen entre páginas.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        $orderBy = \App\Helpers\OrdenListado::clausula($ordenMulti, self::MAPA_ORDEN, 'n.orden', 'n.id DESC');

        $whereSql = $this->getBaseWhere($idEmpresa, 'n', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(['n.nombre'], $parsed['texto_libre'], $params, 'niv_b');
            if ($cond !== '') {
                $whereSql .= ' AND ' . $cond;
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto'    => [
                'nombre' => 'n.nombre',
            ],
            'exacto'   => [
                'estado'  => 'n.estado',
                'usuario' => 'n.created_by',
            ],
            'numerico' => [
                'orden'   => 'n.orden',
                'alumnos' => 'COALESCE(cnt.total, 0)',
            ],
            'fecha'    => [
                'registro'   => 'n.created_at',
                'created_at' => 'n.created_at',
            ],
        ]);

        $from = "{$this->table} n " . self::JOIN_CONTEO;

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM {$from} {$whereSql}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT n.*, COALESCE(cnt.total, 0) AS alumnos FROM {$from} {$whereSql} {$orderBy}";
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);

        return ['total' => $total, 'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Opciones de los selects del modal de filtros: solo los usuarios que realmente
     * registraron niveles en la empresa.
     *
     * @return array{usuarios: array}
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT u.id, u.nombre
               FROM {$this->table} n
               JOIN usuarios u ON u.id = n.created_by
              WHERE n.id_empresa = :id_empresa AND n.eliminado = false
              ORDER BY u.nombre"
        );
        $st->execute([':id_empresa' => $idEmpresa]);
        return ['usuarios' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Listado corto para selects (id, nombre) — usado por el modal de Alumno.
     */
    public function getParaSelect(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre FROM {$this->table}
                WHERE id_empresa = :id_empresa AND eliminado = false AND estado = 'activo'
                ORDER BY orden ASC, nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE id_empresa = :id_empresa AND UPPER(nombre) = UPPER(:nombre) AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa, ':nombre' => $nombre];
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function contarAlumnosAsignados(int $id, int $idEmpresa): int
    {
        // Cualquier período (vigente o histórico) de un alumno no eliminado bloquea.
        $sql = "SELECT COUNT(DISTINCT ap.id_alumno) FROM alumnos_periodos ap
                JOIN alumnos a ON a.id = ap.id_alumno AND a.eliminado = false
                WHERE ap.id_nivel = :id AND ap.id_empresa = :id_empresa AND ap.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (id_empresa, nombre, orden, estado, created_by, updated_by)
                VALUES (:id_empresa, :nombre, :orden, :estado, :id_usuario, :id_usuario)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':nombre'     => $data['nombre'],
            ':orden'      => $data['orden'],
            ':estado'     => $data['estado'],
            ':id_usuario' => $data['id_usuario'],
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    nombre = :nombre, orden = :orden, estado = :estado,
                    updated_by = :id_usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'     => $data['nombre'],
            ':orden'      => $data['orden'],
            ':estado'     => $data['estado'],
            ':id_usuario' => $data['id_usuario'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }
}

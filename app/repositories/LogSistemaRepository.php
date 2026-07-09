<?php
declare(strict_types=1);

namespace App\repositories;

use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Repositorio de solo lectura para la bitácora de auditoría (log_sistema).
 *
 * OJO: log_sistema NO es una tabla operativa clásica: no tiene columna `eliminado`
 * y su `id_empresa` puede ser NULL (acciones globales: login, catálogos, etc.).
 * Por eso NO usa getBaseWhere(): el filtrado multiempresa se arma aquí a mano
 * según el nivel del usuario.
 */
class LogSistemaRepository extends BaseRepository
{
    /** Columnas de ordenamiento permitidas (whitelist) mapeadas a SQL. */
    private const MAPA_ORDEN = [
        'created_at' => 'l.created_at',
        'accion'     => 'l.accion',
        'tabla'      => 'l.tabla_afectada',
        'usuario'    => 'u.nombre',
        'empresa'    => 'e.nombre_comercial',
        'id'         => 'l.id',
    ];

    public function __construct()
    {
        parent::__construct('log_sistema');
    }

    /**
     * Listado paginado de la bitácora.
     *
     * @param array{nivel:int,id_empresa:int} $scope Alcance del usuario actual.
     * @return array{rows: array, total: int}
     */
    public function getListado(
        array $scope,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        array $filtros = []
    ): array {
        $filtros['tabla'] = $this->resolverTablaFiltro($scope, $filtros['tabla'] ?? null);
        [$where, $params] = $this->construirWhere($scope, $buscar, $filtros);

        // Total
        $sqlCount = "SELECT COUNT(*)
                     FROM log_sistema l
                     LEFT JOIN usuarios u ON u.id = l.id_usuario
                     LEFT JOIN empresas e ON e.id = l.id_empresa
                     {$where}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Ordenamiento seguro
        $colSql = self::MAPA_ORDEN[$ordenCol] ?? 'l.created_at';
        $dirSql = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        $sql = "SELECT l.id, l.id_usuario, l.id_empresa, l.accion, l.tabla_afectada,
                       l.id_registro, l.ip_usuario, l.user_agent, l.created_at,
                       u.nombre AS usuario_nombre,
                       e.nombre_comercial AS empresa_nombre,
                       e.nombre AS empresa_razon
                FROM log_sistema l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                LEFT JOIN empresas e ON e.id = l.id_empresa
                {$where}
                ORDER BY {$colSql} {$dirSql}, l.id {$dirSql}
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Filas para exportación (respeta alcance + filtros de búsqueda, sin paginar).
     * Con tope de seguridad para no volcar la bitácora completa.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array{rows: array, total: int, truncado: bool}
     */
    public function getParaExportar(
        array $scope,
        string $buscar,
        string $ordenCol,
        string $ordenDir,
        int $limit = 10000,
        array $filtros = []
    ): array {
        $filtros['tabla'] = $this->resolverTablaFiltro($scope, $filtros['tabla'] ?? null);
        [$where, $params] = $this->construirWhere($scope, $buscar, $filtros);

        $sqlCount = "SELECT COUNT(*)
                     FROM log_sistema l
                     LEFT JOIN usuarios u ON u.id = l.id_usuario
                     LEFT JOIN empresas e ON e.id = l.id_empresa
                     {$where}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colSql = self::MAPA_ORDEN[$ordenCol] ?? 'l.created_at';
        $dirSql = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $limit  = max(1, min(50000, $limit));

        $sql = "SELECT l.id, l.id_usuario, l.id_empresa, l.accion, l.tabla_afectada,
                       l.id_registro, l.ip_usuario, l.created_at,
                       u.nombre AS usuario_nombre,
                       e.nombre_comercial AS empresa_nombre
                FROM log_sistema l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                LEFT JOIN empresas e ON e.id = l.id_empresa
                {$where}
                ORDER BY {$colSql} {$dirSql}, l.id {$dirSql}
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total, 'truncado' => $total > $limit];
    }

    /**
     * Obtiene un registro completo (incluye los JSON de datos) respetando el alcance.
     * El scope se aplica también aquí para que un admin no pueda leer por ID
     * un log de otra empresa.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     */
    public function getPorId(int $id, array $scope): ?array
    {
        // Solo el alcance de empresa: la búsqueda por ID NO debe aplicar la
        // ventana de "últimos 30 días" ni ningún filtro del listado; si no, un
        // registro más antiguo que 30 días daría "no encontrado".
        [$where, $params] = $this->whereAlcance($scope);
        $params[':id'] = $id;

        $sql = "SELECT l.id, l.id_usuario, l.id_empresa, l.accion, l.tabla_afectada,
                       l.id_registro, l.datos_anteriores, l.datos_nuevos,
                       l.ip_usuario, l.user_agent, l.created_at,
                       u.nombre AS usuario_nombre,
                       e.nombre_comercial AS empresa_nombre,
                       e.nombre AS empresa_razon
                FROM log_sistema l
                LEFT JOIN usuarios u ON u.id = l.id_usuario
                LEFT JOIN empresas e ON e.id = l.id_empresa
                {$where} AND l.id = :id
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * WHERE con solo el alcance multiempresa por nivel (sin fechas ni texto).
     * Reutilizado por el listado y por la carga de opciones de filtros.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array{0:string,1:array}
     */
    private function whereAlcance(array $scope): array
    {
        $nivel     = (int) ($scope['nivel'] ?? 1);
        $idEmpresa = (int) ($scope['id_empresa'] ?? 0);

        $where  = 'WHERE 1=1';
        $params = [];

        // Nivel 3 ve todo; nivel < 3 ve su empresa activa más los eventos
        // globales (id_empresa IS NULL: login, catálogos, etc.).
        if ($nivel < 3) {
            $where .= ' AND (l.id_empresa = :scope_empresa OR l.id_empresa IS NULL)';
            $params[':scope_empresa'] = $idEmpresa;
        }

        return [$where, $params];
    }

    /**
     * Arma el WHERE aplicando alcance por nivel + filtros explícitos (dropdowns/
     * fechas) + búsqueda por texto libre y tokens.
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @param array $filtros ['usuario','empresa','accion','tabla','desde','hasta']
     * @return array{0:string,1:array}
     */
    private function construirWhere(array $scope, string $buscar, array $filtros = []): array
    {
        [$where, $params] = $this->whereAlcance($scope);
        $nivel = (int) ($scope['nivel'] ?? 1);

        // Filtros explícitos de la barra de filtros.
        $tieneFechaExplicita = false;

        if (!empty($filtros['usuario'])) {
            $where .= ' AND l.id_usuario = :f_usuario';
            $params[':f_usuario'] = (int) $filtros['usuario'];
        }
        // El filtro por empresa solo aplica a nivel 3 (los demás ya están acotados).
        if (!empty($filtros['empresa']) && $nivel >= 3) {
            $where .= ' AND l.id_empresa = :f_empresa';
            $params[':f_empresa'] = (int) $filtros['empresa'];
        }
        if (!empty($filtros['accion'])) {
            // Case-insensitive: la acción se muestra normalizada pero en BD tiene
            // casing inconsistente (crear/CREAR/…).
            $where .= ' AND LOWER(l.accion) = :f_accion';
            $params[':f_accion'] = mb_strtolower((string) $filtros['accion'], 'UTF-8');
        }
        if (!empty($filtros['tabla'])) {
            $where .= ' AND l.tabla_afectada = :f_tabla';
            $params[':f_tabla'] = (string) $filtros['tabla'];
        }
        if (!empty($filtros['desde'])) {
            $where .= ' AND l.created_at >= :f_desde';
            $params[':f_desde'] = $filtros['desde'] . ' 00:00:00';
            $tieneFechaExplicita = true;
        }
        if (!empty($filtros['hasta'])) {
            $where .= ' AND l.created_at <= :f_hasta';
            $params[':f_hasta'] = $filtros['hasta'] . ' 23:59:59';
            $tieneFechaExplicita = true;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);

        // Texto libre → busca en acción, tabla, usuario, empresa e IP.
        if ($parsed['texto_libre'] !== '') {
            $where .= ' AND ('
                . 'l.accion ILIKE :txt'
                . ' OR l.tabla_afectada ILIKE :txt'
                . ' OR u.nombre ILIKE :txt'
                . ' OR e.nombre_comercial ILIKE :txt'
                . ' OR l.ip_usuario ILIKE :txt'
                . ')';
            $params[':txt'] = '%' . $parsed['texto_libre'] . '%';
        }

        // Filtros clave:valor
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'usuario' => 'u.nombre',
                'accion'  => 'l.accion',
                'tabla'   => 'l.tabla_afectada',
                'empresa' => 'e.nombre_comercial',
                'ip'      => 'l.ip_usuario',
            ],
            'numerico' => [
                'registro' => 'l.id_registro',
            ],
            'fecha' => [
                'fecha' => 'l.created_at',
            ],
        ]);

        // Rango por defecto: si el usuario no filtró por fecha (ni por token ni por
        // los campos desde/hasta), se muestran los últimos 30 días.
        if (!isset($parsed['filtros']['fecha']) && !$tieneFechaExplicita) {
            $where .= " AND l.created_at >= (CURRENT_DATE - INTERVAL '30 days')";
        }

        return [$where, $params];
    }

    /**
     * Opciones para poblar los selects de la barra de filtros, tomadas de los
     * valores que realmente existen en la bitácora dentro del alcance del usuario.
     * Se ejecuta una sola vez al abrir la página (no en cada refresco AJAX).
     *
     * @param array{nivel:int,id_empresa:int} $scope
     * @return array{acciones: array, tablas: array, usuarios: array, empresas: array}
     */
    public function getOpcionesFiltros(array $scope): array
    {
        [$where, $params] = $this->whereAlcance($scope);
        $nivel = (int) ($scope['nivel'] ?? 1);

        // Acciones distintas → normalizadas por minúsculas (colapsa crear/CREAR).
        $st = $this->db->prepare(
            "SELECT DISTINCT l.accion FROM log_sistema l {$where}
             AND l.accion IS NOT NULL AND l.accion <> '' LIMIT 500"
        );
        $st->execute($params);
        $accionesRaw = $st->fetchAll(PDO::FETCH_COLUMN);
        $accMap = [];
        foreach ($accionesRaw as $a) {
            $valor = mb_strtolower(trim((string) $a), 'UTF-8');
            if ($valor === '' || isset($accMap[$valor])) {
                continue;
            }
            $accMap[$valor] = ['valor' => $valor, 'label' => \App\Helpers\AuditoriaEtiquetas::accion($valor)];
        }
        $acciones = array_values($accMap);
        usort($acciones, fn ($a, $b) => strcmp($a['label'], $b['label']));

        // Tablas / módulos → etiqueta amigable + código opaco (sin exponer el nombre real).
        $st = $this->db->prepare(
            "SELECT DISTINCT l.tabla_afectada FROM log_sistema l {$where}
             AND l.tabla_afectada IS NOT NULL AND l.tabla_afectada <> '' LIMIT 500"
        );
        $st->execute($params);
        $tablasRaw = $st->fetchAll(PDO::FETCH_COLUMN);
        $tablas = [];
        foreach ($tablasRaw as $t) {
            $tablas[] = [
                'codigo' => \App\Helpers\AuditoriaEtiquetas::codigo((string) $t),
                'label'  => \App\Helpers\AuditoriaEtiquetas::tabla((string) $t),
            ];
        }
        usort($tablas, fn ($a, $b) => strcmp($a['label'], $b['label']));

        // Usuarios que aparecen en la bitácora.
        // Alcance ESTRICTO: para nivel < 3 solo se listan usuarios con actividad
        // de SU empresa (se excluyen los eventos globales id_empresa IS NULL) para
        // no filtrar por nombre a usuarios de otras empresas que solo hicieron
        // login u otras acciones globales. Los eventos globales siguen visibles en
        // el listado; esto acota únicamente las opciones del filtro.
        if ($nivel < 3) {
            $whereUsuarios  = 'WHERE l.id_empresa = :scope_empresa_u';
            $paramsUsuarios = [':scope_empresa_u' => (int) ($scope['id_empresa'] ?? 0)];
        } else {
            $whereUsuarios  = 'WHERE 1=1';
            $paramsUsuarios = [];
        }
        $st = $this->db->prepare(
            "SELECT DISTINCT l.id_usuario, COALESCE(u.nombre, '#' || l.id_usuario) AS nombre
             FROM log_sistema l
             LEFT JOIN usuarios u ON u.id = l.id_usuario
             {$whereUsuarios} AND l.id_usuario IS NOT NULL
             ORDER BY nombre LIMIT 500"
        );
        $st->execute($paramsUsuarios);
        $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

        // Empresas: solo para nivel 3 (los demás están acotados a su empresa).
        $empresas = [];
        if ($nivel >= 3) {
            $st = $this->db->prepare(
                "SELECT DISTINCT l.id_empresa,
                        COALESCE(e.nombre_comercial, e.nombre, '#' || l.id_empresa) AS nombre
                 FROM log_sistema l
                 LEFT JOIN empresas e ON e.id = l.id_empresa
                 {$where} AND l.id_empresa IS NOT NULL
                 ORDER BY nombre LIMIT 500"
            );
            $st->execute($params);
            $empresas = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'acciones' => $acciones,
            'tablas'   => $tablas,
            'usuarios' => $usuarios,
            'empresas' => $empresas,
        ];
    }

    /**
     * Traduce el código opaco de tabla (enviado por el cliente) al nombre real,
     * buscándolo entre las tablas presentes en la bitácora dentro del alcance.
     * Devuelve null si no hay filtro; un valor imposible si el código no coincide
     * (para que el filtro no muestre nada en vez de ignorarse).
     *
     * @param array{nivel:int,id_empresa:int} $scope
     */
    public function resolverTablaFiltro(array $scope, ?string $codigo): ?string
    {
        $codigo = $codigo !== null ? trim($codigo) : '';
        if ($codigo === '') {
            return null;
        }
        [$where, $params] = $this->whereAlcance($scope);
        $st = $this->db->prepare(
            "SELECT DISTINCT tabla_afectada FROM log_sistema l {$where}
             AND tabla_afectada IS NOT NULL AND tabla_afectada <> '' LIMIT 500"
        );
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (\App\Helpers\AuditoriaEtiquetas::codigo((string) $t) === $codigo) {
                return (string) $t;
            }
        }
        return '__sin_coincidencia__';
    }
}

<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class AutomatizacionesRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre', 'modulo', 'accion', 'frecuencia_tipo', 'proxima_ejecucion', 'ultima_ejecucion', 'ultimo_resultado', 'estado', 'created_at'];

    public function __construct()
    {
        parent::__construct('automatizaciones');
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
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) $ordenCol = 'nombre';
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $offset   = ($page - 1) * $perPage;

        $whereFiltro = '';
        $params      = [':id_empresa' => $idEmpresa];

        // $buscar es el string serializado del buscador (FiltrosModal en la vista):
        // `clave:valor ... texto libre`. Antes se buscaba el string completo con un
        // solo ILIKE, así que los filtros del buscador (estado:, resultado:…) nunca
        // se aplicaban.
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre: columnas del listado y lo que identifica la automatización.
            // Decisión del usuario: Módulo, Acción y Frecuencia (clasificación), Resultado
            // y Estado NO entran en el texto libre; se filtran solo desde el modal. La
            // descripción (que el sistema arma con el nombre del módulo y la acción) sí.
            // Los parámetros (JSONB) no se buscan: pueden llevar correos o destinatarios.
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'a.nombre',                                                 // Nombre
                    'e.nombre',                                                 // Nombre (establecimiento debajo)
                    'a.descripcion',
                    "TO_CHAR(a.proxima_ejecucion, 'DD-MM-YYYY HH24:MI:SS')",    // Próx. ejecución
                    "TO_CHAR(a.ultima_ejecucion, 'DD-MM-YYYY HH24:MI:SS')",     // Últ. ejecución
                    'u.nombre',                                                 // Usuario que la creó
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereFiltro .= " AND {$condicion}";
            }
        }
        // Claves del modal de filtros (las viejas nombre/modulo/accion/estado/resultado
        // se conservan: viajan en los enlaces de PDF/Excel).
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereFiltro, $params, $parsed['filtros'], [
            'texto'  => [
                'nombre'      => 'a.nombre',
                'modulo'      => 'a.modulo',
                'accion'      => 'a.accion',
                'descripcion' => 'a.descripcion',
            ],
            'exacto' => [
                'estado'             => 'a.estado',
                // Sin ejecuciones el resultado es NULL: se filtra como 'sin_ejecutar'.
                'resultado'          => "COALESCE(a.ultimo_resultado, 'sin_ejecutar')",
                'modulo_clave'       => 'a.modulo',
                'accion_clave'       => 'a.accion',
                'frecuencia'         => 'a.frecuencia_tipo',
                'id_establecimiento' => 'a.id_establecimiento',
                'usuario'            => 'a.created_by',
            ],
            'fecha'  => [
                'proxima'  => 'a.proxima_ejecucion',
                'ultima'   => 'a.ultima_ejecucion',
                'registro' => 'a.created_at',
            ],
        ]);

        if ($idUsuarioFiltro !== null) {
            $whereFiltro    .= " AND a.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Mismos JOIN en el listado y el conteo: el texto libre usa e y u.
        $joins = "
            LEFT JOIN empresa_establecimiento e ON e.id = a.id_establecimiento
            LEFT JOIN usuarios u ON u.id = a.created_by
        ";

        $sql = "
            SELECT a.*,
                   e.nombre AS nombre_establecimiento
            FROM automatizaciones a
            {$joins}
            WHERE a.id_empresa = :id_empresa AND a.eliminado = false
            {$whereFiltro}
            ORDER BY a.{$ordenCol} {$ordenDir}, a.id DESC
            " . ($perPage > 0 ? "LIMIT :limit OFFSET :offset" : "") . "
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($perPage > 0) {
            $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sqlCount = "
            SELECT COUNT(*) FROM automatizaciones a
            {$joins}
            WHERE a.id_empresa = :id_empresa AND a.eliminado = false
            {$whereFiltro}
        ";
        $stmtC = $this->db->prepare($sqlCount);
        $stmtC->execute($params);
        $total = (int) $stmtC->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Opciones de los selects del modal de filtros: módulos, acciones, frecuencias,
     * establecimientos y usuarios que ya aparecen en alguna automatización de la
     * empresa. Con $idUsuarioFiltro (sin acceso total) solo cuenta las propias.
     *
     * @return array{modulos: string[], acciones: array<int, array{modulo:string, accion:string}>, frecuencias: string[],
     *               establecimientos: array<int, array{id:int, nombre:string}>, usuarios: array<int, array{id:int, nombre:string}>}
     */
    public function getOpcionesFiltroListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where  = 'a.id_empresa = :id_empresa AND a.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND a.created_by = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $leer = function (string $sql, int $modo) use ($params): array {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll($modo);
        };

        return [
            'modulos'          => $leer("SELECT DISTINCT a.modulo FROM automatizaciones a WHERE $where AND COALESCE(a.modulo, '') <> '' ORDER BY 1", \PDO::FETCH_COLUMN),
            'acciones'         => $leer("SELECT DISTINCT a.modulo, a.accion FROM automatizaciones a WHERE $where AND COALESCE(a.accion, '') <> '' ORDER BY 1, 2", \PDO::FETCH_ASSOC),
            'frecuencias'      => $leer("SELECT DISTINCT a.frecuencia_tipo FROM automatizaciones a WHERE $where AND COALESCE(a.frecuencia_tipo, '') <> '' ORDER BY 1", \PDO::FETCH_COLUMN),
            'establecimientos' => $leer("SELECT DISTINCT e.id, e.nombre FROM automatizaciones a JOIN empresa_establecimiento e ON e.id = a.id_establecimiento WHERE $where ORDER BY e.nombre", \PDO::FETCH_ASSOC),
            'usuarios'         => $leer("SELECT DISTINCT u.id, u.nombre FROM automatizaciones a JOIN usuarios u ON u.id = a.created_by WHERE $where ORDER BY u.nombre", \PDO::FETCH_ASSOC),
        ];
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, e.nombre AS nombre_establecimiento
            FROM automatizaciones a
            LEFT JOIN empresa_establecimiento e ON e.id = a.id_establecimiento
            WHERE a.id = :id AND a.id_empresa = :id_empresa AND a.eliminado = false
        ");
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO automatizaciones
                (id_empresa, id_establecimiento, nombre, descripcion, modulo, accion, parametros,
                 frecuencia_tipo, frecuencia_valor, cron_expression, proxima_ejecucion,
                 estado, created_by, updated_by)
            VALUES
                (:id_empresa, :id_establecimiento, :nombre, :descripcion, :modulo, :accion, :parametros::jsonb,
                 :frecuencia_tipo, :frecuencia_valor, :cron_expression, :proxima_ejecucion,
                 :estado, :created_by, :updated_by)
            RETURNING id
        ");
        $stmt->execute($data);
        return (int) $stmt->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, array $data): bool
    {
        $data[':id']         = $id;
        $data[':id_empresa'] = $idEmpresa;
        $stmt = $this->db->prepare("
            UPDATE automatizaciones SET
                id_establecimiento = :id_establecimiento,
                nombre             = :nombre,
                descripcion        = :descripcion,
                modulo             = :modulo,
                accion             = :accion,
                parametros         = :parametros::jsonb,
                frecuencia_tipo    = :frecuencia_tipo,
                frecuencia_valor   = :frecuencia_valor,
                cron_expression    = :cron_expression,
                proxima_ejecucion  = :proxima_ejecucion,
                estado             = :estado,
                updated_at         = NOW(),
                updated_by         = :updated_by
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $stmt->execute($data);
        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $stmt = $this->db->prepare("
            UPDATE automatizaciones
            SET eliminado = true, deleted_at = NOW(), deleted_by = :deleted_by, updated_at = NOW()
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ");
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':deleted_by' => $idUsuario]);
        return $stmt->rowCount() > 0;
    }

    public function actualizarEjecucion(int $id, string $proximaEjecucion, string $resultado): void
    {
        $stmt = $this->db->prepare("
            UPDATE automatizaciones
            SET ultima_ejecucion  = NOW(),
                proxima_ejecucion = :proxima_ejecucion,
                ultimo_resultado  = :resultado,
                updated_at        = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id, ':proxima_ejecucion' => $proximaEjecucion, ':resultado' => $resultado]);
    }

    /** Tareas pendientes de ejecutar (para el cron runner) */
    public function getPendientes(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM automatizaciones
            WHERE estado = 'activo'
              AND eliminado = false
              AND proxima_ejecucion IS NOT NULL
              AND proxima_ejecucion <= NOW()
            ORDER BY proxima_ejecucion ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function marcarEnProceso(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE automatizaciones SET estado = 'en_proceso', updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function marcarActivo(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE automatizaciones SET estado = 'activo', updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    // ── Log ──────────────────────────────────────────────────────────────────

    public function crearLog(int $idAutomatizacion, int $idEmpresa, string $ejecutadoPor = 'cron'): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO automatizaciones_log (id_automatizacion, id_empresa, ejecutado_por)
            VALUES (:id_automatizacion, :id_empresa, :ejecutado_por)
            RETURNING id
        ");
        $stmt->execute([
            ':id_automatizacion' => $idAutomatizacion,
            ':id_empresa'        => $idEmpresa,
            ':ejecutado_por'     => $ejecutadoPor,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function cerrarLog(int $idLog, string $resultado, int $registros, ?string $mensaje, ?string $detalleError): void
    {
        $stmt = $this->db->prepare("
            UPDATE automatizaciones_log
            SET finalizado_en       = NOW(),
                duracion_ms         = EXTRACT(EPOCH FROM (NOW() - iniciado_en)) * 1000,
                resultado           = :resultado,
                registros_afectados = :registros,
                mensaje             = :mensaje,
                detalle_error       = :detalle_error
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'            => $idLog,
            ':resultado'     => $resultado,
            ':registros'     => $registros,
            ':mensaje'       => $mensaje,
            ':detalle_error' => $detalleError,
        ]);
    }

    public function getLog(int $idAutomatizacion, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt   = $this->db->prepare("
            SELECT * FROM automatizaciones_log
            WHERE id_automatizacion = :id
            ORDER BY iniciado_en DESC, id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':id',     $idAutomatizacion, \PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage,          \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,           \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM automatizaciones_log WHERE id_automatizacion = :id");
        $stmtC->execute([':id' => $idAutomatizacion]);
        $total = (int) $stmtC->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
    }
}

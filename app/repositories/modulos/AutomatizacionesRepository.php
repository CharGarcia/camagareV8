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

        if ($buscar !== '') {
            $whereFiltro = " AND (a.nombre ILIKE :buscar OR a.modulo ILIKE :buscar OR a.accion ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        if ($idUsuarioFiltro !== null) {
            $whereFiltro    .= " AND a.created_by = :id_usuario_filtro";
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $sql = "
            SELECT a.*,
                   e.nombre AS nombre_establecimiento
            FROM automatizaciones a
            LEFT JOIN empresa_establecimiento e ON e.id = a.id_establecimiento
            WHERE a.id_empresa = :id_empresa AND a.eliminado = false
            {$whereFiltro}
            ORDER BY a.{$ordenCol} {$ordenDir}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sqlCount = "
            SELECT COUNT(*) FROM automatizaciones a
            WHERE a.id_empresa = :id_empresa AND a.eliminado = false
            {$whereFiltro}
        ";
        $stmtC = $this->db->prepare($sqlCount);
        foreach ($params as $k => $v) {
            if ($k === ':buscar' || $k === ':id_empresa' || $k === ':id_usuario_filtro') {
                $stmtC->bindValue($k, $v);
            }
        }
        $stmtC->execute();
        $total = (int) $stmtC->fetchColumn();

        return ['rows' => $rows, 'total' => $total];
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
            ORDER BY iniciado_en DESC
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

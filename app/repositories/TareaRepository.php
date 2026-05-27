<?php
declare(strict_types=1);

namespace App\repositories;

use App\repositories\BaseRepository;
use PDO;

class TareaRepository extends BaseRepository
{
    protected string $table = 'tareas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Listado paginado de tareas (global, sin id_empresa).
     */
    public function getListado(
        string $buscar,
        int    $page,
        int    $perPage,
        string $ordenCol,
        string $ordenDir,
        bool   $incluirArchivadas = false,
        int    $idUsuario = 0,
        array  $filtros   = [],
        int    $nivel     = 1
    ): array {
        // Visibilidad: SuperAdmin (N3) ve todo. Otros solo lo que crearon o tienen asignado.
        $whereSql = "WHERE t.eliminado = false";
        $params = [];

        if ($nivel < 3) {
            // Obtenemos el correo del usuario para un matching más robusto
            $selMail = $this->db->prepare("SELECT mail FROM usuarios WHERE id = :id_u");
            $selMail->execute([':id_u' => $idUsuario]);
            $uMail = strtolower(trim((string)$selMail->fetchColumn()));

            $whereSql .= " AND (
                t.created_by = :id_usuario 
                OR t.id IN (
                    SELECT id_tarea FROM tareas_responsables 
                    WHERE id_usuario = :id_usuario_aux 
                       OR (:u_mail <> '' AND LOWER(correo_cache) = :u_mail_aux)
                )
            )";
            $params[':id_usuario']     = $idUsuario;
            $params[':id_usuario_aux'] = $idUsuario;
            $params[':u_mail']         = $uMail;
            $params[':u_mail_aux']     = $uMail;
        }

        if ($incluirArchivadas) {
            $whereSql .= ' AND t.archivada = true';
        } else {
            $whereSql .= ' AND t.archivada = false';
        }

        // --- Filtros Especializados ---
        if (!empty($filtros['desde'])) {
            $whereSql .= ' AND t.fecha_tarea >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $whereSql .= ' AND t.fecha_tarea <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['obligacion'])) {
            $whereSql .= ' AND t.id_obligacion = :id_oblig';
            $params[':id_oblig'] = (int)$filtros['obligacion'];
        }
        if (!empty($filtros['estado'])) {
            $whereSql .= ' AND t.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['responsable'])) {
            $val = $filtros['responsable'];
            if (str_starts_with($val, 'u_')) {
                $idU = (int) substr($val, 2);
                $whereSql .= ' AND t.id IN (SELECT id_tarea FROM tareas_responsables WHERE id_usuario = :id_resp_u)';
                $params[':id_resp_u'] = $idU;
            } elseif (str_starts_with($val, 'r_')) {
                $idR = (int) substr($val, 2);
                $whereSql .= ' AND t.id IN (SELECT id_tarea FROM tareas_responsables WHERE id_resp_tarea = :id_resp_r)';
                $params[':id_resp_r'] = $idR;
            }
        }

        if ($buscar !== '') {
            $whereSql .= " AND (t.cliente_nombre ILIKE :b OR t.cliente_correo ILIKE :b2
                              OR co.nombre ILIKE :b3)";
            $params[':b']  = '%' . $buscar . '%';
            $params[':b2'] = '%' . $buscar . '%';
            $params[':b3'] = '%' . $buscar . '%';
        }

        $cols = [
            'cliente_nombre'  => 't.cliente_nombre',
            'cliente_correo'  => 't.cliente_correo',
            'obligacion'      => 'co.nombre',
            'fecha_tarea'     => 't.fecha_tarea',
            'estado'          => 't.estado',
            'periodicidad'    => 't.periodicidad',
            'created_at'      => 't.created_at',
        ];
        $col = $cols[$ordenCol] ?? 't.fecha_tarea';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $joins = "LEFT JOIN cat_obligaciones co ON co.id = t.id_obligacion
                  LEFT JOIN usuarios uc ON uc.id = t.created_by";

        // Contar
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} t {$joins} {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // Filas
        $offset  = ($page - 1) * $perPage;
        $sqlRows = "SELECT t.id, t.cliente_nombre, t.cliente_correo, t.id_cliente,
                           t.periodicidad, t.fecha_tarea, t.estado,
                           t.notas, t.resumen, t.archivada,
                           t.id_tarea_origen, t.id_obligacion,
                           t.created_at, t.updated_at,
                           co.nombre AS obligacion_nombre,
                           uc.nombre AS creado_por_nombre
                    FROM {$this->table} t
                    {$joins}
                    {$whereSql}
                    ORDER BY {$col} {$dir}";

        if ($perPage > 0) {
            $sqlRows .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Obtiene una tarea por ID con datos relacionados.
     */
    public function findByIdCompleto(int $id): ?array
    {
        $sql = "SELECT t.*,
                       co.nombre AS obligacion_nombre,
                       uc.nombre AS creado_por_nombre,
                       ua.nombre AS actualizado_por_nombre
                FROM {$this->table} t
                LEFT JOIN cat_obligaciones co ON co.id = t.id_obligacion
                LEFT JOIN usuarios uc ON uc.id = t.created_by
                LEFT JOIN usuarios ua ON ua.id = t.updated_by
                WHERE t.id = :id AND t.eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene una tarea por ID simple (para uso interno).
     */
    public function findByIdGlobal(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea una nueva tarea.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_obligacion, id_cliente, cliente_nombre, cliente_correo,
                    periodicidad, fecha_tarea, estado, notas, resumen,
                    motivo_cancelacion, archivada, id_tarea_origen,
                    eliminado, created_at, created_by
                ) VALUES (
                    :id_obligacion, :id_cliente, :cliente_nombre, :cliente_correo,
                    :periodicidad, :fecha_tarea, :estado, :notas, :resumen,
                    :motivo_cancelacion, :archivada, :id_tarea_origen,
                    false, CURRENT_TIMESTAMP, :created_by
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_obligacion'      => $data['id_obligacion'],
            ':id_cliente'         => $data['id_cliente'] ?? null,
            ':cliente_nombre'     => $data['cliente_nombre'],
            ':cliente_correo'     => $data['cliente_correo'],
            ':periodicidad'       => $data['periodicidad'],
            ':fecha_tarea'        => $data['fecha_tarea'],
            ':estado'             => $data['estado'] ?? 'por_realizar',
            ':notas'              => $data['notas'] ?? null,
            ':resumen'            => $data['resumen'] ?? null,
            ':motivo_cancelacion' => $data['motivo_cancelacion'] ?? null,
            ':archivada'          => ($data['archivada'] ?? false) ? 'true' : 'false',
            ':id_tarea_origen'    => $data['id_tarea_origen'] ?? null,
            ':created_by'         => $data['created_by'],
        ]);
        return $this->lastInsertId('tareas_id_seq');
    }

    /**
     * Actualiza una tarea existente.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET id_obligacion      = :id_obligacion,
                    id_cliente         = :id_cliente,
                    cliente_nombre     = :cliente_nombre,
                    cliente_correo     = :cliente_correo,
                    periodicidad       = :periodicidad,
                    fecha_tarea        = :fecha_tarea,
                    estado             = :estado,
                    notas              = :notas,
                    resumen            = :resumen,
                    motivo_cancelacion = :motivo_cancelacion,
                    archivada          = :archivada,
                    updated_by         = :updated_by,
                    updated_at         = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_obligacion'      => $data['id_obligacion'],
            ':id_cliente'         => $data['id_cliente'] ?? null,
            ':cliente_nombre'     => $data['cliente_nombre'],
            ':cliente_correo'     => $data['cliente_correo'],
            ':periodicidad'       => $data['periodicidad'],
            ':fecha_tarea'        => $data['fecha_tarea'],
            ':estado'             => $data['estado'],
            ':notas'              => $data['notas'] ?? null,
            ':resumen'            => $data['resumen'] ?? null,
            ':motivo_cancelacion' => $data['motivo_cancelacion'] ?? null,
            ':archivada'          => ($data['archivada'] ?? false) ? 'true' : 'false',
            ':updated_by'         => $data['updated_by'],
            ':id'                 => $id,
        ]);
    }

    /**
     * Eliminación lógica.
     */
    public function delete(int $id, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table}
                SET eliminado = true, deleted_by = :uid, deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st  = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':uid' => $idUsuario]);
    }

    // ─── Responsables ───────────────────────────────────────────

    public function getResponsables(int $idTarea): array
    {
        $sql = "SELECT tr.id, tr.id_usuario, tr.id_resp_tarea, tr.nombre_cache, tr.correo_cache,
                       COALESCE(u.nombre, rt.nombre, tr.nombre_cache) AS nombre,
                       COALESCE(u.mail, rt.correo, tr.correo_cache) AS mail,
                       CASE WHEN tr.id_usuario IS NOT NULL THEN 'usuario' ELSE 'propio' END AS tipo
                FROM tareas_responsables tr
                LEFT JOIN usuarios u  ON u.id  = tr.id_usuario
                LEFT JOIN responsables_tareas rt ON rt.id = tr.id_resp_tarea
                WHERE tr.id_tarea = :id_tarea
                ORDER BY nombre ASC";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_tarea' => $idTarea]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Limpia los responsables de una tarea.
     */
    public function clearResponsables(int $idTarea): void
    {
        $sql = "DELETE FROM tareas_responsables WHERE id_tarea = :id_tarea";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_tarea' => $idTarea]);
    }

    /**
     * Vincula un responsable a una tarea (usuario del sistema o externo).
     * Evita duplicados insertando en una sola operación.
     */
    public function vincularResponsable(int $idTarea, array $r): void
    {
        $sql = "INSERT INTO tareas_responsables 
                (id_tarea, id_usuario, id_resp_tarea, nombre_cache, correo_cache, created_at)
                VALUES 
                (:id_tarea, :id_usuario, :id_resp_tarea, :nombre, :correo, CURRENT_TIMESTAMP)";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_tarea'      => $idTarea,
            ':id_usuario'    => !empty($r['id_usuario']) ? (int)$r['id_usuario'] : null,
            ':id_resp_tarea' => !empty($r['id_resp_tarea']) ? (int)$r['id_resp_tarea'] : null,
            ':nombre'        => $r['nombre'] ?? '',
            ':correo'        => $r['mail'] ?? $r['correo'] ?? ''
        ]);
    }

    /**
     * Elimina todos los responsables de una tarea (para re-asignar).
     */
    public function deleteResponsables(int $idTarea): void
    {
        $sql = "DELETE FROM tareas_responsables WHERE id_tarea = :id_tarea";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_tarea' => $idTarea]);
    }

    // ─── Adjuntos ─────────────────────────────────────────────

    /**
     * Obtiene los adjuntos activos de una tarea.
     */
    public function getAdjuntos(int $idTarea): array
    {
        $sql = "SELECT id, nombre_archivo, ruta_archivo, tipo_mime, tamanio, created_at
                FROM tareas_adjuntos
                WHERE id_tarea = :id_tarea AND eliminado = false
                ORDER BY created_at ASC";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_tarea' => $idTarea]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un adjunto.
     */
    public function addAdjunto(array $data): int
    {
        $sql = "INSERT INTO tareas_adjuntos (id_tarea, nombre_archivo, ruta_archivo, tipo_mime, tamanio, created_by, created_at)
                VALUES (:id_tarea, :nombre_archivo, :ruta_archivo, :tipo_mime, :tamanio, :created_by, CURRENT_TIMESTAMP)";
        $st  = $this->db->prepare($sql);
        $st->execute([
            ':id_tarea'       => $data['id_tarea'],
            ':nombre_archivo' => $data['nombre_archivo'],
            ':ruta_archivo'   => $data['ruta_archivo'],
            ':tipo_mime'      => $data['tipo_mime'] ?? null,
            ':tamanio'        => $data['tamanio'] ?? null,
            ':created_by'     => $data['created_by'],
        ]);
        return $this->lastInsertId('tareas_adjuntos_id_seq');
    }

    /**
     * Eliminación lógica de adjunto.
     */
    public function deleteAdjunto(int $idAdjunto, int $idUsuario): ?string
    {
        // Obtener ruta antes de marcar eliminado
        $sel = $this->db->prepare("SELECT ruta_archivo FROM tareas_adjuntos WHERE id = :id AND eliminado = false");
        $sel->execute([':id' => $idAdjunto]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $sql = "UPDATE tareas_adjuntos
                SET eliminado = true, deleted_by = :uid, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $idAdjunto, ':uid' => $idUsuario]);

        return $row['ruta_archivo'];
    }

    /**
     * Busca usuarios del sistema para el selector de responsables.
     */
    public function buscarUsuariosSistema(string $buscar, int $limit = 15): array
    {
        $sql = "SELECT id, nombre, mail, 'sistema' AS tipo
                FROM usuarios
                WHERE estado = 1
                  AND (nombre ILIKE :b OR mail ILIKE :b2)
                ORDER BY nombre ASC
                LIMIT :lim";
        $st  = $this->db->prepare($sql);
        $st->bindValue(':b',   '%' . $buscar . '%');
        $st->bindValue(':b2',  '%' . $buscar . '%');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca responsables propios (responsables_tareas) para el selector.
     */
    public function buscarResponsablesPropios(string $buscar, int $limit = 15): array
    {
        $sql = "SELECT id, cedula, nombre, correo AS mail, 'propio' AS tipo
                FROM responsables_tareas
                WHERE eliminado = false
                  AND (nombre ILIKE :b OR correo ILIKE :b2 OR cedula ILIKE :b3)
                ORDER BY nombre ASC
                LIMIT :lim";
        $st  = $this->db->prepare($sql);
        $st->bindValue(':b',   '%' . $buscar . '%');
        $st->bindValue(':b2',  '%' . $buscar . '%');
        $st->bindValue(':b3',  '%' . $buscar . '%');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CRUD responsables_tareas ─────────────────────────────────

    public function createResponsableTarea(array $data): int
    {
        $sql = "INSERT INTO responsables_tareas (cedula, nombre, correo, telefono, created_by, created_at)
                VALUES (:cedula, :nombre, :correo, :telefono, :created_by, CURRENT_TIMESTAMP)";
        $st  = $this->db->prepare($sql);
        $st->execute([
            ':cedula'     => $data['cedula'] ?? null,
            ':nombre'     => $data['nombre'],
            ':correo'     => $data['correo'],
            ':telefono'   => $data['telefono'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return $this->lastInsertId('responsables_tareas_id_seq');
    }

    public function findResponsableTareaByCedula(string $cedula): ?array
    {
        $sql = "SELECT * FROM responsables_tareas WHERE cedula = :cedula AND eliminado = false LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([':cedula' => $cedula]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findResponsableTareaByNameEmail(string $nombre, string $email): ?array
    {
        $sql = "SELECT * FROM responsables_tareas 
                WHERE (UPPER(nombre) = UPPER(:nombre) OR UPPER(correo) = UPPER(:email)) 
                  AND eliminado = false 
                LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([':nombre' => $nombre, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateResponsableTarea(int $id, array $data): bool
    {
        $sql = "UPDATE responsables_tareas
                SET cedula = :cedula, nombre = :nombre, correo = :correo,
                    telefono = :telefono, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        return $st->execute([
            ':cedula'     => $data['cedula'] ?? null,
            ':nombre'     => $data['nombre'],
            ':correo'     => $data['correo'],
            ':telefono'   => $data['telefono'] ?? null,
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
        ]);
    }
    public function findClienteTareaById(int $id): ?array
    {
        $sql = "SELECT * FROM clientes_tareas WHERE id = :id AND eliminado = false LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca en clientes_tareas para el selector del modal.
     * También busca en clientes de empresa para importar.
     */
    public function buscarClientesTareas(string $buscar, int $limit = 20): array
    {
        $sql = "SELECT id, ruc, nombre, correo, 'propio' AS origen
                FROM clientes_tareas
                WHERE eliminado = false
                  AND (nombre ILIKE :b OR correo ILIKE :b2 OR ruc ILIKE :b3)
                ORDER BY nombre ASC
                LIMIT :lim";
        $st  = $this->db->prepare($sql);
        $st->bindValue(':b',   '%' . $buscar . '%');
        $st->bindValue(':b2',  '%' . $buscar . '%');
        $st->bindValue(':b3',  '%' . $buscar . '%');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca clientes de la tabla operativa clientes (para importar al módulo).
     */
    public function buscarClientesEmpresa(string $buscar, ?int $idEmpresa = null, int $limit = 15): array
    {
        $sql = "SELECT DISTINCT c.id, c.identificacion AS ruc, c.nombre, 
                       COALESCE(c.email, '') AS correo, 'empresa' AS origen
                FROM clientes c
                WHERE c.eliminado = false
                  AND (c.nombre ILIKE :b OR c.email ILIKE :b2 OR c.identificacion ILIKE :b3)";
        
        if ($idEmpresa !== null && $idEmpresa > 0) {
            $sql .= " AND c.id_empresa = :id_empresa";
        }

        $sql .= " ORDER BY c.nombre ASC LIMIT :lim";

        $st  = $this->db->prepare($sql);
        $st->bindValue(':b',   '%' . $buscar . '%');
        $st->bindValue(':b2',  '%' . $buscar . '%');
        $st->bindValue(':b3',  '%' . $buscar . '%');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        if ($idEmpresa !== null && $idEmpresa > 0) {
            $st->bindValue(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene correos de un cliente de clientes_tareas por nombre.
     */
    public function getCorreosClienteTarea(string $nombre): array
    {
        $sql = "SELECT id, ruc, nombre, correo
                FROM clientes_tareas
                WHERE eliminado = false
                  AND UPPER(nombre) = UPPER(:nombre)
                  AND correo IS NOT NULL AND correo <> ''
                ORDER BY correo ASC";
        $st  = $this->db->prepare($sql);
        $st->execute([':nombre' => $nombre]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CRUD clientes_tareas ─────────────────────────────────

    public function createClienteTarea(array $data): int
    {
        $sql = "INSERT INTO clientes_tareas (ruc, nombre, correo, telefono, created_by, created_at)
                VALUES (:ruc, :nombre, :correo, :telefono, :created_by, CURRENT_TIMESTAMP)";
        $st  = $this->db->prepare($sql);
        $st->execute([
            ':ruc'        => $data['ruc'] ?? null,
            ':nombre'     => $data['nombre'],
            ':correo'     => $data['correo'],
            ':telefono'   => $data['telefono'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return $this->lastInsertId('clientes_tareas_id_seq');
    }

    public function findClienteTareaByRuc(string $ruc): ?array
    {
        $sql = "SELECT * FROM clientes_tareas WHERE ruc = :ruc AND eliminado = false LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([':ruc' => $ruc]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findClienteTareaByNameEmail(string $nombre, string $email): ?array
    {
        $sql = "SELECT * FROM clientes_tareas 
                WHERE (UPPER(nombre) = UPPER(:nombre) OR UPPER(correo) = UPPER(:email)) 
                  AND eliminado = false 
                LIMIT 1";
        $st  = $this->db->prepare($sql);
        $st->execute([':nombre' => $nombre, ':email' => $email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateClienteTarea(int $id, array $data): bool
    {
        $sql = "UPDATE clientes_tareas
                SET ruc = :ruc, nombre = :nombre, correo = :correo,
                    telefono = :telefono, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND eliminado = false";
        $st  = $this->db->prepare($sql);
        return $st->execute([
            ':ruc'        => $data['ruc'] ?? null,
            ':nombre'     => $data['nombre'],
            ':correo'     => $data['correo'],
            ':telefono'   => $data['telefono'] ?? null,
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
        ]);
    }

    public function getAlertaTareasCount(int $idUsuario): int
    {
        $sql = "SELECT COUNT(*) 
                FROM tareas t
                WHERE t.eliminado = false 
                  AND (
                    t.estado = 'vencida' 
                    OR (t.estado = 'por_realizar' AND t.fecha_tarea <= (CURRENT_DATE + INTERVAL '2 days'))
                  )
                  AND (
                    t.created_by = :id_usuario 
                    OR t.id IN (
                      SELECT id_tarea FROM tareas_responsables 
                      WHERE id_usuario = :id_usuario_rep
                         OR (:u_mail <> '' AND LOWER(correo_cache) = :u_mail_aux)
                    )
                  )";
        $selMail = $this->db->prepare("SELECT mail FROM usuarios WHERE id = :id_u");
        $selMail->execute([':id_u' => $idUsuario]);
        $uMail = strtolower(trim((string)$selMail->fetchColumn()));

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_usuario'      => $idUsuario,
            ':id_usuario_rep'  => $idUsuario,
            ':u_mail'          => $uMail,
            ':u_mail_aux'      => $uMail
        ]);
        return (int)$st->fetchColumn();
    }
}

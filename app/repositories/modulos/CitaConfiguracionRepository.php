<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CitaConfiguracionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('citas_tipos');
    }

    // ─── TIPOS DE CITA ────────────────────────────────────────────────────────

    public function getTipos(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        $validas = ['nombre', 'duracion_minutos', 'precio', 'tipo_pago', 'status'];
        if (!in_array($ordenCol, $validas, true)) $ordenCol = 'nombre';
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = 'WHERE id_empresa = :id_empresa AND eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= ' AND (nombre ILIKE :buscar OR descripcion ILIKE :buscar)';
            $params[':buscar'] = '%' . $buscar . '%';
        }

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM citas_tipos $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset = ($page - 1) * $perPage;
            $limit  = $perPage > 0 ? "LIMIT $perPage OFFSET $offset" : '';
            $st = $this->db->prepare("SELECT * FROM citas_tipos $where ORDER BY \"$ordenCol\" $ordenDir $limit");
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // Adjuntar recursos_ids a cada tipo
            if (!empty($rows)) {
                $ids = implode(',', array_map('intval', array_column($rows, 'id')));
                $tr  = $this->db->query(
                    "SELECT id_tipo, id_recurso FROM citas_tipos_recursos WHERE id_tipo IN ($ids) ORDER BY id_tipo, id_recurso"
                )->fetchAll(PDO::FETCH_ASSOC);
                $mapa = [];
                foreach ($tr as $r) {
                    $mapa[(int)$r['id_tipo']][] = (int)$r['id_recurso'];
                }
                foreach ($rows as &$row) {
                    $row['recursos_ids'] = $mapa[(int)$row['id']] ?? [];
                }
                unset($row);
            }
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getTipoPorId(int $id, int $idEmpresa): ?array
    {
        return $this->findById($id, $idEmpresa);
    }

    public function createTipo(array $data): int
    {
        $st = $this->db->prepare("
            INSERT INTO citas_tipos
                (id_empresa, nombre, descripcion, duracion_minutos, precio, requiere_pago, tipo_pago, anticipo_porcentaje, color, status, created_by, created_at, eliminado)
            VALUES
                (:id_empresa, :nombre, :descripcion, :duracion_minutos, :precio, :requiere_pago, :tipo_pago, :anticipo_porcentaje, :color, :status, :created_by, CURRENT_TIMESTAMP, false)
        ");
        $st->execute([
            ':id_empresa'          => $data['id_empresa'],
            ':nombre'              => $data['nombre'],
            ':descripcion'         => $data['descripcion'],
            ':duracion_minutos'    => $data['duracion_minutos'],
            ':precio'              => $data['precio'],
            ':requiere_pago'       => $data['requiere_pago'] ? 'true' : 'false',
            ':tipo_pago'           => $data['tipo_pago'],
            ':anticipo_porcentaje' => $data['anticipo_porcentaje'],
            ':color'               => $data['color'],
            ':status'              => $data['status'],
            ':created_by'          => $data['id_usuario'],
        ]);
        return $this->lastInsertId('citas_tipos_id_seq');
    }

    public function updateTipo(int $id, int $idEmpresa, array $data): bool
    {
        $st = $this->db->prepare("
            UPDATE citas_tipos SET
                nombre=:nombre, descripcion=:descripcion, duracion_minutos=:duracion_minutos,
                precio=:precio, requiere_pago=:requiere_pago, tipo_pago=:tipo_pago,
                anticipo_porcentaje=:anticipo_porcentaje, color=:color, status=:status,
                updated_by=:updated_by, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id AND id_empresa=:id_empresa AND eliminado=false
        ");
        return $st->execute([
            ':nombre'              => $data['nombre'],
            ':descripcion'         => $data['descripcion'],
            ':duracion_minutos'    => $data['duracion_minutos'],
            ':precio'              => $data['precio'],
            ':requiere_pago'       => $data['requiere_pago'] ? 'true' : 'false',
            ':tipo_pago'           => $data['tipo_pago'],
            ':anticipo_porcentaje' => $data['anticipo_porcentaje'],
            ':color'               => $data['color'],
            ':status'              => $data['status'],
            ':updated_by'          => $data['id_usuario'],
            ':id'                  => $id,
            ':id_empresa'          => $idEmpresa,
        ]);
    }

    public function deleteTipo(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE citas_tipos SET eliminado=true, deleted_by=:u, deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND id_empresa=:emp AND eliminado=false");
        return $st->execute([':id' => $id, ':emp' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ─── TIPO ↔ RECURSO ───────────────────────────────────────────────────────

    /** Retorna array de id_recurso asociados al tipo */
    public function getRecursosDeTipo(int $idTipo): array
    {
        $st = $this->db->prepare(
            "SELECT id_recurso FROM citas_tipos_recursos WHERE id_tipo = :it ORDER BY id_recurso"
        );
        $st->execute([':it' => $idTipo]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id_recurso'));
    }

    /**
     * Reemplaza la lista completa de recursos asociados a un tipo.
     * Si $idRecursos está vacío, elimina todas las asociaciones (sin restricción).
     */
    public function setRecursosDeTipo(int $idTipo, int $idEmpresa, array $idRecursos, int $idUsuario): void
    {
        $stDel = $this->db->prepare("DELETE FROM citas_tipos_recursos WHERE id_tipo = :it");
        $stDel->execute([':it' => $idTipo]);

        if (!empty($idRecursos)) {
            $stIns = $this->db->prepare(
                "INSERT INTO citas_tipos_recursos (id_empresa, id_tipo, id_recurso, created_by)
                 VALUES (:ie, :it, :ir, :cb)
                 ON CONFLICT (id_tipo, id_recurso) DO NOTHING"
            );
            foreach ($idRecursos as $idRec) {
                $stIns->execute([':ie' => $idEmpresa, ':it' => $idTipo, ':ir' => (int)$idRec, ':cb' => $idUsuario]);
            }
        }
    }

    // ─── RECURSOS ─────────────────────────────────────────────────────────────

    public function getRecursos(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        $validas = ['nombre', 'tipo', 'status'];
        if (!in_array($ordenCol, $validas, true)) $ordenCol = 'nombre';
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $where  = 'WHERE id_empresa = :id_empresa AND eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= ' AND (nombre ILIKE :buscar OR tipo ILIKE :buscar OR descripcion ILIKE :buscar)';
            $params[':buscar'] = '%' . $buscar . '%';
        }

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM citas_recursos $where");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $offset = ($page - 1) * $perPage;
            $limit  = $perPage > 0 ? "LIMIT $perPage OFFSET $offset" : '';
            $st = $this->db->prepare("SELECT * FROM citas_recursos $where ORDER BY \"$ordenCol\" $ordenDir $limit");
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getRecursoPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM citas_recursos WHERE id=:id AND id_empresa=:emp AND eliminado=false");
        $st->execute([':id' => $id, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createRecurso(array $data): int
    {
        $st = $this->db->prepare("
            INSERT INTO citas_recursos (id_empresa, nombre, tipo, descripcion, status, created_by, created_at, eliminado)
            VALUES (:id_empresa, :nombre, :tipo, :descripcion, :status, :created_by, CURRENT_TIMESTAMP, false)
        ");
        $st->execute([
            ':id_empresa'  => $data['id_empresa'],
            ':nombre'      => $data['nombre'],
            ':tipo'        => $data['tipo'],
            ':descripcion' => $data['descripcion'],
            ':status'      => $data['status'],
            ':created_by'  => $data['id_usuario'],
        ]);
        return $this->lastInsertId('citas_recursos_id_seq');
    }

    public function updateRecurso(int $id, int $idEmpresa, array $data): bool
    {
        $st = $this->db->prepare("
            UPDATE citas_recursos SET nombre=:nombre, tipo=:tipo, descripcion=:descripcion, status=:status,
                updated_by=:updated_by, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id AND id_empresa=:id_empresa AND eliminado=false
        ");
        return $st->execute([
            ':nombre'      => $data['nombre'],
            ':tipo'        => $data['tipo'],
            ':descripcion' => $data['descripcion'],
            ':status'      => $data['status'],
            ':updated_by'  => $data['id_usuario'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa,
        ]);
    }

    public function deleteRecurso(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE citas_recursos SET eliminado=true, deleted_by=:u, deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND id_empresa=:emp AND eliminado=false");
        return $st->execute([':id' => $id, ':emp' => $idEmpresa, ':u' => $idUsuario]);
    }

    public function getRecursosActivos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT id, nombre, tipo FROM citas_recursos WHERE id_empresa=:emp AND status=1 AND eliminado=false ORDER BY nombre");
        $st->execute([':emp' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── HORARIOS ─────────────────────────────────────────────────────────────

    public function getHorarios(int $idEmpresa, ?int $idRecurso = null): array
    {
        $where  = 'WHERE ch.id_empresa = :id_empresa AND ch.eliminado = false';
        $params = [':id_empresa' => $idEmpresa];

        if ($idRecurso !== null) {
            $where .= ' AND ch.id_recurso = :id_recurso';
            $params[':id_recurso'] = $idRecurso;
        }

        $st = $this->db->prepare("
            SELECT ch.*, cr.nombre AS nombre_recurso
            FROM citas_horarios ch
            LEFT JOIN citas_recursos cr ON cr.id = ch.id_recurso AND cr.eliminado = false
            $where
            ORDER BY COALESCE(cr.nombre, ''), ch.dia_semana, ch.hora_inicio
        ");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHorarioPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM citas_horarios WHERE id=:id AND id_empresa=:emp AND eliminado=false");
        $st->execute([':id' => $id, ':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createHorario(array $data): int
    {
        $st = $this->db->prepare("
            INSERT INTO citas_horarios (id_empresa, id_recurso, dia_semana, hora_inicio, hora_fin, status, created_by, created_at, eliminado)
            VALUES (:id_empresa, :id_recurso, :dia_semana, :hora_inicio, :hora_fin, true, :created_by, CURRENT_TIMESTAMP, false)
        ");
        $st->execute([
            ':id_empresa'  => $data['id_empresa'],
            ':id_recurso'  => $data['id_recurso'],
            ':dia_semana'  => $data['dia_semana'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin'    => $data['hora_fin'],
            ':created_by'  => $data['id_usuario'],
        ]);
        return $this->lastInsertId('citas_horarios_id_seq');
    }

    public function updateHorario(int $id, int $idEmpresa, array $data): bool
    {
        $st = $this->db->prepare("
            UPDATE citas_horarios SET id_recurso=:id_recurso, dia_semana=:dia_semana,
                hora_inicio=:hora_inicio, hora_fin=:hora_fin,
                updated_by=:updated_by, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id AND id_empresa=:id_empresa AND eliminado=false
        ");
        return $st->execute([
            ':id_recurso'  => $data['id_recurso'],
            ':dia_semana'  => $data['dia_semana'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin'    => $data['hora_fin'],
            ':updated_by'  => $data['id_usuario'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa,
        ]);
    }

    public function deleteHorario(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare("UPDATE citas_horarios SET eliminado=true, deleted_by=:u, deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND id_empresa=:emp AND eliminado=false");
        return $st->execute([':id' => $id, ':emp' => $idEmpresa, ':u' => $idUsuario]);
    }

    // ─── PORTAL ───────────────────────────────────────────────────────────────

    public function getPortalConfig(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM citas_config_portal WHERE id_empresa=:emp AND eliminado=false LIMIT 1");
        $st->execute([':emp' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function savePortalConfig(int $idEmpresa, array $data): void
    {
        $existing = $this->getPortalConfig($idEmpresa);
        $params   = $this->portalParams($data);

        if ($existing) {
            $st = $this->db->prepare("
                UPDATE citas_config_portal SET
                    slug=:slug, titulo=:titulo, mensaje_bienvenida=:mensaje_bienvenida,
                    color_primario=:color_primario, activo=:activo,
                    requiere_confirmacion=:requiere_confirmacion,
                    max_dias_anticipacion=:max_dias_anticipacion,
                    min_horas_anticipacion=:min_horas_anticipacion,
                    permite_pagos_online=:permite_pagos_online,
                    updated_by=:id_usuario, updated_at=CURRENT_TIMESTAMP
                WHERE id_empresa=:id_empresa AND eliminado=false
            ");
        } else {
            $st = $this->db->prepare("
                INSERT INTO citas_config_portal
                    (id_empresa, slug, titulo, mensaje_bienvenida, color_primario, activo,
                     requiere_confirmacion, max_dias_anticipacion, min_horas_anticipacion,
                     permite_pagos_online, created_by, created_at, eliminado)
                VALUES
                    (:id_empresa, :slug, :titulo, :mensaje_bienvenida, :color_primario, :activo,
                     :requiere_confirmacion, :max_dias_anticipacion, :min_horas_anticipacion,
                     :permite_pagos_online, :id_usuario, CURRENT_TIMESTAMP, false)
            ");
        }

        $st->execute(array_merge($params, [':id_empresa' => $idEmpresa, ':id_usuario' => $data['id_usuario']]));
    }

    public function slugExists(string $slug, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM citas_config_portal WHERE slug=:slug AND id_empresa!=:emp AND eliminado=false");
        $st->execute([':slug' => $slug, ':emp' => $idEmpresa]);
        return ((int) $st->fetchColumn()) > 0;
    }

    private function portalParams(array $data): array
    {
        return [
            ':slug'                   => $data['slug'],
            ':titulo'                 => $data['titulo'],
            ':mensaje_bienvenida'     => $data['mensaje_bienvenida'],
            ':color_primario'         => $data['color_primario'],
            ':activo'                 => $data['activo'] ? 'true' : 'false',
            ':requiere_confirmacion'  => $data['requiere_confirmacion'] ? 'true' : 'false',
            ':max_dias_anticipacion'  => $data['max_dias_anticipacion'],
            ':min_horas_anticipacion' => $data['min_horas_anticipacion'],
            ':permite_pagos_online'   => $data['permite_pagos_online'] ? 'true' : 'false',
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class AlumnoRepository extends BaseRepository
{
    protected string $table = 'alumnos';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Listado con campus/nivel "actuales" resueltos por el período abierto
     * (o el más reciente si no hay ninguno abierto) — no se cachean en la
     * cabecera para evitar desincronización (CLAUDE.md §8).
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
        $whereSql = $this->getBaseWhere($idEmpresa, 'a', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (a.nombres ILIKE :b OR a.apellidos ILIKE :b OR a.numero_identificacion ILIKE :b OR a.codigo_alumno ILIKE :b OR cli.nombre ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $joins = "LEFT JOIN clientes cli ON cli.id = a.id_cliente
                  LEFT JOIN LATERAL (
                        SELECT ap.id_campus, ap.id_nivel, ap.anio_lectivo, ap.fecha_ingreso, ap.fecha_salida
                        FROM alumnos_periodos ap
                        WHERE ap.id_alumno = a.id AND ap.eliminado = false
                        ORDER BY (ap.fecha_salida IS NULL) DESC, ap.fecha_ingreso DESC
                        LIMIT 1
                  ) per ON true
                  LEFT JOIN alumnos_campus camp ON camp.id = per.id_campus
                  LEFT JOIN alumnos_niveles niv ON niv.id = per.id_nivel";

        $cols = [
            'nombres'      => 'a.nombres',
            'apellidos'    => 'a.apellidos',
            'codigo_alumno'=> 'a.codigo_alumno',
            'campus'       => 'camp.nombre',
            'nivel'        => 'niv.nombre',
            'estado_academico' => 'a.estado_academico',
            'representante'=> 'cli.nombre',
        ];
        $col = $cols[$ordenCol] ?? 'a.apellidos';
        $dir = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} a {$joins} {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT a.*, cli.nombre AS representante_nombre, cli.identificacion AS representante_identificacion,
                           camp.id AS campus_actual_id, camp.nombre AS campus_actual_nombre,
                           niv.id AS nivel_actual_id, niv.nombre AS nivel_actual_nombre,
                           per.anio_lectivo AS anio_lectivo_actual,
                           (per.fecha_salida IS NULL AND per.fecha_ingreso IS NOT NULL) AS matricula_vigente
                    FROM {$this->table} a {$joins}
                    {$whereSql}
                    ORDER BY {$col} {$dir}, a.id DESC";
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);

        return ['total' => $total, 'rows' => $stRows->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, codigo_alumno, nombres, apellidos, tipo_identificacion, numero_identificacion,
                    fecha_nacimiento, sexo, nacionalidad, foto_ruta, estado_academico,
                    id_cliente, relacion_representante, id_punto_emision,
                    tipo_sangre, alergias_condiciones, contacto_emergencia_nombre, contacto_emergencia_telefono,
                    observaciones, created_by, updated_by
                ) VALUES (
                    :id_empresa, :codigo_alumno, :nombres, :apellidos, :tipo_identificacion, :numero_identificacion,
                    :fecha_nacimiento, :sexo, :nacionalidad, :foto_ruta, :estado_academico,
                    :id_cliente, :relacion_representante, :id_punto_emision,
                    :tipo_sangre, :alergias_condiciones, :contacto_emergencia_nombre, :contacto_emergencia_telefono,
                    :observaciones, :id_usuario, :id_usuario
                )";
        $st = $this->db->prepare($sql);
        $st->execute($this->paramsDesdeData($data));
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET
                    codigo_alumno = :codigo_alumno, nombres = :nombres, apellidos = :apellidos,
                    tipo_identificacion = :tipo_identificacion, numero_identificacion = :numero_identificacion,
                    fecha_nacimiento = :fecha_nacimiento, sexo = :sexo, nacionalidad = :nacionalidad,
                    foto_ruta = :foto_ruta, estado_academico = :estado_academico,
                    id_cliente = :id_cliente, relacion_representante = :relacion_representante, id_punto_emision = :id_punto_emision,
                    tipo_sangre = :tipo_sangre, alergias_condiciones = :alergias_condiciones,
                    contacto_emergencia_nombre = :contacto_emergencia_nombre, contacto_emergencia_telefono = :contacto_emergencia_telefono,
                    observaciones = :observaciones, updated_by = :id_usuario, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = $this->paramsDesdeData($data);
        $params[':id'] = $id;
        $params[':id_empresa'] = $idEmpresa;
        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    private function paramsDesdeData(array $data): array
    {
        return [
            ':id_empresa'                   => $data['id_empresa'],
            ':codigo_alumno'                => $data['codigo_alumno'] !== '' ? $data['codigo_alumno'] : null,
            ':nombres'                      => $data['nombres'],
            ':apellidos'                    => $data['apellidos'],
            ':tipo_identificacion'          => $data['tipo_identificacion'] !== '' ? $data['tipo_identificacion'] : null,
            ':numero_identificacion'        => $data['numero_identificacion'] !== '' ? $data['numero_identificacion'] : null,
            ':fecha_nacimiento'             => $data['fecha_nacimiento'] !== '' ? $data['fecha_nacimiento'] : null,
            ':sexo'                         => $data['sexo'] !== '' ? $data['sexo'] : null,
            ':nacionalidad'                 => $data['nacionalidad'] !== '' ? $data['nacionalidad'] : null,
            ':foto_ruta'                    => $data['foto_ruta'] !== '' ? $data['foto_ruta'] : null,
            ':estado_academico'             => $data['estado_academico'],
            ':id_cliente'                   => $data['id_cliente'],
            ':relacion_representante'       => $data['relacion_representante'] !== '' ? $data['relacion_representante'] : null,
            ':id_punto_emision'             => $data['id_punto_emision'] > 0 ? $data['id_punto_emision'] : null,
            ':tipo_sangre'                  => $data['tipo_sangre'] !== '' ? $data['tipo_sangre'] : null,
            ':alergias_condiciones'         => $data['alergias_condiciones'] !== '' ? $data['alergias_condiciones'] : null,
            ':contacto_emergencia_nombre'   => $data['contacto_emergencia_nombre'] !== '' ? $data['contacto_emergencia_nombre'] : null,
            ':contacto_emergencia_telefono' => $data['contacto_emergencia_telefono'] !== '' ? $data['contacto_emergencia_telefono'] : null,
            ':observaciones'                => $data['observaciones'] !== '' ? $data['observaciones'] : null,
            ':id_usuario'                   => $data['id_usuario'],
        ];
    }

    public function deleteLogic(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET eliminado = true, deleted_by = :id_u, deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':id_u' => $idUsuario]);
    }

    // ------------------------------------------------------------------
    // Matrícula (períodos): historial + sincronización (mismo patrón que
    // EmpleadoRepository::getPeriodos/syncPeriodos).
    // ------------------------------------------------------------------

    public function getPeriodos(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT ap.*, camp.nombre AS campus_nombre, niv.nombre AS nivel_nombre
                FROM alumnos_periodos ap
                LEFT JOIN alumnos_campus camp ON camp.id = ap.id_campus
                LEFT JOIN alumnos_niveles niv ON niv.id = ap.id_nivel
                WHERE ap.id_alumno = :id_a AND ap.id_empresa = :id_e AND ap.eliminado = false
                ORDER BY ap.fecha_ingreso DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_a' => $idAlumno, ':id_e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncPeriodos(int $idAlumno, int $idEmpresa, array $periodos, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_periodos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($periodos)) {
            return;
        }

        $sql = "INSERT INTO alumnos_periodos (
                    id_alumno, id_empresa, id_campus, id_nivel, anio_lectivo,
                    fecha_ingreso, fecha_salida, motivo_salida, estado, observacion, created_by, updated_by
                ) VALUES (
                    :a, :e, :campus, :nivel, :anio, :fi, :fs, :motivo, :estado, :obs, :u, :u
                )";
        $st = $this->db->prepare($sql);
        foreach ($periodos as $p) {
            if (empty($p['fecha_ingreso'])) {
                continue;
            }
            $abierto = empty($p['fecha_salida']);
            $st->execute([
                ':a'      => $idAlumno,
                ':e'      => $idEmpresa,
                ':campus' => !empty($p['id_campus']) ? (int)$p['id_campus'] : null,
                ':nivel'  => !empty($p['id_nivel']) ? (int)$p['id_nivel'] : null,
                ':anio'   => $p['anio_lectivo'] ?? null,
                ':fi'     => $p['fecha_ingreso'],
                ':fs'     => !empty($p['fecha_salida']) ? $p['fecha_salida'] : null,
                ':motivo' => !empty($p['motivo_salida']) ? $p['motivo_salida'] : null,
                ':estado' => $abierto ? 'activo' : 'finalizado',
                ':obs'    => $p['observacion'] ?? null,
                ':u'      => $idUsuario,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Horario (individual por alumno).
    // ------------------------------------------------------------------

    public function getHorarios(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT * FROM alumnos_horarios
                WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false
                ORDER BY dia_semana ASC, hora_inicio ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncHorarios(int $idAlumno, int $idEmpresa, array $horarios, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_horarios SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($horarios)) {
            return;
        }

        $sql = "INSERT INTO alumnos_horarios (id_alumno, id_empresa, dia_semana, hora_inicio, hora_fin, jornada, observacion, created_by, updated_by)
                VALUES (:a, :e, :dia, :hi, :hf, :jor, :obs, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($horarios as $h) {
            if (empty($h['dia_semana']) || empty($h['hora_inicio']) || empty($h['hora_fin'])) {
                continue;
            }
            $st->execute([
                ':a'   => $idAlumno,
                ':e'   => $idEmpresa,
                ':dia' => (int) $h['dia_semana'],
                ':hi'  => $h['hora_inicio'],
                ':hf'  => $h['hora_fin'],
                ':jor' => $h['jornada'] ?? null,
                ':obs' => $h['observacion'] ?? null,
                ':u'   => $idUsuario,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Servicios/productos predeterminados a facturar.
    // ------------------------------------------------------------------

    public function getServicios(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT s.*, p.nombre AS producto_nombre, p.precio_base AS producto_precio_base
                FROM alumnos_servicios s
                LEFT JOIN productos p ON p.id = s.id_producto
                WHERE s.id_alumno = :a AND s.id_empresa = :e AND s.eliminado = false
                ORDER BY s.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncServicios(int $idAlumno, int $idEmpresa, array $servicios, int $idUsuario): void
    {
        $st = $this->db->prepare("UPDATE alumnos_servicios SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP
                                   WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':u' => $idUsuario, ':a' => $idAlumno, ':e' => $idEmpresa]);

        if (empty($servicios)) {
            return;
        }

        $sql = "INSERT INTO alumnos_servicios (id_alumno, id_empresa, id_producto, cantidad_default, precio_override, frecuencia, activo, created_by, updated_by)
                VALUES (:a, :e, :prod, :cant, :precio, :frec, :act, :u, :u)";
        $st = $this->db->prepare($sql);
        foreach ($servicios as $s) {
            if (empty($s['id_producto'])) {
                continue;
            }
            $st->execute([
                ':a'      => $idAlumno,
                ':e'      => $idEmpresa,
                ':prod'   => (int) $s['id_producto'],
                ':cant'   => (float) ($s['cantidad_default'] ?? 1),
                ':precio' => ($s['precio_override'] ?? '') !== '' ? (float) $s['precio_override'] : null,
                ':frec'   => $s['frecuencia'] ?? 'mensual',
                ':act'    => (!isset($s['activo']) || $s['activo']) ? 'true' : 'false',
                ':u'      => $idUsuario,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Documentos adjuntos (altas/bajas individuales, no se sincronizan
    // en bloque porque cada fila representa un archivo ya subido).
    // ------------------------------------------------------------------

    public function getDocumentos(int $idAlumno, int $idEmpresa): array
    {
        $sql = "SELECT * FROM alumnos_documentos
                WHERE id_alumno = :a AND id_empresa = :e AND eliminado = false
                ORDER BY fecha_carga DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAlumno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarDocumento(int $idAlumno, int $idEmpresa, array $data, int $idUsuario): int
    {
        $sql = "INSERT INTO alumnos_documentos (id_alumno, id_empresa, tipo_documento, nombre_archivo, ruta_archivo, id_usuario)
                VALUES (:a, :e, :tipo, :nombre, :ruta, :u)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':a'      => $idAlumno,
            ':e'      => $idEmpresa,
            ':tipo'   => $data['tipo_documento'],
            ':nombre' => $data['nombre_archivo'],
            ':ruta'   => $data['ruta_archivo'],
            ':u'      => $idUsuario,
        ]);
        return $this->lastInsertId();
    }

    public function eliminarDocumento(int $id, int $idAlumno, int $idEmpresa, int $idUsuario): ?array
    {
        $st = $this->db->prepare("SELECT * FROM alumnos_documentos WHERE id = :id AND id_alumno = :a AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':a' => $idAlumno, ':e' => $idEmpresa]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            return null;
        }
        $upd = $this->db->prepare("UPDATE alumnos_documentos SET eliminado = true, deleted_by = :u, deleted_at = CURRENT_TIMESTAMP WHERE id = :id");
        $upd->execute([':u' => $idUsuario, ':id' => $id]);
        return $doc;
    }

    // ------------------------------------------------------------------
    // Catálogos auxiliares para el modal.
    // ------------------------------------------------------------------

    public function getPuntosEmisionParaSelect(int $idEmpresa): array
    {
        $sql = "SELECT pe.id, pe.nombre, pe.codigo_punto, e.nombre AS establecimiento_nombre
                FROM empresa_punto_emision pe
                LEFT JOIN empresa_establecimiento e ON e.id = pe.id_establecimiento
                WHERE pe.id_empresa = :id_empresa AND pe.eliminado = false AND pe.estado = 'activo'
                ORDER BY pe.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

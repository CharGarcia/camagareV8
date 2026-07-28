<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Acceso a datos del módulo Videollamadas.
 *
 * Cubre las cinco tablas del módulo (salas, participantes, eventos, config y
 * grabaciones). Sin lógica de negocio: eso vive en VideollamadaService.
 *
 * Todas las consultas filtran por id_empresa + eliminado = false vía
 * getBaseWhere(), que además aplica el filtro de registros propios cuando el
 * usuario no tiene permiso de acceso total (§6 de CLAUDE.md).
 */
class VideollamadaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('videollamadas_salas');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Salas
    // ────────────────────────────────────────────────────────────────────

    /**
     * Listado paginado del módulo.
     *
     * @param int|null $idUsuarioFiltro Si no es null, solo devuelve las salas creadas
     *                                  por ese usuario (permiso sin acceso total).
     */
    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_inicio',
        string $ordenDir = 'DESC',
        ?int $idUsuarioFiltro = null
    ): array {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = $this->getBaseWhere($idEmpresa, 's', $idUsuarioFiltro);
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (s.titulo ILIKE :buscar OR s.codigo ILIKE :buscar
                             OR s.descripcion ILIKE :buscar OR u.nombre ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'titulo'    => 's.titulo',
                'codigo'    => 's.codigo',
                'anfitrion' => 'u.nombre',
                'desc'      => 's.descripcion',
            ],
            'exacto' => [
                'estado'    => 's.estado',
                'tipo'      => 's.tipo',
                'proveedor' => 's.proveedor',
            ],
            'fecha' => [
                'fecha'        => 's.fecha_inicio',
                'fecha_inicio' => 's.fecha_inicio',
                'creado'       => 's.created_at',
            ],
            'numerico' => [
                'participantes' => 's.max_participantes',
                'duracion'      => 's.duracion_minutos',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM videollamadas_salas s
                     LEFT JOIN usuarios u ON s.id_anfitrion = u.id
                     $where";
        $total = (int) $this->query($sqlCount, $params)->fetchColumn();

        $allowedCols = ['id', 'codigo', 'titulo', 'tipo', 'estado', 'fecha_inicio', 'created_at'];
        if (!in_array($ordenCol, $allowedCols, true)) {
            $ordenCol = 'fecha_inicio';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT s.*,
                       u.nombre AS anfitrion_nombre,
                       (SELECT COUNT(*) FROM videollamadas_participantes p
                         WHERE p.id_sala = s.id AND p.eliminado = FALSE) AS total_participantes
                FROM videollamadas_salas s
                LEFT JOIN usuarios u ON s.id_anfitrion = u.id
                $where
                ORDER BY s.$ordenCol $ordenDir NULLS LAST
                LIMIT $perPage OFFSET $offset";

        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT s.*,
                       u.nombre AS anfitrion_nombre,
                       uc.nombre AS creador_nombre
                FROM videollamadas_salas s
                LEFT JOIN usuarios u ON s.id_anfitrion = u.id
                LEFT JOIN usuarios uc ON s.created_by = uc.id
                WHERE s.id = :id AND s.id_empresa = :id_empresa AND s.eliminado = FALSE";
        $row = $this->query($sql, [':id' => $id, ':id_empresa' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca por el código público de la sala.
     * No filtra por empresa a propósito: el código es la dirección que se
     * comparte y es único global. Quien llame DEBE validar el acceso después.
     */
    public function getPorCodigo(string $codigo): ?array
    {
        $sql = "SELECT * FROM videollamadas_salas WHERE codigo = :codigo AND eliminado = FALSE";
        $row = $this->query($sql, [':codigo' => $codigo])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Solo el estado de la sala. Lo consulta el poll de señalización una vez por
     * segundo, así que se mantiene lo más liviano posible.
     */
    public function getEstado(int $idSala, int $idEmpresa): string
    {
        $sql = "SELECT estado FROM videollamadas_salas
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $estado = $this->query($sql, [':id' => $idSala, ':emp' => $idEmpresa])->fetchColumn();
        return $estado !== false ? (string) $estado : 'finalizada';
    }

    public function existeCodigo(string $codigo): bool
    {
        $sql = "SELECT COUNT(*) FROM videollamadas_salas WHERE codigo = :codigo AND eliminado = FALSE";
        return (int) $this->query($sql, [':codigo' => $codigo])->fetchColumn() > 0;
    }

    public function insertSala(array $data): int
    {
        $sql = "INSERT INTO videollamadas_salas (
                    id_empresa, codigo, titulo, descripcion, tipo, proveedor,
                    fecha_inicio, fecha_fin, duracion_minutos, id_anfitrion, estado,
                    sala_espera, permite_invitados, max_participantes, grabar,
                    created_by, updated_by
                ) VALUES (
                    :id_empresa, :codigo, :titulo, :descripcion, :tipo, :proveedor,
                    :fecha_inicio, :fecha_fin, :duracion, :id_anfitrion, :estado,
                    :sala_espera, :permite_invitados, :max_participantes, :grabar,
                    :usr, :usr
                ) RETURNING id";

        $st = $this->query($sql, [
            ':id_empresa'        => (int) $data['id_empresa'],
            ':codigo'            => (string) $data['codigo'],
            ':titulo'            => (string) $data['titulo'],
            ':descripcion'       => $data['descripcion'] ?? null,
            ':tipo'              => $data['tipo'] ?? 'instantanea',
            ':proveedor'         => $data['proveedor'] ?? 'interno',
            ':fecha_inicio'      => $data['fecha_inicio'] ?? null,
            ':fecha_fin'         => $data['fecha_fin'] ?? null,
            ':duracion'          => !empty($data['duracion_minutos']) ? (int) $data['duracion_minutos'] : null,
            ':id_anfitrion'      => (int) $data['id_anfitrion'],
            ':estado'            => $data['estado'] ?? 'programada',
            ':sala_espera'       => !empty($data['sala_espera']),
            ':permite_invitados' => !empty($data['permite_invitados']),
            ':max_participantes' => (int) ($data['max_participantes'] ?? 6),
            ':grabar'            => !empty($data['grabar']),
            ':usr'               => (int) $data['usuario_id'],
        ]);

        return (int) $st->fetchColumn();
    }

    public function updateSala(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE videollamadas_salas SET
                    titulo = :titulo,
                    descripcion = :descripcion,
                    tipo = :tipo,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    duracion_minutos = :duracion,
                    id_anfitrion = :id_anfitrion,
                    sala_espera = :sala_espera,
                    permite_invitados = :permite_invitados,
                    max_participantes = :max_participantes,
                    grabar = :grabar,
                    updated_by = :usr,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";

        $st = $this->query($sql, [
            ':titulo'            => (string) $data['titulo'],
            ':descripcion'       => $data['descripcion'] ?? null,
            ':tipo'              => $data['tipo'] ?? 'instantanea',
            ':fecha_inicio'      => $data['fecha_inicio'] ?? null,
            ':fecha_fin'         => $data['fecha_fin'] ?? null,
            ':duracion'          => !empty($data['duracion_minutos']) ? (int) $data['duracion_minutos'] : null,
            ':id_anfitrion'      => (int) $data['id_anfitrion'],
            ':sala_espera'       => !empty($data['sala_espera']),
            ':permite_invitados' => !empty($data['permite_invitados']),
            ':max_participantes' => (int) ($data['max_participantes'] ?? 6),
            ':grabar'            => !empty($data['grabar']),
            ':usr'               => (int) $data['usuario_id'],
            ':id'                => $id,
            ':id_empresa'        => $idEmpresa,
        ]);

        return $st->rowCount() > 0;
    }

    /** Cambia el estado de la sala (programada → en_curso → finalizada). */
    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): bool
    {
        $campoFecha = match ($estado) {
            'en_curso'   => ', iniciada_at = COALESCE(iniciada_at, CURRENT_TIMESTAMP)',
            'finalizada' => ', finalizada_at = CURRENT_TIMESTAMP',
            default      => '',
        };

        $sql = "UPDATE videollamadas_salas
                SET estado = :estado, updated_by = :usr, updated_at = CURRENT_TIMESTAMP {$campoFecha}
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";

        $st = $this->query($sql, [
            ':estado'     => $estado,
            ':usr'        => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
        ]);
        return $st->rowCount() > 0;
    }

    /** Eliminación lógica de la sala (§5: nunca se borra físicamente). */
    public function softDeleteSala(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE videollamadas_salas
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->query($sql, [':usr' => $idUsuario, ':id' => $id, ':id_empresa' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Participantes
    // ────────────────────────────────────────────────────────────────────

    public function getParticipantes(int $idSala, int $idEmpresa): array
    {
        $sql = "SELECT p.*, u.nombre AS usuario_nombre, u.mail AS usuario_email
                FROM videollamadas_participantes p
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE p.id_sala = :id_sala AND p.id_empresa = :id_empresa AND p.eliminado = FALSE
                ORDER BY CASE p.rol WHEN 'anfitrion' THEN 1 WHEN 'moderador' THEN 2 ELSE 3 END,
                         COALESCE(u.nombre, p.nombre_invitado)";
        return $this->query($sql, [':id_sala' => $idSala, ':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertParticipante(array $data): int
    {
        $sql = "INSERT INTO videollamadas_participantes (
                    id_empresa, id_sala, id_usuario, nombre_invitado, email,
                    token_acceso, rol, estado, created_by, updated_by
                ) VALUES (
                    :id_empresa, :id_sala, :id_usuario, :nombre_invitado, :email,
                    :token, :rol, :estado, :usr, :usr
                ) RETURNING id";

        $st = $this->query($sql, [
            ':id_empresa'      => (int) $data['id_empresa'],
            ':id_sala'         => (int) $data['id_sala'],
            ':id_usuario'      => !empty($data['id_usuario']) ? (int) $data['id_usuario'] : null,
            ':nombre_invitado' => $data['nombre_invitado'] ?? null,
            ':email'           => $data['email'] ?? null,
            ':token'           => $data['token_acceso'] ?? null,
            ':rol'             => $data['rol'] ?? 'participante',
            ':estado'          => $data['estado'] ?? 'invitado',
            ':usr'             => (int) $data['usuario_id'],
        ]);

        return (int) $st->fetchColumn();
    }

    public function getIdParticipante(int $idSala, int $idEmpresa, int $idUsuario): ?int
    {
        $sql = "SELECT id FROM videollamadas_participantes
                WHERE id_sala = :sala AND id_empresa = :emp AND id_usuario = :usr AND eliminado = FALSE";
        $id = $this->query($sql, [':sala' => $idSala, ':emp' => $idEmpresa, ':usr' => $idUsuario])->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * Marca al participante como conectado.
     *
     * `ultima_conexion` guarda el inicio del tramo de conexión actual (no se
     * refresca en cada poll): es la referencia para calcular cuánto estuvo
     * dentro cuando se desconecte.
     */
    public function marcarConexion(int $idSala, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE videollamadas_participantes
                SET estado = 'conectado',
                    primera_conexion = COALESCE(primera_conexion, CURRENT_TIMESTAMP),
                    ultima_conexion = CURRENT_TIMESTAMP,
                    ip = :ip,
                    user_agent = :ua,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_sala = :sala AND id_empresa = :emp AND id_usuario = :usr AND eliminado = FALSE";
        $this->query($sql, [
            ':ip'   => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ':ua'   => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ':sala' => $idSala,
            ':emp'  => $idEmpresa,
            ':usr'  => $idUsuario,
        ]);
    }

    /** Marca la desconexión y acumula el tiempo del tramo que termina. */
    public function marcarDesconexion(int $idSala, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE videollamadas_participantes
                SET estado = 'desconectado',
                    segundos_conectado = segundos_conectado + GREATEST(0,
                        EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - COALESCE(ultima_conexion, CURRENT_TIMESTAMP)))::int),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_sala = :sala AND id_empresa = :emp AND id_usuario = :usr
                  AND estado = 'conectado' AND eliminado = FALSE";
        $this->query($sql, [':sala' => $idSala, ':emp' => $idEmpresa, ':usr' => $idUsuario]);
    }

    /**
     * Marca como eliminados todos los participantes de una sala.
     * Se usa al reemplazar la lista completa en una edición.
     */
    public function softDeleteParticipantes(int $idSala, int $idEmpresa, int $idUsuario): int
    {
        $sql = "UPDATE videollamadas_participantes
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr
                WHERE id_sala = :id_sala AND id_empresa = :id_empresa AND eliminado = FALSE";
        return $this->query($sql, [':usr' => $idUsuario, ':id_sala' => $idSala, ':id_empresa' => $idEmpresa])->rowCount();
    }

    /**
     * Usuarios que pueden ser invitados a una sala de esta empresa.
     *
     * Son los asignados a la empresa (tabla empresa_asignada) más los de nivel 3,
     * que tienen acceso a todas las empresas y por eso no siempre están asignados.
     */
    public function getUsuariosEmpresa(int $idEmpresa): array
    {
        $sql = "SELECT u.id, u.nombre, u.mail AS email, u.nivel
                FROM usuarios u
                WHERE u.eliminado = FALSE
                  AND u.estado = '1'
                  AND (
                        EXISTS (SELECT 1 FROM empresa_asignada ea
                                 WHERE ea.id_usuario = u.id AND ea.id_empresa = :emp)
                        OR u.nivel = 3
                      )
                ORDER BY u.nombre";
        return $this->query($sql, [':emp' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Eventos (bitácora append-only)
    // ────────────────────────────────────────────────────────────────────

    public function registrarEvento(int $idEmpresa, int $idSala, ?int $idParticipante, string $tipo, ?array $payload, int $idUsuario): void
    {
        $sql = "INSERT INTO videollamadas_eventos (
                    id_empresa, id_sala, id_participante, tipo, payload, created_by, updated_by
                ) VALUES (:emp, :sala, :part, :tipo, :payload, :usr, :usr)";
        $this->query($sql, [
            ':emp'     => $idEmpresa,
            ':sala'    => $idSala,
            ':part'    => $idParticipante,
            ':tipo'    => $tipo,
            ':payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ':usr'     => $idUsuario,
        ]);
    }

    public function getEventos(int $idSala, int $idEmpresa, int $limite = 200): array
    {
        $sql = "SELECT e.*, COALESCE(u.nombre, p.nombre_invitado) AS participante_nombre
                FROM videollamadas_eventos e
                LEFT JOIN videollamadas_participantes p ON e.id_participante = p.id
                LEFT JOIN usuarios u ON p.id_usuario = u.id
                WHERE e.id_sala = :id_sala AND e.id_empresa = :id_empresa AND e.eliminado = FALSE
                ORDER BY e.created_at DESC
                LIMIT {$limite}";
        return $this->query($sql, [':id_sala' => $idSala, ':id_empresa' => $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Configuración por empresa
    // ────────────────────────────────────────────────────────────────────

    public function getConfig(int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM videollamadas_config WHERE id_empresa = :emp AND eliminado = FALSE";
        $row = $this->query($sql, [':emp' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Crea la configuración por defecto de la empresa la primera vez que se usa el módulo. */
    public function crearConfigPorDefecto(int $idEmpresa, int $idUsuario): int
    {
        $sql = "INSERT INTO videollamadas_config (id_empresa, created_by, updated_by)
                VALUES (:emp, :usr, :usr) RETURNING id";
        return (int) $this->query($sql, [':emp' => $idEmpresa, ':usr' => $idUsuario])->fetchColumn();
    }

    /**
     * Guarda la configuración de la empresa.
     *
     * Los secretos (credencial TURN y token de API) usan COALESCE con el valor
     * actual: si llegan como null es porque el formulario los dejó en blanco, y
     * eso significa "no lo cambies", no "bórralo". El formulario nunca muestra
     * el valor guardado.
     */
    public function guardarConfig(int $idEmpresa, array $data, int $idUsuario): bool
    {
        $sql = "UPDATE videollamadas_config SET
                    max_participantes        = :max,
                    duracion_max_minutos     = :duracion,
                    umbral_proveedor_externo = :umbral,
                    stun_urls                = :stun,
                    turn_urls                = :turn_urls,
                    turn_usuario             = :turn_usuario,
                    turn_credencial          = COALESCE(:turn_credencial, turn_credencial),
                    turn_key_id              = :turn_key_id,
                    turn_api_token           = COALESCE(:turn_api_token, turn_api_token),
                    updated_by               = :usr,
                    updated_at               = CURRENT_TIMESTAMP
                WHERE id_empresa = :emp AND eliminado = FALSE";

        $st = $this->query($sql, [
            ':max'             => (int) $data['max_participantes'],
            ':duracion'        => (int) $data['duracion_max_minutos'],
            ':umbral'          => (int) $data['umbral_proveedor_externo'],
            ':stun'            => $data['stun_urls'],
            ':turn_urls'       => $data['turn_urls'],
            ':turn_usuario'    => $data['turn_usuario'],
            ':turn_credencial' => $data['turn_credencial'],
            ':turn_key_id'     => $data['turn_key_id'],
            ':turn_api_token'  => $data['turn_api_token'],
            ':usr'             => $idUsuario,
            ':emp'             => $idEmpresa,
        ]);

        return $st->rowCount() > 0;
    }

    /** Borra un secreto guardado (el usuario quiere quitar la credencial, no cambiarla). */
    public function limpiarSecretoConfig(int $idEmpresa, string $campo, int $idUsuario): void
    {
        $permitidos = ['turn_credencial', 'turn_api_token'];
        if (!in_array($campo, $permitidos, true)) {
            return;
        }
        $sql = "UPDATE videollamadas_config SET {$campo} = NULL, updated_by = :usr, updated_at = CURRENT_TIMESTAMP
                WHERE id_empresa = :emp AND eliminado = FALSE";
        $this->query($sql, [':usr' => $idUsuario, ':emp' => $idEmpresa]);
    }
}

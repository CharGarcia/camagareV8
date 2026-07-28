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
     * Lo mínimo que necesita el poll de señalización: estado, anfitrión y si la
     * sala exige admisión.
     *
     * Va con caché de 2 segundos en APCu porque lo consulta CADA participante
     * CADA segundo: sin esto, una reunión de seis personas dispararía seis
     * consultas por segundo contra la base gestionada, que es justo el recurso
     * más escaso del sistema. La caché la comparten todos los participantes de
     * la misma sala, así que quedan ~0,5 consultas por segundo y sala.
     *
     * @return array{estado:string, id_anfitrion:int, sala_espera:bool}
     */
    public function getDatosPoll(int $idSala, int $idEmpresa): array
    {
        $clave = 'vc:poll:' . $idEmpresa . ':' . $idSala;

        $cacheado = \App\Helpers\Cache::get($clave);
        if (is_array($cacheado)) {
            return $cacheado;
        }

        $sql = "SELECT estado, id_anfitrion, sala_espera FROM videollamadas_salas
                WHERE id = :id AND id_empresa = :emp AND eliminado = FALSE";
        $row = $this->query($sql, [':id' => $idSala, ':emp' => $idEmpresa])->fetch(PDO::FETCH_ASSOC);

        $datos = $row === false
            ? ['estado' => 'finalizada', 'id_anfitrion' => 0, 'sala_espera' => false]
            : [
                'estado'       => (string) $row['estado'],
                'id_anfitrion' => (int) $row['id_anfitrion'],
                'sala_espera'  => filter_var($row['sala_espera'], FILTER_VALIDATE_BOOLEAN),
            ];

        \App\Helpers\Cache::set($clave, $datos, 2);
        return $datos;
    }

    /**
     * Invalida la caché del poll.
     * Se llama al cambiar el estado de la sala para que quienes están dentro se
     * enteren en el acto de que la reunión terminó, sin esperar al TTL.
     */
    public function invalidarCachePoll(int $idSala, int $idEmpresa): void
    {
        \App\Helpers\Cache::delete('vc:poll:' . $idEmpresa . ':' . $idSala);
    }

    public function existeCodigo(string $codigo): bool
    {
        $sql = "SELECT COUNT(*) FROM videollamadas_salas WHERE codigo = :codigo AND eliminado = FALSE";
        return (int) $this->query($sql, [':codigo' => $codigo])->fetchColumn() > 0;
    }

    /**
     * Reuniones programadas que empiezan dentro de la ventana indicada y a las
     * que todavía no se les mandó el recordatorio.
     *
     * "Todavía no" se comprueba contra la bitácora de eventos, que es la fuente
     * de verdad: así el cron puede correr cada minuto sin reenviar nada.
     * No filtra por empresa porque lo ejecuta el cron para todo el sistema.
     */
    public function getSalasPorRecordar(int $minutosAntes): array
    {
        $sql = "SELECT s.id, s.id_empresa, s.titulo, s.fecha_inicio
                FROM videollamadas_salas s
                WHERE s.eliminado = FALSE
                  AND s.estado = 'programada'
                  AND s.tipo = 'programada'
                  AND s.fecha_inicio IS NOT NULL
                  AND s.fecha_inicio BETWEEN CURRENT_TIMESTAMP
                                         AND CURRENT_TIMESTAMP + (:minutos * INTERVAL '1 minute')
                  AND NOT EXISTS (
                        SELECT 1 FROM videollamadas_eventos e
                        WHERE e.id_sala = s.id AND e.tipo = 'recordatorio_enviado'
                      )
                ORDER BY s.fecha_inicio";
        return $this->query($sql, [':minutos' => $minutosAntes])->fetchAll(PDO::FETCH_ASSOC);
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

        // Que los participantes vean el cambio en su próximo poll, no dentro de 2s.
        $this->invalidarCachePoll($id, $idEmpresa);

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

    /**
     * Busca al invitado externo por el token de su enlace personal.
     *
     * No filtra por empresa a propósito: el token ES la credencial y viene de
     * fuera, sin sesión. Devuelve la sala junto al participante para que quien
     * llame pueda validar estado y empresa de una sola vez.
     */
    public function getParticipantePorToken(string $token): ?array
    {
        $sql = "SELECT p.id, p.id_empresa, p.id_sala, p.id_usuario, p.nombre_invitado, p.email, p.rol,
                       s.codigo, s.titulo, s.estado, s.sala_espera, s.max_participantes,
                       s.permite_invitados, s.id_anfitrion
                FROM videollamadas_participantes p
                INNER JOIN videollamadas_salas s ON s.id = p.id_sala AND s.eliminado = FALSE
                WHERE p.token_acceso = :token AND p.eliminado = FALSE";
        $row = $this->query($sql, [':token' => $token])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Marca conexión/desconexión de un invitado, que se identifica por su id de fila. */
    public function marcarConexionParticipante(int $idParticipante, bool $conectado): void
    {
        if ($conectado) {
            $sql = "UPDATE videollamadas_participantes
                    SET estado = 'conectado',
                        primera_conexion = COALESCE(primera_conexion, CURRENT_TIMESTAMP),
                        ultima_conexion = CURRENT_TIMESTAMP,
                        ip = :ip, user_agent = :ua, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND eliminado = FALSE";
            $this->query($sql, [
                ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                ':ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ':id' => $idParticipante,
            ]);
            return;
        }

        $sql = "UPDATE videollamadas_participantes
                SET estado = 'desconectado',
                    segundos_conectado = segundos_conectado + GREATEST(0,
                        EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - COALESCE(ultima_conexion, CURRENT_TIMESTAMP)))::int),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND estado = 'conectado' AND eliminado = FALSE";
        $this->query($sql, [':id' => $idParticipante]);
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

    /** $idUsuario es null cuando el evento lo genera un invitado externo, que no tiene cuenta. */
    public function registrarEvento(int $idEmpresa, int $idSala, ?int $idParticipante, string $tipo, ?array $payload, ?int $idUsuario): void
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

    // ────────────────────────────────────────────────────────────────────
    //  Configuración GLOBAL (una sola fila para todo el sistema, sin id_empresa)
    // ────────────────────────────────────────────────────────────────────

    public function getConfigGlobal(): ?array
    {
        $sql = "SELECT * FROM videollamadas_config_global WHERE eliminado = FALSE LIMIT 1";
        $row = $this->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Crea la fila global si aún no existe (primera vez que se usa el módulo). */
    public function crearConfigGlobalPorDefecto(int $idUsuario): void
    {
        $sql = "INSERT INTO videollamadas_config_global (stun_urls, created_by, updated_by)
                SELECT 'stun:stun.l.google.com:19302', :usr, :usr
                WHERE NOT EXISTS (SELECT 1 FROM videollamadas_config_global WHERE eliminado = FALSE)";
        $this->query($sql, [':usr' => $idUsuario]);
    }

    /** Mismo criterio que la de empresa: un secreto en null significa "no lo cambies". */
    public function guardarConfigGlobal(array $data, int $idUsuario): bool
    {
        $sql = "UPDATE videollamadas_config_global SET
                    stun_urls                 = :stun,
                    turn_urls                 = :turn_urls,
                    turn_usuario              = :turn_usuario,
                    turn_credencial           = COALESCE(:turn_credencial, turn_credencial),
                    turn_key_id               = :turn_key_id,
                    turn_api_token            = COALESCE(:turn_api_token, turn_api_token),
                    max_participantes_defecto = :max_def,
                    duracion_max_defecto      = :dur_def,
                    permite_override_empresa  = :override,
                    updated_by                = :usr,
                    updated_at                = CURRENT_TIMESTAMP
                WHERE eliminado = FALSE";

        $st = $this->query($sql, [
            ':stun'            => $data['stun_urls'],
            ':turn_urls'       => $data['turn_urls'],
            ':turn_usuario'    => $data['turn_usuario'],
            ':turn_credencial' => $data['turn_credencial'],
            ':turn_key_id'     => $data['turn_key_id'],
            ':turn_api_token'  => $data['turn_api_token'],
            ':max_def'         => (int) $data['max_participantes_defecto'],
            ':dur_def'         => (int) $data['duracion_max_defecto'],
            ':override'        => !empty($data['permite_override_empresa']),
            ':usr'             => $idUsuario,
        ]);

        return $st->rowCount() > 0;
    }

    public function limpiarSecretoGlobal(string $campo, int $idUsuario): void
    {
        $permitidos = ['turn_credencial', 'turn_api_token'];
        if (!in_array($campo, $permitidos, true)) {
            return;
        }
        $sql = "UPDATE videollamadas_config_global SET {$campo} = NULL, updated_by = :usr,
                    updated_at = CURRENT_TIMESTAMP
                WHERE eliminado = FALSE";
        $this->query($sql, [':usr' => $idUsuario]);
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

<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\VideollamadaRepository;
use App\Rules\modulos\VideollamadaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\videollamadas\ProveedorInterno;
use App\Services\modulos\videollamadas\ProveedorVideollamada;

/**
 * Lógica de negocio del módulo Videollamadas.
 *
 * Orquesta salas, participantes, configuración por empresa y auditoría. El
 * motor de video se resuelve por composición (ver ProveedorVideollamada): el
 * service nunca sabe si detrás hay WebRTC propio, Jitsi o un SaaS.
 */
class VideollamadaService
{
    /** Alfabeto del código de sala: sin vocales ni caracteres que se confunden al dictarlo. */
    private const ALFABETO_CODIGO = 'bcdfghjkmnpqrstvwxyz';

    private VideollamadaRepository $repository;
    private VideollamadaRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        VideollamadaRepository $repository,
        VideollamadaRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Lectura
    // ────────────────────────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_inicio',
        string $ordenDir = 'DESC',
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Sala completa: cabecera + participantes. */
    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sala = $this->repository->getPorId($id, $idEmpresa);
        if ($sala === null) {
            return null;
        }
        $sala['participantes'] = $this->repository->getParticipantes($id, $idEmpresa);
        return $sala;
    }

    public function getEventos(int $idSala, int $idEmpresa): array
    {
        return $this->repository->getEventos($idSala, $idEmpresa);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Escritura
    // ────────────────────────────────────────────────────────────────────

    /**
     * Crea una sala con sus participantes.
     *
     * @param array $data Debe traer id_empresa, usuario_id, titulo y, opcionalmente,
     *                    'participantes' como array de filas.
     */
    public function crear(array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['usuario_id'];

        $config = $this->getConfig($idEmpresa, $idUsuario);

        // El anfitrión por defecto es quien crea la sala.
        $data['id_anfitrion'] = !empty($data['id_anfitrion']) ? (int) $data['id_anfitrion'] : $idUsuario;
        $data['proveedor']    = $data['proveedor'] ?? ($config['proveedor_defecto'] ?? 'interno');
        $data['estado']       = ($data['tipo'] ?? 'instantanea') === 'programada' ? 'programada' : 'en_curso';

        $participantes = $this->normalizarParticipantes($data['participantes'] ?? [], (int) $data['id_anfitrion']);

        $this->rules->validar($data);
        $this->rules->validarParticipantes(
            $participantes,
            (int) ($data['max_participantes'] ?? 6),
            !empty($data['permite_invitados'])
        );

        $data['codigo'] = $this->generarCodigoUnico();

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) {
            $db->beginTransaction();
        }

        try {
            $idSala = $this->repository->insertSala($data);

            foreach ($participantes as $p) {
                $this->guardarParticipante($idSala, $idEmpresa, $idUsuario, $p);
            }

            $this->repository->registrarEvento(
                $idEmpresa,
                $idSala,
                null,
                'sala_creada',
                ['titulo' => $data['titulo'], 'tipo' => $data['tipo'] ?? 'instantanea'],
                $idUsuario
            );

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                'videollamadas_salas',
                $idSala,
                null,
                [
                    'codigo'            => $data['codigo'],
                    'titulo'            => $data['titulo'],
                    'tipo'              => $data['tipo'] ?? 'instantanea',
                    'proveedor'         => $data['proveedor'],
                    'max_participantes' => $data['max_participantes'] ?? 6,
                    'participantes'     => count($participantes),
                ]
            );

            if (!$inTrans) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $idSala;
    }

    /** Actualiza la sala y reemplaza por completo su lista de participantes. */
    public function actualizar(int $id, array $data): bool
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['usuario_id'];

        $antes = $this->repository->getPorId($id, $idEmpresa);
        if ($antes === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        $this->rules->validarEditable($antes);

        $data['id_anfitrion'] = !empty($data['id_anfitrion']) ? (int) $data['id_anfitrion'] : (int) $antes['id_anfitrion'];
        $data['proveedor']    = $antes['proveedor'];

        $participantes = $this->normalizarParticipantes($data['participantes'] ?? [], (int) $data['id_anfitrion']);

        $this->rules->validar($data);
        $this->rules->validarParticipantes(
            $participantes,
            (int) ($data['max_participantes'] ?? 6),
            !empty($data['permite_invitados'])
        );

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) {
            $db->beginTransaction();
        }

        try {
            $ok = $this->repository->updateSala($id, $idEmpresa, $data);

            // Se reemplaza la lista completa: es más simple y deja rastro en la
            // bitácora de quién quedó invitado en cada versión de la sala.
            $this->repository->softDeleteParticipantes($id, $idEmpresa, $idUsuario);
            foreach ($participantes as $p) {
                $this->guardarParticipante($id, $idEmpresa, $idUsuario, $p);
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR',
                'videollamadas_salas',
                $id,
                [
                    'titulo'            => $antes['titulo'],
                    'tipo'              => $antes['tipo'],
                    'fecha_inicio'      => $antes['fecha_inicio'],
                    'max_participantes' => $antes['max_participantes'],
                ],
                [
                    'titulo'            => $data['titulo'],
                    'tipo'              => $data['tipo'] ?? $antes['tipo'],
                    'fecha_inicio'      => $data['fecha_inicio'] ?? null,
                    'max_participantes' => $data['max_participantes'] ?? 6,
                ]
            );

            if (!$inTrans) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $ok;
    }

    /** Eliminación lógica de la sala y de sus participantes. */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $antes = $this->repository->getPorId($id, $idEmpresa);
        if ($antes === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        if (($antes['estado'] ?? '') === 'en_curso') {
            throw new \Exception('No se puede eliminar una reunión en curso. Finalícela primero.');
        }

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) {
            $db->beginTransaction();
        }

        try {
            $this->repository->softDeleteParticipantes($id, $idEmpresa, $idUsuario);
            $ok = $this->repository->softDeleteSala($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'videollamadas_salas',
                $id,
                ['codigo' => $antes['codigo'], 'titulo' => $antes['titulo'], 'estado' => $antes['estado']],
                null
            );

            if (!$inTrans) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $ok;
    }

    /**
     * Marca la sala como en curso. La conexión WebRTC en sí llega en la Fase 2;
     * aquí queda registrado el inicio y el consentimiento de grabación si aplica.
     */
    public function iniciar(int $id, int $idEmpresa, int $idUsuario, bool $accesoTotal): array
    {
        $sala = $this->repository->getPorId($id, $idEmpresa);
        if ($sala === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        $esParticipante = $this->repository->getIdParticipante($id, $idEmpresa, $idUsuario) !== null;
        $this->rules->validarPuedeEntrar($sala, $idUsuario, $accesoTotal, $esParticipante);

        // Solo quien manda en la sala la pone en curso. Un participante que
        // entra a una reunión ya empezada no cambia su estado ni deja el evento
        // de "iniciada": únicamente se suma.
        $mandaSala = $accesoTotal || (int) $sala['id_anfitrion'] === $idUsuario;

        if ($mandaSala && $sala['estado'] !== 'en_curso') {
            $this->repository->cambiarEstado($id, $idEmpresa, 'en_curso', $idUsuario);
            $this->repository->registrarEvento($idEmpresa, $id, null, 'sala_iniciada', null, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'INICIAR',
                'videollamadas_salas',
                $id,
                ['estado' => $sala['estado']],
                ['estado' => 'en_curso']
            );

            $sala['estado'] = 'en_curso';
        }

        return $sala;
    }

    public function finalizar(int $id, int $idEmpresa, int $idUsuario, bool $accesoTotal): bool
    {
        $sala = $this->repository->getPorId($id, $idEmpresa);
        if ($sala === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        if (!$accesoTotal && (int) $sala['id_anfitrion'] !== $idUsuario) {
            throw new \Exception('Solo el anfitrión puede finalizar esta reunión.');
        }

        $ok = $this->repository->cambiarEstado($id, $idEmpresa, 'finalizada', $idUsuario);
        $this->repository->registrarEvento($idEmpresa, $id, null, 'sala_finalizada', null, $idUsuario);

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'FINALIZAR',
            'videollamadas_salas',
            $id,
            ['estado' => $sala['estado']],
            ['estado' => 'finalizada']
        );

        return $ok;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Invitaciones por correo
    // ────────────────────────────────────────────────────────────────────

    /**
     * Envía a cada participante con correo su invitación (o su recordatorio).
     *
     * Cada destinatario recibe SU enlace: los invitados externos el que lleva su
     * token personal, y los usuarios del sistema la dirección del módulo, porque
     * ellos entran autenticados.
     *
     * Un correo que falla no detiene a los demás ni tumba la operación: se
     * cuenta y se sigue. Devuelve el detalle para poder decírselo al usuario.
     *
     * @return array{enviados:int, fallidos:int, sin_correo:int}
     */
    public function enviarInvitaciones(int $idSala, int $idEmpresa, int $idUsuario, bool $esRecordatorio = false): array
    {
        $sala = $this->getPorId($idSala, $idEmpresa);
        if ($sala === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        require_once MVC_APP . '/helpers/mail.php';
        if (!function_exists('enviar_correo_invitacion_videollamada')) {
            throw new \Exception('El envío de correo no está disponible en este servidor.');
        }

        $nombreEmpresa = '';
        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = (string) ($empresa['nombre_comercial'] ?? $empresa['razon_social'] ?? '');
        } catch (\Throwable $e) {
            // El nombre de la empresa es decorativo: si falla, el correo sale igual.
        }

        $resultado = ['enviados' => 0, 'fallidos' => 0, 'sin_correo' => 0];

        foreach ($sala['participantes'] as $p) {
            $correo = trim((string) ($p['email'] ?? $p['usuario_email'] ?? ''));
            if ($correo === '') {
                $resultado['sin_correo']++;
                continue;
            }

            $esInvitado = empty($p['id_usuario']);
            // El invitado externo entra con su token; el usuario del ERP, con el
            // enlace de la sala, que lleva el código para identificarla.
            //
            // url_absoluta() es imprescindible aquí: BASE_URL es relativo (en
            // producción es cadena vacía) y en un correo una ruta relativa no
            // lleva a ninguna parte. Además esto corre desde el cron, sin
            // contexto de petición.
            $enlace = $esInvitado && !empty($p['token_acceso'])
                ? url_absoluta('videollamada-invitado?t=' . rawurlencode((string) $p['token_acceso']))
                : url_absoluta('modulos/videollamadas/sala/' . rawurlencode((string) $sala['codigo']));

            $ok = enviar_correo_invitacion_videollamada($correo, [
                'titulo'              => $sala['titulo'],
                'descripcion'         => $sala['descripcion'] ?? '',
                'fecha_texto'         => $this->fechaLegible($sala),
                'anfitrion'           => $sala['anfitrion_nombre'] ?? '',
                'codigo'              => $sala['codigo'],
                'enlace'              => $enlace,
                'nombre_destinatario' => $p['usuario_nombre'] ?? $p['nombre_invitado'] ?? '',
                'empresa'             => $nombreEmpresa,
                'es_invitado'         => $esInvitado,
            ], $esRecordatorio);

            $ok ? $resultado['enviados']++ : $resultado['fallidos']++;
        }

        $this->repository->registrarEvento(
            $idEmpresa,
            $idSala,
            null,
            $esRecordatorio ? 'recordatorio_enviado' : 'invitaciones_enviadas',
            $resultado,
            $idUsuario ?: null
        );

        return $resultado;
    }

    /**
     * Avisa por correo de las reuniones que están por empezar. Lo llama el cron.
     *
     * No lleva marca de "ya corrí hoy" como otras tareas del cron: la condición
     * de no repetir es por reunión, no por día, y se comprueba en la consulta
     * contra la bitácora de eventos. Así el cron puede correr cada minuto y cada
     * reunión recibe exactamente un recordatorio.
     *
     * @return array{salas:int, correos:int}
     */
    public function enviarRecordatoriosPendientes(int $minutosAntes = 15): array
    {
        $salas = $this->repository->getSalasPorRecordar($minutosAntes);
        $totalCorreos = 0;

        foreach ($salas as $s) {
            try {
                $r = $this->enviarInvitaciones((int) $s['id'], (int) $s['id_empresa'], 0, true);
                $totalCorreos += $r['enviados'];
            } catch (\Throwable $e) {
                // Una reunión con problema no debe frenar el resto de la cola.
                error_log('Videollamadas: recordatorio de la sala ' . $s['id'] . ' falló: ' . $e->getMessage());
            }
        }

        return ['salas' => count($salas), 'correos' => $totalCorreos];
    }

    /** Fecha de la reunión en el formato del sistema, o vacío si es instantánea. */
    private function fechaLegible(array $sala): string
    {
        if (empty($sala['fecha_inicio'])) {
            return '';
        }
        $ts = strtotime((string) $sala['fecha_inicio']);
        return $ts !== false ? date('d-m-Y H:i:s', $ts) : '';
    }

    // ────────────────────────────────────────────────────────────────────
    //  Presencia en la sala
    // ────────────────────────────────────────────────────────────────────

    /**
     * Deja constancia de que un usuario entró a la sala.
     * Alimenta el reporte de asistencia y el tiempo de permanencia.
     */
    public function registrarEntrada(int $idSala, int $idEmpresa, int $idUsuario): void
    {
        $this->repository->marcarConexion($idSala, $idEmpresa, $idUsuario);

        $idParticipante = $this->repository->getIdParticipante($idSala, $idEmpresa, $idUsuario);
        $this->repository->registrarEvento($idEmpresa, $idSala, $idParticipante, 'entro', null, $idUsuario);
    }

    /** Deja constancia de a quién se admitió o rechazó en la sala de espera. */
    public function registrarAdmision(int $idSala, int $idEmpresa, int $idUsuario, string $peerId, bool $admitido): void
    {
        $this->repository->registrarEvento(
            $idEmpresa,
            $idSala,
            null,
            $admitido ? 'admitido' : 'rechazado',
            ['peer_id' => $peerId],
            $idUsuario
        );
    }

    public function registrarSalida(int $idSala, int $idEmpresa, int $idUsuario): void
    {
        $idParticipante = $this->repository->getIdParticipante($idSala, $idEmpresa, $idUsuario);

        $this->repository->marcarDesconexion($idSala, $idEmpresa, $idUsuario);
        $this->repository->registrarEvento($idEmpresa, $idSala, $idParticipante, 'salio', null, $idUsuario);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Configuración y motor
    // ────────────────────────────────────────────────────────────────────

    /** Devuelve la configuración de la empresa, creándola con valores por defecto la primera vez. */
    public function getConfig(int $idEmpresa, int $idUsuario): array
    {
        $config = $this->repository->getConfig($idEmpresa);
        if ($config === null) {
            $this->repository->crearConfigPorDefecto($idEmpresa, $idUsuario);
            $config = $this->repository->getConfig($idEmpresa) ?? [];
        }
        return $config;
    }

    /** Configuración global del sistema, creándola la primera vez. */
    public function getConfigGlobal(int $idUsuario): array
    {
        $global = $this->repository->getConfigGlobal();
        if ($global === null) {
            $this->repository->crearConfigGlobalPorDefecto($idUsuario);
            $global = $this->repository->getConfigGlobal() ?? [];
        }
        return $global;
    }

    /**
     * Configuración que se usa realmente en una llamada: la global, con lo que
     * la empresa haya decidido sobrescribir.
     *
     * La herencia va POR BLOQUE, no campo por campo. Mezclar la dirección de un
     * servidor TURN con la credencial de otro daría una configuración que no
     * conecta con ninguno, y el fallo sería dificilísimo de diagnosticar. Los
     * bloques son tres:
     *   1. STUN (un solo campo).
     *   2. TURN estático: dirección + usuario + credencial.
     *   3. TURN por credenciales temporales: key id + token de API.
     *
     * Si la empresa define un bloque, ese bloque es suyo entero; si lo deja
     * vacío, hereda el global completo.
     */
    public function getConfigEfectiva(int $idEmpresa, int $idUsuario): array
    {
        $empresa = $this->getConfig($idEmpresa, $idUsuario);
        $global  = $this->getConfigGlobal($idUsuario);

        // El global manda: si desactiva la anulación, lo de la empresa se ignora.
        $puedeAnular = !isset($global['permite_override_empresa'])
            || filter_var($global['permite_override_empresa'], FILTER_VALIDATE_BOOLEAN);

        $stunEmpresa = trim((string) ($empresa['stun_urls'] ?? ''));
        $stun = ($puedeAnular && $stunEmpresa !== '')
            ? $stunEmpresa
            : trim((string) ($global['stun_urls'] ?? ''));

        $empresaDefineTurn  = $puedeAnular && trim((string) ($empresa['turn_urls'] ?? '')) !== '';
        $empresaDefineCloud = $puedeAnular && trim((string) ($empresa['turn_key_id'] ?? '')) !== '';

        $bloqueTurn  = $empresaDefineTurn ? $empresa : $global;
        $bloqueCloud = $empresaDefineCloud ? $empresa : $global;

        return [
            // Los límites NO se heredan: son siempre de la empresa.
            'max_participantes'        => (int) ($empresa['max_participantes'] ?? 6),
            'duracion_max_minutos'     => (int) ($empresa['duracion_max_minutos'] ?? 120),
            'umbral_proveedor_externo' => (int) ($empresa['umbral_proveedor_externo'] ?? 8),
            'proveedor_defecto'        => (string) ($empresa['proveedor_defecto'] ?? 'interno'),

            'stun_urls'       => $stun,
            'turn_urls'       => (string) ($bloqueTurn['turn_urls'] ?? ''),
            'turn_usuario'    => (string) ($bloqueTurn['turn_usuario'] ?? ''),
            'turn_credencial' => (string) ($bloqueTurn['turn_credencial'] ?? ''),
            'turn_key_id'     => (string) ($bloqueCloud['turn_key_id'] ?? ''),
            'turn_api_token'  => (string) ($bloqueCloud['turn_api_token'] ?? ''),

            // Para que la interfaz pueda decir de dónde salió cada cosa.
            'origen_turn'  => $empresaDefineTurn ? 'empresa' : 'global',
            'origen_cloud' => $empresaDefineCloud ? 'empresa' : 'global',
            'puede_anular' => $puedeAnular,
        ];
    }

    /** Guarda la configuración global. Solo la usa el superadministrador. */
    public function guardarConfigGlobal(int $idUsuario, array $data): void
    {
        $this->getConfigGlobal($idUsuario); // garantiza que la fila exista

        $credencial = trim((string) ($data['turn_credencial'] ?? ''));
        $apiToken   = trim((string) ($data['turn_api_token'] ?? ''));

        $this->repository->guardarConfigGlobal([
            'stun_urls'                 => trim((string) ($data['stun_urls'] ?? '')) ?: null,
            'turn_urls'                 => trim((string) ($data['turn_urls'] ?? '')) ?: null,
            'turn_usuario'              => trim((string) ($data['turn_usuario'] ?? '')) ?: null,
            'turn_key_id'               => trim((string) ($data['turn_key_id'] ?? '')) ?: null,
            'turn_credencial'           => $credencial !== '' ? CryptoHelper::encriptar($credencial) : null,
            'turn_api_token'            => $apiToken !== '' ? CryptoHelper::encriptar($apiToken) : null,
            'max_participantes_defecto' => (int) ($data['max_participantes_defecto'] ?? 6),
            'duracion_max_defecto'      => (int) ($data['duracion_max_defecto'] ?? 120),
            'permite_override_empresa'  => !empty($data['permite_override_empresa']),
        ], $idUsuario);

        foreach (['turn_credencial', 'turn_api_token'] as $campo) {
            if (!empty($data['borrar_' . $campo])) {
                $this->repository->limpiarSecretoGlobal($campo, $idUsuario);
            }
        }

        // Tabla global: la auditoría va con id_empresa nulo (§7 de CLAUDE.md).
        $this->logService->registrar(
            $idUsuario,
            null,
            'ACTUALIZAR',
            'videollamadas_config_global',
            null,
            null,
            [
                'turn_urls'                => $data['turn_urls'] ?? '',
                'permite_override_empresa' => !empty($data['permite_override_empresa']),
                // Nunca se registra el valor de un secreto en la auditoría.
                'turn_credencial'          => $credencial !== '' ? '(actualizada)' : '(sin cambio)',
                'turn_api_token'           => $apiToken !== '' ? '(actualizado)' : '(sin cambio)',
            ]
        );
    }

    /** Configuración global lista para el formulario, sin exponer secretos. */
    public function getConfigGlobalParaVista(int $idUsuario): array
    {
        $g = $this->getConfigGlobal($idUsuario);

        return [
            'stun_urls'                 => (string) ($g['stun_urls'] ?? ''),
            'turn_urls'                 => (string) ($g['turn_urls'] ?? ''),
            'turn_usuario'              => (string) ($g['turn_usuario'] ?? ''),
            'turn_key_id'               => (string) ($g['turn_key_id'] ?? ''),
            'turn_credencial_puesta'    => trim((string) ($g['turn_credencial'] ?? '')) !== '',
            'turn_api_token_puesto'     => trim((string) ($g['turn_api_token'] ?? '')) !== '',
            'max_participantes_defecto' => (int) ($g['max_participantes_defecto'] ?? 6),
            'duracion_max_defecto'      => (int) ($g['duracion_max_defecto'] ?? 120),
            'permite_override_empresa'  => !isset($g['permite_override_empresa'])
                || filter_var($g['permite_override_empresa'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Configuración lista para mostrar en el formulario.
     * Los secretos NO se devuelven: solo si están puestos o no.
     */
    public function getConfigParaVista(int $idEmpresa, int $idUsuario): array
    {
        $config = $this->getConfig($idEmpresa, $idUsuario);

        return [
            'max_participantes'        => (int) ($config['max_participantes'] ?? 6),
            'duracion_max_minutos'     => (int) ($config['duracion_max_minutos'] ?? 120),
            'umbral_proveedor_externo' => (int) ($config['umbral_proveedor_externo'] ?? 8),
            'stun_urls'                => (string) ($config['stun_urls'] ?? ''),
            'turn_urls'                => (string) ($config['turn_urls'] ?? ''),
            'turn_usuario'             => (string) ($config['turn_usuario'] ?? ''),
            'turn_key_id'              => (string) ($config['turn_key_id'] ?? ''),
            'turn_credencial_puesta'   => trim((string) ($config['turn_credencial'] ?? '')) !== '',
            'turn_api_token_puesto'    => trim((string) ($config['turn_api_token'] ?? '')) !== '',
        ];
    }

    /** Guarda la configuración de la empresa, cifrando los secretos. */
    public function guardarConfig(int $idEmpresa, int $idUsuario, array $data): void
    {
        $max = (int) ($data['max_participantes'] ?? 6);
        if ($max < 2 || $max > VideollamadaRules::MAX_PARTICIPANTES_MESH) {
            throw new \Exception('El máximo de participantes debe estar entre 2 y ' . VideollamadaRules::MAX_PARTICIPANTES_MESH . '.');
        }

        $this->getConfig($idEmpresa, $idUsuario); // garantiza que la fila exista

        // Un secreto en blanco significa "déjalo como está"; para borrarlo hay un
        // botón aparte. Así el formulario nunca necesita mostrar el valor real.
        $credencial = trim((string) ($data['turn_credencial'] ?? ''));
        $apiToken   = trim((string) ($data['turn_api_token'] ?? ''));

        $this->repository->guardarConfig($idEmpresa, [
            'max_participantes'        => $max,
            'duracion_max_minutos'     => (int) ($data['duracion_max_minutos'] ?? 120),
            'umbral_proveedor_externo' => (int) ($data['umbral_proveedor_externo'] ?? 8),
            'stun_urls'                => trim((string) ($data['stun_urls'] ?? '')) ?: null,
            'turn_urls'                => trim((string) ($data['turn_urls'] ?? '')) ?: null,
            'turn_usuario'             => trim((string) ($data['turn_usuario'] ?? '')) ?: null,
            'turn_key_id'              => trim((string) ($data['turn_key_id'] ?? '')) ?: null,
            'turn_credencial'          => $credencial !== '' ? CryptoHelper::encriptar($credencial) : null,
            'turn_api_token'           => $apiToken !== '' ? CryptoHelper::encriptar($apiToken) : null,
        ], $idUsuario);

        foreach (['turn_credencial', 'turn_api_token'] as $campo) {
            if (!empty($data['borrar_' . $campo])) {
                $this->repository->limpiarSecretoConfig($idEmpresa, $campo, $idUsuario);
            }
        }

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'ACTUALIZAR',
            'videollamadas_config',
            null,
            null,
            [
                'max_participantes' => $max,
                'turn_urls'         => $data['turn_urls'] ?? '',
                // Nunca se registra el valor de un secreto en la auditoría.
                'turn_credencial'   => $credencial !== '' ? '(actualizada)' : '(sin cambio)',
                'turn_api_token'    => $apiToken !== '' ? '(actualizado)' : '(sin cambio)',
            ]
        );
    }

    /**
     * Elige el motor según el cupo de la sala y la configuración de la empresa.
     *
     * Por debajo del umbral se usa el motor interno (gratis, P2P); por encima
     * habría que usar un proveedor externo. Mientras esos drivers no existan
     * (Fase 6), se avisa con una excepción clara en lugar de fallar en silencio.
     */
    public function resolverProveedor(array $sala, array $config): ProveedorVideollamada
    {
        $proveedor = $sala['proveedor'] ?? ($config['proveedor_defecto'] ?? 'interno');

        return match ($proveedor) {
            'interno' => new ProveedorInterno(),
            default   => throw new \Exception(
                'El proveedor "' . $proveedor . '" todavía no está implementado. '
                . 'Por ahora el módulo funciona con el motor interno.'
            ),
        };
    }

    /** Credenciales que el navegador necesita para entrar a la sala. */
    public function getCredenciales(int $idSala, int $idEmpresa, int $idUsuario): array
    {
        $sala = $this->repository->getPorId($idSala, $idEmpresa);
        if ($sala === null) {
            throw new \Exception('La reunión no existe o no pertenece a esta empresa.');
        }

        // Efectiva, no la cruda: aquí es donde se aplica la herencia de los
        // servidores globales.
        $config    = $this->getConfigEfectiva($idEmpresa, $idUsuario);
        $proveedor = $this->resolverProveedor($sala, $config);

        $participantes = $this->repository->getParticipantes($idSala, $idEmpresa);
        $participante  = ['rol' => 'participante'];
        foreach ($participantes as $p) {
            if ((int) ($p['id_usuario'] ?? 0) === $idUsuario) {
                $participante = $p;
                break;
            }
        }

        return $proveedor->obtenerCredenciales($sala, $config, $participante);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Apoyo
    // ────────────────────────────────────────────────────────────────────

    /**
     * Normaliza la lista de participantes y garantiza que el anfitrión esté
     * siempre presente con su rol, aunque el formulario no lo haya enviado.
     */
    private function normalizarParticipantes(array $participantes, int $idAnfitrion): array
    {
        $normalizados   = [];
        $anfitrionPuesto = false;

        foreach ($participantes as $p) {
            $idUsuario = (int) ($p['id_usuario'] ?? 0);
            $nombre    = trim((string) ($p['nombre_invitado'] ?? ''));

            if ($idUsuario <= 0 && $nombre === '') {
                continue;
            }

            $esAnfitrion = $idUsuario > 0 && $idUsuario === $idAnfitrion;
            if ($esAnfitrion) {
                $anfitrionPuesto = true;
            }

            $normalizados[] = [
                'id_usuario'      => $idUsuario > 0 ? $idUsuario : null,
                'nombre_invitado' => $idUsuario > 0 ? null : $nombre,
                'email'           => trim((string) ($p['email'] ?? '')) ?: null,
                'rol'             => $esAnfitrion ? 'anfitrion' : ($p['rol'] ?? 'participante'),
            ];
        }

        if (!$anfitrionPuesto) {
            array_unshift($normalizados, [
                'id_usuario'      => $idAnfitrion,
                'nombre_invitado' => null,
                'email'           => null,
                'rol'             => 'anfitrion',
            ]);
        }

        return $normalizados;
    }

    private function guardarParticipante(int $idSala, int $idEmpresa, int $idUsuario, array $p): void
    {
        // Los invitados externos entran por enlace, así que necesitan un token
        // propio. Los usuarios del ERP entran autenticados y no llevan token.
        $token = empty($p['id_usuario']) ? bin2hex(random_bytes(24)) : null;

        $this->repository->insertParticipante([
            'id_empresa'      => $idEmpresa,
            'id_sala'         => $idSala,
            'id_usuario'      => $p['id_usuario'],
            'nombre_invitado' => $p['nombre_invitado'],
            'email'           => $p['email'],
            'token_acceso'    => $token,
            'rol'             => $p['rol'],
            'estado'          => 'invitado',
            'usuario_id'      => $idUsuario,
        ]);
    }

    /**
     * Genera un código público estilo "bcd-fghj-kmn".
     * Reintenta ante colisión; el índice único de la tabla es la garantía final.
     */
    private function generarCodigoUnico(): string
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = $this->generarCodigo();
            if (!$this->repository->existeCodigo($codigo)) {
                return $codigo;
            }
        }
        throw new \Exception('No se pudo generar un código de sala único. Intente nuevamente.');
    }

    private function generarCodigo(): string
    {
        $bloque = function (int $largo): string {
            $out = '';
            $max = strlen(self::ALFABETO_CODIGO) - 1;
            for ($i = 0; $i < $largo; $i++) {
                $out .= self::ALFABETO_CODIGO[random_int(0, $max)];
            }
            return $out;
        };

        return $bloque(3) . '-' . $bloque(4) . '-' . $bloque(3);
    }
}

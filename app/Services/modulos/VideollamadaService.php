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

        $this->rules->validarPuedeIniciar($sala, $idUsuario, $accesoTotal);

        if ($sala['estado'] !== 'en_curso') {
            $this->repository->cambiarEstado($id, $idEmpresa, 'en_curso', $idUsuario);
        }

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

        $config    = $this->getConfig($idEmpresa, $idUsuario);
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

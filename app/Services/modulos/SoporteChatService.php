<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Cache;
use App\repositories\modulos\SoporteChatRepository;
use App\Rules\modulos\SoporteChatRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Chat de soporte del ERP: el usuario consulta desde la burbuja y el equipo
 * responde desde la bandeja.
 *
 * Notas de diseño:
 *
 *  - CONTROL DE ACCESO. Hay dos lados y el Service es quien los separa:
 *    el usuario solo alcanza SUS conversaciones; el agente alcanza todas
 *    (excepción documentada a §4). Ningún endpoint recibe "soy agente" desde
 *    el cliente: se resuelve aquí con la sesión.
 *
 *  - PULSO. El polling no consulta la base: pregunta por un número de versión
 *    (el id del último mensaje) que vive en APCu y se actualiza al escribir.
 *    Solo cuando ese número cambia el front pide los mensajes nuevos. Sin APCu
 *    todo sigue funcionando, simplemente consultando (ver App\Helpers\Cache).
 *
 *  - AUDITORÍA. Se registran los hechos del ciclo de vida (abrir, cambiar
 *    estado, calificar, eliminar), NO cada mensaje: log_sistema crecería sin
 *    aportar nada que el propio hilo no cuente mejor.
 */
class SoporteChatService
{
    /** TTL de los números de versión del pulso (segundos). */
    private const TTL_VERSION = 300;
    /** TTL de los contadores del navbar; igual criterio que ContadoresNavbarService. */
    private const TTL_CONTADORES = 30;
    /** TTL de la configuración global (la lee el layout en cada página). */
    private const TTL_CONFIG = 120;
    /** TTL del flag "atiende soporte" por usuario (se invalida al cambiar permisos). */
    private const TTL_AGENTE = 60;

    /** Ruta MVC del módulo: define quién atiende (quien la tenga asignada). */
    private const RUTA_MODULO = 'modulos/soporte-chat';

    public function __construct(
        private SoporteChatRepository $repo,
        private SoporteChatRules $rules,
        private LogSistemaService $logService,
    ) {
    }

    // ── Quién es agente de soporte ───────────────────────────────────────────

    /**
     * Agente = quien tiene asignado el submódulo del chat en CUALQUIERA de sus
     * empresas, no solo en la activa.
     *
     * Es una excepción consciente a §4 (permisos por empresa) y va con el alcance
     * del módulo: la bandeja NO es por empresa —recibe las consultas de todas—, así
     * que a quien atiende soporte no puede dejar de atenderle porque en ese momento
     * esté trabajando en otra de sus empresas. El permiso sigue siendo el del
     * sistema: si nadie le asignó "Chat de Soporte" en ninguna empresa, no es
     * agente. El nivel 3 entra siempre, como en cualquier módulo.
     *
     * Se resuelve con el helper de permisos —la misma fuente que usa el resto del
     * sistema— y nunca con un dato que mande el cliente.
     */
    public function esAgente(int $idUsuario, int $idEmpresa, int $nivel): bool
    {
        if ($nivel >= 3) {
            return true;
        }
        if ($idUsuario <= 0) {
            return false;
        }

        // Cacheado por usuario (no por empresa: la respuesta ya no depende de la
        // activa). Lo pregunta cada polling de la burbuja y de la bandeja, y para
        // quien NO es agente en la empresa activa implica mirar modulos_asignados
        // en todas sus empresas: sin caché sería una consulta por petición.
        $clave = self::claveAgente($idUsuario);
        $cache = Cache::get($clave);
        if (is_bool($cache)) {
            return $cache;
        }

        try {
            // La empresa activa primero: es la respuesta en el caso normal y sale de
            // la caché por request del helper, sin tocar la base.
            $es = ($idEmpresa > 0 && \App\Helpers\Permisos::puedeVer(self::RUTA_MODULO))
                || \App\Helpers\Permisos::puedeVerEnAlgunaEmpresa(self::RUTA_MODULO);
        } catch (\Throwable $e) {
            return false;   // sin cachear: el fallo puede ser transitorio
        }

        Cache::set($clave, $es, self::TTL_AGENTE);
        return $es;
    }

    private static function claveAgente(int $idUsuario): string
    {
        return 'cmg_soporte_agente_' . $idUsuario;
    }

    /**
     * Invalida el flag "atiende soporte" de un usuario. La llama el modelo de
     * permisos tras escribir en modulos_asignados: dar o quitar el Chat de Soporte
     * debe verse en el navbar sin esperar a que expire el TTL.
     */
    public static function invalidarAgente(int $idUsuario): void
    {
        if ($idUsuario > 0) {
            Cache::delete(self::claveAgente($idUsuario));
        }
    }

    /** @throws Exception si el usuario no es agente. */
    private function exigirAgente(int $idUsuario, int $idEmpresa, int $nivel): void
    {
        if (!$this->esAgente($idUsuario, $idEmpresa, $nivel)) {
            throw new Exception('No tiene acceso a la bandeja de soporte.');
        }
    }

    // ── Configuración ────────────────────────────────────────────────────────

    /**
     * Configuración global del chat. La consume el layout en CADA página, así
     * que va cacheada: sin caché serían tantas consultas como cargas de página.
     * El horario se recalcula siempre (depende de la hora, no de la fila).
     */
    public function getConfig(): array
    {
        $clave = 'cmg_soporte_config';
        $config = Cache::get($clave);
        if (!is_array($config)) {
            $config = $this->repo->getConfig();
            Cache::set($clave, $config, self::TTL_CONFIG);
        }

        $config['en_horario'] = $this->rules->esHorarioAtencion($config);
        return $config;
    }

    /** Invalida la configuración cacheada (usar al guardarla desde la UI). */
    public function invalidarConfig(): void
    {
        Cache::delete('cmg_soporte_config');
    }

    // ── Lado usuario ─────────────────────────────────────────────────────────

    /** Conversaciones propias del usuario, para la burbuja. */
    public function listarMias(int $idUsuario, int $idEmpresa): array
    {
        return $this->repo->getListadoUsuario($idUsuario, $idEmpresa);
    }

    /**
     * Abre una conversación con su primer mensaje. Todo o nada (§8).
     *
     * @param array{asunto?:string,mensaje:string,origen_url?:string,origen_modulo?:string} $data
     */
    public function abrirConversacion(int $idEmpresa, int $idUsuario, array $data): int
    {
        $mensaje = trim((string) ($data['mensaje'] ?? ''));
        $asunto  = trim((string) ($data['asunto'] ?? ''));

        $this->rules->validarMensaje($mensaje);
        $this->rules->validarAsunto($asunto);

        if ($asunto === '') {
            $asunto = mb_substr($mensaje, 0, 60) . (mb_strlen($mensaje) > 60 ? '…' : '');
        }

        $this->repo->beginTransaction();
        try {
            $idConversacion = $this->repo->crearConversacion([
                'id_empresa' => $idEmpresa,
                // Queda en NULL: hoy atienden TODAS las empresas que tengan el
                // submódulo asignado, así que no hay un destino único que grabar.
                // La columna espera al día que el reparto sea por empresa.
                'id_empresa_destino' => null,
                'canal'         => 'interno',
                'asunto'        => $asunto,
                'origen_url'    => mb_substr((string) ($data['origen_url'] ?? ''), 0, 500) ?: null,
                'origen_modulo' => mb_substr((string) ($data['origen_modulo'] ?? ''), 0, 100) ?: null,
                'id_usuario'    => $idUsuario,
            ]);

            $idMensaje = $this->repo->crearMensaje([
                'id_empresa'      => $idEmpresa,
                'id_conversacion' => $idConversacion,
                'rol'             => 'usuario',
                'contenido'       => $mensaje,
                'id_usuario'      => $idUsuario,
            ]);

            $this->repo->actualizarResumen($idConversacion, $mensaje, 'usuario', $idUsuario);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw new Exception('No se pudo abrir la conversación: ' . $e->getMessage(), 0, $e);
        }

        $this->refrescarVersion($idConversacion, $idMensaje);
        $this->invalidarContadores($idUsuario);

        $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'soporte_conversaciones', $idConversacion, null, [
            'asunto'        => $asunto,
            'origen_modulo' => $data['origen_modulo'] ?? null,
        ]);

        return $idConversacion;
    }

    // ── Mensajería (ambos lados) ─────────────────────────────────────────────

    /**
     * Envía un mensaje. El rol se deduce del acceso, no de lo que mande el
     * cliente: si el remitente es el dueño del hilo escribe como 'usuario';
     * si es del equipo, como 'agente'.
     *
     * @return array{id:int,rol:string}
     */
    public function enviarMensaje(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel, string $contenido, array $extra = []): array
    {
        $this->rules->validarMensaje($contenido);
        $contenido = trim($contenido);

        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);
        $this->rules->validarConversacionAbierta($conversacion);
        $this->rules->validarRateLimit($this->repo->contarMensajesUltimoMinuto($idConversacion, $idUsuario));

        $rol = $this->resolverRol($conversacion, $idUsuario, $idEmpresa, $nivel, (string) ($extra['origen'] ?? ''));

        $this->repo->beginTransaction();
        try {
            $idMensaje = $this->repo->crearMensaje([
                // La conversación manda: el mensaje pertenece a la empresa del
                // hilo, no a la empresa activa de quien escribe (un agente puede
                // estar en otra empresa distinta a la del usuario que consulta).
                'id_empresa'      => (int) $conversacion['id_empresa'],
                'id_conversacion' => $idConversacion,
                'rol'             => $rol,
                'contenido'       => $contenido,
                'sugerida_por_ia' => !empty($extra['sugerida_por_ia']),
                'id_usuario'      => $idUsuario,
            ]);

            $this->repo->actualizarResumen($idConversacion, $contenido, $rol, $idUsuario);

            // El agente que responde toma la conversación si estaba libre.
            if ($rol === 'agente' && empty($conversacion['id_agente_asignado'])) {
                $this->repo->asignarAgente($idConversacion, $idUsuario);
            }

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw new Exception('No se pudo enviar el mensaje: ' . $e->getMessage(), 0, $e);
        }

        $this->refrescarVersion($idConversacion, $idMensaje);
        $this->invalidarContadores((int) $conversacion['created_by']);

        return ['id' => $idMensaje, 'rol' => $rol];
    }

    /**
     * Mensajes de una conversación. Marca como leído lo de la contraparte solo
     * cuando se pide el hilo completo (desdeId = 0), que es cuando el usuario
     * realmente lo está mirando; el polling incremental no marca nada.
     */
    public function listarMensajes(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel, int $desdeId = 0): array
    {
        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);

        $mensajes = $this->repo->getMensajes($idConversacion, $desdeId);
        foreach ($mensajes as &$m) {
            $m['fuentes'] = !empty($m['fuentes']) ? json_decode((string) $m['fuentes'], true) : [];
        }
        unset($m);

        if ($desdeId === 0) {
            $lado = (int) $conversacion['created_by'] === $idUsuario ? 'usuario' : 'agente';
            $this->repo->marcarLeidos($idConversacion, $lado);
            $this->invalidarContadores((int) $conversacion['created_by']);
        }

        return $mensajes;
    }

    /**
     * Número de versión de la conversación para el polling. Sale de APCu; solo
     * toca la base cuando la clave no está cacheada.
     */
    public function getVersion(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel): int
    {
        $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);

        $clave = $this->claveVersion($idConversacion);
        $v = Cache::get($clave);
        if (is_int($v)) {
            return $v;
        }
        $v = $this->repo->getUltimoMensajeId($idConversacion);
        Cache::set($clave, $v, self::TTL_VERSION);
        return $v;
    }

    /** Versión global de la bandeja (para el agente). */
    public function getVersionBandeja(int $idUsuario, int $idEmpresa, int $nivel): int
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $v = Cache::get('cmg_soporte_bandeja_v');
        if (is_int($v)) {
            return $v;
        }
        $v = $this->repo->getUltimoMensajeIdGlobal();
        Cache::set('cmg_soporte_bandeja_v', $v, self::TTL_VERSION);
        return $v;
    }

    // ── Lado agente ──────────────────────────────────────────────────────────

    public function listarBandeja(int $idUsuario, int $idEmpresa, int $nivel, array $filtros = []): array
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);
        return $this->repo->getListadoBandeja($filtros);
    }

    public function tomarConversacion(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $conversacion = $this->repo->findConversacion($idConversacion);
        if ($conversacion === null) {
            throw new Exception('La conversación no existe o ya fue eliminada.');
        }

        $this->repo->asignarAgente($idConversacion, $idUsuario);
        $this->invalidarBandeja();

        $this->logService->registrar($idUsuario, (int) $conversacion['id_empresa'], 'actualizar', 'soporte_conversaciones', $idConversacion,
            ['id_agente_asignado' => $conversacion['id_agente_asignado']],
            ['id_agente_asignado' => $idUsuario]);
    }

    // ── Copiloto de IA ───────────────────────────────────────────────────────

    /**
     * Redacta un BORRADOR de respuesta para el agente. No guarda nada ni envía:
     * el agente lo revisa, lo edita y decide. Por eso el mensaje que acabe
     * saliendo lo firma siempre una persona.
     *
     * La API key que se consume es la de la empresa del AGENTE (su ia_config),
     * no la de la empresa que consulta: el soporte lo presta quien atiende, y
     * quien atiende paga su propio uso.
     *
     * @return array{contenido:string,fuentes:array}
     * @throws Exception
     */
    public function sugerirRespuesta(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $config = $this->getConfig();
        if (empty($config['copiloto_activo'])) {
            throw new Exception('El copiloto de IA está desactivado en la configuración del chat.');
        }

        $conversacion = $this->repo->findConversacion($idConversacion);
        if ($conversacion === null) {
            throw new Exception('La conversación no existe o ya fue eliminada.');
        }

        $mensajes = $this->repo->getMensajes($idConversacion);
        if ($mensajes === []) {
            throw new Exception('La conversación todavía no tiene mensajes.');
        }

        $rag = new \App\Services\Ia\IaRagService();
        if (!$rag->estaConfigurado($idEmpresa)) {
            throw new Exception('Esta empresa no tiene configurado un proveedor de IA. Configúrelo en el módulo IA Soporte.');
        }

        // El hilo, en los roles que entiende el proveedor: lo que escribió quien
        // consulta es 'user'; lo que respondió el equipo, 'assistant'. Los avisos
        // automáticos (rol 'sistema') no aportan y se omiten.
        $historial = [];
        foreach ($mensajes as $m) {
            if ($m['rol'] === 'sistema') {
                continue;
            }
            $historial[] = [
                'rol'       => $m['rol'] === 'usuario' ? 'user' : 'assistant',
                'contenido' => (string) $m['contenido'],
            ];
        }
        if ($historial === []) {
            throw new Exception('No hay ningún mensaje que responder todavía.');
        }

        // El contexto se busca con lo último que dijo quien consulta: es la
        // pregunta viva, no todo el hilo (que arrastraría ruido de temas ya
        // resueltos y degradaría la búsqueda).
        $consulta = '';
        for ($i = count($mensajes) - 1; $i >= 0; $i--) {
            if ($mensajes[$i]['rol'] === 'usuario') {
                $consulta = (string) $mensajes[$i]['contenido'];
                break;
            }
        }
        if ($consulta === '') {
            $consulta = (string) ($conversacion['asunto'] ?? '');
        }

        $resultado = $rag->responder(
            $idEmpresa,
            $historial,
            $this->promptCopiloto((string) ($conversacion['origen_modulo'] ?? '')),
            $consulta,
            [
                // 0 = solo documentos sin restricción de agente: el copiloto no
                // es ninguno de los agentes del catálogo de IA Soporte.
                'id_agente'    => 0,
                'pista_modulo' => (string) ($conversacion['origen_modulo'] ?? ''),
            ]
        );

        $this->logService->registrar($idUsuario, (int) $conversacion['id_empresa'], 'sugerencia_ia', 'soporte_conversaciones', $idConversacion, null, [
            'tokens_entrada' => $resultado['tokens_entrada'],
            'tokens_salida'  => $resultado['tokens_salida'],
        ]);

        return [
            'contenido' => $resultado['contenido'],
            'fuentes'   => $resultado['fuentes'],
        ];
    }

    /**
     * Instrucciones del copiloto. Deliberadamente conservadoras: el borrador lo
     * revisa una persona, así que es mejor que se quede corto y admita lo que no
     * sabe a que rellene con pasos inventados que suenan bien.
     */
    private function promptCopiloto(string $modulo): string
    {
        $contexto = $modulo !== ''
            ? "La persona escribió desde la pantalla \"{$modulo}\" del sistema; tenlo en cuenta al interpretar la consulta.\n\n"
            : '';

        return "Eres el asistente del equipo de soporte de este ERP. Tu tarea es REDACTAR UN BORRADOR "
            . "de respuesta que un agente humano va a revisar, editar y enviar a un usuario del sistema.\n\n"
            . $contexto
            . "Escribe en español, tuteando, con tono cercano y profesional. Ve al grano: primero la "
            . "respuesta, y después los pasos concretos si hacen falta.\n\n"
            . "Reglas que no puedes saltarte:\n"
            . "- Responde ÚNICAMENTE con lo que digan el Manual del Sistema y los documentos del contexto. "
            . "Si no alcanzan para responder, dilo de forma explícita dentro del borrador para que el agente "
            . "lo complete: nunca inventes rutas de menú, nombres de campos, permisos ni pasos.\n"
            . "- No prometas plazos, precios, desarrollos futuros ni excepciones: eso lo decide el agente.\n"
            . "- No te presentes ni firmes el mensaje; lo envía una persona en su propio nombre.\n"
            . "- Si la consulta es ambigua, redacta una repregunta breve en lugar de suponer qué quiso decir.";
    }

    // ── Ciclo de vida ────────────────────────────────────────────────────────

    public function cambiarEstado(int $idConversacion, string $estado, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $this->rules->validarEstado($estado);
        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);

        $this->repo->cambiarEstado($idConversacion, $estado, $idUsuario);
        $this->invalidarContadores((int) $conversacion['created_by']);

        $this->logService->registrar($idUsuario, (int) $conversacion['id_empresa'], 'actualizar', 'soporte_conversaciones', $idConversacion,
            ['estado' => $conversacion['estado']], ['estado' => $estado]);
    }

    public function calificar(int $idConversacion, int $calificacion, ?string $comentario, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $this->rules->validarCalificacion($calificacion);
        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);

        // Califica quien abrió el hilo, no quien lo atendió.
        if ((int) $conversacion['created_by'] !== $idUsuario) {
            throw new Exception('Solo quien abrió la conversación puede calificarla.');
        }

        $this->repo->calificar($idConversacion, $calificacion, $comentario, $idUsuario);

        $this->logService->registrar($idUsuario, (int) $conversacion['id_empresa'], 'actualizar', 'soporte_conversaciones', $idConversacion,
            ['calificacion' => $conversacion['calificacion']], ['calificacion' => $calificacion]);
    }

    public function eliminarConversacion(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);

        $this->repo->eliminarConversacion($idConversacion, $idUsuario);
        $this->invalidarContadores((int) $conversacion['created_by']);
        $this->invalidarBandeja();

        $this->logService->registrar($idUsuario, (int) $conversacion['id_empresa'], 'eliminar', 'soporte_conversaciones', $idConversacion, $conversacion, null);
    }

    // ── Contadores para el navbar ────────────────────────────────────────────

    /** Mensajes sin leer esperando al usuario (con caché por usuario). */
    public function contarSinLeerUsuario(int $idUsuario): int
    {
        $clave = 'cmg_soporte_sinleer_' . $idUsuario;
        $n = Cache::get($clave);
        if (is_int($n)) {
            return $n;
        }
        $n = $this->repo->contarSinLeerUsuario($idUsuario);
        Cache::set($clave, $n, self::TTL_CONTADORES);
        return $n;
    }

    /**
     * Carga pendiente de la bandeja. Clave única global (no por empresa): la
     * bandeja tampoco lo es.
     *
     * @return array{espera:int,sin_leer:int}
     */
    public function contarBandeja(): array
    {
        $clave = 'cmg_soporte_bandeja_c';
        $c = Cache::get($clave);
        if (is_array($c)) {
            return $c;
        }
        $c = $this->repo->contarBandeja();
        Cache::set($clave, $c, self::TTL_CONTADORES);
        return $c;
    }

    // ── Respuestas rápidas (del equipo de soporte) ───────────────────────────

    public function listarRespuestasRapidas(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $filas = $this->repo->getRespuestasRapidas($idEmpresa, $idUsuario);
        $empresa = [];
        $personales = [];
        foreach ($filas as $f) {
            $item = [
                'id'        => (int) $f['id'],
                'titulo'    => $f['titulo'],
                'contenido' => $f['contenido'],
            ];
            if ($f['id_usuario'] === null) {
                $empresa[] = $item;
            } else {
                $personales[] = $item;
            }
        }
        return ['empresa' => $empresa, 'personales' => $personales];
    }

    /**
     * Crea o actualiza una plantilla. $tipo: 'empresa' (compartida) | 'personal'.
     */
    public function guardarRespuestaRapida(int $id, string $titulo, string $contenido, string $tipo, int $idUsuario, int $idEmpresa, int $nivel): int
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);
        $this->rules->validarRespuestaRapida($titulo, $contenido);

        $titulo = trim($titulo);
        $contenido = trim($contenido);

        if ($id > 0) {
            $actual = $this->repo->findRespuestaRapida($id, $idEmpresa);
            if ($actual === null) {
                throw new Exception('La respuesta rápida no existe o ya fue eliminada.');
            }
            // Una plantilla personal solo la toca su dueño; las de empresa,
            // cualquier agente de esa empresa.
            if ($actual['id_usuario'] !== null && (int) $actual['id_usuario'] !== $idUsuario) {
                throw new Exception('No puede editar una respuesta rápida personal de otro usuario.');
            }
            $this->repo->actualizarRespuestaRapida($id, $idEmpresa, $titulo, $contenido, $idUsuario);
            return $id;
        }

        return $this->repo->crearRespuestaRapida(
            $idEmpresa,
            $tipo === 'empresa' ? null : $idUsuario,
            $titulo,
            $contenido,
            $idUsuario
        );
    }

    public function eliminarRespuestaRapida(int $id, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $actual = $this->repo->findRespuestaRapida($id, $idEmpresa);
        if ($actual === null) {
            throw new Exception('La respuesta rápida no existe o ya fue eliminada.');
        }
        if ($actual['id_usuario'] !== null && (int) $actual['id_usuario'] !== $idUsuario) {
            throw new Exception('No puede eliminar una respuesta rápida personal de otro usuario.');
        }

        $this->repo->eliminarRespuestaRapida($id, $idEmpresa, $idUsuario);
    }

    // ── Adjuntos ─────────────────────────────────────────────────────────────

    /**
     * Adjunta un archivo a la conversación como un mensaje más. El texto es
     * opcional: si no lo hay, se usa el nombre del archivo como contenido para
     * que el hilo se lea bien.
     *
     * @param array<string,mixed> $file entrada de $_FILES
     * @return array{id:int,rol:string}
     */
    public function adjuntar(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel, array $file, string $texto = '', string $origen = ''): array
    {
        $this->rules->validarAdjunto($file);

        $conversacion = $this->cargarConversacionAccesible($idConversacion, $idUsuario, $idEmpresa, $nivel);
        $this->rules->validarConversacionAbierta($conversacion);
        $this->rules->validarRateLimit($this->repo->contarMensajesUltimoMinuto($idConversacion, $idUsuario));

        $rol = $this->resolverRol($conversacion, $idUsuario, $idEmpresa, $nivel, $origen);

        // El archivo se guarda bajo la empresa DE LA CONVERSACIÓN, para que la
        // ruta física siga al hilo y no a quién subió el archivo.
        $idEmpresaHilo = (int) $conversacion['id_empresa'];
        $guardado = $this->guardarArchivoFisico($idEmpresaHilo, $file);

        try {
            $this->repo->beginTransaction();

            $contenido = trim($texto) !== '' ? trim($texto) : $guardado['nombre_original'];
            $idMensaje = $this->repo->crearMensaje([
                'id_empresa'      => $idEmpresaHilo,
                'id_conversacion' => $idConversacion,
                'rol'             => $rol,
                'contenido'       => $contenido,
                'adjunto'         => $guardado['archivo'],
                'adjunto_nombre'  => $guardado['nombre_original'],
                'adjunto_mime'    => $guardado['mime_type'],
                'adjunto_bytes'   => $guardado['tamano_bytes'],
                'id_usuario'      => $idUsuario,
            ]);

            $this->repo->actualizarResumen($idConversacion, '📎 ' . $guardado['nombre_original'], $rol, $idUsuario);

            if ($rol === 'agente' && empty($conversacion['id_agente_asignado'])) {
                $this->repo->asignarAgente($idConversacion, $idUsuario);
            }

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            $this->borrarFisico($idEmpresaHilo, $guardado['archivo']);
            throw new Exception('No se pudo adjuntar el archivo: ' . $e->getMessage(), 0, $e);
        }

        $this->refrescarVersion($idConversacion, $idMensaje);
        $this->invalidarContadores((int) $conversacion['created_by']);

        return ['id' => $idMensaje, 'rol' => $rol];
    }

    /**
     * Datos y ruta física de un adjunto, ya validado el acceso.
     *
     * El control es por CONVERSACIÓN, no por ruta: se pide el id del mensaje y
     * se comprueba que quien lo pide pueda ver ese hilo. Así no hay forma de
     * llegar a un archivo manipulando la ruta.
     *
     * @return array{ruta:string,nombre:string,mime:string}
     */
    public function getAdjunto(int $idMensaje, int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $mensaje = $this->repo->findMensaje($idMensaje);
        if ($mensaje === null || empty($mensaje['adjunto'])) {
            throw new Exception('El archivo no existe.');
        }

        // Valida el acceso al hilo; lanza si no corresponde.
        $this->cargarConversacionAccesible((int) $mensaje['id_conversacion'], $idUsuario, $idEmpresa, $nivel);

        $ruta = $this->storagePath((int) $mensaje['id_empresa']) . '/' . basename((string) $mensaje['adjunto']);
        if (!is_file($ruta)) {
            throw new Exception('El archivo ya no está disponible en el servidor.');
        }

        return [
            'ruta'   => $ruta,
            'nombre' => (string) ($mensaje['adjunto_nombre'] ?: basename($ruta)),
            'mime'   => (string) ($mensaje['adjunto_mime'] ?: 'application/octet-stream'),
        ];
    }

    // ── Configuración ────────────────────────────────────────────────────────

    public function guardarConfig(array $data, int $idUsuario, int $idEmpresa, int $nivel): void
    {
        $this->exigirAgente($idUsuario, $idEmpresa, $nivel);

        $antes  = $this->repo->getConfig();
        $limpio = $this->rules->validarConfig($data);

        $this->repo->guardarConfig($limpio, $idUsuario);
        $this->invalidarConfig();

        $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'soporte_config', 1, $antes, $limpio);
    }

    // ── Mantenimiento (cron) ─────────────────────────────────────────────────

    /**
     * Avisa de las conversaciones que llevan demasiado tiempo en espera: por
     * correo y, si hay WhatsApp disponible, también por WhatsApp. Se autolimita:
     * no vuelve a avisar de las mismas hasta que cambien.
     *
     * Los dos canales son independientes —basta con que uno esté configurado
     * para que el aviso salga—, pero comparten la misma huella: si ya se avisó
     * de esta lista, no se repite por ningún canal.
     *
     * @return array{enviado:bool,conversaciones:int,correo:bool,whatsapp:int}
     */
    public function alertarSinAtender(): array
    {
        $config  = $this->repo->getConfig();
        $minutos = (int) ($config['minutos_alerta_sin_atender'] ?? 0);
        $vacio   = ['enviado' => false, 'conversaciones' => 0, 'correo' => false, 'whatsapp' => 0];

        if ($minutos <= 0 || empty($config['activo'])) {
            return $vacio;
        }

        $pendientes = $this->repo->getSinAtender($minutos);
        if ($pendientes === []) {
            return $vacio;
        }

        // Huella de lo que se está avisando: si es la misma que la última vez,
        // no se reenvía. Sin esto el cron mandaría un aviso por minuto mientras
        // la conversación siga sin atender.
        $huella = md5(implode(',', array_map(static fn ($c) => $c['id'] . ':' . $c['ultimo_mensaje_at'], $pendientes)));
        $archivo = $this->archivoMarcaAlerta();
        if (is_file($archivo) && trim((string) @file_get_contents($archivo)) === $huella) {
            return ['enviado' => false, 'conversaciones' => count($pendientes), 'correo' => false, 'whatsapp' => 0];
        }

        $okCorreo = false;
        $destinatarios = $this->destinatariosAlerta($config);
        if ($destinatarios !== []) {
            require_once MVC_APP . '/helpers/mail.php';
            $okCorreo = enviar_correo_soporte_pendiente($destinatarios, $pendientes);
        }

        // Nunca debe tumbar el aviso por correo: si WhatsApp falla (credenciales
        // caducadas, Meta caído), se registra y se sigue.
        $enviadosWa = 0;
        try {
            $enviadosWa = $this->notificarWhatsappSinAtender($config, $pendientes);
        } catch (\Throwable $e) {
            $enviadosWa = 0;
        }

        $ok = $okCorreo || $enviadosWa > 0;
        if ($ok) {
            $dir = dirname($archivo);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($archivo, $huella);
        }

        return [
            'enviado'        => $ok,
            'conversaciones' => count($pendientes),
            'correo'         => $okCorreo,
            'whatsapp'       => $enviadosWa,
        ];
    }

    /**
     * Manda el aviso por WhatsApp con la empresa y el usuario que están pidiendo
     * soporte. Reutiliza el módulo de WhatsApp tal cual: las credenciales de Meta
     * son las de empresa_whatsapp_config y los destinos, los números de aviso ya
     * registrados ahí (whatsapp_aviso_numeros), para no llevar dos listas.
     *
     * QUIÉN LO ENVÍA. Primero, una empresa que atienda soporte y tenga WhatsApp
     * configurado; si ninguna lo tiene, la empresa del usuario que consulta
     * —tiene WhatsApp y es la interesada en que su consulta se vea—. Si no hay
     * ninguna de las dos, no se envía nada y el correo sigue su curso.
     *
     * TEXTO LIBRE vs PLANTILLA. Meta solo entrega texto libre dentro de la
     * ventana de 24 h desde el último mensaje del destinatario. Por eso, si se
     * configuró una plantilla aprobada, se usa esa ({{1}} empresa, {{2}} usuario,
     * {{3}} asunto) y el aviso llega siempre.
     *
     * @param array<string,mixed>       $config
     * @param array<int,array<string,mixed>> $pendientes
     * @return int números avisados correctamente
     */
    private function notificarWhatsappSinAtender(array $config, array $pendientes): int
    {
        $idEmpresaEmisora = $this->empresaEmisoraWhatsapp($pendientes);
        if ($idEmpresaEmisora === null) {
            return 0;
        }

        $destinos = $this->numerosAlertaWhatsapp($config, $idEmpresaEmisora);
        if ($destinos === []) {
            return 0;
        }

        $primera   = $pendientes[0];
        $empresa   = trim((string) ($primera['empresa_nombre'] ?? '')) ?: 'Empresa sin nombre';
        $usuario   = trim((string) ($primera['usuario_nombre'] ?? '')) ?: 'Usuario sin nombre';
        $asunto    = trim((string) ($primera['asunto'] ?? '')) ?: 'Consulta de soporte';
        $plantilla = trim((string) ($config['whatsapp_plantilla'] ?? ''));
        $idioma    = trim((string) ($config['whatsapp_plantilla_idioma'] ?? '')) ?: 'es';

        $wa       = new \App\services\WhatsappService();
        $enviados = 0;

        foreach ($destinos as $telefono) {
            if ($plantilla !== '') {
                $componentes = [[
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $empresa],
                        ['type' => 'text', 'text' => $usuario],
                        ['type' => 'text', 'text' => mb_substr($asunto, 0, 200)],
                    ],
                ]];
                $res = $wa->sendTemplateMessage($idEmpresaEmisora, $telefono, $plantilla, $idioma, $componentes);
            } else {
                $res = $wa->sendTextMessage($idEmpresaEmisora, $telefono, $this->textoAlertaWhatsapp($pendientes));
            }

            if (!empty($res['success'])) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /**
     * Empresa cuyas credenciales de WhatsApp envían el aviso: la que atiende, y
     * si ninguna de las que atienden tiene WhatsApp, la del usuario que consulta.
     *
     * @param array<int,array<string,mixed>> $pendientes
     */
    private function empresaEmisoraWhatsapp(array $pendientes): ?int
    {
        try {
            $idSubmodulo = (new \App\models\PermisoSubmodulo())->getIdSubmoduloPorRutaMvc(self::RUTA_MODULO);
        } catch (\Throwable $e) {
            $idSubmodulo = null;
        }

        if ($idSubmodulo !== null) {
            $conWhatsapp = $this->repo->getEmpresasAsignadasConWhatsapp($idSubmodulo);
            if ($conWhatsapp !== []) {
                return $conWhatsapp[0];
            }
        }

        $idEmpresaConsulta = (int) ($pendientes[0]['id_empresa'] ?? 0);

        return $idEmpresaConsulta > 0 && $this->repo->empresaTieneWhatsapp($idEmpresaConsulta)
            ? $idEmpresaConsulta
            : null;
    }

    /**
     * Números que reciben el aviso: el configurado en el chat si lo hay, y si no
     * los que la empresa emisora ya tiene registrados para los avisos de WhatsApp.
     *
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private function numerosAlertaWhatsapp(array $config, int $idEmpresaEmisora): array
    {
        $configurado = preg_replace('/\D/', '', (string) ($config['whatsapp_alertas'] ?? ''));
        if ($configurado !== '') {
            return [$configurado];
        }

        return $this->repo->getNumerosAvisoWhatsapp($idEmpresaEmisora);
    }

    /**
     * Texto del aviso. Empieza por la empresa y el usuario —que es lo que hay que
     * saber para reaccionar— y añade el resto de consultas en espera si las hay.
     *
     * @param array<int,array<string,mixed>> $pendientes
     */
    private function textoAlertaWhatsapp(array $pendientes): string
    {
        $primera = $pendientes[0];
        $empresa = trim((string) ($primera['empresa_nombre'] ?? '')) ?: 'Empresa sin nombre';
        $usuario = trim((string) ($primera['usuario_nombre'] ?? '')) ?: 'Usuario sin nombre';
        $asunto  = trim((string) ($primera['asunto'] ?? ''));
        $minutos = (int) round((float) ($primera['minutos_espera'] ?? 0));

        $texto  = "🎧 *Consulta de soporte sin atender*\n\n";
        $texto .= "*Empresa:* {$empresa}\n";
        $texto .= "*Usuario:* {$usuario}\n";
        if ($asunto !== '') {
            $texto .= "*Asunto:* " . mb_substr($asunto, 0, 200) . "\n";
        }
        $texto .= "*Esperando:* {$minutos} min\n";

        $otras = count($pendientes) - 1;
        if ($otras > 0) {
            $texto .= "\nHay {$otras} consulta(s) más en espera:\n";
            foreach (array_slice($pendientes, 1, 5) as $c) {
                $emp = trim((string) ($c['empresa_nombre'] ?? '')) ?: 'Empresa sin nombre';
                $usu = trim((string) ($c['usuario_nombre'] ?? '')) ?: 'Usuario sin nombre';
                $texto .= "• {$emp} — {$usu}\n";
            }
            if ($otras > 5) {
                $texto .= '• … y ' . ($otras - 5) . " más\n";
            }
        }

        return $texto;
    }

    /**
     * A quién se avisa: a todas las empresas que tienen asignado el submódulo
     * del chat, es decir, a las que atienden.
     *
     * El campo de configuración sigue existiendo como excepción, para desviar
     * los avisos a un alias de equipo distinto del correo de las empresas.
     * Vacío —que es lo normal— manda el reparto por permisos.
     *
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private function destinatariosAlerta(array $config): array
    {
        $configurado = trim((string) ($config['correo_alertas'] ?? ''));
        if ($configurado !== '' && filter_var($configurado, FILTER_VALIDATE_EMAIL) !== false) {
            return [$configurado];
        }

        try {
            $idSubmodulo = (new \App\models\PermisoSubmodulo())->getIdSubmoduloPorRutaMvc(self::RUTA_MODULO);
        } catch (\Throwable $e) {
            return [];
        }

        return $idSubmodulo !== null ? $this->repo->getCorreosEmpresasAsignadas($idSubmodulo) : [];
    }

    private function archivoMarcaAlerta(): string
    {
        return MVC_ROOT . '/storage/soporte_chat/.alerta_sin_atender';
    }

    // ── Archivos ─────────────────────────────────────────────────────────────

    /**
     * @return array{archivo:string,nombre_original:string,mime_type:string,tamano_bytes:int}
     */
    private function guardarArchivoFisico(int $idEmpresa, array $file): array
    {
        $dir = $this->storagePath($idEmpresa);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }

        $nombreOrig = basename((string) $file['name']);
        $ext = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        // Nombre generado: el del usuario nunca toca el sistema de archivos.
        $nombreUnico = uniqid('sop_', true) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);

        $destino = $dir . '/' . $nombreUnico;
        if (!move_uploaded_file((string) $file['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        $mime = function_exists('mime_content_type') ? (@mime_content_type($destino) ?: '') : '';

        return [
            'archivo'         => $nombreUnico,
            'nombre_original' => mb_substr($nombreOrig, 0, 255),
            'mime_type'       => $mime !== '' ? $mime : 'application/octet-stream',
            'tamano_bytes'    => (int) $file['size'],
        ];
    }

    private function borrarFisico(int $idEmpresa, string $archivo): void
    {
        if ($archivo === '') {
            return;
        }
        $ruta = $this->storagePath($idEmpresa) . '/' . basename($archivo);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function storagePath(int $idEmpresa): string
    {
        return MVC_ROOT . '/storage/soporte_chat/' . $idEmpresa;
    }

    /**
     * Archiva las conversaciones cerradas que cumplieron el plazo configurado.
     * Pensado para ejecutarse una vez al día desde cron_runner.
     */
    public function archivarCerradas(): int
    {
        $config = $this->repo->getConfig();
        $dias = (int) ($config['dias_archivar_cerradas'] ?? 0);
        if ($dias <= 0) {
            return 0;
        }

        $n = $this->repo->archivarCerradas($dias);
        if ($n > 0) {
            $this->invalidarBandeja();
        }
        return $n;
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    /**
     * Devuelve la conversación solo si quien pregunta puede verla: su dueño, o
     * un agente. Cualquier otro caso es un 'no existe' — no se distingue entre
     * "no existe" y "no es tuya" para no filtrar la existencia de hilos ajenos.
     *
     * @throws Exception
     */
    /**
     * Decide si un mensaje se registra como del 'usuario' o del 'agente'.
     *
     * No basta con mirar quién abrió el hilo: el equipo de soporte también usa
     * el sistema y puede abrir consultas. Si el rol dependiera solo de eso, todo
     * lo que escribiera un agente en su propia conversación quedaría como
     * 'usuario' y el aviso al otro lado nunca se encendería.
     *
     * El origen de la petición ('bandeja' o 'widget') solo se tiene en cuenta
     * cuando quien escribe es agente Y además es el dueño del hilo — el único
     * caso ambiguo. Para todos los demás manda el servidor, así que un usuario
     * normal NO puede hacerse pasar por soporte mandando otro origen.
     *
     * @param array<string,mixed> $conversacion
     */
    private function resolverRol(array $conversacion, int $idUsuario, int $idEmpresa, int $nivel, string $origen): string
    {
        if (!$this->esAgente($idUsuario, $idEmpresa, $nivel)) {
            return 'usuario';   // quien no atiende, nunca escribe como soporte
        }
        if ((int) $conversacion['created_by'] !== $idUsuario) {
            return 'agente';    // agente respondiendo un hilo ajeno
        }
        // Agente en su propia conversación: manda desde dónde escribe.
        return $origen === 'bandeja' ? 'agente' : 'usuario';
    }

    private function cargarConversacionAccesible(int $idConversacion, int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $conversacion = $this->repo->findConversacion($idConversacion);
        if ($conversacion === null) {
            throw new Exception('La conversación no existe o ya fue eliminada.');
        }

        $esDueno = (int) $conversacion['created_by'] === $idUsuario;
        if ($esDueno || $this->esAgente($idUsuario, $idEmpresa, $nivel)) {
            return $conversacion;
        }

        throw new Exception('La conversación no existe o ya fue eliminada.');
    }

    private function claveVersion(int $idConversacion): string
    {
        return 'cmg_soporte_v_' . $idConversacion;
    }

    /** Publica el nuevo número de versión sin esperar al TTL. */
    private function refrescarVersion(int $idConversacion, int $idMensaje): void
    {
        Cache::set($this->claveVersion($idConversacion), $idMensaje, self::TTL_VERSION);
        Cache::set('cmg_soporte_bandeja_v', $idMensaje, self::TTL_VERSION);
        $this->invalidarBandeja();
    }

    private function invalidarContadores(int $idUsuarioDueno): void
    {
        Cache::delete('cmg_soporte_sinleer_' . $idUsuarioDueno);
    }

    private function invalidarBandeja(): void
    {
        Cache::delete('cmg_soporte_bandeja_c');
    }
}

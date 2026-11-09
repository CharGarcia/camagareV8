<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmCanalRepository;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmContactoRepository;
use App\repositories\modulos\CrmConversacionRepository;
use App\Rules\modulos\CrmMensajeRules;
use App\Services\InstagramApiService;
use App\Services\LogSistemaService;

/**
 * Bandeja social (modulos/bandeja_social): leer y responder las conversaciones
 * de Instagram, asignarlas, cerrarlas y convertirlas en oportunidades.
 * Diseño: docs/diseno/crm-redes-fase1.md §7 y §9.2.
 *
 * Envío en dos tiempos: (1) en una transacción corta y bajo candado de la
 * conversación se valida la ventana y se guarda el mensaje como «enviando»;
 * (2) fuera de la transacción se llama a Instagram y se anota «enviado» o
 * «fallido». Así un Instagram lento no retiene candados ni la base.
 */
class CrmBandejaService
{
    public const MODOS = ['dm', 'publica', 'privada'];

    public function __construct(
        private CrmConversacionRepository $conversaciones,
        private CrmCanalRepository $canales,
        private CrmContactoRepository $contactos,
        private CrmCatalogoRepository $catalogos,
        private ?InstagramApiService $apiFija,   // solo pruebas; si no, la app de la empresa
        private CrmMensajeRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(?InstagramApiService $api = null): self
    {
        return new self(
            new CrmConversacionRepository(), new CrmCanalRepository(), new CrmContactoRepository(),
            new CrmCatalogoRepository(), $api, new CrmMensajeRules(), new LogSistemaService()
        );
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    public function listar(int $idEmpresa, array $filtros, int $idUsuario, ?int $idUsuarioFiltro): array
    {
        $rows = $this->conversaciones->listarBandeja($idEmpresa, $filtros, $idUsuario, $idUsuarioFiltro);
        foreach ($rows as &$r) {
            $r['ventana'] = $r['tipo'] === 'dm' ? $this->ventana($r) : null;
        }
        return $rows;
    }

    public function contarSinLeer(int $idEmpresa, ?int $idUsuarioFiltro): int
    {
        return $this->conversaciones->contarSinLeer($idEmpresa, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        return $this->conversaciones->getDetalle($id, $idEmpresa);
    }

    /**
     * Abre la conversación: datos, mensajes (los posteriores a $desdeId) y la
     * marca como leída. $det ya viene validado por el controlador (acceso).
     */
    public function abrir(array $det, int $idEmpresa, int $desdeId = 0): array
    {
        $id = (int) $det['id'];
        $this->conversaciones->marcarLeida($id, $idEmpresa);
        $det['sin_leer'] = 0;
        $det['ventana'] = $det['tipo'] === 'dm' ? $this->ventana($det) : null;
        unset($det['canal_id_externo']);
        if ($det['tipo'] === 'comentario') {
            $com = $this->conversaciones->ultimoComentarioCliente($id);
            $det['respuesta_privada_disponible'] = $com
                && !$this->conversaciones->tieneRespuestaPrivada($id, (string) $com['id_externo'])
                && (time() - strtotime((string) $com['fecha'])) <= CrmMensajeRules::DIAS_HUMANO * 86400;
        }
        return ['conversacion' => $det, 'mensajes' => $this->conversaciones->mensajes($id, $idEmpresa, $desdeId)];
    }

    public function respuestasRapidas(int $idEmpresa, int $idUsuario): array
    {
        return $this->catalogos->respuestasRapidas($idEmpresa, $idUsuario);
    }

    /** Ruta absoluta del adjunto de un mensaje (solo dentro de storage/crm_media). */
    public function rutaAdjunto(array $mensaje): ?string
    {
        $rel = (string) ($mensaje['adjunto_ruta'] ?? '');
        if ($rel === '' || !str_starts_with($rel, 'crm_media/') || str_contains($rel, '..')) {
            return null;
        }
        $base = realpath(MVC_ROOT . CrmWebhookService::DIR_ADJUNTOS);
        $abs = realpath(MVC_ROOT . '/storage/' . $rel);
        return ($base && $abs && str_starts_with($abs, $base) && is_file($abs)) ? $abs : null;
    }

    public function getMensaje(int $id, int $idEmpresa): ?array
    {
        return $this->conversaciones->getMensaje($id, $idEmpresa);
    }

    private function ventana(array $c): array
    {
        $v = $this->rules->ventana($c['ultima_entrada_at'] ?? null, in_array($c['ventana_humana'] ?? false, [true, 't', 'true', 1, '1'], true));
        return $v;
    }

    // ── Envío ────────────────────────────────────────────────────────────────

    /**
     * Responde en la conversación. $modo: dm (mensaje directo), publica (bajo el
     * comentario) o privada (DM a quien comentó). Devuelve el mensaje guardado
     * con su estado final y, si falló, el motivo en 'error'.
     */
    public function enviar(int $idConv, string $modo, string $texto, int $idEmpresa, int $idUsuario): array
    {
        if (!in_array($modo, self::MODOS, true)) {
            throw new \InvalidArgumentException('Tipo de respuesta no válido.');
        }
        $texto = $this->rules->validarTexto($texto);

        // 1. Validar y guardar como «enviando» (transacción corta, bajo candado).
        $prep = $this->enTransaccion(function () use ($idConv, $modo, $texto, $idEmpresa, $idUsuario) {
            if (!$this->conversaciones->bloquear($idConv, $idEmpresa)) {
                throw new \RuntimeException('Conversación no encontrada.');
            }
            $det = $this->conversaciones->getDetalle($idConv, $idEmpresa);
            $destino = $this->validarDestino($det, $modo);
            $tipo = ['dm' => 'text', 'publica' => 'comment_reply', 'privada' => 'private_reply'][$modo];
            $idMsg = $this->conversaciones->insertarSaliente($idEmpresa, $idConv, $tipo, $texto, $idUsuario, $destino);
            $this->log->registrar($idUsuario, $idEmpresa, 'ENVIAR_MENSAJE', 'crm_mensajes', $idMsg, null,
                ['id_conversacion' => $idConv, 'modo' => $modo, 'tag' => $destino['tag'] ?? null]);
            return ['id' => $idMsg, 'det' => $det, 'destino' => $destino];
        });

        // 2. Instagram, fuera de la transacción.
        return $this->despachar($prep['id'], $prep['det'], $modo, $texto, $prep['destino'], $idEmpresa);
    }

    /** Vuelve a enviar un mensaje que quedó «fallido». */
    public function reintentar(int $idMensaje, int $idEmpresa, int $idUsuario): array
    {
        $prep = $this->enTransaccion(function () use ($idMensaje, $idEmpresa, $idUsuario) {
            $m = $this->conversaciones->getMensaje($idMensaje, $idEmpresa);
            if (!$m || $m['direccion'] !== 'OUT' || $m['estado_envio'] !== 'fallido') {
                throw new \InvalidArgumentException('Solo se reintentan mensajes que no se pudieron enviar.');
            }
            $idConv = (int) $m['id_conversacion'];
            $this->conversaciones->bloquear($idConv, $idEmpresa);
            $det = $this->conversaciones->getDetalle($idConv, $idEmpresa);
            $modo = ['text' => 'dm', 'comment_reply' => 'publica', 'private_reply' => 'privada'][$m['tipo']] ?? 'dm';
            $destino = $this->validarDestino($det, $modo);
            $this->conversaciones->reintentar($idMensaje);
            $this->log->registrar($idUsuario, $idEmpresa, 'REINTENTAR_MENSAJE', 'crm_mensajes', $idMensaje, null, ['modo' => $modo]);
            return ['det' => $det, 'modo' => $modo, 'texto' => (string) $m['texto'], 'destino' => $destino];
        });
        return $this->despachar($idMensaje, $prep['det'], $prep['modo'], $prep['texto'], $prep['destino'], $idEmpresa);
    }

    /** Reglas de Instagram según el modo. Devuelve a quién y cómo se envía. */
    private function validarDestino(?array $det, string $modo): array
    {
        if (!$det) {
            throw new \RuntimeException('Conversación no encontrada.');
        }
        if ($det['canal_estado'] !== 'activo') {
            throw new \InvalidArgumentException('La cuenta de Instagram está desconectada o con el acceso vencido. Reconéctela en Configuración del CRM.');
        }
        if ($modo === 'dm') {
            if ($det['tipo'] !== 'dm') {
                throw new \InvalidArgumentException('En un comentario elija responder en público o en privado.');
            }
            $v = $this->ventana($det);
            if (!$v['puede']) {
                throw new \InvalidArgumentException($v['motivo']);
            }
            return ['igsid' => $det['id_hilo_externo'], 'tag' => $v['tag']];
        }
        if ($det['tipo'] !== 'comentario') {
            throw new \InvalidArgumentException('Las respuestas a comentarios solo aplican a conversaciones de comentarios.');
        }
        $com = $this->conversaciones->ultimoComentarioCliente((int) $det['id']);
        if (!$com) {
            throw new \InvalidArgumentException('No hay comentario del cliente al que responder.');
        }
        if ($modo === 'privada') {
            $this->rules->validarRespuestaPrivada($com, $this->conversaciones->tieneRespuestaPrivada((int) $det['id'], (string) $com['id_externo']));
        }
        return ['comment_id' => (string) $com['id_externo']];
    }

    private function despachar(int $idMsg, array $det, string $modo, string $texto, array $destino, int $idEmpresa): array
    {
        $error = null;
        $idExterno = null;
        try {
            $token = CryptoHelper::desencriptar((string) $this->canales->getTokenCifrado((int) $det['id_canal'], $idEmpresa));
            if ($token === '') {
                throw new \RuntimeException('La cuenta de Instagram no tiene un acceso vigente. Reconéctela en Configuración del CRM.');
            }
            $api = $this->apiFija ?? InstagramApiService::paraEmpresa($idEmpresa);
            $idExterno = match ($modo) {
                'dm'      => $api->enviarMensaje($token, (string) $destino['igsid'], $texto, $destino['tag'] ?? null),
                'privada' => $api->respuestaPrivada($token, (string) $destino['comment_id'], $texto),
                default   => $api->responderComentario($token, (string) $destino['comment_id'], $texto),
            };
        } catch (\Throwable $e) {
            $error = $e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo enviar. Intente de nuevo.';
        }
        $this->conversaciones->resultadoEnvio($idMsg, $idExterno, $error ? 'fallido' : 'enviado', $error);
        if (!$error) {
            $this->conversaciones->registrarMensaje((int) $det['id'], 'Tú: ' . mb_substr($texto, 0, 120), date('Y-m-d H:i:s'), false);
        }
        $m = $this->conversaciones->getMensaje($idMsg, $idEmpresa) ?? ['id' => $idMsg];
        $m['error'] = $error;
        return $m;
    }

    // ── Gestión ──────────────────────────────────────────────────────────────

    public function asignar(int $idConv, ?int $idResponsable, int $idEmpresa, int $idUsuario): void
    {
        if ($idResponsable !== null && $idResponsable !== $idUsuario
            && !$this->catalogos->usuarioPerteneceEmpresa($idResponsable, $idEmpresa)) {
            throw new \InvalidArgumentException('El usuario elegido no pertenece a esta empresa.');
        }
        $this->enTransaccion(function () use ($idConv, $idResponsable, $idEmpresa, $idUsuario) {
            $antes = $this->conversaciones->bloquear($idConv, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Conversación no encontrada.');
            }
            $this->conversaciones->asignar($idConv, $idEmpresa, $idResponsable, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ASIGNAR', 'crm_conversaciones', $idConv,
                ['id_responsable' => $antes['id_responsable']], ['id_responsable' => $idResponsable]);
        });
    }

    public function cambiarEstado(int $idConv, string $estado, int $idEmpresa, int $idUsuario): void
    {
        if (!in_array($estado, ['abierta', 'cerrada'], true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
        $this->enTransaccion(function () use ($idConv, $estado, $idEmpresa, $idUsuario) {
            $antes = $this->conversaciones->bloquear($idConv, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Conversación no encontrada.');
            }
            $this->conversaciones->cambiarEstado($idConv, $idEmpresa, $estado, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $estado === 'cerrada' ? 'CERRAR' : 'REABRIR', 'crm_conversaciones', $idConv,
                ['estado' => $antes['estado']], ['estado' => $estado]);
        });
    }

    /** Une la conversación a un contacto que ya existía (p. ej. el mismo cliente registrado a mano). */
    public function enlazarContacto(int $idConv, int $idContacto, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro): void
    {
        if (!$this->contactos->buscarParaSelect($idEmpresa, '', $idUsuarioFiltro, $idContacto)) {
            throw new \InvalidArgumentException('El contacto no existe o no tiene acceso a él.');
        }
        $this->enTransaccion(function () use ($idConv, $idContacto, $idEmpresa, $idUsuario) {
            $antes = $this->conversaciones->bloquear($idConv, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Conversación no encontrada.');
            }
            $this->conversaciones->enlazarContacto($idConv, $idEmpresa, $idContacto, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ENLAZAR_CONTACTO', 'crm_conversaciones', $idConv,
                ['id_contacto' => $antes['id_contacto']], ['id_contacto' => $idContacto]);
        });
    }

    /**
     * Oportunidad nacida de la conversación: con su contacto, el origen del
     * contacto y la conversación enlazada. Guardado único con el token del formulario.
     */
    public function crearOportunidad(array $det, array $d, int $idEmpresa, int $idUsuario): array
    {
        if (empty($det['id_contacto'])) {
            throw new \InvalidArgumentException('La conversación no tiene contacto. Enlácela a uno primero.');
        }
        $contacto = $this->contactos->getPorId((int) $det['id_contacto'], $idEmpresa);
        $svc = CrmOportunidadService::crear();
        // El acceso a la conversación ya se validó: no se filtra el contacto por registros propios.
        $r = $svc->crearOportunidad([
            'titulo'          => $d['titulo'] ?? '',
            'valor_estimado'  => $d['valor_estimado'] ?? 0,
            'id_embudo'       => $d['id_embudo'] ?? 0,
            'id_contacto'     => (int) $det['id_contacto'],
            'id_origen'       => $contacto['id_origen'] ?? null,
            'id_responsable'  => $det['id_responsable'] ?: $idUsuario,
            'id_conversacion' => (int) $det['id'],
            'token_guardado'  => $d['token_guardado'] ?? '',
        ], $idEmpresa, $idUsuario, null);
        $r['ya_existia'] = $svc->ultimoGuardadoPrevio;
        return $r;
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\modulos\videollamadas;

/**
 * Contrato del motor de video.
 *
 * El módulo (salas, permisos, participantes, auditoría, multiempresa) es
 * independiente del motor que transporta el video. Un proveedor solo responde
 * dos preguntas: "dame una sala" y "dame las credenciales para que este
 * participante entre".
 *
 * Implementaciones previstas:
 *   ProveedorInterno → WebRTC mesh P2P propio, sin servidor de medios (Fase 2)
 *   ProveedorJitsi   → Jitsi self-hosted, para reuniones grandes (Fase 6)
 *   ProveedorDaily   → SaaS, alternativa a Jitsi (Fase 6)
 *
 * Añadir un motor nuevo NO obliga a tocar el service, el controlador ni la BD.
 */
interface ProveedorVideollamada
{
    /** Identificador del proveedor tal como se guarda en videollamadas_salas.proveedor. */
    public function getNombre(): string;

    /**
     * Máximo de participantes que el motor sostiene con calidad aceptable.
     * El mesh P2P tiene un techo bajo; un SFU no.
     */
    public function getMaxParticipantes(): int;

    /**
     * Prepara la sala en el motor.
     *
     * @param array $sala   Fila de videollamadas_salas ya creada.
     * @param array $config Fila de videollamadas_config de la empresa.
     * @return array{id_externo: ?string, url: ?string} Datos que devuelve el motor,
     *         o nulls si el motor no necesita registrar nada (caso del interno).
     */
    public function crearSala(array $sala, array $config): array;

    /**
     * Credenciales que el navegador necesita para conectarse a la sala.
     *
     * En el motor interno son los servidores ICE (STUN/TURN); en un proveedor
     * externo suele ser un JWT de acceso.
     *
     * Regla de seguridad: lo que devuelve este método SÍ baja al navegador, así
     * que jamás puede incluir la clave maestra ni el token de API del proveedor.
     *
     * @param array $participante Fila de videollamadas_participantes.
     */
    public function obtenerCredenciales(array $sala, array $config, array $participante): array;

    /**
     * Libera la sala en el motor cuando la reunión termina o se elimina.
     * Los motores que no guardan estado pueden no hacer nada.
     */
    public function cerrarSala(array $sala, array $config): void;
}

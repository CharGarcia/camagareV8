<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CrmConversacionRepository;

/**
 * Reparto de conversaciones de una cuenta (decisión 5 de la fase 1): sin
 * asignar, siempre a un usuario o por turnos (al de menos conversaciones
 * abiertas). Lo usan el webhook (conversación nueva) y el asistente virtual
 * (traspaso a una persona). Llamar dentro de una transacción: el reparto por
 * turnos toma un candado del canal.
 */
class CrmRepartoService
{
    public function __construct(private CrmConversacionRepository $conversaciones)
    {
    }

    /** @param array $canal Fila de crm_canales con `config` ya decodificado. */
    public function elegir(array $canal): ?int
    {
        $config = is_array($canal['config'] ?? null) ? $canal['config'] : (json_decode((string) ($canal['config'] ?? ''), true) ?: []);
        $asig = (array) ($config['asignacion'] ?? []);
        $usuarios = array_values(array_filter(array_map('intval', (array) ($asig['usuarios'] ?? []))));
        switch ($asig['regla'] ?? 'sin_asignar') {
            case 'fija':
                return $usuarios[0] ?? null;
            case 'rotacion':
                $this->conversaciones->lockReparto((int) $canal['id']);
                return $this->conversaciones->usuarioConMenosCarga((int) $canal['id'], $usuarios);
            default:
                return null;
        }
    }
}

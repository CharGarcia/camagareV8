<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\BloqueoEdicionRepository;

/**
 * Bloqueo de edición concurrente entre módulos que comparten un mismo registro
 * (p. ej. Pedidos <-> Consignaciones de Venta). Cualquier módulo puede reusar este
 * Service para evitar que dos usuarios editen/usen a la vez el mismo registro.
 *
 * No es un lock pesimista de base de datos (no se puede sostener una transacción
 * abierta mientras el usuario tiene un formulario abierto en el navegador): es un
 * bloqueo optimista con heartbeat. Mientras el modal sigue abierto, el cliente hace
 * ping cada ~30s; si deja de hacerlo (cierre de navegador, pérdida de red), el lock
 * expira solo tras TTL_SEGUNDOS y el registro queda libre de nuevo.
 */
class BloqueoEdicionService
{
    /** Tiempo sin heartbeat tras el cual un lock se considera abandonado y expira. */
    public const TTL_SEGUNDOS = 180;

    private BloqueoEdicionRepository $repo;

    public function __construct(?BloqueoEdicionRepository $repo = null)
    {
        $this->repo = $repo ?? new BloqueoEdicionRepository();
    }

    /**
     * Intenta tomar el bloqueo. Si ya lo tiene el mismo usuario, renueva el ping.
     *
     * @return array{tomado:bool, propio:bool, usuario?:string, modulo_contexto?:?string, desde?:string}
     */
    public function intentarTomar(
        string $tabla,
        int $idRegistro,
        int $idEmpresa,
        int $idUsuario,
        string $nombreUsuario,
        ?string $moduloContexto = null
    ): array {
        $this->repo->limpiarExpirados($tabla, $idRegistro, $idEmpresa, self::TTL_SEGUNDOS);

        if ($this->repo->tomar($tabla, $idRegistro, $idEmpresa, $idUsuario, $nombreUsuario, $moduloContexto)) {
            return ['tomado' => true, 'propio' => true];
        }

        $existente = $this->repo->buscar($tabla, $idRegistro, $idEmpresa);
        if (!$existente) {
            // Carrera extremadamente improbable (se liberó justo entre el INSERT fallido y el SELECT): reintentar una vez.
            if ($this->repo->tomar($tabla, $idRegistro, $idEmpresa, $idUsuario, $nombreUsuario, $moduloContexto)) {
                return ['tomado' => true, 'propio' => true];
            }
            return ['tomado' => false, 'propio' => false];
        }

        if ((int) $existente['id_usuario'] === $idUsuario) {
            $this->repo->renovarPing($tabla, $idRegistro, $idEmpresa, $idUsuario);
            return ['tomado' => true, 'propio' => true];
        }

        return [
            'tomado' => false,
            'propio' => false,
            'usuario' => $existente['nombre_usuario'],
            'modulo_contexto' => $existente['modulo_contexto'],
            'desde' => $existente['fecha_inicio'],
        ];
    }

    /** Heartbeat: false si el lock ya no pertenece a este usuario (expiró y otro lo tomó). */
    public function renovarPing(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario): bool
    {
        return $this->repo->renovarPing($tabla, $idRegistro, $idEmpresa, $idUsuario);
    }

    public function liberar(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario): void
    {
        $this->repo->liberar($tabla, $idRegistro, $idEmpresa, $idUsuario);
    }

    /**
     * Guarda para el servidor (defensa en profundidad, no solo UI): null si el registro
     * está libre o el lock es del propio usuario; si no, info de quién lo tiene para
     * poder lanzar un error de negocio legible.
     *
     * @return array{usuario:string, modulo_contexto:?string}|null
     */
    public function verificarLibreOPropio(string $tabla, int $idRegistro, int $idEmpresa, int $idUsuario): ?array
    {
        $this->repo->limpiarExpirados($tabla, $idRegistro, $idEmpresa, self::TTL_SEGUNDOS);

        $existente = $this->repo->buscar($tabla, $idRegistro, $idEmpresa);
        if (!$existente || (int) $existente['id_usuario'] === $idUsuario) {
            return null;
        }

        return [
            'usuario' => $existente['nombre_usuario'],
            'modulo_contexto' => $existente['modulo_contexto'],
        ];
    }
}

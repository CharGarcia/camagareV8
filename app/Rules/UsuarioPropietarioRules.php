<?php

declare(strict_types=1);

namespace App\Rules;

use App\models\Usuario;

/**
 * Reglas del usuario Propietario (`usuarios.es_propietario`).
 *
 * Permiten tener varios superadministradores (nivel 3) que hagan el trabajo
 * del dueño sin que puedan tocarlo:
 *   1. Al Propietario solo lo gestiona otro Propietario (ficha, nivel, estado,
 *      eliminar, contraseña provisional, invitación, empresas asignadas).
 *   2. Solo un Propietario otorga el nivel 3.
 *   3. Solo un Propietario gestiona a otros superadministradores; los demás
 *      nivel 3 pueden verlos, no modificarlos (sí su propia ficha).
 * Además, un Propietario no puede quedar degradado ni inactivo (evita dejar el
 * sistema sin dueño por error); la marca solo se quita por SQL.
 *
 * Si nadie está marcado como Propietario (o la columna no existe todavía), no
 * aplica ninguna restricción: el sistema se comporta como antes.
 *
 * Cada método devuelve null si la acción está permitida o el motivo del
 * rechazo, listo para mostrar.
 */
class UsuarioPropietarioRules
{
    public function __construct(private Usuario $usuarios)
    {
    }

    /** True si las reglas están activas y el actor NO es Propietario. */
    private function restringido(int $idActor): bool
    {
        if (!$this->usuarios->hayPropietario()) {
            return false;
        }
        $actor = $this->usuarios->getDatosGestion($idActor);
        return !($actor['es_propietario'] ?? false);
    }

    /**
     * ¿Puede el actor actuar sobre el usuario objetivo? (editar, eliminar,
     * contraseña provisional, reenviar invitación, quitar empresas).
     */
    public function motivoNoGestionable(int $idActor, int $idObjetivo): ?string
    {
        if (!$this->restringido($idActor)) {
            return null;
        }
        $objetivo = $this->usuarios->getDatosGestion($idObjetivo);
        if ($objetivo === null) {
            return null; // inexistente: lo resuelve la acción con su propio mensaje
        }
        if ($objetivo['es_propietario']) {
            return 'Ese usuario es el Propietario del sistema: solo el Propietario puede modificarlo.';
        }
        if ((int) $objetivo['nivel'] >= 3 && $idObjetivo !== $idActor) {
            return 'Solo el Propietario del sistema puede gestionar a otros superadministradores.';
        }
        return null;
    }

    /** Valida el nivel y estado que se quieren guardar en la ficha del objetivo. */
    public function motivoCambioNivelEstado(int $idActor, int $idObjetivo, int $nivelNuevo, int $estadoNuevo): ?string
    {
        $objetivo = $this->usuarios->getDatosGestion($idObjetivo);
        if ($objetivo === null) {
            return null;
        }
        if ($objetivo['es_propietario'] && ($nivelNuevo < 3 || $estadoNuevo !== 1)) {
            return 'El Propietario del sistema siempre es superadministrador activo: no se puede bajar de nivel ni desactivar.';
        }
        if ($nivelNuevo >= 3 && (int) $objetivo['nivel'] < 3 && $this->restringido($idActor)) {
            return 'Solo el Propietario del sistema puede otorgar el nivel de superadministrador.';
        }
        return null;
    }

    /** True si el actor es Propietario (para la vista: qué controles mostrar). */
    public function esPropietario(int $idActor): bool
    {
        return (bool) ($this->usuarios->getDatosGestion($idActor)['es_propietario'] ?? false);
    }

    /** True si las reglas restringen al actor (hay Propietario y él no lo es). */
    public function actorRestringido(int $idActor): bool
    {
        return $this->restringido($idActor);
    }
}

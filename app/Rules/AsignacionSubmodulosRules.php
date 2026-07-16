<?php
/**
 * Validaciones de negocio para la asignación masiva de submódulos (superadmin).
 */

declare(strict_types=1);

namespace App\Rules;

class AsignacionSubmodulosRules
{
    private const MODOS_VALIDOS = ['usuarios', 'nivel', 'empresa'];
    private const NIVELES_VALIDOS = ['1', '2', 'todos'];

    /**
     * @param array{ver?:bool,crear?:bool,actualizar?:bool,eliminar?:bool,t?:bool} $permisos
     * @return string[] Lista de errores (vacío = selección válida)
     */
    public function validar(int $idSubmodulo, int $idModulo, array $permisos, string $modo, array $params): array
    {
        $errores = [];

        if ($idSubmodulo <= 0 || $idModulo <= 0) {
            $errores[] = 'Debe seleccionar el submódulo a asignar.';
        }

        $tienePermiso = !empty($permisos['ver']) || !empty($permisos['crear'])
            || !empty($permisos['actualizar']) || !empty($permisos['eliminar']) || !empty($permisos['t']);
        if (!$tienePermiso) {
            $errores[] = 'Debe marcar al menos un permiso (Ver, Crear, Actualizar, Eliminar o Acceso total).';
        }

        if (!in_array($modo, self::MODOS_VALIDOS, true)) {
            $errores[] = 'Debe elegir cómo seleccionar a los destinatarios.';
            return $errores;
        }

        switch ($modo) {
            case 'usuarios':
                if (empty($params['ids_usuario'])) {
                    $errores[] = 'Debe seleccionar al menos un usuario.';
                }
                break;
            case 'nivel':
                if (!in_array((string) ($params['nivel'] ?? ''), self::NIVELES_VALIDOS, true)) {
                    $errores[] = 'Debe indicar el nivel de destinatarios (administradores, usuarios o ambos).';
                }
                break;
            case 'empresa':
                if ((int) ($params['id_empresa'] ?? 0) <= 0) {
                    $errores[] = 'Debe seleccionar la empresa.';
                }
                break;
        }

        return $errores;
    }
}

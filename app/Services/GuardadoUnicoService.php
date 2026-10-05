<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\GuardadoFormularioRepository;

/**
 * Guardado único por formulario (idempotencia de servidor) — CLAUDE.md §8.
 *
 * El navegador ya evita el doble clic (public/js/anti-doble-envio.js), pero no el reintento
 * cuando la respuesta se perdió: el servidor guardó, la pantalla mostró "Error de conexión" y
 * el usuario vuelve a pulsar Guardar. Para eso, cada formulario de documento NUEVO genera una
 * clave (`CMG_nuevoTokenGuardado()`) que viaja como `token_guardado` en todos sus intentos, y
 * el Service, DENTRO de su transacción, hace:
 *
 *     $guardado = new GuardadoUnicoService();
 *     $db->beginTransaction();
 *     if ($previo = $guardado->previo($token, $idEmpresa, 'pedidos')) {   // toma el candado
 *         $db->rollBack();
 *         return (int) $previo['id_registro'];                            // el ya creado
 *     }
 *     ... crear el documento ...
 *     $guardado->registrar($token, $idEmpresa, 'pedidos', $id, $numero, $idUsuario);
 *     $db->commit();
 *
 * `previo()` va ANTES de cualquier otra validación o candado del guardado (saldo, stock,
 * secuencial): si no, un reintento chocaría con "ya consignado/ya en uso" en vez de devolver el
 * documento creado. `registrar()` va en la misma transacción que el documento: si el guardado
 * se revierte, la clave tampoco queda.
 *
 * Sin clave (formularios antiguos, edición de un documento existente) o sin la tabla aún
 * (database/20261005_guardados_formulario.sql no aplicado) no hace nada: el guardado sigue
 * como siempre.
 */
class GuardadoUnicoService
{
    private GuardadoFormularioRepository $repo;

    public function __construct(?GuardadoFormularioRepository $repo = null)
    {
        $this->repo = $repo ?? new GuardadoFormularioRepository();
    }

    /** Clave saneada ('' si no sirve): solo letras, números y guiones, hasta 64. */
    public static function normalizar($token): string
    {
        $t = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($token ?? ''));
        return (strlen($t) >= 8 && strlen($t) <= 64) ? $t : '';
    }

    private function activo(string $token): bool
    {
        return $token !== '' && $this->repo->disponible();
    }

    /**
     * Toma el candado de la clave y devuelve el documento que ya se creó con ella
     * (`id_registro`, `numero`, `respuesta` decodificada) o null. Llamar dentro de la transacción.
     */
    public function previo($token, int $idEmpresa, string $modulo): ?array
    {
        $token = self::normalizar($token);
        if (!$this->activo($token)) {
            return null;
        }
        $this->repo->lock($idEmpresa, $modulo, $token);
        $row = $this->repo->buscar($idEmpresa, $modulo, $token);
        if (!$row) {
            return null;
        }
        $row['id_registro'] = (int) $row['id_registro'];
        $row['respuesta'] = $row['respuesta'] !== null ? json_decode((string) $row['respuesta'], true) : null;
        return $row;
    }

    /** Anota qué documento creó la clave. Misma transacción que el documento. */
    public function registrar($token, int $idEmpresa, string $modulo, int $idRegistro, ?string $numero, int $idUsuario, ?array $respuesta = null): void
    {
        $token = self::normalizar($token);
        if (!$this->activo($token)) {
            return;
        }
        $this->repo->insertar(
            $idEmpresa, $modulo, $token, $idRegistro, $numero,
            $respuesta !== null ? json_encode($respuesta, JSON_UNESCAPED_UNICODE) : null,
            $idUsuario
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\core\Database;

/**
 * Registro central de errores del sistema en la tabla `errores_sistema`.
 *
 * Regla de oro: registrar un error NUNCA debe lanzar otro error. Cada método
 * envuelve todo en try/catch y, si ni siquiera puede escribir en la BD (p. ej.
 * el propio error era de conexión), cae a error_log() y sigue. La petición que
 * ya venía fallando no se rompe más por intentar registrarla.
 */
class ErrorLogService
{
    /** Evita registrar dos veces el mismo error (excepción capturada + shutdown). */
    private static array $huellas = [];

    /**
     * Registra una excepción/throwable capturada en un catch.
     *
     * @param \Throwable $e
     * @param array $ctx  ['ruta' => ..., 'accion' => ..., 'tipo' => 'excepcion'|'manual']
     */
    public static function registrar(\Throwable $e, array $ctx = []): void
    {
        try {
            $sqlState = null;
            // PDOException expone el SQLSTATE en errorInfo[0] o en el code.
            if ($e instanceof \PDOException) {
                $sqlState = is_array($e->errorInfo ?? null) ? ($e->errorInfo[0] ?? null) : (string) $e->getCode();
            }

            self::guardar([
                'tipo'      => $ctx['tipo']   ?? 'excepcion',
                'clase'     => get_class($e),
                'mensaje'   => $e->getMessage(),
                'sql_state' => $sqlState,
                'archivo'   => $e->getFile(),
                'linea'     => $e->getLine(),
                'ruta'      => $ctx['ruta']   ?? null,
                'accion'    => $ctx['accion'] ?? null,
                'traza'     => $e->getTraceAsString(),
            ]);
        } catch (\Throwable $propio) {
            self::fallback($e->getMessage(), $propio);
        }
    }

    /**
     * Registra un error que NO es una excepción (una condición de fallo detectada
     * a mano). Ej.: un correo que no se pudo enviar por configuración/SMTP.
     *
     * @param string $mensaje
     * @param array  $ctx  ['ruta','accion','clase','tipo'=>'manual','sql_state']
     */
    public static function registrarManual(string $mensaje, array $ctx = []): void
    {
        try {
            self::guardar([
                'tipo'      => $ctx['tipo']      ?? 'manual',
                'clase'     => $ctx['clase']     ?? null,
                'mensaje'   => $mensaje,
                'sql_state' => $ctx['sql_state'] ?? null,
                'archivo'   => $ctx['archivo']   ?? null,
                'linea'     => $ctx['linea']     ?? null,
                'ruta'      => $ctx['ruta']      ?? null,
                'accion'    => $ctx['accion']    ?? null,
                'traza'     => null,
            ]);
        } catch (\Throwable $propio) {
            self::fallback($mensaje, $propio);
        }
    }

    /**
     * Registra los manejadores globales. Se llama una vez por request desde
     * Application::run(). Solo usa register_shutdown_function: captura los errores
     * FATALES y las excepciones NO capturadas (que PHP convierte en fatal al morir)
     * SIN alterar cómo se muestran — es puro registro post-mortem.
     */
    public static function registrarHandlersGlobales(): void
    {
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err === null) {
                return;
            }
            $fatales = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
            if (!((int) $err['type'] & $fatales)) {
                return; // warnings/notices no se registran
            }
            try {
                self::guardar([
                    'tipo'      => 'fatal',
                    'clase'     => self::nombreTipoError((int) $err['type']),
                    'mensaje'   => $err['message'] ?? '',
                    'sql_state' => self::sqlStateDesdeMensaje($err['message'] ?? ''),
                    'archivo'   => $err['file'] ?? null,
                    'linea'     => $err['line'] ?? null,
                    'ruta'      => null,
                    'accion'    => null,
                    'traza'     => null, // el fatal ya trae la traza embebida en el mensaje
                ]);
            } catch (\Throwable $propio) {
                self::fallback($err['message'] ?? '', $propio);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function guardar(array $d): void
    {
        // Dedupe dentro del mismo request (misma clase+archivo+línea+mensaje).
        $huella = md5(($d['clase'] ?? '') . '|' . ($d['archivo'] ?? '') . '|' . ($d['linea'] ?? '') . '|' . ($d['mensaje'] ?? ''));
        if (isset(self::$huellas[$huella])) {
            return;
        }
        self::$huellas[$huella] = true;

        $db = Database::getConnection();
        $sql = "INSERT INTO errores_sistema
                    (id_empresa, id_usuario, tipo, clase, mensaje, sql_state, archivo, linea,
                     ruta, accion, url, metodo_http, ip_usuario, user_agent, traza)
                VALUES
                    (:emp, :usr, :tipo, :clase, :msg, :sqlstate, :arch, :linea,
                     :ruta, :accion, :url, :metodo, :ip, :ua, :traza)";
        $st = $db->prepare($sql);
        $st->execute([
            ':emp'      => !empty($_SESSION['id_empresa']) ? (int) $_SESSION['id_empresa'] : null,
            ':usr'      => !empty($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null,
            ':tipo'     => substr((string) ($d['tipo'] ?? 'excepcion'), 0, 20),
            ':clase'    => $d['clase'] !== null ? substr((string) $d['clase'], 0, 255) : null,
            ':msg'      => (string) ($d['mensaje'] ?? ''),
            ':sqlstate' => $d['sql_state'] !== null ? substr((string) $d['sql_state'], 0, 12) : null,
            ':arch'     => $d['archivo'] ?? null,
            ':linea'    => isset($d['linea']) ? (int) $d['linea'] : null,
            ':ruta'     => $d['ruta'] !== null ? substr((string) $d['ruta'], 0, 255) : null,
            ':accion'   => $d['accion'] !== null ? substr((string) $d['accion'], 0, 150) : null,
            ':url'      => $_SERVER['REQUEST_URI'] ?? null,
            ':metodo'   => isset($_SERVER['REQUEST_METHOD']) ? substr((string) $_SERVER['REQUEST_METHOD'], 0, 10) : null,
            ':ip'       => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 50),
            ':ua'       => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            ':traza'    => $d['traza'] ?? null,
        ]);
    }

    /** Última red: si no se pudo escribir en BD, al menos queda en el log de PHP. */
    private static function fallback(string $mensajeOriginal, \Throwable $propio): void
    {
        error_log('[ErrorLogService] No se pudo registrar el error en BD: ' . $propio->getMessage()
            . ' | Error original: ' . $mensajeOriginal);
    }

    private static function nombreTipoError(int $tipo): string
    {
        return match ($tipo) {
            E_ERROR             => 'E_ERROR',
            E_PARSE             => 'E_PARSE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            default             => 'FATAL',
        };
    }

    /** Extrae el SQLSTATE (p. ej. 22P02) del mensaje de un fatal de PDO. */
    private static function sqlStateDesdeMensaje(string $mensaje): ?string
    {
        if (preg_match('/SQLSTATE\[([0-9A-Z]+)\]/', $mensaje, $m)) {
            return $m[1];
        }
        return null;
    }
}

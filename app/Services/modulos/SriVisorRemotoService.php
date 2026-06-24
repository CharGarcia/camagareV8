<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Gestiona la sesión del "visor remoto" usado por la descarga ASISTIDA del SRI.
 *
 * Arquitectura (ver deploy/sri-visor/README):
 *   navegador → nginx (/sri-visor-ws/, valida token) → websockify(127.0.0.1:6080)
 *            → x11vnc(:5900) → Xvfb(:99) → Chromium del scraper en modo asistido.
 *
 * Este servicio NO arranca Xvfb/x11vnc/websockify (eso lo hacen servicios systemd del SO).
 * Solo administra:
 *   - un TOKEN efímero que nginx valida (auth_request) antes de abrir el WebSocket,
 *   - un LOCK de 1 sesión concurrente (el display :99 es único),
 *   - el cierre/expiración de la sesión.
 *
 * El token se persiste en un archivo JSON (no requiere tabla); como solo hay una sesión
 * simultánea, es suficiente y simple.
 */
class SriVisorRemotoService
{
    /** Vida de la sesión del visor (segundos). Tras esto, el token deja de ser válido. */
    public const SESION_TTL = 900; // 15 min

    /** Ruta del WebSocket que expone nginx hacia websockify. */
    public const WS_PATH = '/sri-visor-ws/';

    private function dirSesiones(): string
    {
        $dir = MVC_ROOT . '/storage/sri_visor';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function rutaSesion(): string
    {
        return $this->dirSesiones() . '/sesion.json';
    }

    /**
     * Devuelve la sesión activa (no expirada) o null. Si encontró una expirada, la borra.
     */
    public function getSesionActiva(): ?array
    {
        $ruta = $this->rutaSesion();
        if (!is_file($ruta)) return null;

        $data = json_decode((string) @file_get_contents($ruta), true);
        if (!is_array($data) || empty($data['expira_en'])) {
            @unlink($ruta);
            return null;
        }

        if (time() >= (int) $data['expira_en']) {
            @unlink($ruta);
            return null;
        }

        return $data;
    }

    /**
     * Inicia una sesión de visor para la empresa/usuario y devuelve el token y la ruta WS.
     * Si ya hay una sesión activa de OTRO usuario, falla (lock de 1 sesión concurrente).
     * Si la sesión activa es del mismo usuario, la renueva (reutiliza el display).
     *
     * @return array{ok:bool, token?:string, ws_path?:string, expira_en?:int, error?:string}
     */
    public function iniciarSesion(int $idEmpresa, int $idUsuario): array
    {
        $activa = $this->getSesionActiva();
        if ($activa && (int) ($activa['id_usuario'] ?? 0) !== $idUsuario) {
            return [
                'ok'    => false,
                'error' => 'Ya hay una sesión de descarga asistida en curso por otro usuario. '
                         . 'Inténtalo de nuevo en unos minutos.',
            ];
        }

        $token  = bin2hex(random_bytes(32));
        $expira = time() + self::SESION_TTL;

        $sesion = [
            'token'      => $token,
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'creado_en'  => time(),
            'expira_en'  => $expira,
        ];

        if (@file_put_contents($this->rutaSesion(), json_encode($sesion), LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'No se pudo registrar la sesión del visor (permisos de storage/).'];
        }

        return [
            'ok'        => true,
            'token'     => $token,
            'ws_path'   => self::WS_PATH,
            'expira_en' => $expira,
        ];
    }

    /**
     * Valida un token contra la sesión activa. Lo usa nginx (auth_request) antes de
     * abrir el WebSocket del visor. Devuelve true solo si coincide y no expiró.
     */
    public function validarToken(string $token): bool
    {
        if ($token === '') return false;
        $activa = $this->getSesionActiva();
        return $activa !== null && hash_equals((string) $activa['token'], $token);
    }

    /** Cierra/borra la sesión activa (al terminar la descarga o por cancelación). */
    public function cerrarSesion(): void
    {
        $ruta = $this->rutaSesion();
        if (is_file($ruta)) @unlink($ruta);
    }

    /**
     * Comprobación defensiva de infraestructura en Linux: que el display :99 (Xvfb) y
     * websockify estén corriendo. Da un mensaje claro si la infra no está levantada.
     * En Windows (desarrollo) no aplica y devuelve ok=true.
     *
     * @return array{ok:bool, detalle:string}
     */
    public function verificarInfra(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['ok' => true, 'detalle' => 'Windows: visor remoto no aplica en local.'];
        }

        $faltan = [];
        // Xvfb en el display :99
        @exec('pgrep -f "Xvfb :99" 2>/dev/null', $o1, $r1);
        if ($r1 !== 0 || empty($o1)) $faltan[] = 'Xvfb :99';
        // websockify
        @exec('pgrep -f websockify 2>/dev/null', $o2, $r2);
        if ($r2 !== 0 || empty($o2)) $faltan[] = 'websockify';

        if ($faltan) {
            return [
                'ok'      => false,
                'detalle' => 'Infraestructura del visor no disponible: ' . implode(', ', $faltan)
                           . '. Inicia los servicios (ver deploy/sri-visor).',
            ];
        }
        return ['ok' => true, 'detalle' => 'Infraestructura del visor OK.'];
    }
}

<?php
/**
 * Service: freno de fuerza bruta en el inicio de sesión.
 *
 * El sistema admite contraseñas de 4 caracteres, así que el bloqueo por intentos
 * es la defensa principal contra el adivinado de credenciales: sin él, probar
 * todas las combinaciones cortas es cuestión de minutos. Con 5 intentos por cada
 * 15 minutos, el mismo ataque pasa a ser inviable.
 *
 * Dos frenos independientes:
 *   - Por identificador (la cédula tecleada): frena el ataque contra una cuenta.
 *   - Por IP: frena el ataque que va rotando cédulas desde el mismo origen.
 *
 * Tolerante a que la tabla aún no exista: si algo falla en la BD, el login sigue
 * funcionando (nunca dejar a los clientes fuera por culpa del freno).
 */

declare(strict_types=1);

namespace App\Services;

use App\repositories\LoginIntentoRepository;

class LoginRateLimitService
{
    private ?LoginIntentoRepository $repo = null;
    private array $cfg;

    public function __construct()
    {
        $app = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $login = $app['security']['login'] ?? [];

        $this->cfg = [
            'activo'               => (bool) ($login['activo']               ?? true),
            'max_intentos_usuario' => (int)  ($login['max_intentos_usuario'] ?? 5),
            'ventana_minutos'      => (int)  ($login['ventana_minutos']      ?? 15),
            'bloqueo_minutos'      => (int)  ($login['bloqueo_minutos']      ?? 15),
            'max_intentos_ip'      => (int)  ($login['max_intentos_ip']      ?? 20),
            'bloqueo_ip_minutos'   => (int)  ($login['bloqueo_ip_minutos']   ?? 30),
            'dias_retencion'       => (int)  ($login['dias_retencion']       ?? 90),
        ];

        try {
            $this->repo = new LoginIntentoRepository();
        } catch (\Throwable $e) {
            $this->repo = null;   // sin BD, el freno queda inactivo
        }
    }

    /**
     * ¿Debe rechazarse este intento antes siquiera de comprobar la contraseña?
     *
     * @return array|null null si puede continuar; si está bloqueado:
     *                    ['motivo' => 'usuario'|'ip', 'minutos' => int]
     */
    public function bloqueo(string $identificador): ?array
    {
        if (!$this->cfg['activo'] || $this->repo === null || $identificador === '') {
            return null;
        }

        try {
            $ip = $this->ip();

            $fallosUsuario = $this->repo->contarFallosIdentificador($identificador, $this->cfg['ventana_minutos']);
            if ($fallosUsuario >= $this->cfg['max_intentos_usuario']) {
                $restan = $this->minutosRestantes(
                    $this->repo->ultimoFalloIdentificador($identificador),
                    $this->cfg['bloqueo_minutos']
                );
                if ($restan > 0) {
                    return ['motivo' => 'usuario', 'minutos' => $restan];
                }
            }

            $fallosIp = $this->repo->contarFallosIp($ip, $this->cfg['ventana_minutos']);
            if ($fallosIp >= $this->cfg['max_intentos_ip']) {
                $restan = $this->minutosRestantes(
                    $this->repo->ultimoFalloIp($ip),
                    $this->cfg['bloqueo_ip_minutos']
                );
                if ($restan > 0) {
                    return ['motivo' => 'ip', 'minutos' => $restan];
                }
            }
        } catch (\Throwable $e) {
            return null;   // la tabla no existe todavía o la BD falló: no bloquear
        }

        return null;
    }

    /** Registra un intento fallido. */
    public function registrarFallo(string $identificador): void
    {
        $this->registrar($identificador, false);
    }

    /**
     * Registra un acceso correcto. Además de auditar, el conteo de fallos se
     * calcula desde el último éxito, así que esto pone el contador a cero.
     */
    public function registrarExito(string $identificador): void
    {
        $this->registrar($identificador, true);
        $this->purgarDeVezEnCuando();
    }

    /** Mensaje para el usuario. No revela si la cédula existe o no. */
    public function mensajeBloqueo(array $bloqueo): string
    {
        $minutos = max(1, (int) $bloqueo['minutos']);
        $tiempo  = $minutos === 1 ? 'un minuto' : "{$minutos} minutos";

        if (($bloqueo['motivo'] ?? '') === 'ip') {
            return "Se han hecho demasiados intentos fallidos desde esta conexión. "
                 . "Vuelva a intentarlo en {$tiempo}.";
        }
        return "Demasiados intentos fallidos. Por seguridad, el acceso quedó "
             . "bloqueado temporalmente. Vuelva a intentarlo en {$tiempo}.";
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    private function registrar(string $identificador, bool $exitoso): void
    {
        if (!$this->cfg['activo'] || $this->repo === null || $identificador === '') {
            return;
        }
        try {
            $this->repo->registrar(
                $identificador,
                $this->ip(),
                $exitoso,
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (\Throwable $e) {
            // Nunca impedir el login por no poder registrar el intento.
        }
    }

    /**
     * IP del cliente. Se usa REMOTE_ADDR a propósito: las cabeceras tipo
     * X-Forwarded-For las puede falsificar quien llama, y eso permitiría saltarse
     * el bloqueo cambiándolas en cada intento. Es además lo que ya usan
     * SesionActivaService y LogSistemaService.
     */
    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** Minutos que faltan para que expire un bloqueo iniciado en $desde. */
    private function minutosRestantes(?string $desde, int $duracionMinutos): int
    {
        if ($desde === null) {
            return 0;
        }
        $ts = strtotime($desde);
        if ($ts === false) {
            return 0;
        }
        $fin = $ts + ($duracionMinutos * 60);
        $restan = $fin - time();
        return $restan > 0 ? (int) ceil($restan / 60) : 0;
    }

    /** Limpieza ocasional (1 de cada 50 accesos correctos) para no acumular años de registros. */
    private function purgarDeVezEnCuando(): void
    {
        if ($this->repo === null || random_int(1, 50) !== 1) {
            return;
        }
        try {
            $this->repo->purgar($this->cfg['dias_retencion']);
        } catch (\Throwable $e) {
            // irrelevante para el login
        }
    }
}

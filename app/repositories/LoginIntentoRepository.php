<?php
/**
 * Repository: Intentos de inicio de sesión.
 * Registro de accesos (exitosos y fallidos) para frenar la fuerza bruta.
 *
 * Tabla global: no lleva id_empresa (el intento ocurre antes de saber a qué
 * empresa pertenece el usuario, e incluso puede no existir tal usuario).
 */

declare(strict_types=1);

namespace App\repositories;

use PDO;

class LoginIntentoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /** Deja constancia de un intento. $identificador es la cédula tecleada. */
    public function registrar(string $identificador, string $ip, bool $exitoso, string $userAgent = ''): bool
    {
        $sql = "INSERT INTO login_intentos (identificador, ip, exitoso, user_agent, created_at)
                VALUES (:identificador, :ip, :exitoso, :user_agent, NOW())";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':identificador' => mb_substr($identificador, 0, 50),
            ':ip'            => mb_substr($ip, 0, 45),
            ':exitoso'       => $exitoso ? 'true' : 'false',
            ':user_agent'    => mb_substr($userAgent, 0, 500),
        ]);
    }

    /**
     * Fallos de un identificador desde el último acceso correcto (o dentro de la
     * ventana, lo que sea más reciente). Contar desde el último éxito evita que
     * un usuario que se equivocó ayer arrastre ese saldo hoy.
     */
    public function contarFallosIdentificador(string $identificador, int $ventanaMinutos): int
    {
        $sql = "SELECT COUNT(*) FROM login_intentos
                 WHERE identificador = :identificador
                   AND exitoso = FALSE
                   AND created_at > GREATEST(
                         NOW() - (:ventana || ' minutes')::interval,
                         COALESCE((SELECT MAX(created_at) FROM login_intentos
                                    WHERE identificador = :identificador2 AND exitoso = TRUE),
                                  TIMESTAMP '1970-01-01')
                       )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':identificador'  => $identificador,
            ':identificador2' => $identificador,
            ':ventana'        => (string) $ventanaMinutos,
        ]);
        return (int) $st->fetchColumn();
    }

    /** Fallos de una IP dentro de la ventana (ataque que rota cédulas). */
    public function contarFallosIp(string $ip, int $ventanaMinutos): int
    {
        $sql = "SELECT COUNT(*) FROM login_intentos
                 WHERE ip = :ip
                   AND exitoso = FALSE
                   AND created_at > NOW() - (:ventana || ' minutes')::interval";
        $st = $this->db->prepare($sql);
        $st->execute([':ip' => $ip, ':ventana' => (string) $ventanaMinutos]);
        return (int) $st->fetchColumn();
    }

    /** Momento del último fallo del identificador (para calcular cuánto falta). */
    public function ultimoFalloIdentificador(string $identificador): ?string
    {
        $st = $this->db->prepare(
            "SELECT MAX(created_at) FROM login_intentos
              WHERE identificador = :identificador AND exitoso = FALSE"
        );
        $st->execute([':identificador' => $identificador]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Momento del último fallo de la IP. */
    public function ultimoFalloIp(string $ip): ?string
    {
        $st = $this->db->prepare(
            "SELECT MAX(created_at) FROM login_intentos WHERE ip = :ip AND exitoso = FALSE"
        );
        $st->execute([':ip' => $ip]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Purga de registros antiguos (se invoca de forma esporádica desde el service). */
    public function purgar(int $diasRetencion = 90): int
    {
        $st = $this->db->prepare(
            "DELETE FROM login_intentos WHERE created_at < NOW() - (:dias || ' days')::interval"
        );
        $st->execute([':dias' => (string) $diasRetencion]);
        return $st->rowCount();
    }
}

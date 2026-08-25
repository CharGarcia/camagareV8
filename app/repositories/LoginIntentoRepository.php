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

use App\Helpers\FiltrosBusqueda;
use PDO;

class LoginIntentoRepository
{
    /** Columnas de ordenamiento permitidas (whitelist) para el listado de consulta. */
    private const MAPA_ORDEN = [
        'created_at'    => 'created_at',
        'identificador' => 'identificador',
        'ip'            => 'ip',
        'exitoso'       => 'exitoso',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /**
     * Listado paginado de intentos de login, para la pestaña de consulta en
     * Auditoría del sistema (solo nivel 3: la tabla es global, sin id_empresa).
     *
     * @param array{exitoso:?bool,desde:?string,hasta:?string} $filtros
     * @return array{rows: array, total: int}
     */
    public function getListado(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, array $filtros = []): array
    {
        [$where, $params] = $this->construirWhere($buscar, $filtros);

        $stCount = $this->db->prepare("SELECT COUNT(*) FROM login_intentos {$where}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $colSql = self::MAPA_ORDEN[$ordenCol] ?? 'created_at';
        $dirSql = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        $sql = "SELECT id, identificador, ip, exitoso, user_agent, created_at
                FROM login_intentos
                {$where}
                ORDER BY {$colSql} {$dirSql}, id {$dirSql}
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Arma el WHERE: filtros explícitos (dropdown resultado/fechas) + búsqueda
     * por texto libre y tokens (identificador:, ip:, resultado:, fecha:).
     *
     * @param array{exitoso:?bool,desde:?string,hasta:?string} $filtros
     * @return array{0:string,1:array}
     */
    private function construirWhere(string $buscar, array $filtros): array
    {
        $where  = 'WHERE 1=1';
        $params = [];
        $tieneFechaExplicita = false;

        if (array_key_exists('exitoso', $filtros) && $filtros['exitoso'] !== null) {
            $where .= ' AND exitoso = :f_exitoso';
            $params[':f_exitoso'] = $filtros['exitoso'] ? 'true' : 'false';
        }
        if (!empty($filtros['desde'])) {
            $where .= ' AND created_at >= :f_desde';
            $params[':f_desde'] = $filtros['desde'] . ' 00:00:00';
            $tieneFechaExplicita = true;
        }
        if (!empty($filtros['hasta'])) {
            $where .= ' AND created_at <= :f_hasta';
            $params[':f_hasta'] = $filtros['hasta'] . ' 23:59:59';
            $tieneFechaExplicita = true;
        }

        $parsed = FiltrosBusqueda::parsear($buscar);

        // "resultado:exitoso" / "resultado:fallido": valor de texto, no columna
        // ILIKE/exacta genérica, así que se traduce a mano antes de aplicarFiltros.
        if (isset($parsed['filtros']['resultado'])) {
            $f = $parsed['filtros']['resultado'];
            $valor = is_array($f['valor']) ? ($f['valor'][0] ?? '') : $f['valor'];
            $esExitoso = in_array(mb_strtolower((string) $valor, 'UTF-8'), ['exitoso', 'exito', 'éxito', 'ok'], true);
            $where .= ($f['neg'] ? ' AND exitoso != :f_res' : ' AND exitoso = :f_res');
            $params[':f_res'] = $esExitoso ? 'true' : 'false';
            unset($parsed['filtros']['resultado']);
        }

        if ($parsed['texto_libre'] !== '') {
            $condicion = FiltrosBusqueda::condicionTexto(
                ['identificador', 'ip'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'identificador' => 'identificador',
                'ip'            => 'ip',
            ],
            'fecha' => [
                'fecha' => 'created_at',
            ],
        ]);

        // Igual que la bitácora general: sin filtro de fecha explícito, últimos 30 días.
        if (!isset($parsed['filtros']['fecha']) && !$tieneFechaExplicita) {
            $where .= " AND created_at >= (CURRENT_DATE - INTERVAL '30 days')";
        }

        return [$where, $params];
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
                   AND exitoso = FALSE" . $this->condVigentes() . "
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
                   AND exitoso = FALSE" . $this->condVigentes() . "
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
              WHERE identificador = :identificador AND exitoso = FALSE" . $this->condVigentes()
        );
        $st->execute([':identificador' => $identificador]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Momento del último fallo de la IP. */
    public function ultimoFalloIp(string $ip): ?string
    {
        $st = $this->db->prepare(
            "SELECT MAX(created_at) FROM login_intentos WHERE ip = :ip AND exitoso = FALSE" . $this->condVigentes()
        );
        $st->execute([':ip' => $ip]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /**
     * Fecha del último acceso correcto del identificador (informativo para la
     * ficha del usuario; el conteo de fallos ya arranca desde este momento).
     */
    public function ultimoExitoIdentificador(string $identificador): ?string
    {
        $st = $this->db->prepare(
            "SELECT MAX(created_at) FROM login_intentos
              WHERE identificador = :identificador AND exitoso = TRUE"
        );
        $st->execute([':identificador' => $identificador]);
        $v = $st->fetchColumn();
        return $v ? (string) $v : null;
    }

    /**
     * Reinicia el contador de un identificador: marca sus intentos fallidos
     * vigentes como anulados, dejando constancia de quién lo hizo y cuándo.
     *
     * No se borra nada a propósito: la misma tabla alimenta la auditoría de
     * accesos, así que el intento sigue visible; solo deja de sumar al bloqueo.
     *
     * @return int Cuántos intentos dejaron de contar.
     * @throws \RuntimeException Si falta la migración que agregó la columna.
     */
    public function anularFallosIdentificador(string $identificador, int $idUsuario): int
    {
        if (!$this->tieneColumnaAnulado()) {
            throw new \RuntimeException(
                'Falta aplicar la migración database/migrations/20260825_login_intentos_anulado.sql '
                . 'para poder reiniciar los intentos de acceso.'
            );
        }

        $st = $this->db->prepare(
            "UPDATE login_intentos
                SET anulado = TRUE, anulado_at = NOW(), anulado_por = :id_usuario
              WHERE identificador = :identificador
                AND exitoso = FALSE
                AND anulado = FALSE"
        );
        $st->execute([
            ':identificador' => $identificador,
            ':id_usuario'    => $idUsuario > 0 ? $idUsuario : null,
        ]);
        return $st->rowCount();
    }

    /**
     * Fragmento que descarta los intentos anulados por un reinicio manual.
     * Devuelve cadena vacía mientras la columna no exista, para que el freno
     * siga funcionando en instalaciones sin la migración aplicada.
     */
    private function condVigentes(): string
    {
        return $this->tieneColumnaAnulado() ? ' AND anulado = FALSE' : '';
    }

    /** ¿La instalación ya tiene la columna `anulado`? (se resuelve una sola vez). */
    private function tieneColumnaAnulado(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $st = $this->db->query(
                    "SELECT 1 FROM information_schema.columns
                      WHERE table_name = 'login_intentos' AND column_name = 'anulado' LIMIT 1"
                );
                $existe = (bool) $st->fetchColumn();
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
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

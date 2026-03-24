<?php
/**
 * Modelo CorreoConfig - CRUD de configuraciones de correo por propósito
 * Tabla: correos_config
 * Propósitos: recuperar_password, notificaciones, cobros, etc.
 * Almacena SMTP completo por cada propósito. La contraseña se guarda (considerar cifrado en producción).
 */

declare(strict_types=1);

namespace App\models;

class CorreoConfig extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'email', 'status'];

    /** Códigos predefinidos que la aplicación puede usar */
    public const CODIGOS_SUGERIDOS = [
        'recuperar_password' => 'Recuperación de contraseña',
        'notificaciones' => 'Notificaciones del sistema',
        'cobros' => 'Cobros y facturación',
        'soporte' => 'Soporte técnico',
    ];

    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%' OR email LIKE '%{$b}%')";
        }
        // No incluir password_smtp en el listado por seguridad
        $queries = [
            "SELECT id_correo_config AS id, codigo, nombre, email, nombre_remitente, host_smtp, puerto_smtp, usuario_smtp, encryption, status FROM correos_config{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, email, nombre_remitente, host_smtp, puerto_smtp, usuario_smtp, encryption, status FROM correos_config{$where} ORDER BY {$col} {$dir}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_correo_config != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM correos_config WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM correos_config WHERE codigo = '{$cod}'{$excluirAlt}",
        ];
        foreach ($queries as $sql) {
            try {
                return !empty($this->query($sql));
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Obtiene la configuración por código (para uso en envío de correos)
     */
    public function getPorCodigo(string $codigo): ?array
    {
        $cod = $this->escape(trim($codigo));
        $queries = [
            "SELECT id_correo_config AS id, codigo, nombre, email, nombre_remitente, host_smtp, puerto_smtp, usuario_smtp, password_smtp, encryption FROM correos_config WHERE codigo = '{$cod}' AND status = 1 LIMIT 1",
            "SELECT id, codigo, nombre, email, nombre_remitente, host_smtp, puerto_smtp, usuario_smtp, password_smtp, encryption FROM correos_config WHERE codigo = '{$cod}' AND status = 1 LIMIT 1",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                return $rows[0] ?? null;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM correos_config WHERE id_correo_config = {$id}",
            "DELETE FROM correos_config WHERE id = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    public function crear(
        string $codigo,
        string $nombre,
        string $email,
        string $nombreRemitente,
        string $hostSmtp,
        int $puertoSmtp,
        string $usuarioSmtp,
        string $passwordSmtp,
        string $encryption,
        int $status
    ): int {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $em = $this->escape($email);
        $nr = $this->escape($nombreRemitente);
        $host = $this->escape($hostSmtp);
        $user = $this->escape($usuarioSmtp);
        $pass = $this->escape($passwordSmtp);
        $enc = in_array($encryption, ['tls', 'ssl', ''], true) ? $this->escape($encryption) : 'tls';
        $puerto = (int) $puertoSmtp;
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO correos_config (codigo, nombre, email, nombre_remitente, host_smtp, puerto_smtp, usuario_smtp, password_smtp, encryption, status) " .
            "VALUES ('{$cod}', '{$nom}', '{$em}', '{$nr}', '{$host}', {$puerto}, '{$user}', '{$pass}', '{$enc}', {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    public function actualizar(
        int $id,
        string $codigo,
        string $nombre,
        string $email,
        string $nombreRemitente,
        string $hostSmtp,
        int $puertoSmtp,
        string $usuarioSmtp,
        ?string $passwordSmtp,
        string $encryption,
        int $status
    ): bool {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $em = $this->escape($email);
        $nr = $this->escape($nombreRemitente);
        $host = $this->escape($hostSmtp);
        $user = $this->escape($usuarioSmtp);
        $enc = in_array($encryption, ['tls', 'ssl', ''], true) ? $this->escape($encryption) : 'tls';
        $puerto = (int) $puertoSmtp;
        $st = $status ? 1 : 0;
        $passClause = $passwordSmtp !== null && $passwordSmtp !== ''
            ? ", password_smtp = '" . $this->escape($passwordSmtp) . "'"
            : '';
        $queries = [
            "UPDATE correos_config SET codigo='{$cod}', nombre='{$nom}', email='{$em}', nombre_remitente='{$nr}', host_smtp='{$host}', puerto_smtp={$puerto}, usuario_smtp='{$user}'{$passClause}, encryption='{$enc}', status={$st} WHERE id_correo_config={$id}",
            "UPDATE correos_config SET codigo='{$cod}', nombre='{$nom}', email='{$em}', nombre_remitente='{$nr}', host_smtp='{$host}', puerto_smtp={$puerto}, usuario_smtp='{$user}'{$passClause}, encryption='{$enc}', status={$st} WHERE id={$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}

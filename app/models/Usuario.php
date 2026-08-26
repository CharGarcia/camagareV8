<?php
/**
 * Modelo Usuario - Gestión de usuarios
 */

declare(strict_types=1);

namespace App\models;

class Usuario extends BaseModel
{
    /**
     * Los booleanos de Postgres llegan por PDO como 't'/'f' (texto), no como bool
     * nativo de PHP — (bool)'f' sería true por error. Normaliza cualquier variante.
     */
    private static function pgBool(mixed $v): bool
    {
        return $v === true || $v === 't' || $v === '1' || $v === 1 || $v === 'true';
    }

    /**
     * Expresión SQL que responde "¿este usuario ya completó su registro?".
     * Única en todo el módulo para que el badge del listado, el buscador, la ficha
     * y el reenvío de invitación no puedan contradecirse entre sí.
     *
     * - `registrado` es la fuente de verdad: la marcan los tres caminos por los que
     *   una cuenta queda utilizable (completar la invitación, recuperar la clave y
     *   la clave provisional que fija un administrador).
     * - El resto es un respaldo para filas antiguas en las que esa columna nunca se
     *   escribió: si el usuario ya NO tiene token pendiente y su identificación ya
     *   no es la provisional (el hash de su correo), se le da por registrado.
     *   La condición del token es la que evita el falso positivo que se veía al
     *   editar la ficha de un invitado — cambiarle el correo o escribirle su cédula
     *   real rompía la coincidencia con el hash y el sistema lo daba por activado,
     *   perdiéndose la opción de reenviarle la invitación.
     *
     * @param string $a alias de la tabla `usuarios` en la consulta ('' si no hay).
     */
    private static function sqlRegistrado(string $a = ''): string
    {
        $p = $a === '' ? '' : $a . '.';
        return "({$p}registrado OR (COALESCE({$p}token,'') = ''"
            . " AND COALESCE({$p}cedula,'') <> substr(md5(COALESCE({$p}mail,'')), 1, 15)))";
    }

    /**
     * Valida login por cédula y contraseña.
     * Soporta bcrypt y MD5 (legacy). Si la contraseña está en MD5 y es correcta,
     * la migra automáticamente a bcrypt (el usuario no nota ningún cambio).
     * Usa prepared statements para evitar inyección SQL.
     *
     * @return array{id: int, nombre: string, cedula: string, nivel: int}|false
     */
    public function validaLogin(string $cedula, string $password): array|false
    {
        $cedula = trim($cedula);
        $password = trim($password);
        if ($cedula === '' || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id, nombre, cedula, password, nivel, id_empresa_favorita, puede_app_movil FROM usuarios WHERE cedula = ? AND estado = 1 AND eliminado = false LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->execute([$cedula]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$user || empty($user['password'])) {
            return false;
        }

        $stored = $user['password'];

        // Bcrypt (password_hash): $2y$ o $2a$
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
            if (password_verify($password, $stored)) {
                return ['id' => (int) $user['id'], 'nombre' => $user['nombre'], 'cedula' => $user['cedula'], 'nivel' => (int) $user['nivel'], 'id_empresa_favorita' => $user['id_empresa_favorita'] ? (int)$user['id_empresa_favorita'] : null, 'puede_app_movil' => self::pgBool($user['puede_app_movil'])];
            }
            return false;
        }

        // MD5 legacy (32 hex): validar y migrar a bcrypt
        if (strlen($stored) === 32 && ctype_xdigit($stored) && md5($password) === $stored) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash !== false) {
                $id = (int) $user['id'];
                $upd = $this->db->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
                if ($upd) {
                    $upd->execute([$newHash, $id]);
                }
            }
            return ['id' => (int) $user['id'], 'nombre' => $user['nombre'], 'cedula' => $user['cedula'], 'nivel' => (int) $user['nivel'], 'id_empresa_favorita' => $user['id_empresa_favorita'] ? (int)$user['id_empresa_favorita'] : null, 'puede_app_movil' => self::pgBool($user['puede_app_movil'])];
        }

        return false;
    }

    /**
     * Resuelve el usuario a partir de su token de agente (extensión/agente de escritorio).
     * El token es personal: un solo token sirve para todas las empresas del usuario.
     */
    public function getPorAgenteToken(string $token): ?array
    {
        if ($token === '') return null;
        $st = $this->db->prepare(
            "SELECT id, nombre, nivel FROM usuarios
             WHERE agente_token = ? AND estado = 1 AND eliminado = false LIMIT 1"
        );
        $st->execute([$token]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Guarda (o regenera) el token del agente para un usuario. */
    public function setAgenteToken(int $idUsuario, string $token): bool
    {
        $st = $this->db->prepare("UPDATE usuarios SET agente_token = ? WHERE id = ?");
        return $st->execute([$token, $idUsuario]);
    }

    /** Devuelve el token del agente del usuario, generándolo si aún no tiene (idempotente). */
    public function asegurarAgenteToken(int $idUsuario): string
    {
        $st = $this->db->prepare("SELECT agente_token FROM usuarios WHERE id = ?");
        $st->execute([$idUsuario]);
        $tok = $st->fetchColumn();
        if (is_string($tok) && $tok !== '') return $tok;
        $tok = bin2hex(random_bytes(32));
        $this->setAgenteToken($idUsuario, $tok);
        return $tok;
    }

    /** Marca la empresa que el usuario quiere loguear en el SRI (al pulsar el botón) y la HORA,
     *  para que la marca tenga caducidad. */
    public function setLoginPendiente(int $idUsuario, int $idEmpresa): bool
    {
        $st = $this->db->prepare("UPDATE usuarios SET login_pendiente_empresa = ?, login_pendiente_at = NOW() WHERE id = ?");
        return $st->execute([$idEmpresa, $idUsuario]);
    }

    /** Lee la empresa pendiente de login y la limpia (uso único). Devuelve null si no hay. */
    public function tomarLoginPendiente(int $idUsuario): ?int
    {
        $st = $this->db->prepare("SELECT login_pendiente_empresa FROM usuarios WHERE id = ?");
        $st->execute([$idUsuario]);
        $id = $st->fetchColumn();
        if ($id === false || $id === null) return null;

        $up = $this->db->prepare("UPDATE usuarios SET login_pendiente_empresa = NULL WHERE id = ?");
        $up->execute([$idUsuario]);
        return (int) $id;
    }

    /**
     * Lee la empresa marcada SÓLO si sigue VIGENTE (marcada en los últimos $ventanaMin minutos).
     * Fuera de esa ventana devuelve null. Se usa con DOS ventanas distintas:
     *   - LOGIN automático (agenteLoginPendienteAjax): ventana MUY corta, para que la extensión
     *     inicie sesión en el SRI solo justo después de pulsar "Generar descarga del SRI" y NO cada
     *     vez que el usuario abre el portal por su cuenta.
     *   - REGISTRO de claves (agenteRegistrarClavesAjax): ventana amplia, porque registrar requiere
     *     que el usuario pulse "Enviar comprobantes" (acción deliberada, sin riesgo de seguridad) y
     *     puede querer enviar varios períodos de la misma empresa.
     */
    public function getLoginPendiente(int $idUsuario, int $ventanaMin = 30): ?int
    {
        $st = $this->db->prepare(
            "SELECT login_pendiente_empresa FROM usuarios
             WHERE id = ?
               AND login_pendiente_empresa IS NOT NULL
               AND login_pendiente_at IS NOT NULL
               AND login_pendiente_at > (NOW() - ((?)::int * INTERVAL '1 minute'))"
        );
        $st->execute([$idUsuario, $ventanaMin]);
        $id = $st->fetchColumn();
        return ($id === false || $id === null) ? null : (int) $id;
    }

    /** Limpia la marca de empresa pendiente (tras registrar las claves). */
    public function limpiarLoginPendiente(int $idUsuario): void
    {
        $up = $this->db->prepare("UPDATE usuarios SET login_pendiente_empresa = NULL WHERE id = ?");
        $up->execute([$idUsuario]);
    }

    /**
     * Apaga el INGRESO AUTOMÁTICO al SRI sin cortar el registro de claves: envejece la marca 10 min
     * (valor entre la ventana corta del login [5 min] y la amplia del registro [180 min]) para que
     * la ventana de login quede vencida pero la de registro siga vigente. Se llama justo al entregar
     * las credenciales en agenteLoginPendienteAjax(), para que ese sea el ÚNICO intento de login
     * automático por cada clic en "Generar descarga del SRI": si el SRI rechaza el login y recarga
     * la pantalla, la extensión ya no encuentra marca vigente y no reintenta (evita agotar los 3
     * intentos que permite el SRI antes de bloquear al usuario).
     */
    public function desactivarLoginAuto(int $idUsuario): void
    {
        $up = $this->db->prepare("UPDATE usuarios SET login_pendiente_at = NOW() - INTERVAL '10 minutes' WHERE id = ?");
        $up->execute([$idUsuario]);
    }

    /**
     * Valida contraseña actual (MD5 o bcrypt) y actualiza a nueva contraseña en bcrypt.
     * Si la actual está en MD5 y es correcta, migra transparentemente.
     *
     * @return bool True si se actualizó correctamente
     */
    public function cambiarPassword(int $idUsuario, string $claveActual, string $nuevaClave): bool
    {
        $id = (int) $idUsuario;
        $claveActual = trim($claveActual);
        $nuevaClave = trim($nuevaClave);
        if ($id <= 0 || $claveActual === '' || strlen($nuevaClave) < 4) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT password FROM usuarios WHERE id = ? AND estado = 1 AND eliminado = false LIMIT 1');
        if (!$stmt) return false;
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$row) return false;
        $stored = $row['password'];

        $claveActualValida = false;

        // Bcrypt
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
            $claveActualValida = password_verify($claveActual, $stored);
        } elseif (strlen($stored) === 32 && ctype_xdigit($stored)) {
            $claveActualValida = (md5($claveActual) === $stored);
        }

        if (!$claveActualValida) return false;

        $hash = password_hash($nuevaClave, PASSWORD_DEFAULT);
        if ($hash === false) return false;

        $upd = $this->db->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
        if (!$upd) return false;

        return $upd->execute([$hash, $id]);
    }

    /**
     * Datos de empresas asignadas para el login (numrows, id_empresa, ruc_empresa).
     */
    public function getEmpresasAsignadasParaLogin(int $idUsuario): array
    {
        $id = (int) $idUsuario;
        $r = $this->query("SELECT emp.id AS id_empresa, emp.ruc AS ruc_empresa,
            (SELECT COUNT(*) FROM empresa_asignada ea 
             INNER JOIN empresas e ON e.id = ea.id_empresa 
             INNER JOIN usuarios u ON u.id = ea.id_usuario 
             WHERE ea.id_usuario = {$id} AND e.estado = '1' AND u.estado = '1' AND u.eliminado = false) AS numrows
            FROM empresa_asignada emp_asi 
            INNER JOIN empresas emp ON emp.id = emp_asi.id_empresa 
            INNER JOIN usuarios usu ON usu.id = emp_asi.id_usuario
            WHERE emp_asi.id_usuario = {$id} AND emp.estado = '1' AND usu.estado = '1'
            LIMIT 1");
        if (empty($r)) {
            return ['numrows' => 0];
        }
        $first = $r[0];
        return [
            'numrows' => (int) ($first['numrows'] ?? 0),
            'id_empresa' => (int) ($first['id_empresa'] ?? 0),
            'ruc_empresa' => $first['ruc_empresa'] ?? '',
        ];
    }

    /**
     * Primera empresa asignada (por nombre_comercial) para usuario con múltiples empresas.
     */
    public function getPrimeraEmpresaAsignada(int $idUsuario): ?array
    {
        $id = (int) $idUsuario;
        $r = $this->query("SELECT emp.id AS id_empresa, emp.ruc AS ruc_empresa 
            FROM empresa_asignada emp_asi 
            INNER JOIN empresas emp ON emp.id = emp_asi.id_empresa 
            INNER JOIN usuarios u ON u.id = emp_asi.id_usuario 
            WHERE emp_asi.id_usuario = {$id} AND emp.estado = '1' AND u.estado = '1' AND u.eliminado = false 
            ORDER BY emp.nombre_comercial ASC LIMIT 1");
        if (empty($r)) return null;
        return ['id_empresa' => (int) $r[0]['id_empresa'], 'ruc_empresa' => $r[0]['ruc_empresa']];
    }
    /**
     * Obtiene una empresa específica si está asignada al usuario.
     */
    public function getEmpresaAsignadaEspecifica(int $idUsuario, int $idEmpresa): ?array
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $r = $this->query("SELECT emp.id AS id_empresa, emp.ruc AS ruc_empresa 
            FROM empresa_asignada emp_asi 
            INNER JOIN empresas emp ON emp.id = emp_asi.id_empresa 
            WHERE emp_asi.id_usuario = {$idU} AND emp_asi.id_empresa = {$idE} AND emp.estado = '1' AND emp.eliminado = false 
            LIMIT 1");
        if (empty($r)) return null;
        return ['id_empresa' => (int) $r[0]['id_empresa'], 'ruc_empresa' => $r[0]['ruc_empresa']];
    }

    public function existePorCedula(string $cedula, ?int $excluirId = null): bool
    {
        $c = $this->escape(trim($cedula));
        $sql = "SELECT 1 FROM usuarios WHERE cedula = '{$c}' AND estado = 1 AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }

    /**
     * ¿Esa identificación ya la usa otro usuario del sistema?
     *
     * A diferencia de existePorCedula(), aquí NO se filtra por estado = 1: la
     * cédula es la credencial de ingreso, así que un usuario inactivo también
     * la mantiene ocupada (si se reactiva, dos cuentas responderían al mismo
     * login). Se usa al editar la identificación desde la ficha del usuario.
     */
    public function existeCedulaEnUso(string $cedula, ?int $excluirId = null): bool
    {
        $c = $this->escape(trim($cedula));
        $sql = "SELECT 1 FROM usuarios WHERE cedula = '{$c}' AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }

    public function crear(array $data): int
    {
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $cedula = $this->escape(trim($data['cedula'] ?? ''));
        $password = $data['password'] ?? '';
        $nivel = (int) ($data['nivel'] ?? 1);
        $mail = $this->escape(trim($data['mail'] ?? ''));

        if (strlen($cedula) > 15) {
            throw new \InvalidArgumentException('La identificación no puede superar los 15 caracteres.');
        }

        if ($this->existePorCedula($cedula)) {
            throw new \InvalidArgumentException('Ya existe un usuario con esa cédula.');
        }

        $token = bin2hex(random_bytes(16));
        $hash = password_hash($password, PASSWORD_DEFAULT) ?: md5($password);
        $nivel = max(1, min(3, $nivel));

        $sql = "INSERT INTO usuarios (nombre, cedula, password, nivel, estado, mail, token, telefono) 
                VALUES (:nombre, :cedula, :password, :nivel, 1, :mail, :token, :telefono)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'   => trim($data['nombre'] ?? ''),
            ':cedula'   => trim($cedula),
            ':password' => $hash,
            ':nivel'    => $nivel,
            ':mail'     => trim($data['mail'] ?? ''),
            ':token'    => $token,
            ':telefono' => trim($data['telefono'] ?? '')
        ]);

        return $this->lastInsertId('usuarios_id_seq');
    }

    /**
     * Obtiene usuario activo por correo (para recuperar contraseña).
     */
    public function getUsuarioActivoPorCorreo(string $correo): ?array
    {
        $c = $this->escape(strtolower(trim($correo)));
        if ($c === '') return null;
        $r = $this->query("SELECT id, nombre, mail, nivel FROM usuarios WHERE LOWER(mail) = '{$c}' AND estado = 1 AND eliminado = false LIMIT 1");
        return $r[0] ?? null;
    }

    /**
     * Datos básicos de un usuario activo, para flujos que necesitan el correo real
     * guardado (recuperación de contraseña) y no el que venga del navegador.
     */
    public function getBasicoPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT id, nombre, mail, nivel FROM usuarios
            WHERE id = {$id} AND estado = 1 AND eliminado = false LIMIT 1");
        return $r[0] ?? null;
    }

    /**
     * Cédula y nombre de un usuario para gestionarlo desde la ficha, sin exigir
     * que esté activo: el bloqueo por intentos fallidos hay que poder revisarlo
     * y reiniciarlo también en un usuario inactivo.
     */
    public function getIdentificacionPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT id, nombre, cedula FROM usuarios
            WHERE id = {$id} AND eliminado = false LIMIT 1");
        return $r[0] ?? null;
    }

    /**
     * Fragmento SQL (sobre el alias `u` de `usuarios`) con el criterio de
     * visibilidad de un administrador nivel 2: los usuarios que comparten al
     * menos una empresa con las que él tiene asignadas, los que gestiona
     * directamente en `usuario_asignado` y él mismo.
     *
     * Punto único de verdad del criterio: lo usan el listado
     * (getTodosParaListado) y las acciones sobre un usuario concreto
     * (esGestionablePorAdmin), para que "lo que veo" y "lo que puedo tocar"
     * nunca se desalineen.
     */
    private static function whereVisiblesParaAdmin(int $idAdmin): string
    {
        $idA = (int) $idAdmin;
        return "u.id = {$idA}
                OR EXISTS (
                    SELECT 1 FROM empresa_asignada ea_adm
                    INNER JOIN empresa_asignada ea_otro ON ea_otro.id_empresa = ea_adm.id_empresa
                    WHERE ea_adm.id_usuario = {$idA} AND ea_otro.id_usuario = u.id
                )
                OR EXISTS (
                    SELECT 1 FROM usuario_asignado ua
                    WHERE ua.id_usuario = u.id AND ua.id_adm = {$idA}
                )";
    }

    /**
     * True si ese usuario está bajo la gestión del administrador indicado:
     * comparte empresa con él, lo tiene en `usuario_asignado` o es él mismo.
     * El superadministrador no pasa por aquí: gestiona a todos.
     */
    public function esGestionablePorAdmin(int $idUsuario, int $idAdmin): bool
    {
        $idU = (int) $idUsuario;
        $idA = (int) $idAdmin;
        if ($idU <= 0 || $idA <= 0) return false;
        $r = $this->query("SELECT 1 AS x FROM usuarios u
            WHERE u.id = {$idU} AND u.eliminado = false AND u.nivel < 3
              AND (" . self::whereVisiblesParaAdmin($idA) . ")
            LIMIT 1");
        return !empty($r);
    }
    /**
     * Actualiza el token de recuperación del usuario.
     */
    public function actualizarToken(int $id, string $token): bool
    {
        $id = (int) $id;
        $t = $this->escape($token);
        return $this->execute("UPDATE usuarios SET token = '{$t}' WHERE id = {$id}");
    }

    /**
     * Obtiene usuario por correo y token (validar enlace de recuperación).
     */
    public function getUsuarioPorCorreoYToken(string $correo, string $token): ?array
    {
        $c = $this->escape(strtolower(trim($correo)));
        $t = $this->escape(trim($token));
        if ($c === '' || $t === '') return null;
        $r = $this->query("SELECT id, nombre, mail FROM usuarios WHERE LOWER(mail) = '{$c}' AND token = '{$t}' AND estado = 1 AND eliminado = false LIMIT 1");
        return $r[0] ?? null;
    }

    /**
     * Actualiza la contraseña y borra el token tras recuperación exitosa.
     */
    public function actualizarPasswordPorRecuperacion(int $id, string $token, string $nuevaPassword): bool
    {
        $id = (int) $id;
        $t = $this->escape($token);
        $p = trim($nuevaPassword);
        if ($p === '' || strlen($p) < 4) return false;
        $hash = password_hash($p, PASSWORD_DEFAULT);
        if ($hash === false) $hash = md5($p);
        $h = $this->escape($hash);
        return $this->execute("UPDATE usuarios SET password = '{$h}', token = '', registrado = true WHERE id = {$id} AND token = '{$t}'");
    }

    /**
     * Registra solicitud de recuperación en recuperaciones_clave_usuario.
     */
    public function registrarRecuperacion(int $idUser, string $nombre, string $mail): bool
    {
        $id = (int) $idUser;
        $n = $this->escape($nombre);
        $m = $this->escape($mail);
        return $this->execute("INSERT INTO recuperaciones_clave_usuario (id_user, nombre, mail) VALUES ({$id}, '{$n}', '{$m}')");
    }

    /**
     * Obtiene datos del perfil (sin contraseña) para edición por el propio usuario.
     */
    public function getPerfil(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT id, nombre, cedula, mail, nivel, puede_app_movil FROM usuarios WHERE id = {$id} AND estado = 1 AND eliminado = false LIMIT 1");
        if (!isset($r[0])) return null;
        $r[0]['puede_app_movil'] = self::pgBool($r[0]['puede_app_movil']);
        return $r[0];
    }

    /**
     * Actualiza perfil propio: nombre, cédula, correo. No modifica nivel.
     */
    public function actualizarPerfil(int $id, string $nombre, string $cedula, string $mail): bool
    {
        $id = (int) $id;
        $nombre = trim($nombre);
        $cedula = trim($cedula);
        $mail = trim($mail);

        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if ($cedula === '') {
            throw new \InvalidArgumentException('La cédula es obligatoria.');
        }
        if ($this->existePorCedula($cedula, $id)) {
            throw new \InvalidArgumentException('Ya existe un usuario con esa cédula.');
        }
        if ($mail !== '' && $this->existePorCorreo($mail, $id)) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
        }
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no es válido.');
        }

        $n = $this->escape($nombre);
        $c = $this->escape($cedula);
        $m = $this->escape($mail);
        return $this->execute("UPDATE usuarios SET nombre = '{$n}', cedula = '{$c}', mail = '{$m}' WHERE id = {$id}");
    }

    public function existePorCorreo(string $correo, ?int $excluirId = null): bool
    {
        $c = $this->escape(trim($correo));
        if ($c === '') return false;
        $sql = "SELECT 1 FROM usuarios WHERE mail = '{$c}' AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }

    /**
     * Cuenta super administradores activos (nivel=3, estado=1).
     */
    public function contarSuperAdminActivos(?int $excluirId = null): int
    {
        $excluir = ($excluirId !== null && $excluirId > 0) ? " AND id != " . (int) $excluirId : '';
        $r = $this->query("SELECT COUNT(*) AS n FROM usuarios WHERE nivel = 3 AND estado = 1 AND eliminado = false{$excluir}");
        return (int) ($r[0]['n'] ?? 0);
    }

    /**
     * Actualiza correo, nivel, estado y (opcionalmente) nombre e identificación
     * de un usuario. Valida que ni el correo ni la cédula estén registrados por
     * otro usuario. Impide desactivar o degradar al último super administrador
     * activo.
     *
     * @param ?string $cedula null deja la identificación intacta.
     * @param ?string $nombre null deja el nombre intacto.
     */
    public function actualizar(int $id, string $mail, int $nivel, int $estado, ?bool $puedeAppMovil = null, ?string $cedula = null, ?string $nombre = null): bool
    {
        $id = (int) $id;
        $mail = trim($mail);
        $mailEsc = $this->escape($mail);

        if ($mail !== '' && $this->existePorCorreo($mail, $id)) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
        }

        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no es válido.');
        }

        $actual = $this->query("SELECT nivel, estado, cedula, mail, registrado FROM usuarios WHERE id = {$id}");

        // Identificación: es la credencial de ingreso, así que se valida contra
        // todos los usuarios no eliminados (activos e inactivos). Solo se valida
        // cuando REALMENTE cambia: un usuario pendiente de registro lleva su
        // correo como cédula provisional, y guardar otro campo de su ficha no
        // debe tropezar con las reglas de formato de una identificación.
        $cedulaSet = '';
        if ($cedula !== null && trim($cedula) === trim((string) ($actual[0]['cedula'] ?? ''))) {
            $cedula = null;
        }
        if ($cedula !== null) {
            $cedula = $this->validarCedula($cedula, $id);
            $cedulaSet = ", cedula = '" . $this->escape($cedula) . "'";
        }

        // Correo de un usuario que AÚN no completa su registro: su cédula es la
        // provisional derivada del correo (substr(md5(mail),1,15)), y de ahí sale la
        // detección de "pendiente de registro". Si se cambia el correo sin recalcularla,
        // la cédula deja de coincidir con el hash del correo nuevo y el usuario pasa a
        // verse como registrado: desaparece el badge "Pendiente registro" y con él la
        // opción de reenviarle la invitación. Por eso, si sigue sin registrarse y
        // conserva la provisional, se regenera con el correo nuevo.
        if ($cedulaSet === '' && $mail !== '' && !empty($actual)) {
            $yaRegistrado = self::pgBool($actual[0]['registrado'] ?? false);
            $cedulaAct = trim((string) ($actual[0]['cedula'] ?? ''));
            $mailAct = (string) ($actual[0]['mail'] ?? '');
            if (!$yaRegistrado && $cedulaAct === substr(md5($mailAct), 0, 15) && $mail !== $mailAct) {
                $cedulaSet = ", cedula = '" . $this->escape(substr(md5($mail), 0, 15)) . "'";
            }
        }

        // Nombre: no es credencial de ingreso (esa es la cédula), solo la etiqueta
        // con la que se muestra a la persona. Se edita sobre todo en usuarios
        // invitados, donde quien creó la invitación pudo escribirlo mal.
        $nombreSet = '';
        if ($nombre !== null) {
            $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
            if ($nombre === '') {
                throw new \InvalidArgumentException('El nombre no puede quedar vacío.');
            }
            if (mb_strlen($nombre) > 100) {
                throw new \InvalidArgumentException('El nombre no puede superar los 100 caracteres.');
            }
            $nombreSet = ", nombre = '" . $this->escape($nombre) . "'";
        }

        $nivel = max(1, min(3, $nivel));
        $estado = $estado ? 1 : 0;

        if (!empty($actual)) {
            $esSuperAdmin = (int) ($actual[0]['nivel'] ?? 0) === 3;
            $estaActivo = (int) ($actual[0]['estado'] ?? 0) === 1;
            if ($esSuperAdmin && $estaActivo) {
                $otrosActivos = $this->contarSuperAdminActivos($id);
                if ($otrosActivos === 0) {
                    if ($estado === 0) {
                        throw new \InvalidArgumentException('Debe haber al menos un super administrador activo en el sistema.');
                    }
                    if ($nivel < 3) {
                        throw new \InvalidArgumentException('Debe haber al menos un super administrador activo. No puede cambiar el nivel del último.');
                    }
                }
            }
        }

        $movilSet = $puedeAppMovil === null ? '' : (', puede_app_movil = ' . ($puedeAppMovil ? 'true' : 'false'));
        $sql = "UPDATE usuarios SET mail = '{$mailEsc}', nivel = {$nivel}, estado = {$estado}{$movilSet}{$cedulaSet}{$nombreSet} WHERE id = {$id}";
        return $this->execute($sql);
    }

    /**
     * Crear usuario por correo (invitación). El usuario completará registro y clave vía correo.
     * Cedula temporal = correo. Password = token aleatorio hasta que se registre.
     */
    public function crearPorCorreo(string $nombre, string $correo, int $idAdmin, int $nivel = 1): array
    {
        $nombre = $this->escape(trim($nombre));
        $correo = trim($correo);
        if ($nombre === '' || $correo === '') {
            throw new \InvalidArgumentException('Nombre y correo son obligatorios.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no es válido.');
        }
        $mail = $this->escape($correo);

        if ($this->existePorCorreo($correo)) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
        }

        // Nivel: 1 (usuario) o 2 (administrador). Nunca super admin por esta vía.
        $nivel = in_array($nivel, [1, 2], true) ? $nivel : 1;

        $token = bin2hex(random_bytes(16));
        $hash = password_hash($token, PASSWORD_DEFAULT) ?: md5($token);

        $sql = "INSERT INTO usuarios (nombre, cedula, password, nivel, estado, mail, token, telefono)
                VALUES (:nombre, :cedula, :password, :nivel, 1, :mail, :token, :telefono)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'   => trim($nombre),
            ':cedula'   => substr(md5($correo), 0, 15), // Hash único de máx 15 caracteres
            ':password' => $hash,
            ':nivel'    => $nivel,
            ':mail'     => trim($correo),
            ':token'    => $token,
            ':telefono' => '' // Teléfono vacío por defecto en invitación
        ]);

        $idNuevo = $this->lastInsertId('usuarios_id_seq');
        $this->agregarAUsuarioAsignado($idNuevo, $idAdmin);

        return ['id' => $idNuevo, 'token' => $token];
    }

    public function agregarAUsuarioAsignado(int $idUsuario, int $idAdmin): bool
    {
        $idU = (int) $idUsuario;
        $idA = (int) $idAdmin;
        if ($idU <= 0 || $idA <= 0) return false;

        $sqlExiste = "SELECT 1 FROM usuario_asignado WHERE id_usuario = :id_u AND id_adm = :id_a";
        $stmtExiste = $this->db->prepare($sqlExiste);
        $stmtExiste->execute([':id_u' => $idU, ':id_a' => $idA]);
        
        if ($stmtExiste->fetch()) {
            return true;
        }

        $sqlInsert = "INSERT INTO usuario_asignado (id_usuario, id_adm) VALUES (:id_u, :id_a)";
        $stmtInsert = $this->db->prepare($sqlInsert);
        return $stmtInsert->execute([':id_u' => $idU, ':id_a' => $idA]);
    }

    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['nombre', 'cedula', 'mail', 'nivel', 'estado', 'puede_app_movil'];

    /**
     * Lista usuarios para el módulo de usuarios del sistema.
     * SuperAdmin (nivel 3): todos los usuarios del sistema.
     * Admin (nivel 2): los usuarios que comparten al menos una empresa con las
     * que él tiene asignadas — mismo criterio que /config/permisos-modulos
     * (EmpresaAsignada::getUsuariosAsignables) —, más los que gestiona
     * directamente en usuario_asignado (p. ej. invitados que aún no tienen
     * empresa) y él mismo. Un admin nunca ve superadministradores.
     */
    public function getTodosParaListado(int $idActual, int $nivel, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        $offset = ($page - 1) * $perPage;
        $idActual = (int) $idActual;

        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $col = $ordenCol === 'nombre' ? 'u.nombre' : 'u.' . $ordenCol;

        $from = 'usuarios u';
        if ($nivel >= 3) {
            $where = "WHERE u.eliminado = false";
        } else {
            $where = "WHERE u.eliminado = false AND u.nivel < 3 AND (" . self::whereVisiblesParaAdmin($idActual) . ")";
        }

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            // Nivel y Estado se buscan por su texto tal como se muestran en la tabla
            // (Nivel: Usuario/Administrador/Super Admin; Estado: Activo/Inactivo/Pendiente registro).
            $nivelTexto = "(CASE WHEN u.nivel >= 3 THEN 'Super Admin' WHEN u.nivel >= 2 THEN 'Administrador' ELSE 'Usuario' END)";
            $estadoTexto = "(CASE WHEN NOT " . self::sqlRegistrado('u') . " THEN 'Pendiente registro' WHEN u.estado = 1 THEN 'Activo' ELSE 'Inactivo' END)";
            $where .= " AND (u.nombre ILIKE '%{$b}%' OR u.cedula ILIKE '%{$b}%' OR u.mail ILIKE '%{$b}%'
                OR {$nivelTexto} ILIKE '%{$b}%' OR {$estadoTexto} ILIKE '%{$b}%')";
        }

        $countSql = "SELECT COUNT(DISTINCT u.id) AS total FROM {$from} {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);

        // Estado de registro: ver self::sqlRegistrado().
        $sql = "SELECT DISTINCT u.id, u.nombre, u.cedula, u.nivel, u.estado, u.mail, u.token, u.puede_app_movil,
                " . self::sqlRegistrado('u') . " AS registrado
            FROM {$from} {$where}
            ORDER BY {$col} {$dir}
            LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->query($sql);

        $empresaModel = new EmpresaAsignada();
        foreach ($rows as &$r) {
            $r['empresas'] = $empresaModel->getEmpresasDeUsuario((int) $r['id']);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Cuenta cuántas empresas tiene asignadas un usuario.
     */
    public function contarAsignacionesEmpresa(int $idUsuario): int
    {
        $id = (int) $idUsuario;
        $sql = "SELECT COUNT(*) AS total FROM empresa_asignada WHERE id_usuario = :id_u";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_u' => $id]);
        return (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Eliminación lógica de un usuario.
     * Restricción: No puede tener empresas asignadas.
     * Solo para niveles 2 y 3.
     */
    public function eliminar(int $idUsuario, int $idEliminador): bool
    {
        $id = (int) $idUsuario;
        $idAsig = (int) $idEliminador;

        if ($id <= 0) return false;

        // 1. Validar que no tenga empresas asignadas
        $asignaciones = $this->contarAsignacionesEmpresa($id);
        if ($asignaciones > 0) {
            throw new \RuntimeException("No se puede eliminar el usuario porque tiene {$asignaciones} empresas asignadas. Primero debe quitarle las asignaciones.");
        }

        // 2. Validar que no sea el último super administrador activo
        $actual = $this->query("SELECT nivel, estado FROM usuarios WHERE id = {$id}");
        if (!empty($actual)) {
            $esSuperAdmin = (int) ($actual[0]['nivel'] ?? 0) === 3;
            $estaActivo = (int) ($actual[0]['estado'] ?? 0) === 1;
            if ($esSuperAdmin && $estaActivo) {
                $otrosActivos = $this->contarSuperAdminActivos($id);
                if ($otrosActivos === 0) {
                    throw new \RuntimeException("Al menos debe existir un usuario superadministrador en el sistema. No puede eliminar al último.");
                }
            }
        }

        // 3. Marcar como eliminado (lógico)
        $sql = "UPDATE usuarios 
                SET eliminado = true, 
                    estado = 0, 
                    deleted_at = CURRENT_TIMESTAMP, 
                    deleted_by = :deleted_by 
                WHERE id = :id AND eliminado = false";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $idAsig
        ]);
    }

    /**
     * Completa el registro de un nuevo usuario (invitación).
     * Actualiza nombre, cédula, password y teléfono, y limpia el token.
     */
    public function completarRegistro(int $id, string $nombre, string $cedula, string $password, string $telefono, string $token): bool
    {
        $id = (int) $id;
        $nombre = trim($nombre);
        $cedula = trim($cedula);
        $password = trim($password);
        $telefono = trim($telefono);
        $token = trim($token);

        if ($nombre === '' || $cedula === '' || $password === '' || $token === '') {
            return false;
        }

        if ($this->existePorCedula($cedula, $id)) {
            throw new \InvalidArgumentException('Ya existe un usuario con esa identificación.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) $hash = md5($password);

        $sql = "UPDATE usuarios
                SET nombre = :nombre,
                    cedula = :cedula,
                    password = :password,
                    telefono = :telefono,
                    token = '',
                    registrado = true,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND token = :token AND eliminado = false";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre'   => $nombre,
            ':cedula'   => $cedula,
            ':password' => $hash,
            ':telefono' => $telefono,
            ':id'       => $id,
            ':token'    => $token
        ]);
    }

    /**
     * Valida una identificación para un usuario y la devuelve normalizada.
     * Punto único de las reglas del campo: la usan tanto la edición de la ficha
     * como la asignación de una clave provisional.
     *
     * Se valida contra todos los usuarios no eliminados (activos e inactivos):
     * un usuario inactivo conserva su credencial de ingreso por si se reactiva.
     */
    private function validarCedula(string $cedula, int $id): string
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            throw new \InvalidArgumentException('La identificación no puede quedar vacía.');
        }
        if (mb_strlen($cedula) > 15) {
            throw new \InvalidArgumentException('La identificación no puede superar los 15 caracteres.');
        }
        if (!preg_match('/^[A-Za-z0-9-]+$/', $cedula)) {
            throw new \InvalidArgumentException('La identificación solo admite letras, números y guiones.');
        }
        if ($this->existeCedulaEnUso($cedula, $id)) {
            throw new \InvalidArgumentException('Ya existe un usuario con esa identificación.');
        }
        return $cedula;
    }

    /**
     * True si el usuario todavía carga la identificación PROVISIONAL que le puso
     * la invitación (el hash de su correo): nunca completó el registro y, por
     * tanto, no tiene un número real con el cual iniciar sesión.
     *
     * Misma expresión que usan el listado y getDatosInvitacion, para que
     * "pendiente de registro" signifique lo mismo en todo el módulo.
     */
    public function tieneCedulaProvisional(int $id): bool
    {
        $id = (int) $id;
        if ($id <= 0) return false;
        $r = $this->query("SELECT (COALESCE(cedula,'') = substr(md5(COALESCE(mail,'')), 1, 15)) AS provisional
            FROM usuarios WHERE id = {$id} AND eliminado = false LIMIT 1");
        return isset($r[0]) && self::pgBool($r[0]['provisional']);
    }

    /**
     * Asigna una contraseña provisional a un usuario, fijada por un administrador
     * (salida cuando el correo de invitación no llega: se le entrega la clave por
     * otro medio y él la cambia luego desde su perfil).
     *
     * Deja el registro como completado (`registrado = true`) porque el usuario ya
     * tiene credenciales, y borra el token: el enlace de invitación anterior deja
     * de servir, así que un correo filtrado no puede usarse después para tomar la
     * cuenta.
     *
     * @param ?string $cedula identificación a fijar en la misma operación (para el
     *        usuario que nunca se registró y aún carga la provisional). La clave
     *        sin un número real con el cual entrar no sirve de nada, así que las
     *        dos cosas se guardan juntas o no se guarda ninguna.
     */
    public function establecerClaveProvisional(int $id, string $password, int $idAdmin, ?string $cedula = null): bool
    {
        $id = (int) $id;
        $password = trim($password);
        if ($id <= 0) return false;
        if (mb_strlen($password) < 4) {
            throw new \InvalidArgumentException('La contraseña provisional debe tener al menos 4 caracteres.');
        }
        if (strlen($password) > 72) {
            throw new \InvalidArgumentException('La contraseña provisional no puede superar los 72 caracteres.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo cifrar la contraseña.');
        }

        $params = [
            ':password'   => $hash,
            ':updated_by' => $idAdmin > 0 ? $idAdmin : null,
            ':id'         => $id,
        ];
        $cedulaSet = '';
        if ($cedula !== null) {
            $params[':cedula'] = $this->validarCedula($cedula, $id);
            $cedulaSet = ', cedula = :cedula';
        }

        $sql = "UPDATE usuarios
                SET password = :password{$cedulaSet},
                    token = '',
                    registrado = true,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :updated_by
                WHERE id = :id AND eliminado = false";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Estado de registro REAL de un usuario, consultado al vuelo.
     *
     * La ficha del modal se arma con los `data-*` de la fila del listado, que son
     * una foto del momento en que se pintó la tabla. Si en esa misma sesión se le
     * asignó una clave provisional, se le reenvió la invitación o el propio usuario
     * terminó su registro, esa foto queda vieja. Este método permite que el modal
     * pregunte el estado cada vez que se abre, en lugar de confiar en el atributo.
     *
     * @return ?array ['registrado' => bool, 'mail' => string]
     */
    public function getEstadoRegistro(int $id): ?array
    {
        $id = (int) $id;
        $sql = "SELECT mail, " . self::sqlRegistrado() . " AS registrado
                FROM usuarios WHERE id = {$id} AND eliminado = false LIMIT 1";
        $r = $this->query($sql);
        if (empty($r[0])) {
            return null;
        }
        return [
            'registrado' => self::pgBool($r[0]['registrado'] ?? false),
            'mail'       => (string) ($r[0]['mail'] ?? ''),
        ];
    }

    /**
     * Obtiene datos básicos y token para reenviar invitación.
     */
    public function getDatosInvitacion(int $id): ?array
    {
        $id = (int) $id;
        $sql = "SELECT nombre, mail, token, " . self::sqlRegistrado() . " AS registrado
                FROM usuarios WHERE id = {$id} AND eliminado = false LIMIT 1";
        $r = $this->query($sql);
        return $r[0] ?? null;
    }

    /**
     * Establece la empresa favorita del usuario.
     */
    public function setEmpresaFavorita(int $idUsuario, int $idEmpresa): bool
    {
        try {
            $idU = $idUsuario;
            $idE = $idEmpresa > 0 ? $idEmpresa : null;
            
            if ($idU <= 0) return false;
            
            $sql = "UPDATE usuarios SET id_empresa_favorita = :id_empresa WHERE id = :id_usuario";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id_empresa' => $idE,
                'id_usuario' => $idU
            ]);
        } catch (\PDOException $e) {
            error_log("Error en setEmpresaFavorita: " . $e->getMessage());
            return false;
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\IdentificacionTercero;
use App\models\Empresa;
use App\repositories\modulos\AlumnoPortalRepository;
use App\repositories\modulos\AlumnoRepository;
use App\Rules\modulos\AlumnoPortalRules;
use App\Rules\modulos\AlumnoRules;
use App\Services\EnvioDocumentosSRIService;
use App\Services\LogSistemaService;
use Exception;

/**
 * Portal de representantes del módulo Alumnos.
 *
 * Un QR general por colegio (empresa). El representante se identifica con su
 * cédula/RUC; se le envía un código de 6 dígitos a su correo registrado (o al que
 * escriba, si es nuevo). Con el código verificado puede, UNA sola vez, actualizar
 * sus datos de facturación y los de sus alumnos, o registrar alumnos nuevos. Los
 * cambios se aplican al instante (decisión del usuario: la verificación por correo
 * es la autorización) y quedan en log_sistema con origen «portal».
 *
 * Endpoint público (CLAUDE.md §6): el token se resuelve con la empresa activa y se
 * revalida antes de escribir.
 */
class AlumnoPortalService
{
    private const MINUTOS_CODIGO   = 10;
    private const MAX_INTENTOS     = 5;   // por código
    private const MAX_CODIGOS_IDENT = 5;  // por identificación y hora
    private const MAX_CODIGOS_IP   = 15;  // por IP y hora (wifi compartido de un colegio)
    private const MAX_ALUMNOS      = 10;  // por envío
    private const MAX_RETIROS      = 6;   // personas autorizadas por alumno

    public const ERROR_ENLACE = 'El enlace no es válido o ya no está disponible.';

    public function __construct(
        private AlumnoPortalRepository $repo = new AlumnoPortalRepository(),
        private AlumnoRepository $alumnoRepo = new AlumnoRepository(),
        private AlumnoPortalRules $rules = new AlumnoPortalRules(),
        private AlumnoRules $alumnoRules = new AlumnoRules(),
        private LogSistemaService $log = new LogSistemaService()
    ) {}

    private function exigirEsquema(): void
    {
        if (!$this->repo->existeEsquema()) {
            throw new Exception('Falta ejecutar en la base el script database/migrations/20260929_alumnos_portal_representantes.sql.');
        }
    }

    public static function urlPortal(string $token): string
    {
        return url_absoluta('portal-alumnos/' . rawurlencode($token));
    }

    private static function nuevoToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    // ─── 1. Administración (usuario del sistema) ─────────────────────────────

    /** Portal de la empresa; se crea la primera vez. */
    public function getPortal(int $idEmpresa, int $idUsuario): array
    {
        $this->exigirEsquema();
        $p = $this->repo->getPortalEmpresa($idEmpresa);
        if (!$p) {
            $this->repo->beginTransaction();
            try {
                $id = $this->repo->crearPortal($idEmpresa, self::nuevoToken(), $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'alumnos_portal', $id, null, ['activo' => true]);
                $this->repo->commit();
            } catch (\Throwable $e) {
                $this->repo->rollBack();
                throw $e;
            }
            $p = $this->repo->getPortalEmpresa($idEmpresa);
        }
        return ['activo' => in_array($p['activo'], [true, 't', 1, '1'], true), 'url' => self::urlPortal((string) $p['token'])];
    }

    public function regenerar(int $idEmpresa, int $idUsuario): array
    {
        $this->getPortal($idEmpresa, $idUsuario);
        $p = $this->repo->getPortalEmpresa($idEmpresa);
        $this->repo->beginTransaction();
        try {
            $this->repo->actualizarPortal((int) $p['id'], $idEmpresa, ['token' => self::nuevoToken()], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'regenerar_qr', 'alumnos_portal', (int) $p['id'], null, ['motivo' => 'El QR anterior deja de funcionar']);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return $this->getPortal($idEmpresa, $idUsuario);
    }

    public function setActivo(int $idEmpresa, bool $activo, int $idUsuario): array
    {
        $this->getPortal($idEmpresa, $idUsuario);
        $p = $this->repo->getPortalEmpresa($idEmpresa);
        $this->repo->beginTransaction();
        try {
            $this->repo->actualizarPortal((int) $p['id'], $idEmpresa, ['activo' => $activo], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $activo ? 'activar' : 'desactivar', 'alumnos_portal', (int) $p['id'], null, ['activo' => $activo]);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return $this->getPortal($idEmpresa, $idUsuario);
    }

    /**
     * Envía el enlace del portal por correo a los representantes (clientes que
     * facturan) de los alumnos indicados. Un correo por cliente.
     *
     * @return array{enviados:int, sin_correo:int, fallidos:int, total:int, sin_correo_muestra:array}
     */
    public function enviarInvitaciones(int $idEmpresa, int $idUsuario, array $idsAlumno): array
    {
        $portal = $this->getPortal($idEmpresa, $idUsuario);
        if (!$portal['activo']) {
            throw new Exception('Active el portal antes de enviar el enlace.');
        }
        $clientes = $this->repo->getClientesDeAlumnos($idsAlumno, $idEmpresa);
        $empresaNombre = $this->nombreEmpresa($idEmpresa);
        $res = ['enviados' => 0, 'sin_correo' => 0, 'fallidos' => 0, 'total' => count($clientes), 'sin_correo_muestra' => []];
        foreach ($clientes as $c) {
            if ($c['identificacion'] === AlumnoPortalRules::CONSUMIDOR_FINAL || !$this->correosValidos((string) $c['email'])) {
                $res['sin_correo']++;
                if (count($res['sin_correo_muestra']) < 8) { $res['sin_correo_muestra'][] = $c['nombre']; }
                continue;
            }
            $ok = $this->enviarCorreo($idEmpresa, (string) $c['email'], (string) $c['nombre'],
                'Actualice los datos de sus hijos' . ($empresaNombre !== '' ? ' · ' . $empresaNombre : ''),
                'alumnos_portal_invitacion', ['nombre' => $c['nombre'], 'empresa_nombre' => $empresaNombre, 'url' => $portal['url']], $empresaNombre);
            $ok ? $res['enviados']++ : $res['fallidos']++;
        }
        $this->log->registrar($idUsuario, $idEmpresa, 'enviar_enlace', 'alumnos_portal', null, null, $res);
        return $res;
    }

    // ─── 2. Portal público (sin login) ───────────────────────────────────────

    /** Portal por token (empresa activa), o null. Mismo trato para todos los motivos (§6). */
    public function getContexto(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 64 || !$this->repo->existeEsquema()) {
            return null;
        }
        return $this->repo->getPortalPorToken($token);
    }

    private function portalOError(string $token): array
    {
        $p = $this->getContexto($token);
        if (!$p) { throw new Exception(self::ERROR_ENLACE); }
        return $p;
    }

    /**
     * Paso 1: identificación. Envía el código al correo registrado del cliente, o
     * pide un correo si el representante es nuevo.
     *
     * @return array{estado:string, id_codigo?:int, correo?:string, identificacion:string, nuevo?:bool}
     *         estado: 'codigo' (enviado), 'pedir_correo' (nuevo sin correo), 'sin_correo' (cliente sin correo registrado)
     */
    public function solicitarCodigo(string $token, string $identificacion, string $correoNuevo, string $ip): array
    {
        $portal = $this->portalOError($token);
        $idEmpresa = (int) $portal['id_empresa'];
        $ident = AlumnoPortalRules::normalizarIdentificacion($identificacion);
        $this->rules->validarIdentificacion($ident);

        [$porIdent, $porIp] = $this->repo->contarCodigosRecientes($idEmpresa, array_merge([$ident], IdentificacionTercero::variantes($ident)), $ip, 60);
        if ($porIdent >= self::MAX_CODIGOS_IDENT || $porIp >= self::MAX_CODIGOS_IP) {
            throw new Exception('Se pidieron demasiados códigos. Espere unos minutos e intente de nuevo.');
        }

        $cliente = $this->clientePorIdentificacion($idEmpresa, $ident);
        if ($cliente) {
            $correos = $this->correosValidos((string) $cliente['email']);
            if (!$correos) {
                return ['estado' => 'sin_correo', 'identificacion' => $ident];
            }
            $destino = $correos[0];
            $nombre  = (string) $cliente['nombre'];
            $idCli   = (int) $cliente['id'];
        } else {
            $correoNuevo = trim($correoNuevo);
            if ($correoNuevo === '') {
                return ['estado' => 'pedir_correo', 'identificacion' => $ident];
            }
            $this->rules->validarCorreo($correoNuevo);
            $destino = $correoNuevo;
            $nombre  = '';
            $idCli   = null;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $idCodigo = $this->repo->crearCodigo([
            'id_empresa' => $idEmpresa, 'identificacion' => $ident, 'id_cliente' => $idCli, 'correo' => $destino,
            'codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT), 'minutos' => self::MINUTOS_CODIGO, 'ip' => $ip,
        ]);
        $empresaNombre = (string) ($portal['empresa_nombre'] ?? '');
        $ok = $this->enviarCorreo($idEmpresa, $destino, $nombre, 'Código de verificación: ' . $codigo,
            'alumnos_portal_codigo', ['codigo' => $codigo, 'minutos' => self::MINUTOS_CODIGO, 'empresa_nombre' => $empresaNombre, 'nombre' => $nombre], $empresaNombre);
        if (!$ok) {
            throw new Exception('No se pudo enviar el código a su correo. Intente más tarde o comuníquese con la institución.');
        }
        return ['estado' => 'codigo', 'id_codigo' => $idCodigo, 'correo' => self::enmascararCorreo($destino), 'identificacion' => $ident, 'nuevo' => $idCli === null];
    }

    /** Paso 2: verifica el código. Devuelve el ticket de sesión (un solo guardado). */
    public function verificarCodigo(string $token, int $idCodigo, string $codigo): string
    {
        $portal = $this->portalOError($token);
        $codigo = trim($codigo);
        $this->rules->validarFormatoCodigo($codigo);
        $c = $this->repo->getCodigo($idCodigo, (int) $portal['id_empresa']);
        $falla = 'El código es incorrecto o ya venció. Pida uno nuevo si es necesario.';
        if (!$c || $c['usado_at'] !== null || $c['verificado_at'] !== null
            || !in_array($c['vigente'], [true, 't', 1, '1'], true) || (int) $c['intentos'] >= self::MAX_INTENTOS) {
            throw new Exception($falla);
        }
        if (!password_verify($codigo, (string) $c['codigo_hash'])) {
            $this->repo->sumarIntento($idCodigo);
            throw new Exception($falla);
        }
        $sesion = bin2hex(random_bytes(24));
        $this->repo->marcarVerificado($idCodigo, $sesion);
        return $sesion;
    }

    /** Código verificado, vigente y aún sin usar para esa sesión; si no, excepción. */
    private function codigoDeSesion(array $portal, string $sesion): array
    {
        $c = preg_match('/^[a-f0-9]{48}$/', $sesion) ? $this->repo->getCodigoPorSesion($sesion, (int) $portal['id_empresa']) : null;
        if (!$c || $c['usado_at'] !== null || !in_array($c['sesion_vigente'], [true, 't', 1, '1'], true)) {
            throw new Exception('La verificación ya se usó o venció. Para hacer otro cambio, pida un código nuevo.');
        }
        return $c;
    }

    /** Paso 3: datos para el formulario (representante + sus alumnos). */
    public function getFormulario(string $token, string $sesion): array
    {
        $portal = $this->portalOError($token);
        $c = $this->codigoDeSesion($portal, $sesion);
        $idEmpresa = (int) $portal['id_empresa'];
        $cliente = $c['id_cliente'] ? $this->repo->getCliente((int) $c['id_cliente'], $idEmpresa) : null;
        $alumnos = [];
        if ($cliente) {
            foreach ($this->repo->getAlumnosDeCliente((int) $cliente['id'], $idEmpresa) as $a) {
                $a['representantes'] = $this->alumnoRepo->getRepresentantes((int) $a['id'], $idEmpresa);
                $alumnos[] = $a;
            }
        }
        return [
            'portal'         => $portal,
            'identificacion' => (string) $c['identificacion'],
            'correo'         => (string) $c['correo'],
            'cliente'        => $cliente,
            'alumnos'        => $alumnos,
            // Solo para alumnos NUEVOS: campus y nivel de su matrícula.
            'campus'         => $this->campusActivos($idEmpresa),
            'niveles'        => $this->nivelesActivos($idEmpresa),
        ];
    }

    /**
     * Paso 4: guarda (una sola vez). Crea o actualiza el cliente y los alumnos.
     *
     * @return array{actualizados:int, creados:int, cliente:string}
     */
    public function guardar(string $token, string $sesion, array $post, string $ip): array
    {
        $portal = $this->portalOError($token);
        $c = $this->codigoDeSesion($portal, $sesion);
        $idEmpresa = (int) $portal['id_empresa'];
        $actor = (int) ($portal['created_by'] ?: $portal['updated_by']);
        if ($actor <= 0) { throw new Exception(self::ERROR_ENLACE); }

        // Defensa en profundidad (§6): la empresa se revalida justo antes de escribir.
        if (!(new Empresa())->estaActiva($idEmpresa)) {
            throw new Exception(self::ERROR_ENLACE);
        }

        $ident   = (string) $c['identificacion'];
        $cliente = $c['id_cliente'] ? $this->repo->getCliente((int) $c['id_cliente'], $idEmpresa) : null;

        // ── Validación de todo antes de escribir ──
        $cli = [
            'nombre'    => AlumnoPortalRules::limpiarTexto((string) ($post['cliente']['nombre'] ?? ''), 300),
            'direccion' => AlumnoPortalRules::limpiarTexto((string) ($post['cliente']['direccion'] ?? ''), 300),
            'email'     => trim((string) ($post['cliente']['email'] ?? '')),
            'telefono'  => AlumnoPortalRules::limpiarTexto((string) ($post['cliente']['telefono'] ?? ''), 50),
        ];
        $this->rules->validarCliente($cli);

        $permitidos = [];
        if ($cliente) {
            foreach ($this->repo->getAlumnosDeCliente((int) $cliente['id'], $idEmpresa) as $a) { $permitidos[(int) $a['id']] = $a; }
        }
        $alumnos = [];
        foreach (array_values((array) ($post['alumnos'] ?? [])) as $i => $a) {
            if (!is_array($a)) { continue; }
            $id = (int) ($a['id'] ?? 0);
            $d = $this->datosAlumno($a);
            if ($id === 0 && $d['nombres'] === '' && $d['apellidos'] === '') { continue; } // bloque nuevo vacío
            if ($id !== 0 && !isset($permitidos[$id])) {
                throw new Exception(self::ERROR_ENLACE); // un id que no es de sus alumnos: manipulado
            }
            $num = $i + 1;
            try {
                $this->alumnoRules->validar($d + ['id_cliente' => 1, 'estado_academico' => 'activo']);
            } catch (Exception $e) {
                throw new Exception("Alumno #{$num}: " . $e->getMessage());
            }
            if ($d['fecha_nacimiento'] !== '' && ($d['fecha_nacimiento'] > date('Y-m-d') || !strtotime($d['fecha_nacimiento']))) {
                throw new Exception("Alumno #{$num}: la fecha de nacimiento no es válida.");
            }
            if ($id === 0) {
                // Matrícula del alumno nuevo: solo campus/niveles activos de ESTE colegio.
                $d['id_campus'] = (int) ($a['id_campus'] ?? 0) ?: null;
                $d['id_nivel']  = (int) ($a['id_nivel'] ?? 0) ?: null;
                if ($d['id_campus'] !== null && !in_array($d['id_campus'], array_column($this->campusActivos($idEmpresa), 'id'), true)) {
                    throw new Exception("Alumno #{$num}: el campus elegido no es válido.");
                }
                if ($d['id_nivel'] !== null && !in_array($d['id_nivel'], array_column($this->nivelesActivos($idEmpresa), 'id'), true)) {
                    throw new Exception("Alumno #{$num}: el nivel o curso elegido no es válido.");
                }
            }
            if ($id === 0 && $d['numero_identificacion'] !== '' && $this->alumnoRepo->existeIdentificacion($idEmpresa, $d['numero_identificacion'])) {
                throw new Exception("Alumno #{$num}: ya existe un alumno con la identificación {$d['numero_identificacion']}. Comuníquese con la institución.");
            }
            $alumnos[] = ['id' => $id, 'datos' => $d];
        }
        if (count($alumnos) > self::MAX_ALUMNOS) {
            throw new Exception('Puede enviar hasta ' . self::MAX_ALUMNOS . ' alumnos a la vez.');
        }
        if (!$cliente && !$alumnos) {
            throw new Exception('Agregue al menos un alumno para registrarse.');
        }

        // ── Escritura: todo o nada ──
        $this->repo->beginTransaction();
        try {
            if (!$this->repo->marcarUsado((int) $c['id'])) {
                throw new Exception('La verificación ya se usó. Para hacer otro cambio, pida un código nuevo.');
            }
            $auditoria = ['origen' => 'portal', 'ip' => $ip, 'identificacion' => $ident, 'correo_verificado' => $c['correo']];
            if ($cliente) {
                $idCliente = (int) $cliente['id'];
                $this->repo->actualizarCliente($idCliente, $idEmpresa, $cli, $actor);
                $this->log->registrar($actor, $idEmpresa, 'portal_actualizar', 'clientes', $idCliente, $cliente, $cli + $auditoria);
            } else {
                // Por si alguien lo registró en el sistema mientras el padre llenaba el formulario.
                $ya = $this->clientePorIdentificacion($idEmpresa, $ident);
                $nuevo = $cli + ['tipo_id' => AlumnoPortalRules::tipoIdCliente($ident), 'identificacion' => $ident];
                $idCliente = $ya ? (int) $ya['id'] : $this->repo->crearCliente($idEmpresa, $nuevo, $actor);
                if ($ya) { $this->repo->actualizarCliente($idCliente, $idEmpresa, $cli, $actor); }
                $this->log->registrar($actor, $idEmpresa, $ya ? 'portal_actualizar' : 'portal_crear', 'clientes', $idCliente, $ya ?: null, $nuevo + $auditoria);
            }

            $creados = 0; $actualizados = 0;
            foreach ($alumnos as $a) {
                $d = $a['datos'];
                if ($a['id'] > 0) {
                    $antes = $permitidos[$a['id']];
                    $this->repo->actualizarAlumno($a['id'], $idEmpresa, $d, $actor);
                    $idAlumno = $a['id'];
                    $actualizados++;
                    $this->log->registrar($actor, $idEmpresa, 'portal_actualizar', 'alumnos', $idAlumno, $antes, $d + $auditoria);
                } else {
                    $idAlumno = $this->repo->crearAlumno($idEmpresa, $idCliente, $d, $actor);
                    if ($d['id_campus'] !== null || $d['id_nivel'] !== null) {
                        $this->repo->crearMatricula($idAlumno, $idEmpresa, $d['id_campus'], $d['id_nivel'], $actor);
                    }
                    $creados++;
                    $this->log->registrar($actor, $idEmpresa, 'portal_crear', 'alumnos', $idAlumno, null, $d + ['id_cliente' => $idCliente] + $auditoria);
                }
                if ($this->alumnoRepo->existeTablaRepresentantes()) {
                    $this->alumnoRepo->syncRepresentantes($idAlumno, $idEmpresa, $d['representantes'], $actor);
                }
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        // Constancia al correo verificado (si falla, lo guardado ya quedó).
        $empresaNombre = (string) ($portal['empresa_nombre'] ?? '');
        $this->enviarCorreo($idEmpresa, (string) $c['correo'], $cli['nombre'], 'Sus datos fueron actualizados' . ($empresaNombre !== '' ? ' · ' . $empresaNombre : ''),
            'alumnos_portal_confirmacion', ['nombre' => $cli['nombre'], 'empresa_nombre' => $empresaNombre, 'actualizados' => $actualizados, 'creados' => $creados,
            'alumnos' => array_map(fn($a) => trim($a['datos']['apellidos'] . ' ' . $a['datos']['nombres']), $alumnos)], $empresaNombre);

        return ['actualizados' => $actualizados, 'creados' => $creados, 'cliente' => $cli['nombre']];
    }

    // ─── Auxiliares ──────────────────────────────────────────────────────────

    /** Datos de un alumno del formulario, acotados a los campos permitidos. */
    private function datosAlumno(array $a): array
    {
        $txt = fn(string $k, int $max) => AlumnoPortalRules::limpiarTexto((string) ($a[$k] ?? ''), $max);
        $tipo = in_array(($a['tipo_identificacion'] ?? ''), ['05', '06'], true) ? $a['tipo_identificacion'] : '';
        $sexo = in_array(($a['sexo'] ?? ''), ['M', 'F', 'O'], true) ? $a['sexo'] : '';
        $sangre = in_array(($a['tipo_sangre'] ?? ''), ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true) ? $a['tipo_sangre'] : '';
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($a['fecha_nacimiento'] ?? '')) ? (string) $a['fecha_nacimiento'] : '';

        $reps = [];
        foreach (array_slice(array_values((array) ($a['representantes'] ?? [])), 0, self::MAX_RETIROS) as $r) {
            if (!is_array($r)) { continue; }
            $nom = AlumnoPortalRules::limpiarTexto((string) ($r['nombres'] ?? ''), 200);
            if ($nom === '') { continue; }
            $reps[] = [
                'nombres'        => $nom,
                'identificacion' => AlumnoPortalRules::limpiarTexto((string) ($r['identificacion'] ?? ''), 20),
                'telefono'       => AlumnoPortalRules::limpiarTexto((string) ($r['telefono'] ?? ''), 30),
                'relacion'       => in_array(($r['relacion'] ?? ''), ['padre', 'madre', 'tutor', 'abuelo', 'hermano', 'tio', 'otro'], true) ? $r['relacion'] : '',
                'puede_retirar'  => !empty($r['puede_retirar']),
                'observacion'    => '',
            ];
        }

        return [
            'nombres'                      => mb_strtoupper($txt('nombres', 150)),
            'apellidos'                    => mb_strtoupper($txt('apellidos', 150)),
            'tipo_identificacion'          => $tipo,
            'numero_identificacion'        => AlumnoPortalRules::normalizarIdentificacion(mb_substr((string) ($a['numero_identificacion'] ?? ''), 0, 20)),
            'fecha_nacimiento'             => $fecha,
            'sexo'                         => $sexo,
            'nacionalidad'                 => $txt('nacionalidad', 80),
            'tipo_sangre'                  => $sangre,
            'alergias_condiciones'         => $txt('alergias_condiciones', 500),
            'contacto_emergencia_nombre'   => $txt('contacto_emergencia_nombre', 150),
            'contacto_emergencia_telefono' => $txt('contacto_emergencia_telefono', 30),
            'representantes'               => $reps,
        ];
    }

    /** Campus activos del colegio: [{id:int, nombre}] */
    private function campusActivos(int $idEmpresa): array
    {
        return array_map(fn($r) => ['id' => (int) $r['id'], 'nombre' => $r['nombre']],
            (new \App\repositories\modulos\AlumnoCampusRepository())->getParaSelect($idEmpresa));
    }

    /** Niveles/cursos activos del colegio: [{id:int, nombre}] */
    private function nivelesActivos(int $idEmpresa): array
    {
        return array_map(fn($r) => ['id' => (int) $r['id'], 'nombre' => $r['nombre']],
            (new \App\repositories\modulos\AlumnoNivelRepository())->getParaSelect($idEmpresa));
    }

    /** Cliente de la empresa por cédula o RUC (cédula ↔ RUC …001 se tratan como el mismo). */
    private function clientePorIdentificacion(int $idEmpresa, string $ident): ?array
    {
        $variantes = array_merge([$ident], IdentificacionTercero::variantes($ident));
        return $this->repo->buscarClientes($idEmpresa, $variantes)[0] ?? null;
    }

    private function correosValidos(string $campo): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $campo) ?: []),
            fn($c) => $c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)));
    }

    public static function enmascararCorreo(string $correo): string
    {
        [$u, $d] = array_pad(explode('@', $correo, 2), 2, '');
        $vis = mb_substr($u, 0, min(2, max(1, mb_strlen($u) - 1)));
        return $vis . str_repeat('*', max(3, mb_strlen($u) - mb_strlen($vis))) . '@' . $d;
    }

    private function nombreEmpresa(int $idEmpresa): string
    {
        $e = (new Empresa())->getPorId($idEmpresa) ?? [];
        return (string) (($e['nombre_comercial'] ?? '') !== '' ? $e['nombre_comercial'] : ($e['nombre'] ?? ''));
    }

    protected function enviarCorreo(int $idEmpresa, string $destino, string $nombre, string $asunto, string $plantilla, array $data, string $empresaNombre): bool
    {
        try {
            ob_start();
            require MVC_APP . '/views/emails/' . $plantilla . '.php';
            $cuerpo = (string) ob_get_clean();
            return (new EnvioDocumentosSRIService())->enviarAvisoSimple($idEmpresa, $destino, $nombre, $asunto, $cuerpo, $empresaNombre);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) { @ob_end_clean(); }
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'correo_' . $plantilla]);
            return false;
        }
    }
}

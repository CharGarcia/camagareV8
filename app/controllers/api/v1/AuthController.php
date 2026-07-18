<?php
/**
 * Controlador API v1: autenticación de la app móvil.
 * Espeja el flujo de App\controllers\AuthController (web) pero emite JWT en vez
 * de sesión-cookie, y usa el canal 'movil' en sesiones_activas/api_refresh_tokens
 * para poder convivir con una sesión web activa del mismo usuario.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\Empresa;
use App\models\Usuario;
use App\Services\Api\JwtService;
use App\Services\Api\RefreshTokenService;
use App\Services\SesionActivaService;

class AuthController extends ApiBaseController
{
    private const CANAL = 'movil';
    private const ACCESS_TTL = 1800; // 30 minutos

    /**
     * No aplica: AuthController no usa requireLeer/Crear/Actualizar/Eliminar (login/refresh
     * son públicos; el resto solo exige requireAuthApi(), sin permiso de submódulo).
     * Se implementa solo para satisfacer el método abstracto de ApiBaseController.
     */
    protected function getRutaModulo(): string
    {
        return 'auth';
    }

    /**
     * POST /api/v1/auth/login
     * body: { cedula, password, dispositivo_id, force_login? }
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $cedula = trim((string) ($body['cedula'] ?? ''));
        $password = trim((string) ($body['password'] ?? ''));
        $dispositivoId = trim((string) ($body['dispositivo_id'] ?? ''));
        $forceLogin = !empty($body['force_login']);

        if ($cedula === '' || $password === '') {
            $this->jsonError('CREDENCIALES', 'Usuario o contraseña incorrectos.', 401);
        }

        $model = new Usuario();
        $user = $model->validaLogin($cedula, $password);
        if (!$user) {
            $this->jsonError('CREDENCIALES', 'Usuario o contraseña incorrectos.', 401);
        }

        $sesionSvc = new SesionActivaService();
        $sesionActiva = $sesionSvc->obtenerSesionActiva((int) $user['id'], self::CANAL);

        if ($sesionActiva && !$forceLogin) {
            $ultimaActividad = $sesionActiva['ultima_actividad'] ?? $sesionActiva['created_at'] ?? '';
            if ($ultimaActividad) {
                $ultimaActividad = (new \DateTime($ultimaActividad))->format('d-m-Y H:i:s');
            }
            $this->jsonError('SESION_ACTIVA_OTRO_DISPOSITIVO', 'Ya hay una sesión activa desde otro celular.', 409, [
                'ultima_actividad' => $ultimaActividad,
            ]);
        }

        [$idEmpresa, $requiereSeleccion, $errorSinEmpresa] = $this->resolverEmpresaInicial($model, (int) $user['id'], (int) $user['nivel'], (int) ($user['id_empresa_favorita'] ?? 0));
        if ($errorSinEmpresa) {
            $this->jsonError('SIN_EMPRESA', 'El usuario no tiene empresas asignadas.', 403);
        }

        $sessionToken = $sesionSvc->iniciarSesion((int) $user['id'], self::CANAL);
        $refreshToken = (new RefreshTokenService())->emitir((int) $user['id'], $sessionToken, $dispositivoId, self::CANAL);
        $accessToken = $this->emitirAccessToken((int) $user['id'], $user['nombre'], (int) $user['nivel'], $idEmpresa, $sessionToken);

        $this->jsonOk([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => self::ACCESS_TTL,
            'usuario'       => [
                'id'     => (int) $user['id'],
                'nombre' => $user['nombre'],
                'nivel'  => (int) $user['nivel'],
            ],
            'id_empresa'                 => $idEmpresa,
            'requiere_seleccion_empresa' => $requiereSeleccion,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * body: { refresh_token, id_empresa? }
     * id_empresa es opcional: el cliente reenvía la última empresa activa que conoce;
     * se revalida contra las empresas asignadas antes de incluirla en el nuevo token.
     */
    public function refresh(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $rawRefresh = trim((string) ($body['refresh_token'] ?? ''));
        if ($rawRefresh === '') {
            $this->jsonError('REFRESH_REQUERIDO', 'Falta refresh_token.', 422);
        }

        $refreshSvc = new RefreshTokenService();
        try {
            $datos = $refreshSvc->validarYRotar($rawRefresh);
        } catch (\Throwable $e) {
            $this->jsonError('REFRESH_INVALIDO', 'Refresh token inválido o expirado. Inicie sesión de nuevo.', 401);
        }

        $idUsuario = $datos['id_usuario'];
        $sessionToken = $datos['session_token'];

        // Crítico: el refresh token es válido por sí mismo, pero solo debe servir si
        // la sesión a la que estaba atado SIGUE siendo la activa. Si otro dispositivo
        // hizo force_login mientras tanto, esta sesión ya no es válida y el refresh
        // debe fallar (si no, el dispositivo desplazado podría seguir renovando acceso).
        $sesionSvc = new SesionActivaService();
        if ($sessionToken === '' || !$sesionSvc->validarToken($sessionToken)) {
            $this->jsonError('SESION_CERRADA', 'La sesión móvil fue cerrada desde otro dispositivo. Inicie sesión de nuevo.', 401);
        }

        $model = new Usuario();
        $perfil = $model->getPerfil($idUsuario);
        if (!$perfil) {
            $this->jsonError('USUARIO_NO_ENCONTRADO', 'El usuario ya no existe o está inactivo.', 401);
        }

        $idEmpresa = null;
        $idEmpresaSolicitada = (int) ($body['id_empresa'] ?? 0);
        if ($idEmpresaSolicitada > 0) {
            $idEmpresa = $this->validarAccesoEmpresa($idUsuario, (int) $perfil['nivel'], $idEmpresaSolicitada);
        }

        $nuevoRefresh = $refreshSvc->emitir($idUsuario, $sessionToken, $datos['dispositivo_id'], self::CANAL);
        $accessToken = $this->emitirAccessToken($idUsuario, $perfil['nombre'] ?? '', (int) $perfil['nivel'], $idEmpresa, $sessionToken);

        $this->jsonOk([
            'access_token'  => $accessToken,
            'refresh_token' => $nuevoRefresh,
            'expires_in'    => self::ACCESS_TTL,
            'id_empresa'    => $idEmpresa,
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * body: { refresh_token? }
     */
    public function logout(): void
    {
        $claims = $this->requireAuthApi();

        try {
            (new SesionActivaService())->cerrarSesion((string) ($claims['session_token'] ?? ''));
        } catch (\Throwable $e) {
            // No interrumpir el logout si falla la BD, igual que el logout web.
        }

        $body = $this->getJsonBody();
        $rawRefresh = trim((string) ($body['refresh_token'] ?? ''));
        if ($rawRefresh !== '') {
            (new RefreshTokenService())->revocar($rawRefresh);
        }

        $this->jsonOk(['cerrada' => true]);
    }

    /**
     * GET /api/v1/auth/empresas — empresas asignadas al usuario autenticado.
     */
    public function empresas(): void
    {
        $this->requireAuthApi();
        $idUsuario = (int) $_SESSION['id_usuario'];

        $model = new Empresa();
        $asignadas = $model->getEmpresasAsignadas($idUsuario);

        $resultado = array_map(static function (array $e): array {
            return [
                'id_empresa' => (int) $e['id_empresa'],
                'ruc'        => $e['ruc'] ?? '',
                'nombre'     => !empty($e['nombre_comercial']) ? $e['nombre_comercial'] : ($e['nombre'] ?? ''),
            ];
        }, $asignadas);

        $this->jsonOk(['empresas' => $resultado]);
    }

    /**
     * POST /api/v1/auth/seleccionar-empresa
     * body: { id_empresa }
     * Devuelve un access_token nuevo con la empresa activa fijada.
     */
    public function seleccionarEmpresa(): void
    {
        $claims = $this->requireAuthApi();
        $idUsuario = (int) $_SESSION['id_usuario'];
        $nivel = (int) $_SESSION['nivel'];

        $body = $this->getJsonBody();
        $idEmpresa = (int) ($body['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            $this->jsonError('ID_EMPRESA_REQUERIDO', 'Falta id_empresa.', 422);
        }

        $idEmpresaValidado = $this->validarAccesoEmpresa($idUsuario, $nivel, $idEmpresa);
        if ($idEmpresaValidado === null) {
            $this->jsonError('EMPRESA_NO_ASIGNADA', 'No tiene permiso para acceder a esta empresa.', 403);
        }

        $accessToken = $this->emitirAccessToken($idUsuario, (string) ($_SESSION['nombre'] ?? ''), $nivel, $idEmpresaValidado, (string) $claims['session_token']);

        $this->jsonOk([
            'access_token' => $accessToken,
            'expires_in'   => self::ACCESS_TTL,
            'id_empresa'   => $idEmpresaValidado,
        ]);
    }

    /**
     * Replica la resolución de empresa del login web (AuthController::login), sin
     * tocar ese código: favorita → única → primera. A diferencia de la web, si hay
     * más de una empresa asignada NO se autoselecciona ninguna: se marca
     * requiere_seleccion_empresa y el cliente debe llamar a seleccionar-empresa.
     *
     * @return array{0: ?int, 1: bool, 2: bool} [idEmpresa, requiereSeleccion, errorSinEmpresa]
     */
    private function resolverEmpresaInicial(Usuario $model, int $idUsuario, int $nivel, int $idEmpresaFavorita): array
    {
        $empresasLogin = $model->getEmpresasAsignadasParaLogin($idUsuario);
        $numEmpresas = (int) ($empresasLogin['numrows'] ?? 0);

        if ($numEmpresas === 0) {
            if ($nivel !== 3) {
                return [null, false, true];
            }
            return [null, false, false];
        }

        if ($numEmpresas === 1) {
            return [(int) $empresasLogin['id_empresa'], false, false];
        }

        // Más de una empresa: si tiene favorita entre las asignadas, se preselecciona;
        // igual se informa requiere_seleccion_empresa=true para que el usuario confirme/cambie.
        if ($idEmpresaFavorita > 0) {
            $emp = $model->getEmpresaAsignadaEspecifica($idUsuario, $idEmpresaFavorita);
            if ($emp) {
                return [(int) $emp['id_empresa'], true, false];
            }
        }

        return [null, true, false];
    }

    /**
     * Valida que el usuario tenga acceso a $idEmpresa (nivel 3 = acceso total).
     * Mismo criterio que EmpresaController::setEmpresa, sin reimplementar su lógica de redirect.
     */
    private function validarAccesoEmpresa(int $idUsuario, int $nivel, int $idEmpresa): ?int
    {
        if ($nivel >= 3) {
            return $idEmpresa;
        }
        $model = new Empresa();
        $asignadas = $model->getEmpresasAsignadas($idUsuario);
        $ids = array_map('intval', array_column($asignadas, 'id_empresa'));
        return in_array($idEmpresa, $ids, true) ? $idEmpresa : null;
    }

    private function emitirAccessToken(int $idUsuario, string $nombre, int $nivel, ?int $idEmpresa, string $sessionToken): string
    {
        return (new JwtService())->emitir([
            'sub'           => $idUsuario,
            'nombre'        => $nombre,
            'nivel'         => $nivel,
            'id_empresa'    => $idEmpresa,
            'session_token' => $sessionToken,
            'canal'         => self::CANAL,
        ], self::ACCESS_TTL);
    }
}

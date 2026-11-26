<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ClienteQrRepository;
use App\repositories\modulos\ClienteRepository;
use App\Rules\modulos\ClienteQrRules;
use App\Services\LogSistemaService;

/**
 * QR de registro de clientes: un QR fijo por empresa (vale para todos sus
 * establecimientos). El cliente lo escanea, llena sus datos y queda registrado en
 * Clientes al instante; si su identificación ya existe en la empresa se actualizan
 * nombre, correo, teléfono y dirección, así que para corregir un registro mal hecho
 * basta con volver a enviarlo.
 */
class ClienteQrService
{
    /** Envíos permitidos por IP y por hora (anti-abuso del enlace público). */
    public const MAX_ENVIOS_HORA = 10;

    public const TITULO_DEFECTO  = 'Registra tus datos';
    public const MENSAJE_DEFECTO = 'Escanea este código con la cámara de tu celular y completa tus datos para emitir tu factura.';

    public function __construct(
        private ClienteQrRepository $repo,
        private ClienteRepository $clientes,
        private ClienteQrRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new ClienteQrRepository(), new ClienteRepository(), new ClienteQrRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repo->tablasDisponibles();
    }

    /**
     * QR de la empresa. Si aún no existe y `$crearSiFalta`, lo genera (con un token
     * aleatorio que ya no cambia) y deja constancia en la auditoría.
     */
    public function getConfig(int $idEmpresa, int $idUsuario, bool $crearSiFalta): ?array
    {
        $cfg = $this->repo->getConfigPorEmpresa($idEmpresa);
        if ($cfg !== null || !$crearSiFalta) {
            return $cfg;
        }

        $this->repo->beginTransaction();
        try {
            // Candado por empresa: dos usuarios abriendo la ventana a la vez no deben
            // crear dos QR (el índice único lo impediría con un error feo).
            $this->repo->bloquearClave('clientes_qr:' . $idEmpresa);
            $cfg = $this->repo->getConfigPorEmpresa($idEmpresa);
            if ($cfg === null) {
                $token = bin2hex(random_bytes(24));
                $id = $this->repo->crearConfig($idEmpresa, $token, self::TITULO_DEFECTO, self::MENSAJE_DEFECTO, $idUsuario);
                $cfg = $this->repo->getConfigPorEmpresa($idEmpresa);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'clientes_qr_config', $id, null, $this->sinToken($cfg));
            }
            $this->repo->commit();
            return $cfg;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Guarda si el QR está activo y el título/mensaje del impreso. */
    public function guardarConfig(int $idEmpresa, int $idUsuario, bool $activo, string $titulo, string $mensaje): array
    {
        $titulo  = trim(preg_replace('/\s+/u', ' ', $titulo));
        $mensaje = trim(str_replace("\r\n", "\n", $mensaje));
        $this->rules->validarConfig($titulo, $mensaje);

        $antes = $this->getConfig($idEmpresa, $idUsuario, true);

        $this->repo->beginTransaction();
        try {
            $this->repo->actualizarConfig((int) $antes['id'], $idEmpresa, $activo,
                $titulo !== '' ? $titulo : null, $mensaje !== '' ? $mensaje : null, $idUsuario);
            $despues = $this->repo->getConfigPorEmpresa($idEmpresa);
            $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'clientes_qr_config', (int) $antes['id'],
                $this->sinToken($antes), $this->sinToken($despues));
            $this->repo->commit();
            return $despues;
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function resumen(int $idEmpresa): array
    {
        return $this->repo->resumenRegistros($idEmpresa);
    }

    /** QR activo de una empresa activa, o null (el enlace se comporta como inexistente). */
    public function getConfigPublica(string $token): ?array
    {
        return $this->repo->getConfigPorToken($token);
    }

    /**
     * Envío del formulario público. Crea el cliente o actualiza el existente (misma
     * identificación en la empresa, incluso si estaba eliminado: se reactiva).
     *
     * @return array{accion:string, nombre:string}
     * @throws \InvalidArgumentException con mensaje para el cliente.
     */
    public function registrarPublico(string $token, array $form, string $ip, string $userAgent): array
    {
        $cfg = $this->repo->getConfigPorToken($token);
        if ($cfg === null) {
            throw new \InvalidArgumentException('Este enlace no está disponible o ha sido desactivado.');
        }
        $datos     = $this->rules->validarRegistroPublico($form);
        $idEmpresa = (int) $cfg['id_empresa'];
        // Flujo sin sesión: la auditoría se atribuye al usuario que generó el QR.
        $idUsuario = (int) ($cfg['created_by'] ?? 0);

        $this->repo->beginTransaction();
        try {
            // Defensa en profundidad (§6): revalidar QR y empresa activos bajo candado.
            if ($this->repo->bloquearConfig((int) $cfg['id']) === null) {
                throw new \InvalidArgumentException('Este enlace no está disponible o ha sido desactivado.');
            }
            if ($this->repo->contarEnviosPorIp((int) $cfg['id'], $ip, 60) >= self::MAX_ENVIOS_HORA) {
                throw new \InvalidArgumentException('Has enviado demasiados registros. Espera un momento e intenta nuevamente.');
            }
            // Dos envíos simultáneos de la misma identificación no deben crear dos fichas.
            $this->repo->bloquearClave('cliente_ident:' . $idEmpresa . ':' . $datos['identificacion']);

            $existente = $this->clientes->findByIdentificacion($idEmpresa, $datos['identificacion']);
            if ($existente) {
                $idCliente = (int) $existente['id'];
                $this->clientes->actualizarDatosQr($idCliente, $idEmpresa, $datos, $idUsuario);
                $accion = 'actualizado';
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'clientes', $idCliente,
                    $this->camposContacto($existente),
                    $this->camposContacto($datos) + ['origen' => 'QR de registro de clientes', 'ip' => $ip]);
            } else {
                $alta = [
                    'id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario,
                    'nombre' => $datos['nombre'], 'tipo_id' => $datos['tipo_id'], 'identificacion' => $datos['identificacion'],
                    'telefono' => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                    'email' => $datos['email'],
                    'direccion' => $datos['direccion'] !== '' ? $datos['direccion'] : null,
                    'plazo' => 0, 'provincia' => null, 'ciudad' => null, 'status' => 1, 'id_vendedor' => null,
                ];
                $idCliente = $this->clientes->create($alta);
                $accion = 'creado';
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'clientes', $idCliente, null,
                    $this->camposContacto($datos) + ['tipo_id' => $datos['tipo_id'], 'origen' => 'QR de registro de clientes', 'ip' => $ip]);
            }

            $this->repo->insertarRegistro([
                'id_empresa' => $idEmpresa, 'id_config' => (int) $cfg['id'], 'id_cliente' => $idCliente,
                'accion' => $accion, 'datos' => $datos, 'ip_origen' => $ip, 'user_agent' => $userAgent,
                'created_by' => $idUsuario ?: null,
            ]);

            $this->repo->commit();
            return ['accion' => $accion, 'nombre' => $datos['nombre']];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    private function camposContacto(array $c): array
    {
        return [
            'identificacion' => $c['identificacion'] ?? null,
            'nombre'         => $c['nombre'] ?? null,
            'email'          => $c['email'] ?? null,
            'telefono'       => $c['telefono'] ?? null,
            'direccion'      => $c['direccion'] ?? null,
        ];
    }

    /** El token es la llave del enlace público: no se copia a la auditoría. */
    private function sinToken(?array $cfg): ?array
    {
        if ($cfg === null) {
            return null;
        }
        unset($cfg['token']);
        return $cfg;
    }
}

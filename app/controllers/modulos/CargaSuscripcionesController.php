<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CargaSuscripcionesRepository;
use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\CargaSuscripcionesRules;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CargaSuscripcionesAplicacionService;
use App\Services\modulos\CargaSuscripcionesPlantillaService;
use App\Services\modulos\CargaSuscripcionesValidacionService;
use App\Services\modulos\SuscripcionesService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Carga masiva de suscripciones desde Excel.
 *
 * Flujo en dos pasos, sin tablas de staging:
 *   1. validarAjax()  guarda el archivo en storage/ y devuelve el informe + un token.
 *   2. aplicarAjax()  relee ese archivo por el token y crea las suscripciones.
 *
 * El token vive en la sesión del usuario, de modo que nadie aplica el archivo de otro.
 */
class CargaSuscripcionesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/carga-suscripciones';

    private const SESSION_KEY = 'carga_suscripciones_pendientes';

    private const VIDA_TEMPORAL = 7200; // 2 horas

    private CargaSuscripcionesRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new CargaSuscripcionesRepository();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos.carga_suscripciones.index', [
            'titulo'     => 'Carga de Suscripciones',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
        ]);
    }

    /** Descarga la plantilla con los catálogos de la empresa activa. */
    public function descargarPlantilla(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            http_response_code(400);
            echo 'No hay una empresa activa.';
            exit;
        }

        $servicio = new CargaSuscripcionesPlantillaService($this->repository);
        $libro    = $servicio->generar($idEmpresa);
        $nombre   = $servicio->nombreArchivo($idEmpresa);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        (new Xlsx($libro))->save('php://output');
        exit;
    }

    /** Sube el archivo y lo valida. No escribe nada. */
    public function validarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            if ($idEmpresa <= 0) {
                throw new \RuntimeException('No hay una empresa activa.');
            }

            $rutaTemporal = $this->recibirArchivo($idEmpresa);

            $servicio = new CargaSuscripcionesValidacionService($this->repository, new CargaSuscripcionesRules());
            $informe  = $servicio->validar($rutaTemporal, $idEmpresa);

            if ($informe['errores_globales']) {
                @unlink($rutaTemporal);
                echo json_encode([
                    'ok'      => false,
                    'informe' => $this->informeParaVista($informe),
                ]);
                exit;
            }

            $token = bin2hex(random_bytes(16));
            $_SESSION[self::SESSION_KEY][$token] = [
                'ruta'       => $rutaTemporal,
                'id_empresa' => $idEmpresa,
                'creado'     => time(),
            ];

            echo json_encode([
                'ok'      => true,
                'token'   => $token,
                'informe' => $this->informeParaVista($informe),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Aplica una carga previamente validada. */
    public function aplicarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $token     = trim($_POST['token'] ?? '');

            $pendiente = $_SESSION[self::SESSION_KEY][$token] ?? null;
            if (!$token || !$pendiente) {
                throw new \RuntimeException('La carga expiró o no existe. Vuelva a subir el archivo.');
            }

            if ((int) $pendiente['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Cambió la empresa activa. Vuelva a subir el archivo.');
            }
            if (!is_file($pendiente['ruta'])) {
                throw new \RuntimeException('El archivo temporal ya no está disponible. Vuelva a subirlo.');
            }

            // Se revalida siempre: el informe no viaja por el navegador.
            $validacion = new CargaSuscripcionesValidacionService($this->repository, new CargaSuscripcionesRules());
            $informe    = $validacion->validar($pendiente['ruta'], $idEmpresa);

            if ($informe['errores_globales']) {
                throw new \RuntimeException(implode(' ', $informe['errores_globales']));
            }

            $resultado = $this->construirAplicacionService()->aplicar($informe, $idEmpresa, $idUsuario);

            $this->descartarPendiente($token);

            echo json_encode(['ok' => true, 'resultado' => $resultado]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Cancela una carga pendiente y borra su archivo. */
    public function cancelarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $this->descartarPendiente(trim($_POST['token'] ?? ''));
        echo json_encode(['ok' => true]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function construirAplicacionService(): CargaSuscripcionesAplicacionService
    {
        $logService = new LogSistemaService();

        $suscripcionesService = new SuscripcionesService(
            new SuscripcionesRepository(),
            new SuscripcionesRules(),
            $logService
        );

        return new CargaSuscripcionesAplicacionService($suscripcionesService, $logService);
    }

    private function recibirArchivo(int $idEmpresa): string
    {
        $archivo = $_FILES['archivo'] ?? null;

        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->mensajeErrorSubida($archivo['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new \RuntimeException('El archivo debe ser un Excel (.xlsx).');
        }
        if ($archivo['size'] > 20 * 1024 * 1024) {
            throw new \RuntimeException('El archivo excede los 20 MB.');
        }

        $directorio = MVC_ROOT . '/storage/cargas_suscripciones/' . $idEmpresa;
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new \RuntimeException('No se pudo preparar la carpeta temporal.');
        }

        $this->limpiarTemporalesViejos($directorio);

        $destino = $directorio . '/' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo subido.');
        }

        return $destino;
    }

    private function mensajeErrorSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_NO_FILE   => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
            UPLOAD_ERR_PARTIAL   => 'La subida se interrumpió. Intente de nuevo.',
            default              => 'No se pudo subir el archivo.',
        };
    }

    private function limpiarTemporalesViejos(string $directorio): void
    {
        foreach (glob($directorio . '/*') ?: [] as $archivo) {
            if (is_file($archivo) && (time() - filemtime($archivo)) > self::VIDA_TEMPORAL) {
                @unlink($archivo);
            }
        }
    }

    private function descartarPendiente(string $token): void
    {
        $pendiente = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if ($pendiente) {
            @unlink($pendiente['ruta']);
            unset($_SESSION[self::SESSION_KEY][$token]);
        }
    }

    /** Recorta el informe a lo que la vista necesita. */
    private function informeParaVista(array $informe): array
    {
        $filas = array_values(array_filter(
            $informe['filas'] ?? [],
            static fn($f) => !empty($f['errores']) || !empty($f['avisos'])
        ));

        $recortado = count($filas) > 300;
        if ($recortado) {
            $filas = array_slice($filas, 0, 300);
        }

        return [
            'ok'               => $informe['ok'] ?? false,
            'errores_globales' => $informe['errores_globales'] ?? [],
            'resumen'          => $informe['resumen'] ?? [],
            'filas'            => $filas,
            'recortado'        => $recortado,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CargaProductosRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\CargaProductosRules;
use App\Rules\modulos\ProductoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CargaProductosAplicacionService;
use App\Services\modulos\CargaProductosPlantillaService;
use App\Services\modulos\CargaProductosValidacionService;
use App\Services\modulos\InventarioService;
use App\Services\modulos\ProductoService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Carga masiva de productos y servicios desde Excel.
 *
 * Flujo en dos pasos, sin tablas de staging:
 *   1. validarAjax()  guarda el archivo en storage/ y devuelve el informe + un token.
 *   2. aplicarAjax()  relee ese archivo por el token y escribe en las tablas reales.
 *
 * El token vive en la sesión del usuario, de modo que nadie puede aplicar el
 * archivo de otro.
 */
class CargaProductosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/carga-productos';

    /** Clave en $_SESSION donde se guardan las cargas pendientes de aplicar. */
    private const SESSION_KEY = 'carga_productos_pendientes';

    /** Vida máxima de un archivo temporal, en segundos. */
    private const VIDA_TEMPORAL = 7200; // 2 horas

    private CargaProductosRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new CargaProductosRepository();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos.carga_productos.index', [
            'titulo'     => 'Carga de Productos',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
        ]);
    }

    /** Descarga la plantilla ya poblada con los productos de la empresa activa. */
    public function descargarPlantilla(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            http_response_code(400);
            echo 'No hay una empresa activa.';
            exit;
        }

        $servicio = new CargaProductosPlantillaService($this->repository);
        $libro    = $servicio->generar($idEmpresa);
        $nombre   = $servicio->nombreArchivo($idEmpresa);

        // Descartar cualquier salida previa para no corromper el .xlsx.
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

    /** Sube el archivo y lo valida. No escribe nada en las tablas de productos. */
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

            $servicio = new CargaProductosValidacionService($this->repository, new CargaProductosRules());
            $informe  = $servicio->validar($rutaTemporal, $idEmpresa);

            if ($informe['errores_globales']) {
                // El archivo no sirve: no tiene sentido conservarlo.
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

            // La empresa activa no puede haber cambiado entre validar y aplicar.
            if ((int) $pendiente['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Cambió la empresa activa. Vuelva a subir el archivo.');
            }
            if (!is_file($pendiente['ruta'])) {
                throw new \RuntimeException('El archivo temporal ya no está disponible. Vuelva a subirlo.');
            }

            // Se revalida siempre: el informe no viaja por el navegador, así que
            // el cliente no puede alterar lo que se va a escribir.
            $validacion = new CargaProductosValidacionService($this->repository, new CargaProductosRules());
            $informe    = $validacion->validar($pendiente['ruta'], $idEmpresa);

            if ($informe['errores_globales']) {
                throw new \RuntimeException(implode(' ', $informe['errores_globales']));
            }

            $resultado = $this->construirAplicacionService()->aplicar($informe, $idEmpresa, $idUsuario);

            $this->descartarPendiente($token);

            echo json_encode(['ok' => true, 'resultado' => $resultado]);
        } catch (\Throwable $e) {
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

    private function construirAplicacionService(): CargaProductosAplicacionService
    {
        $productoRepo = new ProductoRepository();
        $logService   = new LogSistemaService();
        $invService   = new InventarioService(new InventarioRepository(), $logService);

        $productoService = new ProductoService(
            $productoRepo,
            new ProductoRules(),
            $logService,
            $invService
        );

        return new CargaProductosAplicacionService(
            $this->repository,
            $productoRepo,
            $productoService,
            $logService
        );
    }

    /**
     * Valida y guarda el archivo subido en storage/.
     * @return string Ruta del archivo temporal.
     */
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

        $directorio = MVC_ROOT . '/storage/cargas_productos/' . $idEmpresa;
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

    /** Borra archivos temporales que quedaron de cargas abandonadas. */
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

    /**
     * Recorta el informe a lo que la vista necesita.
     * El payload completo de productos no se envía al navegador: es voluminoso y
     * no se usa (aplicarAjax revalida el archivo desde cero).
     */
    private function informeParaVista(array $informe): array
    {
        $filas = array_values(array_filter(
            $informe['filas'] ?? [],
            static fn($f) => !empty($f['errores']) || !empty($f['avisos'])
        ));

        // Un archivo con miles de errores no aporta nada extra en pantalla.
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

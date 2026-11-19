<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CargaClientesRepository;
use App\repositories\modulos\ClienteRepository;
use App\Rules\modulos\CargaClientesRules;
use App\Rules\modulos\ClienteRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CargaClientesAplicacionService;
use App\Services\modulos\CargaClientesEsquema;
use App\Services\modulos\CargaClientesPlantillaService;
use App\Services\modulos\CargaClientesValidacionService;
use App\Services\modulos\ClienteService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Descarga, carga y edición masiva de clientes desde Excel.
 *
 * Flujo en dos pasos, sin tablas de staging (igual que Carga de Productos):
 *   1. validarAjax()  guarda el archivo en storage/ y devuelve el informe + un token.
 *   2. aplicarAjax()  relee ese archivo por el token, lo revalida y escribe.
 *
 * El token vive en la sesión del usuario: nadie puede aplicar el archivo de otro.
 *
 * Permisos del módulo: ver = descargar; crear = dar de alta clientes nuevos;
 * actualizar = modificar existentes. Sin acceso total solo se descargan y se
 * modifican los clientes que el usuario registró (§6).
 */
class CargaClientesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/carga-clientes';

    private const SESSION_KEY = 'carga_clientes_pendientes';

    /** Vida máxima de un archivo temporal, en segundos. */
    private const VIDA_TEMPORAL = 7200;

    private CargaClientesRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new CargaClientesRepository();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos.carga_clientes.index', [
            'titulo'            => 'Carga de Clientes',
            'perm'              => $this->getPermisos(),
            'rutaModulo'        => self::RUTA_MODULO,
            'columnasElegibles' => CargaClientesEsquema::columnasElegibles(),
            'soloPropios'       => $this->idUsuarioFiltro() !== null,
        ]);
    }

    /** Descarga la plantilla poblada con los clientes de la empresa activa. */
    public function descargarPlantilla(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            http_response_code(400);
            echo 'No hay una empresa activa.';
            exit;
        }

        $servicio = new CargaClientesPlantillaService($this->repository);
        $libro    = $servicio->generar($idEmpresa, $this->idUsuarioFiltro());
        $nombre   = $servicio->nombreArchivo($idEmpresa);

        // Descargar la base de clientes es una acción sensible: queda auditada.
        (new LogSistemaService())->registrar(
            (int) ($_SESSION['id_usuario'] ?? 0),
            $idEmpresa,
            'descargar_plantilla',
            'clientes',
            null,
            null,
            ['archivo' => $nombre, 'solo_propios' => $this->idUsuarioFiltro() !== null]
        );

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

    /** Sube el archivo y lo revisa. No escribe nada en la tabla de clientes. */
    public function validarAjax(): void
    {
        $this->requireEscribir();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            if ($idEmpresa <= 0) {
                throw new \RuntimeException('No hay una empresa activa.');
            }

            $parcial = $this->leerModoParcial();
            $rutaTemporal = $this->recibirArchivo($idEmpresa);

            $informe = $this->validacion()->validar($rutaTemporal, $idEmpresa, $parcial, $this->contexto());

            if ($informe['errores_globales']) {
                @unlink($rutaTemporal);
                echo json_encode(['ok' => false, 'informe' => $this->informeParaVista($informe)]);
                exit;
            }

            $token = bin2hex(random_bytes(16));
            $_SESSION[self::SESSION_KEY][$token] = [
                'ruta'       => $rutaTemporal,
                'id_empresa' => $idEmpresa,
                'creado'     => time(),
                // La elección se fija al revisar: al aplicar se usa esta, no la
                // que pudiera mandar el navegador.
                'parcial'    => $parcial,
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

    /** Aplica una carga previamente revisada. */
    public function aplicarAjax(): void
    {
        $this->requireEscribir();
        header('Content-Type: application/json');

        $pendiente = null;
        try {
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $token     = trim((string) ($_POST['token'] ?? ''));

            $pendiente = $_SESSION[self::SESSION_KEY][$token] ?? null;
            if (!$token || !$pendiente) {
                throw new \RuntimeException('La carga ya se aplicó, expiró o no existe. Vuelva a subir el archivo.');
            }
            // Se reclama el token ANTES de escribir: un segundo clic o un reintento
            // ya no lo encuentra y no puede aplicar la misma carga dos veces.
            unset($_SESSION[self::SESSION_KEY][$token]);

            if ((int) $pendiente['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Cambió la empresa activa. Vuelva a subir el archivo.');
            }
            if (!is_file($pendiente['ruta'])) {
                throw new \RuntimeException('El archivo temporal ya no está disponible. Vuelva a subirlo.');
            }

            // Se revalida siempre: el informe no viaja por el navegador, así que
            // el cliente no puede alterar lo que se va a escribir.
            $informe = $this->validacion()->validar(
                $pendiente['ruta'],
                $idEmpresa,
                $pendiente['parcial'] ?? null,
                $this->contexto()
            );
            if ($informe['errores_globales']) {
                throw new \RuntimeException(implode(' ', $informe['errores_globales']));
            }

            $logService = new LogSistemaService();
            $aplicacion = new CargaClientesAplicacionService(
                new ClienteService(new ClienteRepository(), new ClienteRules(), $logService),
                $logService
            );
            $resultado = $aplicacion->aplicar($informe, $idEmpresa, $idUsuario);

            echo json_encode(['ok' => true, 'resultado' => $resultado]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        } finally {
            if ($pendiente) {
                @unlink($pendiente['ruta']);
            }
        }
        exit;
    }

    /** Cancela una carga pendiente y borra su archivo. */
    public function cancelarAjax(): void
    {
        $this->requireEscribir();
        header('Content-Type: application/json');

        $token = trim((string) ($_POST['token'] ?? ''));
        $pendiente = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if ($pendiente) {
            @unlink($pendiente['ruta']);
            unset($_SESSION[self::SESSION_KEY][$token]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** Subir un archivo exige poder crear o actualizar (cada fila se revisa aparte). */
    private function requireEscribir(): void
    {
        $perm = $this->getPermisos();
        if (!empty($perm['crear'])) {
            $this->requireCrear();
        } else {
            $this->requireActualizar();
        }
    }

    /** Registros propios (§6): sin acceso total, solo los clientes que creó el usuario. */
    private function idUsuarioFiltro(): ?int
    {
        if ((int) ($_SESSION['nivel'] ?? 1) >= 3) {
            return null;
        }
        return empty($this->getPermisos()['todo']) ? (int) ($_SESSION['id_usuario'] ?? 0) : null;
    }

    private function contexto(): array
    {
        $perm = $this->getPermisos();
        return [
            'puede_crear'       => !empty($perm['crear']),
            'puede_actualizar'  => !empty($perm['actualizar']),
            'id_usuario_filtro' => $this->idUsuarioFiltro(),
        ];
    }

    private function validacion(): CargaClientesValidacionService
    {
        return new CargaClientesValidacionService($this->repository, new CargaClientesRules());
    }

    /**
     * Modo "Actualizar solo algunas columnas" elegido en pantalla.
     * @return string[]|null Nombres de columna; null = carga completa.
     */
    private function leerModoParcial(): ?array
    {
        if (($_POST['modo'] ?? 'completa') !== 'parcial') {
            return null;
        }

        $indices = CargaClientesValidacionService::normalizarParcial((array) ($_POST['columnas'] ?? []));
        if (!$indices) {
            throw new \RuntimeException('Marque al menos una columna para actualizar.');
        }
        return array_values(array_map(
            static fn($i) => CargaClientesEsquema::COLUMNAS[$i],
            array_keys($indices)
        ));
    }

    /** Valida y guarda el archivo subido en storage/. Devuelve su ruta. */
    private function recibirArchivo(int $idEmpresa): string
    {
        $archivo = $_FILES['archivo'] ?? null;

        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->mensajeErrorSubida((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new \RuntimeException('El archivo debe ser un Excel (.xlsx) descargado de este módulo.');
        }
        if ($archivo['size'] > 20 * 1024 * 1024) {
            throw new \RuntimeException('El archivo excede los 20 MB.');
        }

        $directorio = MVC_ROOT . '/storage/cargas_clientes/' . $idEmpresa;
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new \RuntimeException('No se pudo preparar la carpeta temporal.');
        }

        foreach (glob($directorio . '/*') ?: [] as $viejo) {
            if (is_file($viejo) && (time() - filemtime($viejo)) > self::VIDA_TEMPORAL) {
                @unlink($viejo);
            }
        }

        $destino = $directorio . '/' . bin2hex(random_bytes(12)) . '.xlsx';
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

    /**
     * Recorta el informe a lo que la vista necesita: el payload de cada cliente
     * no viaja al navegador (aplicarAjax revalida el archivo desde cero).
     */
    private function informeParaVista(array $informe): array
    {
        $filas = $informe['filas'] ?? [];
        $recortado = count($filas) > 300;
        if ($recortado) {
            $filas = array_slice($filas, 0, 300);
        }

        return [
            'modo'             => $informe['modo'] ?? 'completa',
            'ok'               => $informe['ok'] ?? false,
            'errores_globales' => $informe['errores_globales'] ?? [],
            'resumen'          => $informe['resumen'] ?? [],
            'filas'            => $filas,
            'recortado'        => $recortado,
        ];
    }
}

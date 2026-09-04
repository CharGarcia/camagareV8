<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\EstadosFinancierosService;
use App\Services\modulos\EmpresaService;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\Services\ReportService;
use App\core\Database;
use Exception;

class EstadosFinancierosController extends BaseModuloController
{
    private EstadosFinancierosService $service;
    private EmpresaService $empresaService;

    public function __construct()
    {
        parent::__construct();
        
        $this->service = new EstadosFinancierosService(
            new EstadosFinancierosRepository(),
            new ReportService()
        );
        $this->empresaService = new EmpresaService(
            new \App\repositories\modulos\EmpresaRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $aniosDisponibles = $this->service->getAniosDisponibles($idEmpresa);
        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int)date('Y')];
        }

        $centrosCosto = $this->service->getCentrosCostoActivos($idEmpresa);
        $proyectos = $this->service->getProyectosActivos($idEmpresa);

        // Variables para la vista
        $fechaInicio = date('Y-01-01');
        $fechaFin = date('Y-12-31');

        $perm = $this->getPermisos();

        $idsGrupoRuc = (new \App\repositories\modulos\EmpresaRepository())->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);

        // La generación de asientos pendientes NO se hace aquí (bloquearía la carga cuando hay
        // muchos por generar). La dispara la vista en segundo plano vía sincronizarAjax().
        $this->viewWithLayout('layouts.main', 'modulos.estados_financieros.index', [
            'titulo' => 'Estados Financieros',
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'aniosDisponibles' => $aniosDisponibles,
            'centrosCosto' => $centrosCosto,
            'proyectos' => $proyectos,
            'rutaModulo' => $this->getRutaModulo(),
            'perm' => $perm,
            'hayGrupoRuc' => count($idsGrupoRuc) > 1,
            'fullWidth' => true
        ]);
    }

    /**
     * Genera en segundo plano los asientos contables pendientes (documentos sin asiento).
     * Se invoca por AJAX desde la vista al cargar, para que la página no quede bloqueada
     * mientras se generan (puede tardar cuando hay muchos documentos pendientes).
     * Devuelve los avisos de configuración recolectados por el sincronizador.
     */
    public function sincronizarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            // Liberar el lock de sesión para no bloquear otras peticiones del usuario
            // mientras dura la generación, y ampliar el tiempo máximo de ejecución.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(300);

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            $sincronizador->sincronizar($idEmpresa, $idUsuario);

            echo json_encode([
                'success'   => true,
                'resumen'   => $sincronizador->getResumenMensaje(),
                'detalle'   => $sincronizador->getDetalle(),
                'warnings'  => $sincronizador->getWarnings(),
                'info'      => $sincronizador->getInfo(),
                'generados' => $sincronizador->getGenerados(),
            ]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Ejecuta UN paso de la sincronización (un módulo, o una de las verificaciones fijas del
     * final) y devuelve de inmediato — permite a la UI mostrar una barra de progreso real
     * (paso/totalPasos) e interrumpir el proceso entre pasos. Ver
     * SincronizadorAsientosService::ejecutarPaso().
     */
    public function sincronizarPasoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $paso = (int) ($_GET['paso'] ?? $_POST['paso'] ?? 0);

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(120);

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            $resultado = $sincronizador->ejecutarPaso($idEmpresa, $idUsuario, $paso);

            echo json_encode(['ok' => true] + $resultado);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Cuenta cuántos documentos operativos están pendientes de generar su asiento contable,
     * sin generar nada. La vista lo consulta al cargar para preguntar al usuario si desea
     * generarlos ahora o continuar sin generar.
     */
    public function contarPendientesAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
            echo json_encode([
                'ok'                   => true,
                'pendientes'           => $sincronizador->contarPendientes($idEmpresa),
                'migrados_sin_asiento' => $sincronizador->contarMigradosSinAsiento($idEmpresa),
            ]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    public function generarEstadoResultados(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            // TODO: Agregar filtros si existen
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage() . ' en ' . $th->getFile() . ':' . $th->getLine()]);
        }
    }

    public function generarEstadoSituacionFinanciera(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            // TODO: Agregar filtros si existen
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage() . ' en ' . $th->getFile() . ':' . $th->getLine()]);
        }
    }

    /**
     * "Consolidado por RUC": resumen de los conceptos mapeados en Balances Consolidados
     * (sumados entre establecimientos) + el reporte completo de cada establecimiento del RUC
     * por separado. Ver EstadosFinancierosService::getConsolidadoRuc().
     */
    public function generarConsolidadoRucAjax(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int) $_GET['nivel'] : 5;

            $datos = $this->service->getConsolidadoRuc($idEmpresa, $idUsuario, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function generarEstadoResultadosPorPeriodos(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');

            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoResultadosPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function generarEstadoSituacionFinancieraPorPeriodos(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');

            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoSituacionFinancieraPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function exportar(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = $_GET['tipo'] ?? 'resultados';
        $formato = $_GET['formato'] ?? 'excel';
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
        
        $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
        $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
        $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $fechaInicio . ' al ' . $fechaFin;

        if ($tipo === 'resultados_periodos' || $tipo === 'situacion_periodos') {
            $datos = $tipo === 'resultados_periodos'
                ? $this->service->getEstadoResultadosPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel)
                : $this->service->getEstadoSituacionFinancieraPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            if ($formato === 'pdf') {
                $this->service->exportarPdfPorPeriodos($tipo, $datos, $empresaNombre, $rangoFechas);
            } else {
                $this->service->exportarExcelPorPeriodos($tipo, $datos, $empresaNombre, $rangoFechas);
            }
            return;
        }

        if ($tipo === 'resultados') {
            $datos = $this->service->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        } else {
            $datos = $this->service->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        }

        if ($formato === 'pdf') {
            $this->service->exportarPdf($tipo, $datos, $empresaNombre, $rangoFechas);
        } else if ($formato === 'sri') {
            $ruc = $empresa['ruc'] ?? '';
            $this->service->exportarSri($tipo, $datos, $empresaNombre, $rangoFechas, $ruc);
        } else if (str_starts_with($formato, 'supercias_')) {
            $superciasTipo = strtoupper(substr($formato, 10)); // 'ESF', 'ERI', 'ECP', 'EFE'
            $anio = (int) substr($fechaFin, 0, 4);
            
            $superciasService = new \App\Services\SuperciasEvaluatorService(\App\core\Database::getConnection());
            $resultados = $superciasService->evaluar($idEmpresa, $anio);
            $datosTipo = $resultados[$superciasTipo] ?? [];

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="SUPERCIAS_' . $superciasTipo . '_' . $anio . '.txt"');

            $out = fopen('php://output', 'w');
            foreach ($datosTipo as $key => $casillero) {
                $valor = $casillero['valor'];
                
                if ($superciasTipo === 'ECP') {
                    $partes = explode('.', (string)$key);
                    $codigo = $partes[0];
                    $subcodigo = $partes[1] ?? '';
                    if ($subcodigo !== '') {
                        fwrite($out, $codigo . "\t" . $subcodigo . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                    } else {
                        fwrite($out, $codigo . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                    }
                } else {
                    fwrite($out, $key . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                }
            }
            fclose($out);
            exit;
        } else {
            $this->service->exportarExcel($tipo, $datos, $empresaNombre, $rangoFechas);
        }
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/estados-financieros';
    }

    public function generarMayorAuxiliar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $codigoCuenta = $_GET['codigo_cuenta'] ?? '';
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null;

            if (empty($codigoCuenta)) {
                echo json_encode(['success' => false, 'error' => 'Código de cuenta requerido']);
                return;
            }

            $datos = $this->service->generarMayorAuxiliar(
                $idEmpresa,
                $codigoCuenta,
                $fechaInicio,
                $fechaFin,
                $idCentroCosto,
                $idProyecto
            );

            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Detalle del documento que originó el asiento (factura, compra, egreso…), para el modal de
     * solo lectura que se abre desde la columna "Documento Ref." del mayor auxiliar. Valida el
     * permiso de lectura de Estados Financieros: no expone nada que el usuario no vea ya.
     */
    public function getDocumentoOrigenAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $servicio = new \App\Services\modulos\DocumentoOrigenService();
            $datos = $servicio->getDetalle(
                trim($_GET['modulo'] ?? ''),
                (int) ($_GET['id'] ?? 0),
                (int) $_SESSION['id_empresa']
            );
            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

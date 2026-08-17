<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\BalanceComprobacionService;
use App\repositories\modulos\BalanceComprobacionRepository;
use App\Services\ReportService;

class BalanceComprobacionController extends BaseModuloController
{
    private BalanceComprobacionService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/balance-comprobacion';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new BalanceComprobacionService(new BalanceComprobacionRepository(), new ReportService());
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $aniosDisponibles = $this->service->getAniosDisponibles($idEmpresa);
        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int) date('Y')];
        }

        $this->viewWithLayout('layouts.main', 'modulos.balance_comprobacion.index', [
            'titulo' => 'Balance de Comprobación',
            'fechaInicio' => date('Y-01-01'),
            'fechaFin' => date('Y-12-31'),
            'aniosDisponibles' => $aniosDisponibles,
            'centrosCosto' => $this->service->getCentrosCostoActivos($idEmpresa),
            'proyectos' => $this->service->getProyectosActivos($idEmpresa),
            'rutaModulo' => $this->getRutaModulo(),
            'perm' => $this->getPermisos(),
            'fullWidth' => true,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-01-01'),
            'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-12-31'),
            'id_centro_costo' => !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null,
            'id_proyecto' => !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null,
            'nivel' => !empty($_GET['nivel']) ? (int) $_GET['nivel'] : 5,
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $f = $this->getFiltrosDesdeRequest();
            $datos = $this->service->generar($idEmpresa, $f['fecha_inicio'], $f['fecha_fin'], $f['id_centro_costo'], $f['id_proyecto'], $f['nivel']);
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
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
            echo json_encode(['ok' => true, 'pendientes' => $sincronizador->contarPendientes($idEmpresa)]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Genera los asientos contables pendientes (documentos sin asiento). Se invoca por AJAX
     * desde la vista solo si el usuario acepta el aviso de pendientes. Libera el lock de sesión
     * y amplía el tiempo de ejecución porque puede tardar cuando hay muchos documentos.
     */
    public function sincronizarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

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
                'generados' => $sincronizador->getGenerados(),
            ]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltrosDesdeRequest();

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $f['fecha_inicio'] . ' al ' . $f['fecha_fin'];

        $datos = $this->service->generar($idEmpresa, $f['fecha_inicio'], $f['fecha_fin'], $f['id_centro_costo'], $f['id_proyecto'], $f['nivel']);
        $this->service->exportarExcel($datos, $empresaNombre, $rangoFechas);
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltrosDesdeRequest();

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $f['fecha_inicio'] . ' al ' . $f['fecha_fin'];

        $datos = $this->service->generar($idEmpresa, $f['fecha_inicio'], $f['fecha_fin'], $f['id_centro_costo'], $f['id_proyecto'], $f['nivel']);
        $this->service->exportarPdf($datos, $empresaNombre, $rangoFechas);
    }
}

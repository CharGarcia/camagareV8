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
            // Modal reutilizable de Plan de Cuentas (abrir/editar una cuenta desde el reporte)
            'centros' => $centrosCosto,
            'permPlanCuentas' => \App\Helpers\Permisos::porRuta('modulos/plan-cuentas'),
            'rutaModulo' => $this->getRutaModulo(),
            'perm' => $perm,
            'hayGrupoRuc' => count($idsGrupoRuc) > 1,
            // Selector "Establecimiento": los del mismo RUC a los que el usuario tiene acceso.
            'establecimientosRuc' => count($idsGrupoRuc) > 1 ? $this->establecimientosAccesibles() : [],
            'idEmpresaActual' => $idEmpresa,
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

            // Desde la matriz también se avisa lo que falta generar en los demás establecimientos
            // del RUC (solo el aviso: cada uno los genera entrando a su propio establecimiento).
            $empresaRepo = new \App\repositories\modulos\EmpresaRepository();
            $idsOtros = array_values(array_diff(
                $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) ($_SESSION['id_usuario'] ?? 0)),
                [$idEmpresa]
            ));

            $conteos = $sincronizador->contarPendientesEmpresas(array_merge([$idEmpresa], $idsOtros));
            $etiquetas = $idsOtros ? $empresaRepo->getEtiquetasEstablecimiento($idsOtros) : [];
            $otros = [];
            foreach ($idsOtros as $idOtro) {
                if (($conteos[$idOtro] ?? 0) > 0) {
                    $otros[] = [
                        'etiqueta'   => $etiquetas[$idOtro] ?? ('Empresa ' . $idOtro),
                        'pendientes' => $conteos[$idOtro],
                    ];
                }
            }
            usort($otros, fn ($a, $b) => strcmp($a['etiqueta'], $b['etiqueta']));

            echo json_encode([
                'ok'         => true,
                'pendientes' => $conteos[$idEmpresa] ?? 0,
                'otros'      => $otros,
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
            $datos = $this->reporteSegunEstablecimiento('resultados');

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
            $datos = $this->reporteSegunEstablecimiento('situacion');

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
            $datos = $this->reporteSegunEstablecimiento('resultados_periodos');

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
            $datos = $this->reporteSegunEstablecimiento('situacion_periodos');

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function exportar(): void
    {
        $this->requireLeer();
        $tipo = $_GET['tipo'] ?? 'resultados';
        $formato = $_GET['formato'] ?? 'excel';
        // Los archivos son de UN establecimiento (el elegido en pantalla, ya validado).
        try {
            $f = $this->filtrosReporte();
            if ($f['todos']) {
                $this->empresaReporte(); // lanza el mensaje de "elija un establecimiento"
            }
        } catch (\Throwable $th) {
            http_response_code(400);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => $th->getMessage()]);
            } else {
                echo htmlspecialchars($th->getMessage());
            }
            return;
        }
        $idEmpresa = $f['id_empresa'];
        $fechaInicio = $f['fecha_inicio'];
        $fechaFin = $f['fecha_fin'];
        $idCentroCosto = $f['centro_costo'];
        $idProyecto = $f['proyecto'];
        $nivel = $f['nivel'];

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $fechaInicio . ' al ' . $fechaFin;

        if ($tipo === 'resultados_periodos' || $tipo === 'situacion_periodos') {
            $datos = $tipo === 'resultados_periodos'
                ? $this->service->getEstadoResultadosPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel)
                : $this->service->getEstadoSituacionFinancieraPorPeriodos($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            if ($formato === 'pdf') {
                $this->service->exportarPdfPorPeriodos($tipo, $datos, $empresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
            } else {
                $this->service->exportarExcelPorPeriodos($tipo, $datos, $empresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
            }
            return;
        }

        // TXT Supercías (ESF/ERI/ECP/EFE): mismos valores que el reporte en pantalla (fechas,
        // centro de costo y proyecto; solo asientos contabilizados del ambiente activo).
        if (str_starts_with($formato, 'supercias_')) {
            try {
                $this->service->exportarSupercias(substr($formato, 10), $idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            } catch (\Throwable $th) {
                \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
                http_response_code(400);
                echo htmlspecialchars($th->getMessage());
            }
            return;
        }

        if ($tipo === 'resultados') {
            $datos = $this->service->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        } else {
            $datos = $this->service->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        }

        if ($formato === 'pdf') {
            $this->service->exportarPdf($tipo, $datos, $empresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        } else if ($formato === 'sri') {
            $ruc = $empresa['ruc'] ?? '';
            $this->service->exportarSri($tipo, $datos, $empresaNombre, $rangoFechas, $ruc);
        } else {
            $this->service->exportarExcel($tipo, $datos, $empresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        }
    }

    /**
     * Vista previa del Estado de Cambios en el Patrimonio (Supercías): matriz fila × columna ya
     * evaluada con los mismos filtros de pantalla, para revisar antes de descargar el TXT.
     */
    public function generarEcpAjax(): void
    {
        try {
            $this->requireLeer();
            $f = $this->filtrosReporte();
            if ($f['todos']) {
                $this->empresaReporte(); // lanza el mensaje de "elija un establecimiento"
            }
            [$idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto] =
                [$f['id_empresa'], $f['fecha_inicio'], $f['fecha_fin'], $f['centro_costo'], $f['proyecto']];

            $datos = $this->service->getEcpMatriz($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Vista previa del Estado de Flujos de Efectivo (Supercías): casilleros evaluados, cuadres y
     * detalle de cada asiento de efectivo con su clasificación.
     */
    public function generarEfeAjax(): void
    {
        try {
            $this->requireLeer();
            $f = $this->filtrosReporte();
            if ($f['todos']) {
                $this->empresaReporte(); // lanza el mensaje de "elija un establecimiento"
            }
            [$idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto] =
                [$f['id_empresa'], $f['fecha_inicio'], $f['fecha_fin'], $f['centro_costo'], $f['proyecto']];

            $datos = $this->service->getEfeDetalle($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    /**
     * Diagnóstico Supercías: qué falta mapear o corregir para que los TXT (ESF/ERI/ECP/EFE)
     * salgan completos y cuadrados, con sugerencias de casillero por cuenta.
     */
    public function diagnosticoSuperciasAjax(): void
    {
        try {
            $this->requireLeer();
            $f = $this->filtrosReporte();
            if ($f['todos']) {
                $this->empresaReporte(); // lanza el mensaje de "elija un establecimiento"
            }
            [$idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto] =
                [$f['id_empresa'], $f['fecha_inicio'], $f['fecha_fin'], $f['centro_costo'], $f['proyecto']];

            $diag = new \App\Services\modulos\SuperciasDiagnosticoService(new EstadosFinancierosRepository(), $this->service);
            $datos = $diag->diagnosticar($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
            // Corregir edita el plan de cuentas de la empresa ACTIVA: no aplica a otro establecimiento.
            $datos['puede_corregir'] = $idEmpresa === (int) $_SESSION['id_empresa']
                && \App\Helpers\Permisos::puedeActualizar('modulos/plan-cuentas');
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/estados-financieros';
    }

    /**
     * Establecimientos del mismo RUC que el usuario puede consultar aquí (id_empresa =>
     * "001 - Nombre"), ordenados por código de establecimiento. Misma regla de acceso que el
     * Consolidado por RUC (EmpresaRepository::getIdsGrupoRucAccesible): todos para nivel 3, las
     * empresas asignadas para el resto. Con un solo establecimiento devuelve solo la activa.
     *
     * @return array<int,string>
     */
    private function establecimientosAccesibles(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo = new \App\repositories\modulos\EmpresaRepository();
        $ids = $repo->getIdsGrupoRucAccesible($idEmpresa, (int) $_SESSION['id_usuario']);
        $etiquetas = $repo->getEtiquetasEstablecimiento($ids);
        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = $etiquetas[$id] ?? ('Empresa ' . $id);
        }
        asort($out, SORT_NATURAL);
        return $out;
    }

    /** ¿Se pidió "Todos los establecimientos" (?id_establecimiento=todos)? */
    private function pideTodos(): bool
    {
        return ($_GET['id_establecimiento'] ?? '') === 'todos';
    }

    /**
     * Empresa (establecimiento) cuyo reporte se pide en ?id_establecimiento=. Vacío = la empresa
     * activa. Cualquier otro id debe ser del mismo RUC y estar asignado al usuario; si no, se
     * rechaza (nunca se cae en silencio a otra empresa).
     */
    private function empresaReporte(): int
    {
        $idActiva = (int) $_SESSION['id_empresa'];
        if ($this->pideTodos()) {
            throw new Exception('Esta opción no está disponible para «Todos los establecimientos». Elija un establecimiento.');
        }
        $pedido = (int) ($_GET['id_establecimiento'] ?? 0);
        if ($pedido <= 0 || $pedido === $idActiva) {
            return $idActiva;
        }
        if (!array_key_exists($pedido, $this->establecimientosAccesibles())) {
            throw new Exception('No tiene acceso a ese establecimiento.');
        }
        return $pedido;
    }

    /**
     * Filtros comunes del reporte. Centro de costo y proyecto son catálogos propios de cada
     * empresa: los del selector son de la empresa activa, así que no se aplican al consultar otro
     * establecimiento.
     *
     * @return array{todos:bool, id_empresa:int, fecha_inicio:string, fecha_fin:string, centro_costo:?int, proyecto:?int, nivel:int}
     */
    private function filtrosReporte(): array
    {
        $todos = $this->pideTodos();
        $idEmpresa = $todos ? (int) $_SESSION['id_empresa'] : $this->empresaReporte();
        $propia = !$todos && $idEmpresa === (int) $_SESSION['id_empresa'];

        return [
            'todos'        => $todos,
            'id_empresa'   => $idEmpresa,
            'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-01-01'),
            'fecha_fin'    => $_GET['fecha_fin'] ?? date('Y-12-31'),
            'centro_costo' => $propia && !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null,
            'proyecto'     => $propia && !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null,
            'nivel'        => !empty($_GET['nivel']) ? (int) $_GET['nivel'] : 5,
        ];
    }

    /** Datos del reporte $tipo para el establecimiento elegido, o de cada uno si se pidió "Todos". */
    private function reporteSegunEstablecimiento(string $tipo): array
    {
        $f = $this->filtrosReporte();
        if ($f['todos']) {
            return $this->service->getReportePorEstablecimientos($tipo, $this->establecimientosAccesibles(), $f['fecha_inicio'], $f['fecha_fin'], $f['nivel']);
        }
        return $this->service->getReporte($tipo, $f['id_empresa'], $f['fecha_inicio'], $f['fecha_fin'], $f['centro_costo'], $f['proyecto'], $f['nivel']);
    }

    /**
     * "Cuadre con módulos": saldo de cada cuenta bancaria, Cuentas por Cobrar, Cuentas por
     * Pagar e Inventarios según el módulo y según la contabilidad (solo lectura).
     */
    public function cuadreModulosAjax(): void
    {
        $this->requireLeer();
        $this->responderJsonComprobacion(fn (int $idEmpresa, string $desde, string $hasta) =>
            (new \App\Services\modulos\CuadreModulosService())->resumen($idEmpresa, $desde, $hasta));
    }

    /** Detalle documento por documento de una fila del cuadre (?modulo=&forma=). */
    public function cuadreModuloDetalleAjax(): void
    {
        $this->requireLeer();
        $modulo = (string) ($_GET['modulo'] ?? '');
        $idForma = (int) ($_GET['forma'] ?? 0);
        $this->responderJsonComprobacion(fn (int $idEmpresa, string $desde, string $hasta) =>
            (new \App\Services\modulos\CuadreModulosService())->detalle($idEmpresa, $modulo, $idForma, $desde, $hasta));
    }

    public function generarMayorAuxiliar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            // El mayor es de una cuenta de UN establecimiento: en "Todos", la vista manda el de la sección.
            $f = $this->filtrosReporte();
            $idEmpresa = $f['id_empresa'];
            $codigoCuenta = $_GET['codigo_cuenta'] ?? '';
            $fechaInicio = $f['fecha_inicio'];
            $fechaFin = $f['fecha_fin'];
            $idCentroCosto = $f['centro_costo'];
            $idProyecto = $f['proyecto'];

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
                $this->empresaReporte() // el del mayor abierto (validado como del mismo RUC)
            );
            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

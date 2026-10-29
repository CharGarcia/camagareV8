<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\repositories\modulos\AlumnoNivelRepository;
use App\Rules\modulos\AlumnoNivelRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AlumnoNivelService;

class AlumnosNivelesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/alumnos-niveles';
    private const ORDEN_DEFECTO = 'orden';
    private AlumnoNivelService $service;

    public function __construct()
    {
        parent::__construct();
        $repo = new AlumnoNivelRepository();
        $rules = new AlumnoNivelRules();
        $logService = new LogSistemaService();
        $this->service = new AlumnoNivelService($repo, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar  = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        // Orden múltiple (Shift+clic): la vista lo manda como `orden=col:DIR,col:DIR`.
        $orden   = OrdenListado::leer($prefsVista, self::ORDEN_DEFECTO);
        $perPage = $this->porPagina();

        $result = $this->listar($idEmpresa, $buscar, $page, $perPage, $orden);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.alumnos_niveles.index', [
            'titulo'         => 'Niveles / Cursos',
            'perm'           => $perm,
            'rutaModulo'     => self::RUTA_MODULO,
            'rows'           => $result['rows'],
            'total'          => $total,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'perPage'        => $perPage,
            'buscar'         => $buscar,
            'ordenJson'      => OrdenListado::aJson($orden),
            'ordenParam'     => OrdenListado::aCadena($orden),
            'vistaConfig'    => $prefsVista,
            // Selects del modal de filtros: solo valores usados en la empresa.
            'opcionesFiltro' => $this->service->getOpcionesFiltroListado($idEmpresa),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar  = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $orden   = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), self::ORDEN_DEFECTO);
        $perPage = $this->porPagina();

        $result = $this->listar($idEmpresa, $buscar, $page, $perPage, $orden);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        require MVC_APP . '/views/modulos/alumnos_niveles/_filas.php';
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaNivelAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaNivelAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';

        $qs = '?b=' . urlencode($buscar) . '&orden=' . urlencode(OrdenListado::aCadena($orden));
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf' . $qs,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel' . $qs,
        ]);
        exit;
    }

    /**
     * Listado corto para el select del modal de Alumno (id, nombre).
     */
    public function paraSelectAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getParaSelect($idEmpresa)]);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = (int) $_SESSION['id_empresa'];
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso creado correctamente.', 'id' => $id, 'nombre' => mb_strtoupper(trim($data['nombre']), 'UTF-8')]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->recogerDatosFormulario();
        $data['id_empresa'] = $idEmpresa;
        $data['id_usuario'] = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID de nivel/curso no válido.');
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso actualizado correctamente.', 'id' => $id, 'nombre' => mb_strtoupper(trim($data['nombre']), 'UTF-8')]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de nivel/curso no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Nivel/curso eliminado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rows = $this->filasExportacion($idEmpresa);

        try {
            $nombreEmpresa = (new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial; font-size: 9pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: left; }
                td { border: 1px solid #ccc; padding: 6px; }
                h1 { margin: 0; font-size: 14pt; text-align: center; }
                h2 { margin: 5px 0 15px 0; font-size: 10pt; text-align: center; color: #666; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                <h2>Listado de Niveles / Cursos</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Orden</th>
                            <th style="width: 63%">Nombre</th>
                            <th style="width: 12%">Alumnos</th>
                            <th style="width: 13%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="width: 12%; text-align: right"><?= (int)($r['orden'] ?? 0) ?></td>
                                <td style="width: 63%"><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></td>
                                <td style="width: 12%; text-align: right"><?= (int)($r['alumnos'] ?? 0) ?></td>
                                <td style="width: 13%"><?= htmlspecialchars(ucfirst((string)($r['estado'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Niveles_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rows = $this->filasExportacion($idEmpresa);

        try {
            $nombreEmpresa = (new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '';

            $headers = ['Orden', 'Nombre', 'Alumnos vigentes', 'Estado', 'Fecha Creación'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (int)($r['orden'] ?? 0),
                    (string)($r['nombre'] ?? ''),
                    (int)($r['alumnos'] ?? 0),
                    ucfirst((string)($r['estado'] ?? '')),
                    !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '',
                ];
            }

            (new \App\Services\ReportService())->exportToExcel('Niveles', $headers, $exportData, 'Listado de Niveles / Cursos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    /**
     * Listado con el alcance del usuario: sin acceso total solo ve los niveles que él creó.
     */
    private function listar(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden): array
    {
        $idUsuarioFiltro = empty($this->getPermisos()['todo']) ? (int)$_SESSION['id_usuario'] : null;
        return $this->service->getListado(
            $idEmpresa,
            $buscar,
            $page,
            $perPage,
            OrdenListado::primeraCol($orden, self::ORDEN_DEFECTO),
            OrdenListado::primeraDir($orden),
            $idUsuarioFiltro,
            $orden
        );
    }

    /**
     * Todas las filas del filtro y orden de pantalla (el enlace lleva `b` y `orden`;
     * sin `orden` se respeta la preferencia guardada del usuario).
     */
    private function filasExportacion(int $idEmpresa): array
    {
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $orden  = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO), self::ORDEN_DEFECTO);
        return $this->listar($idEmpresa, $buscar, 1, 0, $orden)['rows'];
    }

    private function recogerDatosFormulario(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'orden'  => (int) ($_POST['orden'] ?? 0),
            'estado' => trim($_POST['estado'] ?? 'activo'),
        ];
    }
}

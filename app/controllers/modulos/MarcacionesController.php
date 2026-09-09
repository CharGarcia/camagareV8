<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\MarcacionRepository;
use App\repositories\modulos\BiometriaRepository;
use App\repositories\modulos\AsistenciaPuntoRepository;
use App\Rules\modulos\MarcacionRules;
use App\Services\modulos\MarcacionService;
use App\Services\LogSistemaService;
use App\Helpers\PreferenciasHelper;

/**
 * Módulo Marcaciones (bitácora de marcaciones de asistencia).
 * Separado de Control de Asistencia: aquí solo se consulta/depura el registro
 * de marcaciones. La marcación en sí la crea el empleado desde su celular (PWA).
 */
class MarcacionesController extends BaseModuloController
{
    private MarcacionService $service;
    private const RUTA_MODULO = 'modulos/marcaciones';

    public function __construct()
    {
        parent::__construct();
        $log = new LogSistemaService();
        $this->service = new MarcacionService(
            new MarcacionRepository(),
            new BiometriaRepository(),
            new AsistenciaPuntoRepository(),
            new MarcacionRules(),
            $log
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_hora');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        // Las filas se arman con el mismo renderFila() que usa searchAjax: así la
        // primera página y las siguientes no pueden divergir en cómo se pintan el
        // punto, la distancia o los badges.
        $filasHtml = '';
        foreach ($result['rows'] as $r) {
            $filasHtml .= $this->renderFila($r, $perm);
        }

        $this->viewWithLayout('layouts.main', 'modulos.marcaciones.index', [
            'titulo'     => 'Marcaciones',
            'perm'       => $perm,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $result['rows'],
            'filasHtml'  => $filasHtml,
            'total'      => $result['total'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig' => $prefsVista,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_hora');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No hay marcaciones registradas.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFila($r, $perm);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
        ]);
        exit;
    }

    private function renderFila(array $r, array $perm): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $fecha = $r['fecha_hora'] ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_hora'])) : '—';

        $tipoMap = ['entrada' => 'success', 'salida' => 'danger', 'inicio_break' => 'warning', 'fin_break' => 'info'];
        $tipoColor = $tipoMap[$r['tipo']] ?? 'secondary';
        $tipoBadge = '<span class="badge bg-' . $tipoColor . ' bg-opacity-10 text-' . $tipoColor . ' border border-' . $tipoColor . ' border-opacity-25">' . $h(ucfirst(str_replace('_', ' ', (string) $r['tipo']))) . '</span>';

        $estado = $r['estado'] ?? 'valida';
        $estadoMap = ['valida' => 'success', 'sospechosa' => 'warning', 'anulada' => 'secondary'];
        $estadoColor = $estadoMap[$estado] ?? 'secondary';
        $estadoBadge = '<span class="badge bg-' . $estadoColor . ' bg-opacity-10 text-' . $estadoColor . ' border border-' . $estadoColor . ' border-opacity-25">' . $h(ucfirst($estado)) . '</span>';

        $btnDel = $perm['eliminar']
            ? '<button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarMarcacion(' . (int) $r['id'] . ')" title="Eliminar"><i class="bi bi-trash"></i></button>'
            : '';

        return '<tr>'
            . '<td class="ps-3 fw-medium" data-col="empleado">' . $h($r['empleado_nombre']) . '</td>'
            . '<td data-col="punto" class="small">' . $this->celdaPunto($r) . '</td>'
            . '<td data-col="fecha">' . $h($fecha) . '</td>'
            . '<td class="text-center" data-col="tipo">' . $tipoBadge . '</td>'
            . '<td class="text-center" data-col="distancia">' . $this->celdaDistancia($r) . '</td>'
            . '<td class="text-center" data-col="estado">' . $estadoBadge . '</td>'
            . '<td class="text-center pe-3">' . $btnDel . '</td>'
            . '</tr>';
    }

    /**
     * Celda "Punto". Una marca sin punto no es lo mismo según cómo se creó: la
     * manual (corrección desde el panel) nunca tuvo uno, así que se dice; el resto
     * queda como dato faltante.
     */
    private function celdaPunto(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        if (!empty($r['punto_nombre'])) {
            return '<span class="text-body">' . $h($r['punto_nombre']) . '</span>';
        }
        if (($r['metodo'] ?? '') === 'manual') {
            return '<span class="text-muted fst-italic" title="Marcación registrada a mano desde el panel">Registro manual</span>';
        }
        return '<span class="text-muted">—</span>';
    }

    /**
     * Celda "Distancia": metros entre el celular y el punto. Cuando no hay número
     * se explica por qué (el punto no tiene coordenadas, o el celular no envió su
     * ubicación), que es justo lo que el supervisor necesita para actuar.
     */
    private function celdaDistancia(array $r): string
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        if (($r['distancia_m'] ?? null) !== null && $r['distancia_m'] !== '') {
            $m     = (int) $r['distancia_m'];
            $radio = isset($r['punto_radio_m']) ? (int) $r['punto_radio_m'] : 0;
            $fuera = $radio > 0 && $m > $radio;
            $title = $radio > 0 ? 'Radio permitido del punto: ' . $radio . ' m' : '';
            $clase = $fuera ? 'text-danger fw-medium' : 'text-body';
            return '<span class="' . $clase . '" title="' . $h($title) . '">' . $m . ' m'
                . ($fuera ? ' <i class="bi bi-exclamation-triangle"></i>' : '') . '</span>';
        }

        if (empty($r['id_punto'])) {
            return '<span class="text-muted">—</span>';
        }
        if (($r['punto_latitud'] ?? null) === null || ($r['punto_longitud'] ?? null) === null) {
            return '<span class="text-muted" title="El punto de servicio no tiene coordenadas configuradas">sin ubicación del punto</span>';
        }
        return '<span class="text-muted" title="El dispositivo no envió su ubicación al marcar">sin GPS</span>';
    }

    /** Datos para el modal de registro manual: empleados activos + puntos de servicio. */
    public function datosManualAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $empleados = (new \App\repositories\modulos\EmpleadoRepository())->getActivosParaSelect($idEmpresa);

            // Los puntos son opcionales en la marca manual, pero sin ellos la bitácora
            // no dice dónde estuvo el empleado; se ofrecen para poder completarlo.
            $puntos = [];
            try {
                $puntos = (new AsistenciaPuntoRepository())
                    ->getListado($idEmpresa, '', 1, 0, 'nombre', 'ASC')['rows'];
            } catch (\Throwable $e) {
                $puntos = []; // módulo de puntos aún no desplegado: no bloquea el registro
            }

            echo json_encode(['ok' => true, 'empleados' => $empleados, 'puntos' => $puntos]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Registro manual de una marcación (p. ej. la salida que faltó). La hace un
     * usuario del sistema con permiso de actualizar; recalcula la jornada del día.
     */
    public function registrarManualAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
            $tipo       = trim($_POST['tipo'] ?? '');
            $fecha      = trim($_POST['fecha'] ?? '');
            $hora       = trim($_POST['hora'] ?? '');
            $obs        = trim($_POST['observacion'] ?? '');

            if ($idEmpleado <= 0) throw new \Exception('Seleccione un empleado.');
            if ($fecha === '' || $hora === '') throw new \Exception('Indique la fecha y la hora.');

            $ts = strtotime($fecha . ' ' . $hora);
            if ($ts === false) throw new \Exception('Fecha u hora no válidas.');
            if ($ts > time() + 60) throw new \Exception('La marcación no puede ser futura.');

            $id = $this->service->marcarManual([
                'id_empleado' => $idEmpleado,
                'id_punto'    => ($_POST['id_punto'] ?? '') !== '' ? (int) $_POST['id_punto'] : null,
                'tipo'        => $tipo,
                'fecha_hora'  => date('Y-m-d H:i:s', $ts),
                'observacion' => $obs !== '' ? $obs : 'Registro manual desde el panel.',
            ], $idEmpresa, $idUsuario);

            echo json_encode(['ok' => true, 'id' => $id, 'msg' => 'Marcación registrada. Jornada recalculada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORTACIONES (respetan el buscador y el orden del listado)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trae el listado completo (sin paginar) con los mismos filtros que la
     * pantalla, para que el PDF y el Excel salgan con lo que el usuario ve.
     */
    private function getListadoParaExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_hora');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $data['buscar'] = $buscar;
        return $data;
    }

    /** Texto de la distancia para los exports (sin HTML). */
    private function distanciaTexto(array $r): string
    {
        if (($r['distancia_m'] ?? null) !== null && $r['distancia_m'] !== '') {
            $m     = (int) $r['distancia_m'];
            $radio = isset($r['punto_radio_m']) ? (int) $r['punto_radio_m'] : 0;
            return $m . ' m' . ($radio > 0 && $m > $radio ? ' (fuera del radio)' : '');
        }
        if (empty($r['id_punto'])) {
            return '';
        }
        return (($r['punto_latitud'] ?? null) === null || ($r['punto_longitud'] ?? null) === null)
            ? 'sin ubicación del punto'
            : 'sin GPS';
    }

    /** Nombre del punto para los exports (sin HTML). */
    private function puntoTexto(array $r): string
    {
        if (!empty($r['punto_nombre'])) {
            return (string) $r['punto_nombre'];
        }
        return (($r['metodo'] ?? '') === 'manual') ? 'Registro manual' : '';
    }

    private function nombreEmpresa(int $idEmpresa): string
    {
        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            return (string) ($empresa['nombre'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        $data      = $this->getListadoParaExport();
        $rows      = $data['rows'];
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $headers = ['Empleado', 'Identificación', 'Punto', 'Fecha/Hora', 'Tipo', 'Método', 'Distancia', 'Latitud', 'Longitud', 'Estado', 'Observación'];
        $exportData = [];
        foreach ($rows as $r) {
            $exportData[] = [
                (string) ($r['empleado_nombre'] ?? ''),
                (string) ($r['empleado_identificacion'] ?? ''),
                $this->puntoTexto($r),
                !empty($r['fecha_hora']) ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_hora'])) : '',
                ucfirst(str_replace('_', ' ', (string) ($r['tipo'] ?? ''))),
                ucfirst(str_replace('_', ' ', (string) ($r['metodo'] ?? ''))),
                $this->distanciaTexto($r),
                (string) ($r['latitud'] ?? ''),
                (string) ($r['longitud'] ?? ''),
                ucfirst((string) ($r['estado'] ?? '')),
                (string) ($r['observacion'] ?? ''),
            ];
        }

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $info = [];
            if ($data['buscar'] !== '') {
                $info['Filtro aplicado'] = $data['buscar'];
            }
            $info['Registros'] = (string) count($exportData);

            (new \App\Services\ReportService())->exportToExcel(
                'Marcaciones',
                $headers,
                $exportData,
                'Marcaciones',
                $this->nombreEmpresa($idEmpresa),
                $info
            );
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();

        $data      = $this->getListadoParaExport();
        $rows      = $data['rows'];
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $nombreEmpresa = $this->nombreEmpresa($idEmpresa) ?: 'MARCACIONES';
        $buscar = $data['buscar'];

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px; text-align: left; }
                td { border: 1px solid #ccc; padding: 3px; overflow: hidden; word-wrap: break-word; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
                .filtro { font-size: 7pt; color: #666; margin-bottom: 6px; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Marcaciones de asistencia</h2>
                </div>
                <div class="filtro">
                    Generado: <?= date('d-m-Y H:i:s') ?> &nbsp;|&nbsp; Registros: <?= count($rows) ?>
                    <?= $buscar !== '' ? ' &nbsp;|&nbsp; Filtro: ' . htmlspecialchars($buscar) : '' ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 24%">Empleado</th>
                            <th style="width: 11%">Identificación</th>
                            <th style="width: 18%">Punto</th>
                            <th style="width: 15%">Fecha/Hora</th>
                            <th style="width: 10%">Tipo</th>
                            <th style="width: 9%">Método</th>
                            <th style="width: 6%" class="text-center">Dist.</th>
                            <th style="width: 7%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center">No hay marcaciones para los filtros aplicados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($r['empleado_nombre'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($r['empleado_identificacion'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($this->puntoTexto($r)) ?></td>
                                    <td><?= !empty($r['fecha_hora']) ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_hora'])) : '-' ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($r['tipo'] ?? '')))) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($r['metodo'] ?? '')))) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($this->distanciaTexto($r)) ?></td>
                                    <td><?= htmlspecialchars(ucfirst((string) ($r['estado'] ?? ''))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Marcaciones_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Marcación eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

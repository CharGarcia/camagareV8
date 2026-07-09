<?php
/**
 * Controlador LogSistemaConsulta - Auditoría del sistema (solo lectura).
 * Accesible desde la sección Config. Nivel 2+ ve su empresa activa + eventos
 * globales; nivel 3 (superadmin) ve absolutamente todo.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Helpers\AuditoriaEtiquetas;
use App\Services\LogSistemaConsultaService;

class LogSistemaConsultaController extends Controller
{
    private LogSistemaConsultaService $service;

    /** Columnas de ordenamiento permitidas (deben coincidir con el repositorio). */
    private const COLUMNAS_ORDEN = ['created_at', 'accion', 'tabla', 'usuario', 'empresa', 'id'];

    public function __construct()
    {
        parent::__construct();
        $this->service = new LogSistemaConsultaService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $opciones = $this->service->getOpcionesFiltros($this->getScope());

        $this->viewWithLayout('layouts.main', 'config.log_sistema', [
            'titulo'          => 'Auditoría del sistema',
            'nivel'           => (int) ($_SESSION['nivel'] ?? 1),
            'opcionesFiltros' => $opciones,
        ]);
    }

    public function listarAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));
        $perPage  = 25;

        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'created_at';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }

        $scope   = $this->getScope();
        $filtros = $this->leerFiltros();

        try {
            $result = $this->service->getListado($scope, $buscar, $page, $perPage, $ordenCol, $ordenDir, $filtros);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al cargar la bitácora: ' . $e->getMessage()]);
        }

        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $this->json([
            'ok'         => true,
            'rows'       => $this->renderFilas($rows),
            'pagination' => $this->renderPaginacion($page, $totalPages),
            'info'       => "$from-$to / $total",
        ]);
    }

    public function detalleAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
        }

        $detalle = $this->service->getDetalle($id, $this->getScope());
        if ($detalle === null) {
            $this->json(['ok' => false, 'error' => 'Registro no encontrado o fuera de su alcance.']);
        }

        $this->json(['ok' => true, 'html' => $this->renderDetalle($detalle)]);
    }

    public function exportarExcel(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $data = $this->datosExport();

        $fecha = date('Ymd_His');
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="auditoria_sistema_' . $fecha . '.xls"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8 para que Excel respete acentos
        echo $this->tablaExportHtml($data['rows'], $data['truncado'], $data['total']);
        exit;
    }

    public function exportarPdf(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        // El PDF nativo (TCPDF) es más pesado que el .xls: tope más conservador.
        $data = $this->datosExport(2000);

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $empresa   = [];
        if ($idEmpresa > 0) {
            try {
                $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?: [];
            } catch (\Throwable $e) {
                $empresa = [];
            }
        }

        $buscar  = trim($_GET['b'] ?? '');
        $filtros = $this->leerFiltros();

        $desc = [];
        if ($buscar !== '')             $desc[] = 'Texto: ' . $buscar;
        if (!empty($filtros['accion'])) $desc[] = 'Acción: ' . AuditoriaEtiquetas::accion((string) $filtros['accion']);
        if (!empty($filtros['tabla'])) {
            $etMod = $this->service->etiquetaModulo($this->getScope(), (string) $filtros['tabla']);
            if ($etMod !== '') $desc[] = 'Módulo: ' . $etMod;
        }
        if (!empty($filtros['usuario'])) $desc[] = 'Usuario #' . $filtros['usuario'];
        if (!empty($filtros['empresa'])) $desc[] = 'Empresa #' . $filtros['empresa'];

        if (!empty($filtros['desde']) || !empty($filtros['hasta'])) {
            $rango = 'Desde ' . ($filtros['desde'] ?? '—') . ' hasta ' . ($filtros['hasta'] ?? '—');
        } elseif (str_contains($buscar, 'fecha:')) {
            $rango = 'Según filtro de fecha';
        } else {
            $rango = 'Últimos 30 días';
        }

        $meta = [
            'filtro'       => implode('   |   ', $desc),
            'rango'        => $rango,
            'total'        => $data['total'],
            'truncado'     => $data['truncado'],
            'generado_por' => (string) ($_SESSION['nombre'] ?? ''),
        ];

        (new \App\Services\LogSistemaPdfService())->generar($data['rows'], $empresa, $meta, 'I');
        exit;
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /** Lee filtros/orden y trae las filas de exportación respetando el alcance. */
    private function datosExport(int $limit = 10000): array
    {
        $buscar   = trim($_GET['b'] ?? '');
        $ordenCol = trim($_GET['sort'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'created_at';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }
        return $this->service->getParaExportar($this->getScope(), $buscar, $ordenCol, $ordenDir, $limit, $this->leerFiltros());
    }

    /**
     * Lee y sanea los filtros explícitos de la barra de filtros.
     * @return array{usuario:?int,empresa:?int,accion:?string,tabla:?string,desde:?string,hasta:?string}
     */
    private function leerFiltros(): array
    {
        $rgxFecha = '/^\d{4}-\d{2}-\d{2}$/';
        $desde = trim($_GET['fd'] ?? $_POST['fd'] ?? '');
        $hasta = trim($_GET['fh'] ?? $_POST['fh'] ?? '');

        return [
            'usuario' => ((int) ($_GET['fu'] ?? $_POST['fu'] ?? 0)) ?: null,
            'empresa' => ((int) ($_GET['fe'] ?? $_POST['fe'] ?? 0)) ?: null,
            'accion'  => (trim($_GET['fa'] ?? $_POST['fa'] ?? '')) ?: null,
            'tabla'   => (trim($_GET['ft'] ?? $_POST['ft'] ?? '')) ?: null,
            'desde'   => preg_match($rgxFecha, $desde) ? $desde : null,
            'hasta'   => preg_match($rgxFecha, $hasta) ? $hasta : null,
        ];
    }

    /** Tabla HTML compartida por Excel y PDF. */
    private function tablaExportHtml(array $rows, bool $avisoTrunc, int $total): string
    {
        ob_start();
        if ($avisoTrunc) {
            echo '<p style="color:#b00">Nota: se exportaron las primeras ' . count($rows) . ' de ' . $total . ' filas. Acote la búsqueda o el rango de fechas para exportar menos registros.</p>';
        }
        echo '<table><thead><tr>'
            . '<th>Fecha</th><th>Usuario</th><th>Empresa</th><th>Acción</th>'
            . '<th>Módulo</th><th>Registro</th><th>IP</th>'
            . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $fecha    = date('d-m-Y H:i:s', strtotime((string) $r['created_at']));
            $usuario  = ($r['usuario_nombre'] ?? '') !== '' ? $r['usuario_nombre'] : '#' . (int) $r['id_usuario'];
            $empresa  = ($r['empresa_nombre'] ?? '') !== '' ? $r['empresa_nombre'] : ($r['id_empresa'] === null ? 'Global' : '#' . (int) $r['id_empresa']);
            $registro = $r['id_registro'] !== null ? '#' . (int) $r['id_registro'] : '-';
            echo '<tr>'
                . '<td>' . htmlspecialchars($fecha) . '</td>'
                . '<td>' . htmlspecialchars((string) $usuario) . '</td>'
                . '<td>' . htmlspecialchars((string) $empresa) . '</td>'
                . '<td>' . htmlspecialchars(AuditoriaEtiquetas::accion((string) $r['accion'])) . '</td>'
                . '<td>' . htmlspecialchars(AuditoriaEtiquetas::tabla((string) ($r['tabla_afectada'] ?? ''))) . '</td>'
                . '<td>' . htmlspecialchars($registro) . '</td>'
                . '<td>' . htmlspecialchars((string) ($r['ip_usuario'] ?? '-')) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';
        return (string) ob_get_clean();
    }

    /**
     * Alcance del usuario actual para el filtrado multiempresa.
     * @return array{nivel:int,id_empresa:int}
     */
    private function getScope(): array
    {
        return [
            'nivel'      => (int) ($_SESSION['nivel'] ?? 1),
            'id_empresa' => (int) ($_SESSION['id_empresa'] ?? 0),
        ];
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'error' => 'No tiene permisos.'], 403);
            }
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a la auditoría.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }

    /** Badge de color según el tipo de acción. */
    private function badgeAccion(string $accion): string
    {
        $a = strtolower(trim($accion));
        $color = match (true) {
            str_contains($a, 'crear') || str_contains($a, 'insert') || str_contains($a, 'registrar') => 'success',
            str_contains($a, 'actualiz') || str_contains($a, 'editar') || str_contains($a, 'update')  => 'warning',
            str_contains($a, 'elimin') || str_contains($a, 'borrar') || str_contains($a, 'delete')    => 'danger',
            str_contains($a, 'anular')                                                                => 'danger',
            str_contains($a, 'login') || str_contains($a, 'sesion') || str_contains($a, 'ingreso')    => 'primary',
            default                                                                                    => 'secondary',
        };
        $txt = htmlspecialchars(AuditoriaEtiquetas::accion($accion));
        return "<span class=\"badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25 py-1 px-2 small\">{$txt}</span>";
    }

    private function renderFilas(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron registros de auditoría en el rango seleccionado.</td></tr>';
        }

        ob_start();
        foreach ($rows as $r) {
            $fecha    = date('d-m-Y H:i:s', strtotime((string) $r['created_at']));
            $usuario  = $r['usuario_nombre'] !== null && $r['usuario_nombre'] !== ''
                ? htmlspecialchars($r['usuario_nombre'])
                : '<span class="text-muted">#' . (int) $r['id_usuario'] . '</span>';
            $empresa  = $r['empresa_nombre'] !== null && $r['empresa_nombre'] !== ''
                ? htmlspecialchars($r['empresa_nombre'])
                : ($r['id_empresa'] === null ? '<span class="text-muted fst-italic">Global</span>' : '<span class="text-muted">#' . (int) $r['id_empresa'] . '</span>');
            $tabla    = htmlspecialchars(AuditoriaEtiquetas::tabla((string) ($r['tabla_afectada'] ?? '')));
            $registro = $r['id_registro'] !== null ? (int) $r['id_registro'] : '<span class="text-muted">-</span>';
            $ip       = htmlspecialchars((string) ($r['ip_usuario'] ?? '-'));

            echo '<tr role="button" class="log-row align-middle" onclick="LOGSIS_verDetalle(' . (int) $r['id'] . ')">'
                . '<td class="ps-3 text-nowrap small">' . $fecha . '</td>'
                . '<td class="small">' . $usuario . '</td>'
                . '<td class="small">' . $empresa . '</td>'
                . '<td>' . $this->badgeAccion((string) $r['accion']) . '</td>'
                . '<td class="small">' . $tabla . '</td>'
                . '<td class="text-center small">' . $registro . '</td>'
                . '<td class="small text-muted text-nowrap">' . $ip . '</td>'
                . '</tr>';
        }
        return (string) ob_get_clean();
    }

    private function renderPaginacion(int $page, int $totalPages): string
    {
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        return '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="LOGSIS_cambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="LOGSIS_cambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';
    }

    private function renderDetalle(array $d): string
    {
        $fecha   = date('d-m-Y H:i:s', strtotime((string) $d['created_at']));
        $usuario = ($d['usuario_nombre'] ?? '') !== '' ? htmlspecialchars($d['usuario_nombre']) : '#' . (int) $d['id_usuario'];
        $empresa = ($d['empresa_nombre'] ?? '') !== ''
            ? htmlspecialchars($d['empresa_nombre'])
            : ($d['id_empresa'] === null ? 'Global (sin empresa)' : '#' . (int) $d['id_empresa']);
        $tabla    = htmlspecialchars(AuditoriaEtiquetas::tabla((string) ($d['tabla_afectada'] ?? '')));
        $registro = $d['id_registro'] !== null ? (int) $d['id_registro'] : '-';
        $ip       = htmlspecialchars((string) ($d['ip_usuario'] ?? '-'));
        $ua       = htmlspecialchars((string) ($d['user_agent'] ?? '-'));

        ob_start();
        ?>
        <div class="row g-2 small mb-3">
            <div class="col-md-6"><span class="text-muted">Fecha:</span> <strong><?= $fecha ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Usuario:</span> <strong><?= $usuario ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Empresa:</span> <strong><?= $empresa ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Acción:</span> <?= $this->badgeAccion((string) $d['accion']) ?></div>
            <div class="col-md-6"><span class="text-muted">Módulo:</span> <strong><?= $tabla ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Registro:</span> <strong>#<?= $registro ?></strong></div>
            <div class="col-md-6"><span class="text-muted">IP:</span> <?= $ip ?></div>
            <div class="col-12"><span class="text-muted">Navegador:</span> <span class="text-break"><?= $ua ?></span></div>
        </div>

        <h6 class="fw-bold small text-uppercase text-muted border-bottom pb-1 mb-2">Cambios</h6>
        <?php if (empty($d['cambios'])): ?>
            <p class="text-muted small fst-italic mb-3">Sin cambios de campos registrados para esta acción.</p>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Campo</th><th>Antes</th><th>Después</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($d['cambios'] as $c): ?>
                        <tr>
                            <td class="fw-medium"><?= htmlspecialchars((string) ($c['campo'] ?? '')) ?></td>
                            <td class="text-danger"><?= htmlspecialchars((string) ($c['antes'] ?? '-')) ?></td>
                            <td class="text-success"><?= htmlspecialchars((string) ($c['despues'] ?? '-')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($d['antes_json'] !== null || $d['despues_json'] !== null): ?>
        <div class="accordion accordion-flush border rounded" id="accLogJson">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#logJsonCrudo">
                        <i class="bi bi-braces me-2"></i> Ver datos crudos (JSON)
                    </button>
                </h2>
                <div id="logJsonCrudo" class="accordion-collapse collapse" data-bs-parent="#accLogJson">
                    <div class="accordion-body">
                        <?php if ($d['antes_json'] !== null): ?>
                            <div class="text-muted small mb-1">datos_anteriores</div>
                            <pre class="bg-light border rounded p-2 small mb-3" style="max-height:220px;overflow:auto;"><?= htmlspecialchars($d['antes_json']) ?></pre>
                        <?php endif; ?>
                        <?php if ($d['despues_json'] !== null): ?>
                            <div class="text-muted small mb-1">datos_nuevos</div>
                            <pre class="bg-light border rounded p-2 small mb-0" style="max-height:220px;overflow:auto;"><?= htmlspecialchars($d['despues_json']) ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}

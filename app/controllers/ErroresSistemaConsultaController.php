<?php
/**
 * Controlador ErroresSistemaConsulta - Registro de errores del sistema (solo lectura).
 * Tarjeta en la sección Config, EXCLUSIVA de nivel 3 (superadministrador).
 * Ve todos los errores del sistema (no filtra por empresa).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\ErroresSistemaRepository;

class ErroresSistemaConsultaController extends Controller
{
    private ErroresSistemaRepository $repo;

    private const COLUMNAS_ORDEN = ['created_at', 'tipo', 'sql_state', 'ruta', 'id'];

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ErroresSistemaRepository();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $this->viewWithLayout('layouts.main', 'config.errores_sistema', [
            'titulo' => 'Errores del sistema',
        ]);
    }

    public function listarAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

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

        try {
            $result = $this->repo->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al cargar los errores: ' . $e->getMessage()]);
            return;
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
        $this->requireNivel(3);

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        $d = $this->repo->getPorId($id);
        if ($d === null) {
            $this->json(['ok' => false, 'error' => 'Registro no encontrado.']);
            return;
        }

        $this->json(['ok' => true, 'html' => $this->renderDetalle($d)]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'error' => 'No tiene permisos.'], 403);
                return;
            }
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a los errores del sistema.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }

    /** Recorte seguro en UTF-8 sin depender de la extensión mbstring. */
    private function recortar(string $s, int $max): string
    {
        if ($s === '' || $max <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            $out = mb_substr($s, 0, $max, 'UTF-8');
        } else {
            $out = preg_replace('/^((?:.){0,' . $max . '}).*$/us', '$1', $s);
            if ($out === null) {
                $out = substr($s, 0, $max);
            }
        }
        return $out !== $s ? $out . '…' : $out;
    }

    private function badgeTipo(string $tipo): string
    {
        $t = strtolower(trim($tipo));
        $color = match ($t) {
            'fatal'     => 'danger',
            'excepcion' => 'warning',
            'manual'    => 'secondary',
            default     => 'secondary',
        };
        $txt = htmlspecialchars(ucfirst($tipo));
        return "<span class=\"badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25 py-1 px-2 small\">{$txt}</span>";
    }

    private function renderFilas(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted">No se han registrado errores en el rango seleccionado.</td></tr>';
        }

        ob_start();
        foreach ($rows as $r) {
            $fecha   = date('d-m-Y H:i:s', strtotime((string) $r['created_at']));
            $usuario = ($r['usuario_nombre'] ?? '') !== ''
                ? htmlspecialchars((string) $r['usuario_nombre'])
                : ($r['id_usuario'] === null ? '<span class="text-muted fst-italic">—</span>' : '<span class="text-muted">#' . (int) $r['id_usuario'] . '</span>');
            $sqlState = ($r['sql_state'] ?? '') !== ''
                ? '<code class="text-danger">' . htmlspecialchars((string) $r['sql_state']) . '</code>'
                : '<span class="text-muted">-</span>';
            $ruta    = ($r['ruta'] ?? '') !== '' ? htmlspecialchars((string) $r['ruta']) : '<span class="text-muted">-</span>';
            // Mensaje recortado (el completo va en el detalle y en el title).
            $msgFull = (string) $r['mensaje'];
            $msg     = htmlspecialchars($this->recortar($msgFull, 140));

            echo '<tr role="button" class="err-row align-middle" onclick="ERRSIS_verDetalle(' . (int) $r['id'] . ')">'
                . '<td class="ps-3 text-nowrap small">' . $fecha . '</td>'
                . '<td>' . $this->badgeTipo((string) $r['tipo']) . '</td>'
                . '<td class="text-center small">' . $sqlState . '</td>'
                . '<td class="small">' . $ruta . '</td>'
                . '<td class="small text-truncate" style="max-width:420px;" title="' . htmlspecialchars($msgFull) . '">' . $msg . '</td>'
                . '<td class="small text-muted">' . $usuario . '</td>'
                . '</tr>';
        }
        return (string) ob_get_clean();
    }

    private function renderPaginacion(int $page, int $totalPages): string
    {
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        return '<div class="btn-group btn-group-sm">'
            . '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="ERRSIS_cambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
            . '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="ERRSIS_cambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
            . '</div>';
    }

    private function renderDetalle(array $d): string
    {
        $fecha   = date('d-m-Y H:i:s', strtotime((string) $d['created_at']));
        $usuario = ($d['usuario_nombre'] ?? '') !== '' ? htmlspecialchars((string) $d['usuario_nombre']) : ($d['id_usuario'] === null ? '—' : '#' . (int) $d['id_usuario']);
        $empresa = ($d['empresa_nombre'] ?? '') !== '' ? htmlspecialchars((string) $d['empresa_nombre']) : ($d['id_empresa'] === null ? 'Global (sin empresa)' : '#' . (int) $d['id_empresa']);

        ob_start();
        ?>
        <div class="row g-2 small mb-3">
            <div class="col-md-6"><span class="text-muted">Fecha:</span> <strong><?= $fecha ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Tipo:</span> <?= $this->badgeTipo((string) $d['tipo']) ?></div>
            <div class="col-md-6"><span class="text-muted">Clase / error:</span> <strong><?= htmlspecialchars((string) ($d['clase'] ?? '-')) ?></strong></div>
            <div class="col-md-6"><span class="text-muted">SQLSTATE:</span> <?= ($d['sql_state'] ?? '') !== '' ? '<code class="text-danger">' . htmlspecialchars((string) $d['sql_state']) . '</code>' : '-' ?></div>
            <div class="col-md-6"><span class="text-muted">Usuario:</span> <strong><?= $usuario ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Empresa:</span> <strong><?= $empresa ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Ruta:</span> <strong><?= htmlspecialchars((string) ($d['ruta'] ?? '-')) ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Acción:</span> <strong><?= htmlspecialchars((string) ($d['accion'] ?? '-')) ?></strong></div>
            <div class="col-md-8"><span class="text-muted">Archivo:</span> <span class="text-break"><?= htmlspecialchars((string) ($d['archivo'] ?? '-')) ?></span></div>
            <div class="col-md-4"><span class="text-muted">Línea:</span> <strong><?= $d['linea'] !== null ? (int) $d['linea'] : '-' ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Método:</span> <strong><?= htmlspecialchars((string) ($d['metodo_http'] ?? '-')) ?></strong></div>
            <div class="col-md-6"><span class="text-muted">IP:</span> <?= htmlspecialchars((string) ($d['ip_usuario'] ?? '-')) ?></div>
            <div class="col-12"><span class="text-muted">URL:</span> <span class="text-break"><?= htmlspecialchars((string) ($d['url'] ?? '-')) ?></span></div>
            <div class="col-12"><span class="text-muted">Navegador:</span> <span class="text-break"><?= htmlspecialchars((string) ($d['user_agent'] ?? '-')) ?></span></div>
        </div>

        <h6 class="fw-bold small text-uppercase text-muted border-bottom pb-1 mb-2">Mensaje</h6>
        <pre class="bg-light border rounded p-2 small mb-3" style="max-height:180px;overflow:auto;white-space:pre-wrap;"><?= htmlspecialchars((string) $d['mensaje']) ?></pre>

        <?php if (($d['traza'] ?? '') !== ''): ?>
        <div class="accordion accordion-flush border rounded" id="accErrTraza">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#errTrazaBody">
                        <i class="bi bi-list-nested me-2"></i> Ver traza completa (stack trace)
                    </button>
                </h2>
                <div id="errTrazaBody" class="accordion-collapse collapse" data-bs-parent="#accErrTraza">
                    <div class="accordion-body">
                        <pre class="bg-light border rounded p-2 small mb-0" style="max-height:300px;overflow:auto;white-space:pre-wrap;"><?= htmlspecialchars((string) $d['traza']) ?></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}

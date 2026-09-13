<?php
/**
 * Responsables de Traslado — catálogo de quien lleva físicamente la mercadería
 * (chofer / repartidor / asesor). Hasta ahora solo se podían crear al vuelo
 * desde el modal de Pedidos y Consignaciones; este módulo da el listado, la
 * edición y la baja lógica.
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\PreferenciasHelper;
use App\Services\ErrorLogService;
use App\Services\modulos\ResponsableTrasladoService;
use App\Services\UsuarioResponsableTrasladoService;
use Throwable;

class ResponsablesTrasladosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/responsables-traslados';
    private const PER_PAGE    = 20;

    private ResponsableTrasladoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ResponsableTrasladoService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));

        $result = $this->service->getListado(
            $idEmpresa,
            $buscar,
            $page,
            self::PER_PAGE,
            $ordenCol,
            $ordenDir,
            $this->idUsuarioFiltro($perm)
        );

        $total      = $result['total'];
        $totalPages = (int) ceil($total / self::PER_PAGE);

        $this->viewWithLayout('layouts.main', 'modulos.responsables_traslados.index', [
            'titulo'      => 'Responsables de Traslado',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => max(1, $totalPages),
            'perPage'     => self::PER_PAGE,
            'from'        => $total > 0 ? (($page - 1) * self::PER_PAGE) + 1 : 0,
            'to'          => $total > 0 ? min($page * self::PER_PAGE, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'ASC'));

        $perm   = $this->getPermisos();
        $result = $this->service->getListado(
            $idEmpresa, $buscar, $page, self::PER_PAGE, $ordenCol, $ordenDir, $this->idUsuarioFiltro($perm)
        );

        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $from       = $total > 0 ? (($page - 1) * self::PER_PAGE) + 1 : 0;
        $to         = $total > 0 ? min($page * self::PER_PAGE, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">'
               . '<i class="bi bi-person-badge fs-3 d-block mb-2"></i>No se encontraron responsables de traslado.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFila($r);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDis = ($page <= 1) ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml =
            "<button type='button' class='btn btn-outline-secondary' {$prevDis} onclick='RT_fetchSearch(" . ($page - 1) . ")'><i class='bi bi-chevron-left'></i></button>"
          . "<button type='button' class='btn btn-outline-secondary' {$nextDis} onclick='RT_fetchSearch(" . ($page + 1) . ")'><i class='bi bi-chevron-right'></i></button>";

        $qs = '?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf' . $qs,
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel' . $qs,
        ], JSON_FLAGS);
        exit;
    }

    /** Alta y edición en el mismo endpoint: hay id → actualiza, no hay → crea. */
    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id        = (int) ($_POST['id'] ?? 0);

            $data = [
                'nombre'         => $_POST['nombre'] ?? '',
                'identificacion' => $_POST['identificacion'] ?? null,
                'telefono'       => $_POST['telefono'] ?? null,
                'email'          => $_POST['email'] ?? null,
                'estado'         => $_POST['estado'] ?? 'activo',
                'id_empresa'     => $idEmpresa,
                'id_usuario'     => (int) $_SESSION['id_usuario'],
            ];

            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizar($id, $data);
                $mensaje = 'Responsable de traslado actualizado correctamente.';
            } else {
                $this->requireCrear();
                $id      = $this->service->crear($data);
                $mensaje = 'Responsable de traslado creado correctamente.';
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => $mensaje,
                'id'      => $id,
                'data'    => $this->service->getPorId($id, $idEmpresa),
            ], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_FLAGS);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new \InvalidArgumentException('ID del responsable no válido.');
            }
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Responsable de traslado eliminado correctamente.'], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_FLAGS);
        }
        exit;
    }

    // ─── Usuarios vinculados (pestaña del modal) ─────────────────────────────
    //
    // Es la vista inversa del vínculo que se administra en la ficha del usuario
    // (/config/usuarios-sistema → pestaña "Responsables de traslado"): ahí se
    // pregunta "¿qué responsables representa este usuario?" y aquí "¿qué usuarios
    // representan a este responsable?". Misma tabla, mismo Service, misma auditoría.

    /** Vinculados + disponibles del responsable, y si el usuario actual puede editarlos. */
    public function usuariosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idResponsable = (int) ($_GET['id'] ?? 0);
            $idEmpresa     = (int) $_SESSION['id_empresa'];
            if ($idResponsable <= 0) {
                throw new \InvalidArgumentException('ID del responsable no válido.');
            }
            if (!$this->service->getPorId($idResponsable, $idEmpresa)) {
                throw new \RuntimeException('Responsable de traslado no encontrado.');
            }

            $puedeEditar = $this->puedeEditarVinculos();
            $vinculo     = new UsuarioResponsableTrasladoService();

            echo json_encode([
                'ok'           => true,
                'vinculados'   => $vinculo->usuariosDeResponsable($idResponsable, $idEmpresa),
                // La lista de candidatos solo se arma para quien puede usarla.
                'disponibles'  => $puedeEditar ? $vinculo->usuariosDisponiblesParaResponsable($idResponsable, $idEmpresa) : [],
                'puede_editar' => $puedeEditar,
            ], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_FLAGS);
        }
        exit;
    }

    public function vincularUsuarioAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireEditarVinculos();

            $idResponsable = (int) ($_POST['id_responsable'] ?? 0);
            $idUsuario     = (int) ($_POST['id_usuario'] ?? 0);
            $idEmpresa     = (int) $_SESSION['id_empresa'];
            if ($idResponsable <= 0 || $idUsuario <= 0) {
                throw new \InvalidArgumentException('Datos incompletos.');
            }

            $res = (new UsuarioResponsableTrasladoService())
                ->vincular($idEmpresa, $idUsuario, $idResponsable, (int) $_SESSION['id_usuario']);

            echo json_encode([
                'ok'      => true,
                'mensaje' => $res['creado'] ? 'Usuario vinculado correctamente.' : 'Ese usuario ya estaba vinculado.',
            ], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_FLAGS);
        }
        exit;
    }

    public function desvincularUsuarioAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireEditarVinculos();

            $id = (int) ($_POST['id'] ?? 0);   // id de la fila de usuarios_responsables_traslado
            if ($id <= 0) {
                throw new \InvalidArgumentException('Vínculo no válido.');
            }

            (new UsuarioResponsableTrasladoService())
                ->desvincular($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);

            echo json_encode(['ok' => true, 'mensaje' => 'Usuario desvinculado.'], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_FLAGS);
        }
        exit;
    }

    /**
     * Vincular un usuario no es editar un catálogo: DA ACCESO A DATOS (ese usuario
     * pasa a ver, desde la app móvil, las consignaciones de este responsable). Por
     * eso se exige lo mismo que en /config/usuarios-sistema —nivel 2 o superior—
     * además del permiso de actualizar del módulo. Con menos que eso, la pestaña
     * se muestra en solo lectura.
     */
    private function puedeEditarVinculos(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 0) >= 2 && !empty($this->getPermisos()['actualizar']);
    }

    private function requireEditarVinculos(): void
    {
        $this->requireActualizar();
        if ((int) ($_SESSION['nivel'] ?? 0) < 2) {
            throw new \RuntimeException('Solo un administrador puede vincular usuarios a un responsable de traslado.');
        }
    }

    /** Responsables activos, para refrescar los selects de Pedidos / Consignaciones. */
    public function listarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $rows = $this->service->listarActivos((int) $_SESSION['id_empresa']);
            echo json_encode(['ok' => true, 'data' => $rows], JSON_FLAGS);
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => []], JSON_FLAGS);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows          = $this->filasParaExportar();
        $nombreEmpresa = $this->nombreEmpresa();

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start(); ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Responsables de Traslado</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%">Nombre</th>
                            <th style="width: 15%">Identificación</th>
                            <th style="width: 13%">Teléfono</th>
                            <th style="width: 24%">Correo</th>
                            <th style="width: 8%">Usuarios</th>
                            <th style="width: 10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['identificacion'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['telefono'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['email'] ?? '-')) ?></td>
                                <td><?= (int) ($r['usuarios_vinculados'] ?? 0) ?></td>
                                <td><?= ucfirst((string) ($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Responsables_traslado_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=UTF-8');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows          = $this->filasParaExportar();
        $nombreEmpresa = $this->nombreEmpresa();

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nombre', 'Identificación', 'Teléfono', 'Correo', 'Usuarios vinculados', 'Estado', 'Fecha de creación'];
            $datos   = [];
            foreach ($rows as $r) {
                $datos[] = [
                    (string) ($r['nombre'] ?? ''),
                    (string) ($r['identificacion'] ?? '-'),
                    (string) ($r['telefono'] ?? '-'),
                    (string) ($r['email'] ?? '-'),
                    (int) ($r['usuarios_vinculados'] ?? 0),
                    ucfirst((string) ($r['estado'] ?? '')),
                    !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['created_at'])) : '-',
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Responsables_traslado',
                $headers,
                $datos,
                'Listado de Responsables de Traslado',
                $nombreEmpresa
            );
            exit;
        } catch (Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=UTF-8');
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    // ─── Auxiliares ──────────────────────────────────────────────────────────

    /**
     * Registros propios vs. acceso total (§6): sin el flag `todo`, el usuario
     * solo ve los responsables que él mismo creó.
     */
    private function idUsuarioFiltro(array $perm): ?int
    {
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /** Listado completo (sin paginar) con los mismos filtros que ve el usuario. */
    private function filasParaExportar(): array
    {
        $perm = $this->getPermisos();
        return $this->service->getListado(
            (int) $_SESSION['id_empresa'],
            trim($_GET['b'] ?? ''),
            1,
            0,
            trim($_GET['sort'] ?? 'nombre'),
            strtoupper(trim($_GET['dir'] ?? 'ASC')),
            $this->idUsuarioFiltro($perm)
        )['rows'];
    }

    private function nombreEmpresa(): string
    {
        try {
            $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            return (string) ($empresa['nombre'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Una fila de la tabla. El HTML vive en un único partial que incluyen tanto
     * la carga inicial (index.php) como este refresco AJAX: si la fila se
     * escribiera en dos sitios, al tocar una columna la otra se quedaría vieja
     * —exactamente el fallo que ya tuvo el listado de Transportistas—.
     */
    private function renderFila(array $r): string
    {
        ob_start();
        include MVC_APP . '/views/modulos/responsables_traslados/_fila.php';
        return (string) ob_get_clean();
    }
}

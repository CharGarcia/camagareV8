<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\Services\modulos\PresupuestoService;

/**
 * Presupuestos (modulos/presupuestos): presupuesto de ingresos y gastos por cuenta y mes, con
 * alcance de empresa / centro de costo / proyecto, versiones aprobadas y ejecución desde los
 * asientos. Sin lógica de negocio: valida lo básico, delega al Service y responde.
 *
 * Permisos: ver = consultar; crear/actualizar = armar borradores y reformas; eliminar = borrar
 * borradores y descartar reformas; APROBAR exige Acceso total ('t'), decisión del usuario.
 */
class PresupuestosController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/presupuestos';
    private const PER_PAGE    = 20;

    private PresupuestoService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = PresupuestoService::crear();
    }

    /** Registros propios vs. acceso total (CLAUDE.md §6). */
    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /** Aprobar: solo nivel 3 o quien tenga Acceso total en el módulo. */
    private function requireAccesoTotal(): void
    {
        $this->requireActualizar();
        if ((int) ($_SESSION['nivel'] ?? 1) >= 3 || !empty($this->getPermisos()['todo'])) {
            return;
        }
        $this->json(['ok' => false, 'mensaje' => 'Aprobar un presupuesto requiere Acceso total en este módulo.'], 403);
    }

    private function sesion(): array
    {
        return [(int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']];
    }

    /** Respuesta uniforme de error: los de negocio se muestran tal cual, el resto se registra. */
    private function error(\Throwable $e, string $accion): never
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado.']);
        }
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $prefs  = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden  = OrdenListado::leer($prefs, 'periodo', 'DESC');
        $buscar = trim((string) ($_GET['b'] ?? ''));
        $page   = max(1, (int) ($_GET['page'] ?? 1));

        $res = $this->service->getListado($idEmpresa, $buscar, $page, self::PER_PAGE, $orden, $this->idUsuarioFiltro());
        $this->viewWithLayout('layouts.main', 'modulos/presupuestos/index', [
            'titulo'        => 'Presupuestos',
            'perm'          => $this->getPermisos(),
            'rutaModulo'    => self::RUTA_MODULO,
            'vistaConfig'   => $prefs,
            'rows'          => $res['rows'],
            'total'         => $res['total'],
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'totalPages'    => max(1, (int) ceil($res['total'] / self::PER_PAGE)),
            'buscar'        => $buscar,
            'ordenJson'     => OrdenListado::aJson($orden),
            'ordenParam'    => OrdenListado::aCadena($orden),
            'centrosCosto'  => $this->service->repo()->getCentrosCosto($idEmpresa),
            'proyectos'     => $this->service->repo()->getProyectos($idEmpresa),
            'puedeAprobar'  => (int) ($_SESSION['nivel'] ?? 1) >= 3 || !empty($this->getPermisos()['todo']),
            'base'          => BASE_URL,
        ]);
    }

    /** Filas del listado en JSON (el JS las pinta; sin recarga, porque el orden es multi-columna). */
    public function searchAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $prefs  = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
            $orden  = OrdenListado::leer($prefs, 'periodo', 'DESC');
            $buscar = trim((string) ($_GET['b'] ?? ''));
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $res    = $this->service->getListado($idEmpresa, $buscar, $page, self::PER_PAGE, $orden, $this->idUsuarioFiltro());
            $qs     = http_build_query(['b' => $buscar, 'orden' => OrdenListado::aCadena($orden)]);
            $this->json([
                'ok'          => true,
                'rows'        => $res['rows'],
                'total'       => $res['total'],
                'page'        => $page,
                'per_page'    => self::PER_PAGE,
                'total_pages' => max(1, (int) ceil($res['total'] / self::PER_PAGE)),
                'excel_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel?' . $qs,
                'pdf_url'     => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Detalle y cabecera ───────────────────────────────────────────────────

    public function getAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $id  = (int) ($_GET['id'] ?? 0);
            $det = $this->service->getDetalle($id, $idEmpresa, (int) ($_GET['id_version'] ?? 0) ?: null);
            $this->requireRegistroPropio($det['cabecera']);
            $this->json(['ok' => true] + $det);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function storeAjax(): void
    {
        $this->requireCrear();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = $this->service->crearPresupuesto($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Presupuesto creado. Ahora agregue las cuentas y sus montos.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function updateAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $this->service->actualizarPresupuesto($id, $_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Presupuesto actualizado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $this->service->eliminarPresupuesto($id, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Presupuesto eliminado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Cerrar (aprobado → cerrado) o reabrir (cerrado → aprobado). */
    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $this->service->cambiarEstado($id, (string) ($_POST['estado'] ?? ''), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Grilla ───────────────────────────────────────────────────────────────

    /** Guarda las líneas de la versión en borrador. Recibe lineas como JSON. */
    public function guardarLineasAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $idVersion = (int) ($_POST['id_version'] ?? 0);
            $lineas = json_decode((string) ($_POST['lineas'] ?? '[]'), true);
            if (!is_array($lineas)) {
                throw new \InvalidArgumentException('Las líneas no tienen un formato válido.');
            }
            $ver = $this->service->repo()->getVersion($idVersion, $idEmpresa);
            $this->requireRegistroPropio($ver ? $this->service->repo()->findById((int) $ver['id_presupuesto'], $idEmpresa) : null);
            $res = $this->service->guardarLineas($idVersion, $lineas, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => "Guardado: {$res['lineas']} cuenta(s), total $" . number_format($res['total'], 2) . '.'] + $res);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function buscarCuentasAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $this->json(['ok' => true, 'data' => $this->service->repo()->buscarCuentas($idEmpresa, trim((string) ($_GET['q'] ?? '')))]);
    }

    /** Líneas precargadas (sin grabar) desde otra versión, con % opcional. */
    public function copiarDeAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa] = $this->sesion();
        try {
            $lineas = $this->service->lineasDesdeVersion((int) ($_GET['id_version_origen'] ?? 0), (int) ($_GET['id'] ?? 0), $idEmpresa, (float) ($_GET['pct'] ?? 0));
            $this->json(['ok' => true, 'lineas' => $lineas]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Líneas precargadas (sin grabar) desde lo ejecutado en un período, con % opcional. */
    public function desdeEjecutadoAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa] = $this->sesion();
        try {
            $lineas = $this->service->lineasDesdeEjecutado((int) ($_GET['id'] ?? 0), (string) ($_GET['desde'] ?? ''), (string) ($_GET['hasta'] ?? ''), (float) ($_GET['pct'] ?? 0), $idEmpresa);
            $this->json(['ok' => true, 'lineas' => $lineas]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Presupuestos de la empresa con alguna versión, para el selector «Copiar de». */
    public function listaParaCopiarAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $res = $this->service->repo()->getListado($idEmpresa, '', 1, 0, [['col' => 'periodo', 'dir' => 'DESC']], $this->idUsuarioFiltro());
            $out = [];
            foreach ($res['rows'] as $r) {
                foreach ($this->service->repo()->getVersiones((int) $r['id'], $idEmpresa) as $v) {
                    $out[] = ['id_version' => (int) $v['id'], 'id_presupuesto' => (int) $r['id'],
                              'label' => $r['nombre'] . ' · ' . $v['nombre'] . ' (' . $v['estado'] . ')'];
                }
            }
            $this->json(['ok' => true, 'data' => $out]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Versiones ────────────────────────────────────────────────────────────

    public function aprobarAjax(): void
    {
        $this->requireAccesoTotal();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->aprobarVersion((int) ($_POST['id_version'] ?? 0), $_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Versión aprobada. Desde ahora es la vigente y se compara con lo ejecutado.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function reformaAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $idVersion = $this->service->crearReforma($id, (string) ($_POST['motivo'] ?? ''), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'id_version' => $idVersion, 'mensaje' => 'Reforma creada en borrador a partir de la versión vigente.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function eliminarReformaAjax(): void
    {
        $this->requireEliminar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $this->service->eliminarReforma((int) ($_POST['id_version'] ?? 0), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => 'Reforma descartada.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Ejecución ────────────────────────────────────────────────────────────

    public function ejecucionAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $this->json(['ok' => true] + $this->service->ejecucion($id, $idEmpresa, (int) ($_GET['id_version'] ?? 0) ?: null));
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    public function asientosEjecucionAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->json(['ok' => true, 'data' => $this->service->asientosEjecucion(
                (int) ($_GET['id'] ?? 0), (int) ($_GET['id_cuenta'] ?? 0), (string) ($_GET['periodo'] ?? ''), $idEmpresa
            )]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Rubros ───────────────────────────────────────────────────────────────

    public function rubrosAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $accion = (string) ($_POST['accion'] ?? 'listar');
            if ($accion === 'crear') {
                $this->requireActualizar();
                $id = $this->service->crearRubro((string) ($_POST['nombre'] ?? ''), $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'id' => $id, 'rubros' => $this->service->repo()->getRubros($idEmpresa)]);
            }
            if ($accion === 'renombrar') {
                $this->requireActualizar();
                $this->service->renombrarRubro((int) ($_POST['id'] ?? 0), (string) ($_POST['nombre'] ?? ''), $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'rubros' => $this->service->repo()->getRubros($idEmpresa)]);
            }
            if ($accion === 'eliminar') {
                $this->requireEliminar();
                $this->service->eliminarRubro((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'rubros' => $this->service->repo()->getRubros($idEmpresa)]);
            }
            $this->json(['ok' => true, 'rubros' => $this->service->repo()->getRubros($idEmpresa)]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Excel: plantilla e importación ───────────────────────────────────────

    public function plantillaExcel(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $id = (int) ($_GET['id'] ?? 0);
            $libro = $this->service->plantillaExcel($id, $idEmpresa);
            (new \App\Services\ReportService())->descargarSpreadsheet($libro, 'Plantilla_Presupuesto_' . $id);
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    /** Lee el Excel y devuelve las líneas para la grilla (el usuario revisa y guarda). */
    public function importarExcelAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $archivo = $_FILES['archivo'] ?? null;
            if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('No se recibió el archivo.');
            }
            $ext = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                throw new \RuntimeException('El archivo debe ser un Excel (.xlsx).');
            }
            if ($archivo['size'] > 10 * 1024 * 1024) {
                throw new \RuntimeException('El archivo excede los 10 MB.');
            }
            $dir = MVC_ROOT . '/storage/presupuestos/' . $idEmpresa;
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('No se pudo preparar la carpeta temporal.');
            }
            $ruta = $dir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
                throw new \RuntimeException('No se pudo guardar el archivo subido.');
            }
            try {
                $res = $this->service->leerExcel($ruta, (int) ($_POST['id'] ?? 0), $idEmpresa);
            } finally {
                @unlink($ruta);
            }
            $this->json(['ok' => true] + $res);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    // ── Exportaciones ────────────────────────────────────────────────────────

    /** Excel de UN presupuesto (grilla + ejecución). */
    public function excelPresupuesto(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $id = (int) ($_GET['id'] ?? 0);
            $this->requireRegistroPropio($this->service->repo()->findById($id, $idEmpresa));
            $nombreEmpresa = (string) ((new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '');
            $libro = $this->service->excelPresupuesto($id, $idEmpresa, (int) ($_GET['id_version'] ?? 0) ?: null, $nombreEmpresa);
            (new \App\Services\ReportService())->descargarSpreadsheet($libro, 'Presupuesto_' . $id);
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    /** PDF de UN presupuesto (se abre con CMG_pdfDocumento). */
    public function pdfPresupuesto(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            $id  = (int) ($_GET['id'] ?? 0);
            $det = $this->service->getDetalle($id, $idEmpresa, (int) ($_GET['id_version'] ?? 0) ?: null);
            $this->requireRegistroPropio($det['cabecera']);
            $ej = null;
            try {
                $ej = $this->service->ejecucion($id, $idEmpresa, $det['version'] && $det['version']['estado'] !== 'borrador' ? (int) $det['version']['id'] : null);
            } catch (\DomainException $e) {
                // sin versión aprobada: solo la grilla
            }
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $html = $this->renderPdf($det, $ej, $empresa);
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Presupuesto_' . $id . '_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    private function renderPdf(array $det, ?array $ej, array $empresa): string
    {
        $cab   = $det['cabecera'];
        $meses = $cab['meses'];
        $money = fn ($v) => number_format((float) $v, 2);
        $nCols = 3 + count($meses) + 1;
        $wMes  = count($meses) > 12 ? 4 : (count($meses) > 1 ? round(60 / count($meses), 1) : 20);
        ob_start(); ?>
        <style>
            table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:<?= count($meses) > 12 ? '5.5' : '7' ?>pt; table-layout:fixed; }
            th { background:#f2f2f2; border:1px solid #ccc; padding:2px; }
            td { border:1px solid #ccc; padding:2px; overflow:hidden; }
            .r { text-align:right; } .c { text-align:center; } .b { font-weight:bold; background:#fafafa; }
            .head { text-align:center; margin-bottom:8px; }
            .verde { background:#d1e7dd; } .amarillo { background:#fff3cd; } .rojo { background:#f8d7da; }
        </style>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3>
            <h4><?= htmlspecialchars($cab['nombre']) ?> — <?= htmlspecialchars($det['version']['nombre'] ?? '') ?> (<?= htmlspecialchars($det['version']['estado'] ?? '') ?>)</h4>
            <p style="font-size:8pt"><?= htmlspecialchars(PresupuestoService::alcanceTexto($cab)) ?> · <?= PresupuestoService::mesCorto($meses[0]) ?> a <?= PresupuestoService::mesCorto(end($meses)) ?> · Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table>
            <thead><tr>
                <th style="width:9%">Cuenta</th><th style="width:<?= count($meses) > 12 ? 14 : 20 ?>%">Nombre</th><th style="width:8%">Rubro</th>
                <?php foreach ($meses as $ym): ?><th style="width:<?= $wMes ?>%" class="r"><?= PresupuestoService::mesCorto($ym) ?></th><?php endforeach; ?>
                <th style="width:8%" class="r">Total</th>
            </tr></thead>
            <tbody>
                <?php $totM = []; $tot = 0; foreach ($det['lineas'] as $l): $suma = 0; ?>
                    <tr>
                        <td><?= htmlspecialchars($l['cuenta_codigo']) ?></td><td><?= htmlspecialchars($l['cuenta_nombre']) ?></td><td><?= htmlspecialchars($l['rubro_nombre'] ?? '') ?></td>
                        <?php foreach ($meses as $ym): $v = (float) ($l['valores'][$ym] ?? 0); $suma += $v; $totM[$ym] = ($totM[$ym] ?? 0) + $v; ?>
                            <td class="r"><?= $v ? $money($v) : '' ?></td>
                        <?php endforeach; $tot += $suma; ?>
                        <td class="r b"><?= $money($suma) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="b"><td colspan="3">TOTAL</td>
                    <?php foreach ($meses as $ym): ?><td class="r"><?= $money($totM[$ym] ?? 0) ?></td><?php endforeach; ?>
                    <td class="r"><?= $money($tot) ?></td></tr>
                <?php if (empty($det['lineas'])): ?><tr><td colspan="<?= $nCols ?>" class="c">Sin líneas.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($ej): ?>
            <h4 style="margin-top:10px">Ejecución (presupuesto vs real)</h4>
            <table style="font-size:7pt">
                <thead><tr><th style="width:16%">Rubro</th><th style="width:12%">Cuenta</th><th style="width:28%">Nombre</th>
                    <th style="width:11%" class="r">Presupuesto</th><th style="width:11%" class="r">Ejecutado</th><th style="width:11%" class="r">Diferencia</th><th style="width:11%" class="r">% Ejec.</th></tr></thead>
                <tbody>
                    <?php foreach ($ej['filas'] as $f): ?>
                        <tr class="<?= in_array($f['semaforo'], ['verde', 'amarillo', 'rojo'], true) ? $f['semaforo'] : '' ?>">
                            <td><?= htmlspecialchars($f['rubro']) ?></td><td><?= htmlspecialchars($f['cuenta_codigo']) ?></td><td><?= htmlspecialchars($f['cuenta_nombre']) ?></td>
                            <td class="r"><?= $money($f['presupuesto']) ?></td><td class="r"><?= $money($f['ejecutado']) ?></td>
                            <td class="r"><?= $money($f['diferencia']) ?></td><td class="r"><?= $f['pct'] === null ? '—' : $f['pct'] . ' %' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach (['ingresos' => 'TOTAL INGRESOS', 'gastos' => 'TOTAL COSTOS Y GASTOS'] as $k => $et): $t = $ej['totales'][$k]; ?>
                        <tr class="b"><td colspan="3"><?= $et ?></td><td class="r"><?= $money($t['presupuesto']) ?></td><td class="r"><?= $money($t['ejecutado']) ?></td>
                            <td class="r"><?= $money($t['diferencia']) ?></td><td class="r"><?= $t['pct'] === null ? '—' : $t['pct'] . ' %' ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:7pt">Semáforo: verde bajo <?= (float) $cab['umbral_amarillo'] ?> %, amarillo hasta <?= (float) $cab['umbral_rojo'] ?> %, rojo por encima. En ingresos se invierte (lo malo es no llegar).</p>
        <?php endif;
        return (string) ob_get_clean();
    }

    /** Excel del listado. */
    public function exportExcel(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            [$headers, $data] = $this->datosListado($idEmpresa);
            $nombreEmpresa = (string) ((new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '');
            (new \App\Services\ReportService())->exportToExcel('Presupuestos', $headers, $data, 'Presupuestos', $nombreEmpresa);
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    /** PDF del listado. */
    public function exportPdf(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        try {
            $this->autoload();
            [$headers, $data] = $this->datosListado($idEmpresa);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            ob_start(); ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; table-layout:fixed; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; } td { border:1px solid #ccc; padding:3px; overflow:hidden; }
                .r { text-align:right; } .head { text-align:center; margin-bottom:10px; }
            </style>
            <div class="head"><h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3><h4>Presupuestos</h4><p style="font-size:8pt">Generado: <?= date('d-m-Y H:i:s') ?></p></div>
            <table>
                <thead><tr><?php foreach ($headers as $i => $h): ?><th class="<?= $i >= 5 ? 'r' : '' ?>"><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php foreach ($data as $fila): ?><tr><?php foreach ($fila as $i => $v): ?><td class="<?= $i >= 5 ? 'r' : '' ?>"><?= htmlspecialchars((string) $v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    <?php if (!$data): ?><tr><td colspan="<?= count($headers) ?>" style="text-align:center">Sin presupuestos.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <?php
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML((string) ob_get_clean());
            $pdf->output('Presupuestos_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            $this->errorDescarga($e, __FUNCTION__);
        }
        exit;
    }

    private function datosListado(int $idEmpresa): array
    {
        $prefs = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefs, 'periodo', 'DESC');
        $rows  = $this->service->getListado($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 0, $orden, $this->idUsuarioFiltro())['rows'];
        $headers = ['Nombre', 'Período', 'Alcance', 'Versión', 'Estado', 'Ingresos', 'Gastos', 'Ejecutado', '% Ejec.'];
        $data = array_map(fn ($r) => [
            $r['nombre'],
            PresupuestoService::mesCorto(substr((string) $r['periodo_desde'], 0, 7)) . ' a ' . PresupuestoService::mesCorto(substr((string) $r['periodo_hasta'], 0, 7)),
            PresupuestoService::alcanceTexto($r),
            ($r['version_nombre'] ?? '—') . ' (' . ($r['version_estado'] ?? '') . ')',
            ucfirst((string) $r['estado']),
            number_format((float) ($r['ingresos'] ?? 0), 2, '.', ''),
            number_format((float) ($r['gastos'] ?? 0), 2, '.', ''),
            $r['ejecutado_gastos'] === null ? '' : number_format((float) $r['ejecutado_gastos'], 2, '.', ''),
            $r['pct_ejecucion'] === null ? '' : $r['pct_ejecucion'] . ' %',
        ], $rows);
        return [$headers, $data];
    }

    private function autoload(): void
    {
        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    /** Error en una descarga: JSON con {error} si aún no se envió nada (CMG_descargar lo muestra). */
    private function errorDescarga(\Throwable $e, string $accion): void
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\Services\modulos\CrmActividadService;
use App\Services\modulos\CrmConfiguracionService;
use App\Services\modulos\CrmContactoService;
use App\Services\modulos\CrmOportunidadService;
use App\Traits\CrmControllerTrait;

/**
 * CRM (modulos/crm): embudo de oportunidades, contactos y actividades.
 * Diseño: docs/diseno/crm-redes-fase1.md. Las pantallas (Kanban, Lista, fichas)
 * consumen estos endpoints JSON.
 *
 * Permisos: r ver · w crear · u modificar/mover/ganar/perder/reabrir · d eliminar.
 * Sin acceso total (t) el usuario ve lo que creó o lo que tiene asignado.
 */
class CrmController extends BaseModuloController
{
    use CrmControllerTrait;

    private CrmOportunidadService $oportunidades;
    private CrmContactoService $contactos;
    private CrmActividadService $actividades;
    private CrmConfiguracionService $configCrm;

    protected function getRutaModulo(): string
    {
        return 'modulos/crm';
    }

    public function __construct()
    {
        parent::__construct();
        $this->oportunidades = CrmOportunidadService::crear();
        $this->contactos     = CrmContactoService::crear();
        $this->actividades   = CrmActividadService::crear();
        $this->configCrm     = CrmConfiguracionService::crear();
    }

    /** Columnas personalizables de la vista Lista (data-col => etiqueta). */
    public const COLUMNAS_LISTA = [
        'numero' => 'N.º', 'titulo' => 'Oportunidad', 'contacto' => 'Contacto', 'etapa' => 'Etapa',
        'valor_estimado' => 'Valor', 'probabilidad' => 'Prob.', 'fecha_cierre_estimada' => 'Cierre estimado',
        'responsable' => 'Responsable', 'origen' => 'Origen', 'estado' => 'Estado', 'created_at' => 'Creada',
    ];

    // ── Pantalla ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $orden      = OrdenListado::leer($prefsVista, 'created_at', 'DESC');
        $perPage    = $this->porPagina();   // deja listo el selector de filas por página
        $disponible = $this->configCrm->disponible();
        if ($disponible) {
            try {
                $this->configCrm->asegurarInicial($this->idEmpresa(), $this->idUsuario());
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos/crm/index', [
            'titulo'          => 'CRM',
            'fullWidth'       => true,
            'perm'            => $this->getPermisos(),
            'rutaModulo'      => $this->getRutaModulo(),
            'vistaConfig'     => $prefsVista,
            'ordenJson'       => OrdenListado::aJson($orden),
            'perPage'         => $perPage,
            'disponible'      => $disponible,
            'columnasLista'   => self::COLUMNAS_LISTA,
            'idUsuario'       => $this->idUsuario(),
            // Acciones que dependen de otros módulos: crear proforma y crear cliente al vuelo.
            'puedeProforma'   => !empty($this->permisosModuloPorRuta('modulos/proformas')['crear']),
            'puedeCliente'    => !empty($this->permisosModuloPorRuta('modulos/clientes')['crear']),
        ]);
    }

    // ── Datos de apoyo ───────────────────────────────────────────────────────

    /**
     * Embudos con etapas, orígenes, motivos, usuarios y vendedores para armar la
     * pantalla. La primera vez siembra el embudo «Ventas» de la empresa.
     */
    public function datosAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $this->configCrm->asegurarInicial($this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true]
                + ['embudos' => $this->configCrm->getEmbudosConEtapas($this->idEmpresa())]
                + $this->configCrm->getCatalogos($this->idEmpresa())
                + ['permisos' => $this->getPermisos(), 'id_usuario' => $this->idUsuario()]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── Oportunidades ────────────────────────────────────────────────────────

    /** Filtros de pantalla comunes a Kanban, Lista y totales. */
    private function filtros(): array
    {
        return [
            'id_embudo'      => (int) ($_GET['id_embudo'] ?? 0),
            'estado'         => (string) ($_GET['estado'] ?? ''),
            'id_responsable' => (int) ($_GET['id_responsable'] ?? 0),
            'id_origen'      => (int) ($_GET['id_origen'] ?? 0),
            'cierre_desde'   => (string) ($_GET['cierre_desde'] ?? ''),
            'cierre_hasta'   => (string) ($_GET['cierre_hasta'] ?? ''),
            'buscar'         => trim((string) ($_GET['b'] ?? '')),
        ];
    }

    public function kanbanAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $f = $this->filtros();
            if ($f['id_embudo'] <= 0) {
                $this->json(['ok' => false, 'error' => 'Seleccione un embudo.'], 422);
            }
            $this->json([
                'ok'       => true,
                'columnas' => $this->oportunidades->getKanban($this->idEmpresa(), $f['id_embudo'], $f, $this->idUsuarioFiltro()),
                'resumen'  => $this->oportunidades->getResumen($this->idEmpresa(), $f, $this->idUsuarioFiltro()),
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function listadoAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $perPage = $this->porPagina();
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $orden   = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()), 'created_at', 'DESC');
            $f = $this->filtros();
            $res = $this->oportunidades->getListado($this->idEmpresa(), $f, $page, $perPage, $orden, $this->idUsuarioFiltro());
            $this->json([
                'ok'         => true,
                'rows'       => $res['rows'],
                'total'      => $res['total'],
                'page'       => $page,
                'perPage'    => $perPage,
                'totalPages' => (int) ceil($res['total'] / max(1, $perPage)),
                'orden'      => $orden,
                'resumen'    => $this->oportunidades->getResumen($this->idEmpresa(), $f, $this->idUsuarioFiltro()),
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function oportunidadAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $ficha = $this->oportunidades->getFicha((int) ($_GET['id'] ?? 0), $this->idEmpresa());
        if (!$ficha) {
            $this->json(['ok' => false, 'error' => 'Oportunidad no encontrada.'], 404);
        }
        $this->requireAccesoCrm($ficha);
        $this->json(['ok' => true, 'oportunidad' => $ficha]);
    }

    public function guardarOportunidadAjax(): void
    {
        $id = $this->postInt('id');
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        $d = $_POST;
        unset($d['id_conversacion'], $d['numero'], $d['estado'], $d['id_cliente'], $d['id_proforma']);
        try {
            if ($id > 0) {
                $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
                $this->oportunidades->actualizar($id, $d, $this->idEmpresa(), $this->idUsuario(), $this->idUsuarioFiltro());
                $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Oportunidad actualizada.']);
            }
            $r = $this->oportunidades->crearOportunidad($d, $this->idEmpresa(), $this->idUsuario(), $this->idUsuarioFiltro());
            $this->json([
                'ok' => true, 'id' => $r['id'], 'numero' => $r['numero'],
                'mensaje' => $this->oportunidades->ultimoGuardadoPrevio
                    ? 'La oportunidad ya estaba registrada; no se creó otra.'
                    : 'Oportunidad creada.',
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Arrastre en el Kanban (cambio de etapa y/o de posición). */
    public function moverEtapaAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $op = $this->oportunidades->moverEtapa($id, $this->postInt('id_etapa'), $_POST['orden'] ?? null, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'oportunidad' => $op]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function ganarAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $this->oportunidades->ganar($id, $this->postInt('id_cliente'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => '¡Oportunidad ganada!']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function perderAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $this->oportunidades->perder($id, $this->postInt('id_motivo_perdida'), (string) ($_POST['detalle_perdida'] ?? ''),
                $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Oportunidad marcada como perdida.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function reabrirAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $this->oportunidades->reabrir($id, $this->postInt('id_etapa'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Oportunidad reabierta.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarOportunidadAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $this->oportunidades->eliminar($id, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Oportunidad eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /**
     * «Crear proforma»: deja la oportunidad y su cliente en sesión y el navegador va a
     * /modulos/proformas (URL limpia), que abre la proforma nueva una sola vez.
     */
    public function prepararProformaAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        if (empty($this->permisosModuloPorRuta('modulos/proformas')['crear'])) {
            $this->json(['ok' => false, 'error' => 'No tiene permiso para crear proformas.'], 403);
        }
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->oportunidades->getPorId($id, $this->idEmpresa()));
            $_SESSION['proformas_desde_oportunidad'] = $this->oportunidades->prepararProforma(
                $id, $this->postInt('id_cliente'), $this->idEmpresa(), $this->idUsuario()
            );
            $this->json(['ok' => true, 'url' => rtrim(BASE_URL, '/') . '/modulos/proformas']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Clientes activos para los diálogos Ganar / Crear proforma. `id` = traer uno (crear al vuelo). */
    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        $q  = trim((string) ($_GET['q'] ?? ''));
        $id = (int) ($_GET['id'] ?? 0);
        $repo = new \App\repositories\modulos\ClienteRepository();
        if ($id > 0) {
            $c = (new \App\repositories\modulos\CrmCatalogoRepository())->getCliente($id, $this->idEmpresa());
            $this->json(['ok' => true, 'data' => $c ? [$c] : []]);
        }
        $rows = $repo->getListado($this->idEmpresa(), $q, 1, 15, 'nombre', 'ASC', null, true)['rows'];
        $this->json(['ok' => true, 'data' => array_map(fn($r) => [
            'id' => (int) $r['id'], 'nombre' => $r['nombre'], 'identificacion' => $r['identificacion'],
            'email' => $r['email'] ?? null, 'telefono' => $r['telefono'] ?? null,
        ], $rows)]);
    }

    // ── Reportes ─────────────────────────────────────────────────────────────

    /** Reportes del embudo: período (desde/hasta) + filtros de la tarjeta de control. */
    public function reportesAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $this->json(['ok' => true] + \App\Services\modulos\CrmReporteService::crear()->generar(
                $this->idEmpresa(), $this->filtros(),
                (string) ($_GET['desde'] ?? ''), (string) ($_GET['hasta'] ?? ''), $this->idUsuarioFiltro()
            ));
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── Exportaciones de la Lista ────────────────────────────────────────────

    /** Filas de la Lista con los filtros y el orden de pantalla, sin paginar. */
    private function filasExportacion(): array
    {
        if (is_file(MVC_ROOT . '/vendor/autoload.php')) {
            require_once MVC_ROOT . '/vendor/autoload.php';   // Html2Pdf / PhpSpreadsheet
        }
        $orden = OrdenListado::leer(PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()), 'created_at', 'DESC');
        return $this->oportunidades->getListado($this->idEmpresa(), $this->filtros(), 1, 0, $orden, $this->idUsuarioFiltro())['rows'];
    }

    private const ESTADOS = ['abierta' => 'Abierta', 'ganada' => 'Ganada', 'perdida' => 'Perdida'];

    private static function fecha(?string $f, bool $conHora = false): string
    {
        if (empty($f)) return '-';
        return date($conHora ? 'd-m-Y H:i:s' : 'd-m-Y', strtotime($f));
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $rows = $this->filasExportacion();
            $datos = array_map(fn($r) => [
                (int) $r['numero'], (string) $r['titulo'], (string) $r['contacto'], (string) $r['embudo'],
                (string) $r['etapa'], (float) $r['valor_estimado'], (int) $r['probabilidad'] . '%',
                self::fecha($r['fecha_cierre_estimada']), (string) ($r['responsable'] ?? '-'),
                (string) ($r['origen'] ?? '-'), self::ESTADOS[$r['estado']] ?? $r['estado'],
                self::fecha($r['created_at'], true),
            ], $rows);
            $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
            (new \App\Services\ReportService())->exportToExcel(
                'CRM_Oportunidades',
                ['N.º', 'Oportunidad', 'Contacto', 'Embudo', 'Etapa', 'Valor', 'Prob.', 'Cierre estimado', 'Responsable', 'Origen', 'Estado', 'Creada'],
                $datos, 'Oportunidades', $empresa['nombre'] ?? null
            );
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'No se pudo generar el Excel.';
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $rows = $this->filasExportacion();
            $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
            $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            // Anchos en % repetidos en cada td (Html2Pdf no ajusta el texto si solo van en el th).
            $cols = [['N.º', 5], ['Oportunidad', 19], ['Contacto', 15], ['Etapa', 10], ['Valor', 8],
                     ['Prob.', 5], ['Cierre', 8], ['Responsable', 12], ['Origen', 10], ['Estado', 8]];
            $total = 0.0;
            $html = '<style>table{border-collapse:collapse;font-family:helvetica;font-size:7.5pt;width:100%}'
                  . 'th{background:#f2f2f2;border:0.5px solid #bbb;padding:3px;text-align:left}'
                  . 'td{border:0.5px solid #ccc;padding:3px}.r{text-align:right}h1{font-size:13pt;margin:0}'
                  . 'h2{font-size:9pt;color:#666;margin:2px 0 8px 0}</style>'
                  . '<page backtop="8mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">'
                  . '<h1>' . $h($empresa['nombre'] ?? '') . '</h1><h2>CRM · Oportunidades · ' . date('d-m-Y H:i:s') . '</h2>'
                  . '<table><thead><tr>';
            foreach ($cols as [$t, $w]) {
                $html .= '<th style="width:' . $w . '%">' . $h($t) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $total += (float) $r['valor_estimado'];
                $celdas = [
                    $r['numero'], $r['titulo'], $r['contacto'], $r['etapa'],
                    number_format((float) $r['valor_estimado'], 2, '.', ','), (int) $r['probabilidad'] . '%',
                    self::fecha($r['fecha_cierre_estimada']), $r['responsable'] ?? '-', $r['origen'] ?? '-',
                    self::ESTADOS[$r['estado']] ?? $r['estado'],
                ];
                $html .= '<tr>';
                foreach ($celdas as $i => $v) {
                    $html .= '<td style="width:' . $cols[$i][1] . '%"' . (in_array($i, [4, 5], true) ? ' class="r"' : '') . '>' . $h($v) . '</td>';
                }
                $html .= '</tr>';
            }
            if (!$rows) {
                $html .= '<tr><td colspan="10" style="width:100%">Sin oportunidades con los filtros elegidos.</td></tr>';
            }
            $html .= '</tbody></table>'
                   . '<table style="margin-top:4px"><tr><td style="width:57%;border:0;font-weight:bold">'
                   . count($rows) . ' oportunidad(es)</td><td class="r" style="width:8%;border:0;font-weight:bold">'
                   . number_format($total, 2, '.', ',') . '</td><td style="width:35%;border:0"></td></tr></table></page>';

            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('CRM_Oportunidades_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'No se pudo generar el PDF.';
            exit;
        }
    }

    // ── Contactos ────────────────────────────────────────────────────────────

    public function contactosAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $perPage = $this->porPagina();
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $orden   = OrdenListado::leer([], 'nombre');
            $res = $this->contactos->getListado($this->idEmpresa(), trim((string) ($_GET['b'] ?? '')), $page, $perPage, $orden, $this->idUsuarioFiltro());
            $this->json([
                'ok' => true, 'rows' => $res['rows'], 'total' => $res['total'], 'page' => $page,
                'perPage' => $perPage, 'totalPages' => (int) ceil($res['total'] / max(1, $perPage)),
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function contactoAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $id = (int) ($_GET['id'] ?? 0);
        $c = $this->contactos->getPorId($id, $this->idEmpresa());
        if (!$c) {
            $this->json(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
        }
        $this->requireAccesoCrm($c);
        $c['oportunidades'] = $this->oportunidades->getPorContacto($id, $this->idEmpresa(), $this->idUsuarioFiltro());
        $this->json(['ok' => true, 'contacto' => $c]);
    }

    /** Autocompletar de contactos (modal de oportunidad). `id` = traer uno concreto (crear al vuelo). */
    public function buscarContactosAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $id = (int) ($_GET['id'] ?? 0);
        $this->json(['ok' => true, 'data' => $this->contactos->buscar(
            $this->idEmpresa(), trim((string) ($_GET['q'] ?? '')), $this->idUsuarioFiltro(), $id > 0 ? $id : null
        )]);
    }

    public function guardarContactoAjax(): void
    {
        $id = $this->postInt('id');
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        $d = $_POST;
        unset($d['instagram_id'], $d['id_whatsapp_chat']);   // los llena el sistema, no el formulario
        try {
            if ($id > 0) {
                $this->requireAccesoCrm($this->contactos->getPorId($id, $this->idEmpresa()));
                $aviso = $this->contactos->actualizar($id, $d, $this->idEmpresa(), $this->idUsuario());
                $this->json(['ok' => true, 'id' => $id, 'nuevo' => false, 'mensaje' => 'Contacto actualizado.', 'aviso' => $aviso]);
            }
            $r = $this->contactos->crearContacto($d, $this->idEmpresa(), $this->idUsuario());
            $this->json([
                'ok' => true, 'id' => $r['id'], 'nuevo' => !$this->contactos->ultimoGuardadoPrevio, 'aviso' => $r['aviso'],
                'mensaje' => $this->contactos->ultimoGuardadoPrevio
                    ? 'El contacto ya estaba registrado; no se creó otro.'
                    : 'Contacto creado.',
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarContactoAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $this->requireAccesoCrm($this->contactos->getPorId($id, $this->idEmpresa()));
            $this->contactos->eliminar($id, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Contacto eliminado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── Actividades ──────────────────────────────────────────────────────────

    /**
     * Acceso a una actividad: la tiene asignada, o puede ver su oportunidad /
     * su contacto. Corta con 403/404 si no.
     */
    private function requireAccesoActividad(array $d): void
    {
        if ((int) ($d['id_responsable'] ?? 0) === $this->idUsuario() && !empty($d['id'])) {
            return;
        }
        if (!empty($d['id_oportunidad'])) {
            $op = $this->oportunidades->getPorId((int) $d['id_oportunidad'], $this->idEmpresa());
            if (!$op) {
                $this->json(['ok' => false, 'error' => 'Oportunidad no encontrada.'], 404);
            }
            $this->requireAccesoCrm($op);
            return;
        }
        if (!empty($d['id_contacto'])) {
            $c = $this->contactos->getPorId((int) $d['id_contacto'], $this->idEmpresa());
            if (!$c) {
                $this->json(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
            }
            $this->requireAccesoCrm($c);
            return;
        }
        $this->requireAccesoCrm($d);
    }

    /** Pendientes del usuario actual («Mis actividades» y aviso del navbar). */
    public function misActividadesAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $this->json([
            'ok'        => true,
            'data'      => $this->actividades->getPendientesUsuario($this->idEmpresa(), $this->idUsuario()),
            'vencidas'  => $this->actividades->contarVencidasUsuario($this->idEmpresa(), $this->idUsuario()),
        ]);
    }

    public function guardarActividadAjax(): void
    {
        $id = $this->postInt('id');
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            if ($id > 0) {
                $actual = $this->actividades->getPorId($id, $this->idEmpresa());
                if (!$actual) {
                    $this->json(['ok' => false, 'error' => 'Actividad no encontrada.'], 404);
                }
                $this->requireAccesoActividad($actual);
            } else {
                $this->requireAccesoActividad([
                    'id_oportunidad' => $this->postInt('id_oportunidad'),
                    'id_contacto'    => $this->postInt('id_contacto'),
                ]);
            }
            $nuevoId = $this->actividades->guardar($_POST, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $nuevoId, 'mensaje' => $id > 0 ? 'Actividad actualizada.' : 'Actividad registrada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function completarActividadAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $actual = $this->actividades->getPorId($id, $this->idEmpresa());
            if (!$actual) {
                $this->json(['ok' => false, 'error' => 'Actividad no encontrada.'], 404);
            }
            $this->requireAccesoActividad($actual);
            $this->actividades->completar($id, ($_POST['completada'] ?? '1') !== '0', $this->idEmpresa(), $this->idUsuario());
            \App\Services\ContadoresNavbarService::invalidarCrm($this->idEmpresa(), (int) ($actual['id_responsable'] ?: $this->idUsuario()));
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarActividadAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        try {
            $actual = $this->actividades->getPorId($id, $this->idEmpresa());
            if (!$actual) {
                $this->json(['ok' => false, 'error' => 'Actividad no encontrada.'], 404);
            }
            $this->requireAccesoActividad($actual);
            $this->actividades->eliminar($id, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Actividad eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }
}

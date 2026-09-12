<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\IdentificacionTercero;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\Services\LogSistemaService;
use PDO;

class CuentasPorPagarController extends BaseModuloController
{
    private CuentasPorPagarRepository $repo;
    private LogSistemaService $log;

    protected function getRutaModulo(): string
    {
        return 'modulos/cuentas_por_pagar';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuentasPorPagarRepository();
        $this->log  = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS JSON
    // ─────────────────────────────────────────────────────────────────────

    private function jsonSuccess(array $data): never
    {
        $this->json(array_merge(['ok' => true], $data));
    }

    private function jsonError(string $mensaje, int $code = 200): never
    {
        $this->json(['ok' => false, 'error' => $mensaje], $code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VISTA PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios      = $this->repo->getAniosDisponibles($idEmpresa);
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        // Consolidado por RUC (fase 1, SOLO LECTURA): el selector de alcance aparece únicamente
        // si la empresa activa es la matriz del grupo y el usuario tiene acceso a al menos otro
        // establecimiento del mismo RUC (ver EmpresaRepository::getIdsConsolidadoDesdeMatriz).
        $empresaRepo      = new \App\repositories\modulos\EmpresaRepository();
        $idsConsolidado   = $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        $establecimientos = $idsConsolidado ? $empresaRepo->getEtiquetasEstablecimiento($idsConsolidado) : [];

        $this->viewWithLayout('layouts.main', 'modulos/cuentas_por_pagar/index', [
            'titulo'      => 'Cuentas por Pagar',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'anios'       => $anios,
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'idEmpresa'        => $idEmpresa,
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – LISTADO PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas      = $this->getFilasUnificadas($idsEmpresa, $filtros);
        $stats      = $this->repo->getEstadisticas($idsEmpresa, $filtros);
        $antiguedad = $this->repo->getAntiguedad($idsEmpresa, $filtros);

        foreach ($filas as &$f) {
            $f['total']          = number_format((float)$f['total'],          2, '.', '');
            $f['total_pagado']   = number_format((float)$f['total_pagado'],   2, '.', '');
            $f['total_nc']       = number_format((float)($f['total_nc'] ?? 0),2, '.', '');
            $f['total_nd']       = number_format((float)($f['total_nd'] ?? 0),2, '.', '');
            $f['total_retenido'] = number_format((float)($f['total_retenido'] ?? 0), 2, '.', '');
            $f['saldo']          = number_format((float)$f['saldo'],          2, '.', '');
            $f['dias_vencido']   = (int)($f['dias_vencido'] ?? 0);
            // Consolidado: los documentos de OTRO establecimiento son solo lectura
            // (sin pago desde aquí). La vista lo usa para deshabilitar esa acción y
            // mostrar el badge del establecimiento.
            $f['id_empresa'] = (int)($f['id_empresa'] ?? $idEmpresa);
            $f['es_hermana'] = $f['id_empresa'] !== $idEmpresa;
            // Fase 2: pagar desde la matriz un documento de una hermana exige permiso de
            // CREAR en ESA empresa (el egreso se registra en sus libros). Se resuelve una
            // vez por establecimiento; la vista deshabilita el botón cuando es false.
            $permCrearPorEmpresa ??= [];
            if ($f['es_hermana']) {
                $e = $f['id_empresa'];
                if (!isset($permCrearPorEmpresa[$e])) {
                    $permCrearPorEmpresa[$e] = !empty(\App\Helpers\Permisos::porRutaEnEmpresa($this->getRutaModulo(), $e)['crear']);
                }
                $f['puede_operar'] = $permCrearPorEmpresa[$e];
            } else {
                $f['puede_operar'] = true;
            }
        }
        unset($f);

        $this->jsonSuccess([
            'filas'            => $filas,
            'stats'            => $stats,
            'antiguedad'       => $antiguedad,
            'consolidado'      => $consolidado,
            'establecimientos' => count($idsEmpresa),
        ]);
    }

    /**
     * Alcance del listado (fase 1 del consolidado por RUC: SOLO LECTURA desde la matriz).
     * Devuelve [idsEmpresa, consolidado]. El valor `alcance=CONSOLIDADO` que manda la vista
     * solo se honra si la empresa activa es la matriz del grupo RUC y hay hermanas accesibles
     * para el usuario (EmpresaRepository::getIdsConsolidadoDesdeMatriz); en cualquier otro
     * caso se ignora en silencio y el listado queda como siempre (solo la empresa activa).
     * El filtro de proveedor SIEMPRE se expande a las demás filas del mismo proveedor
     * (expandirProveedoresPorIdentificacion): dentro de una empresa, al contribuyente
     * registrado dos veces —con la cédula y con el RUC, que es esa cédula + '001'— y,
     * en consolidado, además a sus hermanas de los otros establecimientos, porque
     * `proveedores` es una tabla por empresa. Sin eso su cartera saldría partida en dos.
     */
    private function resolverAlcance(int $idEmpresa, array &$filtros): array
    {
        $idsEmpresa  = [$idEmpresa];
        $consolidado = false;
        if (($filtros['alcance'] ?? '') === 'CONSOLIDADO') {
            $grupo = (new \App\repositories\modulos\EmpresaRepository())
                ->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
            if ($grupo) {
                $idsEmpresa  = $grupo;
                $consolidado = true;
            }
        }
        $filtros['alcance'] = $consolidado ? 'CONSOLIDADO' : 'ESTABLECIMIENTO';
        if (!empty($filtros['id_proveedor'])) {
            $raw = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string)$filtros['id_proveedor']);
            $filtros['id_proveedor'] = $this->repo->expandirProveedoresPorIdentificacion($raw, $idsEmpresa);
        }
        return [$idsEmpresa, $consolidado];
    }

    /**
     * Empresa sobre la que se consulta un documento (historial, datos para el modal,
     * catálogos de pago). Por defecto la activa; en la vista consolidada cada fila trae su
     * `id_empresa`, y se acepta únicamente si es una hermana del grupo consolidable desde la
     * matriz (misma regla que el listado). Cualquier otro valor se ignora y se responde por
     * la activa.
     */
    private function empresaLectura(): int
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $pedida    = (int) ($_REQUEST['id_empresa'] ?? 0);
        if ($pedida <= 0 || $pedida === $idEmpresa) {
            return $idEmpresa;
        }
        $grupo = (new \App\repositories\modulos\EmpresaRepository())
            ->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        return in_array($pedida, $grupo, true) ? $pedida : $idEmpresa;
    }

    /**
     * Empresa en la que se REGISTRA un pago (fase 2 del consolidado). Por defecto la activa;
     * si la petición trae el `id_empresa` de una hermana del grupo consolidable desde la
     * matriz, el egreso se registra en los libros de ESA empresa (su punto de emisión, su
     * secuencial, su cartera y su contabilidad), y se exige que el usuario tenga permiso de
     * CREAR en ella (Permisos::porRutaEnEmpresa): responde 403 si no lo tiene. Un id fuera del
     * grupo cae a la empresa activa, donde el documento no existe y el pago se rechaza.
     */
    private function empresaEscritura(): int
    {
        $idEmpresa = $this->empresaLectura();
        if ($idEmpresa !== (int) $_SESSION['id_empresa']
            && empty(\App\Helpers\Permisos::porRutaEnEmpresa($this->getRutaModulo(), $idEmpresa)['crear'])) {
            $this->json(['ok' => false, 'error' => 'No tiene permiso para registrar pagos en ese establecimiento.'], 403);
        }
        return $idEmpresa;
    }

    /**
     * Lista unificada de Cuentas por Pagar: compras/liquidaciones/importaciones
     * + saldos iniciales por pagar, en una sola tabla. Cada fila lleva
     * `tipo_fuente` ('COMPRA' | 'LIQUIDACION' | 'IMPORTACION' | 'SALDO_INICIAL')
     * para distinguirla y enrutar.
     */
    private function getFilasUnificadas(int|array $idEmpresa, array $filtros): array
    {
        // Compras + liquidaciones + importaciones
        $docs = $this->repo->getListado($idEmpresa, $filtros);

        // Si el usuario filtró explícitamente por tipo (COMPRA/LIQUIDACION/
        // IMPORTACION), no incluir saldos iniciales (no aplican a ese filtro).
        $tipo = $filtros['tipo_fuente'] ?? '';
        if (in_array($tipo, ['COMPRA', 'LIQUIDACION', 'IMPORTACION'], true)) {
            return $docs;
        }

        // Saldos iniciales (todos; se filtran en PHP por el mismo estado)
        $saldos = $this->repo->getSaldosInicialesCxp($idEmpresa, [
            'estado'       => 'TODOS',
            'id_proveedor' => $filtros['id_proveedor'] ?? '',
            'fecha_desde'  => $filtros['fecha_desde'] ?? '',
            'fecha_hasta'  => $filtros['fecha_hasta'] ?? '',
        ]);

        $estado = $filtros['estado'] ?? 'PENDIENTES';
        $filasSI = [];
        foreach ($saldos as $s) {
            $pend = (float)$s['saldo_pendiente'];
            $venc = ((int)($s['dias_vencido'] ?? 0)) > 0;
            $incluir = match ($estado) {
                'PENDIENTES' => $pend > 0,
                'VENCIDAS'   => $pend > 0 && $venc,
                'AL_DIA'     => $pend > 0 && !$venc,
                'PAGADAS'    => $pend <= 0,
                default      => true, // TODOS
            };
            if (!$incluir) continue;

            $filasSI[] = [
                'tipo_fuente'        => 'SALDO_INICIAL',
                'id'                 => (int)$s['id'],
                'id_empresa'         => (int)($s['id_empresa'] ?? 0),
                'establecimiento'    => $s['establecimiento'] ?? '',
                'empresa_nombre'     => $s['empresa_nombre'] ?? '',
                'id_proveedor'       => $s['id_proveedor'] ?? null,
                'proveedor_nombre'   => $s['nombre_proveedor'],
                'proveedor_ruc'      => $s['ruc_proveedor'],
                'proveedor_email'    => '',
                'proveedor_telefono' => '',
                'numero_documento'   => $s['nro_documento'],
                'fecha_emision'      => $s['fecha_emision'],
                'fecha_vencimiento'  => $s['fecha_vencimiento'],
                'total'              => $s['saldo_inicial'],
                'total_pagado'       => $s['monto_pagado'],
                'total_nc'           => 0,
                'total_nd'           => 0,
                'total_retenido'     => 0,
                'saldo'              => $s['saldo_pendiente'],
                'dias_vencido'       => (int)($s['dias_vencido'] ?? 0),
            ];
        }

        $filas = array_merge($docs, $filasSI);

        usort($filas, function ($a, $b) {
            return strcmp((string)($a['fecha_vencimiento'] ?? ''), (string)($b['fecha_vencimiento'] ?? ''));
        });

        return $filas;
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – REGISTRAR PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function registrarPagoAjax(): void
    {
        $this->requireCrear();
        $idEmpresa   = $this->empresaEscritura(); // consolidado: pago en los libros de la hermana dueña
        $idUsuario   = (int) $_SESSION['id_usuario'];

        $idDoc       = (int)($_POST['id_doc']           ?? 0);
        $tipoFuente  = trim($_POST['tipo_fuente']        ?? 'COMPRA');
        $monto       = (float)($_POST['monto']           ?? 0);
        $idFormaPago = (int)($_POST['id_forma_pago']     ?? 0);
        $idPunto     = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto  = !empty($_POST['id_egreso_concepto']) ? (int)$_POST['id_egreso_concepto'] : null;
        $fechaPago   = trim($_POST['fecha_pago']          ?? date('Y-m-d'));
        $observ      = trim($_POST['observaciones']       ?? '');
        $tipoOp      = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp       = trim($_POST['numero_operacion']        ?? '');
        // fecha_cobro: aplica cuando tipo_operacion es CHEQUE; de lo contrario se usa fecha_pago
        $fechaCobro  = !empty($_POST['fecha_cobro']) ? trim($_POST['fecha_cobro']) : $fechaPago;

        if ($idDoc <= 0 || $monto <= 0 || $idFormaPago <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de pago.');
        }

        $datosPago = [
            'id_empresa'              => $idEmpresa,
            'id_usuario'              => $idUsuario,
            'id_doc'                  => $idDoc,
            'tipo_fuente'             => $tipoFuente,
            'monto'                   => $monto,
            'id_forma_pago'           => $idFormaPago,
            'id_punto_emision'        => $idPunto,
            'id_egreso_concepto'      => $idConcepto,
            'fecha_pago'              => $fechaPago,
            'observaciones'           => $observ,
            'tipo_operacion_bancaria' => $tipoOp,
            'numero_operacion'        => $numOp,
            'fecha_cobro'             => $fechaCobro,
        ];

        $cxpService = new \App\Services\modulos\CuentasPorPagarService($this->repo, $this->log);

        try {
            $res = $cxpService->registrarPago($datosPago);
            unset($res['doc']);
            $this->jsonSuccess($res);
        } catch (\Throwable $e) {
            error_log('[CxP registrarPago] ' . $e->getMessage());
            $this->jsonError('Error al registrar el pago: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – DATOS EN TIEMPO REAL PARA EL MODAL DE PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function getDocumentoParaPagoInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa  = $this->empresaLectura(); // consolidado: puede ser una hermana
        $idDoc      = (int) ($_GET['id_doc']       ?? 0);
        $tipoFuente = trim($_GET['tipo_fuente']    ?? 'COMPRA');

        if ($idDoc <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $doc = $this->repo->getDocumentoParaPago($idDoc, $tipoFuente, $idEmpresa);
        if (!$doc) {
            $this->jsonError('Documento no encontrado.');
            return;
        }

        $this->jsonSuccess(['doc' => $doc]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – HISTORIAL DE PAGOS
    // ─────────────────────────────────────────────────────────────────────

    public function historialPagosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa  = $this->empresaLectura(); // consolidado: puede ser una hermana (solo lectura)
        $idDoc      = (int)($_GET['id_doc']       ?? 0);
        $tipoFuente = trim($_GET['tipo_fuente']   ?? 'COMPRA');

        if ($idDoc <= 0) {
            $this->jsonError('ID de documento inválido.');
        }

        $historial = $this->repo->getHistorialPagos($idDoc, $tipoFuente, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – CATÁLOGOS PARA EL MODAL PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function getCatalogosPagoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura(); // consolidado: series/conceptos/formas de la hermana dueña
        $this->jsonSuccess([
            'puntos'    => $this->repo->getPuntosEmision($idEmpresa),
            'conceptos' => $this->repo->getConceptos($idEmpresa),
            'formas'    => $this->repo->getFormasPago($idEmpresa),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – SIGUIENTE SECUENCIAL DE EGRESO
    // ─────────────────────────────────────────────────────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        $idPunto = (int)($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID de punto de emisión inválido.');
        }
        // El punto debe pertenecer a la empresa del pago (la activa o, en consolidado, la
        // hermana dueña del documento); así no se consulta el secuencial de cualquier serie.
        if (!$this->repo->getPuntoEmisionPorId($idPunto, $this->empresaLectura())) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
        }
        // Fecha del documento: solo pesa si este tipo numera por fecha de emisión
        // (Empresa → Secuenciales); en modo consecutivo el servidor la ignora.
        $fecha = trim($_GET['fecha'] ?? '') ?: null;
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos', $fecha);
        $this->jsonSuccess($res);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – BÚSQUEDA DE PROVEEDORES
    // ─────────────────────────────────────────────────────────────────────

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['proveedores' => []]);
        }

        // Consolidado: se busca en todos los establecimientos del grupo (una fila por
        // identificación). Si el alcance no procede, resolverAlcance() lo deja en la activa.
        $filtros = ['alcance' => strtoupper(trim((string)($_GET['alcance'] ?? '')))];
        [$idsEmpresa] = $this->resolverAlcance($idEmpresa, $filtros);
        $this->jsonSuccess(['proveedores' => $this->repo->buscarProveedores($idsEmpresa, $idEmpresa, $q)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN EXCEL
    // ─────────────────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas = $this->getFilasUnificadas($idsEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Pagar';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);

            $headers = ['Tipo', 'Documento', 'Proveedor', 'RUC', 'F.Emisión', 'F.Vencimiento', 'Días Vencidos', 'Total', 'Abonos', 'Notas de Crédito', 'Retenciones', 'Pagado', 'Saldo', 'Estado'];
            // Columnas de montos (1-based): número con 2 decimales, sin separador de miles
            $formatos = array_fill_keys([8, 9, 10, 11, 12, 13], '0.00');
            if ($consolidado) {
                // Consolidado: primera columna con el establecimiento dueño del documento
                array_unshift($headers, 'Estab.');
                $formatos = array_fill_keys([9, 10, 11, 12, 13, 14], '0.00');
            }

            $exportData = [];
            foreach ($filas as $r) {
                $dias      = (int)($r['dias_vencido'] ?? 0);
                $saldo     = (float)$r['saldo'];
                $estadoCxP = $saldo <= 0 ? 'PAGADA' : ($dias > 0 ? "VENCIDA ({$dias} días)" : 'VIGENTE');
                $abonos    = (float)($r['total_pagado'] ?? 0);
                $nc        = (float)($r['total_nc'] ?? 0);
                $ret       = (float)($r['total_retenido'] ?? 0);
                $exportData[] = [
                    ...($consolidado ? [(string)($r['establecimiento'] ?? '')] : []),
                    match ($r['tipo_fuente']) {
                        'SALDO_INICIAL' => 'Saldo inicial',
                        'LIQUIDACION'   => 'Liquidación',
                        'IMPORTACION'   => 'Importación',
                        default         => 'Factura',
                    },
                    (string)($r['numero_documento'] ?? ''),
                    (string)($r['proveedor_nombre'] ?? ''),
                    (string)($r['proveedor_ruc'] ?? ''),
                    $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                    $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                    $dias > 0 ? $dias : 0,
                    round((float)$r['total'], 2),
                    round($abonos, 2),
                    round($nc, 2),
                    round($ret, 2),
                    round($abonos + $nc + $ret, 2),
                    round($saldo, 2),
                    $estadoCxP,
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('cuentas_por_pagar', $headers, $exportData, 'Cuentas por Pagar', $nombreEmpresa, $filtrosTxt, $formatos);
            exit;
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                $_SESSION['cuentas_por_pagar_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . $this->getRutaModulo());
            }
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

        $filas = $this->getFilasUnificadas($idsEmpresa, $filtros);
        $stats = $this->repo->getEstadisticas($idsEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Pagar';
            $filtrosTxt    = $this->describirFiltros($idsEmpresa, $filtros);

            // Consolidado: columna "Estab." al inicio; se le resta ancho a "Proveedor" para
            // que la suma siga en 100% (table-layout: fixed).
            $wEst  = $consolidado ? 6 : 0;
            $wProv = 28 - $wEst;
            $tdEst = fn (array $r): string => $consolidado
                ? "<td class='text-center' style='width:{$wEst}%;'>" . htmlspecialchars((string)($r['establecimiento'] ?? '')) . "</td>"
                : '';

            $totalSaldo   = 0;
            $totalPagRet  = 0;   // pagado + retenido + NC (lo que muestra la columna)
            $totalTotal   = 0;
            $filaHtml     = '';

            foreach ($filas as $r) {
                $dias  = (int)($r['dias_vencido'] ?? 0);
                $ts    = (float)$r['total'];
                $tp    = (float)$r['total_pagado'];
                $tsal  = (float)$r['saldo'];
                $tret  = (float)($r['total_retenido'] ?? 0) + (float)($r['total_nc'] ?? 0);
                $totalTotal  += $ts;
                $totalPagRet += $tp + $tret;
                $totalSaldo  += $tsal;
                $color = $dias > 0 && $tsal > 0 ? 'color:#dc3545;' : '';
                $badge = $tsal <= 0
                    ? "<small style='color:#6c757d;'>Pagada</small>"
                    : ($dias > 0
                        ? "<small style='color:#dc3545;font-weight:bold;'>{$dias}d vencida</small>"
                        : "<small style='color:#198754;'>Vigente</small>");
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $tipo  = match ($r['tipo_fuente']) {
                    'SALDO_INICIAL' => 'Saldo ini.',
                    'LIQUIDACION'   => 'Liq.',
                    'IMPORTACION'   => 'Imp.',
                    default         => 'Fac.',
                };

                $filaHtml .= "<tr style='{$color}'>{$tdEst($r)}
                    <td style='width:14%;'><small style='color:#6c757d;'>{$tipo}</small><br>" . htmlspecialchars($r['numero_documento'] ?? '') . "</td>
                    <td style='width:{$wProv}%;'>" . htmlspecialchars($r['proveedor_nombre'] ?? '') . "</td>
                    <td class='text-center' style='width:12%;'>{$fEmis}</td>
                    <td class='text-center' style='width:16%;'>{$fVenc}<br>{$badge}</td>
                    <td class='text-end' style='width:10%;'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='width:10%;color:#198754;'>\$" . number_format($tp + $tret, 2) . "</td>
                    <td class='text-end' style='width:10%;{$color}font-weight:bold;'>\$" . number_format($tsal, 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                th { background: #e9ecef; border: 1px solid #ccc; padding: 4px 5px; text-align: center; font-size: 8pt; }
                td { border: 1px solid #ddd; padding: 3px 5px; font-size: 7.5pt; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 10px; }
                .header h2 { margin: 0 0 2px 0; font-size: 13pt; }
                .header h3 { margin: 0 0 2px 0; font-size: 10pt; color: #555; }
                .header p  { margin: 0; font-size: 7.5pt; color: #777; }
                .stats-box { text-align: center; padding: 5px; }
                .stat-val  { font-size: 11pt; font-weight: bold; }
                table.filtros td { border: none; padding: 1px 4px; font-size: 7.5pt; }
                table.filtros td.filtro-lbl { width: 12%; font-weight: bold; color: #555; }
                table.filtros td.filtro-val { width: 88%; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Cuentas por Pagar</h3>
                <p>Generado: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table class="filtros" style="border:1px solid #ccc;background:#f8f9fa;">
                <?php foreach ($filtrosTxt as $lbl => $val): ?>
                <tr>
                    <td class="filtro-lbl"><?= htmlspecialchars($lbl) ?>:</td>
                    <td class="filtro-val"><?= htmlspecialchars($val) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <table class="stats">
                <tr>
                    <td class="stats-box" style="width:25%;">
                        <div>Documentos</div>
                        <div class="stat-val"><?= $stats['total_docs'] ?></div>
                    </td>
                    <td class="stats-box" style="width:25%;">
                        <div>Saldo Total</div>
                        <div class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></div>
                    </td>
                    <td class="stats-box" style="width:25%;color:#dc3545;">
                        <div>Vencido</div>
                        <div class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></div>
                    </td>
                    <td class="stats-box" style="width:25%;color:#198754;">
                        <div>Al Día</div>
                        <div class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></div>
                    </td>
                </tr>
            </table>
            <table>
                <thead>
                    <tr>
                        <?php if ($consolidado): ?><th style="width:<?= $wEst ?>%;">Estab.</th><?php endif; ?>
                        <th style="width:14%;">Documento</th>
                        <th style="width:<?= $wProv ?>%;">Proveedor</th>
                        <th style="width:12%;">F. Emisión</th>
                        <th style="width:16%;">F. Vencimiento</th>
                        <th style="width:10%;">Total</th>
                        <th style="width:10%;">Pagado/Ret/NC</th>
                        <th style="width:10%;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $filaHtml ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <td colspan="<?= $consolidado ? 5 : 4 ?>" class="text-end" style="width:70%;">TOTALES:</td>
                        <td class="text-end" style="width:10%;">$<?= number_format($totalTotal, 2) ?></td>
                        <td class="text-end" style="width:10%;color:#198754;">$<?= number_format($totalPagRet, 2) ?></td>
                        <td class="text-end" style="width:10%;color:#dc3545;">$<?= number_format($totalSaldo, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            </page>
            <?php
            $html     = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorPagar_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        return [
            'estado'       => $_REQUEST['estado']       ?? 'PENDIENTES',
            'fecha_desde'  => $_REQUEST['fecha_desde']  ?? '',
            'fecha_hasta'  => $_REQUEST['fecha_hasta']  ?? '',
            'id_proveedor' => $_REQUEST['id_proveedor'] ?? '',
            'tipo_fuente'  => $_REQUEST['tipo_fuente']  ?? '',
            // ESTABLECIMIENTO (solo la empresa activa) | CONSOLIDADO (todo el grupo RUC;
            // solo se honra desde la matriz — ver resolverAlcance()).
            'alcance'      => strtoupper(trim((string)($_REQUEST['alcance'] ?? ''))),
        ];
    }

    /**
     * Descripción legible de los filtros aplicados (encabezado de PDF y Excel).
     * Devuelve etiqueta => valor, con los ids de proveedor resueltos a nombre.
     */
    private function describirFiltros(int|array $idsEmpresa, array $filtros): array
    {
        $idsEmpresa = (array) $idsEmpresa;
        $tipoLbl = [
            ''            => 'Todos (facturas, liquidaciones, importaciones y saldos iniciales)',
            'COMPRA'      => 'Solo facturas',
            'LIQUIDACION' => 'Solo liquidaciones',
            'IMPORTACION' => 'Solo importaciones',
        ];
        $estadoLbl = [
            'PENDIENTES' => 'Saldo pendiente',
            'VENCIDAS'   => 'Vencidas',
            'AL_DIA'     => 'Al día',
            'PAGADAS'    => 'Pagadas',
            'TODOS'      => 'Todos',
        ];

        $fmt = fn(string $f): string => $f !== '' ? date('d-m-Y', strtotime($f)) : '';
        $desde = $fmt((string)($filtros['fecha_desde'] ?? ''));
        $hasta = $fmt((string)($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $periodo = "Del {$desde} al {$hasta}";
        } elseif ($desde !== '') {
            $periodo = "Desde {$desde}";
        } elseif ($hasta !== '') {
            $periodo = "Hasta {$hasta} (fecha de corte)";
        } else {
            $periodo = 'Sin límite de fechas';
        }

        $proveedorTxt = 'Todos';
        if (!empty($filtros['id_proveedor'])) {
            $ids = is_array($filtros['id_proveedor']) ? $filtros['id_proveedor'] : explode(',', (string)$filtros['id_proveedor']);
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if ($ids) {
                // El filtro viene expandido a las demás filas del mismo proveedor (cédula/RUC y,
                // en consolidado, otros establecimientos): se nombra una sola vez por proveedor.
                $nombres = [];
                $vistos  = [];
                foreach ($this->repo->getProveedoresPorIds($ids, $idsEmpresa) as $id => $p) {
                    $clave = IdentificacionTercero::claveGrupo($p['identificacion'], 'id:' . $id);
                    if (isset($vistos[$clave])) {
                        continue;
                    }
                    $vistos[$clave] = true;
                    $nombres[] = trim($p['nombre'] . ($p['identificacion'] !== '' ? " ({$p['identificacion']})" : ''));
                }
                $proveedorTxt = $nombres ? implode(', ', $nombres) : implode(', ', array_map(static fn ($i) => "#{$i}", $ids));
            }
        }

        $alcanceTxt = 'Este establecimiento';
        if (($filtros['alcance'] ?? '') === 'CONSOLIDADO') {
            $etq = (new \App\repositories\modulos\EmpresaRepository())->getEtiquetasEstablecimiento($idsEmpresa);
            $alcanceTxt = 'Consolidado por RUC (' . count($etq) . ' establecimientos: ' . implode(' · ', $etq) . ')';
        }

        $tipo   = (string)($filtros['tipo_fuente'] ?? '');
        $estado = (string)($filtros['estado'] ?? 'PENDIENTES');

        return [
            'Alcance'           => $alcanceTxt,
            'Tipo de documento' => $tipoLbl[$tipo] ?? $tipo,
            'Estado'            => $estadoLbl[$estado] ?? $estado,
            'Período'           => $periodo,
            'Proveedor'         => $proveedorTxt,
        ];
    }

    // ─── SALDOS INICIALES CXP (para mostrar en la vista de CXP) ─────────────

    public function getSaldosInicialesCxpAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = [
            'estado'         => $_GET['estado']         ?? 'TODOS',
            'tipo_documento' => $_GET['tipo_documento']  ?? '',
            'id_proveedor'   => $_GET['id_proveedor']   ?? '',
        ];
        $filas = $this->repo->getSaldosInicialesCxp($idEmpresa, $filtros);
        $this->jsonSuccess(['filas' => $filas]);
    }

    /**
     * Pago de un saldo inicial CXP desde la tabla unificada de Cuentas por Pagar.
     * Delega en SaldosInicialesService::registrarPagoCxp (mismo flujo de egresos).
     */
    public function registrarPagoSaldoInicialAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = $this->empresaEscritura(); // consolidado: pago en los libros de la hermana dueña
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idSaldo = (int)($_POST['id_saldo'] ?? 0);
        $idPunto = (int)($_POST['id_punto_emision'] ?? 0);
        $monto   = (float)($_POST['monto'] ?? 0);
        $idForma = (int)($_POST['id_forma_pago'] ?? 0);

        if ($idSaldo <= 0 || $idPunto <= 0 || $monto <= 0 || $idForma <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de pago.');
            return;
        }

        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('La serie (punto de emisión) no es válida o está inactiva.');
            return;
        }

        try {
            $service = new \App\Services\modulos\SaldosInicialesService(
                new \App\repositories\modulos\SaldosInicialesRepository(),
                new \App\Rules\modulos\SaldosInicialesRules(),
                new \App\Services\LogSistemaService()
            );
            $result = $service->registrarPagoCxp($idSaldo, $idEmpresa, $idUsuario, [
                'id_punto_emision'       => $idPunto,
                'punto'                  => $punto,
                'monto'                  => $monto,
                'id_forma_pago'          => $idForma,
                'id_egreso_concepto'     => !empty($_POST['id_egreso_concepto']) ? (int)$_POST['id_egreso_concepto'] : null,
                'fecha_pago'             => $_POST['fecha_pago'] ?? date('Y-m-d'),
                'observaciones'          => $_POST['observaciones'] ?? '',
                'tipo_operacion_bancaria'=> $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_operacion'       => $_POST['numero_operacion'] ?? '',
            ]);
            $this->jsonSuccess(array_merge($result, [
                'mensaje'     => "Pago registrado correctamente. Egreso: {$result['numero_egreso']}",
                'nuevo_saldo' => $result['nuevo_saldo'] ?? null,
                'pagada'      => $result['pagado'] ?? false,
            ]));
        } catch (\Throwable $e) {
            error_log('[CxP pago saldo inicial] ' . $e->getMessage());
            $this->jsonError('Error al registrar el pago: ' . $e->getMessage());
        }
    }

    /**
     * Historial de pagos de un saldo inicial CXP (para la tabla unificada).
     */
    public function historialPagosSaldoInicialAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = $this->empresaLectura(); // consolidado: puede ser una hermana (solo lectura)
        $idSaldo   = (int)($_GET['id_saldo'] ?? 0);
        if ($idSaldo <= 0) {
            $this->jsonError('ID de saldo inválido.');
            return;
        }
        $repo = new \App\repositories\modulos\SaldosInicialesRepository();
        $historial = $repo->getHistorialPagosCxp($idSaldo, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }
}

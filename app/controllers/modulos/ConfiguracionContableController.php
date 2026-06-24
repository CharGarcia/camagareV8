<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\OpcionIngresoEgresoRepository;
use App\repositories\modulos\FormaPagoRepository;
use App\Services\modulos\AsientoProgramadoService;
use App\Services\modulos\AsientosTipoService;
use App\core\Database;
use PDO;

class ConfiguracionContableController extends BaseModuloController
{
    private AsientoProgramadoRepository $repository;
    private AsientoProgramadoService $service;
    private const RUTA_MODULO = 'modulos/configuracion-contable';

    public function __construct()
    {
        parent::__construct();
        $this->repository = new AsientoProgramadoRepository();
        $this->service = new AsientoProgramadoService();
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
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage  = 15;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Cargar los asientos tipo base de manera global
        $asientoTipoService = new AsientosTipoService();
        $asientosTipo = $asientoTipoService->getListado('', 1, 200, 'codigo', 'ASC')['rows'];

        $this->viewWithLayout('layouts.main', 'modulos.configuracion_contable.index', [
            'titulo'       => 'Configuración Contable',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'perPage'      => $perPage,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'vistaConfig'  => $prefsVista,
            'asientosTipo' => $asientosTipo,
            'fullWidth'    => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'id');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage   = 15;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        $tiposTextos = [
            'ventas_factura' => 'Ventas con Factura',
            'ventas_recibo' => 'Ventas con Recibo',
            'adquisiciones_compras' => 'Adquisiciones de Compras/Servicios',
            'retenciones_venta' => 'Retenciones en Venta',
            'retenciones_compra' => 'Retenciones en Compra',
            'ingresos_egresos' => 'Ingresos y Egresos',
            'cobros_pagos' => 'Cobros y Pagos',
            'nomina' => 'Nómina'
        ];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-sliders fs-3 d-block mb-2 text-muted"></i> No se encontraron configuraciones contables.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $conceptoText = $tiposTextos[$r['asiento_tipo_concepto']] ?? ucwords(str_replace('_', ' ', $r['asiento_tipo_concepto']));
                
                $refNombre = 'General (Toda la Empresa)';
                if ($r['tipo_referencia'] === 'cliente' && !empty($r['cliente_nombre'])) {
                    $refNombre = '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small me-1">Cliente</span> ' . htmlspecialchars($r['cliente_nombre']);
                } elseif ($r['tipo_referencia'] === 'proveedor' && !empty($r['proveedor_nombre'])) {
                    $refNombre = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small me-1">Proveedor</span> ' . htmlspecialchars($r['proveedor_nombre']);
                }

                echo '<tr class="asiento-programado-row" role="button" onclick="ASIENTOPROG_editar(' . $r['id'] . ')">
                        <td class="ps-3 fw-bold" data-col="codigo">' . htmlspecialchars($r['asiento_tipo_codigo']) . '</td>
                        <td data-col="concepto">' . htmlspecialchars($conceptoText) . ' - ' . htmlspecialchars($r['asiento_tipo_referencia']) . '</td>
                        <td data-col="cuenta">' . htmlspecialchars($r['cuenta_codigo']) . ' - ' . htmlspecialchars($r['cuenta_nombre']) . '</td>
                        <td data-col="entidad">' . $refNombre . '</td>
                        <td class="text-center" onclick="event.stopPropagation()">
                            <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminar(' . $r['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data = $this->repository->findByIdAndEmpresa($id, $idEmpresa);

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'No se encontró la configuración contable.']);
        } else {
            $entidadNombre = '';
            if (!empty($data['id_referencia']) && !empty($data['tipo_referencia'])) {
                $db = Database::getConnection();
                if ($data['tipo_referencia'] === 'cliente') {
                    $st = $db->prepare("SELECT nombre FROM clientes WHERE id = ? LIMIT 1");
                    $st->execute([$data['id_referencia']]);
                    $entidadNombre = $st->fetchColumn() ?: '';
                } elseif ($data['tipo_referencia'] === 'proveedor') {
                    $st = $db->prepare("SELECT razon_social FROM proveedores WHERE id = ? LIMIT 1");
                    $st->execute([$data['id_referencia']]);
                    $entidadNombre = $st->fetchColumn() ?: '';
                }
            }

            $data['entidad_nombre'] = $entidadNombre;
            echo json_encode(['ok' => true, 'data' => $data]);
        }
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $data = [
            'id_asiento_tipo'  => (int) ($_POST['id_asiento_tipo'] ?? 0),
            'id_cuenta'        => (int) ($_POST['id_cuenta'] ?? 0),
            'id_referencia'    => !empty($_POST['id_referencia']) ? (int) $_POST['id_referencia'] : null,
            'tipo_referencia'  => !empty($_POST['tipo_referencia']) ? trim($_POST['tipo_referencia']) : null
        ];

        try {
            $id = $this->service->registrar($data, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Configuración contable registrada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $data = [
            'id_asiento_tipo'  => (int) ($_POST['id_asiento_tipo'] ?? 0),
            'id_cuenta'        => (int) ($_POST['id_cuenta'] ?? 0),
            'id_referencia'    => !empty($_POST['id_referencia']) ? (int) $_POST['id_referencia'] : null,
            'tipo_referencia'  => !empty($_POST['tipo_referencia']) ? trim($_POST['tipo_referencia']) : null
        ];

        try {
            if ($id <= 0) {
                throw new \Exception('ID de configuración inválido.');
            }
            $this->service->actualizar($id, $data, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Configuración contable actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) {
                throw new \Exception('ID de configuración inválido.');
            }
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Configuración contable eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Obtiene todas las reglas de homologación general asociadas a un tipo de asiento
     */
    public function cargarConfiguracionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipoAsiento = trim($_GET['tipo_asiento'] ?? $_POST['tipo_asiento'] ?? '');

        if ($tipoAsiento === '') {
            echo json_encode(['ok' => false, 'error' => 'Tipo de asiento no especificado.']);
            exit;
        }

        try {
            // Caso especial: Ingresos y Egresos se arma desde el módulo Opciones de Ingreso/Egreso,
            // separado en dos bloques (ingresos = Haber, egresos = Debe).
            if ($tipoAsiento === 'ingresos_egresos') {
                $ingresos = $this->repository->getReglasOpcionesIngresoEgreso($idEmpresa, 'ingreso');
                $egresos  = $this->repository->getReglasOpcionesIngresoEgreso($idEmpresa, 'egreso');
                $metodo   = $this->repository->getMetodoPreferencia($idEmpresa, $tipoAsiento);
                echo json_encode([
                    'ok'       => true,
                    'modo'     => 'ingresos_egresos',
                    'ingresos' => $ingresos,
                    'egresos'  => $egresos,
                    'metodo'   => $metodo
                ]);
                exit;
            }

            // Caso especial: Cobros y Pagos se arma desde el módulo Formas de Cobros/Pagos,
            // separado en dos bloques (cobros = Debe, pagos = Haber).
            if ($tipoAsiento === 'cobros_pagos') {
                $cobros = $this->repository->getReglasFormasCobrosPagos($idEmpresa, 'cobro');
                $pagos  = $this->repository->getReglasFormasCobrosPagos($idEmpresa, 'pago');
                $metodo = $this->repository->getMetodoPreferencia($idEmpresa, $tipoAsiento);
                echo json_encode([
                    'ok'     => true,
                    'modo'   => 'cobros_pagos',
                    'cobros' => $cobros,
                    'pagos'  => $pagos,
                    'metodo' => $metodo
                ]);
                exit;
            }

            if ($tipoAsiento === 'retenciones_venta') {
                $reglas = $this->repository->getReglasRetencionesVenta($idEmpresa);
            } else {
                $reglas = $this->repository->getReglasGeneralesPorConcepto($idEmpresa, $tipoAsiento);
                
                // Aumentar reglas específicas para las tarifas de IVA si estamos en ventas_factura
                if ($tipoAsiento === 'ventas_factura') {
                    $reglasIva = $this->repository->getReglasIvaVentas($idEmpresa);
                    $reglas = array_merge($reglas, $reglasIva);
                }
            }

            $metodo = $this->repository->getMetodoPreferencia($idEmpresa, $tipoAsiento);
            echo json_encode(['ok' => true, 'data' => $reglas, 'metodo' => $metodo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Registra o actualiza al vuelo una regla general de cuenta contable
     */
    public function guardarReglaGeneralAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idAsientoTipo = (int) ($_POST['id_asiento_tipo'] ?? 0);
        $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
        
        $tipoReferencia = trim($_POST['tipo_referencia'] ?? '');
        $idReferencia = (int) ($_POST['id_referencia'] ?? 0);

        if ($idCuenta <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Parámetro incompleto o cuenta contable inválida.']);
            exit;
        }

        try {
            if ($tipoReferencia === 'iva_ventas_factura') {
                if ($idReferencia <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'ID de referencia de tarifa inválido.']);
                    exit;
                }
                // Verificar si ya existe una regla para este IVA
                $reglaExistente = $this->repository->getReglaGeneralIva($idEmpresa, $idReferencia);
                
                if ($reglaExistente) {
                    $dataUpdate = [
                        'id_asiento_tipo' => 0,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idReferencia,
                        'tipo_referencia' => 'iva_ventas_factura',
                        'updated_by'      => $idUsuario
                    ];
                    $this->service->actualizar((int)$reglaExistente['id'], $dataUpdate, $idEmpresa, $idUsuario);
                    $idProgramado = $reglaExistente['id'];
                    $msg = 'Configuración contable actualizada correctamente.';
                } else {
                    $dataInsert = [
                        'id_asiento_tipo' => 0,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idReferencia,
                        'tipo_referencia' => 'iva_ventas_factura'
                    ];
                    $idProgramado = $this->service->registrar($dataInsert, $idEmpresa, $idUsuario);
                    $msg = 'Configuración contable registrada correctamente.';
                }
            } elseif ($tipoReferencia === 'retenciones_venta_debe' || $tipoReferencia === 'retenciones_venta_haber') {
                if ($idReferencia <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'ID de referencia de retención SRI inválido.']);
                    exit;
                }
                // Verificar si ya existe una regla para esta retención en venta (según tipo Debe o Haber)
                $db = Database::getConnection();
                $stCheck = $db->prepare("SELECT id FROM asientos_programados 
                                         WHERE id_empresa = ? AND id_referencia = ? AND tipo_referencia = ? AND eliminado = false");
                $stCheck->execute([$idEmpresa, $idReferencia, $tipoReferencia]);
                $idProgramado = $stCheck->fetchColumn();

                if ($idProgramado) {
                    $dataUpdate = [
                        'id_asiento_tipo' => 0,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idReferencia,
                        'tipo_referencia' => $tipoReferencia,
                        'updated_by'      => $idUsuario
                    ];
                    $this->service->actualizar((int)$idProgramado, $dataUpdate, $idEmpresa, $idUsuario);
                    $idProgramado = (int)$idProgramado;
                    $msg = 'Configuración contable de retención actualizada.';
                } else {
                    $dataInsert = [
                        'id_asiento_tipo' => 0,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idReferencia,
                        'tipo_referencia' => $tipoReferencia
                    ];
                    $idProgramado = $this->service->registrar($dataInsert, $idEmpresa, $idUsuario);
                    $msg = 'Configuración contable de retención registrada.';
                }
            } else {
                if ($idAsientoTipo <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos o cuenta contable inválida.']);
                    exit;
                }
                // Verificar si ya existe una regla general para este id_asiento_tipo
                $reglaExistente = $this->repository->getReglaGeneralPorAsientoTipo($idEmpresa, $idAsientoTipo);

                if ($reglaExistente) {
                    // Actualizar
                    $dataUpdate = [
                        'id_asiento_tipo' => $idAsientoTipo,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idAsientoTipo,
                        'tipo_referencia' => 'asientos tipo',
                        'updated_by'      => $idUsuario
                    ];
                    $this->service->actualizar((int)$reglaExistente['id'], $dataUpdate, $idEmpresa, $idUsuario);
                    $idProgramado = $reglaExistente['id'];
                    $msg = 'Configuración contable actualizada correctamente.';
                } else {
                    // Crear nueva
                    $dataInsert = [
                        'id_asiento_tipo' => $idAsientoTipo,
                        'id_cuenta'       => $idCuenta,
                        'id_referencia'   => $idAsientoTipo,
                        'tipo_referencia' => 'asientos tipo'
                    ];
                    $idProgramado = $this->service->registrar($dataInsert, $idEmpresa, $idUsuario);
                    $msg = 'Configuración contable registrada correctamente.';
                }
            }

            echo json_encode(['ok' => true, 'msg' => $msg, 'id_programado' => $idProgramado]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Elimina lógicamente una regla de cuenta contable general al vuelo
     */
    public function eliminarReglaGeneralAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idAsientoTipo = (int) ($_POST['id_asiento_tipo'] ?? 0);
        $tipoReferencia = trim($_POST['tipo_referencia'] ?? '');
        $idReferencia = (int) ($_POST['id_referencia'] ?? 0);

        try {
            if ($tipoReferencia === 'iva_ventas_factura') {
                $reglaExistente = $this->repository->getReglaGeneralIva($idEmpresa, $idReferencia);
            } elseif ($tipoReferencia === 'retenciones_venta_debe' || $tipoReferencia === 'retenciones_venta_haber') {
                $db = Database::getConnection();
                $stCheck = $db->prepare("SELECT id FROM asientos_programados 
                                         WHERE id_empresa = ? AND id_referencia = ? AND tipo_referencia = ? AND eliminado = false LIMIT 1");
                $stCheck->execute([$idEmpresa, $idReferencia, $tipoReferencia]);
                $reglaExistente = $stCheck->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                if ($idAsientoTipo <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'Parámetro de regla inválido.']);
                    exit;
                }
                $reglaExistente = $this->repository->getReglaGeneralPorAsientoTipo($idEmpresa, $idAsientoTipo);
            }
            
            if ($reglaExistente) {
                $this->service->eliminar((int)$reglaExistente['id'], $idEmpresa, $idUsuario);
                echo json_encode(['ok' => true, 'msg' => 'Configuración contable eliminada correctamente.']);
            } else {
                echo json_encode(['ok' => true, 'msg' => 'No había ninguna cuenta configurada para esta regla.']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Asigna al vuelo la cuenta contable a una opción de Ingreso/Egreso.
     * Se guarda en dos lugares: el módulo de Opciones (id_cuenta_contable) y en asientos_programados.
     */
    public function guardarReglaOpcionAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idOpcion   = (int) ($_POST['id_opcion'] ?? 0);
        $idCuenta   = (int) ($_POST['id_cuenta'] ?? 0);
        $naturaleza = trim($_POST['naturaleza'] ?? '');

        if ($idOpcion <= 0 || $idCuenta <= 0 || !in_array($naturaleza, ['ingreso', 'egreso'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos o inválidos.']);
            exit;
        }

        $tipoReferencia = $naturaleza === 'ingreso' ? 'opcion_ingreso' : 'opcion_egreso';

        try {
            // 1. Sincronizar la cuenta en el módulo de Opciones de Ingreso/Egreso
            $opcRepo = new OpcionIngresoEgresoRepository();
            $opcRepo->updateCuentaContable($idOpcion, $idEmpresa, $idCuenta, $idUsuario);

            // 2. Crear o actualizar el asiento programado asociado
            $reglaExistente = $this->repository->getReglaPorReferencia($idEmpresa, $idOpcion, $tipoReferencia);
            $dataRule = [
                'id_asiento_tipo' => 0,
                'id_cuenta'       => $idCuenta,
                'id_referencia'   => $idOpcion,
                'tipo_referencia' => $tipoReferencia
            ];

            if ($reglaExistente) {
                $dataRule['updated_by'] = $idUsuario;
                $this->service->actualizar((int) $reglaExistente['id'], $dataRule, $idEmpresa, $idUsuario);
                $msg = 'Cuenta contable actualizada correctamente.';
            } else {
                $this->service->registrar($dataRule, $idEmpresa, $idUsuario);
                $msg = 'Cuenta contable asignada correctamente.';
            }

            echo json_encode(['ok' => true, 'msg' => $msg]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Quita la cuenta contable de una opción de Ingreso/Egreso (en ambos lugares).
     */
    public function eliminarReglaOpcionAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idOpcion   = (int) ($_POST['id_opcion'] ?? 0);
        $naturaleza = trim($_POST['naturaleza'] ?? '');

        if ($idOpcion <= 0 || !in_array($naturaleza, ['ingreso', 'egreso'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos o inválidos.']);
            exit;
        }

        $tipoReferencia = $naturaleza === 'ingreso' ? 'opcion_ingreso' : 'opcion_egreso';

        try {
            // 1. Limpiar la cuenta en el módulo de Opciones
            $opcRepo = new OpcionIngresoEgresoRepository();
            $opcRepo->updateCuentaContable($idOpcion, $idEmpresa, null, $idUsuario);

            // 2. Eliminar lógicamente el asiento programado asociado, si existe
            $reglaExistente = $this->repository->getReglaPorReferencia($idEmpresa, $idOpcion, $tipoReferencia);
            if ($reglaExistente) {
                $this->service->eliminar((int) $reglaExistente['id'], $idEmpresa, $idUsuario);
            }

            echo json_encode(['ok' => true, 'msg' => 'Cuenta contable desvinculada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Asigna al vuelo la cuenta contable a una forma de Cobro/Pago.
     * Se guarda en dos lugares: el módulo de Formas (id_cuenta_contable) y en asientos_programados.
     */
    public function guardarReglaFormaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idForma  = (int) ($_POST['id_forma'] ?? 0);
        $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
        $flujo    = trim($_POST['flujo'] ?? '');

        if ($idForma <= 0 || $idCuenta <= 0 || !in_array($flujo, ['cobro', 'pago'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos o inválidos.']);
            exit;
        }

        $tipoReferencia = $flujo === 'cobro' ? 'forma_cobro' : 'forma_pago';

        try {
            // 1. Sincronizar la cuenta en el módulo de Formas de Cobros/Pagos
            $formaRepo = new FormaPagoRepository();
            $formaRepo->updateCuentaContable($idForma, $idEmpresa, $idCuenta, $idUsuario);

            // 2. Crear o actualizar el asiento programado asociado
            $reglaExistente = $this->repository->getReglaPorReferencia($idEmpresa, $idForma, $tipoReferencia);
            $dataRule = [
                'id_asiento_tipo' => 0,
                'id_cuenta'       => $idCuenta,
                'id_referencia'   => $idForma,
                'tipo_referencia' => $tipoReferencia
            ];

            if ($reglaExistente) {
                $dataRule['updated_by'] = $idUsuario;
                $this->service->actualizar((int) $reglaExistente['id'], $dataRule, $idEmpresa, $idUsuario);
                $msg = 'Cuenta contable actualizada correctamente.';
            } else {
                $this->service->registrar($dataRule, $idEmpresa, $idUsuario);
                $msg = 'Cuenta contable asignada correctamente.';
            }

            echo json_encode(['ok' => true, 'msg' => $msg]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Quita la cuenta contable de una forma de Cobro/Pago (en ambos lugares).
     */
    public function eliminarReglaFormaAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idForma = (int) ($_POST['id_forma'] ?? 0);
        $flujo   = trim($_POST['flujo'] ?? '');

        if ($idForma <= 0 || !in_array($flujo, ['cobro', 'pago'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos o inválidos.']);
            exit;
        }

        $tipoReferencia = $flujo === 'cobro' ? 'forma_cobro' : 'forma_pago';

        try {
            // 1. Limpiar la cuenta en el módulo de Formas
            $formaRepo = new FormaPagoRepository();
            $formaRepo->updateCuentaContable($idForma, $idEmpresa, null, $idUsuario);

            // 2. Eliminar lógicamente el asiento programado asociado, si existe
            $reglaExistente = $this->repository->getReglaPorReferencia($idEmpresa, $idForma, $tipoReferencia);
            if ($reglaExistente) {
                $this->service->eliminar((int) $reglaExistente['id'], $idEmpresa, $idUsuario);
            }

            echo json_encode(['ok' => true, 'msg' => 'Cuenta contable desvinculada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Busca clientes o proveedores para el autocompletado en el formulario.
     */
    public function searchEntidadesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo      = trim($_GET['tipo'] ?? '');
        $q         = trim($_GET['q'] ?? '');

        if ($q === '') {
            echo json_encode([]);
            exit;
        }

        $db = Database::getConnection();
        $results = [];

        if ($tipo === 'cliente') {
            $st = $db->prepare("SELECT id, nombre AS text, identificacion FROM clientes 
                                WHERE id_empresa = ? AND eliminado = false AND (nombre ILIKE ? OR identificacion ILIKE ?) 
                                ORDER BY nombre ASC LIMIT 10");
            $st->execute([$idEmpresa, "%$q%", "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tipo === 'proveedor') {
            $st = $db->prepare("SELECT id, razon_social AS text, identificacion FROM proveedores 
                                WHERE id_empresa = ? AND eliminado = false AND (razon_social ILIKE ? OR identificacion ILIKE ?) 
                                ORDER BY razon_social ASC LIMIT 10");
            $st->execute([$idEmpresa, "%$q%", "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tipo === 'producto') {
            $st = $db->prepare("SELECT id, nombre AS text, codigo FROM productos 
                                WHERE id_empresa = ? AND eliminado = false AND (nombre ILIKE ? OR codigo ILIKE ?) 
                                ORDER BY nombre ASC LIMIT 10");
            $st->execute([$idEmpresa, "%$q%", "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tipo === 'categoria') {
            $st = $db->prepare("SELECT id, nombre AS text FROM categorias 
                                WHERE id_empresa = ? AND eliminado = false AND nombre ILIKE ? 
                                ORDER BY nombre ASC LIMIT 10");
            $st->execute([$idEmpresa, "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tipo === 'marca') {
            $st = $db->prepare("SELECT id, nombre AS text FROM marcas 
                                WHERE id_empresa = ? AND eliminado = false AND nombre ILIKE ? 
                                ORDER BY nombre ASC LIMIT 10");
            $st->execute([$idEmpresa, "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tipo === 'iva') {
            $st = $db->prepare("SELECT codigo::integer AS id, tarifa AS text FROM tarifa_iva
                                WHERE (tarifa ILIKE ? OR porcentaje_iva::text ILIKE ? OR codigo ILIKE ?)
                                ORDER BY tarifa ASC LIMIT 10");
            $st->execute(["%$q%", "%$q%", "%$q%"]);
            $results = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($results);
        exit;
    }

    /**
     * Guarda la preferencia del método de contabilización preferido de la empresa.
     */
    public function guardarMetodoPreferenciaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $tipoAsiento = trim($_POST['tipo_asiento'] ?? '');
        $metodo = trim($_POST['metodo'] ?? 'general');

        if ($tipoAsiento === '') {
            echo json_encode(['ok' => false, 'error' => 'Tipo de asiento no especificado.']);
            exit;
        }

        try {
            $this->service->guardarMetodoPreferencia($idEmpresa, $tipoAsiento, $metodo, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Método de contabilización preferido actualizado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Registra o actualiza una regla de dimensión contable.
     */
    public function guardarReglaDimensionAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idAsientoTipo = (int) ($_POST['id_asiento_tipo'] ?? 0);
        $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
        $idReferencia = (int) ($_POST['id_referencia'] ?? 0);
        $tipoReferencia = trim($_POST['tipo_referencia'] ?? '');

        if ($idAsientoTipo <= 0 || $idCuenta <= 0 || $idReferencia <= 0 || $tipoReferencia === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos. Debe seleccionar una entidad y una cuenta válida.']);
            exit;
        }

        try {
            // Comprobar si ya existe una regla para esa dimensión específica y asiento tipo
            $db = Database::getConnection();
            $stCheck = $db->prepare("SELECT id FROM asientos_programados 
                                     WHERE id_empresa = ? AND id_asiento_tipo = ? AND id_referencia = ? AND tipo_referencia = ? AND eliminado = false");
            $stCheck->execute([$idEmpresa, $idAsientoTipo, $idReferencia, $tipoReferencia]);
            $idExistente = $stCheck->fetchColumn();

            $dataRule = [
                'id_asiento_tipo' => $idAsientoTipo,
                'id_cuenta'       => $idCuenta,
                'id_referencia'   => $idReferencia,
                'tipo_referencia' => $tipoReferencia
            ];

            if ($idExistente) {
                // Actualizar usando el servicio
                $this->service->actualizar((int)$idExistente, $dataRule, $idEmpresa, $idUsuario);
            } else {
                // Insertar usando el servicio
                $this->service->registrar($dataRule, $idEmpresa, $idUsuario);
            }

            echo json_encode(['ok' => true, 'msg' => 'Regla de dimensión guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Elimina una regla de dimensión contable.
     */
    public function eliminarReglaDimensionAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idRule = (int) ($_POST['id'] ?? 0);

        if ($idRule <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de regla no válido.']);
            exit;
        }

        try {
            $db = Database::getConnection();
            $st = $db->prepare("UPDATE asientos_programados 
                                SET eliminado = true, deleted_at = NOW(), deleted_by = ? 
                                WHERE id = ? AND id_empresa = ?");
            $st->execute([$idUsuario, $idRule, $idEmpresa]);
            echo json_encode(['ok' => true, 'msg' => 'Asociación eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Carga las reglas asociadas a las dimensiones de un tipo de asiento contable.
     */
    public function cargarReglasDimensionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipoAsiento = trim($_GET['tipo_asiento'] ?? '');
        $tipoReferencia = trim($_GET['tipo_referencia'] ?? '');

        if ($tipoAsiento === '' || $tipoReferencia === '') {
            echo json_encode(['ok' => false, 'error' => 'Parámetros incompletos.']);
            exit;
        }

        try {
            $db = Database::getConnection();
            
            $joinTable = '';
            $joinField = '';
            
            if ($tipoReferencia === 'cliente') {
                $joinTable = 'clientes';
                $joinField = 'nombre';
            } elseif ($tipoReferencia === 'producto') {
                $joinTable = 'productos';
                $joinField = 'nombre';
            } elseif ($tipoReferencia === 'categoria') {
                $joinTable = 'categorias';
                $joinField = 'nombre';
            } elseif ($tipoReferencia === 'marca') {
                $joinTable = 'marcas';
                $joinField = 'nombre';
            } elseif ($tipoReferencia === 'iva') {
                $joinTable = 'tarifa_iva';
                $joinField = 'tarifa';
            }

            if ($joinTable === '') {
                echo json_encode(['ok' => false, 'error' => 'Dimensión no soportada.']);
                exit;
            }

            $sql = "SELECT ap.id, 
                           ap.id_asiento_tipo, 
                           ap.id_cuenta, 
                           ap.id_referencia, 
                           ap.tipo_referencia,
                           at.referencia AS asiento_tipo_referencia,
                           pc.codigo AS cuenta_codigo, 
                           pc.nombre AS cuenta_nombre,
                           ref.{$joinField} AS dimension_nombre
                    FROM asientos_programados ap
                    INNER JOIN plan_cuentas pc ON pc.id = ap.id_cuenta
                    INNER JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
                    INNER JOIN {$joinTable} ref ON ref.id = ap.id_referencia
                    WHERE ap.id_empresa = ? 
                      AND at.tipo_asiento = ? 
                      AND ap.tipo_referencia = ? 
                      AND ap.eliminado = false
                    ORDER BY dimension_nombre ASC";

            $st = $db->prepare($sql);
            $st->execute([$idEmpresa, $tipoAsiento, $tipoReferencia]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getMetodoPreferenciaAjax(): void
    {
        $this->requireVer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipoAsiento = trim($_GET['tipo_asiento'] ?? '');

        if ($tipoAsiento === '') {
            echo json_encode(['ok' => false, 'error' => 'Tipo de asiento incompleto.']);
            exit;
        }

        try {
            $metodo = $this->service->getMetodoPreferencia($idEmpresa, $tipoAsiento);
            echo json_encode(['ok' => true, 'metodo' => $metodo]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

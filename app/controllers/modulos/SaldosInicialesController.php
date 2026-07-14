<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\SaldosInicialesRepository;
use App\Services\modulos\SaldosInicialesService;
use App\Rules\modulos\SaldosInicialesRules;
use App\Services\LogSistemaService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SaldosInicialesController extends BaseModuloController
{
    private SaldosInicialesService $service;
    private SaldosInicialesRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/saldos_iniciales';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new SaldosInicialesRepository();
        $this->service = new SaldosInicialesService(
            $this->repo,
            new SaldosInicialesRules(),
            new LogSistemaService()
        );
    }

    private function jsonOk(array $data): never
    {
        $this->json(array_merge(['ok' => true], $data));
    }

    private function jsonErr(string $msg): never
    {
        $this->json(['ok' => false, 'error' => $msg]);
    }

    // ─────────────────────────────────────────────────────────
    // VISTA PRINCIPAL
    // ─────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $this->viewWithLayout('layouts.main', 'modulos/saldos_iniciales/index', [
            'titulo'     => 'Saldos Iniciales',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // CXC — LISTADO
    // ─────────────────────────────────────────────────────────

    public function getCxcAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $filtros = [
            'estado'      => $_GET['estado']      ?? 'TODOS',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];
        $filas = $this->service->getCxcListado($idEmpresa, $filtros);
        foreach ($filas as &$f) {
            $f['saldo_inicial']   = number_format((float)$f['saldo_inicial'],   2, '.', '');
            $f['monto_cobrado']   = number_format((float)$f['monto_cobrado'],   2, '.', '');
            $f['monto_retenido']  = number_format((float)($f['monto_retenido'] ?? 0), 2, '.', '');
            $f['saldo_pendiente'] = number_format((float)$f['saldo_pendiente'], 2, '.', '');
        }
        unset($f);
        $this->jsonOk(['filas' => $filas]);
    }

    // ─────────────────────────────────────────────────────────
    // CXC — GUARDAR (crear / editar)
    // ─────────────────────────────────────────────────────────

    public function guardarCxcAjax(): void
    {
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        $data = array_merge($_POST, [
            'id_empresa' => $idEmpresa,
            'created_by' => $idUsuario,
            'updated_by' => $idUsuario,
        ]);

        try {
            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizarCxc($id, $idEmpresa, $data);
                $this->jsonOk(['mensaje' => 'Registro actualizado correctamente.', 'id' => $id]);
            } else {
                $this->requireCrear();
                $newId = $this->service->crearCxc($data);
                $this->jsonOk(['mensaje' => 'Registro guardado correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXC — ELIMINAR
    // ─────────────────────────────────────────────────────────

    public function eliminarCxcAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonErr('ID inválido.');
        }
        try {
            $this->service->eliminarCxc($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXC — IMPORTAR EXCEL
    // ─────────────────────────────────────────────────────────

    public function importarCxcAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        if (empty($_FILES['archivo']['tmp_name'])) {
            $this->jsonErr('No se recibió ningún archivo.');
        }

        try {
            $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = [];
            $encabezado = null;

            foreach ($hoja->getRowIterator() as $row) {
                $celdas = [];
                foreach ($row->getCellIterator() as $celda) {
                    $celdas[] = trim((string)$celda->getValue());
                }
                if ($encabezado === null) {
                    $encabezado = array_map('strtoupper', $celdas);
                    continue;
                }
                if (implode('', $celdas) === '') continue;

                $fila = array_combine($encabezado, array_pad($celdas, count($encabezado), ''));
                $filas[] = [
                    'nro_documento'    => $fila['NRO_DOCUMENTO']     ?? $fila['NUMERO_DOCUMENTO'] ?? '',
                    'fecha_emision'    => $this->parseDate($fila['FECHA_EMISION']     ?? ''),
                    'fecha_vencimiento'=> $this->parseDate($fila['FECHA_VENCIMIENTO'] ?? ''),
                    'identificacion'   => $fila['IDENTIFICACION']    ?? $fila['RUC'] ?? $fila['RUC_CLIENTE'] ?? '',
                    'saldo_inicial'    => str_replace(',', '.', $fila['SALDO_PENDIENTE'] ?? $fila['SALDO'] ?? '0'),
                    'observaciones'    => $fila['OBSERVACIONES']     ?? '',
                ];
            }

            $nombreArchivo = $_FILES['archivo']['name'];
            $resultado = $this->service->importarCxcDesdeArray($idEmpresa, $idUsuario, $filas, $nombreArchivo);

            $this->jsonOk([
                'insertados' => $resultado['insertados'],
                'errores'    => $resultado['errores'],
                'mensaje'    => "Se importaron {$resultado['insertados']} registros."
                    . (count($resultado['errores']) > 0 ? ' Con ' . count($resultado['errores']) . ' errores.' : ''),
            ]);
        } catch (\Throwable $e) {
            $this->jsonErr('Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXC — DESCARGAR TEMPLATE
    // ─────────────────────────────────────────────────────────

    public function descargarTemplateCxc(): void
    {
        $this->requireLeer();
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Saldos Iniciales CXC');

        $encabezados = [
            'NRO_DOCUMENTO', 'FECHA_EMISION', 'FECHA_VENCIMIENTO',
            'IDENTIFICACION', 'SALDO_PENDIENTE', 'OBSERVACIONES'
        ];
        $ejemplos = [
            '001-001-000000001', '2024-01-01', '2024-01-31',
            '1234567890001', '1500.00', 'Saldo migrado'
        ];

        foreach ($encabezados as $col => $titulo) {
            $letra = chr(65 + $col);
            $hoja->setCellValue("{$letra}1", $titulo);
            $hoja->getStyle("{$letra}1")->getFont()->setBold(true);
            $hoja->getStyle("{$letra}1")->getFill()
                 ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                 ->getStartColor()->setRGB('198754');
            $hoja->getStyle("{$letra}1")->getFont()->getColor()->setRGB('FFFFFF');
            $hoja->getColumnDimension($letra)->setWidth(22);
            $hoja->setCellValue("{$letra}2", $ejemplos[$col]);
        }

        $hoja->getStyle('A1:F1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Nota: el cliente debe existir previamente (match por IDENTIFICACION)
        $hoja->setCellValue('A4', 'IMPORTANTE: el cliente debe estar registrado previamente. El cruce se hace por IDENTIFICACION (RUC, cédula, pasaporte o identificación del exterior). Las filas cuya identificación no exista serán rechazadas.');
        $hoja->getStyle('A4')->getFont()->setItalic(true)->setSize(9);
        $hoja->getStyle('A4')->getFont()->getColor()->setRGB('6c757d');
        $hoja->mergeCells('A4:F4');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_saldos_iniciales_cxc.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // CXP — LISTADO
    // ─────────────────────────────────────────────────────────

    public function getCxpAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $filtros = [
            'estado'         => $_GET['estado']         ?? 'TODOS',
            'tipo_documento' => $_GET['tipo_documento'] ?? '',
            'fecha_desde'    => $_GET['fecha_desde']    ?? '',
            'fecha_hasta'    => $_GET['fecha_hasta']    ?? '',
        ];
        $filas = $this->service->getCxpListado($idEmpresa, $filtros);
        foreach ($filas as &$f) {
            $f['saldo_inicial']   = number_format((float)$f['saldo_inicial'],   2, '.', '');
            $f['monto_pagado']    = number_format((float)$f['monto_pagado'],    2, '.', '');
            $f['saldo_pendiente'] = number_format((float)$f['saldo_pendiente'], 2, '.', '');
        }
        unset($f);
        $this->jsonOk(['filas' => $filas]);
    }

    // ─────────────────────────────────────────────────────────
    // CXP — GUARDAR
    // ─────────────────────────────────────────────────────────

    public function guardarCxpAjax(): void
    {
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        $data = array_merge($_POST, [
            'id_empresa' => $idEmpresa,
            'created_by' => $idUsuario,
            'updated_by' => $idUsuario,
        ]);

        try {
            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizarCxp($id, $idEmpresa, $data);
                $this->jsonOk(['mensaje' => 'Registro actualizado correctamente.', 'id' => $id]);
            } else {
                $this->requireCrear();
                $newId = $this->service->crearCxp($data);
                $this->jsonOk(['mensaje' => 'Registro guardado correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXP — ELIMINAR
    // ─────────────────────────────────────────────────────────

    public function eliminarCxpAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonErr('ID inválido.');
        }
        try {
            $this->service->eliminarCxp($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXP — IMPORTAR EXCEL
    // ─────────────────────────────────────────────────────────

    public function importarCxpAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        if (empty($_FILES['archivo']['tmp_name'])) {
            $this->jsonErr('No se recibió ningún archivo.');
        }

        try {
            $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
            $hoja = $spreadsheet->getActiveSheet();
            $filas = [];
            $encabezado = null;

            foreach ($hoja->getRowIterator() as $row) {
                $celdas = [];
                foreach ($row->getCellIterator() as $celda) {
                    $celdas[] = trim((string)$celda->getValue());
                }
                if ($encabezado === null) {
                    $encabezado = array_map('strtoupper', $celdas);
                    continue;
                }
                if (implode('', $celdas) === '') continue;

                $fila = array_combine($encabezado, array_pad($celdas, count($encabezado), ''));

                $tipoBruto = strtoupper(trim($fila['TIPO_DOCUMENTO'] ?? 'FACTURA_COMPRA'));
                $tipoMap = [
                    'FACTURA'         => 'FACTURA_COMPRA',
                    'FACTURA_COMPRA'  => 'FACTURA_COMPRA',
                    'LIQUIDACION'     => 'LIQUIDACION',
                    'NOTA_CREDITO'    => 'NOTA_CREDITO',
                    'NC'              => 'NOTA_CREDITO',
                    'NOTA_DEBITO'     => 'NOTA_DEBITO',
                    'ND'              => 'NOTA_DEBITO',
                ];
                $tipo = $tipoMap[$tipoBruto] ?? 'FACTURA_COMPRA';

                $filas[] = [
                    'tipo_documento'   => $tipo,
                    'nro_documento'    => $fila['NRO_DOCUMENTO']     ?? $fila['NUMERO_DOCUMENTO'] ?? '',
                    'fecha_emision'    => $this->parseDate($fila['FECHA_EMISION']     ?? ''),
                    'fecha_vencimiento'=> $this->parseDate($fila['FECHA_VENCIMIENTO'] ?? ''),
                    'identificacion'   => $fila['IDENTIFICACION']    ?? $fila['RUC'] ?? $fila['RUC_PROVEEDOR'] ?? '',
                    'saldo_inicial'    => str_replace(',', '.', $fila['SALDO_PENDIENTE'] ?? $fila['SALDO'] ?? '0'),
                    'observaciones'    => $fila['OBSERVACIONES']     ?? '',
                ];
            }

            $nombreArchivo = $_FILES['archivo']['name'];
            $resultado = $this->service->importarCxpDesdeArray($idEmpresa, $idUsuario, $filas, $nombreArchivo);

            $this->jsonOk([
                'insertados' => $resultado['insertados'],
                'errores'    => $resultado['errores'],
                'mensaje'    => "Se importaron {$resultado['insertados']} registros."
                    . (count($resultado['errores']) > 0 ? ' Con ' . count($resultado['errores']) . ' errores.' : ''),
            ]);
        } catch (\Throwable $e) {
            $this->jsonErr('Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // CXP — DESCARGAR TEMPLATE
    // ─────────────────────────────────────────────────────────

    public function descargarTemplateCxp(): void
    {
        $this->requireLeer();
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Saldos Iniciales CXP');

        $encabezados = [
            'TIPO_DOCUMENTO', 'NRO_DOCUMENTO', 'FECHA_EMISION', 'FECHA_VENCIMIENTO',
            'IDENTIFICACION', 'SALDO_PENDIENTE', 'OBSERVACIONES'
        ];
        $ejemplos = [
            'FACTURA_COMPRA', '001-001-000000001', '2024-01-01', '2024-01-31',
            '1234567890001', '2000.00', 'Saldo migrado'
        ];

        foreach ($encabezados as $col => $titulo) {
            $letra = chr(65 + $col);
            $hoja->setCellValue("{$letra}1", $titulo);
            $hoja->getStyle("{$letra}1")->getFont()->setBold(true);
            $hoja->getStyle("{$letra}1")->getFill()
                 ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                 ->getStartColor()->setRGB('0d6efd');
            $hoja->getStyle("{$letra}1")->getFont()->getColor()->setRGB('FFFFFF');
            $hoja->getColumnDimension($letra)->setWidth(22);
            $hoja->setCellValue("{$letra}2", $ejemplos[$col]);
        }

        // Nota informativa sobre tipos válidos
        $hoja->setCellValue('A3', 'Tipos válidos: FACTURA_COMPRA, LIQUIDACION, NOTA_CREDITO, NOTA_DEBITO');
        $hoja->getStyle('A3')->getFont()->setItalic(true)->setSize(9);
        $hoja->getStyle('A3')->getFont()->getColor()->setRGB('6c757d');
        $hoja->mergeCells('A3:G3');

        // Nota: el proveedor debe existir previamente (match por IDENTIFICACION)
        $hoja->setCellValue('A4', 'IMPORTANTE: el proveedor debe estar registrado previamente. El cruce se hace por IDENTIFICACION (RUC, cédula o pasaporte). Las filas cuya identificación no exista serán rechazadas.');
        $hoja->getStyle('A4')->getFont()->setItalic(true)->setSize(9);
        $hoja->getStyle('A4')->getFont()->getColor()->setRGB('6c757d');
        $hoja->mergeCells('A4:G4');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_saldos_iniciales_cxp.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // BANCOS — LISTADO Y GUARDAR
    // ─────────────────────────────────────────────────────────

    public function getBancosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $cuentas = $this->service->getBancosDisponibles($idEmpresa);
        $this->jsonOk(['cuentas' => $cuentas]);
    }

    public function getEfectivoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $cuentas = $this->service->getEfectivoDisponibles($idEmpresa);
        $this->jsonOk(['cuentas' => $cuentas]);
    }

    /** Catálogo de formas de pago de tipo ANTICIPO (para el modal). */
    public function getFormasAnticipoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $this->jsonOk(['formas' => $this->service->getAnticiposDisponibles($idEmpresa)]);
    }

    /** Listado de saldos iniciales de anticipos (atados a cliente/proveedor). */
    public function getAnticiposAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $filas = $this->service->getSaldosAnticipo($idEmpresa);
        foreach ($filas as &$f) {
            $f['saldo_inicial'] = number_format((float)$f['saldo_inicial'], 2, '.', '');
        }
        unset($f);
        $this->jsonOk(['filas' => $filas]);
    }

    public function guardarAnticipoAjax(): void
    {
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        $data = array_merge($_POST, [
            'id_empresa' => $idEmpresa,
            'created_by' => $idUsuario,
            'updated_by' => $idUsuario,
        ]);

        try {
            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizarAnticipo($id, $idEmpresa, $data);
                $this->jsonOk(['mensaje' => 'Registro actualizado correctamente.', 'id' => $id]);
            } else {
                $this->requireCrear();
                $newId = $this->service->crearAnticipo($data);
                $this->jsonOk(['mensaje' => 'Registro guardado correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function eliminarAnticipoAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        try {
            $this->service->eliminarAnticipo($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function guardarBancosAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        $cuentasJson = $_POST['cuentas'] ?? '[]';
        $cuentas = is_array($cuentasJson) ? $cuentasJson : json_decode($cuentasJson, true);

        if (!is_array($cuentas) || empty($cuentas)) {
            $this->jsonErr('No se recibieron cuentas para guardar.');
        }

        try {
            $this->service->guardarBancos($idEmpresa, $idUsuario, $cuentas);
            $this->jsonOk(['mensaje' => 'Saldos de bancos guardados correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function eliminarBancoAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        try {
            $this->service->eliminarBanco($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Saldo eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // COBROS Y PAGOS (desde CXC y CXP)
    // ─────────────────────────────────────────────────────────

    public function registrarCobroAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $idSaldo   = (int)($_POST['id_saldo'] ?? 0);

        if ($idSaldo <= 0) $this->jsonErr('ID de saldo inválido.');

        $idPunto = (int)($_POST['id_punto_emision'] ?? 0);
        $monto   = (float)($_POST['monto'] ?? 0);
        $idForma = (int)($_POST['id_forma_cobro'] ?? 0);

        if ($idPunto <= 0 || $monto <= 0 || $idForma <= 0) {
            $this->jsonErr('Datos incompletos. Verifique serie, monto y forma de cobro.');
        }

        $punto = $this->repo->getDb()->prepare(
            "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id = :id AND p.id_empresa = :ie AND p.eliminado = false"
        );
        $punto->execute([':id' => $idPunto, ':ie' => $idEmpresa]);
        $puntoData = $punto->fetch(\PDO::FETCH_ASSOC);

        if (!$puntoData) {
            $this->jsonErr('Punto de emisión no válido.');
        }

        try {
            $result = $this->service->registrarCobroCxc($idSaldo, $idEmpresa, $idUsuario, [
                'id_punto_emision'       => $idPunto,
                'punto'                  => $puntoData,
                'monto'                  => $monto,
                'id_forma_cobro'         => $idForma,
                'id_ingreso_concepto'    => $_POST['id_ingreso_concepto'] ?? null,
                'fecha_cobro'            => $_POST['fecha_cobro'] ?? date('Y-m-d'),
                'observaciones'          => $_POST['observaciones'] ?? '',
                'tipo_operacion_bancaria'=> $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_operacion'       => $_POST['numero_operacion'] ?? '',
                // Fecha en que se podrá cobrar el cheque (control de posfechados)
                'fecha_cheque'           => $_POST['fecha_cheque'] ?? '',
            ]);
            $this->jsonOk(array_merge($result, ['mensaje' => "Cobro registrado. Ingreso: {$result['numero_ingreso']}"]));
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function registrarPagoAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $idSaldo   = (int)($_POST['id_saldo'] ?? 0);

        if ($idSaldo <= 0) $this->jsonErr('ID de saldo inválido.');

        $idPunto = (int)($_POST['id_punto_emision'] ?? 0);
        $monto   = (float)($_POST['monto'] ?? 0);
        $idForma = (int)($_POST['id_forma_pago'] ?? 0);

        if ($idPunto <= 0 || $monto <= 0 || $idForma <= 0) {
            $this->jsonErr('Datos incompletos. Verifique serie, monto y forma de pago.');
        }

        $puntoSt = $this->repo->getDb()->prepare(
            "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id = :id AND p.id_empresa = :ie AND p.eliminado = false"
        );
        $puntoSt->execute([':id' => $idPunto, ':ie' => $idEmpresa]);
        $puntoData = $puntoSt->fetch(\PDO::FETCH_ASSOC);

        if (!$puntoData) {
            $this->jsonErr('Punto de emisión no válido.');
        }

        try {
            $result = $this->service->registrarPagoCxp($idSaldo, $idEmpresa, $idUsuario, [
                'id_punto_emision'       => $idPunto,
                'punto'                  => $puntoData,
                'monto'                  => $monto,
                'id_forma_pago'          => $idForma,
                'id_egreso_concepto'     => $_POST['id_egreso_concepto'] ?? null,
                'fecha_pago'             => $_POST['fecha_pago'] ?? date('Y-m-d'),
                'observaciones'          => $_POST['observaciones'] ?? '',
                'tipo_operacion_bancaria'=> $_POST['tipo_operacion_bancaria'] ?? '',
                'numero_operacion'       => $_POST['numero_operacion'] ?? '',
                // Fecha en que se podrá cobrar el cheque (control de posfechados)
                'fecha_cheque'           => $_POST['fecha_cheque'] ?? '',
            ]);
            $this->jsonOk(array_merge($result, ['mensaje' => "Pago registrado. Egreso: {$result['numero_egreso']}"]));
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function historialCobrosCxcAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        $historial = $this->repo->getHistorialCobrosCxc($id, $idEmpresa);
        $this->jsonOk(['historial' => $historial]);
    }

    public function historialPagosCxpAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        $historial = $this->repo->getHistorialPagosCxp($id, $idEmpresa);
        $this->jsonOk(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────
    // CATÁLOGOS (puntos, formas, conceptos) — reutiliza CXC/CXP
    // ─────────────────────────────────────────────────────────

    public function getCatalogosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $db = $this->repo->getDb();

        $puntosSt = $db->prepare(
            "SELECT p.id AS id_punto, e.codigo AS cod_establecimiento,
                    p.codigo_punto, p.id_establecimiento
             FROM empresa_punto_emision p
             JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
             WHERE p.id_empresa = :ie AND p.eliminado = false AND e.eliminado = false
             ORDER BY e.codigo, p.codigo_punto"
        );
        $puntosSt->execute([':ie' => $idEmpresa]);

        $formasSt = $db->prepare(
            "SELECT id, nombre, tipo FROM empresa_formas_pago
             WHERE id_empresa = :ie AND eliminado = false AND activo = true
             ORDER BY nombre"
        );
        $formasSt->execute([':ie' => $idEmpresa]);

        $conceptosIngSt = $db->prepare(
            "SELECT id, nombre FROM empresa_opciones_ingreso_egreso
             WHERE id_empresa = :ie AND aplica_ingresos = true
               AND UPPER(estado) = 'ACTIVO' AND eliminado = false ORDER BY nombre"
        );
        $conceptosIngSt->execute([':ie' => $idEmpresa]);

        $conceptosEgrSt = $db->prepare(
            "SELECT id, nombre FROM empresa_opciones_ingreso_egreso
             WHERE id_empresa = :ie AND aplica_egresos = true
               AND UPPER(estado) = 'ACTIVO' AND eliminado = false ORDER BY nombre"
        );
        $conceptosEgrSt->execute([':ie' => $idEmpresa]);

        $this->jsonOk([
            'puntos'          => $puntosSt->fetchAll(\PDO::FETCH_ASSOC),
            'formas'          => $formasSt->fetchAll(\PDO::FETCH_ASSOC),
            'conceptos_ing'   => $conceptosIngSt->fetchAll(\PDO::FETCH_ASSOC),
            'conceptos_egr'   => $conceptosEgrSt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // BÚSQUEDA DE CLIENTES / PROVEEDORES (para vincular el saldo)
    // ─────────────────────────────────────────────────────────

    public function buscarClienteAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $this->jsonOk(['data' => $this->service->buscarClientes($idEmpresa, $q)]);
    }

    public function buscarProveedorAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $this->jsonOk(['data' => $this->service->buscarProveedores($idEmpresa, $q)]);
    }

    // ─────────────────────────────────────────────────────────
    // CATÁLOGOS COMPARTIDOS (productos, bodegas, vendedores)
    // ─────────────────────────────────────────────────────────

    public function buscarProductoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $repo = new \App\repositories\modulos\ProductoRepository();
        // tipo '01' = bienes/productos
        $rows = $repo->searchSimple($idEmpresa, $q, 15, '01');
        $this->jsonOk(['data' => $rows]);
    }

    public function getBodegasAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $nivel     = (int)($_SESSION['nivel'] ?? 1);
        $repo = new \App\repositories\modulos\BodegaRepository();
        $this->jsonOk(['data' => $repo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel)]);
    }

    public function getVendedoresAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $repo = new \App\repositories\modulos\VendedorRepository();
        $this->jsonOk(['data' => $repo->getVendedoresActivos($idEmpresa)]);
    }

    // ─────────────────────────────────────────────────────────
    // INVENTARIO — saldos de apertura
    // ─────────────────────────────────────────────────────────

    public function getInventarioAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $filas = $this->service->getSaldosInventario($idEmpresa);
        $this->jsonOk(['filas' => $filas]);
    }

    public function guardarInventarioAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        try {
            $id = $this->service->guardarSaldoInventario($idEmpresa, $idUsuario, $_POST);
            $this->jsonOk(['mensaje' => 'Saldo de inventario registrado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function eliminarInventarioAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        try {
            $this->service->eliminarSaldoInventario($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Saldo de inventario eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function importarInventarioAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        if (empty($_FILES['archivo']['tmp_name'])) {
            $this->jsonErr('No se recibió ningún archivo.');
        }
        try {
            $filas = $this->leerHoja($_FILES['archivo']['tmp_name'], function (array $fila) {
                return [
                    'codigo'          => $fila['CODIGO']         ?? $fila['CODIGO_PRODUCTO'] ?? '',
                    'bodega'          => $fila['BODEGA']         ?? '',
                    'cantidad'        => str_replace(',', '.', $fila['CANTIDAD']       ?? '0'),
                    'costo_unitario'  => str_replace(',', '.', $fila['COSTO_UNITARIO'] ?? $fila['COSTO'] ?? '0'),
                    'lote'            => $fila['LOTE']           ?? '',
                    'fecha_caducidad' => $this->parseDate($fila['CADUCIDAD'] ?? $fila['FECHA_CADUCIDAD'] ?? ''),
                    'nup'             => $fila['NUP']            ?? '',
                    'observaciones'   => $fila['OBSERVACIONES']  ?? '',
                ];
            });
            $resultado = $this->service->importarInventarioDesdeArray($idEmpresa, $idUsuario, $filas);
            $this->jsonOk([
                'insertados' => $resultado['insertados'],
                'errores'    => $resultado['errores'],
                'mensaje'    => "Se importaron {$resultado['insertados']} registros."
                    . (count($resultado['errores']) > 0 ? ' Con ' . count($resultado['errores']) . ' errores.' : ''),
            ]);
        } catch (\Throwable $e) {
            $this->jsonErr('Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    public function descargarTemplateInventario(): void
    {
        $this->requireLeer();
        $encabezados = ['CODIGO', 'BODEGA', 'CANTIDAD', 'COSTO_UNITARIO', 'LOTE', 'CADUCIDAD', 'NUP', 'OBSERVACIONES'];
        $ejemplos    = ['P001', 'Bodega Principal', '100', '5.50', '', '', '', 'Stock inicial'];
        $nota = 'IMPORTANTE: el producto debe estar registrado (se busca por CODIGO) y la BODEGA debe existir (por nombre exacto). Cada fila genera una entrada de apertura en el kardex.';
        $this->generarTemplate('Saldos Inventario', 'template_saldos_inventario.xlsx', $encabezados, $ejemplos, '0dcaf0', $nota);
    }

    // ─────────────────────────────────────────────────────────
    // CONSIGNACIONES — registro de saldo pendiente
    // ─────────────────────────────────────────────────────────

    public function getConsignacionesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $filas = $this->service->getSaldosConsignacion($idEmpresa);
        foreach ($filas as &$f) {
            $f['cantidad']        = number_format((float)$f['cantidad'], 2, '.', '');
            $f['precio_unitario'] = number_format((float)$f['precio_unitario'], 2, '.', '');
            $f['total']           = number_format((float)$f['total'], 2, '.', '');
        }
        unset($f);
        $this->jsonOk(['filas' => $filas]);
    }

    public function guardarConsignacionAjax(): void
    {
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);

        $data = array_merge($_POST, [
            'id_empresa' => $idEmpresa,
            'created_by' => $idUsuario,
            'updated_by' => $idUsuario,
        ]);

        try {
            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizarConsignacion($id, $idEmpresa, $data);
                $this->jsonOk(['mensaje' => 'Registro actualizado correctamente.', 'id' => $id]);
            } else {
                $this->requireCrear();
                $newId = $this->service->crearConsignacion($data);
                $this->jsonOk(['mensaje' => 'Registro guardado correctamente.', 'id' => $newId]);
            }
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function eliminarConsignacionAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErr('ID inválido.');
        try {
            $this->service->eliminarConsignacion($id, $idEmpresa, $idUsuario);
            $this->jsonOk(['mensaje' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            $this->jsonErr($e->getMessage());
        }
    }

    public function importarConsignacionAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        if (empty($_FILES['archivo']['tmp_name'])) {
            $this->jsonErr('No se recibió ningún archivo.');
        }
        try {
            $filas = $this->leerHoja($_FILES['archivo']['tmp_name'], function (array $fila) {
                return [
                    'fecha_emision'   => $this->parseDate($fila['FECHA'] ?? $fila['FECHA_EMISION'] ?? ''),
                    'nro_documento'   => $fila['NRO_DOCUMENTO']  ?? $fila['NUMERO_DOCUMENTO'] ?? '',
                    'identificacion'  => $fila['IDENTIFICACION'] ?? $fila['RUC'] ?? '',
                    'codigo'          => $fila['CODIGO_PRODUCTO'] ?? $fila['CODIGO'] ?? '',
                    'cantidad'        => str_replace(',', '.', $fila['CANTIDAD'] ?? '0'),
                    'precio_unitario' => str_replace(',', '.', $fila['PRECIO'] ?? $fila['PRECIO_UNITARIO'] ?? '0'),
                    'vendedor'        => $fila['VENDEDOR'] ?? '',
                    'bodega'          => $fila['BODEGA']   ?? '',
                    'lote'            => $fila['LOTE']     ?? '',
                    'fecha_caducidad' => $this->parseDate($fila['CADUCIDAD'] ?? $fila['FECHA_CADUCIDAD'] ?? ''),
                    'nup'             => $fila['NUP']      ?? '',
                    'observaciones'   => $fila['OBSERVACIONES'] ?? '',
                ];
            });
            $resultado = $this->service->importarConsignacionDesdeArray($idEmpresa, $idUsuario, $filas);
            $this->jsonOk([
                'insertados' => $resultado['insertados'],
                'errores'    => $resultado['errores'],
                'mensaje'    => "Se importaron {$resultado['insertados']} registros."
                    . (count($resultado['errores']) > 0 ? ' Con ' . count($resultado['errores']) . ' errores.' : ''),
            ]);
        } catch (\Throwable $e) {
            $this->jsonErr('Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    public function descargarTemplateConsignacion(): void
    {
        $this->requireLeer();
        $encabezados = ['FECHA', 'NRO_DOCUMENTO', 'IDENTIFICACION', 'CODIGO_PRODUCTO', 'CANTIDAD', 'PRECIO', 'VENDEDOR', 'BODEGA', 'LOTE', 'CADUCIDAD', 'NUP', 'OBSERVACIONES'];
        $ejemplos    = ['2024-01-01', '001-001-000000001', '1234567890001', 'P001', '10', '12.50', '', '', '', '', '', 'Consignado'];
        $nota = 'IMPORTANTE: el cliente debe estar registrado (se busca por IDENTIFICACION) y el producto por CODIGO_PRODUCTO. VENDEDOR y BODEGA son opcionales (por nombre exacto). No afecta inventario.';
        $this->generarTemplate('Saldos Consignaciones', 'template_saldos_consignaciones.xlsx', $encabezados, $ejemplos, '6c757d', $nota);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────

    private function parseDate(string $valor): string
    {
        if (empty($valor)) return '';
        // Intenta varios formatos
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $valor);
            if ($d) return $d->format('Y-m-d');
        }
        return '';
    }

    /**
     * Lee una hoja Excel y devuelve las filas mapeadas con el callback dado.
     * El callback recibe la fila como array asociativo [ENCABEZADO => valor].
     */
    private function leerHoja(string $tmpPath, callable $mapper): array
    {
        $spreadsheet = IOFactory::load($tmpPath);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = [];
        $encabezado = null;
        foreach ($hoja->getRowIterator() as $row) {
            $celdas = [];
            foreach ($row->getCellIterator() as $celda) {
                $celdas[] = trim((string)$celda->getValue());
            }
            if ($encabezado === null) {
                $encabezado = array_map('strtoupper', $celdas);
                continue;
            }
            if (implode('', $celdas) === '') continue;
            $celdas = array_pad($celdas, count($encabezado), '');
            $celdas = array_slice($celdas, 0, count($encabezado));
            $fila = array_combine($encabezado, $celdas);
            $filas[] = $mapper($fila);
        }
        return $filas;
    }

    /**
     * Genera y descarga una plantilla Excel con encabezados, ejemplos y nota.
     */
    private function generarTemplate(string $titulo, string $filename, array $encabezados, array $ejemplos, string $colorHdr, string $nota = ''): never
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle($titulo);

        foreach ($encabezados as $col => $t) {
            $letra = chr(65 + $col);
            $hoja->setCellValue("{$letra}1", $t);
            $hoja->getStyle("{$letra}1")->getFont()->setBold(true);
            $hoja->getStyle("{$letra}1")->getFill()
                 ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                 ->getStartColor()->setRGB($colorHdr);
            $hoja->getStyle("{$letra}1")->getFont()->getColor()->setRGB('FFFFFF');
            $hoja->getColumnDimension($letra)->setWidth(20);
            if (isset($ejemplos[$col]) && $ejemplos[$col] !== '') {
                $hoja->setCellValue("{$letra}2", $ejemplos[$col]);
            }
        }

        $ultima = chr(65 + count($encabezados) - 1);
        $hoja->getStyle("A1:{$ultima}1")->getAlignment()
             ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        if ($nota !== '') {
            $hoja->setCellValue('A4', $nota);
            $hoja->getStyle('A4')->getFont()->setItalic(true)->setSize(9);
            $hoja->getStyle('A4')->getFont()->getColor()->setRGB('6c757d');
            $hoja->mergeCells("A4:{$ultima}4");
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }
}

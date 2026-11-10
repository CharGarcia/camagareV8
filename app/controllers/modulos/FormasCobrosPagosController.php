<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FormaPagoRepository;
use App\Services\modulos\FormaPagoService;
use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;

class FormasCobrosPagosController extends BaseModuloController
{
    private FormaPagoService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/formas_cobros_pagos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new FormaPagoService(new FormaPagoRepository());
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        // URL limpia: búsqueda, página y orden llegan por sesión (estadoListadoAjax).
        $estado   = $this->leerEstadoListado();
        $buscar   = $estado['b'];
        $page     = $estado['page'];
        // leer() deja la columna como identificador y la dirección en ASC/DESC: la vista las
        // imprime dentro de su <script>.
        $orden    = OrdenListado::leer($prefsVista, 'nombre');
        $ordenCol = $estado['sort'] !== '' ? $estado['sort'] : OrdenListado::primeraCol($orden, 'nombre');
        $ordenDir = $estado['dir'] !== '' ? $estado['dir'] : OrdenListado::primeraDir($orden);
        $perPage = $this->porPagina();

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = (int) ceil($result['total'] / $perPage);

        // Cargar datos necesarios para el modal
        $bancos = $this->service->getBancos();

        $this->viewWithLayout('layouts.main', 'modulos/formas_cobros_pagos/index', [
            'titulo'      => 'Formas de Pago',
            'perm'        => $this->getPermisos(),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'bancos'      => $bancos,
            'fullWidth'   => true
        ]);
    }

    public function getFormaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $data = $this->service->getPorId($id, $idEmpresa);

        if ($data) {
            echo json_encode(['ok' => true, 'data' => $data]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Registro no encontrado.']);
        }
        exit;
    }

    /** Datos de las cuentas bancarias de la empresa, para enviarlos a un cliente. */
    public function getCuentasBancariasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $data = $this->service->getCuentasBancariasParaClientes((int) $_SESSION['id_empresa']);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron cargar las cuentas bancarias.']);
        }
        exit;
    }

    /**
     * PDF (A4 vertical) con los datos de las cuentas bancarias, para enviarlo a un cliente.
     * Formato común de reportes (App\Helpers\ReportePdf): logo y nombre de la empresa arriba.
     * `c` = posiciones de las cuentas elegidas en la ventana (vacío = todas).
     */
    public function cuentasBancariasPdf(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data      = $this->service->getCuentasBancariasParaClientes($idEmpresa);
            $cuentas   = $data['cuentas'];

            $elegidas = trim((string) ($_GET['c'] ?? ''));
            if ($elegidas !== '') {
                $idx     = array_flip(array_map('intval', explode(',', $elegidas)));
                $cuentas = array_values(array_intersect_key($cuentas, $idx));
            }
            if (!$cuentas) {
                throw new \RuntimeException('No hay cuentas bancarias para incluir en el PDF.');
            }

            $pt   = 10.0;
            $e    = static fn ($v): string => htmlspecialchars((string) $v);
            $tipo = static fn (string $t): string => match (strtoupper($t)) {
                'AHORROS'   => 'Ahorros',
                'CORRIENTE' => 'Corriente',
                'VIRTUAL'   => 'Virtual / Digital',
                default     => '-',
            };
            $txt  = static fn (string $v, float $w): string
                => \App\Helpers\ReportePdf::texto($v, \App\Helpers\ReportePdf::anchoPt($w, false), $pt);

            // El ancho va en el <th> y en cada <td>: si no, Html2Pdf ensancha la tabla.
            $filas = '';
            foreach ($cuentas as $i => $c) {
                $zebra  = ($i % 2 === 1) ? 'background:#f6f8fa;' : '';
                $filas .= '<tr>'
                    . "<td style='width:46%;padding:6px;{$zebra}'><b>" . $txt((string) $c['banco'], 46) . '</b></td>'
                    . "<td class='text-center' style='width:22%;padding:6px;{$zebra}'>" . $e($tipo((string) $c['tipo_cuenta'])) . '</td>'
                    . "<td class='text-center' style='width:32%;padding:6px;{$zebra}'><b>" . $txt((string) $c['numero'], 32) . '</b></td>'
                    . '</tr>';
            }
            $tabla = '<table><thead><tr>'
                . "<th style='width:46%;padding:5px;'>Banco</th>"
                . "<th style='width:22%;padding:5px;'>Tipo de cuenta</th>"
                . "<th style='width:32%;padding:5px;'>Número de cuenta</th>"
                . "</tr></thead><tbody>{$filas}</tbody></table>";

            $lbl = "width:22%;padding:5px;background:#f8f9fa;font-weight:bold;font-size:{$pt}pt;";
            $val = "width:78%;padding:5px;font-size:{$pt}pt;";
            $titular = '';
            foreach (['Titular' => $data['titular'], 'RUC' => $data['ruc'], 'Correo' => $data['correo']] as $rotulo => $valor) {
                if ($valor !== '') {
                    $titular .= "<tr><td style='{$lbl}'>{$rotulo}:</td><td style='{$val}'>" . $txt((string) $valor, 78) . '</td></tr>';
                }
            }
            $titular = $titular !== '' ? "<table style='margin-top:8px;'>{$titular}</table>" : '';

            $nota = "<p style='font-size:9pt;color:#555;margin-top:10px;'>"
                  . 'Por favor, envíenos el comprobante una vez realizado el depósito o la transferencia.</p>';

            $html = \App\Helpers\ReportePdf::pagina(
                $pt,
                \App\Helpers\ReportePdf::encabezado($idEmpresa, $data['titular'], 'Cuentas bancarias', 'Datos para depósito o transferencia')
                . $tabla . $titular . $nota
            );

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('Cuentas_Bancarias_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            if (!$e instanceof \RuntimeException) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'mensaje' => $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'No se pudo generar el PDF de cuentas bancarias.']);
            exit;
        }
    }

    public function searchCuentasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        $cuentas = $this->service->buscarCuentas($idEmpresa, $q);
        echo json_encode(['ok' => true, 'data' => $cuentas]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int)$_SESSION['id_empresa'];
            $data['usuario_id'] = (int)$_SESSION['id_usuario'];
            
            // Convertir checkbox activo a boolean real
            $data['activo'] = isset($_POST['activo']) && ($_POST['activo'] === '1' || $_POST['activo'] === 'on' || $_POST['activo'] === 'true');
            // "Mostrar saldo" en Ingresos/Egresos: el modal lo envía siempre ('1'/'0'); si no llega,
            // queda visible, que es el comportamiento de siempre.
            $data['mostrar_saldo'] = !isset($_POST['mostrar_saldo'])
                || in_array((string)$_POST['mostrar_saldo'], ['1', 'on', 'true'], true);

            $id = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $this->requireActualizar();
                $id = $this->service->guardar($data);
                $msg = 'Forma de pago actualizada satisfactoriamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->guardar($data);
                $msg = 'Forma de pago registrada satisfactoriamente.';
            }
            
            echo json_encode([
                'ok' => true, 
                'mensaje' => $msg, 
                'id' => $id
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)$_SESSION['id_empresa'];
            $usuarioId = (int)$_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $usuarioId);
            echo json_encode(['ok' => true, 'mensaje' => 'Registro eliminado exitosamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}

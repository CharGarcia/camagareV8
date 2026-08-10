<?php
/**
 * Controlador API v1: Compras (solo consulta + alta por número de autorización SRI).
 * Adaptador HTTP→JSON puro: reutiliza ComprasRepository/ComprasService tal cual los usa
 * la web, y para la carga por autorización reutiliza el mismo par de servicios que ya usa
 * DescargasSriController::procesarClavesAccesoAjax() (web) — SriService::obtenerComprobanteXml()
 * + DocumentoAutomatedRegisterService::procesarYRegistrar() — sin reimplementar el parseo del
 * XML ni la inserción de cabecera/detalle/proveedor/impuestos/pagos.
 *
 * Alcance "básico" a propósito: la app NO permite crear una compra a mano (formulario
 * completo), solo listar/ver y cargar por número de autorización (clave de acceso
 * electrónica, 49 dígitos) — la alta manual sigue siendo exclusiva de la web.
 * Mismo permiso 'modulos/compras' que la web (letra 'w' para la carga por autorización,
 * ya que crea un registro).
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\repositories\modulos\ComprasRepository;
use App\repositories\modulos\EgresoRepository;
use App\repositories\modulos\FormaPagoRepository;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ComprasService;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use App\Services\modulos\EgresoService;
use App\Services\SecuencialService;
use App\Services\SriService;
use PDO;
use Throwable;

class ComprasController extends ApiBaseController
{
    private ComprasRepository $repository;
    private ComprasService $service;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ComprasRepository();
        $this->service = new ComprasService();
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/compras';
    }

    /**
     * GET /api/v1/compras/listar?buscar=&page=&per_page=
     */
    public function listar(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, 'fecha_emision', 'DESC', $idUsuarioFiltro);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->jsonOk($result['rows'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * GET /api/v1/compras/obtener?id=123
     */
    public function obtener(): void
    {
        $this->requireLeer();

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id.', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $compra = $this->service->getPorId($id, $idEmpresa);
        if (!$compra) {
            $this->jsonError('NO_ENCONTRADO', 'Compra no encontrada.', 404);
        }

        $this->jsonOk($compra);
    }

    /**
     * POST /api/v1/compras/cargar-por-autorizacion
     * body: { numero_autorizacion }
     * numero_autorizacion = clave de acceso electrónica del SRI (49 dígitos). Consulta el
     * webservice oficial del SRI y, si está autorizado, registra la compra automáticamente
     * (mismo motor que usa Descargas SRI en la web).
     */
    public function cargarPorAutorizacion(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $body = $this->getJsonBody();
        $clave = preg_replace('/\D/', '', (string) ($body['numero_autorizacion'] ?? '')) ?? '';

        if (strlen($clave) !== 49) {
            $this->jsonError('CLAVE_INVALIDA', 'El número de autorización debe tener 49 dígitos (clave de acceso electrónica del SRI).', 422);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $sriService = new SriService();
        $respuesta = $sriService->obtenerComprobanteXml($clave);

        if (empty($respuesta['ok']) || empty($respuesta['xml'])) {
            $this->jsonError(
                'SRI_NO_DISPONIBLE',
                $respuesta['mensaje'] ?? 'No se pudo obtener el comprobante desde el SRI.',
                422,
                ['estado_sri' => $respuesta['estado'] ?? 'DESCONOCIDO']
            );
        }

        try {
            $registerService = new DocumentoAutomatedRegisterService();
            $resRegistro = $registerService->procesarYRegistrar((string) $respuesta['xml'], $idEmpresa, $idUsuario);
        } catch (Throwable $e) {
            $this->jsonError('ERROR_REGISTRO', $e->getMessage(), 422);
        }

        if (empty($resRegistro['ok'])) {
            $this->jsonError(
                'ERROR_REGISTRO',
                $resRegistro['mensaje'] ?? $resRegistro['error'] ?? 'No se pudo registrar la compra.',
                422,
                ['estado_registro' => $resRegistro['estado_registro'] ?? 'ERROR']
            );
        }

        $this->jsonOk([
            'id' => $resRegistro['id'] ?? null,
            'existe' => (bool) ($resRegistro['existe'] ?? false),
            'estado_registro' => $resRegistro['estado_registro'] ?? 'REGISTRADO',
            'mensaje' => $resRegistro['mensaje'] ?? 'Compra registrada correctamente.',
            'numero_documento' => $resRegistro['numero_documento'] ?? null,
            'tipo_nombre' => $resRegistro['tipo_nombre'] ?? null,
            'emisor' => $resRegistro['emisor'] ?? null,
            'total' => $resRegistro['total'] ?? null,
        ], [], 201);
    }

    /**
     * GET /api/v1/compras/dependencias-pago
     * Bootstrap del formulario de pago: formas de pago (flujo EGRESO), conceptos de
     * egreso y puntos de emisión activos (para reservar el secuencial del egreso).
     */
    public function dependenciasPago(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = \App\core\Database::getConnection();

        $formas = (new FormaPagoRepository())->getFormasFiltradas($idEmpresa, 'EGRESO');

        $stC = $db->prepare(
            "SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso
             WHERE id_empresa = ? AND aplica_egresos = TRUE AND eliminado = FALSE ORDER BY nombre ASC"
        );
        $stC->execute([$idEmpresa]);
        $conceptos = $stC->fetchAll(PDO::FETCH_ASSOC);

        $stPto = $db->prepare(
            "SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
             FROM empresa_punto_emision pe
             JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
             WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
               AND LOWER(pe.estado) = 'activo'
             ORDER BY es.codigo ASC, pe.codigo_punto ASC"
        );
        $stPto->execute([$idEmpresa]);
        $puntos = $stPto->fetchAll(PDO::FETCH_ASSOC);

        $this->jsonOk([
            'formas_pago' => $formas,
            'conceptos' => $conceptos,
            'puntos' => $puntos,
        ]);
    }

    /**
     * POST /api/v1/compras/registrar-pago
     * body: { id_compra, monto_pagar, id_punto_emision, id_forma_pago, id_egreso_concepto?,
     *         fecha_emision?, tipo_operacion_bancaria?, numero_operacion?, observaciones? }
     *
     * A diferencia del flujo web equivalente (App\controllers\modulos\ComprasController::
     * registrarEgresoAjax, que confía en un "saldo_actual" que manda el cliente), aquí el
     * saldo pendiente se recalcula SIEMPRE en el servidor, bajo candado
     * (ComprasRepository::lockPago + calcularSaldoPendiente) — cierra la ventana de
     * concurrencia "leer saldo → calcular → escribir" que exige el CLAUDE.md §8: dos pagos
     * simultáneos contra la misma compra ya no pueden pasar ambos la validación de
     * "no pagar de más".
     */
    public function registrarPago(): void
    {
        $this->requireCrear();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('METODO_NO_PERMITIDO', 'Use POST.', 405);
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $body = $this->getJsonBody();

        $idCompra = (int) ($body['id_compra'] ?? 0);
        $montoPagar = (float) ($body['monto_pagar'] ?? 0);
        $idPunto = (int) ($body['id_punto_emision'] ?? 0);
        $idFormaPago = (int) ($body['id_forma_pago'] ?? 0);

        if ($idCompra <= 0) {
            $this->jsonError('ID_REQUERIDO', 'Falta id_compra.', 422);
        }
        if ($montoPagar <= 0) {
            $this->jsonError('MONTO_INVALIDO', 'El monto a pagar debe ser mayor a cero.', 422);
        }
        if ($idPunto <= 0) {
            $this->jsonError('PUNTO_REQUERIDO', 'Debe indicar el punto de emisión.', 422);
        }
        if ($idFormaPago <= 0) {
            $this->jsonError('FORMA_PAGO_REQUERIDA', 'Debe indicar la forma de pago.', 422);
        }

        $comprasRepo = new ComprasRepository();
        $compra = $comprasRepo->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            $this->jsonError('NO_ENCONTRADO', 'Compra no encontrada.', 404);
        }

        $db = \App\core\Database::getConnection();
        $stPto = $db->prepare(
            "SELECT pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
             FROM empresa_punto_emision pe
             JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
             WHERE pe.id = ? AND es.id_empresa = ? AND pe.eliminado = FALSE"
        );
        $stPto->execute([$idPunto, $idEmpresa]);
        $pto = $stPto->fetch(PDO::FETCH_ASSOC);
        if (!$pto) {
            $this->jsonError('PUNTO_INVALIDO', 'El punto de emisión no existe o está inactivo.', 422);
        }

        $idEgreso = null;
        $numEgreso = '';
        $saldoAnterior = 0.0;

        try {
            $db->beginTransaction();

            // Candado + recálculo del saldo real ANTES de reservar el secuencial.
            $comprasRepo->lockPago($idCompra, $idEmpresa);
            $saldoAnterior = $comprasRepo->calcularSaldoPendiente($idCompra, $idEmpresa);

            if ($montoPagar > $saldoAnterior + 0.01) {
                throw new \RuntimeException(sprintf(
                    'El monto a pagar (%.2f) supera el saldo pendiente de la compra (%.2f).',
                    $montoPagar,
                    $saldoAnterior
                ));
            }

            $secuencialService = new SecuencialService();
            $rSec = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
            $secuencial = (string) ($rSec['formateado'] ?? '');
            if ($secuencial === '') {
                throw new \RuntimeException('Error al reservar correlativo para el Egreso.');
            }

            $est = str_pad((string) ($pto['estab'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $ptoCod = str_pad((string) ($pto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $numEgreso = "{$est}-{$ptoCod}-{$secuencial}";

            $fechaEgreso = trim((string) ($body['fecha_emision'] ?? '')) !== '' ? (string) $body['fecha_emision'] : date('Y-m-d');
            $tipoOp = trim((string) ($body['tipo_operacion_bancaria'] ?? '')) !== '' ? trim((string) $body['tipo_operacion_bancaria']) : null;
            $numeroOperacion = trim((string) ($body['numero_operacion'] ?? '')) !== '' ? trim((string) $body['numero_operacion']) : null;
            $numDoc = "{$compra['establecimiento_prov']}-{$compra['punto_emision_prov']}-{$compra['secuencial_prov']}";

            $dataEgreso = [
                'id_empresa' => $idEmpresa,
                'usuario_id' => $idUsuario,
                'fecha_emision' => $fechaEgreso,
                'establecimiento' => $est,
                'punto_emision' => $ptoCod,
                'secuencial' => $secuencial,
                'numero_egreso' => $numEgreso,
                'id_punto_emision' => $idPunto,
                'id_establecimiento' => (int) $pto['id_estab'],
                'tipo_egreso' => 'COMPRA',
                'tipo_sujeto' => 'PROVEEDOR',
                'id_proveedor' => (int) $compra['id_proveedor'],
                'id_egreso_concepto' => (int) ($body['id_egreso_concepto'] ?? 0),
                'monto_total' => $montoPagar,
                'observaciones' => trim((string) ($body['observaciones'] ?? '')) !== '' ? trim((string) $body['observaciones']) : "Pago de Compra #{$numDoc}",
                'estado' => 'registrado',
                'detalles' => [[
                    'tipo_documento' => 'COMPRA',
                    'id_referencia_documento' => $idCompra,
                    'numero_documento' => $numDoc,
                    'descripcion' => "Pago de Compra #{$numDoc}",
                    'monto_documento' => (float) $compra['importe_total'],
                    'saldo_anterior' => $saldoAnterior,
                    'monto_pagado' => $montoPagar,
                    'saldo_actual' => max(0.0, $saldoAnterior - $montoPagar),
                ]],
                'pagos' => [[
                    'id_forma_pago' => $idFormaPago,
                    'monto' => $montoPagar,
                    'fecha_cobro' => ($tipoOp === 'CHEQUE' && !empty($body['fecha_cobro'])) ? $body['fecha_cobro'] : $fechaEgreso,
                    'tipo_operacion_bancaria' => $tipoOp,
                    'numero_cheque' => $tipoOp === 'CHEQUE' ? $numeroOperacion : null,
                    'referencia' => $numeroOperacion,
                ]],
            ];

            $egresoService = new EgresoService(new EgresoRepository(), new EgresoRules(), new LogSistemaService());
            $idEgreso = $egresoService->registrar($dataEgreso);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->jsonError('ERROR_PAGO', $e->getMessage(), 422);
        }

        $this->jsonOk([
            'id_egreso' => $idEgreso,
            'numero_egreso' => $numEgreso,
            'saldo_restante' => max(0.0, $saldoAnterior - $montoPagar),
        ], [], 201);
    }
}

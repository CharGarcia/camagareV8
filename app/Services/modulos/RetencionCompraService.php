<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RetencionCompraRepository;
use App\Rules\modulos\RetencionCompraRules;
use App\Services\LogSistemaService;
use App\Services\ClaveAccesoService;
use App\Services\Xml\XmlRetencionCompraService;
use App\core\Database;

class RetencionCompraService
{
    private RetencionCompraRepository $repository;
    private RetencionCompraRules      $rules;
    private LogSistemaService         $logService;

    public function __construct(
        RetencionCompraRepository $repository,
        RetencionCompraRules      $rules,
        LogSistemaService         $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTADO
    // ─────────────────────────────────────────────────────────────────────────

    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'fecha_emision',
        string $ordenDir = 'DESC',
        ?int $idUsuario = null
    ): array {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OBTENER
    // ─────────────────────────────────────────────────────────────────────────

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            return null;
        }
        $cabecera['lineas'] = $this->repository->getDetalle($id);
        return $cabecera;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        // Validar unicidad: solo una retención por documento de sustento
        if (!empty($data['num_doc_sustento']) && !empty($data['tipo_doc_sustento'])) {
            $this->validarUnicidadDocSustento($idEmpresa, $data);
        }

        // Calcular totales
        $data = $this->calcularTotales($data);
        error_log("RetencionCompraService::crear - Total calculado: " . ($data['total_retenido'] ?? 'N/A') . " - Lineas: " . count($data['lineas'] ?? []));

        // Generar secuencial y clave de acceso
        $data = $this->prepararSecuencialYClaveAcceso($data);

        // Validar que el secuencial no esté duplicado (igual que factura de venta)
        $this->validarSecuencial($data);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idRetencion = $this->repository->insertCabecera($data);

            $this->guardarLineas($idRetencion, $data['lineas'] ?? [], $idEmpresa, $data);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'retencion_compra_cabecera', $idRetencion,
                null, ['total_retenido' => $data['total_retenido'] ?? 0]
            );

            $this->sincronizarCasilleros($idRetencion, $data);

            if ($managed) $db->commit();
            $this->generarYGuardarXml($idRetencion, $data);
            return $idRetencion;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            error_log("RetencionCompraService::crear ERROR: " . $e->getMessage());
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────────────────

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== (int)$data['id_empresa']) {
            throw new \Exception('Retención no encontrada.');
        }
        if (($cabecera['estado'] ?? '') !== 'borrador') {
            throw new \Exception('Solo se pueden modificar retenciones en estado borrador.');
        }

        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        // Calcular totales
        $data = $this->calcularTotales($data);

        // Regenerar clave de acceso conservando el código numérico original
        $codigoNumerico = ClaveAccesoService::extraerCodigoNumerico($cabecera['clave_acceso'] ?? '');
        $data = $this->prepararSecuencialYClaveAcceso($data, $codigoNumerico);

        // Validar que el secuencial no esté duplicado, excluyendo la propia retención
        $this->validarSecuencial($data, $id);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->updateCabecera($id, $idEmpresa, $data);

            // Reemplazar líneas
            $this->repository->deleteDetalle($id);
            $this->guardarLineas($id, $data['lineas'] ?? [], $idEmpresa, $data);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'retencion_compra_cabecera', $id,
                $cabecera, ['total_retenido' => $data['total_retenido'] ?? 0]
            );

            $this->sincronizarCasilleros($id, $data);

            if ($managed) $db->commit();
            $this->generarYGuardarXml($id, $data);
            return $id;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ANULAR
    // ─────────────────────────────────────────────────────────────────────────

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \Exception('Retención no encontrada.');
        }
        if (($cabecera['estado'] ?? '') === 'anulada') {
            throw new \Exception('La retención ya está anulada.');
        }

        // Verificación SRI (igual que factura de venta): si la retención está autorizada,
        // solo se puede anular internamente cuando el SRI ya NO la reporta como AUTORIZADO,
        // es decir, cuando ya se anuló en el portal del SRI.
        $claveAcceso = trim((string)($cabecera['clave_acceso'] ?? ''));
        if (($cabecera['estado'] ?? '') === 'autorizada' && $claveAcceso !== '') {
            $tipoAmbiente = (string)($cabecera['tipo_ambiente'] ?? '1');
            $envioSri = new \App\Services\Sri\SriEnvioService();
            $consulta = $envioSri->verificarAutorizacion($claveAcceso, $tipoAmbiente);
            if (strtoupper($consulta['estado'] ?? '') === 'AUTORIZADO') {
                throw new \Exception(
                    'No se puede anular: el comprobante sigue AUTORIZADO en el SRI. ' .
                    'Primero debe anularlo en el portal del SRI; cuando deje de estar autorizado podrá anularlo aquí.'
                );
            }
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $db->prepare(
                "UPDATE retencion_compra_cabecera SET estado = 'anulada', updated_by = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$idUsuario, $id]);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ANULAR', 'retencion_compra_cabecera', $id,
                $cabecera, ['nuevo_estado' => 'anulada']
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'retenciones_compras', $id);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ELIMINAR (lógico)
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \Exception('Retención no encontrada.');
        }
        if (($cabecera['estado'] ?? '') === 'autorizada') {
            throw new \Exception('No se puede eliminar una retención autorizada por el SRI.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'retencion_compra_cabecera', $id,
                $cabecera, ['eliminado' => true]
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'retenciones_compras', $id);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CATÁLOGOS / AUXILIARES
    // ─────────────────────────────────────────────────────────────────────────

    public function getRetencionesSri(?string $impuesto = null, ?string $buscar = null): array
    {
        return $this->repository->getRetencionesSri($impuesto, $buscar);
    }

    public function buscarComprasDisponibles(int $idEmpresa, string $buscar = ''): array
    {
        return $this->repository->buscarComprasDisponibles($idEmpresa, $buscar);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    private function calcularTotales(array $data): array
    {
        $totalGeneral = 0;
        $totalRenta = 0;
        $totalIva = 0;

        if (isset($data['lineas']) && is_array($data['lineas'])) {
            foreach ($data['lineas'] as $i => $linea) {
                $base = (float)($linea['base_imponible'] ?? 0);
                $porc = (float)($linea['porcentaje_retener'] ?? 0);
                $val  = round(($base * $porc) / 100, 2);

                $data['lineas'][$i]['valor_retenido'] = $val;
                $totalGeneral += $val;

                // Agrupar por tipo de impuesto
                $codImp = strtoupper((string)($linea['codigo_impuesto'] ?? ''));
                if ($codImp === '1' || $codImp === 'RENTA') {
                    $totalRenta += $val;
                } elseif ($codImp === '2' || $codImp === 'IVA') {
                    $totalIva += $val;
                }
            }
        }

        $data['total_retenido_renta'] = round($totalRenta, 2);
        $data['total_retenido_iva']   = round($totalIva, 2);
        $data['total_retenido']       = round($totalGeneral, 2);
        return $data;
    }

    private function prepararSecuencialYClaveAcceso(array $data, ?string $codigoNumerico = null): array
    {
        // Si ya viene la clave de acceso (ej: desde SRI), no regeneramos nada
        if (!empty($data['clave_acceso']) && !empty($data['secuencial'])) {
            return $data;
        }

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId((int)$data['id_empresa']) ?? [];

        $ruc          = $empresa['ruc'] ?? '';
        $tipoAmbiente = (string)($empresa['tipo_ambiente'] ?? $data['tipo_ambiente'] ?? '1');
        $tipoEmision  = (string)($data['tipo_emision'] ?? '1');

        // Obtener secuencial si no viene
        $establecimiento = str_pad((string)($data['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        $puntoEmision    = str_pad((string)($data['punto_emision'] ?? '001'), 3, '0', STR_PAD_LEFT);

        if (empty($data['secuencial']) && !empty($data['id_punto_emision'])) {
            $secService = new \App\Services\SecuencialService();
            $secResult  = $secService->obtenerSiguienteSecuencial((int)$data['id_punto_emision'], 'Retenciones de compras');
            $data['secuencial'] = $secResult['formateado'];
        }

        $secuencial = str_pad((string)($data['secuencial'] ?? '000000001'), 9, '0', STR_PAD_LEFT);

        $data['tipo_ambiente'] = $tipoAmbiente;
        $data['tipo_emision']  = $tipoEmision;
        $data['establecimiento'] = $establecimiento;
        $data['punto_emision']   = $puntoEmision;
        $data['secuencial']      = $secuencial;

        $data['clave_acceso'] = ClaveAccesoService::generar(
            (string)($data['fecha_emision'] ?? date('Y-m-d')),
            ClaveAccesoService::RETENCION,
            $ruc,
            $tipoAmbiente,
            $establecimiento,
            $puntoEmision,
            $secuencial,
            $tipoEmision,
            $codigoNumerico
        );

        return $data;
    }

    private function guardarLineas(int $idRetencion, array $lineas, int $idEmpresa, array $cabData): void
    {
        foreach ($lineas as $linea) {
            $linea['id_retencion'] = $idRetencion;
            $linea['id_empresa']   = $idEmpresa;

            // Heredar datos de sustento de cabecera si no vienen en la línea
            if (empty($linea['cod_doc_sustento']))           $linea['cod_doc_sustento']           = $cabData['tipo_doc_sustento'] ?? '01';
            if (empty($linea['num_doc_sustento']))           $linea['num_doc_sustento']           = $cabData['num_doc_sustento'] ?? '';
            if (empty($linea['fecha_emision_doc_sustento'])) $linea['fecha_emision_doc_sustento'] = $cabData['fecha_emision_doc_sustento'] ?? null;

            error_log("RetencionCompraService::guardarLineas - Insertando linea: Base=" . ($linea['base_imponible'] ?? 0) . " Porc=" . ($linea['porcentaje_retener'] ?? 0) . " Val=" . ($linea['valor_retenido'] ?? 0));
            $this->repository->insertDetalle($linea);
        }
    }

    /**
     * Valida que el secuencial no esté duplicado para el punto de emisión
     * (mismo criterio que FacturaVentaService::validarSecuencial).
     */
    private function validarSecuencial(array $data, ?int $excluirId = null): void
    {
        if (empty($data['secuencial']) || empty($data['id_punto_emision'])) {
            return;
        }
        if ($this->repository->existeSecuencial(
            (int) $data['id_empresa'],
            (int) ($data['id_establecimiento'] ?? 0),
            (int) $data['id_punto_emision'],
            (string) $data['secuencial'],
            (string) ($data['tipo_ambiente'] ?? '1'),
            $excluirId
        )) {
            throw new \Exception('El número de secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }
    }

    private function validarUnicidadDocSustento(int $idEmpresa, array $data, ?int $excluirId = null): void
    {
        $existe = $this->repository->existeRetencionParaDocSustento(
            $idEmpresa,
            (string)$data['tipo_doc_sustento'],
            (string)$data['num_doc_sustento'],
            $excluirId
        );

        if ($existe) {
            throw new \Exception(
                'Ya existe una retención registrada para el documento de sustento ' .
                $data['num_doc_sustento'] . '. Solo se permite una retención por documento.'
            );
        }
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idRetencion, array $data): void
    {
        try {
            $idEmpresa = (int) ($data['id_empresa'] ?? 0);
            $cabecera  = $this->repository->getPorIdSri($idRetencion, $idEmpresa);
            if (!$cabecera) return;

            $lineas = $this->repository->getDetalle($idRetencion);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $docSustento = $this->repository->getDatosDocSustento($cabecera);

            $xml = (new XmlRetencionCompraService())->generar($cabecera, $lineas, $empresa, $dirEstablecimiento, $docSustento);
            $this->repository->updateDetalleXml($idRetencion, $xml);
        } catch (\Throwable $e) {
            error_log('[Retencion] Error generando XML para retención #' . $idRetencion . ': ' . $e->getMessage());
        }
    }

    public function sincronizarCasilleros(int $idRetencion, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idRetencion, $idEmpresa);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            $data['lineas'] = $this->repository->getDetalle($idRetencion);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'retenciones_compras', $idRetencion);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['retencion_iva'])) return;

        $confRet = $configDec['retencion_iva'];

        // Mapa de codigo_ret a ID interno de la base
        $db = \App\core\Database::getConnection();
        $st = $db->query("SELECT id, codigo_ret FROM retenciones_sri WHERE impuesto_ret = 'IVA'");
        $sriMap = [];
        foreach ($st->fetchAll() as $r) {
            $sriMap[$r['codigo_ret']] = $r['id'];
        }

        // Agrupar bases e impuestos por codigo_retencion (solo IVA)
        $agrupacion = [];
        $lineas = $data['lineas'] ?? [];
        foreach ($lineas as $linea) {
            $codImp = strtoupper((string)($linea['codigo_impuesto'] ?? ''));
            // 1=Renta, 2=IVA, 6=ISD
            if ($codImp !== '2' && $codImp !== 'IVA') continue;
            
            $codRet = (string)($linea['codigo_retencion'] ?? '');
            $sriId = $sriMap[$codRet] ?? null;
            if (!$sriId) continue;

            if (!isset($agrupacion[$sriId])) {
                $agrupacion[$sriId] = ['valor' => 0.0, 'codRet' => $codRet];
            }
            $agrupacion[$sriId]['valor'] += (float)($linea['valor_retenido'] ?? 0);
        }

        // Mapear y guardar
        foreach ($agrupacion as $sriId => $datos) {
            $valor = $datos['valor'];
            $codRet = $datos['codRet'];

            if ($valor <= 0) continue;
            if (!isset($confRet[$sriId])) continue;

            $c = $confRet[$sriId];
            $casilleroCompras = $c['bruto'] ?? '';

            if ($casilleroCompras !== '') {
                $decIvaRepo->insertarCasilleroDeclaracion([
                    'id_empresa' => $idEmpresa, 'origen' => 'retenciones_compras', 'id_origen' => $idRetencion,
                    'fecha' => $fechaEmision, 'casillero' => $casilleroCompras, 'valor' => $valor, 'concepto' => 'Retención IVA (Cód: ' . $codRet . ')'
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE (mismo modelo que retención de venta / factura)
    // ─────────────────────────────────────────────────────────────────────────

    /** Asiento contable sugerido para una retención (líneas debe/haber). */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idRetencion): array
    {
        return (new \App\Services\modulos\AsientoBuilderService())->generarAsientoRetencionCompra($idEmpresa, $idRetencion);
    }

    /** Devuelve el asiento contable ya REGISTRADO (cabecera + detalles) por su id. */
    public function getAsientoRegistrado(int $idAsiento, int $idEmpresa): array
    {
        return $this->asientoContableService()->getDetalleAsiento($idAsiento, $idEmpresa);
    }

    private function asientoContableService(): \App\Services\modulos\AsientoContableService
    {
        return new \App\Services\modulos\AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );
    }

    /**
     * Genera (o regenera) y guarda el asiento contable de la retención y enlaza
     * id_asiento_contable en la cabecera. No hace nada si el asiento queda vacío.
     */
    public function procesarAsientoContable(int $idRetencion, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);
        $fecha     = $data['fecha_emision'] ?? date('Y-m-d');

        $detallesSugeridos = $this->obtenerAsientoSugerido($idEmpresa, $idRetencion);
        if (empty($detallesSugeridos)) {
            return;
        }

        $num = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Retención # $num",
                'documento_referencia' => "Retención # $num",
                'id_entidad'           => (int) ($data['id_proveedor'] ?? 0),
                'tipo_entidad'         => 'proveedor',
            ];
        }

        $asientoService = $this->asientoContableService();
        $asientoPrevio  = $asientoService->getAsientoPorOrigen('retencion_compra', $idRetencion, $idEmpresa);
        $idAsiento      = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'compras',
            'numero_comprobante'   => '',
            'concepto'             => "Retención # $num",
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'retencion_compra',
            'id_referencia_origen' => $idRetencion,
            'observaciones'        => null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idRetencion, $idAsientoGenerado);
    }

    /**
     * Genera el asiento de una retención de compra por sincronización masiva (estados financieros).
     * Toma los datos desde la cabecera guardada. Propaga la excepción si no se puede generar
     * (descuadre / cuentas faltantes) para que el sincronizador lo contabilice como pendiente.
     */
    public function procesarAsientoContablePorSincronizacion(int $idRetencion): void
    {
        $cabecera = $this->repository->getPorId($idRetencion);
        if (!$cabecera) {
            return;
        }
        $cabecera['id_usuario'] = (int) ($cabecera['updated_by'] ?? $cabecera['created_by'] ?? 0);
        $this->procesarAsientoContable($idRetencion, $cabecera);
    }
}

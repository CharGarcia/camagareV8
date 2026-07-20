<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RetencionVentaRepository;
use App\Rules\modulos\RetencionVentaRules;
use App\Services\LogSistemaService;
use App\core\Database;

class RetencionVentaService
{
    private RetencionVentaRepository $repository;
    private RetencionVentaRules      $rules;
    private LogSistemaService        $logService;

    public function __construct(
        RetencionVentaRepository $repository,
        RetencionVentaRules      $rules,
        LogSistemaService        $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        // Ambiente del documento: el que llega (flujo automático del SRI) o, si no
        // viene (alta manual), el ambiente activo de la empresa. Sin esto la
        // inserción caía al default fijo '1' y el registro quedaba invisible.
        $data['tipo_ambiente'] = $this->resolverAmbiente($data, $idEmpresa);

        // Validar duplicado por número del cliente (dentro del mismo ambiente)
        $this->validarDuplicado($idEmpresa, $data);

        // Validar clave de acceso única si viene (dentro del mismo ambiente)
        if (!empty($data['clave_acceso'])) {
            if ($this->repository->existeClaveAcceso($data['clave_acceso'], $idEmpresa, null, $data['tipo_ambiente'])) {
                throw new \Exception('Ya existe una retención registrada con esa clave de acceso.');
            }
        }

        $data = $this->calcularTotales($data);
        $data['id_usuario'] = $idUsuario;

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idRetencion = $this->repository->insertCabecera($data);
            $this->guardarLineas($idRetencion, $data['lineas'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'retencion_venta_cabecera', $idRetencion,
                null, ['total_renta' => $data['total_renta'] ?? 0, 'total_iva' => $data['total_iva'] ?? 0]
            );

            $this->sincronizarCasilleros($idRetencion, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable fuera de la transacción: un fallo aquí no revierte la retención.
        try {
            $this->procesarAsientoContable($idRetencion, $data);
        } catch (\Throwable $e) {
            error_log('[RetencionVenta] Asiento no generado: ' . $e->getMessage());
        }

        return $idRetencion;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────────────────

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? 0);

        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Retención no encontrada.');
        }

        $this->rules->validar($data);

        // Conservar el ambiente del registro guardado (updateCabecera no lo cambia).
        $data['tipo_ambiente'] = trim((string)($cabecera['tipo_ambiente'] ?? '')) !== ''
            ? (string)$cabecera['tipo_ambiente']
            : $this->resolverAmbiente($data, $idEmpresa);

        $this->validarDuplicado($idEmpresa, $data, $id);

        if (!empty($data['clave_acceso'])) {
            if ($this->repository->existeClaveAcceso($data['clave_acceso'], $idEmpresa, $id, $data['tipo_ambiente'])) {
                throw new \Exception('Ya existe otra retención con esa clave de acceso.');
            }
        }

        $data = $this->calcularTotales($data);
        $data['id_usuario'] = $idUsuario;

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->updateCabecera($id, $idEmpresa, $data);
            $this->repository->deleteDetalle($id);
            $this->guardarLineas($id, $data['lineas'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'retencion_venta_cabecera', $id,
                $cabecera, ['total_renta' => $data['total_renta'] ?? 0, 'total_iva' => $data['total_iva'] ?? 0]
            );

            $this->sincronizarCasilleros($id, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable fuera de la transacción: un fallo aquí no revierte la retención.
        try {
            $this->procesarAsientoContable($id, $data);
        } catch (\Throwable $e) {
            error_log('[RetencionVenta] Asiento no generado: ' . $e->getMessage());
        }

        return $id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ELIMINAR (lógico)
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Retención no encontrada.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'retencion_venta_cabecera', $id,
                $cabecera, ['eliminado' => true]
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'retenciones_ventas', $id);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREAR DESDE XML (usado por DocumentoAutomatedRegisterService)
    // ─────────────────────────────────────────────────────────────────────────

    public function crearDesdeXml(array $data): int
    {
        $data['origen'] = 'electronico';
        return $this->crear($data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    /** Asiento contable sugerido para una retención (líneas debe/haber, recalculadas). */
    public function obtenerAsientoSugerido(int $idEmpresa, int $idRetencion): array
    {
        return (new AsientoBuilderService())->generarAsientoRetencionVenta($idEmpresa, $idRetencion);
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
                'id_entidad'           => (int) ($data['id_cliente'] ?? 0),
                'tipo_entidad'         => 'cliente',
            ];
        }

        $asientoService = $this->asientoContableService();
        $asientoPrevio  = $asientoService->getAsientoPorOrigen('retencion_venta', $idRetencion, $idEmpresa);
        $idAsiento     = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'ventas',
            'numero_comprobante'   => '',
            'concepto'             => "Retención # $num",
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'retencion_venta',
            'id_referencia_origen' => $idRetencion,
            'observaciones'        => null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idRetencion, $idAsientoGenerado);
    }

    /**
     * Genera el asiento de una retención por sincronización masiva (estados financieros).
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

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /** Método público para calcular totales desde servicios externos */
    public function calcularTotalesPublic(array $data): array
    {
        return $this->calcularTotales($data);
    }

    private function calcularTotales(array $data): array
    {
        $totalRenta = 0;
        $totalIva   = 0;
        $totalIsd   = 0;

        if (isset($data['lineas']) && is_array($data['lineas'])) {
            foreach ($data['lineas'] as $i => $linea) {
                $base = (float)($linea['base_imponible']   ?? 0);
                $porc = (float)($linea['porcentaje_retencion'] ?? 0);
                $val  = round(($base * $porc) / 100, 2);

                $data['lineas'][$i]['valor_retenido'] = $val;

                $codImp = strtoupper((string)($linea['codigo_impuesto'] ?? ''));
                if ($codImp === '1' || $codImp === 'RENTA') {
                    $totalRenta += $val;
                } elseif ($codImp === '2' || $codImp === 'IVA') {
                    $totalIva += $val;
                } elseif ($codImp === '6' || $codImp === 'ISD') {
                    $totalIsd += $val;
                }
            }
        }

        $data['total_renta'] = round($totalRenta, 2);
        $data['total_iva']   = round($totalIva,   2);
        $data['total_isd']   = round($totalIsd,   2);
        return $data;
    }

    private function guardarLineas(int $idRetencion, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $linea['id_retencion'] = $idRetencion;
            $this->repository->insertDetalle($linea);
        }
    }

    /**
     * Ambiente del documento: el que traiga $data (flujo automático del SRI) o,
     * si no viene, el ambiente activo de la empresa.
     */
    private function resolverAmbiente(array $data, int $idEmpresa): string
    {
        $amb = trim((string)($data['tipo_ambiente'] ?? ''));
        return $amb !== '' ? $amb : $this->repository->getTipoAmbienteEmpresa($idEmpresa);
    }

    private function validarDuplicado(int $idEmpresa, array $data, ?int $excluirId = null): void
    {
        $ambiente = trim((string)($data['tipo_ambiente'] ?? ''));

        $existe = $this->repository->existeNumero(
            $idEmpresa,
            $data['establecimiento'] ?? '',
            $data['punto_emision']   ?? '',
            $data['secuencial']      ?? '',
            (int)($data['id_cliente'] ?? 0),
            $excluirId,
            $ambiente !== '' ? $ambiente : null
        );

        if ($existe) {
            $num = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . ($data['secuencial'] ?? '');
            throw new \Exception("Ya existe una retención registrada con el número {$num} para este cliente.");
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
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'retenciones_ventas', $idRetencion);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);
        if (!$configDec || !isset($configDec['retencion_iva'])) return;

        $confRet = $configDec['retencion_iva'];

        // Mapa de codigo_ret (ej. '9', '10') a ID interno de la base
        $db = \App\core\Database::getConnection();
        $st = $db->query("SELECT id, codigo_ret FROM retenciones_sri WHERE impuesto_ret = 'IVA'");
        $sriMap = [];
        foreach ($st->fetchAll() as $r) {
            $sriMap[$r['codigo_ret']] = $r['id'];
        }

        // Agrupar por codigo_retencion (solo IVA)
        $agrupacion = [];
        $lineas = $data['lineas'] ?? [];
        foreach ($lineas as $linea) {
            $codImp = strtoupper((string)($linea['codigo_impuesto'] ?? ''));
            if ($codImp === '2' || $codImp === 'IVA') {
                $codRet = (string)($linea['codigo_retencion'] ?? '');
                $sriId = $sriMap[$codRet] ?? null;
                if (!$sriId) continue;

                if (!isset($agrupacion[$sriId])) {
                    $agrupacion[$sriId] = ['valor' => 0.0, 'codRet' => $codRet];
                }
                $agrupacion[$sriId]['valor'] += (float)($linea['valor_retenido'] ?? 0);
            }
        }

        // Mapear y guardar en el casillero de ventas (neto)
        foreach ($agrupacion as $sriId => $datos) {
            $valor = $datos['valor'];
            $codRet = $datos['codRet'];
            
            if ($valor <= 0) continue;
            if (!isset($confRet[$sriId])) continue;

            $c = $confRet[$sriId];
            $casilleroVentas = $c['neto'] ?? '';

            if ($casilleroVentas !== '') {
                $decIvaRepo->insertarCasilleroDeclaracion([
                    'id_empresa' => $idEmpresa, 'origen' => 'retenciones_ventas', 'id_origen' => $idRetencion,
                    'fecha' => $fechaEmision, 'casillero' => $casilleroVentas, 'valor' => $valor, 'concepto' => 'Retención IVA en Venta (Cód: ' . $codRet . ')'
                ]);
            }
        }
    }
}

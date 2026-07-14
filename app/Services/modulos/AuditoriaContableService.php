<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AuditoriaContableRepository;
use App\Rules\modulos\AuditoriaContableRules;
use App\Services\LogSistemaService;

/**
 * Service del módulo Auditoría Contable.
 *
 * Orquesta:
 *  - La ejecución de la auditoría (corre los chequeos del repository, hace el
 *    upsert de incidencias preservando la revisión del usuario, resuelve las que
 *    ya no aplican y registra la corrida).
 *  - Las acciones de corrección por fila (generar faltante, anular duplicado,
 *    corregir ambiente incoherente).
 *  - La regeneración masiva por origen (anula + desvincula respetando períodos
 *    cerrados y vuelve a generar vía el SincronizadorAsientosService/servicios de origen).
 *
 * Todo cambio de datos va en transacción y se audita en log_sistema.
 */
class AuditoriaContableService
{
    public function __construct(
        private AuditoriaContableRepository $repo,
        private AuditoriaContableRules $rules,
        private LogSistemaService $log
    ) {}

    // ==================================================================
    //  EJECUCIÓN DE LA AUDITORÍA
    // ==================================================================

    /**
     * Corre la auditoría sobre el ambiente activo de la empresa y sincroniza la
     * tabla de incidencias. Devuelve el resumen por tipo + totales de la corrida.
     *
     * @param string|null $soloOrigen Limita a un modulo_origen (opcional).
     * @param string|null $fechaDesde Acota la auditoría por fecha (AAAA-MM-DD).
     * @param string|null $fechaHasta Idem.
     */
    public function ejecutarAuditoria(int $idEmpresa, int $idUsuario, ?string $soloOrigen = null,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $this->rules->validarRango($fechaDesde, $fechaHasta);
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);

        $this->repo->beginTransaction();
        try {
            $abiertas  = $this->repo->getIncidenciasAbiertas($idEmpresa, $ambiente, $fechaDesde, $fechaHasta);
            $hallazgos = $this->repo->detectarTodos($idEmpresa, $soloOrigen, $fechaDesde, $fechaHasta);

            $clavesVigentes = [];
            foreach ($hallazgos as $h) {
                $this->repo->upsertIncidencia($idEmpresa, $ambiente, $h, $idUsuario);
                $clavesVigentes[$this->repo->claveLogica($h)] = true;
            }

            // Resolver las incidencias abiertas que ya no fueron detectadas.
            // Si la corrida es de un solo origen, solo se tocan las de ese origen.
            $idsResolver = [];
            foreach ($abiertas as $clave => $id) {
                if (isset($clavesVigentes[$clave])) {
                    continue;
                }
                if ($soloOrigen !== null) {
                    $partes = explode('|', $clave);
                    if (($partes[1] ?? '') !== $soloOrigen) {
                        continue;
                    }
                }
                $idsResolver[] = $id;
            }
            $resueltas = $this->repo->marcarResueltas($idsResolver, $idUsuario);

            $corridaId = $this->repo->registrarCorrida([
                'id_empresa'       => $idEmpresa,
                'tipo_ambiente'    => $ambiente,
                'tipo_corrida'     => 'auditoria',
                'modulo_origen'    => $soloOrigen,
                'fecha_desde'      => $fechaDesde,
                'fecha_hasta'      => $fechaHasta,
                'total_detectadas' => count($hallazgos),
                'estado'           => 'ok',
                'mensaje'          => $resueltas > 0 ? "{$resueltas} incidencia(s) resuelta(s)." : null,
            ], $idUsuario);

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_ejecutada',
            'auditoria_contable_corridas', $corridaId, null,
            ['detectadas' => count($hallazgos), 'resueltas' => $resueltas, 'origen' => $soloOrigen]);

        return [
            'corrida_id'  => $corridaId,
            'detectadas'  => count($hallazgos),
            'resueltas'   => $resueltas,
            'resumen'     => $this->repo->getResumenPorTipo($idEmpresa, $ambiente),
        ];
    }

    // ==================================================================
    //  REVISIÓN MANUAL
    // ==================================================================

    public function marcarRevision(int $idIncidencia, int $idEmpresa, int $idUsuario, string $estadoRevision, ?string $nota): void
    {
        $this->rules->validarEstadoRevision($estadoRevision);

        $inc = $this->repo->getIncidenciaPorId($idIncidencia, $idEmpresa);
        if ($inc === null) {
            throw new \Exception('Incidencia no encontrada.');
        }

        $this->repo->actualizarRevision($idIncidencia, $idEmpresa, $estadoRevision, $nota, $idUsuario);

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_marcar_revision',
            'auditoria_contable_incidencias', $idIncidencia,
            ['estado_revision' => $inc['estado_revision']],
            ['estado_revision' => $estadoRevision, 'nota' => $nota]);
    }

    // ==================================================================
    //  ACCIONES DE CORRECCIÓN POR FILA
    // ==================================================================

    /** Genera el asiento faltante de una incidencia y re-audita el origen. */
    public function generarFaltante(int $idIncidencia, int $idEmpresa, int $idUsuario): void
    {
        $inc = $this->repo->getIncidenciaPorId($idIncidencia, $idEmpresa);
        if ($inc === null) {
            throw new \Exception('Incidencia no encontrada.');
        }
        if ($inc['tipo_hallazgo'] !== 'faltante') {
            throw new \Exception('La incidencia no es de tipo «faltante».');
        }
        if (empty($inc['id_documento'])) {
            throw new \Exception('La incidencia no tiene documento asociado.');
        }

        $origen = (string) $inc['modulo_origen'];
        $this->rules->validarOrigen($origen, $this->repo->getOrigenes());

        $svc = $this->serviceParaOrigen($origen);
        $svc->procesarAsientoContablePorSincronizacion((int) $inc['id_documento']);

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_generar_faltante',
            $origen, (int) $inc['id_documento'], null, ['incidencia' => $idIncidencia]);

        // Refrescar incidencias del origen tras generar.
        $this->ejecutarAuditoria($idEmpresa, $idUsuario, $origen);
    }

    /** Anula lógicamente un asiento duplicado (el usuario elige cuál) y re-audita. */
    public function anularDuplicado(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $this->repo->beginTransaction();
        try {
            $this->repo->anularAsiento($idAsiento, $idEmpresa, $idUsuario);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_anular_duplicado',
            'asientos_contables_cabecera', $idAsiento, null, null);

        $this->ejecutarAuditoria($idEmpresa, $idUsuario);
    }

    /** Corrige el ambiente de un asiento heredándolo del documento y re-audita. */
    public function corregirAmbiente(int $idIncidencia, int $idEmpresa, int $idUsuario): void
    {
        $inc = $this->repo->getIncidenciaPorId($idIncidencia, $idEmpresa);
        if ($inc === null) {
            throw new \Exception('Incidencia no encontrada.');
        }
        if ($inc['tipo_hallazgo'] !== 'ambiente_incoherente') {
            throw new \Exception('La incidencia no es de tipo «ambiente incoherente».');
        }
        if (empty($inc['id_asiento'])) {
            throw new \Exception('La incidencia no tiene asiento asociado.');
        }

        $origen = (string) $inc['modulo_origen'];
        $n = $this->repo->corregirAmbienteAsiento((int) $inc['id_asiento'], $origen, $idEmpresa, $idUsuario);

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_corregir_ambiente',
            'asientos_contables_cabecera', (int) $inc['id_asiento'], null, ['filas' => $n]);

        $this->ejecutarAuditoria($idEmpresa, $idUsuario, $origen);
    }

    // ==================================================================
    //  REGENERACIÓN MASIVA
    // ==================================================================

    /**
     * Anula y vuelve a generar todos los asientos de un origen (opcionalmente
     * acotado por rango de fechas), respetando los períodos contables cerrados.
     *
     * Fase 1 (transacción atómica): anula asientos vigentes + desvincula documentos,
     *   omitiendo los que caen en período cerrado.
     * Fase 2 (idempotente): regenera vía el servicio del origen, cada documento en
     *   su propia transacción.
     */
    public function regenerarMasivo(int $idEmpresa, int $idUsuario, string $origen, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        // Solo se ofrecen (y aceptan) orígenes cuyo Service sabe regenerar el asiento.
        $this->rules->validarRegeneracion($origen, $this->repo->getOrigenesRegenerables(), $fechaDesde, $fechaHasta);

        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        $asientos = $this->repo->getAsientosDeOrigen($idEmpresa, $origen, $fechaDesde, $fechaHasta);

        $anulados = 0;
        $omitidos = 0;
        $docs = [];

        // Fase 1: anular + desvincular (atómica)
        $this->repo->beginTransaction();
        try {
            foreach ($asientos as $a) {
                $fecha = substr((string) $a['fecha_asiento'], 0, 10);
                if ($fecha !== '' && $this->repo->fechaEnPeriodoCerrado($idEmpresa, $fecha)) {
                    $omitidos++;
                    continue;
                }
                $this->repo->anularAsiento((int) $a['id'], $idEmpresa, $idUsuario);
                if (!empty($a['id_referencia_origen'])) {
                    $this->repo->desvincularDocumento($origen, (int) $a['id_referencia_origen'], $idEmpresa);
                    $docs[] = (int) $a['id_referencia_origen'];
                }
                $anulados++;
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        // Fase 2: regenerar (cada documento en su propia transacción interna del servicio)
        $regenerados = 0;
        $errores = 0;
        if (!empty($docs)) {
            $svc = $this->serviceParaOrigen($origen);
            foreach ($docs as $idDoc) {
                try {
                    $svc->procesarAsientoContablePorSincronizacion($idDoc);
                    $regenerados++;
                } catch (\Throwable $e) {
                    $errores++;
                }
            }
        }

        $estado = $errores > 0 ? 'parcial' : 'ok';
        $mensaje = "Anulados: {$anulados}, Regenerados: {$regenerados}, Omitidos (período cerrado): {$omitidos}";
        if ($errores > 0) {
            $mensaje .= ", Errores al regenerar: {$errores} (revise la configuración de cuentas).";
        }

        $corridaId = $this->repo->registrarCorrida([
            'id_empresa'        => $idEmpresa,
            'tipo_ambiente'     => $ambiente,
            'tipo_corrida'      => 'regeneracion',
            'modulo_origen'     => $origen,
            'fecha_desde'       => $fechaDesde,
            'fecha_hasta'       => $fechaHasta,
            'total_documentos'  => count($asientos),
            'total_anulados'    => $anulados,
            'total_regenerados' => $regenerados,
            'total_omitidos'    => $omitidos,
            'estado'            => $estado,
            'mensaje'           => $mensaje,
        ], $idUsuario);

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_regeneracion_masiva',
            $origen, $corridaId, null,
            ['anulados' => $anulados, 'regenerados' => $regenerados, 'omitidos' => $omitidos, 'errores' => $errores]);

        // Re-auditar el origen para reflejar el nuevo estado.
        $this->ejecutarAuditoria($idEmpresa, $idUsuario, $origen);

        return [
            'corrida_id'  => $corridaId,
            'anulados'    => $anulados,
            'regenerados' => $regenerados,
            'omitidos'    => $omitidos,
            'errores'     => $errores,
            'estado'      => $estado,
            'mensaje'     => $mensaje,
        ];
    }

    /**
     * Regenera el asiento de UN solo documento (corrección de un clic para
     * monto_no_coincide, descuadrado y cab_vs_detalle): anula los asientos vivos
     * de ese documento y lo vuelve a generar con la configuración actual.
     * Aborta si algún asiento del documento cae en un período cerrado.
     */
    public function regenerarDocumento(int $idIncidencia, int $idEmpresa, int $idUsuario): array
    {
        $inc = $this->repo->getIncidenciaPorId($idIncidencia, $idEmpresa);
        if ($inc === null) {
            throw new \Exception('Incidencia no encontrada.');
        }
        $origen = (string) $inc['modulo_origen'];
        $this->rules->validarOrigen($origen, $this->repo->getOrigenes());

        if (!$this->repo->esOrigenRegenerable($origen)) {
            throw new \Exception('Este tipo de documento no admite regenerar su asiento desde '
                . 'Auditoría Contable; corríjalo desde su propio módulo.');
        }

        $idDoc = (int) ($inc['id_documento'] ?? 0);
        if ($idDoc <= 0) {
            throw new \Exception('La incidencia no tiene documento asociado para regenerar.');
        }

        // Los documentos traídos por la migración desde la BD vieja NO se regeneran:
        // su contabilidad viene del histórico migrado, no del generador de asientos.
        if ($this->repo->esDocumentoMigrado($origen, $idDoc)) {
            throw new \Exception('Este documento proviene de la migración de la base anterior: '
                . 'su contabilidad es la del histórico migrado y no puede regenerarse.');
        }

        $asientos = $this->repo->getAsientosDeDocumento($idEmpresa, $origen, $idDoc);

        // Salvaguarda: no tocar nada si algún asiento del documento está en período cerrado.
        foreach ($asientos as $a) {
            $fecha = substr((string) $a['fecha_asiento'], 0, 10);
            if ($fecha !== '' && $this->repo->fechaEnPeriodoCerrado($idEmpresa, $fecha)) {
                throw new \Exception('El asiento pertenece a un período contable cerrado; no se puede regenerar.');
            }
        }

        // Fase 1: anular + desvincular (atómica)
        $anulados = 0;
        $this->repo->beginTransaction();
        try {
            foreach ($asientos as $a) {
                $this->repo->anularAsiento((int) $a['id'], $idEmpresa, $idUsuario);
                $anulados++;
            }
            $this->repo->desvincularDocumento($origen, $idDoc, $idEmpresa);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        // Fase 2: regenerar el documento
        try {
            $this->serviceParaOrigen($origen)->procesarAsientoContablePorSincronizacion($idDoc);
        } catch (\Throwable $e) {
            $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_regenerar_documento_error',
                $origen, $idDoc, null, ['anulados' => $anulados, 'error' => $e->getMessage()]);
            throw new \Exception('Se anuló el asiento anterior pero falló la regeneración: ' . $e->getMessage()
                . ' Revise la configuración de cuentas del origen.');
        }

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_regenerar_documento',
            $origen, $idDoc, null, ['anulados' => $anulados]);

        $this->ejecutarAuditoria($idEmpresa, $idUsuario, $origen);

        return ['anulados' => $anulados, 'regenerado' => true];
    }

    // ==================================================================
    //  LECTURA PARA LA VISTA (delegación)
    // ==================================================================

    public function getResumen(int $idEmpresa): array
    {
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        return $this->repo->getResumenPorTipo($idEmpresa, $ambiente);
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20,
        string $ordenCol = 'detectado_at', string $ordenDir = 'DESC', ?int $idUsuarioFiltro = null,
        ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $this->rules->validarRango($fechaDesde, $fechaHasta);
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        return $this->repo->getListado($idEmpresa, $ambiente, $buscar, $page, $perPage,
            $ordenCol, $ordenDir, $idUsuarioFiltro, $fechaDesde, $fechaHasta);
    }

    public function getCorridas(int $idEmpresa, int $limit = 50): array
    {
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        return $this->repo->getCorridas($idEmpresa, $ambiente, $limit);
    }

    public function getOrigenes(): array
    {
        return $this->repo->getOrigenes();
    }

    /** Orígenes que admiten regeneración (los demás se corrigen en su propio módulo). */
    public function getOrigenesRegenerables(): array
    {
        return $this->repo->getOrigenesRegenerables();
    }

    /** ¿Este origen admite regenerar su asiento desde el módulo? */
    public function esOrigenRegenerable(string $origen): bool
    {
        return $this->repo->esOrigenRegenerable($origen);
    }

    /** Asientos vivos de un documento (para el modal de resolución de duplicados). */
    public function getAsientosDeDocumento(int $idEmpresa, string $origen, int $idDocumento): array
    {
        return $this->repo->getAsientosDeDocumento($idEmpresa, $origen, $idDocumento);
    }

    // ==================================================================
    //  FÁBRICA DE SERVICIOS DE ORIGEN (replica SincronizadorAsientosService)
    // ==================================================================

    /** Devuelve el Service que sabe generar el asiento de un origen dado. */
    private function serviceParaOrigen(string $origen): object
    {
        $log = new LogSistemaService();

        switch ($origen) {
            case 'factura_venta':
                return new FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    $log
                );
            case 'liquidacion_compra':
                return new LiquidacionCompraService(
                    new \App\repositories\modulos\LiquidacionCompraRepository(),
                    new \App\Rules\modulos\LiquidacionCompraRules(),
                    $log
                );
            case 'compra':
                return new ComprasService();
            case 'nota_credito':
                return new NotaCreditoService(
                    new \App\repositories\modulos\NotaCreditoRepository(),
                    new \App\Rules\modulos\NotaCreditoRules(),
                    $log
                );
            case 'retencion_venta':
                return new RetencionVentaService(
                    new \App\repositories\modulos\RetencionVentaRepository(),
                    new \App\Rules\modulos\RetencionVentaRules(),
                    $log
                );
            case 'ingreso':
                return new IngresoService(
                    new \App\repositories\modulos\IngresoRepository(),
                    new \App\Rules\modulos\IngresoRules(),
                    $log
                );
            case 'egreso':
                return new EgresoService(
                    new \App\repositories\modulos\EgresoRepository(),
                    new \App\Rules\modulos\EgresoRules(),
                    $log
                );
            case 'retencion_compra':
                return new RetencionCompraService(
                    new \App\repositories\modulos\RetencionCompraRepository(),
                    new \App\Rules\modulos\RetencionCompraRules(),
                    $log
                );
            case 'consignacion_venta':
                return new ConsignacionVentaService(
                    new \App\repositories\modulos\ConsignacionVentaRepository(),
                    new \App\Rules\modulos\ConsignacionVentaRules(),
                    $log
                );
            case 'recibo_venta':
                return new ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    $log
                );
            default:
                throw new \Exception("El origen «{$origen}» no admite regenerar su asiento desde "
                    . "Auditoría Contable; corríjalo desde su propio módulo.");
        }
    }
}

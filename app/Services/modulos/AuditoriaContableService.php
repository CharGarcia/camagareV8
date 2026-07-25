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

            // Completa número/entidad en incidencias antiguas (las resueltas ya no se re-detectan).
            $this->repo->backfillDatosDocumento($idEmpresa, $ambiente);

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

    /** Documentos que procesa cada lote de regeneración (una petición del navegador). */
    public const LOTE_REGENERACION = 25;

    /** Tope de vueltas del bucle de regenerarMasivo(); salvaguarda contra un lote que no avance. */
    private const MAX_LOTES = 2000;

    /**
     * Dimensiona una corrida de regeneración: por cada origen a procesar informa cuántos
     * asientos se van a rehacer y cuántos quedan fuera por caer en período cerrado.
     * Devuelve además el `id_maximo`, la «foto» del último asiento existente: los lotes
     * solo tocan asientos anteriores a ese id, así lo que la corrida genera no se reprocesa.
     *
     * @param string|null $origen Un origen concreto, o null/'' para TODOS los regenerables.
     */
    public function planRegeneracion(int $idEmpresa, ?string $origen = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $this->rules->validarRango($fechaDesde, $fechaHasta);

        $regenerables = $this->repo->getOrigenesRegenerables();
        $origenes = ($origen !== null && $origen !== '') ? [$origen] : $regenerables;

        $plan = [];
        $total = 0;
        $cerrados = 0;
        foreach ($origenes as $o) {
            $this->rules->validarRegeneracion($o, $regenerables, $fechaDesde, $fechaHasta);
            $c = $this->repo->contarAsientosDeOrigen($idEmpresa, $o, $fechaDesde, $fechaHasta);
            $plan[]    = ['origen' => $o, 'pendientes' => $c['pendientes'], 'cerrados' => $c['cerrados']];
            $total    += $c['pendientes'];
            $cerrados += $c['cerrados'];
        }

        return [
            'origenes'  => $plan,
            'total'     => $total,
            'cerrados'  => $cerrados,
            'lote'      => self::LOTE_REGENERACION,
            'id_maximo' => $this->repo->getMaxIdAsiento($idEmpresa),
        ];
    }

    /**
     * Procesa UN lote de la regeneración de un origen: anula + desvincula hasta `$limite`
     * asientos y los vuelve a generar. Está pensado para llamarse en cadena desde la UI,
     * de modo que una corrida grande no dependa del max_execution_time.
     *
     * Fase 1 (transacción atómica): anula los asientos del lote y desvincula sus documentos.
     * Fase 2 (idempotente): regenera vía el Service del origen, cada documento en su
     *   propia transacción. Un documento que falla no aborta el lote.
     *
     * Los asientos en período cerrado se excluyen en la consulta (no se anulan nunca) y
     * los cuenta `planRegeneracion()` como omitidos.
     */
    public function regenerarLote(int $idEmpresa, int $idUsuario, string $origen, ?string $fechaDesde = null,
        ?string $fechaHasta = null, int $limite = self::LOTE_REGENERACION, ?int $idMaximo = null): array
    {
        $this->rules->validarRegeneracion($origen, $this->repo->getOrigenesRegenerables(), $fechaDesde, $fechaHasta);
        $limite = max(1, min(200, $limite));

        $asientos = $this->repo->getAsientosDeOrigen($idEmpresa, $origen, $fechaDesde, $fechaHasta, $limite, $idMaximo);
        if (empty($asientos)) {
            return ['procesados' => 0, 'anulados' => 0, 'regenerados' => 0, 'errores' => 0,
                    'restantes' => 0, 'mensajes' => []];
        }

        // Fase 1: anular + desvincular el lote (atómica).
        $docs     = [];
        $anulados = 0;
        $this->repo->beginTransaction();
        try {
            foreach ($asientos as $a) {
                $this->repo->anularAsiento((int) $a['id'], $idEmpresa, $idUsuario);
                if (!empty($a['id_referencia_origen'])) {
                    // Clave del array: un documento con varios asientos se regenera una sola vez.
                    $docs[(int) $a['id_referencia_origen']] = true;
                    $this->repo->desvincularDocumento($origen, (int) $a['id_referencia_origen'], $idEmpresa);
                }
                $anulados++;
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        // Fase 2: regenerar. Los fallos se agrupan por motivo real, como en el Sincronizador.
        $regenerados = 0;
        $fallos      = [];
        if (!empty($docs)) {
            $svc = $this->serviceParaOrigen($origen);
            foreach (array_keys($docs) as $idDoc) {
                try {
                    $svc->procesarAsientoContablePorSincronizacion($idDoc);
                    $regenerados++;
                } catch (\Throwable $e) {
                    $motivo = trim($e->getMessage()) ?: 'Error no especificado (' . get_class($e) . ')';
                    $fallos[$motivo][] = $idDoc;
                }
            }
        }

        $mensajes = [];
        $errores  = 0;
        foreach ($fallos as $motivo => $ids) {
            $errores += count($ids);
            $mensajes[] = count($ids) . " documento(s) con error — «{$motivo}» — #" . implode(', #', array_slice($ids, 0, 5));
        }

        return [
            'procesados'  => count($docs),
            'anulados'    => $anulados,
            'regenerados' => $regenerados,
            'errores'     => $errores,
            'restantes'   => $this->repo->contarAsientosDeOrigen($idEmpresa, $origen, $fechaDesde, $fechaHasta, $idMaximo)['pendientes'],
            'mensajes'    => $mensajes,
        ];
    }

    /**
     * Anula y vuelve a generar todos los asientos de un origen (opcionalmente
     * acotado por rango de fechas), respetando los períodos contables cerrados.
     * Corre los lotes en cadena dentro de la misma petición: sirve para volúmenes
     * chicos o para invocarlo por CLI; desde la UI se prefiere lote a lote.
     */
    public function regenerarMasivo(int $idEmpresa, int $idUsuario, string $origen, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        // Solo se ofrecen (y aceptan) orígenes cuyo Service sabe regenerar el asiento.
        $this->rules->validarRegeneracion($origen, $this->repo->getOrigenesRegenerables(), $fechaDesde, $fechaHasta);

        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        $conteo   = $this->repo->contarAsientosDeOrigen($idEmpresa, $origen, $fechaDesde, $fechaHasta);
        $idMaximo = $this->repo->getMaxIdAsiento($idEmpresa);

        $anulados = 0;
        $regenerados = 0;
        $errores = 0;
        $mensajes = [];
        $vueltas = 0;

        while ($vueltas++ < self::MAX_LOTES) {
            $r = $this->regenerarLote($idEmpresa, $idUsuario, $origen, $fechaDesde, $fechaHasta,
                self::LOTE_REGENERACION, $idMaximo);
            if ($r['procesados'] === 0 && $r['anulados'] === 0) {
                break;
            }
            $anulados    += $r['anulados'];
            $regenerados += $r['regenerados'];
            $errores     += $r['errores'];
            $mensajes     = array_merge($mensajes, $r['mensajes']);
            if ($r['restantes'] === 0) {
                break;
            }
        }

        $omitidos = $conteo['cerrados'];
        $estado   = $errores > 0 ? 'parcial' : 'ok';
        $mensaje  = "Anulados: {$anulados}, Regenerados: {$regenerados}, Omitidos (período cerrado): {$omitidos}";
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
            'total_documentos'  => $conteo['pendientes'] + $omitidos,
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
            'mensajes'    => $mensajes,
        ];
    }

    /**
     * Genera los asientos de los documentos vigentes que NUNCA tuvieron uno (no se
     * regeneran los existentes). Es el mismo motor que corre al abrir Estados Financieros.
     * Se usa como paso final de la regeneración total, para que no queden documentos sin
     * contabilizar. Nunca toca asientos de tipo Diario: solo crea los que faltan.
     */
    public function generarFaltantes(int $idEmpresa, int $idUsuario): array
    {
        $svc = new SincronizadorAsientosService();
        $svc->sincronizar($idEmpresa, $idUsuario);

        return [
            'generados' => $svc->getGenerados(),
            'avisos'    => $svc->getWarnings(),
        ];
    }

    /**
     * Deja constancia de una corrida de regeneración ejecutada por lotes desde la UI
     * (los totales los acumula el controlador a lo largo de la cadena de lotes) y la
     * registra en log_sistema.
     */
    public function registrarCorridaRegeneracion(int $idEmpresa, int $idUsuario, array $totales,
        ?string $origen = null, ?string $fechaDesde = null, ?string $fechaHasta = null): int
    {
        $errores  = (int) ($totales['errores'] ?? 0);
        $estado   = $errores > 0 ? 'parcial' : 'ok';
        $mensaje  = "Anulados: " . (int) ($totales['anulados'] ?? 0)
                  . ", Regenerados: " . (int) ($totales['regenerados'] ?? 0)
                  . ", Omitidos (período cerrado): " . (int) ($totales['omitidos'] ?? 0);
        if (isset($totales['faltantes'])) {
            $mensaje .= ", Faltantes generados: " . (int) $totales['faltantes'];
        }
        if ($errores > 0) {
            $mensaje .= ", Errores: {$errores} (revise la configuración de cuentas).";
        }
        if (!empty($totales['mensaje_extra'])) {
            $mensaje .= ' ' . $totales['mensaje_extra'];
        }

        $corridaId = $this->repo->registrarCorrida([
            'id_empresa'        => $idEmpresa,
            'tipo_ambiente'     => $this->repo->getAmbienteEmpresa($idEmpresa),
            'tipo_corrida'      => 'regeneracion',
            'modulo_origen'     => $origen,   // NULL = todos los orígenes
            'fecha_desde'       => $fechaDesde,
            'fecha_hasta'       => $fechaHasta,
            'total_documentos'  => (int) ($totales['total'] ?? 0),
            'total_anulados'    => (int) ($totales['anulados'] ?? 0),
            'total_regenerados' => (int) ($totales['regenerados'] ?? 0),
            'total_omitidos'    => (int) ($totales['omitidos'] ?? 0),
            'estado'            => $estado,
            'mensaje'           => $mensaje,
        ], $idUsuario);

        $this->log->registrar($idUsuario, $idEmpresa, 'auditoria_regeneracion_total',
            $origen ?? 'todos_los_origenes', $corridaId, null, $totales);

        return $corridaId;
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
        ?string $fechaDesde = null, ?string $fechaHasta = null, string $vista = 'pendientes'): array
    {
        $this->rules->validarRango($fechaDesde, $fechaHasta);
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        return $this->repo->getListado($idEmpresa, $ambiente, $buscar, $page, $perPage,
            $ordenCol, $ordenDir, $idUsuarioFiltro, $fechaDesde, $fechaHasta, $vista);
    }

    /** Contadores para las pestañas «Por corregir» / «Corregidas». */
    public function getContadores(int $idEmpresa, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $ambiente = $this->repo->getAmbienteEmpresa($idEmpresa);
        return [
            'pendientes' => $this->repo->contarPorVista($idEmpresa, $ambiente, 'pendientes', $fechaDesde, $fechaHasta),
            'corregidas' => $this->repo->contarPorVista($idEmpresa, $ambiente, 'corregidas', $fechaDesde, $fechaHasta),
        ];
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

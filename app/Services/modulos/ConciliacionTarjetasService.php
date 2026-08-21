<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\ConciliacionTarjetasRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Rules\modulos\ConciliacionTarjetasRules;
use App\Services\LogSistemaService;

/**
 * Conciliación de Tarjetas: cruza el estado de cuenta de la procesadora contra
 * los cobros con tarjeta registrados en el sistema.
 *
 * Dos modos, según lo que tenga configurado la empresa:
 *   • OPERATIVO (siempre): saber qué se depositó, qué sigue pendiente y qué entró
 *     al banco sin documento. No necesita plan de cuentas.
 *   • CONTABLE (si hay cuentas): además genera el asiento del depósito.
 * La falta de cuentas NUNCA bloquea el cierre: se registra el motivo en
 * cabecera.asiento_omitido_motivo y el usuario lo ve en pantalla.
 */
class ConciliacionTarjetasService
{
    public function __construct(
        private ConciliacionTarjetasRepository $repository,
        private ConciliacionTarjetasRules $rules,
        private ConciliacionTarjetasImportService $importService,
        private ConciliacionTarjetasMatchService $matchService,
        private LogSistemaService $logService
    ) {
    }

    // ─── Catálogos y configuración ───────────────────────────────────────────

    public function getProcesadoras(int $idEmpresa): array
    {
        return $this->repository->getProcesadoras($idEmpresa);
    }

    public function getFormasDestino(int $idEmpresa): array
    {
        return $this->repository->getFormasDestino($idEmpresa);
    }

    public function getResumenPendientes(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getResumenPendientes($idEmpresa, $idUsuarioFiltro);
    }

    public function guardarConfig(int $idEmpresa, int $idUsuario, array $data): int
    {
        if (empty($data['id_forma_cobro'])) {
            throw new \Exception('Debe indicar a qué forma de cobro corresponde la configuración.');
        }

        $antes = $this->repository->getConfig($idEmpresa, (int) $data['id_forma_cobro']);
        $id    = $this->repository->guardarConfig($idEmpresa, $idUsuario, $data);

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            $antes ? 'ACTUALIZAR_CONFIG_CONCILIACION_TARJETAS' : 'CREAR_CONFIG_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_config',
            $id,
            $antes,
            $this->repository->getConfig($idEmpresa, (int) $data['id_forma_cobro'])
        );

        return $id;
    }

    // ─── Perfiles de estado de cuenta ────────────────────────────────────────

    public function getPerfiles(int $idEmpresa, ?int $idFormaCobro = null): array
    {
        return $this->repository->getPerfiles($idEmpresa, $idFormaCobro);
    }

    public function guardarPerfil(int $idEmpresa, int $idUsuario, array $data): int
    {
        if (is_string($data['mapeo_columnas'] ?? null)) {
            $data['mapeo_columnas'] = json_decode($data['mapeo_columnas'], true) ?: [];
        }
        $this->rules->validarPerfil($data);

        $antes = !empty($data['id']) ? $this->repository->getPerfil((int) $data['id'], $idEmpresa) : null;
        $id    = $this->repository->guardarPerfil($idEmpresa, $idUsuario, $data);

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            $antes ? 'ACTUALIZAR_PERFIL_CONCILIACION_TARJETAS' : 'CREAR_PERFIL_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_perfiles',
            $id,
            $antes,
            $this->repository->getPerfil($id, $idEmpresa)
        );

        return $id;
    }

    public function eliminarPerfil(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->getPerfil($id, $idEmpresa);
        if ($antes === null) {
            throw new \Exception('El perfil no existe.');
        }
        $this->repository->eliminarPerfil($id, $idEmpresa, $idUsuario);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ELIMINAR_PERFIL_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_perfiles', $id, $antes, null
        );
    }

    /** Asistente de perfil: muestra el archivo tal cual se lee. */
    public function previsualizarArchivo(array $archivo, string $tipoArchivo, int $filaInicio, ?array $mapeoPrueba, string $formatoFecha, string $separador): array
    {
        $ruta = $this->recibirArchivo($archivo, false);
        try {
            return $this->importService->previsualizar($ruta, $tipoArchivo, $filaInicio, 60, $mapeoPrueba, $formatoFecha, $separador);
        } finally {
            @unlink($ruta);
        }
    }

    // ─── Conciliaciones ──────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro, array $filtros): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $filtros);
    }

    /**
     * Conciliación completa para la pantalla de cruce: cabecera, líneas del estado
     * de cuenta, cobros disponibles y el diagnóstico contable.
     */
    public function getDetalle(int $id, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $cabecera = $this->repository->getCabecera($id, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }

        $lineas = $this->repository->getLineas($id, $idEmpresa);
        $cruces = $this->repository->getCruces($id, $idEmpresa);

        // Cobros del sistema que se pueden cruzar aquí: los pendientes, más los ya
        // cruzados en ESTA conciliación (para verlos marcados mientras se edita).
        $cobros = $this->repository->getCobrosPendientes(
            $idEmpresa,
            (int) $cabecera['id_forma_cobro'],
            $cabecera['fecha_desde'] ?: null,
            $cabecera['fecha_hasta'] ?: null,
            '',
            $idUsuarioFiltro,
            $id
        );

        // Índice id_linea → cobros cruzados, para pintar el emparejamiento.
        $porLinea = [];
        foreach ($cruces as $c) {
            $porLinea[(int) $c['id_linea']][] = $c;
        }
        foreach ($lineas as &$l) {
            $l['cruces_detalle'] = $porLinea[(int) $l['id']] ?? [];
        }
        unset($l);

        $totales = $this->calcularTotales($lineas, $cruces, (float) $cabecera['neto_depositado']);

        return [
            'cabecera'      => $cabecera,
            'lineas'        => $lineas,
            'cruces'        => $cruces,
            'cobros'        => $cobros,
            'totales'       => $totales,
            'contabilidad'  => $this->evaluarContabilidad($cabecera, $idEmpresa, $totales),
        ];
    }

    public function crear(int $idEmpresa, int $idUsuario, array $data): int
    {
        $procesadora = $this->buscarProcesadora($idEmpresa, (int) ($data['id_forma_cobro'] ?? 0));
        $this->rules->validarCabecera($data, $procesadora);

        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            // El secuencial se toma dentro de la transacción (§8): el candado de
            // siguienteSecuencial() solo protege si sigue viva hasta el INSERT.
            $id = $this->repository->crearCabecera($idEmpresa, $idUsuario, $data);
            $this->commitTx($db, $txPropia);
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'CREAR_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, null, $this->repository->getCabecera($id, $idEmpresa)
        );

        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, int $idUsuario, array $data): void
    {
        $antes = $this->repository->getCabecera($id, $idEmpresa);
        if ($antes === null) {
            throw new \Exception('La conciliación no existe.');
        }
        if ($antes['estado'] !== 'borrador') {
            throw new \Exception('Solo se puede modificar una conciliación en borrador.');
        }

        $procesadora = $this->buscarProcesadora($idEmpresa, (int) $antes['id_forma_cobro']);
        $this->rules->validarCabecera($data + ['id_forma_cobro' => $antes['id_forma_cobro']], $procesadora);

        $this->repository->actualizarCabecera($id, $idEmpresa, $idUsuario, $data);

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ACTUALIZAR_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, $antes, $this->repository->getCabecera($id, $idEmpresa)
        );
    }

    /**
     * Carga el estado de cuenta en una conciliación en borrador. Reemplaza las
     * líneas anteriores (y sus cruces): volver a cargar el archivo es empezar de
     * nuevo, no acumular.
     */
    public function importarEstadoCuenta(int $id, int $idEmpresa, int $idUsuario, int $idPerfil, array $archivo): array
    {
        $cabecera = $this->repository->getCabecera($id, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }
        if ($cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se puede cargar el estado de cuenta en una conciliación en borrador.');
        }

        $perfil = $this->repository->getPerfil($idPerfil, $idEmpresa);
        if ($perfil === null) {
            throw new \Exception('El perfil de lectura seleccionado no existe.');
        }

        $ruta      = $this->recibirArchivo($archivo, true);
        $resultado = $this->importService->parsear($perfil, $ruta);

        if (empty($resultado['lineas'])) {
            @unlink($ruta);
            throw new \Exception(
                'No se pudo leer ninguna línea del archivo con el perfil "' . $perfil['nombre_perfil'] . '". '
                . 'Revise la fila de inicio y el mapeo de columnas del perfil.'
            );
        }

        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            $this->repository->eliminarLineasDeCabecera($id, $idUsuario);
            $insertadas = $this->repository->insertarLineas($id, $idEmpresa, $idUsuario, $resultado['lineas']);

            $this->repository->actualizarCabecera($id, $idEmpresa, $idUsuario, [
                'id_forma_cobro_destino' => $cabecera['id_forma_cobro_destino'],
                'fecha_desde'            => $cabecera['fecha_desde'],
                'fecha_hasta'            => $cabecera['fecha_hasta'],
                'fecha_conciliacion'     => $cabecera['fecha_conciliacion'],
                'neto_depositado'        => $cabecera['neto_depositado'],
                'observaciones'          => $cabecera['observaciones'],
            ]);
            $this->guardarDatosArchivo($id, $idEmpresa, $idPerfil, $archivo, $ruta, (string) $perfil['tipo_archivo']);

            $this->commitTx($db, $txPropia);
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'IMPORTAR_ESTADO_CUENTA_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, null,
            ['archivo' => $archivo['name'] ?? '', 'lineas' => $insertadas, 'perfil' => $perfil['nombre_perfil']]
        );

        return [
            'insertadas'   => $insertadas,
            'total_leidas' => $resultado['total_leidas'],
            'descartadas'  => $resultado['descartadas'] ?? 0,
        ];
    }

    /** Agrega una línea a mano (procesadoras que no entregan archivo). */
    public function agregarLinea(int $idCabecera, int $idEmpresa, int $idUsuario, array $data): int
    {
        $cabecera = $this->repository->getCabecera($idCabecera, $idEmpresa);
        if ($cabecera === null || $cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se pueden agregar líneas a una conciliación en borrador.');
        }
        $this->rules->validarLinea($data);

        $this->repository->insertarLineas($idCabecera, $idEmpresa, $idUsuario, [[
            'fecha'            => $data['fecha_movimiento'],
            'tipo_linea'       => $data['tipo_linea'] ?? 'transaccion',
            'autorizacion'     => $data['autorizacion'] ?? null,
            'referencia'       => $data['referencia'] ?? null,
            'descripcion'      => $data['descripcion'] ?? null,
            'monto_bruto'      => (float) $data['monto_bruto'],
            'comision'         => (float) ($data['comision'] ?? 0),
            'iva_comision'     => (float) ($data['iva_comision'] ?? 0),
            'retencion_ir'     => (float) ($data['retencion_ir'] ?? 0),
            'retencion_iva'    => (float) ($data['retencion_iva'] ?? 0),
            'otros_descuentos' => (float) ($data['otros_descuentos'] ?? 0),
            'monto_neto'       => (float) ($data['monto_neto'] ?? 0),
        ]], 'manual');

        $lineas = $this->repository->getLineas($idCabecera, $idEmpresa);
        return (int) (end($lineas)['id'] ?? 0);
    }

    public function guardarLinea(int $idLinea, int $idEmpresa, int $idUsuario, array $data): void
    {
        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea === null) {
            throw new \Exception('La línea no existe.');
        }
        $cabecera = $this->repository->getCabecera((int) $linea['id_cabecera'], $idEmpresa);
        if ($cabecera === null || $cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se pueden editar líneas de una conciliación en borrador.');
        }

        $this->rules->validarLinea($data);
        $this->repository->actualizarLinea($idLinea, $idEmpresa, $idUsuario, $data);
    }

    public function eliminarLinea(int $idLinea, int $idEmpresa, int $idUsuario): void
    {
        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea === null) {
            throw new \Exception('La línea no existe.');
        }
        $cabecera = $this->repository->getCabecera((int) $linea['id_cabecera'], $idEmpresa);
        if ($cabecera === null || $cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se pueden eliminar líneas de una conciliación en borrador.');
        }
        $this->repository->eliminarLinea($idLinea, $idEmpresa, $idUsuario);
    }

    /** Marca una línea como "entró plata sin documento" (o la devuelve a pendiente). */
    public function marcarLineaSinCobro(int $idLinea, int $idEmpresa, int $idUsuario, bool $sinCobro): void
    {
        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea === null) {
            throw new \Exception('La línea no existe.');
        }
        $cabecera = $this->repository->getCabecera((int) $linea['id_cabecera'], $idEmpresa);
        if ($cabecera === null || $cabecera['estado'] !== 'borrador') {
            throw new \Exception('Solo se pueden marcar líneas de una conciliación en borrador.');
        }
        if ($sinCobro && (int) ($linea['cruces'] ?? 0) > 0) {
            throw new \Exception('Esa línea ya está cruzada con un cobro. Deshaga el cruce antes de marcarla como sin documento.');
        }

        $this->repository->actualizarEstadoLinea($idLinea, $idEmpresa, $idUsuario, $sinCobro ? 'sin_cobro' : 'pendiente');
    }

    // ─── Cruce ───────────────────────────────────────────────────────────────

    /** Sugerencias automáticas para las líneas que aún no tienen cruce. */
    public function sugerirCruces(int $idCabecera, int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $detalle = $this->getDetalle($idCabecera, $idEmpresa, $idUsuarioFiltro);

        $sinCruzar = array_values(array_filter(
            $detalle['lineas'],
            static fn($l) => (int) $l['cruces'] === 0 && $l['estado'] !== 'sin_cobro'
        ));
        $libres = array_values(array_filter(
            $detalle['cobros'],
            static fn($c) => empty($c['id_cruce'])
        ));

        return $this->matchService->sugerir($sinCruzar, $libres);
    }

    /**
     * Aplica sugerencias (o un cruce manual). Todo dentro de una transacción y con
     * el candado de la procesadora tomado ANTES de releer los pendientes (§8), para
     * que dos usuarios conciliando a la vez no crucen el mismo cobro.
     *
     * @param array $pares [['id_linea' => X, 'id_ingreso_pago' => Y, 'origen' => 'auto'|'manual', ...], ...]
     * @return array{creados:int, omitidos:array}
     */
    public function cruzar(int $idCabecera, int $idEmpresa, int $idUsuario, array $pares, ?int $idUsuarioFiltro = null): array
    {
        $cabecera = $this->repository->getCabecera($idCabecera, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }

        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            $this->repository->lockConciliacion($idEmpresa, (int) $cabecera['id_forma_cobro']);

            // Se releen los pendientes YA con el candado tomado.
            $cobros = [];
            foreach ($this->repository->getCobrosPendientes(
                $idEmpresa, (int) $cabecera['id_forma_cobro'], null, null, '', $idUsuarioFiltro, $idCabecera
            ) as $c) {
                $cobros[(int) $c['id_ingreso_pago']] = $c;
            }

            $creados  = 0;
            $omitidos = [];

            foreach ($pares as $par) {
                $idLinea = (int) ($par['id_linea'] ?? 0);
                $idPago  = (int) ($par['id_ingreso_pago'] ?? 0);

                $linea = $this->repository->getLinea($idLinea, $idEmpresa);
                $cobro = $cobros[$idPago] ?? null;
                $ya    = $this->repository->estaCruzado($idPago);

                try {
                    $this->rules->validarCruce($cabecera, $linea, $cobro, $ya);
                } catch (\Throwable $e) {
                    $omitidos[] = ['id_linea' => $idLinea, 'id_ingreso_pago' => $idPago, 'motivo' => $e->getMessage()];
                    continue;
                }

                $this->repository->crearCruce($idEmpresa, $idUsuario, [
                    'id_cabecera'     => $idCabecera,
                    'id_linea'        => $idLinea,
                    'id_ingreso_pago' => $idPago,
                    'id_ingreso'      => (int) $cobro['id_ingreso'],
                    'monto_cruzado'   => (float) $cobro['monto'],
                    'origen'          => $par['origen'] ?? 'manual',
                    'score'           => $par['score'] ?? null,
                    'criterio'        => $par['criterio'] ?? null,
                ]);
                $this->repository->actualizarEstadoLinea($idLinea, $idEmpresa, $idUsuario, 'cruzada');
                unset($cobros[$idPago]);
                $creados++;
            }

            $this->commitTx($db, $txPropia);
            return ['creados' => $creados, 'omitidos' => $omitidos];
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }
    }

    /** Deshace un emparejamiento: el cobro vuelve a la lista de pendientes. */
    public function descruzar(int $idCruce, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            $st = $db->prepare(
                "SELECT cr.*, c.estado AS estado_cabecera
                   FROM conciliacion_tarjetas_cruces cr
                   INNER JOIN conciliacion_tarjetas_cabecera c ON c.id = cr.id_cabecera
                  WHERE cr.id = :id AND cr.id_empresa = :e AND cr.eliminado = FALSE"
            );
            $st->execute([':id' => $idCruce, ':e' => $idEmpresa]);
            $cruce = $st->fetch(\PDO::FETCH_ASSOC);

            if (!$cruce) {
                throw new \Exception('El cruce no existe.');
            }
            if ($cruce['estado_cabecera'] !== 'borrador') {
                throw new \Exception('Solo se puede deshacer un cruce en una conciliación en borrador.');
            }

            $this->repository->eliminarCruce($idCruce, $idEmpresa, $idUsuario);

            // Si la línea se queda sin cruces, vuelve a "pendiente".
            $linea = $this->repository->getLinea((int) $cruce['id_linea'], $idEmpresa);
            if ($linea !== null && (int) $linea['cruces'] === 0) {
                $this->repository->actualizarEstadoLinea((int) $cruce['id_linea'], $idEmpresa, $idUsuario, 'pendiente');
            }

            $this->commitTx($db, $txPropia);
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }
    }

    // ─── Cierre y anulación ──────────────────────────────────────────────────

    /**
     * Cierra la conciliación: fija los totales, deja los cobros conciliados y, si
     * se puede, genera el asiento. Devuelve qué pasó con la contabilidad para que
     * la pantalla lo informe.
     */
    public function cerrar(int $id, int $idEmpresa, int $idUsuario): array
    {
        $cabecera = $this->repository->getCabecera($id, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }

        $lineas  = $this->repository->getLineas($id, $idEmpresa);
        $cruces  = $this->repository->getCruces($id, $idEmpresa);
        $totales = $this->calcularTotales($lineas, $cruces, (float) $cabecera['neto_depositado']);

        $config     = $this->repository->getConfig($idEmpresa, (int) $cabecera['id_forma_cobro']);
        $tolerancia = (float) ($config['tolerancia_diferencia'] ?? 0.05);

        $this->rules->validarCierre($cabecera, count($cruces), (float) $totales['diferencia'], $tolerancia);

        $diagnostico = $this->evaluarContabilidad($cabecera, $idEmpresa, $totales);

        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            $idAsiento = null;
            $motivo    = $diagnostico['puede'] ? null : $diagnostico['motivo'];

            if ($diagnostico['puede']) {
                try {
                    $idAsiento = $this->generarAsiento($cabecera, $totales, $diagnostico['cuentas'], $idEmpresa, $idUsuario);
                } catch (\Throwable $e) {
                    // Lo contable no bloquea lo operativo: se cierra igual y se
                    // deja constancia del motivo (mismo criterio que IngresoService).
                    $motivo = 'No se pudo generar el asiento: ' . $e->getMessage();
                    \App\Services\ErrorLogService::registrar($e, ['modulo' => 'conciliacion-tarjetas', 'id' => $id]);
                }
            }

            $this->repository->actualizarEstadoYTotales($id, $idEmpresa, $idUsuario, 'cerrada', $totales, $idAsiento, $motivo);
            $this->commitTx($db, $txPropia);
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'CERRAR_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, $cabecera, $this->repository->getCabecera($id, $idEmpresa)
        );

        return [
            'id_asiento' => $idAsiento ?? null,
            'motivo'     => $motivo,
            'totales'    => $totales,
        ];
    }

    /** Anula: revierte el asiento y libera los cobros, que vuelven a pendientes. */
    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getCabecera($id, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }
        $this->rules->validarAnulacion($cabecera);

        $db = Database::getConnection();
        $txPropia = $this->abrirTx($db);
        try {
            if (!empty($cabecera['id_asiento_contable'])) {
                $this->asientoService()->anular((int) $cabecera['id_asiento_contable'], $idEmpresa, $idUsuario);
            }

            $this->repository->eliminarCrucesDeCabecera($id, $idUsuario);
            $this->repository->actualizarEstadoYTotales(
                $id, $idEmpresa, $idUsuario, 'anulada',
                [
                    'total_lineas'       => (int) $cabecera['total_lineas'],
                    'total_bruto_estado' => (float) $cabecera['total_bruto_estado'],
                ],
                null,
                'Conciliación anulada: los cobros vuelven a estar pendientes.'
            );

            $this->commitTx($db, $txPropia);
        } catch (\Throwable $e) {
            $this->rollbackTx($db, $txPropia);
            throw $e;
        }

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ANULAR_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, $cabecera, $this->repository->getCabecera($id, $idEmpresa)
        );
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cabecera = $this->repository->getCabecera($id, $idEmpresa);
        if ($cabecera === null) {
            throw new \Exception('La conciliación no existe.');
        }
        $this->rules->validarEliminacion($cabecera);

        $this->repository->eliminarCabecera($id, $idEmpresa, $idUsuario);

        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'ELIMINAR_CONCILIACION_TARJETAS',
            'conciliacion_tarjetas_cabecera', $id, $cabecera, null
        );
    }

    // ─── Totales ─────────────────────────────────────────────────────────────

    /**
     * El asiento se arma SOLO sobre lo cruzado: una línea sin cobro en el sistema
     * se reporta como diferencia, no se contabiliza (el usuario debe registrar el
     * documento que falta y volver a conciliar).
     *
     * @return array<string, float|int>
     */
    public function calcularTotales(array $lineas, array $cruces, float $netoDepositado): array
    {
        $lineasCruzadas = array_filter($lineas, static fn($l) => (int) ($l['cruces'] ?? 0) > 0);

        $sumar = static function (array $filas, string $campo): float {
            $t = 0.0;
            foreach ($filas as $f) {
                $t += (float) ($f[$campo] ?? 0);
            }
            return round($t, 2);
        };

        $brutoCruzado = $sumar($cruces, 'monto_cruzado');
        $comision     = $sumar($lineasCruzadas, 'comision');
        $iva          = $sumar($lineasCruzadas, 'iva_comision');
        $retIr        = $sumar($lineasCruzadas, 'retencion_ir');
        $retIva       = $sumar($lineasCruzadas, 'retencion_iva');
        $otros        = $sumar($lineasCruzadas, 'otros_descuentos');

        $neto = round($brutoCruzado - $comision - $iva - $retIr - $retIva - $otros, 2);

        // Si el usuario no declaró cuánto le depositaron, se asume el neto calculado
        // (no hay diferencia que reportar).
        $depositado = $netoDepositado > 0 ? round($netoDepositado, 2) : $neto;

        return [
            'total_lineas'        => count($lineas),
            'total_bruto_estado'  => $sumar($lineas, 'monto_bruto'),
            'total_bruto_cruzado' => $brutoCruzado,
            'total_comision'      => $comision,
            'total_iva_comision'  => $iva,
            'total_retencion_ir'  => $retIr,
            'total_retencion_iva' => $retIva,
            'total_otros'         => $otros,
            'total_neto'          => $neto,
            'neto_depositado'     => $depositado,
            'diferencia'          => round($depositado - $neto, 2),
            'lineas_cruzadas'     => count($lineasCruzadas),
            'lineas_sin_cobro'    => count(array_filter($lineas, static fn($l) => $l['estado'] === 'sin_cobro')),
            'lineas_pendientes'   => count(array_filter($lineas, static fn($l) => $l['estado'] === 'pendiente')),
        ];
    }

    // ─── Contabilidad (opcional) ─────────────────────────────────────────────

    /**
     * ¿Se puede generar el asiento? La contabilidad es opcional en el sistema, así
     * que esto NO bloquea: informa. El motivo se muestra en pantalla y queda
     * guardado en la cabecera al cerrar.
     *
     * @return array{puede: bool, motivo: ?string, cuentas: array}
     */
    public function evaluarContabilidad(array $cabecera, int $idEmpresa, array $totales): array
    {
        $sin = static fn(string $motivo) => ['puede' => false, 'motivo' => $motivo, 'cuentas' => []];

        $idCuentaPuente = (int) ($cabecera['procesadora_id_cuenta'] ?? 0);
        if ($idCuentaPuente <= 0) {
            return $sin(sprintf(
                'La forma de cobro "%s" no tiene cuenta contable asignada. Asígnele una cuenta puente '
                . '(por ejemplo "Tarjetas de crédito por liquidar") en Formas de Cobro/Pago para contabilizar el depósito.',
                $cabecera['procesadora_nombre'] ?? ''
            ));
        }

        $idCuentaBanco = (int) ($cabecera['destino_id_cuenta'] ?? 0);
        if (empty($cabecera['id_forma_cobro_destino'])) {
            return $sin('Falta indicar a qué forma de cobro (banco) ingresó el dinero.');
        }
        if ($idCuentaBanco <= 0) {
            return $sin(sprintf(
                'La forma de cobro destino "%s" no tiene cuenta contable asignada.',
                $cabecera['destino_nombre'] ?? ''
            ));
        }

        // La cuenta de la tarjeta debe ser una cuenta PUENTE, no la del banco: si el
        // cobro ya debitó el banco, volver a debitarlo aquí duplicaría el ingreso.
        if ($idCuentaPuente === $idCuentaBanco) {
            return $sin(sprintf(
                'La forma de cobro "%s" apunta a la misma cuenta contable que el banco destino. '
                . 'El cobro ya debitó esa cuenta, así que contabilizar el depósito la duplicaría. '
                . 'Asigne a la tarjeta una cuenta puente distinta (por ejemplo "Tarjetas de crédito por liquidar").',
                $cabecera['procesadora_nombre'] ?? ''
            ));
        }
        if (!empty($cabecera['procesadora_id_banco'])) {
            return $sin(sprintf(
                'La forma de cobro "%s" está configurada como cuenta bancaria. Para conciliar tarjetas su cuenta '
                . 'debe ser una cuenta puente (por ejemplo "Tarjetas de crédito por liquidar"), no la del banco.',
                $cabecera['procesadora_nombre'] ?? ''
            ));
        }

        $config = $this->repository->getConfig($idEmpresa, (int) $cabecera['id_forma_cobro']) ?? [];

        // Cada descuento con valor necesita su cuenta; si no hay descuentos, no hace falta.
        $requeridas = [
            'total_comision'      => ['campo' => 'id_cuenta_comision',       'nombre' => 'comisión de la procesadora'],
            'total_iva_comision'  => ['campo' => 'id_cuenta_iva_comision',   'nombre' => 'IVA de la comisión'],
            'total_retencion_ir'  => ['campo' => 'id_cuenta_retencion_ir',   'nombre' => 'retención de renta'],
            'total_retencion_iva' => ['campo' => 'id_cuenta_retencion_iva',  'nombre' => 'retención de IVA'],
        ];

        $cuentas = ['puente' => $idCuentaPuente, 'banco' => $idCuentaBanco];
        foreach ($requeridas as $totalKey => $def) {
            if (round((float) ($totales[$totalKey] ?? 0), 2) <= 0) {
                continue;
            }
            $idCuenta = (int) ($config[$def['campo']] ?? 0);
            if ($idCuenta <= 0) {
                return $sin(sprintf(
                    'Falta configurar la cuenta contable para %s. Se configura en el botón "Configuración contable" de este módulo.',
                    $def['nombre']
                ));
            }
            $cuentas[$def['campo']] = $idCuenta;
        }

        // "Otros descuentos" no tiene cuenta propia: se contabiliza junto con la comisión.
        if (round((float) ($totales['total_otros'] ?? 0), 2) > 0 && empty($cuentas['id_cuenta_comision'])) {
            $idCuenta = (int) ($config['id_cuenta_comision'] ?? 0);
            if ($idCuenta <= 0) {
                return $sin('Hay otros descuentos en el estado de cuenta pero falta configurar la cuenta de comisión, que es donde se registran.');
            }
            $cuentas['id_cuenta_comision'] = $idCuenta;
        }

        return ['puede' => true, 'motivo' => null, 'cuentas' => $cuentas];
    }

    /**
     * Asiento del depósito:
     *   Debe  Banco ................ neto depositado
     *   Debe  Comisión + otros ..... gasto de la procesadora
     *   Debe  IVA de la comisión ... crédito tributario
     *   Debe  Retención IR / IVA ... lo que retuvo la procesadora
     *      Haber  Cuenta puente .... bruto conciliado
     */
    private function generarAsiento(array $cabecera, array $totales, array $cuentas, int $idEmpresa, int $idUsuario): int
    {
        $mapaCuentas = $this->repository->getDatosCuentas(array_values($cuentas));
        $datosCuenta = static function (int $id) use ($mapaCuentas): array {
            return $mapaCuentas[$id] ?? ['codigo' => '', 'nombre' => ''];
        };

        $detalles = [];
        $agregar = static function (int $idCuenta, float $debe, float $haber, string $referencia) use (&$detalles, $datosCuenta): void {
            if (round($debe, 2) <= 0 && round($haber, 2) <= 0) {
                return;
            }
            $c = $datosCuenta($idCuenta);
            $detalles[] = [
                'id_cuenta_contable' => $idCuenta,
                'cuenta_codigo'      => $c['codigo'],
                'cuenta_nombre'      => $c['nombre'],
                'debe'               => round($debe, 2),
                'haber'              => round($haber, 2),
                'referencia_detalle' => $referencia,
            ];
        };

        $numero = $cabecera['numero'];
        $proc   = $cabecera['procesadora_nombre'] ?? 'tarjeta';

        $agregar((int) $cuentas['banco'], (float) $totales['neto_depositado'], 0.0, 'Depósito ' . $proc . ' — ' . $numero);

        $comisionTotal = round((float) $totales['total_comision'] + (float) $totales['total_otros'], 2);
        if ($comisionTotal > 0) {
            $agregar((int) $cuentas['id_cuenta_comision'], $comisionTotal, 0.0, 'Comisión ' . $proc);
        }
        if ((float) $totales['total_iva_comision'] > 0) {
            $agregar((int) $cuentas['id_cuenta_iva_comision'], (float) $totales['total_iva_comision'], 0.0, 'IVA comisión ' . $proc);
        }
        if ((float) $totales['total_retencion_ir'] > 0) {
            $agregar((int) $cuentas['id_cuenta_retencion_ir'], (float) $totales['total_retencion_ir'], 0.0, 'Retención renta ' . $proc);
        }
        if ((float) $totales['total_retencion_iva'] > 0) {
            $agregar((int) $cuentas['id_cuenta_retencion_iva'], (float) $totales['total_retencion_iva'], 0.0, 'Retención IVA ' . $proc);
        }

        $agregar((int) $cuentas['puente'], 0.0, (float) $totales['total_bruto_cruzado'], 'Cobros con tarjeta liquidados — ' . $proc);

        $this->ajustarCentavos($detalles);

        $cabeceraAsiento = [
            'id'                   => null,
            'fecha_asiento'        => $cabecera['fecha_conciliacion'],
            'tipo_comprobante'     => 'ingresos',
            'numero_comprobante'   => $numero,
            'concepto'             => 'Conciliación de tarjetas ' . $numero . ' — ' . $proc,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'conciliacion_tarjetas',
            'id_referencia_origen' => (int) $cabecera['id'],
            'observaciones'        => $cabecera['observaciones'] ?? null,
        ];

        foreach ($detalles as &$d) {
            $d['documento_referencia'] = 'Conciliación ' . $numero;
        }
        unset($d);

        return $this->asientoService()->guardarAsiento($cabeceraAsiento, $detalles, $idEmpresa, $idUsuario);
    }

    /**
     * Absorbe el descuadre de centavos en el lado corto, en la línea de mayor valor
     * (mismo criterio que el resto de asientos del sistema). El descuadre real ya
     * quedó acotado por la tolerancia validada en el cierre.
     */
    private function ajustarCentavos(array &$detalles): void
    {
        $debe  = round(array_sum(array_column($detalles, 'debe')), 2);
        $haber = round(array_sum(array_column($detalles, 'haber')), 2);
        $dif   = round($debe - $haber, 2);

        if (abs($dif) < 0.01) {
            return;
        }

        $lado  = $dif > 0 ? 'haber' : 'debe';  // se suma al lado corto
        $ajuste = abs($dif);

        $keyMax = null;
        $max = -1.0;
        foreach ($detalles as $k => $d) {
            if ((float) $d[$lado] > $max) {
                $max = (float) $d[$lado];
                $keyMax = $k;
            }
        }
        if ($keyMax !== null) {
            $detalles[$keyMax][$lado] = round((float) $detalles[$keyMax][$lado] + $ajuste, 2);
        }
    }

    // ─── Transacciones ───────────────────────────────────────────────────────
    // Patrón "transacción gestionada" (igual que AsientoContableService): si el
    // llamador ya abrió una, se trabaja dentro de ella y el commit/rollback queda
    // en sus manos. Sin esto, invocar este Service desde otro que ya está en
    // transacción reventaría con "There is already an active transaction".

    private function abrirTx(\PDO $db): bool
    {
        if ($db->inTransaction()) {
            return false;
        }
        $db->beginTransaction();
        return true;
    }

    private function commitTx(\PDO $db, bool $propia): void
    {
        if ($propia && $db->inTransaction()) {
            $db->commit();
        }
    }

    private function rollbackTx(\PDO $db, bool $propia): void
    {
        if ($propia && $db->inTransaction()) {
            $db->rollBack();
        }
    }

    private function asientoService(): AsientoContableService
    {
        return new AsientoContableService(
            new AsientoContableRepository(),
            new AsientoContableRules(),
            $this->logService
        );
    }

    // ─── Utilidades ──────────────────────────────────────────────────────────

    private function buscarProcesadora(int $idEmpresa, int $idFormaCobro): ?array
    {
        foreach ($this->repository->getProcesadoras($idEmpresa) as $p) {
            if ((int) $p['id'] === $idFormaCobro) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Valida y mueve el archivo subido. Si $conservar es false devuelve una ruta
     * temporal que el llamador debe borrar.
     */
    private function recibirArchivo(array $archivo, bool $conservar): string
    {
        if (empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            throw new \Exception('Debe seleccionar el archivo del estado de cuenta.');
        }
        if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \Exception('Hubo un problema al subir el archivo.');
        }

        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx', 'csv', 'pdf'], true)) {
            throw new \Exception('El estado de cuenta debe ser un archivo Excel, CSV o PDF.');
        }
        if ((int) ($archivo['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new \Exception('El archivo supera el tamaño máximo permitido (20 MB).');
        }

        $destinoDir = $conservar
            ? MVC_ROOT . '/storage/conciliacion_tarjetas'
            : sys_get_temp_dir();

        if ($conservar && !is_dir($destinoDir)) {
            @mkdir($destinoDir, 0775, true);
        }

        $destino = rtrim($destinoDir, '/\\') . DIRECTORY_SEPARATOR
                 . uniqid('ct_', true) . '.' . $extension;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new \Exception('No se pudo guardar el archivo en el servidor.');
        }

        return $destino;
    }

    /** Guarda en la cabecera de qué archivo y perfil salieron las líneas. */
    private function guardarDatosArchivo(int $id, int $idEmpresa, int $idPerfil, array $archivo, string $ruta, string $tipoArchivo): void
    {
        $st = Database::getConnection()->prepare(
            "UPDATE conciliacion_tarjetas_cabecera
                SET id_perfil = :p, nombre_archivo = :n, ruta_archivo = :r, tipo_archivo = :t,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = :id AND id_empresa = :e"
        );
        $st->execute([
            ':p'  => $idPerfil,
            ':n'  => mb_substr((string) ($archivo['name'] ?? ''), 0, 255),
            ':r'  => mb_substr($ruta, 0, 255),
            ':t'  => $tipoArchivo,
            ':id' => $id,
            ':e'  => $idEmpresa,
        ]);
    }
}

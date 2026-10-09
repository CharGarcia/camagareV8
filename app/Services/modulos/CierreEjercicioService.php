<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\CierreEjercicioSql;
use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\CierreEjercicioRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Rules\modulos\CierreEjercicioRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;
use Exception;

/**
 * Cierre del Ejercicio: pasa de un año contable al siguiente con dos asientos.
 *
 *  1. CIERRE (31-12-AAAA, tipo 'cierre'): salda cada cuenta de resultados (clases 4, 5, 6 y la 7
 *     de los migrados) con su movimiento del año y manda la diferencia a Utilidad o Pérdida del
 *     Ejercicio.
 *  2. APERTURA (01-01-AAAA+1, tipo 'apertura'): arrastra el saldo de cada cuenta de balance y
 *     pasa a Resultados Acumulados lo que quedó en Utilidad/Pérdida del Ejercicio (el resultado
 *     del año y, si los hubiera, los de años anteriores que nunca se cerraron).
 *
 * Después bloquea el año en Períodos Contables. Todo en una transacción; se puede revertir (el
 * último año cerrado) y queda el historial.
 *
 * Los reportes por rango ignoran el asiento de cierre (ver CierreEjercicioSql), así que el año
 * cerrado se sigue viendo igual; el año siguiente arranca con la apertura.
 */
class CierreEjercicioService
{
    private const MODULO_GUARDADO = 'cierre_ejercicio';

    /** Clases de cuentas de resultados (las salda el cierre; la apertura no las arrastra). */
    private const CLASES_RESULTADO = ['4', '5', '6', '7'];

    public function __construct(
        private CierreEjercicioRepository $repo,
        private CierreEjercicioRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CierreEjercicioRepository(), new CierreEjercicioRules(), new LogSistemaService());
    }

    public function tablaDisponible(): bool
    {
        return $this->repo->tablaDisponible();
    }

    // ── Consulta ────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        $res = $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idUsuarioFiltro);
        foreach ($res['rows'] as &$r) {
            $r['cambios_posteriores'] = $r['estado'] === 'vigente'
                ? $this->repo->contarCambiosPosteriores($idEmpresa, $r['saldos_desde'] ?: null, (string) $r['fecha_cierre'], (string) $r['created_at'])
                : 0;
        }
        unset($r);
        return $res;
    }

    public function getDetalle(int $id, int $idEmpresa): array
    {
        $c = $this->repo->getDetalle($id, $idEmpresa);
        if (!$c) {
            throw new Exception('El cierre no existe.');
        }
        $c['lineas_cierre']   = $this->repo->getLineasAsiento((int) $c['id_asiento_cierre'], $idEmpresa);
        $c['lineas_apertura'] = $this->repo->getLineasAsiento((int) $c['id_asiento_apertura'], $idEmpresa);
        $c['cambios_posteriores'] = $c['estado'] === 'vigente'
            ? $this->repo->contarCambiosPosteriores($idEmpresa, $c['saldos_desde'] ?: null, (string) $c['fecha_cierre'], (string) $c['created_at'])
            : 0;
        $c['es_ultimo'] = $c['estado'] === 'vigente' && $this->repo->getUltimoAnioVigente($idEmpresa) === (int) $c['anio'];
        return $c;
    }

    /** Lo que necesita el formulario de un cierre nuevo: años, año sugerido y "saldos desde". */
    public function getContextoNuevo(int $idEmpresa): array
    {
        $ultimo = $this->repo->getUltimoAnioVigente($idEmpresa);
        $anios  = $this->repo->getAniosConAsientos($idEmpresa);
        $hoy    = date('Y-m-d');
        $anios  = array_values(array_filter($anios, static fn(int $a) => "{$a}-12-31" <= $hoy && ($ultimo === null || $a > $ultimo)));
        // Sin cierres previos se sugiere el último año terminado: los anteriores que nunca se
        // cerraron entran solos a la apertura como "resultados anteriores".
        $sugerido = $ultimo !== null ? $ultimo + 1 : (int) date('Y') - 1;
        if (!in_array($sugerido, $anios, true) && "{$sugerido}-12-31" <= $hoy) {
            $anios[] = $sugerido;
        }
        rsort($anios);
        return [
            'anios'        => $anios,
            'sugerido'     => $sugerido,
            'ultimo'       => $ultimo,
            'ambiente'     => $this->repo->getTipoAmbienteEmpresa($idEmpresa),
            'cuentas'      => $this->repo->getCuentasConfiguradas($idEmpresa),
        ];
    }

    /**
     * Desde dónde se acumulan los saldos de balance para la apertura. Si el año anterior se
     * cerró con este módulo, es obligatoriamente su apertura; si no, la primera apertura del
     * último grupo de aperturas hasta el 31-12 del año (manuales o migradas; ver
     * CierreEjercicioRepository::getPuntoPartidaApertura()); si nunca hubo una, todo el
     * histórico (null).
     *
     * @return array{defecto:?string, minimo:?string, fijo:bool}
     */
    public function resolverSaldosDesde(int $idEmpresa, int $anio): array
    {
        $ultimo = $this->repo->getUltimoAnioVigente($idEmpresa);
        $minimo = ($ultimo !== null && $ultimo < $anio) ? ($ultimo + 1) . '-01-01' : null;
        if ($minimo !== null && $ultimo === $anio - 1) {
            return ['defecto' => $minimo, 'minimo' => $minimo, 'fijo' => true];
        }
        $defecto = $this->repo->getPuntoPartidaApertura($idEmpresa, "{$anio}-12-31");
        if ($minimo !== null && ($defecto === null || $defecto < $minimo)) {
            $defecto = $minimo;
        }
        return ['defecto' => $defecto, 'minimo' => $minimo, 'fijo' => false];
    }

    /**
     * Arma los dos asientos sin grabar nada (vista previa). Mismo cálculo que generar().
     *
     * @param string|null $saldosDesde 'auto' = el sugerido; '' o null = todo el histórico.
     */
    public function calcular(int $idEmpresa, int $anio, ?string $saldosDesde = 'auto'): array
    {
        $hoy = date('Y-m-d');
        $this->rules->validarAnio($anio, $hoy);
        $this->rules->validarAmbiente($this->repo->getTipoAmbienteEmpresa($idEmpresa));
        $this->rules->validarOrden($anio, $this->repo->getVigente($idEmpresa, $anio), $this->repo->getUltimoAnioVigente($idEmpresa));

        $fi = "{$anio}-01-01";
        $fc = "{$anio}-12-31";
        $fa = ($anio + 1) . '-01-01';

        $desdeInfo = $this->resolverSaldosDesde($idEmpresa, $anio);
        if ($saldosDesde === 'auto' || $desdeInfo['fijo']) {
            $desde = $desdeInfo['defecto'];
        } else {
            $desde = ($saldosDesde === null || trim($saldosDesde) === '') ? null : trim($saldosDesde);
        }
        $this->rules->validarSaldosDesde($desde, $fc, $desdeInfo['minimo']);

        $cuentas = $this->repo->getCuentasConfiguradas($idEmpresa);
        $this->rules->validarCuentasDistintas($cuentas);
        $ctaUtilidad  = $cuentas['utilidad'] ?? $cuentas['perdida'];
        $ctaPerdida   = $cuentas['perdida'] ?? $cuentas['utilidad'];
        $ctaUtilAcum  = $cuentas['utilidades_acumuladas'] ?? $cuentas['perdidas_acumuladas'];
        $ctaPerdAcum  = $cuentas['perdidas_acumuladas'] ?? $cuentas['utilidades_acumuladas'];
        $idsEjercicio = array_filter([$cuentas['utilidad']['id'] ?? null, $cuentas['perdida']['id'] ?? null]);

        $errores = [];
        $avisos  = [];
        $eliminadas = []; // cuentas borradas del plan que aún tienen saldo: ningún asiento puede usarlas

        // 0. Una apertura del año siguiente que no generó este módulo se sumaría a la nuestra y
        //    duplicaría los saldos iniciales de ese año en todos los reportes.
        $ajenas = $this->repo->getAperturasAjenasEntre($idEmpresa, $fc, ($anio + 1) . '-12-31');
        if ($ajenas) {
            $lista = array_map(static fn($a) => trim(($a['numero_comprobante'] ?: '#' . $a['id']) . ' del '
                . date('d-m-Y', strtotime((string) $a['fecha_asiento'])) . ($a['estado'] === 'borrador' ? ' (borrador)' : '')), $ajenas);
            $errores[] = 'Ya existe un asiento de apertura del año ' . ($anio + 1) . ': ' . implode(', ', $lista)
                . '. El cierre genera su propia apertura y los saldos iniciales quedarían duplicados. Anúlelo en Asientos Contables y vuelva a calcular.';
        }

        // El año se cierra desde el punto de partida si cae dentro de él (la empresa empezó a
        // mitad de año con una apertura): lo anterior lo resume esa apertura, igual que en la
        // apertura que se genera, y cierre y apertura quedan hablando del mismo tramo.
        $inicioCierre = ($desde !== null && $desde > $fi) ? $desde : $fi;
        if ($inicioCierre > $fi) {
            $avisos[] = 'Los saldos parten de la apertura del ' . date('d-m-Y', strtotime($inicioCierre))
                . ', dentro del año: el cierre y la apertura solo toman los movimientos desde esa fecha. Lo anterior del año queda resumido en esa apertura.';
        }

        // 1. Asiento de cierre: cuentas de resultados con su movimiento del año.
        $lineasCierre = [];
        $resultado = 0.0; // + utilidad / − pérdida
        foreach ($this->repo->getSaldosPorCuenta($idEmpresa, $inicioCierre, $fc) as $c) {
            if (!$this->esResultado($c['codigo'])) {
                continue;
            }
            if ($c['eliminada']) {
                $eliminadas[$c['id']] = $c;
            }
            $s = round($c['saldo'], 2);
            $lineasCierre[] = $this->linea($c, $s < 0 ? -$s : 0.0, $s > 0 ? $s : 0.0, "Cierre del ejercicio {$anio}");
            $resultado = round($resultado - $s, 2);
        }
        if (abs($resultado) >= 0.005) {
            $cta = $resultado > 0 ? $ctaUtilidad : $ctaPerdida;
            if ($cta === null) {
                $errores[] = 'Configure la cuenta de Utilidad y/o Pérdida del Ejercicio en Configuración Contable → Cierre del Ejercicio.';
            } else {
                $lineasCierre[] = $this->linea($cta, $resultado < 0 ? -$resultado : 0.0, $resultado > 0 ? $resultado : 0.0,
                    ($resultado > 0 ? 'Utilidad' : 'Pérdida') . " del ejercicio {$anio}");
            }
        }

        // 2. Asiento de apertura: saldos de balance acumulados hasta el 31-12, ya con el cierre.
        $saldos = $this->repo->getSaldosPorCuenta($idEmpresa, $desde, $fc);
        $lineasApertura = [];
        $activos = $pasivos = $patrimonio = 0.0;
        $arrastrado = 0.0;      // Σ(debe − haber) de lo que pasa tal cual
        $aTrasladar = 0.0;      // resultado total que va a acumulados (+ utilidad)
        foreach ($saldos as $c) {
            $s = round($c['saldo'], 2);
            if ($this->esResultado($c['codigo']) || in_array($c['id'], $idsEjercicio, true)) {
                $aTrasladar = round($aTrasladar - $s, 2);
                continue;
            }
            if ($c['eliminada']) {
                $eliminadas[$c['id']] = $c;
            }
            $lineasApertura[] = $this->linea($c, $s > 0 ? $s : 0.0, $s < 0 ? -$s : 0.0, "Saldo al 31-12-{$anio}");
            $arrastrado = round($arrastrado + $s, 2);
            match ($c['codigo'][0] ?? '') {
                '1' => $activos = round($activos + $s, 2),
                '2' => $pasivos = round($pasivos - $s, 2),
                '3' => $patrimonio = round($patrimonio - $s, 2),
                default => null,
            };
        }
        $descuadre = round($arrastrado - $aTrasladar, 2);
        if (abs($descuadre) >= 0.01) {
            $errores[] = 'La contabilidad hasta el 31-12-' . $anio . ' no cuadra por ' . number_format($descuadre, 2, ',', '.')
                . ': hay asientos descuadrados. Revíselos en Auditoría Contable o en el Balance de Comprobación antes de cerrar.';
        }
        if (abs($aTrasladar) >= 0.005) {
            $cta = $aTrasladar > 0 ? $ctaUtilAcum : $ctaPerdAcum;
            if ($cta === null) {
                $errores[] = 'Configure la cuenta de Utilidades y/o Pérdidas Acumuladas en Configuración Contable → Cierre del Ejercicio.';
            } else {
                $lineasApertura[] = $this->linea($cta, $aTrasladar < 0 ? -$aTrasladar : 0.0, $aTrasladar > 0 ? $aTrasladar : 0.0,
                    'Resultado acumulado al 31-12-' . $anio);
            }
        }
        $patrimonio = round($patrimonio + $aTrasladar, 2);

        // Resultado REAL del año: lo que salda nuestro cierre más lo que ya sacaron de 4/5/6 los
        // cierres registrados por fuera (manuales, del sistema anterior). Sin esto, un año que
        // el contador cerró a mano aparecería con utilidad 0 y toda su utilidad como "anterior".
        $resultadoCierre = $resultado;
        $cierresAjenos = $this->repo->getCierresAjenos($idEmpresa, $inicioCierre, $fc);
        foreach ($cierresAjenos as $ca) {
            $resultado = round($resultado + (float) $ca['utilidad_movida'], 2);
        }
        if ($cierresAjenos) {
            $lista = array_map(static fn($a) => trim(($a['numero_comprobante'] ?: '#' . $a['id']) . ' del '
                . date('d-m-Y', strtotime((string) $a['fecha_asiento']))), $cierresAjenos);
            $avisos[] = 'El año ya tiene asientos que cerraron resultados: ' . implode(', ', $lista) . '. '
                . (abs($resultadoCierre) >= 0.005
                    ? 'El asiento de cierre solo salda lo que quedó (' . number_format($resultadoCierre, 2, ',', '.') . ').'
                    : 'Las cuentas de resultados ya están en cero, así que no hace falta asiento de cierre.')
                . ' La utilidad o pérdida del año que se muestra incluye lo que movieron esos asientos. Como los reportes los cuentan, el Estado de Resultados de ' . $anio . ' sale en cero.';
        }
        $resultadoAnterior = round($aTrasladar - $resultado, 2);

        if ($eliminadas) {
            $lista = array_map(static fn($c) => $c['codigo'] . ' ' . $c['nombre'] . ' (' . number_format($c['saldo'], 2, ',', '.') . ')', $eliminadas);
            $errores[] = 'Estas cuentas están eliminadas del Plan de Cuentas pero tienen saldo: ' . implode('; ', $lista)
                . '. Reactívelas en el Plan de Cuentas, o traslade su saldo a otra cuenta con un asiento, antes de cerrar.';
        }
        if (empty($lineasCierre) && empty($lineasApertura)) {
            $errores[] = "No hay movimientos contabilizados de producción hasta el 31-12-{$anio}.";
        }
        if (abs($resultadoAnterior) >= 0.01) {
            $avisos[] = 'Además del resultado del año, se trasladan ' . number_format($resultadoAnterior, 2, ',', '.')
                . ' de resultados anteriores que no se habían cerrado (o que estaban en la cuenta de Utilidad/Pérdida del Ejercicio). Si no corresponde, revise la fecha "Saldos desde".';
        }
        $borradores = $this->repo->contarBorradores($idEmpresa, $desde ?? '1900-01-01', $fc);
        if ($borradores > 0) {
            $avisos[] = "Hay {$borradores} asiento(s) en borrador hasta el 31-12-{$anio}: no entran en el cierre.";
        }
        if ($desde === null) {
            $avisos[] = 'Los saldos se acumulan desde el primer asiento de la empresa (no hay una apertura anterior). Si ya registró a mano una apertura con los saldos de un año que también está en el sistema, indique su fecha en "Saldos desde" para no contarlo dos veces.';
        }

        return [
            'anio'               => $anio,
            'fecha_cierre'       => $fc,
            'fecha_apertura'     => $fa,
            'saldos_desde'       => $desde,
            'saldos_desde_fijo'  => $desdeInfo['fijo'],
            'saldos_desde_min'   => $desdeInfo['minimo'],
            'primera_fecha'      => $this->repo->getPrimeraFechaAsiento($idEmpresa),
            'cierre'             => $this->conTotales($lineasCierre),
            'apertura'           => $this->conTotales($lineasApertura),
            'resultado'          => $resultado,
            'resultado_anterior' => $resultadoAnterior,
            'total_activos'      => $activos,
            'total_pasivos'      => $pasivos,
            'total_patrimonio'   => $patrimonio,
            'errores'            => $errores,
            'avisos'             => $avisos,
        ];
    }

    // ── Escritura ───────────────────────────────────────────────────────────

    /**
     * Genera el cierre: asientos, registro y bloqueo del año, en una transacción.
     * @return array{id:int, existente:bool, anio:int}
     */
    public function generar(int $idEmpresa, int $idUsuario, int $anio, ?string $saldosDesde, string $observaciones, ?string $token): array
    {
        if (!$this->repo->tablaDisponible()) {
            throw new Exception('Falta crear la tabla del módulo (database/2026-10-09_cierre_ejercicio.sql).');
        }
        $guardado = new GuardadoUnicoService();

        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);

            $previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO);
            if ($previo !== null) {
                $this->repo->rollBack();
                return ['id' => $previo['id_registro'], 'existente' => true, 'anio' => $anio];
            }

            // Bajo el candado: el cálculo y sus validaciones se repiten con los datos de ahora.
            $plan = $this->calcular($idEmpresa, $anio, $saldosDesde);
            if (!empty($plan['errores'])) {
                throw new Exception(implode(' ', $plan['errores']));
            }

            $id = $this->repo->insertar([
                'id_empresa'          => $idEmpresa,
                'anio'                => $anio,
                'fecha_cierre'        => $plan['fecha_cierre'],
                'fecha_apertura'      => $plan['fecha_apertura'],
                'saldos_desde'        => $plan['saldos_desde'],
                'id_asiento_cierre'   => null,
                'id_asiento_apertura' => null,
                'resultado'           => $plan['resultado'],
                'resultado_anterior'  => $plan['resultado_anterior'],
                'total_activos'       => $plan['total_activos'],
                'total_pasivos'       => $plan['total_pasivos'],
                'total_patrimonio'    => $plan['total_patrimonio'],
                'periodos'            => [],
                'observaciones'       => trim($observaciones) !== '' ? mb_substr(trim($observaciones), 0, 2000) : null,
                'created_by'          => $idUsuario,
            ]);

            $asientos = $this->asientoService();
            $idCierre = null;
            if (!empty($plan['cierre']['lineas'])) {
                $idCierre = $asientos->guardarAsiento([
                    'fecha_asiento'        => $plan['fecha_cierre'],
                    'tipo_comprobante'     => 'cierre',
                    'concepto'             => "Cierre del ejercicio {$anio}",
                    'estado'               => 'contabilizado',
                    'modulo_origen'        => CierreEjercicioSql::ORIGEN_CIERRE,
                    'id_referencia_origen' => $id,
                ], $plan['cierre']['lineas'], $idEmpresa, $idUsuario);
            }
            $idApertura = null;
            if (!empty($plan['apertura']['lineas'])) {
                $idApertura = $asientos->guardarAsiento([
                    'fecha_asiento'        => $plan['fecha_apertura'],
                    'tipo_comprobante'     => 'apertura',
                    'concepto'             => 'Apertura del ejercicio ' . ($anio + 1) . " (saldos al 31-12-{$anio})",
                    'estado'               => 'contabilizado',
                    'modulo_origen'        => CierreEjercicioSql::ORIGEN_APERTURA,
                    'id_referencia_origen' => $id,
                ], $plan['apertura']['lineas'], $idEmpresa, $idUsuario);
            }
            $this->repo->setAsientos($id, $idEmpresa, $idCierre, $idApertura);

            $periodos = $this->bloquearAnio($idEmpresa, $anio, $idUsuario);
            $this->repo->setPeriodos($id, $idEmpresa, $periodos);

            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'cierre_ejercicio', $id, null, [
                'anio'                => $anio,
                'saldos_desde'        => $plan['saldos_desde'],
                'id_asiento_cierre'   => $idCierre,
                'id_asiento_apertura' => $idApertura,
                'resultado'           => $plan['resultado'],
                'resultado_anterior'  => $plan['resultado_anterior'],
                'periodos'            => $periodos,
            ]);

            $guardado->registrar($token, $idEmpresa, self::MODULO_GUARDADO, $id, (string) $anio, $idUsuario);
            $this->repo->commit();
            return ['id' => $id, 'existente' => false, 'anio' => $anio];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /**
     * Revierte el último cierre vigente: anula sus dos asientos, devuelve los períodos al
     * estado anterior y deja el registro como 'revertido' (historial).
     */
    public function revertir(int $id, int $idEmpresa, int $idUsuario, string $motivo): void
    {
        $this->rules->validarMotivo($motivo);

        $this->repo->beginTransaction();
        try {
            $this->repo->lockEmpresa($idEmpresa);
            $c = $this->repo->getDetalle($id, $idEmpresa);
            if (!$c) {
                throw new Exception('El cierre no existe.');
            }
            if ($c['estado'] !== 'vigente') {
                throw new Exception('Este cierre ya fue revertido.');
            }
            $ultimo = $this->repo->getUltimoAnioVigente($idEmpresa);
            if ($ultimo !== null && $ultimo > (int) $c['anio']) {
                throw new Exception("Primero revierta el cierre del ejercicio {$ultimo}: su apertura parte de los saldos de este año.");
            }

            $asientos = $this->asientoService();
            foreach ([(int) $c['id_asiento_apertura'], (int) $c['id_asiento_cierre']] as $idAsiento) {
                if ($idAsiento > 0) {
                    $asientos->anularDeDocumento($idAsiento, $idEmpresa, $idUsuario, 'del cierre del ejercicio');
                }
            }

            $periodos = json_decode((string) ($c['periodos'] ?? '[]'), true) ?: [];
            foreach (($periodos['cerrados'] ?? []) as $idPeriodo) {
                $p = $this->repo->getPeriodo((int) $idPeriodo, $idEmpresa);
                if ($p && (int) $p['status'] === 0) {
                    $this->repo->setStatusPeriodo((int) $idPeriodo, $idEmpresa, 1, $idUsuario);
                }
            }
            if (!empty($periodos['creado'])) {
                $this->repo->eliminarPeriodo((int) $periodos['creado'], $idEmpresa, $idUsuario);
            }

            $this->repo->marcarRevertido($id, $idEmpresa, $idUsuario, trim($motivo));
            $this->log->registrar($idUsuario, $idEmpresa, 'revertir', 'cierre_ejercicio', $id,
                ['estado' => 'vigente', 'id_asiento_cierre' => $c['id_asiento_cierre'], 'id_asiento_apertura' => $c['id_asiento_apertura']],
                ['estado' => 'revertido', 'motivo' => trim($motivo), 'periodos_devueltos' => $periodos]
            );
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /**
     * Cierra los períodos abiertos que caen dentro del año y, si ningún período cerrado cubre el
     * año completo, crea uno. Devuelve lo hecho para poder deshacerlo al revertir.
     */
    private function bloquearAnio(int $idEmpresa, int $anio, int $idUsuario): array
    {
        $fi = "{$anio}-01-01";
        $fc = "{$anio}-12-31";
        $hecho = ['cerrados' => [], 'creado' => null];
        foreach ($this->repo->getPeriodosDentro($idEmpresa, $fi, $fc) as $p) {
            if ((int) $p['status'] === 1) {
                $this->repo->setStatusPeriodo((int) $p['id'], $idEmpresa, 0, $idUsuario);
                $hecho['cerrados'][] = (int) $p['id'];
            }
        }
        if (!$this->repo->existePeriodoCerradoQueCubre($idEmpresa, $fi, $fc)) {
            $hecho['creado'] = $this->repo->crearPeriodoCerrado($idEmpresa, "Ejercicio {$anio} (cierre)", $fi, $fc, $idUsuario);
        }
        return $hecho;
    }

    private function asientoService(): AsientoContableService
    {
        $s = new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->log);
        $s->desdeCierreEjercicio = true;
        return $s;
    }

    private function esResultado(string $codigo): bool
    {
        return in_array($codigo[0] ?? '', self::CLASES_RESULTADO, true);
    }

    private function linea(array $cuenta, float $debe, float $haber, string $referencia): array
    {
        return [
            'id_cuenta_contable' => (int) $cuenta['id'],
            'codigo'             => (string) $cuenta['codigo'],
            'nombre'             => (string) $cuenta['nombre'],
            'debe'               => round($debe, 2),
            'haber'              => round($haber, 2),
            'referencia_detalle' => $referencia,
        ];
    }

    private function conTotales(array $lineas): array
    {
        $d = $h = 0.0;
        foreach ($lineas as $l) {
            $d += $l['debe'];
            $h += $l['haber'];
        }
        return ['lineas' => $lineas, 'total_debe' => round($d, 2), 'total_haber' => round($h, 2)];
    }
}

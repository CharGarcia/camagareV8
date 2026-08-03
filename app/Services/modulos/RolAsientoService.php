<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\RolPagoRepository;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;
use App\models\CatalogoNovedades;
use App\models\CatalogoRol;
use Exception;

/**
 * Contabiliza un rol MENSUAL en base DEVENGADO: agrega los conceptos de todos
 * los empleados (gastos, IESS, provisiones, anticipos) y el líquido a pagar
 * como pasivo (Sueldos por Pagar) — no como salida de Bancos, porque el rol se
 * contabiliza al calcularse, no al pagarse. El pago real (Generar egresos de
 * nómina) cancela después esa misma cuenta contra Bancos.
 *
 * Idempotente: si ya existe un asiento para este rol (mismo modulo_origen +
 * id_referencia_origen), lo actualiza en el mismo lugar en vez de duplicar —
 * así que se puede volver a llamar cada vez que el rol cambia (ver
 * SincronizadorAsientosService, que detecta cuándo el rol quedó desactualizado
 * respecto a su último asiento).
 *
 * Mapea cada concepto a su cuenta configurada (asientos_programados /
 * asientos_tipo, tipo 'nomina') y persiste con AsientoContableService. Cuadra
 * por construcción.
 */
class RolAsientoService
{
    private RolPagoRepository $repo;
    private LogSistemaService $log;

    /** Mapa provisión → [código gasto (debe), código por pagar (haber)]. */
    private const PROV_MAP = [
        'Décimo Tercero'    => ['GASTODECIMOTERCERONOMINA', 'DECIMOTERCEROPORPAGARNOMINA'],
        'Décimo Cuarto'     => ['GASTODECIMOCUARTONOMINA', 'DECIMOCUARTOPORPAGARNOMINA'],
        'Vacaciones'        => ['GASTOVACACIONESNOMINA', 'VACACIONESPORPAGARNOMINA'],
        'Fondos de Reserva' => ['GASTOFONDOSRESERVANOMINA', 'FONDOSRESERVAPORPAGARNOMINA'],
        'Desahucio'         => ['GASTODESAHUCIONOMINA', 'DESAHUCIOPORPAGARNOMINA'],
    ];

    public function __construct(RolPagoRepository $repo, LogSistemaService $log)
    {
        $this->repo = $repo;
        $this->log = $log;
    }

    /**
     * Punto de entrada para SincronizadorAsientosService: recibe solo el id (ya
     * filtrado por id_empresa en la consulta del sincronizador, igual que
     * FacturaVentaService y análogos). Usa como "usuario" quien pagó/actualizó
     * por última vez la corrida — es lo más cercano a quién debería figurar como
     * responsable de la contabilización automática.
     */
    public function procesarAsientoContablePorSincronizacion(int $idRol): void
    {
        $cab = $this->repo->findCabeceraPorId($idRol);
        if (!$cab) return;
        $idUsuario = (int) ($cab['updated_by'] ?? 0) ?: (int) ($cab['created_by'] ?? 0);
        if ($idUsuario <= 0) return;
        $this->contabilizar($idRol, (int) $cab['id_empresa'], $idUsuario);
    }

    public function contabilizar(int $idRol, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findCabecera($idRol, $idEmpresa);
        if (!$cab) throw new Exception('Corrida no encontrada.');
        if ($cab['tipo_rol'] !== 'MENSUAL') {
            throw new Exception('Solo el rol mensual se contabiliza (las quincenas/semanas se netean en el mensual).');
        }
        if (!in_array($cab['estado'], ['generado', 'pagado', 'contabilizado'], true)) {
            throw new Exception('Genere el rol antes de contabilizarlo.');
        }

        $detalle = $this->repo->getDetalleCompleto($idRol, $idEmpresa);
        if (empty($detalle)) throw new Exception('El rol no tiene empleados.');
        $salario = $this->repo->getSalario((int) $cab['periodo_anio']);
        $prov = new RolProvisionService();

        // ── Agregación ───────────────────────────────────────────────────────
        // Cuentas generales por concepto (código => regla con id_asiento_tipo, id_cuenta).
        $progRepo = new AsientoProgramadoRepository();
        $ctas = [];
        foreach ($progRepo->getReglasGeneralesPorConcepto($idEmpresa, 'nomina') as $r) {
            $ctas[$r['codigo']] = $r;
        }

        $mes = CatalogoNovedades::MESES[(int) $cab['periodo_mes']] ?? $cab['periodo_mes'];
        $ref = 'Rol ' . $mes . ' ' . $cab['periodo_anio'];
        $faltan = [];

        // Resuelve la cuenta de un concepto: override por empleado > cuenta general.
        $cuentaDe = function (string $codigo, array $empOv) use ($ctas, &$faltan): ?int {
            $regla = $ctas[$codigo] ?? null;
            if (!$regla) { $faltan[$codigo] = $codigo; return null; }
            $at = (int) $regla['id_asiento_tipo'];
            $idCuenta = ((int) ($empOv[$at] ?? 0)) ?: ((int) ($regla['id_cuenta'] ?? 0));
            if ($idCuenta <= 0) { $faltan[$codigo] = $regla['concepto']; return null; }
            return $idCuenta;
        };

        // ── Modo del rol: GENERAL (un solo asiento combinado) vs POR EMPLEADO (uno
        // independiente por cada uno) ────────────────────────────────────────────
        // Se detecta automáticamente: basta que UN empleado del rol tenga una cuenta propia
        // configurada (override en Configuración Contable, tipo_referencia='empleado') para que
        // TODO el rol pase a modo por-empleado — así se puede reportar el gasto por centro de
        // costo/departamento sin mezclarlo con quienes siguen la cuenta general.
        $overridesPorEmpleado = [];
        $modoPorEmpleado = false;
        foreach ($detalle as $lin) {
            $idEmp = (int) $lin['id_empleado'];
            $ov = $progRepo->getReglasEmpleado($idEmpresa, $idEmp);
            $overridesPorEmpleado[$idEmp] = $ov;
            if (!empty($ov)) {
                $modoPorEmpleado = true;
            }
        }

        // Líneas agrupadas por empleado (id_empleado => líneas[]). Construirlas así de una vez
        // sirve para ambos modos: en modo por empleado, cada grupo es su propio asiento; en modo
        // general se funden todas en un solo asiento SUMANDO por cuenta (agruparPorCuenta) — no
        // se aplanan sueltas, porque eso dejaría una línea por empleado por concepto en vez de
        // una sola línea con el total por cuenta.
        $lineasPorEmpleado = [];
        $push = function (string $codigo, string $lado, float $valor, string $concepto, array $empOv, int $idEmp, string $nombre) use (&$lineasPorEmpleado, $cuentaDe, $ref): void {
            $valor = round($valor, 2);
            if ($valor <= 0) return;
            $idCuenta = $cuentaDe($codigo, $empOv);
            if (!$idCuenta) return;
            $lineasPorEmpleado[$idEmp][] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $lado === 'debe' ? $valor : 0,
                'haber'                => $lado === 'haber' ? $valor : 0,
                'concepto'             => $concepto,
                'referencia_detalle'   => $concepto . ' - ' . $nombre,
                'documento_referencia' => $ref,
                'id_entidad'           => $idEmp,
                'tipo_entidad'         => 'empleado',
            ];
        };

        $nombresPorEmpleado = [];
        foreach ($detalle as $lin) {
            $idEmp  = (int) $lin['id_empleado'];
            $nombre = (string) $lin['nombres_apellidos'];
            $nombresPorEmpleado[$idEmp] = $nombre;
            $empOv  = $overridesPorEmpleado[$idEmp];
            $provis = $prov->calcularProvisiones($lin, $salario);

            $push('GASTOSUELDOSNOMINA', 'debe', (float) $lin['total_ingresos'], 'Gasto Sueldos y Salarios', $empOv, $idEmp, $nombre);
            $push('GASTOAPORTEPATRONALNOMINA', 'debe', (float) $lin['aporte_patronal'], 'Gasto Aporte Patronal IESS', $empOv, $idEmp, $nombre);
            foreach ($provis as $p) {
                if (!empty($p['incluir']) && $p['valor'] > 0) {
                    $push(self::PROV_MAP[$p['concepto']][0] ?? '', 'debe', (float) $p['valor'], 'Gasto ' . $p['concepto'], $empOv, $idEmp, $nombre);
                }
            }

            $push('IESSPORPAGARNOMINA', 'haber', (float) $lin['aporte_iess'] + (float) $lin['aporte_patronal'], 'IESS por Pagar', $empOv, $idEmp, $nombre);
            foreach ($provis as $p) {
                if (!empty($p['incluir']) && $p['valor'] > 0) {
                    $push(self::PROV_MAP[$p['concepto']][1] ?? '', 'haber', (float) $p['valor'], $p['concepto'] . ' por Pagar', $empOv, $idEmp, $nombre);
                }
            }
            $push('ANTICIPOSDESCUENTOSNOMINA', 'haber', (float) $lin['total_egresos'] - (float) $lin['aporte_iess'], 'Anticipos y Descuentos', $empOv, $idEmp, $nombre);
            // Base DEVENGADO: el rol se contabiliza al calcularse, no al pagarse — el
            // líquido a pagar todavía no salió de Bancos, así que se reconoce como
            // pasivo (Sueldos por Pagar), no como salida de caja. El egreso que
            // efectivamente paga el rol (Generar egresos de nómina) cancela esta misma
            // cuenta contra Bancos — ver AsientoBuilderService::generarAsientoEgreso().
            $push('SUELDOSPORPAGARNOMINA', 'haber', (float) $lin['neto'], 'Sueldos por Pagar', $empOv, $idEmp, $nombre);
        }

        if (!empty($faltan)) {
            throw new Exception('Configure las cuentas de nómina en Configuración Contable: ' . implode(', ', array_unique($faltan)) . '.');
        }
        if (empty($lineasPorEmpleado)) throw new Exception('No hay valores para contabilizar.');

        // ── Persistir ────────────────────────────────────────────────────────
        $asientoRepo    = new AsientoContableRepository();
        $asientoService = new AsientoContableService($asientoRepo, new AsientoContableRules(), $this->log);
        $fecha          = $cab['fecha_pago'] ?: date('Y-m-t', mktime(0, 0, 0, (int) $cab['periodo_mes'], 1, (int) $cab['periodo_anio']));
        $tituloBase     = CatalogoRol::nombreTipo((string) $cab['tipo_rol']) . ' - ' . $ref;
        $idsExistentes  = $asientoService->getIdsAsientosPorOrigen('nomina', $idRol, $idEmpresa);

        if (!$modoPorEmpleado) {
            // Un solo asiento combinado, SUMANDO los rubros de todos los empleados y agrupando
            // en la cuenta que corresponda — una línea por cuenta (con el total), no una línea
            // por empleado por concepto. Si quedaron varios asientos activos de una corrida "por
            // empleado" anterior (el modo cambió porque se quitaron los overrides), se anulan
            // todos menos el que se reutiliza para no duplicar.
            $lineas   = $this->agruparPorCuenta(array_merge(...array_values($lineasPorEmpleado)));
            $previoId = count($idsExistentes) === 1 ? $idsExistentes[0] : null;
            foreach ($idsExistentes as $idExistente) {
                if ($idExistente !== $previoId) {
                    $asientoService->anular($idExistente, $idEmpresa, $idUsuario);
                }
            }

            $cabeceraData = [
                'id'                   => $previoId,
                'fecha_asiento'        => $fecha,
                'tipo_comprobante'     => 'nomina',
                'numero_comprobante'   => '',
                'concepto'             => $tituloBase,
                'estado'               => 'contabilizado',
                'modulo_origen'        => 'nomina',
                'id_referencia_origen' => $idRol,
                'observaciones'        => null,
            ];
            $idAsiento = $asientoService->guardarAsiento($cabeceraData, $lineas, $idEmpresa, $idUsuario);

            $this->repo->setIdAsiento($idRol, $idAsiento);
            $this->repo->setEstado($idRol, $idEmpresa, 'contabilizado', $idUsuario);
            $this->sincronizarUpdatedAt($idRol, $asientoService, [$idAsiento]);
            $this->log->registrar($idUsuario, $idEmpresa, 'CONTABILIZAR', 'rol_cabecera', $idRol, $cab, ['id_asiento' => $idAsiento, 'modo' => 'general']);

            return ['id_asiento' => $idAsiento, 'lineas' => count($lineas), 'modo' => 'general'];
        }

        // Modo por empleado: un asiento independiente por cada uno. Se empareja cada asiento ya
        // existente con "su" empleado (vía id_entidad de sus propias líneas) para actualizarlo en
        // el mismo lugar en vez de duplicar; lo que sobre (empleado ya no está en la corrida, o
        // era el asiento combinado de una corrida "general" anterior) se anula.
        $mapaEmpleadoAsientoPrevio = [];
        foreach ($idsExistentes as $idExistente) {
            $entidades = $asientoService->getEntidadesDeAsiento($idExistente, 'empleado');
            if (count($entidades) === 1) {
                $mapaEmpleadoAsientoPrevio[$entidades[0]] = $idExistente;
            } else {
                $asientoService->anular($idExistente, $idEmpresa, $idUsuario);
            }
        }

        $idsGenerados = [];
        foreach ($lineasPorEmpleado as $idEmp => $lineasEmp) {
            $previoId = $mapaEmpleadoAsientoPrevio[$idEmp] ?? null;
            unset($mapaEmpleadoAsientoPrevio[$idEmp]);

            $cabeceraData = [
                'id'                   => $previoId,
                'fecha_asiento'        => $fecha,
                'tipo_comprobante'     => 'nomina',
                'numero_comprobante'   => '',
                'concepto'             => $tituloBase . ' - ' . ($nombresPorEmpleado[$idEmp] ?? ''),
                'estado'               => 'contabilizado',
                'modulo_origen'        => 'nomina',
                'id_referencia_origen' => $idRol,
                'observaciones'        => null,
            ];
            $idsGenerados[] = $asientoService->guardarAsiento($cabeceraData, $lineasEmp, $idEmpresa, $idUsuario);
        }

        // Empleados que ya no están en esta corrida (removidos del rol entre una contabilización
        // y otra): su asiento anterior queda sin reclamar, se anula.
        foreach ($mapaEmpleadoAsientoPrevio as $idSobrante) {
            $asientoService->anular($idSobrante, $idEmpresa, $idUsuario);
        }

        // rol_cabecera.id_asiento guarda uno cualquiera de los generados: solo se usa como
        // marcador booleano de "¿este rol ya tiene asiento(s)?" (ver anularAsiento() y
        // SincronizadorAsientosService, que detectan la lista real por origen, no por esta
        // columna).
        $this->repo->setIdAsiento($idRol, $idsGenerados[0] ?? null);
        $this->repo->setEstado($idRol, $idEmpresa, 'contabilizado', $idUsuario);
        $this->sincronizarUpdatedAt($idRol, $asientoService, $idsGenerados);
        $this->log->registrar($idUsuario, $idEmpresa, 'CONTABILIZAR', 'rol_cabecera', $idRol, $cab, ['ids_asiento' => $idsGenerados, 'modo' => 'por_empleado']);

        return [
            'id_asiento'  => $idsGenerados[0] ?? null,
            'ids_asiento' => $idsGenerados,
            'lineas'      => array_sum(array_map('count', $lineasPorEmpleado)),
            'modo'        => 'por_empleado',
        ];
    }

    /**
     * Modo GENERAL: funde las líneas de todos los empleados en una sola por cuenta contable,
     * sumando debe/haber — así el asiento combinado no repite una fila por cada empleado que
     * comparte la misma cuenta (p. ej. "Gasto Sueldos" de 30 empleados = una sola línea con el
     * total, no 30). Si más de un concepto cayera en la misma cuenta (poco común, pero posible
     * si el contador configuró la misma cuenta para dos rubros), sus nombres se combinan en la
     * referencia. No aplica en modo por-empleado: ahí cada asiento ya es de un solo empleado.
     *
     * @param array $lineas Líneas individuales (una por empleado y concepto), con clave 'concepto'.
     */
    private function agruparPorCuenta(array $lineas): array
    {
        // Se agrupa por cuenta Y lado (no solo cuenta): si por configuración una cuenta de
        // Gasto y una de Pasivo coincidieran en el mismo id_cuenta_contable, deben seguir siendo
        // dos líneas separadas (una de Debe, otra de Haber) — mezclarlas violaría la regla de
        // que ninguna línea puede tener Debe y Haber a la vez.
        $grupos = [];
        foreach ($lineas as $l) {
            $cta  = (int) $l['id_cuenta_contable'];
            $lado = ((float) $l['debe']) > 0 ? 'debe' : 'haber';
            $clave = $cta . '|' . $lado;
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'id_cuenta_contable'   => $cta,
                    'debe'                 => 0.0,
                    'haber'                => 0.0,
                    'conceptos'            => [],
                    'documento_referencia' => $l['documento_referencia'],
                ];
            }
            $grupos[$clave]['debe']  += (float) $l['debe'];
            $grupos[$clave]['haber'] += (float) $l['haber'];
            $grupos[$clave]['conceptos'][$l['concepto']] = true;
        }

        $resultado = [];
        foreach ($grupos as $g) {
            $resultado[] = [
                'id_cuenta_contable'   => $g['id_cuenta_contable'],
                'debe'                 => round($g['debe'], 2),
                'haber'                => round($g['haber'], 2),
                'referencia_detalle'   => implode(' + ', array_keys($g['conceptos'])),
                'documento_referencia' => $g['documento_referencia'],
            ];
        }
        return $resultado;
    }

    /**
     * setIdAsiento()/setEstado() tocan rol_cabecera.updated_at con CURRENT_TIMESTAMP DESPUÉS de
     * que el/los asiento(s) ya se guardaron — eso deja al rol "más nuevo" que su propia
     * contabilización recién hecha, y SincronizadorAsientosService (que compara
     * asiento.updated_at < rol.updated_at para decidir "pendiente") lo vuelve a marcar
     * pendiente de inmediato, en un ciclo que nunca se resuelve. Se alinea el updated_at del rol
     * con el más reciente de sus asientos para que quede exactamente al día.
     */
    private function sincronizarUpdatedAt(int $idRol, AsientoContableService $asientoService, array $idsAsiento): void
    {
        $fecha = $asientoService->getMaxUpdatedAt($idsAsiento);
        if ($fecha !== null) {
            $this->repo->forzarUpdatedAt($idRol, $fecha);
        }
    }

    /**
     * Asiento contable de UN empleado con las cuentas reales resueltas
     * (override por empleado > cuenta general). Formato para mostrar en la UI.
     */
    public function asientoEmpleado(array $lin, int $idEmpresa, array $salario): array
    {
        $progRepo = new AsientoProgramadoRepository();
        $ctas = [];
        foreach ($progRepo->getReglasGeneralesPorConcepto($idEmpresa, 'nomina') as $r) $ctas[$r['codigo']] = $r;
        $empOv = $progRepo->getReglasEmpleadoConCuenta($idEmpresa, (int) $lin['id_empleado']);

        $cuenta = function (string $codigo) use ($ctas, $empOv): array {
            $regla = $ctas[$codigo] ?? null;
            $at = $regla ? (int) $regla['id_asiento_tipo'] : 0;
            if ($at && isset($empOv[$at])) return ['codigo' => $empOv[$at]['codigo'], 'nombre' => $empOv[$at]['nombre']];
            if ($regla && !empty($regla['id_cuenta'])) return ['codigo' => $regla['cuenta_codigo'], 'nombre' => $regla['cuenta_nombre']];
            return ['codigo' => '', 'nombre' => '(cuenta no configurada)'];
        };
        $mk = function (string $codigo, string $concepto, float $valor) use ($cuenta): ?array {
            $valor = round($valor, 2);
            if ($valor <= 0) return null;
            $c = $cuenta($codigo);
            return ['cuenta_codigo' => $c['codigo'], 'cuenta_nombre' => $c['nombre'], 'concepto' => $concepto, 'valor' => $valor];
        };

        $prov = (new RolProvisionService())->calcularProvisiones($lin, $salario);
        $debe = [];
        $haber = [];

        $debe[] = $mk('GASTOSUELDOSNOMINA', 'Gasto Sueldos y Salarios', (float) $lin['total_ingresos']);
        $debe[] = $mk('GASTOAPORTEPATRONALNOMINA', 'Gasto Aporte Patronal IESS', (float) $lin['aporte_patronal']);
        foreach ($prov as $p) if (!empty($p['incluir']) && $p['valor'] > 0) $debe[] = $mk(self::PROV_MAP[$p['concepto']][0] ?? '', 'Gasto ' . $p['concepto'], (float) $p['valor']);

        $haber[] = $mk('IESSPORPAGARNOMINA', 'IESS por Pagar', (float) $lin['aporte_iess'] + (float) $lin['aporte_patronal']);
        foreach ($prov as $p) if (!empty($p['incluir']) && $p['valor'] > 0) $haber[] = $mk(self::PROV_MAP[$p['concepto']][1] ?? '', $p['concepto'] . ' por Pagar', (float) $p['valor']);
        $haber[] = $mk('ANTICIPOSDESCUENTOSNOMINA', 'Anticipos y Descuentos', (float) $lin['total_egresos'] - (float) $lin['aporte_iess']);
        $haber[] = $mk('SUELDOSPORPAGARNOMINA', 'Sueldos por Pagar', (float) $lin['neto']);

        $debe = array_values(array_filter($debe));
        $haber = array_values(array_filter($haber));
        $td = array_sum(array_map(fn($x) => $x['valor'], $debe));
        $th = array_sum(array_map(fn($x) => $x['valor'], $haber));

        return ['debe' => $debe, 'haber' => $haber, 'total_debe' => round($td, 2), 'total_haber' => round($th, 2), 'cuadrado' => abs($td - $th) < 0.01];
    }

    /**
     * Anula TODOS los asientos asociados al rol (si tiene) y lo desvincula. Puede haber más de
     * uno si el rol se contabilizó en modo "por empleado" — por eso se busca por origen
     * (modulo_origen='nomina' + id_referencia_origen) en vez de confiar solo en el único id que
     * guarda rol_cabecera.id_asiento.
     */
    public function anularAsiento(array $cab, int $idEmpresa, int $idUsuario): void
    {
        $asientoService = new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->log);
        $ids = $asientoService->getIdsAsientosPorOrigen('nomina', (int) $cab['id'], $idEmpresa);
        if (empty($ids)) return;
        foreach ($ids as $idAsiento) {
            try {
                $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            } catch (\Throwable $e) {
                // Un período cerrado debe abortar la operación completa: si se tragara, el rol
                // quedaría anulado con parte de sus asientos aún vigentes (descuadre silencioso).
                if (stripos($e->getMessage(), 'contable cerrado') !== false) {
                    throw $e;
                }
                // Otros errores: continuar con los demás, igual desvinculamos del rol al final.
            }
        }
        $this->repo->setIdAsiento((int) $cab['id'], null);
    }
}

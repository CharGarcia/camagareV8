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
 * Contabiliza un rol MENSUAL: agrega los conceptos de todos los empleados
 * (gastos, IESS, provisiones, anticipos) en líneas resumen, y el líquido a
 * pagar en una línea por empleado. Mapea cada concepto a su cuenta configurada
 * (asientos_programados / asientos_tipo, tipo 'nomina') y persiste con
 * AsientoContableService. Cuadra por construcción.
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

        // Una línea de detalle por empleado y concepto (etiquetada con el empleado).
        $lineas = [];
        $push = function (string $codigo, string $lado, float $valor, string $concepto, array $empOv, int $idEmp, string $nombre) use (&$lineas, $cuentaDe, $ref): void {
            $valor = round($valor, 2);
            if ($valor <= 0) return;
            $idCuenta = $cuentaDe($codigo, $empOv);
            if (!$idCuenta) return;
            $lineas[] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $lado === 'debe' ? $valor : 0,
                'haber'                => $lado === 'haber' ? $valor : 0,
                'referencia_detalle'   => $concepto . ' - ' . $nombre,
                'documento_referencia' => $ref,
                'id_entidad'           => $idEmp,
                'tipo_entidad'         => 'empleado',
            ];
        };

        foreach ($detalle as $lin) {
            $idEmp  = (int) $lin['id_empleado'];
            $nombre = (string) $lin['nombres_apellidos'];
            $empOv  = $progRepo->getReglasEmpleado($idEmpresa, $idEmp); // overrides por empleado
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
            $push('BANCOSNOMINA', 'haber', (float) $lin['neto'], 'Líquido a pagar', $empOv, $idEmp, $nombre);
        }

        if (!empty($faltan)) {
            throw new Exception('Configure las cuentas de nómina en Configuración Contable: ' . implode(', ', array_unique($faltan)) . '.');
        }
        if (empty($lineas)) throw new Exception('No hay valores para contabilizar.');

        // ── Persistir ────────────────────────────────────────────────────────
        $asientoRepo    = new AsientoContableRepository();
        $asientoService = new AsientoContableService($asientoRepo, new AsientoContableRules(), $this->log);
        $previo         = $asientoService->getAsientoPorOrigen('nomina', $idRol, $idEmpresa);

        $fecha = $cab['fecha_pago'] ?: date('Y-m-t', mktime(0, 0, 0, (int) $cab['periodo_mes'], 1, (int) $cab['periodo_anio']));
        $cabeceraData = [
            'id'                   => $previo ? (int) $previo['id'] : null,
            'fecha_asiento'        => $fecha,
            'tipo_comprobante'     => 'nomina',
            'numero_comprobante'   => $previo['numero_comprobante'] ?? '',
            'concepto'             => CatalogoRol::nombreTipo((string) $cab['tipo_rol']) . ' - ' . $ref,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'nomina',
            'id_referencia_origen' => $idRol,
            'observaciones'        => null,
        ];

        $idAsiento = $asientoService->guardarAsiento($cabeceraData, $lineas, $idEmpresa, $idUsuario);

        $this->repo->setIdAsiento($idRol, $idAsiento);
        $this->repo->setEstado($idRol, $idEmpresa, 'contabilizado', $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'CONTABILIZAR', 'rol_cabecera', $idRol, $cab, ['id_asiento' => $idAsiento]);

        return ['id_asiento' => $idAsiento, 'lineas' => count($lineas)];
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
        $haber[] = $mk('BANCOSNOMINA', 'Líquido a pagar', (float) $lin['neto']);

        $debe = array_values(array_filter($debe));
        $haber = array_values(array_filter($haber));
        $td = array_sum(array_map(fn($x) => $x['valor'], $debe));
        $th = array_sum(array_map(fn($x) => $x['valor'], $haber));

        return ['debe' => $debe, 'haber' => $haber, 'total_debe' => round($td, 2), 'total_haber' => round($th, 2), 'cuadrado' => abs($td - $th) < 0.01];
    }

    /** Anula el asiento asociado al rol (si existe) y lo desvincula. */
    public function anularAsiento(array $cab, int $idEmpresa, int $idUsuario): void
    {
        $idAsiento = (int) ($cab['id_asiento'] ?? 0);
        if ($idAsiento <= 0) return;
        try {
            $asientoService = new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->log);
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            // continuar: igual desvinculamos del rol
        }
        $this->repo->setIdAsiento((int) $cab['id'], null);
    }
}

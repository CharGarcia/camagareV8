<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SuscripcionDevengoRepository;
use App\Services\LogSistemaService;

/**
 * Devengado del ingreso de suscripciones (NIIF 15 / NIIF para PYMES Sección 23).
 *
 * Un servicio de suscripción se presta a lo largo del tiempo: su ingreso se reconoce mes a mes,
 * no el día en que se factura. Este service:
 *   - calcula el período de servicio que cubre cada documento generado
 *     (cobro por adelantado → el período que empieza en el próximo cobro;
 *      mes caído → el período que termina el día anterior al próximo cobro);
 *   - arma el cronograma mensual de las líneas de SERVICIO de un documento cobrado por
 *     adelantado, cuando la suscripción reconoce el ingreso «durante el período».
 *
 * Reglas del cronograma:
 *   - Solo líneas de servicio (productos.tipo_produccion = '02'); los bienes se reconocen al
 *     facturar.
 *   - Solo periodicidades por meses (mensual, trimestral, semestral, anual, bianual); diaria,
 *     semanal y quincenal se reconocen al facturar (diferirlas es inmaterial).
 *   - La base de la línea se reparte en partes iguales, una por mes del período (el último mes
 *     absorbe los centavos). Solo entran al cronograma los meses POSTERIORES al mes de emisión:
 *     lo del mes de emisión o anterior ya se prestó y la factura lo reconoce directo al ingreso.
 *   - El IVA no se difiere: se declara en el mes de la factura.
 */
class SuscripcionDevengoService
{
    use \App\Traits\PeriodoContableTrait;

    /** Periodicidades que no se miden en meses (calcularProximoCobro las trata por días). */
    private const CODIGOS_POR_DIAS = ['DIARIO' => 1, 'SEMANAL' => 7, 'QUINCENAL' => 15];

    public function __construct(
        private SuscripcionDevengoRepository $repo,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new SuscripcionDevengoRepository(), new LogSistemaService());
    }

    /**
     * Período de servicio que cubre el documento que vence en $proximo.
     *
     * @param array  $susc         Fila de la suscripción (modalidad_cobro, fecha_inicio, fecha_fin, periodicidad_*)
     * @param string $proximo      Próximo cobro con el que se genera el documento (Y-m-d)
     * @param string $siguiente    Próximo cobro siguiente (calcularProximoCobro($proximo))
     * @return array{desde:string, hasta:string}
     */
    public function periodoServicio(array $susc, string $proximo, string $siguiente): array
    {
        $dtProximo = new \DateTimeImmutable($proximo);

        if (($susc['modalidad_cobro'] ?? 'anticipado') === 'vencido') {
            $desde = $this->restarPeriodo($dtProximo, (int) ($susc['periodicidad_meses'] ?? 1), (string) ($susc['periodicidad_codigo'] ?? ''));
            $hasta = $dtProximo->modify('-1 day');
            // El primer período no empieza antes del inicio del contrato.
            if (!empty($susc['fecha_inicio']) && $desde < new \DateTimeImmutable((string) $susc['fecha_inicio'])) {
                $desde = new \DateTimeImmutable((string) $susc['fecha_inicio']);
            }
        } else {
            $desde = $dtProximo;
            $hasta = (new \DateTimeImmutable($siguiente))->modify('-1 day');
        }

        // Ningún período se extiende más allá del fin del contrato.
        if (!empty($susc['fecha_fin'])) {
            $fin = new \DateTimeImmutable((string) $susc['fecha_fin']);
            if ($hasta > $fin) {
                $hasta = $fin;
            }
        }
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        return ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];
    }

    /**
     * ¿Este período de mes caído todavía se factura? El último período (el que contiene la fecha
     * de fin) se factura DESPUÉS de esa fecha; uno que empieza pasada la fecha de fin, ya no.
     */
    public function periodoVencidoDentroDelContrato(array $susc, string $proximo): bool
    {
        if (empty($susc['fecha_fin'])) {
            return true;
        }
        $desde = $this->restarPeriodo(new \DateTimeImmutable($proximo), (int) ($susc['periodicidad_meses'] ?? 1), (string) ($susc['periodicidad_codigo'] ?? ''));
        return $desde <= new \DateTimeImmutable((string) $susc['fecha_fin']);
    }

    /**
     * Arma el cronograma de las líneas de servicio de un documento generado por la suscripción.
     * No hace nada (devuelve 0) si la suscripción reconoce al facturar, si es de mes caído, si la
     * periodicidad no es por meses, si no hay meses futuros o si aún no se aplicó el SQL.
     *
     * Transacción propia: el documento ya está confirmado. El que llama decide qué hacer si falla
     * (las automatizaciones lo registran y siguen: el documento ya existe y no debe duplicarse).
     *
     * @param array $res Resultado de SuscripcionFacturacionService::generarUnPeriodo()
     * @return int Filas de cronograma creadas
     */
    public function crearCronogramaDocumento(
        int $idEmpresa,
        int $idUsuario,
        array $susc,
        ?int $idPago,
        array $res,
        string $servicioDesde,
        string $fechaEmision
    ): int {
        if (($susc['reconocimiento'] ?? 'inmediato') !== 'diferido'
            || isset(self::CODIGOS_POR_DIAS[(string) ($susc['periodicidad_codigo'] ?? '')])
            || !$this->repo->disponible()) {
            return 0;
        }
        // Mes caído: la factura no difiere nada; cancela las provisiones del cierre (también con
        // el interruptor apagado: una provisión ya registrada debe cancelarse con su factura).
        if (($susc['modalidad_cobro'] ?? 'anticipado') === 'vencido') {
            return $this->vincularProvisiones($idEmpresa, $idUsuario, $susc, $idPago, $res, $servicioDesde);
        }
        // «Módulos que contabilizan» apagado: no se difiere nada nuevo.
        if (!$this->contabiliza($idEmpresa)) {
            return 0;
        }

        $meses = max(1, (int) ($susc['periodicidad_meses'] ?? 1));
        [$tipoDoc, $idDoc] = !empty($res['id_factura'])
            ? ['factura', (int) $res['id_factura']]
            : ['recibo', (int) ($res['id_recibo'] ?? 0)];
        if ($idDoc <= 0) {
            return 0;
        }

        $mesEmision = (new \DateTimeImmutable($fechaEmision))->modify('first day of this month');
        $mesInicio  = (new \DateTimeImmutable($servicioDesde))->modify('first day of this month');

        $propia = $this->abrirTransaccion();
        try {
            // Un documento, un cronograma: si la generación se reintenta, no se duplica.
            $this->repo->bloquear('susc_devengo_doc:' . $tipoDoc . ':' . $idDoc);
            if ($this->repo->existeCronogramaDocumento($tipoDoc, $idDoc, $idEmpresa)) {
                $this->confirmar($propia);
                return 0;
            }

            $filas = [];
            foreach ($this->repo->getLineasDocumento($tipoDoc, $idDoc, $idEmpresa) as $linea) {
                if (($linea['tipo_produccion'] ?? '') !== '02') {
                    continue; // bienes: se reconocen al facturar
                }
                $partes = self::repartirEnMeses((float) $linea['base'], $meses);
                foreach ($partes as $k => $monto) {
                    $periodo = $mesInicio->modify("+{$k} months");
                    if ($periodo <= $mesEmision || $monto <= 0) {
                        continue; // ya prestado: la factura lo reconoce directo al ingreso
                    }
                    $fila = [
                        'id_empresa'           => $idEmpresa,
                        'id_suscripcion'       => (int) $susc['id'],
                        'id_suscripcion_pago'  => $idPago,
                        'tipo'                 => 'diferido',
                        'tipo_documento'       => $tipoDoc,
                        'id_documento'         => $idDoc,
                        'id_documento_detalle' => (int) $linea['id'],
                        'id_producto'          => !empty($linea['id_producto']) ? (int) $linea['id_producto'] : null,
                        'descripcion'          => (string) ($linea['descripcion'] ?? ''),
                        'periodo'              => $periodo->format('Y-m-d'),
                        'monto'                => $monto,
                        'estado'               => 'pendiente',
                        'created_by'           => $idUsuario,
                    ];
                    $fila['id'] = $this->repo->insertar($fila);
                    $filas[] = $fila;
                }
            }

            if ($filas) {
                $this->log->registrar($idUsuario, $idEmpresa, 'crear_cronograma_devengo', 'suscripciones',
                    (int) $susc['id'], null, [
                        'tipo_documento' => $tipoDoc,
                        'id_documento'   => $idDoc,
                        'servicio_desde' => $servicioDesde,
                        'total_diferido' => round(array_sum(array_column($filas, 'monto')), 2),
                        'meses'          => count(array_unique(array_column($filas, 'periodo'))),
                    ]);
            }

            $this->confirmar($propia);
            return count($filas);
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Mes caído: enlaza las provisiones del cierre (servicio ya prestado sin facturar) de los meses
     * que cubre esta factura/recibo con sus líneas de servicio, por producto. Así el asiento del
     * documento acredita «Ingresos devengados por facturar» en vez del ingreso, que ya se reconoció.
     * Una provisión que no cabe en ninguna línea (otro producto, o la línea quedó por menos valor)
     * queda sin enlazar y sigue en el activo hasta revisarla.
     *
     * @return int Provisiones enlazadas
     */
    private function vincularProvisiones(int $idEmpresa, int $idUsuario, array $susc, ?int $idPago, array $res, string $servicioDesde): int
    {
        [$tipoDoc, $idDoc] = !empty($res['id_factura'])
            ? ['factura', (int) $res['id_factura']]
            : ['recibo', (int) ($res['id_recibo'] ?? 0)];
        if ($idDoc <= 0) {
            return 0;
        }
        $meses  = max(1, (int) ($susc['periodicidad_meses'] ?? 1));
        $inicio = (new \DateTimeImmutable($servicioDesde))->modify('first day of this month');
        $periodos = [];
        for ($k = 0; $k < $meses; $k++) {
            $periodos[] = $inicio->modify("+{$k} months")->format('Y-m-d');
        }

        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_doc:' . $tipoDoc . ':' . $idDoc);
            $provisiones = $this->repo->getProvisionesSinFacturar((int) $susc['id'], $periodos, $idEmpresa);
            if (!$provisiones) {
                $this->confirmar($propia);
                return 0;
            }

            // Capacidad de cada línea de servicio: lo que la provisión puede tomar de su base.
            $lineas = [];
            foreach ($this->repo->getLineasDocumento($tipoDoc, $idDoc, $idEmpresa) as $l) {
                if (($l['tipo_produccion'] ?? '') === '02') {
                    $lineas[] = ['id' => (int) $l['id'], 'id_producto' => (int) ($l['id_producto'] ?? 0), 'libre' => round((float) $l['base'], 2)];
                }
            }

            $enlazadas = 0;
            $total = 0.0;
            foreach ($provisiones as $p) {
                $monto = round((float) $p['monto'], 2);
                foreach ($lineas as &$l) {
                    if ($l['id_producto'] === (int) $p['id_producto'] && $l['libre'] + 0.001 >= $monto) {
                        $this->repo->marcarProvisionFacturada((int) $p['id'], $tipoDoc, $idDoc, $l['id'], $idPago, $idUsuario);
                        $l['libre'] = round($l['libre'] - $monto, 2);
                        $enlazadas++;
                        $total += $monto;
                        break;
                    }
                }
                unset($l);
            }

            if ($enlazadas > 0) {
                $this->log->registrar($idUsuario, $idEmpresa, 'facturar_provision_devengo', 'suscripciones',
                    (int) $susc['id'], null, [
                        'tipo_documento' => $tipoDoc,
                        'id_documento'   => $idDoc,
                        'provisiones'    => $enlazadas,
                        'total'          => round($total, 2),
                    ]);
            }
            $this->confirmar($propia);
            return $enlazadas;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    // ── Reporte de ingresos diferidos y conciliación con el mayor ───────────

    /**
     * Saldos al cierre del mes elegido, por documento:
     *   - Diferido corriente (se devenga en los 12 meses siguientes al corte) y no corriente.
     *   - Ingresos devengados por facturar (provisiones de mes caído sin facturar a esa fecha).
     * Más la conciliación: el total del cronograma contra el saldo del mayor de cada cuenta
     * (Configuración Contable → Suscripciones - Devengo). Una diferencia indica asientos hechos
     * a mano sobre esas cuentas, documentos sin asiento o un cambio de cuenta a mitad de camino.
     */
    public function reporteSaldos(int $idEmpresa, string $mes): array
    {
        $periodo = $this->validarMes($mes);
        if (!$this->repo->disponible()) {
            throw new \RuntimeException('Falta aplicar el SQL del devengo de suscripciones.');
        }
        $corte      = $periodo->modify('last day of this month')->format('Y-m-d');
        $limiteCorr = $periodo->modify('+12 months')->format('Y-m-d');

        $porDoc = [];
        $tot = ['corriente' => 0.0, 'no_corriente' => 0.0, 'por_facturar' => 0.0];
        foreach ($this->repo->getSaldosAlCorte($idEmpresa, $corte) as $f) {
            $clave = $f['tipo'] === 'provision' && empty($f['id_documento'])
                ? 'prov:' . $f['id_suscripcion']
                : $f['tipo_documento'] . ':' . $f['id_documento'];
            $porDoc[$clave] ??= [
                'cliente'        => (string) ($f['cliente'] ?? ''),
                'identificacion' => (string) ($f['identificacion'] ?? ''),
                'id_suscripcion' => (int) $f['id_suscripcion'],
                'documento'      => !empty($f['numero'])
                    ? (($f['tipo_documento'] === 'recibo' ? 'REC ' : 'FAC ') . $f['numero'])
                    : 'Sin facturar (mes caído)',
                'fecha'          => !empty($f['fecha_documento']) ? substr((string) $f['fecha_documento'], 0, 10) : '',
                'corriente'      => 0.0,
                'no_corriente'   => 0.0,
                'por_facturar'   => 0.0,
                'ultimo_mes'     => '',
            ];
            $monto = round((float) $f['monto'], 2);
            if ($f['tipo'] === 'provision') {
                $col = 'por_facturar';
            } else {
                $col = (string) $f['periodo'] <= $limiteCorr ? 'corriente' : 'no_corriente';
                $porDoc[$clave]['ultimo_mes'] = max($porDoc[$clave]['ultimo_mes'], substr((string) $f['periodo'], 0, 7));
            }
            $porDoc[$clave][$col] = round($porDoc[$clave][$col] + $monto, 2);
            $tot[$col] = round($tot[$col] + $monto, 2);
        }

        // Conciliación con el mayor (diferido es pasivo: saldo acreedor = −deudor).
        $cuentas = [];
        foreach ((new \App\repositories\modulos\AsientoProgramadoRepository())->getReglasGeneralesPorConcepto($idEmpresa, 'suscripciones_devengo') as $r) {
            $cuentas[(string) $r['codigo']] = $r;
        }
        $conciliacion = [];
        foreach ([
            'INGRESODIFERIDOSUSCRIPCION'    => ['Ingresos diferidos', round($tot['corriente'] + $tot['no_corriente'], 2), -1],
            'INGRESOPORFACTURARSUSCRIPCION' => ['Ingresos devengados por facturar', $tot['por_facturar'], 1],
        ] as $codigo => [$nombre, $cronograma, $signo]) {
            $r = $cuentas[$codigo] ?? [];
            $mayor = !empty($r['id_cuenta'])
                ? round($signo * $this->repo->getSaldoMayor($idEmpresa, (int) $r['id_cuenta'], $corte), 2)
                : null;
            $conciliacion[] = [
                'concepto'   => $nombre,
                'cuenta'     => !empty($r['id_cuenta']) ? trim(($r['cuenta_codigo'] ?? '') . ' ' . ($r['cuenta_nombre'] ?? '')) : '',
                'cronograma' => $cronograma,
                'mayor'      => $mayor,
                'diferencia' => $mayor === null ? null : round($mayor - $cronograma, 2),
            ];
        }

        return [
            'mes'          => $periodo->format('Y-m'),
            'fecha_corte'  => $corte,
            'filas'        => array_values($porDoc),
            'totales'      => $tot,
            'conciliacion' => $conciliacion,
        ];
    }

    /** Libro de Excel del reporte (hoja de detalle + hoja de conciliación). */
    public function reporteSaldosExcel(int $idEmpresa, string $mes, string $nombreEmpresa): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $rep = $this->reporteSaldos($idEmpresa, $mes);
        $filas = array_map(fn($f) => [
            $f['cliente'], $f['identificacion'], $f['id_suscripcion'], $f['documento'], $f['fecha'],
            $f['ultimo_mes'], $f['corriente'], $f['no_corriente'], $f['por_facturar'],
        ], $rep['filas']);
        $filas[] = ['TOTAL', '', '', '', '', '', $rep['totales']['corriente'], $rep['totales']['no_corriente'], $rep['totales']['por_facturar']];

        $reportService = new \App\Services\ReportService();
        $libro = $reportService->construirSpreadsheet(
            ['Cliente', 'RUC/Cédula', 'Suscripción', 'Documento', 'Fecha', 'Último mes', 'Diferido corriente', 'Diferido no corriente', 'Por facturar'],
            $filas,
            'Ingresos diferidos de suscripciones',
            $nombreEmpresa,
            ['Saldos al' => date('d-m-Y', strtotime($rep['fecha_corte'])), 'Documentos' => (string) count($rep['filas'])]
        );

        $h = $libro->createSheet();
        $h->setTitle('Conciliación');
        $h->fromArray([['Concepto', 'Cuenta', 'Según cronograma', 'Según mayor', 'Diferencia']], null, 'A1');
        $fila = 2;
        foreach ($rep['conciliacion'] as $c) {
            $h->fromArray([[$c['concepto'], $c['cuenta'] ?: '(sin cuenta configurada)', $c['cronograma'], $c['mayor'], $c['diferencia']]], null, 'A' . $fila++);
        }
        $h->getStyle('A1:E1')->getFont()->setBold(true);
        $h->getStyle('C2:E' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'E') as $col) {
            $h->getColumnDimension($col)->setAutoSize(true);
        }
        return $libro;
    }

    // ── Apertura: documentos emitidos antes de activar el devengado ─────────

    /**
     * Documentos de suscripción ya contabilizados (todo al ingreso) que todavía cubren meses
     * posteriores a $mesCorte, con lo que se diferiría de cada uno y el asiento resultante.
     * El período de servicio sale de suscripciones_pagos.servicio_desde o, en los documentos
     * anteriores al devengado (sin ese dato), de la fecha de emisión.
     */
    public function previsualizarApertura(int $idEmpresa, string $mesCorte): array
    {
        $corte = $this->validarMes($mesCorte);
        if (!$this->repo->disponible() || !$this->repo->tieneOrigen()) {
            throw new \RuntimeException('Falta aplicar el SQL database/migrations/20261004_suscripciones_devengo_apertura.sql.');
        }
        [$documentos, $filas] = $this->calcularApertura($idEmpresa, $corte);

        $asiento = [];
        $error = null;
        if ($filas) {
            try {
                $asiento = $this->asientoApertura($idEmpresa, $filas)['detalles'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        return [
            'mes'                 => $corte->format('Y-m'),
            'fecha_asiento'       => $corte->modify('last day of this month')->format('Y-m-d'),
            'documentos'          => $documentos,
            'total'               => round(array_sum(array_column($filas, 'monto')), 2),
            'asiento'             => $asiento,
            'error'               => $error,
            'asientos_existentes' => $this->repo->getAsientosApertura($idEmpresa, (int) $corte->format('Ym')),
        ];
    }

    /**
     * Registra la apertura: arma el cronograma (origen 'apertura') de los meses posteriores al
     * corte y un asiento con fecha del último día del mes de corte: DEBE ingreso / HABER Ingresos
     * diferidos. Desde el mes siguiente, el devengo mensual los pasa al ingreso como cualquier otro.
     */
    public function aplicarApertura(int $idEmpresa, int $idUsuario, string $mesCorte): array
    {
        $corte = $this->validarMes($mesCorte);
        if (!$this->repo->disponible() || !$this->repo->tieneOrigen()) {
            throw new \RuntimeException('Falta aplicar el SQL database/migrations/20261004_suscripciones_devengo_apertura.sql.');
        }
        $finMes    = $corte->modify('last day of this month')->format('Y-m-d');
        $nombreMes = $this->nombreMes($corte);
        $this->validarPeriodoContable($finMes, $idEmpresa,
            "No se puede registrar la apertura al cierre de {$nombreMes}: ese período contable está cerrado.");

        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_mes:' . $idEmpresa);
            [$documentos, $filas] = $this->calcularApertura($idEmpresa, $corte);
            if (!$filas) {
                throw new \DomainException('No hay facturas ni recibos de suscripciones con meses por diferir después de ' . $nombreMes . '.');
            }
            $res = $this->asientoApertura($idEmpresa, $filas);

            $asientoService = new AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->log
            );
            $idAsiento = $asientoService->guardarAsiento([
                'id'                   => null,
                'fecha_asiento'        => $finMes,
                'tipo_comprobante'     => 'diario',
                'numero_comprobante'   => '',
                'concepto'             => "Apertura de ingresos diferidos de suscripciones - {$nombreMes}",
                'estado'               => 'contabilizado',
                'modulo_origen'        => 'suscripcion_devengo_apertura',
                'id_referencia_origen' => (int) $corte->format('Ym'),
                'observaciones'        => null,
            ], array_map(fn($d) => $d + ['documento_referencia' => "Apertura devengo {$nombreMes}"], $res['detalles']), $idEmpresa, $idUsuario);

            foreach ($filas as $k => $f) {
                $f['origen']              = 'apertura';
                $f['id_asiento_apertura'] = $idAsiento;
                $f['id_cuenta_ingreso']   = $res['cuenta_por_fila'][$k] ?? null;
                $f['estado']              = 'pendiente';
                $f['created_by']          = $idUsuario;
                $this->repo->insertar($f);
            }

            $resumen = [
                'mes'        => $corte->format('Y-m'),
                'id_asiento' => $idAsiento,
                'documentos' => count($documentos),
                'filas'      => count($filas),
                'total'      => round(array_sum(array_column($filas, 'monto')), 2),
            ];
            $this->log->registrar($idUsuario, $idEmpresa, 'apertura_devengo', 'asientos_contables_cabecera', $idAsiento, null, $resumen);
            $this->confirmar($propia);
            return $resumen;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Deshace la apertura de un mes de corte: anula su asiento y da de baja sus filas. No se puede
     * si alguna ya se devengó o la tomó una nota de crédito (habría que revertir eso primero).
     */
    public function revertirApertura(int $idEmpresa, int $idUsuario, string $mesCorte): array
    {
        $corte     = $this->validarMes($mesCorte);
        $nombreMes = $this->nombreMes($corte);
        $asientos  = $this->repo->getAsientosApertura($idEmpresa, (int) $corte->format('Ym'));
        if (!$asientos) {
            throw new \DomainException("No hay apertura registrada al cierre de {$nombreMes}.");
        }
        $this->validarPeriodoContable($corte->modify('last day of this month')->format('Y-m-d'), $idEmpresa,
            "No se puede revertir la apertura de {$nombreMes}: ese período contable está cerrado.");

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->log
        );
        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_mes:' . $idEmpresa);
            $filas = 0;
            foreach ($asientos as $a) {
                $vivas = $this->repo->getFilasApertura((int) $a['id'], $idEmpresa);
                foreach ($vivas as $f) {
                    if ($f['estado'] !== 'pendiente') {
                        throw new \DomainException("No se puede revertir la apertura de {$nombreMes}: ya hay meses devengados o devueltos con nota de crédito. Revierta primero esos movimientos.");
                    }
                }
                foreach ($vivas as $f) {
                    $this->repo->darDeBaja((int) $f['id'], $idUsuario);
                    $filas++;
                }
                $asientoService->anular((int) $a['id'], $idEmpresa, $idUsuario);
            }
            $resumen = ['mes' => $corte->format('Y-m'), 'asientos_anulados' => array_column($asientos, 'id'), 'filas' => $filas];
            $this->log->registrar($idUsuario, $idEmpresa, 'revertir_apertura_devengo', 'asientos_contables_cabecera', (int) $asientos[0]['id'], null, $resumen);
            $this->confirmar($propia);
            return $resumen;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Cronograma de apertura (sin grabar) de los documentos candidatos: por cada línea de servicio,
     * las porciones mensuales posteriores al mes de corte y al mes de emisión.
     *
     * @return array{0: array<int,array>, 1: array<string,array>} [documentos con su total, filas por clave]
     */
    private function calcularApertura(int $idEmpresa, \DateTimeImmutable $corte): array
    {
        $documentos = [];
        $filas = [];
        foreach ($this->repo->getCandidatosApertura($idEmpresa) as $doc) {
            $meses   = max(1, (int) $doc['periodicidad_meses']);
            $desde   = (string) ($doc['servicio_desde'] ?: substr((string) $doc['fecha_emision'], 0, 10));
            $mesIni  = (new \DateTimeImmutable($desde))->modify('first day of this month');
            $emision = (new \DateTimeImmutable(substr((string) $doc['fecha_emision'], 0, 10)))->modify('first day of this month');
            $limite  = max($corte, $emision);

            $total = 0.0;
            foreach ($this->repo->getLineasDocumento((string) $doc['tipo_documento'], (int) $doc['id_documento'], $idEmpresa) as $l) {
                if (($l['tipo_produccion'] ?? '') !== '02') {
                    continue;
                }
                foreach (self::repartirEnMeses((float) $l['base'], $meses) as $k => $monto) {
                    $periodo = $mesIni->modify("+{$k} months");
                    if ($periodo <= $limite || $monto <= 0) {
                        continue;
                    }
                    $clave = $doc['tipo_documento'] . ':' . $l['id'] . ':' . $periodo->format('Ym');
                    $filas[$clave] = [
                        'id_empresa'           => $idEmpresa,
                        'id_suscripcion'       => (int) $doc['id_suscripcion'],
                        'id_suscripcion_pago'  => (int) $doc['id_pago'],
                        'tipo'                 => 'diferido',
                        'tipo_documento'       => $doc['tipo_documento'],
                        'id_documento'         => (int) $doc['id_documento'],
                        'id_documento_detalle' => (int) $l['id'],
                        'id_producto'          => !empty($l['id_producto']) ? (int) $l['id_producto'] : null,
                        'descripcion'          => (string) ($l['descripcion'] ?? ''),
                        'periodo'              => $periodo->format('Y-m-d'),
                        'monto'                => $monto,
                        // Para la cuenta de ingreso (no se graban):
                        'id_cliente'           => (int) $doc['id_cliente'],
                        'tipo_asiento'         => $doc['tipo_documento'] === 'recibo' ? 'recibos_venta' : 'ventas_factura',
                    ];
                    $total += $monto;
                }
            }
            if ($total > 0) {
                $documentos[] = [
                    'tipo_documento' => $doc['tipo_documento'],
                    'numero'         => $doc['numero'],
                    'fecha_emision'  => substr((string) $doc['fecha_emision'], 0, 10),
                    'cliente'        => $doc['cliente'],
                    'id_suscripcion' => (int) $doc['id_suscripcion'],
                    'monto'          => round($total, 2),
                ];
            }
        }
        return [$documentos, $filas];
    }

    /** Asiento de apertura: el del devengo mensual con los lados invertidos. */
    private function asientoApertura(int $idEmpresa, array $filas): array
    {
        $res = (new AsientoBuilderService())->generarAsientoDevengoSuscripciones($idEmpresa, $filas);
        foreach ($res['detalles'] as &$d) {
            [$d['debe'], $d['haber']] = [$d['haber'], $d['debe']];
            $d['referencia_detalle'] = ($d['debe'] > 0 ? 'Reclasificación a ingreso diferido: ' : '') . $d['referencia_detalle'];
        }
        unset($d);
        return $res;
    }

    // ── Proceso mensual de devengo ──────────────────────────────────────────

    /**
     * Qué haría el devengo del mes, sin escribir nada: filas diferidas a devengar (incluye meses
     * anteriores pendientes), provisiones de mes caído, el asiento resultante (o por qué no se
     * puede armar), lo que queda esperando que su documento tenga asiento y los asientos que el
     * mes ya tiene.
     */
    public function previsualizarMes(int $idEmpresa, string $mes): array
    {
        $periodo = $this->validarMes($mes);
        if (!$this->repo->disponible()) {
            throw new \RuntimeException('Falta aplicar el SQL del devengo de suscripciones.');
        }
        $p = $periodo->format('Y-m-d');

        $diferidos   = $this->repo->getDiferidosPorDevengar($idEmpresa, $p);
        $provisiones = $this->calcularProvisiones($idEmpresa, $periodo);

        $asiento = [];
        $error   = null;
        if ($diferidos || $provisiones) {
            try {
                $asiento = (new AsientoBuilderService())
                    ->generarAsientoDevengoSuscripciones($idEmpresa, $this->filasParaAsiento($diferidos, $provisiones))['detalles'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $anteriores = array_filter($diferidos, fn($d) => (string) $d['periodo'] < $p);
        $sinAsiento = $this->repo->contarDiferidosSinAsiento($idEmpresa, $p);

        return [
            'mes'                 => $periodo->format('Y-m'),
            'fecha_asiento'       => $periodo->modify('last day of this month')->format('Y-m-d'),
            'diferidos'           => ['filas' => count($diferidos), 'monto' => round(array_sum(array_column($diferidos, 'monto')), 2)],
            'meses_anteriores'    => ['filas' => count($anteriores), 'monto' => round(array_sum(array_column($anteriores, 'monto')), 2)],
            'provisiones'         => ['filas' => count($provisiones), 'monto' => round(array_sum(array_column($provisiones, 'monto')), 2),
                                      'suscripciones' => count(array_unique(array_column($provisiones, 'id_suscripcion')))],
            'esperando_asiento'   => ['filas' => (int) $sinAsiento['filas'], 'monto' => round((float) $sinAsiento['monto'], 2)],
            'asiento'             => $asiento,
            'error'               => $error,
            'asientos_existentes' => $this->repo->getAsientosMes($idEmpresa, (int) $periodo->format('Ym')),
        ];
    }

    /**
     * Genera el asiento consolidado del mes: devenga lo diferido (pasivo → ingreso) y provisiona el
     * servicio de mes caído ya prestado sin facturar (activo ← ingreso). Fecha: último día del mes.
     * Se puede correr más de una vez en el mes (cada corrida toma lo que quedó pendiente).
     *
     * @throws \DomainException si no hay nada que devengar ni provisionar (no es un error).
     */
    public function devengarMes(int $idEmpresa, int $idUsuario, string $mes): array
    {
        $periodo = $this->validarMes($mes);
        if (!$this->repo->disponible()) {
            throw new \RuntimeException('Falta aplicar el SQL del devengo de suscripciones.');
        }
        $p          = $periodo->format('Y-m-d');
        $finMes     = $periodo->modify('last day of this month')->format('Y-m-d');
        $nombreMes  = $this->nombreMes($periodo);

        $this->validarPeriodoContable($finMes, $idEmpresa,
            "No se puede devengar {$nombreMes}: ese período contable está cerrado.");

        $propia = $this->abrirTransaccion();
        try {
            // Un solo devengo a la vez por empresa: incluye filas de meses anteriores.
            $this->repo->bloquear('susc_devengo_mes:' . $idEmpresa);

            $diferidos   = $this->repo->getDiferidosPorDevengar($idEmpresa, $p);
            $provisiones = $this->calcularProvisiones($idEmpresa, $periodo);
            if (!$diferidos && !$provisiones) {
                throw new \DomainException("No hay ingresos por devengar ni servicios de mes caído por provisionar en {$nombreMes}.");
            }

            $res = (new AsientoBuilderService())
                ->generarAsientoDevengoSuscripciones($idEmpresa, $this->filasParaAsiento($diferidos, $provisiones));

            $detalles = [];
            foreach ($res['detalles'] as $det) {
                $detalles[] = [
                    'id_cuenta_contable'   => $det['id_cuenta_contable'],
                    'debe'                 => $det['debe'],
                    'haber'                => $det['haber'],
                    'referencia_detalle'   => $det['referencia_detalle'],
                    'documento_referencia' => "Devengo suscripciones {$nombreMes}",
                ];
            }

            $asientoService = new AsientoContableService(
                new \App\repositories\modulos\AsientoContableRepository(),
                new \App\Rules\modulos\AsientoContableRules(),
                $this->log
            );
            $idAsiento = $asientoService->guardarAsiento([
                'id'                   => null,
                'fecha_asiento'        => $finMes,
                'tipo_comprobante'     => 'diario',
                'numero_comprobante'   => '',
                'concepto'             => "Devengo de ingresos de suscripciones - {$nombreMes}",
                'estado'               => 'contabilizado',
                'modulo_origen'        => 'suscripcion_devengo',
                'id_referencia_origen' => (int) $periodo->format('Ym'),
                'observaciones'        => null,
            ], $detalles, $idEmpresa, $idUsuario);

            foreach ($diferidos as $d) {
                $this->repo->marcarDevengado((int) $d['id'], $idAsiento, $res['cuenta_por_fila']['d' . $d['id']] ?? null, $idUsuario);
            }
            foreach ($provisiones as $k => $prov) {
                $prov['estado']     = 'devengado';
                $prov['created_by'] = $idUsuario;
                $idFila = $this->repo->insertar($prov);
                $this->repo->marcarDevengado($idFila, $idAsiento, $res['cuenta_por_fila']['p' . $k] ?? null, $idUsuario);
            }

            $resumen = [
                'mes'                => $periodo->format('Y-m'),
                'id_asiento'         => $idAsiento,
                'diferidos_filas'    => count($diferidos),
                'diferidos_monto'    => round(array_sum(array_column($diferidos, 'monto')), 2),
                'provisiones_filas'  => count($provisiones),
                'provisiones_monto'  => round(array_sum(array_column($provisiones, 'monto')), 2),
            ];
            $this->log->registrar($idUsuario, $idEmpresa, 'devengar_mes', 'asientos_contables_cabecera', $idAsiento, null, $resumen);

            $this->confirmar($propia);
            return $resumen;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Deshace el devengo de un mes: anula sus asientos, devuelve lo diferido a «por devengar» y da
     * de baja las provisiones. No se puede si una factura ya canceló alguna de esas provisiones
     * (habría que revertir primero esa factura) ni si el período está cerrado.
     */
    public function revertirMes(int $idEmpresa, int $idUsuario, string $mes): array
    {
        $periodo   = $this->validarMes($mes);
        $nombreMes = $this->nombreMes($periodo);
        $asientos  = $this->repo->getAsientosMes($idEmpresa, (int) $periodo->format('Ym'));
        if (!$asientos) {
            throw new \DomainException("{$nombreMes} no tiene devengo para revertir.");
        }
        $this->validarPeriodoContable($periodo->modify('last day of this month')->format('Y-m-d'), $idEmpresa,
            "No se puede revertir el devengo de {$nombreMes}: ese período contable está cerrado.");

        $ids = array_map(fn($a) => (int) $a['id'], $asientos);
        if ($this->repo->contarProvisionesFacturadas($ids, $idEmpresa) > 0) {
            throw new \DomainException("No se puede revertir {$nombreMes}: alguna provisión de mes caído ya la canceló su factura.");
        }

        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->log
        );

        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_mes:' . $idEmpresa);
            $filas = 0;
            foreach ($ids as $idAsiento) {
                $filas += $this->repo->revertirPorAsiento($idAsiento, $idEmpresa, $idUsuario);
                $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
            }
            $resumen = ['mes' => $periodo->format('Y-m'), 'asientos_anulados' => $ids, 'filas' => $filas];
            $this->log->registrar($idUsuario, $idEmpresa, 'revertir_devengo_mes', 'asientos_contables_cabecera', $ids[0], null, $resumen);
            $this->confirmar($propia);
            return $resumen;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Automatización: devenga mes a mes, desde el mes más antiguo con algo pendiente hasta el mes
     * anterior al actual. Un mes cerrado o sin nada que hacer se salta (lo pendiente de un mes
     * cerrado se devenga en el siguiente abierto, porque cada corrida toma también lo anterior).
     */
    public function ponerseAlDia(int $idEmpresa, int $idUsuario): array
    {
        if (!$this->repo->disponible()) {
            return ['meses' => 0, 'mensaje' => 'Falta aplicar el SQL del devengo de suscripciones.'];
        }
        $ultimo = (new \DateTimeImmutable('first day of this month'))->modify('-1 month');
        $primero = $ultimo;
        $pendiente = $this->repo->getPrimerMesPendiente($idEmpresa);
        if ($pendiente !== null && new \DateTimeImmutable($pendiente) < $primero) {
            $primero = new \DateTimeImmutable($pendiente);
        }

        $hechos = [];
        $avisos = [];
        for ($m = $primero; $m <= $ultimo; $m = $m->modify('+1 month')) {
            try {
                $r = $this->devengarMes($idEmpresa, $idUsuario, $m->format('Y-m'));
                $hechos[] = $this->nombreMes($m) . ': $' . number_format($r['diferidos_monto'] + $r['provisiones_monto'], 2);
            } catch (\DomainException $e) {
                continue; // nada que hacer ese mes
            } catch (\Throwable $e) {
                $avisos[] = $this->nombreMes($m) . ': ' . $e->getMessage();
            }
        }

        $msg = $hechos ? 'Devengo generado. ' . implode(' | ', $hechos) : 'No había ingresos de suscripciones por devengar.';
        if ($avisos) {
            $msg .= ' Avisos: ' . implode(' | ', $avisos);
        }
        return ['meses' => count($hechos), 'mensaje' => $msg];
    }

    /**
     * Provisiones de mes caído para el mes $periodo (sin grabar): por cada suscripción activa de mes
     * caído que reconoce durante el período, si el mes ya pertenece a un período aún no facturado
     * (desde el inicio del período que vence en el próximo cobro) y está dentro del contrato, su
     * porción mensual de cada línea de servicio. La porción sale del mismo reparto que el diferido:
     * base de la línea / meses de la periodicidad, el último mes absorbe los centavos.
     *
     * @return array<int, array> Filas listas para insertar
     */
    private function calcularProvisiones(int $idEmpresa, \DateTimeImmutable $periodo): array
    {
        if (!$this->contabiliza($idEmpresa)) {
            return []; // «Módulos que contabilizan» apagado: no se provisiona el mes caído
        }
        $suscRepo = new \App\repositories\modulos\SuscripcionesRepository();
        $filas = [];
        foreach ($this->repo->getSuscripcionesMesCaido($idEmpresa) as $s) {
            $meses = max(1, (int) $s['periodicidad_meses']);
            // Primer mes aún sin facturar: inicio del período que vence en el próximo cobro.
            $m0 = $this->restarPeriodo(new \DateTimeImmutable((string) $s['proximo_cobro']), $meses, (string) $s['periodicidad_codigo'])
                ->modify('first day of this month');
            $mIni = (new \DateTimeImmutable((string) $s['fecha_inicio']))->modify('first day of this month');
            if ($periodo < $m0 || $periodo < $mIni) {
                continue;
            }
            if (!empty($s['fecha_fin']) && $periodo > (new \DateTimeImmutable((string) $s['fecha_fin']))->modify('first day of this month')) {
                continue;
            }
            if ($this->repo->existeProvision((int) $s['id'], $periodo->format('Y-m-d'), $idEmpresa)) {
                continue;
            }

            $diff = ((int) $periodo->format('Y') - (int) $m0->format('Y')) * 12 + (int) $periodo->format('n') - (int) $m0->format('n');
            $k = $diff % $meses;
            foreach ($suscRepo->getDetalle((int) $s['id']) as $l) {
                if (($l['tipo_produccion'] ?? '') !== '02') {
                    continue;
                }
                $base  = round((float) $l['cantidad'] * (float) $l['precio_unitario'], 2);
                $monto = self::repartirEnMeses($base, $meses)[$k];
                if ($monto <= 0) {
                    continue;
                }
                $filas[] = [
                    'id_empresa'     => $idEmpresa,
                    'id_suscripcion' => (int) $s['id'],
                    'tipo'           => 'provision',
                    'id_producto'    => (int) $l['id_producto'],
                    'descripcion'    => (string) ($l['descripcion'] ?: ($l['nombre_producto'] ?? '')),
                    'periodo'        => $periodo->format('Y-m-d'),
                    'monto'          => $monto,
                    // Para la cuenta de ingreso (no se graban):
                    'id_cliente'     => (int) $s['id_cliente'],
                    'tipo_asiento'   => ($s['tipo_comprobante'] ?? 'factura') === 'recibo' ? 'recibos_venta' : 'ventas_factura',
                ];
            }
        }
        return $filas;
    }

    // ── Transacciones ───────────────────────────────────────────────────────
    // Estos métodos también se llaman DENTRO de la transacción de otro service (anular una
    // factura, guardar una NC). BaseRepository::commit() confirma cualquier transacción abierta,
    // así que solo se abre/confirma/revierte la propia; si ya hay una, manda quien la abrió.

    private function abrirTransaccion(): bool
    {
        if ($this->repo->enTransaccion()) {
            return false;
        }
        $this->repo->beginTransaction();
        return true;
    }

    private function confirmar(bool $propia): void
    {
        if ($propia) {
            $this->repo->commit();
        }
    }

    private function deshacer(bool $propia): void
    {
        if ($propia) {
            $this->repo->rollBack();
        }
    }

    // ── Paso 5: notas de crédito, anulación y edición del documento ─────────

    /**
     * Nota de crédito sobre una factura de suscripción: lo que devuelve de una línea de servicio
     * sale primero de lo que AÚN NO se devengó (el servicio no prestado), del último mes hacia
     * atrás. Esas filas quedan «anulado» con la NC; si una fila se consume en parte, se parte en
     * dos. Lo que excede lo pendiente reduce el ingreso como cualquier NC. Se recalcula entero en
     * cada guardado de la NC (primero devuelve lo que la misma NC había tomado).
     *
     * Su asiento (AsientoBuilderService::generarAsientoNotaCreditoVenta) debita Ingresos diferidos
     * por esas filas en vez de la cuenta de ingreso.
     *
     * @return float Monto tomado del diferido
     */
    public function aplicarNotaCredito(int $idEmpresa, int $idUsuario, int $idNotaCredito): float
    {
        if ($idNotaCredito <= 0 || !$this->repo->disponible()) {
            return 0.0;
        }
        $propia = $this->abrirTransaccion();
        try {
            $this->restaurarNotaCredito($idEmpresa, $idUsuario, $idNotaCredito);

            $total = 0.0;
            foreach ($this->repo->getLineasNotaCredito($idNotaCredito, $idEmpresa) as $l) {
                $porTomar = round((float) $l['base'], 2);
                if ($porTomar <= 0) {
                    continue;
                }
                $this->repo->bloquear('susc_devengo_doc:factura:linea:' . (int) $l['id_venta_detalle']);
                foreach ($this->repo->getPendientesLineaFactura((int) $l['id_venta_detalle'], $idEmpresa) as $f) {
                    if ($porTomar <= 0) {
                        break;
                    }
                    $monto = round((float) $f['monto'], 2);
                    if ($monto <= $porTomar + 0.001) {
                        $this->repo->cambiarEstado((int) $f['id'], 'anulado', $idUsuario, $idNotaCredito);
                        $tomado = $monto;
                    } else {
                        // Se consume en parte: la fila sigue pendiente por el resto y la parte
                        // devuelta queda en una fila anulada con la NC.
                        $tomado = $porTomar;
                        $this->repo->cambiarMonto((int) $f['id'], $monto - $tomado, $idUsuario);
                        $this->repo->insertar([
                            'id_empresa'           => $idEmpresa,
                            'id_suscripcion'       => (int) $f['id_suscripcion'],
                            'id_suscripcion_pago'  => $f['id_suscripcion_pago'] !== null ? (int) $f['id_suscripcion_pago'] : null,
                            'tipo'                 => 'diferido',
                            'tipo_documento'       => $f['tipo_documento'],
                            'id_documento'         => (int) $f['id_documento'],
                            'id_documento_detalle' => (int) $f['id_documento_detalle'],
                            'id_producto'          => $f['id_producto'] !== null ? (int) $f['id_producto'] : null,
                            'descripcion'          => $f['descripcion'],
                            'periodo'              => $f['periodo'],
                            'monto'                => $tomado,
                            'estado'               => 'anulado',
                            'id_nota_credito'      => $idNotaCredito,
                            'created_by'           => $idUsuario,
                        ]);
                    }
                    $porTomar = round($porTomar - $tomado, 2);
                    $total += $tomado;
                }
            }

            if ($total > 0) {
                $this->log->registrar($idUsuario, $idEmpresa, 'nota_credito_devengo', 'notas_credito_cabecera',
                    $idNotaCredito, null, ['diferido_devuelto' => round($total, 2)]);
            }
            $this->confirmar($propia);
            return round($total, 2);
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * Deshace lo que una NC tomó del diferido (al anularla, eliminarla o antes de recalcularla):
     * cada fila vuelve a «por devengar», sumándose a la parte que quedó pendiente del mismo mes
     * si la fila se había partido.
     *
     * @return int Filas restauradas
     */
    public function restaurarNotaCredito(int $idEmpresa, int $idUsuario, int $idNotaCredito): int
    {
        if ($idNotaCredito <= 0 || !$this->repo->disponible()) {
            return 0;
        }
        $propia = $this->abrirTransaccion();
        try {
            $n = 0;
            foreach ($this->repo->getAnuladasPorNotaCredito($idNotaCredito, $idEmpresa) as $f) {
                $pendiente = $this->repo->getPendienteLineaMes((string) $f['tipo_documento'], (int) $f['id_documento_detalle'], (string) $f['periodo'], $idEmpresa);
                if ($pendiente) {
                    $this->repo->cambiarMonto((int) $pendiente['id'], (float) $pendiente['monto'] + (float) $f['monto'], $idUsuario);
                    $this->repo->darDeBaja((int) $f['id'], $idUsuario);
                } else {
                    $this->repo->cambiarEstado((int) $f['id'], 'pendiente', $idUsuario, null);
                }
                $n++;
            }
            $this->confirmar($propia);
            return $n;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * La factura/recibo se anula o se elimina (su asiento ya se anuló, o se anula en la misma
     * transacción):
     *   - lo diferido por devengar queda «anulado» (el asiento anulado ya revierte ese pasivo);
     *   - lo YA devengado se revierte con un asiento: DEBE ingreso / HABER Ingresos diferidos,
     *     con la fecha de hoy (anular el asiento de la factura devolvió todo el diferido, incluida
     *     la parte que el devengo mensual ya había pasado al ingreso);
     *   - las provisiones de mes caído que este documento cancelaba vuelven a «por facturar».
     *
     * @return array{anuladas:int, revertido:float, id_asiento:?int, provisiones:int}
     */
    public function anularDocumento(int $idEmpresa, int $idUsuario, string $tipoDocumento, int $idDocumento): array
    {
        $vacio = ['anuladas' => 0, 'revertido' => 0.0, 'id_asiento' => null, 'provisiones' => 0];
        if ($idDocumento <= 0 || !in_array($tipoDocumento, ['factura', 'recibo'], true) || !$this->repo->disponible()) {
            return $vacio;
        }
        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_doc:' . $tipoDocumento . ':' . $idDocumento);
            $pendientes  = $this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'diferido', ['pendiente']);
            $devengadas  = $this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'diferido', ['devengado']);
            $provisiones = $this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'provision', ['facturado']);
            if (!$pendientes && !$devengadas && !$provisiones) {
                $this->confirmar($propia);
                return $vacio;
            }

            $idAsiento = null;
            // Filas de apertura: su pasivo no lo creó el asiento de la factura (ver
            // generarAsientoReversoDevengoSuscripciones): las devengadas no se tocan y las
            // pendientes se revierten al revés.
            $esApertura   = fn(array $f): bool => ($f['origen'] ?? 'documento') === 'apertura';
            $devNormales  = array_values(array_filter($devengadas, fn($f) => !$esApertura($f)));
            $apPendientes = array_values(array_filter($pendientes, $esApertura));
            $revertido = round(array_sum(array_column($devNormales, 'monto')) + array_sum(array_column($apPendientes, 'monto')), 2);
            if ($revertido > 0) {
                $hoy = date('Y-m-d');
                $this->validarPeriodoContable($hoy, $idEmpresa,
                    'No se puede revertir el ingreso ya devengado del documento: el período contable de hoy está cerrado.');
                $detalles = (new AsientoBuilderService())->generarAsientoReversoDevengoSuscripciones($idEmpresa, $devNormales, $apPendientes);
                $asientoService = new AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    $this->log
                );
                $nombreDoc = $tipoDocumento === 'recibo' ? 'recibo' : 'factura';
                $idAsiento = $asientoService->guardarAsiento([
                    'id'                   => null,
                    'fecha_asiento'        => $hoy,
                    'tipo_comprobante'     => 'diario',
                    'numero_comprobante'   => '',
                    'concepto'             => "Reverso del ingreso devengado de suscripción ({$nombreDoc} anulada/eliminada #{$idDocumento})",
                    'estado'               => 'contabilizado',
                    'modulo_origen'        => 'suscripcion_devengo_reverso',
                    'id_referencia_origen' => $idDocumento,
                    'observaciones'        => null,
                ], array_map(fn($d) => $d + ['documento_referencia' => "Reverso devengo {$nombreDoc} #{$idDocumento}"], $detalles), $idEmpresa, $idUsuario);
            }

            foreach (array_merge($pendientes, $devengadas) as $f) {
                $this->repo->cambiarEstado((int) $f['id'], 'anulado', $idUsuario, $f['id_nota_credito'] !== null ? (int) $f['id_nota_credito'] : null);
            }
            foreach ($provisiones as $p) {
                $this->repo->desvincularProvision((int) $p['id'], $idUsuario);
            }

            $res = [
                'anuladas'    => count($pendientes) + count($devengadas),
                'revertido'   => $revertido,
                'id_asiento'  => $idAsiento,
                'provisiones' => count($provisiones),
            ];
            $this->log->registrar($idUsuario, $idEmpresa, 'anular_documento_devengo', 'suscripciones_devengos', $idDocumento, null,
                $res + ['tipo_documento' => $tipoDocumento]);
            $this->confirmar($propia);
            return $res;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /**
     * La factura/recibo en borrador se modificó (sus líneas se reemplazan): el cronograma se
     * rehace con las líneas nuevas. Si ya hay meses devengados de este documento, no se puede
     * (habría que revertir primero el devengo de esos meses).
     */
    public function rehacerCronogramaDocumento(int $idEmpresa, int $idUsuario, string $tipoDocumento, int $idDocumento): int
    {
        if ($idDocumento <= 0 || !in_array($tipoDocumento, ['factura', 'recibo'], true) || !$this->repo->disponible()) {
            return 0;
        }
        $origen = $this->repo->getOrigenDocumento($tipoDocumento, $idDocumento, $idEmpresa);
        if (!$origen || empty($origen['servicio_desde'])) {
            return 0; // no lo generó una suscripción (o es anterior al devengado)
        }
        $propia = $this->abrirTransaccion();
        try {
            $this->repo->bloquear('susc_devengo_doc:' . $tipoDocumento . ':' . $idDocumento);
            if ($this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'diferido', ['devengado'])) {
                throw new \DomainException('Este documento ya tiene meses de ingreso devengados (Suscripciones → Devengar mes). Revierta esos meses antes de modificarlo.');
            }
            foreach ($this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'diferido', ['pendiente', 'anulado']) as $f) {
                $this->repo->darDeBaja((int) $f['id'], $idUsuario);
            }
            foreach ($this->repo->getFilasDocumento($tipoDocumento, $idDocumento, $idEmpresa, 'provision', ['facturado']) as $p) {
                $this->repo->desvincularProvision((int) $p['id'], $idUsuario);
            }

            $fecha = $this->repo->getFechaEmisionDocumento($tipoDocumento, $idDocumento, $idEmpresa) ?? date('Y-m-d');
            $res = $tipoDocumento === 'recibo' ? ['id_factura' => null, 'id_recibo' => $idDocumento] : ['id_factura' => $idDocumento];
            $n = $this->crearCronogramaDocumento($idEmpresa, $idUsuario, $origen, (int) $origen['id_pago'], $res, (string) $origen['servicio_desde'], $fecha);
            $this->confirmar($propia);
            return $n;
        } catch (\Throwable $e) {
            $this->deshacer($propia);
            throw $e;
        }
    }

    /** ¿Encendido en Configuración Contable → «Módulos que contabilizan»? (por defecto, sí). */
    private function contabiliza(int $idEmpresa): bool
    {
        try {
            return ContabilidadInterruptorService::crear()->contabiliza($idEmpresa, 'suscripciones_devengo');
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Filas en el formato de AsientoBuilderService::generarAsientoDevengoSuscripciones(). */
    private function filasParaAsiento(array $diferidos, array $provisiones): array
    {
        $filas = [];
        foreach ($diferidos as $d) {
            $filas['d' . $d['id']] = [
                'tipo'         => 'diferido',
                'monto'        => $d['monto'],
                'id_producto'  => $d['id_producto'] !== null ? (int) $d['id_producto'] : null,
                'id_cliente'   => (int) $d['id_cliente'],
                'tipo_asiento' => $d['tipo_documento'] === 'recibo' ? 'recibos_venta' : 'ventas_factura',
                'descripcion'  => $d['descripcion'],
            ];
        }
        foreach ($provisiones as $k => $p) {
            $filas['p' . $k] = [
                'tipo'         => 'provision',
                'monto'        => $p['monto'],
                'id_producto'  => $p['id_producto'],
                'id_cliente'   => $p['id_cliente'],
                'tipo_asiento' => $p['tipo_asiento'],
                'descripcion'  => $p['descripcion'],
            ];
        }
        return $filas;
    }

    /** 'YYYY-MM' → primer día del mes. No se devenga un mes futuro. */
    private function validarMes(string $mes): \DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw new \InvalidArgumentException('Elija un mes válido.');
        }
        $periodo = new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $m[1], (int) $m[2]));
        if ($periodo > new \DateTimeImmutable('first day of this month')) {
            throw new \InvalidArgumentException('No se puede devengar un mes futuro.');
        }
        return $periodo;
    }

    private function nombreMes(\DateTimeImmutable $periodo): string
    {
        $nombres = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $nombres[(int) $periodo->format('n')] . ' ' . $periodo->format('Y');
    }

    /** Cronograma de una suscripción (pestaña «Devengo»). Vacío si aún no se aplicó el SQL. */
    public function getPorSuscripcion(int $idSuscripcion, int $idEmpresa): array
    {
        return $this->repo->disponible() ? $this->repo->getPorSuscripcion($idSuscripcion, $idEmpresa) : [];
    }

    /**
     * Reparte $monto en $n partes iguales redondeadas a centavos; la última absorbe la diferencia
     * para que la suma sea exactamente el monto.
     *
     * @return float[]
     */
    public static function repartirEnMeses(float $monto, int $n): array
    {
        $n = max(1, $n);
        $monto = round($monto, 2);
        $parte = round($monto / $n, 2);
        $partes = array_fill(0, $n, $parte);
        $partes[$n - 1] = round($monto - $parte * ($n - 1), 2);
        return $partes;
    }

    private function restarPeriodo(\DateTimeImmutable $fecha, int $meses, string $codigo): \DateTimeImmutable
    {
        if (isset(self::CODIGOS_POR_DIAS[$codigo])) {
            return $fecha->modify('-' . self::CODIGOS_POR_DIAS[$codigo] . ' days');
        }
        // Sin desbordar fin de mes: 31-mar menos 1 mes es 28/29-feb, no 3-mar.
        $mes = $fecha->modify('first day of this month')->modify('-' . max(1, $meses) . ' months');
        $dia = min((int) $fecha->format('j'), (int) $mes->format('t'));
        return $mes->setDate((int) $mes->format('Y'), (int) $mes->format('n'), $dia);
    }
}

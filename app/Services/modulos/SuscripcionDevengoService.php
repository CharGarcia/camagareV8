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
            || ($susc['modalidad_cobro'] ?? 'anticipado') !== 'anticipado'
            || isset(self::CODIGOS_POR_DIAS[(string) ($susc['periodicidad_codigo'] ?? '')])
            || !$this->repo->disponible()) {
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

        $this->repo->beginTransaction();
        try {
            // Un documento, un cronograma: si la generación se reintenta, no se duplica.
            $this->repo->bloquear('susc_devengo_doc:' . $tipoDoc . ':' . $idDoc);
            if ($this->repo->existeCronogramaDocumento($tipoDoc, $idDoc, $idEmpresa)) {
                $this->repo->commit();
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

            $this->repo->commit();
            return count($filas);
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
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

<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Rules\modulos\IngresoRules;
use App\repositories\modulos\IngresoRepository;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use PDO;

/**
 * Generación automática del ingreso (cobro) de una venta a partir de la
 * configuración de la pestaña Cobros del cliente.
 *
 * Se usa desde dos flujos:
 *  1. En caliente, cuando el SRI autoriza una factura de venta
 *     (App\Services\Sri\SriEnvioService).
 *  2. En lote y hacia atrás, desde el botón "Generar cobros pendientes" del
 *     modal de clientes, para los documentos que quedaron sin cobro.
 *
 * Ambos flujos cobran el SALDO REAL del documento —neto de cobros previos,
 * retenciones de venta y notas de crédito—, no el importe total: la fuente es
 * IngresoRepository::getFacturasPendientes(), la misma consulta que alimenta el
 * selector de documentos del módulo Ingresos.
 *
 * Es idempotente: un documento sin saldo pendiente simplemente no aparece, así
 * que llamar dos veces no duplica cobros. Eso también evita pisar el ingreso que
 * el POS ya genera en su propio flujo.
 *
 * Nota sobre cheques: a diferencia de los pagos a proveedores, aquí el cheque lo
 * entrega el cliente y su número viene impreso en el documento físico, por lo que
 * NO se autonumera; el cobro se registra sin número de cheque y con la fecha de
 * cobro diferida por los días de crédito del cliente.
 */
class CobroAutomaticoClienteService
{
    /** Tipos de documento que este servicio cobra (SALDO_INICIAL queda fuera a propósito). */
    private const TIPOS_COBRABLES = ['FACTURA', 'RECIBO'];

    private PDO $db;
    private IngresoRepository $ingresoRepo;

    public function __construct()
    {
        $this->db          = Database::getConnection();
        $this->ingresoRepo = new IngresoRepository();
    }

    // ─────────────────────────────────────────────────────────────────────
    // CONFIGURACIÓN
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resuelve y valida la configuración de cobro automático del cliente.
     *
     * @return array{ok:bool, motivo:?string, config:?array}
     */
    public function getConfiguracion(int $idCliente, int $idEmpresa): array
    {
        $st = $this->db->prepare("
            SELECT id_forma_cobro_predeterminada, tipo_operacion_bancaria_predeterminada,
                   monto_minimo_auto_cobro, monto_maximo_auto_cobro,
                   plazo, id_ingreso_concepto_predeterminado, nombre
            FROM clientes
            WHERE id = ? AND id_empresa = ? AND eliminado = false
            LIMIT 1
        ");
        $st->execute([$idCliente, $idEmpresa]);
        $cli = $st->fetch(PDO::FETCH_ASSOC);

        if (!$cli || empty($cli['id_forma_cobro_predeterminada'])) {
            return ['ok' => false, 'motivo' => null, 'config' => null]; // Sin configurar: se omite en silencio
        }

        // La forma de cobro debe seguir activa, igual que exige el flujo manual.
        $stFc = $this->db->prepare("
            SELECT 1 FROM empresa_formas_pago
            WHERE id = ? AND id_empresa = ? AND activo = TRUE AND eliminado = FALSE
            LIMIT 1
        ");
        $stFc->execute([(int) $cli['id_forma_cobro_predeterminada'], $idEmpresa]);
        if (!$stFc->fetchColumn()) {
            return ['ok' => false, 'motivo' => 'La forma de cobro predeterminada del cliente está inactiva o fue eliminada.', 'config' => null];
        }

        return [
            'ok'     => true,
            'motivo' => null,
            'config' => [
                'id_forma_cobro'      => (int) $cli['id_forma_cobro_predeterminada'],
                'tipo_operacion'      => !empty($cli['tipo_operacion_bancaria_predeterminada']) ? (string) $cli['tipo_operacion_bancaria_predeterminada'] : null,
                'monto_minimo'        => !empty($cli['monto_minimo_auto_cobro']) ? (float) $cli['monto_minimo_auto_cobro'] : null,
                'monto_maximo'        => !empty($cli['monto_maximo_auto_cobro']) ? (float) $cli['monto_maximo_auto_cobro'] : null,
                'plazo'               => (int) ($cli['plazo'] ?? 0),
                'id_ingreso_concepto' => !empty($cli['id_ingreso_concepto_predeterminado']) ? (int) $cli['id_ingreso_concepto_predeterminado'] : null,
                'nombre_cliente'      => (string) ($cli['nombre'] ?? ''),
            ],
        ];
    }

    /** Comprueba el rango de monto configurado. Devuelve null si pasa, o el motivo. */
    public function validarRango(array $config, float $monto): ?string
    {
        $min = $config['monto_minimo'];
        $max = $config['monto_maximo'];

        if ($min !== null && $min > 0.001 && $monto < ($min - 0.001)) {
            return '$' . number_format($monto, 2) . ' no alcanza el mínimo de $' . number_format($min, 2);
        }
        if ($max !== null && $max > 0.001 && $monto > ($max + 0.001)) {
            return '$' . number_format($monto, 2) . ' supera el límite de $' . number_format($max, 2);
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // DOCUMENTOS PENDIENTES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Documentos del cliente con saldo pendiente, emitidos hasta $fechaHasta.
     *
     * Reutiliza la consulta del módulo Ingresos, que ya descuenta cobros previos,
     * retenciones de venta y notas de crédito, y filtra por el ambiente activo.
     * Se limita a facturas y recibos de venta.
     */
    public function getDocumentosPendientes(int $idCliente, int $idEmpresa, string $fechaHasta): array
    {
        $docs = $this->ingresoRepo->getFacturasPendientes($idCliente, $idEmpresa);

        return array_values(array_filter($docs, function (array $d) use ($fechaHasta): bool {
            if (!in_array($d['tipo_documento'], self::TIPOS_COBRABLES, true)) {
                return false;
            }
            return substr((string) $d['fecha_emision'], 0, 10) <= $fechaHasta;
        }));
    }

    /**
     * Clasifica los documentos pendientes entre los que se cobrarían y los que
     * quedan fuera por el rango de monto configurado.
     *
     * @return array{ok:bool, motivo:?string, elegibles:array, excluidas:array, total:float, config:?array}
     */
    public function previsualizarPendientes(int $idCliente, int $idEmpresa, string $fechaHasta): array
    {
        $cfg = $this->getConfiguracion($idCliente, $idEmpresa);
        if (!$cfg['ok']) {
            return [
                'ok'        => false,
                'motivo'    => $cfg['motivo'] ?? 'El cliente no tiene una forma de cobro predeterminada configurada.',
                'elegibles' => [],
                'excluidas' => [],
                'total'     => 0.0,
                'config'    => null,
            ];
        }

        $elegibles = [];
        $excluidas = [];
        $total     = 0.0;

        foreach ($this->getDocumentosPendientes($idCliente, $idEmpresa, $fechaHasta) as $d) {
            $saldo = round((float) $d['saldo_pendiente'], 2);
            if ($saldo <= 0.001) {
                continue;
            }

            $motivoRango = $this->validarRango($cfg['config'], $saldo);
            if ($motivoRango !== null) {
                $d['motivo'] = $motivoRango . '.';
                $excluidas[] = $d;
                continue;
            }

            $d['monto_a_cobrar'] = $saldo;
            $elegibles[] = $d;
            $total += $saldo;
        }

        return [
            'ok'        => true,
            'motivo'    => null,
            'elegibles' => $elegibles,
            'excluidas' => $excluidas,
            'total'     => round($total, 2),
            'config'    => $cfg['config'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // GENERACIÓN
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra el ingreso que cobra un documento. Lanza excepción si falla.
     *
     * @return array{id_ingreso:int, numero_ingreso:string, fecha_cobro:string}
     */
    public function generarParaDocumento(
        array $config,
        string $tipoDocumento,
        int $idDocumento,
        string $numeroDocumento,
        string $fechaEmisionDoc,
        float $importeTotal,
        float $montoACobrar,
        int $idCliente,
        int $idEmpresa,
        int $idUsuario,
        string $observaciones
    ): array {
        $punto = $this->resolverPuntoEmision($tipoDocumento, $idDocumento, $idEmpresa);
        if (!$punto) {
            throw new \Exception('No se localizó punto de emisión activo.');
        }

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (IngresoService::crear()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
        $resSec = (new SecuencialService())->obtenerSiguienteSecuencial((int) $punto['id_punto'], 'Ingresos');
        if (empty($resSec['secuencial'])) {
            throw new \Exception('Error al reservar secuencial correlativo.');
        }
        $secuencial    = $resSec['formateado'] ?? str_pad((string) $resSec['secuencial'], 9, '0', STR_PAD_LEFT);
        $numeroIngreso = "{$punto['estab']}-{$punto['punto']}-{$secuencial}";

        // La fecha de cobro se difiere por los días de crédito del cliente.
        $fechaCobro = $this->calcularFechaCobroCredito($fechaEmisionDoc, (int) ($config['plazo'] ?? 0));

        $payload = [
            'id_empresa'         => $idEmpresa,
            'id_usuario'         => $idUsuario,
            'id_establecimiento' => (int) $punto['id_estab'],
            'id_punto_emision'   => (int) $punto['id_punto'],
            'id_cliente'         => $idCliente,
            'fecha_emision'      => $fechaEmisionDoc,
            'establecimiento'    => (string) $punto['estab'],
            'punto_emision'      => (string) $punto['punto'],
            'secuencial'         => $secuencial,
            'numero_ingreso'     => $numeroIngreso,
            'tipo_ingreso'       => $tipoDocumento === 'FACTURA' ? 'FACTURA_VENTA' : 'RECIBO_VENTA',
            'id_ingreso_concepto' => $config['id_ingreso_concepto'],
            'monto_total'        => $montoACobrar,
            'observaciones'      => $observaciones,
            'estado'             => 'registrado',
            'recibo_de'          => $config['nombre_cliente'] ?? '',
            'id_recibo_cliente'  => $idCliente,
            'detalles'           => [
                [
                    'tipo_documento'          => $tipoDocumento,
                    'id_referencia_documento' => $idDocumento,
                    'numero_documento'        => $numeroDocumento,
                    'descripcion'             => 'Cobro de ' . ($tipoDocumento === 'FACTURA' ? 'Factura' : 'Recibo') . ' #' . $numeroDocumento,
                    'monto_documento'         => $importeTotal,
                    'saldo_anterior'          => $montoACobrar,
                    'monto_cobrado'           => $montoACobrar,
                    'saldo_actual'            => 0.0,
                ],
            ],
            'pagos' => [
                [
                    'id_forma_cobro'          => $config['id_forma_cobro'],
                    'monto'                   => $montoACobrar,
                    'tipo_operacion_bancaria' => $config['tipo_operacion'],
                    // El número de cheque lo trae el documento físico del cliente:
                    // no hay consecutivo propio que asignar automáticamente.
                    'numero_cheque'           => null,
                    'fecha_cobro'             => $fechaCobro,
                ],
            ],
        ];

        $ingresoService = new IngresoService($this->ingresoRepo, new IngresoRules(), new LogSistemaService());
        $idIngreso = $ingresoService->crear($payload);

        if ($managedTransaction) {
            $db->commit();
        }

        return [
            'id_ingreso'     => $idIngreso,
            'numero_ingreso' => $numeroIngreso,
            'fecha_cobro'    => $fechaCobro,
        ];
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cobro automático de una factura recién autorizada por el SRI.
     *
     * Nunca lanza: un fallo aquí no debe tumbar la autorización de la factura.
     * Devuelve un texto para el log, o cadena vacía si no aplicaba.
     */
    public function generarCobroFacturaAutorizada(int $idVenta, int $idEmpresa, int $idUsuario): string
    {
        try {
            $st = $this->db->prepare("
                SELECT id_cliente, fecha_emision, importe_total,
                       CONCAT(establecimiento,'-',punto_emision,'-',secuencial) AS numero_documento
                FROM ventas_cabecera
                WHERE id = ? AND id_empresa = ? AND eliminado = false
                LIMIT 1
            ");
            $st->execute([$idVenta, $idEmpresa]);
            $venta = $st->fetch(PDO::FETCH_ASSOC);
            if (!$venta || empty($venta['id_cliente'])) {
                return '';
            }

            $idCliente = (int) $venta['id_cliente'];
            $cfg = $this->getConfiguracion($idCliente, $idEmpresa);
            if (!$cfg['ok']) {
                return $cfg['motivo'] === null ? '' : 'Cobro automático omitido: ' . $cfg['motivo'];
            }

            // El saldo real sale de la misma consulta del módulo Ingresos: si la
            // factura ya está cobrada (por ejemplo por el POS) no aparecerá aquí.
            $doc = null;
            foreach ($this->ingresoRepo->getFacturasPendientes($idCliente, $idEmpresa) as $d) {
                if ($d['tipo_documento'] === 'FACTURA' && (int) $d['id'] === $idVenta) {
                    $doc = $d;
                    break;
                }
            }
            if (!$doc) {
                return ''; // Sin saldo pendiente: nada que cobrar
            }

            $saldo = round((float) $doc['saldo_pendiente'], 2);
            $motivoRango = $this->validarRango($cfg['config'], $saldo);
            if ($motivoRango !== null) {
                return 'Cobro omitido (' . $motivoRango . ').';
            }

            $numDoc = (string) $doc['numero_documento'];
            $res = $this->generarParaDocumento(
                $cfg['config'],
                'FACTURA',
                $idVenta,
                $numDoc,
                (string) $venta['fecha_emision'],
                (float) $venta['importe_total'],
                $saldo,
                $idCliente,
                $idEmpresa,
                $idUsuario,
                'Cobro generado automáticamente al autorizar la Factura #' . $numDoc . '.'
            );

            return 'Cobro automático generado: ' . $res['numero_ingreso'] . '.';
        } catch (\Throwable $e) {
            error_log('[CobroAutomatico] Factura #' . $idVenta . ': ' . $e->getMessage());
            return 'Fallo al generar el cobro automático: ' . $e->getMessage();
        }
    }

    /**
     * Genera los cobros pendientes del cliente hasta la fecha indicada.
     *
     * Cada ingreso se registra por separado (IngresoService maneja su propia
     * transacción y su asiento), de modo que un documento que falle no impide
     * los demás.
     *
     * @return array{ok:bool, motivo:?string, generados:int, fallidos:array, omitidas:int, total:float}
     */
    public function generarPendientes(int $idCliente, int $idEmpresa, int $idUsuario, string $fechaHasta): array
    {
        $prev = $this->previsualizarPendientes($idCliente, $idEmpresa, $fechaHasta);
        if (!$prev['ok']) {
            return ['ok' => false, 'motivo' => $prev['motivo'], 'generados' => 0, 'fallidos' => [], 'omitidas' => 0, 'total' => 0.0];
        }

        $config    = $prev['config'];
        $generados = 0;
        $total     = 0.0;
        $fallidos  = [];

        foreach ($prev['elegibles'] as $d) {
            $numDoc = (string) $d['numero_documento'];
            try {
                $this->generarParaDocumento(
                    $config,
                    (string) $d['tipo_documento'],
                    (int) $d['id'],
                    $numDoc,
                    substr((string) $d['fecha_emision'], 0, 10),
                    (float) $d['importe_total'],
                    (float) $d['monto_a_cobrar'],
                    $idCliente,
                    $idEmpresa,
                    $idUsuario,
                    'Cobro generado desde la configuración de cobros del cliente para el documento #' . $numDoc . '.'
                );
                $generados++;
                $total += (float) $d['monto_a_cobrar'];
            } catch (\Throwable $e) {
                $fallidos[] = ['numero_documento' => $numDoc, 'error' => $e->getMessage()];
            }
        }

        return [
            'ok'        => true,
            'motivo'    => null,
            'generados' => $generados,
            'fallidos'  => $fallidos,
            'omitidas'  => count($prev['excluidas']),
            'total'     => round($total, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // AUXILIARES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Punto de emisión del ingreso: el mismo del documento que se cobra, para
     * que el cobro se emita donde se emitió la venta. Si no se puede resolver,
     * cae al primer punto activo de la empresa.
     */
    private function resolverPuntoEmision(string $tipoDocumento, int $idDocumento, int $idEmpresa): ?array
    {
        $tabla = $tipoDocumento === 'RECIBO' ? 'recibos_venta_cabecera' : 'ventas_cabecera';

        $st = $this->db->prepare("
            SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
            FROM {$tabla} d
            JOIN empresa_punto_emision pe    ON pe.id = d.id_punto_emision AND pe.eliminado = FALSE
            JOIN empresa_establecimiento es  ON es.id = pe.id_establecimiento AND es.eliminado = FALSE
            WHERE d.id = ? AND d.id_empresa = ?
            LIMIT 1
        ");
        $st->execute([$idDocumento, $idEmpresa]);
        $punto = $st->fetch(PDO::FETCH_ASSOC);
        if ($punto) {
            return $punto;
        }

        $stF = $this->db->prepare("
            SELECT pe.id AS id_punto, pe.codigo_punto AS punto, es.id AS id_estab, es.codigo AS estab
            FROM empresa_punto_emision pe
            JOIN empresa_establecimiento es ON pe.id_establecimiento = es.id
            WHERE es.id_empresa = ? AND pe.eliminado = FALSE AND es.eliminado = FALSE
            ORDER BY es.codigo ASC, pe.codigo_punto ASC LIMIT 1
        ");
        $stF->execute([$idEmpresa]);
        return $stF->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Fecha de cobro = fecha de emisión del documento + días de crédito del cliente. */
    public function calcularFechaCobroCredito(string $fechaEmision, int $plazo): string
    {
        if ($plazo <= 0) {
            return $fechaEmision;
        }
        $ts = strtotime("+{$plazo} days", strtotime($fechaEmision));
        return $ts !== false ? date('Y-m-d', $ts) : $fechaEmision;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\ChequePosfechadoRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;

/**
 * Cheques posfechados con cuenta puente (Configuración Contable → Cobros y Pagos):
 *
 *  - Ingreso con cheque recibido posfechado: Debe «Cheques posfechados por cobrar» / Haber CxC
 *    (lo arma AsientoBuilderService::lineasFormas con aplicaPuente()).
 *  - Al registrar su Fecha Banco en Control Bancario: asiento de cobro con esa fecha,
 *    Debe Bancos / Haber «Cheques posfechados por cobrar». En egresos, el espejo con
 *    «Cheques posfechados por pagar».
 *
 * Posfechado = fecha del cheque posterior a la fecha del ingreso/egreso (mismo criterio que
 * Control Bancario). Solo aplica a documentos con fecha desde el día en que la empresa asignó la
 * cuenta puente: lo anterior se ajusta a mano.
 *
 * El asiento de cobro se liga a la anotación de Control Bancario
 * (control_bancario_movimientos.id_asiento_cobro; modulo_origen 'cobro_cheque', id = anotación).
 */
class ChequePosfechadoService
{
    public const MODULO_ORIGEN = 'cobro_cheque';

    private ChequePosfechadoRepository $repo;
    private LogSistemaService $logService;

    public function __construct(?ChequePosfechadoRepository $repo = null, ?LogSistemaService $logService = null)
    {
        $this->repo       = $repo ?? new ChequePosfechadoRepository();
        $this->logService = $logService ?? new LogSistemaService();
    }

    /**
     * ¿Esta línea de cobro/pago va a la cuenta puente en vez de a Bancos?
     *
     * @param array|null  $puente      ChequePosfechadoRepository::getCuentaPuente()
     * @param string|null $tipoOp      tipo_operacion_bancaria del pago
     * @param string|null $tipoForma   empresa_formas_pago.tipo (respaldo cuando el pago no dice la operación)
     * @param string|null $fechaCheque fecha_cobro del pago (fecha girada en el cheque)
     * @param string|null $fechaDoc    fecha_emision del ingreso/egreso
     */
    public static function aplicaPuente(?array $puente, ?string $tipoOp, ?string $tipoForma, ?string $fechaCheque, ?string $fechaDoc): bool
    {
        if ($puente === null || empty($fechaCheque) || empty($fechaDoc)) {
            return false;
        }
        $op = strtoupper(trim((string) $tipoOp));
        $esCheque = $op !== '' ? $op === 'CHEQUE' : strtoupper(trim((string) $tipoForma)) === 'CHEQUE';
        $fechaDoc    = substr($fechaDoc, 0, 10);
        $fechaCheque = substr($fechaCheque, 0, 10);
        return $esCheque && $fechaCheque > $fechaDoc && $fechaDoc >= (string) $puente['desde'];
    }

    public function getCuentaPuente(int $idEmpresa, string $flujo): ?array
    {
        return $this->repo->getCuentaPuente($idEmpresa, $flujo);
    }

    /**
     * Deja el asiento de cobro de una anotación de Control Bancario como corresponde:
     * lo crea o actualiza si el cheque es posfechado con cuenta puente y tiene Fecha Banco; si
     * no (Fecha Banco quitada, documento anulado, cheque anulado…), anula el que hubiera.
     *
     * Se llama dentro de la transacción de Control Bancario: si el asiento no se puede guardar
     * (período cerrado, cuenta bancaria sin configurar) la Fecha Banco tampoco se guarda.
     */
    public function sincronizarCobro(int $idEmpresa, int $idMovimiento, int $idUsuario): void
    {
        $m = $this->repo->getMovimiento($idEmpresa, $idMovimiento);
        if ($m === null) {
            return;
        }
        $flujo     = (string) $m['origen_tipo'];
        $idAsiento = (int) ($m['id_asiento_cobro'] ?? 0);
        $puente    = $this->repo->getCuentaPuente($idEmpresa, $flujo);

        $vivo = !self::esVerdadero($m['movimiento_eliminado'])
            && !empty($m['fecha_banco'])
            && !self::esVerdadero($m['documento_eliminado'])
            && strtolower(trim((string) $m['documento_estado'])) !== 'anulado'
            && !self::esVerdadero($m['pago_eliminado'])
            && strtolower(trim((string) ($m['estado_cheque'] ?? 'vigente'))) !== 'anulado'
            && self::aplicaPuente($puente, $m['tipo_operacion_bancaria'], $m['forma_tipo'], $m['fecha_cobro'], $m['fecha_emision']);

        $asientoService = $this->asientoService();

        if (!$vivo) {
            if ($idAsiento > 0) {
                $asientoService->anularDeDocumento($idAsiento, $idEmpresa, $idUsuario, 'del cobro del cheque');
                $this->repo->setAsientoCobro($idMovimiento, null);
            }
            return;
        }

        // El documento ya tenía un asiento de cobro anulado (Fecha Banco quitada y vuelta a
        // poner): se crea uno nuevo; getAsientoPorOrigen() solo devuelve asientos vivos.
        $previo = $asientoService->getAsientoPorOrigen(self::MODULO_ORIGEN, $idMovimiento, $idEmpresa);
        $idPrevio = $previo ? (int) $previo['id'] : 0;

        if (empty($m['id_cuenta_banco'])) {
            throw new \Exception(sprintf(
                'La forma de %s «%s» no tiene cuenta contable: no se puede registrar el cobro del cheque. '
                . 'Asígnela en Configuración Contable → Cobros y Pagos.',
                $flujo === 'ingreso' ? 'cobro' : 'pago',
                (string) $m['forma_nombre']
            ));
        }

        $monto   = round((float) $m['monto'], 2);
        $numCh   = trim((string) ($m['numero_cheque'] ?? ''));
        $textoCh = 'cheque #' . ($numCh !== '' ? $numCh : '?');
        $numDoc  = (string) ($m['numero'] ?? $m['id_documento']);
        $numCorto = AsientoBuilderService::secuencialCorto($numDoc);
        $docTxt  = ($flujo === 'ingreso' ? 'Ingreso ' : 'Egreso ') . ($numCorto !== '' ? $numCorto : $numDoc);

        [$idEntidad, $tipoEntidad] = $this->entidad($m, $flujo);
        $linea = fn(int $idCuenta, float $debe, float $haber, string $ref) => [
            'id_cuenta_contable'   => $idCuenta,
            'debe'                 => $debe,
            'haber'                => $haber,
            'referencia_detalle'   => $ref,
            'documento_referencia' => mb_substr("{$docTxt} · {$ref}", 0, 100),
            'id_entidad'           => $idEntidad,
            'tipo_entidad'         => $tipoEntidad,
        ];

        if ($flujo === 'ingreso') {
            $detalles = [
                $linea((int) $m['id_cuenta_banco'], $monto, 0.0, "Cobro de {$textoCh} posfechado: " . $m['forma_nombre']),
                $linea((int) $puente['id_cuenta'], 0.0, $monto, "Cheque posfechado cobrado ({$textoCh})"),
            ];
            $concepto = "Cobro de {$textoCh} posfechado recibido · {$docTxt}";
        } else {
            $detalles = [
                $linea((int) $puente['id_cuenta'], $monto, 0.0, "Cheque posfechado cobrado ({$textoCh})"),
                $linea((int) $m['id_cuenta_banco'], 0.0, $monto, "Pago de {$textoCh} posfechado: " . $m['forma_nombre']),
            ];
            $concepto = "Cobro de {$textoCh} posfechado emitido · {$docTxt}";
        }

        $idNuevo = $asientoService->guardarAsiento([
            'id'                   => $idPrevio > 0 ? $idPrevio : null,
            'fecha_asiento'        => substr((string) $m['fecha_banco'], 0, 10),
            'tipo_comprobante'     => 'cheques',
            'numero_comprobante'   => '',
            'concepto'             => $concepto,
            'estado'               => 'contabilizado',
            'modulo_origen'        => self::MODULO_ORIGEN,
            'id_referencia_origen' => $idMovimiento,
            'observaciones'        => null,
        ], $detalles, $idEmpresa, $idUsuario);

        if ($idNuevo !== $idAsiento) {
            $this->repo->setAsientoCobro($idMovimiento, $idNuevo);
        }
    }

    /**
     * Para el sincronizador de asientos: genera el asiento de cobro que falte. Toma empresa y
     * usuario de la anotación y propaga el error para que se informe como pendiente.
     */
    public function procesarAsientoContablePorSincronizacion(int $idMovimiento): void
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT id_empresa, COALESCE(updated_by, created_by) AS id_usuario FROM control_bancario_movimientos WHERE id = ?");
        $st->execute([$idMovimiento]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $this->sincronizarCobro((int) $row['id_empresa'], $idMovimiento, (int) ($row['id_usuario'] ?? 0));
    }

    /**
     * Anula los asientos de cobro de los cheques de un ingreso/egreso que se anula o elimina.
     * Dentro de la transacción del documento: si no se puede (período cerrado), no se anula nada.
     */
    public function anularCobrosDeDocumento(int $idEmpresa, string $flujo, int $idDocumento, int $idUsuario): void
    {
        $asientoService = null;
        foreach ($this->repo->getMovimientosConCobro($idEmpresa, $flujo, $idDocumento) as $mov) {
            $asientoService ??= $this->asientoService();
            $asientoService->anularDeDocumento($mov['id_asiento_cobro'], $idEmpresa, $idUsuario, 'del cobro del cheque');
            $this->repo->setAsientoCobro($mov['id'], null);
        }
    }

    /**
     * Impide editar las formas de cobro/pago de un ingreso/egreso cuyo cheque posfechado ya tiene
     * asiento de cobro: al editar se reemplazan las líneas de pago y ese asiento quedaría huérfano.
     */
    public function validarSinCobrosContabilizados(int $idEmpresa, string $flujo, int $idDocumento): void
    {
        if ($this->repo->getMovimientosConCobro($idEmpresa, $flujo, $idDocumento)) {
            throw new \Exception(
                'Este ' . ($flujo === 'ingreso' ? 'ingreso' : 'egreso') . ' tiene un cheque posfechado ya cobrado '
                . '(con Fecha Banco en Control Bancario). Quite primero la Fecha Banco de ese cheque para poder editarlo.'
            );
        }
    }

    /**
     * Protesto de un cheque RECIBIDO que el banco devolvió: anula el ingreso que lo registró, así
     * la factura (o recibo) vuelve a quedar pendiente en todas las pantallas y su asiento se anula
     * (la cuenta por cobrar vuelve y la cuenta puente se cancela). El cheque queda marcado
     * 'protestado' con fecha y motivo.
     *
     * - Solo si el banco aún no lo cobró (sin Fecha Banco en Control Bancario).
     * - Si el ingreso tiene otras formas de cobro (efectivo + cheque) no se sabe a qué documento
     *   devolver el monto: se pide editar el ingreso a mano.
     * - Anular exige que el mes del ingreso esté abierto (Períodos Contables).
     */
    public function protestarCheque(int $idEmpresa, int $idPago, string $fecha, string $motivo, int $idUsuario): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \Exception('Indique el motivo del protesto.');
        }
        $f = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha) {
            throw new \Exception('Fecha del protesto inválida.');
        }

        $db = \App\core\Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) {
            $db->beginTransaction();
        }
        try {
            $p = $this->repo->getPagoIngresoParaProtesto($idEmpresa, $idPago);
            if ($p === null || self::esVerdadero($p['ingreso_eliminado'])) {
                throw new \Exception('Cheque no encontrado.');
            }
            $op = strtoupper(trim((string) $p['tipo_operacion_bancaria']));
            if (($op !== '' ? $op : strtoupper((string) $p['forma_tipo'])) !== 'CHEQUE') {
                throw new \Exception('El cobro indicado no es un cheque.');
            }
            if (($p['estado_cheque'] ?? 'vigente') === 'protestado') {
                throw new \Exception('Este cheque ya está registrado como protestado.');
            }
            if (strtolower((string) $p['ingreso_estado']) === 'anulado') {
                throw new \Exception('El ingreso de este cheque ya está anulado.');
            }
            if (!empty($p['fecha_banco'])) {
                throw new \Exception('El banco ya cobró este cheque (tiene Fecha Banco en Control Bancario): quite primero la Fecha Banco si en realidad fue devuelto.');
            }
            if ($fecha < substr((string) $p['fecha_emision'], 0, 10)) {
                throw new \Exception('La fecha del protesto no puede ser anterior a la del ingreso.');
            }
            $numIngreso = (string) ($p['numero_ingreso'] ?? $p['id_ingreso']);
            if ((int) $p['formas_con_monto'] > 1) {
                throw new \Exception("El ingreso {$numIngreso} tiene otras formas de cobro además de este cheque, así que no se "
                    . 'puede saber a qué documento devolver el monto. Edite el ingreso: quite el cheque y ajuste lo cobrado de cada documento.');
            }

            $this->repo->marcarProtestado($idPago, $fecha, $motivo, $idUsuario);

            $ingresoService = new IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                $this->logService
            );
            $ingresoService->anular((int) $p['id_ingreso'], $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'PROTESTAR_CHEQUE',
                'ingresos_pagos',
                $idPago,
                ['estado_cheque' => $p['estado_cheque'] ?? 'vigente'],
                [
                    'estado_cheque'   => 'protestado',
                    'fecha_protesto'  => $fecha,
                    'motivo_protesto' => $motivo,
                    'numero_cheque'   => $p['numero_cheque'],
                    'monto'           => $p['monto'],
                    'id_ingreso'      => (int) $p['id_ingreso'],
                    'ingreso'         => $numIngreso,
                ]
            );

            if ($managed) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{0:?int, 1:?string} */
    private function entidad(array $m, string $flujo): array
    {
        if ($flujo === 'ingreso') {
            return !empty($m['id_cliente']) ? [(int) $m['id_cliente'], 'cliente'] : [null, null];
        }
        $sujeto = strtolower((string) ($m['tipo_sujeto'] ?? ''));
        if ($sujeto === 'proveedor' && !empty($m['id_proveedor'])) {
            return [(int) $m['id_proveedor'], 'proveedor'];
        }
        if ($sujeto === 'empleado' && !empty($m['id_empleado'])) {
            return [(int) $m['id_empleado'], 'empleado'];
        }
        return [null, null];
    }

    private static function esVerdadero(mixed $v): bool
    {
        return in_array($v, [true, 't', 'true', 1, '1'], true);
    }

    private function asientoService(): AsientoContableService
    {
        return new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->logService);
    }
}

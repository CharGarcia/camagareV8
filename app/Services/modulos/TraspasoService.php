<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\TraspasoRepository;
use App\repositories\modulos\FormaPagoRepository;
use App\Rules\modulos\TraspasoRules;
use App\Services\LogSistemaService;
use App\core\Database;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;

class TraspasoService
{
    private TraspasoRepository $repository;
    private TraspasoRules $rules;
    private FormaPagoRepository $formaPagoRepository;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;

    /** true si el último registrar() devolvió un traspaso ya creado con la misma clave de formulario. */
    private bool $ultimoGuardadoRepetido = false;

    public function isUltimoGuardadoRepetido(): bool
    {
        return $this->ultimoGuardadoRepetido;
    }

    public function __construct(
        TraspasoRepository $repository,
        TraspasoRules $rules,
        FormaPagoRepository $formaPagoRepository,
        LogSistemaService $logService
    ) {
        $this->repository          = $repository;
        $this->rules                = $rules;
        $this->formaPagoRepository = $formaPagoRepository;
        $this->logService           = $logService;

        // Inicializar manualmente el servicio de periodos (mismo patrón que Egresos/Ingresos)
        $periodosRepo  = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC'): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getPorId($id, $idEmpresa);
    }

    private function validarSecuencial(array $data): void
    {
        if (!empty($data['secuencial']) && !empty($data['id_establecimiento']) && !empty($data['id_punto_emision'])) {
            if ($this->repository->existeSecuencial(
                (int) $data['id_empresa'],
                (int) $data['id_establecimiento'],
                (int) $data['id_punto_emision'],
                (string) $data['secuencial']
            )) {
                throw new \Exception('El número de secuencial de traspaso ya existe en la base de datos.');
            }
        }
    }

    /** Bloquea el traspaso si la forma de origen no tiene saldo suficiente. */
    private function validarSaldoSuficiente(array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idOrigen  = (int) $data['id_forma_origen'];
        $monto     = (float) $data['monto'];

        $saldos      = $this->formaPagoRepository->getSaldosActuales($idEmpresa);
        $saldoOrigen = $saldos[$idOrigen] ?? null;

        if ($saldoOrigen === null) {
            throw new \Exception('No se pudo determinar el saldo de la forma de pago de origen (verifique que no sea de tipo Anticipo o que esté activa).');
        }
        if ($monto > $saldoOrigen + 0.01) {
            throw new \Exception('Saldo insuficiente en la forma de pago de origen. Disponible: $' . number_format($saldoOrigen, 2) . '.');
        }
    }

    public function registrar(array $data): int
    {
        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        $this->ultimoGuardadoRepetido = false;
        $guardado = new \App\Services\GuardadoUnicoService();
        try {
            // 0. Guardado único (CLAUDE.md §8): un reintento del mismo formulario devuelve el
            //    traspaso ya registrado. Va ANTES de validar el secuencial, que si no rechazaría
            //    el reintento con "el secuencial ya existe".
            if ($previo = $guardado->previo($data['token_guardado'] ?? '', (int) $data['id_empresa'], 'traspasos')) {
                if (!$inTrans) $db->rollBack();
                $this->ultimoGuardadoRepetido = true;
                return $previo['id_registro'];
            }

            // 1. Validar reglas de negocio
            $this->rules->validar($data);

            // 2. Validar secuencial duplicado
            $this->validarSecuencial($data);

            // 3. Validar saldo suficiente en la forma de origen
            $this->validarSaldoSuficiente($data);

            // 4. Validar periodo contable
            $this->validarPeriodo($data, 'No se puede registrar el traspaso porque el periodo contable está cerrado.');

            $idTraspaso = $this->repository->insertCabecera($data);

            $this->logService->registrar(
                (int) $data['usuario_id'],
                (int) $data['id_empresa'],
                'CREAR',
                'traspasos_cabecera',
                $idTraspaso,
                null,
                [
                    'monto'            => $data['monto'],
                    'id_forma_origen'  => $data['id_forma_origen'],
                    'id_forma_destino' => $data['id_forma_destino'],
                ]
            );

            $guardado->registrar($data['token_guardado'] ?? '', (int) $data['id_empresa'], 'traspasos', (int) $idTraspaso, (string) ($data['numero_traspaso'] ?? ''), (int) $data['usuario_id']);

            if (!$inTrans) $db->commit();
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Asiento contable fuera de la transacción: un fallo aquí no revierte el traspaso.
        if (!$inTrans) {
            $this->generarAsientoContableSeguro($idTraspaso, $data);
        }

        return $idTraspaso;
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $traspaso = $this->repository->getPorId($id, $idEmpresa);
        if (!$traspaso) throw new \Exception("Documento no encontrado.");
        if ($traspaso['estado'] === 'anulado') throw new \Exception("El traspaso ya está anulado.");

        $this->periodosService->validarFechaPermitida(
            $traspaso['fecha_emision'],
            $idEmpresa,
            'No se puede anular el traspaso porque el periodo contable original está cerrado.'
        );

        $db = Database::getConnection();
        $inTrans = $db->inTransaction();
        if (!$inTrans) $db->beginTransaction();

        try {
            $res = $this->repository->anular($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'traspasos_cabecera',
                $id,
                ['estado' => $traspaso['estado']],
                ['estado' => 'anulado']
            );

            // El asiento se anula en la MISMA transacción: si no se puede, el traspaso tampoco
            // queda anulado (antes quedaba anulado con su asiento vivo y el error solo en el log).
            $this->anularAsientoContable($traspaso, $idEmpresa, $idUsuario);

            if (!$inTrans) $db->commit();
        } catch (\Throwable $e) {
            if (!$inTrans && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $res;
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha     = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);

        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }

    /** Expuesto para verificación rápida desde el controller (AJAX). */
    public function verificarPeriodo(string $fecha, int $idEmpresa): void
    {
        $this->periodosService->validarFechaPermitida(
            $fecha,
            $idEmpresa,
            "La fecha $fecha corresponde a un periodo contable cerrado. No se pueden registrar transacciones en ese periodo."
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    private function asientoContableService(): AsientoContableService
    {
        return new AsientoContableService(
            new AsientoContableRepository(),
            new AsientoContableRules(),
            $this->logService
        );
    }

    /** Devuelve el asiento contable (cabecera + detalles) generado para el traspaso, o null. */
    public function getAsientoContable(int $idTraspaso, int $idEmpresa): ?array
    {
        $asientoService = $this->asientoContableService();
        $previo = $asientoService->getAsientoPorOrigen('traspaso', $idTraspaso, $idEmpresa);
        if (!$previo) {
            $row = $this->repository->getPorId($idTraspaso, $idEmpresa);
            $idAsiento = (int) ($row['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                return $asientoService->getDetalleAsiento($idAsiento, $idEmpresa) ?: null;
            }
            return null;
        }
        $detalle = $asientoService->getDetalleAsiento((int) $previo['id'], $idEmpresa);
        return $detalle ?: null;
    }

    /** Genera el asiento sin propagar errores (lo contable no bloquea lo operativo). */
    private function generarAsientoContableSeguro(int $idTraspaso, array $data): void
    {
        try {
            $this->procesarAsientoContable($idTraspaso, $data);
        } catch (\Throwable $e) {
            error_log('[Traspaso] Asiento no generado para traspaso #' . $idTraspaso . ': ' . $e->getMessage());
        }
    }

    /**
     * Genera (o regenera) y enlaza el asiento contable del traspaso:
     * DEBE = cuenta de la forma destino, HABER = cuenta de la forma origen.
     */
    /**
     * Generación por sincronización (automática al abrir el módulo o manual desde Asientos
     * Contables) de un traspaso registrado que quedó sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idTraspaso): void
    {
        $origen = $this->repository->getOrigenSincronizacion($idTraspaso);
        if (!$origen || ($origen['estado'] ?? '') === 'anulado') {
            return;
        }
        $this->procesarAsientoContable($idTraspaso, [
            'id_empresa' => (int) $origen['id_empresa'],
            'id_usuario' => (int) ($_SESSION['id_usuario'] ?? $origen['created_by'] ?? 0),
        ]);
    }

    public function procesarAsientoContable(int $idTraspaso, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['usuario_id'] ?? $data['id_usuario'] ?? 0);

        // Interruptor por empresa (Configuración Contable → Módulos que contabilizan).
        if (ContabilidadInterruptorService::crear()->omitirGeneracion($idEmpresa, 'traspasos', 'traspaso', $idTraspaso)) {
            return;
        }

        $traspaso = $this->repository->getPorId($idTraspaso, $idEmpresa);
        if (!$traspaso) {
            return;
        }

        $detalles = (new AsientoBuilderService())->generarAsientoTraspaso($idEmpresa, $idTraspaso);
        if (empty($detalles)) {
            return;
        }

        $num = $traspaso['numero_traspaso'] ?? (string) $idTraspaso;
        foreach ($detalles as &$d) {
            $d['documento_referencia'] = 'Traspaso ' . $num;
        }
        unset($d);

        $asientoService = $this->asientoContableService();
        $previo    = $asientoService->getAsientoPorOrigen('traspaso', $idTraspaso, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        $cabecera = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $traspaso['fecha_emision'],
            'tipo_comprobante'     => 'traspasos',
            'numero_comprobante'   => '',
            'concepto'             => 'Traspaso ' . $num,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'traspaso',
            'id_referencia_origen' => $idTraspaso,
            'observaciones'        => $traspaso['observaciones'] ?? null,
        ];

        $idGenerado = $asientoService->guardarAsiento($cabecera, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idTraspaso, $idGenerado);
    }

    /**
     * Anula el asiento del traspaso. Se llama DENTRO de la transacción de anular() y propaga
     * cualquier error (p. ej. período contable cerrado en la fecha del asiento), para que el
     * documento y su asiento cambien juntos o no cambie ninguno.
     *
     * $traspaso es la fila leída ANTES de anular.
     */
    private function anularAsientoContable(array $traspaso, int $idEmpresa, int $idUsuario): void
    {
        $idTraspaso = (int) $traspaso['id'];
        $asientoService = $this->asientoContableService();
        $previo = $asientoService->getAsientoPorOrigen('traspaso', $idTraspaso, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        // Fallback (traspasos migrados): su asiento no tiene modulo_origen='traspaso' y solo se
        // enlaza por id_asiento_contable (el mismo criterio con el que Control Bancario lo cruza).
        if ($idAsiento <= 0) {
            $idAsiento = (int) ($traspaso['id_asiento_contable'] ?? 0);
        }
        if ($idAsiento <= 0) {
            return;
        }

        $asientoService->anularDeDocumento($idAsiento, $idEmpresa, $idUsuario, 'del traspaso');
        $this->repository->updateAsientoContable($idTraspaso, null);
    }
}

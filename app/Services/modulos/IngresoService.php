<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\IngresoRepository;
use App\Rules\modulos\IngresoRules;
use App\Services\LogSistemaService;
use App\core\Database;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;

class IngresoService
{
    private IngresoRepository $repository;
    private IngresoRules $rules;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;

    public function __construct(IngresoRepository $repository, IngresoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;

        // Inicialización manual de dependencias del servicio de periodos
        $periodosRepo = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $ingreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$ingreso) {
            return null;
        }

        $ingreso['detalles'] = $this->repository->getDetalles($id);
        $ingreso['pagos']    = $this->repository->getPagos($id);

        return $ingreso;
    }

    private function validarSecuencial(array $data, ?int $excluirId = null): void
    {
        if ($this->repository->existeSecuencial(
            (int) $data['id_empresa'],
            (int) ($data['id_establecimiento'] ?? 0),
            (int) ($data['id_punto_emision'] ?? 0),
            (string) $data['secuencial'],
            $excluirId
        )) {
            throw new \Exception('El número de secuencial de ingreso ya existe. Recargue la numeración e intente nuevamente.');
        }
    }

    public function crear(array $data): int
    {
        // 1. Validar Secuencial
        $this->validarSecuencial($data);

        // 2. Aplicar Reglas de Validación
        $this->rules->validar($data);

        // 3. Validar Periodo Contable
        $this->validarPeriodo($data, 'No se puede registrar el ingreso porque el periodo contable está cerrado.');

        // 4. Validar fecha de emisión vs fechas de documentos
        $this->validarFechaVsDocumentos($data);

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            // Insert Cabecera
            $idIngreso = $this->repository->insertCabecera($data);

            // Insert Detalles
            if (!empty($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as $d) {
                    $d['id_ingreso'] = $idIngreso;
                    $this->repository->insertDetalle($d);
                }
            }

            // Insert Pagos (Formas de Cobro)
            if (!empty($data['pagos']) && is_array($data['pagos'])) {
                foreach ($data['pagos'] as $p) {
                    $p['id_ingreso'] = $idIngreso;
                    $this->repository->insertPago($p);
                }
            }

            // Auditoría
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                'ingresos_cabecera',
                $idIngreso,
                null,
                ['id_ingreso' => $idIngreso, 'monto_total' => $data['monto_total']]
            );

            if ($managedTransaction) {
                $db->commit();
            }

            return $idIngreso;
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): void
    {
        // 1. Validar Secuencial (excluyendo el ID actual)
        $this->validarSecuencial($data, $id);

        // 2. Aplicar Reglas de Validación
        $this->rules->validar($data);

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $original = $this->getPorId($id, $idEmpresa);
            if (!$original) {
                throw new \Exception('No se encontró el registro a actualizar.');
            }
            if ($original['estado'] === 'anulado') {
                throw new \Exception('No se puede editar un ingreso anulado.');
            }

            // Validar Periodo Contable Original
            $this->periodosService->validarFechaPermitida(
                $original['fecha_emision'], 
                $idEmpresa, 
                'No se puede modificar el ingreso porque el periodo contable original está cerrado.'
            );
            
            // Validar Periodo Contable Nuevo
            $this->validarPeriodo($data, 'No se puede cambiar la fecha a este periodo porque está cerrado.');

            // Validar fecha de emisión vs fechas de documentos
            $this->validarFechaVsDocumentos($data);

            // Update Cabecera
            $this->repository->updateCabecera($id, $data);

            // Wipe and rewrite Details and Payments to simplify rebalancing
            $this->repository->deleteDetalles($id);
            $this->repository->deletePagos($id);

            // Insert Details
            if (!empty($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as $d) {
                    $d['id_ingreso'] = $id;
                    $this->repository->insertDetalle($d);
                }
            }

            // Insert Payments
            if (!empty($data['pagos']) && is_array($data['pagos'])) {
                foreach ($data['pagos'] as $p) {
                    $p['id_ingreso'] = $id;
                    $this->repository->insertPago($p);
                }
            }

            // Auditar Cambios
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR',
                'ingresos_cabecera',
                $id,
                $original,
                $data
            );

            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $ingreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$ingreso) {
            throw new \Exception('Ingreso no encontrado.');
        }
        if ($ingreso['estado'] === 'anulado') {
            throw new \Exception('El ingreso ya se encuentra anulado.');
        }

        // Validar Periodo Contable antes de anular
        $this->periodosService->validarFechaPermitida(
            $ingreso['fecha_emision'], 
            $idEmpresa, 
            'No se puede anular el ingreso porque el periodo contable original está cerrado.'
        );

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $res = $this->repository->anular($id, $idEmpresa, $idUsuario);
            if (!$res) {
                throw new \Exception('No se pudo anular el ingreso.');
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR',
                'ingresos_cabecera',
                $id,
                $ingreso,
                ['id_ingreso' => $id, 'nuevo_estado' => 'anulado']
            );

            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $ingreso = $this->repository->getPorId($id, $idEmpresa);
        if (!$ingreso) {
            throw new \Exception('Ingreso no encontrado.');
        }

        // Validar Periodo Contable antes de eliminar
        $this->periodosService->validarFechaPermitida(
            $ingreso['fecha_emision'], 
            $idEmpresa, 
            'No se puede eliminar el ingreso porque el periodo contable está cerrado.'
        );

        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $res = $this->repository->eliminarLogico($id, $idEmpresa, $idUsuario);
            if (!$res) {
                throw new \Exception('No se pudo eliminar el ingreso.');
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'ingresos_cabecera',
                $id,
                $ingreso,
                ['id_ingreso' => $id, 'eliminado' => true]
            );

            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function getFormasCobro(int $idEmpresa): array
    {
        return $this->repository->getFormasCobro($idEmpresa);
    }

    public function getConceptosIngreso(int $idEmpresa): array
    {
        return $this->repository->getConceptosIngreso($idEmpresa);
    }

    public function getFacturasPendientes(int $idCliente, int $idEmpresa, ?int $excluirIngresoId = null): array
    {
        return $this->repository->getFacturasPendientes($idCliente, $idEmpresa, $excluirIngresoId);
    }

    /**
     * Expuesto para verificación rápida desde el controller (AJAX).
     */
    public function verificarPeriodo(string $fecha, int $idEmpresa): void
    {
        $this->periodosService->validarFechaPermitida(
            $fecha,
            $idEmpresa,
            "La fecha $fecha corresponde a un periodo contable cerrado. No se pueden registrar transacciones en ese periodo."
        );
    }

    /**
     * Valida que la fecha de emisión no sea anterior a la fecha de ningún documento en el detalle.
     */
    private function validarFechaVsDocumentos(array $data): void
    {
        $fechaEmision = $data['fecha_emision'] ?? null;
        if (!$fechaEmision || empty($data['detalles'])) return;

        foreach ($data['detalles'] as $detalle) {
            $fechaDoc = $detalle['fecha_documento'] ?? null;
            if ($fechaDoc && $fechaEmision < $fechaDoc) {
                throw new \Exception(
                    "La fecha de emisión ($fechaEmision) no puede ser anterior a la fecha del documento {$detalle['numero_documento']} ($fechaDoc)."
                );
            }
        }
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);

        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }
}

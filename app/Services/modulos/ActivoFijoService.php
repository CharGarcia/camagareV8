<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\repositories\modulos\ActivoFijoLoteRepository;
use App\repositories\modulos\ActivoFijoRepository;
use App\repositories\modulos\ComprasRepository;
use App\Rules\modulos\ActivoFijoRules;
use App\Services\LogSistemaService;

class ActivoFijoService
{
    /** Código del concepto de Configuración Contable que resuelve la contrapartida del alta manual. */
    private const CODIGO_CONTRAPARTIDA = 'CONTRAPARTIDAALTAACTIVOFIJO';

    /** Aviso de la última operación cuando la contrapartida se propagó a Configuración Contable. */
    private ?string $avisoContrapartida = null;

    public function __construct(
        private ActivoFijoRepository $repository,
        private ActivoFijoCategoriaRepository $categoriaRepository,
        private ActivoFijoLoteRepository $loteRepository,
        private ComprasRepository $comprasRepository,
        private ActivoFijoRules $rules,
        private LogSistemaService $logService
    ) {
    }

    public function getAvisoContrapartida(): ?string
    {
        return $this->avisoContrapartida;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuario): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getPorId($id, $idEmpresa);
    }

    public function getHistorialDepreciaciones(int $idActivo): array
    {
        return $this->repository->getHistorialDepreciaciones($idActivo);
    }

    /**
     * Cuenta contrapartida configurada como regla general en Configuración Contable
     * (concepto 'activos_fijos_alta'). Es la que se precarga en el modal del activo.
     *
     * @return array{id:int,codigo:?string,nombre:?string}|null
     */
    public function getContrapartidaConfigurada(int $idEmpresa): ?array
    {
        $regla = $this->buscarReglaContrapartida($idEmpresa);
        if (!$regla || empty($regla['id_cuenta'])) {
            return null;
        }
        return [
            'id'     => (int) $regla['id_cuenta'],
            'codigo' => $regla['cuenta_codigo'] ?? null,
            'nombre' => $regla['cuenta_nombre'] ?? null,
        ];
    }

    /** Regla general del concepto 'activos_fijos_alta' con código CONTRAPARTIDAALTAACTIVOFIJO. */
    private function buscarReglaContrapartida(int $idEmpresa): ?array
    {
        $programadoRepo = new \App\repositories\modulos\AsientoProgramadoRepository();
        foreach ($programadoRepo->getReglasGeneralesPorConcepto($idEmpresa, 'activos_fijos_alta') as $r) {
            if (strtoupper((string) $r['codigo']) === self::CODIGO_CONTRAPARTIDA) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Propaga la cuenta contrapartida elegida en el activo a la regla general de
     * Configuración Contable, para que los siguientes activos la traigan precargada.
     * No borra la regla cuando el activo se guarda sin cuenta: solo escribe cuando hay
     * una cuenta nueva y distinta de la ya configurada.
     *
     * Debe invocarse FUERA de una transacción: AsientoProgramadoService abre la suya.
     */
    private function sincronizarContrapartidaGeneral(int $idEmpresa, int $idCuenta, int $idUsuario): void
    {
        $regla = $this->buscarReglaContrapartida($idEmpresa);
        if (!$regla) {
            throw new \Exception(
                'No existe el concepto «Activos Fijos - Alta» en el catálogo de asientos tipo ' .
                '(código ' . self::CODIGO_CONTRAPARTIDA . ').'
            );
        }
        if ((int) ($regla['id_cuenta'] ?? 0) === $idCuenta) {
            return; // ya está configurada con esa cuenta
        }

        $idAsientoTipo   = (int) $regla['id_asiento_tipo'];
        $programadoRepo  = new \App\repositories\modulos\AsientoProgramadoRepository();
        $programadoSvc   = new AsientoProgramadoService();
        $reglaExistente  = $programadoRepo->getReglaGeneralPorAsientoTipo($idEmpresa, $idAsientoTipo);

        $payload = [
            'id_asiento_tipo' => $idAsientoTipo,
            'id_cuenta'       => $idCuenta,
            'id_referencia'   => $idAsientoTipo,
            'tipo_referencia' => 'asientos tipo',
        ];

        if ($reglaExistente) {
            $payload['updated_by'] = $idUsuario;
            $programadoSvc->actualizar((int) $reglaExistente['id'], $payload, $idEmpresa, $idUsuario);
        } else {
            $programadoSvc->registrar($payload, $idEmpresa, $idUsuario);
        }

        $this->avisoContrapartida = 'La cuenta contrapartida también se actualizó en Configuración Contable.';
    }

    /**
     * Guarda la contrapartida del activo y la propaga a Configuración Contable. Un fallo
     * aquí no invalida el activo (ya guardado): se registra en el log y se sigue.
     */
    private function procesarContrapartida(array $data): void
    {
        if (($data['origen'] ?? 'manual') !== 'manual' || empty($data['id_cuenta_contrapartida_alta'])) {
            return;
        }
        try {
            $this->sincronizarContrapartidaGeneral(
                (int) $data['id_empresa'],
                (int) $data['id_cuenta_contrapartida_alta'],
                (int) $data['id_usuario']
            );
        } catch (\Throwable $e) {
            error_log('[ActivoFijo] Contrapartida no propagada a Configuración Contable: ' . $e->getMessage());
        }
    }

    public function crear(array $data): int
    {
        $this->avisoContrapartida = null;
        $data['origen'] = $data['origen'] ?? 'manual';

        if ($data['origen'] === 'compra') {
            $detalleCompra = $this->comprasRepository->getDetalleById((int) ($data['id_compra_detalle'] ?? 0), (int) $data['id_empresa']);
            if (!$detalleCompra) {
                throw new \Exception('La línea de factura de compra seleccionada no existe.');
            }
            $data['id_compra'] = (int) $detalleCompra['id_compra'];
            $data['id_proveedor'] = (int) $detalleCompra['id_proveedor'];
            $data['valor_adquisicion'] = (float) $detalleCompra['precio_total_sin_impuesto'];
            $data['fecha_adquisicion'] = $detalleCompra['fecha_emision'];
            if (empty($data['nombre'])) {
                $data['nombre'] = $detalleCompra['descripcion'];
            }
            $data['proveedor_texto'] = null;
            // La contrapartida es del asiento de alta manual; una compra ya trae su propio asiento.
            $data['id_cuenta_contrapartida_alta'] = null;
        } else {
            $data['id_compra'] = null;
            $data['id_compra_detalle'] = null;
        }

        $this->rules->validar($data);

        $categoria = $this->categoriaRepository->getPorId((int) $data['id_categoria'], (int) $data['id_empresa']);

        $valorAdquisicion = round((float) $data['valor_adquisicion'], 2);
        $valorResidual = round((float) ($data['valor_residual'] ?? 0), 2);
        $porcentaje = (float) $categoria['porcentaje_depreciacion_anual'];

        $data['valor_adquisicion'] = $valorAdquisicion;
        $data['valor_residual'] = $valorResidual;
        $data['valor_depreciable'] = round($valorAdquisicion - $valorResidual, 2);
        $data['porcentaje_depreciacion_anual'] = $porcentaje;
        $data['meses_vida_util'] = $porcentaje > 0 ? (int) round(1200 / $porcentaje) : 0;
        $data['depreciacion_acumulada'] = 0.0;
        $data['valor_en_libros'] = $valorAdquisicion;
        $data['estado'] = 'activo';
        $data['fecha_inicio_depreciacion'] = $this->calcularInicioDepreciacion((int) $data['id_empresa'], (string) $data['fecha_adquisicion']);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repository->insert($data);
            $this->logService->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'crear',
                'activos_fijos',
                $id,
                null,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Fuera de la transacción: la contrapartida elegida se propaga a Configuración
        // Contable y un fallo de configuración contable no revierte el alta.
        $this->procesarContrapartida($data);

        // Asiento de alta SOLO si es manual (una compra ya tiene su propio asiento).
        if ($data['origen'] === 'manual') {
            try {
                $this->procesarAsientoAlta($id, $data);
            } catch (\Throwable $eAs) {
                error_log("[ActivoFijo] Asiento de alta no generado para activo $id: " . $eAs->getMessage());
            }
        }

        return $id;
    }

    public function actualizar(int $id, array $data): int
    {
        $this->avisoContrapartida = null;
        $idEmpresa = (int) $data['id_empresa'];
        $activo = $this->repository->getPorId($id, $idEmpresa);
        if (!$activo) {
            throw new \Exception('Activo fijo no encontrado.');
        }
        // El origen se fija en el alta: no se toma del formulario.
        $data['origen'] = $activo['origen'];
        if ($data['origen'] !== 'manual') {
            $data['id_cuenta_contrapartida_alta'] = null;
        }

        $this->rules->validarEdicion($activo, $data);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            if ($this->repository->tieneDepreciacionesGeneradas($id)) {
                $this->repository->updateDescriptivo($id, $data);
            } else {
                $valorResidual = round((float) ($data['valor_residual'] ?? $activo['valor_residual']), 2);
                $data['valor_residual'] = $valorResidual;
                $data['valor_depreciable'] = round((float) $activo['valor_adquisicion'] - $valorResidual, 2);
                $this->repository->update($id, $data);
            }
            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'activos_fijos',
                $id,
                $activo,
                $data
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Fuera de la transacción (AsientoProgramadoService abre la suya).
        $this->procesarContrapartida($data);

        return $id;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        // Solo bloquea si ya hay depreciaciones contabilizadas; el asiento de ALTA existe
        // desde el registro, así que un activo sin depreciar sí llega hasta acá con asiento.
        $this->rules->validarEliminacion($id);

        $db = \App\core\Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            // Antes del borrado lógico: `anular()` necesita leer el activo todavía vivo.
            $this->anularAsientoAlta($id, $idEmpresa, $idUsuario);

            $this->repository->softDelete($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'activos_fijos', $id, null, null);

            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Anula el asiento de ALTA del activo. Se llama al ELIMINAR: un asiento que sobrevive a su
     * activo queda huérfano y sigue sumando en el Balance. A diferencia de compras/retenciones,
     * `AsientoContableService::anular()` NO desvincula este origen (`activos_fijos_alta` no está
     * en su mapa `DocumentoOrigenAsiento`), así que la columna se limpia acá.
     *
     * Las depreciaciones ya contabilizadas no se tocan: `validarEliminacion()` impide llegar
     * hasta aquí si existen.
     */
    private function anularAsientoAlta(int $idActivo, int $idEmpresa, int $idUsuario): void
    {
        $asientoService = new AsientoContableService(
            new \App\repositories\modulos\AsientoContableRepository(),
            new \App\Rules\modulos\AsientoContableRules(),
            $this->logService
        );

        // getAsientoPorOrigen ya excluye los anulados: si no devuelve nada, no hay asiento vivo.
        $previo = $asientoService->getAsientoPorOrigen('activos_fijos_alta', $idActivo, $idEmpresa);
        if ($previo === null) {
            return;
        }

        try {
            $asientoService->anular((int) $previo['id'], $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'ya se encuentra anulado') === false) {
                throw $e;
            }
        }
        $this->repository->setAsientoAlta($idActivo, null);
    }

    /**
     * Arma (vía AsientoBuilderService::generarAsientoAltaActivoFijo) y persiste el
     * asiento de alta manual. Idempotente: si ya existe asiento para este activo lo actualiza.
     */
    public function procesarAsientoAlta(int $idActivo, array $data): void
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) ($data['id_usuario'] ?? $_SESSION['id_usuario'] ?? 0);

        $builder = new AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoAltaActivoFijo($idEmpresa, $idActivo);
        if (empty($detallesSugeridos)) {
            return;
        }

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?? 'Alta de activo fijo',
                'documento_referencia' => 'Activo Fijo #' . $idActivo,
            ];
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('activos_fijos_alta', $idActivo, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $data['fecha_adquisicion'],
            'tipo_comprobante'     => 'diario',
            'numero_comprobante'   => '',
            'concepto'             => 'Alta de activo fijo: ' . $data['nombre'],
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'activos_fijos_alta',
            'id_referencia_origen' => $idActivo,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->setAsientoAlta($idActivo, $idAsientoGenerado);
    }

    /**
     * Primer día del mes de la fecha de adquisición; si ese período ya tiene un lote
     * de depreciación contabilizado, se recorre al primer día del mes siguiente abierto
     * (sin depreciación retroactiva en v1).
     */
    private function calcularInicioDepreciacion(int $idEmpresa, string $fechaAdquisicion): string
    {
        $ts = strtotime($fechaAdquisicion) ?: time();
        $anio = (int) date('Y', $ts);
        $mes = (int) date('n', $ts);

        $intentos = 0;
        while ($this->loteRepository->existsLote($idEmpresa, $anio, $mes) && $intentos < 240) {
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $anio++;
            }
            $intentos++;
        }

        return sprintf('%04d-%02d-01', $anio, $mes);
    }
}

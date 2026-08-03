<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\OpcionIngresoEgresoRepository;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\Rules\modulos\OpcionIngresoEgresoRules;
use App\Services\LogSistemaService;
use Exception;

class OpcionIngresoEgresoService
{
    private OpcionIngresoEgresoRepository $repo;
    private AsientoProgramadoRepository $programadoRepo;
    private OpcionIngresoEgresoRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repo = new OpcionIngresoEgresoRepository();
        $this->programadoRepo = new AsientoProgramadoRepository();
        $this->rules = new OpcionIngresoEgresoRules();
        $this->logService = new LogSistemaService();
    }

    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre', string $ordenDir = 'ASC'): array
    {
        $resultado = $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $resultado['rows'] = array_map(fn($r) => $this->aplicarCuentaOficial($r, $idEmpresa), $resultado['rows']);
        return $resultado;
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $row = $this->repo->getById($id, $idEmpresa);
        return $row ? $this->aplicarCuentaOficial($row, $idEmpresa) : null;
    }

    /**
     * Para conceptos atados a un módulo con contabilización propia (COMPRA, LIQUIDACION,
     * FACTURA_VENTA, RECIBO_VENTA), sobreescribe la cuenta a mostrar con la "oficial" de
     * Configuración Contable — la que AsientoBuilderService realmente usa — en vez de la que
     * pueda tener guardada aparte este concepto (campo legado, ver
     * egresos-compras-cxp-contrapartida-faltante). Agrega `cuenta_bloqueada` para que la vista
     * deshabilite la edición de ese campo.
     */
    private function aplicarCuentaOficial(array $row, int $idEmpresa): array
    {
        $oficial = $this->programadoRepo->getCuentaOficialPorComportamiento($idEmpresa, (string) ($row['comportamiento'] ?? ''));
        $row['cuenta_bloqueada'] = $oficial !== null;
        if ($oficial !== null) {
            $row['id_cuenta_contable'] = $oficial['id_cuenta'] ?: null;
            $row['cuenta_codigo']      = $oficial['cuenta_codigo'];
            $row['cuenta_nombre']      = $oficial['cuenta_nombre'];
        }
        return $row;
    }

    /**
     * Los 4 comportamientos con cuenta oficial (ver aplicarCuentaOficial) no pueden guardar una
     * cuenta propia desde este módulo: se ignora cualquier id_cuenta_contable que llegue del
     * formulario, aunque el campo esté deshabilitado en la vista (defensa en profundidad contra
     * una llamada directa a la API).
     */
    private function normalizarCuentaBloqueada(array $data): array
    {
        if ($this->programadoRepo->tieneCuentaOficialPorComportamiento((string) ($data['comportamiento'] ?? ''))) {
            $data['id_cuenta_contable'] = null;
        }
        return $data;
    }

    public function registrar(array $data): int
    {
        $this->rules->validar($data);
        $data = $this->normalizarCuentaBloqueada($data);

        $id = $this->repo->create($data);
        
        $this->logService->registrar(
            (int)$data['id_usuario'],
            (int)$data['id_empresa'],
            'CREAR',
            'empresa_opciones_ingreso_egreso',
            $id,
            null,
            ['nombre' => $data['nombre']]
        );
        
        return $id;
    }

    public function actualizar(int $id, int $idEmpresa, array $data): bool
    {
        $original = $this->repo->getById($id, $idEmpresa);
        if (!$original) throw new Exception("Registro no encontrado.");

        $this->rules->validar($data);
        $data = $this->normalizarCuentaBloqueada($data);

        $ok = $this->repo->update($id, $idEmpresa, $data);
        if ($ok) {
            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$idEmpresa,
                'ACTUALIZAR',
                'empresa_opciones_ingreso_egreso',
                $id,
                $original,
                ['nombre' => $data['nombre']]
            );
        }
        return $ok;
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $original = $this->repo->getById($id, $idEmpresa);
        if (!$original) throw new Exception("Registro no encontrado.");
        
        if ($this->repo->estaUsado($id, $idEmpresa)) {
            throw new Exception("No se puede eliminar este concepto porque ya está asignado a transacciones de Ingresos o Egresos.");
        }
        
        $ok = $this->repo->logicalDelete($id, $idEmpresa, $idUsuario);
        if ($ok) {
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ELIMINAR',
                'empresa_opciones_ingreso_egreso',
                $id,
                $original,
                null
            );
        }
        return $ok;
    }
}

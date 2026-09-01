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
    private ?AsientoProgramadoService $asientoService = null;

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
     *
     * ROL (Nómina) también queda bloqueado, pero sin una única cuenta oficial que mostrar en su
     * lugar (se reparte entre "Sueldos por Pagar" y "Anticipos" según el tipo de rol — ver
     * AsientoProgramadoRepository::COMPORTAMIENTO_CUENTA_BLOQUEADA_SIN_OFICIAL): en vez de la
     * cuenta legada que pudiera tener guardada, se agrega `cuentas_oficiales_multiples` con las
     * cuentas reales (autocompletado de solo lectura, misma idea que la cuenta única de arriba
     * pero mostrando varias) para que la vista las liste en vez de dejar el campo en blanco.
     */
    private function aplicarCuentaOficial(array $row, int $idEmpresa): array
    {
        $comportamiento = (string) ($row['comportamiento'] ?? '');
        $oficial = $this->programadoRepo->getCuentaOficialPorComportamiento($idEmpresa, $comportamiento);
        $row['cuenta_bloqueada'] = $oficial !== null || $this->programadoRepo->tieneCuentaOficialPorComportamiento($comportamiento);
        $row['cuentas_oficiales_multiples'] = [];
        if ($oficial !== null) {
            $row['id_cuenta_contable'] = $oficial['id_cuenta'] ?: null;
            $row['cuenta_codigo']      = $oficial['cuenta_codigo'];
            $row['cuenta_nombre']      = $oficial['cuenta_nombre'];
        } elseif ($row['cuenta_bloqueada']) {
            $row['cuentas_oficiales_multiples'] = $this->programadoRepo->getCuentasOficialesMultiplesPorComportamiento($idEmpresa, $comportamiento);
            $row['id_cuenta_contable'] = null;
            $row['cuenta_codigo']      = '';
            $row['cuenta_nombre']      = '';
        } else {
            $row = $this->aplicarCuentaProgramada($row);
        }
        return $row;
    }

    /**
     * Conceptos LIBRES (los que no toman su cuenta de un módulo): su cuenta vive en dos sitios
     * —la columna de este módulo y el asiento programado de su naturaleza— y Configuración
     * Contable muestra el asiento programado cuando existe. Aquí se muestra lo mismo, para que
     * las dos pantallas no puedan decir cosas distintas sobre el mismo concepto.
     */
    private function aplicarCuentaProgramada(array $row): array
    {
        $naturaleza = $this->naturalezaDe($row);
        if ($naturaleza === null) {
            return $row;
        }

        $idCuenta = $row["id_cuenta_{$naturaleza}"] ?? null;
        $row['id_cuenta_contable'] = $idCuenta !== null ? (int) $idCuenta : null;
        $row['cuenta_codigo']      = $row["cuenta_{$naturaleza}_codigo"] ?? '';
        $row['cuenta_nombre']      = $row["cuenta_{$naturaleza}_nombre"] ?? '';
        return $row;
    }

    /**
     * Naturaleza que gobierna la cuenta del concepto: la pantalla obliga a elegir una sola
     * (Ingreso o Egreso). Si un registro antiguo trae las dos marcadas, manda Ingresos, que es
     * el orden en que Configuración Contable presenta los bloques.
     *
     * @return string|null 'ingreso' | 'egreso' | null (no aplica a ninguna)
     */
    private function naturalezaDe(array $row): ?string
    {
        if (!empty($row['aplica_ingresos'])) {
            return 'ingreso';
        }
        if (!empty($row['aplica_egresos'])) {
            return 'egreso';
        }
        return null;
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

    /**
     * Deja el asiento programado del concepto ('opcion_ingreso' / 'opcion_egreso') igual a la
     * cuenta que se acaba de guardar aquí, y retira el de la naturaleza contraria. Es el mismo
     * registro que edita Configuración Contable y el que esa pantalla muestra por encima de la
     * columna del módulo: sin esto, cambiar la cuenta aquí no se vería allá.
     *
     * Solo aplica a conceptos LIBRES: los atados a un módulo (Compras, Liquidaciones, Facturas
     * y Recibos de Venta, Nómina) no tienen cuenta propia ni aparecen en Configuración Contable.
     */
    private function sincronizarCuentaProgramada(array $data, int $id, ?int $idCuenta): void
    {
        if ($this->programadoRepo->tieneCuentaOficialPorComportamiento((string) ($data['comportamiento'] ?? ''))) {
            return;
        }

        $naturaleza = $this->naturalezaDe($data);
        $idEmpresa  = (int) $data['id_empresa'];
        $idUsuario  = (int) $data['id_usuario'];

        foreach (['ingreso', 'egreso'] as $nat) {
            // La naturaleza que no aplica pierde su regla; la que aplica queda con esta cuenta
            // (o también sin regla, si el concepto se dejó sin cuenta).
            $cuenta = ($nat === $naturaleza) ? $idCuenta : null;
            $this->sincronizarCuentaNaturaleza($idEmpresa, $idUsuario, $id, $nat, $cuenta, false);
        }
    }

    /**
     * Asigna (o quita) la cuenta contable de un concepto en una naturaleza, en los DOS lugares
     * donde vive: el asiento programado y —si $actualizarCuentaBase— la columna del propio
     * módulo. Lo usan este módulo y Configuración Contable, para que no haya dos
     * implementaciones que puedan divergir.
     *
     * @param string $naturaleza 'ingreso' | 'egreso'
     */
    public function sincronizarCuentaNaturaleza(
        int $idEmpresa,
        int $idUsuario,
        int $idOpcion,
        string $naturaleza,
        ?int $idCuenta,
        bool $actualizarCuentaBase = true
    ): void {
        $tipoReferencia = $naturaleza === 'ingreso' ? 'opcion_ingreso' : 'opcion_egreso';
        $idCuenta = ($idCuenta !== null && $idCuenta > 0) ? $idCuenta : null;

        if ($actualizarCuentaBase) {
            $this->repo->updateCuentaContable($idOpcion, $idEmpresa, $idCuenta, $idUsuario);
        }

        $asientoService = $this->getAsientoService();
        $reglaExistente = $this->programadoRepo->getReglaPorReferencia($idEmpresa, $idOpcion, $tipoReferencia);

        if ($idCuenta === null) {
            if ($reglaExistente) {
                $asientoService->eliminar((int) $reglaExistente['id'], $idEmpresa, $idUsuario);
            }
            return;
        }

        $dataRule = [
            'id_asiento_tipo' => 0,
            'id_cuenta'       => $idCuenta,
            'id_referencia'   => $idOpcion,
            'tipo_referencia' => $tipoReferencia
        ];

        if ($reglaExistente) {
            if ((int) $reglaExistente['id_cuenta'] === $idCuenta) {
                return; // Ya apunta a esa cuenta: no se toca (evita ruido en la auditoría).
            }
            $dataRule['updated_by'] = $idUsuario;
            $asientoService->actualizar((int) $reglaExistente['id'], $dataRule, $idEmpresa, $idUsuario);
        } else {
            $asientoService->registrar($dataRule, $idEmpresa, $idUsuario);
        }
    }

    private function getAsientoService(): AsientoProgramadoService
    {
        return $this->asientoService ??= new AsientoProgramadoService();
    }

    public function registrar(array $data): int
    {
        $this->rules->validar($data);
        $data = $this->normalizarCuentaBloqueada($data);

        $id = $this->repo->create($data);
        $this->sincronizarCuentaProgramada($data, $id, !empty($data['id_cuenta_contable']) ? (int) $data['id_cuenta_contable'] : null);

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
            $this->sincronizarCuentaProgramada($data, $id, !empty($data['id_cuenta_contable']) ? (int) $data['id_cuenta_contable'] : null);
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

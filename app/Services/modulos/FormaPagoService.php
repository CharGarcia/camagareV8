<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\FormaPagoRepository;
use Exception;

class FormaPagoService
{
    private FormaPagoRepository $repository;
    private ?AsientoProgramadoRepository $asientoRepo = null;
    private ?AsientoProgramadoService $asientoService = null;

    public function __construct(FormaPagoRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repository->getPorId($id, $idEmpresa);
    }

    public function getBancos(): array
    {
        return $this->repository->getBancosDisponibles();
    }

    public function buscarCuentas(int $idEmpresa, string $q): array
    {
        return $this->repository->getCuentasContables($idEmpresa, $q);
    }

    public function guardar(array $data): int
    {
        // El modal envía la cuenta contable separada por flujo (Cobros / Pagos), igual que
        // Configuración Contable. Si esos campos no vienen (llamada antigua), se respeta el
        // comportamiento previo con una sola cuenta base y no se toca ningún asiento programado.
        $sincronizarFlujos = array_key_exists('id_cuenta_cobro', $data)
                          || array_key_exists('id_cuenta_pago', $data);

        $cuentaCobro = null;
        $cuentaPago  = null;
        if ($sincronizarFlujos) {
            [$cuentaCobro, $cuentaPago] = $this->cuentasPorFlujo($data);
            // Cuenta base de la forma: la que Control Bancario y el resto del sistema leen
            // cuando la forma no tiene asiento programado propio. Manda la de Cobros; si la
            // forma no aplica a ingresos (o quedó vacía), se usa la de Pagos.
            $data['id_cuenta_contable'] = $cuentaCobro ?? $cuentaPago;
        }

        $this->validar($data);

        if (!empty($data['id']) && (int)$data['id'] > 0) {
            $id = (int)$data['id'];
            $this->repository->update($id, (int)$data['id_empresa'], $data);
        } else {
            $id = $this->repository->create($data);
        }

        if ($sincronizarFlujos) {
            $this->sincronizarCuentasProgramadas(
                (int)$data['id_empresa'],
                (int)($data['usuario_id'] ?? 0),
                $id,
                $cuentaCobro,
                $cuentaPago
            );
        }

        return $id;
    }

    /**
     * Normaliza las cuentas que llegan del modal según el "Aplica en" de la forma: un flujo
     * al que la forma no aplica no tiene cuenta (no se muestra en Configuración Contable).
     *
     * @return array{0: ?int, 1: ?int} [cuenta de cobro, cuenta de pago]
     */
    private function cuentasPorFlujo(array $data): array
    {
        $aplica = strtoupper((string)($data['aplica_en'] ?? 'AMBAS'));
        $cobro  = (int)($data['id_cuenta_cobro'] ?? 0);
        $pago   = (int)($data['id_cuenta_pago'] ?? 0);

        return [
            in_array($aplica, ['INGRESO', 'AMBAS'], true) && $cobro > 0 ? $cobro : null,
            in_array($aplica, ['EGRESO',  'AMBAS'], true) && $pago  > 0 ? $pago  : null,
        ];
    }

    /**
     * Deja los asientos programados de la forma ('forma_cobro' / 'forma_pago') iguales a lo
     * que se acaba de guardar aquí. Es el mismo par de registros que edita Configuración
     * Contable, y es el que manda en la contabilidad real (AsientoBuilderService lee
     * COALESCE(asiento_programado.id_cuenta, forma.id_cuenta_contable)): sin esta
     * sincronización, cambiar la cuenta aquí no tendría efecto mientras existiera el asiento.
     *
     * Un flujo sin cuenta —o al que la forma ya no aplica— pierde su asiento programado, para
     * que no siga sobrescribiendo a la cuenta base con un valor viejo.
     */
    private function sincronizarCuentasProgramadas(
        int $idEmpresa,
        int $idUsuario,
        int $idForma,
        ?int $cuentaCobro,
        ?int $cuentaPago
    ): void {
        $this->sincronizarCuentaFlujo($idEmpresa, $idUsuario, $idForma, 'cobro', $cuentaCobro, false);
        $this->sincronizarCuentaFlujo($idEmpresa, $idUsuario, $idForma, 'pago',  $cuentaPago,  false);
    }

    /**
     * Asigna (o quita) la cuenta contable de una forma en un flujo concreto, en los DOS lugares
     * donde vive: el asiento programado del flujo y —si $actualizarCuentaBase— la cuenta base
     * del propio módulo de Formas. Lo usan tanto este módulo como Configuración Contable, para
     * que no existan dos implementaciones que puedan divergir.
     *
     * @param string $flujo 'cobro' | 'pago'
     */
    public function sincronizarCuentaFlujo(
        int $idEmpresa,
        int $idUsuario,
        int $idForma,
        string $flujo,
        ?int $idCuenta,
        bool $actualizarCuentaBase = true
    ): void {
        $tipoReferencia = $flujo === 'cobro' ? 'forma_cobro' : 'forma_pago';
        $idCuenta = ($idCuenta !== null && $idCuenta > 0) ? $idCuenta : null;

        if ($actualizarCuentaBase) {
            $this->repository->updateCuentaContable($idForma, $idEmpresa, $idCuenta, $idUsuario);
        }

        $repo    = $this->getAsientoRepo();
        $service = $this->getAsientoService();
        $reglaExistente = $repo->getReglaPorReferencia($idEmpresa, $idForma, $tipoReferencia);

        if ($idCuenta === null) {
            if ($reglaExistente) {
                $service->eliminar((int)$reglaExistente['id'], $idEmpresa, $idUsuario);
            }
            return;
        }

        $dataRule = [
            'id_asiento_tipo' => 0,
            'id_cuenta'       => $idCuenta,
            'id_referencia'   => $idForma,
            'tipo_referencia' => $tipoReferencia
        ];

        if ($reglaExistente) {
            if ((int)$reglaExistente['id_cuenta'] === $idCuenta) {
                return; // Ya apunta a esa cuenta: no se toca (evita ruido en la auditoría).
            }
            $dataRule['updated_by'] = $idUsuario;
            $service->actualizar((int)$reglaExistente['id'], $dataRule, $idEmpresa, $idUsuario);
        } else {
            $service->registrar($dataRule, $idEmpresa, $idUsuario);
        }
    }

    private function getAsientoRepo(): AsientoProgramadoRepository
    {
        return $this->asientoRepo ??= new AsientoProgramadoRepository();
    }

    private function getAsientoService(): AsientoProgramadoService
    {
        return $this->asientoService ??= new AsientoProgramadoService();
    }

    private function validar(array $data): void
    {
        if (empty($data['nombre'])) {
            throw new Exception("El nombre de la forma de pago es obligatorio.");
        }
        
        if (empty($data['tipo'])) {
            throw new Exception("Debe especificar un tipo de forma de pago.");
        }

        if (empty($data['aplica_en'])) {
            throw new Exception("Debe definir si aplica a Ingresos, Egresos o Ambas.");
        }

        // Lógica de validación especial para BANCO
        if ($data['tipo'] === 'BANCO') {
            if (empty($data['id_banco'])) {
                throw new Exception("Debe seleccionar una entidad bancaria para este tipo.");
            }
            if (empty($data['tipo_cuenta'])) {
                throw new Exception("Debe seleccionar el tipo de cuenta bancaria.");
            }
            if (empty($data['numero_cuenta'])) {
                throw new Exception("El número de cuenta es obligatorio.");
            }
        }

        // Validación especial para TARJETA (tarjeta física/datáfono): requiere modalidad (Débito/Crédito/Ambas)
        if ($data['tipo'] === 'TARJETA') {
            $mod = strtoupper((string)($data['modalidad_tarjeta'] ?? ''));
            if (!in_array($mod, ['DEBITO', 'CREDITO', 'AMBAS'], true)) {
                throw new Exception("Debe seleccionar al menos una modalidad de tarjeta (Débito o Crédito).");
            }
        }

        // Validación especial para PAYPHONE: es un gateway de cobro online, solo aplica a Ingresos.
        if ($data['tipo'] === 'PAYPHONE') {
            $aplica = strtoupper((string)($data['aplica_en'] ?? ''));
            if ($aplica !== 'INGRESO') {
                throw new Exception("Payphone es un cobro online: solo puede aplicar a Ingresos.");
            }
        }

        // Validación especial para NUVEI: es un gateway de cobro online, solo aplica a Ingresos.
        if ($data['tipo'] === 'NUVEI') {
            $aplica = strtoupper((string)($data['aplica_en'] ?? ''));
            if ($aplica !== 'INGRESO') {
                throw new Exception("Nuvei es un cobro online: solo puede aplicar a Ingresos.");
            }
        }

        // Validación especial para ANTICIPO: aplica a una sola dirección
        // (INGRESO = anticipos de clientes, EGRESO = anticipos a proveedores), nunca AMBAS.
        if ($data['tipo'] === 'ANTICIPO') {
            $aplica = strtoupper((string)($data['aplica_en'] ?? ''));
            if (!in_array($aplica, ['INGRESO', 'EGRESO'], true)) {
                throw new Exception("Un anticipo debe aplicar a una sola dirección: Ingreso (clientes) o Egreso (proveedores).");
            }
        }

        // Una cuenta contable de tipo BANCO/CHEQUE es tratada por Control Bancario como el
        // mayor de esa cuenta bancaria (filtra por id_cuenta_contable, no por la forma de pago
        // elegida). Si una forma NO bancaria (Efectivo, Tarjeta...) reutiliza esa misma cuenta,
        // sus movimientos se mezclan en la conciliación de esa cuenta como si fueran del banco.
        // Entre dos formas BANCO/CHEQUE compartir cuenta SÍ es válido (representan la misma
        // cuenta física vista por dos medios, p. ej. "Cheques Pichincha" y "Transferencias
        // Pichincha"): el conflicto solo existe cuando una es bancaria y la otra no.
        if (!empty($data['id_cuenta_contable'])) {
            $tiposBancarios = ['BANCO', 'CHEQUE'];
            $estaEsBancaria = in_array($data['tipo'], $tiposBancarios, true);
            $excluirId = !empty($data['id']) ? (int) $data['id'] : null;
            $otra = $this->repository->getOtraFormaConMismaCuenta(
                (int) $data['id_empresa'], (int) $data['id_cuenta_contable'], $excluirId
            );
            if ($otra !== null) {
                $otraEsBancaria = in_array($otra['tipo'], $tiposBancarios, true);
                if ($estaEsBancaria !== $otraEsBancaria) {
                    throw new Exception(
                        "Esa cuenta contable ya está asignada a \"{$otra['nombre']}\" (tipo {$otra['tipo']}). "
                        . "Una forma bancaria (Banco/Cheque) no puede compartir cuenta con una que no lo es: "
                        . "sus movimientos se mezclarían en Control Bancario. Elija otra cuenta contable."
                    );
                }
            }
        }
    }

    /** Mapa [id_forma => saldo] de las formas no-anticipo (Efectivo/Banco/Tarjeta/Otro). */
    public function getSaldosActuales(int $idEmpresa): array
    {
        return $this->repository->getSaldosActuales($idEmpresa);
    }

    /** Saldo de un anticipo para un cliente/proveedor concreto. */
    public function getSaldoAnticipo(int $idEmpresa, int $idForma, int $idTercero): float
    {
        return $this->repository->getSaldoAnticipo($idEmpresa, $idForma, $idTercero);
    }

    public function eliminar(int $id, int $idEmpresa, int $usuarioId): bool
    {
        if ($this->repository->estaUsado($id, $idEmpresa)) {
            throw new Exception("No se puede eliminar esta forma de cobro/pago porque ya registra movimientos en transacciones de Ingresos o Egresos.");
        }
        return $this->repository->delete($id, $idEmpresa, $usuarioId);
    }
}

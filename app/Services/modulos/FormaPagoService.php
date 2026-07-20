<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FormaPagoRepository;
use Exception;

class FormaPagoService
{
    private FormaPagoRepository $repository;

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
        $this->validar($data);

        if (!empty($data['id']) && (int)$data['id'] > 0) {
            $this->repository->update((int)$data['id'], (int)$data['id_empresa'], $data);
            return (int)$data['id'];
        } else {
            return $this->repository->create($data);
        }
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

        // Validación especial para ANTICIPO: aplica a una sola dirección
        // (INGRESO = anticipos de clientes, EGRESO = anticipos a proveedores), nunca AMBAS.
        if ($data['tipo'] === 'ANTICIPO') {
            $aplica = strtoupper((string)($data['aplica_en'] ?? ''));
            if (!in_array($aplica, ['INGRESO', 'EGRESO'], true)) {
                throw new Exception("Un anticipo debe aplicar a una sola dirección: Ingreso (clientes) o Egreso (proveedores).");
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

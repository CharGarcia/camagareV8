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

        // Validación especial para TARJETA: requiere modalidad (Débito/Crédito/Ambas)
        if ($data['tipo'] === 'TARJETA') {
            $mod = strtoupper((string)($data['modalidad_tarjeta'] ?? ''));
            if (!in_array($mod, ['DEBITO', 'CREDITO', 'AMBAS'], true)) {
                throw new Exception("Debe seleccionar al menos una modalidad de tarjeta (Débito o Crédito).");
            }
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $usuarioId): bool
    {
        if ($this->repository->estaUsado($id, $idEmpresa)) {
            throw new Exception("No se puede eliminar esta forma de cobro/pago porque ya registra movimientos en transacciones de Ingresos o Egresos.");
        }
        return $this->repository->delete($id, $idEmpresa, $usuarioId);
    }
}

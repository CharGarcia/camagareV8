<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;

class ActivoFijoCategoriaRules
{
    public function __construct(private ActivoFijoCategoriaRepository $repository)
    {
    }

    public function validar(array $data, ?int $idExcluir = null): void
    {
        if (empty($data['nombre'])) {
            throw new \Exception('El nombre de la categoría es obligatorio.');
        }

        $porcentaje = (float) ($data['porcentaje_depreciacion_anual'] ?? -1);
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new \Exception('El porcentaje de depreciación anual debe estar entre 0 y 100.');
        }

        if (empty($data['id_cuenta_activo'])) {
            throw new \Exception('Debe seleccionar la cuenta contable del Activo.');
        }
        if (empty($data['id_cuenta_depreciacion_acumulada'])) {
            throw new \Exception('Debe seleccionar la cuenta contable de Depreciación Acumulada.');
        }
        if (empty($data['id_cuenta_gasto_depreciacion'])) {
            throw new \Exception('Debe seleccionar la cuenta contable de Gasto por Depreciación.');
        }

        if ($this->repository->nombreExiste((int) $data['id_empresa'], (string) $data['nombre'], $idExcluir)) {
            throw new \Exception('Ya existe una categoría de activos fijos con ese nombre.');
        }
    }

    public function validarEliminacion(int $idCategoria): void
    {
        if ($this->repository->tieneActivosVinculados($idCategoria)) {
            throw new \Exception('No se puede eliminar: hay activos fijos registrados en esta categoría.');
        }
    }
}

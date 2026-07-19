<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\repositories\modulos\ActivoFijoRepository;

class ActivoFijoRules
{
    public function __construct(
        private ActivoFijoRepository $repository,
        private ActivoFijoCategoriaRepository $categoriaRepository
    ) {
    }

    public function validar(array $data): void
    {
        if (empty($data['id_categoria'])) {
            throw new \Exception('Debe seleccionar la categoría del activo.');
        }
        $categoria = $this->categoriaRepository->getPorId((int) $data['id_categoria'], (int) $data['id_empresa']);
        if (!$categoria) {
            throw new \Exception('La categoría seleccionada no existe.');
        }
        // Postgres vía PDO devuelve columnas boolean como 't'/'f' (no true/false ni 1/0);
        // empty('f') es false, así que hay que comparar explícito en vez de usar empty().
        $estadoActivo = ($categoria['estado'] === true || $categoria['estado'] === 't' || $categoria['estado'] === '1' || $categoria['estado'] === 1);
        if (!$estadoActivo) {
            throw new \Exception('La categoría seleccionada está inactiva.');
        }

        if (empty($data['nombre'])) {
            throw new \Exception('El nombre/descripción del activo es obligatorio.');
        }

        $valorAdquisicion = (float) ($data['valor_adquisicion'] ?? 0);
        if ($valorAdquisicion <= 0) {
            throw new \Exception('El valor de adquisición debe ser mayor a cero.');
        }

        $valorResidual = (float) ($data['valor_residual'] ?? 0);
        if ($valorResidual < 0 || $valorResidual >= $valorAdquisicion) {
            throw new \Exception('El valor residual debe ser mayor o igual a cero y menor que el valor de adquisición.');
        }

        if (empty($data['fecha_adquisicion'])) {
            throw new \Exception('Debe ingresar la fecha de adquisición.');
        }
        if (strtotime((string) $data['fecha_adquisicion']) > time()) {
            throw new \Exception('La fecha de adquisición no puede ser futura.');
        }

        $origen = $data['origen'] ?? 'manual';
        if (!in_array($origen, ['compra', 'manual'], true)) {
            throw new \Exception('Origen de activo no válido.');
        }

        if ($origen === 'compra') {
            if (empty($data['id_compra_detalle'])) {
                throw new \Exception('Debe seleccionar la línea de la factura de compra.');
            }
            if ($this->repository->compraDetalleVinculado((int) $data['id_compra_detalle'])) {
                throw new \Exception('Esa línea de la factura de compra ya fue registrada como otro activo fijo.');
            }
        }
    }

    /**
     * Categoría, valor de adquisición y fecha se fijan en el alta (definen el cálculo de
     * depreciación) y no se editan después. Si ya hay depreciaciones generadas, solo se
     * permiten cambios descriptivos (validados en el repository, sin reglas de negocio aquí).
     */
    public function validarEdicion(array $activoActual, array $data): void
    {
        if ($this->repository->tieneDepreciacionesGeneradas((int) $activoActual['id'])) {
            return;
        }

        if (empty($data['nombre'])) {
            throw new \Exception('El nombre/descripción del activo es obligatorio.');
        }
        $valorResidual = (float) ($data['valor_residual'] ?? 0);
        if ($valorResidual < 0 || $valorResidual >= (float) $activoActual['valor_adquisicion']) {
            throw new \Exception('El valor residual debe ser mayor o igual a cero y menor que el valor de adquisición.');
        }
    }

    public function validarEliminacion(int $idActivo): void
    {
        if ($this->repository->tieneDepreciacionesGeneradas($idActivo)) {
            throw new \Exception('No se puede eliminar: el activo ya tiene depreciaciones contabilizadas.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\ProyectoRepository;

/**
 * Proyecto elegido en la cabecera de un documento (Ingresos, Egresos). El documento pertenece a
 * un solo proyecto y, al generar su asiento, ese proyecto se copia a todas las líneas.
 */
class ProyectoDocumentoRules
{
    public function __construct(private ?ProyectoRepository $repository = null)
    {
        $this->repository ??= new ProyectoRepository();
    }

    /**
     * Valida el proyecto que llega del formulario y devuelve su id (null = sin proyecto).
     * Debe ser de la empresa, no eliminado y activo; al editar se acepta conservar el que ya
     * tenía el documento aunque el proyecto se haya cerrado después.
     */
    public function resolver(mixed $valor, int $idEmpresa, ?int $actual = null): ?int
    {
        $id = (int) ($valor ?? 0);
        if ($id <= 0) {
            return null;
        }
        $proyecto = $this->repository->findById($id, $idEmpresa);
        if ($proyecto === null) {
            throw new \InvalidArgumentException('El proyecto seleccionado no existe en esta empresa.');
        }
        if (($proyecto['estado'] ?? '') !== 'activo' && $id !== (int) $actual) {
            throw new \InvalidArgumentException('El proyecto «' . $proyecto['nombre'] . '» no está activo.');
        }
        return $id;
    }
}

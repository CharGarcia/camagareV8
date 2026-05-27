<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\modulos\UnidadesMedidaRepository;
use Exception;

/**
 * Servicio reutilizable para conversión entre unidades de medida.
 *
 * Fórmula: resultado = valor × (factor_destino / factor_origen)
 *
 * Ejemplo: producto configurado en kg (factor_base=1), vender en lb (factor_base=0.453592)
 *   precio_lb = precio_kg × (0.453592 / 1) = precio_kg × 0.453592
 *
 * Si el producto está configurado en lb y se vende en kg:
 *   precio_kg = precio_lb × (1 / 0.453592) = precio_lb × 2.20462
 */
class ConversionUnidadService
{
    private UnidadesMedidaRepository $repository;

    public function __construct(UnidadesMedidaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Convierte un valor de una unidad a otra.
     *
     * @param float $valor           Valor en la unidad de origen
     * @param int   $idUnidadOrigen  ID de la unidad de origen
     * @param int   $idUnidadDestino ID de la unidad de destino
     * @param int   $idEmpresa       ID de la empresa (seguridad multitenant)
     * @return float                 Valor convertido a la unidad de destino
     * @throws Exception             Si las unidades no existen, no pertenecen a la empresa,
     *                               o no son del mismo tipo de medida
     */
    public function convertirValor(
        float $valor,
        int $idUnidadOrigen,
        int $idUnidadDestino,
        int $idEmpresa
    ): float {
        if ($idUnidadOrigen === $idUnidadDestino) {
            return $valor;
        }

        $factores = $this->obtenerFactores($idUnidadOrigen, $idUnidadDestino, $idEmpresa);

        $factorOrigen  = (float) $factores['origen']['factor_base'];
        $factorDestino = (float) $factores['destino']['factor_base'];

        if ($factorOrigen <= 0) {
            throw new Exception("El factor base de la unidad de origen ({$factores['origen']['nombre']}) no es válido.");
        }

        return $valor * ($factorDestino / $factorOrigen);
    }

    /**
     * Calcula el precio de un producto en una unidad de destino dado su precio en la unidad de origen.
     *
     * Caso típico: producto configurado en kg, se vende en libras.
     *   getPrecioEnUnidad(precio_kg, id_kg, id_lb, idEmpresa)
     *
     * @param float $precioBase      Precio en la unidad de origen (unidad configurada en el producto)
     * @param int   $idUnidadOrigen  ID de la unidad del producto
     * @param int   $idUnidadDestino ID de la unidad seleccionada al vender
     * @param int   $idEmpresa       ID de la empresa
     * @return float                 Precio convertido a la unidad de destino
     * @throws Exception
     */
    public function getPrecioEnUnidad(
        float $precioBase,
        int $idUnidadOrigen,
        int $idUnidadDestino,
        int $idEmpresa
    ): float {
        return $this->convertirValor($precioBase, $idUnidadOrigen, $idUnidadDestino, $idEmpresa);
    }

    /**
     * Retorna todas las unidades compatibles (mismo tipo de medida) con la unidad dada.
     * Incluye la propia unidad. Útil para poblar el selector de unidad al momento de vender.
     *
     * @param int $idUnidad  ID de la unidad de referencia (unidad del producto)
     * @param int $idEmpresa ID de la empresa
     * @return array         Array de unidades: [id, nombre, abreviatura, factor_base, es_base]
     */
    public function getUnidadesCompatibles(int $idUnidad, int $idEmpresa): array
    {
        return $this->repository->getUnidadesMismoTipo($idUnidad, $idEmpresa);
    }

    /**
     * Retorna los factores de conversión de dos unidades con validación de mismo tipo.
     *
     * @param int $idUnidadOrigen
     * @param int $idUnidadDestino
     * @param int $idEmpresa
     * @return array  ['origen' => [...], 'destino' => [...]]
     * @throws Exception
     */
    public function obtenerFactores(int $idUnidadOrigen, int $idUnidadDestino, int $idEmpresa): array
    {
        $factores = $this->repository->getFactoresConversion($idUnidadOrigen, $idUnidadDestino, $idEmpresa);

        if ($factores === null) {
            throw new Exception(
                'No se encontraron las unidades de medida especificadas o no pertenecen a esta empresa.'
            );
        }

        // Validar que pertenecen al mismo tipo de medida
        if (!$this->sonMismoTipo($idUnidadOrigen, $idUnidadDestino, $idEmpresa)) {
            throw new Exception(
                "Las unidades \"{$factores['origen']['nombre']}\" y \"{$factores['destino']['nombre']}\" " .
                "no son del mismo tipo de medida. No se puede realizar la conversión."
            );
        }

        return $factores;
    }

    /**
     * Indica si dos unidades son del mismo tipo de medida (y por tanto convertibles entre sí).
     *
     * @param int $idUnidadA
     * @param int $idUnidadB
     * @param int $idEmpresa
     * @return bool
     */
    public function sonMismoTipo(int $idUnidadA, int $idUnidadB, int $idEmpresa): bool
    {
        return $this->repository->mismoTipo($idUnidadA, $idUnidadB, $idEmpresa);
    }

    /**
     * Construye un array de conversiones desde una unidad de origen hacia todas las unidades
     * del mismo tipo. Útil para mostrar en la vista de ventas cuánto vale el producto en cada unidad.
     *
     * @param float $precioBase      Precio en la unidad de origen
     * @param int   $idUnidadOrigen  ID de la unidad base del producto
     * @param int   $idEmpresa
     * @return array  [['id', 'nombre', 'abreviatura', 'factor_base', 'es_base', 'precio_convertido'], ...]
     */
    public function getPreciosTodaUnidades(float $precioBase, int $idUnidadOrigen, int $idEmpresa): array
    {
        $unidades = $this->getUnidadesCompatibles($idUnidadOrigen, $idEmpresa);
        $factorOrigen = null;

        // Buscar el factor de la unidad de origen
        foreach ($unidades as $u) {
            if ((int)$u['id'] === $idUnidadOrigen) {
                $factorOrigen = (float)$u['factor_base'];
                break;
            }
        }

        if ($factorOrigen === null || $factorOrigen <= 0) {
            return $unidades;
        }

        foreach ($unidades as &$u) {
            $factorDestino          = (float)$u['factor_base'];
            $u['precio_convertido'] = $factorOrigen > 0
                ? round($precioBase * ($factorDestino / $factorOrigen), 6)
                : $precioBase;
        }
        unset($u);

        return $unidades;
    }
}

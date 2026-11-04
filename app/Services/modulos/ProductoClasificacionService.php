<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CategoriaRepository;
use App\repositories\modulos\MarcaRepository;
use App\repositories\modulos\ProductoRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Cambio rápido de la categoría o la marca de UN producto, fuera del modal de Productos.
 *
 * Lo usa el aviso de Configuración Contable «hay productos sin categoría/marca asignada»: cuando
 * un tipo de asiento se contabiliza por categoría (o marca), cada producto sin ella deja sus
 * documentos sin las cuentas de esa dimensión. El aviso permite asignarla ahí mismo, producto por
 * producto, sin abrir el módulo de Productos.
 *
 * Solo toca id_categoria / id_marca: el resto del producto (precios, impuestos, inventario) no se
 * revalida, a propósito, para que un producto con algún dato viejo no impida clasificarlo.
 */
class ProductoClasificacionService
{
    /** Dimensiones que se pueden cambiar aquí: dimensión => [columna del producto, etiqueta]. */
    public const DIMENSIONES = [
        'categoria' => ['id_categoria', 'categoría'],
        'marca'     => ['id_marca', 'marca'],
    ];

    public function __construct(
        private ProductoRepository $productos,
        private CategoriaRepository $categorias,
        private MarcaRepository $marcas,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new ProductoRepository(), new CategoriaRepository(), new MarcaRepository(), new LogSistemaService());
    }

    /**
     * Categorías o marcas de la empresa para el selector del modal.
     *
     * @return array<int, array{id:int, nombre:string}>
     */
    public function opciones(int $idEmpresa, string $dimension): array
    {
        $this->validarDimension($dimension);
        $filas = $dimension === 'categoria' ? $this->categorias->getCombo($idEmpresa) : $this->marcas->getCombo($idEmpresa);
        return array_map(fn($f) => ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre']], $filas);
    }

    /**
     * Asigna la categoría o marca $idValor al producto. Con $idUsuarioFiltro (usuario sin acceso
     * total en Productos) solo puede cambiar los productos que él creó.
     *
     * @return array{nombre:string} Nombre de la categoría/marca asignada.
     */
    public function cambiar(int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro, int $idProducto, string $dimension, int $idValor): array
    {
        $this->validarDimension($dimension);
        [$columna, $etiqueta] = self::DIMENSIONES[$dimension];
        if ($idProducto <= 0) {
            throw new Exception('Producto no válido.');
        }
        if ($idValor <= 0) {
            throw new Exception("Elija la {$etiqueta}.");
        }

        $this->productos->beginTransaction();
        try {
            $producto = $this->productos->getDetalleCompleto($idProducto, $idEmpresa);
            if (!$producto || !empty($producto['eliminado'])) {
                throw new Exception('El producto no existe.');
            }
            if ($idUsuarioFiltro !== null && (int) ($producto['created_by'] ?? 0) !== $idUsuarioFiltro) {
                throw new Exception('Solo puede cambiar los productos que usted registró.');
            }

            // Debe ser de esta empresa y no estar eliminada (getDetalleCompleto filtra ambas cosas).
            $valor = $dimension === 'categoria'
                ? $this->categorias->getDetalleCompleto($idValor, $idEmpresa)
                : $this->marcas->getDetalleCompleto($idValor, $idEmpresa);
            if (!$valor) {
                throw new Exception("La {$etiqueta} no existe.");
            }

            $antes = isset($producto[$columna]) ? (int) $producto[$columna] : null;
            if ($dimension === 'categoria') {
                $this->productos->actualizarCategoria($idProducto, $idEmpresa, $idValor, $idUsuario);
            } else {
                $this->productos->actualizarMarca($idProducto, $idEmpresa, $idValor, $idUsuario);
            }

            $this->log->registrar(
                $idUsuario, $idEmpresa, 'actualizar_' . $dimension, 'productos', $idProducto,
                [$columna => $antes ?: null],
                [$columna => $idValor]
            );

            $this->productos->commit();
            return ['nombre' => (string) ($valor['nombre'] ?? '')];
        } catch (\Throwable $e) {
            $this->productos->rollBack();
            throw $e;
        }
    }

    private function validarDimension(string $dimension): void
    {
        if (!isset(self::DIMENSIONES[$dimension])) {
            throw new Exception('Dimensión no válida.');
        }
    }
}

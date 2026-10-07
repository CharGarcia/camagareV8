<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\ProductoRepository;

/**
 * Productos similares con saldo: cuando el usuario elige un producto que no tiene saldo
 * en la bodega del documento (y la facturación afecta al inventario), se le ofrecen
 * otros productos que sí tienen saldo, priorizando la misma categoría, luego la misma
 * marca y por último un nombre parecido.
 *
 * Compartido por Facturas de Venta y Car-Wash (y cualquier módulo que agregue
 * productos a un documento). La única diferencia por módulo es el tipo de referencia
 * del kardex que se excluye al calcular el saldo cuando se edita un documento ya
 * guardado ('factura_venta', 'carwash_orden', …).
 */
class ProductosSimilaresService
{
    private ProductoRepository $prodRepo;
    private InventarioRepository $invRepo;

    public function __construct(?ProductoRepository $prodRepo = null, ?InventarioRepository $invRepo = null)
    {
        $this->prodRepo = $prodRepo ?? new ProductoRepository();
        $this->invRepo  = $invRepo ?? new InventarioRepository();
    }

    /**
     * @param int         $idProducto     Producto elegido (sin saldo).
     * @param int         $idEmpresa      Empresa activa.
     * @param int         $idBodega       Bodega de la cabecera del documento.
     * @param int|null    $excludeRefId   Id del documento que se edita (su propio consumo no cuenta).
     * @param string|null $excludeRefTipo Tipo de referencia del kardex de ese documento.
     * @param int         $limite         Máximo de sugerencias.
     *
     * @return array filas con el mismo formato del buscador de productos + stock_actual,
     *               controla_stock y `coincide` (por qué se sugiere), con precios_lista y
     *               variantes en lote para que "Usar este" deje la línea como si se hubiera buscado.
     */
    public function buscar(int $idProducto, int $idEmpresa, int $idBodega, ?int $excludeRefId = null, ?string $excludeRefTipo = null, int $limite = 8): array
    {
        $base = $this->prodRepo->getProductoBasico($idProducto, $idEmpresa);
        if (!$base || $idBodega <= 0) return [];

        $buscar = fn(string $q) => $this->prodRepo->getListado($idEmpresa, $q, 1, 40, 'nombre', 'ASC', null, 'venta', true)['rows'] ?? [];

        // Palabras principales del nombre (sin números ni palabras cortas).
        $norm = fn(string $s) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s)));
        $palabras = array_values(array_filter(
            explode(' ', preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $norm((string) $base['nombre']))),
            fn($w) => mb_strlen($w) >= 4 && !is_numeric($w)
        ));

        $cand = [];
        $agregar = function (array $rows) use (&$cand, $idProducto) {
            foreach ($rows as $r) {
                if ((int) $r['id'] !== $idProducto && !isset($cand[(int) $r['id']])) $cand[(int) $r['id']] = $r;
            }
        };
        if (!empty($base['id_categoria'])) $agregar($buscar('id_categoria:' . (int) $base['id_categoria']));
        if (!empty($base['id_marca']))     $agregar($buscar('id_marca:' . (int) $base['id_marca']));
        foreach (array_slice($palabras, 0, 2) as $w) $agregar($buscar($w));

        // Solo los que controlan inventario; su saldo en UNA consulta.
        $cand = array_filter($cand, fn($p) => self::esInventariable($p));
        if (!$cand) return [];
        $stocks = $this->invRepo->getStockActualPorProductos(
            array_keys($cand), $idBodega, $idEmpresa,
            $excludeRefId ?: null, $excludeRefId ? $excludeRefTipo : null
        );

        $out = [];
        foreach ($cand as $p) {
            $stock = (float) ($stocks[(int) $p['id']] ?? 0);
            if ($stock <= 0) continue;

            $motivos = [];
            $puntaje = 0;
            if (!empty($base['id_categoria']) && (int) ($p['id_categoria'] ?? 0) === (int) $base['id_categoria']) { $puntaje += 3; $motivos[] = 'categoría'; }
            if (!empty($base['id_marca']) && (int) ($p['id_marca'] ?? 0) === (int) $base['id_marca'])             { $puntaje += 2; $motivos[] = 'marca'; }
            $nombreP = $norm((string) $p['nombre']);
            $comunes = count(array_filter($palabras, fn($w) => str_contains($nombreP, $w)));
            if ($comunes > 0) { $puntaje += $comunes; $motivos[] = 'nombre'; }

            $p['stock_actual']   = $stock;
            $p['stock_bodega']   = $stock; // nombre que usa el buscador de Facturas de Venta
            $p['controla_stock'] = true;
            $p['coincide']       = implode(', ', $motivos);
            $p['_puntaje']       = $puntaje;
            $out[] = $p;
        }
        usort($out, fn($a, $b) => [$b['_puntaje'], $b['stock_actual']] <=> [$a['_puntaje'], $a['stock_actual']]);
        $out = array_map(function ($p) { unset($p['_puntaje']); return $p; }, array_slice($out, 0, $limite));

        // Precios de lista y variantes EN LOTE, igual que el buscador de productos.
        $ids        = array_column($out, 'id');
        $preciosMap = $this->prodRepo->getPreciosPorProductos($ids, $idEmpresa);
        $variantMap = $this->prodRepo->getVariantesPorProductos($ids, $idEmpresa);
        foreach ($out as &$p) {
            $p['precios_lista'] = $preciosMap[(int) $p['id']] ?? [];
            $p['variantes']     = $variantMap[(int) $p['id']] ?? [];
        }
        unset($p);

        return $out;
    }

    /** Bien que afecta al inventario (inventariable y no servicio), mismo criterio que las vistas. */
    private static function esInventariable(array $p): bool
    {
        $inv = $p['inventariable'] ?? false;
        return ($inv === true || $inv === 't' || $inv === 'true' || $inv == 1)
            && (string) ($p['tipo_produccion'] ?? '01') !== '02';
    }
}

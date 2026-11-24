<?php

declare(strict_types=1);

namespace App\Helpers;

use App\repositories\modulos\ProductoRepository;

/**
 * Empresa → Facturación «¿Se puede editar la descripción de un producto o servicio
 * en la factura?» (empresa_establecimiento.editar_descripcion_factura).
 *
 * Con la opción encendida, una línea puede llevar el código de un producto que ya
 * existe y una descripción distinta a la del catálogo. El documento guarda su
 * propia copia y, en Factura y Recibo de Venta, el catálogo toma esa descripción
 * (DescripcionProductoDocumentoService).
 */
final class DescripcionItemDocumento
{
    /**
     * Una línea llega como ítem libre (sin id_producto) pero con el código de un
     * producto que ya existe. Sin esto, el guardado intenta crear un servicio nuevo
     * con ese código y se rechaza con «El código … ya existe en el catálogo».
     *
     * Si la configuración lo permite, la línea se enlaza al producto existente y
     * conserva la descripción escrita. Debe llamarse ANTES de leer el control de
     * inventario del producto y de validar las reglas, para que la línea pase por
     * las mismas validaciones (stock, lotes) que cualquier producto del catálogo.
     *
     * No hace nada si la opción está apagada, si la línea ya tiene producto o si el
     * código no existe (en ese caso sigue el flujo normal de ítem libre).
     */
    public static function enlazarCodigoExistente(array &$det, int $idEmpresa, ?array $estConfig, ProductoRepository $productos): void
    {
        if (!Booleano::es($estConfig['editar_descripcion_factura'] ?? false)) {
            return;
        }
        if (!empty($det['id_producto']) || !Booleano::es($det['es_libre'] ?? false)) {
            return;
        }
        $codigo = trim((string) ($det['codigo_principal'] ?? ''));
        if ($codigo === '' || strtoupper($codigo) === '__LIBRE__') {
            return;
        }
        $id = $productos->getIdVigentePorCodigo($idEmpresa, $codigo);
        if ($id === null) {
            return;
        }
        $det['id_producto'] = $id;
        $det['es_libre']    = '0';
        // La descripción la escribió el usuario para este código: también se lleva
        // al catálogo (DescripcionProductoDocumentoService::actualizarCatalogo).
        $det['descripcion_editada'] = '1';
    }
}

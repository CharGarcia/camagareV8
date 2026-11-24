<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Booleano;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\ProductoRules;
use App\Services\LogSistemaService;

/**
 * Empresa → Facturación «¿Se puede editar la descripción de un producto o servicio
 * en la factura?» (empresa_establecimiento.editar_descripcion_factura).
 *
 * Cuando el usuario cambia la descripción de un producto en la Factura o el Recibo
 * de Venta, ese mismo código queda con la descripción nueva también en el catálogo
 * de Productos (decisión del usuario, 2026-11-24).
 *
 * Solo actúa sobre las líneas que el modal marcó con `descripcion_editada` (el
 * usuario escribió sobre la descripción en esa sesión). Los demás flujos que crean
 * facturas con descripciones propias (proformas, consignaciones, suscripciones,
 * carga por Excel, API) no envían esa marca, así que nunca renombran el catálogo.
 */
final class DescripcionProductoDocumentoService
{
    /**
     * Debe llamarse DENTRO de la transacción del documento (no abre la suya).
     *
     * Si otro producto de la empresa ya usa ese nombre, o supera el largo permitido,
     * se rechaza el guardado con un mensaje claro (misma regla que el módulo de
     * Productos: el nombre no se repite).
     */
    public static function actualizarCatalogo(
        array $detalles,
        int $idEmpresa,
        int $idUsuario,
        ?array $estConfig,
        ProductoRepository $productos,
        LogSistemaService $log,
        string $origen
    ): void {
        if (!Booleano::es($estConfig['editar_descripcion_factura'] ?? false)) {
            return;
        }

        $hechos = [];
        foreach ($detalles as $det) {
            $idProducto = (int) ($det['id_producto'] ?? 0);
            if ($idProducto <= 0 || isset($hechos[$idProducto]) || !Booleano::es($det['descripcion_editada'] ?? false)) {
                continue;
            }
            $nombre = trim((string) preg_replace('/\s+/u', ' ', (string) ($det['descripcion'] ?? '')));
            if ($nombre === '') {
                continue;
            }
            $hechos[$idProducto] = true;

            $actual = $productos->getNombreVigente($idProducto, $idEmpresa);
            if ($actual === null || $actual === $nombre) {
                continue;
            }
            if (mb_strlen($nombre) > ProductoRules::MAX_NOMBRE) {
                throw new \Exception("\"{$actual}\": la descripción nueva supera " . ProductoRules::MAX_NOMBRE . ' caracteres.');
            }
            if ($productos->existeNombre($idEmpresa, $nombre, $idProducto)) {
                throw new \Exception("No se puede cambiar la descripción a \"{$nombre}\": ya existe otro producto con ese nombre. Use otra descripción o elija ese producto.");
            }

            $productos->actualizarNombre($idProducto, $idEmpresa, $nombre, $idUsuario);
            $log->registrar(
                $idUsuario,
                $idEmpresa,
                'MODIFICAR',
                'productos',
                $idProducto,
                ['nombre' => $actual],
                ['nombre' => $nombre, 'origen' => $origen]
            );
        }
    }
}

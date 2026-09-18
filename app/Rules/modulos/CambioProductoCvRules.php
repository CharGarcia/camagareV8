<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio para Cambios de productos (`modulos/cambio-producto-cv`).
 */
class CambioProductoCvRules
{
    /**
     * Numeración obligatoria al EMITIR (solo en la creación): sin punto de emisión el
     * documento quedaría sin número de serie.
     *
     * El secuencial y la serie NO se validan aquí: ya no llegan del navegador (eran la vista
     * previa del modal), los reserva el servidor al guardar —CambioProductoCvService::
     * reservarNumero()—, que es también quien avisa si el punto no tiene numeración configurada.
     */
    public function validarNumeracion(array $data): void
    {
        if (empty($data['id_punto_emision'])) {
            throw new Exception("Debe seleccionar la serie (punto de emisión). Configúrela en Empresa → Secuenciales.");
        }
    }

    /**
     * Cabecera y líneas del cambio, al crearlo y al guardar un borrador.
     *
     * Lo que se ENTREGA a cambio sale SOLO de una consignación (decisión del usuario,
     * 18-09-2026): se rechaza cualquier entrega desde bodega o catálogo. La única excepción son
     * las que un borrador anterior a esa fecha ya tenía guardadas ($entregasPrevias, solo al
     * editar: id de la línea guardada → línea). Esas viajan con su `id_detalle` y se pueden
     * conservar, reducir o quitar, y corregir su bodega, lote y NUP, pero no aparecer de nuevo,
     * cambiar de producto ni aumentar su cantidad.
     */
    public function validarCreacion(array $data, array $entregasPrevias = []): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("El cliente es obligatorio.");
        }
        if (empty($data['fecha_cambio'])) {
            throw new Exception("La fecha del cambio es obligatoria.");
        }

        $devoluciones = array_filter($data['devoluciones'] ?? [], fn($d) => (float)($d['cantidad'] ?? 0) > 0);
        $entregas     = array_filter($data['entregas'] ?? [],    fn($d) => (float)($d['cantidad'] ?? 0) > 0);

        if (empty($devoluciones)) {
            throw new Exception("Debe agregar al menos un producto a devolver (con cantidad mayor a 0).");
        }

        foreach ($devoluciones as $idx => $det) {
            if (empty($det['origen_tipo']) || empty($det['id_origen_detalle'])) {
                throw new Exception("Hay una línea de devolución sin origen (factura o cambio) en la fila " . ($idx + 1) . ".");
            }
            if (!in_array($det['origen_tipo'], ['FACTURA', 'CAMBIO'], true)) {
                throw new Exception("Origen de devolución no válido en la fila " . ($idx + 1) . ".");
            }
        }

        $previasUsadas = [];
        foreach ($entregas as $idx => $det) {
            $fila   = $idx + 1;
            $origen = strtoupper((string) ($det['origen_tipo'] ?? ''));
            if ($origen === 'CONSIGNACION') {
                // Tomada de una consignación: el producto lo determina la línea de consignación.
                if (empty($det['id_origen_detalle'])) {
                    throw new Exception("Hay una línea de entrega desde consignación sin la línea de origen en la fila {$fila}.");
                }
                continue;
            }
            if ($origen !== '') {
                throw new Exception("Origen de entrega no válido en la fila {$fila}.");
            }

            // Desde bodega o catálogo: solo una línea que este borrador ya tenía, y una sola vez.
            $idPrevia = (int) ($det['id_detalle'] ?? 0);
            $previa   = $entregasPrevias[$idPrevia] ?? null;
            if ($previa === null || isset($previasUsadas[$idPrevia])) {
                throw new Exception("Lo que se entrega a cambio debe salir de una consignación (fila {$fila} de productos que entrega): búsquela por su número. Ya no se entregan productos desde bodega ni catálogo.");
            }
            $previasUsadas[$idPrevia] = true;

            $nombre = $previa['producto_nombre'] ?? 'Producto';
            if ((int) ($det['id_producto'] ?? 0) !== (int) $previa['id_producto']) {
                throw new Exception("La entrega desde bodega de \"{$nombre}\" (fila {$fila}) no puede cambiar de producto: quítela y entregue desde una consignación.");
            }
            $maximo = (float) $previa['cantidad'];
            if ((float) $det['cantidad'] > $maximo + 1e-9) {
                throw new Exception("La entrega desde bodega de \"{$nombre}\" (fila {$fila}) no puede aumentar su cantidad (máximo {$maximo}): lo que se agregue debe salir de una consignación.");
            }
        }
    }
}

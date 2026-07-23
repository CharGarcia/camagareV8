<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio del módulo Comandas (POS Restaurantes).
 */
class ComandaRules
{
    public function validarAbrir(?array $mesa): void
    {
        if (!$mesa) {
            throw new Exception('La mesa no existe.');
        }
        if (($mesa['estado'] ?? '') !== 'disponible') {
            throw new Exception('La mesa no está disponible.');
        }
    }

    /** Solo se puede agregar/anular líneas o modificar la cabecera mientras la comanda está abierta. */
    public function validarPuedeModificar(?array $comanda): void
    {
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        if (($comanda['estado'] ?? '') !== 'abierta') {
            throw new Exception('La comanda no está abierta; no se puede modificar.');
        }
    }

    public function validarLinea(array $item): void
    {
        if (empty($item['id_producto'])) {
            throw new Exception('Selecciona un producto.');
        }
        if ((float) ($item['cantidad'] ?? 0) <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0.');
        }
    }

    /** Orden de avance de una línea en cocina/barra; 'anulado' se maneja aparte (anularLinea). */
    private const ORDEN_ESTADO_LINEA = ['pendiente' => 0, 'enviado' => 1, 'preparando' => 2, 'listo' => 3, 'entregado' => 4];

    /** Solo se permite avanzar (nunca retroceder ni saltar a/desde 'anulado') el estado de una línea. */
    public function validarTransicionLinea(string $actual, string $nuevo): void
    {
        if (!isset(self::ORDEN_ESTADO_LINEA[$nuevo])) {
            throw new Exception('Estado no válido.');
        }
        if ($actual === 'anulado') {
            throw new Exception('Este ítem está anulado.');
        }
        if (!isset(self::ORDEN_ESTADO_LINEA[$actual]) || self::ORDEN_ESTADO_LINEA[$nuevo] <= self::ORDEN_ESTADO_LINEA[$actual]) {
            throw new Exception('No se puede pasar de "' . $actual . '" a "' . $nuevo . '".');
        }
    }
}

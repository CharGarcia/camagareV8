<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de las órdenes de producción (docs/diseno/produccion-fase1.md §7).
 */
class ProduccionOrdenRules
{
    public const ESTADOS = [
        'liberada'   => 'Planificada',
        'en_proceso' => 'En proceso',
        'cerrada'    => 'Cerrada',
        'anulada'    => 'Anulada',
    ];

    /** Tipos visibles en la fase 1 (por_pedido, ciclo y maquila llegan en fases 2 y 3). */
    public const TIPOS = [
        'orden'  => 'Orden de producción',
        'rapida' => 'Producción rápida',
    ];

    public function validarCreacion(array $d, ?array $formula): void
    {
        if (!$formula) {
            throw new \DomainException('Elija una fórmula vigente.');
        }
        if (($formula['estado'] ?? '') !== 'vigente') {
            throw new \DomainException('Solo se puede producir con una fórmula vigente.');
        }
        if (!isset(self::TIPOS[$d['tipo']])) {
            throw new \DomainException('Tipo de orden no válido.');
        }
        if ((float) $d['cantidad_planificada'] <= 0) {
            throw new \DomainException('Indique cuánto va a producir (mayor que cero).');
        }
        if ((int) $d['id_bodega_insumos'] <= 0) {
            throw new \DomainException('Elija la bodega de la que salen los insumos.');
        }
        if ((int) $d['id_bodega_destino'] <= 0) {
            throw new \DomainException('Elija la bodega a la que entra lo producido.');
        }
        $this->validarFecha($d['fecha_inicio'], 'de la orden');
        if (!empty($d['fecha_caducidad']) && $d['fecha_caducidad'] < $d['fecha_inicio']) {
            throw new \DomainException('La caducidad no puede ser anterior a la fecha de la orden.');
        }
    }

    public function validarAbierta(array $orden, string $accion): void
    {
        if (!in_array($orden['estado'], ['liberada', 'en_proceso'], true)) {
            $estado = self::ESTADOS[$orden['estado']] ?? $orden['estado'];
            throw new \DomainException("No se puede {$accion}: la orden está {$estado}.");
        }
    }

    public function validarFecha(string $fecha, string $de): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
            throw new \DomainException("La fecha {$de} no es válida.");
        }
        if ($fecha > date('Y-m-d')) {
            throw new \DomainException("La fecha {$de} no puede ser futura.");
        }
    }
}

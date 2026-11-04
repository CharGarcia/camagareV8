<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de las fórmulas de producción (docs/diseno/produccion-fase1.md §7).
 * Trabaja sobre los datos ya normalizados por ProduccionFormulaService::normalizar().
 */
class ProduccionFormulaRules
{
    public const ROLES = ['insumo', 'coproducto', 'subproducto', 'desperdicio'];
    public const MODOS = ['proporcional' => 'Proporcional', 'porcentaje' => '% del lote', 'fija' => 'Fija por orden'];
    public const TIPOS_RECURSO = ['mo' => 'Mano de obra', 'maquina' => 'Máquina', 'cif' => 'Costos indirectos'];
    public const BASES_RECURSO = [
        'unidad' => 'Por unidad producida',
        'lote'   => 'Fijo por orden',
        'hora'   => 'Por hora (horas de la cantidad base)',
        'pct_mp' => '% de la materia prima',
    ];

    private const TOLERANCIA_PCT = 0.01;

    /**
     * @param array $d        cabecera + 'lineas' + 'recursos' normalizados
     * @param array $productos datos de los productos usados (id => fila)
     */
    public function validar(array $d, array $productos, string $metodoReparto): void
    {
        if (trim($d['codigo']) === '') {
            throw new \DomainException('Indique el código de la fórmula.');
        }
        if (trim($d['nombre']) === '') {
            throw new \DomainException('Indique el nombre de la fórmula.');
        }
        if ((int) $d['id_producto'] <= 0 || !isset($productos[(int) $d['id_producto']])) {
            throw new \DomainException('Elija el producto que se fabrica (salida principal).');
        }
        if ((float) $d['cantidad_base'] <= 0) {
            throw new \DomainException('La cantidad que rinde la fórmula debe ser mayor que cero.');
        }
        if ($d['vida_util_dias'] !== null && (int) $d['vida_util_dias'] < 0) {
            throw new \DomainException('La vida útil no puede ser negativa.');
        }
        $this->validarProducto($productos[(int) $d['id_producto']], 'El producto que se fabrica');

        $insumos = array_values(array_filter($d['lineas'], fn($l) => $l['rol'] === 'insumo'));
        if ($insumos === []) {
            throw new \DomainException('Agregue al menos un insumo (lo que se consume para fabricar).');
        }

        $vistos  = [];
        $sumaPct = 0.0;
        $todosPct = true;
        foreach ($d['lineas'] as $i => $l) {
            $n = $i + 1;
            if (!in_array($l['rol'], array_merge(self::ROLES, ['principal']), true)) {
                throw new \DomainException("Línea {$n}: tipo de línea no válido.");
            }
            if ($l['rol'] === 'principal') {
                continue;
            }
            if (!isset(self::MODOS[$l['modo_cantidad']])) {
                throw new \DomainException("Línea {$n}: forma de cantidad no válida.");
            }
            if ((float) $l['cantidad'] <= 0) {
                throw new \DomainException("Línea {$n}: la cantidad debe ser mayor que cero.");
            }
            if ($l['rol'] === 'desperdicio') {
                if (empty($l['id_producto']) && trim((string) $l['descripcion']) === '') {
                    throw new \DomainException("Línea {$n}: describa el desperdicio o elija un producto.");
                }
            } else {
                $idP = (int) ($l['id_producto'] ?? 0);
                if ($idP <= 0 || !isset($productos[$idP])) {
                    throw new \DomainException("Línea {$n}: elija el producto.");
                }
                $this->validarProducto($productos[$idP], "Línea {$n} ({$productos[$idP]['nombre']})");
                if ($idP === (int) $d['id_producto']) {
                    throw new \DomainException("Línea {$n}: el producto que se fabrica no puede ser también {$this->nombreRol($l['rol'])} de su propia fórmula.");
                }
                $clave = $l['rol'] . ':' . $idP;
                if (isset($vistos[$clave])) {
                    throw new \DomainException("Línea {$n}: «{$productos[$idP]['nombre']}» está repetido.");
                }
                $vistos[$clave] = true;
            }
            if ($l['rol'] === 'insumo') {
                if ((float) $l['merma_pct'] < 0 || (float) $l['merma_pct'] >= 100) {
                    throw new \DomainException("Línea {$n}: la merma debe estar entre 0 y 99,99 %.");
                }
                if ($l['modo_cantidad'] === 'porcentaje') {
                    $sumaPct += (float) $l['cantidad'];
                } else {
                    $todosPct = false;
                }
            }
            if ($l['rol'] === 'subproducto' && (float) ($l['valor_neto_realizable'] ?? 0) < 0) {
                throw new \DomainException("Línea {$n}: el valor del subproducto no puede ser negativo.");
            }
        }

        if ($sumaPct > 100 + self::TOLERANCIA_PCT) {
            throw new \DomainException('Los insumos en % del lote suman ' . number_format($sumaPct, 2) . ' %: no pueden pasar de 100 %.');
        }
        if ($todosPct && abs($sumaPct - 100) > self::TOLERANCIA_PCT) {
            throw new \DomainException('La fórmula está toda en % del lote y suma ' . number_format($sumaPct, 2) . ' %: debe sumar 100 %.');
        }

        // Reparto fijo: principal + coproductos deben sumar 100 %.
        $hayCoproductos = (bool) array_filter($d['lineas'], fn($l) => $l['rol'] === 'coproducto');
        if ($metodoReparto === 'porcentaje' && $hayCoproductos) {
            $suma = 0.0;
            foreach ($d['lineas'] as $l) {
                if (in_array($l['rol'], ['principal', 'coproducto'], true)) {
                    $suma += (float) ($l['reparto_pct'] ?? 0);
                }
            }
            if (abs($suma - 100) > self::TOLERANCIA_PCT) {
                throw new \DomainException('Con reparto por % fijo, la salida principal y los coproductos deben sumar 100 % (hoy suman ' . number_format($suma, 2) . ' %).');
            }
        }

        foreach ($d['recursos'] as $i => $r) {
            $n = $i + 1;
            if (!isset(self::TIPOS_RECURSO[$r['tipo']]) || !isset(self::BASES_RECURSO[$r['base']])) {
                throw new \DomainException("Costo {$n}: tipo o base de cálculo no válidos.");
            }
            if ((float) $r['cantidad'] < 0 || (float) $r['tarifa'] < 0) {
                throw new \DomainException("Costo {$n}: la cantidad y la tarifa no pueden ser negativas.");
            }
        }
    }

    /** Requisitos para dejar una fórmula vigente. */
    public function validarActivacion(array $formula, bool $tieneComponentesVenta, bool $generaCiclo): void
    {
        if ($tieneComponentesVenta) {
            throw new \DomainException(
                '«' . $formula['producto_nombre'] . '» tiene componentes de venta en su ficha de Productos (se descuentan al venderlo). '
                . 'Si además se fabrica con esta fórmula, sus insumos se descontarían dos veces. Quite los componentes de la ficha del producto o use otro producto.'
            );
        }
        if ($generaCiclo) {
            throw new \DomainException('Esta fórmula forma un ciclo con otra vigente (un insumo se fabrica, directa o indirectamente, con el mismo producto).');
        }
    }

    private function validarProducto(array $p, string $quien): void
    {
        $inventariable = $p['inventariable'] === true || $p['inventariable'] === 't' || $p['inventariable'] === 1 || $p['inventariable'] === '1';
        if (!$inventariable || (string) ($p['tipo_produccion'] ?? '01') === '02') {
            throw new \DomainException("{$quien} debe ser un bien inventariable (no un servicio).");
        }
    }

    private function nombreRol(string $rol): string
    {
        return match ($rol) {
            'insumo'      => 'insumo',
            'coproducto'  => 'coproducto',
            'subproducto' => 'subproducto',
            default       => 'desperdicio',
        };
    }
}

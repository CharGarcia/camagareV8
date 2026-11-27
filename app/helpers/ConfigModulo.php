<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de opciones personalizables por empresa (y, si la opción lo admite,
 * por cliente) de cada módulo. Se editan en Configuración de Empresa
 * (modulos/empresa): cada módulo del catálogo tiene su propia pestaña, que se
 * dibuja sola a partir de aquí.
 *
 * Es la única fuente de verdad de qué opciones existen, de qué tipo son y cuál
 * es su valor por defecto: la pestaña las lista desde aquí, las Rules rechazan
 * cualquier clave o valor que no encaje y ConfigModuloService::valor() devuelve
 * el valor por defecto cuando no hay nada guardado. Por eso, sin configuración,
 * todo sale exactamente igual que antes.
 *
 * Los valores se guardan en `config_modulos` (database/2026-11-24_config_modulos.sql):
 * una fila de la empresa (id_cliente NULL) y una por cliente con valores propios.
 *
 * Resolución de una opción: cliente (si 'por_cliente' y el cliente la tiene) →
 * empresa → valor por defecto del catálogo.
 *
 * Tipos de opción:
 *  - 'bool'     casilla (true/false).
 *  - 'select'   lista cerrada: 'valores' => [valor => etiqueta].
 *  - 'texto'    texto libre de hasta 'max' caracteres.
 *  - 'columnas' qué columnas se muestran: 'columnas' => [clave => ['titulo', 'def'
 *               (visible por defecto), 'fija' (no se puede quitar)]]. El valor es
 *               la lista de claves visibles, en el orden del catálogo.
 *
 * 'por_cliente' => true: además del valor de la empresa, se pueden agregar
 * clientes con un valor propio (la pestaña muestra la tabla de clientes).
 *
 * Agregar una opción nueva: una entrada más en 'opciones' (sin SQL) y, en el
 * módulo, leerla con ConfigModuloService::crear()->valor($idEmpresa, $modulo,
 * $clave, $idCliente). Agregar un módulo nuevo: otra entrada en CATALOGO; su
 * pestaña aparece sola en Configuración de Empresa.
 * Manual: docs/manual/guias/personalizacion-modulos.md.
 */
final class ConfigModulo
{
    public const TIPOS = ['bool', 'select', 'texto', 'columnas'];

    /** @var array<string, array{titulo:string, icono:string, descripcion:string, opciones: array<string, array<string,mixed>>}> */
    public const CATALOGO = [
        'modulos/consignaciones-ventas' => [
            'titulo'      => 'Consignaciones',
            'icono'       => 'bi-truck',
            'descripcion' => 'Personaliza el comprobante de las Consignaciones de Venta.',
            'opciones'    => [
                // Columnas del detalle de productos del PDF y del Excel del comprobante
                // (ConsignacionVentaPdfService / ConsignacionesVentasController::excel()).
                // Los defaults reproducen lo que salía antes de esta opción.
                'columnas_pdf' => [
                    'tipo'        => 'columnas',
                    'etiqueta'    => 'Columnas del detalle de productos (PDF y Excel)',
                    'ayuda'       => 'Lote y Caducidad, aunque estén marcadas, solo salen si alguna línea de la consignación las tiene. La Caducidad solo se imprime en el PDF: el Excel no la lleva.',
                    'por_cliente' => true,
                    'columnas'    => [
                        'codigo'      => ['titulo' => 'Código',      'def' => true],
                        'descripcion' => ['titulo' => 'Descripción', 'def' => true, 'fija' => true],
                        'bodega'      => ['titulo' => 'Bodega',      'def' => true],
                        'lote'        => ['titulo' => 'Lote',        'def' => true],
                        'nup'         => ['titulo' => 'NUP',         'def' => true],
                        'caducidad'   => ['titulo' => 'Caducidad',   'def' => false],
                        'cantidad'    => ['titulo' => 'Cantidad',    'def' => true, 'fija' => true],
                        'retornado'   => ['titulo' => 'Retorno',     'def' => true],
                        'facturado'   => ['titulo' => 'Facturados',  'def' => true],
                        'acon'        => ['titulo' => 'Acon',        'def' => true],
                    ],
                ],
            ],
        ],
        'modulos/roles-pago' => [
            'titulo'      => 'Roles de Pago',
            'icono'       => 'bi-cash-coin',
            'descripcion' => 'Cómo se contabiliza el rol mensual. «Automático» es el comportamiento de siempre: un solo asiento para todos los empleados, salvo que alguno tenga cuentas propias (pestaña Contable del empleado o Configuración Contable), y entonces un asiento por cada empleado. «Uno general» suma todo en un asiento aunque haya cuentas propias (cada cuenta propia queda en su propia línea). «Uno por empleado» genera siempre un asiento por empleado. El cambio rige para los roles que se contabilicen desde ahora.',
            'opciones'    => [
                // Lo lee RolAsientoService::modoAsientoEmpresa().
                'modo_asiento' => [
                    'tipo'     => 'select',
                    'etiqueta' => 'Asiento contable del rol mensual',
                    'def'      => 'automatico',
                    'valores'  => [
                        'automatico'   => 'Automático (como siempre)',
                        'general'      => 'Uno general para todos los empleados',
                        'por_empleado' => 'Uno por cada empleado',
                    ],
                ],
            ],
        ],
    ];

    /** Definición del módulo, o null si no es personalizable. */
    public static function definicion(string $modulo): ?array
    {
        return self::CATALOGO[$modulo] ?? null;
    }

    /** Definición de una opción, o null si no existe. */
    public static function opcion(string $modulo, string $clave): ?array
    {
        return self::CATALOGO[$modulo]['opciones'][$clave] ?? null;
    }

    /** @return array<string, array<string,mixed>> clave => definición */
    public static function opciones(string $modulo): array
    {
        return self::CATALOGO[$modulo]['opciones'] ?? [];
    }

    /**
     * Valor por defecto de una opción (null si no existe). En las de tipo
     * 'columnas' es la lista de columnas marcadas 'def' (más las fijas).
     */
    public static function porDefecto(string $modulo, string $clave)
    {
        $def = self::opcion($modulo, $clave);
        if ($def === null) {
            return null;
        }
        if ($def['tipo'] === 'columnas') {
            $out = [];
            foreach ($def['columnas'] as $k => $c) {
                if (!empty($c['def']) || !empty($c['fija'])) {
                    $out[] = $k;
                }
            }
            return $out;
        }
        return $def['def'] ?? null;
    }

    /**
     * Identificador seguro para HTML (ids, nombres de pestaña) a partir de la ruta
     * del módulo: 'modulos/consignaciones-ventas' → 'consignaciones-ventas'.
     */
    public static function slug(string $modulo): string
    {
        $s = preg_replace('~^modulos/~', '', $modulo);
        return preg_replace('~[^a-z0-9_-]~', '-', strtolower((string) $s));
    }
}

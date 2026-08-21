<?php
/**
 * Generación automática de asientos contables: qué módulo genera qué.
 *
 * Al abrir un módulo operativo, el sistema genera en segundo plano los asientos
 * contables que le falten a ESE módulo (nunca a los demás). Este archivo es el
 * único punto donde se declara esa relación.
 *
 * Flujo completo:
 *   vista (layout) → app/views/partials/contabilidad_auto.php
 *                  → /modulos/contabilidad-auto/generar-ajax?modulo={ruta}
 *                  → ContabilidadAutoController → ContabilidadAutoService
 *                  → SincronizadorAsientosService (SQL de detección + service del módulo)
 *
 * ─── CÓMO AGREGAR UN MÓDULO ─────────────────────────────────────────────────
 *
 * 1. El trabajo debe existir en SincronizadorAsientosService::construirTrabajos()
 *    con una 'clave' — esa misma clave es la que se usa aquí.
 * 2. Agregar la entrada con la ruta MVC del módulo y CÓMO se sabe si la empresa
 *    tiene configuración contable para él (ver abajo).
 * 3. Nada más: el disparo, el candado, el tope por pasada y el registro de
 *    fallos son automáticos.
 *
 * ─── CAMPOS ─────────────────────────────────────────────────────────────────
 *
 *   nombre       Etiqueta legible (solo para logs).
 *   rutas        Ruta(s) MVC que disparan este trabajo. Un módulo puede disparar
 *                varios trabajos (Consignaciones) y un trabajo puede dispararse
 *                desde varias rutas.
 *   conceptos    Valores de asientos_tipo.tipo_asiento. Hay configuración si
 *                existe una fila en asientos_programados con cuenta asignada
 *                para alguno de esos conceptos.
 *   conceptos_firma
 *                Conceptos que NO abren la compuerta pero sí entran en la firma.
 *                Para los módulos cuyo generador usa un concepto propio y además
 *                cae a otro como respaldo: el respaldo por sí solo no alcanza
 *                para contabilizar (faltaría la cuenta principal), pero si el
 *                usuario corrige una cuenta ahí, los documentos que fallaron
 *                deben reintentarse igual.
 *   referencias  Valores de asientos_programados.tipo_referencia, para los
 *                módulos que NO se configuran por concepto sino por referencia
 *                (retenciones por código, formas de cobro/pago, conceptos de
 *                Ingresos/Egresos).
 *   tablas       Fuentes de configuración que viven en la tabla del propio
 *                módulo, no en asientos_programados. El sistema lee la cuenta
 *                con COALESCE(asientos_programados.id_cuenta, tabla.col_cuenta)
 *                — ver AsientoBuilderService::lineasFormas() —, así que la
 *                compuerta tiene que mirar ambos sitios o daría "sin configurar"
 *                a empresas que sí tienen sus cuentas puestas desde el módulo.
 *                Cada entrada: ['tabla', 'col_cuenta', 'filtro' (SQL opcional)].
 *
 * La misma definición se usa para dos cosas: decidir si se genera algo (¿hay al
 * menos una cuenta?) y calcular la FIRMA de la configuración — un hash de esas
 * filas. Cuando el usuario corrige una cuenta, la firma cambia y los documentos
 * que habían fallado se vuelven a intentar. Mientras no cambie, no se reintentan.
 */

declare(strict_types=1);

return [

    // ─── Ventas ─────────────────────────────────────────────────────────────
    'facturas_venta' => [
        'nombre'    => 'Facturas de Venta',
        'rutas'     => ['modulos/factura-venta'],
        'conceptos' => ['ventas_factura'],
    ],

    'recibos_venta' => [
        'nombre'    => 'Recibos de Venta',
        'rutas'     => ['modulos/recibo-venta'],
        'conceptos' => ['recibos_venta'],
    ],

    // Las NC de venta se arman con el catálogo de cuentas de la factura
    // (AsientoBuilderService::generarAsientoNotaCreditoVenta usa 'ventas_factura').
    'notas_credito' => [
        'nombre'    => 'Notas de Crédito',
        'rutas'     => ['modulos/notas_credito'],
        'conceptos' => ['ventas_factura'],
    ],

    'retenciones_venta' => [
        'nombre'      => 'Retenciones en Ventas',
        'rutas'       => ['modulos/retenciones_ventas'],
        // Se configura por CÓDIGO de retención, no por concepto.
        'referencias' => ['retenciones_venta_debe', 'retenciones_venta'],
    ],

    // ─── Compras ────────────────────────────────────────────────────────────
    'compras' => [
        'nombre'    => 'Facturas de Compra',
        'rutas'     => ['modulos/compras'],
        'conceptos' => ['adquisiciones_compras'],
    ],

    'liquidaciones_compra' => [
        'nombre'    => 'Liquidaciones de Compra',
        'rutas'     => ['modulos/liquidacion-compra'],
        'conceptos' => ['adquisiciones_compras'],
    ],

    'retenciones_compra' => [
        'nombre'      => 'Retenciones en Compras',
        'rutas'       => ['modulos/retenciones_compras'],
        'referencias' => ['retenciones_compra_haber'],
    ],

    'importaciones' => [
        'nombre'    => 'Importaciones',
        'rutas'     => ['modulos/importaciones'],
        'conceptos' => ['adquisiciones_importacion'],
    ],

    // ─── Tesorería ──────────────────────────────────────────────────────────
    // El asiento sale de dos lados: el concepto (opción) del documento y la
    // forma de cobro/pago. Ambos pueden tener la cuenta en asientos_programados
    // o en su propio módulo, de ahí las tres fuentes.
    'ingresos' => [
        'nombre'      => 'Ingresos',
        'rutas'       => ['modulos/ingresos'],
        'referencias' => ['opcion_ingreso', 'forma_cobro'],
        'tablas'      => [
            [
                'tabla'      => 'empresa_opciones_ingreso_egreso',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => "aplica_ingresos = TRUE AND UPPER(estado) = 'ACTIVO'",
            ],
            [
                'tabla'      => 'empresa_formas_pago',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => "activo = TRUE AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')",
            ],
        ],
    ],

    'egresos' => [
        'nombre'      => 'Egresos',
        'rutas'       => ['modulos/egresos'],
        'referencias' => ['opcion_egreso', 'forma_pago'],
        'tablas'      => [
            [
                'tabla'      => 'empresa_opciones_ingreso_egreso',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => "aplica_egresos = TRUE AND UPPER(estado) = 'ACTIVO'",
            ],
            [
                'tabla'      => 'empresa_formas_pago',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => "activo = TRUE AND (aplica_en = 'AMBAS' OR aplica_en = 'EGRESO')",
            ],
        ],
    ],

    // ─── Consignaciones ─────────────────────────────────────────────────────
    // Tres de estos trabajos mueven la cuenta «Mercadería en Consignación», que
    // solo existe en el concepto 'consignacion_venta': sin ese concepto
    // configurado el asiento saldría sin cuenta, así que es él quien abre la
    // compuerta. La cuenta de Inventario, en cambio, tiene fallback a
    // 'ventas_factura' (ver AsientoBuilderService::generarAsientoConsignacion),
    // por eso ese concepto va en 'conceptos_firma': no abre la compuerta, pero
    // si el usuario corrige ahí la cuenta de inventario, la firma cambia y los
    // documentos que habían fallado se reintentan.
    'consignaciones' => [
        'nombre'          => 'Consignaciones en Ventas',
        'rutas'           => ['modulos/consignaciones-ventas'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
    ],

    'retornos_cv' => [
        'nombre'          => 'Retornos de Consignaciones',
        'rutas'           => ['modulos/retornos-cv'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
    ],

    // Excepción entre los cuatro: el cambio de productos NO toca la cuenta de
    // consignación. Es un neto entre Inventario y Costo de Ventas, y ambas
    // cuentas las toma de 'ventas_factura' (generarAsientoCambioProductoCv).
    // Exigir aquí 'consignacion_venta' dejaría sin generar a las empresas que
    // llevan ventas configuradas pero no consignaciones.
    'cambios_producto_cv' => [
        'nombre'    => 'Cambios de Productos',
        'rutas'     => ['modulos/cambio-producto-cv'],
        'conceptos' => ['ventas_factura'],
    ],

    'facturacion_cv' => [
        'nombre'          => 'Facturación de Consignaciones',
        'rutas'           => ['modulos/facturacion-cv'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
    ],

    // ─── Nómina ─────────────────────────────────────────────────────────────
    'roles_pago' => [
        'nombre'    => 'Roles de Pago',
        'rutas'     => ['modulos/roles-pago'],
        'conceptos' => ['nomina'],
    ],
];

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
 *   grupo        Encabezado bajo el que se lista el módulo en la ventana
 *                «Módulos que contabilizan» de Configuración Contable.
 *   ayuda        (Opcional) Texto que explica qué deja de pasar al apagarlo.
 *   sigue_a      (Opcional) El módulo NO tiene interruptor propio: su asiento
 *                depende del documento de origen que pertenece a otro módulo
 *                (Retornos y Facturación CV siguen a su consignación madre).
 *
 * INTERRUPTOR POR EMPRESA: cada módulo con interruptor puede apagarse por
 * empresa (tabla contabilidad_modulos_empresa, ContabilidadInterruptorService).
 * Apagado, no se contabilizan los documentos que aún no tienen asiento; los que
 * ya lo tienen lo conservan y lo siguen actualizando.
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
        'grupo'     => 'Ventas',
        'rutas'     => ['modulos/factura-venta'],
        'conceptos' => ['ventas_factura'],
    ],

    'recibos_venta' => [
        'nombre'    => 'Recibos de Venta',
        'grupo'     => 'Ventas',
        'rutas'     => ['modulos/recibo-venta'],
        'conceptos' => ['recibos_venta'],
    ],

    // Las NC de venta se arman con el catálogo de cuentas de la factura
    // (AsientoBuilderService::generarAsientoNotaCreditoVenta usa 'ventas_factura').
    'notas_credito' => [
        'nombre'    => 'Notas de Crédito',
        'grupo'     => 'Ventas',
        'rutas'     => ['modulos/notas_credito'],
        'conceptos' => ['ventas_factura'],
    ],

    // La ND de venta arma su asiento con el catálogo de la factura
    // (AsientoBuilderService::generarAsientoNotaDebitoVenta usa 'ventas_factura').
    'notas_debito' => [
        'nombre'    => 'Notas de Débito',
        'grupo'     => 'Ventas',
        'rutas'     => ['modulos/nota_debito'],
        'conceptos' => ['ventas_factura'],
    ],

    // La cuenta puente de terceros solo existe en 'factura_reembolso'; CxC e ingresos
    // caen a 'ventas_factura' como respaldo, por eso ese va solo en la firma.
    'factura_reembolso' => [
        'nombre'          => 'Facturas de Reembolso',
        'grupo'           => 'Ventas',
        'rutas'           => ['modulos/factura-reembolso'],
        'conceptos'       => ['factura_reembolso'],
        'conceptos_firma' => ['ventas_factura'],
    ],

    'retenciones_venta' => [
        'nombre'      => 'Retenciones en Ventas',
        'grupo'       => 'Ventas',
        'rutas'       => ['modulos/retenciones_ventas'],
        // Se configura por CÓDIGO de retención, no por concepto.
        'referencias' => ['retenciones_venta_debe', 'retenciones_venta'],
    ],

    // ─── Compras ────────────────────────────────────────────────────────────
    'compras' => [
        'nombre'    => 'Facturas de Compra',
        'grupo'     => 'Compras',
        'rutas'     => ['modulos/compras'],
        'conceptos' => ['adquisiciones_compras'],
    ],

    'liquidaciones_compra' => [
        'nombre'    => 'Liquidaciones de Compra',
        'grupo'     => 'Compras',
        'rutas'     => ['modulos/liquidacion-compra'],
        'conceptos' => ['adquisiciones_compras'],
    ],

    'retenciones_compra' => [
        'nombre'      => 'Retenciones en Compras',
        'grupo'       => 'Compras',
        'rutas'       => ['modulos/retenciones_compras'],
        'referencias' => ['retenciones_compra_haber'],
    ],

    'importaciones' => [
        'nombre'    => 'Importaciones',
        'grupo'     => 'Compras',
        'rutas'     => ['modulos/importaciones'],
        'conceptos' => ['adquisiciones_importacion'],
    ],

    // ─── Tesorería ──────────────────────────────────────────────────────────
    // El asiento sale de dos lados: el concepto (opción) del documento y la
    // forma de cobro/pago. Ambos pueden tener la cuenta en asientos_programados
    // o en su propio módulo, de ahí las tres fuentes.
    'ingresos' => [
        'nombre'      => 'Ingresos',
        'grupo'       => 'Tesorería',
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
        'grupo'       => 'Tesorería',
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

    // El asiento del depósito usa la cuenta de la forma de cobro de la tarjeta (puente) y la
    // del banco destino —ambas con COALESCE(asientos_programados 'forma_cobro', la de la
    // forma)— más las cuentas de comisión/IVA/retenciones de la pestaña «Configuración» de la
    // conciliación. Las tres fuentes entran en la firma: corregir cualquiera reintenta las
    // conciliaciones cerradas que quedaron sin asiento.
    'conciliacion_tarjetas' => [
        'nombre'      => 'Conciliación de Tarjetas',
        'grupo'       => 'Tesorería',
        'rutas'       => ['modulos/conciliacion-tarjetas'],
        'referencias' => ['forma_cobro'],
        'tablas'      => [
            [
                'tabla'      => 'empresa_formas_pago',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => "activo = TRUE AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')",
            ],
            ['tabla' => 'conciliacion_tarjetas_config', 'col_cuenta' => 'id_cuenta_comision'],
            ['tabla' => 'conciliacion_tarjetas_config', 'col_cuenta' => 'id_cuenta_iva_comision'],
            ['tabla' => 'conciliacion_tarjetas_config', 'col_cuenta' => 'id_cuenta_retencion_ir'],
            ['tabla' => 'conciliacion_tarjetas_config', 'col_cuenta' => 'id_cuenta_retencion_iva'],
        ],
        'ayuda'       => 'Asiento del depósito de la procesadora (banco, comisión, IVA y retenciones contra la cuenta puente de la tarjeta).',
    ],

    // Debe = cuenta de la forma destino (regla 'forma_cobro'), Haber = la de la forma
    // origen (regla 'forma_pago'); ambas con respaldo en la cuenta de la propia forma.
    'traspasos' => [
        'nombre'      => 'Traspasos',
        'grupo'       => 'Tesorería',
        'rutas'       => ['modulos/traspasos'],
        'referencias' => ['forma_pago', 'forma_cobro'],
        'tablas'      => [
            [
                'tabla'      => 'empresa_formas_pago',
                'col_cuenta' => 'id_cuenta_contable',
                'filtro'     => 'activo = TRUE',
            ],
        ],
    ],

    // ─── Activos Fijos ──────────────────────────────────────────────────────
    // Solo el asiento de ALTA de los activos manuales. La contrapartida puede estar en el
    // propio activo o en la regla general 'activos_fijos_alta'.
    'activos_fijos_alta' => [
        'nombre'    => 'Activos Fijos (alta)',
        'grupo'     => 'Activos Fijos',
        'rutas'     => ['modulos/activos-fijos'],
        'conceptos' => ['activos_fijos_alta'],
        'tablas'    => [
            [
                'tabla'      => 'activos_fijos',
                'col_cuenta' => 'id_cuenta_contrapartida_alta',
                'filtro'     => "origen = 'manual'",
            ],
        ],
        'ayuda'     => 'Asiento de alta de los activos registrados a mano (Activo contra la contrapartida). '
                     . 'Los que vienen de una compra ya están contabilizados por la compra.',
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
        'grupo'           => 'Consignaciones',
        'rutas'           => ['modulos/consignaciones-ventas'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
        'ayuda'           => 'Reclasifica la mercadería entregada a «Mercadería en Consignación». '
                           . 'Apagado, la mercadería sigue en Inventario y solo la factura mueve cuentas; '
                           . 'retornos y facturaciones siguen a su consignación de origen.',
    ],

    // Retornos y Facturación CV no tienen interruptor propio: su asiento es el
    // INVERSO del de la consignación madre, así que solo se genera si la madre
    // tiene asiento (ver ConsignacionVentaRepository::sqlContabilizada).
    'retornos_cv' => [
        'nombre'          => 'Retornos de Consignaciones',
        'grupo'           => 'Consignaciones',
        'rutas'           => ['modulos/retornos-cv'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
        'sigue_a'         => 'consignaciones',
    ],

    // Excepción entre los cuatro: el cambio de productos NO toca la cuenta de
    // consignación. Es un neto entre Inventario y Costo de Ventas, y ambas
    // cuentas las toma de 'ventas_factura' (generarAsientoCambioProductoCv).
    // Exigir aquí 'consignacion_venta' dejaría sin generar a las empresas que
    // llevan ventas configuradas pero no consignaciones.
    'cambios_producto_cv' => [
        'nombre'    => 'Cambios de Productos',
        'grupo'     => 'Consignaciones',
        'rutas'     => ['modulos/cambio-producto-cv'],
        'conceptos' => ['ventas_factura'],
        'ayuda'     => 'Asiento a costo del intercambio (Inventario ↔ Costo de Ventas). Lo entregado desde una '
                     . 'consignación sale de «Mercadería en Consignación» solo si esa consignación tiene asiento.',
    ],

    'facturacion_cv' => [
        'nombre'          => 'Facturación de Consignaciones',
        'grupo'           => 'Consignaciones',
        'rutas'           => ['modulos/facturacion-cv'],
        'conceptos'       => ['consignacion_venta'],
        'conceptos_firma' => ['ventas_factura'],
        'sigue_a'         => 'consignaciones',
    ],

    // ─── Nómina ─────────────────────────────────────────────────────────────
    'roles_pago' => [
        'nombre'    => 'Roles de Pago',
        'grupo'     => 'Nómina',
        'rutas'     => ['modulos/roles-pago'],
        'conceptos' => ['nomina'],
    ],
];

<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de reportes que un administrador puede PERSONALIZAR POR EMPRESA (botón
 * «Personalizar» del encabezado de filtros): qué columnas se muestran en pantalla y
 * Excel, cuáles salen en el PDF y la orientación de la hoja.
 *
 * Es la única fuente de verdad de las columnas de cada vista de un reporte: de aquí
 * salen la cabecera de la pantalla, las columnas del PDF y las del Excel, lo que ofrece
 * la ventana de personalización y lo que aceptan las Rules. El módulo solo aporta cómo se
 * pinta cada celda (por clave de columna).
 *
 * Cada vista declara sus columnas en el orden en que se muestran. Atributos de columna:
 *   titulo   Texto de la cabecera en pantalla y en la ventana de personalización.
 *   corto    Cabecera en el PDF (por defecto, `titulo`).
 *   excel    Cabecera(s) en el Excel: un texto o una lista si la columna ocupa varias
 *            celdas (p. ej. Proveedor + RUC). Por defecto, `titulo`.
 *   alinear  'izq' (por defecto) | 'centro' | 'der'.
 *   ancho    Peso relativo en el PDF; el ancho real (%) se reparte entre las visibles.
 *   nombre   Nombre en la ventana de personalización (por defecto, `titulo`).
 *   fija     true = no se puede ocultar (identifica la fila).
 *   pantalla false = por defecto NO se muestra en pantalla (el administrador puede activarla).
 *   excel_def false = por defecto NO sale en el Excel. Solo cuenta si la vista declara
 *            `excel_propio`; si no, el Excel lleva las mismas columnas que la pantalla.
 *   pdf      false = por defecto NO sale en el PDF (el administrador puede activarla).
 *   no       Medios en los que la columna NO existe (['pantalla', 'excel', 'pdf']): ahí no
 *            se puede activar y la ventana muestra una raya. Para reportes cuyos medios no
 *            comparten todas las columnas (p. ej. la clave de acceso solo va en el Excel).
 *   px, sort, tip  Datos de la tabla en pantalla para los módulos que la arman en el
 *            navegador: ancho en px (null = flexible), clave de ordenamiento y tooltip.
 *
 * Atributos de vista: `titulo`, `auto` (orientación automática del PDF: 'P' o 'L'),
 * `auto_txt` (texto de la opción Automática cuando depende de los datos),
 * `excel_propio` (true = el Excel se elige aparte de la pantalla) y `orden` (claves que van
 * primero en un medio cuando su orden no es el del catálogo: ['pantalla' => […], 'pdf' => […]]).
 *   suma     Campo de la fila que se suma en la fila de totales (pantalla y Excel).
 *   stat     Clave de las estadísticas del reporte para el total del PDF.
 *
 * Para habilitar otro reporte: agregar su entrada al CATALOGO con la ruta MVC como
 * clave; en el controlador, resolver las columnas con
 * ReportePersonalizacionService::crear()->resolver() y pintar pantalla, PDF y Excel
 * recorriendo ese resultado; en la vista, `ColumnasReporte::boton($rutaModulo)` en el
 * card-header de la tarjeta de control y `ColumnasReporte::modal($rutaModulo)` al final.
 * Los endpoints (get/guardar/restablecer) ya viven en BaseModuloController.
 * Referencia: ReporteComprasController. Manual: docs/manual/guias/personalizar-reportes.md.
 *
 * La configuración se guarda en `reportes_personalizacion`
 * (database/2026-11-10_reportes_personalizacion.sql): sin fila, valores por defecto.
 */
final class ColumnasReporte
{
    /** Nivel mínimo para personalizar: administrador (2) y superadministrador (3). */
    public const NIVEL_MINIMO = 2;

    public const ORIENTACIONES = ['auto', 'P', 'L'];

    /** Importes comunes a todas las vistas del Reporte de Compras. */
    private const COMPRAS_MONTOS = [
        'base_0'    => ['titulo' => 'Base 0% / Exento', 'corto' => 'Base 0%', 'excel' => 'Base 0%', 'alinear' => 'der', 'ancho' => 8, 'suma' => 'base_0',    'stat' => 'total_base_0'],
        'base_iva'  => ['titulo' => 'Base IVA',                                                      'alinear' => 'der', 'ancho' => 8, 'suma' => 'base_iva',  'stat' => 'total_base_iva'],
        'valor_iva' => ['titulo' => 'Total IVA',        'corto' => 'IVA',     'excel' => 'IVA',     'alinear' => 'der', 'ancho' => 8, 'suma' => 'valor_iva', 'stat' => 'total_iva'],
        'valor_ice' => ['titulo' => 'Total ICE',        'corto' => 'ICE',     'excel' => 'ICE',     'alinear' => 'der', 'ancho' => 7, 'suma' => 'valor_ice', 'stat' => 'total_ice'],
        'total'     => ['titulo' => 'Gran Total',       'corto' => 'Total',   'excel' => 'Total',   'alinear' => 'der', 'ancho' => 9, 'suma' => 'total',     'stat' => 'gran_total'],
    ];

    /** Importes comunes a las vistas con montos del Reporte de Ventas. */
    private const VENTAS_MONTOS = [
        'base_0'    => ['titulo' => 'Base 0% / Exento'],
        'base_iva'  => ['titulo' => 'Base IVA'],
        'valor_iva' => ['titulo' => 'Total IVA'],
        'total'     => ['titulo' => 'Gran Total'],
    ];

    private const COMPRAS_COMPROBANTES = [
        'comprobantes' => ['titulo' => 'Nro Comp.', 'excel' => 'Nro Comprobantes', 'alinear' => 'centro', 'ancho' => 9, 'suma' => 'cantidad_comprobantes'],
    ];

    /** @var array<string, array{titulo:string, icono:string, vistas: array<string, array{titulo:string, auto:string, columnas: array<string, array<string,mixed>>}>}> */
    public const CATALOGO = [
        'modulos/reporte_compras' => [
            'titulo' => 'Reporte de Compras',
            'icono'  => 'bi-file-earmark-bar-graph',
            'vistas' => [
                'NINGUNO' => [
                    'titulo'   => 'Detallado',
                    'auto'     => 'L',
                    'columnas' => [
                        'fecha_emision'    => ['titulo' => 'F. Emisión', 'alinear' => 'centro', 'ancho' => 8],
                        'fecha_registro'   => ['titulo' => 'F. Registro', 'alinear' => 'centro', 'ancho' => 8, 'pdf' => false],
                        'numero_documento' => ['titulo' => 'Nro Documento', 'ancho' => 13, 'fija' => true],
                        'proveedor'        => ['titulo' => 'Proveedor', 'excel' => ['Proveedor', 'RUC/Cédula'], 'ancho' => 20],
                        'tipo'             => ['titulo' => 'Tipo', 'alinear' => 'centro', 'ancho' => 10],
                        'usuario'          => ['titulo' => 'Usuario', 'ancho' => 9],
                        'autorizacion'     => ['titulo' => 'Nro Autorización', 'ancho' => 14, 'pdf' => false],
                    ] + self::COMPRAS_MONTOS + [
                        'retenciones'      => ['titulo' => 'Retenciones', 'alinear' => 'der', 'ancho' => 8, 'suma' => 'retenciones'],
                    ],
                ],
                'PROVEEDOR' => [
                    'titulo'   => 'Por proveedor',
                    'auto'     => 'L',
                    'columnas' => [
                        'proveedor' => ['titulo' => 'Proveedor', 'excel' => ['RUC/Cédula', 'Proveedor'], 'ancho' => 34, 'fija' => true],
                    ] + self::COMPRAS_COMPROBANTES + self::COMPRAS_MONTOS,
                ],
                'PRODUCTO' => [
                    'titulo'   => 'Por producto',
                    'auto'     => 'L',
                    'columnas' => [
                        'producto' => ['titulo' => 'Producto', 'excel' => ['Código', 'Producto'], 'ancho' => 34, 'fija' => true],
                        'cantidad' => ['titulo' => 'Cantidad Comprada', 'corto' => 'Cant.', 'excel' => 'Cant. Comprada', 'alinear' => 'centro', 'ancho' => 8, 'suma' => 'cantidad_comprada'],
                        'tarifa'   => ['titulo' => 'Tipo IVA', 'corto' => 'T. IVA', 'alinear' => 'centro', 'ancho' => 7],
                    ] + self::COMPRAS_MONTOS,
                ],
                'FECHA' => [
                    'titulo'   => 'Por fecha',
                    'auto'     => 'L',
                    'columnas' => [
                        'fecha' => ['titulo' => 'Fecha', 'alinear' => 'centro', 'ancho' => 16, 'fija' => true],
                    ] + self::COMPRAS_COMPROBANTES + self::COMPRAS_MONTOS,
                ],
                'MES' => [
                    'titulo'   => 'Por mes',
                    'auto'     => 'L',
                    'columnas' => [
                        'mes' => ['titulo' => 'Mes', 'alinear' => 'centro', 'ancho' => 16, 'fija' => true],
                    ] + self::COMPRAS_COMPROBANTES + self::COMPRAS_MONTOS,
                ],
            ],
        ],

        // Cuentas por Cobrar y por Pagar: solo la vista Detallado (las vistas por cliente /
        // proveedor, por producto y por antigüedad tienen estructura propia). Pantalla,
        // Excel y PDF se eligen por separado: cualquier columna puede ir en cualquier medio;
        // los `false` reproducen lo que cada medio mostraba antes de existir la personalización.
        // El orden es el del Excel. `suma` marca los importes (fila de totales del PDF y
        // formato numérico del Excel). Las celdas las pinta cada módulo por clave.
        'modulos/cuentas_por_cobrar' => [
            'titulo' => 'Cuentas por Cobrar',
            'icono'  => 'bi-wallet2',
            'vistas' => [
                'DETALLE' => [
                    'titulo'       => 'Detallado',
                    'auto'         => 'P',
                    'excel_propio' => true,
                    'columnas'     => [
                        'documento'         => ['titulo' => 'Documento', 'ancho' => 17, 'px' => 170, 'sort' => 'numero_factura', 'tip' => 'Ordenar por documento', 'fija' => true],
                        'origen'            => ['titulo' => 'Origen', 'alinear' => 'centro', 'ancho' => 9, 'px' => 120, 'sort' => 'origen', 'tip' => 'Ordenar por origen'],
                        'cliente'           => ['titulo' => 'Cliente', 'ancho' => 22, 'sort' => 'cliente_nombre', 'tip' => 'Ordenar por cliente'],
                        'ruc'               => ['titulo' => 'RUC/Cédula', 'ancho' => 11, 'px' => 115, 'pantalla' => false, 'pdf' => false],
                        'vendedor'          => ['titulo' => 'Vendedor', 'ancho' => 12, 'px' => 150, 'pantalla' => false, 'pdf' => false],
                        'fecha_emision'     => ['titulo' => 'F.Emisión', 'corto' => 'F. Emisión', 'ancho' => 11, 'px' => 110, 'sort' => 'fecha_emision', 'tip' => 'Ordenar por fecha de emisión'],
                        'fecha_vencimiento' => ['titulo' => 'F.Vencimiento', 'corto' => 'F. Vencimiento', 'ancho' => 14, 'px' => 126, 'sort' => 'fecha_vencimiento', 'tip' => 'Ordenar por fecha de vencimiento'],
                        'dias'              => ['titulo' => 'Días', 'nombre' => 'Días transcurridos', 'alinear' => 'centro', 'ancho' => 6, 'px' => 70, 'pantalla' => false, 'pdf' => false, 'tip' => 'Días transcurridos desde la emisión'],
                        'total'             => ['titulo' => 'Total', 'alinear' => 'der', 'ancho' => 9, 'px' => 95, 'sort' => 'total', 'tip' => 'Ordenar por total', 'suma' => 'total'],
                        'abonos'            => ['titulo' => 'Abonos', 'alinear' => 'der', 'ancho' => 9, 'px' => 95, 'pantalla' => false, 'pdf' => false, 'tip' => 'Cobros recibidos (efectivo, banco, tarjeta…)', 'suma' => 'abonos'],
                        'nc'                => ['titulo' => 'NC', 'nombre' => 'Notas de crédito', 'excel' => 'Notas de Crédito', 'alinear' => 'der', 'ancho' => 9, 'px' => 90, 'pantalla' => false, 'pdf' => false, 'tip' => 'Notas de crédito aplicadas', 'suma' => 'nc'],
                        'retenciones'       => ['titulo' => 'Retenciones', 'alinear' => 'der', 'ancho' => 9, 'px' => 100, 'pantalla' => false, 'pdf' => false, 'suma' => 'retenciones'],
                        'cobrado'           => ['titulo' => 'Cobrado', 'nombre' => 'Cobrado (abonos + NC + retenciones)', 'alinear' => 'der', 'ancho' => 9, 'px' => 100, 'sort' => 'cobrado', 'tip' => 'Ordenar por cobrado', 'suma' => 'cobrado'],
                        'saldo'             => ['titulo' => 'Saldo', 'alinear' => 'der', 'ancho' => 9, 'px' => 95, 'sort' => 'saldo', 'tip' => 'Ordenar por saldo', 'suma' => 'saldo'],
                        'estado'            => ['titulo' => 'Estado', 'alinear' => 'centro', 'ancho' => 11, 'px' => 125, 'sort' => 'dias_vencido', 'tip' => 'Ordenar por días vencidos', 'pdf' => false],
                    ],
                ],
            ],
        ],
        // Reporte de Ventas: cada medio conserva sus columnas propias y la empresa decide
        // cuáles ocultar (`no` = la columna no existe en ese medio). Las claves son las de
        // ordenamiento de la pantalla (RV_COLUMNAS / ReporteVentasRepository::ORDEN_COLUMNAS).
        // Las columnas que dependen de los datos no se personalizan: los meses de "Unidades
        // por producto y mes", las categorías, "Motivo" (notas de crédito) y "Estab." (consolidado).
        'modulos/reporte_ventas' => [
            'titulo' => 'Reporte de Ventas',
            'icono'  => 'bi-file-earmark-bar-graph',
            'vistas' => [
                'NINGUNO' => [
                    'titulo' => 'Detallado', 'auto' => 'L', 'excel_propio' => true,
                    'columnas' => [
                        'fecha_emision'   => ['titulo' => 'Fecha'],
                        'numero_factura'  => ['titulo' => 'Nro Documento', 'fija' => true],
                        'cliente_nombre'  => ['titulo' => 'Cliente'],
                        'cliente_ruc'     => ['titulo' => 'RUC/Cédula', 'nombre' => 'RUC/Cédula (columna aparte)', 'no' => ['pantalla', 'pdf']],
                        'estado'          => ['titulo' => 'Estado'],
                        'vendedor_nombre' => ['titulo' => 'Vendedor'],
                        'usuario_nombre'  => ['titulo' => 'Usuario'],
                        'clave_acceso'    => ['titulo' => 'Clave de acceso', 'no' => ['pantalla', 'pdf']],
                    ] + self::VENTAS_MONTOS + [
                        'retenciones'     => ['titulo' => 'Retenciones'],
                    ],
                ],
                'CLIENTE' => [
                    'titulo' => 'Por cliente', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'cliente_ruc'       => ['titulo' => 'RUC/Cédula', 'nombre' => 'RUC/Cédula (columna aparte)', 'no' => ['pantalla', 'pdf']],
                        'cliente_nombre'    => ['titulo' => 'Cliente', 'fija' => true],
                        'cantidad_facturas' => ['titulo' => 'Nro Documentos', 'no' => ['pantalla']],
                        'saldo'             => ['titulo' => 'Saldo x Cobrar'],
                    ] + self::VENTAS_MONTOS,
                ],
                'PRODUCTO' => [
                    'titulo' => 'Por producto', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'producto_codigo'  => ['titulo' => 'Código'],
                        'producto_nombre'  => ['titulo' => 'Producto', 'fija' => true],
                        'unidad_medida'    => ['titulo' => 'Unidad de Medida'],
                        'total_venta'      => ['titulo' => 'Total Venta'],
                        'cantidad_vendida' => ['titulo' => 'Cantidad'],
                        'pct_venta'        => ['titulo' => '% Venta'],
                        'pct_unidades'     => ['titulo' => '% Unidades'],
                    ],
                ],
                'VARIANTE' => [
                    'titulo' => 'Por variante', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'producto_nombre'  => ['titulo' => 'Producto'],
                        'variante_nombre'  => ['titulo' => 'Variante', 'fija' => true],
                        'variante_valor'   => ['titulo' => 'Valor', 'nombre' => 'Valor de la variante (columna aparte)', 'no' => ['pantalla', 'pdf']],
                        'cantidad_vendida' => ['titulo' => 'Cantidad Vendida'],
                        'tarifa_iva'       => ['titulo' => 'Tipo IVA'],
                    ] + self::VENTAS_MONTOS,
                ],
                'FECHA' => [
                    'titulo' => 'Por fecha', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'fecha'             => ['titulo' => 'Fecha', 'fija' => true],
                        'cantidad_facturas' => ['titulo' => 'Nro Facturas'],
                    ] + self::VENTAS_MONTOS,
                ],
                'CAJERO' => [
                    'titulo' => 'Por cajero', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'cajero_nombre'     => ['titulo' => 'Cajero', 'fija' => true],
                        'cantidad_facturas' => ['titulo' => 'Nro Documentos'],
                    ] + self::VENTAS_MONTOS,
                ],
                'MES' => [
                    'titulo' => 'Por mes', 'auto' => 'P', 'excel_propio' => true,
                    'columnas' => [
                        'mes'               => ['titulo' => 'Mes', 'fija' => true],
                        'cantidad_facturas' => ['titulo' => 'Nro Facturas'],
                    ] + self::VENTAS_MONTOS,
                ],
                'PRODUCTO_MES' => [
                    'titulo' => 'Unidades por producto y mes', 'auto' => 'P', 'auto_txt' => 'según los meses', 'excel_propio' => true,
                    'columnas' => [
                        'producto_codigo' => ['titulo' => 'Código'],
                        'producto_nombre' => ['titulo' => 'Descripción', 'fija' => true],
                        'total_unidades'  => ['titulo' => 'Total'],
                    ],
                ],
                'CLIENTE_CATEGORIA' => [
                    'titulo' => 'Unidades por cliente y categoría', 'auto' => 'P', 'auto_txt' => 'según las categorías', 'excel_propio' => true,
                    'columnas' => [
                        'cliente_ruc'    => ['titulo' => 'RUC/Cédula', 'nombre' => 'RUC/Cédula (columna aparte)', 'no' => ['pantalla', 'pdf']],
                        'cliente_nombre' => ['titulo' => 'Cliente', 'fija' => true],
                        'total_unidades' => ['titulo' => 'Total Unidades'],
                        'total_venta'    => ['titulo' => 'Total Venta sin IVA'],
                    ],
                ],
            ],
        ],
        'modulos/cuentas_por_pagar' => [
            'titulo' => 'Cuentas por Pagar',
            'icono'  => 'bi-credit-card',
            'vistas' => [
                'DETALLE' => [
                    'titulo'       => 'Detallado',
                    'auto'         => 'P',
                    'excel_propio' => true,
                    // En pantalla y en el PDF el documento va primero; en el Excel, el tipo.
                    'orden'        => ['pantalla' => ['documento'], 'pdf' => ['documento']],
                    'columnas'     => [
                        'origen'            => ['titulo' => 'Origen', 'nombre' => 'Origen / Tipo', 'excel' => 'Tipo', 'alinear' => 'centro', 'ancho' => 9, 'px' => 120, 'sort' => 'tipo_fuente', 'tip' => 'Ordenar por origen', 'pdf' => false],
                        'documento'         => ['titulo' => 'Documento', 'ancho' => 17, 'px' => 175, 'sort' => 'numero_documento', 'tip' => 'Ordenar por documento', 'fija' => true],
                        'proveedor'         => ['titulo' => 'Proveedor', 'ancho' => 25, 'sort' => 'proveedor_nombre', 'tip' => 'Ordenar por proveedor'],
                        'ruc'               => ['titulo' => 'RUC', 'ancho' => 11, 'px' => 115, 'pantalla' => false, 'pdf' => false],
                        'fecha_emision'     => ['titulo' => 'F.Emisión', 'corto' => 'F. Emisión', 'alinear' => 'centro', 'ancho' => 12, 'px' => 110, 'sort' => 'fecha_emision', 'tip' => 'Ordenar por fecha de emisión'],
                        'fecha_vencimiento' => ['titulo' => 'F.Vencimiento', 'corto' => 'F. Vencimiento', 'alinear' => 'centro', 'ancho' => 16, 'px' => 134, 'sort' => 'fecha_vencimiento', 'tip' => 'Ordenar por fecha de vencimiento'],
                        'dias'              => ['titulo' => 'Días', 'nombre' => 'Días vencidos', 'excel' => 'Días Vencidos', 'alinear' => 'centro', 'ancho' => 6, 'px' => 70, 'pantalla' => false, 'pdf' => false, 'tip' => 'Días vencidos'],
                        'total'             => ['titulo' => 'Total', 'alinear' => 'der', 'ancho' => 10, 'px' => 98, 'sort' => 'total', 'tip' => 'Ordenar por total', 'suma' => 'total'],
                        'abonos'            => ['titulo' => 'Pagado', 'nombre' => 'Pagado (abonos)', 'corto' => 'Abonos', 'excel' => 'Abonos', 'alinear' => 'der', 'ancho' => 10, 'px' => 96, 'sort' => 'total_pagado', 'tip' => 'Ordenar por pagado', 'pdf' => false, 'suma' => 'abonos'],
                        'nc'                => ['titulo' => 'NC', 'nombre' => 'Notas de crédito', 'excel' => 'Notas de Crédito', 'alinear' => 'der', 'ancho' => 9, 'px' => 90, 'pantalla' => false, 'pdf' => false, 'tip' => 'Notas de crédito del proveedor', 'suma' => 'nc'],
                        'nd'                => ['titulo' => 'ND', 'nombre' => 'Notas de débito', 'excel' => 'Notas de Débito', 'alinear' => 'der', 'ancho' => 9, 'px' => 90, 'pantalla' => false, 'pdf' => false, 'tip' => 'Notas de débito del proveedor (suman al documento)', 'suma' => 'nd'],
                        'retenciones'       => ['titulo' => 'Retenciones', 'alinear' => 'der', 'ancho' => 9, 'px' => 100, 'pantalla' => false, 'pdf' => false, 'tip' => 'Retenciones practicadas al proveedor', 'suma' => 'retenciones'],
                        'nc_ret'            => ['titulo' => 'NC/Ret.', 'nombre' => 'NC + retenciones − ND', 'alinear' => 'der', 'ancho' => 9, 'px' => 96, 'sort' => 'nc_ret', 'tip' => 'Ordenar por Notas de Crédito / Retenciones', 'excel_def' => false, 'pdf' => false, 'suma' => 'nc_ret'],
                        'descontado'        => ['titulo' => 'Pag./Ret/NC', 'nombre' => 'Pagado + retenciones + NC', 'corto' => 'Pagado/Ret/NC', 'excel' => 'Pagado', 'alinear' => 'der', 'ancho' => 10, 'px' => 110, 'pantalla' => false, 'tip' => 'Abonos + notas de crédito + retenciones − notas de débito', 'suma' => 'descontado'],
                        'saldo'             => ['titulo' => 'Saldo', 'alinear' => 'der', 'ancho' => 10, 'px' => 102, 'sort' => 'saldo', 'tip' => 'Ordenar por saldo', 'suma' => 'saldo'],
                        'estado'            => ['titulo' => 'Estado', 'alinear' => 'centro', 'ancho' => 12, 'px' => 128, 'sort' => 'dias_vencido', 'tip' => 'Ordenar por días vencidos', 'pdf' => false],
                    ],
                ],
            ],
        ],
    ];

    /** Ruta tal como está en el catálogo: sin barras al borde y con guion bajo o guion, la que exista. */
    public static function normalizar(string $modulo): string
    {
        $ruta = trim($modulo, '/');
        if (isset(self::CATALOGO[$ruta])) {
            return $ruta;
        }
        foreach ([str_replace('-', '_', $ruta), str_replace('_', '-', $ruta)] as $alt) {
            if (isset(self::CATALOGO[$alt])) {
                return $alt;
            }
        }
        return $ruta;
    }

    /** Definición de un reporte (título, ícono y vistas), o null si no es personalizable. */
    public static function definicion(string $modulo): ?array
    {
        return self::CATALOGO[self::normalizar($modulo)] ?? null;
    }

    /** Vistas del reporte: clave => [titulo, auto, columnas]. */
    public static function vistas(string $modulo): array
    {
        return self::definicion($modulo)['vistas'] ?? [];
    }

    /**
     * Columnas de una vista, en su orden, con todos los atributos ya completos
     * (corto, excel, alinear, ancho, fija, pdf, suma, stat).
     *
     * @return array<string, array<string,mixed>>
     */
    public static function columnas(string $modulo, string $vista): array
    {
        $out = [];
        foreach ((self::vistas($modulo)[$vista]['columnas'] ?? []) as $clave => $def) {
            $out[$clave] = [
                'titulo'  => (string) $def['titulo'],
                'corto'   => (string) ($def['corto'] ?? $def['titulo']),
                'excel'   => (array) ($def['excel'] ?? $def['titulo']),
                'alinear' => (string) ($def['alinear'] ?? 'izq'),
                'ancho'   => (float) ($def['ancho'] ?? 10),
                'nombre'  => (string) ($def['nombre'] ?? $def['titulo']),
                'fija'    => !empty($def['fija']),
                'pantalla' => ($def['pantalla'] ?? true) !== false,
                'excel_def' => ($def['excel_def'] ?? true) !== false,
                'pdf'     => ($def['pdf'] ?? true) !== false,
                'px'      => $def['px'] ?? null,
                'sort'    => $def['sort'] ?? null,
                'tip'     => $def['tip'] ?? null,
                'no'      => (array) ($def['no'] ?? []),
                'suma'    => $def['suma'] ?? null,
                'stat'    => $def['stat'] ?? null,
            ];
        }
        return $out;
    }

    /** Listas de columnas ocultas de la configuración => atributo que da su valor por defecto. */
    public const MEDIOS = ['ocultas' => 'pantalla', 'ocultas_excel' => 'excel_def', 'ocultas_pdf' => 'pdf'];

    /** ¿El Excel de la vista se elige aparte de la pantalla? */
    public static function excelPropio(string $modulo, string $vista): bool
    {
        return !empty(self::vistas($modulo)[$vista]['excel_propio']);
    }

    /**
     * Configuración por defecto de una vista: columnas ocultas en cada medio y orientación
     * automática. Es lo que rige mientras la empresa no personalice la vista.
     */
    public static function configPorDefecto(string $modulo, string $vista): array
    {
        $columnas = self::columnas($modulo, $vista);
        // Oculta por defecto en un medio: la que el catálogo apaga o la que ahí no existe.
        $ocultas  = static fn (string $attr, string $medio): array => array_keys(array_filter(
            $columnas,
            static fn (array $c): bool => !$c[$attr] || in_array($medio, $c['no'], true)
        ));

        return [
            'ocultas'       => $ocultas('pantalla', 'pantalla'),
            'ocultas_excel' => self::excelPropio($modulo, $vista) ? $ocultas('excel_def', 'excel') : [],
            'ocultas_pdf'   => $ocultas('pdf', 'pdf'),
            'orientacion'   => 'auto',
        ];
    }

    /** Medio ('pantalla' | 'excel' | 'pdf') de cada lista de ocultas de la configuración. */
    public const MEDIO_DE = ['ocultas' => 'pantalla', 'ocultas_excel' => 'excel', 'ocultas_pdf' => 'pdf'];

    /** Claves de las columnas que no existen en un medio (siempre ocultas ahí). */
    public static function noDisponibles(string $modulo, string $vista, string $medio): array
    {
        return array_keys(array_filter(self::columnas($modulo, $vista), static fn (array $c): bool => in_array($medio, $c['no'], true)));
    }

    /**
     * Reordena las columnas de un medio: primero las claves de `orden[$medio]` de la vista
     * y luego el resto en el orden del catálogo.
     */
    public static function ordenar(string $modulo, string $vista, string $medio, array $columnas): array
    {
        $primero = (array) (self::vistas($modulo)[$vista]['orden'][$medio] ?? []);
        if (!$primero) {
            return $columnas;
        }
        $out = [];
        foreach ($primero as $clave) {
            if (isset($columnas[$clave])) {
                $out[$clave] = $columnas[$clave];
            }
        }
        return $out + $columnas;
    }

    /**
     * Tamaño de letra (pt) de un listado en PDF según cuántas columnas lleva y la
     * orientación: $base hasta el número de columnas que cabe cómodo, y menos al crecer.
     */
    public static function fuentePdf(int $nCols, bool $horizontal, float $base = 8.0): float
    {
        $comodo = $horizontal ? 12 : 9;
        if ($nCols <= $comodo) {
            return $base;
        }
        return $nCols <= $comodo + 3 ? $base - 1.0 : $base - 1.5;
    }

    /** ¿El usuario en sesión puede personalizar reportes? (niveles 2 y 3) */
    public static function puedePersonalizar(): bool
    {
        return (int) ($_SESSION['nivel'] ?? 1) >= self::NIVEL_MINIMO;
    }

    /** Clase CSS de alineación de una columna (cabecera y celdas). */
    public static function claseAlinear(string $alinear): string
    {
        return match ($alinear) {
            'der'    => 'text-end',
            'centro' => 'text-center',
            default  => '',
        };
    }

    /**
     * Anchos del PDF en % para las columnas dadas: reparte 100 según el peso de cada una.
     * El último absorbe el redondeo para que la suma sea exactamente 100.
     *
     * @param  array<string,float> $pesos clave => peso
     * @return array<string,float> clave => %
     */
    public static function anchosPdf(array $pesos): array
    {
        $total = array_sum($pesos);
        if ($total <= 0) {
            return [];
        }
        $out  = [];
        $acum = 0.0;
        $ultima = array_key_last($pesos);
        foreach ($pesos as $clave => $peso) {
            $w = $clave === $ultima ? round(100 - $acum, 2) : round($peso / $total * 100, 2);
            $out[$clave] = $w;
            $acum += $w;
        }
        return $out;
    }

    /**
     * Botón «Personalizar» para el card-header de la tarjeta de control. Vacío si el
     * usuario no es administrador o el módulo no está en el catálogo.
     */
    public static function boton(string $modulo): string
    {
        if (!self::puedePersonalizar() || self::definicion($modulo) === null) {
            return '';
        }
        return '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.CMG_personalizarReporte()"'
             . ' title="Personalizar el reporte para toda la empresa: columnas y orientación del PDF">'
             . '<i class="bi bi-sliders me-1"></i>Personalizar</button>';
    }

    /**
     * Ventana de personalización y su JS. Incluir una vez, al final de la vista (fuera de
     * cualquier otro modal). Vacío si el usuario no es administrador.
     */
    public static function modal(string $modulo): string
    {
        $def = self::definicion($modulo);
        if (!self::puedePersonalizar() || $def === null) {
            return '';
        }
        $rutaModulo = self::normalizar($modulo);
        ob_start();
        require MVC_APP . '/views/partials/modal_personalizar_reporte.php';
        return (string) ob_get_clean();
    }
}

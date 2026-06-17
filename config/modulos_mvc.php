<?php
/**
 * Rutas MVC de módulos operativos bajo modulos/{nombre}.
 *
 * ─── CÓMO REGISTRAR UN NUEVO MÓDULO ────────────────────────────────────────
 *
 * Para que el sistema de permisos (modulos_asignados) funcione correctamente
 * con un nuevo controlador bajo app/controllers/modulos/, siga estos pasos:
 *
 * 1. Crear el controlador extendiendo BaseModuloController e implementando getRutaModulo().
 *    Ejemplo: class ProductosController extends BaseModuloController { ... }
 *
 * 2. Registrar la ruta aquí:
 *    'modulos/productos' => [
 *        'legacy_rutas' => ['modulos/productos.php', 'sistema/modulos/productos.php'],
 *    ],
 *
 * 3. Actualizar submodulos_menu.ruta en la BD para el submodulo correspondiente:
 *    UPDATE submodulos_menu SET ruta = 'modulos/productos' WHERE id = <id_submodulo>;
 *
 *    Esto permite que PermisoSubmodulo::getIdSubmoduloPorRutaMvc() resuelva
 *    el id_submodulo y consulte modulos_asignados correctamente.
 *
 * 4. Si el submodulo no tiene id_submodulo aún en la BD, puede forzarlo:
 *    'modulos/productos' => [
 *        'id_submodulo' => 152,   // id del registro en submodulos_menu
 *    ],
 *
 * ─── NIVELES DE USUARIO ──────────────────────────────────────────────────────
 *   Nivel 3 = Super admin → acceso total, sin verificar modulos_asignados.
 *   Nivel 1-2 = Admin/Usuario → acceso según modulos_asignados (r,w,u,d).
 *
 * Administrar permisos: /config/permisos-modulos
 *
 * ─── COLUMNAS modulos_asignados ──────────────────────────────────────────────
 *   r = ver/leer     (usado en index y AJAX de lectura)
 *   w = crear        (usado en store)
 *   u = actualizar   (usado en update)
 *   d = eliminar     (usado en delete)
 */

declare(strict_types=1);

return [
    // ─── VENTAS (id_modulo = 308) ────────────────────────────────────────────
    'modulos/clientes' => [
        'id_submodulo' => 150, // submodulos_menu.id donde ruta='modulos/clientes'
        'legacy_rutas' => [
            'modulos/clientes.php',
            'modulos/cliente.php',
            'sistema/modulos/clientes.php',
            'sistema/modulos/cliente.php',
        ],
    ],

    'modulos/factura-venta' => [
        'id_submodulo' => 149,
        'legacy_rutas' => [
            'modulos/facturacion.php',
            'modulos/facturacion_lista.php',
            'sistema/modulos/facturacion.php'
        ],
    ],

    'modulos/notas_credito' => [
        'id_submodulo' => 165,
        'legacy_rutas' => [
            'modulos/nota_credito.php',
            'sistema/modulos/nota_credito.php'
        ],
    ],

    'modulos/guias_remision' => [
        'id_submodulo' => 166,
        'legacy_rutas' => [],
    ],

    'modulos/transportistas' => [
        'id_submodulo' => 177,
        'legacy_rutas' => [],
    ],

    'modulos/ingresos' => [
        'legacy_rutas' => [],
    ],

    'modulos/egresos' => [
        'legacy_rutas' => [],
    ],

    'modulos/opciones_ingreso_egreso' => [
        'id_submodulo' => 44,
        'legacy_rutas' => ['modulos/opciones-ingreso-egreso'],
    ],

    // Pendientes de implementación (agregar cuando se cree el controlador):
    'modulos/vendedores' => ['id_submodulo' => 151, 'legacy_rutas' => []],
    'modulos/productos'  => ['id_submodulo' => 152, 'legacy_rutas' => []],
    'modulos/categorias' => ['id_submodulo' => 153, 'legacy_rutas' => []],
    'modulos/marcas'          => ['id_submodulo' => 154, 'legacy_rutas' => []],

    // ─── CONFIGURACIÓN DE EMPRESA ─────────────────────────────────────────
    // Registrar el id_submodulo que retorne la migración:
    // database/migration_submodulo_unidades_medida.sql
    'modulos/unidades-medida' => ['id_submodulo' => 0, 'legacy_rutas' => []],

    // ─── INVENTARIOS (id_modulo = 1) ─────────────────────────────────────────
    'modulos/inventario' => [
        'id_submodulo' => 52, // submodulos_menu.id = 52 (Kardex / Stock)
        'legacy_rutas' => [],
    ],
    'modulos/bodegas' => [
        'id_submodulo' => 1,  // submodulos_menu.id = 1 (Bodegas)
        'legacy_rutas' => [],
    ],

    // ─── ADQUISICIONES (id_modulo = 309) ─────────────────────────────────────
    // 'modulos/compras'     => ['id_submodulo' => 155, 'legacy_rutas' => [...]],
    // 'modulos/proveedores' => ['id_submodulo' => 156, 'legacy_rutas' => [...]],
    'modulos/ordenes-compra' => [
        'id_submodulo' => 0, // Actualizar con el id real después de insertar en submodulos_menu
        'legacy_rutas' => [],
    ],

    'modulos/retenciones_compras' => [
        'id_submodulo' => 0, // Actualizar con el id real después de ejecutar create_retenciones_compras.sql
        'legacy_rutas' => [],
    ],
    'modulos/retenciones_ventas' => [
        'id_submodulo' => 0, // Actualizar con el id real después de ejecutar create_retenciones_ventas.sql
        'legacy_rutas' => [],
    ],

    // ─── NÓMINA (id_modulo = 313) ────────────────────────────────────────────
    // 'modulos/empleados' => ['id_submodulo' => 169, 'legacy_rutas' => [...]],

    // ─── CONTABILIDAD (id_modulo = 314) ──────────────────────────────────────
    'modulos/centro-costos' => [
        'id_submodulo' => 16,
    ],
    'modulos/proyectos' => [
        'id_submodulo' => 17,
    ],
    'modulos/plan-cuentas' => [
        'id_submodulo' => 175,
    ],
    'modulos/periodos_contables' => [
        'id_submodulo' => 0, // Ajustar con el ID de la base de datos después de la migración
        'legacy_rutas' => [],
    ],
    'modulos/configuracion-contable' => [
        'id_submodulo' => 0,
        'legacy_rutas' => ['modulos/configuracion_contable', 'modulos/plantillas_contables', 'modulos/plantillas-contables'],
    ],
    'modulos/empresa' => [
        'legacy_rutas' => ['modulos/empresa.php'],
    ],

    // ─── PLANTILLAS DE DOCUMENTOS PDF ────────────────────────────────────────
    'modulos/plantillas-pdf' => [
        'id_submodulo' => 0, // Registrar id luego de ejecutar migración/insertar submodulo
        'legacy_rutas' => [],
    ],

    'modulos/formas_cobros_pagos' => [
        'id_submodulo' => 0,
        'legacy_rutas' => ['modulos/formas_cobro', 'modulos/formas_pago'],
    ],

    // ─── SUSCRIPCIONES ───────────────────────────────────────────────────────
    // Registrar id_submodulo después de insertar en submodulos_menu con:
    //   INSERT INTO submodulos_menu (nombre, ruta, ...) VALUES ('Suscripciones', 'modulos/suscripciones', ...)
    'modulos/suscripciones' => [
        'id_submodulo' => 0, // Actualizar con el id real tras insertar en submodulos_menu
        'legacy_rutas' => [],
    ],

    // ─── FACTURA EXPRESS QR ──────────────────────────────────────────────────
    // Dos submódulos independientes con permisos separados.
    // Registrar cada id_submodulo tras insertar en submodulos_menu.
    'modulos/factura-express-config' => [
        'id_submodulo' => 0, // Actualizar con el id real tras insertar en submodulos_menu
        'legacy_rutas' => [],
    ],
    'modulos/factura-express-solicitudes' => [
        'id_submodulo' => 0, // Actualizar con el id real tras insertar en submodulos_menu
        'legacy_rutas' => [],
    ],
    
    // ─── CITAS (id_modulo = 14) ──────────────────────────────────────────────
    'modulos/citas-configuracion' => ['id_submodulo' => 178, 'legacy_rutas' => []],
    'modulos/citas-agenda'        => ['id_submodulo' => 179, 'legacy_rutas' => []],
    'modulos/citas-portal'        => ['id_submodulo' => 180, 'legacy_rutas' => []],
    'modulos/citas-pagos'         => ['id_submodulo' => 181, 'legacy_rutas' => []],

    // ─── WHATSAPP ────────────────────────────────────────────────────────────
    'modulos/configuracion-whatsapp' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
    'modulos/plantillas-whatsapp' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
    'modulos/whatsapp-chat' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
	'modulos/whatsapp-campanas' => [
		'id_submodulo' => 66,
		'legacy_rutas' => [],
	],

    // ─── AUTOMATIZACIONES (Cron dinámico) ─────────────────────────────────────
    'modulos/automatizaciones' => [
        'id_submodulo' => 184,
        'legacy_rutas' => [],
    ],

    // ─── REPORTES ─────────────────────────────────────────────────────────────
    'modulos/reporte_ventas' => [
        'id_submodulo' => 38,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_compras' => [
        'id_submodulo' => 39,
        'legacy_rutas' => [],
    ],

    // ─── CUENTAS POR COBRAR ───────────────────────────────────────────────────
    'modulos/cuentas_por_cobrar' => [
        'id_submodulo' => 36,
        'legacy_rutas' => [],
    ],

    // ─── CUENTAS POR PAGAR ────────────────────────────────────────────────────
    'modulos/cuentas_por_pagar' => [
        'id_submodulo' => 37,
        'legacy_rutas' => [],
    ],

    // ─── SALDOS INICIALES ─────────────────────────────────────────────────────
    'modulos/saldos_iniciales' => [
        'id_submodulo' => 38,
        'legacy_rutas' => [],
    ],
];

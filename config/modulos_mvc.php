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

    // IA Soporte (asistente legal/tributario/contable con IA, BYOK).
    // id_submodulo = 0 → se resuelve por la ruta desde submodulos_menu en cada
    // entorno (200 en local, otro id en producción). No hardcodear el id.
    'modulos/ia-soporte' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],

    'modulos/factura-venta' => [
        'id_submodulo' => 149,
        'legacy_rutas' => [
            'modulos/facturacion.php',
            'modulos/facturacion_lista.php',
            'sistema/modulos/facturacion.php'
        ],
    ],

    // Recibos de venta (comprobante interno, NO electrónico / NO SRI).
    // id_submodulo = 0 → el sistema resuelve el id real por la ruta desde
    // submodulos_menu en CADA entorno (50 en local, otro en producción).
    // No hardcodear el id: así permisos y asignaciones siempre coinciden.
    'modulos/recibo-venta' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],

    'modulos/proformas' => [
        'id_submodulo' => 0, // Actualizar con el id real tras ejecutar la migración 20260619_create_proformas.sql
        'legacy_rutas' => [],
    ],

    // Servicio Car-Wash (órdenes de lavado de vehículos).
    // Actualizar id_submodulo con el id real que retorne 20260705_menu_carwash.sql
    // (SELECT id FROM submodulos_menu WHERE ruta = 'modulos/car-wash';).
    'modulos/car-wash' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
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

    // Envío en lote de comprobantes electrónicos al SRI.
    // Submódulo en submodulos_menu.ruta = 'modulos/envio-lote-sri' (módulo 11, Documentos).
    'modulos/envio-lote-sri' => [
        'id_submodulo' => 190,
        'legacy_rutas' => [],
    ],

    'modulos/transportistas' => [
        'id_submodulo' => 177,
        'legacy_rutas' => [],
    ],

    // Cargas de Inventario (Documentos, id_modulo 11). Actualizar id_submodulo con el
    // id real que retorne la migración de menú (create_menu_cargas_inventario.sql).
    'modulos/cargas-inventario' => [
        'id_submodulo' => 0,
        'legacy_rutas' => ['modulos/cargas_inventario'],
    ],

    // ─── APROBACIONES (config) ────────────────────────────────────────────────
    // Configuración: qué checkpoints exigen aprobación y quién aprueba, por
    // empresa (la bandeja de solicitudes se retiró; solo queda la config).
    'modulos/aprobaciones-config' => [
        'id_submodulo' => 0,
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

    // Traspasos de Fondos (Tesorería): mueve saldo entre dos formas de pago en un solo paso.
    // Actualizar id_submodulo con el id real tras registrar el submódulo en submodulos_menu.
    'modulos/traspasos' => [
        'id_submodulo' => 0,
        'legacy_rutas' => [],
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
    'modulos/anexo-ats' => [
        'id_submodulo' => 27, // submodulos_menu.id donde ruta='modulos/anexo-ats' (Anexo ATS)
        'legacy_rutas' => [
            'modulos/anexo_ats.php',
            'sistema/modulos/anexo_ats.php',
        ],
    ],

    // ─── NÓMINA (id_modulo = 313) ────────────────────────────────────────────
    'modulos/empleados' => [
        'id_submodulo' => 169, // submodulos_menu.id (Empleados)
        'legacy_rutas' => [],
    ],
    'modulos/novedades' => [
        'id_submodulo' => 170, // submodulos_menu.id (Novedades)
        'legacy_rutas' => [],
    ],
    'modulos/roles-pago' => [
        'id_submodulo' => 172, // submodulos_menu.id (Roles de pagos)
        'legacy_rutas' => [],
    ],
    'modulos/vacaciones' => [
        'id_submodulo' => 47, // submodulos_menu.id (Vacaciones)
        'legacy_rutas' => [],
    ],
    // Puntos de servicio (antes "Control asistencia"): puntos QR + horarios/turnos + config.
    'modulos/puntos-servicio' => [
        'id_submodulo' => 194,
        'legacy_rutas' => [],
    ],

    // Marcaciones (bitácora de marcaciones, separada de Control de Asistencia).
    'modulos/marcaciones' => [
        'id_submodulo' => 195,
        'legacy_rutas' => [],
    ],

    // Jornadas (consolidado diario + puente al rol, separado de Control de Asistencia).
    'modulos/jornadas' => [
        'id_submodulo' => 196,
        'legacy_rutas' => [],
    ],

    // Horarios y turnos (separado de Puntos de servicio).
    'modulos/horarios' => [
        'id_submodulo' => 197,
        'legacy_rutas' => [],
    ],

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
    'modulos/auditoria_contable' => [
        'id_submodulo' => 188,
        'legacy_rutas' => [],
    ],
    'modulos/mayores' => [
        'id_submodulo' => 0, // Ajustar con el ID de la base de datos después de la migración
        'legacy_rutas' => [],
    ],
    'modulos/control-bancario' => [
        'id_submodulo' => 0, // Ajustar con el ID real tras ejecutar database/migrations/20260716_menu_control_bancario.sql
        'legacy_rutas' => [],
    ],
    // Conciliación de Cobros Bancarios: importa el extracto del banco (Excel/PDF),
    // sugiere factura/cliente por línea y genera Ingresos en lote.
    'modulos/conciliacion-cobros' => [
        'id_submodulo' => 0, // Ajustar con el ID real tras registrar el submódulo en submodulos_menu
        'legacy_rutas' => [],
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
    'modulos/dashboard' => [
        // Registrar el submódulo en submodulos_menu (id_modulo = 9, Reportes) con
        // ruta = 'modulos/dashboard', y actualizar este id_submodulo con el id real.
        // Asignar permisos en /config/permisos-modulos.
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_ventas' => [
        'id_submodulo' => 38,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_ingresos_egresos' => [
        // Registrar el submódulo en submodulos_menu (id_modulo = 9, Reportes) con
        // ruta = 'modulos/reporte_ingresos_egresos', y actualizar este id_submodulo
        // con el id real. Asignar permisos en /config/permisos-modulos.
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_compras' => [
        'id_submodulo' => 39,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_trazabilidad_productos' => [
        // Registrar el submódulo en submodulos_menu (id_modulo = 9, Reportes) con
        // ruta = 'modulos/reporte_trazabilidad_productos', y actualizar este
        // id_submodulo con el id real. Asignar permisos en /config/permisos-modulos.
        'id_submodulo' => 0,
        'legacy_rutas' => [],
    ],
    'modulos/reporte_inventarios' => [
        // Registrar el submódulo en submodulos_menu (id_modulo = 9, Reportes) con
        // ruta = 'modulos/reporte_inventarios' (ver database/migrations/
        // 20260712_create_reporte_inventarios_submodulo.sql), y actualizar este
        // id_submodulo con el id real. Asignar permisos en /config/permisos-modulos.
        'id_submodulo' => 0,
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

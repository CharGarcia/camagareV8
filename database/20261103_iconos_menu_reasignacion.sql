-- ============================================================================
-- Reasignación de íconos del menú (modulos_menu / submodulos_menu)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Muchos submódulos compartían el mismo ícono (fa-file-contract en 22,
--   fa-dolly en 19, fa-coins en 15…) y otros no tenían relación con la pantalla
--   (helado en el chat de WhatsApp, pan en Plantillas PDF). Se asigna un ícono
--   propio y descriptivo a cada módulo y submódulo.
--
--   Todos son nombres de Font Awesome 5 (prefijo "fas fa-"): la web (Font
--   Awesome 6.5.1) los reconoce como alias y la app móvil, que dibuja con
--   FontAwesome5 (mobile/src/components/ModuloIcono.tsx), también. De paso se
--   corrigen íconos que en la app salían en blanco por ser nombres solo de FA6
--   (fa-diagram-project, fa-file-lines, fa-hand-holding-dollar).
--
--   Los submódulos se buscan por su RUTA y los módulos por su NOMBRE (los id
--   pueden ser distintos entre bases). Una ruta que no exista en esta base
--   simplemente no actualiza nada. "modulos/vehiculos" está en Car-Wash y en
--   Mecánica: ambos quedan con el mismo ícono (auto).
--
-- Qué hace:
--   1. Guarda los íconos actuales en respaldo_iconos_menu_20261103 (solo la
--      primera vez que se ejecuta; si se vuelve a ejecutar, conserva el original).
--   2. Agrega al catálogo iconos_fontawesome los íconos que falten (sin duplicar).
--   3. Cambia id_icono de los módulos y submódulos de la lista.
--   No borra nada. Todo va en una transacción: si algo falla, no cambia nada.
--
-- Reversible: sí, con el bloque «REVERTIR» comentado al final.
--
-- Cómo ejecutarlo en pgAdmin: Query Tool → pegar todo → F5. Es instantáneo.
-- Los usuarios ven los íconos nuevos al recargar la página (y en la app, al
-- volver a abrir el menú).
-- ============================================================================

BEGIN;

-- 1) Respaldo de los íconos actuales -----------------------------------------
CREATE TABLE IF NOT EXISTS respaldo_iconos_menu_20261103 AS
    SELECT 'modulo'::text AS tipo, id, id_icono FROM modulos_menu
    UNION ALL
    SELECT 'submodulo'::text, id, id_icono FROM submodulos_menu;

-- 2) Íconos nuevos -----------------------------------------------------------
CREATE TEMP TABLE tmp_iconos_modulo (nombre text, icono text) ON COMMIT DROP;
INSERT INTO tmp_iconos_modulo (nombre, icono) VALUES
    ('Adquisiciones',          'fas fa-shopping-basket'),
    ('Car-Wash',               'fas fa-car-side'),
    ('Centro Educativo',       'fas fa-school'),
    ('Citas',                  'fas fa-calendar-check'),
    ('Comunicaciones',         'fas fa-comments'),
    ('Whatsapp',               'fas fa-comments'),
    ('Condominios',            'fas fa-building'),
    ('Configuración',          'fas fa-cogs'),
    ('Consignaciones',         'fas fa-dolly-flatbed'),
    ('Contabilidad',           'fas fa-balance-scale'),
    ('Documentos',             'fas fa-folder-open'),
    ('Factura Express',        'fas fa-bolt'),
    ('Inventarios',            'fas fa-boxes'),
    ('Mecánica',               'fas fa-wrench'),
    ('Nómina',                 'fas fa-users'),
    ('Punto de venta (POS)',   'fas fa-cash-register'),
    ('Recurentes',             'fas fa-sync-alt'),
    ('Reportes',               'fas fa-chart-pie'),
    ('Restaurante',            'fas fa-utensils'),
    ('SRI',                    'fas fa-landmark'),
    ('Tesorería',              'fas fa-piggy-bank'),
    ('Ventas',                 'fas fa-shopping-cart');

CREATE TEMP TABLE tmp_iconos_submodulo (ruta text, icono text) ON COMMIT DROP;
INSERT INTO tmp_iconos_submodulo (ruta, icono) VALUES
    -- Adquisiciones
    ('modulos/compras',                        'fas fa-shopping-basket'),
    ('modulos/importaciones',                  'fas fa-ship'),
    ('modulos/liquidacion-compra',             'fas fa-file-invoice-dollar'),
    ('modulos/ordenes-compra',                 'fas fa-clipboard-check'),
    ('modulos/proveedores',                    'fas fa-industry'),
    ('modulos/retenciones_compras',            'fas fa-percentage'),
    -- Car-Wash / Mecánica
    ('modulos/car-wash',                       'fas fa-spray-can'),
    ('modulos/vehiculos',                      'fas fa-car'),
    ('modulos/taller',                         'fas fa-tools'),
    ('modulos/taller-tablero',                 'fas fa-columns'),
    ('modulos/taller-checklist',               'fas fa-clipboard-check'),
    ('modulos/taller-estacion',                'fas fa-toolbox'),
    ('modulos/taller-departamentos',           'fas fa-sitemap'),
    -- Centro Educativo
    ('modulos/alumnos',                        'fas fa-user-graduate'),
    ('modulos/alumnos-campus',                 'fas fa-university'),
    ('modulos/alumnos-niveles',                'fas fa-layer-group'),
    -- Citas
    ('modulos/citas-agenda',                   'fas fa-calendar-alt'),
    ('modulos/citas-portal',                   'fas fa-globe'),
    ('modulos/citas-pagos',                    'fas fa-money-bill-wave'),
    ('modulos/citas-configuracion',            'fas fa-cog'),
    -- Comunicaciones
    ('modulos/whatsapp-chat',                  'fas fa-inbox'),
    ('modulos/whatsapp-campanas',              'fas fa-bullhorn'),
    ('modulos/plantillas-whatsapp',            'fas fa-clone'),
    ('modulos/configuracion-whatsapp',         'fas fa-cog'),
    ('modulos/soporte-chat',                   'fas fa-headset'),
    ('modulos/ia-soporte',                     'fas fa-brain'),
    ('modulos/videollamadas',                  'fas fa-video'),
    -- Configuración
    ('modulos/empresa',                        'fas fa-briefcase'),
    ('modulos/unidades_medida',                'fas fa-ruler'),
    ('modulos/plantillas_pdf',                 'fas fa-file-pdf'),
    ('modulos/automatizaciones',               'fas fa-robot'),
    ('modulos/aprobaciones-config',            'fas fa-check-double'),
    ('modulos/condominios-config',             'fas fa-building'),
    ('modulos/configuracion-payphone',         'fas fa-mobile-alt'),
    ('modulos/configuracion-kushki',           'fas fa-money-check'),
    ('modulos/configuracion-nuvei',            'fas fa-credit-card'),
    -- Consignaciones
    ('modulos/consignaciones-ventas',          'fas fa-dolly'),
    ('modulos/facturacion-cv',                 'fas fa-file-invoice-dollar'),
    ('modulos/entregas-consignaciones',        'fas fa-shipping-fast'),
    ('modulos/retornos-cv',                    'fas fa-undo-alt'),
    ('modulos/cambio-producto-cv',             'fas fa-exchange-alt'),
    -- Contabilidad
    ('modulos/plan-cuentas',                   'fas fa-list-ol'),
    ('modulos/asientos_contables',             'fas fa-book'),
    ('modulos/mayores',                        'fas fa-book-open'),
    ('modulos/balance-comprobacion',           'fas fa-th-list'),
    ('modulos/estados-financieros',            'fas fa-chart-line'),
    ('modulos/indices-financieros',            'fas fa-chart-area'),
    ('modulos/flujo-caja',                     'fas fa-stream'),
    ('modulos/presupuestos',                   'fas fa-bullseye'),
    ('modulos/centro-costos',                  'fas fa-sitemap'),
    ('modulos/proyectos',                      'fas fa-project-diagram'),
    ('modulos/periodos_contables',             'fas fa-calendar-alt'),
    ('modulos/cierre_ejercicio',               'fas fa-lock'),
    ('modulos/auditoria_contable',             'fas fa-search-dollar'),
    ('modulos/configuracion_contable',         'fas fa-sliders-h'),
    ('modulos/balances-consolidados',          'fas fa-object-group'),
    ('modulos/activos-fijos',                  'fas fa-chair'),
    ('modulos/activos-fijos-categorias',       'fas fa-tags'),
    -- Documentos
    ('modulos/descargas_sri',                  'fas fa-cloud-download-alt'),
    ('modulos/carga-facturas',                 'fas fa-file-upload'),
    ('modulos/carga-productos',                'fas fa-file-import'),
    ('modulos/carga-suscripciones',            'fas fa-upload'),
    ('modulos/cargas-inventario',              'fas fa-pallet'),
    ('modulos/saldos_iniciales',               'fas fa-flag-checkered'),
    ('modulos/descargas-masivas',              'fas fa-download'),
    ('modulos/envio-lote-sri',                 'fas fa-paper-plane'),
    ('modulos/reasignar-establecimiento',      'fas fa-random'),
    -- Factura Express
    ('modulos/factura-express-config',         'fas fa-cog'),
    ('modulos/factura-express-solicitudes',    'fas fa-inbox'),
    -- Inventarios
    ('modulos/bodegas',                        'fas fa-warehouse'),
    ('modulos/inventario',                     'fas fa-history'),
    ('modulos/transferencias-inventario',      'fas fa-exchange-alt'),
    -- Nómina
    ('modulos/empleados',                      'fas fa-id-badge'),
    ('modulos/roles-pago',                     'fas fa-money-check-alt'),
    ('modulos/novedades',                      'fas fa-clipboard'),
    ('modulos/decimo-tercero',                 'fas fa-gifts'),
    ('modulos/decimo-cuarto',                  'fas fa-gift'),
    ('modulos/utilidades',                     'fas fa-hand-holding-usd'),
    ('modulos/vacaciones',                     'fas fa-umbrella-beach'),
    ('modulos/horarios',                       'fas fa-clock'),
    ('modulos/jornadas',                       'fas fa-business-time'),
    ('modulos/marcaciones',                    'fas fa-fingerprint'),
    ('modulos/puntos-servicio',                'fas fa-map-marker-alt'),
    -- Punto de venta (POS)
    ('modulos/caja-pos',                       'fas fa-store'),
    ('modulos/reporte-pos',                    'fas fa-chart-bar'),
    -- Recurentes
    ('modulos/suscripciones',                  'fas fa-redo-alt'),
    ('modulos/reporte_ingresos_diferidos',     'fas fa-hourglass-half'),
    -- Reportes
    ('modulos/dashboard',                      'fas fa-tachometer-alt'),
    ('modulos/reporte_ventas',                 'fas fa-chart-line'),
    ('modulos/reporte_ventas_vendedor',        'fas fa-user-tie'),
    ('modulos/reporte_compras',                'fas fa-chart-bar'),
    ('modulos/reporte_inventarios',            'fas fa-boxes'),
    ('modulos/reporte_trazabilidad_productos', 'fas fa-route'),
    ('modulos/cuentas_por_cobrar',             'fas fa-hand-holding-usd'),
    ('modulos/cuentas_por_pagar',              'fas fa-wallet'),
    ('modulos/reporte_cartera',                'fas fa-address-book'),
    ('modulos/reporte_ingresos_egresos',       'fas fa-arrows-alt-v'),
    ('modulos/reporte_resumen_diario',         'fas fa-calendar-day'),
    ('modulos/reporte_pedidos',                'fas fa-clipboard-list'),
    ('modulos/reporte-retenciones',            'fas fa-percentage'),
    ('modulos/reporte_retenciones_pendientes', 'fas fa-hourglass-half'),
    ('modulos/reporte_consolidado',            'fas fa-layer-group'),
    ('modulos/mejor_cliente',                  'fas fa-crown'),
    ('modulos/producto_mas_vendido',           'fas fa-trophy'),
    -- Restaurante
    ('modulos/comandas',                       'fas fa-concierge-bell'),
    ('modulos/mesas',                          'fas fa-chair'),
    ('modulos/menu',                           'fas fa-book-open'),
    ('modulos/kds',                            'fas fa-tv'),
    ('modulos/configuracion-restaurante',      'fas fa-cog'),
    ('modulos/reporte-restaurante',            'fas fa-chart-bar'),
    -- SRI
    ('modulos/declaracion-iva',                'fas fa-percent'),
    ('modulos/declaracion_retenciones',        'fas fa-receipt'),
    ('modulos/declaracion-renta',              'fas fa-file-invoice-dollar'),
    ('modulos/anexo-ats',                      'fas fa-file-alt'),
    ('modulos/anexo-rdep',                     'fas fa-id-card'),
    ('modulos/anexo-dividendos',               'fas fa-coins'),
    -- Tesorería
    ('modulos/ingresos',                       'fas fa-arrow-circle-down'),
    ('modulos/egresos',                        'fas fa-arrow-circle-up'),
    ('modulos/traspasos',                      'fas fa-exchange-alt'),
    ('modulos/control-bancario',               'fas fa-university'),
    ('modulos/conciliacion-cobros',            'fas fa-handshake'),
    ('modulos/conciliacion-tarjetas',          'fas fa-credit-card'),
    ('modulos/transferencias',                 'fas fa-file-upload'),
    ('modulos/formas_cobros_pagos',            'fas fa-money-check'),
    ('modulos/opciones-ingreso-egreso',        'fas fa-list-ul'),
    -- Ventas
    ('modulos/factura-venta',                  'fas fa-file-invoice-dollar'),
    ('modulos/notas_credito',                  'fas fa-minus-square'),
    ('modulos/nota_debito',                    'fas fa-plus-square'),
    ('modulos/proformas',                      'fas fa-file-alt'),
    ('modulos/pedidos',                        'fas fa-clipboard-list'),
    ('modulos/recibo-venta',                   'fas fa-receipt'),
    ('modulos/factura-reembolso',              'fas fa-file-invoice'),
    ('modulos/retenciones_ventas',             'fas fa-percentage'),
    ('modulos/guias_remision',                 'fas fa-truck-moving'),
    ('modulos/clientes',                       'fas fa-user-friends'),
    ('modulos/productos',                      'fas fa-box'),
    ('modulos/categorias',                     'fas fa-tags'),
    ('modulos/marcas',                         'fas fa-registered'),
    ('modulos/vendedores',                     'fas fa-user-tie'),
    ('modulos/transportistas',                 'fas fa-truck'),
    ('modulos/responsables-traslados',         'fas fa-people-carry'),
    ('modulos/firmas_electronicas',            'fas fa-signature'),
    ('modulos/cotizacion-publicidad',          'fas fa-bullhorn'),
    ('modulos/servicio-externo',               'fas fa-hands-helping');

-- 3) Catálogo: agregar los íconos que falten ---------------------------------
-- Por si la secuencia quedó atrás de los id cargados a mano.
SELECT setval(pg_get_serial_sequence('iconos_fontawesome', 'id'),
              GREATEST((SELECT MAX(id) FROM iconos_fontawesome), 1));

-- El catálogo ya trae muchos como "fa-x" (sin "fas "), que la web y la app
-- dibujan igual: se reutilizan en vez de duplicarlos.
INSERT INTO iconos_fontawesome (nombre_icono)
SELECT x.icono
  FROM (SELECT icono FROM tmp_iconos_modulo
        UNION
        SELECT icono FROM tmp_iconos_submodulo) x
 WHERE NOT EXISTS (SELECT 1 FROM iconos_fontawesome i
                    WHERE i.nombre_icono IN (x.icono, REPLACE(x.icono, 'fas ', '')));

-- 4) Asignar ----------------------------------------------------------------
UPDATE modulos_menu m
   SET id_icono = (SELECT MIN(i.id) FROM iconos_fontawesome i
                    WHERE i.nombre_icono IN (t.icono, REPLACE(t.icono, 'fas ', '')))
  FROM tmp_iconos_modulo t
 WHERE LOWER(TRIM(m.nombre_modulo)) = LOWER(t.nombre);

UPDATE submodulos_menu s
   SET id_icono = (SELECT MIN(i.id) FROM iconos_fontawesome i
                    WHERE i.nombre_icono IN (t.icono, REPLACE(t.icono, 'fas ', '')))
  FROM tmp_iconos_submodulo t
 WHERE LOWER(TRIM(BOTH '/' FROM TRIM(s.ruta))) = t.ruta;

COMMIT;

-- ----------------------------------------------------------------------------
-- COMPROBACIÓN (ejecutar aparte). Submódulos que quedaron con el mismo ícono:
-- deben ser solo los que ya tenían el ícono propuesto (Bodegas, Transferencias,
-- Vendedores, Transportistas, Configuración de Factura Express); cualquier otro
-- es un submódulo que no estaba en la lista y conviene revisar.
-- ----------------------------------------------------------------------------
-- SELECT m.nombre_modulo, s.nombre_submodulo, s.ruta, i.nombre_icono
--   FROM submodulos_menu s
--   JOIN modulos_menu m ON m.id = s.id_modulo
--   LEFT JOIN iconos_fontawesome i ON i.id = s.id_icono
--  WHERE s.id NOT IN (SELECT s2.id FROM submodulos_menu s2
--                       JOIN respaldo_iconos_menu_20261103 r ON r.tipo = 'submodulo' AND r.id = s2.id
--                      WHERE r.id_icono IS DISTINCT FROM s2.id_icono)
--  ORDER BY 1, 2;

-- ----------------------------------------------------------------------------
-- REVERTIR (ejecutar aparte, solo si se quiere volver a los íconos anteriores):
-- ----------------------------------------------------------------------------
-- BEGIN;
-- UPDATE modulos_menu m    SET id_icono = r.id_icono FROM respaldo_iconos_menu_20261103 r WHERE r.tipo = 'modulo'    AND r.id = m.id;
-- UPDATE submodulos_menu s SET id_icono = r.id_icono FROM respaldo_iconos_menu_20261103 r WHERE r.tipo = 'submodulo' AND r.id = s.id;
-- COMMIT;
-- (Cuando ya no haga falta: DROP TABLE IF EXISTS respaldo_iconos_menu_20261103;)

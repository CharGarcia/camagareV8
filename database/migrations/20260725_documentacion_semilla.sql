-- ============================================================================
-- Manual del Sistema — artículos semilla (Fase 1)
--
-- Requiere haber ejecutado antes: 20260725_create_documentacion.sql
--
-- Inserta tres artículos para probar el circuito completo:
--   1. modulos/clientes          → visibilidad 'todos'
--   2. modulos/factura-venta     → visibilidad 'todos'
--   3. config/permisos-modulos   → visibilidad 'superadmin' (comprobar que un
--                                   usuario de nivel 1 o 2 NO lo ve ni siquiera
--                                   en los resultados de búsqueda)
--
-- Los tres quedan con origen='archivo' y su archivo_origen apuntando al .md que
-- vivirá en docs/manual/ a partir de la Fase 2: así el sincronizador podrá
-- reemplazarlos por la versión del repositorio. Si se editan desde la pantalla
-- de gestión pasan a origen='manual' y el sincronizador ya no los toca.
--
-- El texto plano de búsqueda y el índice de secciones NO se escriben a mano:
-- se derivan del propio HTML al final de este script (ver bloques 2 y 3), que
-- es exactamente lo que hace el Service al guardar desde la pantalla.
--
-- Idempotente: se puede volver a ejecutar (borra y reinserta estos tres slugs).
-- ============================================================================

BEGIN;

-- Limpieza previa para poder reejecutar el script sin duplicar.
DELETE FROM documentacion_secciones
 WHERE id_documentacion IN (
     SELECT id FROM documentacion
      WHERE slug IN ('modulos/clientes', 'modulos/factura-venta', 'config/permisos-modulos')
 );

DELETE FROM documentacion
 WHERE slug IN ('modulos/clientes', 'modulos/factura-venta', 'config/permisos-modulos');


-- ────────────────────────────────────────────────────────────────────────────
-- 1) Artículos
-- ────────────────────────────────────────────────────────────────────────────

-- 1.1 Clientes ---------------------------------------------------------------
INSERT INTO documentacion
    (slug, titulo, resumen, categoria, ruta_modulo, tipo, visibilidad,
     requiere_permiso_modulo, etiquetas, version, orden, estado,
     origen, archivo_origen, contenido_html)
VALUES (
    'modulos/clientes',
    'Clientes',
    'Registro de los clientes de la empresa: datos de identificación, búsqueda, permisos y eliminación.',
    'Ventas',
    'modulos/clientes',
    'modulo',
    'todos',
    TRUE,
    'clientes, cliente, cartera, ruc, cedula, consumidor final, deudores',
    '1.0',
    10,
    'activo',
    'archivo',
    'modulos/clientes.md',
$html$
<p>El módulo de <strong>Clientes</strong> mantiene el registro de las personas y empresas a las que se les factura. Es la base de las facturas de venta, las proformas, los cobros y las cuentas por cobrar.</p>

<h2 id="que-es-y-para-que-sirve">Qué es y para qué sirve</h2>
<p>Cada cliente pertenece a <strong>una empresa</strong>: los clientes registrados en una empresa no se ven desde otra. Si trabaja con varias empresas, debe registrarlos en cada una.</p>
<p>Un cliente guardado aquí queda disponible en todos los documentos de venta sin volver a escribir sus datos.</p>

<h2 id="como-se-usa">Cómo se usa</h2>
<ol>
  <li>Abra el módulo desde el menú <em>Ventas → Clientes</em>.</li>
  <li>Pulse <strong>Nuevo</strong> para registrar un cliente.</li>
  <li>Complete la identificación (RUC, cédula o pasaporte), el nombre y los datos de contacto.</li>
  <li>Guarde. El cliente queda disponible de inmediato en facturas y proformas.</li>
</ol>
<p>Para modificar un cliente existente, haga clic sobre su fila en el listado.</p>

<h2 id="buscar-en-el-listado">Buscar en el listado</h2>
<p>El buscador acepta texto libre y también filtros con la forma <code>clave:valor</code>:</p>
<ul>
  <li><code>garcia</code> busca ese texto en las columnas principales.</li>
  <li><code>identificacion:1712345678</code> filtra por un campo concreto.</li>
  <li><code>clave:"valor con espacios"</code> para valores que llevan espacios.</li>
  <li><code>-clave:valor</code> excluye los que coincidan.</li>
</ul>
<p>El listado permite además ordenar por cualquier columna, mostrar u ocultar columnas, ajustar su ancho y exportar a PDF y Excel. Esas preferencias se guardan por usuario.</p>

<h2 id="permisos">Permisos</h2>
<p>Lo que puede hacer cada persona depende de los permisos asignados al submódulo:</p>
<ul>
  <li><strong>Ver</strong>: consultar el listado.</li>
  <li><strong>Crear</strong>, <strong>Modificar</strong>, <strong>Eliminar</strong>: las acciones correspondientes.</li>
  <li><strong>Acceso total</strong>: ver los clientes de toda la empresa. Sin este permiso, cada usuario ve únicamente <em>los clientes que él mismo creó</em>.</li>
</ul>
<p>Si no ve clientes que sabe que existen, lo más probable es que le falte el permiso de acceso total.</p>

<h2 id="eliminar-un-cliente">Eliminar un cliente</h2>
<p>La eliminación es <strong>lógica</strong>: el cliente deja de aparecer en los listados pero no se borra de la base de datos, y los documentos que ya lo referencian siguen intactos. Toda eliminación queda registrada en la auditoría del sistema con el usuario y la fecha.</p>

<h2 id="errores-frecuentes">Errores frecuentes</h2>
<ul>
  <li><strong>No aparece en la factura</strong>: verifique que el cliente esté en la misma empresa en la que está facturando.</li>
  <li><strong>No puedo editarlo</strong>: le falta el permiso de modificar, o el cliente lo creó otro usuario y usted no tiene acceso total.</li>
</ul>
$html$
);

-- 1.2 Facturas de Venta ------------------------------------------------------
INSERT INTO documentacion
    (slug, titulo, resumen, categoria, ruta_modulo, tipo, visibilidad,
     requiere_permiso_modulo, etiquetas, version, orden, estado,
     origen, archivo_origen, contenido_html)
VALUES (
    'modulos/factura-venta',
    'Facturas de Venta',
    'Emisión de facturas electrónicas: creación, envío al SRI, PDF, correo y anulación.',
    'Ventas',
    'modulos/factura-venta',
    'modulo',
    'todos',
    TRUE,
    'factura, facturar, venta, sri, comprobante electronico, xml, anular, nota de credito',
    '1.0',
    20,
    'activo',
    'archivo',
    'modulos/factura-venta.md',
$html$
<p>El módulo de <strong>Facturas de Venta</strong> emite los comprobantes electrónicos de venta y gestiona su ciclo completo: creación, autorización en el SRI, entrega al cliente y anulación.</p>

<h2 id="antes-de-empezar">Antes de empezar</h2>
<p>Para emitir facturas electrónicas la empresa necesita tener configurado:</p>
<ul>
  <li>Los datos tributarios de la empresa y su establecimiento.</li>
  <li>La <strong>firma electrónica</strong> vigente.</li>
  <li>El <strong>secuencial</strong> del documento y el ambiente (pruebas o producción).</li>
  <li>El cliente y los productos o servicios que va a facturar.</li>
</ul>

<h2 id="crear-una-factura">Crear una factura</h2>
<ol>
  <li>Pulse <strong>Nuevo</strong>.</li>
  <li>Elija el cliente. Si no existe, regístrelo primero en el módulo de Clientes.</li>
  <li>Agregue las líneas de detalle: producto, cantidad, precio y descuento.</li>
  <li>Revise los totales y el IVA calculado.</li>
  <li>Guarde la factura.</li>
</ol>
<p>Una factura guardada queda en borrador hasta que se envía al SRI.</p>

<h2 id="barra-de-acciones-del-documento">Barra de acciones del documento</h2>
<p>En la parte superior del formulario están las acciones sobre el documento ya guardado: generar el <strong>PDF</strong>, ver el <strong>XML</strong>, enviarlo por <strong>correo</strong> o por <strong>WhatsApp</strong> y remitirlo al <strong>SRI</strong>. Cada acción comprueba primero que la factura esté guardada.</p>

<h2 id="envio-al-sri">Envío al SRI</h2>
<p>Al enviar, el sistema firma el XML y lo transmite al Servicio de Rentas Internas. El comprobante puede quedar autorizado o devuelto con observaciones. Si es devuelto, corrija lo que indique el mensaje y vuelva a enviar.</p>
<p>Cuando hay muchos documentos pendientes conviene usar el envío en lote, que los procesa en segundo plano.</p>

<h2 id="anular-una-factura">Anular una factura</h2>
<p>Una factura <strong>autorizada</strong> no se elimina: se anula, y esa anulación se informa al SRI. Si lo que se necesita es corregir valores o devolver mercadería, el documento correcto es una <strong>nota de crédito</strong>, no la anulación.</p>
<p>Anular una factura revierte también los movimientos asociados (inventario, cobro y asiento contable) según la configuración de la empresa.</p>

<h2 id="errores-frecuentes">Errores frecuentes</h2>
<ul>
  <li><strong>Firma caducada</strong>: renueve el certificado y vuelva a cargarlo en la empresa.</li>
  <li><strong>Secuencial repetido</strong>: revise el secuencial configurado para el establecimiento y punto de emisión.</li>
  <li><strong>El cliente no recibe el correo</strong>: verifique la dirección registrada en su ficha.</li>
</ul>
$html$
);

-- 1.3 Permisos de módulos (SOLO SUPERADMIN) ----------------------------------
-- Aunque aquí se envíe 'superadmin' explícitamente, el Service lo forzaría de
-- todas formas por empezar el slug con 'config/'.
INSERT INTO documentacion
    (slug, titulo, resumen, categoria, ruta_modulo, tipo, visibilidad,
     requiere_permiso_modulo, etiquetas, version, orden, estado,
     origen, archivo_origen, contenido_html)
VALUES (
    'config/permisos-modulos',
    'Permisos de módulos',
    'Cómo se asignan los accesos por submódulo y qué significa cada permiso.',
    'Configuración',
    'config/permisos-modulos',
    'modulo',
    'superadmin',
    FALSE,
    'permisos, accesos, roles, niveles, usuarios, modulos asignados, acceso total',
    '1.0',
    10,
    'activo',
    'archivo',
    'config/permisos-modulos.md',
$html$
<p>Esta pantalla define <strong>qué puede hacer cada usuario en cada submódulo</strong> del sistema, empresa por empresa.</p>

<h2 id="niveles-de-usuario">Niveles de usuario</h2>
<ul>
  <li><strong>Nivel 3 — Superadministrador</strong>: acceso total a todos los módulos, empresas y configuraciones. No necesita asignaciones.</li>
  <li><strong>Nivel 2 — Administrador</strong>: accede a los módulos y configuraciones que el superadministrador le asigne.</li>
  <li><strong>Nivel 1 — Usuario</strong>: accede solo a los módulos y empresas asignados.</li>
</ul>
<p>Ningún usuario, salvo el nivel 3, puede ver información de empresas que no tenga asignadas.</p>

<h2 id="los-cinco-permisos">Los cinco permisos</h2>
<ul>
  <li><strong>Ver</strong>: consultar el listado del submódulo.</li>
  <li><strong>Crear</strong>: registrar nuevos documentos.</li>
  <li><strong>Modificar</strong>: editar los existentes.</li>
  <li><strong>Eliminar</strong>: dar de baja (siempre de forma lógica).</li>
  <li><strong>Acceso total</strong>: ver los registros de <em>toda la empresa</em>.</li>
</ul>

<h2 id="registros-propios-vs-acceso-total">Registros propios frente a acceso total</h2>
<p>Es la distinción que más consultas genera. Si un usuario <strong>no</strong> tiene acceso total, ve únicamente <em>los registros que él mismo creó</em>. Con acceso total ve los de todos los usuarios de esa empresa.</p>
<p>Por eso un vendedor nuevo entra y ve el listado vacío: no es un error, todavía no ha creado nada y no tiene acceso total.</p>

<h2 id="como-asignar-permisos">Cómo asignar permisos</h2>
<ol>
  <li>Elija el usuario y la empresa.</li>
  <li>Marque los permisos submódulo por submódulo.</li>
  <li>Guarde. El cambio se aplica en la siguiente pantalla que abra el usuario.</li>
</ol>

<h2 id="por-que-un-modulo-manda-al-tablero">Por qué un módulo manda al tablero</h2>
<p>Cuando un usuario con permisos correctos entra a un módulo y el sistema lo devuelve al tablero, casi siempre es porque la ruta registrada en el menú no coincide con la ruta real del módulo. Revise que la ruta del submódulo esté escrita igual que la del controlador.</p>
$html$
);


-- ────────────────────────────────────────────────────────────────────────────
-- 2) Texto plano de búsqueda (derivado del HTML)
--
-- Alimenta el tsvector a través del trigger y es la fuente de los fragmentos
-- resaltados de los resultados. Se quitan las etiquetas y se colapsan espacios.
-- ────────────────────────────────────────────────────────────────────────────
UPDATE documentacion
   SET contenido_texto = trim(regexp_replace(
                             regexp_replace(contenido_html, '<[^>]+>', ' ', 'g'),
                             '\s+', ' ', 'g'))
 WHERE slug IN ('modulos/clientes', 'modulos/factura-venta', 'config/permisos-modulos');


-- ────────────────────────────────────────────────────────────────────────────
-- 3) Índice de secciones (derivado del HTML)
--
-- Se parte el contenido por cada <h2 id="…"> y de cada trozo se extrae el ancla,
-- el título y el texto de la sección. Es lo que permite que el buscador lleve al
-- usuario directamente a la sección correcta.
--
-- Los artículos semilla usan solo encabezados de nivel 2 a propósito, para que
-- esta derivación sea exacta. Al guardar desde la pantalla (o al sincronizar en
-- la Fase 2) el Service regenera las secciones incluyendo también los h3.
-- ────────────────────────────────────────────────────────────────────────────
INSERT INTO documentacion_secciones (id_documentacion, nivel, titulo, ancla, contenido, orden)
SELECT d.id,
       2,
       substring(t.parte from 'id="[^"]*">([^<]*)</h2>'),
       substring(t.parte from 'id="([^"]*)"'),
       trim(regexp_replace(
            regexp_replace('<h2 ' || t.parte, '<[^>]+>', ' ', 'g'),
            '\s+', ' ', 'g')),
       (t.orden - 2)::int
  FROM documentacion d
  CROSS JOIN LATERAL unnest(string_to_array(d.contenido_html, '<h2 ')) WITH ORDINALITY AS t(parte, orden)
 WHERE d.slug IN ('modulos/clientes', 'modulos/factura-venta', 'config/permisos-modulos')
   AND t.orden > 1
   AND substring(t.parte from 'id="([^"]*)"') IS NOT NULL;

COMMIT;

-- ── Comprobación rápida ─────────────────────────────────────────────────────
-- SELECT slug, titulo, visibilidad,
--        (SELECT COUNT(*) FROM documentacion_secciones s WHERE s.id_documentacion = d.id) AS secciones,
--        length(contenido_texto) AS caracteres_indexados
--   FROM documentacion d
--  WHERE slug IN ('modulos/clientes', 'modulos/factura-venta', 'config/permisos-modulos')
--  ORDER BY slug;

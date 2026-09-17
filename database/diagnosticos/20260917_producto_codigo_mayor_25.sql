-- ============================================================================
-- Diagnóstico (SOLO LECTURA): producto cuyo código supera los 25 caracteres
-- que admite el SRI en <codigoPrincipal>.
-- ----------------------------------------------------------------------------
-- Contexto:
--   Los XSD del SRI (factura 1.0.0/1.1.0/2.0.0/2.1.0, NC, ND, guía de remisión y
--   liquidación — ver "SRI GUIA TECNICA/") definen codigoPrincipal y codigoAuxiliar
--   con maxLength 25. El catálogo de Productos, en cambio, acepta hasta 50, así que
--   un producto con código largo se cotiza en una proforma pero revienta al
--   convertirla en factura ("Línea #N: el código tiene 26 caracteres...").
--
-- Qué responde:
--   1. Qué producto es y cuánto mide su código.
--   2. Si ya tiene ventas o movimientos de inventario (si los tiene, el formulario
--      de Productos NO deja cambiarle el código: hay que hacerlo por SQL).
--   3. Qué proformas y qué plantillas de proforma guardaron ese código largo en su
--      línea (esa copia es la que usa "convertir a factura", no el maestro).
--   4. Con qué código lo factura su proveedor (candidato natural para el nuevo
--      código, porque ya viene recortado a 25) y sus homologaciones.
--   5. Si el código nuevo que se piensa usar ya está ocupado en la empresa.
--
-- No modifica nada. Ajustar los tres valores del bloque `par` y ejecutar (F5).
-- Corrección: database/20260917_acortar_codigo_producto_a_25.sql
-- ============================================================================

WITH par AS (
    SELECT 54::int    AS id_empresa,                      -- << empresa
           68309::int AS id_producto,                     -- << producto a revisar
           'HAC-HFW1219SLN-IL-A-PRO'::text AS codigo_nuevo -- << código que se piensa usar
)
SELECT origen, valor, detalle
FROM (
    -- 1. El producto
    SELECT 1 AS orden,
           'PRODUCTO' AS origen,
           p.codigo || '  (' || char_length(p.codigo) || ' caracteres)' AS valor,
           p.nombre AS detalle
      FROM productos p
      JOIN par ON p.id = par.id_producto AND p.id_empresa = par.id_empresa

    -- 2. ¿Está en uso? (bloquea el cambio de código desde el formulario)
    UNION ALL
    SELECT 2, 'USO · ventas', COUNT(*)::text || ' línea(s) de factura',
           'Si es mayor a 0, Productos no deja cambiar el código desde la pantalla'
      FROM ventas_detalle vd JOIN par ON vd.id_producto = par.id_producto

    UNION ALL
    SELECT 3, 'USO · inventario', COUNT(*)::text || ' movimiento(s) de kardex',
           'Igual que arriba: bloquea el código en el formulario'
      FROM inventario_kardex k
      JOIN par ON k.id_producto = par.id_producto AND k.id_empresa = par.id_empresa

    -- 3. Copias del código largo guardadas en proformas y plantillas
    UNION ALL
    SELECT 4, 'PROFORMA con el código largo',
           pc.establecimiento || '-' || pc.punto_emision || '-' || pc.secuencial,
           'estado ' || pc.estado || CASE WHEN pc.eliminado THEN ' (eliminada)' ELSE '' END
           || ' · línea ' || pd.id || ' · ' || pd.codigo_principal
      FROM proformas_detalle pd
      JOIN proformas_cabecera pc ON pc.id = pd.id_proforma
      JOIN par ON pc.id_empresa = par.id_empresa AND pd.id_producto = par.id_producto
     WHERE char_length(pd.codigo_principal) > 25

    UNION ALL
    SELECT 5, 'PLANTILLA de proforma con el código largo',
           pl.nombre,
           'línea ' || pld.id || ' · ' || pld.codigo_principal
      FROM proformas_plantillas_detalle pld
      JOIN proformas_plantillas pl ON pl.id = pld.id_plantilla
      JOIN par ON pl.id_empresa = par.id_empresa AND pld.id_producto = par.id_producto
     WHERE char_length(pld.codigo_principal) > 25

    UNION ALL
    SELECT 6, 'LIQUIDACIÓN DE COMPRA con el código largo',
           lc.establecimiento || '-' || lc.punto_emision || '-' || lc.secuencial,
           'línea ' || ld.id || ' · ' || ld.codigo_principal
      FROM liquidaciones_detalle ld
      JOIN liquidaciones_cabecera lc ON lc.id = ld.id_cabecera
      JOIN par ON lc.id_empresa = par.id_empresa AND ld.id_producto = par.id_producto
     WHERE char_length(ld.codigo_principal) > 25

    -- 4. Candidatos para el código nuevo: lo que usa el proveedor
    UNION ALL
    SELECT 7, 'CÓDIGO DEL PROVEEDOR (en compras)',
           cd.codigo_principal || '  (' || char_length(cd.codigo_principal) || ' caracteres)',
           COUNT(*)::text || ' línea(s) de compra'
      FROM compras_detalle cd
      JOIN compras_cabecera cc ON cc.id = cd.id_compra
      JOIN par ON cc.id_empresa = par.id_empresa AND cd.id_producto = par.id_producto
     WHERE COALESCE(cd.codigo_principal, '') <> ''
     GROUP BY cd.codigo_principal

    UNION ALL
    SELECT 8, 'HOMOLOGACIÓN con proveedor',
           ph.codigo_proveedor || '  (' || char_length(ph.codigo_proveedor) || ' caracteres)',
           COALESCE(pr.razon_social, 'proveedor ' || ph.id_proveedor)
      FROM productos_homologacion ph
      LEFT JOIN proveedores pr ON pr.id = ph.id_proveedor
      JOIN par ON ph.id_empresa = par.id_empresa AND ph.id_producto = par.id_producto
     WHERE ph.eliminado = false

    -- 5. ¿El código nuevo está libre? (sin filas = libre)
    UNION ALL
    SELECT 9, 'OJO: el código nuevo YA ESTÁ OCUPADO',
           p2.codigo,
           'lo usa el producto ' || p2.id || ' — ' || p2.nombre
      FROM productos p2
      JOIN par ON p2.id_empresa = par.id_empresa
     WHERE p2.eliminado = false
       AND p2.id <> par.id_producto
       AND lower(p2.codigo) = lower(par.codigo_nuevo)
) t
ORDER BY orden, valor;

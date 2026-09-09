-- ============================================================================
-- Declaración de IVA (F104) — casilleros de ajuste que faltaban en la estructura
-- ----------------------------------------------------------------------------
-- Las fórmulas del SUBTOTAL A PAGAR (620) y del TOTAL IMPUESTO A PAGAR (902)
-- referencian casilleros que no tenían fila en sri_casilleros_etiquetas, así que
-- el módulo los tomaba como 0 y lo advertía:
--
--   620 = (601-602-603-604-605-606-607-608-609-622-623+610+611+612+613+614)
--   902 = (859-898)
--
-- Faltaban: 623, 622, 610, 611, 612, 613, 614 (resumen impositivo) y 898
-- (imputación al pago). Las descripciones son las del formulario 104 vigente,
-- tomadas de una declaración real del SRI ("SRI GUIA TECNICA/declaracion_iva.pdf").
--
-- Todos son casilleros que el CONTRIBUYENTE llena a mano: son ajustes
-- excepcionales (devoluciones de IVA, fusiones, compensaciones, imputación al
-- pago de una sustitutiva) que no salen de ningún documento del sistema. Por eso
-- van con editable = TRUE: aparecen como campo escribible en el formulario y su
-- valor se guarda con la declaración. Mientras no se llenen valen 0, que es el
-- caso normal y deja el 620 y el 902 calculando igual que hasta ahora.
--
-- Ubicación: se colocan justo ANTES de la fila que ya contiene su casillero de
-- destino (620 y 902), en la misma sección y con orden = orden_destino - 1, de
-- modo que quedan agrupadas delante de él sin renumerar nada. El orden entre
-- ellas lo da el id (se insertan en el orden del formulario oficial). El orden
-- solo afecta la presentación: el cálculo no depende de él.
--
-- Tabla GLOBAL de configuración: no lleva id_empresa (CLAUDE.md §4).
-- Ejecutar completo en pgAdmin (idempotente: no duplica si ya existen).
-- ============================================================================

-- Columnas que estos INSERT necesitan, por si la base viene de una versión previa.
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS eliminado BOOLEAN DEFAULT FALSE;
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS fuente_valor VARCHAR(50) DEFAULT 'documentos';
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS editable BOOLEAN NOT NULL DEFAULT FALSE;

-- Casilleros del RESUMEN IMPOSITIVO, en el orden del formulario oficial.
INSERT INTO sri_casilleros_etiquetas (
    seccion, orden, indent, bold, tipo,
    casillero_bruto, formula_bruto, casillero_neto, formula_neto,
    casillero_impuesto, formula_impuesto,
    descripcion, fuente_valor, editable, eliminado
)
SELECT ref.seccion, ref.orden - 1, 1, FALSE, 'valor',
       nuevo.codigo, '', '', '', '', '',
       nuevo.descripcion, 'documentos', TRUE, FALSE
FROM (
    SELECT seccion, orden
    FROM sri_casilleros_etiquetas
    WHERE eliminado = FALSE
      AND '620' IN (COALESCE(casillero_bruto, ''), COALESCE(casillero_neto, ''), COALESCE(casillero_impuesto, ''))
    ORDER BY id
    LIMIT 1
) AS ref
CROSS JOIN (
    VALUES
        (1, '623', '(-) Saldo crédito tributario del mes anterior por procesos de fusión o absorción de sociedades'),
        (2, '622', '(-) IVA devuelto o descontado por transacciones realizadas con personas adultas mayores o personas con discapacidad'),
        (3, '610', '(+) Ajuste por IVA devuelto o descontado por adquisiciones efectuadas con medio electrónico'),
        (4, '611', '(+) Ajuste por IVA devuelto o descontado en adquisiciones efectuadas en zonas afectadas - Ley de solidaridad'),
        (5, '612', '(+) Ajuste por IVA devuelto e IVA rechazado (por concepto de devoluciones de IVA), ajuste de IVA por procesos de control y otros (adquisiciones e importaciones), imputables al crédito tributario'),
        (6, '613', '(+) Ajuste por IVA devuelto e IVA rechazado, ajuste de IVA por procesos de control y otros (por concepto de retenciones en la fuente de IVA), imputables al crédito tributario'),
        (7, '614', '(+) Ajuste por IVA devuelto por otras instituciones del sector público imputable al crédito tributario en el mes')
) AS nuevo(secuencia, codigo, descripcion)
WHERE NOT EXISTS (
    SELECT 1 FROM sri_casilleros_etiquetas x
    WHERE x.eliminado = FALSE
      AND nuevo.codigo IN (COALESCE(x.casillero_bruto, ''), COALESCE(x.casillero_neto, ''), COALESCE(x.casillero_impuesto, ''))
)
ORDER BY nuevo.secuencia;

-- Casillero 898: imputación al pago (solo aplica a declaraciones sustitutivas).
INSERT INTO sri_casilleros_etiquetas (
    seccion, orden, indent, bold, tipo,
    casillero_bruto, formula_bruto, casillero_neto, formula_neto,
    casillero_impuesto, formula_impuesto,
    descripcion, fuente_valor, editable, eliminado
)
SELECT ref.seccion, ref.orden - 1, 1, FALSE, 'valor',
       '898', '', '', '', '', '',
       'Detalle de imputación al pago (para declaraciones sustitutivas): Impuesto',
       'documentos', TRUE, FALSE
FROM (
    SELECT seccion, orden
    FROM sri_casilleros_etiquetas
    WHERE eliminado = FALSE
      AND '902' IN (COALESCE(casillero_bruto, ''), COALESCE(casillero_neto, ''), COALESCE(casillero_impuesto, ''))
    ORDER BY id
    LIMIT 1
) AS ref
WHERE NOT EXISTS (
    SELECT 1 FROM sri_casilleros_etiquetas x
    WHERE x.eliminado = FALSE
      AND '898' IN (COALESCE(x.casillero_bruto, ''), COALESCE(x.casillero_neto, ''), COALESCE(x.casillero_impuesto, ''))
);

-- ----------------------------------------------------------------------------
-- Verificación: los 8 casilleros, existieran antes o se acaben de crear.
-- "filas" debe ser 1 en todos. Si alguno sale con editable = false es porque ya
-- tenía su fila creada de antes y esta migración NO la pisa (respeta la
-- configuración existente): ese campo no se podrá escribir en el formulario
-- hasta marcarlo, con el UPDATE opcional del final de este archivo.
-- ----------------------------------------------------------------------------
SELECT c.codigo,
       count(e.id)                        AS filas,
       min(e.seccion)                     AS seccion,
       min(e.orden)                       AS orden,
       bool_or(e.editable)                AS editable,
       min(e.descripcion)                 AS descripcion
FROM (VALUES ('610'), ('611'), ('612'), ('613'), ('614'), ('622'), ('623'), ('898')) AS c(codigo)
LEFT JOIN sri_casilleros_etiquetas e
       ON e.eliminado = FALSE
      AND c.codigo IN (COALESCE(e.casillero_bruto, ''), COALESCE(e.casillero_neto, ''), COALESCE(e.casillero_impuesto, ''))
GROUP BY c.codigo
ORDER BY c.codigo;

-- ----------------------------------------------------------------------------
-- OPCIONAL — solo si la verificación de arriba muestra alguno con editable = false
-- y usted quiere poder escribir en él. Descomente y ejecute:
-- ----------------------------------------------------------------------------
-- UPDATE sri_casilleros_etiquetas
--    SET editable = TRUE, updated_at = CURRENT_TIMESTAMP
--  WHERE eliminado = FALSE
--    AND editable = FALSE
--    AND COALESCE(casillero_bruto, '') IN ('610', '611', '612', '613', '614', '622', '623', '898');

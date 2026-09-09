-- ============================================================================
-- Declaración de IVA (F104) — fila del casillero 609 en la estructura
-- ----------------------------------------------------------------------------
-- El casillero 609 ("Retenciones en la fuente de IVA que le han sido efectuadas
-- en este período") YA se venía sincronizando desde las retenciones de venta
-- (RetencionVentaService escribe el `casillero_neto` que la empresa mapeó en
-- Empresa → Form 104 IVA → retencion_iva, que es el 609), y DeclaracionIvaService
-- lo usa para repartir el arrastre de crédito tributario entre 615 y 617.
--
-- Pero NO tenía fila en sri_casilleros_etiquetas, y el formulario se dibuja
-- recorriendo esa tabla: el valor existía y afectaba el cálculo, pero era
-- invisible en pantalla. Esta migración crea la fila que faltaba.
--
-- Se ubica en la sección 600_CRED entre el 606 (saldo del mes anterior por
-- retenciones) y el 615 (saldo para el próximo mes por adquisiciones), con
-- orden 185 para no tocar el orden de las filas ya existentes:
--     605 (170) → 606 (180) → 609 (185) → 615 (190) → 617 (200)
--
-- fuente_valor = 'documentos': el valor viene de los documentos sincronizados
-- del período, no de un conteo ni de un arrastre. editable = false por lo mismo
-- (se corrige cambiando el documento o reasignando el casillero desde la
-- pestaña "Detalle de Casilleros", no escribiendo encima).
--
-- Tabla GLOBAL de configuración: no lleva id_empresa (CLAUDE.md §4), así que
-- esta única fila sirve para todas las empresas.
-- Ejecutar completo en pgAdmin (idempotente: no duplica si ya existe).
-- ============================================================================

-- Columnas que este INSERT necesita, por si la base viene de una versión previa.
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS eliminado BOOLEAN DEFAULT FALSE;
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS fuente_valor VARCHAR(50) DEFAULT 'documentos';
ALTER TABLE sri_casilleros_etiquetas ADD COLUMN IF NOT EXISTS editable BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO sri_casilleros_etiquetas (
    seccion, orden, indent, bold, tipo,
    casillero_bruto, formula_bruto,
    casillero_neto,  formula_neto,
    casillero_impuesto, formula_impuesto,
    descripcion, fuente_valor, editable, eliminado
)
SELECT
    '600_CRED', 185, 0, FALSE, 'valor',
    '609', '',
    '',    '',
    '',    '',
    'Retenciones en la fuente de IVA que le han sido efectuadas en este período',
    'documentos', FALSE, FALSE
WHERE NOT EXISTS (
    SELECT 1
    FROM sri_casilleros_etiquetas
    WHERE eliminado = FALSE
      AND '609' IN (COALESCE(casillero_bruto, ''), COALESCE(casillero_neto, ''), COALESCE(casillero_impuesto, ''))
);

-- Verificación: debe listar 605, 606, 609, 615 y 617 en ese orden.
SELECT orden, casillero_bruto, fuente_valor, editable, descripcion
FROM sri_casilleros_etiquetas
WHERE seccion = '600_CRED' AND eliminado = FALSE
ORDER BY orden;

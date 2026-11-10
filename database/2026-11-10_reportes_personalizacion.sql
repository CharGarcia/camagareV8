-- ============================================================================
--  Personalización de reportes por empresa
-- ----------------------------------------------------------------------------
--  Para qué: en los reportes con botón «Personalizar» (hoy: Reporte de Compras;
--  el catálogo vive en app/helpers/ColumnasReporte.php), un administrador
--  (nivel 2 o 3) decide para TODA la empresa qué columnas se muestran en
--  pantalla y Excel, cuáles salen en el PDF y la orientación de la hoja.
--
--  Una fila por empresa, módulo y vista del reporte (Detallado, Por proveedor…).
--  `configuracion` es un JSON:
--     {"ocultas":["usuario"],"ocultas_pdf":["usuario","autorizacion"],"orientacion":"auto"}
--  Sin fila, el reporte se ve y se exporta como siempre (valores por defecto).
--  «Restablecer» = marcar eliminado = true.
--
--  Tabla operativa (lleva id_empresa).
--  Toca datos: no. Reversible: sí (DROP TABLE reportes_personalizacion;
--  los reportes vuelven a sus columnas y orientación por defecto).
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS reportes_personalizacion (
    id             SERIAL PRIMARY KEY,
    id_empresa     INT NOT NULL,
    modulo         VARCHAR(100) NOT NULL,   -- ruta MVC del módulo (p. ej. modulos/reporte_compras)
    vista          VARCHAR(50)  NOT NULL,   -- clave de la vista del reporte (p. ej. NINGUNO, PROVEEDOR)
    configuracion  TEXT NOT NULL,           -- JSON: ocultas, ocultas_pdf, orientacion
    created_at     TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMP NULL,
    created_by     INT NULL,
    updated_by     INT NULL,
    eliminado      BOOLEAN NOT NULL DEFAULT false,
    deleted_at     TIMESTAMP NULL,
    deleted_by     INT NULL
);

COMMENT ON TABLE reportes_personalizacion IS
    'Columnas visibles y orientación del PDF de un reporte, por empresa y vista. Sin fila = valores por defecto. Lo configura un administrador con el botón Personalizar del reporte.';

-- Una sola configuración vigente por empresa, módulo y vista.
CREATE UNIQUE INDEX IF NOT EXISTS ux_reportes_personalizacion
    ON reportes_personalizacion (id_empresa, modulo, vista)
    WHERE eliminado = false;

-- Comprobación (debe devolver 1 fila, no NULL):
-- SELECT to_regclass('public.reportes_personalizacion');

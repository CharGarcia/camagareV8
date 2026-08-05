-- Módulo "Entregas de Consignaciones" (modulos/entregas-consignaciones): resumen de
-- solo lectura sobre consignaciones_ventas_entregas (ver 20260719_entregas_consignaciones.sql).
-- No agrega columnas ni tablas nuevas, solo un índice de apoyo para el listado/KPIs
-- que filtran por empresa + fecha de captura (e.capturado_en) con frecuencia.
-- Deploy manual: subir y ejecutar contra la BD de producción cuando corresponda.

CREATE INDEX IF NOT EXISTS idx_cv_entregas_empresa_capturado
    ON consignaciones_ventas_entregas (id_empresa, capturado_en);

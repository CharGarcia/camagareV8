-- =============================================================
-- Tarjeta de /config: "Importar desde antiguo CaMaGaRe"
-- Hub de importaciones desde la base/servidor viejo (nivel 3).
-- Enlace -> /config/importar-antiguo (ConfigController::importarAntiguo)
-- Tablas globales: configuracion_opciones + configuracion_opcion_enlaces
-- =============================================================

-- Opción (tarjeta) — no duplica si ya existe por nombre
INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
SELECT 'Importar desde antiguo CaMaGaRe',
       'Migra información del sistema anterior: comprobantes XML autorizados y más.',
       'cloud-download', 'dark', 3, 90, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_opciones WHERE nombre = 'Importar desde antiguo CaMaGaRe'
);

-- Enlace de la tarjeta hacia el hub
INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
SELECT o.id, 'Abrir importador', '/config/importar-antiguo', 'dark', 0
FROM configuracion_opciones o
WHERE o.nombre = 'Importar desde antiguo CaMaGaRe'
  AND NOT EXISTS (
      SELECT 1 FROM configuracion_opcion_enlaces e
      WHERE e.id_opcion = o.id AND e.ruta = '/config/importar-antiguo'
  );

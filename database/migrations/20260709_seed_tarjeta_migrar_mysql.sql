-- =============================================================
-- Tarjeta de /config: "Migrar desde base anterior (MySQL)"
-- Conecta a la BD MySQL vieja y migra info al sistema nuevo (nivel 3).
-- Enlace -> /config/migrar-mysql (ConfigController::migrarMysql)
-- =============================================================

INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
SELECT 'Migrar desde base anterior (MySQL)',
       'Conecta a la base MySQL del sistema anterior y migra clientes, productos, documentos, cobros y más.',
       'database-down', 'primary', 3, 91, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM configuracion_opciones WHERE nombre = 'Migrar desde base anterior (MySQL)'
);

INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
SELECT o.id, 'Abrir migración', '/config/migrar-mysql', 'primary', 0
FROM configuracion_opciones o
WHERE o.nombre = 'Migrar desde base anterior (MySQL)'
  AND NOT EXISTS (
      SELECT 1 FROM configuracion_opcion_enlaces e
      WHERE e.id_opcion = o.id AND e.ruta = '/config/migrar-mysql'
  );

-- ============================================================
-- Migración: Crear tabla periodos_contables y registrar módulo
-- Ruta MVC: modulos/periodos_contables
-- ============================================================

CREATE TABLE IF NOT EXISTS periodos_contables (
    id SERIAL PRIMARY KEY,
    id_empresa INTEGER NOT NULL,
    id_usuario INTEGER NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_inicial DATE NOT NULL,
    fecha_final DATE NOT NULL,
    status INTEGER DEFAULT 1, -- 1: Abierto, 0: Cerrado
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

-- Insertar el submódulo en submodulos_menu (ajustar id_modulo si es necesario)
INSERT INTO submodulos_menu (id_modulo, nombre, ruta, icono, orden, status)
VALUES (
    (SELECT id FROM modulos_menu WHERE nombre ILIKE '%contabilidad%' LIMIT 1),
    'Periodos Contables',
    'modulos/periodos_contables',
    'bi bi-calendar-range',
    999,
    true
)
RETURNING id;

-- IMPORTANTE:
-- Después de ejecutar, actualiza config/modulos_mvc.php con el id retornado:
-- 'modulos/periodos_contables' => ['id_submodulo' => <ID_RETORNADO>, 'legacy_rutas' => []],

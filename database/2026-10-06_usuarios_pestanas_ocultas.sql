-- ============================================================================
--  Pestañas que un usuario NO ve en un módulo (configuración por usuario)
-- ----------------------------------------------------------------------------
--  Para qué: en /config/permisos-modulos, al elegir un usuario de nivel 1 y una
--  empresa, aparece una tarjeta por cada módulo con pestañas configurables
--  (hoy: Reporte de Inventarios; el catálogo vive en
--  app/helpers/PestanasModulo.php). El administrador desmarca las pestañas que
--  ese usuario no debe ver.
--
--  Se guardan solo las pestañas OCULTAS: sin filas, el usuario ve todas
--  (comportamiento por defecto). Los niveles 2 y 3 ven todas siempre.
--
--  Tabla operativa (lleva id_empresa). Volver a mostrar una pestaña = marcar
--  eliminado = true; volver a ocultarla crea una fila nueva.
--
--  Toca datos: no. Reversible: sí (DROP TABLE usuarios_pestanas_ocultas;
--  el sistema vuelve a mostrar todas las pestañas a todos).
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios_pestanas_ocultas (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    id_usuario   INT NOT NULL,            -- usuario al que se le oculta la pestaña
    modulo       VARCHAR(100) NOT NULL,   -- ruta MVC del módulo (p. ej. modulos/reporte_inventarios)
    pestana      VARCHAR(50) NOT NULL,    -- clave de la pestaña (p. ej. auditoria)
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);

COMMENT ON TABLE usuarios_pestanas_ocultas IS
    'Pestañas de un módulo que un usuario de nivel 1 no ve en una empresa. Sin filas = ve todas. Se configura en /config/permisos-modulos.';

-- Una pestaña se oculta una sola vez (vigente) por usuario, empresa y módulo.
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_pestanas_ocultas
    ON usuarios_pestanas_ocultas (id_empresa, id_usuario, modulo, pestana)
    WHERE eliminado = false;

-- Comprobación (debe devolver 1 fila, no NULL):
-- SELECT to_regclass('public.usuarios_pestanas_ocultas');

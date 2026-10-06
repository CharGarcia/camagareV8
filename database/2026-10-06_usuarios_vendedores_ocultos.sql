-- ============================================================================
--  Vendedores cuya información NO ve un usuario en un módulo (por usuario)
-- ----------------------------------------------------------------------------
--  Para qué: en /config/permisos-modulos, al elegir un usuario de nivel 1 y una
--  empresa, aparece una tarjeta "Vendedores que puede ver" por cada módulo que
--  admite esta configuración (hoy: Reporte de Ventas; el catálogo vive en
--  app/helpers/VendedoresModulo.php). El administrador desmarca los vendedores
--  cuyas ventas ese usuario no debe ver; el reporte y su selector Vendedor
--  quedan limitados a los demás.
--
--  Se guardan solo los vendedores OCULTOS: sin filas, el usuario ve a todos
--  (comportamiento por defecto). Los niveles 2 y 3 ven a todos siempre. A un
--  usuario sin Acceso total no le cambia nada: ya ve solo lo de su vendedor.
--
--  No confundir con usuarios_vendedores_visibles (Reporte de Ventas por
--  Vendedor), que SUMA vendedores a un usuario restringido.
--
--  Tabla operativa (lleva id_empresa). Volver a mostrar un vendedor = marcar
--  eliminado = true; volver a ocultarlo crea una fila nueva.
--
--  Toca datos: no. Reversible: sí (DROP TABLE usuarios_vendedores_ocultos;
--  todos vuelven a ver a todos los vendedores).
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios_vendedores_ocultos (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    id_usuario   INT NOT NULL,            -- usuario al que se le oculta el vendedor
    modulo       VARCHAR(100) NOT NULL,   -- ruta MVC del módulo (p. ej. modulos/reporte_ventas)
    id_vendedor  INT NOT NULL,            -- vendedor cuya información no ve
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);

COMMENT ON TABLE usuarios_vendedores_ocultos IS
    'Vendedores cuya información un usuario de nivel 1 no ve en un módulo (p. ej. Reporte de Ventas). Sin filas = ve a todos. Se configura en /config/permisos-modulos.';

-- Un vendedor se oculta una sola vez (vigente) por usuario, empresa y módulo.
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_vendedores_ocultos
    ON usuarios_vendedores_ocultos (id_empresa, id_usuario, modulo, id_vendedor)
    WHERE eliminado = false;

-- Comprobación (debe devolver el nombre de la tabla, no NULL):
-- SELECT to_regclass('public.usuarios_vendedores_ocultos');

-- ============================================================================
--  Vendedores que un usuario puede ver en el Reporte de Ventas por Vendedor
-- ----------------------------------------------------------------------------
--  Para qué: en modulos/reporte_ventas_vendedor un usuario de nivel 1 sin
--  "Ver Todo" solo ve las ventas de SU vendedor. Esta tabla permite que el
--  administrador (nivel 2) o el superadministrador (nivel 3) le habilite,
--  además, las ventas de otros vendedores concretos de la empresa (p. ej. un
--  supervisor que revisa a su equipo), sin darle acceso a todos.
--
--  Se administra en /config/permisos-modulos, tarjeta "Vendedores que puede
--  ver", al elegir un usuario de nivel 1 y una empresa.
--
--  Tabla operativa (lleva id_empresa). Quitar un vendedor de la lista = marcar
--  eliminado = true; volver a agregarlo crea una fila nueva.
--
--  Idempotente: se puede ejecutar varias veces sin efecto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios_vendedores_visibles (
    id           SERIAL PRIMARY KEY,
    id_empresa   INT NOT NULL,
    id_usuario   INT NOT NULL,          -- usuario al que se le habilita la vista
    id_vendedor  INT NOT NULL,          -- vendedor cuyas ventas puede ver
    created_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP NULL,
    created_by   INT NULL,
    updated_by   INT NULL,
    eliminado    BOOLEAN NOT NULL DEFAULT false,
    deleted_at   TIMESTAMP NULL,
    deleted_by   INT NULL
);

COMMENT ON TABLE usuarios_vendedores_visibles IS
    'Vendedores adicionales (además del suyo) cuyas ventas puede ver un usuario de nivel 1 en el Reporte de Ventas por Vendedor.';

-- Un vendedor aparece una sola vez (vigente) por usuario y empresa.
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_vendedores_visibles
    ON usuarios_vendedores_visibles (id_empresa, id_usuario, id_vendedor)
    WHERE eliminado = false;

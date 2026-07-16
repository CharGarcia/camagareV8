-- ============================================================
-- Tabla submodulos_vistos: marca qué submódulos ya visitó cada
-- usuario (por empresa). Usada para el aviso del navbar "submódulo
-- nuevo asignado" (App\Services\ContadoresNavbarService).
--
-- "Nuevo" = fila en modulos_asignados con r=true que NO tiene fila
-- aquí. Se marca visto la primera vez que el usuario entra a ese
-- submódulo (POST /contadores/marcarSubmoduloVistoAjax).
-- Idempotente.
-- ============================================================

CREATE TABLE IF NOT EXISTS submodulos_vistos (
    id_usuario   INTEGER NOT NULL,
    id_empresa   INTEGER NOT NULL,
    id_submodulo INTEGER NOT NULL,
    visto_at     TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (id_usuario, id_empresa, id_submodulo)
);

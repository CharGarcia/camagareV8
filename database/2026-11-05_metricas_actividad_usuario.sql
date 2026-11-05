-- =============================================================================
-- Métricas del sistema (Fase B) — actividad diaria de cada usuario por empresa
-- -----------------------------------------------------------------------------
-- Qué hace:
--   Crea `metricas_actividad_usuario`: una fila por (día, usuario, empresa, canal).
--   La llena el sistema solo (AuthMiddleware en la web, ApiAuthMiddleware en la app
--   móvil) como máximo una vez cada ~5 minutos por usuario. Con ella, Métricas del
--   sistema muestra empresas conectadas, usuarios activos por día/semana/mes y un
--   tiempo de uso estimado (latidos × 5 min).
-- Tipo de tabla: bitácora técnica de métricas, como log_sistema. Lleva id_empresa
--   para poder agrupar, pero no es un documento: no tiene eliminado/created_by
--   (nunca se edita ni se borra desde la aplicación).
-- Toca datos: no. Solo crea la tabla y sus índices.
-- Idempotente: sí (IF NOT EXISTS). Se puede reejecutar.
-- Orden de despliegue: aplicar este SQL ANTES de desplegar el código. Si el código
--   llega primero no se rompe nada: el registro falla en silencio y no se guarda.
-- Historial: no se puede rellenar hacia atrás; se acumula desde el día en que se
--   aplica (sesiones_activas no guarda la empresa).
-- Revertir:
--   DROP TABLE IF EXISTS metricas_actividad_usuario;
-- =============================================================================

CREATE TABLE IF NOT EXISTS metricas_actividad_usuario (
    id                 BIGSERIAL   PRIMARY KEY,
    fecha              DATE        NOT NULL DEFAULT CURRENT_DATE,
    id_usuario         INTEGER     NOT NULL,
    id_empresa         INTEGER     NOT NULL,
    canal              VARCHAR(10) NOT NULL DEFAULT 'web',   -- web | movil
    primera_actividad  TIMESTAMP   NOT NULL DEFAULT NOW(),
    ultima_actividad   TIMESTAMP   NOT NULL DEFAULT NOW(),
    latidos            INTEGER     NOT NULL DEFAULT 1,       -- ventanas de ~5 min con actividad
    CONSTRAINT uq_metricas_actividad_usuario UNIQUE (fecha, id_usuario, id_empresa, canal)
);

COMMENT ON TABLE metricas_actividad_usuario IS
    'Actividad diaria por usuario y empresa (Métricas del sistema). Una fila por día/usuario/empresa/canal; latidos = ventanas de ~5 min con actividad.';

CREATE INDEX IF NOT EXISTS idx_metricas_actividad_empresa_fecha
    ON metricas_actividad_usuario (id_empresa, fecha);

CREATE INDEX IF NOT EXISTS idx_metricas_actividad_ultima
    ON metricas_actividad_usuario (ultima_actividad);

-- --- Comprobación (debe salir 1 fila con la tabla y luego 3 índices + la PK) ----
-- SELECT to_regclass('public.metricas_actividad_usuario');
-- SELECT indexname FROM pg_indexes WHERE tablename = 'metricas_actividad_usuario';
-- Después de navegar un rato por el sistema:
-- SELECT * FROM metricas_actividad_usuario ORDER BY ultima_actividad DESC LIMIT 20;

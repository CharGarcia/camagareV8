-- ============================================================================
-- Notas de crédito: objetos de BD que el módulo necesita en PRODUCCIÓN.
--
-- En desarrollo estas estructuras se autocrean desde el constructor del
-- repositorio (CREATE/ALTER ... IF NOT EXISTS), pero el usuario de BD de
-- producción normalmente NO tiene permisos DDL, por lo que esa autocreación
-- falla en silencio. Ejecutar este script UNA VEZ con un usuario con permisos
-- (propietario de la BD o superusuario) deja el módulo operativo en producción.
--
-- Idempotente: se puede correr varias veces sin efectos secundarios.
-- ============================================================================

-- 1. Tabla de información adicional de la nota de crédito.
--    (Su ausencia impedía guardar la NC: el DELETE dentro de la transacción
--     abortaba todo el guardado.)
CREATE TABLE IF NOT EXISTS notas_credito_adicional (
    id              SERIAL PRIMARY KEY,
    id_nota_credito INTEGER NOT NULL,
    nombre          VARCHAR(300) NOT NULL,
    valor           VARCHAR(500)
);

CREATE INDEX IF NOT EXISTS idx_nc_adicional_nc
    ON notas_credito_adicional (id_nota_credito);

-- 2. Columnas adicionales en la cabecera.
ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS estado_correo       VARCHAR(20) DEFAULT 'pendiente';

ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS detalle_xml         TEXT;

ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER;

-- 3. (Opcional) Corrige notas de crédito ya guardadas que quedaron con un
--    tipo_ambiente distinto al que tienen grabado en su clave de acceso
--    (las "invisibles" en el listado). El ambiente real está en la posición 24
--    de la clave (8 fecha + 2 tipo + 13 ruc + 1 ambiente).
UPDATE notas_credito_cabecera
SET    tipo_ambiente = SUBSTRING(clave_acceso FROM 24 FOR 1)
WHERE  clave_acceso IS NOT NULL
  AND  LENGTH(clave_acceso) = 49
  AND  tipo_ambiente IS DISTINCT FROM SUBSTRING(clave_acceso FROM 24 FOR 1);

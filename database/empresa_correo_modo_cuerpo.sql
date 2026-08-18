-- ============================================================================
-- Modo del cuerpo del correo de comprobantes electrónicos (Empresa > Correo)
-- ============================================================================
-- 'diseno' (por defecto): el texto de la empresa se inserta dentro del diseño
--          del sistema (cabecera con logo, datos del comprobante, firma y pie).
-- 'propio': se envía únicamente el contenido escrito por la empresa, sin el
--          marco del sistema. Pensado para quien arma su propio mensaje
--          completo (con su propio saludo y su propia despedida).
--
-- Si el modo es 'propio' pero el cuerpo está vacío, el sistema usa igualmente
-- el diseño, para no enviar un correo en blanco.
-- ============================================================================

ALTER TABLE empresa_correo
    ADD COLUMN IF NOT EXISTS modo_cuerpo_correo VARCHAR(10) NOT NULL DEFAULT 'diseno';

-- Las empresas que ya existen mantienen el diseño del sistema.
UPDATE empresa_correo
   SET modo_cuerpo_correo = 'diseno'
 WHERE modo_cuerpo_correo IS NULL
    OR modo_cuerpo_correo NOT IN ('diseno', 'propio');

ALTER TABLE empresa_correo
    DROP CONSTRAINT IF EXISTS empresa_correo_modo_cuerpo_chk;

ALTER TABLE empresa_correo
    ADD CONSTRAINT empresa_correo_modo_cuerpo_chk
    CHECK (modo_cuerpo_correo IN ('diseno', 'propio'));

COMMENT ON COLUMN empresa_correo.modo_cuerpo_correo IS
    'diseno = el cuerpo va dentro del diseño del sistema; propio = se envía solo el cuerpo de la empresa.';

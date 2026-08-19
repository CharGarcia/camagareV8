-- ============================================================
-- Aprobaciones — Fase 2: aprobación de compras registradas
-- ============================================================
-- Idempotente. Ejecutar DESPUÉS de 20260818_aprobaciones_fase1.sql.
--
-- Activa el checkpoint 'aprobacion_compras' que ya estaba en el catálogo pero
-- que ningún código consultaba. Con él activo, una compra nueva nace en
-- 'pendiente_aprobacion' y hasta que se autorice NO se puede pagar, procesar
-- su inventario ni generar su asiento contable.
--
-- IMPORTANTE — sobre compras_cabecera.estado:
-- La columna existe desde la creación del módulo, pero estaba INERTE: los
-- SELECT del repositorio devolvían el literal 'registrado' en vez de leerla,
-- así que su contenido real nunca se mostró. El valor por defecto de la
-- columna es 'borrador', de modo que muchas filas históricas lo tienen. Al
-- pasar a leer la columna de verdad, esas compras aparecerían como borrador,
-- por eso el paso 1 las normaliza a 'registrado' — que es como el sistema las
-- ha venido tratando siempre.
-- ============================================================

-- ─── 1) Asegurar y normalizar el estado ────────────────────────────────────
-- Según cómo se haya creado la base, la columna puede no existir (el sistema
-- devolvía el literal 'registrado' en cada consulta, así que nadie lo notó).
-- Se crea con DEFAULT 'registrado' para que lo existente quede correcto.
ALTER TABLE compras_cabecera
    ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'registrado';

-- Todo lo que existe hoy está, de hecho, registrado.
UPDATE compras_cabecera
   SET estado = 'registrado'
 WHERE estado IS NULL
    OR estado NOT IN ('registrado', 'anulado', 'pendiente_aprobacion', 'rechazada');

-- ─── 2) Campos del flujo de aprobación ─────────────────────────────────────
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS token_aprobacion VARCHAR(64);
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS aprobado_by      INTEGER;
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS aprobado_at      TIMESTAMP;
ALTER TABLE compras_cabecera ADD COLUMN IF NOT EXISTS motivo_rechazo   TEXT;

-- El token identifica la compra desde el enlace del correo (sin sesión), así
-- que se busca por él en cada aprobación: índice parcial, solo las que lo tienen.
CREATE INDEX IF NOT EXISTS idx_compras_token_aprobacion
    ON compras_cabecera (token_aprobacion) WHERE token_aprobacion IS NOT NULL;

-- Las pendientes se consultan a cada rato desde el listado y los bloqueos.
CREATE INDEX IF NOT EXISTS idx_compras_pendientes
    ON compras_cabecera (id_empresa, estado) WHERE eliminado = false;

-- ─── 3) Asegurar el checkpoint en el catálogo ──────────────────────────────
-- Ya venía del script consolidado de la Fase 1, pero se reafirma aquí con la
-- descripción definitiva para que el modal explique bien qué queda detenido.
INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES (
    'aprobacion_compras',
    'Registro de compras',
    'Si se activa, la compra queda pendiente y no se puede pagar, procesar su inventario ni generar su asiento hasta ser aprobada.',
    'modulos/compras'
)
ON CONFLICT (codigo) DO UPDATE SET
    nombre      = EXCLUDED.nombre,
    descripcion = EXCLUDED.descripcion,
    modulo_ruta = EXCLUDED.modulo_ruta,
    updated_at  = CURRENT_TIMESTAMP;

-- ─── Verificación (informativa) ────────────────────────────────────────────
-- SELECT estado, COUNT(*) FROM compras_cabecera WHERE eliminado = false GROUP BY estado;

-- ============================================================
-- Aprobaciones — Fase 1: migrar los checkpoints que vivían en Empresa
-- ============================================================
-- Idempotente. Ejecutar DESPUÉS de 20260716h_aprobaciones_produccion_final.sql
-- (ese crea aprobaciones_tipos y aprobaciones_config).
--
-- Qué hace:
--   1. Agrega eliminación lógica a aprobaciones_config (regla §5). La fila
--      existe = la empresa configuró esa aprobación; eliminada = no la usa.
--   2. Agrega al catálogo los tres checkpoints que hoy se configuran en
--      modulos/empresa (pestañas Inventario y Transferencias).
--   3. Backfill: copia empresa_establecimiento.inv_* / transf_* a
--      aprobaciones_config, elevando la config de establecimiento a empresa.
--
-- NO elimina las columnas viejas de empresa_establecimiento: se dejan como
-- respaldo hasta confirmar el módulo en producción.
-- ============================================================

-- ─── 1) Eliminación lógica en aprobaciones_config ──────────────────────────
ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS eliminado  BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;
ALTER TABLE aprobaciones_config ADD COLUMN IF NOT EXISTS deleted_by INTEGER;

CREATE INDEX IF NOT EXISTS idx_aprob_config_empresa_vivas
    ON aprobaciones_config (id_empresa) WHERE eliminado = false;

-- ─── 2) Catálogo: checkpoints que se migran desde Empresa ──────────────────
-- Inventario e Importaciones se separan en dos checkpoints independientes
-- (antes compartían el flag inv_requiere_aprobacion).
INSERT INTO aprobaciones_tipos (codigo, nombre, descripcion, modulo_ruta)
VALUES
    (
        'carga_inventario',
        'Cargas de inventario',
        'Si se activa, las cargas de inventario quedan pendientes y no afectan el stock hasta ser aprobadas.',
        'modulos/cargas-inventario'
    ),
    (
        'importaciones',
        'Nacionalización de importaciones',
        'Si se activa, la nacionalización queda pendiente y no se postea al kardex hasta ser aprobada.',
        'modulos/importaciones'
    ),
    (
        'pago_bancario',
        'Lotes de pago bancario',
        'Si se activa, el lote queda pendiente y no se puede generar el archivo bancario hasta ser aprobado.',
        'modulos/transferencias'
    )
ON CONFLICT (codigo) DO UPDATE SET
    nombre      = EXCLUDED.nombre,
    descripcion = EXCLUDED.descripcion,
    modulo_ruta = EXCLUDED.modulo_ruta,
    updated_at  = CURRENT_TIMESTAMP;

-- ─── 3) Backfill desde empresa_establecimiento ─────────────────────────────
-- La config vieja era por establecimiento; el motor es por empresa. Se toma
-- un establecimiento por empresa priorizando el que tenga la aprobación
-- ACTIVADA (y, en empate, el de menor id — que es el que leían los services
-- vía getPrimerEstablecimientoId).

-- 3a) Cargas de inventario e Importaciones (ambos salen de inv_*).
WITH origen AS (
    SELECT DISTINCT ON (e.id_empresa)
           e.id_empresa,
           COALESCE(e.inv_requiere_aprobacion, false)          AS requiere,
           COALESCE(e.inv_usuarios_aprobadores, '[]'::jsonb)   AS aprobadores
    FROM empresa_establecimiento e
    WHERE e.eliminado = false
    ORDER BY e.id_empresa, COALESCE(e.inv_requiere_aprobacion, false) DESC, e.id ASC
),
tipos AS (
    SELECT id, codigo FROM aprobaciones_tipos WHERE codigo IN ('carga_inventario', 'importaciones')
)
INSERT INTO aprobaciones_config
    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto, eliminado, created_at, updated_at)
SELECT o.id_empresa, t.id, o.requiere, o.aprobadores, NULL, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM origen o
CROSS JOIN tipos t
WHERE jsonb_array_length(o.aprobadores) > 0   -- solo empresas que alcanzaron a configurarlo
ON CONFLICT (id_empresa, id_tipo) DO NOTHING; -- si ya se configuró en el módulo, manda el módulo

-- 3b) Lotes de pago bancario (transf_*).
WITH origen AS (
    SELECT DISTINCT ON (e.id_empresa)
           e.id_empresa,
           COALESCE(e.transf_requiere_aprobacion, false)        AS requiere,
           COALESCE(e.transf_usuarios_aprobadores, '[]'::jsonb) AS aprobadores
    FROM empresa_establecimiento e
    WHERE e.eliminado = false
    ORDER BY e.id_empresa, COALESCE(e.transf_requiere_aprobacion, false) DESC, e.id ASC
)
INSERT INTO aprobaciones_config
    (id_empresa, id_tipo, requiere_aprobacion, usuarios_aprobadores, umbral_monto, eliminado, created_at, updated_at)
SELECT o.id_empresa, t.id, o.requiere, o.aprobadores, NULL, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM origen o
CROSS JOIN (SELECT id FROM aprobaciones_tipos WHERE codigo = 'pago_bancario') t
WHERE jsonb_array_length(o.aprobadores) > 0
ON CONFLICT (id_empresa, id_tipo) DO NOTHING;

-- ─── Verificación (informativa) ────────────────────────────────────────────
-- SELECT e.nombre_comercial, t.nombre, c.requiere_aprobacion, c.usuarios_aprobadores
-- FROM aprobaciones_config c
-- JOIN aprobaciones_tipos t ON t.id = c.id_tipo
-- JOIN empresas e ON e.id = c.id_empresa
-- WHERE c.eliminado = false ORDER BY e.nombre_comercial, t.nombre;

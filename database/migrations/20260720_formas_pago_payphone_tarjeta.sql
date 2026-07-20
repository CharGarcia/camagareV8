-- ==========================================================
-- Formas de Cobro/Pago: separar TARJETA (Payphone) en dos conceptos
--
-- Contexto: hasta ahora el tipo 'TARJETA' de empresa_formas_pago se usaba
-- exclusivamente para el cobro online vía pasarela Payphone. Se introduce
-- un tipo nuevo 'PAYPHONE' para ese concepto (solo aplica a Ingresos) y el
-- tipo 'TARJETA' queda libre para representar tarjeta física/datáfono
-- (aplica a Ingresos y Egresos).
--
-- Este script:
--   1) Renombra toda fila existente tipo='TARJETA' a tipo='PAYPHONE'
--      (nombre 'Payphone', aplica_en forzado a 'INGRESO').
--   2) Inserta la forma 'Payphone' (tipo PAYPHONE) para toda empresa activa
--      que no tenga ya una.
--   3) Inserta la forma 'Tarjeta' (tipo TARJETA, física) para toda empresa
--      activa que no tenga ya una (todas, ya que el paso 1 liberó el tipo).
--
-- Entregado como SQL para despliegue manual, según el flujo del proyecto.
-- ==========================================================
BEGIN;

-- 1) Renombrar las formas existentes tipo TARJETA (Payphone) -> PAYPHONE
UPDATE empresa_formas_pago
SET tipo = 'PAYPHONE',
    nombre = 'Payphone',
    aplica_en = 'INGRESO',
    modalidad_tarjeta = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE tipo = 'TARJETA'
  AND eliminado = false;

-- 2) Insertar 'Payphone' (tipo PAYPHONE) para empresas activas que no la tengan
INSERT INTO empresa_formas_pago (
    id_empresa, nombre, tipo, aplica_en, modalidad_tarjeta, activo,
    created_at, eliminado
)
SELECT e.id, 'Payphone', 'PAYPHONE', 'INGRESO', NULL, true,
       CURRENT_TIMESTAMP, false
FROM empresas e
WHERE e.eliminado = false
  AND NOT EXISTS (
        SELECT 1 FROM empresa_formas_pago efp
        WHERE efp.id_empresa = e.id
          AND efp.tipo = 'PAYPHONE'
          AND efp.eliminado = false
      );

-- 3) Insertar 'Tarjeta' (tipo TARJETA, física/datáfono) para empresas activas
--    que no la tengan (todas, tras el paso 1)
INSERT INTO empresa_formas_pago (
    id_empresa, nombre, tipo, aplica_en, modalidad_tarjeta, activo,
    created_at, eliminado
)
SELECT e.id, 'Tarjeta', 'TARJETA', 'AMBAS', 'AMBAS', true,
       CURRENT_TIMESTAMP, false
FROM empresas e
WHERE e.eliminado = false
  AND NOT EXISTS (
        SELECT 1 FROM empresa_formas_pago efp
        WHERE efp.id_empresa = e.id
          AND efp.tipo = 'TARJETA'
          AND efp.eliminado = false
      );

COMMIT;

-- ==========================================================
-- Formas de Cobro/Pago: alta de la forma 'Nuvei' (tipo NUVEI)
--
-- A diferencia de PAYPHONE (que reemplazó a TARJETA), NUVEI es puramente
-- aditivo: no renombra ni toca ninguna forma existente. Solo inserta
-- 'Nuvei' (tipo NUVEI, aplica_en INGRESO) para las empresas activas que
-- aún no la tengan. Es opcional: el usuario también puede crearla a mano
-- desde /modulos/formas_cobros_pagos.
--
-- Entregado como SQL para despliegue manual, según el flujo del proyecto.
-- ==========================================================
BEGIN;

INSERT INTO empresa_formas_pago (
    id_empresa, nombre, tipo, aplica_en, modalidad_tarjeta, activo,
    created_at, eliminado
)
SELECT e.id, 'Nuvei', 'NUVEI', 'INGRESO', NULL, true,
       CURRENT_TIMESTAMP, false
FROM empresas e
WHERE e.eliminado = false
  AND NOT EXISTS (
        SELECT 1 FROM empresa_formas_pago efp
        WHERE efp.id_empresa = e.id
          AND efp.tipo = 'NUVEI'
          AND efp.eliminado = false
      );

COMMIT;

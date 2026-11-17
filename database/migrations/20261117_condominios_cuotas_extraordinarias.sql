-- ============================================================================
-- Condominios: cuotas extraordinarias con meta, pagadas a plazos (p. ej. «Pintura del
-- edificio», saldo dividido en 24 cuotas). Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
--
-- NO crea tablas nuevas: completa las que ya trae 20261005_condominios.sql y que hasta hoy
-- no usaba ningún código:
--   condominios_aportes            la cuota extraordinaria (meta, reparto, plazos, dónde sale)
--   condominios_aportes_unidades   el plan de cada inmueble (asignado, anticipo, plazo, pagador)
--   condominios_cargos             una fila por cuota mensual (y por abono inicial / liquidación),
--                                  con el documento en que salió
--   condominios_emisiones          emisión en bloque: ahora también emite las cuotas «aparte»
--
-- Requisitos: 20261005_condominios.sql y 20261009_condominios_emisiones.sql ya aplicados.
-- Solo agrega columnas nullables o con DEFAULT y amplía CHECK: no cambia nada de lo que ya
-- funciona. Idempotente (se puede correr dos veces). Listo para pgAdmin (F5).
-- ============================================================================

BEGIN;

-- ─── 1. condominios_aportes: la cuota extraordinaria ───────────────────────────────
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS acta               VARCHAR(100);   -- «Acta 05-2026»
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS fecha_acta         DATE;
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS plazo_defecto      SMALLINT NOT NULL DEFAULT 1;  -- uno de plazos_permitidos
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS primer_periodo     CHAR(6);        -- AAAAMM de la primera cuota (por defecto para todos)
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS donde_cobro        VARCHAR(10) NOT NULL DEFAULT 'junto';        -- junto | aparte
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS pagador_defecto    VARCHAR(12) NOT NULL DEFAULT 'propietario';  -- propietario | arrendatario
-- Documento propio (cuotas «aparte», abono inicial con anticipo, liquidación del saldo)
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS tipo_comprobante   VARCHAR(10);    -- factura | recibo
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS id_punto_emision   INTEGER;        -- serie
ALTER TABLE condominios_aportes ADD COLUMN IF NOT EXISTS id_forma_anticipo  INTEGER;        -- forma de cobro tipo ANTICIPO con la que se aplican los anticipos

-- Reparto: se agrega 'iguales' (meta ÷ número de inmuebles incluidos). 'porcentaje' = meta × % de
-- alícuota del inmueble ÷ Σ % de los incluidos. Los demás valores se conservan.
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_calculo;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_calculo
    CHECK (calculo IN ('pct_alicuota', 'fijo', 'porcentaje', 'm2', 'manual', 'iguales'));
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_donde;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_donde
    CHECK (donde_cobro IN ('junto', 'aparte'));
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_pagador;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_pagador
    CHECK (pagador_defecto IN ('propietario', 'arrendatario'));
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_comprobante;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_comprobante
    CHECK (tipo_comprobante IS NULL OR tipo_comprobante IN ('factura', 'recibo'));
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_plazo_def;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_plazo_def
    CHECK (plazo_defecto BETWEEN 1 AND 120);
ALTER TABLE condominios_aportes DROP CONSTRAINT IF EXISTS chk_cond_ap_primer_per;
ALTER TABLE condominios_aportes ADD  CONSTRAINT chk_cond_ap_primer_per
    CHECK (primer_periodo IS NULL OR primer_periodo ~ '^[0-9]{6}$');

-- ─── 2. condominios_aportes_unidades: el plan de cada inmueble ─────────────────────
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS pagador             VARCHAR(12) NOT NULL DEFAULT 'propietario'; -- propietario | arrendatario
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS id_cliente          INTEGER;        -- a quién se le cobra hoy (resuelto del pagador)
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS ajuste_manual       BOOLEAN NOT NULL DEFAULT false; -- monto_asignado escrito a mano
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS anticipo_aplicado   NUMERIC(14,2) NOT NULL DEFAULT 0;   -- parte del anticipo de clientes usada aquí
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS saldo_plazos        NUMERIC(14,2) NOT NULL DEFAULT 0;   -- monto_total − anticipo_aplicado
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS cuota_valor         NUMERIC(14,2) NOT NULL DEFAULT 0;   -- cuota mensual
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS cuota_ultima        NUMERIC(14,2) NOT NULL DEFAULT 0;   -- absorbe los centavos
-- Abono inicial con el anticipo: documento + ingreso que lo cruza con la forma ANTICIPO
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS tipo_documento_abono VARCHAR(10);   -- factura | recibo
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS id_documento_abono  INTEGER;
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS id_ingreso_abono    INTEGER;
-- Liquidación (pago total anticipado o venta del inmueble)
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS fecha_liquidacion   DATE;
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS motivo_liquidacion  VARCHAR(20);    -- pago_anticipado | venta
ALTER TABLE condominios_aportes_unidades ADD COLUMN IF NOT EXISTS id_cliente_liquidacion INTEGER;     -- a quién se le emitió el saldo

-- Estados del plan: pendiente (aún sin cuotas) | en_curso | completo (todas las cuotas emitidas)
-- | liquidado (saldo emitido de una vez) | cancelado. 'pagado' se conserva por compatibilidad:
-- lo pagado se deriva de Cuentas por Cobrar, no se guarda.
ALTER TABLE condominios_aportes_unidades DROP CONSTRAINT IF EXISTS chk_cond_apu_estado;
ALTER TABLE condominios_aportes_unidades ADD  CONSTRAINT chk_cond_apu_estado
    CHECK (estado IN ('pendiente', 'en_curso', 'completo', 'liquidado', 'pagado', 'cancelado'));
ALTER TABLE condominios_aportes_unidades DROP CONSTRAINT IF EXISTS chk_cond_apu_pagador;
ALTER TABLE condominios_aportes_unidades ADD  CONSTRAINT chk_cond_apu_pagador
    CHECK (pagador IN ('propietario', 'arrendatario'));
ALTER TABLE condominios_aportes_unidades DROP CONSTRAINT IF EXISTS chk_cond_apu_anticipo;
ALTER TABLE condominios_aportes_unidades ADD  CONSTRAINT chk_cond_apu_anticipo
    CHECK (anticipo_aplicado >= 0 AND anticipo_aplicado <= monto_total);
ALTER TABLE condominios_aportes_unidades DROP CONSTRAINT IF EXISTS chk_cond_apu_tipo_abono;
ALTER TABLE condominios_aportes_unidades ADD  CONSTRAINT chk_cond_apu_tipo_abono
    CHECK (tipo_documento_abono IS NULL OR tipo_documento_abono IN ('factura', 'recibo'));
ALTER TABLE condominios_aportes_unidades DROP CONSTRAINT IF EXISTS chk_cond_apu_motivo_liq;
ALTER TABLE condominios_aportes_unidades ADD  CONSTRAINT chk_cond_apu_motivo_liq
    CHECK (motivo_liquidacion IS NULL OR motivo_liquidacion IN ('pago_anticipado', 'venta'));
CREATE INDEX IF NOT EXISTS idx_cond_apu_aporte ON condominios_aportes_unidades (id_aporte, estado) WHERE eliminado = false;

-- ─── 3. condominios_cargos: una fila por cuota ─────────────────────────────────────
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS subtipo          VARCHAR(12);    -- solo concepto 'aporte': cuota | abono | liquidacion
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS numero_cuota     SMALLINT;       -- 5 (de 24)
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS total_cuotas     SMALLINT;       -- 24
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS id_cliente       INTEGER;        -- a quién se cobra (propietario o arrendatario)
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS donde_cobro      VARCHAR(10);    -- junto | aparte (copiado del aporte al crear el plan)
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS id_suscripcion   INTEGER;        -- si salió junto a la alícuota
ALTER TABLE condominios_cargos ADD COLUMN IF NOT EXISTS id_emision_item  INTEGER;        -- si salió aparte por emisión en bloque

ALTER TABLE condominios_cargos DROP CONSTRAINT IF EXISTS chk_cond_car_subtipo;
ALTER TABLE condominios_cargos ADD  CONSTRAINT chk_cond_car_subtipo
    CHECK (subtipo IS NULL OR subtipo IN ('cuota', 'abono', 'liquidacion'));
ALTER TABLE condominios_cargos DROP CONSTRAINT IF EXISTS chk_cond_car_donde;
ALTER TABLE condominios_cargos ADD  CONSTRAINT chk_cond_car_donde
    CHECK (donde_cobro IS NULL OR donde_cobro IN ('junto', 'aparte'));
ALTER TABLE condominios_cargos DROP CONSTRAINT IF EXISTS chk_cond_car_cuota;
ALTER TABLE condominios_cargos ADD  CONSTRAINT chk_cond_car_cuota
    CHECK (numero_cuota IS NULL OR (numero_cuota >= 1 AND total_cuotas >= numero_cuota));

-- Una misma cuota de un plan nunca existe dos veces (salvo anulada o eliminada).
CREATE UNIQUE INDEX IF NOT EXISTS uq_cond_car_cuota_plan
    ON condominios_cargos (id_aporte_unidad, numero_cuota)
    WHERE eliminado = false AND estado <> 'anulado' AND numero_cuota IS NOT NULL;
-- Búsqueda de lo pendiente al emitir (junto a la suscripción o aparte).
CREATE INDEX IF NOT EXISTS idx_cond_car_pendientes
    ON condominios_cargos (id_empresa, estado, donde_cobro, periodo)
    WHERE eliminado = false AND estado = 'pendiente';
CREATE INDEX IF NOT EXISTS idx_cond_car_aporte_unidad
    ON condominios_cargos (id_aporte_unidad) WHERE eliminado = false;

-- ─── 4. condominios_emisiones: emitir las cuotas «aparte» ──────────────────────────
-- forma_valor 'cargos' = el valor de cada ítem es la suma de las cuotas pendientes del inmueble.
ALTER TABLE condominios_emisiones ADD COLUMN IF NOT EXISTS id_aporte INTEGER;   -- cuota extraordinaria emitida
ALTER TABLE condominios_emisiones ADD COLUMN IF NOT EXISTS periodo   CHAR(6);   -- hasta qué mes se emiten las cuotas
ALTER TABLE condominios_emisiones DROP CONSTRAINT IF EXISTS chk_cond_emi_forma;
ALTER TABLE condominios_emisiones ADD  CONSTRAINT chk_cond_emi_forma
    CHECK (forma_valor IN ('fijo', 'inmueble', 'cargos'));

COMMIT;

-- ============================================================================
-- Novedades: flag de desembolso migrado (Camino 1)
-- ----------------------------------------------------------------------------
-- Los Anticipos (tipo 3) y Préstamos Empresa (tipo 9) que vienen del sistema
-- anterior se registraban YA pagados/desembolsados (sin egreso). En el sistema
-- nuevo "desembolsado" se deriva de un egreso ANTICIPO/PRESTAMO9; como esos
-- egresos no existen para los migrados, se usa este flag para que la nómina los
-- trate como desembolsados (no los muestre pendientes, los cuente como pagados
-- y no vuelva a pedir su desembolso por egreso).
--
-- La migración de Novedades lo marca en true para tipos 3 y 9. El resto de las
-- novedades y las nativas quedan en false (comportamiento normal por egreso/rol).
-- Ejecutar ANTES de desplegar el código nuevo (las consultas de nómina ya
-- referencian la columna).
-- ============================================================================

ALTER TABLE novedades
    ADD COLUMN IF NOT EXISTS desembolsado_migrado BOOLEAN NOT NULL DEFAULT false;

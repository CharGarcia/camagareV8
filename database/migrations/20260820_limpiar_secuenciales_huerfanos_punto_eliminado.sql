-- =============================================================================
-- Limpieza de secuenciales huérfanos: empresa_secuencial con eliminado=false
-- cuyo punto de emisión (empresa_punto_emision) ya está eliminado.
-- ----------------------------------------------------------------------------
-- Causa: deletePuntoEmision() solo daba de baja el punto, no los tipos de
-- secuencial que tenía configurados (empresa_secuencial). El código ya se
-- corrigió para dar de baja ambos en la misma transacción (ver
-- EmpresaRepository::deletePuntoEmision()), pero eso no repara los huérfanos
-- que ya quedaron de puntos eliminados ANTES de ese fix.
--
-- Efecto observado: un tipo de "único punto por empresa" (hoy solo "Facturas
-- de reembolso", ver SecuencialRepository::TIPOS_PUNTO_UNICO) seguía
-- bloqueado en cualquier punto nuevo aunque el punto original que lo tenía
-- ya se hubiera eliminado — porque la fila de empresa_secuencial seguía
-- "activa" (eliminado=false) sin que su punto lo estuviera.
--
-- Este script solo AFECTA filas cuyo punto padre ya está eliminado: no toca
-- ningún secuencial de un punto activo. Idempotente: si se corre dos veces,
-- la segunda vez no encuentra filas para actualizar.
-- =============================================================================

BEGIN;

UPDATE empresa_secuencial es
   SET eliminado = true,
       deleted_at = NOW(),
       updated_at = NOW()
  FROM empresa_punto_emision pe
 WHERE pe.id = es.id_punto_emision
   AND es.eliminado = false
   AND pe.eliminado = true;

COMMIT;

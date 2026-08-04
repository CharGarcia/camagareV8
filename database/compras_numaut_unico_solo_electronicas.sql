-- ============================================================================
-- Autorización de compra: única SOLO para comprobantes ELECTRÓNICOS (49 dígitos)
-- ----------------------------------------------------------------------------
-- El índice uq_compras_numaut_activo exigía que numero_autorizacion fuera único
-- por empresa. Eso es correcto para la CLAVE DE ACCESO electrónica (49 dígitos,
-- única por documento), pero ROMPE los comprobantes FÍSICOS: su autorización de
-- impresión (10 dígitos) se comparte legítimamente entre todos los documentos
-- del mismo talonario, así que la misma autorización aparece en muchas compras.
--
-- Con el índice viejo, migrar (o registrar a mano) dos facturas físicas del mismo
-- talonario reventaba con 23505, y la migración terminaba anulando la autorización
-- física a partir de la 2ª ocurrencia.
--
-- Este índice hace que la unicidad aplique SOLO cuando numero_autorizacion tiene
-- exactamente 49 dígitos (electrónicas). Las físicas de 10 dígitos pueden repetirse.
-- Correr ANTES de re-migrar compras.
-- ============================================================================

DROP INDEX IF EXISTS uq_compras_numaut_activo;

CREATE UNIQUE INDEX uq_compras_numaut_activo
    ON compras_cabecera (id_empresa, numero_autorizacion)
    WHERE eliminado = false
      AND numero_autorizacion IS NOT NULL
      AND numero_autorizacion <> ''
      AND length(regexp_replace(numero_autorizacion, '[^0-9]', '', 'g')) = 49;

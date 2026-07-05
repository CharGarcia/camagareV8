-- Vincula el retorno con su asiento contable (reclasificación inversa a la consignación).
ALTER TABLE retornos_cv ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL;

-- Cliente (de la empresa controladora) al que se factura la suscripción de esta empresa.
-- Reemplaza a id_empresa_facturada: ahora se selecciona el CLIENTE exacto de la
-- controladora (que es a quien apunta la suscripción), no una empresa del sistema.
ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS id_cliente_facturado INTEGER NULL;

-- Opcional (cuando confirmes que ya no se usa):
-- ALTER TABLE empresas DROP COLUMN IF EXISTS id_empresa_facturada;

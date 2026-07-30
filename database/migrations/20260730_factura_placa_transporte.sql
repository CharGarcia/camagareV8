-- ============================================================================
-- Placa del vehículo en facturas de OPERADORAS DE TRANSPORTE COMERCIAL
-- Ficha Técnica SRI v2.34, Anexo 25 (Res. NAC-DGERCGC26-00000024)
-- ----------------------------------------------------------------------------
-- Solo aplica a operadoras de transporte terrestre comercial (excepto taxis):
-- el XML de la factura debe llevar <placa> entre <moneda> y <pagos>, con la
-- placa del vehículo con el que se prestó el servicio (formato ABC1234, sin
-- espacios; cero adelante si son 3 dígitos). Obligatorio a los 90 días de la
-- publicación en el Registro Oficial.
--
-- factura_operadora_transporte: marca en la EMPRESA (tabla empresas). La define
-- el superadmin al crear/editar la empresa en config/empresas-sistema. Apagado =
-- comportamiento actual, sin campo placa.
-- ============================================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS factura_operadora_transporte VARCHAR(10) DEFAULT 'false';

ALTER TABLE ventas_cabecera
    ADD COLUMN IF NOT EXISTS placa VARCHAR(20);

UPDATE empresas
   SET factura_operadora_transporte = COALESCE(factura_operadora_transporte, 'false');

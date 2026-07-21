-- ============================================================
-- MÓDULO DECLARACIÓN DE RETENCIONES EN LA FUENTE — FORMULARIO 103
-- ============================================================
-- 1) Mapeo código de retención SRI (retenciones_sri.codigo_ret) -> casillero
--    del Formulario 103. Es un catálogo GLOBAL (regla nacional del SRI,
--    no varía por empresa) por lo que se agrega directo a retenciones_sri.
--
--    Regla derivada del formulario oficial (ver "SRI GUIA TECNICA/formulario103.xls",
--    Resolución NAC-DGERCGC16-00000125): cada concepto tiene un casillero
--    "base imponible" (3xx / 4xx) y su casillero "valor retenido" es siempre
--    base + 50, salvo los conceptos exentos/no sujetos a retención que no
--    tienen columna de valor en el formulario (331, 332, 405, 412, 416, 423, 433).
-- ============================================================

ALTER TABLE retenciones_sri ADD COLUMN IF NOT EXISTS casillero_base  VARCHAR(6);
ALTER TABLE retenciones_sri ADD COLUMN IF NOT EXISTS casillero_valor VARCHAR(6);

-- Base = los primeros dígitos de codigo_ret (antes de la primera letra),
-- truncado a 3 caracteres (cubre los códigos de 4 dígitos tipo 3120/3140/3430/3440,
-- que son variantes específicas del código base 312/314/343/344).
UPDATE retenciones_sri
SET casillero_base = LEFT(regexp_replace(codigo_ret, '[^0-9].*$', ''), 3)
WHERE impuesto_ret = 'RENTA'
  AND codigo_ret ~ '^[0-9]';

-- Valor retenido = casillero_base + 50, excepto los conceptos sin columna
-- de valor en el formulario (exentos / no sujetos a retención).
UPDATE retenciones_sri
SET casillero_valor = (casillero_base::int + 50)::text
WHERE impuesto_ret = 'RENTA'
  AND casillero_base IS NOT NULL
  AND casillero_base ~ '^[0-9]+$'
  AND casillero_base NOT IN ('331','332','405','412','416','423','433');

-- Códigos fuera del rango vigente del formulario (sin casillero conocido):
-- se anulan explícitamente para que el módulo los marque como "sin mapeo"
-- en vez de apuntar a un casillero inexistente.
UPDATE retenciones_sri SET casillero_base = NULL, casillero_valor = NULL
WHERE codigo_ret IN ('504');

COMMENT ON COLUMN retenciones_sri.casillero_base IS 'Casillero de base imponible en el Formulario 103 del SRI (ej. 303, 304, 411)';
COMMENT ON COLUMN retenciones_sri.casillero_valor IS 'Casillero de valor retenido en el Formulario 103 del SRI (ej. 353, 354, 461); NULL si el concepto no tiene retención (exento/no sujeto)';

-- ============================================================================
-- Notas de crédito: vendedor (asesor) propio en la cabecera.
--
-- Antes la NC no guardaba vendedor: el Reporte de Ventas y el Reporte de Ventas
-- por Asesor lo deducían de la factura que modifica. Ahora el modal de la NC
-- propone el vendedor del cliente (o el de la factura, si se crea desde ella),
-- el usuario puede cambiarlo, y los dos reportes filtran por el de la NC.
--
-- El código funciona con o sin esta columna (columnaExiste), pero conviene
-- aplicar este SQL ANTES de desplegar: en cuanto la columna existe los reportes
-- pasan a usarla, así que el relleno de abajo debe correr en la misma pasada.
--
-- Idempotente: se puede ejecutar más de una vez (solo rellena las NC sin vendedor).
-- ============================================================================

BEGIN;

ALTER TABLE notas_credito_cabecera
    ADD COLUMN IF NOT EXISTS id_vendedor integer;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_nc_vendedor') THEN
        ALTER TABLE notas_credito_cabecera
            ADD CONSTRAINT fk_nc_vendedor FOREIGN KEY (id_vendedor) REFERENCES vendedores (id);
    END IF;
END $$;

-- 1) Vendedor de la factura que modifica la NC (lo que usaban hasta hoy los reportes).
--    El número se compara normalizado a 15 dígitos (001-001-13 = 001001000000013),
--    igual que AbonosVentaSql::normalizar(). Si hay más de una factura con ese número,
--    manda la del mismo ambiente y luego la más reciente.
UPDATE notas_credito_cabecera nc
SET id_vendedor = f.id_vendedor
FROM (
    SELECT DISTINCT ON (n.id) n.id AS id_nc, v.id_vendedor
    FROM notas_credito_cabecera n
    JOIN ventas_cabecera v
      ON v.id_empresa = n.id_empresa
     AND v.eliminado = false
     AND COALESCE(v.id_vendedor, 0) <> 0
     AND lpad(regexp_replace(COALESCE(v.establecimiento, ''), '[^0-9]', '', 'g'), 3, '0')
      || lpad(regexp_replace(COALESCE(v.punto_emision, ''),   '[^0-9]', '', 'g'), 3, '0')
      || lpad(regexp_replace(COALESCE(v.secuencial, ''),      '[^0-9]', '', 'g'), 9, '0')
       = (CASE WHEN COALESCE(n.num_doc_modificado, '') LIKE '%-%-%'
               THEN lpad(regexp_replace(split_part(n.num_doc_modificado, '-', 1), '[^0-9]', '', 'g'), 3, '0')
                 || lpad(regexp_replace(split_part(n.num_doc_modificado, '-', 2), '[^0-9]', '', 'g'), 3, '0')
                 || lpad(regexp_replace(split_part(n.num_doc_modificado, '-', 3), '[^0-9]', '', 'g'), 9, '0')
               ELSE regexp_replace(COALESCE(n.num_doc_modificado, ''), '[^0-9]', '', 'g') END)
    JOIN vendedores vd ON vd.id = v.id_vendedor
    WHERE n.id_vendedor IS NULL
      AND COALESCE(n.cod_doc_modificado, '01') = '01'
    ORDER BY n.id,
             (v.tipo_ambiente::text = n.tipo_ambiente::text) DESC,
             v.fecha_emision DESC, v.id DESC
) f
WHERE nc.id = f.id_nc
  AND nc.id_vendedor IS NULL;

-- 2) Las que siguen sin vendedor (factura sin vendedor, saldo inicial, número manual
--    que no está en el sistema): el vendedor asignado hoy al cliente de la NC.
UPDATE notas_credito_cabecera nc
SET id_vendedor = c.id_vendedor
FROM clientes c
JOIN vendedores vd ON vd.id = c.id_vendedor
WHERE c.id = nc.id_cliente
  AND c.id_empresa = nc.id_empresa
  AND COALESCE(c.id_vendedor, 0) <> 0
  AND nc.id_vendedor IS NULL;

COMMIT;

-- Verificación: cuántas NC quedaron con y sin vendedor.
SELECT (id_vendedor IS NOT NULL) AS con_vendedor, count(*)
FROM notas_credito_cabecera
WHERE eliminado = false
GROUP BY 1;

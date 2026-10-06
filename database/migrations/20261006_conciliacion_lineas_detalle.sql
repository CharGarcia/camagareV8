-- =====================================================================================
-- Conciliación de Cobros: documentos asignados a cada línea del extracto.
--
-- Una línea del banco (un depósito) ya NO se divide en partes: conserva su monto tal como
-- vino en el archivo y aquí se guardan los documentos que la completan (uno o varios, de uno
-- o varios clientes). Al generar, cada línea confirmada crea UN solo ingreso con todos sus
-- documentos y un solo pago.
--
-- conciliacion_lineas.id_cliente_sugerido / tipo_documento_sugerido / id_documento_sugerido
-- quedan como espejo del PRIMER detalle (compatibilidad y lectura rápida);
-- conciliacion_lineas.monto_aplicar pasa a ser la SUMA de los detalles.
--
-- Idempotente. Aplicar ANTES de desplegar el código que la usa.
-- =====================================================================================

CREATE TABLE IF NOT EXISTS conciliacion_lineas_detalle (
    id               SERIAL PRIMARY KEY,
    id_linea         INTEGER NOT NULL REFERENCES conciliacion_lineas(id),
    id_empresa       INTEGER NOT NULL REFERENCES empresas(id),
    id_cliente       INTEGER NOT NULL REFERENCES clientes(id),
    tipo_documento   VARCHAR(20) NOT NULL CHECK (tipo_documento IN ('FACTURA', 'SALDO_INICIAL', 'RECIBO')),
    id_documento     INTEGER NOT NULL,              -- id polimórfico (ventas_cabecera / saldos_iniciales_cxc / recibos_venta_cabecera)
    numero_documento VARCHAR(50) NULL,              -- copia para mostrar aunque el documento ya no esté pendiente
    monto_aplicar    NUMERIC(14,2) NOT NULL,
    orden            SMALLINT NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_by INTEGER,
    eliminado  BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at TIMESTAMP,
    deleted_by INTEGER
);

COMMENT ON TABLE conciliacion_lineas_detalle IS
    'Documentos (y cliente de cada uno) con los que se completa una línea del extracto bancario en Conciliación de Cobros. Una línea confirmada genera un solo ingreso con todos ellos.';

CREATE INDEX IF NOT EXISTS idx_concdet_linea
    ON conciliacion_lineas_detalle (id_linea)
 WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS idx_concdet_documento
    ON conciliacion_lineas_detalle (id_empresa, tipo_documento, id_documento)
 WHERE eliminado = FALSE;

-- Líneas ya existentes: su documento sugerido/confirmado pasa a ser su único detalle.
INSERT INTO conciliacion_lineas_detalle (id_linea, id_empresa, id_cliente, tipo_documento, id_documento, monto_aplicar, orden, created_by, updated_by)
SELECT l.id, l.id_empresa, l.id_cliente_sugerido, l.tipo_documento_sugerido, l.id_documento_sugerido,
       COALESCE(l.monto_aplicar, l.monto), 0, l.created_by, l.created_by
FROM conciliacion_lineas l
WHERE l.eliminado = FALSE
  AND l.id_cliente_sugerido IS NOT NULL
  AND l.tipo_documento_sugerido IS NOT NULL
  AND l.id_documento_sugerido IS NOT NULL
  -- (defensivo: datos de desarrollo con empresa/cliente huérfanos no deben frenar la migración)
  AND EXISTS (SELECT 1 FROM empresas e WHERE e.id = l.id_empresa)
  AND EXISTS (SELECT 1 FROM clientes c WHERE c.id = l.id_cliente_sugerido)
  AND NOT EXISTS (SELECT 1 FROM conciliacion_lineas_detalle d WHERE d.id_linea = l.id);

-- Comprobación (opcional): debe devolver la tabla y, por cada línea con documento, un detalle.
-- SELECT COUNT(*) AS lineas_con_documento FROM conciliacion_lineas WHERE eliminado = FALSE AND id_documento_sugerido IS NOT NULL;
-- SELECT COUNT(*) AS detalles FROM conciliacion_lineas_detalle WHERE eliminado = FALSE;

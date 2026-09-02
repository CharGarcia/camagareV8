-- ============================================================================
-- Impresión de órdenes de cocina/barra
--
-- Hasta ahora `estaciones_impresion` solo enrutaba ítems al KDS (la pantalla):
-- el nombre hablaba de impresión pero no había nada que imprimiera. Esto le
-- agrega la configuración de la impresora de cada estación y una cola de
-- impresión.
--
-- Cómo imprime (importante para entender la cola): el servidor está fuera de la
-- red del restaurante, así que no puede hablarle a la impresora. Quien imprime
-- es el propio navegador que ya tiene abierto el KDS de esa estación: en su
-- poll recoge lo pendiente, lo manda a la impresora conectada a ESE equipo y
-- marca la fila como impresa. Por eso hace falta una tabla y no basta con un
-- disparo en caliente: si la tablet estaba apagada o sin red, la orden sigue
-- pendiente y sale cuando vuelve, sin duplicarse ni perderse.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Configuración de impresora por estación
-- ---------------------------------------------------------------------------
-- imprime_ordenes = FALSE por defecto: quien ya usa el KDS solo con pantalla no
-- empieza a imprimir de golpe tras el despliegue. Se activa a mano por estación.
ALTER TABLE estaciones_impresion
    ADD COLUMN IF NOT EXISTS imprime_ordenes BOOLEAN  NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS imprimir_auto   BOOLEAN  NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS ancho_papel     SMALLINT NOT NULL DEFAULT 80,
    ADD COLUMN IF NOT EXISTS copias          SMALLINT NOT NULL DEFAULT 1;

COMMENT ON COLUMN estaciones_impresion.imprime_ordenes IS 'La estación tiene impresora: las órdenes se encolan para ella.';
COMMENT ON COLUMN estaciones_impresion.imprimir_auto   IS 'TRUE: sale sola al enviar a cocina. FALSE: solo cuando alguien la pide desde la comanda o el KDS.';
COMMENT ON COLUMN estaciones_impresion.ancho_papel     IS 'Ancho del papel en mm (58 u 80). Ajusta el tamaño de letra del ticket.';
COMMENT ON COLUMN estaciones_impresion.copias          IS 'Cuántas veces se imprime cada orden (ej. 2: una para la línea de preparación y otra para el pase).';

-- Postgres no admite ADD CONSTRAINT IF NOT EXISTS: se envuelve para que el
-- script se pueda volver a correr sin abortar.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_estaciones_ancho_papel') THEN
        ALTER TABLE estaciones_impresion ADD CONSTRAINT chk_estaciones_ancho_papel CHECK (ancho_papel IN (58, 80));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_estaciones_copias') THEN
        ALTER TABLE estaciones_impresion ADD CONSTRAINT chk_estaciones_copias CHECK (copias BETWEEN 1 AND 5);
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 2. Cola / registro de impresiones
-- ---------------------------------------------------------------------------
-- Una fila = un ticket. `ids_lineas` congela QUÉ salió en ese papel: si después
-- se agregan más ítems a la misma comanda, esos van en un ticket nuevo, no se
-- mezclan con el ya impreso.
CREATE TABLE IF NOT EXISTS comandas_impresiones (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER NOT NULL,
    id_comanda     INTEGER NOT NULL REFERENCES comandas(id),
    id_estacion    INTEGER NOT NULL REFERENCES estaciones_impresion(id),
    ids_lineas     TEXT    NOT NULL,
    estado         VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    es_reimpresion BOOLEAN NOT NULL DEFAULT FALSE,
    impreso_at     TIMESTAMP,
    impreso_by     INTEGER,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    eliminado      BOOLEAN DEFAULT FALSE,
    deleted_at     TIMESTAMP,
    deleted_by     INTEGER,
    CONSTRAINT chk_comandas_impresiones_estado CHECK (estado IN ('pendiente', 'impreso', 'anulado'))
);

COMMENT ON TABLE  comandas_impresiones     IS 'Cola de órdenes de cocina/barra por imprimir; el KDS de cada estación la consume.';
COMMENT ON COLUMN comandas_impresiones.ids_lineas     IS 'Ids de comanda_detalle incluidos en ESTE ticket, separados por coma.';
COMMENT ON COLUMN comandas_impresiones.es_reimpresion IS 'TRUE cuando alguien volvió a pedir el ticket; se imprime marcado como COPIA.';

-- El índice que sostiene el poll del KDS (lo pendiente de una estación).
CREATE INDEX IF NOT EXISTS idx_comandas_impresiones_cola
    ON comandas_impresiones (id_empresa, id_estacion, estado, id)
    WHERE eliminado = false;

CREATE INDEX IF NOT EXISTS idx_comandas_impresiones_comanda
    ON comandas_impresiones (id_comanda)
    WHERE eliminado = false;

-- ============================================================================
-- POS Restaurantes — División de cuenta en "partes iguales"
--
-- A diferencia del split "por ítems" (una línea completa va a un solo grupo
-- de cobro), "partes iguales" reparte PROPORCIONALMENTE cada línea entre N
-- cuentas (ej. 1 hamburguesa entre 2 personas = 0.5 c/u en cada documento) —
-- el monto y el inventario quedan exactos, y cada documento (Factura o
-- Recibo) muestra el detalle real de productos, no un ítem genérico.
--
-- Se crean N filas en comanda_grupos_cobro (una por "parte"), todas
-- compartiendo el mismo "pool" de líneas vía comanda_grupo_partes_lineas —
-- una línea no puede pertenecer a un solo id_grupo_cobro cuando se reparte
-- entre varias partes, por eso la relación es a través de esta tabla nueva
-- en vez de comanda_detalle.id_grupo_cobro (que sigue usándose tal cual
-- para el split "por ítems").
-- ============================================================================

ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS id_grupo_padre INTEGER REFERENCES comanda_grupos_cobro(id);
ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS numero_parte INTEGER;
ALTER TABLE comanda_grupos_cobro ADD COLUMN IF NOT EXISTS total_partes INTEGER;

CREATE TABLE IF NOT EXISTS comanda_grupo_partes_lineas (
    id              SERIAL PRIMARY KEY,
    id_grupo_raiz   INTEGER NOT NULL REFERENCES comanda_grupos_cobro(id) ON DELETE CASCADE,
    id_linea        INTEGER NOT NULL REFERENCES comanda_detalle(id) ON DELETE CASCADE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cgpl_grupo_raiz ON comanda_grupo_partes_lineas (id_grupo_raiz);
CREATE INDEX IF NOT EXISTS idx_cgpl_linea ON comanda_grupo_partes_lineas (id_linea);

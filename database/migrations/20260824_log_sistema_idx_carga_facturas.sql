-- ---------------------------------------------------------------------------
-- Índice de apoyo para el control de cargas masivas de facturas repetidas.
--
-- El módulo Carga de Facturas (modulos/carga-facturas) impide aplicar dos veces
-- el mismo archivo Excel. Para saberlo consulta log_sistema buscando la entrada
-- 'CARGA_MASIVA_FACTURAS' que lleva el hash del archivo en datos_nuevos.
--
-- Sin este índice la consulta funciona igual, pero hace un recorrido completo de
-- log_sistema, que en producción crece sin límite. El índice es PARCIAL: solo
-- cubre las filas de esa acción, así que ocupa muy poco (una fila por carga
-- aplicada, no por factura).
--
-- Es opcional: aplicarlo cuando log_sistema empiece a ser grande.
--
-- IMPORTANTE: se crea CONCURRENTLY. Un CREATE INDEX normal bloquea las
-- ESCRITURAS de log_sistema mientras se construye, y esa tabla recibe una
-- inserción por cada acción del sistema: en una tabla grande dejaría a todos los
-- usuarios esperando. CONCURRENTLY tarda más pero no bloquea a nadie.
--
-- Por eso este archivo NO puede ejecutarse dentro de una transacción (PostgreSQL
-- lo prohíbe con CONCURRENTLY). Con psql directo funciona; si algo lo envuelve
-- en BEGIN/COMMIT, fallará con "cannot run inside a transaction block".
--
-- Si la creación se interrumpe, PostgreSQL deja un índice marcado como INVALID.
-- Se limpia con:  DROP INDEX IF EXISTS idx_log_sistema_carga_facturas;
-- y se vuelve a lanzar.
-- ---------------------------------------------------------------------------

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_log_sistema_carga_facturas
    ON log_sistema (id_empresa, (datos_nuevos ->> 'hash_archivo'))
    WHERE accion = 'CARGA_MASIVA_FACTURAS';

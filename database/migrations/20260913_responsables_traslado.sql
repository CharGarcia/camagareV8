-- ════════════════════════════════════════════════════════════════════════════
-- Módulo: Responsables de Traslado  (modulos/responsables-traslados)
-- Fecha: 2026-09-13
--
-- La tabla `responsables_traslado` ya existía desde el módulo de Pedidos
-- (database/modulos_pedidos_update_3.sql), pero hasta ahora no tenía módulo
-- propio: los responsables solo se podían CREAR al vuelo desde el modal de
-- Pedidos / Consignaciones, nunca listar, editar ni eliminar. Este script deja
-- la tabla completa y consistente en cualquier instalación.
--
-- IDEMPOTENTE: se puede ejecutar varias veces sin romper nada.
-- NO DESTRUCTIVO: no borra ni modifica datos existentes (solo rellena NULLs).
-- ════════════════════════════════════════════════════════════════════════════

-- 1) Tabla (por si la instalación nunca corrió modulos_pedidos_update_3.sql)
CREATE TABLE IF NOT EXISTS responsables_traslado (
    id             SERIAL PRIMARY KEY,
    id_empresa     INTEGER NOT NULL REFERENCES empresas(id),
    nombre         VARCHAR(100) NOT NULL,
    identificacion VARCHAR(20),
    telefono       VARCHAR(20),
    email          VARCHAR(150),
    estado         VARCHAR(20) DEFAULT 'activo',
    created_at     TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_by     INTEGER,
    updated_by     INTEGER,
    eliminado      BOOLEAN DEFAULT FALSE,
    deleted_at     TIMESTAMP WITHOUT TIME ZONE,
    deleted_by     INTEGER
);

-- 2) `email` se añadió a mano en desarrollo y quedó fuera del SQL versionado:
--    el INSERT de PedidosController/ConsignacionesVentasController ya la escribe,
--    así que sin esta columna esos modales fallan.
ALTER TABLE responsables_traslado ADD COLUMN IF NOT EXISTS email VARCHAR(150);

-- 3) Normalizar filas viejas: `eliminado`/`estado` en NULL rompen los filtros
--    (`eliminado = false` no es cierto para NULL, y la fila se vuelve invisible).
UPDATE responsables_traslado SET eliminado = FALSE WHERE eliminado IS NULL;
UPDATE responsables_traslado SET estado = 'activo' WHERE estado IS NULL OR TRIM(estado) = '';

ALTER TABLE responsables_traslado ALTER COLUMN eliminado SET DEFAULT FALSE;
ALTER TABLE responsables_traslado ALTER COLUMN eliminado SET NOT NULL;

-- 4) Índices del listado (id_empresa + eliminado es el WHERE de TODA consulta
--    del módulo, vía BaseRepository::getBaseWhere).
CREATE INDEX IF NOT EXISTS idx_resp_traslado_empresa
    ON responsables_traslado (id_empresa)
    WHERE eliminado = FALSE;

CREATE INDEX IF NOT EXISTS idx_resp_traslado_nombre
    ON responsables_traslado (id_empresa, nombre)
    WHERE eliminado = FALSE;

-- 5) Identificación única POR EMPRESA, sin contar eliminados ni vacíos.
--    El filtro `eliminado = FALSE` es obligatorio: sin él, borrar un responsable
--    y volver a crearlo con la misma cédula chocaría contra un registro que el
--    usuario ya no ve (mismo problema que tuvo `clientes`).
--    Las filas con identificación NULL o '' quedan fuera del índice: el campo es
--    opcional y hay responsables antiguos creados solo con nombre.
--
--    Si en esta base ya hay identificaciones repetidas, el índice NO se crea y el
--    script avisa en la pestaña "Messages" en vez de fallar: la validación del
--    Service ya impide crear nuevos duplicados, y estos se limpian a mano.
DO $$
DECLARE
    v_dups integer;
    v_lista text;
BEGIN
    SELECT COUNT(*), COALESCE(string_agg(t.detalle, ' | '), '')
      INTO v_dups, v_lista
      FROM (
            SELECT 'empresa ' || id_empresa || ' / ident ' || identificacion
                   || ' (x' || COUNT(*) || ')' AS detalle
              FROM responsables_traslado
             WHERE eliminado = FALSE AND identificacion IS NOT NULL AND identificacion <> ''
             GROUP BY id_empresa, identificacion
            HAVING COUNT(*) > 1
           ) t;

    IF v_dups > 0 THEN
        RAISE NOTICE 'OMITIDO uq_resp_traslado_identificacion: hay % identificacion(es) repetida(s): %', v_dups, v_lista;
        RAISE NOTICE 'Corrija esos registros en el modulo y vuelva a ejecutar este script para crear el indice.';
    ELSE
        CREATE UNIQUE INDEX IF NOT EXISTS uq_resp_traslado_identificacion
            ON responsables_traslado (id_empresa, identificacion)
            WHERE eliminado = FALSE AND identificacion IS NOT NULL AND identificacion <> '';
        RAISE NOTICE 'Indice unico de identificacion: OK';
    END IF;
END $$;

-- 6) Vínculo usuario ↔ responsable (usuarios_responsables_traslado): ya tenía
--    índice por id_usuario ("¿qué responsables representa este usuario?"), que es
--    como lo consulta la app móvil. El módulo pregunta al revés —"¿qué usuarios
--    representan a este responsable?"— tanto en la pestaña del modal como en la
--    columna "Usuarios" del listado, así que hace falta el índice por el otro extremo.
--
--    La tabla la crea 20260718_api_movil_auth.sql. Si esta instalación todavía no
--    la tiene, se omite el índice en lugar de abortar el script entero.
DO $$
BEGIN
    IF to_regclass('public.usuarios_responsables_traslado') IS NULL THEN
        RAISE NOTICE 'OMITIDO idx_usu_resp_traslado_responsable: falta la tabla usuarios_responsables_traslado (ejecute antes 20260718_api_movil_auth.sql).';
    ELSE
        CREATE INDEX IF NOT EXISTS idx_usu_resp_traslado_responsable
            ON usuarios_responsables_traslado (id_responsable_traslado, id_empresa)
            WHERE eliminado = FALSE;
        RAISE NOTICE 'Indice del vinculo usuario-responsable: OK';
    END IF;
END $$;

-- ── Verificación ────────────────────────────────────────────────────────────
-- Deja a la vista el estado final: columnas, índices creados y el submódulo del
-- menú. El submódulo (id 9, "Responsables de traslados", módulo Ventas) YA existe;
-- lo único que falta después de este script es asignar permisos en
-- /config/permisos-modulos.
SELECT 'columna email'                         AS verificacion,
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns
                          WHERE table_name = 'responsables_traslado' AND column_name = 'email')
            THEN 'OK' ELSE 'FALTA' END         AS resultado
UNION ALL
SELECT 'indices de responsables_traslado',
       COUNT(*)::text || ' de 3'
  FROM pg_indexes
 WHERE tablename = 'responsables_traslado'
   AND indexname IN ('idx_resp_traslado_empresa', 'idx_resp_traslado_nombre', 'uq_resp_traslado_identificacion')
UNION ALL
SELECT 'indice del vinculo por responsable',
       CASE WHEN EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'idx_usu_resp_traslado_responsable')
            THEN 'OK' ELSE 'FALTA' END
UNION ALL
SELECT 'submodulo del menu (ruta)',
       COALESCE((SELECT ruta FROM submodulos_menu WHERE id = 9), 'NO EXISTE')
UNION ALL
SELECT 'responsables registrados',
       COUNT(*)::text
  FROM responsables_traslado
 WHERE eliminado = FALSE;

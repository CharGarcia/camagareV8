-- ============================================================================
-- DIAGNÓSTICO (solo lectura): errores "value too long" en producción
-- ----------------------------------------------------------------------------
-- Para qué:
--   El 21-09-2026 aparecieron varios SQLSTATE[22001] al guardar (ingresos,
--   sincronización de casilleros de la Declaración de IVA, facturas de venta).
--   El mensaje de PostgreSQL dice el LARGO de la columna pero no su nombre, así
--   que este script cruza los errores registrados con el esquema real de esa
--   base para dejar la columna culpable a la vista.
--
--   NO modifica nada: son tres SELECT. Se puede ejecutar en cualquier momento.
--
-- Cómo: Query Tool de pgAdmin -> pegar todo -> F5. Salen 3 resultados en
--   pestañas separadas (botón "Data Output" / flechas de resultado).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) Los últimos 30 errores de truncado, con el punto exacto del código.
--    La columna `largo_columna` es el número que hay que mirar: dice de qué
--    tamaño era la columna que rechazó el dato.
-- ----------------------------------------------------------------------------
SELECT e.created_at,
       e.sql_state,
       substring(e.mensaje FROM 'character varying\(([0-9]+)\)') AS largo_columna,
       e.mensaje,
       e.archivo,
       e.linea,
       e.ruta,
       e.accion,
       e.id_empresa,
       e.id_usuario
  FROM errores_sistema e
 WHERE e.sql_state = '22001'
    OR e.mensaje ILIKE '%too long%'
    OR e.mensaje ILIKE '%demasiado largo%'
 ORDER BY e.created_at DESC
 LIMIT 30;

-- ----------------------------------------------------------------------------
-- 2) Esquema real de las tablas implicadas en esas trazas: qué columnas de
--    texto tienen y de qué largo. Cruzando el `largo_columna` del paso 1 con
--    esta lista sale la columna (si hay una sola de ese largo, es esa).
-- ----------------------------------------------------------------------------
SELECT c.table_name,
       c.column_name,
       c.data_type,
       c.character_maximum_length AS largo
  FROM information_schema.columns c
 WHERE c.table_schema = 'public'
   AND c.table_name IN ('ventas_cabecera', 'ventas_detalle', 'ventas_pagos',
                        'casilleros_declaracion_sri',
                        'ingresos_cabecera', 'ingresos_pagos', 'ingresos_detalle')
   AND c.data_type IN ('character varying', 'character')
 ORDER BY c.table_name, c.character_maximum_length, c.column_name;

-- ----------------------------------------------------------------------------
-- 3) Diferencias con desarrollo que ya conocemos y conviene confirmar aquí:
--    en desarrollo `casilleros_declaracion_sri.concepto` es TEXT (sin límite).
--    Si en esta base es varchar, ese es el origen del 22001 de la Declaración
--    de IVA, y ampliarla a text lo cierra del todo (ver el ALTER comentado).
-- ----------------------------------------------------------------------------
SELECT c.column_name,
       c.data_type,
       c.character_maximum_length AS largo,
       CASE WHEN c.data_type = 'text' THEN 'igual que desarrollo'
            ELSE 'DISTINTO de desarrollo (allí es text)' END AS comparacion
  FROM information_schema.columns c
 WHERE c.table_schema = 'public'
   AND c.table_name  = 'casilleros_declaracion_sri'
   AND c.column_name = 'concepto';

-- ----------------------------------------------------------------------------
-- OPCIONAL, solo si el paso 3 dice que `concepto` es varchar en esta base:
-- igualarla a desarrollo. Ampliar a text no reescribe la tabla ni toca datos.
-- (Con el código nuevo tampoco hace falta —el concepto ya se capa al largo de
--  la columna—, pero deja de recortarse la descripción de los ítems largos.)
-- ----------------------------------------------------------------------------
-- ALTER TABLE casilleros_declaracion_sri ALTER COLUMN concepto TYPE text;

-- ============================================================================
-- 4) Datos YA guardados que seguirían rompiendo la emisión
-- ----------------------------------------------------------------------------
--   Los 22001 de varchar(5) y varchar(8) del 24-08 y el 28-07 (facturas de
--   venta y empresas) los provocaba la configuración del emisor: `tipo_emision`
--   con un texto ("Normal") en vez del código "1", o `agente_retencion` con el
--   texto de la resolución en vez de su número. El código ya lo valida al
--   guardar (EmpresaService::saveEmisor), pero una empresa que quedó con el
--   valor malo guardado ANTES de esa validación sigue reventando al emitir.
--   Esta consulta las lista: lo que salga aquí hay que corregirlo en
--   Empresa -> Configuración del emisor (o con el UPDATE comentado abajo).
-- ============================================================================
SELECT e.id,
       e.nombre,
       e.ruc,
       e.tipo_ambiente,
       e.tipo_emision,
       e.agente_retencion,
       e.resolucion_contribuyente,
       CASE
           WHEN COALESCE(e.tipo_emision, '1') <> '1'                       THEN 'tipo_emision debe ser "1"'
           WHEN COALESCE(e.agente_retencion, '') !~ '^[0-9]{0,8}$'         THEN 'agente_retencion debe ser solo dígitos (máx. 8) o vacío'
           WHEN COALESCE(e.tipo_ambiente::text, '1') NOT IN ('1', '2')     THEN 'tipo_ambiente debe ser 1 (pruebas) o 2 (producción)'
       END AS problema
  FROM empresas e
 WHERE COALESCE(e.eliminado, false) = false
   AND (   COALESCE(e.tipo_emision, '1') <> '1'
        OR COALESCE(e.agente_retencion, '') !~ '^[0-9]{0,8}$'
        OR COALESCE(e.tipo_ambiente::text, '1') NOT IN ('1', '2'))
 ORDER BY e.id;

-- Corrección puntual, con el id que haya salido arriba (revisar antes de correr):
-- UPDATE empresas SET tipo_emision = '1'      WHERE id = <id>;
-- UPDATE empresas SET agente_retencion = ''   WHERE id = <id>;   -- vacío = no es agente
-- (si sí es agente de retención, poner solo el número de la resolución, máx. 8 dígitos)

-- ============================================================================
-- 5) El error de la Declaración de IVA NO salió en el paso 1: no es un 22001.
--    Esta consulta trae sus últimos errores, sea cual sea el código, para ver
--    el mensaje real de `DeclaracionIvaRepository::insertarCasilleroDeclaracion`.
-- ============================================================================
SELECT e.created_at, e.sql_state, e.clase, e.mensaje, e.archivo, e.linea, e.accion, e.id_empresa
  FROM errores_sistema e
 WHERE e.archivo ILIKE '%DeclaracionIva%'
    OR e.accion  ILIKE '%generar%'
 ORDER BY e.created_at DESC
 LIMIT 20;

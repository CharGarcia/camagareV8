-- =============================================================================
-- Compras: corrige el campo «Deducible» de las compras que quedaron con el código
-- crudo del sistema anterior ('04' / '05') en vez del valor que usa el sistema.
--
-- Origen   : una versión antigua del migrador desde MySQL copiaba `deducible_en` tal
--            cual. Hoy el migrador ya traduce (MigracionMysqlService: '05' = gasto
--            personal, cualquier otro = deducible IVA), así que no se vuelve a generar.
-- Qué hace : '04' → 'declaracion_iva' y '05' → 'gasto_personal'. Deja un registro por
--            compra en log_sistema (acción CORRECCION_DATOS, antes/después).
-- Toca datos: SÍ. En local eran 331 compras de 2020-02 a 2021-03, todas '04'.
-- Efecto    : esas compras pasan a contar como «Declaración de IVA» (crédito tributario)
--            y como costos y gastos del negocio en la Declaración de Renta. Las
--            declaraciones de IVA ya GUARDADAS no cambian (son una foto); solo cambiaría
--            una declaración de esos meses si se vuelve a sincronizar.
-- Reversible: sí, con log_sistema:
--            UPDATE compras_cabecera c SET deducible = l.datos_anteriores->>'deducible'
--            FROM log_sistema l
--            WHERE l.accion = 'CORRECCION_DATOS' AND l.tabla_afectada = 'compras_cabecera'
--              AND l.datos_nuevos->>'motivo' = 'deducible_codigo_legado' AND c.id = l.id_registro;
-- Idempotente: sí (la segunda vez no encuentra filas). Listo para pgAdmin (F5).
-- =============================================================================

-- Antes (opcional): cuántas hay por empresa y valor
-- SELECT id_empresa, deducible, COUNT(*), MIN(fecha_emision), MAX(fecha_emision)
-- FROM compras_cabecera WHERE deducible IN ('04', '05') GROUP BY 1, 2 ORDER BY 1, 2;

WITH afectadas AS (
    SELECT id, id_empresa, deducible AS antes
    FROM compras_cabecera
    WHERE deducible IN ('04', '05')
    FOR UPDATE
),
corregidas AS (
    UPDATE compras_cabecera c
       SET deducible  = CASE a.antes WHEN '05' THEN 'gasto_personal' ELSE 'declaracion_iva' END,
           updated_at = NOW()
      FROM afectadas a
     WHERE c.id = a.id
    RETURNING c.id, c.id_empresa, a.antes, c.deducible AS despues
)
INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, id_registro,
                         datos_anteriores, datos_nuevos, ip_usuario, user_agent, created_at)
SELECT NULL, id_empresa, 'CORRECCION_DATOS', 'compras_cabecera', id,
       jsonb_build_object('deducible', antes),
       jsonb_build_object('deducible', despues, 'motivo', 'deducible_codigo_legado'),
       NULL, 'SQL 20261008_compras_deducible_codigos_legados', NOW()
FROM corregidas;

-- Comprobación (debe salir 0):
-- SELECT COUNT(*) FROM compras_cabecera WHERE deducible IN ('04', '05');
-- Auditoría (debe coincidir con las filas corregidas):
-- SELECT COUNT(*) FROM log_sistema WHERE accion = 'CORRECCION_DATOS'
--   AND datos_nuevos->>'motivo' = 'deducible_codigo_legado';

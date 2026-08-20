-- MIGRATION: Videollamadas — latido de la sala (cierre automático por inactividad)
-- ---------------------------------------------------------------------------
-- PROBLEMA: una reunión se quedaba en estado 'en_curso' para siempre cuando
-- nadie la finalizaba a mano. Basta con que el último participante cierre la
-- pestaña (o se quede sin internet) para que no se dispare `salirAjax` y la
-- sala quede colgada, ocupando el listado y contando como reunión activa.
--
-- POR QUÉ HACE FALTA UNA COLUMNA NUEVA: la presencia de los participantes vive
-- solo en memoria (APCu, ver SenalizacionService), con un TTL de 25 segundos.
-- El cron corre en CLI, que tiene su propio segmento de memoria y NO ve esa
-- presencia. Tampoco sirve `participantes.ultima_conexion`: esa marca es el
-- INICIO del tramo de conexión (la referencia para calcular la permanencia), no
-- se refresca en cada poll, así que una reunión viva de más de 10 minutos
-- parecería abandonada.
--
-- `ultimo_latido` es la única marca en base de datos de "aquí dentro todavía
-- hay alguien". La escribe el poll de señalización, pero con freno: como mucho
-- una vez por minuto y por SALA (no por participante), para no cargar la base
-- gestionada, que es el recurso más escaso del sistema.
--
-- NULL significa "todavía nadie latió". El cron cae entonces a iniciada_at,
-- que es cuando la sala se puso en curso: así las reuniones que YA estaban
-- colgadas antes de este cambio también se cierran en la primera pasada.
--
-- ES UNA MIGRACIÓN PURAMENTE ADITIVA: agrega una columna anulable y un índice.
-- No modifica ni borra ningún dato existente.
-- ---------------------------------------------------------------------------
BEGIN;

ALTER TABLE videollamadas_salas
    ADD COLUMN IF NOT EXISTS ultimo_latido TIMESTAMP;

COMMENT ON COLUMN videollamadas_salas.ultimo_latido IS
    'Última señal de presencia dentro de la sala. La refresca el poll de señalización (máximo 1 vez por minuto y por sala). El cron cierra las reuniones en curso sin latido reciente.';

-- Índice parcial: el cron solo pregunta por las salas en curso, que son un
-- puñado frente al histórico. Un índice sobre toda la tabla sería desperdicio.
CREATE INDEX IF NOT EXISTS idx_vc_salas_latido
    ON videollamadas_salas (ultimo_latido)
    WHERE eliminado = FALSE AND estado = 'en_curso';

COMMIT;

-- ============================================================================
-- Accesos frecuentes por usuario (pantalla Inicio)
-- ----------------------------------------------------------------------------
-- Contexto:
--   Al ingresar al sistema, la pantalla Inicio muestra como íconos grandes los
--   submódulos que cada usuario usa más últimamente, en la empresa activa.
--   Cada vez que el usuario abre una pantalla completa (no las peticiones AJAX)
--   se suma su uso aquí (Controller::getLayoutData →
--   AccesosFrecuentesService::registrarVisita).
--
--   Criterio «frecuentes recientes»: "puntaje" decae a la mitad cada 7 días.
--   Al registrar un uso: puntaje = puntaje × 0,5^(días desde ultimo_uso / 7) + 1.
--   Al listar, se aplica el mismo decaimiento hasta hoy. Así 40 usos este mes
--   pesan más que 90 usos hace seis meses, con una sola fila por pantalla.
--   Abrir la misma pantalla varias veces en 5 minutos cuenta una sola vez.
--
--   Es una tabla de estadística de uso (como submodulos_vistos), no un
--   documento: no lleva eliminación lógica ni auditoría en log_sistema.
--
-- Qué hace:
--   Crea la tabla y su índice. NO modifica ni borra datos de otras tablas.
--   Mientras no se ejecute, el sistema funciona igual: la pantalla Inicio
--   simplemente no tiene accesos y se entra al dashboard como antes.
--
-- Reversible: sí — DROP TABLE IF EXISTS usuarios_submodulos_uso;
--   (solo se pierde el historial de uso; nada más depende de esta tabla)
--
-- Cómo ejecutarlo en pgAdmin: Query Tool → pegar todo → F5. Es instantáneo.
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios_submodulos_uso (
    id_usuario    integer          NOT NULL,
    id_empresa    integer          NOT NULL,
    id_submodulo  integer          NOT NULL,
    veces         integer          NOT NULL DEFAULT 1,
    puntaje       double precision NOT NULL DEFAULT 1,
    ultimo_uso    timestamp        NOT NULL DEFAULT now(),
    created_at    timestamp        NOT NULL DEFAULT now(),
    updated_at    timestamp        NOT NULL DEFAULT now(),
    CONSTRAINT usuarios_submodulos_uso_pkey PRIMARY KEY (id_usuario, id_empresa, id_submodulo)
);

COMMENT ON TABLE usuarios_submodulos_uso IS
    'Uso de cada submódulo por usuario y empresa: alimenta los accesos frecuentes de la pantalla Inicio.';
COMMENT ON COLUMN usuarios_submodulos_uso.puntaje IS
    'Usos con decaimiento: se reduce a la mitad cada 7 días desde ultimo_uso.';

-- La clave primaria ya sirve para leer los accesos de un usuario en una empresa
-- (id_usuario, id_empresa, …): no hace falta otro índice.

-- Comprobación (debe salir 1 fila con la tabla):
-- SELECT table_name FROM information_schema.tables WHERE table_name = 'usuarios_submodulos_uso';

-- Tarjeta en /config para la configuración de videollamadas.
--
-- Los servidores STUN/TURN son configuración de PLATAFORMA: los contrata y paga
-- CaMaGaRe una sola vez y los heredan todas las empresas. Por eso vive aquí y no
-- dentro del módulo de reuniones, que es operativo.
--
-- Solo visible para superadmin (nivel 3), igual que el resto de configuraciones
-- que guardan credenciales de servicios contratados.

DO $$
DECLARE
    v_id_opcion INTEGER;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM configuracion_opciones WHERE nombre = 'Videollamadas') THEN
        INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
        VALUES (
            'Videollamadas',
            'Servidores STUN/TURN que usan las reuniones y límites por empresa',
            'camera-video',
            'primary',
            3,
            (SELECT COALESCE(MAX(orden), 0) + 1 FROM configuracion_opciones),
            true
        )
        RETURNING id INTO v_id_opcion;

        INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
        VALUES (v_id_opcion, 'Configurar', '/config/videollamadas', 'outline-primary', 0);
    END IF;
END $$;

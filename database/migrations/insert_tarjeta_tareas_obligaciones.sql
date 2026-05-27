-- Insertar tarjeta "Tareas y Obligaciones" en el menu de Config

DO $$
DECLARE
    v_id INT;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM configuracion_opciones WHERE nombre = 'Tareas y Obligaciones') THEN
        INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, activo, orden, nivel_minimo)
        VALUES (
            'Tareas y Obligaciones',
            'Gestion de obligaciones tributarias y tareas asignadas a clientes',
            'bi-list-check',
            'success',
            true,
            99,
            1
        );

        v_id := currval('configuracion_opciones_id_seq');

        IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'configuracion_opcion_enlaces') THEN
            INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, icono, orden)
            VALUES (
                v_id,
                'Abrir modulo',
                '/config/tareas-obligaciones',
                'bi-arrow-right-circle',
                1
            );
        END IF;

        RAISE NOTICE 'Tarjeta creada con id=%', v_id;
    ELSE
        RAISE NOTICE 'La tarjeta ya existe.';
    END IF;
END $$;


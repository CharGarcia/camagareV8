-- Tarjeta en /config para acceder al mantenimiento de tramos de Impuesto a la Renta
-- (catálogo global usado por el motor de retención IR de empleados en relación de
-- dependencia, ver ImpuestoRentaEmpleadoService). Solo visible para superadmin (nivel 3).

DO $$
DECLARE
    v_id_opcion INTEGER;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM configuracion_opciones WHERE nombre = 'Impuesto a la Renta (Empleados)') THEN
        INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
        VALUES (
            'Impuesto a la Renta (Empleados)',
            'Tramos y tope de gasto personal para la retención de IR en relación de dependencia',
            'percent',
            'primary',
            3,
            (SELECT COALESCE(MAX(orden), 0) + 1 FROM configuracion_opciones),
            true
        )
        RETURNING id INTO v_id_opcion;

        INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
        VALUES (v_id_opcion, 'Configurar', '/config/impuesto-renta-tramos', 'outline-primary', 0);
    END IF;
END $$;

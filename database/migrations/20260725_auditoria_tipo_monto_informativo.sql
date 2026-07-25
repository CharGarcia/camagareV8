-- Agrega el tipo de hallazgo 'monto_informativo' a auditoria_contable_incidencias.
--
-- Contexto: hasta ahora, 4 orígenes (consignacion_venta, retorno_cv, cambio_producto_cv,
-- nomina) NO se auditaban por monto, porque su asiento legítimamente no iguala el total del
-- documento (la nómina incluye aportes patronales, los cambios de producto van a costo, las
-- consignaciones reclasifican inventario). Ahora sí se comparan, pero el hallazgo se marca
-- como 'monto_informativo' para que se pueda revisar sin contarse como error de cuadre.
--
-- Idempotente: se puede correr varias veces sin efecto adicional.

ALTER TABLE auditoria_contable_incidencias
    DROP CONSTRAINT IF EXISTS chk_aci_tipo;

ALTER TABLE auditoria_contable_incidencias
    ADD CONSTRAINT chk_aci_tipo CHECK (tipo_hallazgo IN (
        'faltante',
        'monto_no_coincide',
        'monto_informativo',
        'huerfano',
        'estado_incoherente',
        'ambiente_incoherente',
        'duplicado',
        'descuadrado',
        'cab_vs_detalle'
    ));

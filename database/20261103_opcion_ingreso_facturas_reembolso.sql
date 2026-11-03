-- Concepto de ingreso "Facturas de reembolso" (comportamiento FACTURA_REEMBOLSO) para las
-- empresas que ya existen. Las nuevas lo reciben de EmpresaInicializadorService.
-- Sin este concepto, Ingresos no muestra el botón para cobrar facturas de reembolso.
-- No lleva cuenta contable: el cobro acredita la "Cuentas por Cobrar Cliente" de
-- Configuración Contable → Factura de Reembolso (o, de respaldo, la de Ventas con Factura).
-- Idempotente: solo crea el concepto donde todavía no hay uno con ese comportamiento.

INSERT INTO empresa_opciones_ingreso_egreso (
    id_empresa, nombre, aplica_ingresos, aplica_egresos, comportamiento, estado, created_at, eliminado
)
SELECT e.id, 'Facturas de reembolso', true, false, 'FACTURA_REEMBOLSO', 'ACTIVO', CURRENT_TIMESTAMP, false
FROM empresas e
WHERE e.eliminado = false
  AND NOT EXISTS (
      SELECT 1 FROM empresa_opciones_ingreso_egreso o
      WHERE o.id_empresa = e.id
        AND o.comportamiento = 'FACTURA_REEMBOLSO'
        AND o.eliminado = false
  );

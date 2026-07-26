---
titulo: Anexo transaccional (ATS)
resumen: Genera el archivo XML del anexo transaccional del periodo, con su validación y un Excel de revisión.
categoria: Impuestos
ruta_modulo: modulos/anexo-ats
tipo: modulo
visibilidad: todos
etiquetas: ats, anexo transaccional, xml, dimm, declaracion, compras, ventas, anulados, sri
version: 1.0
orden: 30
estado: activo
---

El **anexo transaccional (ATS)** reporta al SRI el detalle de las transacciones
del periodo. Este módulo lo genera a partir de lo ya registrado en el sistema.

## Qué incluye

| Sección | De dónde sale |
|---------|---------------|
| Compras | Compras, liquidaciones de compra y retenciones emitidas |
| Ventas | Facturas de venta y demás comprobantes de venta |
| Anulados | Los comprobantes anulados del periodo |

## Qué no incluye

No cubre RECAP, fideicomisos ni rendimientos financieros. Si la empresa tiene ese
tipo de operaciones, esa parte del anexo hay que completarla aparte.

## El resultado

Al generar se obtienen tres cosas:

- El **XML** del anexo, listo para cargar.
- Un **ZIP** con el archivo.
- Un **Excel de revisión** con el detalle en varias hojas, para cuadrar antes de
  presentar.

Además se valida el archivo generado, de modo que los errores aparezcan aquí y no
al cargarlo.

## Cómo se usa

1. Elija el **periodo**.
2. Genere el anexo.
3. Revise el **Excel** y cuadre los totales con sus declaraciones.
4. Descargue el XML o el ZIP y preséntelo.

## Errores frecuentes

- **Faltan compras del periodo**: compruebe que estén registradas y con la fecha
  correcta.
- **Un proveedor sale mal identificado**: revise su ficha; el anexo usa esos datos
  tal cual.
- **Los totales no cuadran con la declaración de IVA**: compare el Excel de
  revisión contra la declaración; suele ser un documento con fecha fuera del
  periodo.

## Historial de cambios

- **1.0** — Versión inicial.

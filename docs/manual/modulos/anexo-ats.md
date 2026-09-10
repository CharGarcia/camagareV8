---
titulo: Anexo transaccional (ATS)
resumen: Genera el archivo XML del anexo transaccional del periodo, con su validación y un Excel de revisión.
categoria: Impuestos
ruta_modulo: modulos/anexo-ats
tipo: modulo
visibilidad: todos
etiquetas: ats, anexo transaccional, xml, dimm, declaracion, compras, ventas, anulados, sri, sin ventas, solo compras
version: 1.4
orden: 30
estado: activo
---

El **anexo transaccional (ATS)** reporta al SRI el detalle de las transacciones
del periodo. Este módulo lo genera a partir de lo ya registrado en el sistema.

## Qué incluye

| Sección | De dónde sale |
|---------|---------------|
| Compras | Compras, liquidaciones de compra y retenciones emitidas |
| Ventas | Facturas de venta y demás comprobantes de venta *(opcional, ver abajo)* |
| Anulados | Los comprobantes anulados del periodo |

## Generar el anexo sin ventas

Reportar el módulo de ventas no le corresponde a todos los contribuyentes. Por
eso el formulario trae la opción **Incluir el módulo de ventas**, activada:

- **Activada** (como siempre): el anexo lleva el detalle de ventas por cliente y
  el resumen por establecimiento.
- **Desactivada**: el anexo sale solo con compras, liquidaciones y retenciones.
  El total de ventas del informante va en 0.00 y el Excel de revisión no trae la
  hoja *Ventas*.

Los **comprobantes anulados** se reportan en los dos casos: en el anexo son una
sección aparte, no forman parte del módulo de ventas.

El resultado indica siempre con qué se generó, para que no quede duda al
presentarlo: cuántos registros de compras y de ventas entraron, o un aviso
**Sin módulo de ventas** cuando la opción está desactivada.

Si no está seguro de qué le corresponde presentar, consúltelo con su contador:
el sistema respeta lo que usted elija, no decide por usted.

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

## Datos que el anexo toma de las fichas

Buena parte de lo que el SRI revisa no está en el documento, sino en la ficha del
proveedor o del cliente. Conviene tenerlas completas antes de generar:

| Dato | De dónde sale | Cuándo lo exige el SRI |
|------|---------------|------------------------|
| Sustento tributario | Campo del documento en **Compras** | Siempre, y tiene que ser uno de los permitidos para ese tipo de comprobante |
| Tipo de empresa del proveedor | Ficha del **proveedor** | Cuando el proveedor se identifica con pasaporte o identificación del exterior |
| Razón social del proveedor | Ficha del **proveedor** | Igual que el anterior (obligatoria desde mayo de 2016) |
| Tipo de identificación del cliente | Ficha del **cliente** | Siempre; en ventas el anexo solo admite RUC, cédula, pasaporte y consumidor final |
| Pago al exterior | Pestaña **ATS** del documento en Compras | Siempre; si el pago fue al exterior hay que indicar además país, convenio de doble tributación y sujeción a retención |

Los clientes y proveedores del exterior se reportan como **pasaporte**: es el
código que el anexo usa para la identificación del exterior.

## Cómo se usa

1. Elija el **periodo**.
2. Decida si **incluye el módulo de ventas**.
3. Genere el anexo.
4. Revise el **Excel** y cuadre los totales con sus declaraciones.
5. Descargue el XML o el ZIP y preséntelo.

## Errores frecuentes

- **Faltan compras del periodo**: compruebe que estén registradas y con la fecha
  correcta.
- **Un proveedor sale mal identificado**: revise su ficha; el anexo usa esos datos
  tal cual.
- **Los totales no cuadran con la declaración de IVA**: compare el Excel de
  revisión contra la declaración; suele ser un documento con fecha fuera del
  periodo.
- **"IdInformante debe tener 13 dígitos" o "debe terminar en 001"**: el RUC de
  la empresa está mal registrado en su ficha. El sistema solo comprueba el
  largo y la terminación; ya no aplica el algoritmo del dígito verificador,
  porque los RUC nuevos emitidos por el SRI no lo cumplen.
- **"El código de sustento tributario NN no está permitido para comprobantes
  tipo NN"**: cada sustento sirve solo para ciertos tipos de comprobante. Abra
  el documento en **Compras** y elija uno de los que ofrece la lista: al
  seleccionar el tipo de comprobante, el sistema ya filtra los válidos.
- **"Sin código de sustento tributario"** (aparece como advertencia): el
  documento no tiene el dato. El anexo reporta uno permitido para ese tipo de
  comprobante para que el archivo se pueda cargar, pero conviene completarlo en
  **Compras**: es una decisión contable, no un valor por defecto.
- **"Falta el tipo de proveedor" o "falta la razón o denominación social"**:
  ocurre con proveedores del exterior. Complete el **tipo de empresa** y la
  **razón social** en la ficha del proveedor.
- **"El tipo de identificación del cliente no es válido en Ventas"**: la ficha
  del cliente tiene un tipo de identificación que el anexo no reconoce. Elija en
  la ficha el que corresponda (RUC, cédula, pasaporte, identificación del
  exterior o consumidor final).
- **"Es un comprobante emitido en el exterior declarado como pago local"**
  (advertencia): abra la compra y complete la tarjeta *Pago al exterior* de su
  pestaña **ATS**. Una compra al exterior necesita el país del pago, el convenio
  de doble tributación y la sujeción a retención.

## Historial de cambios

- **1.4** — Nueva opción **Incluir el módulo de ventas** al generar: permite
  presentar el anexo solo con compras, liquidaciones y retenciones. Los
  comprobantes anulados siguen reportándose en ambos casos, y el resultado
  indica con qué se generó el archivo.
- **1.3** — El bloque de **pago al exterior** deja de salir siempre como pago
  local: se reporta lo que indique la compra en su pestaña *ATS* (tipo de pago,
  país, convenio de doble tributación y sujeción a retención). La
  validación previa avisa cuando un comprobante emitido en el exterior está
  declarado como pago local, o cuando un pago al exterior está incompleto.
- **1.2** — Correcciones de rechazos del SRI: el tipo y la denominación del
  proveedor se reportan en todos los comprobantes con pasaporte (antes solo en
  liquidaciones de compra) y el tipo de proveedor se deriva bien del tipo de
  empresa; el tipo de identificación del cliente en ventas se traduce al
  catálogo del anexo; y cuando el documento no tiene sustento tributario ya no
  se asume uno que el SRI rechace para ese tipo de comprobante. La validación
  previa detecta estos casos e identifica cada documento por su serie.
- **1.1** — Se elimina la validación del dígito verificador del RUC del
  informante; los RUC nuevos del SRI ya no siguen el algoritmo módulo 10/11.
- **1.0** — Versión inicial.

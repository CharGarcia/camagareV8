---
titulo: Configuración de condominios
resumen: Datos del condominio, producto de la alícuota, método de alícuota, fondo de reserva, intereses de mora y días de gracia, multas y descuentos. Guardarla activa el módulo Condominios.
categoria: Ventas
ruta_modulo: modulos/condominios-config
tipo: modulo
visibilidad: todos
etiquetas: condominio, configuracion condominio, administrador del condominio, alicuota, fondo de reserva, intereses de mora, tasa legal, multas, reglamento, pronto pago, pago anticipado, dia de vencimiento, dias de gracia, restriccion areas comunes, activar condominios
version: 1.2
orden: 1
estado: activo
---

**Configuración de condominios** define las reglas con las que la empresa, que es un
condominio, cobra sus expensas: quién administra, con qué **producto** se factura cada concepto, cómo se calcula la alícuota, el fondo de
reserva, los intereses de mora, las multas del reglamento y los descuentos. **Guardarla activa
el módulo Condominios** para la empresa; sin ella no se pueden registrar inmuebles.

## Qué es y para qué sirve

- Una sola pantalla con todas las reglas del condominio, en pestañas: **Condominio**,
  **Alícuota y fondo**, **Mora y multas** y **Descuentos**. Lo que es de la emisión (recibo o
  factura, serie, periodicidad, día de cobro, correo) **no está aquí**: lo define cada suscripción
  en el módulo Suscripciones, como siempre.
- Todo lo opcional nace **apagado**: intereses, multas, descuentos y restricción automática. El
  condominio que no cobra intereses ni multas no tiene que tocar nada.
- Los conceptos que se cobran **no se crean aquí**: son servicios del módulo **Productos** que
  esta pantalla elige. Así el nombre, el IVA y la cuenta contable salen del producto.

## Requisitos previos

- Crear en **Productos**, como **servicio**, al menos el concepto de la alícuota ordinaria (p. ej.
  «Alícuotas», IVA 0 %). Si se cobra fondo de reserva, intereses o multas, un servicio para cada
  uno.
- Permiso **Actualizar** en este submódulo para guardar.

## Cómo se usa

1. **Condominio**: nombre, dirección, **administrador/a** (obligatorio: firma la liquidación para
   cobro judicial, art. 13 LPH), presidente de la asamblea (opcional), desde cuántas expensas
   vencidas se habilita la liquidación.
2. **Alícuota y fondo**: elija con el buscador el **producto para la alícuota** (escriba parte
   del nombre; el botón de al lado abre Productos para crearlo). Método de alícuota del
   condominio (por %, por m² o manual; cada inmueble puede tener el suyo). Fondo de reserva: no
   / % sobre la alícuota / monto fijo, con su producto.
   Debajo, **Valores que rigen**: la tarifa por m² (inmuebles por m²) y el monto mensual a repartir
   (inmuebles por %), cada uno con el **mes desde el que rige**. Se agregan con **Nuevo valor
   desde…**, que muestra una **vista previa** de la cuota de cada inmueble (y del fondo) antes de
   guardar; el monto a repartir también puede **tomarse de un presupuesto aprobado** (módulo
   Presupuestos: costos y gastos presupuestados del mes). Los valores anteriores nunca se editan.
   **Reajuste de cuotas** (pestaña propia): cambia el valor de un concepto en muchas suscripciones
   a la vez — monto fijo para todas, aumento % sobre el valor actual, o según el inmueble (valor
   que rige). Elija el concepto, la forma, desde cuándo y una descripción; **Vista previa** muestra
   cada suscripción con su valor actual → nuevo (se pueden destildar); **Aplicar** cambia la línea
   del concepto en cada suscripción (si no la tiene, la agrega). Con una **fecha futura** queda
   **programado** y se aplica solo ese día (p. ej. «desde el 1 de enero»); hasta entonces se
   sigue cobrando el valor actual, y se puede cancelar. Todo queda en el historial de la pestaña.
3. **Mora y multas**: interruptor de intereses (tasa legal vigente o % mensual fijo; en el
   siguiente recibo o en uno aparte; con su producto), interruptor de multas, restricción
   automática de áreas comunes al superar N meses de mora, **días de gracia** y el **catálogo de multas** del
   reglamento (nombre, valor, producto; se guarda por fila con *Guardar multa*).
4. **Descuentos**: pronto pago (% hasta el día N) y pago anticipado de varios meses (% desde N
   meses). Ambos apagados por defecto.
5. **Guardar**. El aviso superior indica qué falta para poder emitir (algún producto).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|---|---|---|
| Nombre del condominio, dirección | No | Por defecto, el nombre de la empresa y la dirección de su establecimiento principal; se pueden cambiar. |
| Administrador/a, cédula, cargo | Nombre sí | Firma la liquidación para cobro judicial. Por defecto, el representante legal de la empresa (nombre y cédula); se puede cambiar. |
| Presidente/a de la asamblea | No | Segunda firma opcional en la liquidación. |
| Liquidación judicial desde | Sí | Expensas vencidas mínimas para habilitar el PDF de liquidación (por defecto 1). |
| Días de gracia | Sí | 0–60, contados desde el vencimiento del recibo (que sale del plazo del cliente). Si paga dentro no hay interés; si no, el interés corre desde el vencimiento. |
| Producto para la alícuota | Sí | Servicio de Productos. Su nombre sale en el recibo: «Alícuotas – Dpto 302 – octubre 2026». |
| Método de alícuota | Sí | **Por %**: monto a repartir × % del inmueble. **Por m²**: tarifa × área. **Manual**: monto acordado por inmueble. |
| Manuales con presupuesto | — | Solo cuando el monto a repartir sale de un presupuesto aprobado: *repartir el resto* (por defecto) o *manuales aparte*. |
| Fondo de reserva | — | No / % sobre la alícuota ordinaria / monto fijo por inmueble. Línea separada en el recibo; exige su producto. |
| Valores que rigen | — | Tarifa por m² y monto mensual a repartir, cada uno con el mes desde el que rige. Cada inmueble usa el valor vigente en el mes que se cobra. Con base en un presupuesto y «repartir el resto», a los inmuebles por % se les reparte el monto menos los manuales; los centavos van al de mayor alícuota. |
| Reajuste de cuotas | — | Concepto (producto presente en las suscripciones), forma (fijo / % / según inmueble), parámetro, fecha de aplicación (hoy o futura = programado), descripción o acta, incluir suscripciones sin inmueble. |
| Cobra intereses de mora | — | Apagado por defecto. Tasa legal vigente (tabla global) o % mensual fijo (0,01–20). Interés simple sobre el capital vencido, proporcional a los días, nunca sobre intereses. Exige su producto. |
| Dónde se cobra el interés | — | Línea en el siguiente recibo o recibo aparte. |
| Cobra multas | — | Apagado por defecto. Habilita cargar multas del catálogo a un inmueble. |
| Restricción automática | — | Marca la restricción de áreas comunes al superar N meses de mora; se quita al quedar al día. |
| Pronto pago | — | % de descuento si paga hasta el día N del mes. Se propone al cobrar en Ingresos. No aplica a intereses ni multas. |
| Pago anticipado | — | % de descuento al pagar N meses o más de una vez (un recibo con una línea por mes). |

**Catálogo de multas**: nombre, valor, descripción, producto (servicio) y estado. Se graba
por fila, independiente del botón Guardar de la configuración.

## Permisos

| Permiso | Qué permite |
|---|---|
| Ver | Consultar la configuración y el catálogo de multas. |
| Crear | Crear multas en el catálogo. |
| Actualizar | **Guardar la configuración**, editar multas, registrar valores que rigen y **aplicar o programar reajustes de cuotas**. |
| Eliminar | Eliminar multas del catálogo. |

## Reglas de negocio

- Hay **una configuración por empresa**; guardarla por primera vez activa el módulo
  Condominios. Los demás módulos solo muestran lo de condominios cuando está activa.
- Los productos elegidos deben ser **servicios** de la misma empresa; un producto tipo *bien* se
  rechaza. Fondo de reserva e intereses exigen su producto cuando están activos.
- Con tasa fija, el % mensual debe estar entre 0,01 y 20. Pronto pago y anticipado exigen un %
  entre 0,01 y 100 cuando están activos.
- Todo cambio queda en `log_sistema` con los valores anteriores y nuevos.
- Un reajuste siempre se recalcula en el servidor al aplicar: lo que se graba es lo que mostró la
  vista previa menos las filas destildadas. Con «aumento %», las suscripciones sin la línea del
  concepto se omiten (no hay base); con «monto fijo» se les agrega la línea. Los reajustes
  programados los aplica el cron diario; si uno falla queda en estado *Error* con el motivo.
- No puede haber dos valores que rijan desde el mismo mes. Eliminar un valor hace que vuelva a
  regir el anterior en ese período.
- Sin un valor vigente, los inmuebles por % y por m² no tienen cuota (los manuales sí); el
  listado de Inmuebles lo muestra con «—».

## Integraciones con otros módulos

- **Presupuestos**: un presupuesto aprobado puede ser la base del monto a repartir (sus costos y
  gastos del mes).
- **Condominios (Inmuebles)**: usa esta configuración para validar y calcular las cuotas; su
  listado avisa qué falta para emitir y enlaza aquí.
- **Productos**: fuente de los conceptos.
- **Suscripciones**: el reajuste de cuotas escribe el precio de la línea del concepto en cada
  suscripción (o la agrega); la emisión sigue siendo de Suscripciones.
- **Suscripciones / Ingresos / Cuentas por Cobrar**: emisión, cobro y cartera, como siempre.

## Errores frecuentes

- **«Elija el producto (servicio) con el que se factura la alícuota»**: créelo en Productos
  como servicio y búsquelo por su nombre; el botón junto al campo abre Productos.
- **«El producto elegido … debe ser un servicio»**: el producto es un bien; cree uno de tipo
  servicio.
- **¿Dónde se define si es recibo o factura, la serie y el día de cobro?** En la suscripción de cada
  inmueble, en el módulo Suscripciones.
- **Backspace no borra letra a letra en el buscador de producto**: es a propósito; con una
  selección hecha, Backspace o Supr limpian toda la selección para buscar de nuevo.

## Historial de cambios

- **1.2** — Pestaña **Reajuste de cuotas**: cambio masivo del valor de un concepto en las suscripciones
  (fijo, % o según inmueble), con vista previa, exclusiones, programación por fecha (cron diario) e
  historial.
- **1.1** — **Valores que rigen** (tarifa por m² y monto a repartir con mes de inicio), vista previa de
  la cuota por inmueble, base desde un presupuesto aprobado y reparto del resto. Sin pestaña Emisión.
- **1.0** — Versión inicial: configuración del condominio en cuatro pestañas (Condominio, Alícuota
  y fondo, Mora y multas, Descuentos) y catálogo de multas, como submódulo propio con sus permisos.
  La emisión (comprobante, serie, día de cobro, vencimiento) queda en Suscripciones.

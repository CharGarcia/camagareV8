---
titulo: Proveedores
resumen: Registro de proveedores con sus datos de pago, retenciones y valores predeterminados que agilizan cada compra.
categoria: Compras
ruta_modulo: modulos/proveedores
tipo: modulo
visibilidad: todos
etiquetas: proveedores, proveedor, acreedor, ruc, retencion, cuenta bancaria, plazo, credito, parte relacionada, pago automatico, cheque, egreso automatico, pagos pendientes, resumen comercial, por pagar, buscar, buscador, filtrar, copiar a otra empresa, replicar, duplicar, multiempresa
version: 1.2
orden: 10
estado: activo
---

El módulo de **Proveedores** guarda a quién se le compra. Es la base de las
compras, las retenciones, los egresos y los pagos por transferencia.

Su valor real está en los **valores predeterminados**: bien configurado un
proveedor, cada compra suya llega con la retención, la forma de pago y el
concepto ya propuestos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Complete la **identificación** (RUC o cédula) y la **razón social**.
3. Añada los datos de contacto y ubicación.
4. Configure los valores predeterminados (ver más abajo). No son obligatorios,
   pero es lo que ahorra tiempo después.
5. Guarde.

Al guardar, la ficha **no se cierra**: se queda abierta y se refresca con lo que
quedó realmente grabado, para que siga completando pestañas sin volver a
buscar el proveedor. Si lo creó desde una compra, liquidación u orden, el
documento de fondo ya lo tiene seleccionado; cierre la ficha cuando termine.

## Campos

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Identificación | Sí | RUC, cédula o pasaporte |
| Razón social | Sí | Nombre legal, el que sale en los documentos |
| Nombre comercial | No | Cómo se lo conoce habitualmente |
| Dirección, ciudad, provincia | No | Ubicación del proveedor |
| Teléfono, correo | No | Contacto |
| Parte relacionada | No | Marque si lo es. Afecta a la declaración del anexo |
| Estado | Sí | Activo o inactivo |

## Datos de pago

| Campo | Para qué sirve |
|-------|----------------|
| Banco, número y tipo de cuenta | Necesarios para pagarle por transferencia y para generar el archivo bancario |
| Forma de pago predeterminada | Activa el pago automático: sin ella no se genera ningún egreso solo |
| Operación bancaria predeterminada | Solo si la forma es de tipo banco: transferencia, depósito, débito o cheque |
| Plazo | Días de crédito que le da el proveedor. Define cuándo vence la factura en Cuentas por Pagar |
| Monto mínimo / máximo de pago automático | Rango dentro del cual sus documentos entran en los pagos automáticos |
| Concepto de egreso predeterminado | Concepto contable con el que se registra el egreso |

Deje vacío el mínimo o el máximo para no aplicar ese límite: se puede configurar
solo "mayor a", solo "menor a", o un rango cerrado.

## Pago automático de las compras

Cuando el proveedor tiene **forma de pago predeterminada**, cada compra suya que
entra por **Descargas del SRI** genera sola su egreso, por el total del documento
y con fecha de emisión igual a la de la compra.

Se omite, y queda anotado en el historial de la descarga, si la forma de pago fue
desactivada, si el proveedor tiene retenciones configuradas (aún no existe la
retención, así que pagar el total sería incorrecto), si el monto queda fuera del
rango, o si falta concepto de egreso o punto de emisión activo.

### Pago con cheque

Si la operación bancaria es **Cheque**, el egreso se genera con:

- **Número de cheque**: el consecutivo siguiente al último cheque emitido con esa
  forma de pago. Si no hay ninguno previo, el pago automático se omite y debe
  emitir el primero a mano.
- **Fecha de cobro**: fecha de emisión del documento de compra + los *Días de
  Crédito* del proveedor.

### Generar pagos pendientes

El botón **Generar pagos pendientes**, al pie de la pestaña *Pagos*, registra de
una sola vez los egresos de las compras de ese proveedor **emitidas hasta hoy que
todavía no tienen pago**. Sirve para ponerse al día cuando la forma de pago se
configuró después de que ya habían entrado facturas.

Antes de hacer nada le muestra **cuántos pagos son y por qué monto total**, con el
detalle de los documentos, y pide confirmación.

A diferencia del pago automático en caliente, aquí se paga el **saldo real** de
cada factura (total − retenciones − notas de crédito + notas de débito), el mismo
que muestra Cuentas por Pagar. Por eso sí funciona con proveedores a los que se
les retiene. Quedan fuera las facturas sin saldo y las que no entran en el rango
de monto configurado; el aviso le dice cuántas son.

Cada pago se registra por separado: si uno falla —por ejemplo, porque su período
contable está cerrado— los demás igual se generan y se le informa cuál falló y por
qué. El botón solo aparece si el proveedor ya está guardado, tiene forma de pago
configurada y usted tiene permiso para crear egresos.

## Resumen comercial

En la pestaña *Comercial* se muestran, **solo como lectura**, tres cifras del
proveedor en la empresa y ambiente activos:

| Dato | Qué suma |
|------|----------|
| Documentos recibidos | Compras, notas de crédito/débito y liquidaciones |
| Total compras | Facturas y liquidaciones − notas de crédito + notas de débito |
| Por pagar | Saldo pendiente, con el mismo criterio de Cuentas por Pagar, más los saldos iniciales |

## Retenciones y sustento

| Campo | Para qué sirve |
|-------|----------------|
| Retención de IVA | Porcentaje que se le suele retener de IVA |
| Retención de renta | Porcentaje habitual de retención en la fuente |
| Sustento tributario | El código de sustento con el que se registran sus compras |
| Concepto de egreso predeterminado | Concepto que se propone al pagarle |

Estos valores son **propuestas**, no imposiciones: al registrar la compra o la
retención se pueden cambiar. Configurarlos bien evita el error más común, que es
retener con el porcentaje equivocado por descuido.

## Buscar en el listado

El buscador revisa **todas las columnas del listado**: identificación, tipo de
identificación, razón social, nombre comercial, correo, teléfono, dirección,
plazo, banco, tipo de empresa, provincia y ciudad. Busca por **palabras sueltas**
en cualquier orden y **sin distinguir tildes ni mayúsculas**: escribir
`comercial andina` encuentra *COMERCIAL SANTA ANDINA S.A.* aunque las palabras no
estén juntas.

Escribir exactamente `activo`, `inactivo`, `si` o `no` también filtra por las
columnas *Estado* y *Rela. SRI*.

Además acepta filtros por campo con la sintaxis `clave:valor` (o el desplegable
del buscador): `nombre`, `comercial`, `ruc`, `email`, `telefono`, `direccion`,
`ciudad`, `provincia`, `tipo_empresa`, `banco`, `tipo_id`, `plazo`, `tipo`,
`estado` y `relacionado`. Ejemplos: `ciudad:quito`, `plazo:30..60`,
`estado:inactivo`, `-relacionado:si`.

## Copiar proveedores a otra empresa

Si trabaja con varias empresas, no hace falta volver a teclear la misma ficha en
cada una.

- **Un proveedor**: dentro de su ficha, marque *Aplicar también en otras
  empresas*, elija las empresas y guarde. Al terminar se muestra en qué empresas
  quedó creado, reactivado u omitido.
- **Todos de golpe**: en el listado, el botón **Copiar a otra empresa** copia
  todos los proveedores de la empresa activa hacia la que elija.

Reglas en ambos casos:

- Si el proveedor **ya existe** en la empresa destino (misma identificación), **no
  se duplica ni se sobrescribe**: se respeta lo que ya haya allí.
- Si existía pero estaba **eliminado**, se **reactiva** tal como estaba.
- Solo aparecen las empresas que usted tiene asignadas y en las que tiene permiso
  de **crear** proveedores.
- Los datos que dependen de cada empresa —forma de pago y concepto de egreso
  predeterminados, rango de monto para el pago automático y la ubicación
  geográfica— **no se copian**: se configuran en la empresa destino. Los
  catálogos generales (banco, cuenta, tipo de empresa, retenciones y sustento
  tributario) sí se copian.

## Permisos

Con **acceso total** se ven los proveedores de toda la empresa. Sin ese permiso,
cada usuario ve solo los que creó él — revíselo si alguien reporta proveedores
que "desaparecieron".

Copiar proveedores a otra empresa exige permiso de **crear** en el módulo, tanto
en la empresa de origen como en la de destino. En el copiado masivo, un usuario
sin *acceso total* copia únicamente los proveedores que él creó.

## Eliminar

Es una eliminación **lógica**: el proveedor sale del listado y las compras que ya
lo referencian se conservan intactas. Si solo quiere dejar de usarlo, cámbielo a
**inactivo**.

## Errores frecuentes

- **No aparece al registrar una compra**: está inactivo o pertenece a otra empresa.
- **La retención sale con el porcentaje equivocado**: revise las retenciones
  predeterminadas de su ficha; se aplican a cada compra nueva.
- **No se puede pagar por transferencia**: le faltan banco, número o tipo de
  cuenta.
- **La factura vence en la fecha equivocada**: revise el campo *Plazo*.
- **No se generó el pago automático de una compra**: revise el historial de la
  descarga del SRI; ahí queda escrito el motivo (forma de pago inactiva,
  retenciones configuradas, monto fuera de rango, falta de concepto de egreso).
- **El botón "Generar pagos pendientes" no aparece**: el proveedor aún no está
  guardado, no tiene forma de pago predeterminada, o usted no tiene permiso para
  crear egresos.
- **Se generaron menos pagos de los esperados**: el aviso indica cuántas facturas
  quedaron fuera por no tener saldo o por caer fuera del rango de monto.

## Historial de cambios

- **1.2** — El buscador del listado cubre todas las columnas (incluidas banco,
  tipo de identificación, tipo de empresa, provincia y ciudad), por palabras y sin
  tildes; nuevos filtros `banco:` y `tipo_id:`, y `estado:`/`relacionado:` ya
  funcionan. Copiar un proveedor —o todos— a otra empresa, igual que en Clientes.
- **1.1** — Rango de monto (mínimo y máximo) para el pago automático; pago con
  cheque con número consecutivo y fecha de cobro por días de crédito; botón
  *Generar pagos pendientes*; resumen comercial de solo lectura; la ficha ya no
  se cierra al guardar, se refresca en la misma ventana.
- **1.0** — Versión inicial.

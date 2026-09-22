---
titulo: Cargar transferencias
resumen: Arma el archivo bancario para pagar en lote los egresos por transferencia.
categoria: Tesorería
ruta_modulo: modulos/transferencias
tipo: modulo
visibilidad: admin
etiquetas: transferencias, archivo bancario, pago masivo, lote de pagos, nomina, proveedores, banco, aprobacion, filtro de estado, lotes pendientes, sin aprobar, no aparece el lote, donde estan los lotes aprobados, pagos pendientes de transferencia, no aparece el egreso, sin cuenta bancaria
version: 1.4
orden: 90
estado: activo
---

Este módulo arma el **archivo que se sube al banco** para pagar de una vez muchos
egresos por transferencia: pagos a proveedores o la nómina del mes.

## De dónde salen los pagos

De egresos **ya registrados** con forma de pago de tipo transferencia, y de los
roles de pago. El módulo no crea pagos: los agrupa y les da el formato que pide
el banco.

## El recorrido

1. Filtre los egresos pendientes de transferir.
2. Arme el **lote** con los que van juntos.
3. Apruebe el lote.
4. **Genere el archivo** en el formato de su banco.
5. Súbalo al portal del banco.

## Qué lotes se ven al entrar

La pantalla abre mostrando solo los lotes **sin aprobar**: los que están en
*Borrador* y los *Pendientes de aprobación*, que son los que todavía le piden
algo. En cuanto un lote se aprueba, deja de aparecer en esa vista.

Para ver el resto está el selector de **estado**, junto al buscador: elija
*Todos los estados* para ver el historial completo, o un estado puntual
(*Aprobado*, *Generado*, *Confirmado*, *Rechazado*, *Anulado*). El PDF y el
Excel se descargan con el mismo filtro que tenga la pantalla en ese momento.

Si escribe `estado:APROBADO` directamente en el buscador, manda lo que escribió y
el selector se ignora.

## Aprobación y anti-duplicados

Un lote requiere **aprobación** antes de generarse, y el sistema controla que un
mismo egreso no entre en dos lotes distintos. Son las dos protecciones que evitan
el error más caro posible: pagar dos veces.

La aprobación se configura en el módulo **Aprobaciones**
(`modulos/aprobaciones-config`): ahí se activa el proceso *Lotes de pago
bancario*, se eligen los aprobadores y, si se quiere, un **monto mínimo** por
debajo del cual el lote se aprueba solo. Si el proceso no está configurado, el
lote se aprueba automáticamente al enviarlo. Antes esta configuración estaba en
*Empresa → Pagos al Banco*.

### Cómo aprueba el aprobador

**El sistema no envía ningún correo** cuando un lote queda pendiente. Quien
aprueba entra al módulo y lo hace desde ahí:

1. Abre **Cargar transferencias**. La pantalla ya viene filtrada en *Sin
   aprobar*, así que los lotes que esperan su decisión están a la vista —
   también los que armó otra persona, aunque su permiso sea solo de registros
   propios.
2. Abre el lote y usa los botones **Aprobar** o **Rechazar**.

Un aprobador configurado tiene los mismos botones que un superadministrador,
**incluso en los lotes que él mismo armó**. Quién aprobó cada lote queda
guardado en el registro y en el log del sistema.

## Datos bancarios del beneficiario

Cada pago necesita que el proveedor o el empleado tenga registrados su **banco,
número y tipo de cuenta**. Sin esos datos, esa línea no puede ir en el archivo.

Por eso, al pulsar **Mostrar pagos pendientes de transferencia** en el modal del
lote, la lista trae solo los pagos que cumplen las tres condiciones:

- el egreso se pagó con forma de pago de tipo **transferencia**;
- el beneficiario tiene **banco y número de cuenta** en su ficha;
- el pago **todavía no está en otro lote** activo (los lotes rechazados o
  anulados liberan sus pagos).

Un pago cuyo beneficiario no tiene cuenta no aparece en la lista: complete los
datos bancarios en la ficha del proveedor o del empleado y vuelva a buscar.

## Errores frecuentes

- **Un egreso no aparece en los pagos pendientes**: al beneficiario le faltan el
  banco o el número de cuenta en su ficha, o el pago ya está en otro lote.
- **El banco rechaza el archivo**: revise el formato elegido y que los números de
  cuenta no tengan espacios ni guiones.
- **Un egreso ya está en otro lote**: el control anti-duplicados lo bloqueó;
  revise el lote anterior.
- **Desapareció un lote que acabo de aprobar**: no se borró. La pantalla abre
  filtrada en *Sin aprobar*; cámbiela a *Todos los estados* o a *Aprobado*.
- **No me llegó el correo para aprobar**: no existe ese correo. La aprobación se
  hace entrando al módulo.
- **No veo los botones Aprobar/Rechazar**: no está configurado como aprobador
  del proceso *Lotes de pago bancario* en el módulo **Aprobaciones**.

## Historial de cambios

- **1.0** — Versión inicial.
- **1.1** — La configuración de la aprobación se movió al módulo **Aprobaciones**; se agrega monto mínimo.
- **1.2** — El listado abre mostrando solo los lotes sin aprobar y se agrega un selector de estado para ver el resto.
- **1.3** — Se elimina el aviso por correo a los aprobadores: la aprobación se hace entrando al módulo. El aprobador ve los lotes pendientes aunque los haya armado otra persona, y aprueba con los mismos botones que un superadministrador, incluidos sus propios lotes.
- **1.4** — *Mostrar pagos pendientes de transferencia* lista solo los pagos cuyo beneficiario tiene banco y número de cuenta registrados; los que no los tienen ya no aparecen (antes salían en rojo, sin poder seleccionarse).

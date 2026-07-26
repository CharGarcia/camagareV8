---
titulo: Cargar transferencias
resumen: Arma el archivo bancario para pagar en lote los egresos por transferencia.
categoria: Tesorería
ruta_modulo: modulos/transferencias
tipo: modulo
visibilidad: admin
etiquetas: transferencias, archivo bancario, pago masivo, lote de pagos, nomina, proveedores, banco, aprobacion
version: 1.0
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

## Aprobación y anti-duplicados

Un lote requiere **aprobación** antes de generarse, y el sistema controla que un
mismo egreso no entre en dos lotes distintos. Son las dos protecciones que evitan
el error más caro posible: pagar dos veces.

## Datos bancarios del beneficiario

Cada pago necesita que el proveedor o el empleado tenga registrados su **banco,
número y tipo de cuenta**. Sin esos datos, esa línea no puede ir en el archivo.

## Errores frecuentes

- **Un pago no entra en el lote**: al beneficiario le faltan los datos bancarios
  en su ficha.
- **El banco rechaza el archivo**: revise el formato elegido y que los números de
  cuenta no tengan espacios ni guiones.
- **Un egreso ya está en otro lote**: el control anti-duplicados lo bloqueó;
  revise el lote anterior.

## Historial de cambios

- **1.0** — Versión inicial.

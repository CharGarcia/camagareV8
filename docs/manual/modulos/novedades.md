---
titulo: Novedades
resumen: Hechos que afectan al sueldo de un empleado en un periodo: horas extra, faltas, préstamos, avisos de salida.
categoria: Nómina
ruta_modulo: modulos/novedades
tipo: modulo
visibilidad: todos
etiquetas: novedades, novedad, horas extra, faltas, atrasos, prestamo, anticipo, descuento, aviso de salida, multa
version: 1.0
orden: 20
estado: activo
---

Las **novedades** son los hechos que modifican lo que un empleado cobra en un
periodo: horas extra, faltas, atrasos, anticipos, préstamos, descuentos, bonos.
Son el insumo del rol de pago: lo que se registra aquí es lo que después ajusta
el sueldo.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **empleado**.
3. Elija el **tipo de novedad** del catálogo.
4. Indique la **fecha** y el **periodo** (mes y año) al que se imputa.
5. Escriba el **valor** que corresponda al tipo elegido.
6. Guarde.

## El valor depende del tipo

El campo *valor* es flexible a propósito: según el tipo de novedad representa un
**monto**, un número de **horas** o de **días**. El formulario indica cuál
corresponde en cada caso.

Nunca puede ser negativo: para descontar se usa el tipo de novedad que
corresponde, no un valor en negativo.

## Aviso de salida

El aviso de salida es un tipo especial: exige indicar el **motivo de salida** del
catálogo y **no lleva valor**. Sirve para dejar constancia de la desvinculación y
su causa, que es lo que después determina la liquidación.

## Periodo, no solo fecha

Una novedad tiene fecha (cuándo ocurrió) y periodo (a qué mes se imputa). No
siempre coinciden: una hora extra del 31 de julio puede pagarse en el rol de
agosto. El mes debe estar entre 1 y 12.

## Errores frecuentes

- **"El valor no puede ser negativo"**: elija el tipo de novedad de descuento en
  lugar de poner un número negativo.
- **"El tipo de novedad no es válido"**: el catálogo de tipos es fijo; elija uno
  de la lista.
- **La novedad no aparece en el rol**: revise el periodo (mes y año) al que la
  imputó.

## Historial de cambios

- **1.0** — Versión inicial.

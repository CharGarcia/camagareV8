---
titulo: Décimo cuarto
resumen: Declaración del décimo cuarto sueldo por región, su cálculo, archivo y pago.
categoria: Nómina
ruta_modulo: modulos/decimo-cuarto
tipo: modulo
visibilidad: todos
etiquetas: decimo cuarto, bono escolar, declaracion, ministerio de trabajo, region, sierra, costa, acumulado
version: 1.0
orden: 60
estado: activo
---

Este módulo prepara la declaración del **décimo cuarto sueldo**. Funciona igual
que el décimo tercero, con una diferencia importante: **depende de la región**.

## La región manda

El periodo del décimo cuarto no es el mismo en todo el país, así que la
declaración se hace por **región**. Elegirla mal cambia el periodo de cálculo y
las fechas de pago, así que es lo primero a verificar.

## El recorrido

1. **Cree la declaración** indicando el año y la **región**.
2. **Calcule** los valores de cada empleado.
3. **Revise** el detalle.
4. **Exporte** el archivo para el Ministerio.
5. **Pague**: los pagos se registran como egresos.

No se puede exportar sin haber calculado antes.

## Tipos de pago

| Código | Significado |
|--------|-------------|
| P | Pagado |
| A | Acumulado |
| RP | Reingreso pagado |
| RA | Reingreso acumulado |

## Una vez pagado, no se recalcula

Igual que en el décimo tercero: **con egresos ya registrados sobre la
declaración, no se puede recalcular ni anular**. Para corregir hay que anular
antes esos pagos.

## Errores frecuentes

- **"La región no es válida"**: elija una de las regiones admitidas.
- **"Ya se registraron pagos (Egresos) sobre esta declaración"**: anule primero
  los egresos.
- **"Calcule la declaración antes de exportar el archivo"**: falta calcular.
- **Los valores no cuadran con lo esperado**: verifique que la región elegida sea
  la correcta, porque define el periodo de cálculo.

## Historial de cambios

- **1.0** — Versión inicial.

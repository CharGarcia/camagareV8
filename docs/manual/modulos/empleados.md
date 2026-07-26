---
titulo: Empleados
resumen: Ficha del personal de la empresa; base de la nómina, las novedades y el control de asistencia.
categoria: Nómina
ruta_modulo: modulos/empleados
tipo: modulo
visibilidad: todos
etiquetas: empleados, empleado, personal, trabajadores, nomina, ficha, cedula, sueldo, contratacion
version: 1.0
orden: 10
estado: activo
---

El módulo de **Empleados** es el registro del personal. Alimenta los roles de
pago, las novedades, las vacaciones, los décimos y el control de asistencia: sin
la ficha, el empleado no existe para ninguno de esos procesos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **tipo de identificación** e ingrese el número.
3. Complete **nombres y apellidos**.
4. Añada el resto de datos personales y laborales.
5. Guarde.

## Validaciones

| Campo | Regla |
|-------|-------|
| Tipo de identificación | Obligatorio |
| Identificación | Obligatoria |
| Cédula | Si es cédula, exactamente **10 dígitos** |
| Nombres y apellidos | Obligatorios |
| Correo electrónico | Si se llena, debe tener formato válido |
| Sexo | Debe ser uno de los valores admitidos |

## Un catálogo por empresa

Los empleados pertenecen a una empresa. A diferencia de los comprobantes
electrónicos, **la ficha del empleado no distingue entre ambiente de pruebas y
producción**: es un catálogo maestro, siempre el mismo.

## Errores frecuentes

- **"La cédula debe tener exactamente 10 dígitos"**: revise el número o cambie el
  tipo de identificación si es un extranjero con pasaporte.
- **No aparece al generar el rol de pago**: compruebe que esté activo y en la
  empresa correcta.

## Historial de cambios

- **1.0** — Versión inicial.

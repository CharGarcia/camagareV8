-- =====================================================================================
-- Car-Wash: reparar la placa/marca/modelo de las órdenes en borrador que quedaron con los
-- datos de otro vehículo (versión 1.12 del manual del módulo).
--
-- Causa: la pantalla arrastraba la placa/marca/modelo del último vehículo elegido o creado
-- y los mandaba al guardar cualquier otra orden en borrador. El id_vehiculo de la orden sí
-- quedó correcto; solo la copia (placa, marca, modelo) de la cabecera está mal.
--
-- Ejecutar en pgAdmin UNA sentencia a la vez. Primero el diagnóstico (1), revisar, y recién
-- entonces la corrección (2). Solo toca órdenes vivas, en borrador, con vehículo existente
-- y cuya placa no coincide con la del vehículo. Las facturadas/anuladas no se tocan.
-- =====================================================================================

-- (1) DIAGNÓSTICO: órdenes en borrador cuya placa guardada no es la de su vehículo.
SELECT o.id, o.id_empresa, o.numero_orden, o.estado,
       o.placa  AS placa_orden,  v.placa  AS placa_vehiculo,
       o.marca  AS marca_orden,  v.marca  AS marca_vehiculo,
       o.modelo AS modelo_orden, v.modelo AS modelo_vehiculo,
       o.updated_at, o.updated_by
FROM carwash_ordenes o
JOIN vehiculos v ON v.id = o.id_vehiculo AND v.id_empresa = o.id_empresa AND v.eliminado = false
WHERE o.eliminado = false
  AND o.estado = 'borrador'
  AND COALESCE(o.placa, '') IS DISTINCT FROM COALESCE(v.placa, '')
ORDER BY o.id_empresa, o.id;

-- (2) CORRECCIÓN: copiar placa/marca/modelo desde el vehículo de cada orden.
UPDATE carwash_ordenes o
SET placa      = v.placa,
    marca      = v.marca,
    modelo     = v.modelo,
    updated_at = NOW()
FROM vehiculos v
WHERE v.id = o.id_vehiculo
  AND v.id_empresa = o.id_empresa
  AND v.eliminado = false
  AND o.eliminado = false
  AND o.estado = 'borrador'
  AND COALESCE(o.placa, '') IS DISTINCT FROM COALESCE(v.placa, '');

-- (3) VERIFICACIÓN: debe devolver 0 filas.
SELECT COUNT(*) AS pendientes
FROM carwash_ordenes o
JOIN vehiculos v ON v.id = o.id_vehiculo AND v.id_empresa = o.id_empresa AND v.eliminado = false
WHERE o.eliminado = false
  AND o.estado = 'borrador'
  AND COALESCE(o.placa, '') IS DISTINCT FROM COALESCE(v.placa, '');

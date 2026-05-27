<?php
declare(strict_types=1);

namespace App\models;

class Suscripcion extends BaseModel
{
    public function getActivasPorEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT s.id, s.id_cliente, c.nombre AS nombre_cliente
             FROM suscripciones s
             LEFT JOIN clientes c ON c.id = s.id_cliente
             WHERE s.id_empresa = {$id} AND s.estado = 'activo' AND s.eliminado = false
             ORDER BY s.proximo_cobro ASC"
        );
    }

    public function getVencidasParaCobro(): array
    {
        return $this->query(
            "SELECT s.*, c.email AS cliente_email, c.nombre AS cliente_nombre,
                    per.nombre AS periodicidad_nombre,
                    per.meses  AS periodicidad_meses
             FROM suscripciones s
             LEFT JOIN clientes c ON c.id = s.id_cliente
             LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
             WHERE s.estado = 'activo'
               AND s.eliminado = false
               AND s.proximo_cobro <= CURRENT_DATE
             ORDER BY s.proximo_cobro ASC"
        );
    }
}

<?php
declare(strict_types=1);

namespace App\models;

class SuscripcionPago extends BaseModel
{
    public function getUltimosPorSuscripcion(int $idSuscripcion, int $limit = 10): array
    {
        $id  = (int) $idSuscripcion;
        $lim = (int) $limit;
        return $this->query(
            "SELECT sp.*, vc.factura_numero
             FROM suscripciones_pagos sp
             LEFT JOIN ventas_cabecera vc ON vc.id = sp.id_factura
             WHERE sp.id_suscripcion = {$id} AND sp.eliminado = false
             ORDER BY sp.created_at DESC
             LIMIT {$lim}"
        );
    }
}

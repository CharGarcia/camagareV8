<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Contadores de los badges del navbar (facturas/pedidos/... en borrador o pendientes).
 *
 * Todo el conteo por empresa se resuelve en UNA sola consulta (subconsultas
 * escalares), para no pegarle a la base 10 veces por cada refresco del navbar.
 * El conteo de tareas es global por usuario y vive en TareaRepository (se reutiliza).
 */
class ContadoresNavbarRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Todos los contadores por empresa en una sola consulta.
     *
     * Claves devueltas: facturas_borrador, liquidaciones_borrador,
     * retenciones_compras_borrador, notas_credito_borrador, guias_remision_borrador,
     * ordenes_compra_borrador, pedidos_pendientes, factura_express_pendientes,
     * whatsapp_unread.
     *
     * @return array<string,int>
     */
    public function getConteosEmpresa(int $idEmpresa): array
    {
        // CTE 'amb': el tipo_ambiente actual de la empresa (mismo criterio que los
        // endpoints originales). Las tablas con comprobante electrónico filtran por él;
        // factura_express y whatsapp no lo usan.
        $sql = "
            WITH amb AS (
                SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e
            )
            SELECT
              (SELECT COUNT(*) FROM ventas_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS facturas_borrador,
              (SELECT COUNT(*) FROM liquidaciones_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS liquidaciones_borrador,
              (SELECT COUNT(*) FROM retencion_compra_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS retenciones_compras_borrador,
              (SELECT COUNT(*) FROM notas_credito_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS notas_credito_borrador,
              (SELECT COUNT(*) FROM guias_remision_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS guias_remision_borrador,
              (SELECT COUNT(*) FROM ordenes_compra x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'borrador'  AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS ordenes_compra_borrador,
              (SELECT COUNT(*) FROM pedidos_cabecera x, amb
                 WHERE x.id_empresa = :e AND x.estado = 'Pendiente' AND x.eliminado = false AND x.tipo_ambiente = amb.t) AS pedidos_pendientes,
              (SELECT COUNT(*) FROM factura_express_solicitudes x
                 WHERE x.id_empresa = :e AND x.estado = 'pendiente' AND x.eliminado = false)                             AS factura_express_pendientes,
              (SELECT COUNT(*) FROM whatsapp_chats x
                 WHERE x.id_empresa = :e AND x.mensajes_sin_leer > 0)                                                    AS whatsapp_unread
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($row as $k => $v) {
            $row[$k] = (int) $v;
        }

        return $row;
    }
}

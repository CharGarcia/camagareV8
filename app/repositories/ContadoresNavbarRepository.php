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

    /**
     * Documentos con NOVEDAD del SRI (devueltos / no autorizados / con error) por tipo.
     *
     * Fuente de verdad: la tabla `sri_envio_log` (la misma que alimenta el seguimiento
     * de la pestaña SRI). Se toma el ÚLTIMO `accion` por comprobante (en el ambiente
     * actual de la empresa); si ese estado es de fallo y el documento sigue vigente
     * (no eliminado), cuenta. Así, un documento corregido y reautorizado deja de contar.
     *
     * Los valores de tipo_comprobante y tabla son constantes internas (no entrada de
     * usuario), por lo que interpolarlos en el SQL es seguro.
     *
     * @return array<string,int>  claves: facturas, liquidaciones, retenciones_compras, notas_credito, guias_remision
     */
    public function getNovedadesSri(int $idEmpresa): array
    {
        $tipos = [
            'facturas'            => ['tipo' => 'factura_venta',      'tabla' => 'ventas_cabecera'],
            'liquidaciones'       => ['tipo' => 'liquidacion_compra', 'tabla' => 'liquidaciones_cabecera'],
            'retenciones_compras' => ['tipo' => 'retencion_compra',   'tabla' => 'retencion_compra_cabecera'],
            'notas_credito'       => ['tipo' => 'nota_credito',       'tabla' => 'notas_credito_cabecera'],
            'guias_remision'      => ['tipo' => 'guia_remision',      'tabla' => 'guias_remision_cabecera'],
            // Futuro (cuando exista el módulo): 'notas_debito' => ['tipo' => 'nota_debito', 'tabla' => 'notas_debito_cabecera'],
        ];

        $selects = [];
        foreach ($tipos as $key => $cfg) {
            $tipo  = $cfg['tipo'];   // constante interna
            $tabla = $cfg['tabla'];  // constante interna
            $selects[] = "
              (SELECT COUNT(*) FROM (
                  SELECT DISTINCT ON (l.id_comprobante) l.id_comprobante, l.accion
                  FROM sri_envio_log l
                  WHERE l.id_empresa = :e AND l.tipo_comprobante = '$tipo' AND l.tipo_ambiente = (SELECT t FROM amb)
                  ORDER BY l.id_comprobante, l.id DESC
               ) u
               JOIN $tabla d ON d.id = u.id_comprobante AND d.eliminado = false
               WHERE u.accion IN ('devuelta','no_autorizado','no_autorizada','error')) AS $key";
        }

        $sql = "WITH amb AS (SELECT CAST(tipo_ambiente AS VARCHAR(1)) AS t FROM empresas WHERE id = :e)
                SELECT " . implode(",\n", $selects);

        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($row as $k => $v) {
            $row[$k] = (int) $v;
        }

        return $row;
    }
}

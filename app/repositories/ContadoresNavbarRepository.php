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

    /**
     * Días restantes de vigencia de la suscripción del sistema de la empresa ACTIVA.
     *
     * Mismo criterio que la tarjeta "Suscripción y Vigencia" de modulos/empresa:
     * usa `proximo_cobro` de la suscripción vinculada (cruce por RUC contra la empresa
     * controladora); si no hay suscripción vinculada, cae a `periodo_vigencia_hasta`.
     *
     * @return array{dias:int,meses:?int}|null  Días restantes (negativo = vencida) y los
     *         meses de periodicidad de la suscripción (null = fallback manual, sin periodicidad).
     *         null si no hay dato/columnas.
     */
    public function getDiasVigenciaSuscripcion(int $idEmpresa): ?array
    {
        // Datos de la empresa (defensivo: las columnas pueden no existir si falta la migración).
        try {
            $st = $this->db->prepare(
                "SELECT ruc, id_empresa_suscripciones, periodo_vigencia_hasta
                 FROM empresas WHERE id = :e"
            );
            $st->execute([':e' => $idEmpresa]);
            $emp = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$emp) {
            return null;
        }

        $fechaObjetivo = null;
        $meses         = null;

        // 1) Suscripción vinculada: la de próximo cobro más cercano (activa) en la controladora,
        //    con su periodicidad para poder escalar el umbral del aviso.
        $idCtrl = (int) ($emp['id_empresa_suscripciones'] ?? 0);
        $ruc    = trim((string) ($emp['ruc'] ?? ''));
        if ($idCtrl > 0 && $ruc !== '') {
            try {
                $s = $this->db->prepare(
                    "SELECT s.proximo_cobro, per.meses AS meses
                     FROM suscripciones s
                     JOIN clientes c ON c.id = s.id_cliente
                     LEFT JOIN suscripcion_periodicidades per ON per.id = s.id_periodicidad
                     WHERE s.id_empresa = :ctrl AND s.eliminado = false AND c.eliminado = false
                       AND c.identificacion = :ruc AND s.estado = 'activo' AND s.proximo_cobro IS NOT NULL
                     ORDER BY s.proximo_cobro ASC
                     LIMIT 1"
                );
                $s->execute([':ctrl' => $idCtrl, ':ruc' => $ruc]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['proximo_cobro'])) {
                    $fechaObjetivo = (string) $row['proximo_cobro'];
                    $meses = isset($row['meses']) && $row['meses'] !== null ? (int) $row['meses'] : null;
                }
            } catch (\Throwable $e) {
                // Módulo de suscripciones no disponible: se intenta el fallback.
            }
        }

        // 2) Fallback manual: periodo_vigencia_hasta de la empresa (sin periodicidad).
        if ($fechaObjetivo === null && !empty($emp['periodo_vigencia_hasta'])) {
            $fechaObjetivo = (string) $emp['periodo_vigencia_hasta'];
            $meses = null;
        }

        if ($fechaObjetivo === null) {
            return null;
        }

        $t2 = strtotime($fechaObjetivo);
        if ($t2 === false) {
            return null;
        }

        return [
            // Mismo cálculo que la vista (ceil de la diferencia contra "ahora").
            'dias'  => (int) ceil(($t2 - time()) / 86400),
            'meses' => $meses,
        ];
    }

    /**
     * Estado de la FIRMA ELECTRÓNICA vigente (es_activo) de la empresa activa.
     *
     * Usa la misma firma que el sistema emplea para firmar (empresa_firma con
     * es_activo = true, la de expiración más lejana si hubiera varias).
     *
     * @return array{sin_firma:bool}|array{dias:int}|null
     *   - ['sin_firma' => true]  → no hay ninguna firma vigente instalada.
     *   - ['dias' => int]        → hay firma vigente; días restantes (negativo = caducada).
     *   - null                   → firma vigente sin fecha de expiración, o tabla no disponible
     *                              (no se puede/ no procede avisar).
     */
    public function getEstadoFirma(int $idEmpresa): ?array
    {
        try {
            $st = $this->db->prepare(
                "SELECT fecha_expiracion
                 FROM empresa_firma
                 WHERE id_empresa = :e AND es_activo = TRUE AND eliminado = FALSE
                 ORDER BY fecha_expiracion DESC NULLS LAST, created_at DESC
                 LIMIT 1"
            );
            $st->execute([':e' => $idEmpresa]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null; // tabla/columnas no disponibles → no avisar
        }

        // Sin firma vigente instalada (ninguna es_activo).
        if ($row === false) {
            return ['sin_firma' => true];
        }

        $fecha = $row['fecha_expiracion'] ?? null;
        if (empty($fecha)) {
            return null; // firma activa sin fecha → no se puede calcular
        }
        $t2 = strtotime((string) $fecha);
        if ($t2 === false) {
            return null;
        }

        return ['dias' => (int) ceil(($t2 - time()) / 86400)];
    }
}

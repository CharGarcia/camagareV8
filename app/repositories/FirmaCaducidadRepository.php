<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Consultas del aviso por correo de caducidad de la firma electrónica.
 *
 * Es una consulta GLOBAL (recorre todas las empresas activas) porque la
 * dispara el cron, no un usuario con empresa activa. Usa exactamente la misma
 * firma que el navbar (ContadoresNavbarRepository::getEstadoFirma) y que el
 * firmado de comprobantes: la fila de `empresa_firma` con es_activo = TRUE y
 * la fecha de expiración más lejana si hubiera varias.
 */
class FirmaCaducidadRepository
{
    /** Acción con la que se registra el aviso en log_sistema (sirve de marca "ya enviado"). */
    public const ACCION_AVISO = 'aviso_caducidad_correo';

    /** Segundo aviso (a pocos días): el que lleva el enlace para solicitar la firma nueva. */
    public const ACCION_AVISO_ENLACE = 'aviso_caducidad_enlace';

    /** Tabla afectada en log_sistema. */
    public const TABLA_AVISO = 'empresa_firma';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Firmas vigentes (es_activo) de empresas activas que caducan en `$diasMax` días o
     * menos (incluye caducadas hasta `$diasCaducadaMax` días atrás), con lo necesario
     * para decidir qué aviso toca (el Service aplica los días por tipo de contribuyente).
     *
     * Devuelve una fila por empresa: id_firma, id_empresa, empresa_nombre, empresa_ruc,
     * correo, telefono, tipo_codigo (catálogo tipo_empresa), id_empresa_suscripciones,
     * fecha_expiracion (Y-m-d), dias (negativo = caducada), y si ya recibió cada aviso
     * (avisado_previo, avisado_enlace; la marca vive en log_sistema).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFirmasPorVencer(int $diasMax, int $diasCaducadaMax): array
    {
        $sql = "
            SELECT f.id_firma, f.id_empresa, f.empresa_nombre, f.empresa_ruc, f.correo,
                   f.telefono, f.id_empresa_suscripciones, f.tipo_codigo,
                   TO_CHAR(f.fecha_expiracion, 'YYYY-MM-DD') AS fecha_expiracion,
                   (f.fecha_expiracion - CURRENT_DATE)::int AS dias,
                   EXISTS (SELECT 1 FROM log_sistema l
                           WHERE l.tabla_afectada = :tabla_p AND l.accion = :acc_previo
                             AND l.id_registro = f.id_firma AND l.id_empresa = f.id_empresa) AS avisado_previo,
                   EXISTS (SELECT 1 FROM log_sistema l
                           WHERE l.tabla_afectada = :tabla_e AND l.accion = :acc_enlace
                             AND l.id_registro = f.id_firma AND l.id_empresa = f.id_empresa) AS avisado_enlace
            FROM (
                SELECT DISTINCT ON (ef.id_empresa)
                       ef.id AS id_firma, ef.id_empresa, ef.fecha_expiracion,
                       e.nombre AS empresa_nombre, e.ruc AS empresa_ruc, e.mail AS correo,
                       e.telefono, e.id_empresa_suscripciones, te.codigo AS tipo_codigo
                FROM empresa_firma ef
                JOIN empresas e ON e.id = ef.id_empresa
                LEFT JOIN tipo_empresa te ON te.id::text = NULLIF(TRIM(e.tipo::text), '')
                WHERE ef.es_activo = TRUE
                  AND ef.eliminado = FALSE
                  AND ef.fecha_expiracion IS NOT NULL
                  AND e.estado = '1'
                  AND e.eliminado = FALSE
                ORDER BY ef.id_empresa, ef.fecha_expiracion DESC NULLS LAST, ef.created_at DESC
            ) f
            WHERE (f.fecha_expiracion - CURRENT_DATE) <= :dias_max
              AND (f.fecha_expiracion - CURRENT_DATE) >= :dias_caducada_min
            ORDER BY f.fecha_expiracion, f.id_empresa
        ";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':dias_max'          => $diasMax,
            ':dias_caducada_min' => -$diasCaducadaMax,
            ':tabla_p'           => self::TABLA_AVISO,
            ':tabla_e'           => self::TABLA_AVISO,
            ':acc_previo'        => self::ACCION_AVISO,
            ':acc_enlace'        => self::ACCION_AVISO_ENLACE,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

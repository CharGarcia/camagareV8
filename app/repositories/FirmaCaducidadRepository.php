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

    /** Tabla afectada en log_sistema. */
    public const TABLA_AVISO = 'empresa_firma';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Firmas vigentes (es_activo) de empresas activas cuya fecha de expiración
     * está a `$diasAviso` días o menos (incluye caducadas hasta `$diasCaducadaMax`
     * días atrás) y a las que todavía NO se les envió el aviso por correo.
     *
     * Devuelve una fila por empresa: id_firma, id_empresa, empresa_nombre,
     * empresa_ruc, correo, fecha_expiracion (Y-m-d) y dias (negativo = caducada).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFirmasPorAvisar(int $diasAviso, int $diasCaducadaMax): array
    {
        $sql = "
            SELECT f.id_firma, f.id_empresa, f.empresa_nombre, f.empresa_ruc, f.correo,
                   TO_CHAR(f.fecha_expiracion, 'YYYY-MM-DD') AS fecha_expiracion,
                   (f.fecha_expiracion - CURRENT_DATE)::int AS dias
            FROM (
                SELECT DISTINCT ON (ef.id_empresa)
                       ef.id AS id_firma, ef.id_empresa, ef.fecha_expiracion,
                       e.nombre AS empresa_nombre, e.ruc AS empresa_ruc, e.mail AS correo
                FROM empresa_firma ef
                JOIN empresas e ON e.id = ef.id_empresa
                WHERE ef.es_activo = TRUE
                  AND ef.eliminado = FALSE
                  AND ef.fecha_expiracion IS NOT NULL
                  AND e.estado = '1'
                  AND e.eliminado = FALSE
                ORDER BY ef.id_empresa, ef.fecha_expiracion DESC NULLS LAST, ef.created_at DESC
            ) f
            WHERE (f.fecha_expiracion - CURRENT_DATE) <= :dias_aviso
              AND (f.fecha_expiracion - CURRENT_DATE) >= :dias_caducada_min
              AND NOT EXISTS (
                    SELECT 1 FROM log_sistema l
                    WHERE l.tabla_afectada = :tabla
                      AND l.accion = :accion
                      AND l.id_registro = f.id_firma
                      AND l.id_empresa = f.id_empresa
              )
            ORDER BY f.fecha_expiracion, f.id_empresa
        ";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':dias_aviso'        => $diasAviso,
            ':dias_caducada_min' => -$diasCaducadaMax,
            ':tabla'             => self::TABLA_AVISO,
            ':accion'            => self::ACCION_AVISO,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Persistencia del módulo "Importar desde antiguo CaMaGaRe" (importación de XML).
 * Tablas: importacion_xml_lote (corridas) e importacion_xml_item (un XML c/u).
 * El anti-reproceso vive en el índice único (id_empresa, archivo).
 */
class ImportacionXmlRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('importacion_xml_item');
    }

    // ─── Lotes ───────────────────────────────────────────────

    public function crearLote(array $d): int
    {
        $sql = "INSERT INTO importacion_xml_lote
                    (id_empresa, ruc, ruta_base, tipos_seleccionados, fecha_desde, fecha_hasta, estado, created_by)
                VALUES (:id_empresa, :ruc, :ruta_base, :tipos, :desde, :hasta, 'escaneado', :created_by)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $d['id_empresa'],
            ':ruc'        => $d['ruc'],
            ':ruta_base'  => $d['ruta_base'] ?? null,
            ':tipos'      => $d['tipos_seleccionados'] ?? null,
            ':desde'      => $d['fecha_desde'] ?? null,
            ':hasta'      => $d['fecha_hasta'] ?? null,
            ':created_by' => $d['created_by'] ?? null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarEstadoLote(int $idLote, string $estado): void
    {
        $st = $this->db->prepare("UPDATE importacion_xml_lote SET estado = :e, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $st->execute([':e' => $estado, ':id' => $idLote]);
    }

    /** Recalcula los totales del lote a partir de sus ítems. */
    public function recalcularTotalesLote(int $idLote): void
    {
        $sql = "UPDATE importacion_xml_lote l SET
                    total_detectados = s.total,
                    total_importados = s.importados,
                    total_duplicados = s.duplicados,
                    total_errores    = s.errores,
                    updated_at       = CURRENT_TIMESTAMP
                FROM (
                    SELECT
                        COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE estado = 'importado') AS importados,
                        COUNT(*) FILTER (WHERE estado = 'duplicado') AS duplicados,
                        COUNT(*) FILTER (WHERE estado = 'error')     AS errores
                    FROM importacion_xml_item WHERE id_lote = :id
                ) s
                WHERE l.id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idLote]);
    }

    public function getLote(int $idLote): ?array
    {
        $st = $this->db->prepare("SELECT * FROM importacion_xml_lote WHERE id = :id");
        $st->execute([':id' => $idLote]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function listarLotes(int $idEmpresa, int $limite = 50): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM importacion_xml_lote
             WHERE id_empresa = :e AND eliminado = FALSE
             ORDER BY id DESC LIMIT :lim"
        );
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Ítems ───────────────────────────────────────────────

    /**
     * Inserta ítems provisionales (del escaneo rápido). Dedup por (id_empresa, archivo):
     * los ya existentes se ignoran (ON CONFLICT DO NOTHING). Devuelve cuántos se insertaron.
     *
     * @param array<int,array<string,mixed>> $items  cada uno: archivo, cod_doc, secuencial
     */
    public function insertarItemsProvisionales(int $idLote, int $idEmpresa, array $items, ?int $createdBy): int
    {
        if (empty($items)) {
            return 0;
        }
        $sql = "INSERT INTO importacion_xml_item
                    (id_lote, id_empresa, archivo, cod_doc, secuencial, estado, created_by)
                VALUES (:lote, :emp, :archivo, :cod, :sec, 'pendiente', :cb)
                ON CONFLICT (id_empresa, archivo) WHERE eliminado = FALSE DO NOTHING";
        $st = $this->db->prepare($sql);
        $insertados = 0;
        foreach ($items as $it) {
            $st->execute([
                ':lote'    => $idLote,
                ':emp'     => $idEmpresa,
                ':archivo' => $it['archivo'],
                ':cod'     => $it['cod_doc'] ?? null,
                ':sec'     => $it['secuencial'] ?? null,
                ':cb'      => $createdBy,
            ]);
            $insertados += $st->rowCount();
        }
        return $insertados;
    }

    /**
     * Ítems pendientes de una empresa (resuelve reanudación entre lotes).
     * @param string[] $codDocs  filtro opcional por cod_doc
     * @return array<int,array<string,mixed>>
     */
    public function getPendientes(int $idEmpresa, int $limite, array $codDocs = [], ?int $idLote = null): array
    {
        $where = "id_empresa = :e AND estado = 'pendiente' AND eliminado = FALSE";
        $params = [':e' => $idEmpresa];
        if ($idLote !== null) {
            $where .= " AND id_lote = :lote";
            $params[':lote'] = $idLote;
        }
        if (!empty($codDocs)) {
            $in = [];
            foreach (array_values($codDocs) as $i => $cd) {
                $k = ":cd{$i}";
                $in[] = $k;
                $params[$k] = $cd;
            }
            $where .= " AND cod_doc IN (" . implode(',', $in) . ")";
        }
        $sql = "SELECT id, id_lote, archivo, cod_doc, secuencial FROM importacion_xml_item
                WHERE {$where} ORDER BY id ASC LIMIT :lim";
        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPendientes(int $idEmpresa, array $codDocs = []): int
    {
        $where = "id_empresa = :e AND estado = 'pendiente' AND eliminado = FALSE";
        $params = [':e' => $idEmpresa];
        if (!empty($codDocs)) {
            $in = [];
            foreach (array_values($codDocs) as $i => $cd) {
                $k = ":cd{$i}";
                $in[] = $k;
                $params[$k] = $cd;
            }
            $where .= " AND cod_doc IN (" . implode(',', $in) . ")";
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM importacion_xml_item WHERE {$where}");
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** Marca un ítem con el resultado final de la importación. */
    public function actualizarItem(int $id, array $d): void
    {
        $sql = "UPDATE importacion_xml_item SET
                    estado = :estado,
                    mensaje = :mensaje,
                    clave_acceso = COALESCE(:clave, clave_acceso),
                    ruc_emisor = COALESCE(:ruc, ruc_emisor),
                    razon_social_emisor = COALESCE(:razon, razon_social_emisor),
                    fecha_emision = COALESCE(:fecha, fecha_emision),
                    total = COALESCE(:total, total),
                    es_emitido = COALESCE(:emit, es_emitido),
                    sri_estado = COALESCE(:sri, sri_estado),
                    id_documento_generado = COALESCE(:iddoc, id_documento_generado),
                    tabla_documento = COALESCE(:tabla, tabla_documento),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':estado'  => $d['estado'],
            ':mensaje' => $d['mensaje'] ?? null,
            ':clave'   => $d['clave_acceso'] ?? null,
            ':ruc'     => $d['ruc_emisor'] ?? null,
            ':razon'   => $d['razon_social_emisor'] ?? null,
            ':fecha'   => $d['fecha_emision'] ?? null,
            ':total'   => $d['total'] ?? null,
            ':emit'    => isset($d['es_emitido']) ? ($d['es_emitido'] ? 't' : 'f') : null,
            ':sri'     => $d['sri_estado'] ?? null,
            ':iddoc'   => $d['id_documento_generado'] ?? null,
            ':tabla'   => $d['tabla_documento'] ?? null,
            ':id'      => $id,
        ]);
    }

    /**
     * Resumen del registro de la EMPRESA por cod_doc (opcionalmente filtrado por tipos).
     * Refleja el estado real acumulado entre lotes: total, importados, pendientes, etc.
     */
    public function getResumenEmpresa(int $idEmpresa, array $codDocs = []): array
    {
        $where = "id_empresa = :e AND eliminado = FALSE";
        $params = [':e' => $idEmpresa];
        if (!empty($codDocs)) {
            $in = [];
            foreach (array_values($codDocs) as $i => $cd) {
                $k = ":cd{$i}";
                $in[] = $k;
                $params[$k] = $cd;
            }
            $where .= " AND cod_doc IN (" . implode(',', $in) . ")";
        }
        $sql = "SELECT cod_doc,
                       COUNT(*) AS n,
                       COUNT(*) FILTER (WHERE estado='importado')     AS importados,
                       COUNT(*) FILTER (WHERE estado='pendiente')     AS pendientes,
                       COUNT(*) FILTER (WHERE estado='error')         AS errores,
                       COUNT(*) FILTER (WHERE estado='duplicado')     AS duplicados,
                       COUNT(*) FILTER (WHERE estado='no_autorizado') AS no_autorizados
                FROM importacion_xml_item
                WHERE {$where}
                GROUP BY cod_doc ORDER BY cod_doc";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Resumen de un lote: conteos por cod_doc y por estado. */
    public function getResumenLote(int $idLote): array
    {
        $porTipo = $this->db->prepare(
            "SELECT cod_doc, COUNT(*) AS n,
                    COUNT(*) FILTER (WHERE estado='importado') AS importados,
                    COUNT(*) FILTER (WHERE estado='pendiente') AS pendientes,
                    COUNT(*) FILTER (WHERE estado='error')     AS errores,
                    COUNT(*) FILTER (WHERE estado='duplicado') AS duplicados
             FROM importacion_xml_item WHERE id_lote = :id
             GROUP BY cod_doc ORDER BY cod_doc"
        );
        $porTipo->execute([':id' => $idLote]);
        return $porTipo->fetchAll(PDO::FETCH_ASSOC);
    }
}

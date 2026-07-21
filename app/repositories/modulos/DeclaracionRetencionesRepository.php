<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Formulario 103 SRI — Declaración de Retenciones en la Fuente del Impuesto a la Renta.
 * Reutiliza la tabla genérica `casilleros_declaracion_sri` (misma que Declaración IVA / F104),
 * distinguiendo por la columna `formulario = '103'`.
 */
class DeclaracionRetencionesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('casilleros_declaracion_sri');
    }

    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Estructura oficial del Formulario 103 (una fila por casillero, ordenada como el formulario impreso). */
    public function getEstructuraFormulario(): array
    {
        $sql = "SELECT * FROM sri_form103_casilleros WHERE eliminado = false ORDER BY orden ASC, id ASC";
        return $this->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cabeceras de retención de compra AUTORIZADAS del período (para sincronizar). */
    public function getRetencionesCompraPeriodo(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT id FROM retencion_compra_cabecera
                WHERE id_empresa = :ie AND fecha_emision BETWEEN :d AND :h
                  AND estado = 'autorizada' AND eliminado = false
                  AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :ie2)";
        return $this->query($sql, [':ie' => $idEmpresa, ':d' => $fechaDesde, ':h' => $fechaHasta, ':ie2' => $idEmpresa])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Limpia los casilleros F103 de un documento específico antes de resincronizar. */
    public function limpiarCasillerosDocumento(int $idEmpresa, string $origen, int $idOrigen): void
    {
        $sql = "DELETE FROM casilleros_declaracion_sri WHERE id_empresa = ? AND formulario = '103' AND origen = ? AND id_origen = ?";
        $this->query($sql, [$idEmpresa, $origen, $idOrigen]);
    }

    /** Limpia casilleros F103 de documentos que ya no existen o dejaron de estar autorizados. */
    public function limpiarCasillerosHuerfanos(int $idEmpresa, string $fechaDesde, string $fechaHasta): void
    {
        $sql = "DELETE FROM casilleros_declaracion_sri c
                WHERE c.id_empresa = ? AND c.formulario = '103' AND c.fecha BETWEEN ? AND ?
                AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                AND (
                    (c.origen = 'retenciones_compras_renta' AND NOT EXISTS (
                        SELECT 1 FROM retencion_compra_cabecera rc
                        WHERE rc.id = CAST(c.id_origen AS INTEGER) AND COALESCE(rc.eliminado, false) = false AND rc.estado = 'autorizada'
                    ))
                    OR
                    (c.origen = 'empleados_ir' AND NOT EXISTS (
                        SELECT 1 FROM rol_detalle rd WHERE rd.id = CAST(c.id_origen AS INTEGER)
                    ))
                )";
        $this->query($sql, [$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa]);
    }

    /** Inserta un movimiento de casillero para el Formulario 103. */
    public function insertarCasillero(array $d): void
    {
        $sql = "INSERT INTO casilleros_declaracion_sri
                    (id_empresa, origen, id_origen, fecha, casillero, valor, concepto, formulario, tipo_ambiente)
                VALUES
                    (:ie, :or, :io, :fe, :ca, :va, :co, '103', (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :ie2))";
        $this->query($sql, [
            ':ie'  => $d['id_empresa'],
            ':or'  => $d['origen'],
            ':io'  => $d['id_origen'],
            ':fe'  => $d['fecha'],
            ':ca'  => $d['casillero'],
            ':va'  => $d['valor'],
            ':co'  => $d['concepto'] ?? null,
            ':ie2' => $d['id_empresa'],
        ]);
    }

    /** Resumen de valores agrupados por casillero para el período (Formulario 103). */
    public function getResumenPorCasilleros(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT casillero, SUM(valor) as total
                FROM casilleros_declaracion_sri
                WHERE id_empresa = ? AND formulario = '103' AND fecha BETWEEN ? AND ?
                AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                GROUP BY casillero";
        return $this->query($sql, [$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Detalle de documentos/movimientos que componen cada casillero (para la pestaña de detalle y el Excel). */
    public function getDetalleDocumentos(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT c.id, c.origen, c.id_origen, c.fecha, c.casillero, c.valor, c.editado_manualmente, c.concepto,
                       rc.establecimiento, rc.punto_emision, rc.secuencial, rc.num_doc_sustento,
                       p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_ruc
                FROM casilleros_declaracion_sri c
                LEFT JOIN retencion_compra_cabecera rc ON c.id_origen = rc.id AND c.origen = 'retenciones_compras_renta'
                LEFT JOIN proveedores p ON p.id = rc.id_proveedor
                WHERE c.id_empresa = ? AND c.formulario = '103' AND c.fecha BETWEEN ? AND ?
                AND c.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                ORDER BY c.fecha ASC, c.origen ASC, c.id_origen ASC";
        return $this->query($sql, [$idEmpresa, $fechaDesde, $fechaHasta, $idEmpresa])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Líneas de retención de compra (impuesto Renta) del período, con casillero ya resuelto — para el detalle del Excel. */
    public function getDetalleLineasRenta(int $idEmpresa, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT rc.establecimiento, rc.punto_emision, rc.secuencial, rc.fecha_emision,
                       rc.num_doc_sustento, rc.tipo_doc_sustento,
                       p.razon_social AS proveedor_nombre, p.identificacion AS proveedor_ruc,
                       d.codigo_retencion, d.concepto, d.base_imponible, d.porcentaje_retener, d.valor_retenido,
                       rs.casillero_base, rs.casillero_valor
                FROM retencion_compra_detalle d
                JOIN retencion_compra_cabecera rc ON rc.id = d.id_retencion
                LEFT JOIN proveedores p ON p.id = rc.id_proveedor
                LEFT JOIN retenciones_sri rs ON rs.id = d.id_retencion_sri
                WHERE rc.id_empresa = :ie AND rc.estado = 'autorizada' AND rc.eliminado = false
                  AND rc.fecha_emision BETWEEN :d AND :h
                  AND (d.codigo_impuesto IN ('1','RENTA'))
                  AND rc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :ie2)
                ORDER BY rc.fecha_emision ASC, rc.secuencial ASC";
        return $this->query($sql, [':ie' => $idEmpresa, ':d' => $fechaDesde, ':h' => $fechaHasta, ':ie2' => $idEmpresa])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resuelve el casillero_base/casillero_valor de una línea de retención.
     * Prioriza el id_retencion_sri de la línea; si no está enlazado, cae al
     * código de retención (texto) contra el catálogo global.
     */
    public function getCasilleroDeRetencionSri(?int $idRetencionSri, string $codigoRetencion): array
    {
        if ($idRetencionSri) {
            $row = $this->query(
                "SELECT casillero_base, casillero_valor FROM retenciones_sri WHERE id = ?",
                [$idRetencionSri]
            )->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        $row = $this->query(
            "SELECT casillero_base, casillero_valor FROM retenciones_sri
             WHERE codigo_ret = ? AND impuesto_ret = 'RENTA' AND casillero_base IS NOT NULL
             LIMIT 1",
            [$codigoRetencion]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['casillero_base' => null, 'casillero_valor' => null];
    }

    /** Actualiza manualmente el casillero de un movimiento (corrección puntual). */
    public function actualizarCasilleroManual(int $id, string $nuevoCasillero): void
    {
        $this->query(
            "UPDATE casilleros_declaracion_sri SET casillero = ?, editado_manualmente = TRUE WHERE id = ? AND formulario = '103'",
            [$nuevoCasillero, $id]
        );
    }

    /** Años con retenciones de compra registradas (para el selector de período). */
    public function getAniosConRetenciones(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision) AS anio
                FROM retencion_compra_cabecera
                WHERE id_empresa = ? AND eliminado = false
                ORDER BY anio DESC";
        $anios = $this->query($sql, [$idEmpresa])->fetchAll(PDO::FETCH_COLUMN);
        return $anios ?: [(int) date('Y')];
    }

    // ==========================================================================
    // Declaración guardada (declaracion_retenciones_cabecera)
    // ==========================================================================

    public function findDeclaracion(int $idEmpresa, string $tipoAmbiente, int $anio, int $mes): ?array
    {
        $sql = "SELECT d.*, u.nombre AS usuario_nombre
                FROM declaracion_retenciones_cabecera d
                LEFT JOIN usuarios u ON u.id = COALESCE(d.updated_by, d.created_by)
                WHERE d.id_empresa = :emp AND d.tipo_ambiente = :amb
                  AND d.periodo_anio = :anio AND d.periodo_mes = :mes AND d.eliminado = false
                LIMIT 1";
        $row = $this->query($sql, [':emp' => $idEmpresa, ':amb' => $tipoAmbiente, ':anio' => $anio, ':mes' => $mes])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDeclaracionById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM declaracion_retenciones_cabecera WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $row = $this->query($sql, [$id, $idEmpresa])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertDeclaracion(array $data): int
    {
        $sql = "INSERT INTO declaracion_retenciones_cabecera (
                    id_empresa, tipo_ambiente, periodo_anio, periodo_mes,
                    fecha_desde, fecha_hasta,
                    total_base_nacional, total_retenido_nacional,
                    total_base_exterior, total_retenido_exterior, total_retenido,
                    valores_casilleros, estado, observaciones, created_by, updated_by
                ) VALUES (
                    :id_empresa, :amb, :anio, :mes,
                    :fdesde, :fhasta,
                    :base_nac, :ret_nac,
                    :base_ext, :ret_ext, :total,
                    :valores, :estado, :obs, :usr, :usr2
                ) RETURNING id";
        $params = $this->mapDeclaracionParams($data);
        $params[':usr2'] = $params[':usr'];
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function updateDeclaracion(int $id, int $idEmpresa, array $data): void
    {
        $sql = "UPDATE declaracion_retenciones_cabecera SET
                    fecha_desde = :fdesde, fecha_hasta = :fhasta,
                    total_base_nacional = :base_nac, total_retenido_nacional = :ret_nac,
                    total_base_exterior = :base_ext, total_retenido_exterior = :ret_ext,
                    total_retenido = :total,
                    valores_casilleros = :valores, estado = :estado, observaciones = :obs,
                    updated_by = :usr, updated_at = now()
                WHERE id = :id AND id_empresa = :id_empresa2";
        $params = $this->mapDeclaracionParams($data);
        unset($params[':id_empresa'], $params[':amb'], $params[':anio'], $params[':mes']);
        $params[':id'] = $id;
        $params[':id_empresa2'] = $idEmpresa;
        $this->query($sql, $params);
    }

    private function mapDeclaracionParams(array $data): array
    {
        return [
            ':id_empresa' => (int) $data['id_empresa'],
            ':amb'        => (string) $data['tipo_ambiente'],
            ':anio'       => (int) $data['periodo_anio'],
            ':mes'        => (int) $data['periodo_mes'],
            ':fdesde'     => $data['fecha_desde'],
            ':fhasta'     => $data['fecha_hasta'],
            ':base_nac'   => (float) $data['total_base_nacional'],
            ':ret_nac'    => (float) $data['total_retenido_nacional'],
            ':base_ext'   => (float) $data['total_base_exterior'],
            ':ret_ext'    => (float) $data['total_retenido_exterior'],
            ':total'      => (float) $data['total_retenido'],
            ':valores'    => json_encode($data['valores_casilleros'] ?? [], JSON_UNESCAPED_UNICODE),
            ':estado'     => (string) ($data['estado'] ?? 'guardado'),
            ':obs'        => $data['observaciones'] ?? null,
            ':usr'        => (int) $data['usuario_id'],
        ];
    }

    public function marcarAsiento(int $id, int $idEmpresa, int $idAsiento, int $idUsuario): void
    {
        $sql = "UPDATE declaracion_retenciones_cabecera
                SET id_asiento = ?, estado = CASE WHEN estado = 'guardado' THEN 'contabilizado' ELSE estado END,
                    updated_by = ?, updated_at = now()
                WHERE id = ? AND id_empresa = ?";
        $this->query($sql, [$idAsiento, $idUsuario, $id, $idEmpresa]);
    }

    public function marcarEgreso(int $id, int $idEmpresa, int $idEgreso, int $idUsuario): void
    {
        $sql = "UPDATE declaracion_retenciones_cabecera
                SET id_egreso = ?, estado = 'pagado', updated_by = ?, updated_at = now()
                WHERE id = ? AND id_empresa = ?";
        $this->query($sql, [$idEgreso, $idUsuario, $id, $idEmpresa]);
    }
}

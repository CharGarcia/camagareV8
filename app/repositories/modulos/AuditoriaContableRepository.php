<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use App\Helpers\FiltrosBusqueda;
use PDO;

/**
 * Repository del módulo Auditoría Contable.
 *
 * Detecta inconsistencias entre los documentos operativos y sus asientos
 * contables, y persiste los hallazgos en `auditoria_contable_incidencias`.
 *
 * Reglas clave (CLAUDE.md):
 *  - Multiempresa: TODA consulta filtra por id_empresa + eliminado = false.
 *  - Multi-ambiente: además filtra por el tipo_ambiente activo de la empresa
 *    ('1' Pruebas, '2' Producción), igual que el resto de listados operativos.
 *  - Acceso a BD por PDO preparado; los nombres de tabla/columna/origen que se
 *    interpolan provienen SIEMPRE de la whitelist $origenes, nunca del usuario.
 */
class AuditoriaContableRepository extends BaseRepository
{
    /** Subconsulta del ambiente activo de la empresa (usa el placeholder :id_empresa). */
    private const AMB = "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

    /**
     * Configuración de los orígenes auditables. La clave es el `modulo_origen`
     * tal como lo graban los Services al crear el asiento.
     *
     *  - tabla         : tabla operativa (cabecera).
     *  - total         : expresión SQL del total del documento (alias d).
     *  - estado_filtro : condición de "documento vigente que debe tener asiento"
     *                    (alias d). Replica el criterio del SincronizadorAsientosService.
     *  - tiene_estado  : si la cabecera tiene columna `estado` (para estado_incoherente).
     */
    private array $origenes = [
        'factura_venta' => [
            'tabla'         => 'ventas_cabecera',
            'total'         => 'd.importe_total',
            'estado_filtro' => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'  => true,
        ],
        'compra' => [
            // compras_cabecera no tiene columna `estado` en BD (igual que el SincronizadorAsientosService,
            // que no filtra por estado para compras). Por eso estado_filtro=1=1 y tiene_estado=false.
            'tabla'         => 'compras_cabecera',
            'total'         => 'd.importe_total',
            'estado_filtro' => '1=1',
            'tiene_estado'  => false,
        ],
        'liquidacion_compra' => [
            'tabla'         => 'liquidaciones_cabecera',
            'total'         => 'd.importe_total',
            'estado_filtro' => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'  => true,
        ],
        'nota_credito' => [
            'tabla'         => 'notas_credito_cabecera',
            'total'         => 'd.importe_total',
            'estado_filtro' => "d.estado IN ('autorizado','contabilizado')",
            'tiene_estado'  => true,
        ],
        'retencion_venta' => [
            'tabla'         => 'retencion_venta_cabecera',
            'total'         => '(COALESCE(d.total_isd,0)+COALESCE(d.total_iva,0)+COALESCE(d.total_renta,0))',
            'estado_filtro' => '1=1',
            'tiene_estado'  => false,
        ],
        'ingreso' => [
            'tabla'         => 'ingresos_cabecera',
            'total'         => 'd.monto_total',
            'estado_filtro' => "d.estado <> 'anulado'",
            'tiene_estado'  => true,
        ],
        'egreso' => [
            'tabla'         => 'egresos_cabecera',
            'total'         => 'd.monto_total',
            'estado_filtro' => "d.estado <> 'anulado'",
            'tiene_estado'  => true,
        ],
    ];

    public function __construct()
    {
        parent::__construct('auditoria_contable_incidencias');
    }

    /** Lista de orígenes auditables (claves de modulo_origen). */
    public function getOrigenes(): array
    {
        return array_keys($this->origenes);
    }

    /** Valida que un origen pertenezca a la whitelist. */
    public function esOrigenValido(string $origen): bool
    {
        return isset($this->origenes[$origen]);
    }

    /** Devuelve la tabla operativa de un origen, o null si no existe. */
    public function getTablaOrigen(string $origen): ?string
    {
        return $this->origenes[$origen]['tabla'] ?? null;
    }

    /** Ambiente activo de la empresa ('1' Pruebas, '2' Producción). */
    public function getAmbienteEmpresa(int $idEmpresa): string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?");
        $st->execute([$idEmpresa]);
        return (string) ($st->fetchColumn() ?: '1');
    }

    private function ejecutar(string $sql, array $params): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================================================================
    //  DETECCIÓN DE HALLAZGOS
    // ==================================================================

    /**
     * Corre los 8 chequeos sobre el ambiente activo de la empresa y devuelve
     * los hallazgos normalizados. Cada elemento tiene la forma:
     *   tipo_hallazgo, modulo_origen, id_documento, id_asiento,
     *   monto_documento, monto_asiento, diferencia, detalle, fecha_documento
     *
     * @param string|null $soloOrigen Si se indica, limita a ese modulo_origen.
     */
    public function detectarTodos(int $idEmpresa, ?string $soloOrigen = null): array
    {
        $origenes = $soloOrigen !== null && $this->esOrigenValido($soloOrigen)
            ? [$soloOrigen]
            : $this->getOrigenes();

        $hallazgos = [];
        foreach ($origenes as $origen) {
            $hallazgos = array_merge($hallazgos, $this->detectarFaltantesYMonto($idEmpresa, $origen));
            $hallazgos = array_merge($hallazgos, $this->detectarHuerfanos($idEmpresa, $origen));
            $hallazgos = array_merge($hallazgos, $this->detectarEstadoIncoherente($idEmpresa, $origen));
            $hallazgos = array_merge($hallazgos, $this->detectarAmbienteIncoherente($idEmpresa, $origen));
        }
        // Chequeos sobre el asiento, no dependientes del origen (se limitan a la lista cuando hay filtro).
        $hallazgos = array_merge($hallazgos, $this->detectarDuplicados($idEmpresa, $soloOrigen));
        $hallazgos = array_merge($hallazgos, $this->detectarDescuadrados($idEmpresa, $soloOrigen));
        $hallazgos = array_merge($hallazgos, $this->detectarCabVsDetalle($idEmpresa, $soloOrigen));

        return $hallazgos;
    }

    /**
     * Faltante (documento vigente sin asiento) y monto_no_coincide
     * (documento con asiento cuyo total_debe difiere del total del documento).
     */
    private function detectarFaltantesYMonto(int $idEmpresa, string $origen): array
    {
        $cfg   = $this->origenes[$origen];
        $tabla = $cfg['tabla'];
        $total = $cfg['total'];
        $amb   = self::AMB;

        $sql = "SELECT d.id AS id_documento,
                       {$total} AS monto_documento,
                       a.id AS id_asiento,
                       a.total_debe AS monto_asiento,
                       d.fecha_emision AS fecha_documento
                FROM {$tabla} d
                LEFT JOIN asientos_contables_cabecera a
                       ON a.modulo_origen = '{$origen}'
                      AND a.id_referencia_origen = d.id
                      AND a.eliminado = false
                      AND a.estado <> 'anulado'
                      AND a.id_empresa = d.id_empresa
                      AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                WHERE d.id_empresa = :id_empresa
                  AND d.eliminado = false
                  AND CAST(d.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND {$cfg['estado_filtro']}";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);

        $out = [];
        foreach ($rows as $r) {
            $montoDoc = (float) $r['monto_documento'];
            if ($r['id_asiento'] === null) {
                $out[] = $this->normalizar('faltante', $origen, (int) $r['id_documento'], null,
                    $montoDoc, null, $montoDoc,
                    "Documento vigente sin asiento contable.", $r['fecha_documento']);
                continue;
            }
            $montoAsiento = (float) $r['monto_asiento'];
            if (round($montoDoc, 2) !== round($montoAsiento, 2)) {
                $out[] = $this->normalizar('monto_no_coincide', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                    $montoDoc, $montoAsiento, round($montoDoc - $montoAsiento, 2),
                    "El total del documento no coincide con el total del asiento.", $r['fecha_documento']);
            }
        }
        return $out;
    }

    /** Asiento huérfano: referencia a un documento inexistente o eliminado. */
    private function detectarHuerfanos(int $idEmpresa, string $origen): array
    {
        $tabla = $this->origenes[$origen]['tabla'];
        $amb   = self::AMB;

        $sql = "SELECT a.id AS id_asiento,
                       a.id_referencia_origen AS id_documento,
                       a.total_debe AS monto_asiento,
                       a.fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera a
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND a.modulo_origen = '{$origen}'
                  AND a.id_referencia_origen IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM {$tabla} d
                                  WHERE d.id = a.id_referencia_origen
                                    AND d.eliminado = false)";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('huerfano', $origen,
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El asiento referencia un documento inexistente o eliminado.", $r['fecha_documento']);
        }
        return $out;
    }

    /** Documento anulado que conserva un asiento vivo (no anulado). */
    private function detectarEstadoIncoherente(int $idEmpresa, string $origen): array
    {
        if (!$this->origenes[$origen]['tiene_estado']) {
            return [];
        }
        $tabla = $this->origenes[$origen]['tabla'];
        $amb   = self::AMB;

        $sql = "SELECT a.id AS id_asiento,
                       d.id AS id_documento,
                       a.total_debe AS monto_asiento,
                       d.fecha_emision AS fecha_documento
                FROM asientos_contables_cabecera a
                JOIN {$tabla} d ON d.id = a.id_referencia_origen
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND a.modulo_origen = '{$origen}'
                  AND d.eliminado = false
                  AND d.estado = 'anulado'";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('estado_incoherente', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El documento está anulado pero su asiento sigue activo.", $r['fecha_documento']);
        }
        return $out;
    }

    /**
     * Asiento cuyo tipo_ambiente no coincide con el del documento origen.
     * La incidencia se registra en el ambiente del DOCUMENTO (fuente de verdad),
     * limitándose al ambiente activo de la empresa para encajar con la corrida.
     */
    private function detectarAmbienteIncoherente(int $idEmpresa, string $origen): array
    {
        $tabla = $this->origenes[$origen]['tabla'];
        $amb   = self::AMB;

        $sql = "SELECT a.id AS id_asiento,
                       d.id AS id_documento,
                       a.total_debe AS monto_asiento,
                       CAST(a.tipo_ambiente AS VARCHAR(1)) AS amb_asiento,
                       CAST(d.tipo_ambiente AS VARCHAR(1)) AS amb_doc,
                       d.fecha_emision AS fecha_documento
                FROM asientos_contables_cabecera a
                JOIN {$tabla} d ON d.id = a.id_referencia_origen
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND a.modulo_origen = '{$origen}'
                  AND d.eliminado = false
                  AND CAST(d.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) <> CAST(d.tipo_ambiente AS VARCHAR(1))";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('ambiente_incoherente', $origen, (int) $r['id_documento'], (int) $r['id_asiento'],
                null, (float) $r['monto_asiento'], null,
                "El ambiente del asiento ({$r['amb_asiento']}) difiere del documento ({$r['amb_doc']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Más de un asiento vivo para el mismo documento (modulo_origen + id_referencia_origen). */
    private function detectarDuplicados(int $idEmpresa, ?string $soloOrigen): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND modulo_origen = '{$soloOrigen}'";
        }

        $sql = "SELECT modulo_origen,
                       id_referencia_origen AS id_documento,
                       COUNT(*) AS n,
                       MIN(fecha_asiento) AS fecha_documento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado <> 'anulado'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND modulo_origen IS NOT NULL
                  AND modulo_origen <> 'manual'
                  AND id_referencia_origen IS NOT NULL
                  {$filtroOrigen}
                GROUP BY modulo_origen, id_referencia_origen
                HAVING COUNT(*) > 1";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('duplicado', (string) $r['modulo_origen'], (int) $r['id_documento'], null,
                null, null, null,
                "Existen {$r['n']} asientos para el mismo documento.", $r['fecha_documento']);
        }
        return $out;
    }

    /** Cabecera descuadrada: total_debe <> total_haber. */
    private function detectarDescuadrados(int $idEmpresa, ?string $soloOrigen): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND modulo_origen = '{$soloOrigen}'";
        }

        $sql = "SELECT id AS id_asiento,
                       modulo_origen,
                       id_referencia_origen AS id_documento,
                       total_debe, total_haber,
                       fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado <> 'anulado'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  AND ROUND(total_debe, 2) <> ROUND(total_haber, 2)
                  {$filtroOrigen}";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('descuadrado', (string) ($r['modulo_origen'] ?: 'manual'),
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                (float) $r['total_debe'], (float) $r['total_haber'],
                round((float) $r['total_debe'] - (float) $r['total_haber'], 2),
                "El asiento no cuadra: debe ({$r['total_debe']}) ≠ haber ({$r['total_haber']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Suma del detalle distinta de los totales de la cabecera. */
    private function detectarCabVsDetalle(int $idEmpresa, ?string $soloOrigen): array
    {
        $amb = self::AMB;
        $filtroOrigen = '';
        if ($soloOrigen !== null && $this->esOrigenValido($soloOrigen)) {
            $filtroOrigen = " AND a.modulo_origen = '{$soloOrigen}'";
        }

        $sql = "SELECT a.id AS id_asiento,
                       a.modulo_origen,
                       a.id_referencia_origen AS id_documento,
                       a.total_debe, a.total_haber,
                       COALESCE(SUM(ad.debe), 0)  AS sum_debe,
                       COALESCE(SUM(ad.haber), 0) AS sum_haber,
                       a.fecha_asiento AS fecha_documento
                FROM asientos_contables_cabecera a
                LEFT JOIN asientos_contables_detalle ad
                       ON ad.id_asiento = a.id AND ad.eliminado = false
                WHERE a.id_empresa = :id_empresa
                  AND a.eliminado = false
                  AND a.estado <> 'anulado'
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) = {$amb}
                  {$filtroOrigen}
                GROUP BY a.id, a.modulo_origen, a.id_referencia_origen, a.total_debe, a.total_haber, a.fecha_asiento
                HAVING ROUND(a.total_debe, 2)  <> ROUND(COALESCE(SUM(ad.debe), 0), 2)
                    OR ROUND(a.total_haber, 2) <> ROUND(COALESCE(SUM(ad.haber), 0), 2)";

        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa]);
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizar('cab_vs_detalle', (string) ($r['modulo_origen'] ?: 'manual'),
                $r['id_documento'] !== null ? (int) $r['id_documento'] : null,
                (int) $r['id_asiento'],
                (float) $r['total_debe'], (float) $r['sum_debe'],
                round((float) $r['total_debe'] - (float) $r['sum_debe'], 2),
                "La cabecera no coincide con la suma del detalle (debe {$r['total_debe']} vs {$r['sum_debe']}).",
                $r['fecha_documento']);
        }
        return $out;
    }

    /** Normaliza un hallazgo al formato uniforme usado por el Service. */
    private function normalizar(string $tipo, string $origen, ?int $idDoc, ?int $idAsiento,
        ?float $montoDoc, ?float $montoAsiento, ?float $diferencia, string $detalle, ?string $fecha): array
    {
        return [
            'tipo_hallazgo'   => $tipo,
            'modulo_origen'   => $origen,
            'id_documento'    => $idDoc,
            'id_asiento'      => $idAsiento,
            'monto_documento' => $montoDoc,
            'monto_asiento'   => $montoAsiento,
            'diferencia'      => $diferencia,
            'detalle'         => $detalle,
            'fecha_documento' => $fecha,
        ];
    }

    // ==================================================================
    //  PERSISTENCIA DE INCIDENCIAS (upsert manual preservando revisión)
    // ==================================================================

    /** Clave lógica de una incidencia (debe coincidir con uq_aci_clave_logica). */
    public function claveLogica(array $h): string
    {
        return $h['tipo_hallazgo'] . '|' . $h['modulo_origen'] . '|'
            . ((int) ($h['id_documento'] ?? 0)) . '|' . ((int) ($h['id_asiento'] ?? 0));
    }

    /**
     * Incidencias abiertas (no resueltas) del ambiente activo, indexadas por
     * clave lógica → id. Sirve al Service para diferenciar contra la detección.
     */
    public function getIncidenciasAbiertas(int $idEmpresa, string $ambiente): array
    {
        $sql = "SELECT id, tipo_hallazgo, modulo_origen,
                       COALESCE(id_documento, 0) AS id_documento,
                       COALESCE(id_asiento, 0)   AS id_asiento
                FROM auditoria_contable_incidencias
                WHERE id_empresa = :id_empresa
                  AND tipo_ambiente = :amb
                  AND eliminado = false
                  AND estado_revision <> 'resuelta'";
        $rows = $this->ejecutar($sql, [':id_empresa' => $idEmpresa, ':amb' => $ambiente]);
        $map = [];
        foreach ($rows as $r) {
            $clave = $r['tipo_hallazgo'] . '|' . $r['modulo_origen'] . '|'
                . (int) $r['id_documento'] . '|' . (int) $r['id_asiento'];
            $map[$clave] = (int) $r['id'];
        }
        return $map;
    }

    /**
     * Inserta o actualiza una incidencia. Si ya existe una viva con la misma
     * clave lógica, actualiza montos/detalle y re-sella detectado_at, pero
     * PRESERVA estado_revision y la nota del usuario.
     */
    public function upsertIncidencia(int $idEmpresa, string $ambiente, array $h, int $idUsuario): void
    {
        $existenteId = $this->buscarIdPorClave($idEmpresa, $ambiente, $h);

        if ($existenteId !== null) {
            $sql = "UPDATE auditoria_contable_incidencias
                    SET monto_documento = :md, monto_asiento = :ma, diferencia = :dif,
                        detalle = :det, fecha_documento = :fec,
                        detectado_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP,
                        updated_by = :uid, estado = 'activo'
                    WHERE id = :id";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':md'  => $h['monto_documento'], ':ma' => $h['monto_asiento'], ':dif' => $h['diferencia'],
                ':det' => $h['detalle'], ':fec' => $h['fecha_documento'],
                ':uid' => $idUsuario, ':id' => $existenteId,
            ]);
            return;
        }

        $sql = "INSERT INTO auditoria_contable_incidencias
                    (id_empresa, tipo_ambiente, tipo_hallazgo, modulo_origen,
                     id_documento, id_asiento, monto_documento, monto_asiento, diferencia,
                     detalle, fecha_documento, estado_revision, detectado_at,
                     created_at, updated_at, created_by, updated_by)
                VALUES
                    (:emp, :amb, :tipo, :origen,
                     :iddoc, :idas, :md, :ma, :dif,
                     :det, :fec, 'pendiente', CURRENT_TIMESTAMP,
                     CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :uid, :uid)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':amb' => $ambiente, ':tipo' => $h['tipo_hallazgo'], ':origen' => $h['modulo_origen'],
            ':iddoc' => $h['id_documento'], ':idas' => $h['id_asiento'],
            ':md' => $h['monto_documento'], ':ma' => $h['monto_asiento'], ':dif' => $h['diferencia'],
            ':det' => $h['detalle'], ':fec' => $h['fecha_documento'], ':uid' => $idUsuario,
        ]);
    }

    private function buscarIdPorClave(int $idEmpresa, string $ambiente, array $h): ?int
    {
        $sql = "SELECT id FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb AND eliminado = false
                  AND tipo_hallazgo = :tipo AND modulo_origen = :origen
                  AND COALESCE(id_documento, 0) = :iddoc
                  AND COALESCE(id_asiento, 0)   = :idas
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $idEmpresa, ':amb' => $ambiente,
            ':tipo' => $h['tipo_hallazgo'], ':origen' => $h['modulo_origen'],
            ':iddoc' => (int) ($h['id_documento'] ?? 0), ':idas' => (int) ($h['id_asiento'] ?? 0),
        ]);
        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Marca como resueltas las incidencias cuyos ids ya no fueron detectados. */
    public function marcarResueltas(array $ids, int $idUsuario): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE auditoria_contable_incidencias
                SET estado_revision = 'resuelta', estado = 'resuelto',
                    updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE id IN ($ph) AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute(array_merge([$idUsuario], $ids));
        return $st->rowCount();
    }

    // ==================================================================
    //  LECTURA PARA LA VISTA
    // ==================================================================

    /** Conteo de incidencias abiertas por tipo de hallazgo (para las tarjetas-resumen). */
    public function getResumenPorTipo(int $idEmpresa, string $ambiente): array
    {
        $sql = "SELECT tipo_hallazgo, COUNT(*) AS n
                FROM auditoria_contable_incidencias
                WHERE id_empresa = :emp AND tipo_ambiente = :amb
                  AND eliminado = false AND estado_revision <> 'resuelta'
                GROUP BY tipo_hallazgo";
        $rows = $this->ejecutar($sql, [':emp' => $idEmpresa, ':amb' => $ambiente]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['tipo_hallazgo']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Listado paginado de incidencias con buscador (FiltrosBusqueda) y ordenamiento.
     * Filtra por id_empresa + tipo_ambiente activo (y registros propios si aplica).
     */
    public function getListado(int $idEmpresa, string $ambiente, string $buscar = '',
        int $page = 1, int $perPage = 20, string $ordenCol = 'detectado_at',
        string $ordenDir = 'DESC', ?int $idUsuarioFiltro = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa, ':amb' => $ambiente];

        $where = $this->getBaseWhere($idEmpresa, 'i', $idUsuarioFiltro)
               . " AND i.tipo_ambiente = :amb";

        $parsed = FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (i.detalle ILIKE :buscar OR i.modulo_origen ILIKE :buscar)";
            $params[':buscar'] = '%' . $parsed['texto_libre'] . '%';
        }
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['detalle' => 'i.detalle'],
            'exacto'   => [
                'tipo'   => 'i.tipo_hallazgo', 'tipo_hallazgo' => 'i.tipo_hallazgo',
                'origen' => 'i.modulo_origen', 'modulo' => 'i.modulo_origen',
                'revision' => 'i.estado_revision', 'estado' => 'i.estado_revision',
            ],
            'fecha'    => ['fecha' => 'i.fecha_documento', 'detectado' => 'i.detectado_at'],
            'numerico' => ['diferencia' => 'i.diferencia', 'documento' => 'i.id_documento', 'asiento' => 'i.id_asiento'],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM auditoria_contable_incidencias i $where";
        $total = (int) $this->ejecutar($sqlCount, $params)[0]['count'];

        $allowed = ['detectado_at', 'tipo_hallazgo', 'modulo_origen', 'id_documento', 'id_asiento',
                    'diferencia', 'fecha_documento', 'estado_revision'];
        if (!in_array($ordenCol, $allowed, true)) {
            $ordenCol = 'detectado_at';
        }
        $ordenDir = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        // Número completo del documento (serie-secuencial) resuelto por origen.
        // compras usa columnas *_prov; el resto establecimiento/punto_emision/secuencial.
        $numeroCase = "CASE i.modulo_origen
                        WHEN 'factura_venta'      THEN CONCAT(o_fv.establecimiento,'-',o_fv.punto_emision,'-',o_fv.secuencial)
                        WHEN 'compra'             THEN CONCAT(o_co.establecimiento_prov,'-',o_co.punto_emision_prov,'-',o_co.secuencial_prov)
                        WHEN 'liquidacion_compra' THEN CONCAT(o_lq.establecimiento,'-',o_lq.punto_emision,'-',o_lq.secuencial)
                        WHEN 'nota_credito'       THEN CONCAT(o_nc.establecimiento,'-',o_nc.punto_emision,'-',o_nc.secuencial)
                        WHEN 'retencion_venta'    THEN CONCAT(o_rv.establecimiento,'-',o_rv.punto_emision,'-',o_rv.secuencial)
                        WHEN 'ingreso'            THEN CONCAT(o_in.establecimiento,'-',o_in.punto_emision,'-',o_in.secuencial)
                        WHEN 'egreso'             THEN CONCAT(o_eg.establecimiento,'-',o_eg.punto_emision,'-',o_eg.secuencial)
                       END";

        $sql = "SELECT i.*, u.nombre AS revisado_por_nombre,
                       NULLIF(REPLACE($numeroCase, '--', ''), '') AS documento_numero
                FROM auditoria_contable_incidencias i
                LEFT JOIN usuarios u ON i.revisado_por = u.id
                LEFT JOIN ventas_cabecera          o_fv ON i.modulo_origen = 'factura_venta'      AND o_fv.id = i.id_documento
                LEFT JOIN compras_cabecera         o_co ON i.modulo_origen = 'compra'             AND o_co.id = i.id_documento
                LEFT JOIN liquidaciones_cabecera   o_lq ON i.modulo_origen = 'liquidacion_compra' AND o_lq.id = i.id_documento
                LEFT JOIN notas_credito_cabecera   o_nc ON i.modulo_origen = 'nota_credito'       AND o_nc.id = i.id_documento
                LEFT JOIN retencion_venta_cabecera o_rv ON i.modulo_origen = 'retencion_venta'    AND o_rv.id = i.id_documento
                LEFT JOIN ingresos_cabecera        o_in ON i.modulo_origen = 'ingreso'            AND o_in.id = i.id_documento
                LEFT JOIN egresos_cabecera         o_eg ON i.modulo_origen = 'egreso'             AND o_eg.id = i.id_documento
                $where
                ORDER BY i.$ordenCol $ordenDir
                LIMIT $perPage OFFSET $offset";

        return ['rows' => $this->ejecutar($sql, $params), 'total' => $total];
    }

    public function getIncidenciaPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM auditoria_contable_incidencias
                WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $rows = $this->ejecutar($sql, [':id' => $id, ':emp' => $idEmpresa]);
        return $rows[0] ?? null;
    }

    /** Cambia el estado de revisión de una incidencia (revisada/justificada). */
    public function actualizarRevision(int $id, int $idEmpresa, string $estadoRevision, ?string $nota, int $idUsuario): bool
    {
        $sql = "UPDATE auditoria_contable_incidencias
                SET estado_revision = :rev, nota_revision = :nota,
                    revisado_por = :uid, revisado_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':rev' => $estadoRevision, ':nota' => $nota, ':uid' => $idUsuario,
            ':id' => $id, ':emp' => $idEmpresa,
        ]);
    }

    // ==================================================================
    //  CORRIDAS (historial)
    // ==================================================================

    public function registrarCorrida(array $c, int $idUsuario): int
    {
        $sql = "INSERT INTO auditoria_contable_corridas
                    (id_empresa, tipo_ambiente, tipo_corrida, modulo_origen, fecha_desde, fecha_hasta,
                     total_documentos, total_detectadas, total_anulados, total_regenerados, total_omitidos,
                     estado, mensaje, ejecutado_at, created_at, updated_at, created_by, updated_by)
                VALUES
                    (:emp, :amb, :tipo, :origen, :fd, :fh,
                     :tdoc, :tdet, :tanu, :treg, :tomi,
                     :estado, :msg, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :uid, :uid)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp' => $c['id_empresa'], ':amb' => $c['tipo_ambiente'], ':tipo' => $c['tipo_corrida'],
            ':origen' => $c['modulo_origen'] ?? null, ':fd' => $c['fecha_desde'] ?? null, ':fh' => $c['fecha_hasta'] ?? null,
            ':tdoc' => $c['total_documentos'] ?? 0, ':tdet' => $c['total_detectadas'] ?? 0,
            ':tanu' => $c['total_anulados'] ?? 0, ':treg' => $c['total_regenerados'] ?? 0, ':tomi' => $c['total_omitidos'] ?? 0,
            ':estado' => $c['estado'] ?? 'ok', ':msg' => $c['mensaje'] ?? null, ':uid' => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function getCorridas(int $idEmpresa, string $ambiente, int $limit = 50): array
    {
        $sql = "SELECT c.*, u.nombre AS ejecutado_por
                FROM auditoria_contable_corridas c
                LEFT JOIN usuarios u ON c.created_by = u.id
                WHERE c.id_empresa = :emp AND c.tipo_ambiente = :amb AND c.eliminado = false
                ORDER BY c.ejecutado_at DESC
                LIMIT " . (int) $limit;
        return $this->ejecutar($sql, [':emp' => $idEmpresa, ':amb' => $ambiente]);
    }

    // ==================================================================
    //  REGENERACIÓN MASIVA (helpers; la orquestación va en el Service)
    // ==================================================================

    /**
     * Asientos vivos de un origen en el ambiente activo, opcionalmente acotados
     * por rango de fecha_asiento. Devuelve id, id_referencia_origen y fecha.
     */
    public function getAsientosDeOrigen(int $idEmpresa, string $origen, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $amb = self::AMB;
        $params = [':id_empresa' => $idEmpresa];
        $rango = '';
        if ($fechaDesde !== null) { $rango .= " AND fecha_asiento >= :fd"; $params[':fd'] = $fechaDesde; }
        if ($fechaHasta !== null) { $rango .= " AND fecha_asiento <= :fh"; $params[':fh'] = $fechaHasta; }

        $sql = "SELECT id, id_referencia_origen, fecha_asiento
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND modulo_origen = '{$origen}'
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                  {$rango}";
        return $this->ejecutar($sql, $params);
    }

    /**
     * Asientos vivos asociados a un documento concreto (para resolver duplicados:
     * el usuario ve la lista y elige cuál anular).
     */
    public function getAsientosDeDocumento(int $idEmpresa, string $origen, int $idDocumento): array
    {
        if (!$this->esOrigenValido($origen)) {
            return [];
        }
        $amb = self::AMB;
        $sql = "SELECT id, numero_comprobante, tipo_comprobante, fecha_asiento,
                       total_debe, total_haber, estado, concepto, created_at, created_by
                FROM asientos_contables_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND modulo_origen = '{$origen}'
                  AND id_referencia_origen = :iddoc
                  AND CAST(tipo_ambiente AS VARCHAR(1)) = {$amb}
                ORDER BY id ASC";
        return $this->ejecutar($sql, [':id_empresa' => $idEmpresa, ':iddoc' => $idDocumento]);
    }

    /**
     * ¿La fecha cae dentro de un período contable CERRADO (status = 0)?
     * Se usa como salvaguarda antes de anular/regenerar.
     */
    public function fechaEnPeriodoCerrado(int $idEmpresa, string $fecha): bool
    {
        $sql = "SELECT 1 FROM periodos_contables
                WHERE id_empresa = :emp AND eliminado = false AND status = 0
                  AND :fec BETWEEN fecha_inicial AND fecha_final
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':emp' => $idEmpresa, ':fec' => $fecha]);
        return $st->fetchColumn() !== false;
    }

    /** Anula lógicamente un asiento (cabecera y detalle). */
    public function anularAsiento(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $sqlCab = "UPDATE asientos_contables_cabecera
                   SET eliminado = true, estado = 'anulado',
                       deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                   WHERE id = :id AND id_empresa = :emp AND eliminado = false";
        $st = $this->db->prepare($sqlCab);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento, ':emp' => $idEmpresa]);

        $sqlDet = "UPDATE asientos_contables_detalle
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                       updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                   WHERE id_asiento = :id AND eliminado = false";
        $st = $this->db->prepare($sqlDet);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento]);
    }

    /**
     * Corrige el ambiente de un asiento heredándolo del documento origen
     * (la fuente de verdad). Solo afecta asientos vivos. Devuelve filas afectadas.
     */
    public function corregirAmbienteAsiento(int $idAsiento, string $origen, int $idEmpresa, int $idUsuario): int
    {
        $tabla = $this->getTablaOrigen($origen);
        if ($tabla === null) {
            return 0;
        }
        $sql = "UPDATE asientos_contables_cabecera a
                SET tipo_ambiente = CAST(d.tipo_ambiente AS VARCHAR(1)),
                    updated_at = CURRENT_TIMESTAMP, updated_by = :uid
                FROM {$tabla} d
                WHERE a.id = :id AND a.id_empresa = :emp AND a.eliminado = false
                  AND d.id = a.id_referencia_origen AND d.eliminado = false
                  AND CAST(a.tipo_ambiente AS VARCHAR(1)) <> CAST(d.tipo_ambiente AS VARCHAR(1))";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':id' => $idAsiento, ':emp' => $idEmpresa]);
        return $st->rowCount();
    }

    /** Desvincula el asiento del documento (deja id_asiento_contable en NULL). */
    public function desvincularDocumento(string $origen, int $idDocumento, int $idEmpresa): void
    {
        $tabla = $this->getTablaOrigen($origen);
        if ($tabla === null) {
            return;
        }
        $sql = "UPDATE {$tabla} SET id_asiento_contable = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :emp";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDocumento, ':emp' => $idEmpresa]);
    }
}

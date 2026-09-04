<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\repositories\BaseRepository;
use PDO;

/**
 * Reporte de Retenciones de Venta Pendientes.
 *
 * Facturas de venta AUTORIZADAS de la empresa que no tienen ningún comprobante
 * de retención asociado. El vínculo factura ↔ retención es el mismo que usa
 * Cuentas por Cobrar para descontar lo retenido del saldo
 * (AbonosVentaSql::cteRetenidoPorFactura): por `id_venta` o por número de
 * documento de sustento normalizado a 15 dígitos. Así, una factura que en CxC
 * ya muestra "Retención" nunca aparece aquí como pendiente, y viceversa.
 *
 * Además cruza con `retencion_venta_avisos` para saber cuántos avisos por
 * correo se le han enviado a cada factura y cuándo fue el último.
 */
class ReporteRetencionesPendientesRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('retencion_venta_avisos');
    }

    private function q(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Ambiente actual de la empresa. Se interpola el id (int validado) porque
     * el mismo valor aparece varias veces en la consulta y PDO en pgsql no
     * admite repetir el mismo placeholder.
     */
    private function ambienteEmpresa(int $idEmpresa): string
    {
        return "(SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = {$idEmpresa})";
    }

    /** Número EEE-PPP-SSSSSSSSS de la factura (alias v). */
    private const NUMERO = "(v.establecimiento || '-' || v.punto_emision || '-' || v.secuencial)";

    // ── WHERE de filtros ─────────────────────────────────────────────────────

    /**
     * Arma las condiciones de filtro sobre la factura (alias v) y el cliente (alias c).
     * Todos los filtros se combinan con AND; el JS precarga Desde/Hasta a partir de
     * Año/Mes, así que en la práctica no compiten entre sí.
     */
    private function whereFiltros(array $f, array &$params): string
    {
        $w = '';
        if (!empty($f['anio'])) {
            $w .= " AND EXTRACT(YEAR FROM v.fecha_emision) = :anio";
            $params[':anio'] = (int) $f['anio'];
        }
        if (!empty($f['mes'])) {
            $w .= " AND EXTRACT(MONTH FROM v.fecha_emision) = :mes";
            $params[':mes'] = (int) $f['mes'];
        }
        if (!empty($f['fecha_desde'])) {
            $w .= " AND v.fecha_emision >= :fdesde";
            $params[':fdesde'] = $f['fecha_desde'];
        }
        if (!empty($f['fecha_hasta'])) {
            $w .= " AND v.fecha_emision <= :fhasta";
            $params[':fhasta'] = $f['fecha_hasta'];
        }
        if (!empty($f['id_cliente'])) {
            $w .= " AND v.id_cliente = :id_cliente";
            $params[':id_cliente'] = (int) $f['id_cliente'];
        }

        // Aviso enviado: SIN (nunca avisada) / CON (al menos un aviso)
        $aviso = strtoupper((string) ($f['aviso'] ?? 'TODOS'));
        if ($aviso === 'SIN') {
            $w .= " AND COALESCE(av.n_avisos, 0) = 0";
        } elseif ($aviso === 'CON') {
            $w .= " AND COALESCE(av.n_avisos, 0) > 0";
        }

        // Búsqueda libre por palabras: número, cliente, identificación, correo
        $qtxt = trim((string) ($f['buscar'] ?? ''));
        if ($qtxt !== '') {
            $campos = [self::NUMERO, 'c.nombre', 'c.identificacion', 'c.email'];
            foreach (preg_split('/\s+/', $qtxt) ?: [] as $i => $palabra) {
                if ($palabra === '') continue;
                $ph = ':bq' . $i;
                $params[$ph] = '%' . $palabra . '%';
                $w .= ' AND (' . implode(' OR ', array_map(fn ($c) => "$c ILIKE $ph", $campos)) . ')';
            }
        }
        return $w;
    }

    /**
     * FROM/JOIN/WHERE base de una factura pendiente de retención.
     * `$idUsuarioFiltro` aplica la regla de registros propios (CLAUDE.md §6).
     */
    private function fromPendientes(int $idEmpresa, ?int $idUsuarioFiltro, array &$params): string
    {
        $params[':id_empresa'] = $idEmpresa;
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $cteRetenido = AbonosVentaSql::cteRetenidoPorFactura((string) $idEmpresa);
        $baseWhere   = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);

        return "FROM ventas_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN ( {$cteRetenido} ) rt ON rt.id_venta = v.id
                LEFT JOIN (
                    SELECT a.id_venta, COUNT(*) AS n_avisos, MAX(a.fecha_envio) AS ultimo_aviso
                    FROM retencion_venta_avisos a
                    WHERE a.id_empresa = {$idEmpresa} AND a.eliminado = false
                    GROUP BY a.id_venta
                ) av ON av.id_venta = v.id
                {$baseWhere}
                  AND v.estado IN ('autorizado', 'autorizada')
                  AND v.tipo_ambiente = " . $this->ambienteEmpresa($idEmpresa) . "
                  AND COALESCE(rt.total_retenido, 0) <= 0";
    }

    /** Columnas comunes de la fila del reporte. */
    private function selectFila(): string
    {
        return "SELECT v.id,
                       v.id_cliente,
                       v.fecha_emision,
                       " . self::NUMERO . " AS numero_factura,
                       v.clave_acceso,
                       v.total_sin_impuestos,
                       (v.importe_total - v.total_sin_impuestos - COALESCE(v.propina, 0)) AS impuestos,
                       v.importe_total,
                       (CURRENT_DATE - v.fecha_emision) AS dias,
                       c.nombre         AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       c.tipo_id        AS cliente_tipo_id,
                       c.email          AS cliente_email,
                       c.telefono       AS cliente_telefono,
                       COALESCE(av.n_avisos, 0) AS n_avisos,
                       av.ultimo_aviso";
    }

    // ── Consultas públicas ───────────────────────────────────────────────────

    /** Listado de facturas sin retención (una fila por factura). $limite = 0 => sin tope (exportación). */
    public function getPendientes(int $idEmpresa, array $f, ?int $idUsuarioFiltro = null, int $limite = 5000): array
    {
        $params = [];
        $sql = $this->selectFila() . "\n" . $this->fromPendientes($idEmpresa, $idUsuarioFiltro, $params)
             . $this->whereFiltros($f, $params)
             . " ORDER BY v.fecha_emision DESC, v.secuencial DESC";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        return $this->q($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales para las tarjetas KPI. */
    public function getEstadisticas(int $idEmpresa, array $f, ?int $idUsuarioFiltro = null): array
    {
        $params = [];
        $sql = "SELECT COUNT(*)                                   AS n_facturas,
                       COUNT(DISTINCT v.id_cliente)              AS n_clientes,
                       COALESCE(SUM(v.total_sin_impuestos), 0)   AS total_subtotal,
                       COALESCE(SUM(v.importe_total), 0)         AS total_general,
                       COUNT(*) FILTER (WHERE COALESCE(av.n_avisos, 0) > 0) AS n_avisadas,
                       COUNT(*) FILTER (WHERE COALESCE(NULLIF(TRIM(c.email), ''), '') = '') AS n_sin_correo
                " . $this->fromPendientes($idEmpresa, $idUsuarioFiltro, $params)
                  . $this->whereFiltros($f, $params);
        return $this->q($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Una factura pendiente por id. Devuelve null si no existe, no es de la
     * empresa, no está autorizada o YA tiene retención (por eso reutiliza el
     * mismo FROM del listado: el aviso solo se envía si sigue pendiente).
     */
    public function getPendientePorId(int $idVenta, int $idEmpresa, ?int $idUsuarioFiltro = null): ?array
    {
        $params = [':id_venta' => $idVenta];
        $sql = $this->selectFila() . "\n" . $this->fromPendientes($idEmpresa, $idUsuarioFiltro, $params)
             . " AND v.id = :id_venta";
        return $this->q($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Años con facturas autorizadas (para el selector Año). */
    public function getAnios(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                FROM ventas_cabecera
                WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado', 'autorizada')
                ORDER BY anio DESC";
        return array_map('intval', $this->q($sql, [':e' => $idEmpresa])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Autocomplete de clientes. */
    public function buscarClientes(int $idEmpresa, string $q): array
    {
        $sql = "SELECT id, nombre, identificacion AS ident, email
                FROM clientes
                WHERE id_empresa = :e AND eliminado = false
                  AND (nombre ILIKE :q OR identificacion ILIKE :q)
                ORDER BY nombre LIMIT 15";
        return $this->q($sql, [':e' => $idEmpresa, ':q' => '%' . trim($q) . '%'])->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Avisos enviados ──────────────────────────────────────────────────────

    /** Inserta el registro de un aviso enviado (dentro de la transacción del Service). */
    public function registrarAviso(array $d): int
    {
        $sql = "INSERT INTO retencion_venta_avisos
                    (id_empresa, id_venta, id_cliente, tipo_envio, id_lote, correo_destino, asunto,
                     fecha_envio, created_by, updated_by)
                VALUES
                    (:id_empresa, :id_venta, :id_cliente, :tipo_envio, :id_lote, :correo, :asunto,
                     CURRENT_TIMESTAMP, :usuario, :usuario2)
                RETURNING id";
        $st = $this->q($sql, [
            ':id_empresa' => (int) $d['id_empresa'],
            ':id_venta'   => (int) $d['id_venta'],
            ':id_cliente' => !empty($d['id_cliente']) ? (int) $d['id_cliente'] : null,
            ':tipo_envio' => $d['tipo_envio'] === 'AGRUPADO' ? 'AGRUPADO' : 'INDIVIDUAL',
            ':id_lote'    => $d['id_lote'] ?? null,
            ':correo'     => mb_substr((string) $d['correo_destino'], 0, 500),
            ':asunto'     => mb_substr((string) ($d['asunto'] ?? ''), 0, 255),
            ':usuario'    => (int) $d['created_by'],
            ':usuario2'   => (int) $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    /** Historial de avisos de una factura (modal "Avisos enviados"). */
    public function getAvisosPorVenta(int $idVenta, int $idEmpresa): array
    {
        $sql = "SELECT a.id, a.tipo_envio, a.id_lote, a.correo_destino, a.asunto, a.fecha_envio,
                       COALESCE(u.nombre, '') AS usuario
                FROM retencion_venta_avisos a
                LEFT JOIN usuarios u ON u.id = a.created_by
                WHERE a.id_empresa = :e AND a.id_venta = :v AND a.eliminado = false
                ORDER BY a.fecha_envio DESC";
        return $this->q($sql, [':e' => $idEmpresa, ':v' => $idVenta])->fetchAll(PDO::FETCH_ASSOC);
    }
}

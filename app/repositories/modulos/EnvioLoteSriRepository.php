<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo "Envío en lote al SRI".
 *
 * Responsable de:
 *   - Listar los comprobantes ENVIABLES (pendientes de autorización) de los
 *     4 tipos soportados, filtrados por ambiente, rango de fechas y texto.
 *   - Persistir y consultar los lotes (sri_lotes) y sus ítems (sri_lote_items).
 *
 * No contiene lógica de negocio (§3): solo acceso a datos con PDO preparado.
 */
class EnvioLoteSriRepository extends BaseRepository
{
    /** Tipos soportados y su tabla/estados no enviables. */
    private const TIPOS = ['factura_venta', 'nota_credito', 'retencion_compra', 'liquidacion_compra'];

    public function __construct()
    {
        parent::__construct('sri_lotes');
    }

    // ── Listado de comprobantes enviables ───────────────────────────────────

    /**
     * Comprobantes pendientes de autorización, listos para enviarse al SRI.
     *
     * @param int         $idEmpresa
     * @param string[]    $tipos            Subconjunto de self::TIPOS. Vacío = todos.
     * @param string      $tipoAmbiente     '1' pruebas | '2' producción
     * @param string      $fechaDesde       Y-m-d
     * @param string      $fechaHasta       Y-m-d
     * @param string      $buscar           Texto libre (número o contraparte)
     * @param int|null    $idUsuarioFiltro  Registros propios (§6). null = todos.
     * @param int         $limite           Tope de filas devueltas.
     * @return array<int,array<string,mixed>>
     */
    public function getComprobantesEnviables(
        int $idEmpresa,
        array $tipos,
        string $tipoAmbiente,
        string $fechaDesde,
        string $fechaHasta,
        string $buscar = '',
        ?int $idUsuarioFiltro = null,
        int $limite = 1000
    ): array {
        $tipos = array_values(array_intersect($tipos ?: self::TIPOS, self::TIPOS));
        if (empty($tipos)) {
            return [];
        }

        $buscar = trim($buscar);
        $conBuscar  = $buscar !== '';
        $conUsuario = $idUsuarioFiltro !== null;

        $params = [
            ':id_empresa' => $idEmpresa,
            ':amb'        => $tipoAmbiente,
            ':desde'      => $fechaDesde,
            ':hasta'      => $fechaHasta,
        ];
        if ($conUsuario) { $params[':uf'] = $idUsuarioFiltro; }
        if ($conBuscar)  { $params[':buscar'] = '%' . $buscar . '%'; }

        $subs = [];
        foreach ($tipos as $tipo) {
            $subs[] = $this->subqueryEnviables($tipo, $conUsuario, $conBuscar);
        }

        $sql = implode("\nUNION ALL\n", $subs)
             . "\nORDER BY fecha_emision DESC, tipo, id DESC"
             . "\nLIMIT " . (int) $limite;

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Construye la subconsulta normalizada para un tipo de comprobante.
     * Todas devuelven las mismas columnas para poder unirlas con UNION ALL.
     */
    private function subqueryEnviables(string $tipo, bool $conUsuario, bool $conBuscar): string
    {
        // [alias, tabla, joinContraparte, exprContraparte, exprTotal, estadosNoEnviables]
        $map = [
            'factura_venta' => [
                'v', 'ventas_cabecera',
                'LEFT JOIN clientes c ON c.id = v.id_cliente',
                'c.nombre', 'v.importe_total',
                "('autorizado','aprobado','anulado')",
            ],
            'nota_credito' => [
                'nc', 'notas_credito_cabecera',
                'LEFT JOIN clientes c ON c.id = nc.id_cliente',
                'c.nombre', 'nc.importe_total',
                "('autorizado','aprobado','anulado')",
            ],
            'retencion_compra' => [
                'r', 'retencion_compra_cabecera',
                'LEFT JOIN proveedores p ON p.id = r.id_proveedor',
                'p.razon_social', 'r.total_retenido',
                "('autorizada','aprobada','anulada','anulado')",
            ],
            'liquidacion_compra' => [
                'l', 'liquidaciones_cabecera',
                'LEFT JOIN proveedores p ON p.id = l.id_proveedor',
                'p.razon_social', 'l.importe_total',
                "('autorizado','aprobado','anulado')",
            ],
        ];

        [$a, $tabla, $join, $contraparte, $total, $estadosNo] = $map[$tipo];

        $numero = "CONCAT({$a}.establecimiento,'-',{$a}.punto_emision,'-',LPAD({$a}.secuencial::text,9,'0'))";

        $where  = "{$a}.id_empresa = :id_empresa AND {$a}.eliminado = FALSE"
                . " AND {$a}.tipo_ambiente = :amb"
                . " AND {$a}.clave_acceso IS NOT NULL AND {$a}.clave_acceso <> ''"
                . " AND {$a}.estado NOT IN {$estadosNo}"
                . " AND {$a}.fecha_emision BETWEEN :desde AND :hasta";

        if ($conUsuario) {
            $where .= " AND {$a}.id_usuario = :uf";
        }
        if ($conBuscar) {
            $where .= " AND ({$numero} ILIKE :buscar OR " . $contraparte . " ILIKE :buscar)";
        }

        return "SELECT '{$tipo}' AS tipo,
                       {$a}.id AS id,
                       {$numero} AS numero,
                       {$a}.fecha_emision AS fecha_emision,
                       COALESCE({$contraparte}, '') AS contraparte,
                       {$total} AS total,
                       {$a}.estado AS estado,
                       {$a}.tipo_ambiente AS tipo_ambiente
                FROM {$tabla} {$a}
                {$join}
                WHERE {$where}";
    }

    // ── Lotes ───────────────────────────────────────────────────────────────

    public function crearLote(int $idEmpresa, int $idUsuario, string $tipoAmbiente, int $total, string $filtrosJson): int
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_lotes (id_empresa, estado, tipo_ambiente, total, filtros_json, created_by, updated_by)
             VALUES (:ie, 'pendiente', :amb, :total, :filtros, :uid, :uid)
             RETURNING id"
        );
        $st->execute([
            ':ie'      => $idEmpresa,
            ':amb'     => $tipoAmbiente,
            ':total'   => $total,
            ':filtros' => $filtrosJson,
            ':uid'     => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertarItem(int $idLote, int $idEmpresa, string $tipo, int $idComprobante, ?string $numero, ?string $fechaEmision): void
    {
        $st = $this->db->prepare(
            "INSERT INTO sri_lote_items (id_lote, id_empresa, tipo_comprobante, id_comprobante, numero, fecha_emision, estado)
             VALUES (:il, :ie, :tipo, :idc, :num, :fecha, 'pendiente')"
        );
        $st->execute([
            ':il'    => $idLote,
            ':ie'    => $idEmpresa,
            ':tipo'  => $tipo,
            ':idc'   => $idComprobante,
            ':num'   => $numero,
            ':fecha' => $fechaEmision ?: null,
        ]);
    }

    public function getLote(int $idLote, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT *,
                    TO_CHAR(created_at,    'DD-MM-YYYY HH24:MI:SS') AS created_at_fmt,
                    TO_CHAR(iniciado_at,   'DD-MM-YYYY HH24:MI:SS') AS iniciado_at_fmt,
                    TO_CHAR(finalizado_at, 'DD-MM-YYYY HH24:MI:SS') AS finalizado_at_fmt
             FROM sri_lotes
             WHERE id = :id AND id_empresa = :ie AND eliminado = FALSE"
        );
        $st->execute([':id' => $idLote, ':ie' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Lote sin validar empresa (uso exclusivo del worker CLI). */
    public function getLoteCli(int $idLote): ?array
    {
        $st = $this->db->prepare("SELECT * FROM sri_lotes WHERE id = :id AND eliminado = FALSE");
        $st->execute([':id' => $idLote]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getItems(int $idLote, int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, TO_CHAR(i.processed_at, 'DD-MM-YYYY HH24:MI:SS') AS processed_at_fmt
             FROM sri_lote_items i
             INNER JOIN sri_lotes lt ON lt.id = i.id_lote
             WHERE i.id_lote = :il AND lt.id_empresa = :ie
             ORDER BY i.id ASC"
        );
        $st->execute([':il' => $idLote, ':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reclama atómicamente el siguiente ítem pendiente del lote y lo marca
     * 'procesando' (FOR UPDATE SKIP LOCKED evita doble procesamiento).
     * Devuelve el ítem reclamado o null si no quedan pendientes.
     */
    public function reclamarSiguienteItem(int $idLote): ?array
    {
        $st = $this->db->prepare(
            "UPDATE sri_lote_items
                SET estado = 'procesando', intentos = intentos + 1
              WHERE id = (
                    SELECT id FROM sri_lote_items
                     WHERE id_lote = :il AND estado = 'pendiente'
                     ORDER BY id ASC
                     LIMIT 1
                     FOR UPDATE SKIP LOCKED
              )
              RETURNING *"
        );
        $st->execute([':il' => $idLote]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Actualiza el resultado de un ítem tras el envío. */
    public function marcarItem(int $idItem, string $estado, ?string $mensaje, ?string $numeroAutorizacion): void
    {
        $st = $this->db->prepare(
            "UPDATE sri_lote_items
                SET estado = :estado, mensaje = :msg, numero_autorizacion = :na, processed_at = NOW()
              WHERE id = :id"
        );
        $st->execute([
            ':estado' => $estado,
            ':msg'    => $mensaje,
            ':na'     => $numeroAutorizacion,
            ':id'     => $idItem,
        ]);
    }

    /** Suma 1 a procesados y, según resultado, a exitosos o fallidos. */
    public function incrementarContadores(int $idLote, bool $exito): void
    {
        $col = $exito ? 'exitosos' : 'fallidos';
        $st = $this->db->prepare(
            "UPDATE sri_lotes
                SET procesados = procesados + 1, {$col} = {$col} + 1, updated_at = NOW()
              WHERE id = :id"
        );
        $st->execute([':id' => $idLote]);
    }

    public function marcarLoteEstado(int $idLote, string $estado, bool $tocarInicio = false, bool $tocarFin = false): void
    {
        $sets = ['estado = :estado', 'updated_at = NOW()'];
        if ($tocarInicio) { $sets[] = 'iniciado_at = NOW()'; }
        if ($tocarFin)    { $sets[] = 'finalizado_at = NOW()'; }
        $sql = "UPDATE sri_lotes SET " . implode(', ', $sets) . " WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':estado' => $estado, ':id' => $idLote]);
    }

    public function getEstadoLote(int $idLote): ?array
    {
        $st = $this->db->prepare("SELECT id, estado, total, procesados, exitosos, fallidos FROM sri_lotes WHERE id = :id");
        $st->execute([':id' => $idLote]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Historial de lotes de la empresa. */
    public function getHistorialLotes(int $idEmpresa, int $limite = 30): array
    {
        $st = $this->db->prepare(
            "SELECT lt.*,
                    TO_CHAR(lt.created_at,    'DD-MM-YYYY HH24:MI:SS') AS created_at_fmt,
                    TO_CHAR(lt.finalizado_at, 'DD-MM-YYYY HH24:MI:SS') AS finalizado_at_fmt,
                    u.nombre AS usuario_nombre
             FROM sri_lotes lt
             LEFT JOIN usuarios u ON u.id = lt.created_by
             WHERE lt.id_empresa = :ie AND lt.eliminado = FALSE
             ORDER BY lt.id DESC
             LIMIT " . (int) $limite
        );
        $st->execute([':ie' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

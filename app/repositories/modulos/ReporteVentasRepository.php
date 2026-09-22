<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\repositories\BaseRepository;
use PDO;

class ReporteVentasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    // ── Alcance por empresa (una empresa o varios establecimientos del mismo RUC) ──
    //
    // Los reportes reciben `int|array $idEmpresa`: la empresa activa (int, normal) o los
    // establecimientos del grupo RUC cuando la matriz pide el consolidado (lo resuelve el
    // controller con EmpresaRepository::getIdsConsolidadoDesdeMatriz, nunca el cliente).

    /** Lista `1,2,3` de ids validados, para interpolar en `IN (...)`. */
    private string $inEmp = '0';

    private function setAlcance(int|array $idEmpresa): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $idEmpresa), static fn ($i) => $i > 0)));
        if (!$ids) {
            throw new \InvalidArgumentException('Reporte de ventas: id_empresa requerido.');
        }
        $this->inEmp = implode(',', $ids);
    }

    /** Ambiente actual de la empresa DUEÑA del documento (correlacionado por fila). */
    private function condAmbiente(string $alias): string
    {
        return "{$alias}.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = {$alias}.id_empresa)";
    }

    /**
     * Consolidado: `productos` es por establecimiento, así que el filtro por id de producto
     * se expande a los productos hermanos con el MISMO código en las demás empresas del
     * alcance. Devuelve la unión con los ids originales.
     */
    public function expandirProductosPorCodigo(array $idsProducto, int|array $idsEmpresa): array
    {
        $idsProducto = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        $idsEmp      = array_values(array_unique(array_filter(array_map('intval', (array) $idsEmpresa))));
        if (!$idsProducto || !$idsEmp) {
            return $idsProducto;
        }
        $params = [];
        $inP = []; foreach ($idsProducto as $i => $id) { $inP[] = ":xp{$i}"; $params[":xp{$i}"] = $id; }
        $inE = []; foreach ($idsEmp as $i => $id)      { $inE[] = ":xe{$i}"; $params[":xe{$i}"] = $id; }
        $st = $this->db->prepare("SELECT DISTINCT p2.id
                                  FROM productos p1
                                  JOIN productos p2 ON p2.codigo = p1.codigo AND p2.eliminado = false
                                                   AND p2.id_empresa IN (" . implode(',', $inE) . ")
                                  WHERE p1.id IN (" . implode(',', $inP) . ") AND COALESCE(TRIM(p1.codigo), '') <> ''");
        $st->execute($params);
        return array_values(array_unique(array_merge($idsProducto, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)))));
    }

    /**
     * Consolidado: `marcas` y `categorias` son por establecimiento, así que el filtro por
     * marca/categoría se expande a las homónimas (mismo nombre, sin distinguir mayúsculas ni
     * espacios) de las demás empresas del alcance. Devuelve la unión con el id original.
     */
    public function expandirCatalogoPorNombre(string $tabla, int $id, int|array $idsEmpresa): array
    {
        if (!in_array($tabla, ['marcas', 'categorias'], true)) {
            throw new \InvalidArgumentException("Catálogo no admitido: {$tabla}");
        }
        $idsEmp = array_values(array_unique(array_filter(array_map('intval', (array) $idsEmpresa))));
        if ($id <= 0 || !$idsEmp) {
            return $id > 0 ? [$id] : [];
        }
        $params = [':id' => $id];
        $inE = [];
        foreach ($idsEmp as $i => $e) { $inE[] = ":xe{$i}"; $params[":xe{$i}"] = $e; }
        $st = $this->db->prepare("SELECT t2.id
                                  FROM {$tabla} t1
                                  JOIN {$tabla} t2 ON LOWER(TRIM(t2.nombre)) = LOWER(TRIM(t1.nombre))
                                                  AND t2.eliminado = false
                                                  AND t2.id_empresa IN (" . implode(',', $inE) . ")
                                  WHERE t1.id = :id");
        $st->execute($params);
        return array_values(array_unique(array_merge([$id], array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)))));
    }

    /**
     * Configuración de la fuente de datos según el tipo de documento:
     *  - FACTURA         → ventas_*
     *  - RECIBO          → recibos_venta_*
     *  - NOTA_CREDITO    → notas_credito_*  (notas de crédito en ventas)
     * Todas las tablas son espejo entre sí (FKs distintas). Las notas de crédito
     * no llevan vendedor ni retenciones.
     *
     * El tipo especial FACTURA_MENOS_NC (facturas − notas de crédito) NO tiene
     * fuente propia: se resuelve combinando FACTURA y NOTA_CREDITO (ver esNeto()).
     */
    private function fuente(array $filtros): array
    {
        $tipo = $filtros['tipo_documento'] ?? 'FACTURA';

        if ($tipo === 'RECIBO') {
            return [
                'cab'         => 'recibos_venta_cabecera',
                'det'         => 'recibos_venta_detalle',
                'imp'         => 'recibos_venta_detalle_impuestos',
                'adic'        => 'recibos_venta_adicional',
                'fk_det'      => 'id_recibo',          // detalle.id_recibo = cabecera.id
                'fk_imp'      => 'id_recibo_detalle',  // impuestos.id_recibo_detalle = detalle.id
                'fk_adic'     => 'id_recibo',
                'estado_ok'   => $this->condEstado("{alias}.estado NOT IN ('borrador', 'anulado', 'facturado')", $filtros),
                'retenciones' => false,
                'clave'       => false,
                'vendedor'    => true,
            ];
        }

        if ($tipo === 'NOTA_CREDITO') {
            return [
                'cab'         => 'notas_credito_cabecera',
                'det'         => 'notas_credito_detalle',
                'imp'         => 'notas_credito_detalle_impuestos',
                'adic'        => 'notas_credito_adicional',
                'fk_det'      => 'id_nota_credito',
                'fk_imp'      => 'id_nota_credito_detalle',
                'fk_adic'     => 'id_nota_credito',
                'estado_ok'   => $this->condEstado("{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')", $filtros),
                'retenciones' => false,
                'clave'       => true,
                'vendedor'    => false,   // notas_credito_cabecera no tiene id_vendedor
            ];
        }

        return [
            'cab'         => 'ventas_cabecera',
            'det'         => 'ventas_detalle',
            'imp'         => 'ventas_detalle_impuestos',
            'adic'        => 'ventas_adicional',
            'fk_det'      => 'id_venta',
            'fk_imp'      => 'id_venta_detalle',
            'fk_adic'     => 'id_venta',
            'estado_ok'   => $this->condEstado("{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')", $filtros),
            'retenciones' => true,
            'clave'       => true,
            'vendedor'    => true,
        ];
    }

    /**
     * Condición de estado según el selector "Borradores" del reporte:
     *  - EXCLUIR (por defecto, y también cuando no llega, como en la API móvil o en
     *    Índices Financieros): solo `$validos`, los documentos que cuentan como venta.
     *  - INCLUIR: `$validos` + los borradores.
     *  - SOLO:    únicamente los borradores.
     * El borrador se reconoce igual que en getResumenEstados() (LOWER(estado)), así el
     * modo SOLO coincide con el contador "Borr." de la pantalla.
     */
    private function condEstado(string $validos, array $filtros): string
    {
        $esBorrador = "LOWER({alias}.estado) = 'borrador'";
        return match (strtoupper((string) ($filtros['borradores'] ?? ''))) {
            'INCLUIR' => "({$validos} OR {$esBorrador})",
            'SOLO'    => $esBorrador,
            default   => $validos,
        };
    }

    /** ¿El reporte es el neto "Facturas − Notas de crédito"? */
    private function esNeto(array $filtros): bool
    {
        return ($filtros['tipo_documento'] ?? '') === 'FACTURA_MENOS_NC';
    }

    // ── Orden de las filas ────────────────────────────────────────────────────
    //
    // La pantalla ordena haciendo clic en las cabeceras y manda la columna elegida en los
    // filtros (`orden_col`/`orden_dir`), que viajan dentro del formulario: por eso el Excel
    // y el PDF —que repiten la consulta con esos mismos filtros— salen en el mismo orden.

    /**
     * Columnas por las que se puede ordenar cada agrupación: clave (la misma que manda la
     * pantalla en `data-sort`) => expresión SQL, con `{dir}` donde va la dirección.
     *
     * La clave es además el nombre del campo en la fila devuelta, para poder aplicar el
     * mismo orden en PHP al neto "Facturas − NC", que se combina fuera de SQL (combinarNeto).
     *
     * `_def` es el orden histórico de cada modo: se usa cuando no llega ninguna columna
     * (API móvil, Índices Financieros) y cuando la que llega no aplica a esa agrupación
     * (p. ej. una preferencia guardada desde otro modo). NUNCA se interpola lo que manda el
     * cliente: solo salen de aquí las expresiones que se concatenan al SQL.
     */
    private const ORDEN_COLUMNAS = [
        'NINGUNO' => [
            '_def'            => ['fecha_emision', 'DESC'],
            'fecha_emision'   => 'v.fecha_emision {dir}, v.secuencial {dir}',
            'numero_factura'  => 'numero_factura {dir}',
            'cliente_nombre'  => 'cliente_nombre {dir}',
            'estado'          => 'estado {dir}',
            'vendedor_nombre' => 'vendedor_nombre {dir}',
            'cajero_nombre'   => 'cajero_nombre {dir}',
            'usuario_nombre'  => 'usuario_nombre {dir}',
            'base_0'          => 'base_0 {dir}',
            'base_iva'        => 'base_iva {dir}',
            'valor_iva'       => 'valor_iva {dir}',
            'total'           => 'total {dir}',
            'retenciones'     => 'retenciones {dir}',
        ],
        'CLIENTE' => [
            '_def'           => ['total', 'DESC'],
            'cliente_nombre' => 'cliente_nombre {dir}',
            // La columna "Nro Facturas" se reemplazó por el saldo por cobrar; el conteo
            // sigue calculándose y se muestra al pasar el mouse por el nombre del cliente.
            'saldo'          => 'saldo {dir}',
            'base_0'         => 'base_0 {dir}',
            'base_iva'       => 'base_iva {dir}',
            'valor_iva'      => 'valor_iva {dir}',
            'total'          => 'total {dir}',
        ],
        'PRODUCTO' => [
            '_def'             => ['cantidad_vendida', 'DESC'],
            'producto_nombre'  => 'producto_nombre {dir}',
            'cantidad_vendida' => 'cantidad_vendida {dir}',
            'tarifa_iva'       => 'tarifa_iva {dir}',
            'base_0'           => 'base_0 {dir}',
            'base_iva'         => 'base_iva {dir}',
            'valor_iva'        => 'valor_iva {dir}',
            'total'            => 'total {dir}',
        ],
        'VARIANTE' => [
            '_def'             => ['cantidad_vendida', 'DESC'],
            'producto_nombre'  => 'producto_nombre {dir}',
            'variante_nombre'  => 'variante_nombre {dir}, variante_valor {dir}',
            'cantidad_vendida' => 'cantidad_vendida {dir}',
            'tarifa_iva'       => 'tarifa_iva {dir}',
            'base_0'           => 'base_0 {dir}',
            'base_iva'         => 'base_iva {dir}',
            'valor_iva'        => 'valor_iva {dir}',
            'total'            => 'total {dir}',
        ],
        'FECHA' => [
            '_def'              => ['fecha', 'DESC'],
            'fecha'             => 'fecha {dir}',
            'cantidad_facturas' => 'cantidad_facturas {dir}',
            'base_0'            => 'base_0 {dir}',
            'base_iva'          => 'base_iva {dir}',
            'valor_iva'         => 'valor_iva {dir}',
            'total'             => 'total {dir}',
        ],
        'MES' => [
            '_def'              => ['mes', 'DESC'],
            'mes'               => 'mes {dir}',
            'cantidad_facturas' => 'cantidad_facturas {dir}',
            'base_0'            => 'base_0 {dir}',
            'base_iva'          => 'base_iva {dir}',
            'valor_iva'         => 'valor_iva {dir}',
            'total'             => 'total {dir}',
        ],
        // Unidades por producto y mes: la fila se arma en PHP (pivote), así que aquí solo
        // importa la lista blanca y el orden por defecto; las expresiones no van a SQL. Las
        // columnas de cada mes (`mes:YYYY-MM`) se validan aparte contra los meses del período
        // (ver getReporteUnidadesProductoMes).
        'PRODUCTO_MES' => [
            '_def'            => ['total_unidades', 'DESC'],
            'producto_codigo' => 'producto_codigo {dir}',
            'producto_nombre' => 'producto_nombre {dir}',
            'total_unidades'  => 'total_unidades {dir}',
        ],
    ];

    /** Agrupación que corresponde a cada método (para ordenar el neto Facturas − NC). */
    private const MODO_POR_METODO = [
        'getReporteDetallado'        => 'NINGUNO',
        'getReporteAgrupadoCliente'  => 'CLIENTE',
        'getReporteAgrupadoProducto' => 'PRODUCTO',
        'getReporteAgrupadoVariante' => 'VARIANTE',
        'getReporteAgrupadoFecha'    => 'FECHA',
        'getReporteAgrupadoMes'      => 'MES',
        'getUnidadesProductoMesPlano' => 'PRODUCTO_MES',
    ];

    /** Columna y dirección efectivas: valida contra la lista blanca del modo. */
    private function resolverOrden(array $filtros, string $modo): array
    {
        $cols = self::ORDEN_COLUMNAS[$modo] ?? self::ORDEN_COLUMNAS['NINGUNO'];
        $col  = (string) ($filtros['orden_col'] ?? '');
        if ($col === '' || $col === '_def' || !isset($cols[$col])) {
            return $cols['_def'];
        }
        $dir = strtoupper((string) ($filtros['orden_dir'] ?? '')) === 'ASC' ? 'ASC' : 'DESC';
        return [$col, $dir];
    }

    /** Contenido del ORDER BY para un modo, ya validado. */
    private function ordenSql(array $filtros, string $modo): string
    {
        [$col, $dir] = $this->resolverOrden($filtros, $modo);
        $cols = self::ORDEN_COLUMNAS[$modo] ?? self::ORDEN_COLUMNAS['NINGUNO'];
        return str_replace('{dir}', $dir . ' NULLS LAST', $cols[$col]);
    }

    /**
     * Aplica el mismo orden en PHP a las filas que no salen ordenadas de SQL: el neto
     * "Facturas − NC" se arma combinando dos consultas, así que su ORDER BY no manda.
     */
    private function ordenarFilas(array $rows, array $filtros, string $modo): array
    {
        [$col, $dir] = $this->resolverOrden($filtros, $modo);
        $signo = $dir === 'ASC' ? 1 : -1;
        usort($rows, static function (array $a, array $b) use ($col, $signo): int {
            $x = $a[$col] ?? null;
            $y = $b[$col] ?? null;
            $cmp = (is_numeric($x) && is_numeric($y))
                ? ((float) $x <=> (float) $y)
                : strcasecmp((string) $x, (string) $y);
            return $signo * $cmp;
        });
        return $rows;
    }

    /**
     * Combina un método de reporte para FACTURA y NOTA_CREDITO restando la NC.
     * - $claves: columnas que identifican cada grupo (para agrupados). Si es null,
     *   es el modo detallado: devuelve facturas (+) seguidas de NC (−).
     * - $restar: campos monetarios (la NC se resta).
     * - $sumar:  campos de conteo (se suman ambos: total de documentos).
     */
    private function combinarNeto(int|array $idEmpresa, array $filtros, string $metodo, ?array $claves, array $restar, array $sumar = []): array
    {
        $fFac = array_merge($filtros, ['tipo_documento' => 'FACTURA']);
        $fNc  = array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']);
        $fac  = $this->$metodo($idEmpresa, $fFac);
        $nc   = $this->$metodo($idEmpresa, $fNc);
        // El orden de cada consulta se pierde al mezclarlas: se reaplica sobre el resultado.
        $modo = self::MODO_POR_METODO[$metodo] ?? 'NINGUNO';

        // Modo detallado: mezclar filas, negando montos de las NC.
        if ($claves === null) {
            foreach ($fac as &$r) { $r['_doc_tipo'] = 'FACTURA'; }
            unset($r);
            foreach ($nc as &$r) {
                foreach ($restar as $c) { $r[$c] = -(float)($r[$c] ?? 0); }
                $r['_doc_tipo'] = 'NOTA_CREDITO';
            }
            unset($r);
            return $this->ordenarFilas(array_merge($fac, $nc), $filtros, $modo);
        }

        // Modo agrupado: indexar por clave y restar las NC.
        $keyOf = function (array $r) use ($claves): string {
            $k = '';
            foreach ($claves as $c) { $k .= '|' . ($r[$c] ?? ''); }
            return $k;
        };

        $idx = [];
        foreach ($fac as $r) { $idx[$keyOf($r)] = $r; }
        foreach ($nc as $r) {
            $k = $keyOf($r);
            if (!isset($idx[$k])) {
                // Grupo que solo tiene NC (sin facturas): partir de esta fila con
                // montos y conteos en 0 para que el resultado quede en negativo.
                $base = $r;
                foreach ($restar as $c) { $base[$c] = 0; }
                foreach ($sumar  as $c) { $base[$c] = 0; }
                $idx[$k] = $base;
            }
            foreach ($restar as $c) { $idx[$k][$c] = (float)($idx[$k][$c] ?? 0) - (float)($r[$c] ?? 0); }
            foreach ($sumar  as $c) { $idx[$k][$c] = (float)($idx[$k][$c] ?? 0) + (float)($r[$c] ?? 0); }
        }

        return $this->ordenarFilas(array_values($idx), $filtros, $modo);
    }

    /**
     * Años disponibles (facturas autorizadas + recibos emitidos/facturados).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera
                    WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                    UNION
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM recibos_venta_cabecera
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('borrador','anulado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    // ── Saldo por cobrar del cliente (agrupación por Cliente) ─────────────────
    //
    // La columna "Saldo x Cobrar" de esa vista es lo que queda pendiente de los
    // documentos INCLUIDOS EN EL REPORTE (mismos filtros de período, vendedor,
    // establecimiento…), no la cartera histórica del cliente: así la fila cuadra
    // consigo misma y el saldo nunca supera al Gran Total.
    //
    // La fórmula y los enlaces son los de Cuentas por Cobrar (AbonosVentaSql), para
    // que el mismo cliente muestre el mismo saldo en los dos módulos.

    /** CTEs de abonos para el saldo, según la fuente. Cadena vacía si no aplica. */
    private function getCtesSaldo(array $f): string
    {
        if ($f['cab'] === 'notas_credito_cabecera') {
            return ''; // una NC no genera saldo por cobrar: es un abono
        }
        $tipoDoc = $f['cab'] === 'recibos_venta_cabecera' ? 'RECIBO' : 'FACTURA';
        $ctes = "
            , cobrado_sal AS (
                SELECT idt.id_referencia_documento AS id_doc,
                       SUM(idt.monto_cobrado)      AS total_cobrado
                FROM ingresos_detalle idt
                INNER JOIN ingresos_cabecera icb ON icb.id = idt.id_ingreso
                WHERE idt.tipo_documento = '{$tipoDoc}'
                  AND icb.estado    != 'anulado'
                  AND icb.eliminado  = false
                  AND icb.id_empresa IN ({$this->inEmp})
                GROUP BY idt.id_referencia_documento
            )";

        // Los recibos de venta solo se reducen con cobros (no tienen retenciones ni notas).
        if ($tipoDoc === 'RECIBO') {
            return $ctes;
        }

        $empresaAny = "ANY(ARRAY[{$this->inEmp}])";
        $ambienteNota = "AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = n.id_empresa))";

        return $ctes
            . "\n            , retenido_sal AS (" . AbonosVentaSql::cteRetenidoPorFactura($empresaAny) . ")"
            . "\n            , nc_sal AS (" . AbonosVentaSql::cteNotasPorFactura('notas_credito_cabecera', 'total_nc', $empresaAny, $ambienteNota, true) . ")"
            . "\n            , nd_sal AS (" . AbonosVentaSql::cteNotasPorFactura('nota_debito_cabecera', 'total_nd', $empresaAny, $ambienteNota, true) . ")";
    }

    /** LEFT JOINs que enlazan esas CTEs con el documento `$alias`. */
    private function getJoinsSaldo(array $f, string $alias = 'v'): string
    {
        if ($f['cab'] === 'notas_credito_cabecera') {
            return '';
        }
        $joins = "\n            LEFT JOIN cobrado_sal cs ON cs.id_doc = {$alias}.id";
        if ($f['cab'] === 'recibos_venta_cabecera') {
            return $joins;
        }
        $num = AbonosVentaSql::numFactura($alias);
        return $joins
            . "\n            LEFT JOIN retenido_sal rs ON rs.id_venta = {$alias}.id"
            . "\n            LEFT JOIN nc_sal ns ON ns.id_empresa = {$alias}.id_empresa AND ns.num_norm = {$num}"
            . "\n            LEFT JOIN nd_sal ds ON ds.id_empresa = {$alias}.id_empresa AND ds.num_norm = {$num}";
    }

    /** Expresión SUM(...) del saldo pendiente, según la fuente. */
    private function exprSaldo(array $f, string $alias = 'v'): string
    {
        if ($f['cab'] === 'notas_credito_cabecera') {
            return '0';
        }
        if ($f['cab'] === 'recibos_venta_cabecera') {
            return "SUM({$alias}.importe_total - COALESCE(cs.total_cobrado, 0))";
        }
        return "SUM({$alias}.importe_total
                  + COALESCE(ds.total_nd, 0)
                  - COALESCE(cs.total_cobrado, 0)
                  - COALESCE(rs.total_retenido, 0)
                  - COALESCE(ns.total_nc, 0))";
    }

    /**
     * CTE de bases e impuestos para sumatorias (según la fuente).
     * Se une contra la cabecera y se filtra por id_empresa: sin este JOIN, el GROUP BY
     * agregaba el detalle de TODOS los documentos del sistema (todas las empresas) en
     * cada consulta del reporte, sin importar cuántas filas mostraba el filtro — el
     * costo crecía con el tamaño de la BD completa, no con los datos de la empresa
     * activa, y se sentía cada vez más lento a medida que el sistema acumulaba más
     * empresas/documentos. Requiere que el llamador incluya :id_empresa en sus params
     * (ya lo hace vía buildWhereYParams; reutilizar el mismo nombre de placeholder es
     * seguro en este proyecto, ver memoria pdo-placeholders-repetidos).
     */
    private function getCteBasesImpuestos(array $f): string
    {
        return "
            SELECT
                d.{$f['fk_det']} AS id_doc,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM {$f['det']} d
            JOIN {$f['cab']} vcte ON vcte.id = d.{$f['fk_det']} AND vcte.id_empresa IN ({$this->inEmp})
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            GROUP BY d.{$f['fk_det']}
        ";
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros.
     */
    /**
     * Condición de cruce entre una nota de crédito y la factura de venta que
     * modifica (mismo criterio que ReporteVentasVendedorRepository y
     * NotaCreditoRepository): num_doc_modificado = establecimiento-punto-secuencial
     * de la factura, dentro de la misma empresa y ambiente.
     */
    private function condicionFacturaDeNc(string $aliasNc, string $aliasFactura): string
    {
        return "{$aliasFactura}.id_empresa = {$aliasNc}.id_empresa
                AND {$aliasFactura}.eliminado = false
                AND {$aliasFactura}.tipo_ambiente = {$aliasNc}.tipo_ambiente
                AND CONCAT({$aliasFactura}.establecimiento, '-', {$aliasFactura}.punto_emision, '-', {$aliasFactura}.secuencial) = {$aliasNc}.num_doc_modificado";
    }

    /**
     * Alcance del usuario (§6, ver App\Helpers\AlcanceRegistros). Devuelve la
     * condición a concatenar al WHERE, o cadena vacía si ve toda la empresa:
     *
     *  - Modo VENDEDOR (`id_vendedor_filtro`): solo lo de su vendedor. Manda el
     *    vendedor del documento; si no tiene (NULL o 0), el asignado a su cliente
     *    (`clientes.id_vendedor`). Un documento a nombre de otro vendedor queda
     *    fuera aunque el cliente sea suyo. Las notas de crédito no registran
     *    vendedor (ver fuente()): manda el de la factura que modifican y, si esa
     *    factura no tiene o no se encuentra, el del cliente de la nota.
     *  - Modo REGISTROS PROPIOS (`id_usuario_filtro`): `id_usuario` del documento
     *    (la misma columna que filtran Factura de Venta, Recibo de Venta y NC).
     *
     * Los placeholders del IN se repiten dentro del mismo SQL (cliente y documento);
     * en este proyecto eso es seguro (ver memoria pdo-placeholders-repetidos).
     */
    private function condAlcanceUsuario(array $filtros, array $f, string $alias, array &$params): string
    {
        $idsVend = \App\Helpers\AlcanceRegistros::idsVendedor($filtros);
        if ($idsVend) {
            $ph = [];
            foreach ($idsVend as $i => $id) {
                $ph[] = ":alc_v{$i}";
                $params[":alc_v{$i}"] = $id;
            }
            $in = implode(',', $ph);
            $clienteSuyo = "EXISTS (SELECT 1 FROM clientes alc_c
                                    WHERE alc_c.id = {$alias}.id_cliente AND alc_c.id_vendedor IN ({$in}))";
            if ($f['vendedor']) {
                return " AND ({$alias}.id_vendedor IN ({$in})
                              OR (COALESCE({$alias}.id_vendedor, 0) = 0 AND {$clienteSuyo}))";
            }
            $factura = "SELECT 1 FROM ventas_cabecera alc_fv WHERE " . $this->condicionFacturaDeNc($alias, 'alc_fv');
            return " AND (EXISTS ({$factura} AND alc_fv.id_vendedor IN ({$in}))
                          OR (NOT EXISTS ({$factura} AND COALESCE(alc_fv.id_vendedor, 0) <> 0) AND {$clienteSuyo}))";
        }

        $idUsuario = \App\Helpers\AlcanceRegistros::idUsuario($filtros);
        if ($idUsuario <= 0) {
            return '';
        }
        $params[':id_usuario_filtro'] = $idUsuario;
        return " AND {$alias}.id_usuario = :id_usuario_filtro";
    }

    private function buildWhereYParams(int|array $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle = null, bool $filtrarEstado = true): array
    {
        $f = $this->fuente($filtros);
        $this->setAlcance($idEmpresa);

        $where = "{$aliasVenta}.id_empresa IN ({$this->inEmp})
                  AND {$aliasVenta}.eliminado = false
                  AND " . $this->condAmbiente($aliasVenta);

        if ($filtrarEstado) {
            $where .= " AND " . str_replace('{alias}', $aliasVenta, $f['estado_ok']);
        }

        $params = [];

        // Alcance del usuario (§6): si es de nivel 1 y NO tiene acceso total ('t') en
        // el módulo, el reporte se limita a su cartera (vendedor vinculado) o, si no es
        // vendedor, a los documentos que él registró. Los niveles 2 y 3 ven todo. Lo resuelve el controller con
        // App\Helpers\AlcanceRegistros y viaja dentro de los filtros: NUNCA llega del
        // cliente. Al vivir aquí lo heredan el detallado, todas las agrupaciones, las
        // tarjetas de estadísticas, el resumen de estados, el neto "Facturas − NC", el
        // PDF, el Excel y la API móvil.
        $where .= $this->condAlcanceUsuario($filtros, $f, $aliasVenta, $params);

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasVenta}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasVenta}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_cliente'])) {
            $clientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : [$filtros['id_cliente']];
            $inNames = [];
            foreach ($clientes as $i => $id) {
                $pName = ":cli$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasVenta}.id_cliente IN (" . implode(',', $inNames) . ")";
        }

        // Filtro por Vendedor. Las notas de crédito no registran vendedor
        // (ver fuente()): se toma el de la factura de venta que modifican.
        if (!empty($filtros['id_vendedor'])) {
            if ($f['vendedor']) {
                $where .= " AND {$aliasVenta}.id_vendedor = :id_vendedor";
            } else {
                $where .= " AND EXISTS (
                    SELECT 1 FROM ventas_cabecera fvnc
                    WHERE " . $this->condicionFacturaDeNc($aliasVenta, 'fvnc') . "
                      AND fvnc.id_vendedor = :id_vendedor
                )";
            }
            $params[':id_vendedor'] = (int)$filtros['id_vendedor'];
        }

        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $inNames = [];
            foreach ($productos as $i => $id) {
                $pName = ":prod$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $inNames) . ")";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vd WHERE vd.{$f['fk_det']} = {$aliasVenta}.id AND vd.id_producto IN (" . implode(',', $inNames) . "))";
            }
        }

        // Filtro por Marca / Categoría del producto de la línea. En los agrupados por línea
        // (alias de detalle) acota las líneas; en los que van por documento, entran los
        // documentos que tengan al menos una línea de esa marca/categoría (igual que el
        // filtro por id de producto). En consolidado, el controller ya expandió el id a las
        // marcas/categorías homónimas de los demás establecimientos (expandirCatalogoPorNombre).
        foreach (['id_marca', 'id_categoria'] as $atributo) {
            $ids = array_values(array_filter(array_map('intval', (array) ($filtros[$atributo] ?? []))));
            if (!$ids) {
                continue;
            }
            $ph = [];
            foreach ($ids as $i => $id) {
                $ph[] = ":{$atributo}{$i}";
                $params[":{$atributo}{$i}"] = $id;
            }
            $cond = "EXISTS (SELECT 1 FROM productos pat WHERE pat.id = {alias}.id_producto
                             AND pat.{$atributo} IN (" . implode(',', $ph) . "))";
            if ($aliasDetalle) {
                $where .= " AND " . str_replace('{alias}', $aliasDetalle, $cond);
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vda
                                        WHERE vda.{$f['fk_det']} = {$aliasVenta}.id
                                          AND " . str_replace('{alias}', 'vda', $cond) . ")";
            }
        }

        // Filtro por Producto = texto de los ítems del documento (descripción o código de línea)
        if (!empty($filtros['producto_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdp
                WHERE vdp.{$f['fk_det']} = {$aliasVenta}.id
                  AND (vdp.descripcion ILIKE :prodtxt OR vdp.codigo_principal ILIKE :prodtxt)
            )";
            $params[':prodtxt'] = '%' . trim($filtros['producto_texto']) . '%';
        }

        // Filtro por Variante = texto de la variante elegida en la línea (Color/Talla, etc.)
        if (!empty($filtros['variante_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdv
                JOIN productos_variantes pv ON pv.id = vdv.id_producto_variante
                WHERE vdv.{$f['fk_det']} = {$aliasVenta}.id
                  AND (pv.nombre ILIKE :vartxt OR pv.valor ILIKE :vartxt)
            )";
            $params[':vartxt'] = '%' . trim($filtros['variante_texto']) . '%';
        }

        // Filtro por Información Adicional del documento (campos adicionales nombre/valor)
        if (!empty($filtros['buscar_info'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['adic']} va
                WHERE va.{$f['fk_adic']} = {$aliasVenta}.id
                  AND (va.nombre ILIKE :info OR va.valor ILIKE :info)
            )";
            $params[':info'] = '%' . trim($filtros['buscar_info']) . '%';
        }

        return [$where, $params];
    }

    /**
     * Reporte detallado (por documento).
     */
    public function getReporteDetallado(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteDetallado', null,
                ['base_0', 'base_iva', 'valor_iva', 'total']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $clave = $f['clave'] ? "COALESCE(v.clave_acceso, '')" : "''";
        // Vínculo doble, igual que en FacturaVentaRepository/RetencionVentaRepository: además de
        // r.id_venta (registrada directo desde la factura), cubre la retención electrónica del SRI
        // que solo referencia el número de la factura en su detalle (num_doc_sustento) sin haber
        // quedado enlazada por id_venta.
        // Se resuelve como CTE (una sola pasada sobre las retenciones de la empresa), no como
        // subconsulta correlacionada por fila: con un filtro amplio (producto_texto por nombre)
        // el reporte puede devolver muchas filas, y repetir el escaneo de retenciones por cada
        // una escala mal (N filas × M retenciones) — en el droplet de producción (1 vCPU) eso
        // fue suficiente para saturar la conexión a BD y colgar el sitio entero.
        if ($f['retenciones']) {
            $retenCte = ",
                retenciones_map AS (
                    SELECT COALESCE(r.id_venta, vv.id) AS id_venta,
                           r.total_iva, r.total_renta, r.total_isd
                    FROM retencion_venta_cabecera r
                    LEFT JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id AND r.id_venta IS NULL
                    LEFT JOIN ventas_cabecera vv ON r.id_venta IS NULL
                        AND vv.id_empresa = r.id_empresa
                        AND CONCAT(vv.establecimiento, '-', vv.punto_emision, '-', vv.secuencial) = rd.num_doc_sustento
                    WHERE r.eliminado = false AND r.id_empresa IN ({$this->inEmp})
                ),
                retenciones_agg AS (
                    SELECT id_venta, SUM(total_iva + total_renta + total_isd) AS monto_retenciones
                    FROM retenciones_map
                    WHERE id_venta IS NOT NULL
                    GROUP BY id_venta
                )";
            $retenJoin = "LEFT JOIN retenciones_agg ra ON ra.id_venta = v.id";
            $reten = "COALESCE(ra.monto_retenciones, 0)";
        } else {
            $retenCte = "";
            $retenJoin = "";
            $reten = "0";
        }
        // Las notas de crédito no tienen id_vendedor: se muestra el de la factura modificada.
        $vendedorSel  = "COALESCE(vend.nombre, '')";
        if ($f['vendedor']) {
            $vendedorJoin = "LEFT JOIN vendedores vend ON vend.id = v.id_vendedor";
        } else {
            $vendedorJoin = "LEFT JOIN ventas_cabecera fvnc ON " . $this->condicionFacturaDeNc('v', 'fvnc') . "
            LEFT JOIN vendedores vend ON vend.id = fvnc.id_vendedor";
        }
        $orden = $this->ordenSql($filtros, 'NINGUNO');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . "){$retenCte}
            SELECT
                v.id,
                v.id_empresa,
                (SELECT COALESCE(e.establecimiento, '') FROM empresas e WHERE e.id = v.id_empresa) AS establecimiento,
                v.fecha_emision,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                v.estado,
                COALESCE(b.base_0, 0)   as base_0,
                COALESCE(b.base_iva, 0) as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                v.importe_total          as total,
                {$vendedorSel}              as vendedor_nombre,
                COALESCE(ucaj.nombre, '')   as cajero_nombre,
                COALESCE(uusr.nombre, '')   as usuario_nombre,
                {$clave} as clave_acceso,
                {$reten} as retenciones
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id
            {$retenJoin}
            {$vendedorJoin}
            LEFT JOIN usuarios    ucaj ON ucaj.id = v.id_usuario
            LEFT JOIN usuarios    uusr ON uusr.id = v.created_by
            WHERE {$where}
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por cliente.
     */
    public function getReporteAgrupadoCliente(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            // `saldo` se SUMA (no se resta) al combinar: las notas de crédito aportan 0
            // porque su descuento ya está dentro del saldo de la factura que modifican.
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoCliente', ['id_cliente'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas', 'saldo']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'CLIENTE');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            " . $this->getCtesSaldo($f) . "
            SELECT
                c.id as id_cliente,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total,
                -- Pendiente de cobro de esos mismos documentos (ver getCtesSaldo)
                " . $this->exprSaldo($f) . " as saldo
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id" . $this->getJoinsSaldo($f) . "
            WHERE {$where}
            GROUP BY c.id, c.identificacion, c.nombre
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto.
     */
    public function getReporteAgrupadoProducto(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoProducto', ['id_producto', 'tarifa_iva'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');
        $orden = $this->ordenSql($filtros, 'PRODUCTO');

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(p.codigo, '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), COALESCE(i.tarifa, 0)
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por variante de producto (Color/Talla, etc. —
     * productos_variantes). Solo incluye líneas que efectivamente tienen una
     * variante elegida (id_producto_variante no nulo); el resto del reporte
     * ("Por Producto") ya las cubre de forma agregada sin distinguir variante.
     */
    public function getReporteAgrupadoVariante(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoVariante', ['id_producto_variante', 'tarifa_iva'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_vendida']);
        }

        // Las notas de crédito no registran variantes (no hay id_producto_variante),
        // por lo que este agrupado no aplica para ellas.
        if (($filtros['tipo_documento'] ?? '') === 'NOTA_CREDITO') {
            return [];
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');
        $orden = $this->ordenSql($filtros, 'VARIANTE');

        $sql = "
            SELECT
                d.id_producto_variante,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                pv.nombre as variante_nombre,
                pv.valor as variante_valor,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            JOIN productos_variantes pv ON pv.id = d.id_producto_variante
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto_variante, COALESCE(p.nombre, d.descripcion), pv.nombre, pv.valor, COALESCE(i.tarifa, 0)
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por fecha.
     */
    public function getReporteAgrupadoFecha(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoFecha', ['fecha'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'FECHA');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                v.fecha_emision as fecha,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY v.fecha_emision
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por mes (año-mes).
     */
    public function getReporteAgrupadoMes(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoMes', ['mes'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'MES');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY TO_CHAR(v.fecha_emision, 'YYYY-MM')
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Unidades vendidas por producto y mes ──────────────────────────────────

    /**
     * Filas planas producto × mes con las unidades vendidas (SUM(cantidad) de las líneas).
     * Es la consulta base de getReporteUnidadesProductoMes(), que la pivotea; va aparte
     * para que el neto "Facturas − NC" pueda restar las unidades devueltas mes a mes
     * (combinarNeto). El código sale del catálogo y, para líneas sin producto (concepto
     * libre), del código escrito en la línea.
     */
    public function getUnidadesProductoMesPlano(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getUnidadesProductoMesPlano',
                ['id_producto', 'producto_codigo', 'producto_nombre', 'mes'], ['cantidad']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(NULLIF(TRIM(p.codigo), ''), d.codigo_principal, '') AS producto_codigo,
                COALESCE(p.nombre, d.descripcion) AS producto_nombre,
                TO_CHAR(v.fecha_emision, 'YYYY-MM') AS mes,
                SUM(d.cantidad) AS cantidad
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            WHERE {$where}
            GROUP BY 1, 2, 3, 4
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Unidades vendidas por producto y por mes: una fila por producto con la cantidad de
     * cada mes del período y el total. Devuelve ['meses' => ['YYYY-MM', …], 'rows' => […]].
     *
     * - Los meses son los del rango de fechas del filtro (todos, tengan o no ventas); si
     *   falta una de las fechas, ese extremo se toma del primer/último mes con datos.
     * - Solo salen los productos con total > 0: en el neto "Facturas − NC", los que quedaron
     *   en cero o en negativo por devoluciones también se omiten.
     * - Un mismo código es una sola fila: en consolidado por RUC, el producto de cada
     *   establecimiento (id distinto, mismo código) se suma en la misma fila.
     * - El orden se aplica en PHP (la fila se arma aquí, no en SQL): código, nombre, total o
     *   cualquier mes del período (`orden_col = mes:YYYY-MM`); desempate por nombre.
     */
    public function getReporteUnidadesProductoMes(int|array $idEmpresa, array $filtros): array
    {
        $plano = $this->getUnidadesProductoMesPlano($idEmpresa, $filtros);
        $meses = self::mesesEntre(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
            array_column($plano, 'mes')
        );

        $idx = [];
        foreach ($plano as $r) {
            $codigo = trim((string) ($r['producto_codigo'] ?? ''));
            $nombre = (string) ($r['producto_nombre'] ?? '');
            $k = $codigo !== '' ? 'c:' . $codigo
               : ($r['id_producto'] !== null ? 'p:' . $r['id_producto'] : 't:' . $nombre);
            if (!isset($idx[$k])) {
                $idx[$k] = [
                    'id_producto'     => $r['id_producto'],
                    'producto_codigo' => $codigo,
                    'producto_nombre' => $nombre,
                    'meses'           => array_fill_keys($meses, 0.0),
                    'total_unidades'  => 0.0,
                ];
            }
            $cant = (float) ($r['cantidad'] ?? 0);
            if (array_key_exists((string) $r['mes'], $idx[$k]['meses'])) {
                $idx[$k]['meses'][$r['mes']] += $cant;
            }
            $idx[$k]['total_unidades'] += $cant;
        }
        $rows = array_values(array_filter($idx, static fn (array $r): bool => $r['total_unidades'] > 0.000001));

        // Orden: un mes del período, o una de las columnas fijas (lista blanca del modo).
        $colPedida = (string) ($filtros['orden_col'] ?? '');
        if (preg_match('/^mes:(\d{4}-\d{2})$/', $colPedida, $m) && in_array($m[1], $meses, true)) {
            $mes = $m[1];
            $dir = strtoupper((string) ($filtros['orden_dir'] ?? '')) === 'ASC' ? 'ASC' : 'DESC';
            $val = static fn (array $r) => (float) ($r['meses'][$mes] ?? 0);
        } else {
            [$col, $dir] = $this->resolverOrden($filtros, 'PRODUCTO_MES');
            $val = static fn (array $r) => $r[$col] ?? null;
        }
        $signo = $dir === 'ASC' ? 1 : -1;
        usort($rows, static function (array $a, array $b) use ($val, $signo): int {
            $x = $val($a);
            $y = $val($b);
            $cmp = (is_numeric($x) && is_numeric($y))
                ? ((float) $x <=> (float) $y)
                : strcasecmp((string) $x, (string) $y);
            return ($signo * $cmp) ?: strcasecmp($a['producto_nombre'], $b['producto_nombre']);
        });

        return ['meses' => $meses, 'rows' => $rows];
    }

    /**
     * Meses 'YYYY-MM' consecutivos entre dos fechas. Si falta una de ellas (o no tiene
     * forma de fecha), ese extremo se toma del primer/último mes con datos. Tope de 120
     * meses para que un rango abierto sobre muchos años no produzca una tabla inmanejable.
     */
    private static function mesesEntre(string $desde, string $hasta, array $mesesConDatos): array
    {
        $mesesConDatos = array_values(array_unique(array_filter(array_map('strval', $mesesConDatos))));
        sort($mesesConDatos);
        $ok  = static fn (string $f): bool => (bool) preg_match('/^\d{4}-\d{2}/', $f);
        $ini = $ok($desde) ? substr($desde, 0, 7) : ($mesesConDatos[0] ?? '');
        $fin = $ok($hasta) ? substr($hasta, 0, 7) : ($mesesConDatos ? end($mesesConDatos) : '');
        if ($ini === '' || $fin === '' || $ini > $fin) {
            return [];
        }
        $out  = [];
        $cur  = new \DateTimeImmutable($ini . '-01');
        $tope = new \DateTimeImmutable($fin . '-01');
        while ($cur <= $tope && count($out) < 120) {
            $out[] = $cur->format('Y-m');
            $cur   = $cur->modify('+1 month');
        }
        return $out;
    }

    /**
     * Autocompletado: descripciones distintas de los ítems del documento.
     */
    public function buscarItems(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15, array $alcance = []): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        // Alcance del usuario (§6): las sugerencias salen solo de los documentos que el
        // usuario puede ver en el reporte (misma condición que buildWhereYParams).
        $params = [':ie' => $idEmpresa, ':q' => '%' . $q . '%'];
        $propio = $this->condAlcanceUsuario($alcance, $f, 'v', $params);
        // Busca por nombre (descripción) o por código de línea, igual que el filtro
        // producto_texto del reporte (ver buildWhereYParams: descripcion OR codigo_principal).
        $sql = "SELECT DISTINCT TRIM(d.descripcion) AS descripcion, TRIM(COALESCE(d.codigo_principal, '')) AS codigo
                FROM {$f['det']} d
                JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
                WHERE v.id_empresa = :ie AND v.eliminado = false{$propio}
                  AND d.descripcion IS NOT NULL AND TRIM(d.descripcion) <> ''
                  AND (d.descripcion ILIKE :q OR d.codigo_principal ILIKE :q)
                ORDER BY descripcion
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // valor = lo que queda escrito en el buscador al elegir: siempre el nombre
        // (descripción), igual que si el usuario lo hubiera tecleado a mano — así el
        // filtro producto_texto (que ya busca por descripcion OR codigo_principal)
        // encuentra TODAS las líneas con ese nombre, no solo las que comparten el
        // código exacto de la línea elegida (un mismo producto puede aparecer con
        // código vacío o distinto entre documentos). El código solo se muestra como
        // referencia (sub) en la lista, para que el usuario pueda buscar por código
        // sin que eso reduzca el resultado a una sola variante.
        return array_map(fn($r) => [
            'valor' => $r['descripcion'],
            'label' => $r['descripcion'],
            'sub'   => $r['codigo'],
        ], $rows);
    }

    /**
     * Autocompletado: info adicional (nombre/valor distintos del documento).
     */
    public function buscarInfoAdicional(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15, array $alcance = []): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        // Alcance del usuario (§6), igual que buscarItems().
        $params = [':ie' => $idEmpresa, ':q' => '%' . $q . '%'];
        $propio = $this->condAlcanceUsuario($alcance, $f, 'v', $params);
        $sql = "SELECT DISTINCT va.nombre, va.valor
                FROM {$f['adic']} va
                JOIN {$f['cab']} v ON v.id = va.{$f['fk_adic']}
                WHERE v.id_empresa = :ie AND v.eliminado = false{$propio}
                  AND COALESCE(va.valor, '') <> ''
                  AND (va.nombre ILIKE :q OR va.valor ILIKE :q)
                ORDER BY va.nombre, va.valor
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => [
            'valor' => $r['valor'],
            'label' => $r['valor'],
            'sub'   => $r['nombre'],
        ], $rows);
    }

    /**
     * Obtiene estadísticas globales para el rango de fechas.
     */
    public function getEstadisticas(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            $sf = $this->getEstadisticas($idEmpresa, array_merge($filtros, ['tipo_documento' => 'FACTURA']));
            $sn = $this->getEstadisticas($idEmpresa, array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']));
            return [
                'total_base_0'     => $sf['total_base_0']   - $sn['total_base_0'],
                'total_base_iva'   => $sf['total_base_iva'] - $sn['total_base_iva'],
                'total_iva'        => $sf['total_iva']      - $sn['total_iva'],
                'gran_total'       => $sf['gran_total']     - $sn['gran_total'],
                'total_documentos' => $sf['total_documentos'] + $sn['total_documentos'],
            ];
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(v.importe_total) as gran_total,
                COUNT(v.id) as total_documentos
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0'] ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva'] ?? 0),
            'gran_total'       => (float)($row['gran_total'] ?? 0),
            'total_documentos' => (int)($row['total_documentos'] ?? 0),
        ];
    }

    public function getResumenEstados(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            $rf = $this->getResumenEstados($idEmpresa, array_merge($filtros, ['tipo_documento' => 'FACTURA']));
            $rn = $this->getResumenEstados($idEmpresa, array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']));
            return [
                'autorizados' => $rf['autorizados'] + $rn['autorizados'],
                'anulados'    => $rf['anulados']    + $rn['anulados'],
                'borradores'  => $rf['borradores']  + $rn['borradores'],
            ];
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, false);

        $sql = "
            SELECT
                LOWER(estado) as estado,
                COUNT(*) as cantidad
            FROM {$f['cab']} v
            WHERE {$where}
            GROUP BY LOWER(estado)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        $resumen = [
            'autorizados' => 0,
            'anulados'    => 0,
            'borradores'  => 0
        ];

        foreach ($rows as $row) {
            $estado = $row['estado'];
            $cantidad = (int) $row['cantidad'];
            // "Autorizados" agrupa los documentos emitidos/válidos (facturas autorizadas y recibos emitidos/facturados)
            if (in_array($estado, ['autorizado', 'autorizada', 'emitido', 'facturado'])) {
                $resumen['autorizados'] += $cantidad;
            } elseif ($estado === 'anulado') {
                $resumen['anulados'] += $cantidad;
            } elseif ($estado === 'borrador') {
                $resumen['borradores'] += $cantidad;
            }
        }

        return $resumen;
    }
}

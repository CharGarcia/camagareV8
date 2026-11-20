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

    /**
     * Regla de reportes: solo documentos de PRODUCCIÓN (ver App\Helpers\AmbienteReporte).
     * La API de la app móvil llama usarAmbienteEmpresa() y conserva el comportamiento anterior
     * (ambiente actual de la empresa dueña del documento) hasta confirmar el impacto en la
     * cuenta demo de los revisores de Apple (CLAUDE.md §13).
     */
    private bool $ambienteDeEmpresa = false;

    public function usarAmbienteEmpresa(bool $si = true): static
    {
        $this->ambienteDeEmpresa = $si;
        return $this;
    }

    private function condAmbiente(string $alias): string
    {
        if ($this->ambienteDeEmpresa) {
            return "{$alias}.tipo_ambiente = (SELECT CAST(e.tipo_ambiente AS VARCHAR(1)) FROM empresas e WHERE e.id = {$alias}.id_empresa)";
        }
        return \App\Helpers\AmbienteReporte::condicion($alias);
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
                'estado_ok'   => $this->condEstadoRecibo($filtros),
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
                // La NC guarda su propio vendedor (database/20260924_nc_cabecera_id_vendedor.sql)
                // y se filtra/muestra por él, igual que una factura. Sin esa columna se toma
                // el de la factura que modifica.
                'vendedor'    => $this->columnaExiste('notas_credito_cabecera', 'id_vendedor'),
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

    /**
     * Estados que cuentan como venta en un RECIBO: todo lo que no esté anulado ni
     * facturado. El recibo nace como `borrador` y solo pasa a `emitido` cuando queda pagado
     * por completo (ReciboVentaService::emitirSiPagados): un recibo a crédito sigue en
     * borrador y es venta igual. Excluir `borrador` dejaba fuera del reporte a todos esos. Es el mismo criterio que
     * Cuentas por Cobrar, la Caja POS, el Reporte Restaurante y el Consolidado. Un
     * `facturado` ya está en la factura que generó: contarlo duplicaría la venta.
     *
     * Por eso el selector "Borradores" no aplica a los recibos: con "Solo borradores" no
     * sale ninguno (su borrador no es un documento pendiente de autorizar).
     */
    private function condEstadoRecibo(array $filtros): string
    {
        return strtoupper((string) ($filtros['borradores'] ?? '')) === 'SOLO'
            ? 'false'
            : "{alias}.estado NOT IN ('anulado', 'facturado')";
    }

    /** ¿El reporte es el neto "Facturas − Notas de crédito"? */
    private function esNeto(array $filtros): bool
    {
        return in_array($filtros['tipo_documento'] ?? '', ['FACTURA_MENOS_NC', 'TODOS'], true);
    }

    /**
     * Fuentes que SUMAN en el neto: FACTURA_MENOS_NC = facturas; TODOS = facturas + recibos.
     * En ambos casos las notas de crédito restan. Un recibo facturado no entra (su venta ya
     * está en la factura que generó; ver condEstadoRecibo), así que no se duplica.
     */
    private function fuentesSuma(array $filtros): array
    {
        return ($filtros['tipo_documento'] ?? '') === 'TODOS' ? ['FACTURA', 'RECIBO'] : ['FACTURA'];
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
            'motivo'          => 'motivo {dir}',   // solo se muestra en notas de crédito
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
        // Los % se calculan en PHP (agregarParticipacion): en SQL ordenan igual que su base.
        'PRODUCTO' => [
            '_def'             => ['cantidad_vendida', 'DESC'],
            'producto_codigo'  => 'producto_codigo {dir}',
            'producto_nombre'  => 'producto_nombre {dir}',
            'unidad_medida'    => 'unidad_medida {dir}',
            'total_venta'      => 'total_venta {dir}',
            'cantidad_vendida' => 'cantidad_vendida {dir}',
            'pct_venta'        => 'total_venta {dir}',
            'pct_unidades'     => 'cantidad_vendida {dir}',
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
        'CAJERO' => [
            '_def'              => ['total', 'DESC'],
            'cajero_nombre'     => 'cajero_nombre {dir}',
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
        // Unidades por cliente y categoría: también es un pivote armado en PHP. Las columnas
        // de cada categoría (`cat:<clave>`) se validan contra las categorías del resultado
        // (ver getReporteClienteCategoria).
        'CLIENTE_CATEGORIA' => [
            '_def'           => ['total_venta', 'DESC'],
            'cliente_nombre' => 'cliente_nombre {dir}',
            'total_unidades' => 'total_unidades {dir}',
            'total_venta'    => 'total_venta {dir}',
        ],
        // Rentabilidad (venta − costo): las filas se agregan en PHP (getRentabilidad), así
        // que estas entradas solo son la lista blanca y el orden por defecto; ordena
        // ordenarFilas() por el campo de la fila con el mismo nombre.
        'RENT_PRODUCTO' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['utilidad', 'DESC'],
            'producto_codigo'  => '',
            'producto_nombre'  => '',
            'categoria_nombre' => '',
            'marca_nombre'     => '',
            'precio_promedio'  => '',
            'costo_promedio'   => '',
        ],
        'RENT_CATEGORIA' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['utilidad', 'DESC'],
            'categoria_nombre' => '',
            'productos'        => '',
        ],
        'RENT_MARCA' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['utilidad', 'DESC'],
            'marca_nombre'     => '',
            'productos'        => '',
        ],
        'RENT_MES' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['mes', 'ASC'],
            'mes'              => '',
            'productos'        => '',
        ],
        'RENT_CLIENTE' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['utilidad', 'DESC'],
            'cliente_nombre'   => '',
            'productos'        => '',
        ],
        'RENT_VENDEDOR' => self::ORDEN_RENTABILIDAD + [
            '_def'             => ['utilidad', 'DESC'],
            'vendedor_nombre'  => '',
            'clientes'         => '',
            'productos'        => '',
        ],
    ];

    /** Columnas de importes comunes a las vistas de rentabilidad (lista blanca de orden). */
    private const ORDEN_RENTABILIDAD = [
        'cantidad_vendida' => '',
        'total_venta'      => '',
        'costo'            => '',
        'utilidad'         => '',
        'margen'           => '',
        'markup'           => '',
        'pct_utilidad'     => '',
    ];

    /** Agrupación que corresponde a cada método (para ordenar el neto Facturas − NC). */
    private const MODO_POR_METODO = [
        'getReporteDetallado'        => 'NINGUNO',
        'getReporteAgrupadoCliente'  => 'CLIENTE',
        'getReporteAgrupadoProducto' => 'PRODUCTO',
        'getReporteAgrupadoVariante' => 'VARIANTE',
        'getReporteAgrupadoFecha'    => 'FECHA',
        'getReporteAgrupadoCajero'   => 'CAJERO',
        'getReporteAgrupadoMes'      => 'MES',
        'getUnidadesProductoMesPlano' => 'PRODUCTO_MES',
        'getClienteCategoriaPlano'    => 'CLIENTE_CATEGORIA',
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
     * Combina un método de reporte para las fuentes que suman (FACTURA y, en TODOS, también
     * RECIBO — ver fuentesSuma()) y NOTA_CREDITO, restando la NC.
     * - $claves: columnas que identifican cada grupo (para agrupados). Si es null,
     *   es el modo detallado: devuelve facturas/recibos (+) seguidas de NC (−).
     * - $restar: campos monetarios (la NC se resta).
     * - $sumar:  campos de conteo (se suman todos: total de documentos).
     */
    private function combinarNeto(int|array $idEmpresa, array $filtros, string $metodo, ?array $claves, array $restar, array $sumar = []): array
    {
        // Filas de cada fuente que suma, marcadas con su tipo (el detallado lo necesita para
        // abrir el documento correcto: los ids de facturas y recibos se repiten entre tablas).
        $fac = [];
        foreach ($this->fuentesSuma($filtros) as $tipo) {
            foreach ($this->$metodo($idEmpresa, array_merge($filtros, ['tipo_documento' => $tipo])) as $r) {
                $r['_doc_tipo'] = $tipo;
                $fac[] = $r;
            }
        }
        $fNc  = array_merge($filtros, ['tipo_documento' => 'NOTA_CREDITO']);
        $nc   = $this->$metodo($idEmpresa, $fNc);
        // El orden de cada consulta se pierde al mezclarlas: se reaplica sobre el resultado.
        $modo = self::MODO_POR_METODO[$metodo] ?? 'NINGUNO';

        // Modo detallado: mezclar filas, negando montos de las NC.
        if ($claves === null) {
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

        // Las fuentes que suman comparten clave (el mismo cliente o producto en una factura
        // y en un recibo): la segunda se ACUMULA sobre la primera, no la reemplaza.
        $idx = [];
        foreach ($fac as $r) {
            $k = $keyOf($r);
            unset($r['_doc_tipo']);
            if (!isset($idx[$k])) {
                $idx[$k] = $r;
                continue;
            }
            foreach (array_merge($restar, $sumar) as $c) {
                $idx[$k][$c] = (float) ($idx[$k][$c] ?? 0) + (float) ($r[$c] ?? 0);
            }
        }
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
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('anulado','facturado')
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
        $ambienteNota = "AND (n.tipo_ambiente IS NULL OR " . $this->condAmbiente('n') . ")";

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
    private function getCteBasesImpuestos(array $f, string $condLinea = ''): string
    {
        // Con filtros por línea (categoría, marca, producto, variante) solo cuentan las
        // líneas que cumplen, y se agrega su subtotal para armar el total del documento
        // (exprTotalDoc). Los impuestos se suman por línea en un LATERAL: unirlos directo
        // repetiría el subtotal de una línea con dos impuestos (IVA + ICE).
        if ($condLinea !== '') {
            return "
            SELECT
                d.{$f['fk_det']} AS id_doc,
                SUM(COALESCE(li.base_0, 0))   as base_0,
                SUM(COALESCE(li.base_iva, 0)) as base_iva,
                SUM(COALESCE(li.valor_iva, 0)) as valor_iva,
                SUM(COALESCE(d.precio_total_sin_impuesto, 0)) as subtotal
            FROM {$f['det']} d
            JOIN {$f['cab']} vcte ON vcte.id = d.{$f['fk_det']} AND vcte.id_empresa IN ({$this->inEmp})
            LEFT JOIN LATERAL (
                SELECT SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                       SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                       SUM(i.valor) as valor_iva
                FROM {$f['imp']} i WHERE i.{$f['fk_imp']} = d.id
            ) li ON true
            WHERE " . str_replace('{d}', 'd', $condLinea) . "
            GROUP BY d.{$f['fk_det']}
        ";
        }
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
     *    fuera aunque el cliente sea suyo. Las notas de crédito siguen la misma
     *    regla con su propio vendedor; solo si la BD aún no tiene esa columna (ver
     *    fuente()) manda el de la factura que modifican y, si esa factura no tiene
     *    o no se encuentra, el del cliente de la nota.
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

        // Filtro por Vendedor. Las notas de crédito usan su propio vendedor; solo si la BD
        // aún no tiene esa columna (ver fuente()) se toma el de la factura que modifican.
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

        // Filtro por Cajero = usuario responsable del documento (`id_usuario`, la columna
        // "Cajero" del detallado y la misma que usa el alcance de registros propios). Solo
        // acota: a un usuario restringido nunca le amplía lo que ya ve.
        if (!empty($filtros['id_cajero'])) {
            $where .= " AND {$aliasVenta}.id_usuario = :id_cajero";
            $params[':id_cajero'] = (int) $filtros['id_cajero'];
        }

        // Filtros por LÍNEA (producto, marca, categoría, texto del producto, variante): en los
        // agrupados por línea (alias de detalle) acotan las líneas; en los que van por
        // documento, entran los documentos con al menos una línea que cumpla, y sus montos
        // salen solo de esas líneas (ver condLinea / getCteBasesImpuestos).
        $condLinea = $this->condLinea($filtros, $f, $params);
        if ($condLinea !== '') {
            if ($aliasDetalle) {
                $where .= " AND " . str_replace('{d}', $aliasDetalle, $condLinea);
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vdl
                                        WHERE vdl.{$f['fk_det']} = {$aliasVenta}.id
                                          AND " . str_replace('{d}', 'vdl', $condLinea) . ")";
            }
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

        // Tercer elemento: la condición de línea (con `{d}` como alias del detalle), para que
        // los reportes por documento sumen solo las líneas filtradas. Comparte los params.
        return [$where, $params, $condLinea];
    }

    /**
     * Condición sobre UNA línea de detalle (alias `{d}`) con todos los filtros por línea
     * del reporte, o '' si no hay ninguno. Agrega sus placeholders a `$params`.
     *
     *  - Producto (id): `id_producto` de la línea. En consolidado, el controller ya
     *    expandió el id a los productos hermanos con el mismo código.
     *  - Marca / Categoría: la que tiene HOY la ficha del producto. Una línea sin producto
     *    (concepto libre) no entra. En consolidado, ya expandidas a las homónimas.
     *  - Producto (texto): descripción o código escritos en la línea.
     *  - Variante (texto): nombre o valor de la variante de la línea. Las notas de crédito
     *    no registran variante (su detalle no tiene `id_producto_variante`): con este
     *    filtro no entra ninguna NC.
     */
    private function condLinea(array $filtros, array $f, array &$params): string
    {
        $conds = [];

        if (!empty($filtros['id_producto'])) {
            $raw = is_array($filtros['id_producto']) ? $filtros['id_producto'] : explode(',', (string) $filtros['id_producto']);
            $ids = array_values(array_filter(array_map('intval', $raw)));
            if ($ids) {
                $ph = [];
                foreach ($ids as $i => $id) {
                    $ph[] = ":prod{$i}";
                    $params[":prod{$i}"] = $id;
                }
                $conds[] = "{d}.id_producto IN (" . implode(',', $ph) . ")";
            }
        }

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
            $conds[] = "EXISTS (SELECT 1 FROM productos pat WHERE pat.id = {d}.id_producto
                                AND pat.{$atributo} IN (" . implode(',', $ph) . "))";
        }

        // Tipo de producto de la ficha (productos.tipo_produccion): '01' bien, '02' servicio.
        // Sin valor en la ficha cuenta como bien (el mismo por defecto que el módulo Productos).
        // Una línea sin producto del catálogo (concepto libre) no entra.
        if (in_array($filtros['tipo_produccion'] ?? '', ['01', '02'], true)) {
            $conds[] = "EXISTS (SELECT 1 FROM productos ptp WHERE ptp.id = {d}.id_producto
                                AND COALESCE(NULLIF(TRIM(ptp.tipo_produccion), ''), '01') = :tipo_produccion)";
            $params[':tipo_produccion'] = $filtros['tipo_produccion'];
        }

        if (trim((string) ($filtros['producto_texto'] ?? '')) !== '') {
            $conds[] = "({d}.descripcion ILIKE :prodtxt OR {d}.codigo_principal ILIKE :prodtxt)";
            $params[':prodtxt'] = '%' . trim((string) $filtros['producto_texto']) . '%';
        }

        if (trim((string) ($filtros['variante_texto'] ?? '')) !== '') {
            if ($f['cab'] === 'notas_credito_cabecera') {
                $conds[] = 'false';
            } else {
                $conds[] = "EXISTS (SELECT 1 FROM productos_variantes pvf
                                    WHERE pvf.id = {d}.id_producto_variante
                                      AND (pvf.nombre ILIKE :vartxt OR pvf.valor ILIKE :vartxt))";
                $params[':vartxt'] = '%' . trim((string) $filtros['variante_texto']) . '%';
            }
        }

        return $conds ? '(' . implode(' AND ', $conds) . ')' : '';
    }

    /**
     * Total de cada documento en los reportes por documento: el `importe_total` de la
     * cabecera o, con filtros por línea, solo el de las líneas que cumplen (subtotal + sus
     * impuestos), para que coincida con lo que suma el agrupado por producto.
     */
    private function exprTotalDoc(string $condLinea, string $alias = 'v', string $aliasBases = 'b'): string
    {
        return $condLinea === ''
            ? "{$alias}.importe_total"
            : "(COALESCE({$aliasBases}.subtotal, 0) + COALESCE({$aliasBases}.valor_iva, 0))";
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
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $clave = $f['clave'] ? "COALESCE(v.clave_acceso, '')" : "''";
        // Retenciones aplicadas a cada factura: la MISMA regla que Cuentas por Cobrar, Ingresos,
        // el modal de la factura y el saldo de este reporte (AbonosVentaSql::cteRetenidoPorFactura):
        //  - enlace por id_venta (retención registrada desde la factura) y por número de sustento
        //    normalizado a 15 dígitos (retención electrónica del SRI que solo trae el número);
        //  - cada factura recibe la suma de `valor_retenido` de SUS líneas, nunca el total de la
        //    cabecera repetido por línea. El CTE anterior unía cabecera × detalle y sumaba el
        //    total de la cabecera una vez POR LÍNEA: una retención con IVA + renta (dos líneas)
        //    salía duplicada, y una que sustentaba varias facturas se cargaba entera a cada una.
        // Se resuelve como CTE (una sola pasada sobre las retenciones de la empresa), no como
        // subconsulta correlacionada por fila: con un filtro amplio (producto_texto por nombre)
        // el reporte puede devolver muchas filas, y repetir el escaneo de retenciones por cada
        // una escala mal (N filas × M retenciones) — en el droplet de producción (1 vCPU) eso
        // fue suficiente para saturar la conexión a BD y colgar el sitio entero.
        if ($f['retenciones']) {
            $retenCte  = ",\n                retenciones_agg AS (" . AbonosVentaSql::cteRetenidoPorFactura("ANY(ARRAY[{$this->inEmp}])") . ")";
            $retenJoin = "LEFT JOIN retenciones_agg ra ON ra.id_venta = v.id";
            $reten     = "COALESCE(ra.total_retenido, 0)";
        } else {
            $retenCte = "";
            $retenJoin = "";
            $reten = "0";
        }
        // NC sin columna id_vendedor (SQL no aplicado): se muestra el de la factura modificada.
        $vendedorSel  = "COALESCE(vend.nombre, '')";
        if ($f['vendedor']) {
            $vendedorJoin = "LEFT JOIN vendedores vend ON vend.id = v.id_vendedor";
        } else {
            $vendedorJoin = "LEFT JOIN ventas_cabecera fvnc ON " . $this->condicionFacturaDeNc('v', 'fvnc') . "
            LEFT JOIN vendedores vend ON vend.id = fvnc.id_vendedor";
        }
        // Motivo de la nota de crédito (facturas y recibos no tienen: sale vacío).
        $motivoSel = $f['cab'] === 'notas_credito_cabecera' ? "COALESCE(v.motivo, '')" : "''";
        $orden = $this->ordenSql($filtros, 'NINGUNO');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . "){$retenCte}
            SELECT
                v.id,
                v.id_empresa,
                (SELECT COALESCE(e.establecimiento, '') FROM empresas e WHERE e.id = v.id_empresa) AS establecimiento,
                v.fecha_emision,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                {$motivoSel} as motivo,
                v.estado,
                COALESCE(b.base_0, 0)   as base_0,
                COALESCE(b.base_iva, 0) as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                " . $this->exprTotalDoc($condLinea) . " as total,
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
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'CLIENTE');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . ")
            " . $this->getCtesSaldo($f) . "
            SELECT
                c.id as id_cliente,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(" . $this->exprTotalDoc($condLinea) . ") as total,
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
     * Reporte agrupado por producto: una fila por producto con su unidad de medida (la del
     * catálogo, productos.id_medida), el total vendido SIN impuestos (subtotal de las
     * líneas), las unidades y la participación de cada uno sobre el total del reporte
     * (pct_venta, pct_unidades). No se separa por tarifa de IVA ni se cruza con la tabla de
     * impuestos: una línea con IVA + ICE tiene dos filas de impuesto y duplicaría el subtotal.
     * La cantidad se suma tal como la registra la línea (sin convertir unidades).
     * En el neto "Facturas − NC" las unidades devueltas también se restan.
     */
    public function getReporteAgrupadoProducto(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            $rows = $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoProducto', ['id_producto', 'producto_nombre'],
                ['total_venta', 'cantidad_vendida']);
            // Los porcentajes de cada consulta (facturas / NC) no sirven para el neto: se
            // recalculan sobre el resultado combinado y se reordena por si el orden es por %.
            return $this->ordenarFilas($this->agregarParticipacion($rows), $filtros, 'PRODUCTO');
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');
        $orden = $this->ordenSql($filtros, 'PRODUCTO');

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(NULLIF(TRIM(p.codigo), ''), MAX(d.codigo_principal), '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                COALESCE(um.nombre, '') as unidad_medida,
                SUM(d.precio_total_sin_impuesto) as total_venta,
                SUM(d.cantidad) as cantidad_vendida
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN unidades_medida um ON um.id = p.id_medida
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), um.nombre
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $this->agregarParticipacion($st->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * % de cada fila sobre el total del reporte: pct_venta (total_venta) y pct_unidades
     * (cantidad_vendida). Con un total en cero el porcentaje queda en 0.
     */
    private function agregarParticipacion(array $rows): array
    {
        $totVenta = array_sum(array_map(static fn ($r) => (float) ($r['total_venta'] ?? 0), $rows));
        $totUnid  = array_sum(array_map(static fn ($r) => (float) ($r['cantidad_vendida'] ?? 0), $rows));
        foreach ($rows as &$r) {
            $r['pct_venta']    = abs($totVenta) > 0.000001 ? round((float) $r['total_venta'] / $totVenta * 100, 2) : 0.0;
            $r['pct_unidades'] = abs($totUnid)  > 0.000001 ? round((float) $r['cantidad_vendida'] / $totUnid * 100, 2) : 0.0;
        }
        unset($r);
        return $rows;
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
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'FECHA');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . ")
            SELECT
                v.fecha_emision as fecha,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(" . $this->exprTotalDoc($condLinea) . ") as total
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
     * Reporte agrupado por cajero: una fila por usuario responsable del documento
     * (`id_usuario`, la columna "Cajero" del detallado). Los documentos sin usuario
     * (migrados, id 0) quedan juntos en "Sin cajero".
     */
    public function getReporteAgrupadoCajero(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getReporteAgrupadoCajero', ['id_cajero'],
                ['base_0', 'base_iva', 'valor_iva', 'total'], ['cantidad_facturas']);
        }

        $f = $this->fuente($filtros);
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'CAJERO');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . ")
            SELECT
                COALESCE(v.id_usuario, 0) as id_cajero,
                COALESCE(MAX(u.nombre), 'Sin cajero') as cajero_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(" . $this->exprTotalDoc($condLinea) . ") as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            LEFT JOIN usuarios u ON u.id = v.id_usuario
            WHERE {$where}
            GROUP BY COALESCE(v.id_usuario, 0)
            ORDER BY {$orden}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Usuarios para el selector "Cajero": los asignados a la empresa (empresa_asignada),
     * más el usuario en sesión (un superadministrador no siempre está asignado y es quien
     * más documentos emite en empresas pequeñas). Sin DISTINCT sobre los documentos: en
     * tablas grandes recorre la empresa entera (ver memoria combos-distinct-loose-index-scan).
     */
    public function getCajerosCombo(int $idEmpresa, int $idUsuarioSesion): array
    {
        $st = $this->db->prepare("
            SELECT u.id, u.nombre
              FROM usuarios u
             WHERE u.id IN (SELECT ea.id_usuario FROM empresa_asignada ea WHERE ea.id_empresa = :e)
                OR u.id = :u
             ORDER BY u.nombre
        ");
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuarioSesion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Nombre de un usuario (para la caja de filtros del PDF y la tirilla). */
    public function getNombreUsuario(int $idUsuario): string
    {
        $st = $this->db->prepare("SELECT nombre FROM usuarios WHERE id = :id");
        $st->execute([':id' => $idUsuario]);
        return (string) ($st->fetchColumn() ?: '');
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
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');
        $orden = $this->ordenSql($filtros, 'MES');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . ")
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(" . $this->exprTotalDoc($condLinea) . ") as total
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

    // ── Rentabilidad (utilidad bruta) ─────────────────────────────────────────

    /** Movimiento de kardex que deja cada tipo de documento: [referencia_tipo, tipo_movimiento]. */
    private const KARDEX_DOCUMENTO = [
        'FACTURA'      => ['factura_venta', 'salida'],
        'RECIBO'       => ['recibo_venta', 'salida'],
        'NOTA_CREDITO' => ['nota_credito', 'entrada'],
    ];

    /**
     * Filas planas mes × producto de UNA fuente (facturas, recibos o notas de crédito) con
     * lo vendido sin impuestos y su costo. Base de getRentabilidad().
     *
     * Costo de cada producto en cada documento:
     *  - el `costo_total` de los movimientos del kardex que dejó ese documento para ese
     *    producto (salida de la factura/recibo; reingreso de la NC). Es el mismo costo que
     *    toma el asiento de Costo de Ventas (AsientoBuilderService). El kardex no guarda la
     *    línea, solo producto + documento: por eso las líneas se juntan primero por
     *    documento y producto (un producto en dos líneas no duplica el costo). Los
     *    componentes de un kit se cargan al producto vendido (ver la consulta).
     *  - $vista 'DETALLE' devuelve una fila por documento + producto (panel de detalle) y
     *    $grupo acota a una fila de la pantalla (ver getDetalleRentabilidad).
     *  - si el documento no movió inventario para ese producto (servicios, empresa cuya
     *    facturación no afecta al inventario, concepto libre), se ESTIMA con el costo de la
     *    ficha (`productos.costo_producto`) × cantidad, y se informa aparte en
     *    `costo_estimado`.
     * `venta_sin_costo` suma lo vendido cuyo costo quedó en 0 (sin costo en el kardex ni en
     * la ficha): su utilidad sale igual a la venta, y la pantalla lo advierte.
     */
    private function getRentabilidadPlano(int|array $idEmpresa, array $filtros, string $vista = 'RENT_PRODUCTO', ?array $grupo = null): array
    {
        $tipo = (string) ($filtros['tipo_documento'] ?? 'FACTURA');
        if (!isset(self::KARDEX_DOCUMENTO[$tipo])) {
            return [];
        }
        $f = $this->fuente($filtros);
        [$where, $params] = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');
        [$refTipo, $refMov] = self::KARDEX_DOCUMENTO[$tipo];
        $params[':rk_tipo'] = $refTipo;
        $params[':rk_mov']  = $refMov;
        $detalle = ($vista === 'DETALLE');

        // Vendedor del documento —el mismo que usa el filtro Vendedor—; la NC sin columna
        // id_vendedor (SQL no aplicado) toma el de la factura que modifica.
        $vendJoin = '';
        $vendExpr = 'NULLIF(v.id_vendedor, 0)';
        if (!$f['vendedor']) {
            $vendJoin = "LEFT JOIN LATERAL (SELECT NULLIF(fvnc.id_vendedor, 0) AS id_vendedor
                                           FROM ventas_cabecera fvnc
                                           WHERE " . $this->condicionFacturaDeNc('v', 'fvnc') . "
                                           LIMIT 1) vnc ON true";
            $vendExpr = 'vnc.id_vendedor';
        }

        // Dimensión extra según la vista (solo en esas: multiplica las filas).
        $dimLin = $dimJoin = $dimGrp = $dimLc = $dimSel = $dimJoinFin = $dimGrpFin = '';
        if ($vista === 'RENT_CLIENTE') {
            $dimLin     = ', v.id_cliente AS id_tercero';
            $dimGrp     = ', v.id_cliente';
            $dimLc      = ', l.id_tercero';
            $dimSel     = ", lc.id_tercero AS id_cliente, COALESCE(cli.nombre, '') AS cliente_nombre, COALESCE(cli.identificacion, '') AS cliente_ruc";
            $dimJoinFin = 'LEFT JOIN clientes cli ON cli.id = lc.id_tercero';
            $dimGrpFin  = ', lc.id_tercero, cli.nombre, cli.identificacion';
        } elseif ($vista === 'RENT_VENDEDOR') {
            // El cliente también viaja: la vista cuenta los clientes distintos de cada vendedor.
            $dimJoin    = $vendJoin;
            $dimLin     = ", {$vendExpr} AS id_tercero, v.id_cliente";
            $dimGrp     = ", {$vendExpr}, v.id_cliente";
            $dimLc      = ', l.id_tercero, l.id_cliente';
            $dimSel     = ", lc.id_tercero AS id_vendedor, COALESCE(TRIM(ven.nombre), '') AS vendedor_nombre, lc.id_cliente";
            $dimJoinFin = 'LEFT JOIN vendedores ven ON ven.id = lc.id_tercero';
            $dimGrpFin  = ', lc.id_tercero, ven.nombre, lc.id_cliente';
        } elseif ($detalle) {
            // Una fila por documento + producto, con lo que el panel de detalle necesita.
            $dimJoin = $vendJoin;
            $dimLin  = ", v.fecha_emision, CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) AS numero,
                        v.id_cliente, {$vendExpr} AS id_vendedor,
                        SUM(COALESCE(d.descuento, 0)) AS descuento,
                        SUM(d.cantidad * COALESCE(d.precio_unitario, 0)) AS bruto";
            $dimGrp  = ", v.fecha_emision, v.establecimiento, v.punto_emision, v.secuencial, v.id_cliente, {$vendExpr}";
        }

        // Fila elegida en pantalla (panel de detalle): acota las líneas a ese grupo con la
        // misma clave con que getRentabilidad() las juntó.
        $condGrupo = '';
        if ($grupo !== null) {
            $valor = (string) ($grupo['valor'] ?? '');
            $ids   = array_values(array_filter(array_map('intval', (array) ($grupo['ids'] ?? []))));
            switch ((string) ($grupo['vista'] ?? '')) {
                case 'RENT_PRODUCTO':
                    if ($ids) {
                        $ph = [];
                        foreach ($ids as $i => $id) { $ph[] = ":g_id{$i}"; $params[":g_id{$i}"] = $id; }
                        $condGrupo = ' AND d.id_producto IN (' . implode(',', $ph) . ')';
                    } else {
                        $condGrupo = ' AND d.id_producto IS NULL AND LOWER(TRIM(COALESCE(p.nombre, d.descripcion))) = :g_txt';
                    }
                    break;
                case 'RENT_CATEGORIA':
                    $condGrupo = " AND COALESCE((SELECT LOWER(TRIM(gc.nombre)) FROM categorias gc WHERE gc.id = p.id_categoria), '') = :g_txt";
                    break;
                case 'RENT_MARCA':
                    $condGrupo = " AND COALESCE((SELECT LOWER(TRIM(gm.nombre)) FROM marcas gm WHERE gm.id = p.id_marca), '') = :g_txt";
                    break;
                case 'RENT_MES':
                    $condGrupo = " AND TO_CHAR(v.fecha_emision, 'YYYY-MM') = :g_txt";
                    break;
                case 'RENT_CLIENTE':
                    $ph = [];
                    foreach ($ids ?: [0] as $i => $id) { $ph[] = ":g_id{$i}"; $params[":g_id{$i}"] = $id; }
                    $condGrupo = ' AND v.id_cliente IN (' . implode(',', $ph) . ')';
                    break;
                case 'RENT_VENDEDOR':
                    $condGrupo = " AND COALESCE((SELECT LOWER(TRIM(gv.nombre)) FROM vendedores gv WHERE gv.id = {$vendExpr}), '') = :g_txt";
                    break;
                default:
                    return [];
            }
            if (str_contains($condGrupo, ':g_txt')) {
                $params[':g_txt'] = $valor;
            }
        }

        // lin: una fila por documento + producto (lid la identifica). kx: costo del kardex por
        // documento + producto. Lo que el kardex descargó de un producto que NO es línea del
        // documento (mirando TODAS sus líneas, no solo las filtradas) son componentes de un
        // kit (InventarioService: "Salida componente de …"): su costo va al producto padre
        // vendido en ese documento (productos_componentes) o, si no se identifica, se reparte
        // entre las líneas del documento según su venta. Así el costo de cada documento suma
        // exactamente lo que descargó su kardex, y con un filtro por línea (marca, categoría,
        // tipo…) cada línea filtrada lleva solo su parte: el costo de las líneas que el
        // filtro deja fuera no se le carga.
        $sql = "
            WITH lin AS (
                SELECT ROW_NUMBER() OVER () AS lid,
                       v.id AS id_doc,
                       v.id_empresa,
                       d.id_producto,
                       TO_CHAR(v.fecha_emision, 'YYYY-MM') AS mes,
                       COALESCE(p.nombre, d.descripcion) AS producto_nombre,
                       MAX(d.codigo_principal) AS codigo_linea,
                       SUM(d.cantidad) AS cantidad,
                       SUM(d.precio_total_sin_impuesto) AS venta
                       {$dimLin}
                FROM {$f['det']} d
                JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
                LEFT JOIN productos p ON p.id = d.id_producto
                {$dimJoin}
                WHERE {$where}{$condGrupo}
                GROUP BY v.id, v.id_empresa, d.id_producto, TO_CHAR(v.fecha_emision, 'YYYY-MM'), COALESCE(p.nombre, d.descripcion){$dimGrp}
            ),
            docs AS (
                SELECT DISTINCT id_doc, id_empresa FROM lin
            ),
            kx AS (
                SELECT k.id_empresa, k.referencia_id, k.id_producto, SUM(k.costo_total) AS costo
                FROM inventario_kardex k
                JOIN docs ON docs.id_doc = k.referencia_id AND docs.id_empresa = k.id_empresa
                WHERE k.referencia_tipo = :rk_tipo
                  AND k.tipo_movimiento = :rk_mov
                  AND k.eliminado = false
                  AND k.id_empresa IN ({$this->inEmp})
                GROUP BY k.id_empresa, k.referencia_id, k.id_producto
            ),
            kc AS (
                SELECT kx.id_empresa, kx.referencia_id, kx.costo,
                       (SELECT MIN(pc.id_producto_padre)
                        FROM productos_componentes pc
                        JOIN {$f['det']} dp ON dp.{$f['fk_det']} = kx.referencia_id AND dp.id_producto = pc.id_producto_padre
                        WHERE pc.id_producto_hijo = kx.id_producto AND pc.eliminado = false) AS id_padre
                FROM kx
                WHERE NOT EXISTS (SELECT 1 FROM {$f['det']} dm
                                  WHERE dm.{$f['fk_det']} = kx.referencia_id AND dm.id_producto = kx.id_producto)
            ),
            tv AS (
                SELECT dt.{$f['fk_det']} AS id_doc, SUM(dt.precio_total_sin_impuesto) AS venta, COUNT(*) AS n
                FROM {$f['det']} dt
                JOIN docs ON docs.id_doc = dt.{$f['fk_det']}
                GROUP BY dt.{$f['fk_det']}
            ),
            extra AS (
                SELECT l.lid,
                       SUM(CASE WHEN kc.id_padre IS NOT NULL THEN kc.costo
                                WHEN tv.venta <> 0 THEN kc.costo * l.venta / tv.venta
                                ELSE kc.costo / tv.n END) AS costo
                FROM kc
                JOIN lin l ON l.id_doc = kc.referencia_id AND l.id_empresa = kc.id_empresa
                          AND (kc.id_padre IS NULL OR l.id_producto = kc.id_padre)
                JOIN tv ON tv.id_doc = l.id_doc
                GROUP BY l.lid
            ),
            lc AS (
                SELECT l.*,
                       kx.costo AS costo_kardex,
                       ex.costo AS costo_kit,
                       COALESCE(pf.costo_producto, 0) AS costo_ficha_unit,
                       (kx.referencia_id IS NOT NULL OR ex.lid IS NOT NULL) AS con_kardex,
                       CASE WHEN kx.referencia_id IS NOT NULL OR ex.lid IS NOT NULL
                            THEN COALESCE(kx.costo, 0) + COALESCE(ex.costo, 0)
                            ELSE l.cantidad * COALESCE(pf.costo_producto, 0) END AS costo
                FROM lin l
                LEFT JOIN kx ON kx.id_empresa = l.id_empresa AND kx.referencia_id = l.id_doc AND kx.id_producto = l.id_producto
                LEFT JOIN extra ex ON ex.lid = l.lid
                LEFT JOIN productos pf ON pf.id = l.id_producto
            )";

        if ($detalle) {
            $sql .= "
            SELECT lc.id_doc, lc.id_empresa, lc.fecha_emision, lc.numero, lc.mes,
                   lc.id_producto,
                   COALESCE(NULLIF(TRIM(p.codigo), ''), lc.codigo_linea, '') AS producto_codigo,
                   lc.producto_nombre,
                   lc.id_cliente,
                   COALESCE(cli.nombre, '') AS cliente_nombre,
                   COALESCE(cli.identificacion, '') AS cliente_ruc,
                   COALESCE(TRIM(ven.nombre), '') AS vendedor_nombre,
                   lc.cantidad, lc.venta, lc.descuento, lc.bruto,
                   lc.costo, lc.costo_kardex, lc.costo_kit, lc.costo_ficha_unit, lc.con_kardex,
                   (SELECT COALESCE(e.establecimiento, '') FROM empresas e WHERE e.id = lc.id_empresa) AS establecimiento
            FROM lc
            LEFT JOIN productos p    ON p.id = lc.id_producto
            LEFT JOIN clientes cli   ON cli.id = lc.id_cliente
            LEFT JOIN vendedores ven ON ven.id = lc.id_vendedor
            ORDER BY lc.fecha_emision, lc.numero
            ";
        } else {
            $sql .= "
            SELECT lc.mes,
                   lc.id_producto,
                   COALESCE(NULLIF(TRIM(p.codigo), ''), MAX(lc.codigo_linea), '') AS producto_codigo,
                   lc.producto_nombre,
                   COALESCE(TRIM(cat.nombre), '') AS categoria_nombre,
                   COALESCE(TRIM(mar.nombre), '') AS marca_nombre,
                   SUM(lc.cantidad) AS cantidad,
                   SUM(lc.venta)    AS venta,
                   SUM(lc.costo)    AS costo,
                   SUM(CASE WHEN lc.con_kardex THEN 0 ELSE lc.costo END) AS costo_estimado,
                   SUM(CASE WHEN COALESCE(lc.costo, 0) <= 0 AND lc.venta > 0 THEN lc.venta ELSE 0 END) AS venta_sin_costo
                   {$dimSel}
            FROM lc
            LEFT JOIN productos p    ON p.id = lc.id_producto
            LEFT JOIN categorias cat ON cat.id = p.id_categoria
            LEFT JOIN marcas mar     ON mar.id = p.id_marca
            {$dimJoinFin}
            GROUP BY lc.mes, lc.id_producto, p.codigo, lc.producto_nombre, cat.nombre, mar.nombre{$dimGrpFin}
            ";
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rentabilidad de lo vendido (venta sin impuestos − costo), agrupada por $vista:
     * RENT_PRODUCTO, RENT_CATEGORIA, RENT_MARCA, RENT_MES, RENT_CLIENTE o RENT_VENDEDOR. Respeta todos los filtros del
     * reporte (fechas, cliente, vendedor, cajero, producto, marca, categoría, alcance §6…).
     *
     * En el neto (Facturas − NC / Todos) las notas de crédito restan venta, unidades y
     * costo (el costo de lo devuelto vuelve al inventario). `venta_sin_costo` solo cuenta
     * lo vendido, no lo devuelto.
     *
     * Categoría y marca se agrupan por NOMBRE (sin distinguir mayúsculas ni espacios): en
     * consolidado cada establecimiento tiene las suyas y las homónimas van juntas. El cliente
     * se agrupa por identificación (IdentificacionTercero::claveGrupo: cédula y RUC del mismo
     * titular juntos, y el mismo cliente de varios establecimientos) y el vendedor por nombre.
     *
     * Campos de cada fila: cantidad_vendida, total_venta, costo, utilidad, margen (% sobre
     * la venta), markup (% sobre el costo; null sin costo), pct_utilidad (participación
     * en la utilidad del reporte), costo_estimado, venta_sin_costo, productos (distintos),
     * precio_promedio y costo_promedio (por unidad), clientes (distintos; vista por vendedor).
     */
    public function getRentabilidad(int|array $idEmpresa, array $filtros, string $vista): array
    {
        $tipo    = (string) ($filtros['tipo_documento'] ?? 'FACTURA');
        $fuentes = $this->esNeto($filtros)
            ? array_merge(array_map(static fn ($t) => [$t, 1], $this->fuentesSuma($filtros)), [['NOTA_CREDITO', -1]])
            : [[$tipo, 1]];

        $grupos = [];
        foreach ($fuentes as [$fuente, $signo]) {
            foreach ($this->getRentabilidadPlano($idEmpresa, array_merge($filtros, ['tipo_documento' => $fuente]), $vista) as $r) {
                $prodKey = !empty($r['id_producto']) ? 'p' . (int) $r['id_producto'] : 'd' . mb_strtolower(trim((string) $r['producto_nombre']));
                $cat     = trim((string) $r['categoria_nombre']);
                $mar     = trim((string) $r['marca_nombre']);
                $k = match ($vista) {
                    'RENT_CATEGORIA' => mb_strtolower($cat),
                    'RENT_MARCA'     => mb_strtolower($mar),
                    'RENT_MES'       => (string) $r['mes'],
                    'RENT_CLIENTE'   => \App\Helpers\IdentificacionTercero::claveGrupo((string) ($r['cliente_ruc'] ?? ''), 'id:' . (int) ($r['id_cliente'] ?? 0)),
                    'RENT_VENDEDOR'  => mb_strtolower(trim((string) ($r['vendedor_nombre'] ?? ''))),
                    default          => $prodKey,
                };
                if (!isset($grupos[$k])) {
                    $grupos[$k] = [
                        'id_producto'      => $vista === 'RENT_PRODUCTO' ? ($r['id_producto'] !== null ? (int) $r['id_producto'] : null) : null,
                        'producto_codigo'  => (string) $r['producto_codigo'],
                        'producto_nombre'  => (string) $r['producto_nombre'],
                        'categoria_nombre' => $cat,
                        'marca_nombre'     => $mar,
                        'mes'              => (string) $r['mes'],
                        'id_cliente'       => isset($r['id_cliente']) ? (int) $r['id_cliente'] : null,
                        'cliente_nombre'   => (string) ($r['cliente_nombre'] ?? ''),
                        'cliente_ruc'      => (string) ($r['cliente_ruc'] ?? ''),
                        'vendedor_nombre'  => trim((string) ($r['vendedor_nombre'] ?? '')),
                        'cantidad_vendida' => 0.0,
                        'total_venta'      => 0.0,
                        'costo'            => 0.0,
                        'costo_estimado'   => 0.0,
                        'venta_sin_costo'  => 0.0,
                        '_productos'       => [],
                        '_clientes'        => [],
                    ];
                }
                $g = &$grupos[$k];
                $g['cantidad_vendida'] += $signo * (float) $r['cantidad'];
                $g['total_venta']      += $signo * (float) $r['venta'];
                $g['costo']            += $signo * (float) $r['costo'];
                $g['costo_estimado']   += $signo * (float) $r['costo_estimado'];
                if ($signo > 0) {
                    $g['venta_sin_costo'] += (float) $r['venta_sin_costo'];
                }
                $g['_productos'][$prodKey] = true;
                if (!empty($r['id_cliente'])) {
                    $g['_clientes'][(int) $r['id_cliente']] = true;
                }
                unset($g);
            }
        }

        $totUtilidad = 0.0;
        foreach ($grupos as &$g) {
            foreach (['total_venta', 'costo', 'costo_estimado', 'venta_sin_costo'] as $c) {
                $g[$c] = round($g[$c], 2);
            }
            $g['productos']       = count($g['_productos']);
            $g['clientes']        = count($g['_clientes']);
            unset($g['_productos'], $g['_clientes']);
            $g['utilidad']        = round($g['total_venta'] - $g['costo'], 2);
            $g['margen']          = abs($g['total_venta']) > 0.004 ? round($g['utilidad'] / $g['total_venta'] * 100, 2) : 0.0;
            $g['markup']          = abs($g['costo']) > 0.004 ? round($g['utilidad'] / $g['costo'] * 100, 2) : null;
            $g['precio_promedio'] = abs($g['cantidad_vendida']) > 0.000001 ? round($g['total_venta'] / $g['cantidad_vendida'], 4) : 0.0;
            $g['costo_promedio']  = abs($g['cantidad_vendida']) > 0.000001 ? round($g['costo'] / $g['cantidad_vendida'], 4) : 0.0;
            $totUtilidad += $g['utilidad'];
        }
        unset($g);
        foreach ($grupos as &$g) {
            $g['pct_utilidad'] = abs($totUtilidad) > 0.004 ? round($g['utilidad'] / $totUtilidad * 100, 2) : 0.0;
        }
        unset($g);

        return $this->ordenarFilas(array_values($grupos), $filtros, $vista);
    }

    /**
     * Detalle de UNA fila de la rentabilidad (panel que se abre al hacer clic): los
     * documentos y productos que la forman, uno por fila, con su venta, su costo, de dónde
     * salió el costo y la posible causa cuando la utilidad es negativa o baja.
     *
     * $grupo = ['vista' => RENT_*, 'valor' => clave de texto, 'ids' => ids]: la misma clave
     * con que getRentabilidad() juntó la fila (producto, categoría, marca, mes, cliente o
     * vendedor). Respeta todos los filtros del reporte. En el neto las notas de crédito
     * vienen con importes en negativo y `es_nc` = true.
     *
     * Causas (`causa`, códigos; la pantalla las traduce):
     *  - precio_bajo_costo: precio unitario neto menor que el costo unitario.
     *  - costo_atipico: costo unitario de esa salida > 130 % del costo promedio del mismo
     *    producto en el detalle (suele ser una entrada al kardex con un costo equivocado).
     *  - descuento_alto: con el descuento la línea queda bajo el costo, sin él no.
     *  - nc_sin_reingreso: nota de crédito que no reingresó al inventario (costo estimado o 0).
     *  - kit: el costo sale de los componentes de un kit.
     *  - sin_costo / costo_estimado: sin costo, o costo estimado con la ficha.
     */
    public function getDetalleRentabilidad(int|array $idEmpresa, array $filtros, array $grupo, int $limite = 2000): array
    {
        $tipo    = (string) ($filtros['tipo_documento'] ?? 'FACTURA');
        $fuentes = $this->esNeto($filtros)
            ? array_merge(array_map(static fn ($t) => [$t, 1], $this->fuentesSuma($filtros)), [['NOTA_CREDITO', -1]])
            : [[$tipo, 1]];

        $filas = [];
        foreach ($fuentes as [$fuente, $signo]) {
            foreach ($this->getRentabilidadPlano($idEmpresa, array_merge($filtros, ['tipo_documento' => $fuente]), 'DETALLE', $grupo) as $r) {
                $cant  = (float) $r['cantidad'];
                $venta = (float) $r['venta'];
                $costo = (float) $r['costo'];
                $origen = !$r['con_kardex']
                    ? ($costo > 0.004 ? 'ficha' : 'sin_costo')
                    : (abs((float) ($r['costo_kit'] ?? 0)) > 0.004 && abs((float) ($r['costo_kardex'] ?? 0)) < 0.004 ? 'kit'
                        : (abs((float) ($r['costo_kit'] ?? 0)) > 0.004 ? 'kardex_kit' : 'kardex'));
                $filas[] = [
                    'id_doc'          => (int) $r['id_doc'],
                    'doc_tipo'        => $fuente,
                    'es_nc'           => $fuente === 'NOTA_CREDITO',
                    'establecimiento' => (string) $r['establecimiento'],
                    'fecha'           => (string) $r['fecha_emision'],
                    'numero'          => (string) $r['numero'],
                    'cliente_nombre'  => (string) $r['cliente_nombre'],
                    'cliente_ruc'     => (string) $r['cliente_ruc'],
                    'vendedor_nombre' => (string) $r['vendedor_nombre'],
                    'id_producto'     => $r['id_producto'] !== null ? (int) $r['id_producto'] : null,
                    'producto_codigo' => (string) $r['producto_codigo'],
                    'producto_nombre' => (string) $r['producto_nombre'],
                    'cantidad'        => $signo * $cant,
                    'precio_unitario' => abs($cant) > 0.000001 ? round($venta / $cant, 4) : 0.0,
                    'descuento'       => round($signo * (float) $r['descuento'], 2),
                    'venta'           => round($signo * $venta, 2),
                    'costo_unitario'  => abs($cant) > 0.000001 ? round($costo / $cant, 4) : 0.0,
                    'costo'           => round($signo * $costo, 2),
                    'costo_ficha_unit'=> round((float) $r['costo_ficha_unit'], 4),
                    'origen'          => $origen,
                    '_bruto'          => (float) $r['bruto'],
                ];
            }
        }

        // Costo unitario promedio de cada producto en el detalle (solo salidas del kardex de
        // facturas/recibos): referencia para marcar un costo fuera de lo normal.
        $acum = [];
        foreach ($filas as $x) {
            // Las salidas con costo 0 no cuentan: bajarían el promedio y harían ver «fuera de
            // lo normal» a las que sí tienen costo.
            if (!$x['es_nc'] && $x['id_producto'] !== null && in_array($x['origen'], ['kardex', 'kardex_kit', 'kit'], true)
                && $x['cantidad'] > 0 && $x['costo'] > 0.004) {
                $acum[$x['id_producto']]['c'] = ($acum[$x['id_producto']]['c'] ?? 0) + $x['costo'];
                $acum[$x['id_producto']]['q'] = ($acum[$x['id_producto']]['q'] ?? 0) + $x['cantidad'];
            }
        }

        foreach ($filas as &$x) {
            $x['utilidad'] = round($x['venta'] - $x['costo'], 2);
            $x['margen']   = abs($x['venta']) > 0.004 ? round($x['utilidad'] / $x['venta'] * 100, 2) : 0.0;
            $prom = ($x['id_producto'] !== null && !empty($acum[$x['id_producto']]['q']))
                ? $acum[$x['id_producto']]['c'] / $acum[$x['id_producto']]['q'] : null;
            $x['costo_promedio'] = $prom !== null ? round($prom, 4) : null;

            $causas = [];
            if ($x['es_nc']) {
                if (!in_array($x['origen'], ['kardex', 'kardex_kit', 'kit'], true)) {
                    $causas[] = 'nc_sin_reingreso';
                }
            } else {
                $bajoCosto = $x['costo_unitario'] > 0 && $x['precio_unitario'] < $x['costo_unitario'] - 0.00005;
                if ($bajoCosto) {
                    // ¿Sin el descuento la línea sí cubría el costo?
                    $brutoUnit = abs($x['cantidad']) > 0.000001 ? $x['_bruto'] / abs($x['cantidad']) : 0;
                    $causas[] = ($x['descuento'] > 0.004 && $brutoUnit >= $x['costo_unitario']) ? 'descuento_alto' : 'precio_bajo_costo';
                }
                if ($prom !== null && $prom > 0 && in_array($x['origen'], ['kardex', 'kardex_kit'], true)
                    && $x['costo_unitario'] > $prom * 1.3) {
                    $causas[] = 'costo_atipico';
                }
                if ($x['origen'] === 'kit' || $x['origen'] === 'kardex_kit') {
                    $causas[] = 'kit';
                }
                // Sin costo: ni en el kardex (salida registrada con costo 0) ni en la ficha.
                if ($x['costo'] <= 0.004 && $x['venta'] > 0.004) {
                    $causas[] = 'sin_costo';
                } elseif ($x['origen'] === 'ficha') {
                    $causas[] = 'costo_estimado';
                }
            }
            $x['causas'] = $causas;
            unset($x['_bruto']);
        }
        unset($x);

        // Peor utilidad primero: lo que explica una fila en rojo queda arriba.
        usort($filas, static fn (array $a, array $b): int => [$a['utilidad'], $a['fecha']] <=> [$b['utilidad'], $b['fecha']]);
        $total = count($filas);
        return ['filas' => array_slice($filas, 0, $limite), 'total_filas' => $total];
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

    // ── Unidades vendidas por cliente y categoría ─────────────────────────────

    /**
     * Filas planas cliente × categoría con las unidades (SUM(cantidad)) y el total sin
     * impuestos (SUM(precio_total_sin_impuesto)) de las líneas. Es la consulta base de
     * getReporteClienteCategoria(), que la pivotea; va aparte para que el neto
     * "Facturas − NC" reste las devoluciones (combinarNeto). La categoría es la de la ficha
     * del producto; una línea sin producto o un producto sin categoría queda con ''.
     */
    public function getClienteCategoriaPlano(int|array $idEmpresa, array $filtros): array
    {
        if ($this->esNeto($filtros)) {
            return $this->combinarNeto($idEmpresa, $filtros, 'getClienteCategoriaPlano',
                ['id_cliente', 'categoria'], ['cantidad', 'total_venta']);
        }

        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                c.id AS id_cliente,
                COALESCE(c.identificacion, '') AS cliente_ruc,
                c.nombre AS cliente_nombre,
                COALESCE(TRIM(cat.nombre), '') AS categoria,
                SUM(d.cantidad) AS cantidad,
                SUM(d.precio_total_sin_impuesto) AS total_venta
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN categorias cat ON cat.id = p.id_categoria
            WHERE {$where}
            GROUP BY 1, 2, 3, 4
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Unidades vendidas por cliente y por categoría: una fila por cliente con las unidades
     * de cada categoría, el total de unidades y el total vendido sin impuestos. Devuelve
     * ['categorias' => [clave => nombre, …], 'rows' => […]].
     *
     * - Las columnas son las categorías con ventas en el resultado, en orden alfabético, y
     *   al final "Sin categoría" (líneas sin producto o productos sin categoría), si la hay.
     * - La categoría se agrupa por nombre (sin distinguir mayúsculas ni espacios): en
     *   consolidado por RUC cada establecimiento tiene su propia lista y así se suman.
     * - El cliente se agrupa por identificación (IdentificacionTercero::claveGrupo): en
     *   consolidado y con cédula/RUC de la misma persona sale una sola fila.
     * - Solo salen los clientes con unidades o venta mayores que cero (en el neto, los que
     *   quedaron en cero o en negativo por devoluciones se omiten).
     * - El orden se aplica en PHP: cliente, totales o cualquier categoría (`cat:<clave>`).
     */
    public function getReporteClienteCategoria(int|array $idEmpresa, array $filtros): array
    {
        $plano = $this->getClienteCategoriaPlano($idEmpresa, $filtros);

        $catNombre = [];   // clave => nombre a mostrar (el primero que aparece)
        $idx       = [];
        foreach ($plano as $r) {
            $nombreCat = trim((string) ($r['categoria'] ?? ''));
            // Clave con prefijo de letra: un hash solo de dígitos se volvería clave entera en PHP.
            $claveCat  = $nombreCat === '' ? 'sin' : 'k' . substr(md5(mb_strtolower($nombreCat)), 0, 11);
            $catNombre[$claveCat] ??= $nombreCat === '' ? 'Sin categoría' : $nombreCat;

            $k = \App\Helpers\IdentificacionTercero::claveGrupo((string) ($r['cliente_ruc'] ?? ''), 'id:' . $r['id_cliente']);
            if (!isset($idx[$k])) {
                $idx[$k] = [
                    'id_cliente'     => $r['id_cliente'],
                    'cliente_ruc'    => (string) ($r['cliente_ruc'] ?? ''),
                    'cliente_nombre' => (string) ($r['cliente_nombre'] ?? ''),
                    'categorias'     => [],
                    'total_unidades' => 0.0,
                    'total_venta'    => 0.0,
                ];
            }
            $cant = (float) ($r['cantidad'] ?? 0);
            $idx[$k]['categorias'][$claveCat] = ($idx[$k]['categorias'][$claveCat] ?? 0.0) + $cant;
            $idx[$k]['total_unidades'] += $cant;
            $idx[$k]['total_venta']    += (float) ($r['total_venta'] ?? 0);
        }
        $rows = array_values(array_filter($idx, static fn (array $r): bool
            => $r['total_unidades'] > 0.000001 || $r['total_venta'] > 0.000001));

        // Columnas: solo las categorías que quedan en las filas, alfabéticas; "Sin categoría" al final.
        $usadas = [];
        foreach ($rows as $r) {
            foreach ($r['categorias'] as $c => $v) {
                if (abs($v) > 0.000001) { $usadas[$c] = true; }
            }
        }
        $categorias = array_intersect_key($catNombre, $usadas);
        uksort($categorias, static fn (string $a, string $b): int
            => ($a === 'sin') <=> ($b === 'sin') ?: strcasecmp($categorias[$a], $categorias[$b]));
        foreach ($rows as &$r) {
            $r['categorias'] = array_map(static fn (string $c): float => (float) ($r['categorias'][$c] ?? 0),
                array_combine(array_keys($categorias), array_keys($categorias)));
        }
        unset($r);

        // Orden: una categoría del resultado, o una de las columnas fijas (lista blanca del modo).
        $colPedida = (string) ($filtros['orden_col'] ?? '');
        if (preg_match('/^cat:([0-9a-z]{3,12})$/', $colPedida, $m) && isset($categorias[$m[1]])) {
            $cat = $m[1];
            $dir = strtoupper((string) ($filtros['orden_dir'] ?? '')) === 'ASC' ? 'ASC' : 'DESC';
            $val = static fn (array $r) => (float) ($r['categorias'][$cat] ?? 0);
        } else {
            [$col, $dir] = $this->resolverOrden($filtros, 'CLIENTE_CATEGORIA');
            $val = static fn (array $r) => $r[$col] ?? null;
        }
        $signo = $dir === 'ASC' ? 1 : -1;
        usort($rows, static function (array $a, array $b) use ($val, $signo): int {
            $x = $val($a);
            $y = $val($b);
            $cmp = (is_numeric($x) && is_numeric($y))
                ? ((float) $x <=> (float) $y)
                : strcasecmp((string) $x, (string) $y);
            return ($signo * $cmp) ?: strcasecmp($a['cliente_nombre'], $b['cliente_nombre']);
        });

        return ['categorias' => $categorias, 'rows' => $rows];
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
            // Suma de las fuentes que suman (facturas y, en TODOS, recibos) menos las NC.
            $sf = ['total_base_0' => 0.0, 'total_base_iva' => 0.0, 'total_iva' => 0.0, 'gran_total' => 0.0, 'total_documentos' => 0];
            foreach ($this->fuentesSuma($filtros) as $tipo) {
                foreach ($this->getEstadisticas($idEmpresa, array_merge($filtros, ['tipo_documento' => $tipo])) as $k => $v) {
                    $sf[$k] += $v;
                }
            }
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
        [$where, $params, $condLinea] = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f, $condLinea) . ")
            SELECT
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(" . $this->exprTotalDoc($condLinea) . ") as gran_total,
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
            $rf = ['autorizados' => 0, 'anulados' => 0, 'borradores' => 0];
            foreach ($this->fuentesSuma($filtros) as $tipo) {
                foreach ($this->getResumenEstados($idEmpresa, array_merge($filtros, ['tipo_documento' => $tipo])) as $k => $v) {
                    $rf[$k] += $v;
                }
            }
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

        // En recibos `borrador` es el estado normal de un recibo vigente (ver
        // condEstadoRecibo()): cuenta como válido, no como borrador.
        $esRecibo = $f['cab'] === 'recibos_venta_cabecera';

        foreach ($rows as $row) {
            $estado = $row['estado'];
            if ($esRecibo && $estado === 'borrador') {
                $estado = 'emitido';
            }
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

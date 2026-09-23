<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use App\Helpers\AbonosVentaSql;
use App\repositories\BaseRepository;

class FacturaVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Columnas ordenables del listado: clave que manda la vista (`data-sort`) =>
     * expresión SQL con la que se ordena. Es la whitelist del ORDER BY y el mapa que
     * necesita `OrdenListado::clausula()` para encadenar varias columnas.
     *
     * A diferencia de otros módulos NO es una constante de clase: `estado_pago` se
     * ordena por una expresión que se arma en tiempo de ejecución con las subconsultas
     * de abonos de `AbonosVentaSql`, y eso no cabe en una expresión constante.
     */
    private function mapaOrden(): array
    {
        return [
            'id'                  => 'v.id',
            'fecha_emision'       => 'v.fecha_emision',
            'secuencial'          => 'v.secuencial',
            'numero'              => 'v.secuencial',
            'importe_total'       => 'v.importe_total',
            'total_sin_impuestos' => 'v.total_sin_impuestos',
            'total_descuento'     => 'v.total_descuento',
            'total_ice'           => 'v.total_ice',
            'propina'             => 'v.propina',
            'estado'              => 'v.estado',
            'estado_correo'       => 'v.estado_correo',
            'observaciones'       => 'v.observaciones',
            // Columnas que vienen de un JOIN: se prefija la tabla correcta.
            'cliente_nombre'      => 'c.nombre',
            'cliente_ruc'         => 'c.identificacion',
            'vendedor_nombre'     => 'ven.nombre',
            'usuario_nombre'      => 'u.nombre',
            // Calculadas: el IVA no es una columna y el estado de pago se deduce de
            // cuánto se ha abonado (1 sin cobrar, 2 parcial, 3 pagada, 4 anulada).
            // ab.abonos sale del LEFT JOIN LATERAL que arma getListado() (alias "ab",
            // ver ahí) — la fórmula de abonos ya no se repite aquí.
            'iva'                 => '(v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0))',
            'estado_pago'         => "CASE WHEN v.estado = 'anulado' THEN 4 "
                                     . "WHEN (v.importe_total - ab.abonos) <= 0 THEN 3 "
                                     . "WHEN ab.abonos > 0 THEN 2 ELSE 1 END",
        ];
    }

    /**
     * Texto propio de la factura que entra en la búsqueda libre: número, secuencial,
     * observaciones, guía de remisión, placa, fecha de emisión y los importes
     * (subtotal, descuento, IVA calculado, ICE, propina y total) como texto.
     * `$a` es el prefijo del alias ('v.' en la consulta, '' en el CREATE INDEX): la MISMA
     * expresión alimenta la consulta y el índice, así que no se pueden desalinear.
     *
     * Los importes van aquí —y no como columnas numéricas aparte, que era lo de antes—
     * porque así buscar "298" o "45.50" también usa el índice. El resultado es el mismo:
     * una palabra sin dígitos nunca podía coincidir con un número.
     *
     * La CLAVE DE ACCESO quedó FUERA a propósito (17-09-2026). Son 49 dígitos —fecha,
     * RUC, serie, secuencial y un código numérico aleatorio de 8— así que buscar un
     * número de factura corto caía dentro de la clave de otras facturas por puro azar y
     * el listado devolvía filas sin ninguna coincidencia visible: buscar "556605" traía
     * facturas ajenas (medido en local: "657400", un trozo del RUC, devolvía TODAS).
     * Para buscar por clave está el filtro `clave:…` / `clave_acceso:…`.
     */
    private static function exprFactura(string $a = ''): string
    {
        $m = \App\Helpers\MotorBusqueda::class;
        // Número en formato canónico 000-000-000000000 (SecuencialFormato::sqlNumeroCompleto):
        // así se encuentra escribiéndolo como está en el documento aunque el secuencial se
        // haya guardado sin los ceros a la izquierda.
        return \App\Helpers\SecuencialFormato::sqlNumeroCompleto("{$a}establecimiento", "{$a}punto_emision", "{$a}secuencial")
             . " || ' ' || COALESCE({$a}observaciones, '')"
             . " || ' ' || COALESCE({$a}guia_remision, '')"
             . " || ' ' || COALESCE({$a}placa, '')"
             . " || ' ' || " . $m::fechaIso("{$a}fecha_emision")
             . " || ' ' || COALESCE({$a}total_sin_impuestos::text, '')"
             . " || ' ' || COALESCE({$a}total_descuento::text, '')"
             . " || ' ' || COALESCE(({$a}importe_total - {$a}total_sin_impuestos + {$a}total_descuento - COALESCE({$a}total_ice, 0) - COALESCE({$a}propina, 0))::text, '')"
             . " || ' ' || COALESCE({$a}total_ice::text, '')"
             . " || ' ' || COALESCE({$a}propina::text, '')"
             . " || ' ' || COALESCE({$a}importe_total::text, '')";
    }

    /**
     * Fuentes del texto libre del listado (ver App\Helpers\MotorBusqueda). Cada una es un
     * conjunto que PostgreSQL resuelve UNA vez por palabra con su índice trigram, en lugar
     * de recorrer todas las facturas de la empresa quitándole las tildes al texto de cada
     * una y juntando las líneas de su detalle.
     *
     * Qué busca: número, secuencial, observaciones, guía, placa, fecha, importes, saldo
     * pendiente, cliente (nombre e identificación) y vendedor.
     *
     * Qué NO busca, por decisión del usuario (17-09-2026), y dónde se busca en su lugar:
     *   - Clave de acceso  → filtro `clave:…` (ver exprFactura(): traía filas al azar).
     *   - Usuario que registró → filtro `usuario:…`.
     *   - Productos del detalle (código y descripción) → pestaña "Detalles" del modal de
     *     filtros, que llama a buscarEnDetalles() y SÍ dice qué línea coincidió; desde el
     *     listado la factura aparecía sin que se viera el motivo.
     * Estado, Estado correo y Estado pago siguen fuera del texto libre (misma decisión, de
     * antes): se filtran desde el modal.
     *
     * @param string $saldo Expresión del saldo (usa el LATERAL de abonos `ab`).
     */
    private function fuentesBusqueda(string $saldo): array
    {
        $decimal = \App\Helpers\FiltrosBusqueda::SI_DECIMAL;

        return [
            // Datos propios de la factura (texto e importes)
            [
                'sql'    => "v.id IN (SELECT bx.id FROM ventas_cabecera bx WHERE bx.id_empresa = :id_empresa AND {cond})",
                'expr'   => self::exprFactura('bx.'),
                'indice' => ['tabla' => 'ventas_cabecera', 'nombre' => 'idx_trgm_ventas_cabecera', 'expr' => self::exprFactura()],
            ],
            // Importes escritos con coma decimal ("34,78"): el texto indexado los guarda con
            // punto, así que esa forma se compara aparte y solo para palabras de ese tipo.
            [
                'expr'  => "CONCAT_WS(' ', v.fecha_emision, v.total_sin_impuestos, v.total_descuento,
                                      (v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice, 0) - COALESCE(v.propina, 0)),
                                      v.total_ice, v.propina, v.importe_total)",
                'crudo' => true,
                'si'    => '/^\d+,\d{1,2}$/',
            ],
            // Saldo pendiente: se calcula con los cobros, notas de crédito y retenciones de
            // cada factura, así que no se puede indexar. Igual que antes, solo se evalúa
            // cuando la palabra parece un monto con decimales.
            ['expr' => "ROUND($saldo, 2)", 'crudo' => true, 'si' => $decimal],
            // Cliente: nombre e identificación (mismo índice que usan los demás módulos)
            [
                'sql'    => "v.id_cliente IN (SELECT cx.id FROM clientes cx WHERE {cond})",
                'expr'   => "COALESCE(cx.nombre, '') || ' ' || COALESCE(cx.identificacion, '')",
                'indice' => ['tabla' => 'clientes', 'nombre' => 'idx_trgm_clientes', 'expr' => "COALESCE(nombre, '') || ' ' || COALESCE(identificacion, '')"],
            ],
            // Vendedor: tabla chica, sin índice.
            ['sql' => "v.id_vendedor IN (SELECT vx.id FROM vendedores vx WHERE {cond})", 'expr' => "COALESCE(vx.nombre, '')"],
        ];
    }

    /** SQL de los índices que necesita la búsqueda de este módulo (para database/*.sql). */
    public function sqlIndicesBusqueda(): array
    {
        return \App\Helpers\MotorBusqueda::sqlIndices($this->fuentesBusqueda('(v.importe_total - ab.abonos)'));
    }

    /**
     * @param array $ordenMulti Criterios de orden [['col'=>…,'dir'=>…], …] cuando el
     *        llamador usa `OrdenListado`. Vacío = se ordena por $ordenCol/$ordenDir.
     */
    public function getListado(int $idEmpresa, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'fecha_emision', string $ordenDir = 'DESC', ?int $idUsuario = null, array $ordenMulti = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':id_empresa' => $idEmpresa];

        $where = "WHERE v.id_empresa = :id_empresa AND v.eliminado = FALSE AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        // Parsear filtros (sintaxis tipo "clave:valor") y texto libre
        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        // Abonos (cobros + notas de crédito + retenciones) y saldo, con la regla
        // compartida de AbonosVentaSql (enlace por dígitos; retención repartida por
        // línea si sustenta varias facturas). Los usan el texto libre (columnas Saldo
        // y Estado pago del listado), el filtro "estado de pago" y el numérico "saldo".
        //
        // Antes esta fórmula (3 subconsultas correlacionadas: cobros + NC + retención)
        // se repetía como texto en varios sitios de la misma consulta (texto libre,
        // filtro pago:, ORDER BY de mapaOrden()) — Postgres la recalculaba una vez por
        // cada aparición, no una sola vez por fila. Ahora se calcula UNA vez por fila
        // en el LEFT JOIN LATERAL "ab" (armado más abajo, junto al resto de columnas
        // de abonos que ya se mostraban en el listado: total_cobrado/nc/nd/retención),
        // y $sqlAbonos/$saldo solo referencian ese alias.
        $sqlAbonos = 'ab.abonos';
        $saldo = '(v.importe_total - ab.abonos)';

        // Texto libre: las columnas del listado (incluidas las calculadas IVA y Saldo) y
        // sus relacionadas. El buscador de la vista no sugiere campos; lo escrito se busca
        // en todo LO QUE SE VE en el listado — ni más ni menos. Lo que quedó fuera a
        // propósito y dónde se busca en su lugar está en fuentesBusqueda(): clave de
        // acceso, usuario que registró, productos del detalle, Estado, Estado correo y
        // Estado pago.
        if ($textoLibre !== '') {
            // Conjuntos indexables (ver fuentesBusqueda() y App\Helpers\MotorBusqueda): cada
            // fuente se resuelve una vez por palabra con su índice trigram. El SALDO —que
            // obliga a calcular el LATERAL de abonos de TODAS las facturas de la empresa— es
            // lo único que sigue evaluándose por fila, y solo si la palabra parece un monto
            // con decimales, igual que antes.
            $condicion = \App\Helpers\MotorBusqueda::condicion(
                $this->fuentesBusqueda($saldo),
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // ── Filtro especial: estado de pago (campo CALCULADO, no es columna) ──────
        // Sintaxis: pago:pendiente | pago:abonada | pago:pagada (acepta sinónimos
        // y lista, p. ej. pago:pendiente,abonada). Se resuelve con las mismas
        // sumatorias que la columna.
        $pagoFiltro = $filtros['estado_pago'] ?? $filtros['pago'] ?? null;
        unset($filtros['estado_pago'], $filtros['pago']);
        if ($pagoFiltro !== null) {
            $valores = is_array($pagoFiltro['valor']) ? $pagoFiltro['valor'] : [$pagoFiltro['valor']];
            $conds = [];
            foreach ($valores as $val) {
                $v2 = strtolower(trim((string)$val));
                if (in_array($v2, ['pagada', 'pagado', 'pagadas', 'cobrada', 'cobrado'], true)) {
                    $conds[] = "$saldo <= 0";
                } elseif (in_array($v2, ['abonada', 'abonado', 'abonadas', 'parcial', 'abono'], true)) {
                    $conds[] = "($saldo > 0 AND $sqlAbonos > 0)";
                } elseif (in_array($v2, ['pendiente', 'pendientes', 'falta', 'sinpago', 'impaga', 'impagada'], true)) {
                    $conds[] = "($saldo > 0 AND $sqlAbonos <= 0)";
                }
            }
            if ($conds) {
                $cond = '(' . implode(' OR ', $conds) . ')';
                if (!empty($pagoFiltro['neg'])) $cond = "NOT $cond";
                $where .= " AND $cond";
            }
        }

        // Aplicar filtros estructurados usando el helper genérico
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'ci'             => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'numero'         => "CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)",
                'nro'            => "CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)",
                'vendedor'       => 'ven.nombre',
                'usuario'        => 'u.nombre',
                'obs'            => 'v.observaciones',
                'observacion'    => 'v.observaciones',
                'autorizacion'   => 'v.numero_autorizacion',
                'clave'          => 'v.clave_acceso',
                'clave_acceso'   => 'v.clave_acceso',
                'placa'          => 'v.placa',
                'guia'           => 'v.guia_remision',
                'guia_remision'  => 'v.guia_remision',
            ],
            'exacto' => [
                'estado'         => 'v.estado',
                'estado_correo'  => 'v.estado_correo',
                'correo'         => 'v.estado_correo',
                'estab'          => 'v.establecimiento',
                'establecimiento' => 'v.establecimiento',
                'punto'          => 'v.punto_emision',
                'punto_emision'  => 'v.punto_emision',
                // Serie = establecimiento-puntoEmision (ej. "001-001"), tal como se
                // muestra en el selector "Serie" del modal de factura.
                'serie'          => "CONCAT(v.establecimiento,'-',v.punto_emision)",
                'id_vendedor'    => 'v.id_vendedor',
                'id_usuario'     => 'v.id_usuario',
                // ambiente:1 (pruebas) / ambiente:2 (producción). El listado ya se
                // acota al ambiente actual de la empresa; el filtro sirve para la
                // exportación y para quien lo escriba en el enlace.
                'ambiente'       => 'v.tipo_ambiente',
                // asiento:si / asiento:no
                'asiento'        => "CASE WHEN v.id_asiento_contable IS NULL THEN 'no' ELSE 'si' END",
                // origen:proforma / pos / publicidad / pedido / directa
                'origen'         => "CASE WHEN v.id_proforma IS NOT NULL THEN 'proforma'
                                          WHEN v.id_caja_sesion IS NOT NULL THEN 'pos'
                                          WHEN v.id_cotizacion_publicidad IS NOT NULL THEN 'publicidad'
                                          WHEN EXISTS (SELECT 1 FROM ventas_detalle vdo WHERE vdo.id_venta = v.id AND vdo.id_pedido_detalle IS NOT NULL) THEN 'pedido'
                                          ELSE 'directa' END",
            ],
            'fecha' => [
                'fecha'              => 'v.fecha_emision',
                'fecha_emision'      => 'v.fecha_emision',
                'fecha_autorizacion' => 'v.fecha_autorizacion',
                'autorizada'         => 'v.fecha_autorizacion',
            ],
            'numerico' => [
                'monto'     => 'v.importe_total',
                'total'     => 'v.importe_total',
                'subtotal'  => 'v.total_sin_impuestos',
                'descuento' => 'v.total_descuento',
                'ice'       => 'COALESCE(v.total_ice,0)',
                'propina'   => 'COALESCE(v.propina,0)',
                'iva'       => '(v.importe_total - v.total_sin_impuestos + v.total_descuento - COALESCE(v.total_ice,0) - COALESCE(v.propina,0))',
                'saldo'     => $saldo,
                'dias_credito' => 'COALESCE(v.dias_credito,0)',
                // Comparación numérica: "298" encuentra "000000298" sin que el
                // usuario tenga que escribir los ceros a la izquierda, y sigue
                // siendo coincidencia EXACTA (el bucket numérico convierte ILIKE
                // en '=', nunca hace substring).
                'secuencial' => 'v.secuencial::numeric',
            ],
        ]);

        if ($idUsuario !== null) {
            $where .= " AND v.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        // LATERAL de abonos: calcula UNA sola vez por factura lo que antes se repetía
        // como texto en el WHERE (texto libre, filtro pago:/saldo:) y en el ORDER BY
        // (mapaOrden(), estado_pago) — cada aparición anterior era una reevaluación
        // completa de las 3 subconsultas correlacionadas de AbonosVentaSql. De paso
        // reemplaza las 4 subconsultas sueltas que ya traía el SELECT para las
        // columnas Total cobrado/NC/ND/Retención: antes de este cambio esas 4 también
        // se evaluaban aparte de la del texto libre/orden, aunque fueran el mismo dato.
        $lateralAbonos = "LEFT JOIN LATERAL (
                SELECT x.total_cobrado, x.total_nc, x.total_nd, x.total_retencion,
                       (x.total_cobrado + x.total_nc + x.total_retencion) AS abonos
                FROM (
                    SELECT
                        (SELECT COALESCE(SUM(ind.monto_cobrado), 0) FROM ingresos_detalle ind INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' AND inc.estado != 'anulado' AND inc.eliminado = false) AS total_cobrado,
                        " . AbonosVentaSql::subNotasFactura('notas_credito_cabecera', 'v') . " AS total_nc,
                        " . AbonosVentaSql::subNotasFactura('nota_debito_cabecera', 'v') . " AS total_nd,
                        " . AbonosVentaSql::subRetenidoFactura('v') . " AS total_retencion
                ) x
            ) ab ON true";

        // El COUNT solo necesita el LATERAL cuando el WHERE realmente lo referencia
        // (texto libre, filtro pago:/saldo:); si no, se queda tan barato como antes.
        $joinAbonosFiltro = (strpos($where, 'ab.') !== false) ? $lateralAbonos : '';

        // Una o varias columnas (Shift+clic en el listado), siempre validadas contra
        // el mapa: lo único que puede llegar al ORDER BY sale de ahí.
        $ordenMulti = \App\Helpers\OrdenListado::normalizar(
            $ordenMulti !== [] ? $ordenMulti : [['col' => $ordenCol, 'dir' => $ordenDir]]
        );
        // El desempate por v.id sigue la dirección del criterio principal (no es fijo
        // DESC como en otros módulos) y se omite solo si ya se ordena por id: eso lo
        // resuelve clausula(), que no duplica una expresión que ya está en el ORDER BY.
        $dirPrincipal = \App\Helpers\OrdenListado::primeraDir($ordenMulti, 'DESC');
        $orderBy = \App\Helpers\OrdenListado::clausula(
            $ordenMulti,
            $this->mapaOrden(),
            'v.fecha_emision',
            'v.id ' . $dirPrincipal
        );

        // Rendimiento (2026-09-16): UNA sola consulta en dos fases (mismo patrón que
        // ComprasRepository::getListado).
        //  1) CTE `pagina`: aplica el WHERE una vez, ordena, corta la página y saca el
        //     total con COUNT(*) OVER (). El LATERAL de abonos solo entra aquí si el
        //     WHERE o el ORDER BY lo necesitan (filtro pago:/saldo:, búsqueda de un
        //     monto con decimales, orden por estado de pago).
        //  2) La consulta final calcula el LATERAL de abonos SOLO para las filas de la
        //     página. Antes se calculaba para todas las facturas de la empresa en cada
        //     carga del listado (el LIMIT se aplicaba después), y el WHERE se evaluaba
        //     dos veces (COUNT + SELECT).
        $joins = "INNER JOIN clientes  c   ON v.id_cliente  = c.id
                LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id";
        $joinAbonosPagina = ($joinAbonosFiltro !== '' || strpos($orderBy, 'ab.') !== false) ? $lateralAbonos : '';

        // Columnas explícitas en lugar de "v.*" A PROPÓSITO (mismo criterio que
        // ComprasRepository::getListado): ventas_cabecera tiene `detalle_xml` con el XML del
        // SRI, y el listado lo arrastraba en cada fila para volcarlo entero en el atributo
        // data-row de cada <tr>. Medido en local con facturas firmadas de 17 KB: 558 KB por
        // página de 20 contra 76 KB sin el XML. Nadie lo usa desde el listado: el modal pide
        // la factura con getFacturaAjax() y la descarga del XML tiene su propia consulta.
        // Al agregar una columna nueva a la tabla, añadirla aquí si el listado la necesita —
        // pero NUNCA volver a traer detalle_xml.
        $columnas = "v.id, v.id_empresa, v.id_establecimiento, v.id_punto_emision, v.id_cliente,
                     v.id_usuario, v.fecha_emision, v.establecimiento, v.punto_emision, v.secuencial,
                     v.clave_acceso, v.fecha_autorizacion, v.guia_remision, v.total_sin_impuestos,
                     v.total_descuento, v.importe_total, v.propina, v.moneda, v.estado,
                     v.id_asiento_contable, v.observaciones, v.created_at, v.updated_at, v.created_by,
                     v.updated_by, v.eliminado, v.deleted_at, v.deleted_by, v.total_ice, v.id_vendedor,
                     v.dias_credito, v.plazo, v.tipo_ambiente, v.tipo_emision, v.estado_correo,
                     v.id_proforma, v.id_caja_sesion, v.id_cotizacion_publicidad, v.placa";

        // Conteo + página en UNA consulta (App\Helpers\ListadoPaginado). Sin texto ni filtros
        // usa la forma liviana (los ids de la página salen por índice y el total va aparte),
        // que es más rápida que COUNT(*) OVER () cuando no hay nada que filtrar.
        return \App\Helpers\ListadoPaginado::consultar(
            fn(string $sql, array $prm): array => $this->query($sql, $prm)->fetchAll(\PDO::FETCH_ASSOC),
            [
                'tabla'       => 'ventas_cabecera',
                'alias'       => 'v',
                'joinsFiltro' => $joins . ' ' . $joinAbonosPagina,
                'joinsFinal'  => $joins . ' ' . $lateralAbonos,
                'where'       => $where,
                'orderBy'     => $orderBy,
                'perPage'     => $perPage,
                'offset'      => $offset,
                'conBusqueda' => trim($buscar) !== '',
                'select'      => "$columnas,
                       c.nombre        AS cliente_nombre,
                       c.identificacion AS cliente_ruc,
                       ven.nombre      AS vendedor_nombre,
                       u.nombre        AS usuario_nombre,
                       ab.total_cobrado,
                       ab.total_nc,
                       ab.total_nd,
                       ab.total_retencion",
            ],
            $params
        );
    }

    /**
     * Series (establecimiento-puntoEmision) que REALMENTE tienen al menos una
     * factura guardada, para poblar el filtro "Serie" del buscador. A propósito
     * NO usa los puntos de emisión configurados actualmente (esos solo sirven
     * para elegir la serie de una factura NUEVA): si la empresa tiene más de un
     * establecimiento, o un punto se reconfiguró/desactivó después de haber
     * facturado con él, esa serie histórica seguía sin aparecer como opción de
     * filtro aunque hubiera facturas reales con ella.
     */
    public function getSeriesDistintas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT establecimiento, punto_emision
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false AND establecimiento IS NOT NULL AND establecimiento != ''
                ORDER BY establecimiento, punto_emision";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll();
    }

    /** Vendedores con alguna factura en la empresa (select "Vendedor" del modal de filtros). */
    public function getVendedoresConVentas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT ven.id, ven.nombre
                FROM ventas_cabecera v
                JOIN vendedores ven ON ven.id = v.id_vendedor
                WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                ORDER BY ven.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Usuarios que han registrado alguna factura en la empresa (select "Usuario" del modal de filtros). */
    public function getUsuariosConVentas(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT u.id, u.nombre
                FROM ventas_cabecera v
                JOIN usuarios u ON u.id = v.id_usuario
                WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                ORDER BY u.nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda libre DENTRO de las facturas (pestaña "Detalles" del modal de filtros):
     * devuelve cada producto vendido, forma de pago o campo de información adicional
     * que coincide con el texto, junto con la factura a la que pertenece. Mismo
     * alcance que el listado (empresa, no eliminadas, ambiente, registros propios).
     */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':id_empresa' => $idEmpresa];
        $whereBase = "v.id_empresa = :id_empresa AND v.eliminado = false
                      AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        if ($idUsuario !== null) {
            $whereBase .= " AND v.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }

        $condDet = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['d.codigo_principal', 'd.codigo_auxiliar', 'd.descripcion', 'd.cantidad::text', 'd.precio_unitario::text',
             'd.precio_total_sin_impuesto::text', 'd.numero_lote', 'd.nup', 'd.info_adicional'],
            $q, $params, 'dt'
        );
        $condPago = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['fp.nombre', 'p.forma_pago', 'p.total::text', 'p.plazo::text', 'p.unidad_tiempo'],
            $q, $params, 'pg'
        );
        $condAdic = \App\Helpers\FiltrosBusqueda::condicionTexto(
            ['a.nombre', 'a.valor'],
            $q, $params, 'ad'
        );
        if ($condDet === '' || $condPago === '' || $condAdic === '') {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = "WITH base AS (
                    SELECT v.id, CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero,
                           v.fecha_emision, v.estado, c.nombre AS cliente
                    FROM ventas_cabecera v
                    INNER JOIN clientes c ON c.id = v.id_cliente
                    WHERE $whereBase
                )
                SELECT * FROM (
                    SELECT 'PRODUCTO' AS origen,
                           COALESCE(NULLIF(d.codigo_principal,''), d.codigo_auxiliar) AS tipo,
                           d.descripcion,
                           d.cantidad,
                           d.precio_total_sin_impuesto AS monto,
                           NULLIF(CONCAT_WS(' ', NULLIF(d.numero_lote,''), NULLIF(d.nup,'')), '') AS extra,
                           b.id AS id_venta, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM ventas_detalle d
                    JOIN base b ON b.id = d.id_venta
                    WHERE $condDet
                    UNION ALL
                    SELECT 'PAGO' AS origen,
                           COALESCE(fp.nombre, p.forma_pago) AS tipo,
                           NULLIF(CONCAT_WS(' ', p.plazo::text, p.unidad_tiempo), '') AS descripcion,
                           NULL AS cantidad,
                           p.total AS monto,
                           p.forma_pago AS extra,
                           b.id AS id_venta, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM ventas_pagos p
                    JOIN base b ON b.id = p.id_venta
                    LEFT JOIN formas_pago_sri fp ON fp.codigo = p.forma_pago
                    WHERE $condPago
                    UNION ALL
                    SELECT 'ADICIONAL' AS origen,
                           a.nombre AS tipo,
                           a.valor AS descripcion,
                           NULL AS cantidad,
                           NULL AS monto,
                           NULL AS extra,
                           b.id AS id_venta, b.numero, b.fecha_emision, b.estado, b.cliente
                    FROM ventas_adicional a
                    JOIN base b ON b.id = a.id_venta
                    WHERE $condAdic
                ) x
                ORDER BY x.fecha_emision DESC, x.id_venta DESC, x.origen
                LIMIT $limit";

        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Facturas del rango de fechas para exportación masiva (Descargas Masivas).
     * Sin paginar; el llamador (DescargaMasivaService) valida el límite de cantidad.
     */
    public function getParaDescargaMasiva(int $idEmpresa, ?string $fechaDesde, ?string $fechaHasta, ?int $numeroDesde, ?int $numeroHasta, ?int $idUsuarioFiltro): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                   AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                   " . $this->condicionRangoDescargaMasiva('v.', $fechaDesde, $fechaHasta, $numeroDesde, $numeroHasta, $params);
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND v.id_usuario = :id_usuario_filtro';
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }
        $sql = "SELECT v.id, v.establecimiento, v.punto_emision, v.secuencial, v.fecha_emision, v.estado
                FROM ventas_cabecera v
                $where
                ORDER BY v.fecha_emision ASC, v.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Facturas autorizadas/aprobadas de un cliente, para seleccionarlas como
     * documento a modificar en una Nota de Crédito. Filtro opcional de texto
     * sobre el número de comprobante (establecimiento-punto-secuencial).
     *
     * Devuelve también el `saldo` pendiente (total − cobros − notas de crédito −
     * retenciones), con la misma fórmula del estado de pago del listado de Facturas
     * de Venta. Con `$soloConSaldo` se excluyen las facturas con saldo <= 0.
     */
    public function getFacturasPorCliente(int $idEmpresa, int $idCliente, string $buscar = '', int $limit = 30, bool $soloConSaldo = false): array
    {
        $params = [':id_empresa' => $idEmpresa, ':id_cliente' => $idCliente];
        $where  = "WHERE v.id_empresa = :id_empresa
                     AND v.eliminado = FALSE
                     AND v.id_cliente = :id_cliente
                     AND v.estado IN ('autorizado','aprobado')
                     AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $buscar = trim($buscar);
        if ($buscar !== '') {
            $where .= " AND CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) ILIKE :buscar";
            $params[':buscar'] = "%$buscar%";
        }

        // Saldo = total − abonos (cobros + NC + retenciones), igual que mapaOrden()/estado_pago.
        $saldo = "(v.importe_total - ab.abonos)";
        if ($soloConSaldo) {
            $where .= " AND $saldo > 0";
        }

        $sql = "SELECT v.id, v.establecimiento, v.punto_emision, v.secuencial,
                       v.fecha_emision, v.importe_total, v.estado,
                       v.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_ruc,
                       $saldo AS saldo
                FROM ventas_cabecera v
                INNER JOIN clientes c ON v.id_cliente = c.id
                LEFT JOIN LATERAL (
                    SELECT (
                        (SELECT COALESCE(SUM(ind.monto_cobrado), 0) FROM ingresos_detalle ind INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' AND inc.estado != 'anulado' AND inc.eliminado = false)
                        + " . AbonosVentaSql::subNotasFactura('notas_credito_cabecera', 'v') . "
                        + " . AbonosVentaSql::subRetenidoFactura('v') . "
                    ) AS abonos
                ) ab ON true
                $where
                ORDER BY v.fecha_emision DESC, v.id DESC
                LIMIT $limit";
        return $this->query($sql, $params)->fetchAll();
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT v.*,
                       c.nombre              AS cliente_nombre,
                       c.identificacion      AS cliente_ruc,
                       c.direccion           AS cliente_direccion,
                       c.email               AS cliente_email,
                       c.telefono            AS cliente_telefono,
                       c.tipo_id             AS cliente_tipo_id,
                       c.plazo               AS cliente_plazo,
                       COALESCE(icv.nombre,'') AS cliente_nombre_tipo_id,
                       ven.nombre            AS vendedor_nombre,
                       u.nombre              AS usuario_nombre,
                       uc.nombre             AS creado_por_nombre,
                       uu.nombre             AS actualizado_por_nombre,
                       (SELECT COALESCE(SUM(ind.monto_cobrado), 0) FROM ingresos_detalle ind INNER JOIN ingresos_cabecera inc ON ind.id_ingreso = inc.id WHERE ind.id_referencia_documento = v.id AND ind.tipo_documento = 'FACTURA' AND inc.estado != 'anulado' AND inc.eliminado = false) AS total_cobrado,
                       " . AbonosVentaSql::subNotasFactura('notas_credito_cabecera', 'v') . " AS total_nc,
                       " . AbonosVentaSql::subNotasFactura('nota_debito_cabecera', 'v') . " AS total_nd,
                       " . AbonosVentaSql::subRetenidoFactura('v') . " AS total_retencion
                FROM ventas_cabecera v
                INNER JOIN clientes   c   ON v.id_cliente  = c.id
                LEFT  JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                LEFT  JOIN vendedores ven ON v.id_vendedor = ven.id
                LEFT  JOIN usuarios   u   ON v.id_usuario  = u.id
                LEFT  JOIN usuarios   uc  ON v.created_by  = uc.id
                LEFT  JOIN usuarios   uu  ON v.updated_by  = uu.id
                WHERE v.id = ? AND v.eliminado = FALSE";
        $row = $this->query($sql, [$id])->fetch();
        return $row ?: null;
    }

    /**
     * Devuelve las facturas (no eliminadas) generadas desde una proforma.
     */
    public function getPorProforma(int $idProforma, int $idEmpresa): array
    {
        $sql = "SELECT id, fecha_emision, establecimiento, punto_emision, secuencial,
                       importe_total, estado, estado_correo
                FROM ventas_cabecera
                WHERE id_proforma = ? AND id_empresa = ? AND eliminado = false
                ORDER BY fecha_emision DESC, id DESC";
        return $this->query($sql, [$idProforma, $idEmpresa])->fetchAll();
    }

    public function getPorCotizacionPublicidad(int $idCotizacion, int $idEmpresa): array
    {
        $sql = "SELECT id, fecha_emision, establecimiento, punto_emision, secuencial,
                       importe_total, estado, estado_correo
                FROM ventas_cabecera
                WHERE id_cotizacion_publicidad = ? AND id_empresa = ? AND eliminado = false
                ORDER BY fecha_emision DESC, id DESC";
        return $this->query($sql, [$idCotizacion, $idEmpresa])->fetchAll();
    }

    /**
     * Persiste el XML (sin firma o firmado/autorizado) en detalle_xml.
     */
    public function updateDetalleXml(int $id, string $xml): void
    {
        $st = $this->db->prepare(
            "UPDATE ventas_cabecera SET detalle_xml = ?, updated_at = NOW() WHERE id = ?"
        );
        $st->execute([$xml, $id]);
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$estado, $idUsuario, $id]);
    }

    public function actualizarObservaciones(int $id, string $observaciones, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera SET observaciones = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$observaciones, $idUsuario, $id]);
    }

    public function actualizarVendedor(int $id, ?int $idVendedor, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera SET id_vendedor = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->prepare($sql)->execute([$idVendedor, $idUsuario, $id]);
    }

    /**
     * Sincroniza SOLO la fila "Vendedor" de ventas_adicional con el vendedor
     * actual — la usa actualizarVendedor() (factura ya autorizada), que solo
     * toca id_vendedor en la cabecera y no pasa por el reemplazo completo de
     * info_adicional que sí hace actualizar()/crear(). Sin esto, el modal
     * mostraba la fila "Vendedor" en pantalla (la agrega el JS al cambiar el
     * combo) pero nunca quedaba guardada, así que el PDF/XML no la traían.
     * Actualiza in situ en vez de borrar+reinsertar todo info_adicional, para
     * no tocar otras filas (RUC Proveedor, correo del cliente, etc.).
     */
    public function syncVendedorInfoAdicional(int $idVenta, ?string $nombreVendedor): void
    {
        $this->query("DELETE FROM ventas_adicional WHERE id_venta = ? AND nombre = 'Vendedor'", [$idVenta]);
        if (trim((string) $nombreVendedor) !== '') {
            $this->query(
                "INSERT INTO ventas_adicional (id_venta, nombre, valor) VALUES (?, 'Vendedor', ?)",
                [$idVenta, $this->caparTexto('valor', $nombreVendedor, 'ventas_adicional')]
            );
        }
    }



    public function eliminarLogico(int $id, int $idUsuario): void
    {
        $sql = "UPDATE ventas_cabecera 
                SET eliminado = true, 
                    deleted_at = CURRENT_TIMESTAMP, 
                    deleted_by = ?,
                    updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $this->db->prepare($sql)->execute([$idUsuario, $idUsuario, $id]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ventas_cabecera
                SET eliminado = true, deleted_at = NOW(), deleted_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE ventas_cabecera
                SET estado = 'anulado', updated_at = NOW(), updated_by = ?
                WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([$idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function getDetalles(int $idVenta): array
    {
        // El JOIN de unidades_medida NO filtra por eliminado/status: un documento
        // histórico debe seguir mostrando su unidad aunque el catálogo cambie.
        $sql = "SELECT d.*, COALESCE(p.nombre, d.descripcion) as producto_nombre, p.codigo as producto_codigo,
                       p.id_tipo_medida, p.id_medida as id_medida_base,
                       p.tipo_produccion, p.inventariable, p.ubicacion as producto_ubicacion,
                       um.abreviatura as unidad_abreviatura, um.nombre as unidad_nombre
                FROM ventas_detalle d
                LEFT JOIN productos p ON d.id_producto = p.id
                LEFT JOIN unidades_medida um ON um.id = COALESCE(d.id_unidad_medida, p.id_medida)
                WHERE d.id_venta = ?
                ORDER BY d.id ASC";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function getImpuestosDetalle(int $idDetalle): array
    {
        $sql = "SELECT * FROM ventas_detalle_impuestos WHERE id_venta_detalle = ?";
        return $this->query($sql, [$idDetalle])->fetchAll();
    }

    /**
     * Impuestos de VARIAS líneas en UNA sola consulta, agrupados por línea.
     *
     * Evita el N+1 de llamar a getImpuestosDetalle() dentro del bucle de
     * detalles: con la base en un servidor remoto, un documento de 30 líneas
     * pagaba 30 viajes de red solo para esto.
     *
     * @param int[] $idsDetalle
     * @return array<int,array> id de la línea => sus impuestos
     */
    public function getImpuestosPorDetalles(array $idsDetalle): array
    {
        $ids = array_values(array_unique(array_filter(array_map("intval", $idsDetalle))));
        if (!$ids) {
            return [];
        }

        $ph  = implode(",", array_fill(0, count($ids), "?"));
        $sql = "SELECT * FROM ventas_detalle_impuestos WHERE id_venta_detalle IN ($ph)";

        $porDetalle = [];
        foreach ($this->query($sql, $ids)->fetchAll() as $imp) {
            $porDetalle[(int) $imp["id_venta_detalle"]][] = $imp;
        }
        return $porDetalle;
    }

    public function getPagos(int $idVenta): array
    {
        $sql = "SELECT vp.*, COALESCE(fps.nombre, vp.forma_pago) AS nombre_forma_pago
                FROM ventas_pagos vp
                LEFT JOIN formas_pago_sri fps ON fps.codigo = vp.forma_pago
                WHERE vp.id_venta = ?
                ORDER BY vp.id ASC";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function getInfoAdicional(int $idVenta): array
    {
        $sql = "SELECT * FROM ventas_adicional WHERE id_venta = ?";
        return $this->query($sql, [$idVenta])->fetchAll();
    }

    public function insertCabecera(array $data): int
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;

        // Columnas y valores base (siempre presentes en el schema original)
        $cols   = [
            'id_empresa', 'id_establecimiento', 'id_punto_emision', 'id_cliente', 'id_usuario',
            'fecha_emision', 'establecimiento', 'punto_emision', 'secuencial',
            'total_sin_impuestos', 'total_descuento', 'importe_total', 'propina', 'moneda', 'estado',
            'id_vendedor', 'dias_credito', 'observaciones',
            'created_by', 'updated_by',
        ];
        $params = [
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (int) $data['id_cliente'],
            (int) $data['id_usuario'],
            $data['fecha_emision'],
            $data['establecimiento'],
            $data['punto_emision'],
            $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
            $data['moneda'] ?? 'DOLAR',
            $data['estado'] ?? 'borrador',
            $idVendedor,
            (int) ($data['dias_credito'] ?? 0),
            !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int) $data['id_usuario'],
            (int) $data['id_usuario'],
        ];

        // Columnas opcionales: se agregan solo si existen en la tabla
        $colsOpcionales = $this->columnasExistentes('ventas_cabecera');

        if (in_array('total_ice', $colsOpcionales)) {
            $cols[]   = 'total_ice';
            $params[] = (float) ($data['total_ice'] ?? 0);
        }
        if (in_array('plazo', $colsOpcionales)) {
            $cols[]   = 'plazo';
            $params[] = !empty($data['plazo']) ? $data['plazo'] : null;
        }
        if (in_array('tipo_ambiente', $colsOpcionales)) {
            $cols[]   = 'tipo_ambiente';
            $params[] = $data['tipo_ambiente'] ?? '1';
        }
        if (in_array('tipo_emision', $colsOpcionales)) {
            $cols[]   = 'tipo_emision';
            $params[] = $data['tipo_emision'] ?? '1';
        }
        if (in_array('estado_correo', $colsOpcionales)) {
            $cols[]   = 'estado_correo';
            $params[] = $data['estado_correo'] ?? 'pendiente';
        }
        if (in_array('clave_acceso', $colsOpcionales) && !empty($data['clave_acceso'])) {
            $cols[]   = 'clave_acceso';
            $params[] = $data['clave_acceso'];
        }
        if (in_array('id_proforma', $colsOpcionales) && !empty($data['id_proforma'])) {
            $cols[]   = 'id_proforma';
            $params[] = (int) $data['id_proforma'];
        }
        // Placa del vehículo (operadoras de transporte — Ficha SRI v2.34, Anexo 25/Tabla 33)
        if (in_array('placa', $colsOpcionales) && !empty($data['placa'])) {
            $cols[]   = 'placa';
            $params[] = \App\Helpers\PlacaTransporteHelper::normalizar((string) $data['placa']);
        }
        if (in_array('id_cotizacion_publicidad', $colsOpcionales) && !empty($data['id_cotizacion_publicidad'])) {
            $cols[]   = 'id_cotizacion_publicidad';
            $params[] = (int) $data['id_cotizacion_publicidad'];
        }
        if (in_array('id_caja_sesion', $colsOpcionales) && !empty($data['id_caja_sesion'])) {
            $cols[]   = 'id_caja_sesion';
            $params[] = (int) $data['id_caja_sesion'];
        }

        $colSql  = implode(', ', $cols);
        $valSql  = implode(', ', array_fill(0, count($params), '?'));
        $sql     = "INSERT INTO ventas_cabecera ({$colSql}) VALUES ({$valSql}) RETURNING id";

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    /** Devuelve (y cachea) las columnas existentes de una tabla. */
    private array $colsCache = [];
    private function columnasExistentes(string $tabla): array
    {
        if (!isset($this->colsCache[$tabla])) {
            $st = $this->db->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'"
            );
            $st->execute([$tabla]);
            $this->colsCache[$tabla] = $st->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $this->colsCache[$tabla];
    }

    /**
     * ¿Ya hay una factura con ese secuencial en el punto de emisión?
     *
     * Acotado al AMBIENTE de la empresa (Pruebas / Producción). Pruebas y
     * Producción son numeraciones independientes en el SRI —la clave de acceso
     * lleva el ambiente dentro—, así que al pasar a Producción la serie
     * arranca de nuevo y repite números ya usados en Pruebas. Sin este filtro
     * el sistema los leía como duplicados y bloqueaba la emisión con "El número
     * de secuencial ya existe para este punto de emisión", aunque el número que
     * acababa de calcular SecuencialRepository::getSiguienteDisponible() sí
     * estuviera libre — esa consulta sí filtra por tipo_ambiente. Mismo criterio
     * que IngresoRepository y EgresoRepository.
     *
     * COALESCE porque la columna admite NULL (facturas viejas anteriores a la
     * migración del SRI): esas cuentan como Pruebas, igual que en el índice
     * único uix_ventas_secuencial_activo.
     */
    public function existeSecuencial(int $idEmpresa, int $idEstablecimiento, int $idPunto, string $secuencial, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM ventas_cabecera
                WHERE id_empresa = ? AND id_establecimiento = ? AND id_punto_emision = ?
                  AND secuencial = ? AND eliminado = FALSE
                  AND COALESCE(tipo_ambiente, '1') = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)";
        $params = [$idEmpresa, $idEstablecimiento, $idPunto, $secuencial, $idEmpresa];

        if ($excluirId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $excluirId;
        }

        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function insertDetalle(array $data): int
    {
        $cols   = [
            'id_venta', 'id_producto', 'id_bodega', 'id_unidad_medida',
            'codigo_principal', 'codigo_auxiliar',
            'descripcion', 'cantidad', 'precio_unitario', 'descuento', 'precio_total_sin_impuesto',
        ];
        // Numéricos: un valor vacío ('') o nulo se convierte a 0 para no romper el INSERT
        // con "invalid input syntax for type numeric" (columnas NUMERIC no aceptan '').
        $num = static fn($v) => ($v === '' || $v === null) ? 0 : $v;

        $params = [
            (int) $data['id_venta'],
            !empty($data['id_producto']) ? (int)$data['id_producto'] : null,
            !empty($data['id_bodega'])         ? (int) $data['id_bodega']         : null,
            !empty($data['id_unidad_medida'])   ? (int) $data['id_unidad_medida']  : (!empty($data['id_medida']) ? (int)$data['id_medida'] : null),
            $data['codigo_principal'] ?? null,
            !empty($data['codigo_auxiliar'])    ? $data['codigo_auxiliar']         : null,
            $data['descripcion'],
            $num($data['cantidad'] ?? 0),
            $num($data['precio_unitario'] ?? 0),
            $num($data['descuento'] ?? 0),
            $num($data['precio_total_sin_impuesto'] ?? 0),
        ];

        $colsOpcionales = $this->columnasExistentes('ventas_detalle');

        if (in_array('id_tarifa_iva', $colsOpcionales)) {
            $cols[] = 'id_tarifa_iva';
            $params[] = (int) ($data['id_tarifa_iva'] ?? 0);
        }

        if (in_array('casillero', $colsOpcionales)) {
            $cols[] = 'casillero';
            $params[] = !empty($data['casillero']) ? (string)$data['casillero'] : null;
        }

        if (in_array('info_adicional', $colsOpcionales)) {
            $cols[]   = 'info_adicional';
            $params[] = $data['info_adicional'] ?? null;
        }

        if (in_array('id_producto_variante', $colsOpcionales) && !empty($data['id_producto_variante'])) {
            $cols[]   = 'id_producto_variante';
            $params[] = (int) $data['id_producto_variante'];
        }

        // Línea de origen cuando la factura se generó desde un Pedido (botón
        // "Facturar" en Pedidos). Ver database/agregar_id_pedido_detalle_ventas.sql.
        if (in_array('id_pedido_detalle', $colsOpcionales) && !empty($data['id_pedido_detalle'])) {
            $cols[]   = 'id_pedido_detalle';
            $params[] = (int) $data['id_pedido_detalle'];
        }

        $colSql = implode(', ', $cols);
        $valSql = implode(', ', array_fill(0, count($params), '?'));
        $sql    = "INSERT INTO ventas_detalle ({$colSql}) VALUES ({$valSql}) RETURNING id";

        return (int) $this->query($sql, $params)->fetchColumn();
    }

    public function insertImpuesto(array $data): void
    {
        $sql = "INSERT INTO ventas_detalle_impuestos (
                    id_venta_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor
                ) VALUES (?, ?, ?, ?, ?, ?)";
        $this->query($sql, [
            $data['id_venta_detalle'], $data['codigo_impuesto'], $data['codigo_porcentaje'], $data['tarifa'], $data['base_imponible'], $data['valor']
        ]);
    }

    /**
     * Obtiene todos los impuestos de los detalles de una factura.
     */
    public function getImpuestosPorVenta(int $idVenta): array
    {
        $sql = "SELECT i.*, d.id_venta 
                FROM ventas_detalle_impuestos i
                JOIN ventas_detalle d ON i.id_venta_detalle = d.id
                WHERE d.id_venta = ?";
        return $this->query($sql, [$idVenta])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertPago(array $data): void
    {
        $sql = "INSERT INTO ventas_pagos (id_venta, forma_pago, total, plazo, unidad_tiempo) VALUES (?, ?, ?, ?, ?)";
        $this->query($sql, [$data['id_venta'], $data['forma_pago'], $data['total'], $data['plazo'] ?? 0, $data['unidad_tiempo'] ?? 'dias']);
    }

    /**
     * Información adicional del comprobante. `nombre` y `valor` son VARCHAR(300)
     * y reciben texto LIBRE de varios orígenes sin tope propio: lo que el usuario
     * teclea en el modal, las Observaciones del documento —columna `text`— que
     * Facturación de Consignaciones copia aquí (ConsignacionFacturaService::
     * conCamposDeCabecera), el POS y las cargas masivas.
     *
     * PostgreSQL no trunca solo: un valor más largo aborta el INSERT con
     * SQLSTATE[22001] y, como esto corre dentro de la transacción que crea la
     * factura, se cae la emisión completa —en Facturación CV se revierte además el
     * reingreso de inventario— con un mensaje que no dice qué campo sobró. Se capa
     * aquí porque es el único punto por el que pasan todos los orígenes.
     */
    public function insertInfoAdicional(array $data): void
    {
        $sql = "INSERT INTO ventas_adicional (id_venta, nombre, valor) VALUES (?, ?, ?)";
        $this->query($sql, [
            $data['id_venta'],
            $this->caparTexto('nombre', $data['nombre'] ?? '', 'ventas_adicional'),
            $this->caparTexto('valor',  $data['valor']  ?? '', 'ventas_adicional'),
        ]);
    }

    public function updateCabecera(int $id, array $data): void
    {
        $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;

        $sets   = [
            'id_establecimiento  = ?',
            'id_punto_emision    = ?',
            'id_cliente          = ?',
            'fecha_emision       = ?',
            'establecimiento     = ?',
            'punto_emision       = ?',
            'secuencial          = ?',
            'total_sin_impuestos = ?',
            'total_descuento     = ?',
            'importe_total       = ?',
            'propina             = ?',
            'id_vendedor         = ?',
            'dias_credito        = ?',
            'observaciones       = ?',
            'updated_by          = ?',
            'updated_at          = NOW()',
        ];
        $params = [
            (int)   $data['id_establecimiento'],
            (int)   $data['id_punto_emision'],
            (int)   $data['id_cliente'],
                    $data['fecha_emision'],
                    $data['establecimiento'],
                    $data['punto_emision'],
                    $data['secuencial'],
            (float) $data['total_sin_impuestos'],
            (float) $data['total_descuento'],
            (float) $data['importe_total'],
            (float) ($data['propina'] ?? 0),
                    $idVendedor,
            (int)   ($data['dias_credito'] ?? 0),
                    !empty($data['observaciones']) ? $data['observaciones'] : null,
            (int)   $data['id_usuario'],
        ];

        $colsOpcionales = $this->columnasExistentes('ventas_cabecera');
        if (in_array('total_ice', $colsOpcionales)) {
            $sets[]   = 'total_ice = ?';
            $params[] = (float) ($data['total_ice'] ?? 0);
        }
        if (in_array('plazo', $colsOpcionales)) {
            $sets[]   = 'plazo = ?';
            $params[] = !empty($data['plazo']) ? $data['plazo'] : null;
        }
        if (in_array('clave_acceso', $colsOpcionales) && !empty($data['clave_acceso'])) {
            $sets[]   = 'clave_acceso = ?';
            $params[] = $data['clave_acceso'];
        }
        // Placa del vehículo (operadoras de transporte — Ficha SRI v2.34, Anexo 25/Tabla 33)
        if (in_array('placa', $colsOpcionales)) {
            $sets[]   = 'placa = ?';
            $params[] = !empty($data['placa'])
                ? \App\Helpers\PlacaTransporteHelper::normalizar((string) $data['placa'])
                : null;
        }

        $params[] = $id;
        $params[] = (int) $data['id_empresa'];

        $sql = "UPDATE ventas_cabecera SET " . implode(', ', $sets) . " WHERE id = ? AND id_empresa = ? AND eliminado = false";
        $this->query($sql, $params);
    }

    public function deleteDetalles(int $idVenta): void
    {
        // Eliminar impuestos primero (FK)
        $ids = $this->query("SELECT id FROM ventas_detalle WHERE id_venta = ?", [$idVenta])->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $this->query("DELETE FROM ventas_detalle_impuestos WHERE id_venta_detalle IN ($placeholders)", $ids);
        }
        $this->query("DELETE FROM ventas_detalle WHERE id_venta = ?", [$idVenta]);
    }

    public function deletePagos(int $idVenta): void
    {
        $this->query("DELETE FROM ventas_pagos WHERE id_venta = ?", [$idVenta]);
    }

    public function deleteInfoAdicional(int $idVenta): void
    {
        $this->query("DELETE FROM ventas_adicional WHERE id_venta = ?", [$idVenta]);
    }











    /**
     * Crea un producto tipo "servicio" con código secuencial al vuelo (facturación libre).
     * Retorna el ID del producto creado.
     */
    /**
     * @return array{id: int, codigo: string} id del producto creado y su código
     *         real generado (p. ej. "S001") — el llamador debe usar este código
     *         para reemplazar el "__LIBRE__" que llega del frontend en
     *         codigo_principal del detalle, o el PDF/listados quedan mostrando
     *         ese literal en vez del código del catálogo.
     */
    public function crearServicioLibre(int $idEmpresa, int $idUsuario, string $nombre, float $precio, ?float $porcentajeIva = null, ?string $codigoPorcentaje = null, ?string $codigoDeseado = null): array
    {
        $productoRepo = new ProductoRepository();

        // Si el usuario escribió su propio código en la columna "Código" del detalle
        // (y no coincidió con ningún producto existente al buscar, o ya se habría
        // autoseleccionado ese producto en vez de llegar aquí), se respeta ese código
        // en vez de generar uno automático — validando que siga libre justo antes de
        // insertar, por si cambió entre que se buscó y que se guardó la factura.
        $codigoDeseado = trim((string) $codigoDeseado);
        if ($codigoDeseado !== '' && strtoupper($codigoDeseado) !== '__LIBRE__') {
            if ($productoRepo->existeCodigo($idEmpresa, $codigoDeseado)) {
                throw new \InvalidArgumentException(
                    "El código \"{$codigoDeseado}\" ya existe en el catálogo de productos. Usa otro código o deja el campo vacío para que se genere uno automático."
                );
            }
            $codigo = $codigoDeseado;
        } else {
            $codigo = $productoRepo->getSiguienteCodigo($idEmpresa, '02'); // Genera S001, S002, etc.
        }

        // Resolver la tarifa de IVA por el codigoPorcentaje del SRI cuando venga (distingue
        // 0% / Exento / No objeto, que comparten porcentaje 0); si no, por el porcentaje.
        $idTarifaIva = null;
        if ($codigoPorcentaje !== null && $codigoPorcentaje !== '') {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE codigo = :c LIMIT 1");
            $stIva->execute([':c' => $codigoPorcentaje]);
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }
        if (!$idTarifaIva && $porcentajeIva !== null) {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE porcentaje_iva = :p AND status = 1 ORDER BY id LIMIT 1");
            $stIva->execute([':p' => $porcentajeIva]);
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }
        if (!$idTarifaIva) {
            $stIva = $this->db->prepare("SELECT id FROM tarifa_iva WHERE status = 1 ORDER BY id LIMIT 1");
            $stIva->execute();
            $idTarifaIva = $stIva->fetchColumn() ?: null;
        }

        $sql = "INSERT INTO productos (
                    id_empresa, id_usuario, created_by, updated_by, codigo, nombre,
                    codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva,
                    status, inventariable, eliminado, created_at
                ) VALUES (
                    :emp, :usr, :usr, :usr, :cod, :nom,
                    :cod, :cod, :precio, '02', :tarifa,
                    1, false, false, CURRENT_TIMESTAMP
                ) RETURNING id";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':emp'    => $idEmpresa,
            ':usr'    => $idUsuario,
            ':cod'    => $codigo,
            ':nom'    => $nombre,
            ':precio' => $precio,
            ':tarifa' => $idTarifaIva
        ]);

        return ['id' => (int) $st->fetchColumn(), 'codigo' => $codigo];
    }

    public function updateDetalleLoteNup(int $idDetalle, array $data): void
    {
        $sql = "UPDATE ventas_detalle
                SET numero_lote = :lote, fecha_caducidad = :cad, nup = :nup
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':lote' => $data['numero_lote']     ?? null,
            ':cad'  => $data['fecha_caducidad'] ?? null,
            ':nup'  => $data['nup']             ?? null,
            ':id'   => $idDetalle,
        ]);
    }

    /**
     * Igual que updateDetalleLoteNup() pero SIN tocar la columna nup — para
     * reconciliaciones (p. ej. la migración MySQL, que no conoce el NUP) que no
     * deben pisar un NUP ya cargado a mano con NULL.
     */
    public function updateDetalleLoteCaducidad(int $idDetalle, ?string $numeroLote, ?string $fechaCaducidad): void
    {
        $sql = "UPDATE ventas_detalle
                SET numero_lote = :lote, fecha_caducidad = :cad
                WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':lote' => $numeroLote,
            ':cad'  => $fechaCaducidad,
            ':id'   => $idDetalle,
        ]);
    }

    public function updateDetalleKardex(int $idDetalle, int $idKardex): void
    {
        $sql = "UPDATE ventas_detalle SET id_inventario_kardex = :k WHERE id = :id";
        $st  = $this->db->prepare($sql);
        $st->execute([':k' => $idKardex, ':id' => $idDetalle]);
    }

    /**
     * Retorna el tipo_id del cliente y si es consumidor final.
     * Detecta consumidor final por el nombre en identificador_comprador_vendedor (contiene 'consumidor')
     * o por la identificación '9999999999999'.
     */
    public function getTipoIdCliente(int $idCliente, int $idEmpresa): ?array
    {
        $sql = "SELECT c.tipo_id, c.identificacion,
                       COALESCE(icv.nombre, '') AS nombre_tipo_id
                FROM clientes c
                LEFT JOIN identificador_comprador_vendedor icv ON icv.codigo = c.tipo_id
                WHERE c.id = ? AND c.id_empresa = ? AND c.eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([$idCliente, $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        $esConsumidorFinal = stripos($row['nombre_tipo_id'], 'consumidor') !== false
                          || $row['identificacion'] === '9999999999999';

        return [
            'tipo_id'            => $row['tipo_id'],
            'identificacion'     => $row['identificacion'],
            'nombre_tipo_id'     => $row['nombre_tipo_id'],
            'es_consumidor_final' => $esConsumidorFinal,
        ];
    }

    /**
     * Retorna el valor límite para consumidor final del establecimiento.
     * Retorna null si no hay límite configurado.
     */
    public function getValorLimiteConsumidorFinal(int $idEstablecimiento): ?float
    {
        $sql = "SELECT valor_limite_consumidor_final FROM empresa_establecimiento
                WHERE id = ? AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([$idEstablecimiento]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null || $val === '') return null;
        $f = (float) $val;
        return $f > 0 ? $f : null;
    }

    public function getFormasPago(): array
    {
        return $this->db->query("SELECT * FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTarifasIva(): array
    {
        return $this->db->query("SELECT * FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUnidadesMedida(int $idEmpresa): array
    {
        $sql = "SELECT * FROM unidades_medida WHERE eliminado = false AND status = true AND id_empresa = :id_empresa ORDER BY nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getImpuestosConfig(): array
    {
        // Retornar una estructura básica de impuestos si es necesario, por ahora similar a tarifasIva
        return $this->getTarifasIva();
    }

    public function getPorNumeroCompleto(string $numero, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM ventas_cabecera 
                WHERE CONCAT(establecimiento,'-',punto_emision,'-',secuencial) = ? 
                  AND id_empresa = ? 
                  AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([$numero, $idEmpresa]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function updateAsientoContable(int $id, ?int $idAsiento): void
    {
        $sql = "UPDATE ventas_cabecera SET id_asiento_contable = :id_asiento WHERE id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id_asiento' => $idAsiento, ':id' => $id]);
    }
}

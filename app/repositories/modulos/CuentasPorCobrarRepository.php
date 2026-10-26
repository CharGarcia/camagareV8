<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\AbonosVentaSql;
use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use App\Traits\AmbienteEmpresaTrait;
use App\Traits\ExpansionTerceroTrait;
use PDO;

class CuentasPorCobrarRepository extends BaseRepository
{
    use AmbienteEmpresaTrait;
    use ExpansionTerceroTrait;
    /** Número de la factura `v` normalizado a 15 dígitos (`cand.num_norm`): clave de enlace con retenciones y notas. */
    private string $numV;

    public function __construct()
    {
        parent::__construct('ventas_cabecera');
        $this->numV = AbonosVentaSql::numFactura('v');
    }

    // ─────────────────────────────────────────────────────────────────────
    // ALCANCE POR EMPRESA (una empresa o varios establecimientos del mismo RUC)
    //
    // Todos los listados/agregados reciben `int|array $idsEmpresa`: la empresa
    // activa (int, comportamiento normal) o la lista de establecimientos del
    // grupo RUC cuando la matriz pide el consolidado (el controller la resuelve
    // con EmpresaRepository::getIdsConsolidadoDesdeMatriz, nunca el cliente).
    // ─────────────────────────────────────────────────────────────────────

    /** Normaliza el alcance a lista de ids enteros positivos, sin repetidos y nunca vacía. */
    private function idsEmpresa(int|array $idsEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $idsEmpresa), static fn ($i) => $i > 0)));
        if (!$ids) {
            throw new \InvalidArgumentException('Cuentas por Cobrar: id_empresa requerido.');
        }
        return $ids;
    }

    /** Lista para interpolar en `IN (...)` (ids ya validados como enteros por idsEmpresa()). */
    private function sqlIn(array $ids): string
    {
        return implode(',', $ids);
    }

    /** Expresión `ANY(ARRAY[...])` para los helpers de AbonosVentaSql, que comparan con `=`. */
    private function sqlAny(array $ids): string
    {
        return 'ANY(ARRAY[' . $this->sqlIn($ids) . '])';
    }

    /**
     * Placeholders `:prefijo0,:prefijo1,…` para un IN con PDO (pgsql no admite repetir un
     * placeholder, por eso cada IN lleva su prefijo). Registra los valores en $params.
     */
    private function phIn(array $ids, string $prefijo, array &$params): string
    {
        $ph = [];
        foreach (array_values($ids) as $i => $id) {
            $k = ":{$prefijo}{$i}";
            $ph[] = $k;
            $params[$k] = $id;
        }
        return implode(',', $ph);
    }

    /**
     * El documento pertenece al ambiente actual de SU PROPIA empresa: en el consolidado
     * cada establecimiento puede estar en un ambiente distinto y debe filtrarse contra
     * el suyo.
     *
     * Lo resuelve AmbienteEmpresaTrait con pares literales en vez de una subconsulta
     * correlacionada por fila, que impedía a PostgreSQL estimar la selectividad del
     * filtro y le hacía elegir bucles anidados. Ver el trait: en Cuentas por Pagar ese
     * mismo patrón costaba 10.922 ms frente a 102 ms con el ambiente como literal.
     */
    private function condAmbiente(string $alias, array $ids): string
    {
        // Regla de reportes: solo documentos de PRODUCCIÓN, en todos los establecimientos
        // del consolidado (ver App\Helpers\AmbienteReporte). Literal: el planificador
        // estima bien la selectividad, igual que con los pares de AmbienteEmpresaTrait.
        return \App\Helpers\AmbienteReporte::condicion($alias);
    }

    /**
     * Columnas que identifican el establecimiento dueño de la fila (badge en el consolidado).
     * `id_empresa` sale del documento ($aliasDoc), no de `empresas`: el JOIN a empresas es
     * LEFT para que un documento nunca desaparezca del listado por su empresa.
     */
    private function colsEstablecimiento(string $aliasDoc, string $aliasEmp = 'emp'): string
    {
        return "{$aliasDoc}.id_empresa AS id_empresa,
                COALESCE({$aliasEmp}.establecimiento, '') AS establecimiento,
                COALESCE(NULLIF({$aliasEmp}.nombre_comercial, ''), {$aliasEmp}.nombre, '') AS empresa_nombre";
    }

    /**
     * CTE que calcula lo cobrado por documento hasta una fecha de corte opcional.
     * Si $fechaHasta es null, incluye todos los cobros (comportamiento en tiempo real).
     * $tipoDoc: 'FACTURA' (facturas de venta) o 'RECIBO' (recibos de venta).
     */
    /**
     * $idEmpresa: filtra por empresa (multiempresa, §4) — sin esto el GROUP BY sumaba
     * los cobros de TODAS las empresas del sistema en cada consulta del reporte (mismo
     * problema encontrado y corregido en ReporteVentasRepository y afines). Se
     * interpola directo igual que getCteNC/getCteND en este mismo archivo (int
     * validado por el tipo del parámetro → interpolación segura).
     */
    private function getCteCobrado(array $idsEmpresa, ?string $fechaHasta = null, string $tipoDoc = 'FACTURA'): string
    {
        $filtroFecha = $fechaHasta ? "AND ic2.fecha_emision <= :cobrado_hasta" : '';
        $tipoDoc     = $tipoDoc === 'RECIBO' ? 'RECIBO' : 'FACTURA'; // literal seguro
        return "
            SELECT id2.id_referencia_documento AS id_venta,
                   SUM(id2.monto_cobrado)       AS total_cobrado
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic2
                   ON ic2.id = id2.id_ingreso
            WHERE id2.tipo_documento = '{$tipoDoc}'
              AND ic2.estado    != 'anulado'
              AND ic2.eliminado  = false
              AND ic2.id_empresa IN ({$this->sqlIn($idsEmpresa)})
              {$filtroFecha}
            GROUP BY id2.id_referencia_documento
        ";
    }

    /**
     * CTE con lo retenido por cada factura candidata (`cand`, ver sqlFacturas()) hasta una
     * fecha de corte opcional. La regla de enlace (id_venta o num_doc_sustento normalizado
     * a 15 dígitos, con lo retenido por línea cuando una retención sustenta varias facturas)
     * vive en AbonosVentaSql y es la misma que usan Ingresos y Facturas de Venta.
     *
     * @param bool $pocas true cuando `cand` trae pocas facturas (solo las pendientes, o una
     *                    sola): entonces conviene buscar las líneas de cada una por índice.
     *                    Con todo el histórico ("Todos", "Pagadas") es más rápido el hash.
     */
    private function getCteRetenido(array $idsEmpresa, ?string $fechaHasta = null, bool $pocas = true): string
    {
        $filtroFecha = $fechaHasta ? "AND r.fecha_emision <= :retenido_hasta" : '';
        return AbonosVentaSql::cteRetenidoDeDocumentos('cand', $this->sqlAny($idsEmpresa), $filtroFecha,
            $pocas && $this->indiceExiste('idx_ret_vta_det_sustento_norm'));
    }

    /**
     * Condición sobre las notas `n` (crédito o débito) que cuentan para el saldo: ambiente
     * de producción (tolerando notas legacy sin tipo_ambiente, NULL, para no perderlas del
     * cálculo) y, con corte, emitidas hasta esa fecha.
     */
    private function condNotas(array $idsEmpresa, string $phFecha): string
    {
        return "AND (n.tipo_ambiente IS NULL OR {$this->condAmbiente('n', $idsEmpresa)})"
            . ($phFecha !== '' ? " AND n.fecha_emision <= {$phFecha}" : '');
    }

    /**
     * CTE con las notas de crédito aplicadas a cada factura candidata hasta una fecha de
     * corte opcional. Columnas: id_venta y total_nc. El enlace es por (empresa, número
     * normalizado): la NC de un establecimiento no descuenta la factura de otro con el
     * mismo número.
     */
    private function getCteNC(array $idsEmpresa, ?string $fechaHasta = null): string
    {
        return AbonosVentaSql::cteNotasDeDocumentos('notas_credito_cabecera', 'total_nc', 'cand',
            $this->condNotas($idsEmpresa, $fechaHasta ? ':nc_hasta' : ''));
    }

    /**
     * CTE con las notas de débito aplicadas a cada factura candidata hasta una fecha de
     * corte opcional. A diferencia de la NC (que resta), la ND SUMA al saldo pendiente de
     * la factura: es un cargo adicional al cliente, no una devolución.
     * Columnas: id_venta y total_nd.
     */
    private function getCteND(array $idsEmpresa, ?string $fechaHasta = null): string
    {
        return AbonosVentaSql::cteNotasDeDocumentos('nota_debito_cabecera', 'total_nd', 'cand',
            $this->condNotas($idsEmpresa, $fechaHasta ? ':nd_hasta' : ''));
    }

    /**
     * Condición " OR v.id IN (…)" con las facturas que tienen alguna nota de débito, para
     * el prefiltro de candidatas (ver sqlFacturas()): la ND suma al saldo, así que una
     * factura "pagada" según sus cobros puede volver a deber. Cadena vacía si en estas
     * empresas no hay ninguna ND, que es lo normal: así ni siquiera se busca.
     * La búsqueda va de la nota a la factura (pocas notas), no al revés.
     */
    private function condFacturasConNotaDebito(array $idsEmpresa, ?string $fechaHasta, array &$params): string
    {
        $clave = implode(',', $idsEmpresa);
        $this->hayNotasDebito[$clave] ??= (bool) $this->db->query(
            "SELECT EXISTS (SELECT 1 FROM nota_debito_cabecera
                            WHERE id_empresa IN ({$this->sqlIn($idsEmpresa)}) AND eliminado = false AND estado != 'anulado')"
        )->fetchColumn();
        if (!$this->hayNotasDebito[$clave]) {
            return '';
        }
        if ($fechaHasta) {
            $params[':nd_pre_hasta'] = $fechaHasta;
        }
        $numVc   = AbonosVentaSql::numFactura('vc');
        $numNota = AbonosVentaSql::normalizar('n.num_doc_modificado');
        return " OR v.id IN (
                    SELECT vc.id
                    FROM nota_debito_cabecera n
                    JOIN ventas_cabecera vc
                         ON vc.id_empresa = n.id_empresa
                        AND vc.eliminado  = false
                        AND {$numVc} = {$numNota}
                    WHERE n.id_empresa IN ({$this->sqlIn($idsEmpresa)})
                      AND n.estado    != 'anulado'
                      AND n.eliminado  = false
                      " . $this->condNotas($idsEmpresa, $fechaHasta ? ':nd_pre_hasta' : '') . "
                )";
    }

    /** Caché por petición de condFacturasConNotaDebito(): empresas => ¿hay alguna ND? */
    private array $hayNotasDebito = [];

    /**
     * Extrae fecha_hasta del array de filtros y agrega los parámetros de corte
     * en $params para los CTEs que la necesitan.
     */
    private function aplicarFechaCorteCtEs(array $filtros, array &$params): ?string
    {
        $fechaHasta = $filtros['fecha_hasta'] ?? null;
        if ($fechaHasta) {
            $params[':cobrado_hasta']  = $fechaHasta;
            $params[':retenido_hasta'] = $fechaHasta;
            $params[':nc_hasta']       = $fechaHasta;
            $params[':nd_hasta']       = $fechaHasta;
        }
        return $fechaHasta ?: null;
    }

    /**
     * Expresión SQL de la columna **Días** del listado: los días transcurridos desde la
     * EMISIÓN del documento hasta la **Fecha Hasta** del filtro (o hasta hoy, si no se fijó
     * corte). Es la antigüedad del documento, no su mora.
     *
     * No confundir con `dias_vencido`, que cuenta desde el VENCIMIENTO (emisión + días de
     * crédito) siempre contra `CURRENT_DATE`: ese sigue decidiendo el estado VENCIDA/VIGENTE,
     * el color rojo de la fila y los tramos del gráfico de antigüedad. Los dos viajan juntos
     * en cada fila.
     *
     * @param string $ph Placeholder propio de cada consulta: el listado unificado arma
     *                   varios SELECT y reusar el mismo nombre rompe el bind (ver memoria
     *                   pdo-placeholders-repetidos).
     */
    private function sqlDiasTranscurridos(array $filtros, string $colFechaEmision, string $ph, array &$params): string
    {
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaHasta === '') {
            return "(CURRENT_DATE - {$colFechaEmision}::date)";
        }
        $params[$ph] = $fechaHasta;
        // CAST(:ph AS date) y no ":ph::date": el parser de placeholders de PDO no distingue
        // bien el cast `::` pegado al nombre del parámetro.
        return "(CAST({$ph} AS date) - {$colFechaEmision}::date)";
    }

    // ─────────────────────────────────────────────────────────────────────
    // VENCIMIENTO (selector "Vencimiento según" de la pantalla)
    //
    // Decide desde cuándo un documento cuenta como vencido, y con eso la mora
    // (`dias_vencido`), el estado Vencidas / Al día, las tarjetas y los tramos:
    //   - DOCUMENTO (por defecto): los días de crédito guardados en la factura o
    //     recibo; el saldo inicial, su fecha de vencimiento.
    //   - CLIENTE: el plazo configurado HOY en la ficha del cliente
    //     (`clientes.plazo`), contado desde la emisión.
    //   - EMISION: sin crédito; vence el mismo día de emisión.
    // ─────────────────────────────────────────────────────────────────────

    /** Modo de vencimiento normalizado (lista blanca: se interpola en el SQL). */
    public static function modoVencimiento(array $filtros): string
    {
        $m = strtoupper(trim((string) ($filtros['vencimiento'] ?? '')));
        return in_array($m, ['CLIENTE', 'EMISION'], true) ? $m : 'DOCUMENTO';
    }

    /** Días de crédito de un documento con columnas `fecha_emision`, `dias_credito` e `id_cliente`. */
    private function sqlDiasCredito(array $filtros, string $alias = 'v'): string
    {
        return match (self::modoVencimiento($filtros)) {
            'CLIENTE' => "COALESCE((SELECT cl_dc.plazo FROM clientes cl_dc WHERE cl_dc.id = {$alias}.id_cliente), 0)",
            'EMISION' => '0',
            default   => "COALESCE({$alias}.dias_credito, 0)",
        };
    }

    /** Fecha de vencimiento (date) de una factura o recibo según el modo. */
    private function sqlVencimiento(array $filtros, string $alias = 'v'): string
    {
        return "({$alias}.fecha_emision + INTERVAL '1 day' * {$this->sqlDiasCredito($filtros, $alias)})::date";
    }

    /** Fecha de vencimiento de un saldo inicial `s` según el modo (puede ser NULL en DOCUMENTO). */
    private function sqlVencimientoSaldoInicial(array $filtros): string
    {
        return match (self::modoVencimiento($filtros)) {
            'DOCUMENTO' => 's.fecha_vencimiento',
            default     => $this->sqlVencimiento($filtros, 's'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // ALCANCE DEL USUARIO (§6, ver App\Helpers\AlcanceRegistros)
    //
    // Si el usuario es de nivel 1 y NO tiene acceso total ('t') en el módulo, la
    // cartera se limita (los niveles 2 y 3 ven siempre toda la empresa):
    //   - Modo VENDEDOR (`id_vendedor_filtro`): a los documentos de su vendedor. Manda
    //     el `id_vendedor` del documento y, si no tiene, el asignado al cliente
    //     (`clientes.id_vendedor`); los que llevan otro vendedor quedan fuera aunque
    //     el cliente sea suyo. Los saldos iniciales no llevan vendedor: entran solo
    //     por el cliente.
    //   - Modo REGISTROS PROPIOS (`id_usuario_filtro`, el usuario no es vendedor):
    //     a los documentos que él registró. Cada fuente guarda al creador en su
    //     propia columna: facturas y recibos en `id_usuario` —la misma que filtran
    //     Factura de Venta y Recibo de Venta— y saldos iniciales en `created_by`.
    // Lo resuelve el controller desde el nivel, el permiso y la sesión
    // (CuentasPorCobrarController::alcanceUsuario()) y viaja dentro de los filtros:
    // NUNCA llega del cliente.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Devuelve la condición a concatenar al WHERE (" AND (...)"), o cadena vacía
     * si el usuario ve toda la empresa.
     *
     * Cada llamada usa su propio prefijo de placeholder ($ph): varias consultas
     * combinan más de un WHERE en el mismo SQL. Dentro de una misma condición el
     * IN del vendedor se repite (cliente y documento); en este proyecto eso es
     * seguro (ver memoria pdo-placeholders-repetidos).
     *
     * @param bool $docTieneVendedor false para tablas sin `id_vendedor` (saldos iniciales).
     */
    private function condAlcanceUsuario(array $filtros, string $alias, string $columna, string $ph, array &$params, bool $docTieneVendedor = true): string
    {
        return \App\Helpers\AlcanceRegistros::condicionSql($filtros, $alias, $columna, $ph, $params, $docTieneVendedor);
    }

    /**
     * Filtro Vendedor de la pantalla sobre los saldos iniciales CxC. La tabla no
     * guarda `id_vendedor`: su vendedor es el asignado al cliente (`clientes.id_vendedor`).
     */
    private function condVendedorSaldoInicial(array $filtros, string $ph, array &$params): string
    {
        $idVendedor = (int) ($filtros['id_vendedor'] ?? 0);
        if ($idVendedor <= 0) {
            return '';
        }
        $params[":{$ph}"] = $idVendedor;
        return " AND EXISTS (SELECT 1 FROM clientes {$ph}_c
                             WHERE {$ph}_c.id = s.id_cliente AND {$ph}_c.id_vendedor = :{$ph})";
    }

    /**
     * Vendedor asignado a un cliente (`clientes.id_vendedor`), o null. Para las
     * acciones por id sobre saldos iniciales, cuya consulta no trae al cliente.
     */
    public function getIdVendedorDeCliente(int $idCliente, int $idEmpresa): ?int
    {
        $st = $this->db->prepare(
            "SELECT id_vendedor FROM clientes WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false"
        );
        $st->execute([':id' => $idCliente, ':id_empresa' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v ? (int) $v : null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ORDEN DE LAS FILAS
    //
    // El listado se arma mezclando tres consultas (facturas + recibos + saldos
    // iniciales), así que el ORDER BY de cada una no manda: el orden final se
    // aplica sobre el arreglo ya unido (ver el controller). Como la pantalla, el
    // Excel y el PDF parten de ese mismo arreglo, los tres salen igual.
    //
    // La pantalla ordena haciendo clic en las cabeceras y manda la columna elegida
    // en `orden_col`/`orden_dir`; la lista blanca de abajo es la que decide qué se
    // acepta. El gemelo en el navegador es CXC_ORDEN (public/js/modulos/cuentas_por_cobrar.js).
    // ─────────────────────────────────────────────────────────────────────

    /** Orden por defecto: cartera alfabética por cliente (A-Z). */
    public const ORDEN_DEFECTO = ['cliente_nombre', 'ASC'];

    /** A igualdad de la columna elegida, el documento más próximo a vencer primero. */
    private const ORDEN_DESEMPATES = ['fecha_vencimiento' => 'ASC', 'numero_factura' => 'ASC'];

    /**
     * Columnas por las que se puede ordenar el listado: clave (la misma que la vista
     * manda en `data-sort`) => definición para App\Helpers\OrdenFilas.
     */
    private static function ordenColumnas(): array
    {
        return [
            'numero_factura'    => ['tipo' => 'texto'],
            'origen'            => ['tipo' => 'texto'],
            'cliente_nombre'    => ['tipo' => 'texto'],
            'fecha_emision'     => ['tipo' => 'fecha'],
            'fecha_vencimiento' => ['tipo' => 'fecha'],
            'total'             => ['tipo' => 'numero'],
            // "Cobrado" = abonos + retenciones + notas de crédito aplicadas (lo mismo
            // que suman la columna de la tabla, el Excel y el PDF).
            'cobrado'           => ['tipo' => 'numero', 'valor' => static fn (array $f): float =>
                (float) ($f['total_cobrado'] ?? 0) + (float) ($f['total_retenido'] ?? 0) + (float) ($f['total_nc'] ?? 0)],
            'saldo'             => ['tipo' => 'numero'],
            'dias_vencido'      => ['tipo' => 'numero'],
        ];
    }

    /** Aplica a las filas ya unificadas el orden pedido en los filtros. */
    public function ordenarFilas(array $filas, array $filtros): array
    {
        return \App\Helpers\OrdenFilas::aplicar(
            $filas,
            self::ordenColumnas(),
            self::ORDEN_DEFECTO,
            $filtros['orden_col'] ?? '',
            $filtros['orden_dir'] ?? '',
            self::ORDEN_DESEMPATES
        );
    }

    /**
     * Listado principal de cuentas por cobrar.
     */
    public function getListado(int|array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $sql = $this->sqlFacturas($this->idsEmpresa($idsEmpresa), $filtros, $params) . "
            -- v.id desempata: sin él, las filas con la misma fecha salen en orden
            -- arbitrario y cambian de posición entre cargas (el orden depende del plan).
            ORDER BY fecha_vencimiento ASC, fecha_emision DESC, id DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SELECT de las facturas del listado con su saldo (sin ORDER BY). Lo comparten el
     * listado, las tarjetas y la antigüedad, que lo envuelven para sumar.
     *
     * Rendimiento: calcular retenciones y notas exige normalizar el número de cada factura
     * (varias expresiones regulares por fila). Antes se hacía sobre TODO el histórico de la
     * empresa en cada consulta, aunque casi todo esté cobrado. Ahora va en dos pasos:
     *  1. `cand`: las facturas que pasan los filtros y, cuando el estado pide saldo
     *     pendiente, cuyo total menos lo cobrado aún es positivo (o que tienen nota de
     *     débito). Es una cota segura: retenciones y NC solo bajan el saldo, la ND lo sube.
     *  2. Retenciones, NC y ND se cruzan solo con esas candidatas, y el saldo exacto decide.
     */
    private function sqlFacturas(array $ids, array $filtros, array &$params): string
    {
        [$where, $params] = $this->buildWhere($ids, $filtros);
        $fh = $this->aplicarFechaCorteCtEs($filtros, $params);

        $estado    = $filtros['estado'] ?? 'PENDIENTES';
        $venc      = $this->sqlVencimiento($filtros);
        $saldo     = "(v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0))";
        $prefiltro = '';
        $condSaldo = 'true'; // TODOS → sin filtro extra
        if (in_array($estado, ['PENDIENTES', 'VENCIDAS', 'AL_DIA'], true)) {
            $prefiltro = " AND (v.importe_total - COALESCE(cb.total_cobrado, 0) > 0"
                       . $this->condFacturasConNotaDebito($ids, $fh, $params) . ")";
            if ($estado === 'VENCIDAS') {
                $prefiltro .= " AND {$venc} < CURRENT_DATE";
            } elseif ($estado === 'AL_DIA') {
                $prefiltro .= " AND {$venc} >= CURRENT_DATE";
            }
            $condSaldo = "{$saldo} > 0";
        } elseif ($estado === 'PAGADAS') {
            $condSaldo = "{$saldo} <= 0";
        }

        return "
            WITH cobrado  AS (" . $this->getCteCobrado($ids, $fh) . "),
                 cand     AS MATERIALIZED (
                     SELECT v.id, v.id_empresa, {$this->numV} AS num_norm
                     FROM ventas_cabecera v
                     LEFT JOIN cobrado cb ON cb.id_venta = v.id
                     WHERE {$where}{$prefiltro}
                 ),
                 retenido AS (" . $this->getCteRetenido($ids, $fh, $prefiltro !== '') . "),
                 nc_aplic AS (" . $this->getCteNC($ids, $fh) . "),
                 nd_aplic AS (" . $this->getCteND($ids, $fh) . ")
            SELECT
                v.id,
                {$this->colsEstablecimiento('v')},
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                COALESCE(ven.nombre,'')     AS vendedor_nombre,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                                                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                    AS total_nc,
                COALESCE(nd.total_nd, 0)                                                                                    AS total_nd,
                {$saldo} AS saldo,
                {$venc}::timestamp AS fecha_vencimiento, -- mismo tipo que antes (fecha + intervalo)
                {$this->sqlDiasCredito($filtros)} AS dias_credito,
                (CURRENT_DATE - {$venc}) AS dias_vencido,
                " . $this->sqlDiasTranscurridos($filtros, 'v.fecha_emision', ':dias_corte_fac', $params) . " AS dias_transcurridos
            FROM cand
            JOIN ventas_cabecera v ON v.id = cand.id
            LEFT JOIN empresas emp ON emp.id = v.id_empresa
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN vendedores ven ON ven.id = v.id_vendedor
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.id_venta = v.id
            LEFT JOIN nd_aplic nd ON nd.id_venta = v.id
            WHERE {$condSaldo}
        ";
    }

    /**
     * Normaliza el filtro de tipo de documento del listado unificado.
     * Valores: TODOS | FACTURA | RECIBO | SALDO_INICIAL.
     */
    private function getTipoDoc(array $filtros): string
    {
        $t = strtoupper(trim((string)($filtros['tipo_doc'] ?? 'TODOS')));
        return in_array($t, ['FACTURA', 'RECIBO', 'SALDO_INICIAL'], true) ? $t : 'TODOS';
    }

    /**
     * Indica si los saldos iniciales entran en el cálculo con los filtros dados.
     * Con el filtro Vendedor sí entran: saldos_iniciales_cxc no guarda id_vendedor,
     * así que se toma el vendedor asignado al cliente (ver condVendedorSaldoInicial).
     * La misma regla la aplica el controller al armar el listado unificado.
     */
    public function incluyeSaldosIniciales(array $filtros): bool
    {
        // No aplican con el filtro Producto: un saldo inicial no tiene líneas de detalle.
        return in_array($this->getTipoDoc($filtros), ['TODOS', 'SALDO_INICIAL'], true)
            && !$this->tieneFiltroProducto($filtros);
    }

    /**
     * Estadísticas para las tarjetas superiores (respeta el filtro tipo_doc).
     */
    public function getEstadisticas(int|array $idsEmpresa, array $filtros): array
    {
        $ids     = $this->idsEmpresa($idsEmpresa);
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['total_facturas' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'facturas_vencidas' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            // Para estadísticas, no aplicar filtro de estado pues queremos todos los saldos
            $params = [];
            $sql = "
                SELECT
                    COUNT(*) AS total_facturas,
                    SUM(f.saldo) AS total_saldo,
                    SUM(CASE WHEN f.dias_vencido > 0  THEN f.saldo ELSE 0 END) AS total_vencido,
                    SUM(CASE WHEN f.dias_vencido <= 0 THEN f.saldo ELSE 0 END) AS total_al_dia,
                    COUNT(*) FILTER (WHERE f.dias_vencido > 0) AS facturas_vencidas
                FROM (" . $this->sqlFacturas($ids, array_merge($filtros, ['estado' => 'PENDIENTES']), $params) . ") f
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los recibos de venta pendientes (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getStatsRecibos($ids, $filtros)
            : ['cnt' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'vencidas' => 0];

        // Sumar los saldos iniciales CXC (mismo filtro de cliente; vendedor = el del cliente)
        $si = $this->incluyeSaldosIniciales($filtros)
            ? $this->getStatsSaldosInicialesCxc($ids, $filtros)
            : ['cnt' => 0, 'total_saldo' => 0, 'total_vencido' => 0, 'total_al_dia' => 0, 'vencidas' => 0];

        return [
            'total_facturas'   => (int)($r['total_facturas']    ?? 0) + $rec['cnt']           + $si['cnt'],
            'total_saldo'      => (float)($r['total_saldo']     ?? 0) + $rec['total_saldo']   + $si['total_saldo'],
            'total_vencido'    => (float)($r['total_vencido']   ?? 0) + $rec['total_vencido'] + $si['total_vencido'],
            'total_al_dia'     => (float)($r['total_al_dia']    ?? 0) + $rec['total_al_dia']  + $si['total_al_dia'],
            'facturas_vencidas'=> (int)($r['facturas_vencidas'] ?? 0) + $rec['vencidas']      + $si['vencidas'],
        ];
    }

    /**
     * Agregados de los saldos iniciales CXC pendientes (pendiente = saldo_inicial
     * - cobrado - retenido, con la retención calculada al vuelo) para sumarlos a
     * las tarjetas. Respeta el filtro de cliente.
     */
    private function getStatsSaldosInicialesCxc(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($idsEmpresa, 'si_emp', $params)}) AND s.eliminado = false";
        $where .= $this->condAlcanceUsuario($filtros, "s", "created_by", "prop_si_stats", $params, false);
        $where .= $this->condVendedorSaldoInicial($filtros, 'ven_si_stats', $params);

        if (!empty($filtros['id_cliente'])) {
            $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $cli = array_filter(array_map('intval', $raw));
            if (!empty($cli)) {
                $in = [];
                foreach (array_values($cli) as $i => $id) { $k = ":sicc{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE sub.pend > 0) AS cnt,
                COALESCE(SUM(CASE WHEN sub.pend > 0 THEN sub.pend ELSE 0 END), 0) AS total_saldo,
                COALESCE(SUM(CASE WHEN sub.pend > 0 AND sub.fecha_vencimiento IS NOT NULL AND sub.fecha_vencimiento < CURRENT_DATE THEN sub.pend ELSE 0 END), 0) AS total_vencido,
                COALESCE(SUM(CASE WHEN sub.pend > 0 AND (sub.fecha_vencimiento IS NULL OR sub.fecha_vencimiento >= CURRENT_DATE) THEN sub.pend ELSE 0 END), 0) AS total_al_dia,
                COUNT(*) FILTER (WHERE sub.pend > 0 AND sub.fecha_vencimiento IS NOT NULL AND sub.fecha_vencimiento < CURRENT_DATE) AS vencidas
            FROM (
                SELECT {$this->sqlVencimientoSaldoInicial($filtros)} AS fecha_vencimiento,
                       (s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS pend
                FROM saldos_iniciales_cxc s"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
            ) sub
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'cnt'           => (int)($r['cnt']           ?? 0),
            'total_saldo'   => (float)($r['total_saldo'] ?? 0),
            'total_vencido' => (float)($r['total_vencido'] ?? 0),
            'total_al_dia'  => (float)($r['total_al_dia']  ?? 0),
            'vencidas'      => (int)($r['vencidas']      ?? 0),
        ];
    }

    /**
     * Análisis de antigüedad (aging) para el gráfico (respeta el filtro tipo_doc).
     */
    public function getAntiguedad(int|array $idsEmpresa, array $filtros): array
    {
        $ids     = $this->idsEmpresa($idsEmpresa);
        $tipoDoc = $this->getTipoDoc($filtros);
        $r = ['tramo_vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'tramo_mas_90' => 0];

        if (in_array($tipoDoc, ['TODOS', 'FACTURA'], true)) {
            $params = [];
            $sql = "
                SELECT
                    SUM(CASE WHEN dias_vencido BETWEEN 1 AND 30
                        THEN saldo ELSE 0 END) AS tramo_1_30,
                    SUM(CASE WHEN dias_vencido BETWEEN 31 AND 60
                        THEN saldo ELSE 0 END) AS tramo_31_60,
                    SUM(CASE WHEN dias_vencido BETWEEN 61 AND 90
                        THEN saldo ELSE 0 END) AS tramo_61_90,
                    SUM(CASE WHEN dias_vencido > 90
                        THEN saldo ELSE 0 END) AS tramo_mas_90,
                    SUM(CASE WHEN dias_vencido <= 0
                        THEN saldo ELSE 0 END) AS tramo_vigente
                FROM (" . $this->sqlFacturas($ids, array_merge($filtros, ['estado' => 'PENDIENTES']), $params) . ") sub
            ";

            $st = $this->db->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: $r;
        }

        // Sumar los tramos de los recibos de venta (mismo filtro de cliente/fechas)
        $rec = in_array($tipoDoc, ['TODOS', 'RECIBO'], true)
            ? $this->getAntiguedadRecibos($ids, $filtros)
            : ['vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'mas_90' => 0];

        // Sumar los tramos de los saldos iniciales CXC (mismo filtro de cliente; vendedor = el del cliente)
        $si = $this->incluyeSaldosIniciales($filtros)
            ? $this->getAntiguedadSaldosInicialesCxc($ids, $filtros)
            : ['vigente' => 0, 'tramo_1_30' => 0, 'tramo_31_60' => 0, 'tramo_61_90' => 0, 'mas_90' => 0];

        return [
            'vigente'    => (float)($r['tramo_vigente']  ?? 0) + $rec['vigente']     + $si['vigente'],
            'tramo_1_30' => (float)($r['tramo_1_30']    ?? 0) + $rec['tramo_1_30']  + $si['tramo_1_30'],
            'tramo_31_60'=> (float)($r['tramo_31_60']   ?? 0) + $rec['tramo_31_60'] + $si['tramo_31_60'],
            'tramo_61_90'=> (float)($r['tramo_61_90']   ?? 0) + $rec['tramo_61_90'] + $si['tramo_61_90'],
            'mas_90'     => (float)($r['tramo_mas_90']  ?? 0) + $rec['mas_90']      + $si['mas_90'],
        ];
    }

    /**
     * Tramos de antigüedad de los saldos iniciales CXC pendientes (pendiente
     * neto de retención calculada al vuelo). Respeta el filtro de cliente.
     */
    private function getAntiguedadSaldosInicialesCxc(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($idsEmpresa, 'si_emp', $params)}) AND s.eliminado = false";
        $where .= $this->condAlcanceUsuario($filtros, 's', 'created_by', 'prop_si_ant', $params, false);
        $vencSi = $this->sqlVencimientoSaldoInicial($filtros);
        $where .= $this->condVendedorSaldoInicial($filtros, 'ven_si_ant', $params);

        if (!empty($filtros['id_cliente'])) {
            $raw = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $cli = array_filter(array_map('intval', $raw));
            if (!empty($cli)) {
                $in = [];
                foreach (array_values($cli) as $i => $id) { $k = ":sica{$i}"; $in[] = $k; $params[$k] = $id; }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN dv <= 0           THEN pend ELSE 0 END), 0) AS tramo_vigente,
                COALESCE(SUM(CASE WHEN dv BETWEEN 1 AND 30  THEN pend ELSE 0 END), 0) AS tramo_1_30,
                COALESCE(SUM(CASE WHEN dv BETWEEN 31 AND 60 THEN pend ELSE 0 END), 0) AS tramo_31_60,
                COALESCE(SUM(CASE WHEN dv BETWEEN 61 AND 90 THEN pend ELSE 0 END), 0) AS tramo_61_90,
                COALESCE(SUM(CASE WHEN dv > 90            THEN pend ELSE 0 END), 0) AS tramo_mas_90
            FROM (
                SELECT (s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0)) AS pend,
                       CASE WHEN {$vencSi} IS NULL THEN 0
                            ELSE (CURRENT_DATE - {$vencSi})::int END AS dv
                FROM saldos_iniciales_cxc s"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
            ) sub
            WHERE sub.pend > 0
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'vigente'     => (float)($r['tramo_vigente'] ?? 0),
            'tramo_1_30'  => (float)($r['tramo_1_30']   ?? 0),
            'tramo_31_60' => (float)($r['tramo_31_60']  ?? 0),
            'tramo_61_90' => (float)($r['tramo_61_90']  ?? 0),
            'mas_90'      => (float)($r['tramo_mas_90'] ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECIBOS DE VENTA (espejo del listado de facturas; cobros con
    // tipo_documento = 'RECIBO'; sin retenciones ni notas de crédito)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * WHERE del listado de recibos de venta. Un recibo cuenta como CxC salvo
     * que esté anulado o facturado (al facturarlo, la factura hereda el saldo).
     */
    private function buildWhereRecibos(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        $where = "v.id_empresa IN ({$this->phIn($idsEmpresa, 'emp', $params)})
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','facturado')
              AND {$this->condAmbiente('v', $idsEmpresa)}"
              . $this->condAlcanceUsuario($filtros, 'v', 'id_usuario', 'prop_rec', $params);

        $saldoExpr = "(v.importe_total - COALESCE(cb.total_cobrado, 0))";

        // Filtro de estado CxC (mismos valores que el listado de facturas)
        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND {$saldoExpr} > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND {$saldoExpr} > 0
                        AND {$this->sqlVencimiento($filtros)} < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND {$saldoExpr} > 0
                        AND {$this->sqlVencimiento($filtros)} >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND {$saldoExpr} <= 0";
        }
        // TODOS → sin filtro extra

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":rcli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND v.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND v.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int)$filtros['id_vendedor'];
        }
        // Filtro Producto sobre las líneas del recibo (mismo criterio que las facturas)
        $where .= $this->condProducto('recibos_venta_detalle', 'id_recibo', 'v', $filtros, $params, 'rp');

        return [$where, $params];
    }

    /**
     * Listado de recibos de venta para CxC (mismas columnas que getListado;
     * total_retenido y total_nc siempre 0: el recibo no tiene retenciones ni NC).
     */
    public function getListadoRecibos(int|array $idsEmpresa, array $filtros): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        [$where, $params] = $this->buildWhereRecibos($ids, $filtros);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($ids, $fh, 'RECIBO') . ")
            SELECT
                v.id,
                {$this->colsEstablecimiento('v')},
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                COALESCE(ven.nombre,'')     AS vendedor_nombre,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                       AS total_cobrado,
                0                                                   AS total_retenido,
                0                                                   AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0)     AS saldo,
                {$this->sqlVencimiento($filtros)}::timestamp AS fecha_vencimiento,
                {$this->sqlDiasCredito($filtros)} AS dias_credito,
                (CURRENT_DATE - {$this->sqlVencimiento($filtros)}) AS dias_vencido,
                " . $this->sqlDiasTranscurridos($filtros, 'v.fecha_emision', ':dias_corte_rec', $params) . " AS dias_transcurridos
            FROM recibos_venta_cabecera v
            LEFT JOIN empresas emp ON emp.id = v.id_empresa
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN vendedores ven ON ven.id = v.id_vendedor
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE {$where}
            -- v.id desempata: sin él, las filas con la misma fecha salen en orden
            -- arbitrario y cambian de posición entre cargas (el orden depende del plan).
            ORDER BY fecha_vencimiento ASC, v.fecha_emision DESC, v.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregados de los recibos de venta pendientes para las tarjetas superiores.
     */
    private function getStatsRecibos(array $idsEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idsEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idsEmpresa, $fh, 'RECIBO') . ")
            SELECT
                COUNT(v.id) AS cnt,
                COALESCE(SUM(v.importe_total - COALESCE(cb.total_cobrado, 0)), 0) AS total_saldo,
                COALESCE(SUM(CASE
                    WHEN {$this->sqlVencimiento($filtros)} < CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) ELSE 0 END), 0) AS total_vencido,
                COALESCE(SUM(CASE
                    WHEN {$this->sqlVencimiento($filtros)} >= CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) ELSE 0 END), 0) AS total_al_dia,
                COUNT(CASE
                    WHEN {$this->sqlVencimiento($filtros)} < CURRENT_DATE THEN 1 END) AS vencidas
            FROM recibos_venta_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'cnt'           => (int)($r['cnt']            ?? 0),
            'total_saldo'   => (float)($r['total_saldo']  ?? 0),
            'total_vencido' => (float)($r['total_vencido']?? 0),
            'total_al_dia'  => (float)($r['total_al_dia'] ?? 0),
            'vencidas'      => (int)($r['vencidas']       ?? 0),
        ];
    }

    /**
     * Tramos de antigüedad de los recibos de venta pendientes.
     */
    private function getAntiguedadRecibos(array $idsEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhereRecibos($idsEmpresa, $filtrosSinEstado);
        $fh = !empty($filtros['fecha_hasta']) ? $filtros['fecha_hasta'] : null;
        if ($fh) $params[':cobrado_hasta'] = $fh;

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado($idsEmpresa, $fh, 'RECIBO') . ")
            SELECT
                COALESCE(SUM(CASE WHEN dias_vencido <= 0              THEN saldo ELSE 0 END), 0) AS tramo_vigente,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 1 AND 30  THEN saldo ELSE 0 END), 0) AS tramo_1_30,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 31 AND 60 THEN saldo ELSE 0 END), 0) AS tramo_31_60,
                COALESCE(SUM(CASE WHEN dias_vencido BETWEEN 61 AND 90 THEN saldo ELSE 0 END), 0) AS tramo_61_90,
                COALESCE(SUM(CASE WHEN dias_vencido > 90              THEN saldo ELSE 0 END), 0) AS tramo_mas_90
            FROM (
                SELECT
                    v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                    (CURRENT_DATE - {$this->sqlVencimiento($filtros)}) AS dias_vencido
                FROM recibos_venta_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN cobrado cb ON cb.id_venta = v.id
                WHERE {$where}
            ) sub
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'vigente'     => (float)($r['tramo_vigente'] ?? 0),
            'tramo_1_30'  => (float)($r['tramo_1_30']   ?? 0),
            'tramo_31_60' => (float)($r['tramo_31_60']  ?? 0),
            'tramo_61_90' => (float)($r['tramo_61_90']  ?? 0),
            'mas_90'      => (float)($r['tramo_mas_90'] ?? 0),
        ];
    }

    /**
     * Datos de un recibo de venta para validar el cobro (espejo de
     * getFacturaParaCobro). Excluye recibos anulados o ya facturados.
     */
    public function getReciboParaCobro(int $idRecibo, int $idEmpresa): ?array
    {
        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado([$idEmpresa], null, 'RECIBO') . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                c.id_vendedor    AS cliente_id_vendedor, -- alcance por vendedor (§6)
                COALESCE(cb.total_cobrado, 0)                   AS total_cobrado,
                0                                               AS total_retenido,
                0                                               AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date    AS fecha_vencimiento
            FROM recibos_venta_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
            WHERE v.id         = :id
              AND v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','facturado')
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRecibo, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Historial de cobros de un recibo de venta específico.
     */
    public function getHistorialCobrosRecibo(int $idRecibo, int $idEmpresa): array
    {
        $sql = "
            SELECT
                'COBRO'                        AS tipo,
                ic.id                          AS id,
                ic.fecha_emision               AS fecha_emision,
                ic.numero_ingreso              AS numero,
                COALESCE(ic.observaciones, '') AS observaciones,
                id2.monto_cobrado              AS monto,
                COALESCE(u.nombre, '')         AS usuario_nombre,
                COALESCE(fp.formas, '')        AS forma_cobro
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic  ON ic.id  = id2.id_ingreso
            LEFT  JOIN usuarios          u   ON u.id   = ic.id_usuario
            " . $this->joinFormasCobro() . "
            WHERE id2.tipo_documento           = 'RECIBO'
              AND id2.id_referencia_documento  = :id_recibo
              AND ic.id_empresa                = :id_empresa
              AND ic.estado                   != 'anulado'
              AND ic.eliminado                 = false
            ORDER BY ic.fecha_emision DESC, ic.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id_recibo' => $idRecibo, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Formas de cobro de un ingreso, agregadas en UNA sola celda.
     *
     * Antes se hacía con `LEFT JOIN ingresos_pagos` directo: un ingreso pagado con
     * dos formas (efectivo + transferencia) devolvía DOS filas del mismo cobro, así
     * que el historial lo listaba duplicado y el total lo sumaba dos veces.
     */
    private function joinFormasCobro(): string
    {
        return "LEFT JOIN LATERAL (
                    SELECT string_agg(DISTINCT efp.nombre, ', ') AS formas
                    FROM ingresos_pagos ip
                    JOIN empresa_formas_pago efp
                      ON efp.id = ip.id_forma_cobro AND efp.id_empresa = ic.id_empresa
                    WHERE ip.id_ingreso = ic.id
                ) fp ON TRUE";
    }

    /**
     * Movimientos que afectan el saldo de una factura: cobros (ingresos),
     * retenciones en la fuente, notas de crédito (abonos) y notas de débito (cargo).
     *
     * Antes solo listaba los ingresos, así que una factura cancelada con una NC o
     * una retención aparecía como "sin cobros registrados" aunque su saldo ya
     * estuviera en cero. Las reglas de enlace son EXACTAMENTE las de los CTE que
     * calculan el saldo (getCteCobrado / getCteRetenido / getCteNC / getCteND):
     * si un documento no aparece aquí, tampoco está restando en el saldo.
     *
     * Columnas comunes: tipo, id, fecha_emision, numero, forma_cobro (detalle),
     * usuario_nombre, monto, observaciones y `signo` (+1 abono, -1 cargo).
     */
    public function getHistorialCobros(int $idVenta, int $idEmpresa): array
    {
        $numSus  = AbonosVentaSql::normalizar('rd.num_doc_sustento');
        $numVc   = AbonosVentaSql::numFactura('vc');
        $numNc   = AbonosVentaSql::normalizar('n.num_doc_modificado');
        $ambNota = $this->condAmbiente('n', [$idEmpresa]);

        $sql = "
            WITH fact AS (
                SELECT v.id, v.id_empresa, {$this->numV} AS num_norm
                FROM ventas_cabecera v
                WHERE v.id = :id_venta AND v.id_empresa = :id_empresa AND v.eliminado = false
            )

            -- ── 1. Cobros registrados (ingresos) ──────────────────────────────
            SELECT
                'COBRO'                        AS tipo,
                1                              AS signo,
                ic.id                          AS id,
                ic.fecha_emision               AS fecha_emision,
                ic.numero_ingreso              AS numero,
                COALESCE(fp.formas, '')        AS forma_cobro,
                COALESCE(u.nombre, '')         AS usuario_nombre,
                id2.monto_cobrado              AS monto,
                COALESCE(ic.observaciones, '') AS observaciones
            FROM fact f
            INNER JOIN ingresos_detalle  id2 ON id2.tipo_documento          = 'FACTURA'
                                            AND id2.id_referencia_documento = f.id
            INNER JOIN ingresos_cabecera ic  ON ic.id = id2.id_ingreso
            LEFT  JOIN usuarios          u   ON u.id  = ic.id_usuario
            " . $this->joinFormasCobro() . "
            WHERE ic.id_empresa = f.id_empresa
              AND ic.estado    != 'anulado'
              AND ic.eliminado  = false

            UNION ALL

            -- ── 2a. Retenciones enlazadas por número de documento de sustento ──
            --     Solo lo retenido en LAS LÍNEAS que apuntan a esta factura: una
            --     retención electrónica puede sustentar varias.
            SELECT
                'RETENCION',
                1,
                r.id,
                r.fecha_emision,
                CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                'Retención en la fuente',
                COALESCE(u.nombre, ''),
                SUM(rd.valor_retenido),
                COALESCE(r.periodo_fiscal, '')
            FROM fact f
            INNER JOIN retencion_venta_cabecera r  ON r.id_empresa = f.id_empresa AND r.eliminado = false
            INNER JOIN retencion_venta_detalle  rd ON rd.id_retencion = r.id
            LEFT  JOIN usuarios                 u  ON u.id = r.created_by
            WHERE COALESCE(rd.num_doc_sustento, '') <> ''
              AND {$numSus} = f.num_norm
            GROUP BY r.id, r.fecha_emision, r.establecimiento, r.punto_emision,
                     r.secuencial, u.nombre, r.periodo_fiscal

            UNION ALL

            -- ── 2b. Retención registrada DESDE la factura (id_venta) cuyo detalle
            --     no enlaza ninguna factura por número: cuenta el total de cabecera.
            SELECT
                'RETENCION',
                1,
                r.id,
                r.fecha_emision,
                CONCAT(r.establecimiento,'-',r.punto_emision,'-',r.secuencial),
                'Retención en la fuente',
                COALESCE(u.nombre, ''),
                (r.total_renta + r.total_iva + r.total_isd),
                COALESCE(r.periodo_fiscal, '')
            FROM fact f
            INNER JOIN retencion_venta_cabecera r ON r.id_empresa = f.id_empresa
                                                 AND r.eliminado = false
                                                 AND r.id_venta  = f.id
            LEFT  JOIN usuarios u ON u.id = r.created_by
            WHERE NOT EXISTS (
                      SELECT 1
                      FROM retencion_venta_detalle rd
                      JOIN ventas_cabecera vc ON vc.id_empresa = r.id_empresa
                                             AND vc.eliminado  = false
                                             AND {$numVc} = {$numSus}
                      WHERE rd.id_retencion = r.id
                        AND COALESCE(rd.num_doc_sustento, '') <> ''
                  )

            UNION ALL

            -- ── 3. Notas de crédito aplicadas (abono) ─────────────────────────
            SELECT
                'NOTA_CREDITO',
                1,
                n.id,
                n.fecha_emision,
                CONCAT(n.establecimiento,'-',n.punto_emision,'-',n.secuencial),
                COALESCE(n.motivo, ''),
                COALESCE(u.nombre, ''),
                n.importe_total,
                COALESCE(n.observaciones, '')
            FROM fact f
            INNER JOIN notas_credito_cabecera n ON n.id_empresa = f.id_empresa
            LEFT  JOIN usuarios               u ON u.id = n.id_usuario
            WHERE n.estado   != 'anulado'
              AND n.eliminado = false
              AND (n.tipo_ambiente IS NULL OR {$ambNota})
              AND {$numNc} = f.num_norm

            UNION ALL

            -- ── 4. Notas de débito (CARGO: suman al saldo, no lo abonan) ──────
            SELECT
                'NOTA_DEBITO',
                -1,
                n.id,
                n.fecha_emision,
                CONCAT(n.establecimiento,'-',n.punto_emision,'-',n.secuencial),
                'Nota de débito',
                COALESCE(u.nombre, ''),
                n.importe_total,
                COALESCE(n.observaciones, '')
            FROM fact f
            INNER JOIN nota_debito_cabecera n ON n.id_empresa = f.id_empresa
            LEFT  JOIN usuarios             u ON u.id = n.id_usuario
            WHERE n.estado   != 'anulado'
              AND n.eliminado = false
              AND (n.tipo_ambiente IS NULL OR {$ambNota})
              AND {$numNc} = f.num_norm

            ORDER BY fecha_emision DESC, id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id_venta' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene datos de una factura para validar el cobro.
     */
    public function getFacturaParaCobro(int $idVenta, int $idEmpresa): ?array
    {
        // `cand` = solo esta factura: retenciones y notas se cruzan con ella y no con todo
        // el histórico de la empresa (ver sqlFacturas()).
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado([$idEmpresa]) . "),
                 cand     AS MATERIALIZED (
                     SELECT v.id, v.id_empresa, {$this->numV} AS num_norm
                     FROM ventas_cabecera v
                     WHERE v.id         = :id
                       AND v.id_empresa = :id_empresa
                       AND v.eliminado  = false
                       AND v.estado NOT IN ('anulado','anulada') -- incluye 'borrador' (mismo criterio que Ingresos)
                 ),
                 retenido AS (" . $this->getCteRetenido([$idEmpresa]) . "),
                 nc_aplic AS (" . $this->getCteNC([$idEmpresa]) . "),
                 nd_aplic AS (" . $this->getCteND([$idEmpresa]) . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                c.id_vendedor    AS cliente_id_vendedor, -- alcance por vendedor (§6)
                COALESCE(cb.total_cobrado, 0)                                                                                AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                               AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                     AS total_nc,
                COALESCE(nd.total_nd, 0)                                                                                     AS total_nd,
                v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura
            FROM cand
            JOIN ventas_cabecera v ON v.id = cand.id
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.id_venta = v.id
            LEFT JOIN nd_aplic nd ON nd.id_venta = v.id
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Puntos de emisión activos de la empresa (para el select Serie del cobro).
     * Las series inactivas no se ofrecen: el cobro siempre emite un ingreso nuevo.
     */
    public function getPuntosEmision(int $idEmpresa): array
    {
        return (new \App\repositories\SecuencialRepository())->getPuntosEmisionSerie($idEmpresa, true);
    }

    /**
     * Datos de un punto de emisión específico (para construir el número de ingreso).
     * Solo si está activo: así el servidor rechaza una serie inactiva aunque llegue en la
     * petición (modal abierto antes de inhabilitarla o petición armada a mano).
     */
    public function getPuntoEmisionPorId(int $idPunto, int $idEmpresa): ?array
    {
        return (new \App\repositories\SecuencialRepository())->getPuntoEmisionSerie($idPunto, $idEmpresa, true);
    }

    /**
     * Conceptos de ingreso activos de la empresa.
     */
    public function getConceptos(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, comportamiento
                    FROM empresa_opciones_ingreso_egreso
                    WHERE id_empresa = :id_empresa
                      AND aplica_ingresos = TRUE
                      AND UPPER(estado) = 'ACTIVO'
                      AND eliminado = FALSE
                    ORDER BY nombre ASC";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Formas de cobro activas de la empresa.
     */
    public function getFormasCobro(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo FROM empresa_formas_pago
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND activo     = true
                  AND (aplica_en IN ('AMBAS','INGRESO','COBRO') OR aplica_en IS NULL)
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Plantillas de WhatsApp aprobadas de la empresa.
     * Retorna [] si la tabla no existe (módulo no instalado).
     */
    public function getPlantillasWA(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, idioma, componentes
                    FROM whatsapp_plantillas
                    WHERE id_empresa   = :id_empresa
                      AND estado_meta  = 'APPROVED'
                      AND eliminado    = false
                    ORDER BY nombre";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verifica si la empresa tiene WhatsApp configurado.
     * Retorna false si la tabla no existe (módulo no instalado).
     */
    public function tieneWhatsappConfigurado(int $idEmpresa): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM empresa_whatsapp_config
                    WHERE id_empresa       = :id_empresa
                      AND eliminado        = false
                      AND access_token     IS NOT NULL AND access_token     <> ''
                      AND phone_number_id  IS NOT NULL AND phone_number_id  <> ''";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Años disponibles con facturas no anuladas (incluye borradores, igual que el listado).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND estado NOT IN ('anulado','anulada')
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /**
     * Facturas con saldo pendiente para el envío de estados de cuenta
     * (automatizaciones). Una fila por factura, con datos de contacto del cliente.
     * Si $soloVencidas o $diasMin > 0, limita a facturas vencidas.
     */
    public function getFacturasPendientesParaEnvio(int $idEmpresa, bool $soloVencidas, int $diasMin): array
    {
        $params = [':id_empresa' => $idEmpresa];
        // Candidatas con la misma cota que sqlFacturas(): total menos cobros > 0, o con ND.
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado([$idEmpresa]) . "),
                 cand     AS MATERIALIZED (
                     SELECT v.id, v.id_empresa, {$this->numV} AS num_norm
                     FROM ventas_cabecera v
                     LEFT JOIN cobrado cb ON cb.id_venta = v.id
                     WHERE v.id_empresa = :id_empresa
                       AND v.eliminado  = false
                       AND v.estado    IN ('autorizado','autorizada')
                       AND v.tipo_ambiente = '2'
                       AND (v.importe_total - COALESCE(cb.total_cobrado, 0) > 0"
                       . $this->condFacturasConNotaDebito([$idEmpresa], null, $params) . ")
                 ),
                 retenido AS (" . $this->getCteRetenido([$idEmpresa]) . "),
                 nc_aplic AS (" . $this->getCteNC([$idEmpresa]) . "),
                 nd_aplic AS (" . $this->getCteND([$idEmpresa]) . ")
            SELECT
                v.id,
                v.id_cliente,
                c.nombre                AS cliente_nombre,
                COALESCE(c.email,'')    AS cliente_email,
                COALESCE(c.telefono,'') AS cliente_telefono,
                c.identificacion        AS cliente_ruc,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date     AS fecha_vencimiento,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido,
                v.importe_total                                                              AS total,
                COALESCE(cb.total_cobrado, 0)                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                    AS total_nc,
                (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) AS saldo
            FROM cand
            JOIN ventas_cabecera v ON v.id = cand.id
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.id_venta = v.id
            LEFT JOIN nd_aplic nd ON nd.id_venta = v.id
            WHERE (v.importe_total + COALESCE(nd.total_nd, 0) - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
        ";

        if ($soloVencidas || $diasMin > 0) {
            $sql .= " AND (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) >= :dmin";
            $params[':dmin'] = max(1, $diasMin);
        }

        $sql .= " ORDER BY c.nombre ASC, v.fecha_emision ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Líneas (ítems) de una factura, para el detalle "por línea".
     */
    public function getLineasFactura(int $idVenta): array
    {
        $sql = "SELECT descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                FROM ventas_detalle
                WHERE id_venta = :id
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Descripciones de los ítems de varias facturas y recibos, para el concepto de los
     * mensajes de cobro por WhatsApp (ConceptosCobro). Los ids ya llegan validados contra
     * la empresa y el alcance del usuario por quien llama (getFacturaParaCobro /
     * getReciboParaCobro), igual que en getLineasFactura().
     *
     * @return array<string,string[]> 'FACTURA:id' | 'RECIBO:id' => descripciones en el orden del documento
     */
    public function getDescripcionesDocumentos(array $idsFacturas, array $idsRecibos): array
    {
        $out = [];
        $fuentes = [
            ['FACTURA', 'ventas_detalle',        'id_venta',  $idsFacturas, 'df'],
            ['RECIBO',  'recibos_venta_detalle', 'id_recibo', $idsRecibos,  'dr'],
        ];
        foreach ($fuentes as [$origen, $tabla, $fk, $ids, $pref]) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (!$ids) {
                continue;
            }
            $params = [];
            $in  = $this->phIn($ids, $pref, $params);
            $sql = "SELECT d.{$fk} AS id_doc, d.descripcion
                    FROM {$tabla} d
                    WHERE d.{$fk} IN ({$in})
                    ORDER BY d.{$fk}, d.id";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $out[$origen . ':' . (int) $l['id_doc']][] = (string) ($l['descripcion'] ?? '');
            }
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // SALDOS INICIALES CXC
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Corte de fecha para los abonos de un saldo inicial CxC (misma regla que
     * los CTEs cobrado/retenido/nc_aplic de las facturas): con "Fecha Hasta",
     * un cobro, retención o NC fechado después del corte NO reduce el saldo,
     * así el saldo inicial sigue pendiente hasta el día anterior al cobro.
     *
     * Devuelve [filtroCobro, filtroRet, filtroNc] para los tres LATERAL y
     * registra los parámetros (placeholders distintos: PDO/pgsql no admite
     * repetirlos). Sin corte, los tres filtros van vacíos.
     */
    private function corteSaldoInicialCxc(array $filtros, array &$params): array
    {
        $fh = !empty($filtros['fecha_hasta']) ? (string)$filtros['fecha_hasta'] : null;
        if ($fh === null) {
            return ['', '', ''];
        }
        $params[':si_cob_hasta'] = $fh;
        $params[':si_ret_hasta'] = $fh;
        $params[':si_nc_hasta']  = $fh;
        return [
            "AND ic.fecha_emision <= :si_cob_hasta",
            "AND r.fecha_emision <= :si_ret_hasta",
            "AND ncc.fecha_emision <= :si_nc_hasta",
        ];
    }

    /**
     * JOIN LATERAL con lo cobrado de un saldo inicial CxC. Alias `cob`, columna
     * `cobrado`. Sin corte devuelve el acumulado guardado (monto_cobrado, que
     * SaldosInicialesRepository::actualizarMontoCobradoCxc deriva de estos
     * mismos ingresos); con corte lo recalcula desde ingresos_detalle solo con
     * los ingresos fechados hasta la fecha.
     */
    private function lateralCobradoSaldoInicial(string $filtroFecha = ''): string
    {
        if ($filtroFecha === '') {
            return "
                LEFT JOIN LATERAL (SELECT s.monto_cobrado AS cobrado) cob ON true";
        }
        return "
                LEFT JOIN LATERAL (
                    SELECT COALESCE(SUM(id2.monto_cobrado), 0) AS cobrado
                    FROM ingresos_detalle id2
                    INNER JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                    WHERE id2.tipo_documento = 'SALDO_INICIAL'
                      AND id2.id_referencia_documento = s.id
                      AND ic.estado    != 'anulado'
                      AND ic.eliminado  = false
                      {$filtroFecha}
                ) cob ON true";
    }

    /**
     * JOIN LATERAL que suma lo retenido sobre un saldo inicial CxC (retenciones
     * sin id_venta cuyo sustento coincide con el nro_documento del saldo, del
     * mismo cliente). Alias `ret`, columna `retenido`. La guarda NOT EXISTS
     * evita duplicar cuando el número también corresponde a una factura real.
     */
    private function lateralRetSaldoInicial(string $filtroFecha = ''): string
    {
        return "
                LEFT JOIN LATERAL (
                    SELECT SUM(rd.valor_retenido) AS retenido
                    FROM retencion_venta_detalle rd
                    INNER JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
                    WHERE r.eliminado = false
                      AND r.id_empresa = s.id_empresa
                      AND r.id_venta IS NULL
                      AND r.id_cliente = s.id_cliente
                      AND rd.num_doc_sustento IS NOT NULL
                      AND rd.num_doc_sustento <> ''
                      AND regexp_replace(rd.num_doc_sustento, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      {$filtroFecha}
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ret ON true";
    }

    /**
     * JOIN LATERAL que suma las notas de crédito aplicadas a un saldo inicial CxC
     * (match por número de documento normalizado). Alias `ncsi`, columna `nc_total`.
     * La guarda NOT EXISTS evita duplicar el descuento cuando el número también
     * corresponde a una factura real (esa NC ya la resta el listado de facturas).
     */
    private function lateralNcSaldoInicial(string $filtroFecha = ''): string
    {
        return "
                LEFT JOIN LATERAL (
                    SELECT SUM(ncc.importe_total) AS nc_total
                    FROM notas_credito_cabecera ncc
                    WHERE ncc.eliminado  = false
                      AND ncc.estado    != 'anulado'
                      AND ncc.id_empresa = s.id_empresa
                      AND regexp_replace(ncc.num_doc_modificado, '[^0-9]', '', 'g')
                          = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      {$filtroFecha}
                      AND NOT EXISTS (
                          SELECT 1 FROM ventas_cabecera vc
                          WHERE vc.id_empresa = s.id_empresa
                            AND vc.eliminado = false
                            AND regexp_replace(CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial), '[^0-9]', '', 'g')
                                = regexp_replace(s.nro_documento, '[^0-9]', '', 'g')
                      )
                ) ncsi ON true";
    }

    public function getSaldosInicialesCxc(int|array $idsEmpresa, array $filtros = []): array
    {
        // Pendiente real = saldo_inicial - cobrado - retenido - NC. Lo retenido y
        // las NC se calculan al vuelo (igual que en las facturas normales); no se
        // almacenan en el saldo inicial. Con "Fecha Hasta", los tres abonos se
        // cortan a esa fecha (ver corteSaldoInicialCxc).
        $pend     = '(s.saldo_inicial - cob.cobrado - COALESCE(ret.retenido, 0) - COALESCE(ncsi.nc_total, 0))';
        $aplicado = '(cob.cobrado + COALESCE(ret.retenido, 0) + COALESCE(ncsi.nc_total, 0))';
        $vencSi   = $this->sqlVencimientoSaldoInicial($filtros);

        $params = [];
        $where  = "s.id_empresa IN ({$this->phIn($this->idsEmpresa($idsEmpresa), 'si_emp', $params)}) AND s.eliminado = false";
        $where .= $this->condAlcanceUsuario($filtros, 's', 'created_by', 'prop_si', $params, false);
        $where .= $this->condVendedorSaldoInicial($filtros, 'ven_si', $params);
        [$fCob, $fRet, $fNc] = $this->corteSaldoInicialCxc($filtros, $params);

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODOS') {
            if ($filtros['estado'] === 'PAGADO') {
                $where .= " AND {$pend} <= 0";
            } elseif ($filtros['estado'] === 'PARCIAL') {
                $where .= " AND {$pend} > 0 AND {$aplicado} > 0";
            } elseif ($filtros['estado'] === 'PENDIENTE') {
                $where .= " AND {$pend} > 0 AND {$aplicado} <= 0";
            }
        }

        // Filtro por cliente (mismo criterio que el listado principal de CxC)
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":sicli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND s.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['fecha_desde'])) { $where .= " AND s.fecha_emision >= :sfd"; $params[':sfd'] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where .= " AND s.fecha_emision <= :sfh"; $params[':sfh'] = $filtros['fecha_hasta']; }

        $sql = "SELECT
                    s.id, s.id_cliente, s.nro_documento, s.fecha_emision, {$vencSi} AS fecha_vencimiento,
                    s.ruc_cliente, s.nombre_cliente,
                    COALESCE(ven.nombre,'') AS vendedor_nombre, -- el vendedor asignado al cliente
                    {$this->colsEstablecimiento('s')},
                    CAST(s.saldo_inicial          AS NUMERIC(16,2)) AS saldo_inicial,
                    CAST(cob.cobrado              AS NUMERIC(16,2)) AS monto_cobrado,
                    CAST(COALESCE(ret.retenido,0) AS NUMERIC(16,2)) AS monto_retenido,
                    CAST(COALESCE(ncsi.nc_total,0) AS NUMERIC(16,2)) AS monto_nc,
                    CAST({$pend}                  AS NUMERIC(16,2)) AS saldo_pendiente,
                    CASE
                        WHEN {$pend} <= 0     THEN 'PAGADO'
                        WHEN {$aplicado} > 0  THEN 'PARCIAL'
                        ELSE 'PENDIENTE'
                    END AS estado,
                    s.observaciones,
                    CASE WHEN {$vencSi} < CURRENT_DATE AND {$pend} > 0
                         THEN CURRENT_DATE - {$vencSi} ELSE 0 END AS dias_vencido,
                    " . $this->sqlDiasTranscurridos($filtros, 's.fecha_emision', ':dias_corte_si', $params) . " AS dias_transcurridos
                FROM saldos_iniciales_cxc s
                LEFT JOIN empresas emp ON emp.id = s.id_empresa
                LEFT JOIN clientes cli ON cli.id = s.id_cliente
                LEFT JOIN vendedores ven ON ven.id = cli.id_vendedor"
                . $this->lateralCobradoSaldoInicial($fCob)
                . $this->lateralRetSaldoInicial($fRet)
                . $this->lateralNcSaldoInicial($fNc) . "
                WHERE {$where}
                ORDER BY s.fecha_emision ASC, s.nro_documento ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $idsEmpresa, array $filtros): array
    {
        $params = [];
        // Estado: toda factura no anulada, INCLUIDO el 'borrador' (aún sin autorizar del SRI).
        // Es el mismo criterio del buscador de documentos pendientes de Ingresos
        // (IngresoRepository::buscarDocumentosPendientes): si se puede cobrar, es cartera.
        $where = "v.id_empresa IN ({$this->phIn($idsEmpresa, 'emp', $params)})
              AND v.eliminado  = false
              AND v.estado NOT IN ('anulado','anulada')
              AND {$this->condAmbiente('v', $idsEmpresa)}"
              . $this->condAlcanceUsuario($filtros, 'v', 'id_usuario', 'prop_fac', $params);
        // El filtro de estado CxC (saldo y vencimiento) lo aplica sqlFacturas(): depende de
        // los abonos, que se calculan después de este WHERE.

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":cli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND v.id_cliente IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND v.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int)$filtros['id_vendedor'];
        }
        // Filtro Producto: facturas que tengan al menos una línea cuyo nombre o código
        // contenga el texto (mismo criterio que el Reporte de Ventas, producto_texto).
        $where .= $this->condProducto('ventas_detalle', 'id_venta', 'v', $filtros, $params, 'fp');

        return [$where, $params];
    }

    /**
     * Condición EXISTS sobre las líneas del documento para el filtro Producto (nombre o
     * código de la línea, ILIKE). Devuelve '' si no hay texto. Los saldos iniciales no tienen
     * líneas: cuando este filtro está activo quedan fuera (ver incluyeSaldosIniciales).
     */
    private function condProducto(string $tablaDetalle, string $fk, string $aliasCab, array $filtros, array &$params, string $prefijo): string
    {
        $sql = '';
        // (a) Productos elegidos en el buscador (ids; en consolidado ya vienen expandidos por código)
        $ids = $filtros['id_producto'] ?? '';
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)))));
        if ($ids) {
            $in = $this->phIn($ids, "{$prefijo}_id", $params);
            $sql .= " AND EXISTS (
                    SELECT 1 FROM {$tablaDetalle} dpi
                    WHERE dpi.{$fk} = {$aliasCab}.id AND dpi.id_producto IN ({$in})
                )";
        }
        // (b) Texto libre escrito sin elegir de la lista: nombre o código de la línea
        $txt = trim((string)($filtros['producto'] ?? ''));
        if ($txt !== '') {
            $params[":{$prefijo}_txt1"] = '%' . $txt . '%';
            $params[":{$prefijo}_txt2"] = '%' . $txt . '%';
            $sql .= " AND EXISTS (
                    SELECT 1 FROM {$tablaDetalle} dp
                    WHERE dp.{$fk} = {$aliasCab}.id
                      AND (dp.descripcion ILIKE :{$prefijo}_txt1 OR dp.codigo_principal ILIKE :{$prefijo}_txt2)
                )";
        }
        return $sql;
    }

    /** ¿Hay filtro de producto activo (ids elegidos o texto libre)? */
    public function tieneFiltroProducto(array $filtros): bool
    {
        $ids = $filtros['id_producto'] ?? '';
        $ids = array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string)$ids)));
        return !empty($ids) || trim((string)($filtros['producto'] ?? '')) !== '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRODUCTOS (buscador del filtro)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Buscador del filtro Producto (nombre o código). Con varios establecimientos devuelve
     * UNA fila por código (prefiere la de la empresa activa); el filtro luego se expande a
     * los productos hermanos con expandirProductosPorCodigo().
     */
    public function buscarProductos(int|array $idsEmpresa, int $idEmpresaActual, string $q, int $limite = 15): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        $q   = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':actual' => $idEmpresaActual];
        $inEmp  = $this->phIn($ids, 'bpr', $params);
        // Texto por palabras, en cualquier orden y sin distinguir tildes/eñe (regla del sistema).
        $cond   = FiltrosBusqueda::condicionTexto(['nombre', "COALESCE(codigo, '')"], $q, $params, 'bpr_t');
        if ($cond === '') {
            return [];
        }
        $sql = "SELECT id, COALESCE(codigo, '') AS codigo, nombre, id_empresa
                FROM productos
                WHERE id_empresa IN ({$inEmp})
                  AND eliminado = false
                  AND {$cond}
                ORDER BY (id_empresa = :actual) DESC, nombre
                LIMIT " . max(15, $limite * count($ids));
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        $vistos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $clave = trim((string)$p['codigo']) !== '' ? 'c:' . trim((string)$p['codigo']) : 'id:' . (int)$p['id'];
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $out[] = ['id' => (int)$p['id'], 'codigo' => (string)$p['codigo'], 'nombre' => (string)$p['nombre']];
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    /**
     * Consolidado: `productos` es por establecimiento; expande los ids elegidos a los
     * productos hermanos con el MISMO código en las empresas del alcance.
     */
    public function expandirProductosPorCodigo(array $idsProducto, int|array $idsEmpresa): array
    {
        $idsProducto = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$idsProducto) {
            return [];
        }
        $params = [];
        $inP = $this->phIn($idsProducto, 'xpr', $params);
        $inE = $this->phIn($this->idsEmpresa($idsEmpresa), 'xpe', $params);
        $st = $this->db->prepare("SELECT DISTINCT p2.id
                                  FROM productos p1
                                  JOIN productos p2 ON p2.codigo = p1.codigo AND p2.eliminado = false AND p2.id_empresa IN ({$inE})
                                  WHERE p1.id IN ({$inP}) AND COALESCE(TRIM(p1.codigo), '') <> ''");
        $st->execute($params);
        return array_values(array_unique(array_merge($idsProducto, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)))));
    }

    /**
     * Vista "Por producto": líneas de producto de los documentos del listado, una fila por
     * documento y producto (varias líneas del mismo producto en un documento se suman), con
     * cantidad y valor (base de la línea + sus impuestos). Facturas desde ventas_detalle y
     * recibos desde recibos_venta_detalle; los saldos iniciales no tienen líneas. Si el
     * listado ya está filtrado por producto (ids o texto), solo devuelve esas líneas, para que
     * la vista muestre exactamente la cartera de los productos elegidos.
     *
     * @return array<int,array{origen:string,id_doc:int,id_producto:?int,codigo:string,nombre:string,cantidad:float,valor:float}>
     */
    public function getLineasProductoPorDocumentos(array $idsFacturas, array $idsRecibos, array $filtros = []): array
    {
        $idsFacturas = array_values(array_unique(array_filter(array_map('intval', $idsFacturas))));
        $idsRecibos  = array_values(array_unique(array_filter(array_map('intval', $idsRecibos))));
        $out = [];
        $fuentes = [
            ['FACTURA', 'ventas_detalle',        'id_venta',  'ventas_detalle_impuestos',        'id_venta_detalle',  $idsFacturas, 'lf'],
            ['RECIBO',  'recibos_venta_detalle', 'id_recibo', 'recibos_venta_detalle_impuestos', 'id_recibo_detalle', $idsRecibos,  'lr'],
        ];
        foreach ($fuentes as [$origen, $tabla, $fk, $tablaImp, $fkImp, $ids, $pref]) {
            if (!$ids) {
                continue;
            }
            $params = [];
            $inDoc  = $this->phIn($ids, "{$pref}_doc", $params);
            $where  = "d.{$fk} IN ({$inDoc})";
            $idsProd = $filtros['id_producto'] ?? '';
            $idsProd = array_values(array_unique(array_filter(array_map('intval', is_array($idsProd) ? $idsProd : explode(',', (string)$idsProd)))));
            if ($idsProd) {
                $where .= " AND d.id_producto IN (" . $this->phIn($idsProd, "{$pref}_prod", $params) . ")";
            }
            $txt = trim((string)($filtros['producto'] ?? ''));
            if ($txt !== '') {
                $params[":{$pref}_t1"] = '%' . $txt . '%';
                $params[":{$pref}_t2"] = '%' . $txt . '%';
                $where .= " AND (d.descripcion ILIKE :{$pref}_t1 OR d.codigo_principal ILIKE :{$pref}_t2)";
            }
            $sql = "SELECT '{$origen}' AS origen,
                           d.{$fk} AS id_doc,
                           d.id_producto,
                           COALESCE(NULLIF(TRIM(d.codigo_principal), ''), '') AS codigo,
                           COALESCE(NULLIF(TRIM(d.descripcion), ''), '(sin descripción)') AS nombre,
                           SUM(d.cantidad) AS cantidad,
                           SUM(d.precio_total_sin_impuesto
                               + COALESCE((SELECT SUM(i.valor) FROM {$tablaImp} i WHERE i.{$fkImp} = d.id), 0)) AS valor
                    FROM {$tabla} d
                    WHERE {$where}
                    GROUP BY 1, 2, 3, 4, 5";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $out[] = [
                    'origen'      => $origen,
                    'id_doc'      => (int)$l['id_doc'],
                    'id_producto' => $l['id_producto'] !== null ? (int)$l['id_producto'] : null,
                    'codigo'      => (string)$l['codigo'],
                    'nombre'      => (string)$l['nombre'],
                    'cantidad'    => (float)$l['cantidad'],
                    'valor'       => (float)$l['valor'],
                ];
            }
        }
        return $out;
    }

    /** Código y nombre de varios productos (para describir el filtro en PDF/Excel): id => [codigo, nombre]. */
    public function getProductosPorIds(array $idsProducto, int|array $idsEmpresa): array
    {
        $idsProducto = array_values(array_unique(array_filter(array_map('intval', $idsProducto))));
        if (!$idsProducto) {
            return [];
        }
        $params = [];
        $inP = $this->phIn($idsProducto, 'npr', $params);
        $inE = $this->phIn($this->idsEmpresa($idsEmpresa), 'npe', $params);
        $st = $this->db->prepare("SELECT id, COALESCE(codigo, '') AS codigo, nombre FROM productos
                                  WHERE id IN ({$inP}) AND id_empresa IN ({$inE})");
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $out[(int)$p['id']] = ['codigo' => (string)$p['codigo'], 'nombre' => (string)$p['nombre']];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CLIENTES (buscador del filtro)
    //
    // Un mismo cliente puede estar repartido en varias filas de `clientes`:
    //   · por establecimiento — la tabla es por empresa, así que en el consolidado
    //     el mismo cliente existe una vez en cada empresa del grupo RUC;
    //   · por identificación  — el mismo contribuyente registrado dos veces, una
    //     con la cédula y otra con el RUC (esa cédula + '001').
    // En ambos casos se cruza por la clave base de la identificación
    // (IdentificacionTercero): el buscador muestra UNA entrada y el filtro se expande
    // a todas las filas hermanas, para que su cartera no salga partida en dos.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Buscador del filtro Cliente. Devuelve UNA fila por cliente real: las que comparten
     * la clave base de identificación (la cédula y su RUC, más las hermanas de otros
     * establecimientos en el consolidado) colapsan en una sola entrada del dropdown; el
     * filtro se expande después con expandirClientesPorIdentificacion().
     *
     * Gana la fila de la empresa activa y, entre la cédula y el RUC del mismo cliente, la
     * de identificación más larga (el RUC). El LIMIT del SQL se pide holgado porque varias
     * filas colapsan en una sola entrada.
     */
    public function buscarClientes(int|array $idsEmpresa, int $idEmpresaActual, string $q, int $limite = 15): array
    {
        $ids = $this->idsEmpresa($idsEmpresa);
        $q   = trim($q);
        if ($q === '') {
            return [];
        }
        $params = [':actual' => $idEmpresaActual];
        $inEmp  = $this->phIn($ids, 'bce', $params);
        // Texto por palabras, en cualquier orden y sin distinguir tildes/eñe (regla del sistema).
        $cond   = FiltrosBusqueda::condicionTexto(['nombre', "COALESCE(identificacion, '')"], $q, $params, 'bce_t');
        if ($cond === '') {
            return [];
        }
        $sql = "SELECT id, nombre, identificacion, id_empresa
                FROM clientes
                WHERE id_empresa IN ({$inEmp})
                  AND eliminado  = false
                  AND {$cond}
                ORDER BY (id_empresa = :actual) DESC, nombre, LENGTH(COALESCE(identificacion, '')) DESC
                LIMIT " . max(30, $limite * 2 * count($ids));
        $st = $this->db->prepare($sql);
        $st->execute($params);

        $out = [];
        foreach ($this->unaFilaPorTercero($st->fetchAll(PDO::FETCH_ASSOC)) as $c) {
            $out[] = ['id' => (int)$c['id'], 'nombre' => $c['nombre'], 'identificacion' => $c['identificacion']];
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }

    /**
     * Expande los ids de cliente elegidos a TODAS las filas que son el MISMO cliente (el
     * contribuyente registrado con la cédula y con el RUC, y en consolidado sus hermanas de
     * otros establecimientos), para que `id_cliente IN (...)` alcance los documentos y
     * saldos iniciales de todas ellas. La regla vive en ExpansionTerceroTrait.
     */
    public function expandirClientesPorIdentificacion(array $idsCliente, int|array $idsEmpresa): array
    {
        return $this->expandirTerceroPorIdentificacion('clientes', $idsCliente, $this->idsEmpresa($idsEmpresa));
    }

    /**
     * Nombre e identificación de varios clientes (para describir el filtro en PDF/Excel),
     * buscándolos en cualquiera de los establecimientos del alcance.
     * Devuelve id => ['nombre' => …, 'identificacion' => …].
     */
    public function getClientesPorIds(array $idsCliente, int|array $idsEmpresa): array
    {
        $idsCliente = array_values(array_unique(array_filter(array_map('intval', $idsCliente))));
        if (!$idsCliente) {
            return [];
        }
        $params = [];
        $inCli  = $this->phIn($idsCliente, 'nc', $params);
        $inEmp  = $this->phIn($this->idsEmpresa($idsEmpresa), 'ne', $params);
        $st = $this->db->prepare("SELECT id, nombre, identificacion FROM clientes
                                  WHERE id IN ({$inCli}) AND id_empresa IN ({$inEmp})");
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[(int)$c['id']] = ['nombre' => (string)$c['nombre'], 'identificacion' => (string)($c['identificacion'] ?? '')];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // COMPROBACIÓN CON CONTABILIDAD
    //
    // Definición para App\Services\ComprobacionContableService (formato en
    // App\repositories\ComprobacionContableRepository). El saldo de la cartera se descompone
    // en el documento que origina cada parte, con las MISMAS reglas del listado (getListado,
    // getListadoRecibos, getSaldosInicialesCxc): + factura, recibo, ND y saldo inicial;
    // − cobro (por ingreso), retención y NC aplicadas. Así cada asiento se compara con lo que
    // su documento movió en la cartera. Siempre es la empresa activa completa: sin filtros de
    // cliente/vendedor ni consolidado, porque la cuenta contable no los distingue.
    //
    // Lado contable: las cuentas de los conceptos de cartera de ventas y recibos
    // (PORCOBRARFACTURAVENTA / PORCOBRARRECIBOVENTA) en cualquier nivel de la cascada.
    // ─────────────────────────────────────────────────────────────────────

    public function definicionComprobacionContable(): array
    {
        $numFac  = AbonosVentaSql::numFactura('v');
        $numNc   = AbonosVentaSql::normalizar('n.num_doc_modificado');
        $numSus  = AbonosVentaSql::normalizar('rd.num_doc_sustento');
        $numVc   = AbonosVentaSql::numFactura('vc');
        $digitos = static fn (string $e): string => "regexp_replace({$e}, '[^0-9]', '', 'g')";
        $digVc   = $digitos("CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial)");
        $serie   = \App\repositories\ComprobacionContableRepository::NUM_SERIE;

        // Documentos que forman la cartera (mismo criterio que buildWhere / buildWhereRecibos).
        $ctes = "fac AS (
                    SELECT v.id, v.fecha_emision, v.importe_total, {$numFac} AS num
                    FROM ventas_cabecera v
                    WHERE v.id_empresa = :e AND v.eliminado = FALSE
                      AND v.estado NOT IN ('anulado', 'anulada')
                      AND v.tipo_ambiente = (SELECT t FROM amb)
                ),
                rec AS (
                    SELECT v.id, v.fecha_emision, v.importe_total
                    FROM recibos_venta_cabecera v
                    WHERE v.id_empresa = :e AND v.eliminado = FALSE
                      AND v.estado NOT IN ('anulado', 'facturado')
                      AND v.tipo_ambiente = (SELECT t FROM amb)
                ),
                si AS (
                    SELECT s.id, s.fecha_emision, s.saldo_inicial, s.id_cliente,
                           {$digitos('s.nro_documento')} AS num_dig
                    FROM saldos_iniciales_cxc s
                    WHERE s.id_empresa = :e AND s.eliminado = FALSE
                ),
                -- Facturas (cualquier estado no eliminado) por número en dígitos: guarda
                -- anti-duplicado de las retenciones/NC de saldos iniciales (lateralRetSaldoInicial).
                vdig AS (
                    SELECT DISTINCT {$digVc} AS num_dig
                    FROM ventas_cabecera vc
                    WHERE vc.id_empresa = :e AND vc.eliminado = FALSE
                )";

        $docs = "SELECT 'factura_venta' AS tipo, id AS id_doc, fecha_emision AS fecha, importe_total AS monto FROM fac
                 UNION ALL
                 SELECT 'recibo_venta', id, fecha_emision, importe_total FROM rec
                 UNION ALL
                 SELECT 'saldo_inicial', 0, fecha_emision, saldo_inicial FROM si
                 UNION ALL
                 -- Cobros (getCteCobrado / lateralCobradoSaldoInicial), uno por ingreso.
                 SELECT 'ingreso', ic.id, ic.fecha_emision, -id2.monto_cobrado
                 FROM ingresos_detalle id2
                 JOIN ingresos_cabecera ic ON ic.id = id2.id_ingreso
                 WHERE ic.id_empresa = :e AND ic.estado != 'anulado' AND ic.eliminado = FALSE
                   AND (   (id2.tipo_documento = 'FACTURA'       AND id2.id_referencia_documento IN (SELECT id FROM fac))
                        OR (id2.tipo_documento = 'RECIBO'        AND id2.id_referencia_documento IN (SELECT id FROM rec))
                        OR (id2.tipo_documento = 'SALDO_INICIAL' AND id2.id_referencia_documento IN (SELECT id FROM si)))
                 UNION ALL
                 -- Notas de crédito y de débito aplicadas a facturas (getCteNC / getCteND).
                 SELECT 'nota_credito', n.id, n.fecha_emision, -n.importe_total * (SELECT COUNT(*) FROM fac WHERE fac.num = {$numNc})
                 FROM notas_credito_cabecera n
                 WHERE n.id_empresa = :e AND n.estado != 'anulado' AND n.eliminado = FALSE
                   AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente = (SELECT t FROM amb))
                   AND EXISTS (SELECT 1 FROM fac WHERE fac.num = {$numNc})
                 UNION ALL
                 SELECT 'nota_debito', n.id, n.fecha_emision, n.importe_total * (SELECT COUNT(*) FROM fac WHERE fac.num = {$numNc})
                 FROM nota_debito_cabecera n
                 WHERE n.id_empresa = :e AND n.estado != 'anulado' AND n.eliminado = FALSE
                   AND (n.tipo_ambiente IS NULL OR n.tipo_ambiente = (SELECT t FROM amb))
                   AND EXISTS (SELECT 1 FROM fac WHERE fac.num = {$numNc})
                 UNION ALL
                 -- NC aplicadas a saldos iniciales (lateralNcSaldoInicial).
                 SELECT 'nota_credito', n.id, n.fecha_emision, -n.importe_total
                 FROM notas_credito_cabecera n
                 JOIN si ON si.num_dig = {$digitos('n.num_doc_modificado')}
                 WHERE n.id_empresa = :e AND n.estado != 'anulado' AND n.eliminado = FALSE
                   AND si.num_dig NOT IN (SELECT num_dig FROM vdig)
                 UNION ALL
                 -- Retenciones (AbonosVentaSql::cteRetenidoPorFactura): (a) líneas enlazadas
                 -- por número de sustento, (b) cabecera cuando se registró desde la factura.
                 SELECT 'retencion_venta', r.id, r.fecha_emision, -rd.valor_retenido
                 FROM retencion_venta_cabecera r
                 JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                 JOIN fac ON fac.num = {$numSus}
                 WHERE r.eliminado = FALSE AND r.id_empresa = :e
                   AND COALESCE(rd.num_doc_sustento, '') <> ''
                 UNION ALL
                 SELECT 'retencion_venta', r.id, r.fecha_emision, -(r.total_renta + r.total_iva + r.total_isd)
                 FROM retencion_venta_cabecera r
                 WHERE r.eliminado = FALSE AND r.id_empresa = :e
                   AND r.id_venta IN (SELECT id FROM fac)
                   AND NOT EXISTS (
                       SELECT 1 FROM retencion_venta_detalle rd
                       JOIN ventas_cabecera vc ON vc.id_empresa = r.id_empresa AND vc.eliminado = FALSE
                                              AND {$numVc} = {$numSus}
                       WHERE rd.id_retencion = r.id AND COALESCE(rd.num_doc_sustento, '') <> ''
                   )
                 UNION ALL
                 -- (c) Retenciones sobre saldos iniciales (lateralRetSaldoInicial).
                 SELECT 'retencion_venta', r.id, r.fecha_emision, -rd.valor_retenido
                 FROM retencion_venta_detalle rd
                 JOIN retencion_venta_cabecera r ON r.id = rd.id_retencion
                 JOIN si ON si.id_cliente = r.id_cliente AND si.num_dig = {$digitos('rd.num_doc_sustento')}
                 WHERE r.eliminado = FALSE AND r.id_empresa = :e AND r.id_venta IS NULL
                   AND COALESCE(rd.num_doc_sustento, '') <> ''
                   AND si.num_dig NOT IN (SELECT num_dig FROM vdig)";

        $migrados = "SELECT id_asiento_contable AS id_asiento, 'factura_venta' AS tipo, id AS id_doc FROM ventas_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'recibo_venta', id FROM recibos_venta_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'nota_credito', id FROM notas_credito_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'nota_debito', id FROM nota_debito_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'retencion_venta', id FROM retencion_venta_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL
                     UNION ALL SELECT id_asiento_contable, 'ingreso', id FROM ingresos_cabecera WHERE id_empresa = :e AND id_asiento_contable IS NOT NULL";

        return [
            'conceptos' => ['PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA'],
            'conceptos_texto' => 'Cuenta por cobrar de Facturas de Venta y de Recibos de Venta',
            'signo' => 1,
            'ctes' => $ctes,
            'docs' => $docs,
            'nativos' => [
                'factura_venta' => 'factura_venta', 'recibo_venta' => 'recibo_venta',
                'nota_credito' => 'nota_credito', 'nota_debito' => 'nota_debito',
                'retencion_venta' => 'retencion_venta', 'ingreso' => 'ingreso',
            ],
            'migrados' => $migrados,
            'apertura' => true,
            'numeros' => [
                'factura_venta' => ['ventas_cabecera', $serie],
                'recibo_venta' => ['recibos_venta_cabecera', $serie],
                'nota_credito' => ['notas_credito_cabecera', $serie],
                'nota_debito' => ['nota_debito_cabecera', $serie],
                'retencion_venta' => ['retencion_venta_cabecera', $serie],
                'ingreso' => ['ingresos_cabecera', 'x.numero_ingreso'],
            ],
        ];
    }
}

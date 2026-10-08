<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ConsolidacionGruposRepository;
use App\repositories\modulos\DeclaracionRentaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\Helpers\RubrosGastoPersonal;
use App\Rules\modulos\DeclaracionRentaRules;
use App\Services\ReportService;

/**
 * Declaración de Impuesto a la Renta (reporte anual).
 *
 * Según el tipo de contribuyente configurado en la empresa (catálogo `tipo_empresa`):
 *  - Persona natural no obligada a llevar contabilidad (tipo 1) → formulario 102 armado con
 *    los documentos del ejercicio: ventas = ingresos; compras marcadas "Deducible para
 *    declaración de IVA" (y liquidaciones de compra) = costos y gastos del negocio; compras
 *    marcadas "Gasto personal" = rebaja por gastos personales. Las notas de crédito restan y
 *    las notas de débito suman en cada bloque.
 *  - Persona natural obligada a llevar contabilidad (tipo 2, u `obligado_contabilidad = SI`)
 *    → mismo resumen de documentos y, además, los casilleros del formulario tomados de la
 *    contabilidad (`plan_cuentas.codigo_sri`). La liquidación puede calcularse con cualquiera
 *    de las dos fuentes (`ajustes.fuente`).
 *  - Sociedades (tipos 3, 4 y 5) → formulario 101: casilleros de Estado de Situación
 *    Financiera y Estado de Resultados por `codigo_sri` (misma agrupación que el botón
 *    "Renta SRI" de Estados Financieros) y la conciliación tributaria.
 *
 * El impuesto de personas naturales usa la tabla progresiva y los parámetros de gastos
 * personales ya configurados en /config/impuesto-renta-tramos (los mismos de nómina).
 * No se guarda nada: es un reporte.
 *
 * Varios establecimientos del mismo RUC: la declaración de renta se presenta por RUC, así
 * que se consolidan todos los establecimientos del grupo accesibles al usuario
 * (EmpresaRepository::getIdsGrupoRucAccesible, mismo criterio que el F103 y el consolidado de
 * Estados Financieros). Documentos y retenciones se suman; en la contabilidad, las cuentas que
 * en Consolidación de grupos están en modo UNICA solo cuentan desde su establecimiento fuente
 * (capital, resultados acumulados…), para no duplicarlas en el formulario.
 *
 * Nota de PDF: los textos usan guion ASCII ("-") y no el signo menos Unicode, porque las
 * fuentes core de TCPDF (Latin-1) lo imprimen como "?".
 */
class DeclaracionRentaService
{
    public const TIPO_PN   = 'pn';
    public const TIPO_PNOC = 'pnoc';
    public const TIPO_SOC  = 'soc';

    /** Casilleros del formulario 101/102 (obligados) usados en la conciliación tributaria. */
    private const CAS = [
        'total_ingresos'        => '6999',
        'total_costos_gastos'   => '7999',
        'utilidad_ejercicio'    => '801',
        'perdida_ejercicio'     => '802',
        'participacion'         => '803',
        'rentas_exentas'        => '805',
        'gastos_no_deducibles'  => '806',
        'deducciones_adicionales' => '810',
        'utilidad_gravable'     => '835',
        'perdida_amortizable'   => '836',
        'impuesto_causado'      => '850',
        'anticipo_pagado'       => '851',
        'retenciones'           => '857',
        'credito_anterior'      => '861',
        'subtotal_pagar'        => '866',
        'subtotal_favor'        => '867',
        'impuesto_pagar'        => '869',
        'saldo_favor'           => '870',
    ];

    public const FUENTES_DETALLE = [
        'ventas'           => 'Ventas (facturas)',
        'notas_credito'    => 'Notas de crédito emitidas',
        'notas_debito'     => 'Notas de débito emitidas',
        'compras_negocio'  => 'Compras del negocio (Declaración de IVA)',
        'liquidaciones'    => 'Liquidaciones de compra',
        'compras_personal' => 'Gastos personales',
        'compras_otro'     => 'Compras sin marca Deducible',
        'retenciones'      => 'Retenciones de renta que le hicieron',
    ];

    private DeclaracionRentaRepository $repo;
    private DeclaracionRentaRules $rules;
    private EmpresaRepository $empresaRepo;
    private ?EstadosFinancierosService $estadosFinancieros;
    private ?ImpuestoRentaEmpleadoService $impuestoRenta;

    public function __construct(
        DeclaracionRentaRepository $repo,
        ?DeclaracionRentaRules $rules = null,
        ?EstadosFinancierosService $estadosFinancieros = null,
        ?ImpuestoRentaEmpleadoService $impuestoRenta = null,
        ?EmpresaRepository $empresaRepo = null
    ) {
        $this->repo = $repo;
        $this->rules = $rules ?? new DeclaracionRentaRules();
        $this->estadosFinancieros = $estadosFinancieros;
        $this->impuestoRenta = $impuestoRenta;
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
    }

    private function ef(): EstadosFinancierosService
    {
        return $this->estadosFinancieros ??= new EstadosFinancierosService(
            new EstadosFinancierosRepository(),
            new ReportService()
        );
    }

    private function ir(): ImpuestoRentaEmpleadoService
    {
        return $this->impuestoRenta ??= new ImpuestoRentaEmpleadoService();
    }

    // ───────────────────────────── Contexto ─────────────────────────────

    /**
     * Establecimientos del mismo RUC que entran en la declaración: los accesibles al usuario
     * (siempre incluida la empresa activa). Con $idUsuario = 0 (scripts) solo la activa.
     */
    private function grupoRuc(int $idEmpresa, int $idUsuario): array
    {
        $todos = $this->empresaRepo->getIdsEmpresaMismoRuc($idEmpresa);
        $ids = $idUsuario > 0 ? $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario) : [$idEmpresa];
        if (!in_array($idEmpresa, $ids, true)) {
            $ids[] = $idEmpresa;
        }
        sort($ids);
        $esMatriz = $this->empresaRepo->getEsMatriz($idEmpresa);
        return [
            'ids'         => $ids,
            'etiquetas'   => $this->empresaRepo->getEtiquetasEstablecimiento($ids),
            'total_ruc'   => count($todos),
            'faltan'      => count(array_diff($todos, $ids)),
            'es_matriz'   => $esMatriz,
            'matriz'      => $esMatriz ? null : $this->empresaRepo->getOtraMatrizDelGrupo($idEmpresa),
            'consolidado' => count($ids) > 1,
        ];
    }

    /**
     * Qué formulario le corresponde a la empresa, qué establecimientos entran y con qué años
     * se puede trabajar.
     */
    public function getContexto(int $idEmpresa, int $idUsuario = 0): array
    {
        $empresa = $this->repo->getEmpresa($idEmpresa);
        if (!$empresa) {
            throw new \RuntimeException('Empresa no encontrada.');
        }
        $tipo = $this->resolverTipo($empresa);
        $ambiente = (string) ((int) ($empresa['tipo_ambiente'] ?? 1));
        $regimenId = (int) ($empresa['id_tipo_regimen'] ?? 0);
        $grupo = $this->grupoRuc($idEmpresa, $idUsuario);
        $ambientes = $this->repo->getAmbientes($grupo['ids']);

        $anios = [];
        foreach ($grupo['ids'] as $idEmp) {
            $anios = array_merge($anios, $this->repo->getAnios($idEmp, $ambientes[$idEmp] ?? $ambiente));
        }
        $anios = array_values(array_unique($anios));
        rsort($anios);

        return [
            'empresa'    => $empresa,
            'ambiente'   => $ambiente,
            'ambientes'  => $ambientes,
            'grupo'      => $grupo,
            'tipo'       => $tipo,
            'tipo_nombre' => self::nombreTipo($tipo),
            'formulario' => $tipo === self::TIPO_SOC ? '101' : '102',
            'con_contabilidad' => $tipo !== self::TIPO_PN,
            'regimen'    => $empresa['regimen_nombre'] ?? '',
            'es_rimpe'   => in_array($regimenId, [2, 3], true),
            'anios'      => $anios,
        ];
    }

    public static function nombreTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_PN   => 'Persona natural no obligada a llevar contabilidad',
            self::TIPO_PNOC => 'Persona natural obligada a llevar contabilidad',
            default         => 'Sociedad',
        };
    }

    /** Tipo de contribuyente a partir de `empresas.tipo` (catálogo tipo_empresa) y `obligado_contabilidad`. */
    private function resolverTipo(array $empresa): string
    {
        $codigo = (int) preg_replace('/[^0-9]/', '', (string) ($empresa['tipo'] ?? '1'));
        $obligado = strtoupper(trim((string) ($empresa['obligado_contabilidad'] ?? ''))) === 'SI';
        if ($codigo === 2 || ($codigo === 1 && $obligado)) {
            return self::TIPO_PNOC;
        }
        if ($codigo <= 1) {
            return self::TIPO_PN;
        }
        return self::TIPO_SOC;
    }

    // ───────────────────────────── Cálculo ─────────────────────────────

    /**
     * Arma la declaración completa del ejercicio.
     *
     * @param array $ajustesIn Valores que el usuario escribe en pantalla (ver DeclaracionRentaRules::validarAjustes).
     */
    public function calcular(int $idEmpresa, $anio, array $ajustesIn = [], int $idUsuario = 0): array
    {
        $anio = $this->rules->validarAnio($anio);
        $ajustes = $this->rules->validarAjustes($ajustesIn);
        $ctx = $this->getContexto($idEmpresa, $idUsuario);

        $desde = "{$anio}-01-01";
        $hasta = "{$anio}-12-31";
        $avisos = [];

        $grupo = $ctx['grupo'];
        if ($grupo['faltan'] > 0) {
            $avisos[] = ['tipo' => 'warning', 'texto' => "El RUC tiene {$grupo['total_ruc']} establecimientos y usted solo tiene acceso a " . count($grupo['ids']) . ': la declaración está incompleta (faltan ' . $grupo['faltan'] . '). Pida acceso a los demás establecimientos o genere el reporte desde la matriz.'];
        }
        if ($grupo['consolidado']) {
            $avisos[] = ['tipo' => 'info', 'texto' => 'Declaración consolidada por RUC: incluye ' . count($grupo['ids']) . ' establecimientos (' . implode('; ', $grupo['etiquetas']) . ').'];
        }

        // 1. Documentos del ejercicio (todos los establecimientos del grupo)
        $documentos = $this->resumirDocumentos($grupo['ids'], $ctx['ambientes'], $desde, $hasta, $avisos);

        // 2. Contabilidad (solo obligados y sociedades)
        $contabilidad = null;
        if ($ctx['con_contabilidad']) {
            $contabilidad = $this->casillerosContables($idEmpresa, $grupo, $desde, $hasta, $avisos);
        }
        if ($ctx['tipo'] === self::TIPO_PNOC && $ajustes['fuente'] === 'contabilidad' && empty($contabilidad['disponible'])) {
            $avisos[] = ['tipo' => 'warning', 'texto' => 'No hay asientos contabilizados en el ejercicio: la liquidación se calculó con los documentos.'];
            $ajustes['fuente'] = 'documentos';
        }
        if ($ctx['tipo'] === self::TIPO_PN) {
            $ajustes['fuente'] = 'documentos';
        }

        // 3. Liquidación del impuesto
        if ($ctx['tipo'] === self::TIPO_SOC) {
            $liquidacion = $this->liquidarSociedad($contabilidad, $documentos, $ajustes, $avisos);
            $parametros = null;
        } else {
            $parametros = $this->parametrosPersonaNatural($anio, $ajustes, $avisos);
            $liquidacion = $this->liquidarPersonaNatural($ctx, $documentos, $contabilidad, $ajustes, $parametros, $avisos);
        }

        if (!empty($ctx['es_rimpe'])) {
            $avisos[] = ['tipo' => 'info', 'texto' => 'La empresa está en régimen RIMPE (' . $ctx['regimen'] . '). Este reporte aplica la tabla y tarifa del régimen general; verifique el impuesto con la tabla RIMPE vigente.'];
        }

        return [
            'anio'         => $anio,
            'desde'        => $desde,
            'hasta'        => $hasta,
            'tipo'         => $ctx['tipo'],
            'tipo_nombre'  => $ctx['tipo_nombre'],
            'formulario'   => $ctx['formulario'],
            'empresa'      => $ctx['empresa'],
            'grupo'        => $grupo,
            'ajustes'      => $ajustes,
            'documentos'   => $documentos,
            'contabilidad' => $contabilidad,
            'liquidacion'  => $liquidacion,
            'parametros'   => $parametros,
            'avisos'       => $avisos,
        ];
    }

    /** Suma celda a celda dos bloques ['cantidad','base','total']. */
    private static function sumarBloque(array $a, array $b): array
    {
        return [
            'cantidad' => (int) ($a['cantidad'] ?? 0) + (int) ($b['cantidad'] ?? 0),
            'base'     => round((float) ($a['base'] ?? 0) + (float) ($b['base'] ?? 0), 2),
            'total'    => round((float) ($a['total'] ?? 0) + (float) ($b['total'] ?? 0), 2),
        ];
    }

    /**
     * Resumen de ventas, compras (por deducible), liquidaciones y retenciones del ejercicio,
     * sumando todos los establecimientos del grupo RUC ($idsEmpresa) con su propio ambiente.
     */
    private function resumirDocumentos(array $idsEmpresa, array $ambientes, string $desde, string $hasta, array &$avisos): array
    {
        $vacio = ['cantidad' => 0, 'base' => 0.0, 'total' => 0.0];
        $ventas = ['facturas' => $vacio, 'notas_credito' => $vacio, 'notas_debito' => $vacio];
        $compras = [];
        $liquidaciones = $vacio;
        $retenciones = $vacio;
        $rubros = [];

        foreach ($idsEmpresa as $idEmp) {
            $amb = (string) ($ambientes[$idEmp] ?? '1');
            $v = $this->repo->getResumenVentas($idEmp, $desde, $hasta, $amb);
            foreach ($ventas as $k => $b) {
                $ventas[$k] = self::sumarBloque($b, $v[$k]);
            }
            foreach ($this->repo->getResumenCompras($idEmp, $desde, $hasta, $amb) as $ded => $tipos) {
                foreach ($tipos as $tipo => $b) {
                    $compras[$ded][$tipo] = self::sumarBloque($compras[$ded][$tipo] ?? $vacio, $b);
                }
            }
            $liquidaciones = self::sumarBloque($liquidaciones, $this->repo->getResumenLiquidaciones($idEmp, $desde, $hasta, $amb));
            $retenciones = self::sumarBloque($retenciones, $this->repo->getRetencionesRenta($idEmp, $desde, $hasta, $amb));
            foreach ($this->repo->getGastosPersonalesPorRubro($idEmp, $desde, $hasta, $amb) as $rubro => $b) {
                $rubros[$rubro] = self::sumarBloque($rubros[$rubro] ?? $vacio, $b);
            }
        }

        // Rubros en el orden del formulario del SRI; "Sin rubro" al final (solo si hay).
        $personalRubros = [];
        foreach (RubrosGastoPersonal::CATALOGO as $cod => $nombre) {
            $personalRubros[$cod] = ($rubros[$cod] ?? $vacio) + ['nombre' => $nombre];
        }
        if (!empty($rubros[RubrosGastoPersonal::SIN_RUBRO]['cantidad'])) {
            $personalRubros[RubrosGastoPersonal::SIN_RUBRO] = $rubros[RubrosGastoPersonal::SIN_RUBRO] + ['nombre' => 'Sin rubro (sin clasificar)'];
            $avisos[] = ['tipo' => 'warning', 'texto' => 'Hay ' . $rubros[RubrosGastoPersonal::SIN_RUBRO]['cantidad'] . ' compra(s) de gasto personal sin rubro (vivienda, salud, educación…). Abra cada compra y elija el rubro para que el Anexo de Gastos Personales salga completo.'];
        }

        $bloque = static function (array $grupo) use ($vacio): array {
            $f = $grupo['01'] ?? $vacio;
            $nc = $grupo['04'] ?? $vacio;
            $nd = $grupo['05'] ?? $vacio;
            $otros = $grupo['otro'] ?? $vacio;
            return [
                'facturas'      => $f,
                'notas_credito' => $nc,
                'notas_debito'  => $nd,
                'otros'         => $otros,
                'neto_base'     => round($f['base'] + $otros['base'] - $nc['base'] + $nd['base'], 2),
                'neto_total'    => round($f['total'] + $otros['total'] - $nc['total'] + $nd['total'], 2),
            ];
        };

        $negocio = $bloque($compras['declaracion_iva'] ?? []);
        $negocio['liquidaciones'] = $liquidaciones;
        $negocio['neto_base'] = round($negocio['neto_base'] + $liquidaciones['base'], 2);
        $negocio['neto_total'] = round($negocio['neto_total'] + $liquidaciones['total'], 2);

        $personal = $bloque($compras['gasto_personal'] ?? []);
        $sinMarca = $bloque($compras['otro'] ?? []);

        $cantSinMarca = $sinMarca['facturas']['cantidad'] + $sinMarca['notas_credito']['cantidad']
            + $sinMarca['notas_debito']['cantidad'] + $sinMarca['otros']['cantidad'];
        if ($cantSinMarca > 0) {
            $avisos[] = ['tipo' => 'warning', 'texto' => "Hay {$cantSinMarca} compra(s) del ejercicio sin la marca Deducible (ni «Declaración de IVA» ni «Gasto personal»). No se incluyeron en ningún bloque: revíselas en la pestaña Detalle de documentos."];
        }

        $ventasNetoBase = round($ventas['facturas']['base'] - $ventas['notas_credito']['base'] + $ventas['notas_debito']['base'], 2);
        $ventasNetoTotal = round($ventas['facturas']['total'] - $ventas['notas_credito']['total'] + $ventas['notas_debito']['total'], 2);

        return [
            'ventas'          => $ventas + ['neto_base' => $ventasNetoBase, 'neto_total' => $ventasNetoTotal],
            'negocio'         => $negocio,
            'personal'        => $personal,
            'personal_rubros' => $personalRubros,
            'sin_marca'       => $sinMarca,
            'retenciones'     => $retenciones,
        ];
    }

    /**
     * Casilleros del formulario tomados de la contabilidad de todos los establecimientos del
     * grupo: agrupa las cuentas de último nivel (hojas) por `codigo_sri`, igual que el botón
     * "Renta SRI" de Estados Financieros, y lista aparte las cuentas con saldo que no tienen
     * casillero asignado.
     */
    private function casillerosContables(int $idEmpresa, array $grupo, string $desde, string $hasta, array &$avisos): array
    {
        $ids = $grupo['ids'];
        $etiquetas = $grupo['etiquetas'];
        // Cuentas compartidas entre establecimientos (Consolidación de grupos, modo UNICA):
        // cuentan una sola vez, desde el establecimiento fuente.
        $mapa = [];
        if ($grupo['consolidado']) {
            $ruc = (string) ($this->empresaRepo->getRucPorId($idEmpresa) ?? '');
            $mapa = $ruc !== '' ? (new ConsolidacionGruposRepository())->getMapaCuentaGrupo($ruc) : [];
        }

        $casilleros = [];
        $sinCasillero = [];
        $excluidas = 0;
        $tot = ['ingresos' => 0.0, 'costos' => 0.0, 'gastos' => 0.0, 'activos' => 0.0, 'pasivos' => 0.0, 'patrimonio' => 0.0];

        $agrupar = function (array $items, string $seccion, string $claveTotal, int $idEmp) use (&$casilleros, &$sinCasillero, &$tot, &$excluidas, $mapa, $etiquetas, $grupo): void {
            $codigos = array_map(fn($i) => (string) $i['codigo'], $items);
            foreach ($items as $it) {
                $codigo = (string) $it['codigo'];
                // Solo cuentas hoja: si otra cuenta cuelga de esta, el saldo ya está en sus hijas.
                $esHoja = true;
                foreach ($codigos as $c) {
                    if ($c !== $codigo && str_starts_with($c, $codigo . '.')) {
                        $esHoja = false;
                        break;
                    }
                }
                if (!$esHoja) {
                    continue;
                }
                $valor = round((float) ($it['saldo_final'] ?? 0), 2);
                if ($valor == 0.0) {
                    continue;
                }
                $g = $mapa[(int) ($it['id_cuenta'] ?? 0)] ?? null;
                if ($g && ($g['modo'] ?? 'SUMA') === 'UNICA' && (int) $g['id_empresa_fuente'] !== $idEmp) {
                    $excluidas++;
                    continue;
                }
                $tot[$claveTotal] = round($tot[$claveTotal] + $valor, 2);
                $cas = trim((string) ($it['codigo_sri'] ?? ''));
                $cuenta = ['codigo' => $codigo, 'nombre' => (string) $it['nombre'], 'valor' => $valor, 'seccion' => $seccion,
                           'establecimiento' => $grupo['consolidado'] ? ($etiquetas[$idEmp] ?? '') : ''];
                if ($cas === '') {
                    $sinCasillero[] = $cuenta;
                    continue;
                }
                if (!isset($casilleros[$cas])) {
                    $casilleros[$cas] = ['casillero' => $cas, 'seccion' => $seccion, 'valor' => 0.0, 'cuentas' => []];
                }
                $casilleros[$cas]['valor'] = round($casilleros[$cas]['valor'] + $valor, 2);
                $casilleros[$cas]['cuentas'][] = $cuenta;
            }
        };

        foreach ($ids as $idEmp) {
            $er = $this->ef()->getEstadoResultados($idEmp, $desde, $hasta, null, null, 5);
            $esf = $this->ef()->getEstadoSituacionFinanciera($idEmp, $desde, $hasta, null, null, 5);
            $agrupar($esf['activos'] ?? [], 'Activo', 'activos', $idEmp);
            $agrupar($esf['pasivos'] ?? [], 'Pasivo', 'pasivos', $idEmp);
            $agrupar($esf['patrimonio'] ?? [], 'Patrimonio', 'patrimonio', $idEmp);
            $agrupar($er['ingresos'] ?? [], 'Ingresos', 'ingresos', $idEmp);
            $agrupar($er['costos'] ?? [], 'Costos', 'costos', $idEmp);
            $agrupar($er['gastos'] ?? [], 'Gastos', 'gastos', $idEmp);
        }
        ksort($casilleros, SORT_NATURAL);

        $disponible = $tot['ingresos'] != 0.0 || $tot['costos'] != 0.0 || $tot['gastos'] != 0.0
            || $tot['activos'] != 0.0 || $tot['pasivos'] != 0.0;

        if ($disponible && $sinCasillero) {
            $avisos[] = ['tipo' => 'warning', 'texto' => count($sinCasillero) . ' cuenta(s) con saldo no tienen casillero SRI en el Plan de cuentas: sus valores no entran en ningún casillero del formulario.'];
        }
        if ($excluidas > 0) {
            $avisos[] = ['tipo' => 'info', 'texto' => "{$excluidas} cuenta(s) compartidas entre establecimientos (modo UNICA en Consolidación de grupos) se contaron una sola vez, desde su establecimiento fuente."];
        }
        if (!$disponible) {
            $avisos[] = ['tipo' => 'info', 'texto' => 'No hay asientos contabilizados en el ejercicio; los casilleros contables están vacíos.'];
        }

        return [
            'disponible'    => $disponible,
            'totales'       => $tot + ['utilidad' => round($tot['ingresos'] - $tot['costos'] - $tot['gastos'], 2)],
            'casilleros'    => array_values($casilleros),
            'sin_casillero' => $sinCasillero,
        ];
    }

    /** Tabla progresiva y parámetros de gastos personales del año (los de /config/impuesto-renta-tramos). */
    private function parametrosPersonaNatural(int $anio, array $ajustes, array &$avisos): array
    {
        $tramos = $this->ir()->getTramosAnio($anio);
        $p = $this->ir()->getParametrosAnio($anio);
        $tope = $this->ir()->getTopeGastoPersonal($anio, $ajustes['cargas_familiares'], $ajustes['caso_especial']);

        if (!$tramos) {
            $avisos[] = ['tipo' => 'warning', 'texto' => "No está cargada la tabla de Impuesto a la Renta del año {$anio} (Configuración → Impuesto a la renta: tramos). El impuesto causado sale en 0."];
        }
        if (empty($p['configurado'])) {
            $avisos[] = ['tipo' => 'warning', 'texto' => "No está configurada la canasta básica del año {$anio}: la rebaja por gastos personales se calcula sin tope."];
        }

        return [
            'tramos'            => $tramos,
            'canasta_basica'    => (float) $p['canasta_basica'],
            'porcentaje_rebaja' => (float) $p['porcentaje_rebaja'],
            'factor_canastas'   => ImpuestoRentaEmpleadoService::factorCanastas($p['factores'], $ajustes['cargas_familiares'], $ajustes['caso_especial']),
            'tope_gastos'       => $tope,
        ];
    }

    /** Línea de la liquidación. $signo: '+', '-', '=' o '' (informativa). */
    private function linea(string $clave, string $concepto, float $valor, string $signo = '', array $extra = []): array
    {
        return $extra + [
            'clave'     => $clave,
            'concepto'  => $concepto,
            'valor'     => round($valor, 2),
            'signo'     => $signo,
            'casillero' => self::CAS[$clave] ?? '',
            'nivel'     => 1,
            'editable'  => false,
            'seccion'   => false,
            'nota'      => '',
        ];
    }

    private function seccion(string $titulo): array
    {
        return $this->linea('', $titulo, 0.0, '', ['seccion' => true, 'nivel' => 0, 'casillero' => '']);
    }

    /** Liquidación del impuesto de una persona natural (formulario 102). */
    private function liquidarPersonaNatural(array $ctx, array $doc, ?array $contab, array $aj, array $param, array &$avisos): array
    {
        $L = [];
        $usaContab = $aj['fuente'] === 'contabilidad' && !empty($contab['disponible']);

        // ── Ingresos ──
        $L[] = $this->seccion($usaContab ? 'INGRESOS (según contabilidad)' : 'INGRESOS (según documentos)');
        if ($usaContab) {
            $ingresosActividad = $contab['totales']['ingresos'];
            $L[] = $this->linea('ingresos_contables', 'Ingresos del ejercicio (cuentas de ingreso)', $ingresosActividad, '+', ['casillero' => self::CAS['total_ingresos']]);
        } else {
            $v = $doc['ventas'];
            $L[] = $this->linea('ventas_facturas', 'Ventas (facturas emitidas)', $v['facturas']['base'], '+', ['nota' => $v['facturas']['cantidad'] . ' doc.']);
            $L[] = $this->linea('ventas_nc', 'Notas de crédito emitidas', $v['notas_credito']['base'], '-', ['nota' => $v['notas_credito']['cantidad'] . ' doc.']);
            $L[] = $this->linea('ventas_nd', 'Notas de débito emitidas', $v['notas_debito']['base'], '+', ['nota' => $v['notas_debito']['cantidad'] . ' doc.']);
            $ingresosActividad = $v['neto_base'];
            $L[] = $this->linea('ingresos_actividad', 'Ingresos de la actividad empresarial', $ingresosActividad, '=');
        }
        $L[] = $this->linea('otros_ingresos', 'Otros ingresos gravados (no registrados en el sistema)', $aj['otros_ingresos'], '+', ['editable' => true]);
        $totalIngresos = round($ingresosActividad + $aj['otros_ingresos'], 2);
        $L[] = $this->linea('total_ingresos', 'Total ingresos gravados', $totalIngresos, '=', ['nivel' => 0]);

        // ── Costos y gastos ──
        $L[] = $this->seccion($usaContab ? 'COSTOS Y GASTOS DEDUCIBLES (según contabilidad)' : 'COSTOS Y GASTOS DEDUCIBLES (según documentos)');
        if ($usaContab) {
            $gastosActividad = round($contab['totales']['costos'] + $contab['totales']['gastos'], 2);
            $L[] = $this->linea('costos_contables', 'Costos del ejercicio (cuentas de costo)', $contab['totales']['costos'], '+');
            $L[] = $this->linea('gastos_contables', 'Gastos del ejercicio (cuentas de gasto)', $contab['totales']['gastos'], '+');
            $L[] = $this->linea('gastos_no_deducibles', 'Gastos no deducibles', $aj['gastos_no_deducibles'], '-', ['editable' => true, 'casillero' => self::CAS['gastos_no_deducibles']]);
            $gastosActividad = round($gastosActividad - $aj['gastos_no_deducibles'], 2);
        } else {
            $n = $doc['negocio'];
            $L[] = $this->linea('compras_facturas', 'Compras del negocio (facturas marcadas «Declaración de IVA»)', $n['facturas']['base'] + $n['otros']['base'], '+', ['nota' => ($n['facturas']['cantidad'] + $n['otros']['cantidad']) . ' doc.']);
            $L[] = $this->linea('compras_liquidaciones', 'Liquidaciones de compra', $n['liquidaciones']['base'], '+', ['nota' => $n['liquidaciones']['cantidad'] . ' doc.']);
            $L[] = $this->linea('compras_nc', 'Notas de crédito recibidas', $n['notas_credito']['base'], '-', ['nota' => $n['notas_credito']['cantidad'] . ' doc.']);
            $L[] = $this->linea('compras_nd', 'Notas de débito recibidas', $n['notas_debito']['base'], '+', ['nota' => $n['notas_debito']['cantidad'] . ' doc.']);
            $gastosActividad = $n['neto_base'];
            $L[] = $this->linea('gastos_actividad', 'Costos y gastos de la actividad empresarial', $gastosActividad, '=');
        }
        $L[] = $this->linea('otras_deducciones', 'Otras deducciones (no registradas en el sistema)', $aj['otras_deducciones'], '+', ['editable' => true]);
        $totalGastos = round($gastosActividad + $aj['otras_deducciones'], 2);
        $L[] = $this->linea('total_gastos', 'Total costos y gastos deducibles', $totalGastos, '=', ['nivel' => 0]);

        // ── Base imponible ──
        $L[] = $this->seccion('BASE IMPONIBLE');
        $utilidad = round($totalIngresos - $totalGastos, 2);
        if ($ctx['tipo'] === self::TIPO_PNOC && $usaContab) {
            $participacion = $utilidad > 0 ? round($utilidad * $aj['participacion_trabajadores_pct'] / 100, 2) : 0.0;
            $L[] = $this->linea('utilidad_ejercicio', 'Utilidad del ejercicio (ingresos - costos y gastos)', max(0.0, $utilidad), '=');
            $L[] = $this->linea('participacion', 'Participación a trabajadores (' . $this->pct($aj['participacion_trabajadores_pct']) . ')', $participacion, '-', ['editable' => true]);
            $utilidad = round($utilidad - $participacion, 2);
        }
        $perdida = $utilidad < 0 ? abs($utilidad) : 0.0;
        $base = max(0.0, $utilidad);
        $L[] = $this->linea('base_imponible', 'Base imponible gravada', $base, '=', ['nivel' => 0]);
        if ($perdida > 0) {
            $L[] = $this->linea('perdida', 'Pérdida del ejercicio (los gastos superan a los ingresos)', $perdida, '', ['nota' => 'informativo']);
        }

        // ── Impuesto ──
        $L[] = $this->seccion('IMPUESTO A LA RENTA');
        $causado = ImpuestoRentaEmpleadoService::impuestoCausado($base, $param['tramos']);
        $this->avisarFueraDeTabla($base, $param['tramos'], $avisos);
        $L[] = $this->linea('impuesto_causado', 'Impuesto a la renta causado (tabla progresiva ' . $this->tablaAnio($param) . ')', $causado, '=', ['nivel' => 0]);

        $gastosPersonales = max(0.0, $doc['personal']['neto_total']);
        $tope = (float) $param['tope_gastos'];
        $baseRebaja = $tope > 0 ? min($gastosPersonales, $tope) : $gastosPersonales;
        $rebaja = round($baseRebaja * $param['porcentaje_rebaja'] / 100, 2);
        $rebaja = min($rebaja, $causado);
        $notaTope = $tope > 0
            ? 'tope ' . number_format($tope, 2) . ' (' . number_format($param['canasta_basica'], 2) . ' x ' . $param['factor_canastas'] . ' canastas)'
            : 'sin tope configurado';
        $L[] = $this->linea('gastos_personales', 'Gastos personales del ejercicio (compras marcadas «Gasto personal», con IVA)', $gastosPersonales, '', ['nota' => ($doc['personal']['facturas']['cantidad'] + $doc['personal']['otros']['cantidad']) . ' doc.; ' . $notaTope]);
        foreach ($doc['personal_rubros'] as $cod => $r) {
            if ($r['cantidad'] === 0 && $r['total'] == 0.0) {
                continue;
            }
            $L[] = $this->linea('gp_' . $cod, '      · ' . $r['nombre'], $r['total'], '', ['nota' => $r['cantidad'] . ' doc.', 'nivel' => 2]);
        }
        $L[] = $this->linea('rebaja_gastos_personales', 'Rebaja por gastos personales (' . $this->pct($param['porcentaje_rebaja']) . ' del menor entre gastos y tope)', $rebaja, '-');
        $impuestoNeto = round(max(0.0, $causado - $rebaja), 2);
        $L[] = $this->linea('impuesto_neto', 'Impuesto causado después de la rebaja', $impuestoNeto, '=');

        $L[] = $this->linea('retenciones', 'Retenciones en la fuente que le realizaron en el ejercicio', $doc['retenciones']['total'], '-', ['nota' => $doc['retenciones']['cantidad'] . ' comprobante(s)']);
        $L[] = $this->linea('anticipo_pagado', 'Anticipo de impuesto a la renta pagado', $aj['anticipo_pagado'], '-', ['editable' => true]);
        $L[] = $this->linea('credito_anterior', 'Crédito tributario de años anteriores', $aj['credito_anios_anteriores'], '-', ['editable' => true]);
        $L[] = $this->linea('otros_creditos', 'Otros créditos y rebajas', $aj['otros_creditos'], '-', ['editable' => true]);

        $saldo = round($impuestoNeto - $doc['retenciones']['total'] - $aj['anticipo_pagado'] - $aj['credito_anios_anteriores'] - $aj['otros_creditos'], 2);
        $pagar = max(0.0, $saldo);
        $favor = $saldo < 0 ? abs($saldo) : 0.0;
        $L[] = $this->linea('impuesto_pagar', 'IMPUESTO A LA RENTA A PAGAR', $pagar, '=', ['nivel' => 0, 'casillero' => '']);
        $L[] = $this->linea('saldo_favor', 'SALDO A FAVOR DEL CONTRIBUYENTE', $favor, '=', ['nivel' => 0, 'casillero' => '']);

        // En el 102 de no obligados el SRI reorganiza los casilleros cada año: no se muestran números.
        if ($ctx['tipo'] === self::TIPO_PN) {
            foreach ($L as &$l) {
                $l['casillero'] = '';
            }
            unset($l);
        }

        return [
            'lineas'  => $L,
            'resumen' => [
                'total_ingresos'   => $totalIngresos,
                'total_gastos'     => $totalGastos,
                'base_imponible'   => $base,
                'impuesto_causado' => $causado,
                'rebaja'           => $rebaja,
                'retenciones'      => $doc['retenciones']['total'],
                'impuesto_pagar'   => $pagar,
                'saldo_favor'      => $favor,
            ],
        ];
    }

    /** Conciliación tributaria de una sociedad (formulario 101). */
    private function liquidarSociedad(?array $contab, array $doc, array $aj, array &$avisos): array
    {
        $t = $contab['totales'] ?? ['ingresos' => 0.0, 'costos' => 0.0, 'gastos' => 0.0, 'utilidad' => 0.0];
        $L = [];

        $L[] = $this->seccion('RESULTADO CONTABLE DEL EJERCICIO');
        $L[] = $this->linea('total_ingresos', 'Total ingresos', $t['ingresos'], '+');
        $L[] = $this->linea('total_costos_gastos', 'Total costos y gastos', round($t['costos'] + $t['gastos'], 2), '-');
        $utilidad = round($t['utilidad'], 2);
        $L[] = $this->linea('utilidad_ejercicio', 'Utilidad del ejercicio', max(0.0, $utilidad), '=', ['nivel' => 0]);
        $L[] = $this->linea('perdida_ejercicio', 'Pérdida del ejercicio', $utilidad < 0 ? abs($utilidad) : 0.0, '=', ['nivel' => 0]);

        $L[] = $this->seccion('CONCILIACIÓN TRIBUTARIA');
        $participacion = $utilidad > 0 ? round($utilidad * $aj['participacion_trabajadores_pct'] / 100, 2) : 0.0;
        $L[] = $this->linea('participacion', 'Participación a trabajadores (' . $this->pct($aj['participacion_trabajadores_pct']) . ')', $participacion, '-', ['editable' => true]);
        $L[] = $this->linea('rentas_exentas', 'Otras rentas exentas e ingresos no objeto de impuesto a la renta', $aj['rentas_exentas'], '-', ['editable' => true]);
        $L[] = $this->linea('gastos_no_deducibles', 'Gastos no deducibles locales', $aj['gastos_no_deducibles'], '+', ['editable' => true]);
        $L[] = $this->linea('deducciones_adicionales', 'Deducciones adicionales', $aj['deducciones_adicionales'], '-', ['editable' => true]);
        $L[] = $this->linea('amortizacion_perdidas', 'Amortización de pérdidas tributarias de años anteriores', $aj['amortizacion_perdidas'], '-', ['editable' => true]);

        $gravable = round($utilidad - $participacion - $aj['rentas_exentas'] + $aj['gastos_no_deducibles'] - $aj['deducciones_adicionales'] - $aj['amortizacion_perdidas'], 2);
        $L[] = $this->linea('utilidad_gravable', 'Utilidad gravable', max(0.0, $gravable), '=', ['nivel' => 0]);
        $L[] = $this->linea('perdida_amortizable', 'Pérdida sujeta a amortización en períodos siguientes', $gravable < 0 ? abs($gravable) : 0.0, '=', ['nivel' => 0]);

        $L[] = $this->seccion('IMPUESTO A LA RENTA');
        $causado = $gravable > 0 ? round($gravable * $aj['tarifa_pct'] / 100, 2) : 0.0;
        $L[] = $this->linea('impuesto_causado', 'Total impuesto causado (tarifa ' . $this->pct($aj['tarifa_pct']) . ')', $causado, '=', ['nivel' => 0, 'editable' => true]);
        $L[] = $this->linea('anticipo_pagado', 'Anticipo de impuesto a la renta pagado', $aj['anticipo_pagado'], '-', ['editable' => true]);
        $L[] = $this->linea('retenciones', 'Retenciones en la fuente que le realizaron en el ejercicio fiscal', $doc['retenciones']['total'], '-', ['nota' => $doc['retenciones']['cantidad'] . ' comprobante(s)']);
        $L[] = $this->linea('credito_anterior', 'Crédito tributario de años anteriores', $aj['credito_anios_anteriores'], '-', ['editable' => true]);
        $L[] = $this->linea('otros_creditos', 'Otros créditos, exoneraciones y rebajas', $aj['otros_creditos'], '-', ['editable' => true]);

        $saldo = round($causado - $aj['anticipo_pagado'] - $doc['retenciones']['total'] - $aj['credito_anios_anteriores'] - $aj['otros_creditos'], 2);
        $pagar = max(0.0, $saldo);
        $favor = $saldo < 0 ? abs($saldo) : 0.0;
        $L[] = $this->linea('subtotal_pagar', 'Subtotal impuesto a pagar', $pagar, '=');
        $L[] = $this->linea('subtotal_favor', 'Subtotal saldo a favor', $favor, '=');
        $L[] = $this->linea('impuesto_pagar', 'IMPUESTO A LA RENTA A PAGAR', $pagar, '=', ['nivel' => 0]);
        $L[] = $this->linea('saldo_favor', 'SALDO A FAVOR DEL CONTRIBUYENTE', $favor, '=', ['nivel' => 0]);

        if (empty($contab['disponible'])) {
            $avisos[] = ['tipo' => 'warning', 'texto' => 'La conciliación tributaria de una sociedad parte de la contabilidad y el ejercicio no tiene asientos contabilizados.'];
        }

        return [
            'lineas'  => $L,
            'resumen' => [
                'total_ingresos'   => $t['ingresos'],
                'total_gastos'     => round($t['costos'] + $t['gastos'], 2),
                'base_imponible'   => max(0.0, $gravable),
                'impuesto_causado' => $causado,
                'rebaja'           => 0.0,
                'retenciones'      => $doc['retenciones']['total'],
                'impuesto_pagar'   => $pagar,
                'saldo_favor'      => $favor,
            ],
        ];
    }

    private function avisarFueraDeTabla(float $base, array $tramos, array &$avisos): void
    {
        if (!$tramos || $base <= 0) {
            return;
        }
        $abierto = false;
        $maximo = 0.0;
        foreach ($tramos as $t) {
            if ($t['exceso_hasta'] === null) {
                $abierto = true;
            } else {
                $maximo = max($maximo, (float) $t['exceso_hasta']);
            }
        }
        if (!$abierto && $base >= $maximo) {
            $avisos[] = ['tipo' => 'warning', 'texto' => 'La base imponible (' . number_format($base, 2) . ') supera el último tramo cargado de la tabla (' . number_format($maximo, 2) . '): falta cargar los tramos superiores, el impuesto causado quedó en 0.'];
        }
    }

    private function tablaAnio(array $param): string
    {
        return $param['tramos'] ? count($param['tramos']) . ' tramos' : 'sin cargar';
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%';
    }

    // ───────────────────────────── Detalle ─────────────────────────────

    /** Documentos de una fuente (todos los establecimientos del grupo), para la pestaña de detalle. */
    public function getDetalle(int $idEmpresa, $anio, string $fuente, int $idUsuario = 0): array
    {
        $anio = $this->rules->validarAnio($anio);
        $ctx = $this->getContexto($idEmpresa, $idUsuario);
        $out = [];
        foreach ($ctx['grupo']['ids'] as $idEmp) {
            $amb = (string) ($ctx['ambientes'][$idEmp] ?? $ctx['ambiente']);
            foreach ($this->repo->getDetalle($idEmp, "{$anio}-01-01", "{$anio}-12-31", $amb, $fuente) as $d) {
                $d['establecimiento'] = $ctx['grupo']['consolidado'] ? ($ctx['grupo']['etiquetas'][$idEmp] ?? '') : '';
                $out[] = $d;
            }
        }
        return $out;
    }

    // ───────────────────────────── Exportaciones ─────────────────────────────

    /**
     * XML con el formato que acepta el portal del SRI para cargar la declaración
     * (<detallesDeclaracion><detalle concepto="casillero">valor</detalle>…), el mismo que
     * genera el botón "Renta SRI" de Estados Financieros, más los casilleros de la
     * conciliación tributaria. Solo para formularios con casilleros (101 y 102 de obligados).
     */
    public function generarXml(array $calc): string
    {
        $valores = [];
        foreach ($calc['contabilidad']['casilleros'] ?? [] as $c) {
            $valores[$c['casillero']] = round(($valores[$c['casillero']] ?? 0) + $c['valor'], 2);
        }
        foreach ($calc['liquidacion']['lineas'] as $l) {
            if (!empty($l['casillero']) && empty($l['seccion'])) {
                $valores[$l['casillero']] = $l['valor'];
            }
        }
        ksort($valores, SORT_NATURAL);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<detallesDeclaracion>\n";
        foreach ($valores as $cas => $v) {
            $xml .= '<detalle concepto="' . htmlspecialchars((string) $cas, ENT_XML1) . '">' . number_format((float) $v, 2, '.', '') . "</detalle>\n";
        }
        $ruc = (string) ($calc['empresa']['ruc'] ?? '');
        if ($ruc !== '') {
            $xml .= '<detalle concepto="80">' . htmlspecialchars($ruc, ENT_XML1) . "</detalle>\n";
        }
        return $xml . "</detallesDeclaracion>\n";
    }

    public function nombreArchivo(array $calc, string $ext): string
    {
        $ruc = preg_replace('/[^0-9A-Za-z]/', '', (string) ($calc['empresa']['ruc'] ?? '')) ?: 'sin_ruc';
        return "declaracion_renta_{$calc['formulario']}_{$ruc}_{$calc['anio']}.{$ext}";
    }

    /**
     * PDF de apoyo (Html2Pdf): cabecera, resumen de documentos, liquidación y casilleros
     * contables. Devuelve el binario; el controlador decide las cabeceras.
     */
    public function generarPdf(array $calc): string
    {
        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $html = $this->htmlPdf($calc);
        $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es', true, 'UTF-8', [12, 10, 12, 10]);
        $pdf->writeHTML($html);
        return (string) $pdf->output($this->nombreArchivo($calc, 'pdf'), 'S');
    }

    private function htmlPdf(array $calc): string
    {
        $e = (fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'));
        $m = (fn($v) => number_format((float) $v, 2));
        $emp = $calc['empresa'];
        $doc = $calc['documentos'];

        ob_start(); ?>
        <style>
            * { font-family: Arial, sans-serif; }
            h3 { font-size: 11pt; margin: 0; }
            h4 { font-size: 9pt; margin: 8px 0 3px 0; color: #0d6efd; }
            p { font-size: 8pt; margin: 2px 0; }
            table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
            th { background: #e9ecef; border: 1px solid #999; padding: 3px; text-align: left; }
            td { border: 1px solid #bbb; padding: 2px 3px; }
            .r { text-align: right; } .c { text-align: center; }
            .sec td { background: #dfe7f3; font-weight: bold; }
            .tot td { font-weight: bold; background: #f5f5f5; }
            .muted { color: #666; font-size: 6.5pt; }
        </style>
        <div style="text-align:center;">
            <h3><?= $e($emp['nombre_comercial'] ?: $emp['nombre']) ?></h3>
            <p>RUC: <?= $e($emp['ruc']) ?> - <?= $e($calc['tipo_nombre']) ?></p>
            <h3>DECLARACIÓN DE IMPUESTO A LA RENTA - FORMULARIO <?= $e($calc['formulario']) ?> - EJERCICIO <?= $e($calc['anio']) ?></h3>
            <?php if (!empty($calc['grupo']['consolidado'])): ?>
                <p>Consolidado por RUC: <?= $e(implode(' | ', $calc['grupo']['etiquetas'])) ?></p>
            <?php endif; ?>
        </div>

        <h4>Resumen de documentos del ejercicio</h4>
        <table>
            <tr><th>Bloque</th><th class="c">Documentos</th><th class="r">Base (sin IVA)</th><th class="r">Total (con IVA)</th></tr>
            <?php foreach ($this->filasResumenDocumentos($doc) as $f): ?>
                <tr class="<?= $f['total'] ? 'tot' : '' ?>">
                    <td><?= $e($f['concepto']) ?></td>
                    <td class="c"><?= $f['cantidad'] === null ? '' : $f['cantidad'] ?></td>
                    <td class="r"><?= $m($f['base']) ?></td>
                    <td class="r"><?= $m($f['monto']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h4>Liquidación del impuesto</h4>
        <table>
            <tr><th>Concepto</th><th class="c">Cas.</th><th class="c">+/-</th><th class="r">Valor</th></tr>
            <?php foreach ($calc['liquidacion']['lineas'] as $l): ?>
                <?php if ($l['seccion']): ?>
                    <tr class="sec"><td colspan="4"><?= $e($l['concepto']) ?></td></tr>
                <?php else: ?>
                    <tr class="<?= $l['nivel'] === 0 ? 'tot' : '' ?>">
                        <td><?= $e($l['concepto']) ?><?= $l['nota'] !== '' ? ' <span class="muted">(' . $e($l['nota']) . ')</span>' : '' ?></td>
                        <td class="c"><?= $e($l['casillero']) ?></td>
                        <td class="c"><?= $e($l['signo']) ?></td>
                        <td class="r"><?= $m($l['valor']) ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>

        <?php if (!empty($calc['contabilidad']['casilleros'])): ?>
            <h4>Casilleros del formulario según la contabilidad (plan de cuentas → código SRI)</h4>
            <table>
                <tr><th class="c">Casillero</th><th>Sección</th><th>Cuentas</th><th class="r">Valor</th></tr>
                <?php foreach ($calc['contabilidad']['casilleros'] as $c): ?>
                    <tr>
                        <td class="c"><?= $e($c['casillero']) ?></td>
                        <td><?= $e($c['seccion']) ?></td>
                        <td><?= $e(implode('; ', array_map(fn($q) => ($q['establecimiento'] !== '' ? '[' . $q['establecimiento'] . '] ' : '') . $q['codigo'] . ' ' . $q['nombre'], $c['cuentas']))) ?></td>
                        <td class="r"><?= $m($c['valor']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (!empty($calc['contabilidad']['sin_casillero'])): ?>
            <h4>Cuentas con saldo sin casillero SRI</h4>
            <table>
                <tr><th>Cuenta</th><th>Sección</th><th class="r">Saldo</th></tr>
                <?php foreach ($calc['contabilidad']['sin_casillero'] as $q): ?>
                    <tr><td><?= $e(($q['establecimiento'] !== '' ? '[' . $q['establecimiento'] . '] ' : '') . $q['codigo'] . ' ' . $q['nombre']) ?></td><td><?= $e($q['seccion']) ?></td><td class="r"><?= $m($q['valor']) ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (!empty($calc['avisos'])): ?>
            <h4>Avisos</h4>
            <?php foreach ($calc['avisos'] as $a): ?><p>• <?= $e($a['texto']) ?></p><?php endforeach; ?>
        <?php endif; ?>

        <p class="muted" style="margin-top:8px;">Generado el <?= date('d-m-Y H:i:s') ?>. Documento de apoyo para preparar la declaración; no reemplaza la declaración presentada en el portal del SRI.</p>
        <?php
        return (string) ob_get_clean();
    }

    /** Filas del cuadro "Resumen de documentos" (compartidas por pantalla, PDF y Excel). */
    public function filasResumenDocumentos(array $doc): array
    {
        $v = $doc['ventas'];
        $n = $doc['negocio'];
        $p = $doc['personal'];
        $o = $doc['sin_marca'];
        $r = $doc['retenciones'];
        $fila = fn(string $c, array $b, bool $tot = false) => ['concepto' => $c, 'cantidad' => $b['cantidad'], 'base' => $b['base'], 'monto' => $b['total'], 'total' => $tot];
        $neto = fn(string $c, array $b) => ['concepto' => $c, 'cantidad' => null, 'base' => $b['neto_base'], 'monto' => $b['neto_total'], 'total' => true];

        $filas = [
            $fila('Ventas: facturas emitidas', $v['facturas']),
            $fila('Ventas: (-) notas de crédito emitidas', $v['notas_credito']),
            $fila('Ventas: (+) notas de débito emitidas', $v['notas_debito']),
            $neto('INGRESOS NETOS', $v),
            $fila('Compras del negocio: facturas (Deducible = Declaración de IVA)', ['cantidad' => $n['facturas']['cantidad'] + $n['otros']['cantidad'], 'base' => $n['facturas']['base'] + $n['otros']['base'], 'total' => $n['facturas']['total'] + $n['otros']['total']]),
            $fila('Compras del negocio: liquidaciones de compra', $n['liquidaciones']),
            $fila('Compras del negocio: (-) notas de crédito recibidas', $n['notas_credito']),
            $fila('Compras del negocio: (+) notas de débito recibidas', $n['notas_debito']),
            $neto('COSTOS Y GASTOS DEL NEGOCIO NETOS', $n),
            $fila('Gastos personales: facturas (Deducible = Gasto personal)', ['cantidad' => $p['facturas']['cantidad'] + $p['otros']['cantidad'], 'base' => $p['facturas']['base'] + $p['otros']['base'], 'total' => $p['facturas']['total'] + $p['otros']['total']]),
            $fila('Gastos personales: (-) notas de crédito recibidas', $p['notas_credito']),
            $fila('Gastos personales: (+) notas de débito recibidas', $p['notas_debito']),
            $neto('GASTOS PERSONALES NETOS', $p),
        ];
        foreach ($doc['personal_rubros'] ?? [] as $cod => $r) {
            if ($r['cantidad'] === 0 && $r['total'] == 0.0) {
                continue;
            }
            $filas[] = ['concepto' => '      · Rubro ' . $r['nombre'], 'cantidad' => $r['cantidad'], 'base' => $r['base'], 'monto' => $r['total'], 'total' => false, 'rubro' => $cod];
        }
        $cantO = $o['facturas']['cantidad'] + $o['notas_credito']['cantidad'] + $o['notas_debito']['cantidad'] + $o['otros']['cantidad'];
        if ($cantO > 0) {
            $filas[] = ['concepto' => 'Compras sin marca Deducible (no incluidas)', 'cantidad' => $cantO, 'base' => $o['neto_base'], 'monto' => $o['neto_total'], 'total' => false];
        }
        $filas[] = ['concepto' => 'Retenciones de renta que le hicieron (crédito tributario)', 'cantidad' => $r['cantidad'], 'base' => $r['base'], 'monto' => $r['total'], 'total' => true];
        return $filas;
    }

    /**
     * Excel (PhpSpreadsheet): Resumen, Liquidación, Casilleros y Detalle de documentos.
     * Devuelve el binario del .xlsx; el controlador decide las cabeceras.
     */
    public function generarExcel(array $calc, int $idEmpresa, int $idUsuario = 0): string
    {
        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $emp = $calc['empresa'];
        $titulo = 'Declaración de Impuesto a la Renta - Formulario ' . $calc['formulario'] . ' - Ejercicio ' . $calc['anio'];
        $header = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6EFD']],
        ];
        $fmt = '#,##0.00';

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Hoja 1: resumen de documentos
        $s = $ss->getActiveSheet();
        $s->setTitle('Resumen');
        $s->setCellValue('A1', mb_strtoupper((string) ($emp['nombre_comercial'] ?: $emp['nombre']), 'UTF-8'));
        $s->setCellValue('A2', $titulo . ' - RUC ' . $emp['ruc'] . ' - ' . $calc['tipo_nombre']);
        if (!empty($calc['grupo']['consolidado'])) {
            $s->setCellValue('A3', 'Consolidado por RUC: ' . implode(' | ', $calc['grupo']['etiquetas']));
        }
        $s->getStyle('A1:A2')->getFont()->setBold(true);
        $s->fromArray(['Bloque', 'Documentos', 'Base (sin IVA)', 'Total (con IVA)'], null, 'A4');
        $s->getStyle('A4:D4')->applyFromArray($header);
        $row = 5;
        foreach ($this->filasResumenDocumentos($calc['documentos']) as $f) {
            $s->setCellValue("A{$row}", $f['concepto']);
            if ($f['cantidad'] !== null) {
                $s->setCellValue("B{$row}", $f['cantidad']);
            }
            $s->setCellValue("C{$row}", $f['base']);
            $s->setCellValue("D{$row}", $f['monto']);
            $s->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode($fmt);
            if ($f['total']) {
                $s->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
            }
            $row++;
        }
        $s->getColumnDimension('A')->setWidth(62);
        foreach (['B', 'C', 'D'] as $c) {
            $s->getColumnDimension($c)->setWidth(16);
        }

        // Hoja 2: liquidación
        $s = $ss->createSheet();
        $s->setTitle('Liquidación');
        $s->fromArray(['Concepto', 'Casillero', '+/-', 'Valor', 'Nota'], null, 'A1');
        $s->getStyle('A1:E1')->applyFromArray($header);
        $row = 2;
        foreach ($calc['liquidacion']['lineas'] as $l) {
            $s->setCellValue("A{$row}", $l['concepto']);
            if ($l['seccion']) {
                $s->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                $s->getStyle("A{$row}:E{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DFE7F3');
            } else {
                $s->setCellValueExplicit("B{$row}", (string) $l['casillero'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->setCellValue("C{$row}", $l['signo']);
                $s->setCellValue("D{$row}", $l['valor']);
                $s->getStyle("D{$row}")->getNumberFormat()->setFormatCode($fmt);
                $s->setCellValue("E{$row}", $l['nota']);
                if ($l['nivel'] === 0) {
                    $s->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                }
            }
            $row++;
        }
        $s->getColumnDimension('A')->setWidth(70);
        $s->getColumnDimension('D')->setWidth(16);
        $s->getColumnDimension('E')->setWidth(40);

        // Hoja 3: casilleros contables (solo si hay contabilidad)
        if (!empty($calc['contabilidad'])) {
            $s = $ss->createSheet();
            $s->setTitle('Casilleros');
            $s->fromArray(['Casillero', 'Sección', 'Establecimiento', 'Cuenta', 'Nombre', 'Valor cuenta', 'Valor casillero'], null, 'A1');
            $s->getStyle('A1:G1')->applyFromArray($header);
            $row = 2;
            foreach ($calc['contabilidad']['casilleros'] as $c) {
                foreach ($c['cuentas'] as $i => $q) {
                    $s->setCellValueExplicit("A{$row}", (string) $c['casillero'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $s->setCellValue("B{$row}", $c['seccion']);
                    $s->setCellValue("C{$row}", $q['establecimiento']);
                    $s->setCellValueExplicit("D{$row}", (string) $q['codigo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $s->setCellValue("E{$row}", $q['nombre']);
                    $s->setCellValue("F{$row}", $q['valor']);
                    if ($i === 0) {
                        $s->setCellValue("G{$row}", $c['valor']);
                        $s->getStyle("G{$row}")->getFont()->setBold(true);
                    }
                    $s->getStyle("F{$row}:G{$row}")->getNumberFormat()->setFormatCode($fmt);
                    $row++;
                }
            }
            foreach ($calc['contabilidad']['sin_casillero'] as $q) {
                $s->setCellValue("A{$row}", 'SIN CASILLERO');
                $s->setCellValue("B{$row}", $q['seccion']);
                $s->setCellValue("C{$row}", $q['establecimiento']);
                $s->setCellValueExplicit("D{$row}", (string) $q['codigo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->setCellValue("E{$row}", $q['nombre']);
                $s->setCellValue("F{$row}", $q['valor']);
                $s->getStyle("F{$row}")->getNumberFormat()->setFormatCode($fmt);
                $s->getStyle("A{$row}")->getFont()->getColor()->setRGB('DC3545');
                $row++;
            }
            $s->getColumnDimension('C')->setWidth(28);
            $s->getColumnDimension('D')->setWidth(16);
            $s->getColumnDimension('E')->setWidth(45);
            $s->getColumnDimension('F')->setWidth(16);
            $s->getColumnDimension('G')->setWidth(16);
        }

        // Hoja 4: detalle de documentos
        $s = $ss->createSheet();
        $s->setTitle('Detalle documentos');
        $s->fromArray(['Bloque', 'Establecimiento', 'Fecha', 'Tipo', 'Número', 'Tercero', 'Identificación', 'Base (sin IVA)', 'Total'], null, 'A1');
        $s->getStyle('A1:I1')->applyFromArray($header);
        $row = 2;
        foreach (self::FUENTES_DETALLE as $fuente => $nombre) {
            foreach ($this->getDetalle($idEmpresa, $calc['anio'], $fuente, $idUsuario) as $d) {
                $s->setCellValue("A{$row}", $nombre);
                $s->setCellValue("B{$row}", $d['establecimiento'] ?? '');
                $s->setCellValue("C{$row}", $d['fecha_emision']);
                $s->setCellValue("D{$row}", self::nombreTipoDoc((string) ($d['tipo'] ?? '')));
                $s->setCellValueExplicit("E{$row}", (string) ($d['numero'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->setCellValue("F{$row}", $d['tercero'] ?? '');
                $s->setCellValueExplicit("G{$row}", (string) ($d['identificacion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->setCellValue("H{$row}", (float) $d['base']);
                $s->setCellValue("I{$row}", (float) $d['total']);
                $s->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode($fmt);
                $row++;
            }
        }
        $s->getColumnDimension('A')->setWidth(36);
        $s->getColumnDimension('B')->setWidth(28);
        $s->getColumnDimension('E')->setWidth(20);
        $s->getColumnDimension('F')->setWidth(40);
        $s->getColumnDimension('G')->setWidth(16);
        $s->getColumnDimension('H')->setWidth(16);
        $s->getColumnDimension('I')->setWidth(16);

        $ss->setActiveSheetIndex(0);
        ob_start();
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        return (string) ob_get_clean();
    }

    public static function nombreTipoDoc(string $tipo): string
    {
        return match ($tipo) {
            '01' => 'Factura',
            '03' => 'Liquidación de compra',
            '04' => 'Nota de crédito',
            '05' => 'Nota de débito',
            '07' => 'Retención',
            default => $tipo === '' ? 'Documento' : 'Tipo ' . $tipo,
        };
    }
}

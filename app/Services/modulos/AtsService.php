<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AtsRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Services\Xml\XmlAtsService;
use App\Services\Xml\AtsValidatorService;
use App\Services\LogSistemaService;

/**
 * Orquesta la generación del Anexo Transaccional Simplificado (ATS).
 *
 * Flujo: Controller → AtsService → AtsRepository (datos) + XmlAtsService (XML).
 * Reúne el informante, las compras y liquidaciones del período y sus
 * retenciones (retencion_compra_cabecera/detalle, atadas al documento),
 * normaliza cada documento al formato exacto del SRI y produce ATmmaaaa.xml
 * (y su .zip para carga en el portal). Registra la acción en log_sistema.
 *
 * El ATS se presenta por RUC completo del contribuyente, no por establecimiento — así que
 * recopilar() SIEMPRE consolida todas las filas de `empresas` que comparten RUC y a las que el
 * usuario tiene acceso (nivel 3 ve todo el RUC; el resto, solo sus establecimientos asignados),
 * no solo la empresa activa de sesión. Ver EmpresaRepository::getIdsGrupoRucAccesible().
 */
class AtsService
{
    /** Mapeo tipo_id_proveedor (BD) → tpIdProv ATS (compra: 01 RUC, 02 Cédula, 03 Pasaporte). */
    private const MAP_TP_ID_PROV = [
        '04' => '01', '05' => '02', '06' => '03', '08' => '03',
        '01' => '01', '02' => '02', '03' => '03',
    ];

    private EmpresaRepository $empresaRepo;

    public function __construct(
        private AtsRepository $repo,
        private XmlAtsService $xml,
        private LogSistemaService $log,
        private AtsValidatorService $validator,
        ?EmpresaRepository $empresaRepo = null
    ) {
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
    }

    /**
     * Genera el anexo del período indicado.
     *
     * @param string $mes        '01'..'12' (06/12 actúan como semestre si $semestral)
     * @param string $anio       'YYYY'
     * @param bool   $semestral  Régimen RIMPE semestral
     * @return array{ok:bool, mensaje?:string, registros?:int, nombre_xml?:string,
     *               ruta_xml?:string, nombre_zip?:string, ruta_zip?:string}
     */
    public function generar(int $idEmpresa, int $idUsuario, string $mes, string $anio, bool $semestral): array
    {
        $datos = $this->recopilar($idEmpresa, $mes, $anio, $semestral, $idUsuario);
        if (!$datos['ok']) {
            return ['ok' => false, 'mensaje' => $datos['mensaje']];
        }
        $mes        = $datos['mes'];
        $anio       = $datos['anio'];
        $infXml     = $datos['informante'];
        $documentos = $datos['documentos'];
        $ambiente   = $datos['tipo_ambiente'];

        $contenido = $this->xml->generar(
            $infXml,
            $documentos,
            $datos['ventas'],
            $datos['ventas_estab'],
            $datos['anulados']
        );

        // Validación previa (reglas de la ficha técnica + XSD opcional)
        $validacion = $this->validator->validar($contenido);

        // Persistir XML + ZIP
        $dir = $this->dirSalida($idEmpresa);
        $nombreXml = 'AT' . $mes . $anio . '.xml';
        $nombreZip = 'AT' . $mes . $anio . '.zip';
        $rutaXml = $dir . '/' . $nombreXml;
        $rutaZip = $dir . '/' . $nombreZip;

        if (file_put_contents($rutaXml, $contenido) === false) {
            return ['ok' => false, 'mensaje' => 'No se pudo escribir el archivo XML.'];
        }
        $this->comprimir($rutaXml, $rutaZip, $nombreXml);

        $this->log->registrar(
            $idUsuario,
            $idEmpresa,
            'generar',
            'ats',
            null,
            null,
            ['periodo' => $mes . '/' . $anio, 'semestral' => $semestral, 'ambiente' => $ambiente, 'registros' => count($documentos)]
        );

        return [
            'ok'           => true,
            'registros'    => count($documentos),
            'ambiente'     => $ambiente,
            'nombre_xml'   => $nombreXml,
            'ruta_xml'     => $rutaXml,
            'nombre_zip'   => is_file($rutaZip) ? $nombreZip : null,
            'ruta_zip'     => is_file($rutaZip) ? $rutaZip : null,
            'errores'      => $validacion['errores'],
            'advertencias' => $validacion['advertencias'],
        ];
    }

    /**
     * Recopila y normaliza todos los datos del período (sin escribir archivos).
     * Reutilizado por la generación del XML y por la exportación a Excel.
     *
     * @return array{ok:bool, mensaje?:string, mes?:string, anio?:string,
     *               periodo?:string, informante?:array, documentos?:array, retenciones?:array}
     */
    public function recopilar(int $idEmpresa, string $mes, string $anio, bool $semestral, int $idUsuario = 0): array
    {
        $mes  = str_pad((string) ((int) $mes), 2, '0', STR_PAD_LEFT);
        $anio = (string) ((int) $anio);

        $informante = $this->repo->getInformante($idEmpresa);
        if ($informante === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró la empresa activa.'];
        }

        // El ATS se presenta por RUC completo, no por establecimiento: se consolidan todas las
        // filas de `empresas` con el mismo RUC a las que el usuario tenga acceso. Los queries de
        // AtsRepository siguen siendo por-empresa (id_compra/id_venta son globalmente únicos pero
        // las tablas de retenciones/pagos/reembolso SIEMPRE filtran también por id_empresa), así
        // que se recorre el grupo completo llamando el mismo pipeline una vez por empresa y
        // fusionando resultados — no se tocó ni una sola query de AtsRepository.
        $idsGrupo = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        if (!in_array($idEmpresa, $idsGrupo, true)) {
            $idsGrupo[] = $idEmpresa; // ya validada por sesión, siempre incluida
        }
        // Etiqueta "establecimiento - nombre" por empresa del grupo, para que el Excel de
        // revisión muestre de cuál establecimiento propio viene cada documento (el XML del ATS
        // no distingue esto — es solo para que el usuario audite antes de presentar).
        $etiquetasEstab = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);

        [$desde, $hasta] = $this->rangoFechas($mes, $anio, $semestral);

        // Claves (numero_autorizacion/clave_acceso) ya incluidas — evita duplicar un documento
        // que, por carga manual, haya quedado registrado en dos establecimientos del mismo RUC
        // (la descarga automática del SRI ya deduplica sola entre hermanos, ver
        // DocumentoAutomatedRegisterService::existeClaveEnGrupo(); esto es la red de seguridad
        // para lo cargado a mano). Solo aplica a claves electrónicas (49 dígitos): las físicas
        // comparten legítimamente la misma autorización dentro de un mismo talonario.
        $clavesVistas = [];
        $omitidosPorDuplicado = 0;

        $documentos = [];
        $retenciones = [];
        $grupos = [];
        $ventasPorEstab = [];
        $totalVentas = 0.0;
        $anulados = [];
        $codigosEstabGrupo = [];

        foreach ($idsGrupo as $idEmp) {
            foreach ($this->repo->getEstablecimientos($idEmp) as $cod) {
                if (!in_array($cod, $codigosEstabGrupo, true)) {
                    $codigosEstabGrupo[] = $cod;
                }
            }

            $compras       = $this->filtrarDuplicados($this->repo->getCompras($idEmp, $desde, $hasta), 'numero_autorizacion', $clavesVistas, $omitidosPorDuplicado);
            $liquidaciones = $this->filtrarDuplicados($this->repo->getLiquidaciones($idEmp, $desde, $hasta), 'numero_autorizacion', $clavesVistas, $omitidosPorDuplicado);

            // Retenciones y formas de pago en bloque (evita N+1)
            $idsCompra = array_column($compras, 'id');
            $idsLiq    = array_column($liquidaciones, 'id');

            $retCompras = $this->repo->getRetenciones($idEmp, 'id_compra', $idsCompra);
            $retLiq     = $this->repo->getRetenciones($idEmp, 'id_liquidacion', $idsLiq);
            $retComprasIdx = $this->indexarRetenciones($retCompras);
            $retLiqIdx     = $this->indexarRetenciones($retLiq);
            $pagoComprasIdx = $this->indexarPagos($this->repo->getFormasPago('compras_pagos', 'id_compra', $idsCompra));
            $pagoLiqIdx     = $this->indexarPagos($this->repo->getFormasPago('liquidaciones_pagos', 'id_cabecera', $idsLiq));

            // Facturas de Reembolso RECIBIDAS (codDoc=01, codDocReembolso=41): el bloque
            // *Reemb del ATS-Compras (tipoComprobanteReemb, establecimientoReemb, etc.)
            // no tiene XSD disponible en este proyecto para confirmar su cardinalidad;
            // se emite UNA fila "compra" por cada tercero reembolsado (misma compra base,
            // sub-bloque *Reemb distinto en cada una). Sin validar contra el XSD real del
            // ATS — revisar con el contador antes de presentar el anexo.
            $reembolsoIdx = $this->indexarReembolsoTerceros($this->repo->getReembolsoTercerosCompras($idEmp, $idsCompra));

            $documentosEmp = [];
            foreach ($compras as $c) {
                $terceros = ((string) ($c['cod_doc_reembolso'] ?? '')) === '41'
                    ? ($reembolsoIdx[(int) $c['id']] ?? [])
                    : [];
                if ($terceros !== []) {
                    foreach ($terceros as $i => $t) {
                        $documentosEmp[] = $this->mapearDocumento($c, $retComprasIdx[$c['id']] ?? null, $pagoComprasIdx[$c['id']] ?? [], $t, $i === 0);
                    }
                } else {
                    $documentosEmp[] = $this->mapearDocumento($c, $retComprasIdx[$c['id']] ?? null, $pagoComprasIdx[$c['id']] ?? []);
                }
            }
            foreach ($liquidaciones as $l) {
                $documentosEmp[] = $this->mapearDocumento($l, $retLiqIdx[$l['id']] ?? null, $pagoLiqIdx[$l['id']] ?? []);
            }
            foreach ($documentosEmp as &$dEmp) {
                $dEmp['_id_empresa'] = $idEmp;
                $dEmp['_establecimiento_propio'] = $etiquetasEstab[$idEmp] ?? '';
            }
            unset($dEmp);
            $documentos = array_merge($documentos, $documentosEmp);

            // Serie de cada documento de ESTA empresa, para referenciarla en la hoja de retenciones
            $serie = [];
            $proveedor = [];
            foreach ($documentosEmp as $d) {
                $key = $d['_origen'] . ':' . $d['_id'];
                $serie[$key]     = $d['establecimiento'] . '-' . $d['puntoEmision'] . '-' . $d['secuencial'];
                $proveedor[$key] = $d['_proveedor'];
            }

            foreach ([['compra', $retCompras], ['liquidacion', $retLiq]] as [$origen, $filas]) {
                foreach ($filas as $f) {
                    $key = $origen . ':' . (int) $f['id_documento'];
                    $cod = strtoupper((string) $f['codigo_impuesto']);
                    $retenciones[] = [
                        'origen'        => $origen,
                        'doc_serie'     => $serie[$key] ?? '',
                        'doc_proveedor' => $proveedor[$key] ?? '',
                        'ret_serie'     => str_pad(substr((string) $f['establecimiento'], 0, 3), 3, '0', STR_PAD_LEFT)
                                           . '-' . str_pad(substr((string) $f['punto_emision'], 0, 3), 3, '0', STR_PAD_LEFT)
                                           . '-' . str_pad((string) (int) $f['secuencial'], 9, '0', STR_PAD_LEFT),
                        'ret_aut'       => (string) $f['numero_autorizacion'],
                        'ret_fecha'     => $this->fecha($f['fecha_emision']),
                        'tipo_impuesto' => ($cod === '1' || $cod === 'RENTA') ? 'RENTA' : (($cod === '2' || $cod === 'IVA') ? 'IVA' : $cod),
                        'codigo'        => (string) $f['codigo_retencion'],
                        'concepto'      => (string) ($f['concepto'] ?? ''),
                        'base'          => (float) $f['base_imponible'],
                        'porcentaje'    => (float) $f['porcentaje_retener'],
                        'valor'         => (float) $f['valor_retenido'],
                    ];
                }
            }

            // ── VENTAS (agrupadas por cliente + tipoComprobante + tipoEmisión) ──
            $ventasRaw = $this->filtrarDuplicados($this->repo->getVentas($idEmp, $desde, $hasta), 'clave_acceso', $clavesVistas, $omitidosPorDuplicado);
            $idsVenta  = array_column($ventasRaw, 'id');
            $retVenta  = $this->indexarRetVenta($this->repo->getRetencionesVenta($idEmp, $idsVenta));
            $pagoVenta = $this->indexarPagos($this->repo->getFormasPago('ventas_pagos', 'id_venta', $idsVenta));

            foreach ($ventasRaw as $v) {
            $tpId = str_pad((string) $v['cli_tipo_id'], 2, '0', STR_PAD_LEFT);
            $idCli = $tpId === '07' ? '9999999999999' : (string) $v['cli_identificacion'];
            $tipoEm = !empty($v['clave_acceso']) ? 'E' : 'F';
            $key = $tpId . '|' . $idCli . '|18|' . $tipoEm;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'tpIdCliente' => $tpId,
                    'idCliente'   => $idCli,
                    'cliente'     => (string) $v['cli_nombre'],
                    'parteRel'    => 'NO',
                    'tipoComprobante' => '18',
                    'tipoEm'      => $tipoEm,
                    'numeroComprobantes' => 0,
                    'baseNoGraIva' => 0.0, 'baseImponible' => 0.0, 'baseImpGrav' => 0.0,
                    'montoIva' => 0.0, 'montoIce' => 0.0,
                    'valorRetIva' => 0.0, 'valorRetRenta' => 0.0,
                    'formasPago' => [],
                ];
            }
            $g = &$grupos[$key];
            $g['numeroComprobantes']++;
            $g['baseNoGraIva'] += (float) $v['base_no_gra_iva'];
            // El nodo de ventas del ATS no tiene campo exento (a diferencia de compras): la base
            // exenta (codigoPorcentaje 7) se acumula en baseImponible (base que no genera IVA) para
            // que no se pierda del anexo.
            $g['baseImponible'] += (float) $v['base_imponible_0'] + (float) $v['base_imponible_exe'];
            $g['baseImpGrav']  += (float) $v['base_imponible_grav'];
            $g['montoIva']     += (float) $v['monto_iva'];
            $g['montoIce']     += (float) $v['monto_ice'];
            $g['valorRetIva']  += $retVenta[$v['id']]['iva'] ?? 0.0;
            $g['valorRetRenta']+= $retVenta[$v['id']]['renta'] ?? 0.0;
            foreach ($pagoVenta[$v['id']] ?? [] as $p) {
                $cod = str_pad((string) (int) $p['forma_pago'], 2, '0', STR_PAD_LEFT);
                if (!in_array($cod, $g['formasPago'], true)) {
                    $g['formasPago'][] = $cod;
                }
            }
            unset($g);

            // Solo la emisión FÍSICA (F) suma al talón resumen (totalVentas / ventasEstab).
            // Las electrónicas se listan en el detalle pero el SRI las cruza con los
            // comprobantes electrónicos; no se suman al talón (ficha técnica, 2.3).
            if ($tipoEm === 'F') {
                $baseVenta = (float) $v['base_no_gra_iva'] + (float) $v['base_imponible_0'] + (float) $v['base_imponible_exe'] + (float) $v['base_imponible_grav'];
                $totalVentas += $baseVenta;
                $estab = str_pad(substr((string) $v['establecimiento'], 0, 3), 3, '0', STR_PAD_LEFT);
                $ventasPorEstab[$estab] = ($ventasPorEstab[$estab] ?? 0.0) + $baseVenta;
            }
        }

            // ── VENTAS: Facturas de Reembolso emitidas (tipoComprobante ATS = 41) ──
            // Se reportan aparte (fila propia por cliente), nunca mezcladas con el 18.
            $reembolsoRaw = $this->filtrarDuplicados($this->repo->getVentasReembolso($idEmp, $desde, $hasta), 'clave_acceso', $clavesVistas, $omitidosPorDuplicado);
            foreach ($reembolsoRaw as $v) {
            $tpId = str_pad((string) $v['cli_tipo_id'], 2, '0', STR_PAD_LEFT);
            $idCli = $tpId === '07' ? '9999999999999' : (string) $v['cli_identificacion'];
            $tipoEm = !empty($v['clave_acceso']) ? 'E' : 'F';
            $key = $tpId . '|' . $idCli . '|41|' . $tipoEm;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'tpIdCliente' => $tpId,
                    'idCliente'   => $idCli,
                    'cliente'     => (string) $v['cli_nombre'],
                    'parteRel'    => 'NO',
                    'tipoComprobante' => '41',
                    'tipoEm'      => $tipoEm,
                    'numeroComprobantes' => 0,
                    'baseNoGraIva' => 0.0, 'baseImponible' => 0.0, 'baseImpGrav' => 0.0,
                    'montoIva' => 0.0, 'montoIce' => 0.0,
                    'valorRetIva' => 0.0, 'valorRetRenta' => 0.0,
                    'formasPago' => [],
                ];
            }
            $g = &$grupos[$key];
            $g['numeroComprobantes']++;
            $g['baseNoGraIva'] += (float) $v['base_no_gra_iva'];
            $g['baseImponible'] += (float) $v['base_imponible_0'] + (float) $v['base_imponible_exe'];
            $g['baseImpGrav']  += (float) $v['base_imponible_grav'];
            $g['montoIva']     += (float) $v['monto_iva'];
            $g['montoIce']     += (float) $v['monto_ice'];
            unset($g);

            if ($tipoEm === 'F') {
                $baseVenta = (float) $v['base_no_gra_iva'] + (float) $v['base_imponible_0'] + (float) $v['base_imponible_exe'] + (float) $v['base_imponible_grav'];
                $totalVentas += $baseVenta;
                $estab = str_pad(substr((string) $v['establecimiento'], 0, 3), 3, '0', STR_PAD_LEFT);
                $ventasPorEstab[$estab] = ($ventasPorEstab[$estab] ?? 0.0) + $baseVenta;
            }
            }

            // ── ANULADOS de esta empresa ──
            foreach ($this->repo->getAnulados($idEmp, $desde, $hasta) as $a) {
                $anulados[] = [
                    'tipoComprobante' => (string) $a['tipo_comprobante'],
                    'establecimiento' => str_pad(substr((string) $a['establecimiento'], 0, 3), 3, '0', STR_PAD_LEFT),
                    'puntoEmision'    => str_pad(substr((string) $a['punto_emision'], 0, 3), 3, '0', STR_PAD_LEFT),
                    'secuencialInicio'=> str_pad((string) (int) $a['secuencial'], 9, '0', STR_PAD_LEFT),
                    'secuencialFin'   => str_pad((string) (int) $a['secuencial'], 9, '0', STR_PAD_LEFT),
                    'autorizacion'    => (string) ($a['clave_acceso'] ?: '9999999999'),
                ];
            }
        }
        // ── fin del recorrido por empresa del grupo RUC ──

        $ventas = [];
        foreach ($grupos as $g) {
            $ventas[] = [
                'tpIdCliente'        => $g['tpIdCliente'],
                'idCliente'          => $g['idCliente'],
                'cliente'            => $g['cliente'],
                'parteRel'           => $g['parteRel'],
                'tipoCliente'        => $g['tpIdCliente'] === '06' ? '01' : null,
                'denoCli'            => $g['tpIdCliente'] === '06' ? $this->limpiar(mb_strtoupper($g['cliente'], 'UTF-8')) : null,
                'tipoComprobante'    => $g['tipoComprobante'],
                'tipoEm'             => $g['tipoEm'],
                'numeroComprobantes' => (string) $g['numeroComprobantes'],
                'baseNoGraIva'       => $this->money($g['baseNoGraIva']),
                'baseImponible'      => $this->money($g['baseImponible']),
                'baseImpGrav'        => $this->money($g['baseImpGrav']),
                'montoIva'           => $this->money($g['montoIva']),
                'montoIce'           => $this->money($g['montoIce']),
                'valorRetIva'        => $this->money($g['valorRetIva']),
                'valorRetRenta'      => $this->money($g['valorRetRenta']),
                // Forma de cobro obligatoria desde jun-2016; '01' (sin sistema financiero) por defecto
                'formasDePago'       => $g['formasPago'] !== [] ? $g['formasPago'] : ['01'],
            ];
        }

        // ventasEstablecimiento: un registro por establecimiento inscrito en el RUC (de TODAS
        // las empresas del grupo, ya unidos en $codigosEstabGrupo dentro del recorrido).
        // Solo se emite cuando hay ventas reportadas; los importes son de emisión
        // física (0.00 si el período solo tuvo ventas electrónicas).
        $ventasEstab = [];
        if ($ventas !== []) {
            $codigosEstab = $codigosEstabGrupo;
            foreach (array_keys($ventasPorEstab) as $e) {
                if (!in_array($e, $codigosEstab, true)) {
                    $codigosEstab[] = $e; // establecimiento con ventas pero no listado
                }
            }
            sort($codigosEstab);
            foreach ($codigosEstab as $cod) {
                $ventasEstab[] = [
                    'codEstab'   => str_pad((string) $cod, 3, '0', STR_PAD_LEFT),
                    'ventasEstab'=> $this->money($ventasPorEstab[$cod] ?? 0.0),
                    'ivaComp'    => '0.00',
                ];
            }
        }

        $infXml = [
            'id_informante'        => substr((string) $informante['ruc'], 0, 10) . '001',
            'razon_social'         => $this->limpiar(mb_strtoupper((string) $informante['razon_social'], 'UTF-8')),
            'anio'                 => $anio,
            'mes'                  => $mes,
            'num_estab_ruc'        => str_pad((string) max(1, count($codigosEstabGrupo)), 3, '0', STR_PAD_LEFT),
            'total_ventas'         => $this->money($totalVentas),
            'regimen_microempresa' => $semestral,
        ];

        return [
            'ok'                 => true,
            'mes'                => $mes,
            'anio'               => $anio,
            'periodo'            => $mes . '/' . $anio,
            'tipo_ambiente'      => (string) ($informante['tipo_ambiente'] ?? '1'),
            'informante'         => $infXml,
            'documentos'         => $documentos,
            'retenciones'        => $retenciones,
            'ventas'             => $ventas,
            'ventas_estab'       => $ventasEstab,
            'anulados'           => $anulados,
            // Info de la consolidación por RUC (para avisar en el Excel/UI, no forma parte del XML).
            'empresas_grupo'     => count($idsGrupo),
            'duplicados_omitidos'=> $omitidosPorDuplicado,
        ];
    }

    /**
     * Descarta filas cuya clave (numero_autorizacion/clave_acceso) ya se vio en una empresa
     * anterior del mismo grupo RUC — evita duplicar un documento cargado a mano en dos
     * establecimientos. Solo aplica a claves ELECTRÓNICAS (49 dígitos): las físicas comparten
     * legítimamente la misma autorización dentro de un mismo talonario, no se deben deduplicar.
     * Filas sin esa columna, o con clave no electrónica, se dejan pasar tal cual.
     */
    private function filtrarDuplicados(array $filas, string $campoClave, array &$clavesVistas, int &$omitidos): array
    {
        $out = [];
        foreach ($filas as $f) {
            $clave = trim((string) ($f[$campoClave] ?? ''));
            $esElectronica = $clave !== '' && strlen(preg_replace('/\D/', '', $clave)) === 49;
            if (!$esElectronica) {
                $out[] = $f;
                continue;
            }
            if (isset($clavesVistas[$clave])) {
                $omitidos++;
                continue;
            }
            $clavesVistas[$clave] = true;
            $out[] = $f;
        }
        return $out;
    }

    /** Suma retenciones IVA/Renta que el cliente nos practicó, por id_venta. */
    private function indexarRetVenta(array $filas): array
    {
        $idx = [];
        foreach ($filas as $f) {
            $id = (int) $f['id_venta'];
            if (!isset($idx[$id])) {
                $idx[$id] = ['iva' => 0.0, 'renta' => 0.0];
            }
            $cod = strtoupper((string) $f['codigo_impuesto']);
            if ($cod === '2' || $cod === 'IVA') {
                $idx[$id]['iva'] += (float) $f['valor_retenido'];
            } elseif ($cod === '1' || $cod === 'RENTA') {
                $idx[$id]['renta'] += (float) $f['valor_retenido'];
            }
        }
        return $idx;
    }

    /** Devuelve la ruta absoluta de un archivo de salida ya generado (para descarga). */
    public function rutaArchivo(int $idEmpresa, string $nombre): ?string
    {
        // Solo nombres con el patrón ATmmaaaa(_detalle).(xml|zip|xlsx)
        if (!preg_match('/^AT\d{6}(_detalle)?\.(xml|zip|xlsx)$/', $nombre)) {
            return null;
        }
        $ruta = $this->dirSalida($idEmpresa) . '/' . $nombre;
        return is_file($ruta) ? $ruta : null;
    }

    /** Directorio absoluto de salida del anexo (creándolo si no existe). */
    public function dirArchivos(int $idEmpresa): string
    {
        return $this->dirSalida($idEmpresa);
    }

    // ── normalización de un documento al formato SRI ─────────────────────────

    /**
     * @param ?array $reemb Fila de getReembolsoTercerosCompras() cuando la compra es
     *                      codDocReembolso=41 (una llamada por tercero reembolsado).
     * @param bool $incluirBasePropia Solo la PRIMERA fila de una compra con varios
     *                      terceros lleva las bases/IVA propias de la compra; las
     *                      siguientes van en 0 para no duplicar el total al sumar
     *                      todas las filas del mismo comprobante.
     */
    private function mapearDocumento(array $doc, ?array $ret, array $pagos, ?array $reemb = null, bool $incluirBasePropia = true): array
    {
        $tipoComp = (string) $doc['tipo_comprobante'];
        $tpIdProv = self::MAP_TP_ID_PROV[(string) $doc['tipo_id_proveedor']] ?? (string) $doc['tipo_id_proveedor'];

        $estab = str_pad(substr((string) $doc['establecimiento_prov'], 0, 3), 3, '0', STR_PAD_LEFT);
        $pto   = str_pad(substr((string) $doc['punto_emision_prov'], 0, 3), 3, '0', STR_PAD_LEFT);
        $sec   = str_pad((string) (int) $doc['secuencial_prov'], 9, '0', STR_PAD_LEFT);

        $baseGrav = ($reemb === null || $incluirBasePropia) ? (float) $doc['base_imponible_grav'] : 0.0;
        $base0    = ($reemb === null || $incluirBasePropia) ? (float) $doc['base_imponible_0']    : 0.0;
        $baseNoG  = ($reemb === null || $incluirBasePropia) ? (float) $doc['base_no_gra_iva']      : 0.0;
        $baseExe  = ($reemb === null || $incluirBasePropia) ? (float) $doc['base_imponible_exe']   : 0.0;
        $montoIceDoc = ($reemb === null || $incluirBasePropia) ? (float) $doc['monto_ice'] : 0.0;
        $montoIvaDoc = ($reemb === null || $incluirBasePropia) ? (float) $doc['monto_iva'] : 0.0;

        // Retenciones IVA por porcentaje + líneas AIR (Renta)
        $iva = ['10' => 0.0, '20' => 0.0, '30' => 0.0, '50' => 0.0, '70' => 0.0, '100' => 0.0];
        $air = [];
        $retDoc = null;
        if ($ret !== null) {
            foreach ($ret['lineas'] as $l) {
                $cod = strtoupper((string) $l['codigo_impuesto']);
                if ($cod === '2' || $cod === 'IVA') {
                    $p = (string) (int) round((float) $l['porcentaje_retener']);
                    if (isset($iva[$p])) {
                        $iva[$p] += (float) $l['valor_retenido'];
                    }
                } elseif ($cod === '1' || $cod === 'RENTA') {
                    $air[] = [
                        'codRetAir'     => (string) $l['codigo_retencion'],
                        'baseImpAir'    => $this->money($l['base_imponible']),
                        'porcentajeAir' => $this->money($l['porcentaje_retener']),
                        'valRetAir'     => $this->money($l['valor_retenido']),
                    ];
                }
            }
            $retDoc = [
                'estab' => str_pad(substr((string) $ret['cab']['establecimiento'], 0, 3), 3, '0', STR_PAD_LEFT),
                'pto'   => str_pad(substr((string) $ret['cab']['punto_emision'], 0, 3), 3, '0', STR_PAD_LEFT),
                'sec'   => str_pad((string) (int) $ret['cab']['secuencial'], 9, '0', STR_PAD_LEFT),
                'aut'   => (string) ($ret['cab']['numero_autorizacion'] ?: '9999999999'),
                'fecha' => $this->fecha($ret['cab']['fecha_emision']),
            ];
        }

        // Si no hubo retención de Renta, reportar la base como "no sujeta" (332),
        // salvo en notas de crédito (04).
        if ($air === [] && $tipoComp !== '04') {
            $baseNoRet = $baseGrav + $base0 + $baseNoG + $baseExe;
            if ($baseNoRet > 0) {
                $air[] = [
                    'codRetAir'     => '332',
                    'baseImpAir'    => $this->money($baseNoRet),
                    'porcentajeAir' => '0',
                    'valRetAir'     => '0.00',
                ];
            }
        }

        // Pasaporte en liquidación / nota de venta
        $tipoProv = null;
        $denoProv = null;
        if ($tpIdProv === '03' && $tipoComp === '03') {
            $tipoProv = str_pad((string) (int) $doc['prov_tipo_empresa'], 2, '0', STR_PAD_LEFT);
            $denoProv = $this->limpiar(mb_strtoupper((string) $doc['prov_razon_social'], 'UTF-8'));
        }

        $fechaReg = $this->fecha($doc['fecha_registro']);

        return [
            // Campos de apoyo (ignorados por el XML; usados por el Excel/resumen)
            '_id'              => (int) $doc['id'],
            '_origen'          => (string) $doc['origen'],
            '_proveedor'       => (string) $doc['prov_razon_social'],
            '_importeTotal'    => (float) $doc['importe_total'],

            'codSustento'      => str_pad((string) ($doc['cod_sustento'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'tpIdProv'         => $tpIdProv,
            'idProv'           => (string) $doc['prov_identificacion'],
            'tipoComprobante'  => $tipoComp,
            'tipoProv'         => $tipoProv,
            'denoProv'         => $denoProv,
            'parteRel'         => $this->parteRel($doc),
            'fechaRegistro'    => $fechaReg,
            'establecimiento'  => $estab,
            'puntoEmision'     => $pto,
            'secuencial'       => $sec,
            'fechaEmision'     => $this->fecha($doc['fecha_emision']),
            'autorizacion'     => (string) ($doc['numero_autorizacion'] ?: '9999999999'),
            'baseNoGraIva'     => $this->money($baseNoG),
            'baseImponible'    => $this->money($base0),
            'baseImpGrav'      => $this->money($baseGrav),
            'baseImpExe'       => $this->money($baseExe),
            'montoIce'         => $this->money($montoIceDoc),
            'montoIva'         => $this->money($montoIvaDoc),
            'valRetBien10'     => $this->money($iva['10']),
            'valRetServ20'     => $this->money($iva['20']),
            'valorRetBienes'   => $this->money($iva['30']),
            'valRetServ50'     => $this->money($iva['50']),
            'valorRetServicios'=> $this->money($iva['70']),
            'valRetServ100'    => $this->money($iva['100']),
            'formasDePago'     => $this->formasDePago($doc, $pagos, $tipoComp),
            'air'              => $air,
            'retencionDoc'     => $retDoc,
            'docModificado'    => $this->docModificado($doc, $tipoComp),
            'reembolso'        => $reemb === null ? null : $this->mapearReembolso($reemb),
        ];
    }

    /**
     * Sub-bloque *Reemb del ATS-Compras (sustento 08, codDocReembolso=41).
     * SIN VALIDAR contra el XSD real del ATS (no está disponible en este proyecto):
     * revisar con el contador antes de presentar el anexo.
     */
    private function mapearReembolso(array $t): array
    {
        return [
            'tipoComprobanteReemb' => str_pad((string) ($t['cod_doc_reembolso'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'tpIdProvReemb'        => self::MAP_TP_ID_PROV[(string) $t['tipo_identificacion_proveedor_reembolso']] ?? (string) $t['tipo_identificacion_proveedor_reembolso'],
            'idProvReemb'          => (string) $t['identificacion_proveedor_reembolso'],
            'establecimientoReemb' => str_pad(substr((string) $t['estab_doc_reembolso'], 0, 3), 3, '0', STR_PAD_LEFT),
            'puntoEmisionReemb'    => str_pad(substr((string) $t['pto_emi_doc_reembolso'], 0, 3), 3, '0', STR_PAD_LEFT),
            'secuencialReemb'      => str_pad((string) (int) $t['secuencial_doc_reembolso'], 9, '0', STR_PAD_LEFT),
            'fechaEmisionReemb'    => $this->fecha($t['fecha_emision_doc_reembolso']),
            'autorizacionReemb'    => (string) ($t['numero_autorizacion_doc_reemb'] ?: '9999999999'),
            'baseImponibleReemb'   => $this->money($t['base_imponible_reemb']),
            'baseImpGravReemb'     => $this->money($t['base_imp_grav_reemb']),
            'baseNoGraIvaReemb'    => $this->money($t['base_no_gra_iva_reemb']),
            'baseImpExeReemb'      => $this->money($t['base_imp_exe_reemb']),
            'totbasesImpReemb'     => $this->money(
                (float) $t['base_imponible_reemb'] + (float) $t['base_imp_grav_reemb']
                + (float) $t['base_no_gra_iva_reemb'] + (float) $t['base_imp_exe_reemb']
            ),
            'montoIceReemb'        => '0.00',
            'montoIvaRemb'         => $this->money($t['monto_iva_reemb']),
        ];
    }

    /** Agrupa las filas de getReembolsoTercerosCompras() por id_compra. */
    private function indexarReembolsoTerceros(array $filas): array
    {
        $idx = [];
        foreach ($filas as $f) {
            $idx[(int) $f['id_compra']][] = $f;
        }
        return $idx;
    }

    private function parteRel(array $doc): string
    {
        $rel = $doc['prov_relacionado'] ?? $doc['parte_relacionada'] ?? false;
        $rel = in_array($rel, [true, 't', 'true', '1', 1], true);
        return $rel ? 'SI' : 'NO';
    }

    /**
     * Formas de pago: se reportan cuando el importe supera el umbral del período
     * (USD 500 desde 2024; USD 1000 antes) y no es nota de crédito (04).
     */
    private function formasDePago(array $doc, array $pagos, string $tipoComp): array
    {
        if ($tipoComp === '04' || $pagos === []) {
            return [];
        }
        $anio = (int) date('Y', strtotime((string) $doc['fecha_emision']));
        $umbral = $anio >= 2024 ? 500.0 : 1000.0;
        if ((float) $doc['importe_total'] <= $umbral) {
            return [];
        }
        $codigos = [];
        foreach ($pagos as $p) {
            $cod = str_pad((string) (int) $p['forma_pago'], 2, '0', STR_PAD_LEFT);
            if (!in_array($cod, $codigos, true)) {
                $codigos[] = $cod;
            }
        }
        return $codigos;
    }

    /**
     * Documento modificado (solo notas de crédito/débito 04/05).
     * Best-effort: parsea el número "EEE-PPP-SSSSSSSSS" de documento_modificado.
     */
    private function docModificado(array $doc, string $tipoComp): ?array
    {
        if (!in_array($tipoComp, ['04', '05'], true) || empty($doc['documento_modificado'])) {
            return null;
        }
        $num = preg_replace('/\s+/', '', (string) $doc['documento_modificado']);
        $partes = explode('-', $num);
        if (count($partes) < 3) {
            return null;
        }
        return [
            'docModificado'    => '01', // tipo del comprobante modificado (factura por defecto)
            'estabModificado'  => str_pad(substr($partes[0], 0, 3), 3, '0', STR_PAD_LEFT),
            'ptoEmiModificado' => str_pad(substr($partes[1], 0, 3), 3, '0', STR_PAD_LEFT),
            'secModificado'    => str_pad((string) (int) $partes[2], 9, '0', STR_PAD_LEFT),
            'autModificado'    => (string) ($doc['numero_autorizacion'] ?: '9999999999'),
        ];
    }

    // ── índices auxiliares ───────────────────────────────────────────────────

    /** Agrupa las filas planas cabecera+detalle por id de documento. */
    private function indexarRetenciones(array $filas): array
    {
        $idx = [];
        foreach ($filas as $f) {
            $idDoc = (int) $f['id_documento'];
            if (!isset($idx[$idDoc])) {
                $idx[$idDoc] = [
                    'cab'    => [
                        'establecimiento'     => $f['establecimiento'],
                        'punto_emision'       => $f['punto_emision'],
                        'secuencial'          => $f['secuencial'],
                        'numero_autorizacion' => $f['numero_autorizacion'],
                        'fecha_emision'       => $f['fecha_emision'],
                    ],
                    'lineas' => [],
                ];
            }
            $idx[$idDoc]['lineas'][] = $f;
        }
        return $idx;
    }

    private function indexarPagos(array $filas): array
    {
        $idx = [];
        foreach ($filas as $f) {
            $idx[(int) $f['id_documento']][] = $f;
        }
        return $idx;
    }

    // ── utilidades ───────────────────────────────────────────────────────────

    private function rangoFechas(string $mes, string $anio, bool $semestral): array
    {
        if ($semestral && $mes === '06') {
            return ["$anio-01-01", "$anio-06-30"];
        }
        if ($semestral && $mes === '12') {
            return ["$anio-07-01", "$anio-12-31"];
        }
        $ini = "$anio-$mes-01";
        $fin = date('Y-m-t', strtotime($ini));
        return [$ini, $fin];
    }

    private function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    private function fecha($v): string
    {
        if (empty($v)) {
            return '';
        }
        $ts = strtotime((string) $v);
        return $ts ? date('d/m/Y', $ts) : '';
    }

    /**
     * Normaliza razón social / denominación para el SRI: solo letras, números y
     * espacios (la ficha pide "letras y números, sin caracteres ni símbolos
     * extraños"). Se quitan acentos, puntos, comas, guiones, &, etc.
     * Ej.: "CMG BUSINESS ADMINISTRATION S.A.S." → "CMG BUSINESS ADMINISTRATION SAS".
     */
    private function limpiar(string $s): string
    {
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    private function dirSalida(int $idEmpresa): string
    {
        $base = dirname(MVC_APP) . '/storage/ats/' . $idEmpresa;
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return $base;
    }

    private function comprimir(string $rutaXml, string $rutaZip, string $nombreInterno): void
    {
        if (!class_exists('ZipArchive')) {
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($rutaZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($rutaXml, $nombreInterno);
            $zip->close();
        }
    }
}

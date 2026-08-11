<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\ChequeRepository;
use App\repositories\modulos\ComprasRepository;
use App\repositories\modulos\EgresoRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\GuiaRemisionRepository;
use App\repositories\modulos\IngresoRepository;
use App\repositories\modulos\LiquidacionCompraRepository;
use App\repositories\modulos\NotaCreditoRepository;
use App\repositories\modulos\NotaDebitoRepository;
use App\repositories\modulos\RetencionCompraRepository;
use App\repositories\modulos\RetencionVentaRepository;
use App\Rules\modulos\EgresoRules;
use App\Rules\modulos\IngresoRules;
use App\Services\LogSistemaService;
use App\Services\PdfMergerService;
use App\Services\PlantillasPdfRendererService;
use App\Services\PlantillasPdfSeedService;
use App\Services\Xml\XmlFacturaVentaService;
use App\Services\Xml\XmlGuiaRemisionService;
use App\Services\Xml\XmlLiquidacionCompraService;
use App\Services\Xml\XmlNotaCreditoService;
use App\Services\Xml\XmlNotaDebitoService;
use App\Services\Xml\XmlRetencionCompraService;
use App\Services\ZipBuilderService;

/**
 * Descargas Masivas: genera en memoria el PDF y/o XML de varios documentos de un
 * mismo tipo (rango de fechas o de números) y los entrega al usuario.
 *
 * Ephemeral por diseño (ver CLAUDE.md / decisión del usuario): no crea ninguna
 * tabla ni guarda nada — cuenta primero (contar()), genera el archivo temporal
 * (generarDescarga()) y el Controller lo transmite y lo borra de inmediato.
 * El volumen se acota por CANTIDAD de documentos (config), no por un rango de
 * fechas fijo: un rango largo con pocos documentos es tan válido como uno corto.
 *
 * Salida: si el formato es PDF y la cantidad es pequeña (umbral_pdf_unico,
 * default 20), se fusionan los PDF individuales en UN solo archivo PDF (sin
 * ZIP) vía PdfMergerService. En cualquier otro caso (más documentos, o XML/
 * ambos) se entrega un ZIP, como siempre.
 */
class DescargaMasivaService
{
    /** Estados que cuentan como "documento válido" por tipo; null = no se filtra por estado. */
    private const ESTADOS_VALIDOS = [
        'factura_venta'       => ['autorizado', 'aprobado'],
        'notas_credito'       => ['autorizado'],
        'nota_debito'         => ['autorizado'],
        'guias_remision'      => ['autorizado'],
        'retencion_venta'     => null,
        'retencion_compra'    => ['autorizada'],
        'liquidacion_compra'  => ['autorizado', 'aprobado'],
        'compras'             => null,
        'egreso'              => ['registrado'],
        'ingreso'             => ['registrado'],
        'cheque'              => null, // ya excluye ANULADO en la consulta (whereBaseCheques).
    ];

    public const ETIQUETAS = [
        'factura_venta'      => 'Facturas de Venta',
        'notas_credito'      => 'Notas de Crédito',
        'nota_debito'        => 'Notas de Débito',
        'guias_remision'     => 'Guías de Remisión',
        'retencion_venta'    => 'Retenciones de Venta',
        'retencion_compra'   => 'Retenciones de Compra',
        'liquidacion_compra' => 'Liquidaciones de Compra',
        'compras'            => 'Compras (facturas recibidas)',
        'egreso'             => 'Egresos',
        'ingreso'            => 'Ingresos',
        'cheque'             => 'Cheques',
    ];

    /** Tipos que no son documentos SRI: no tienen XML, solo admiten formato "pdf". */
    public const TIPOS_SIN_XML = ['egreso', 'ingreso', 'cheque'];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    // ── Paso 1: contar (sin generar nada) ───────────────────────────────────

    /**
     * @param array{modo?:string,desde?:string,hasta?:string,numero_desde?:int,numero_hasta?:int} $filtro
     */
    public function contar(int $idEmpresa, string $tipo, array $filtro, ?int $idUsuarioFiltro, string $formato): array
    {
        $cantidad = count($this->listarDocumentos($idEmpresa, $tipo, $filtro, $idUsuarioFiltro));
        $limite = $this->limiteParaFormato($formato);
        $umbral = $this->umbralPdfUnico();
        return [
            'cantidad'         => $cantidad,
            'limite'           => $limite,
            'dentro_limite'    => $cantidad > 0 && $cantidad <= $limite,
            'salida'           => ($formato === 'pdf' && $cantidad > 0 && $cantidad <= $umbral) ? 'pdf' : 'zip',
            'umbral_pdf_unico' => $umbral,
        ];
    }

    private function limiteParaFormato(string $formato): int
    {
        return $formato === 'xml'
            ? max(1, (int) ($this->config['max_documentos_xml'] ?? 2000))
            : max(1, (int) ($this->config['max_documentos_pdf'] ?? 500));
    }

    /** Cantidad máxima de documentos para entregar un PDF único fusionado en vez de un ZIP. */
    private function umbralPdfUnico(): int
    {
        return max(1, (int) ($this->config['umbral_pdf_unico'] ?? 20));
    }

    private function listarDocumentos(int $idEmpresa, string $tipo, array $filtro, ?int $idUsuarioFiltro): array
    {
        $rows = $this->repositorio($tipo)->getParaDescargaMasiva(
            $idEmpresa,
            $filtro['desde'] ?? null,
            $filtro['hasta'] ?? null,
            $filtro['numero_desde'] ?? null,
            $filtro['numero_hasta'] ?? null,
            $idUsuarioFiltro
        );

        $estadosValidos = self::ESTADOS_VALIDOS[$tipo] ?? null;
        if ($estadosValidos !== null) {
            $rows = array_values(array_filter($rows, fn($r) => in_array($r['estado'] ?? '', $estadosValidos, true)));
        }
        return $rows;
    }

    private function repositorio(string $tipo)
    {
        return match ($tipo) {
            'factura_venta'      => new FacturaVentaRepository(),
            'notas_credito'      => new NotaCreditoRepository(),
            'nota_debito'        => new NotaDebitoRepository(),
            'guias_remision'     => new GuiaRemisionRepository(),
            'retencion_venta'    => new RetencionVentaRepository(),
            'retencion_compra'   => new RetencionCompraRepository(),
            'liquidacion_compra' => new LiquidacionCompraRepository(),
            'compras'            => new ComprasRepository(),
            'egreso'             => new EgresoRepository(),
            'ingreso'            => new IngresoRepository(),
            'cheque'             => new ChequeRepository(),
            default => throw new \InvalidArgumentException('Tipo de documento no válido.'),
        };
    }

    // ── Paso 2: generar la descarga (efímera) ───────────────────────────────
    // PDF único fusionado si es formato=pdf y la cantidad cabe en el umbral;
    // ZIP en cualquier otro caso (más documentos, o XML/ambos).

    /**
     * @param array{modo?:string,desde?:string,hasta?:string,numero_desde?:int,numero_hasta?:int} $filtro
     * @return array{ruta:string, nombre:string, cantidad:int, errores:int, tipo_salida:string}
     */
    public function generarDescarga(int $idEmpresa, int $idUsuario, string $tipo, array $filtro, string $formato, ?int $idUsuarioFiltro): array
    {
        $rows = $this->listarDocumentos($idEmpresa, $tipo, $filtro, $idUsuarioFiltro);
        $cantidad = count($rows);
        if ($cantidad === 0) {
            throw new \RuntimeException('No se encontraron documentos con esos filtros.');
        }
        $limite = $this->limiteParaFormato($formato);
        if ($cantidad > $limite) {
            throw new \RuntimeException("Se encontraron {$cantidad} documentos y el máximo por descarga es {$limite}. Reduce el rango.");
        }

        if ($formato === 'pdf' && $cantidad <= $this->umbralPdfUnico()) {
            return $this->generarPdfUnico($idEmpresa, $idUsuario, $tipo, $rows, $filtro);
        }
        return $this->generarZip($idEmpresa, $idUsuario, $tipo, $rows, $filtro, $formato);
    }

    private function generarZip(int $idEmpresa, int $idUsuario, string $tipo, array $rows, array $filtro, string $formato): array
    {
        $rutaZip = $this->rutaTemporal($idEmpresa, $tipo, 'zip');
        $zip = new ZipBuilderService();
        $zip->abrir($rutaZip);

        $errores = 0;
        $usados = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            try {
                $nombreBase = $this->nombreArchivo($row, $usados);

                if ($formato === 'pdf' || $formato === 'ambos') {
                    $bytesPdf = $this->generarPdfBytes($tipo, $idEmpresa, $id);
                    if ($bytesPdf !== null && $bytesPdf !== '') {
                        $zip->agregarDesdeString($nombreBase . '.pdf', $bytesPdf);
                    }
                }
                if ($formato === 'xml' || $formato === 'ambos') {
                    $xml = $this->obtenerXml($tipo, $idEmpresa, $id);
                    if ($xml !== null && $xml !== '') {
                        $zip->agregarDesdeString($nombreBase . '.xml', $xml);
                    }
                }
            } catch (\Throwable $e) {
                $errores++;
                \App\Services\ErrorLogService::registrar($e, ['servicio' => 'DescargaMasivaService', 'tipo' => $tipo, 'id' => $id]);
            }
        }

        $zip->cerrar();

        $cantidad = count($rows);
        $this->registrarLog($idUsuario, $idEmpresa, $tipo, $filtro, $formato, $cantidad, $errores);

        return [
            'ruta'        => $rutaZip,
            'nombre'      => 'descarga_' . $tipo . '_' . $this->sufijoNombre($filtro) . '.zip',
            'cantidad'    => $cantidad,
            'errores'     => $errores,
            'tipo_salida' => 'zip',
        ];
    }

    /** PDF único: genera cada documento y fusiona sus páginas con PdfMergerService (sin ZIP). */
    private function generarPdfUnico(int $idEmpresa, int $idUsuario, string $tipo, array $rows, array $filtro): array
    {
        $bytesPorDocumento = [];
        $errores = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            try {
                $bytes = $this->generarPdfBytes($tipo, $idEmpresa, $id);
                if ($bytes !== null && $bytes !== '') {
                    $bytesPorDocumento[] = $bytes;
                } else {
                    $errores++;
                }
            } catch (\Throwable $e) {
                $errores++;
                \App\Services\ErrorLogService::registrar($e, ['servicio' => 'DescargaMasivaService', 'tipo' => $tipo, 'id' => $id]);
            }
        }
        if (empty($bytesPorDocumento)) {
            throw new \RuntimeException('No se pudo generar el PDF de ningún documento.');
        }

        $pdfFusionado = (new PdfMergerService())->fusionar($bytesPorDocumento);

        $ruta = $this->rutaTemporal($idEmpresa, $tipo, 'pdf');
        $this->asegurarDirectorio(dirname($ruta));
        file_put_contents($ruta, $pdfFusionado);

        $cantidad = count($rows);
        $this->registrarLog($idUsuario, $idEmpresa, $tipo, $filtro, 'pdf', $cantidad, $errores);

        return [
            'ruta'        => $ruta,
            'nombre'      => 'descarga_' . $tipo . '_' . $this->sufijoNombre($filtro) . '.pdf',
            'cantidad'    => $cantidad,
            'errores'     => $errores,
            'tipo_salida' => 'pdf',
        ];
    }

    private function registrarLog(int $idUsuario, int $idEmpresa, string $tipo, array $filtro, string $formato, int $cantidad, int $errores): void
    {
        $detalle = ($filtro['modo'] ?? 'fecha') === 'numero'
            ? ['numero_desde' => $filtro['numero_desde'] ?? null, 'numero_hasta' => $filtro['numero_hasta'] ?? null]
            : ['fecha_desde' => $filtro['desde'] ?? null, 'fecha_hasta' => $filtro['hasta'] ?? null];

        (new LogSistemaService())->registrar(
            $idUsuario,
            $idEmpresa,
            'descarga_masiva',
            $tipo,
            null,
            null,
            $detalle + ['formato' => $formato, 'cantidad' => $cantidad, 'errores' => $errores]
        );
    }

    private function sufijoNombre(array $filtro): string
    {
        $sufijo = ($filtro['modo'] ?? 'fecha') === 'numero'
            ? 'num_' . ($filtro['numero_desde'] ?? '') . '_a_' . ($filtro['numero_hasta'] ?? '')
            : ($filtro['desde'] ?? '') . '_a_' . ($filtro['hasta'] ?? '');
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $sufijo) ?: 'documentos';
    }

    public static function eliminarTemporal(string $ruta): void
    {
        if ($ruta !== '' && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function asegurarDirectorio(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal para la descarga.');
        }
    }

    private function rutaTemporal(int $idEmpresa, string $tipo, string $extension = 'zip'): string
    {
        $dir = rtrim(MVC_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'descargas_masivas' . DIRECTORY_SEPARATOR . $idEmpresa;
        $nombre = 'dm_' . $tipo . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        return $dir . DIRECTORY_SEPARATOR . $nombre;
    }

    private function nombreArchivo(array $row, array &$usados): string
    {
        // Cheques no tienen establecimiento/punto/secuencial propios (son un pago
        // de egreso): se nombran por su número de cheque físico.
        if (array_key_exists('numero_cheque', $row)) {
            $base = 'cheque-' . (string) ($row['numero_cheque'] ?? '');
        } else {
            $estab = (string) ($row['establecimiento'] ?? '');
            $punto = (string) ($row['punto_emision'] ?? '');
            $sec   = (string) ($row['secuencial'] ?? '');
            $base  = trim($estab . '-' . $punto . '-' . $sec, '-');
        }
        $base  = preg_replace('/[^A-Za-z0-9\-]/', '', $base) ?: ('doc-' . (int) ($row['id'] ?? 0));

        $nombre = $base;
        $i = 1;
        while (isset($usados[$nombre])) {
            $i++;
            $nombre = $base . '_' . $i;
        }
        $usados[$nombre] = true;
        return $nombre;
    }

    // ── Empresa/establecimiento para el PDF/XML (logo, leyendas, direcciones) ─
    // Réplica del patrón "construirEmpresaComprobante" que ya duplican, cada uno
    // con su propia copia privada, FacturaVentaController/NotasCreditoController/
    // NotaDebitoController/GuiasRemisionController/FacturaReembolsoController.
    // Una sola copia aquí para los 8 tipos de este módulo, en vez de otra más.

    private function construirEmpresa(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            return $empresa;
        }

        $est = $establecimientos[0];
        if (!empty($est['logo_ruta'])) { $empresa['logo_ruta'] = $est['logo_ruta']; }
        if (!empty($est['direccion'])) { $empresa['direccion_establecimiento'] = $est['direccion']; }
        if (!empty($est['leyenda_pdf_titulo'])) { $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo']; }
        if (!empty($est['leyenda_pdf_mensaje'])) { $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje']; }

        try {
            $estRepo = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig((int) $est['id']);
            if ($estConfig) {
                $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                if (!empty($est['logo_ruta'])) { $estConfig['logo_ruta'] = $est['logo_ruta']; }
                if (!empty($est['leyenda_pdf_titulo'])) { $estConfig['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo']; }
                if (!empty($est['leyenda_pdf_mensaje'])) { $estConfig['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje']; }
                $empresa = array_merge($empresa, $estConfig);
            }
        } catch (\Throwable $e) {
            // El PDF/XML se genera igual sin la config extendida del establecimiento.
        }

        return $empresa;
    }

    /** Empresa + ciudad resuelta (necesaria para el layout del cheque). Réplica de ChequeImpresionService::cargarEmpresa(). */
    private function construirEmpresaCheque(int $idEmpresa): array
    {
        $empresa = $this->construirEmpresa($idEmpresa);
        $cod = trim((string) ($empresa['cod_ciudad'] ?? ''));
        $empresa['ciudad'] = '';
        if ($cod !== '') {
            try {
                $db = \App\core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM ciudad WHERE codigo = :c LIMIT 1");
                $st->execute([':c' => $cod]);
                $empresa['ciudad'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                // El cheque se genera igual sin el nombre de ciudad.
            }
        }
        return $empresa;
    }

    // ── PDF por tipo (misma composición de datos que cada Controller original) ─

    private function generarPdfBytes(string $tipo, int $idEmpresa, int $id): ?string
    {
        switch ($tipo) {
            case 'factura_venta':
                $repo = new FacturaVentaRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                return (new FacturaVentaPdfService())->generarBytes($cab, $detalles, $pagos, $infoAdicional, $this->construirEmpresa($idEmpresa));

            case 'notas_credito':
                $repo = new NotaCreditoRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $infoAdicional = $repo->getInfoAdicional($id);
                return (new NotaCreditoPdfService())->generarBytes($cab, $detalles, $this->construirEmpresa($idEmpresa), $infoAdicional);

            case 'nota_debito':
                $repo = new NotaDebitoRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $motivos = $repo->getMotivos($id);
                $impuestos = $repo->getImpuestos($id);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                return (new NotaDebitoPdfService())->generarBytes($cab, $motivos, $impuestos, $pagos, $this->construirEmpresa($idEmpresa), $infoAdicional);

            case 'guias_remision':
                $repo = new GuiaRemisionRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $detalles = $repo->getDetalles($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                return (new GuiaRemisionPdfService())->generarBytes($cab, $detalles, $infoAdicional, $this->construirEmpresa($idEmpresa));

            case 'retencion_venta':
                $repo = new RetencionVentaRepository();
                $cab = $repo->getPorId($id, $idEmpresa);
                if (!$cab) { return null; }
                $pdfService = new RetencionVentaPdfService();
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') {
                    return $pdfService->generarBytesDesdeXml($xml);
                }
                $lineas = $repo->getDetalle($id);
                $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
                return $pdfService->generarBytes($cab, $lineas, $empresa);

            case 'retencion_compra':
                $repo = new RetencionCompraRepository();
                $cab = $repo->getPorIdSri($id, $idEmpresa);
                if (!$cab) { return null; }
                $lineas = $repo->getDetalle($id);
                return (new RetencionCompraPdfService())->generarBytes($cab, $lineas, $this->construirEmpresa($idEmpresa));

            case 'liquidacion_compra':
                $repo = new LiquidacionCompraRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                return (new LiquidacionCompraPdfService())->generarBytes($cab, $detalles, $pagos, $infoAdicional, $this->construirEmpresa($idEmpresa));

            case 'compras':
                $service = new ComprasService();
                $compra = $service->getPorId($id, $idEmpresa);
                if (!$compra) { return null; }
                $xml = trim((string) ($compra['detalle_xml'] ?? ''));
                if ($xml === '') { return null; }
                $parsed = $service->parsearComprobanteXml($xml);
                $cabecera = array_merge($parsed['cabecera'] ?? [], [
                    'observaciones'  => $compra['observaciones'] ?? null,
                    'fecha_registro' => $compra['fecha_registro'] ?? null,
                ]);
                return (new ComprasPdfService())->generarBytes(
                    $cabecera,
                    $parsed['detalles'] ?? [],
                    $parsed['pagos'] ?? [],
                    $parsed['infoAdicional'] ?? [],
                    $this->construirEmpresa($idEmpresa)
                );

            case 'egreso':
                $service = new EgresoService(new EgresoRepository(), new EgresoRules(), new LogSistemaService());
                $egreso = $service->getPorId($id, $idEmpresa);
                if (!$egreso) { return null; }
                $detalles = $egreso['detalles'] ?? [];
                $pagos = $egreso['pagos'] ?? [];
                $asiento = $service->getAsientoContable($id, $idEmpresa);
                return (new ComprobanteCajaPdfService())->generarEgreso($egreso, $detalles, $pagos, $this->construirEmpresa($idEmpresa), 'S', $asiento);

            case 'ingreso':
                $service = new IngresoService(new IngresoRepository(), new IngresoRules(), new LogSistemaService());
                $ingreso = $service->getPorId($id, $idEmpresa);
                if (!$ingreso) { return null; }
                $detalles = $ingreso['detalles'] ?? [];
                $pagos = $ingreso['pagos'] ?? [];
                $asiento = $service->getAsientoContable($id, $idEmpresa);
                return (new ComprobanteCajaPdfService())->generarIngreso($ingreso, $detalles, $pagos, $this->construirEmpresa($idEmpresa), 'S', $asiento);

            case 'cheque':
                $repo = new ChequeRepository();
                $chq = $repo->getChequePorPago($idEmpresa, $id);
                if (!$chq) { return null; }
                $idBanco = (int) ($chq['id_banco'] ?? 0) ?: null;
                $renderer = new PlantillasPdfRendererService();
                $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cheque', $idBanco) ?: [
                    'tipo_documento' => 'cheque',
                    'configuracion'  => PlantillasPdfSeedService::getSeed('cheque'),
                ];
                $chq['_banco_key'] = '0';
                $pdf = $renderer->generarCheques(['0' => $plantilla], [$chq], $this->construirEmpresaCheque($idEmpresa), 'S');
                return is_string($pdf) && $pdf !== '' ? $pdf : null;

            default:
                return null;
        }
    }

    // ── XML por tipo: prioriza detalle_xml persistido; regenera solo si hace
    // falta (y no lo persiste — este módulo no guarda nada). ────────────────

    private function obtenerXml(string $tipo, int $idEmpresa, int $id): ?string
    {
        switch ($tipo) {
            case 'factura_venta':
                $repo = new FacturaVentaRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                return (new XmlFacturaVentaService())->generar($cab, $detalles, $pagos, $infoAdicional, $empresa, $empresa['direccion_establecimiento'] ?? null);

            case 'notas_credito':
                $repo = new NotaCreditoRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $infoAdicional = $repo->getInfoAdicional($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                return (new XmlNotaCreditoService())->generar($cab, $detalles, $infoAdicional, $empresa, $empresa['direccion_establecimiento'] ?? null);

            case 'nota_debito':
                $repo = new NotaDebitoRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $motivos = $repo->getMotivos($id);
                $impuestos = $repo->getImpuestos($id);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                return (new XmlNotaDebitoService())->generar($cab, $motivos, $impuestos, $pagos, $infoAdicional, $empresa, $empresa['direccion_establecimiento'] ?? null);

            case 'guias_remision':
                $repo = new GuiaRemisionRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $detalles = $repo->getDetalles($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                return (new XmlGuiaRemisionService())->generar($cab, $detalles, $infoAdicional, $empresa, $empresa['direccion_establecimiento'] ?? null);

            case 'retencion_venta':
                // El XML viene del CLIENTE (agente de retención); no se regenera si no está.
                $repo = new RetencionVentaRepository();
                $cab = $repo->getPorId($id, $idEmpresa);
                if (!$cab) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                return $xml !== '' ? $xml : null;

            case 'retencion_compra':
                $repo = new RetencionCompraRepository();
                $cab = $repo->getPorIdSri($id, $idEmpresa);
                if (!$cab) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $lineas = $repo->getDetalle($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                $docSustento = $repo->getDatosDocSustento($cab);
                $dirEst = $cab['estab_direccion'] ?? $cab['punto_direccion'] ?? ($empresa['direccion_establecimiento'] ?? null);
                return (new XmlRetencionCompraService())->generar($cab, $lineas, $empresa, $dirEst, $docSustento);

            case 'liquidacion_compra':
                $repo = new LiquidacionCompraRepository();
                $cab = $repo->getPorId($id);
                if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                if ($xml !== '') { return $xml; }
                $detalles = $repo->getDetalles($id);
                foreach ($detalles as &$d) { $d['impuestos'] = $repo->getImpuestosDetalle((int) $d['id']); }
                unset($d);
                $pagos = $repo->getPagos($id);
                $infoAdicional = $repo->getInfoAdicional($id);
                $empresa = $this->construirEmpresa($idEmpresa);
                return (new XmlLiquidacionCompraService())->generar($cab, $detalles, $pagos, $infoAdicional, $empresa, $empresa['direccion_establecimiento'] ?? null);

            case 'compras':
                // El XML de una compra viene del proveedor/SRI; no se regenera.
                $repo = new ComprasRepository();
                $cab = $repo->getPorId($id, $idEmpresa);
                if (!$cab) { return null; }
                $xml = trim((string) ($cab['detalle_xml'] ?? ''));
                return $xml !== '' ? $xml : null;

            default:
                return null;
        }
    }
}

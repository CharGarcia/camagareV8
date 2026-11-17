<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ComprobantePdf;
use App\repositories\modulos\DescargaPdfRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\DescargaPdfRules;
use App\Services\LogSistemaService;

/**
 * Descargas del SRI → pestaña «Desde PDF».
 *
 * Dos pasos, siempre con una persona en medio:
 *   1. leer():      el PDF se lee con IA y se devuelve una PROPUESTA con sus avisos.
 *   2. registrar(): el usuario revisó/corrigió los datos; se validan, se comprueba
 *                   que el comprobante no exista y se registra por el mismo camino
 *                   que un XML del SRI (DocumentoAutomatedRegisterService).
 *
 * No se consulta al SRI en ningún momento: la fuente es el PDF.
 */
class DescargaPdfService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const CARPETA = '/storage/descargas_sri_pdf';
    private const HORAS_TEMPORAL = 24;

    private DescargaPdfRepository $repo;
    private DescargaPdfRules $rules;
    private EmpresaRepository $empresaRepo;
    private LogSistemaService $log;

    public function __construct(
        ?DescargaPdfRepository $repo = null,
        ?DescargaPdfRules $rules = null,
        private ?DescargaPdfLectorService $lector = null,
        private ?DocumentoAutomatedRegisterService $registro = null
    ) {
        $this->repo = $repo ?? new DescargaPdfRepository();
        $this->rules = $rules ?? new DescargaPdfRules();
        $this->empresaRepo = new EmpresaRepository();
        $this->log = new LogSistemaService();
    }

    // ── Paso 1: leer el PDF ──────────────────────────────────────────────────

    /**
     * @param string $rutaSubida   Archivo temporal de la subida.
     * @param string $tipoEsperado codDoc elegido en el selector ('' = detectar).
     * @param ?int   $idEmpresaIa  Solo superadministrador: empresa que presta su cuenta
     *                             de IA para esta lectura (null = la de la empresa activa).
     *                             El llamador ya validó el nivel; aquí se valida que esa
     *                             empresa esté en la lista de las que tienen IA.
     * @return array Propuesta para la pantalla de revisión (ver armarRespuesta()).
     * @throws \InvalidArgumentException con un mensaje para el usuario.
     */
    public function leer(string $rutaSubida, string $nombre, string $tipoEsperado, int $idEmpresa, int $idUsuario, ?int $idEmpresaIa = null): array
    {
        $tipoEsperado = isset(ComprobantePdf::TIPOS[$tipoEsperado]) ? $tipoEsperado : '';
        $this->validarArchivo($rutaSubida);
        $empresa = $this->empresa($idEmpresa);

        $prestada = $idEmpresaIa !== null && $idEmpresaIa > 0 && $idEmpresaIa !== $idEmpresa;
        if ($prestada && !in_array($idEmpresaIa, array_column($this->empresasConIa(), 'id_empresa'), true)) {
            throw new \InvalidArgumentException('La empresa elegida para prestar su IA no tiene Anthropic (Claude) configurado.');
        }

        [$token, $rutaPdf] = $this->guardarTemporal($rutaSubida, $idEmpresa);

        $this->lector ??= new DescargaPdfLectorService();
        try {
            $lectura = $this->lector->leer($rutaPdf, $tipoEsperado, $prestada ? $idEmpresaIa : $idEmpresa, $prestada);
        } catch (\Throwable $e) {
            @unlink($rutaPdf);
            throw $e;
        }

        // El consumo queda en la empresa que leyó; si la IA fue prestada, también en la
        // empresa dueña de la clave, que es la que paga.
        $uso = ['archivo' => mb_substr($nombre, 0, 200)] + $lectura['uso'];
        $this->log->registrar($idUsuario, $idEmpresa, 'LEER_PDF_IA', 'descargas_sri', null, null,
            $uso + ($prestada ? ['ia_prestada_por_empresa' => $idEmpresaIa] : []));
        if ($prestada) {
            $this->log->registrar($idUsuario, $idEmpresaIa, 'PRESTAR_IA_LECTURA_PDF', 'ia_config', null, null,
                $uso + ['usada_por_empresa' => $idEmpresa, 'ruc_usuaria' => $empresa['ruc']]);
        }

        if (!$lectura['legible']) {
            @unlink($rutaPdf);
            throw new \InvalidArgumentException(
                'El archivo no parece un comprobante electrónico del SRI.'
                . ($lectura['observaciones'] !== '' ? ' ' . $lectura['observaciones'] : '')
            );
        }

        $notas = [];
        $doc = $this->conciliarConClave($lectura['doc'], $tipoEsperado, $notas);

        return $this->armarRespuesta($doc, $empresa, $idEmpresa) + [
            'token_pdf'     => $token,
            'archivo'       => mb_substr($nombre, 0, 200),
            'notas'         => $notas,
            'observaciones' => $lectura['observaciones'],
        ];
    }

    /**
     * Empresas que tienen IA (Anthropic) configurada y pueden prestarla para leer
     * PDF. Listado global: solo para el superadministrador (lo decide el controlador).
     *
     * @return list<array{id_empresa:int, nombre:string, ruc:string}>
     */
    public function empresasConIa(): array
    {
        return (new \App\repositories\modulos\IaConfigRepository())->listarEmpresasConIa('anthropic');
    }

    /** ¿La empresa tiene su propia IA (Anthropic con clave) para leer PDF? */
    public function empresaTieneIa(int $idEmpresa): bool
    {
        $ia = (new \App\repositories\modulos\IaConfigRepository())->getByEmpresa($idEmpresa);
        return $ia !== null
            && (string) ($ia['proveedor'] ?? '') === 'anthropic'
            && trim((string) ($ia['api_key_cifrada'] ?? '')) !== '';
    }

    /**
     * Revalida los datos que el usuario tiene en pantalla (sin registrar nada):
     * la pantalla lo llama para refrescar avisos y opciones tras una corrección.
     */
    public function revisar(array $docCrudo, int $idEmpresa, bool $manual = false): array
    {
        return $this->armarRespuesta(ComprobantePdf::normalizar($docCrudo), $this->empresa($idEmpresa), $idEmpresa, $manual);
    }

    /**
     * Buscador de clientes de la tarjeta «Comprador» (por nombre o identificación).
     *
     * @return list<array{id:int, identificacion:string, nombre:string, direccion:string}>
     */
    public function buscarClientes(int $idEmpresa, string $termino): array
    {
        $filas = (new \App\repositories\modulos\ClienteRepository())->buscarAutocomplete($idEmpresa, $termino, 15);
        return array_map(static fn(array $c): array => [
            'id'             => (int) $c['id'],
            'identificacion' => (string) ($c['identificacion'] ?? ''),
            'nombre'         => (string) ($c['nombre'] ?? ''),
            'direccion'      => (string) ($c['direccion'] ?? ''),
        ], $filas);
    }

    /**
     * Buscador de productos del detalle (ingreso manual). Mismo buscador que Factura de
     * Venta (nombre y códigos); $soloVenta = productos que se venden (factura, nota de
     * crédito); false para la liquidación de compra.
     *
     * @return list<array{codigo:string, nombre:string, precio:float, cod_iva:string}>
     */
    public function buscarProductos(int $idEmpresa, string $termino, bool $soloVenta): array
    {
        $res = (new \App\repositories\modulos\ProductoRepository())
            ->getListado($idEmpresa, $termino, 1, 15, 'nombre', 'ASC', null, $soloVenta ? 'venta' : null, true);
        return array_map(static fn(array $p): array => [
            'codigo'  => (string) ($p['codigo'] ?? ''),
            'nombre'  => (string) ($p['nombre'] ?? ''),
            'precio'  => (float) ($p['precio_base'] ?? 0),
            // Código de porcentaje del SRI (tabla 17): el mismo que usa el detalle.
            'cod_iva' => (string) ($p['codigo_iva_final'] ?? ''),
        ], $res['rows'] ?? []);
    }

    /**
     * Modo de cálculo del IVA por serie ('001-001' => 'subtotal' | 'linea_linea'), del
     * establecimiento de cada punto de emisión (regla §9: la serie del documento manda).
     */
    public function modosIvaPorSerie(int $idEmpresa): array
    {
        $out = [];
        foreach ($this->empresaRepo->getModosIvaPorSerie($idEmpresa) as $serie => $v) {
            $out[$serie] = \App\Helpers\IvaSubtotal::modo(['calculo_iva_facturacion' => $v]);
        }
        return $out;
    }

    /**
     * Datos del emisor para el ingreso manual: la propia empresa (el ingreso manual
     * es solo para comprobantes emitidos por ella).
     *
     * @return array{ruc:string, razon_social:string, nombre_comercial:string, dir_matriz:string, dir_establecimiento:string, obligado_contabilidad:string}
     */
    public function emisorEmpresa(int $idEmpresa): array
    {
        $e = $this->empresaRepo->getEmisorConfig($idEmpresa) ?? [];
        $dir = ComprobantePdf::texto($e['direccion'] ?? '');
        return [
            'ruc'                   => ComprobantePdf::digitos($e['ruc'] ?? ''),
            'razon_social'          => ComprobantePdf::texto($e['nombre'] ?? ''),
            'nombre_comercial'      => ComprobantePdf::texto($e['nombre_comercial'] ?? ''),
            'dir_matriz'            => $dir,
            'dir_establecimiento'   => $dir,
            'obligado_contabilidad' => strtoupper(trim((string) ($e['obligado_contabilidad'] ?? ''))) === 'SI' ? 'SI' : 'NO',
        ];
    }

    // ── Paso 2: registrar lo que el usuario aprobó ───────────────────────────

    /**
     * @param array  $docCrudo         Datos tal como quedaron en la pantalla de revisión.
     * @param bool   $confirmarAvisos  El usuario vio las diferencias y decidió registrar igual.
     * @param bool   $manual           Ingreso manual (sin IA): solo comprobantes emitidos por la empresa.
     * @param ?string $rutaPdfManual   Ingreso manual: PDF opcional que se guarda como respaldo (no se lee).
     * @return array {ok, estado_registro, mensaje, ...} o {ok:false, errores|avisos}
     */
    public function registrar(array $docCrudo, string $tokenPdf, bool $confirmarAvisos, int $idEmpresa, int $idUsuario,
                              bool $manual = false, ?string $rutaPdfManual = null): array
    {
        $empresa = $this->empresa($idEmpresa);
        $doc = ComprobantePdf::normalizar($docCrudo);
        if ($doc['numero_autorizacion'] === '') {
            $doc['numero_autorizacion'] = $doc['clave_acceso'];
        }
        if ($manual && $rutaPdfManual !== null) {
            $this->validarArchivo($rutaPdfManual);
        }

        $val = $this->rules->validar($doc, $empresa['ruc'], $manual);
        $val['errores'] = array_merge($val['errores'], $this->erroresSerieManual($doc, $idEmpresa, $manual));
        if ($val['errores'] !== []) {
            return ['ok' => false, 'errores' => $val['errores'], 'avisos' => $val['avisos']];
        }
        if ($val['avisos'] !== [] && !$confirmarAvisos) {
            return ['ok' => false, 'requiere_confirmacion' => true, 'avisos' => $val['avisos']];
        }

        $numero = $doc['estab'] . '-' . $doc['pto_emi'] . '-' . $doc['secuencial'];
        $base = [
            'clave'            => $doc['clave_acceso'],
            'numero_documento' => $numero,
            'tipo_nombre'      => ComprobantePdf::TIPOS[$doc['cod_doc']],
            'emisor'           => $doc['emisor']['razon_social'],
            'total'            => $doc['cod_doc'] === '07'
                ? round(array_sum(array_column($doc['retenciones'], 'valor')), 2)
                : $doc['totales']['total'],
        ];

        // Un mismo RUC = un solo flujo de registro a la vez (mismo candado que la carga
        // de XML): sin él, dos envíos simultáneos pasan ambos el chequeo de existencia.
        if (!$this->repo->tomarCandadoRegistro($empresa['ruc'])) {
            return ['ok' => false, 'errores' => [
                'Ya hay un registro de comprobantes en curso para esta empresa. Espere a que termine e intente de nuevo.',
            ]];
        }

        try {
            // Antes de registrar: ¿ya existe? (por clave de acceso y por número).
            $existe = $this->buscarExistente($doc, $val['rol'], $empresa, $idEmpresa);
            if ($existe !== null) {
                return ['ok' => true, 'existe' => true, 'estado_registro' => 'YA ESTABA REGISTRADO',
                        'mensaje' => $this->mensajeExiste($existe)] + $base;
            }

            $xml = DescargaPdfXmlBuilder::construir($doc, $manual);

            $this->registro ??= new DocumentoAutomatedRegisterService();
            $res = $this->registro->procesarYRegistrar($xml, $idEmpresa, $idUsuario);
            $this->registro->notificarComprasPendientes();
        } finally {
            $this->repo->soltarCandadoRegistro($empresa['ruc']);
        }

        $res = array_merge($base, $res);
        if (empty($res['ok'])) {
            return ['ok' => false, 'errores' => [(string) ($res['error'] ?? $res['mensaje'] ?? 'No se pudo registrar el comprobante.')]] + $res;
        }

        if (($res['estado_registro'] ?? '') === 'REGISTRADO') {
            $archivo = $manual
                ? ($rutaPdfManual !== null ? $this->conservarPdfManual($rutaPdfManual, $idEmpresa, $doc['clave_acceso']) : null)
                : $this->conservarPdf($tokenPdf, $idEmpresa, $doc['clave_acceso']);
            $this->log->registrar($idUsuario, $idEmpresa, $manual ? 'REGISTRAR_MANUAL' : 'REGISTRAR_DESDE_PDF', 'descargas_sri',
                isset($res['id']) ? (int) $res['id'] : null, null, [
                    'origen'       => $manual
                        ? 'Comprobante emitido ingresado manualmente por el usuario'
                        : 'PDF (RIDE) leído con IA y aprobado por el usuario',
                    'tipo'         => $base['tipo_nombre'],
                    'numero'       => $numero,
                    'clave_acceso' => $doc['clave_acceso'],
                    'emisor'       => $doc['emisor']['ruc'],
                    'total'        => $base['total'],
                    'archivo_pdf'  => $archivo,
                    'avisos_aceptados' => $val['avisos'],
                ]);
            $res['mensaje'] = trim((string) ($res['mensaje'] ?? '')) . ($manual ? ' | Origen: ingreso manual.' : ' | Origen: PDF.');
        }
        $res['xml_base64'] = base64_encode($xml);
        return $res;
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    /** @return array{ruc:string, ambiente:string} */
    private function empresa(int $idEmpresa): array
    {
        $e = $this->empresaRepo->getEmisorConfig($idEmpresa);
        if (!$e) {
            throw new \InvalidArgumentException('No hay una empresa activa.');
        }
        return [
            'ruc'      => ComprobantePdf::digitos($e['ruc'] ?? ''),
            'ambiente' => (string) ($e['tipo_ambiente'] ?? '2'),
        ];
    }

    /**
     * Ingreso manual: la serie (establecimiento-punto) debe existir en la empresa. El registro
     * desde XML crea el establecimiento/punto que no encuentra (útil en una descarga), así que
     * un número mal digitado a mano dejaría una serie nueva y vacía en la empresa.
     *
     * @return list<string>
     */
    private function erroresSerieManual(array $doc, int $idEmpresa, bool $manual): array
    {
        if (!$manual || $doc['estab'] === '' || $doc['pto_emi'] === '') {
            return [];
        }
        $serie = $doc['estab'] . '-' . $doc['pto_emi'];
        if (array_key_exists($serie, $this->empresaRepo->getModosIvaPorSerie($idEmpresa))) {
            return [];
        }
        return ["La serie {$serie} (establecimiento-punto de emisión) no existe en la empresa. "
            . 'Revise el número del comprobante o cree la serie en Empresas.'];
    }

    private function armarRespuesta(array $doc, array $empresa, int $idEmpresa, bool $manual = false): array
    {
        // El código de retención casi nunca se imprime en el PDF: se proponen los del
        // catálogo con el mismo impuesto y porcentaje; si solo hay uno, se pone.
        $opciones = [];
        foreach ($doc['retenciones'] as $i => $r) {
            $opciones[$i] = $this->repo->opcionesRetencion($r['impuesto'], (float) $r['porcentaje']);
            if ($r['codigo_retencion'] === '' && count($opciones[$i]) === 1) {
                $doc['retenciones'][$i]['codigo_retencion'] = $opciones[$i][0]['codigo'];
            }
        }
        if ($doc['numero_autorizacion'] === '') {
            $doc['numero_autorizacion'] = $doc['clave_acceso'];
        }

        $val = $this->rules->validar($doc, $empresa['ruc'], $manual);
        $val['errores'] = array_merge($val['errores'], $this->erroresSerieManual($doc, $idEmpresa, $manual));
        $existe = $val['rol'] !== '' ? $this->buscarExistente($doc, $val['rol'], $empresa, $idEmpresa) : null;

        return [
            'doc'                 => $doc,
            'errores'             => $val['errores'],
            'avisos'              => $val['avisos'],
            'rol'                 => $val['rol'],
            'existe'              => $existe !== null,
            'mensaje_existe'      => $existe !== null ? $this->mensajeExiste($existe) : '',
            'opciones_retencion'  => $opciones,
        ];
    }

    private function buscarExistente(array $doc, string $rol, array $empresa, int $idEmpresa): ?array
    {
        if ($rol === '' || $doc['cod_doc'] === '') {
            return null;
        }
        return $this->repo->buscarComprobante(
            $rol, $doc['cod_doc'], $doc['clave_acceso'], $doc['emisor']['ruc'],
            $doc['estab'], $doc['pto_emi'], $doc['secuencial'],
            $this->empresaRepo->getIdsEmpresaMismoRuc($idEmpresa), $empresa['ambiente']
        );
    }

    private function mensajeExiste(array $existe): string
    {
        return $existe['por'] === 'clave'
            ? 'Este comprobante ya está registrado en el sistema (misma clave de acceso). No se registró otra vez.'
            : 'Ya existe en el sistema un comprobante de ese emisor con el mismo número. No se registró otra vez.';
    }

    /**
     * La clave de acceso lleva codificados tipo, fecha, RUC y número: si es válida,
     * manda sobre lo que se leyó del resto del PDF (sin consultar al SRI).
     */
    private function conciliarConClave(array $doc, string $tipoEsperado, array &$notas): array
    {
        if (!ComprobantePdf::claveValida($doc['clave_acceso']) && ComprobantePdf::claveValida($doc['numero_autorizacion'])) {
            $doc['clave_acceso'] = $doc['numero_autorizacion'];
        }
        $p = ComprobantePdf::claveValida($doc['clave_acceso']) ? ComprobantePdf::desglosarClave($doc['clave_acceso']) : null;

        if ($p !== null) {
            $campos = [
                'cod_doc' => ['el tipo de comprobante', $p['cod_doc']],
                'estab' => ['el establecimiento', $p['estab']],
                'pto_emi' => ['el punto de emisión', $p['pto_emi']],
                'secuencial' => ['el secuencial', $p['secuencial']],
                'fecha_emision' => ['la fecha de emisión', $p['fecha']],
            ];
            if (isset(ComprobantePdf::TIPOS[$p['cod_doc']])) {
                foreach ($campos as $k => [$que, $valor]) {
                    if ($doc[$k] !== '' && $doc[$k] !== $valor) {
                        $notas[] = "Se tomó {$que} de la clave de acceso ({$valor}); en el PDF se leyó «{$doc[$k]}».";
                    }
                    $doc[$k] = $valor;
                }
                if ($doc['emisor']['ruc'] !== '' && $doc['emisor']['ruc'] !== $p['ruc']) {
                    $notas[] = "Se tomó el RUC del emisor de la clave de acceso ({$p['ruc']}); en el PDF se leyó «{$doc['emisor']['ruc']}».";
                }
                $doc['emisor']['ruc'] = $p['ruc'];
            }
        } elseif ($doc['cod_doc'] === '' && $tipoEsperado !== '') {
            $doc['cod_doc'] = $tipoEsperado;
        }

        if ($tipoEsperado !== '' && $doc['cod_doc'] !== '' && $doc['cod_doc'] !== $tipoEsperado) {
            $notas[] = 'Usted eligió «' . ComprobantePdf::TIPOS[$tipoEsperado] . '», pero el PDF es: '
                . ComprobantePdf::TIPOS[$doc['cod_doc']] . '.';
        }
        // El periodo fiscal de una retención es el mes de su emisión.
        if ($doc['cod_doc'] === '07' && $doc['periodo_fiscal'] === '' && $doc['fecha_emision'] !== '') {
            $doc['periodo_fiscal'] = substr($doc['fecha_emision'], 3);
        }
        return $doc;
    }

    private function validarArchivo(string $ruta): void
    {
        if (!is_file($ruta)) {
            throw new \InvalidArgumentException('No se recibió el archivo.');
        }
        $tam = (int) filesize($ruta);
        if ($tam <= 0 || $tam > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El PDF debe pesar menos de ' . (int) (self::MAX_BYTES / 1048576) . ' MB.');
        }
        $h = fopen($ruta, 'rb');
        $cab = $h ? (string) fread($h, 1024) : '';
        if ($h) {
            fclose($h);
        }
        if (!str_contains($cab, '%PDF-')) {
            throw new \InvalidArgumentException('El archivo no es un PDF válido.');
        }
    }

    private function carpeta(int $idEmpresa): string
    {
        return MVC_ROOT . self::CARPETA . '/' . $idEmpresa;
    }

    /** @return array{0:string, 1:string} [token, ruta] */
    private function guardarTemporal(string $rutaSubida, int $idEmpresa): array
    {
        $dir = $this->carpeta($idEmpresa);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \InvalidArgumentException('No se pudo preparar la carpeta para leer el PDF.');
        }
        // Limpieza de lecturas que nunca se registraron.
        foreach (glob($dir . '/tmp_*.pdf') ?: [] as $viejo) {
            if (@filemtime($viejo) < time() - self::HORAS_TEMPORAL * 3600) {
                @unlink($viejo);
            }
        }
        $token = bin2hex(random_bytes(16));
        $ruta = $dir . '/tmp_' . $token . '.pdf';
        if (!@copy($rutaSubida, $ruta)) {
            throw new \InvalidArgumentException('No se pudo guardar el PDF para leerlo.');
        }
        return [$token, $ruta];
    }

    /** Ingreso manual: guarda como respaldo el PDF que adjuntó el usuario (no se lee). */
    private function conservarPdfManual(string $rutaSubida, int $idEmpresa, string $clave): ?string
    {
        $dir = $this->carpeta($idEmpresa);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $final = $dir . '/' . $clave . '.pdf';
        return @copy($rutaSubida, $final) ? self::CARPETA . '/' . $idEmpresa . '/' . $clave . '.pdf' : null;
    }

    /** Deja el PDF original como respaldo del registro. Devuelve su ruta relativa o null. */
    private function conservarPdf(string $token, int $idEmpresa, string $clave): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $dir = $this->carpeta($idEmpresa);
        $tmp = $dir . '/tmp_' . $token . '.pdf';
        if (!is_file($tmp)) {
            return null;
        }
        $final = $dir . '/' . $clave . '.pdf';
        return @rename($tmp, $final) ? self::CARPETA . '/' . $idEmpresa . '/' . $clave . '.pdf' : null;
    }
}

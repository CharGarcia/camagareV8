<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\SriFichaTecnica;
use App\Rules\modulos\CargaFacturasRules;
use App\repositories\modulos\CargaFacturasRepository;
use App\repositories\modulos\EmpresaRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Valida un archivo de carga masiva de facturas SIN escribir nada.
 *
 * Produce un informe con:
 *   - errores_globales: el archivo entero no sirve (hojas borradas, otra empresa).
 *   - filas: los problemas encontrados, para mostrarlos en pantalla.
 *   - facturas: el payload ya armado de cada factura, listo para la aplicación.
 *   - productos_nuevos: los códigos que habrá que crear al vuelo.
 *
 * Las reglas de FacturaVentaRules se siguen aplicando íntegras al crear cada
 * factura; lo que se hace aquí es adelantarlas para poder informarlas antes de
 * escribir. Hay dos validaciones que solo existen en este contexto:
 *   - el stock se chequea SUMANDO todas las facturas del archivo (una a una
 *     pasarían, juntas no);
 *   - las llaves ID_FACTURA deben ser únicas y estar enlazadas entre hojas.
 */
class CargaFacturasValidacionService
{
    private CargaFacturasRepository $repository;
    private CargaFacturasRules $rules;
    private EmpresaRepository $empresaRepository;

    /** Catálogos precargados. */
    private array $mapaClientes   = [];
    private array $identsBorradas = [];
    private array $mapaProductos  = [];
    private array $mapaIva        = [];
    private array $mapaFormasPago = [];
    /** Formas de pago indexadas por ID: es como las guardan cliente y establecimiento. */
    private array $mapaFormasPagoPorId = [];
    private array $mapaPuntos     = [];
    private array $mapaBodegas    = [];
    private array $mapaVendedores = [];

    /** Config de establecimiento cacheada por id. */
    private array $configEstablecimientos = [];

    /** Productos que habrá que crear al vuelo, indexados por código en minúsculas. */
    private array $productosNuevos = [];

    public function __construct(
        CargaFacturasRepository $repository,
        CargaFacturasRules $rules,
        ?EmpresaRepository $empresaRepository = null
    ) {
        $this->repository        = $repository;
        $this->rules             = $rules;
        $this->empresaRepository = $empresaRepository ?? new EmpresaRepository();
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function validar(string $rutaArchivo, int $idEmpresa): array
    {
        $informe = [
            'ok'               => false,
            'errores_globales' => [],
            'resumen'          => [
                'total_facturas'   => 0,
                'aplicables'       => 0,
                'bloqueadas'       => 0,
                'productos_nuevos' => 0,
                'filas_con_error'  => 0,
                'con_aviso'        => 0,
                'total_general'    => 0.0,
            ],
            'filas'            => [],
            'facturas'         => [],
            'productos_nuevos' => [],
            'hash_archivo'     => '',
        ];

        if (!is_file($rutaArchivo)) {
            $informe['errores_globales'][] = 'No se encontró el archivo subido.';
            return $informe;
        }

        // Huella del archivo: la aplicación la deja registrada en la auditoría,
        // de modo que subir dos veces exactamente el mismo libro se detecta antes
        // de duplicar nada.
        $informe['hash_archivo'] = (string) hash_file('sha256', $rutaArchivo);

        $cargaPrevia = $this->repository->getCargaPreviaPorHash($informe['hash_archivo'], $idEmpresa);
        if ($cargaPrevia !== null) {
            $cuando  = date('d-m-Y H:i:s', strtotime((string) $cargaPrevia['created_at']));
            $numeros = trim((string) $cargaPrevia['numeros']);
            $informe['errores_globales'][] = 'Este archivo YA SE CARGÓ el ' . $cuando . ' y creó '
                . $cargaPrevia['creadas'] . ' factura(s)'
                . ($numeros !== '' ? ' (' . $numeros . ')' : '')
                . '. Si vuelve a aplicarlo se duplicarían. Revíselas en Facturas de Venta; '
                . 'si necesita cargar facturas nuevas, prepare un archivo con solo esas filas.';
            return $informe;
        }

        try {
            $libro = IOFactory::load($rutaArchivo);
        } catch (\Throwable $e) {
            $informe['errores_globales'][] = 'El archivo no es un Excel válido o está dañado.';
            return $informe;
        }

        // 1. El libro debe conservar exactamente las hojas de la plantilla.
        $erroresHojas = $this->validarHojas($libro);
        if ($erroresHojas) {
            $informe['errores_globales'] = $erroresHojas;
            return $informe;
        }

        // 2. La plantilla debe pertenecer a la empresa activa.
        $errorEmpresa = $this->validarEmpresa($libro, $idEmpresa);
        if ($errorEmpresa !== null) {
            $informe['errores_globales'][] = $errorEmpresa;
            return $informe;
        }

        // 3. Los encabezados de cada hoja de datos deben coincidir.
        $erroresEncabezados = $this->validarEncabezados($libro);
        if ($erroresEncabezados) {
            $informe['errores_globales'] = $erroresEncabezados;
            return $informe;
        }

        // 4. El EMISOR debe cumplir la ficha técnica. Se comprueba una sola vez:
        //    si el RUC o la razón social de la empresa están mal, ninguna factura
        //    del archivo sería aceptada por el SRI, así que no vale la pena crear
        //    ninguna.
        $erroresEmisor = $this->validarEmisor($idEmpresa);
        if ($erroresEmisor) {
            $informe['errores_globales'] = $erroresEmisor;
            return $informe;
        }

        $this->precargarCatalogos($idEmpresa, $libro);

        // 5. Hoja Facturas (cabeceras).
        $facturas = $this->procesarHojaFacturas($libro, $informe, $idEmpresa);
        if (!$facturas) {
            $informe['errores_globales'][] = 'La hoja "' . CargaFacturasEsquema::HOJA_FACTURAS
                . '" no tiene ninguna fila con datos.';
            return $informe;
        }

        // 6. Hojas hijas.
        $this->procesarHojaDetalles($libro, $facturas, $informe);
        $this->procesarHojaInfoAdicional($libro, $facturas, $informe);

        // 7. Cierres que necesitan la factura completa.
        $this->consolidarErroresDeHijas($facturas);
        $this->calcularTotales($facturas);
        $this->resolverFormaPago($facturas);
        $this->validarLimiteConsumidorFinal($facturas);
        $this->validarNoDuplicadas($facturas, $idEmpresa);
        $this->validarStockAgregado($facturas, $idEmpresa);

        // 8. Resumen.
        foreach ($facturas as $f) {
            $informe['resumen']['total_facturas']++;
            if ($f['errores']) {
                $informe['resumen']['bloqueadas']++;
            } else {
                $informe['resumen']['aplicables']++;
                $informe['resumen']['total_general'] += $f['importe_total'];
            }
        }
        $informe['resumen']['total_general'] = round($informe['resumen']['total_general'], 2);

        // Los errores acumulados en la factura se vuelcan a la fila de su cabecera
        // para que el usuario los vea en el detalle de pantalla.
        foreach ($facturas as $clave => $f) {
            if ($f['errores'] || $f['avisos']) {
                $informe['filas'][] = [
                    'hoja'    => CargaFacturasEsquema::HOJA_FACTURAS,
                    'fila'    => $f['fila'],
                    'clave'   => $clave,
                    'errores' => $f['errores'],
                    'avisos'  => $f['avisos'],
                ];
            }
        }

        foreach ($informe['filas'] as $fila) {
            if ($fila['errores']) {
                $informe['resumen']['filas_con_error']++;
            }
            if ($fila['avisos']) {
                $informe['resumen']['con_aviso']++;
            }
        }

        $informe['facturas'] = $facturas;

        // Productos a crear: solo los que usa alguna factura que sí se va a
        // aplicar. Si la única factura que usaba un código nuevo quedó bloqueada,
        // no tiene sentido crear el registro.
        $informe['productos_nuevos'] = $this->filtrarProductosNuevosUsados($facturas);

        $informe['resumen']['productos_nuevos'] = count($informe['productos_nuevos']);
        $informe['ok'] = ($informe['resumen']['aplicables'] > 0);

        return $informe;
    }

    /** @return array<string,array> Productos nuevos usados por alguna factura aplicable. */
    private function filtrarProductosNuevosUsados(array $facturas): array
    {
        $usados = [];
        foreach ($facturas as $f) {
            if ($f['errores']) {
                continue;
            }
            foreach ($f['detalles'] as $d) {
                $clave = mb_strtolower($d['codigo_producto']);
                if ($d['id_producto'] === null && !$d['es_libre'] && isset($this->productosNuevos[$clave])) {
                    $usados[$clave] = $this->productosNuevos[$clave];
                }
            }
        }
        return $usados;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validaciones estructurales
    // ─────────────────────────────────────────────────────────────────────────

    /** @return string[] */
    private function validarHojas(Spreadsheet $libro): array
    {
        $esperadas = CargaFacturasEsquema::todasLasHojas();
        $presentes = $libro->getSheetNames();

        $faltantes = array_diff($esperadas, $presentes);
        $sobrantes = array_diff($presentes, $esperadas);

        $errores = [];
        if ($faltantes) {
            $errores[] = 'Al archivo le faltan hojas que no se deben borrar: '
                . implode(', ', $faltantes) . '. Descargue la plantilla nuevamente.';
        }
        if ($sobrantes) {
            $errores[] = 'El archivo tiene hojas que no pertenecen a la plantilla: '
                . implode(', ', $sobrantes) . '. Elimínelas o descargue la plantilla nuevamente.';
        }
        return $errores;
    }

    /** La plantilla lleva embebida la empresa para la que se generó. */
    private function validarEmpresa(Spreadsheet $libro, int $idEmpresa): ?string
    {
        $hoja = $libro->getSheetByName(CargaFacturasEsquema::HOJA_CONFIG);
        if ($hoja === null) {
            return 'El archivo no tiene la hoja de control ' . CargaFacturasEsquema::HOJA_CONFIG . '.';
        }

        $idArchivo = trim((string) $hoja->getCell([2, 1])->getValue());
        if ($idArchivo === '') {
            return 'El archivo no indica a qué empresa pertenece. Descargue la plantilla nuevamente.';
        }

        if ((int) $idArchivo !== $idEmpresa) {
            $nombreArchivo = trim((string) $hoja->getCell([2, 2])->getValue());
            return 'Esta plantilla se generó para otra empresa'
                . ($nombreArchivo !== '' ? ' (' . $nombreArchivo . ')' : '')
                . '. Descargue la plantilla desde la empresa en la que desea cargar.';
        }

        return null;
    }

    /** @return string[] */
    private function validarEncabezados(Spreadsheet $libro): array
    {
        $errores = [];

        foreach (CargaFacturasEsquema::hojasDatos() as $nombreHoja => $def) {
            $hoja = $libro->getSheetByName($nombreHoja);
            if ($hoja === null) {
                continue; // ya reportado en validarHojas()
            }

            $esperadas = $def['columnas'];
            $reales    = [];
            foreach (array_keys($esperadas) as $i) {
                $reales[] = strtoupper(trim((string) $hoja->getCell([$i + 1, 1])->getValue()));
            }

            if ($reales !== array_map('strtoupper', $esperadas)) {
                $errores[] = 'Los encabezados de la hoja "' . $nombreHoja
                    . '" fueron modificados. Se esperaba: ' . implode(' | ', $esperadas) . '.';
            }
        }

        return $errores;
    }

    /**
     * Comprueba los datos del EMISOR contra la ficha técnica del SRI.
     *
     * Son los mismos en todas las facturas del archivo, así que se revisan una
     * sola vez y, si están mal, se descarta el archivo entero: crear doscientas
     * facturas que el SRI va a rechazar —y que quedarían numeradas— no ayuda a
     * nadie.
     *
     * @return string[]
     */
    private function validarEmisor(int $idEmpresa): array
    {
        $empresa = $this->repository->getEmpresa($idEmpresa);
        if (!$empresa) {
            return ['No se encontraron los datos de la empresa activa.'];
        }

        $errores = [];

        $ruc = trim((string) ($empresa['ruc'] ?? ''));
        if (!preg_match('/^[0-9]{13}$/', $ruc)) {
            $errores[] = 'El RUC de la empresa ("' . $ruc . '") no tiene 13 dígitos. '
                . 'Corríjalo en la configuración de la empresa antes de cargar facturas.';
        }

        $razonSocial = trim((string) ($empresa['razon_social'] ?? ''));
        if ($razonSocial === '') {
            $errores[] = 'La empresa no tiene razón social registrada; el SRI la exige.';
        } else {
            $error = SriFichaTecnica::excedeLongitud(
                'La razón social de la empresa', $razonSocial, SriFichaTecnica::MAX_RAZON_SOCIAL_COMPRADOR
            );
            if ($error !== null) {
                $errores[] = $error;
            }
        }

        $direccion = trim((string) ($empresa['direccion'] ?? ''));
        if ($direccion === '') {
            $errores[] = 'La empresa no tiene dirección de matriz registrada; el SRI la exige.';
        } else {
            $error = SriFichaTecnica::excedeLongitud(
                'La dirección de la empresa', $direccion, SriFichaTecnica::MAX_DIRECCION
            );
            if ($error !== null) {
                $errores[] = $error;
            }
        }

        return $errores;
    }

    /**
     * Trae de la base solo lo que el archivo necesita.
     *
     * Clientes y productos se acotan a las identificaciones y códigos que
     * aparecen realmente en el libro: una empresa con decenas de miles de
     * registros no puede traerlos todos por red para validar unas pocas
     * facturas. El resto de catálogos (tarifas, formas de pago, puntos, bodegas,
     * vendedores) son pequeños por naturaleza y se traen enteros.
     */
    private function precargarCatalogos(int $idEmpresa, Spreadsheet $libro): void
    {
        $identificaciones = $this->valoresDeColumna($libro, CargaFacturasEsquema::HOJA_FACTURAS, 2, true);
        $codigosProducto  = $this->valoresDeColumna($libro, CargaFacturasEsquema::HOJA_DETALLES, 1, false);

        $this->mapaClientes   = $this->repository->getMapaClientes($idEmpresa, $identificaciones);
        $this->identsBorradas = $this->repository->getIdentificacionesEliminadas($idEmpresa, $identificaciones);
        $this->mapaProductos  = $this->repository->getMapaProductos($idEmpresa, $codigosProducto);
        $this->mapaIva        = $this->repository->getMapaTarifasIva();
        $this->mapaFormasPago      = $this->repository->getMapaFormasPago();
        $this->mapaFormasPagoPorId = $this->repository->getMapaFormasPagoPorId();
        $this->mapaPuntos     = $this->repository->getMapaPuntosEmision($idEmpresa);
        $this->mapaBodegas    = $this->repository->getMapaBodegas($idEmpresa);
        $this->mapaVendedores = $this->repository->getMapaVendedores($idEmpresa);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Facturas
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaFacturas(Spreadsheet $libro, array &$informe, int $idEmpresa): array
    {
        $facturas = [];

        foreach ($this->leerFilas($libro, CargaFacturasEsquema::HOJA_FACTURAS) as $nFila => $celdas) {
            $errores = [];
            $avisos  = [];

            // NOMBRE_CLIENTE se lee pero no se usa: es una ayuda visual para quien
            // arma el Excel. Lo que identifica al cliente es la identificación.
            [$clave, $fecha, $identificacion, , $codEst, $codPto, $bodega, $vendedor,
             $diasCredito, $observaciones, $propina, $totalEsperado] = array_pad(
                array_map(fn($v) => $this->texto($v), $celdas), 12, ''
            );

            $clave = $this->texto($clave);
            if ($clave === '') {
                $this->registrarFila($informe, CargaFacturasEsquema::HOJA_FACTURAS, $nFila, '(sin ID)',
                    ['ID_FACTURA es obligatorio: identifica la factura y enlaza sus líneas.'], []);
                continue;
            }
            if (isset($facturas[$clave])) {
                $this->registrarFila($informe, CargaFacturasEsquema::HOJA_FACTURAS, $nFila, $clave,
                    ['ID_FACTURA "' . $clave . '" está repetido. Debe ser único dentro del archivo.'], []);
                continue;
            }

            // ── Fecha ────────────────────────────────────────────────────────
            $fechaNorm = $this->aFecha($fecha);
            if ($fechaNorm === false) {
                $errores[] = 'FECHA_EMISION no tiene un formato de fecha válido.';
                $fechaNorm = '';
            } else {
                $errores = array_merge($errores, $this->rules->validarFechaEmision($fechaNorm !== '' ? $fechaNorm : null));
            }

            // ── Punto de emisión ─────────────────────────────────────────────
            $codEst = $this->normalizarCodigo($codEst);
            $codPto = $this->normalizarCodigo($codPto);
            $punto  = null;

            if ($codEst === '' || $codPto === '') {
                $errores[] = 'ESTABLECIMIENTO y PUNTO_EMISION son obligatorios (códigos de 3 dígitos).';
            } else {
                $punto = $this->mapaPuntos[$codEst . '-' . $codPto] ?? null;
                if ($punto === null) {
                    $errores[] = 'No existe un punto de emisión activo ' . $codEst . '-' . $codPto
                        . ' en esta empresa. Consulte la hoja ' . CargaFacturasEsquema::HOJA_REF_PUNTOS . '.';
                } elseif (empty($punto['tiene_secuencial'])) {
                    $errores[] = 'El punto de emisión ' . $codEst . '-' . $codPto
                        . ' no tiene configurado el secuencial de "Facturas de venta". '
                        . 'Configúrelo antes de cargar.';
                }
            }

            // ── Cliente ──────────────────────────────────────────────────────
            $identificacion = preg_replace('/\s+/', '', $identificacion) ?? '';
            $idCliente      = null;
            $esConsumidorFinal = false;
            $idFormaPagoCliente = null;
            $nombreCliente  = '';

            if ($identificacion === '') {
                $errores[] = 'IDENTIFICACION_CLIENTE es obligatoria.';
            } elseif (isset($this->mapaClientes[$identificacion])) {
                $cli                = $this->mapaClientes[$identificacion];
                $idCliente          = $cli['id'];
                $esConsumidorFinal  = $cli['es_consumidor_final'];
                $idFormaPagoCliente = $cli['id_forma_pago_sri'];
                // El nombre se toma de la base, no del archivo: en el informe hay
                // que ver a quién se va a facturar de verdad.
                $nombreCliente      = $cli['nombre'];

                // El cliente existe, pero sus datos viajan tal cual al XML y nadie
                // los revisa después (el generador no valida). Un cliente migrado
                // o mal capturado tumba el envío al SRI cuando ya está numerado.
                $errorId = SriFichaTecnica::identificacionIncoherente($cli['tipo_id'], $identificacion);
                if ($errorId !== null) {
                    $errores[] = $errorId . ' Corríjalo en el módulo Clientes.';
                }
                $errorNombre = SriFichaTecnica::excedeLongitud(
                    'El nombre del cliente', (string) $cli['nombre'],
                    SriFichaTecnica::MAX_RAZON_SOCIAL_COMPRADOR
                );
                if ($errorNombre !== null) {
                    $errores[] = $errorNombre . ' Acórtelo en el módulo Clientes.';
                }
            } elseif ($identificacion === CargaFacturasEsquema::IDENTIFICACION_CONSUMIDOR_FINAL) {
                $errores[] = 'No hay un cliente Consumidor Final registrado en esta empresa. '
                    . 'Créelo una sola vez desde el módulo Clientes y vuelva a cargar.';
            } elseif (isset($this->identsBorradas[$identificacion])) {
                // Distinguir "eliminado" de "nunca existió" ahorra al usuario ir a
                // crear un cliente que en realidad ya está, solo que dado de baja.
                $errores[] = 'El cliente con identificación ' . $identificacion . ' está ELIMINADO. '
                    . 'Restáurelo desde el módulo Clientes antes de facturarle.';
            } else {
                $errores[] = 'No existe un cliente con la identificación ' . $identificacion
                    . ' en esta empresa. Regístrelo en el módulo Clientes y vuelva a subir el archivo.';
            }

            // ── Bodega y vendedor ────────────────────────────────────────────
            $idBodega = null;
            if ($bodega !== '') {
                $b = $this->mapaBodegas[mb_strtolower($bodega)] ?? null;
                if ($b === null) {
                    $errores[] = 'La bodega "' . $bodega . '" no existe en esta empresa.';
                } else {
                    $idBodega = $b['id'];
                }
            }

            $idVendedor = null;
            if ($vendedor !== '') {
                $v = $this->mapaVendedores[mb_strtolower($vendedor)] ?? null;
                if ($v === null) {
                    $avisos[] = 'El vendedor "' . $vendedor . '" no existe o está inactivo: la factura quedará sin vendedor.';
                } else {
                    $idVendedor = $v['id'];
                }
            }

            // La forma de pago no viene en el archivo: la resuelve resolverFormaPago()
            // desde el cliente o el establecimiento, una vez calculado el total.

            // ── Numéricos ────────────────────────────────────────────────────
            $dias = $this->aNumero($diasCredito);
            if ($dias === null || $dias < 0) {
                $errores[] = 'DIAS_CREDITO debe ser un número mayor o igual a cero.';
                $dias = 0.0;
            }

            $propinaNum = $this->aNumero($propina);
            if ($propinaNum === null || $propinaNum < 0) {
                $errores[] = 'PROPINA debe ser un número mayor o igual a cero.';
                $propinaNum = 0.0;
            }

            $totalEsperadoNum = ($totalEsperado === '') ? null : $this->aNumero($totalEsperado);
            if ($totalEsperado !== '' && $totalEsperadoNum === null) {
                $avisos[] = 'TOTAL_ESPERADO no es un número: se ignora el control de cuadre.';
            }

            $idEstablecimiento = $punto['id_establecimiento'] ?? 0;

            $facturas[$clave] = [
                'clave'                => $clave,
                'fila'                 => $nFila,
                'fecha_emision'        => $fechaNorm,
                'identificacion'       => $identificacion,
                'cliente_nombre'       => $nombreCliente,
                'id_cliente'           => $idCliente,
                'es_consumidor_final'  => $esConsumidorFinal,
                // Forma de pago preferida del cliente; base de la cascada que
                // resuelve resolverFormaPago().
                'id_forma_pago_cliente' => $idFormaPagoCliente,
                'id_establecimiento'   => $idEstablecimiento,
                'id_punto_emision'     => $punto['id_punto'] ?? 0,
                'establecimiento'      => $punto['establecimiento'] ?? $codEst,
                'punto_emision'        => $punto['punto_emision'] ?? $codPto,
                'id_bodega'            => $idBodega,
                'id_vendedor'          => $idVendedor,
                'dias_credito'         => (int) $dias,
                // Código aplicado y de dónde salió; lo llena resolverFormaPago().
                'forma_pago'           => '',
                'forma_pago_origen'    => '',
                'observaciones'        => $observaciones,
                'propina'              => round($propinaNum, 2),
                'total_esperado'       => $totalEsperadoNum,
                'detalles'             => [],
                'pagos'                => [],
                'info_adicional'       => [],
                'total_sin_impuestos'  => 0.0,
                'total_descuento'      => 0.0,
                'total_iva'            => 0.0,
                'importe_total'        => 0.0,
                'errores'              => $errores,
                'avisos'               => $avisos,
                // Cuántas filas de cada hoja hija tienen error. Se consolida en un
                // único mensaje al final, para no repetir en la cabecera el texto
                // que ya se muestra en la fila de la hoja donde está el problema.
                'errores_hijas'        => [],
            ];
        }

        return $facturas;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Detalles
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaDetalles(Spreadsheet $libro, array &$facturas, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaFacturasEsquema::HOJA_DETALLES) as $nFila => $celdas) {
            $errores = [];
            $avisos  = [];

            $clave       = $this->texto($celdas[0] ?? '');
            $codProducto = $this->texto($celdas[1] ?? '');
            $tipoBruto   = $this->texto($celdas[2] ?? '');
            $descripcion = $this->normalizarTexto($celdas[3] ?? '');
            $codigoIvaBruto = $this->texto($celdas[7] ?? '');
            $codigoIva      = $this->resolverCodigoCatalogo($codigoIvaBruto, $this->mapaIva);
            $lote        = $this->texto($celdas[8] ?? '');
            $nup         = $this->texto($celdas[10] ?? '');
            $infoAdic    = $this->normalizarTexto($celdas[11] ?? '');

            if ($clave === '' || !isset($facturas[$clave])) {
                $this->registrarFila($informe, CargaFacturasEsquema::HOJA_DETALLES, $nFila, $clave,
                    [$clave === ''
                        ? 'ID_FACTURA es obligatorio en cada línea.'
                        : 'ID_FACTURA "' . $clave . '" no existe en la hoja ' . CargaFacturasEsquema::HOJA_FACTURAS . '.'],
                    []);
                continue;
            }

            $cantidad  = $this->aNumero($celdas[4] ?? '');
            $precio    = $this->aNumero($celdas[5] ?? '');
            $descuento = $this->aNumero($celdas[6] ?? '');

            // ── Tipo de ítem ─────────────────────────────────────────────────
            // Solo decide cómo se CREA un código que aún no existe. Si el código
            // ya está en el catálogo, manda el catálogo.
            $tipoPedido = $this->rules->aTipoProduccion($tipoBruto);
            if ($tipoPedido === null) {
                $errores[] = 'TIPO debe ser "Producto" o "Servicio" (o quedar vacío, que se toma como Servicio).';
                $tipoPedido = CargaFacturasEsquema::TIPO_SERVICIO;
            }

            // ── Producto ─────────────────────────────────────────────────────
            $idProducto     = null;
            $esLibre        = false;
            $inventariable  = false;
            $tipoProduccion = CargaFacturasEsquema::TIPO_SERVICIO;

            if ($codProducto === '') {
                $esLibre = true;
                if ($tipoBruto !== '') {
                    $avisos[] = 'La línea no tiene CODIGO_PRODUCTO: se factura como ítem libre y TIPO se ignora.';
                }
            } else {
                $prod = $this->mapaProductos[mb_strtolower($codProducto)] ?? null;
                if ($prod !== null) {
                    $idProducto     = $prod['id'];
                    $inventariable  = $prod['inventariable'];
                    $tipoProduccion = $prod['tipo_produccion'];
                    if (!$prod['status']) {
                        $avisos[] = 'El producto "' . $codProducto . '" está inactivo pero se facturará igual.';
                    }
                    if ($descripcion === '') {
                        $descripcion = $prod['nombre'];
                    }
                    // El catálogo manda: desde aquí no se modifican productos que
                    // ya existen. Pero si el archivo dice otra cosa, conviene verlo.
                    if ($tipoBruto !== '' && $tipoPedido !== $tipoProduccion) {
                        $avisos[] = 'El código "' . $codProducto . '" ya existe en el catálogo como '
                            . $this->nombreTipo($tipoProduccion) . '; se ignora el TIPO del archivo.';
                    }
                } else {
                    // Alta al vuelo según TIPO. Un bien se crea CON control de
                    // inventario pero SIN existencias: el stock se ingresa aparte
                    // desde Cargas de Inventario.
                    $tipoProduccion = $tipoPedido;
                    $inventariable  = ($tipoPedido === CargaFacturasEsquema::TIPO_BIEN);

                    $avisos[] = 'El código "' . $codProducto . '" no existe: se creará como '
                        . $this->nombreTipo($tipoPedido)
                        . ($inventariable ? ' con inventario y stock en cero.' : '.');

                    $claveProd = mb_strtolower($codProducto);
                    if (!isset($this->productosNuevos[$claveProd])) {
                        $this->productosNuevos[$claveProd] = [
                            'codigo'          => $codProducto,
                            'nombre'          => $descripcion,
                            'precio_base'     => $precio ?? 0.0,
                            'codigo_iva'      => $codigoIva,
                            'tipo_produccion' => $tipoPedido,
                        ];
                    } elseif ($this->productosNuevos[$claveProd]['tipo_produccion'] !== $tipoPedido) {
                        // Un mismo código nuevo no puede crearse como bien en una
                        // línea y como servicio en otra.
                        $errores[] = 'El código "' . $codProducto . '" aparece en el archivo como '
                            . $this->nombreTipo($this->productosNuevos[$claveProd]['tipo_produccion'])
                            . ' y como ' . $this->nombreTipo($tipoPedido) . '. Debe ser siempre el mismo tipo.';
                    }
                }
            }

            $errores = array_merge($errores, $this->rules->validarLinea($cantidad, $precio, $descuento, $descripcion));

            // ── Formato exigido por la ficha técnica del SRI ─────────────────
            // El generador de XML no valida nada: lo que no se filtre aquí llega
            // al SRI y vuelve rechazado con la factura ya creada y numerada.
            foreach ([
                SriFichaTecnica::excedeLongitud('DESCRIPCION', $descripcion, SriFichaTecnica::MAX_DESCRIPCION_DETALLE),
                SriFichaTecnica::excedeLongitud('CODIGO_PRODUCTO', $codProducto, SriFichaTecnica::MAX_CODIGO_PRINCIPAL),
                $cantidad !== null
                    ? SriFichaTecnica::excedeDecimales('CANTIDAD', $cantidad, SriFichaTecnica::MAX_DECIMALES_CANTIDAD)
                    : null,
                $precio !== null
                    ? SriFichaTecnica::excedeDecimales('PRECIO_UNITARIO', $precio, SriFichaTecnica::MAX_DECIMALES_PRECIO)
                    : null,
            ] as $errorSri) {
                if ($errorSri !== null) {
                    $errores[] = $errorSri;
                }
            }

            // ── IVA ──────────────────────────────────────────────────────────
            $tarifa = null;
            if ($codigoIvaBruto === '') {
                $errores[] = 'CODIGO_IVA es obligatorio. Consulte la hoja ' . CargaFacturasEsquema::HOJA_REF_IVA . '.';
            } elseif ($codigoIva === '') {
                $errores[] = 'El CODIGO_IVA "' . $codigoIvaBruto . '" no existe. Consulte la hoja '
                    . CargaFacturasEsquema::HOJA_REF_IVA . '.';
            } else {
                $tarifa = $this->mapaIva[$codigoIva];
                if (!$tarifa['activa']) {
                    $avisos[] = 'La tarifa de IVA "' . $codigoIva . '" está derogada; se usará igual.';
                }
            }

            // ── Caducidad ────────────────────────────────────────────────────
            $caducidad = $this->aFecha($celdas[9] ?? '');
            if ($caducidad === false) {
                $errores[] = 'CADUCIDAD no tiene un formato de fecha válido.';
                $caducidad = '';
            }

            // ── Reglas del establecimiento ───────────────────────────────────
            $config = $this->getConfigEstablecimiento((int) $facturas[$clave]['id_establecimiento']);
            $toBool = static fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

            // Decimales configurados por la empresa. Se avisa, no se bloquea: el
            // documento se emite igual (el XML conserva los decimales reales hasta
            // el tope de 6 del SRI), pero conviene saber que el archivo trae más
            // precisión de la que la empresa usa en pantalla.
            $decCantidad = max(0, min(6, (int) ($config['decimales_cantidad'] ?? 2)));
            $decPrecio   = max(0, min(6, (int) ($config['decimales_precio']   ?? 2)));

            if ($cantidad !== null && SriFichaTecnica::decimales($cantidad) > $decCantidad) {
                $avisos[] = 'CANTIDAD tiene ' . SriFichaTecnica::decimales($cantidad)
                    . ' decimales y la empresa está configurada con ' . $decCantidad . '.';
            }
            if ($precio !== null && SriFichaTecnica::decimales($precio) > $decPrecio) {
                $avisos[] = 'PRECIO_UNITARIO tiene ' . SriFichaTecnica::decimales($precio)
                    . ' decimales y la empresa está configurada con ' . $decPrecio . '.';
            }

            if ($esLibre && !$toBool($config['facturacion_libre'] ?? true)) {
                $errores[] = 'Este establecimiento no permite ítems libres: CODIGO_PRODUCTO es obligatorio.';
            }

            // Lote/caducidad/NUP y bodega aplican a todo bien inventariable: tanto
            // los del catálogo como los que esta carga va a crear como Producto.
            if (!$esLibre && $inventariable && $tipoProduccion !== CargaFacturasEsquema::TIPO_SERVICIO) {
                if ($toBool($config['obligatorio_lotes'] ?? false) && $lote === '') {
                    $errores[] = 'LOTE es obligatorio para "' . $descripcion . '" (producto inventariable).';
                }
                if ($toBool($config['obligatorio_caducidad'] ?? false) && $caducidad === '') {
                    $errores[] = 'CADUCIDAD es obligatoria para "' . $descripcion . '" (producto inventariable).';
                }
                if ($toBool($config['obligatorio_nup'] ?? false) && $nup === '') {
                    $errores[] = 'NUP es obligatorio para "' . $descripcion . '" (producto inventariable).';
                }
                if ($facturas[$clave]['id_bodega'] === null) {
                    $errores[] = 'BODEGA es obligatoria: la factura incluye productos que mueven inventario.';
                }

                // Un Producto nuevo nace en cero. Si el establecimiento descuenta
                // inventario y no admite negativos, esa línea no se podrá facturar:
                // se avisa aquí y no al aplicar, cuando ya sería tarde.
                if ($idProducto === null
                    && $toBool($config['facturacion_inventario'] ?? false)
                    && $toBool($config['factura_solo_stock_positivo'] ?? false)) {
                    $errores[] = 'El código "' . $codProducto . '" es nuevo y se crearía como Producto '
                        . 'con stock cero, pero este establecimiento no permite facturar sin existencias. '
                        . 'Créelo e ingrese su stock antes, o use TIPO = Servicio.';
                }
            }

            // ── Importes de la línea ─────────────────────────────────────────
            $cant = $cantidad ?? 0.0;
            $pu   = $precio   ?? 0.0;
            $desc = $descuento ?? 0.0;
            $base = round(round($cant * $pu, 2) - round($desc, 2), 2);
            $pct  = $tarifa['porcentaje_iva'] ?? 0.0;
            $iva  = round($base * $pct / 100, 2);

            // Los mensajes se reportan UNA sola vez, en la fila de la hoja donde
            // está el problema. La factura solo lleva la cuenta, para poder decir
            // por qué queda bloqueada sin repetir todo el texto.
            $this->registrarFila($informe, CargaFacturasEsquema::HOJA_DETALLES, $nFila, $clave, $errores, $avisos);
            if ($errores) {
                $facturas[$clave]['errores_hijas'][CargaFacturasEsquema::HOJA_DETALLES] =
                    ($facturas[$clave]['errores_hijas'][CargaFacturasEsquema::HOJA_DETALLES] ?? 0) + 1;
            }

            $facturas[$clave]['detalles'][] = [
                'fila'                      => $nFila,
                'codigo_producto'           => $codProducto,
                'id_producto'               => $idProducto,
                'es_libre'                  => $esLibre,
                'descripcion'               => $descripcion,
                'cantidad'                  => $cant,
                'precio_unitario'           => $pu,
                'descuento'                 => round($desc, 2),
                'codigo_iva'                => $codigoIva,
                'id_tarifa_iva'             => $tarifa['id'] ?? 0,
                'porcentaje_iva'            => $pct,
                'precio_total_sin_impuesto' => $base,
                'valor_iva'                 => $iva,
                'lote'                      => $lote,
                'caducidad'                 => $caducidad,
                'nup'                       => $nup,
                'info_adicional'            => $infoAdic,
                'inventariable'             => $inventariable,
                'tipo_produccion'           => $tipoProduccion,
            ];
        }

        // Una factura sin líneas no se puede emitir.
        foreach ($facturas as $clave => $f) {
            if (!$f['detalles']) {
                $facturas[$clave]['errores'][] = 'La factura no tiene ninguna línea en la hoja '
                    . CargaFacturasEsquema::HOJA_DETALLES . '.';
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas Pagos e Info_Adicional
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaInfoAdicional(Spreadsheet $libro, array &$facturas, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaFacturasEsquema::HOJA_INFO_ADICIONAL) as $nFila => $celdas) {
            $clave  = $this->texto($celdas[0] ?? '');
            $nombre = $this->normalizarTexto($celdas[1] ?? '');
            $valor  = $this->normalizarTexto($celdas[2] ?? '');

            if ($clave === '' || !isset($facturas[$clave])) {
                $this->registrarFila($informe, CargaFacturasEsquema::HOJA_INFO_ADICIONAL, $nFila, $clave,
                    [$clave === ''
                        ? 'ID_FACTURA es obligatorio en cada línea de información adicional.'
                        : 'ID_FACTURA "' . $clave . '" no existe en la hoja ' . CargaFacturasEsquema::HOJA_FACTURAS . '.'],
                    []);
                continue;
            }

            $errores = [];
            if ($nombre === '' || $valor === '') {
                $errores[] = 'NOMBRE y VALOR son obligatorios en la información adicional.';
            }
            foreach ([
                SriFichaTecnica::excedeLongitud('NOMBRE', $nombre, SriFichaTecnica::MAX_INFO_ADICIONAL_NOMBRE),
                SriFichaTecnica::excedeLongitud('VALOR', $valor, SriFichaTecnica::MAX_INFO_ADICIONAL_VALOR),
            ] as $errorSri) {
                if ($errorSri !== null) {
                    $errores[] = $errorSri;
                }
            }

            if ($errores) {
                $this->registrarFila($informe, CargaFacturasEsquema::HOJA_INFO_ADICIONAL, $nFila, $clave, $errores, []);
                $facturas[$clave]['errores_hijas'][CargaFacturasEsquema::HOJA_INFO_ADICIONAL] =
                    ($facturas[$clave]['errores_hijas'][CargaFacturasEsquema::HOJA_INFO_ADICIONAL] ?? 0) + 1;
                continue;
            }

            $facturas[$clave]['info_adicional'][] = ['nombre' => $nombre, 'valor' => $valor];
        }

        // El SRI admite 15 campoAdicional como máximo, y el sistema añade dos por
        // su cuenta al crear la factura: el correo del cliente y el RUC del
        // proveedor (Res. NAC-DGERCGC26-00000027). Si se pasa, SriProveedorHelper
        // RECORTA los últimos en silencio: mejor avisar antes de perder datos.
        $margen = SriFichaTecnica::MAX_CAMPOS_ADICIONALES - 2;
        foreach ($facturas as $clave => $f) {
            if (count($f['info_adicional']) > $margen) {
                $facturas[$clave]['errores'][] = 'La factura tiene ' . count($f['info_adicional'])
                    . ' campos de información adicional. El SRI admite ' . SriFichaTecnica::MAX_CAMPOS_ADICIONALES
                    . ' y el sistema reserva 2 (correo del cliente y RUC del proveedor), '
                    . 'así que puede escribir hasta ' . $margen . '.';
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cierres que necesitan la factura completa
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convierte los contadores de filas hijas con error en un único mensaje por
     * factura. Así la factura queda bloqueada, pero el detalle del problema se
     * lee una sola vez, en la fila de la hoja donde realmente está.
     */
    private function consolidarErroresDeHijas(array &$facturas): void
    {
        foreach ($facturas as $clave => $f) {
            foreach ($f['errores_hijas'] as $hoja => $cuantas) {
                $facturas[$clave]['errores'][] = $cuantas === 1
                    ? 'Tiene 1 fila con errores en la hoja ' . $hoja . '.'
                    : 'Tiene ' . $cuantas . ' filas con errores en la hoja ' . $hoja . '.';
            }
        }
    }

    /**
     * Calcula los totales respetando cómo tiene configurada la empresa el
     * cálculo del IVA (`empresa_establecimiento.calculo_iva_facturacion`), con
     * el mismo algoritmo que la pantalla de Factura de Venta (`calcTotales()`
     * en la vista del módulo):
     *
     *   - `linea_linea`: se redondea el IVA de cada línea y se suman.
     *   - `subtotal`   : se acumula la base por tarifa y el IVA se calcula sobre
     *                    esa base, una sola vez.
     *
     * La diferencia entre ambos modos es de céntimos por línea, pero se acumula:
     * en una factura de 400 líneas llegó a 15 centavos, suficiente para que el
     * control de cuadre bloqueara la carga.
     *
     * En modo `subtotal` el IVA de las líneas se REAJUSTA para que su suma sea
     * exactamente el IVA del grupo. Si no, el XML llevaría un IVA por línea que
     * no cuadra con el importe total del comprobante y el SRI lo rechazaría
     * (XmlFacturaVentaService solo absorbe desfases de hasta 5 centavos).
     */
    private function calcularTotales(array &$facturas): void
    {
        foreach ($facturas as $clave => $f) {
            $config  = $this->getConfigEstablecimiento((int) $f['id_establecimiento']);
            $modoIva = ($config['calculo_iva_facturacion'] ?? 'linea_linea') === 'subtotal'
                ? 'subtotal'
                : 'linea_linea';

            $sinImp = 0.0;
            $desc   = 0.0;

            // Agrupar por tarifa: el IVA se calcula por grupo, como en la pantalla.
            $grupos = [];
            foreach ($f['detalles'] as $i => $d) {
                $sinImp += $d['precio_total_sin_impuesto'];
                $desc   += $d['descuento'];

                $codigo = (string) $d['codigo_iva'];
                if (!isset($grupos[$codigo])) {
                    $grupos[$codigo] = ['pct' => $d['porcentaje_iva'], 'base' => 0.0, 'iva' => 0.0, 'lineas' => []];
                }
                $grupos[$codigo]['base']    += $d['precio_total_sin_impuesto'];
                $grupos[$codigo]['iva']     += $d['valor_iva'];
                $grupos[$codigo]['lineas'][] = $i;
            }

            $iva = 0.0;
            foreach ($grupos as $g) {
                if ($modoIva === 'subtotal') {
                    $ivaGrupo = round(round($g['base'], 2) * $g['pct'] / 100, 2);

                    // Repartir el desfase en la línea de mayor base del grupo, para
                    // que la suma de los impuestos por línea dé exactamente esto.
                    $desfase = round($ivaGrupo - round($g['iva'], 2), 2);
                    if (abs($desfase) >= 0.01 && $g['lineas']) {
                        $iMax = $g['lineas'][0];
                        foreach ($g['lineas'] as $i) {
                            if ($f['detalles'][$i]['precio_total_sin_impuesto']
                                > $f['detalles'][$iMax]['precio_total_sin_impuesto']) {
                                $iMax = $i;
                            }
                        }
                        $ajustada = round($facturas[$clave]['detalles'][$iMax]['valor_iva'] + $desfase, 2);
                        $facturas[$clave]['detalles'][$iMax]['valor_iva'] = $ajustada;
                    }
                    $iva += $ivaGrupo;
                } else {
                    $iva += round($g['iva'], 2);
                }
            }

            $sinImp = round($sinImp, 2);
            $iva    = round($iva, 2);
            $total  = round($sinImp + $iva + $f['propina'], 2);

            $facturas[$clave]['total_sin_impuestos'] = $sinImp;
            $facturas[$clave]['total_descuento']     = round($desc, 2);
            $facturas[$clave]['total_iva']           = $iva;
            $facturas[$clave]['importe_total']       = $total;

            if ($total < 0) {
                $facturas[$clave]['errores'][] = 'El total de la factura es negativo (' . number_format($total, 2) . ').';
            }

            // Control de cuadre contra el valor declarado en el Excel.
            if ($f['total_esperado'] !== null && abs(round($f['total_esperado'], 2) - $total) > 0.009) {
                $facturas[$clave]['errores'][] = 'El total calculado ($' . number_format($total, 2)
                    . ') no coincide con TOTAL_ESPERADO ($' . number_format($f['total_esperado'], 2)
                    . '). Revise cantidades, precios, descuentos e IVA.';
            }
        }
    }

    /**
     * Asigna a cada factura su ÚNICA forma de pago, por el total del documento.
     *
     * Se replica la misma precedencia que aplica la pantalla de Factura de Venta
     * al elegir cliente (ver `seleccionarCliente()` en la vista del módulo):
     *
     *   1. La forma de pago configurada en la ficha del CLIENTE.
     *   2. La configurada en el ESTABLECIMIENTO (`id_forma_pago_sri_def`).
     *
     * La pantalla intercala el FAVORITO del usuario entre las dos; aquí no
     * aplica: el favorito es una preferencia guardada para el módulo Factura de
     * Venta, no para una carga que puede ejecutar cualquier usuario.
     *
     * Cliente y establecimiento guardan un ID de formas_pago_sri; el documento
     * necesita el CÓDIGO, así que se traduce con el mapa por id.
     */
    private function resolverFormaPago(array &$facturas): void
    {
        foreach ($facturas as $clave => $f) {
            $codigo = '';
            $origen = '';

            $config   = $this->getConfigEstablecimiento((int) $f['id_establecimiento']);
            $idDefEst = !empty($config['id_forma_pago_sri_def']) ? (int) $config['id_forma_pago_sri_def'] : null;

            $candidatos = [
                'cliente'         => $f['id_forma_pago_cliente'],
                'establecimiento' => $idDefEst,
            ];

            foreach ($candidatos as $nombreOrigen => $idFp) {
                if (!$idFp) {
                    continue;
                }
                $fp = $this->mapaFormasPagoPorId[$idFp] ?? null;
                if ($fp === null) {
                    continue;
                }
                if (!$fp['activa']) {
                    $facturas[$clave]['avisos'][] = 'La forma de pago configurada en el '
                        . $nombreOrigen . ' ("' . $fp['nombre'] . '") está inactiva; se usará igual.';
                }
                $codigo = $fp['codigo'];
                $origen = $nombreOrigen;
                break;
            }

            if ($codigo === '') {
                $facturas[$clave]['errores'][] = 'No hay forma de pago: ni el cliente ni el '
                    . 'establecimiento tienen una configurada. Asígnela en la ficha del cliente '
                    . 'o en la configuración del establecimiento.';
                continue;
            }

            $facturas[$clave]['forma_pago']        = $codigo;
            $facturas[$clave]['forma_pago_origen'] = $origen;
            $facturas[$clave]['pagos'] = [[
                'forma_pago'    => $codigo,
                'total'         => $f['importe_total'],
                'plazo'         => $f['dias_credito'],
                'unidad_tiempo' => 'dias',
            ]];
        }
    }

    /**
     * Límite de consumidor final del establecimiento: la misma regla que aplica
     * FacturaVentaRules, adelantada aquí para informarla antes de escribir.
     */
    private function validarLimiteConsumidorFinal(array &$facturas): void
    {
        foreach ($facturas as $clave => $f) {
            if (!$f['es_consumidor_final']) {
                continue;
            }
            $config = $this->getConfigEstablecimiento((int) $f['id_establecimiento']);
            $limite = (float) ($config['valor_limite_consumidor_final'] ?? 50);

            if ($f['importe_total'] >= $limite) {
                $facturas[$clave]['errores'][] = 'Para ventas mayores o iguales a $'
                    . number_format($limite, 2) . ' no se permite el uso de Consumidor Final.';
            }
        }
    }

    /**
     * Detecta facturas que ya existen en el sistema.
     *
     * El control por hash del archivo solo ve el libro byte a byte idéntico; en
     * cuanto alguien lo abre y lo vuelve a guardar, Excel cambia los metadatos y
     * el hash deja de coincidir. Esta segunda capa mira el CONTENIDO: una factura
     * emitida al mismo cliente, con la misma fecha, el mismo total y el mismo
     * número de líneas es, casi con certeza, la misma que se está recargando.
     *
     * Se bloquea en vez de avisar porque el daño es asimétrico: un duplicado es
     * un documento fiscal de más que hay que anular, mientras que un falso
     * positivo solo obliga a quitar esa fila del archivo. El mensaje nombra la
     * factura existente para que se pueda comprobar en un segundo.
     */
    private function validarNoDuplicadas(array &$facturas, int $idEmpresa): void
    {
        $idsCliente = [];
        $fechas     = [];
        foreach ($facturas as $f) {
            if ($f['errores'] || !$f['id_cliente']) {
                continue;
            }
            $idsCliente[] = (int) $f['id_cliente'];
            $fechas[]     = (string) $f['fecha_emision'];
        }

        if (!$idsCliente) {
            return;
        }

        $existentes = $this->repository->getFacturasExistentesPorClienteFecha($idsCliente, $fechas, $idEmpresa);
        if (!$existentes) {
            return;
        }

        // Indexar por cliente|fecha|total|nº de líneas.
        $indice = [];
        foreach ($existentes as $e) {
            $clave = (int) $e['id_cliente'] . '|' . substr((string) $e['fecha_emision'], 0, 10)
                . '|' . number_format((float) $e['importe_total'], 2, '.', '')
                . '|' . (int) $e['n_lineas'];

            $indice[$clave][] = trim(($e['establecimiento'] ?? '') . '-' . ($e['punto_emision'] ?? '')
                . '-' . ($e['secuencial'] ?? ''), '-')
                . ' (' . ($e['estado'] ?? '') . ')';
        }

        foreach ($facturas as $clave => $f) {
            if ($f['errores'] || !$f['id_cliente']) {
                continue;
            }
            $k = (int) $f['id_cliente'] . '|' . $f['fecha_emision']
                . '|' . number_format($f['importe_total'], 2, '.', '')
                . '|' . count($f['detalles']);

            if (isset($indice[$k])) {
                $facturas[$clave]['errores'][] = 'Ya existe una factura igual para este cliente, '
                    . 'con la misma fecha, el mismo total y el mismo número de líneas: '
                    . implode(', ', $indice[$k]) . '. Si de verdad hay que emitirla otra vez, '
                    . 'hágalo desde Facturas de Venta; si no, quite esta fila del archivo.';
            }
        }
    }

    /**
     * Stock agregado de TODO el archivo.
     *
     * Es la validación que no existe en el modal: una a una, veinte facturas del
     * mismo producto pasan el control de stock; sumadas, no. Se agrupa por
     * producto y bodega (no por lote) porque lo que se puede afirmar sin dudas
     * es que el total pedido no cabe en el saldo total. El saldo por lote lo
     * sigue verificando FacturaVentaService al aplicar, con su propio candado.
     */
    private function validarStockAgregado(array &$facturas, int $idEmpresa): void
    {
        // [id_bodega][id_producto] => ['cantidad' => x, 'nombre' => y, 'claves' => []]
        $requerido = [];

        foreach ($facturas as $clave => $f) {
            if ($f['errores']) {
                continue; // ya bloqueada: no consumirá stock
            }
            $config = $this->getConfigEstablecimiento((int) $f['id_establecimiento']);
            $toBool = static fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');

            if (!$toBool($config['facturacion_inventario'] ?? false)) {
                continue; // el establecimiento no descuenta inventario al facturar
            }
            if (!$toBool($config['factura_solo_stock_positivo'] ?? false)) {
                continue; // permite stock negativo: no hay nada que bloquear
            }

            $idBodega = (int) ($f['id_bodega'] ?? 0);
            if ($idBodega <= 0) {
                continue;
            }

            foreach ($f['detalles'] as $d) {
                if ($d['id_producto'] === null || !$d['inventariable'] || $d['tipo_produccion'] === '02') {
                    continue;
                }
                $idP = (int) $d['id_producto'];
                if (!isset($requerido[$idBodega][$idP])) {
                    $requerido[$idBodega][$idP] = ['cantidad' => 0.0, 'nombre' => $d['descripcion'], 'claves' => []];
                }
                $requerido[$idBodega][$idP]['cantidad'] += $d['cantidad'];
                $requerido[$idBodega][$idP]['claves'][$clave] = true;
            }
        }

        foreach ($requerido as $idBodega => $porProducto) {
            $stock = $this->repository->getStockPorProductos(array_keys($porProducto), (int) $idBodega, $idEmpresa);

            foreach ($porProducto as $idProducto => $info) {
                $disponible = $stock[$idProducto] ?? 0.0;
                if ($info['cantidad'] <= $disponible + 1e-9) {
                    continue;
                }

                $mensaje = 'Stock insuficiente para "' . $info['nombre'] . '": el archivo pide '
                    . rtrim(rtrim(number_format($info['cantidad'], 4, '.', ''), '0'), '.')
                    . ' entre todas sus facturas y el saldo disponible es '
                    . rtrim(rtrim(number_format($disponible, 4, '.', ''), '0'), '.') . '.';

                foreach (array_keys($info['claves']) as $clave) {
                    $facturas[$clave]['errores'][] = $mensaje;
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────────────────

    private function getConfigEstablecimiento(int $idEstablecimiento): array
    {
        if ($idEstablecimiento <= 0) {
            return [];
        }
        if (!array_key_exists($idEstablecimiento, $this->configEstablecimientos)) {
            $this->configEstablecimientos[$idEstablecimiento] =
                $this->empresaRepository->getEstablecimientoConfig($idEstablecimiento) ?? [];
        }
        return $this->configEstablecimientos[$idEstablecimiento];
    }

    /** Nombre legible de un tipo_produccion, para los mensajes. */
    private function nombreTipo(string $tipoProduccion): string
    {
        return $tipoProduccion === CargaFacturasEsquema::TIPO_BIEN ? 'Producto' : 'Servicio';
    }

    private function registrarFila(array &$informe, string $hoja, int $nFila, string $clave, array $errores, array $avisos): void
    {
        if (!$errores && !$avisos) {
            return;
        }
        $informe['filas'][] = [
            'hoja'    => $hoja,
            'fila'    => $nFila,
            'clave'   => $clave,
            'errores' => $errores,
            'avisos'  => $avisos,
        ];
    }

    /**
     * Valores distintos de una columna de una hoja (índice 0 = primera columna).
     * Se usa para saber qué clientes y productos hay que traer de la base.
     *
     * @param bool $quitarEspacios Para identificaciones, que se normalizan sin
     *                             espacios. Los códigos de producto NO: pueden
     *                             llevar espacios internos legítimos y hay que
     *                             buscarlos tal como el validador los leerá.
     * @return string[]
     */
    private function valoresDeColumna(Spreadsheet $libro, string $nombreHoja, int $indice, bool $quitarEspacios): array
    {
        $valores = [];
        foreach ($this->leerFilas($libro, $nombreHoja) as $celdas) {
            $v = $this->texto($celdas[$indice] ?? '');
            if ($quitarEspacios) {
                $v = preg_replace('/\s+/', '', $v) ?? '';
            }
            if ($v !== '') {
                $valores[$v] = true;
            }
        }
        return array_keys($valores);
    }

    private function leerFilas(Spreadsheet $libro, string $nombreHoja): array
    {
        $hoja = $libro->getSheetByName($nombreHoja);
        if ($hoja === null) {
            return [];
        }

        $nColumnas = count(CargaFacturasEsquema::columnas($nombreHoja));
        $filas = [];

        foreach ($hoja->toArray(null, true, false, false) as $i => $celdas) {
            if ($i === 0) {
                continue; // encabezado
            }
            $celdas = array_slice($celdas, 0, $nColumnas);

            $tieneDatos = false;
            foreach ($celdas as $v) {
                if (trim((string) $v) !== '') {
                    $tieneDatos = true;
                    break;
                }
            }
            if ($tieneDatos) {
                $filas[$i + 1] = $celdas;
            }
        }

        return $filas;
    }

    /** Limpia caracteres de control y recorta. */
    private function texto($valor): string
    {
        $v = (string) $valor;
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
        return trim($v);
    }

    /**
     * Igual que texto(), pero además colapsa espacios internos: es lo que hace
     * FacturaVentaService con las descripciones antes de guardarlas.
     */
    private function normalizarTexto($valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->texto($valor)) ?? '');
    }

    /**
     * Normaliza un código de establecimiento o punto de emisión a 3 dígitos.
     * Excel puede haber convertido "001" en el número 1.
     */
    private function normalizarCodigo(string $valor): string
    {
        $v = trim($valor);
        if ($v === '') {
            return '';
        }
        // Un número entero se rellena a 3 dígitos; el resto se deja tal cual.
        if (preg_match('/^\d{1,3}$/', $v)) {
            return str_pad($v, 3, '0', STR_PAD_LEFT);
        }
        return $v;
    }

    /**
     * Resuelve un código contra el catálogo que lo define, tolerando que Excel
     * haya perdido los ceros a la izquierda.
     *
     * No se puede rellenar a un ancho fijo a ciegas: los códigos de IVA tienen un
     * dígito ("0", "2", "4") y los de forma de pago dos ("01", "20"). Se busca el
     * valor tal cual y, si no aparece, se prueba rellenándolo hasta el ancho de
     * las claves del propio catálogo.
     *
     * @return string El código tal como está en el catálogo, o '' si no existe.
     */
    private function resolverCodigoCatalogo(string $valor, array $catalogo): string
    {
        $v = trim($valor);
        if ($v === '' || !$catalogo) {
            return '';
        }
        if (isset($catalogo[$v])) {
            return $v;
        }
        if (!ctype_digit($v)) {
            return '';
        }

        // Anchos presentes en el catálogo, del más corto al más largo.
        $anchos = array_unique(array_map('strlen', array_keys($catalogo)));
        sort($anchos);

        foreach ($anchos as $ancho) {
            if ($ancho <= strlen($v)) {
                continue;
            }
            $candidato = str_pad($v, $ancho, '0', STR_PAD_LEFT);
            if (isset($catalogo[$candidato])) {
                return $candidato;
            }
        }

        return '';
    }

    /** Convierte a float; null si no es numérico. Vacío = 0. */
    private function aNumero($valor): ?float
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return 0.0;
        }
        // Tolerar separador de miles y coma decimal.
        $v = str_replace([' ', "\u{00A0}"], '', $v);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d+$/', $v)) {
            $v = str_replace('.', '', $v);
        }
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Normaliza a 'Y-m-d'.
     * Devuelve '' si viene vacío y false si el formato es inválido.
     *
     * @return string|false
     */
    private function aFecha($valor)
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return '';
        }

        // Excel puede entregar la fecha como número de serie.
        if (is_numeric($v)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return false;
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d H:i:s'] as $formato) {
            $dt = \DateTime::createFromFormat($formato, $v);
            if ($dt !== false && $dt->format($formato) === $v) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($v);
        return $ts !== false ? date('Y-m-d', $ts) : false;
    }
}

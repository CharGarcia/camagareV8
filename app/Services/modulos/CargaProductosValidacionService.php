<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Rules\modulos\CargaProductosRules;
use App\repositories\modulos\CargaProductosRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Exception;

/**
 * Valida un libro de Excel de carga de productos SIN escribir nada en la base.
 *
 * Devuelve un informe con el resumen, los errores por fila y el payload ya
 * normalizado de cada producto, listo para que la fase de aplicación lo entregue
 * a ProductoService::crear()/actualizar().
 */
class CargaProductosValidacionService
{
    private CargaProductosRepository $repository;
    private CargaProductosRules $rules;

    /** Catálogos precargados (se llenan en validar()). */
    private array $mapaProductos     = [];
    private array $mapaIva           = [];
    private array $mapaMedidas       = [];
    private array $mapaCategorias    = [];
    private array $mapaMarcas        = [];
    private array $mapaBodegas       = [];
    private array $mapaIce           = [];
    private array $mapaProveedores   = [];
    private array $idsUsados         = [];
    private array $mapaCodigosBarras = [];

    public function __construct(
        CargaProductosRepository $repository,
        CargaProductosRules $rules
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
    }

    /**
     * Valida el archivo completo.
     *
     * @param string $rutaArchivo Ruta al .xlsx subido.
     * @param int    $idEmpresa   Empresa activa (destino de la carga).
     * @return array Informe de validación.
     */
    public function validar(string $rutaArchivo, int $idEmpresa): array
    {
        $informe = [
            'ok'               => false,
            'errores_globales' => [],
            'resumen'          => [
                'total_productos'  => 0,
                'crear'            => 0,
                'actualizar'       => 0,
                'bloqueados'       => 0, // productos que NO se aplicarán por errores
                'filas_con_error'  => 0, // filas con error en todas las hojas
                'con_aviso'        => 0,
            ],
            'filas'     => [],
            'productos' => [],
        ];

        if (!is_file($rutaArchivo)) {
            $informe['errores_globales'][] = 'No se encontró el archivo subido.';
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

        $this->precargarCatalogos($idEmpresa);

        // 4. Hoja Productos.
        $productos = $this->procesarHojaProductos($libro, $informe);

        if (!$productos) {
            $informe['errores_globales'][] = 'La hoja "' . CargaProductosEsquema::HOJA_PRODUCTOS
                . '" no tiene ninguna fila con datos.';
            return $informe;
        }

        // 5. Hojas hijas (solo tocan los productos que aparecen en ellas).
        $this->procesarHojaPrecios($libro, $productos, $informe);
        $this->procesarHojaVariantes($libro, $productos, $informe);
        $this->procesarHojaComponentes($libro, $productos, $informe);
        $this->procesarHojaStockBodegas($libro, $productos, $informe);
        $this->procesarHojaHomologaciones($libro, $productos, $informe);

        // 6. Resumen.
        //    - crear/actualizar/bloqueados se cuentan sobre los PRODUCTOS, cuyo
        //      array 'errores' ya incorpora los errores de sus hojas hijas.
        //    - filas_con_error cuenta las filas del informe, que es lo que ve el
        //      usuario en pantalla (una fila puede tener varios mensajes).
        foreach ($informe['filas'] as $fila) {
            if ($fila['errores']) {
                $informe['resumen']['filas_con_error']++;
            }
            if ($fila['avisos']) {
                $informe['resumen']['con_aviso']++;
            }
        }

        foreach ($productos as $p) {
            $informe['resumen']['total_productos']++;
            if ($p['errores']) {
                $informe['resumen']['bloqueados']++;
            } elseif ($p['accion'] === 'crear') {
                $informe['resumen']['crear']++;
            } else {
                $informe['resumen']['actualizar']++;
            }
        }

        $informe['productos'] = $productos;

        // 'ok' = el archivo entero está limpio. Aunque sea false, la aplicación
        // puede procesar los productos sin errores (aplicación parcial).
        $informe['ok'] = ($informe['resumen']['filas_con_error'] === 0);

        return $informe;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validaciones estructurales del libro
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * El usuario no puede borrar ni agregar hojas: el conjunto debe ser exacto.
     * @return string[]
     */
    private function validarHojas(Spreadsheet $libro): array
    {
        $esperadas = CargaProductosEsquema::todasLasHojas();
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
        $hoja = $libro->getSheetByName(CargaProductosEsquema::HOJA_CONFIG);
        if ($hoja === null) {
            return 'El archivo no tiene la hoja de control ' . CargaProductosEsquema::HOJA_CONFIG . '.';
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

        foreach (CargaProductosEsquema::hojasDatos() as $nombreHoja => $def) {
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

    private function precargarCatalogos(int $idEmpresa): void
    {
        $this->mapaProductos     = $this->repository->getMapaProductos($idEmpresa);
        $this->mapaIva           = $this->repository->getMapaTarifasIva();
        $this->mapaMedidas       = $this->repository->getMapaUnidadesMedida($idEmpresa);
        $this->mapaCategorias    = $this->repository->getMapaCategorias($idEmpresa);
        $this->mapaMarcas        = $this->repository->getMapaMarcas($idEmpresa);
        $this->mapaBodegas       = $this->repository->getMapaBodegas($idEmpresa);
        $this->mapaIce           = $this->repository->getMapaIce($idEmpresa);
        $this->mapaProveedores   = $this->repository->getMapaProveedores($idEmpresa);
        $this->idsUsados         = $this->repository->getIdsUsadosEnDocumentos($idEmpresa);
        $this->mapaCodigosBarras = $this->repository->getMapaCodigosBarras($idEmpresa);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Productos
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaProductos(Spreadsheet $libro, array &$informe): array
    {
        $filas = $this->leerFilas($libro, CargaProductosEsquema::HOJA_PRODUCTOS);
        $productos = [];
        $vistosEnArchivo = [];

        foreach ($filas as $nFila => $celdas) {
            $f = [
                'codigo'          => $this->texto($celdas[0] ?? ''),
                'nombre'          => $this->texto($celdas[1] ?? ''),
                'tipo_produccion' => $this->aTipoProduccion($celdas[2] ?? ''),
                'codigo_auxiliar' => $this->texto($celdas[3] ?? ''),
                'codigo_barras'   => $this->texto($celdas[4] ?? ''),
                'precio_base'     => $this->aNumero($celdas[5] ?? ''),
                'costo_producto'  => $this->aNumero($celdas[6] ?? ''),
                'codigo_iva'      => $this->texto($celdas[7] ?? ''),
                'codigo_medida'   => $this->texto($celdas[8] ?? ''),
                'categoria'       => $this->texto($celdas[9] ?? ''),
                'marca'           => $this->texto($celdas[10] ?? ''),
                'inventariable'   => $this->aBooleano($celdas[11] ?? ''),
                'stock_minimo'    => $this->aNumero($celdas[12] ?? ''),
                'stock_maximo'    => $this->aNumero($celdas[13] ?? ''),
                'aplica_compra'   => $this->aBooleano($celdas[14] ?? ''),
                'aplica_venta'    => $this->aBooleano($celdas[15] ?? ''),
                'codigo_ice'      => $this->texto($celdas[16] ?? ''),
                'estado'          => $this->aEstado($celdas[17] ?? ''),
            ];

            // Un servicio nunca es inventariable: se normaliza antes de validar,
            // igual que hace ProductoService.
            if ($f['tipo_produccion'] === CargaProductosEsquema::TIPO_SERVICIO
                && $f['inventariable'] === null) {
                $f['inventariable'] = false;
            }

            $errores = $this->rules->validarProducto($f);
            $avisos  = [];

            $clave = mb_strtolower($f['codigo']);

            // Duplicado dentro del propio archivo.
            if ($f['codigo'] !== '') {
                if (isset($vistosEnArchivo[$clave])) {
                    $errores[] = 'CODIGO repetido en el archivo (ya aparece en la fila '
                        . $vistosEnArchivo[$clave] . ').';
                } else {
                    $vistosEnArchivo[$clave] = $nFila;
                }
            }

            // Resolución de catálogos.
            $f['tarifa_iva'] = null;
            if ($f['codigo_iva'] === '') {
                $errores[] = 'CODIGO_IVA está vacío. Si el producto ya existía, es porque no tiene'
                    . ' una tarifa de IVA válida asignada; elija una de la hoja '
                    . CargaProductosEsquema::HOJA_REF_IVA . '.';
            } elseif (!isset($this->mapaIva[$f['codigo_iva']])) {
                $errores[] = 'El CODIGO_IVA "' . $f['codigo_iva'] . '" no existe (vea la hoja '
                    . CargaProductosEsquema::HOJA_REF_IVA . ').';
            } else {
                $f['tarifa_iva'] = $this->mapaIva[$f['codigo_iva']]['id'];
                // Las tarifas derogadas (12%, 14%) siguen siendo válidas para
                // productos históricos, pero se avisa por si conviene actualizarlas.
                if (!$this->mapaIva[$f['codigo_iva']]['activa']) {
                    $avisos[] = 'La tarifa de IVA "' . $f['codigo_iva'] . '" ('
                        . $this->mapaIva[$f['codigo_iva']]['tarifa'] . ') está derogada; se conservará.';
                }
            }

            $f['id_medida'] = null;
            $f['id_tipo_medida'] = null;
            if ($f['tipo_produccion'] === CargaProductosEsquema::TIPO_BIEN && $f['codigo_medida'] !== '') {
                $claveMedida = mb_strtoupper($f['codigo_medida']);
                if (!isset($this->mapaMedidas[$claveMedida])) {
                    $errores[] = 'La unidad de medida "' . $f['codigo_medida'] . '" no existe (vea la hoja '
                        . CargaProductosEsquema::HOJA_REF_MEDIDAS . ').';
                } else {
                    $f['id_medida']      = $this->mapaMedidas[$claveMedida]['id'];
                    $f['id_tipo_medida'] = $this->mapaMedidas[$claveMedida]['id_tipo'];
                }
            }

            // Categoría y marca se crean si no existen (igual que el importador actual).
            $f['id_categoria'] = null;
            $f['crear_categoria'] = false;
            if ($f['categoria'] !== '') {
                $k = mb_strtolower($f['categoria']);
                if (isset($this->mapaCategorias[$k])) {
                    $f['id_categoria'] = $this->mapaCategorias[$k]['id'];
                } else {
                    $f['crear_categoria'] = true;
                    $avisos[] = 'La categoría "' . $f['categoria'] . '" no existe y se creará.';
                }
            }

            $f['id_marca'] = null;
            $f['crear_marca'] = false;
            if ($f['marca'] !== '') {
                $k = mb_strtolower($f['marca']);
                if (isset($this->mapaMarcas[$k])) {
                    $f['id_marca'] = $this->mapaMarcas[$k]['id'];
                } else {
                    $f['crear_marca'] = true;
                    $avisos[] = 'La marca "' . $f['marca'] . '" no existe y se creará.';
                }
            }

            $f['id_ice']     = null;
            $f['valor_ice']  = null;
            $f['nombre_ice'] = null;
            if ($f['codigo_ice'] !== '') {
                $k = mb_strtoupper($f['codigo_ice']);
                if (!isset($this->mapaIce[$k])) {
                    $errores[] = 'El CODIGO_ICE "' . $f['codigo_ice'] . '" no existe (vea la hoja '
                        . CargaProductosEsquema::HOJA_REF_ICE . ').';
                } else {
                    $f['id_ice']     = $this->mapaIce[$k]['id'];
                    $f['valor_ice']  = $this->mapaIce[$k]['valor_ice'];
                    $f['nombre_ice'] = $this->mapaIce[$k]['nombre_ice'];
                }
            }

            // Código de barras repetido en OTRO producto.
            if ($f['codigo_barras'] !== '') {
                $kb = mb_strtolower($f['codigo_barras']);
                if (isset($this->mapaCodigosBarras[$kb])
                    && mb_strtolower($this->mapaCodigosBarras[$kb]) !== $clave) {
                    $errores[] = 'El CODIGO_BARRAS "' . $f['codigo_barras']
                        . '" ya pertenece al producto ' . $this->mapaCodigosBarras[$kb] . '.';
                }
            }

            // ¿Crear o actualizar?
            $existente = $this->mapaProductos[$clave] ?? null;
            $f['accion']      = $existente ? 'actualizar' : 'crear';
            $f['id_producto'] = $existente['id'] ?? null;

            if ($existente && isset($this->idsUsados[$existente['id']])) {
                $cambios = [];
                if ($existente['nombre'] !== $f['nombre']) {
                    $cambios[] = 'el nombre';
                }
                if ($existente['tipo_produccion'] !== $f['tipo_produccion']) {
                    $cambios[] = 'el tipo';
                }
                if ($cambios) {
                    $avisos[] = 'El producto ya se usó en documentos: se conservará '
                        . implode(' y ', $cambios) . ' original.';
                }
            }

            $f['fila']    = $nFila;
            $f['errores'] = $errores;
            $f['avisos']  = $avisos;
            $f['precios']        = [];
            $f['variantes']      = [];
            $f['componentes']    = [];
            $f['bodegas']        = [];
            $f['homologaciones'] = [];
            // Hojas hijas en las que aparece este producto. Solo esas secciones
            // se reemplazan; las ausentes se conservan intactas.
            $f['secciones']      = [];

            $informe['filas'][] = [
                'hoja'    => CargaProductosEsquema::HOJA_PRODUCTOS,
                'fila'    => $nFila,
                'codigo'  => $f['codigo'],
                'accion'  => $f['accion'],
                'errores' => $errores,
                'avisos'  => $avisos,
            ];

            // Si el código se repite, se conserva la PRIMERA fila; la segunda ya
            // quedó marcada con error y no debe pisar a la anterior.
            if ($f['codigo'] !== '' && !isset($productos[$clave])) {
                $productos[$clave] = $f;
            }
        }

        return $productos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hojas hijas
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaPrecios(Spreadsheet $libro, array &$productos, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaProductosEsquema::HOJA_PRECIOS) as $nFila => $c) {
            $codigo = $this->texto($c[0] ?? '');
            $f = [
                'nombre_precio' => $this->texto($c[1] ?? ''),
                'precio'        => $this->aNumero($c[2] ?? ''),
                'valido_desde'  => $this->aFecha($c[3] ?? ''),
                'valido_hasta'  => $this->aFecha($c[4] ?? ''),
                'estado'        => $this->aBooleano($c[5] ?? 'Si'),
            ];

            $errores = $this->rules->validarPrecio($f);
            $ref = $this->resolverPadre($codigo, $productos, CargaProductosEsquema::HOJA_PRECIOS, $errores);

            $ok = $this->cerrarFilaHija($informe, $productos, CargaProductosEsquema::HOJA_PRECIOS, $nFila, $codigo, $ref, $errores);

            if ($ok && $ref !== null) {
                $productos[$ref]['precios'][] = [
                    'nombre_precio' => $f['nombre_precio'],
                    'precio'        => $f['precio'],
                    'valido_desde'  => $f['valido_desde'] ?: null,
                    'valido_hasta'  => $f['valido_hasta'] ?: null,
                    'estado'        => $f['estado'],
                ];
            }
        }
    }

    private function procesarHojaVariantes(Spreadsheet $libro, array &$productos, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaProductosEsquema::HOJA_VARIANTES) as $nFila => $c) {
            $codigo = $this->texto($c[0] ?? '');
            $f = [
                'nombre'           => $this->texto($c[1] ?? ''),
                'valor'            => $this->texto($c[2] ?? ''),
                'precio_adicional' => $this->aNumero($c[3] ?? '0'),
            ];

            $errores = $this->rules->validarVariante($f);
            $ref = $this->resolverPadre($codigo, $productos, CargaProductosEsquema::HOJA_VARIANTES, $errores);

            $ok = $this->cerrarFilaHija($informe, $productos, CargaProductosEsquema::HOJA_VARIANTES, $nFila, $codigo, $ref, $errores);

            if ($ok && $ref !== null) {
                $productos[$ref]['variantes'][] = $f;
            }
        }
    }

    private function procesarHojaComponentes(Spreadsheet $libro, array &$productos, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaProductosEsquema::HOJA_COMPONENTES) as $nFila => $c) {
            $codigo = $this->texto($c[0] ?? '');
            $f = [
                'codigo_padre'  => $codigo,
                'codigo_hijo'   => $this->texto($c[1] ?? ''),
                'cantidad'      => $this->aNumero($c[2] ?? ''),
                'codigo_medida' => $this->texto($c[3] ?? ''),
            ];

            $errores = $this->rules->validarComponente($f);
            $ref = $this->resolverPadre($codigo, $productos, CargaProductosEsquema::HOJA_COMPONENTES, $errores);

            // El hijo debe existir: en el archivo o ya en el sistema.
            $idHijo = null;
            if ($f['codigo_hijo'] !== '') {
                $kh = mb_strtolower($f['codigo_hijo']);
                if (isset($this->mapaProductos[$kh])) {
                    $idHijo = $this->mapaProductos[$kh]['id'];
                } elseif (!isset($productos[$kh])) {
                    $errores[] = 'El CODIGO_HIJO "' . $f['codigo_hijo']
                        . '" no existe en el sistema ni en la hoja ' . CargaProductosEsquema::HOJA_PRODUCTOS . '.';
                }
            }

            $idMedida = null;
            if ($f['codigo_medida'] !== '') {
                $km = mb_strtoupper($f['codigo_medida']);
                if (!isset($this->mapaMedidas[$km])) {
                    $errores[] = 'La unidad de medida "' . $f['codigo_medida'] . '" no existe.';
                } else {
                    $idMedida = $this->mapaMedidas[$km]['id'];
                }
            }

            $ok = $this->cerrarFilaHija($informe, $productos, CargaProductosEsquema::HOJA_COMPONENTES, $nFila, $codigo, $ref, $errores);

            if ($ok && $ref !== null) {
                $productos[$ref]['componentes'][] = [
                    'codigo_hijo' => $f['codigo_hijo'],
                    'id_producto_hijo' => $idHijo, // puede quedar null si el hijo se crea en esta misma carga
                    'cantidad'    => $f['cantidad'],
                    'id_medida'   => $idMedida,
                ];
            }
        }
    }

    private function procesarHojaStockBodegas(Spreadsheet $libro, array &$productos, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaProductosEsquema::HOJA_STOCK_BODEGAS) as $nFila => $c) {
            $codigo = $this->texto($c[0] ?? '');
            $f = [
                'bodega'       => $this->texto($c[1] ?? ''),
                'stock_minimo' => $this->aNumero($c[2] ?? '0'),
                'stock_maximo' => $this->aNumero($c[3] ?? '0'),
            ];

            $errores = $this->rules->validarStockBodega($f);
            $ref = $this->resolverPadre($codigo, $productos, CargaProductosEsquema::HOJA_STOCK_BODEGAS, $errores);

            $idBodega = null;
            if ($f['bodega'] !== '') {
                $kb = mb_strtolower($f['bodega']);
                if (!isset($this->mapaBodegas[$kb])) {
                    $errores[] = 'La bodega "' . $f['bodega'] . '" no existe (vea la hoja '
                        . CargaProductosEsquema::HOJA_REF_BODEGAS . ').';
                } else {
                    $idBodega = $this->mapaBodegas[$kb]['id'];
                }
            }

            $ok = $this->cerrarFilaHija($informe, $productos, CargaProductosEsquema::HOJA_STOCK_BODEGAS, $nFila, $codigo, $ref, $errores);

            if ($ok && $ref !== null) {
                $productos[$ref]['bodegas'][] = [
                    'id_bodega'    => $idBodega,
                    'stock_minimo' => $f['stock_minimo'],
                    'stock_maximo' => $f['stock_maximo'],
                ];
            }
        }
    }

    private function procesarHojaHomologaciones(Spreadsheet $libro, array &$productos, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaProductosEsquema::HOJA_HOMOLOGACIONES) as $nFila => $c) {
            $codigo = $this->texto($c[0] ?? '');
            $f = [
                'ruc_proveedor'    => $this->texto($c[1] ?? ''),
                'codigo_proveedor' => $this->texto($c[2] ?? ''),
            ];

            $errores = $this->rules->validarHomologacion($f);
            $ref = $this->resolverPadre($codigo, $productos, CargaProductosEsquema::HOJA_HOMOLOGACIONES, $errores);

            // El proveedor se enlaza por RUC/cédula y debe existir; no se crea solo.
            $idProveedor = null;
            if ($f['ruc_proveedor'] !== '') {
                if (!isset($this->mapaProveedores[$f['ruc_proveedor']])) {
                    $errores[] = 'No existe un proveedor con RUC/cédula "' . $f['ruc_proveedor']
                        . '" en esta empresa. Créelo primero en el módulo de Proveedores.';
                } else {
                    $idProveedor = $this->mapaProveedores[$f['ruc_proveedor']]['id'];
                }
            }

            $ok = $this->cerrarFilaHija($informe, $productos, CargaProductosEsquema::HOJA_HOMOLOGACIONES, $nFila, $codigo, $ref, $errores);

            if ($ok && $ref !== null) {
                $productos[$ref]['homologaciones'][] = [
                    'id_proveedor'     => $idProveedor,
                    'codigo_proveedor' => $f['codigo_proveedor'],
                ];
            }
        }
    }

    /**
     * Cierra el procesamiento de una fila hija.
     *
     * Si la fila tiene errores, además de reportarla marca al producto padre
     * como bloqueado: aplicar la sección sin esa fila la reemplazaría perdiendo
     * el dato que el usuario quiso poner.
     */
    private function cerrarFilaHija(
        array &$informe,
        array &$productos,
        string $hoja,
        int $nFila,
        string $codigo,
        ?string $ref,
        array $errores
    ): bool {
        if (!$errores) {
            if ($ref !== null) {
                $productos[$ref]['secciones'][$hoja] = true;
            }
            return true;
        }

        $this->registrarFilaHija($informe, $hoja, $nFila, $codigo, $errores);

        if ($ref !== null) {
            $productos[$ref]['errores'][] = 'Hoja ' . $hoja . ', fila ' . $nFila . ': '
                . implode(' ', $errores);
        }
        return false;
    }

    /**
     * Resuelve a qué producto de la hoja Productos pertenece una fila hija y
     * aplica las reglas que dependen del tipo del padre.
     *
     * @return string|null Clave del producto en $productos, o null si no aplica.
     */
    private function resolverPadre(string $codigo, array $productos, string $hoja, array &$errores): ?string
    {
        if ($codigo === '') {
            $errores[] = 'El código del producto es obligatorio.';
            return null;
        }

        $clave = mb_strtolower($codigo);

        if (!isset($productos[$clave])) {
            $errores[] = 'El código "' . $codigo . '" no aparece en la hoja '
                . CargaProductosEsquema::HOJA_PRODUCTOS . '.';
            return null;
        }

        $tipo = $productos[$clave]['tipo_produccion'] ?? CargaProductosEsquema::TIPO_BIEN;
        foreach ($this->rules->validarSeccionSegunTipo($hoja, (string) $tipo) as $err) {
            $errores[] = $err;
        }

        return $clave;
    }

    private function registrarFilaHija(array &$informe, string $hoja, int $nFila, string $codigo, array $errores): void
    {
        if (!$errores) {
            return;
        }
        $informe['filas'][] = [
            'hoja'    => $hoja,
            'fila'    => $nFila,
            'codigo'  => $codigo,
            'accion'  => 'detalle',
            'errores' => $errores,
            'avisos'  => [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lectura y conversión de celdas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve las filas con datos de una hoja (sin el encabezado),
     * indexadas por su número de fila real en Excel.
     */
    private function leerFilas(Spreadsheet $libro, string $nombreHoja): array
    {
        $hoja = $libro->getSheetByName($nombreHoja);
        if ($hoja === null) {
            return [];
        }

        $nColumnas = count(CargaProductosEsquema::columnas($nombreHoja));
        $filas = [];

        foreach ($hoja->toArray(null, true, false, false) as $i => $celdas) {
            if ($i === 0) {
                continue; // encabezado
            }
            $celdas = array_slice($celdas, 0, $nColumnas);
            // Ignorar filas totalmente vacías.
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

    /** "Si"/"No" => bool; null si no se reconoce. Vacío = null. */
    private function aBooleano($valor): ?bool
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['si', 'sí', 's', 'yes', 'y', '1', 'true', 'verdadero', 'x'], true)) {
            return true;
        }
        if (in_array($v, ['no', 'n', '0', 'false', 'falso'], true)) {
            return false;
        }
        return null;
    }

    /** "Activo"/"Inactivo" => 1/0; null si no se reconoce. Vacío = Activo. */
    private function aEstado($valor): ?int
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return 1;
        }
        if (in_array($v, ['activo', 'activa', 'a', '1', 'si', 'sí', 'true'], true)) {
            return 1;
        }
        if (in_array($v, ['inactivo', 'inactiva', 'i', '0', 'no', 'false'], true)) {
            return 0;
        }
        return null;
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

    /** Convierte el texto de TIPO a tipo_produccion ('01'/'02'); null si no se reconoce. */
    private function aTipoProduccion($valor): ?string
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return CargaProductosEsquema::TIPO_BIEN;
        }
        if (in_array($v, ['producto', 'productos', 'bien', 'bienes', '01', '1'], true)) {
            return CargaProductosEsquema::TIPO_BIEN;
        }
        if (in_array($v, ['servicio', 'servicios', 'service', 'serv', '02', '2'], true)) {
            return CargaProductosEsquema::TIPO_SERVICIO;
        }
        return null;
    }
}

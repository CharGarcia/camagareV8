<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Rules\modulos\CargaSuscripcionesRules;
use App\repositories\modulos\CargaSuscripcionesRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Valida un libro de Excel de carga de suscripciones SIN escribir nada.
 *
 * Devuelve un informe con el resumen, los errores por fila y el payload ya
 * normalizado de cada suscripción (cabecera + detalle), listo para que la fase
 * de aplicación lo entregue a SuscripcionesService::crear().
 */
class CargaSuscripcionesValidacionService
{
    private CargaSuscripcionesRepository $repository;
    private CargaSuscripcionesRules $rules;

    /** Catálogos precargados (se llenan en validar()). */
    private array $mapaClientes       = [];
    private array $mapaProductos      = [];
    private array $mapaPeriodicidades = [];
    private array $mapaIva            = [];
    private array $firmasExistentes   = [];

    public function __construct(
        CargaSuscripcionesRepository $repository,
        CargaSuscripcionesRules $rules
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
    }

    public function validar(string $rutaArchivo, int $idEmpresa): array
    {
        $informe = [
            'ok'               => false,
            'errores_globales' => [],
            'resumen'          => [
                'total_suscripciones' => 0,
                'crear'               => 0,
                'bloqueados'          => 0,
                'filas_con_error'     => 0,
                'con_aviso'           => 0,
            ],
            'filas'         => [],
            'suscripciones' => [],
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

        $erroresHojas = $this->validarHojas($libro);
        if ($erroresHojas) {
            $informe['errores_globales'] = $erroresHojas;
            return $informe;
        }

        $errorEmpresa = $this->validarEmpresa($libro, $idEmpresa);
        if ($errorEmpresa !== null) {
            $informe['errores_globales'][] = $errorEmpresa;
            return $informe;
        }

        $erroresEncabezados = $this->validarEncabezados($libro);
        if ($erroresEncabezados) {
            $informe['errores_globales'] = $erroresEncabezados;
            return $informe;
        }

        $this->precargarCatalogos($idEmpresa);

        // 1. Cabecera.
        $suscripciones = $this->procesarHojaSuscripciones($libro, $informe);
        if (!$suscripciones) {
            $informe['errores_globales'][] = 'La hoja "' . CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES
                . '" no tiene ninguna fila con datos.';
            return $informe;
        }

        // 2. Detalle (enlaza por CLAVE).
        $this->procesarHojaDetalle($libro, $suscripciones, $informe);

        // 3. Toda suscripción debe tener al menos una línea de detalle.
        foreach ($suscripciones as $clave => &$s) {
            if (empty($s['detalle'])) {
                $s['errores'][] = 'La suscripción no tiene ninguna línea en la hoja '
                    . CargaSuscripcionesEsquema::HOJA_DETALLE . '.';
                // Reflejar el error en la fila de cabecera del informe.
                $this->marcarErrorCabecera($informe, $s['fila'],
                    'La suscripción no tiene ninguna línea en la hoja ' . CargaSuscripcionesEsquema::HOJA_DETALLE . '.');
            }
        }
        unset($s);

        // 3.b. Duplicados: bloquear si el mismo cliente ya tiene una suscripción
        //      (no eliminada) con exactamente el mismo conjunto de productos, ya sea
        //      en la base o en otra fila de este mismo archivo.
        $firmasArchivo = [];
        foreach ($suscripciones as &$s) {
            if (!empty($s['errores'])) {
                continue; // ya bloqueada por otra causa
            }
            $firma = $this->firmaSuscripcion($s);
            if ($firma === null) {
                continue;
            }

            $msg = null;
            if (isset($this->firmasExistentes[$firma])) {
                $msg = 'Ya existe una suscripción de este cliente con el mismo conjunto de productos.';
            } elseif (isset($firmasArchivo[$firma])) {
                $msg = 'Otra fila del archivo (CLAVE ' . $firmasArchivo[$firma]
                    . ') crea una suscripción idéntica para este cliente.';
            }

            if ($msg !== null) {
                $s['errores'][] = $msg;
                $this->marcarErrorCabecera($informe, $s['fila'], $msg);
            } else {
                $firmasArchivo[$firma] = $s['clave'];
            }
        }
        unset($s);

        // 4. Resumen.
        foreach ($informe['filas'] as $fila) {
            if ($fila['errores']) {
                $informe['resumen']['filas_con_error']++;
            }
            if ($fila['avisos']) {
                $informe['resumen']['con_aviso']++;
            }
        }
        foreach ($suscripciones as $s) {
            $informe['resumen']['total_suscripciones']++;
            if ($s['errores']) {
                $informe['resumen']['bloqueados']++;
            } else {
                $informe['resumen']['crear']++;
            }
        }

        $informe['suscripciones'] = $suscripciones;
        $informe['ok'] = ($informe['resumen']['filas_con_error'] === 0);

        return $informe;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validaciones estructurales
    // ─────────────────────────────────────────────────────────────────────────

    private function validarHojas(Spreadsheet $libro): array
    {
        $esperadas = CargaSuscripcionesEsquema::todasLasHojas();
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

    private function validarEmpresa(Spreadsheet $libro, int $idEmpresa): ?string
    {
        $hoja = $libro->getSheetByName(CargaSuscripcionesEsquema::HOJA_CONFIG);
        if ($hoja === null) {
            return 'El archivo no tiene la hoja de control ' . CargaSuscripcionesEsquema::HOJA_CONFIG . '.';
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

    private function validarEncabezados(Spreadsheet $libro): array
    {
        $errores = [];
        foreach (CargaSuscripcionesEsquema::hojasDatos() as $nombreHoja => $def) {
            $hoja = $libro->getSheetByName($nombreHoja);
            if ($hoja === null) {
                continue;
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
        $this->mapaClientes       = $this->repository->getMapaClientes($idEmpresa);
        $this->mapaProductos      = $this->repository->getMapaProductos($idEmpresa);
        $this->mapaPeriodicidades = $this->repository->getMapaPeriodicidades();
        $this->mapaIva            = $this->repository->getMapaTarifasIva();
        $this->firmasExistentes   = $this->repository->getFirmasSuscripciones($idEmpresa);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Suscripciones (cabecera)
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaSuscripciones(Spreadsheet $libro, array &$informe): array
    {
        $filas = $this->leerFilas($libro, CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES);
        $suscripciones = [];
        $vistas = [];

        foreach ($filas as $nFila => $c) {
            $f = [
                'clave'            => $this->texto($c[0] ?? ''),
                'ruc_cliente'      => $this->texto($c[1] ?? ''),
                'periodicidad'     => $this->texto($c[2] ?? ''),
                'fecha_inicio'     => $this->aFecha($c[3] ?? ''),
                'fecha_fin'        => $this->aFecha($c[4] ?? ''),
                'proximo_cobro'    => $this->aFecha($c[5] ?? ''),
                'forma_cobro'      => $this->aFormaCobro($c[6] ?? ''),
                'tipo_comprobante' => $this->aTipoComprobante($c[7] ?? ''),
                'estado'           => $this->aEstado($c[8] ?? ''),
                'observaciones'    => $this->texto($c[9] ?? ''),
                'info_concepto'    => $this->texto($c[10] ?? ''),
                'info_detalle'     => $this->texto($c[11] ?? ''),
            ];

            $errores = $this->rules->validarSuscripcion($f);
            $avisos  = [];
            $clave   = mb_strtolower($f['clave']);

            // CLAVE duplicada dentro del archivo.
            if ($f['clave'] !== '') {
                if (isset($vistas[$clave])) {
                    $errores[] = 'CLAVE repetida en el archivo (ya aparece en la fila ' . $vistas[$clave] . ').';
                } else {
                    $vistas[$clave] = $nFila;
                }
            }

            // Cliente por RUC/cédula.
            $f['id_cliente'] = null;
            if ($f['ruc_cliente'] !== '') {
                if (!isset($this->mapaClientes[$f['ruc_cliente']])) {
                    $errores[] = 'No existe un cliente con RUC/cédula "' . $f['ruc_cliente']
                        . '" en esta empresa (vea la hoja ' . CargaSuscripcionesEsquema::HOJA_REF_CLIENTES . ').';
                } else {
                    $f['id_cliente'] = $this->mapaClientes[$f['ruc_cliente']]['id'];
                }
            }

            // Periodicidad por código o nombre.
            $f['id_periodicidad'] = null;
            if ($f['periodicidad'] !== '') {
                $kp = mb_strtolower($f['periodicidad']);
                if (!isset($this->mapaPeriodicidades[$kp])) {
                    $errores[] = 'La PERIODICIDAD "' . $f['periodicidad'] . '" no existe (vea la hoja '
                        . CargaSuscripcionesEsquema::HOJA_REF_PERIODICIDADES . ').';
                } else {
                    $f['id_periodicidad'] = $this->mapaPeriodicidades[$kp]['id'];
                }
            }

            $f['fila']     = $nFila;
            $f['errores']  = $errores;
            $f['avisos']   = $avisos;
            $f['detalle']  = [];

            $informe['filas'][] = [
                'hoja'    => CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES,
                'fila'    => $nFila,
                'codigo'  => $f['clave'],
                'accion'  => 'crear',
                'errores' => $errores,
                'avisos'  => $avisos,
            ];

            // Si la clave se repite se conserva la primera; la segunda ya quedó con error.
            if ($f['clave'] !== '' && !isset($suscripciones[$clave])) {
                $suscripciones[$clave] = $f;
            }
        }

        return $suscripciones;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Detalle
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaDetalle(Spreadsheet $libro, array &$suscripciones, array &$informe): void
    {
        foreach ($this->leerFilas($libro, CargaSuscripcionesEsquema::HOJA_DETALLE) as $nFila => $c) {
            $clave   = $this->texto($c[0] ?? '');
            $precioRaw = trim((string) ($c[4] ?? ''));
            $ivaRaw    = $this->texto($c[5] ?? '');

            $f = [
                'codigo_producto' => $this->texto($c[1] ?? ''),
                'descripcion'     => $this->texto($c[2] ?? ''),
                'cantidad'        => $this->aNumero($c[3] ?? ''),
                'precio_unitario' => $precioRaw === '' ? 0.0 : $this->aNumero($precioRaw),
            ];

            $errores = $this->rules->validarDetalle($f);

            // Resolver la suscripción padre por CLAVE.
            $ref = null;
            if ($clave === '') {
                $errores[] = 'CLAVE es obligatoria.';
            } else {
                $kc = mb_strtolower($clave);
                if (!isset($suscripciones[$kc])) {
                    $errores[] = 'La CLAVE "' . $clave . '" no aparece en la hoja '
                        . CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES . '.';
                } else {
                    $ref = $kc;
                }
            }

            // Producto por código.
            $prod = null;
            if ($f['codigo_producto'] !== '') {
                $kp = mb_strtolower($f['codigo_producto']);
                if (!isset($this->mapaProductos[$kp])) {
                    $errores[] = 'El CODIGO_PRODUCTO "' . $f['codigo_producto'] . '" no existe (vea la hoja '
                        . CargaSuscripcionesEsquema::HOJA_REF_PRODUCTOS . ').';
                } else {
                    $prod = $this->mapaProductos[$kp];
                }
            }

            // IVA: si se indica CODIGO_IVA se usa esa tarifa; si no, la del producto.
            $idTarifaIva = $prod['id_tarifa_iva'] ?? null;
            $porcentajeIva = $prod['porcentaje_iva'] ?? 0.0;
            if ($ivaRaw !== '') {
                if (!isset($this->mapaIva[$ivaRaw])) {
                    $errores[] = 'El CODIGO_IVA "' . $ivaRaw . '" no existe (vea la hoja '
                        . CargaSuscripcionesEsquema::HOJA_REF_IVA . ').';
                } else {
                    $idTarifaIva   = $this->mapaIva[$ivaRaw]['id'];
                    $porcentajeIva = (float) $this->mapaIva[$ivaRaw]['porcentaje_iva'];
                }
            }

            // Registrar la fila del detalle en el informe solo si tiene problemas.
            if ($errores) {
                $informe['filas'][] = [
                    'hoja'    => CargaSuscripcionesEsquema::HOJA_DETALLE,
                    'fila'    => $nFila,
                    'codigo'  => $clave,
                    'accion'  => 'detalle',
                    'errores' => $errores,
                    'avisos'  => [],
                ];
                // Bloquear la suscripción padre: aplicar sin esta línea perdería el ítem.
                if ($ref !== null) {
                    $suscripciones[$ref]['errores'][] = 'Hoja ' . CargaSuscripcionesEsquema::HOJA_DETALLE
                        . ', fila ' . $nFila . ': ' . implode(' ', $errores);
                }
                continue;
            }

            if ($ref !== null && $prod !== null) {
                $suscripciones[$ref]['detalle'][] = [
                    'id_producto'     => $prod['id'],
                    'descripcion'     => $f['descripcion'] !== '' ? $f['descripcion'] : $prod['nombre'],
                    'cantidad'        => $f['cantidad'],
                    'precio_unitario' => $precioRaw === '' ? $prod['precio_base'] : $f['precio_unitario'],
                    'id_tarifa_iva'   => $idTarifaIva,
                    'porcentaje_iva'  => $porcentajeIva,
                ];
            }
        }
    }

    /**
     * Firma de una suscripción para detectar duplicados: cliente + conjunto de
     * productos (ids distintos, ordenados). null si faltan cliente o productos.
     * Debe coincidir con el formato de CargaSuscripcionesRepository::getFirmasSuscripciones().
     */
    private function firmaSuscripcion(array $s): ?string
    {
        if (empty($s['id_cliente']) || empty($s['detalle'])) {
            return null;
        }
        $ids = [];
        foreach ($s['detalle'] as $d) {
            if (!empty($d['id_producto'])) {
                $ids[(int) $d['id_producto']] = true;
            }
        }
        if (!$ids) {
            return null;
        }
        $ids = array_keys($ids);
        sort($ids, SORT_NUMERIC);
        return $s['id_cliente'] . '|' . implode(',', $ids);
    }

    /** Agrega un mensaje de error a la fila de cabecera ya registrada en el informe. */
    private function marcarErrorCabecera(array &$informe, int $nFila, string $mensaje): void
    {
        foreach ($informe['filas'] as &$fila) {
            if ($fila['hoja'] === CargaSuscripcionesEsquema::HOJA_SUSCRIPCIONES && $fila['fila'] === $nFila) {
                $fila['errores'][] = $mensaje;
                return;
            }
        }
        unset($fila);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lectura y conversión de celdas
    // ─────────────────────────────────────────────────────────────────────────

    private function leerFilas(Spreadsheet $libro, string $nombreHoja): array
    {
        $hoja = $libro->getSheetByName($nombreHoja);
        if ($hoja === null) {
            return [];
        }

        $nColumnas = count(CargaSuscripcionesEsquema::columnas($nombreHoja));
        $filas = [];

        foreach ($hoja->toArray(null, true, false, false) as $i => $celdas) {
            if ($i === 0) {
                continue;
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
        $v = str_replace([' ', "\u{00A0}"], '', $v);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d+$/', $v)) {
            $v = str_replace('.', '', $v);
        }
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Normaliza a 'Y-m-d'. '' si viene vacío, false si el formato es inválido.
     * @return string|false
     */
    private function aFecha($valor)
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return '';
        }
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

    /** "Credito"/"Tarjeta" => 'credito'/'tarjeta'; null si no se reconoce. Vacío = credito. */
    private function aFormaCobro($valor): ?string
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return 'credito';
        }
        if (in_array($v, ['credito', 'crédito', 'c'], true)) {
            return 'credito';
        }
        if (in_array($v, ['tarjeta', 't'], true)) {
            return 'tarjeta';
        }
        return null;
    }

    /** "Factura"/"Recibo" => 'factura'/'recibo'; null si no se reconoce. Vacío = factura. */
    private function aTipoComprobante($valor): ?string
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return CargaSuscripcionesEsquema::TIPO_FACTURA;
        }
        if (in_array($v, ['factura', 'factura de venta', 'f'], true)) {
            return CargaSuscripcionesEsquema::TIPO_FACTURA;
        }
        if (in_array($v, ['recibo', 'recibo de venta', 'r'], true)) {
            return CargaSuscripcionesEsquema::TIPO_RECIBO;
        }
        return null;
    }

    /** Estado en minúsculas si es válido; null si no. Vacío = activo. */
    private function aEstado($valor): ?string
    {
        $v = mb_strtolower(trim((string) $valor));
        if ($v === '') {
            return 'activo';
        }
        return in_array($v, CargaSuscripcionesEsquema::estadosValidos(), true) ? $v : null;
    }
}

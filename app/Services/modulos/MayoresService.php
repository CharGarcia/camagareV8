<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\MayoresRepository;
use App\Services\ReportService;
use App\Helpers\ReportePdf;

class MayoresService
{
    private MayoresRepository $repository;
    private ReportService $reportService;

    public function __construct(MayoresRepository $repository, ReportService $reportService)
    {
        $this->repository = $repository;
        $this->reportService = $reportService;
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    public function getCentrosCostoActivos(int $idEmpresa): array
    {
        return $this->repository->getCentrosCostoActivos($idEmpresa);
    }

    public function getProyectosActivos(int $idEmpresa): array
    {
        return $this->repository->getProyectosActivos($idEmpresa);
    }

    /**
     * Arma el Mayor agrupado por cuenta. El saldo acumulado arranca en 0 por cada cuenta
     * dentro del rango filtrado (sin arrastre de periodos anteriores: el saldo inicial se
     * registra manualmente como asiento de apertura, igual criterio que Estados Financieros).
     */
    public function generarMayor(int $idEmpresa, array $filtros): array
    {
        $movimientos = $this->repository->getMovimientos($idEmpresa, $filtros);
        $desglosables = $this->documentosDesglosables($idEmpresa, $movimientos);

        $cuentas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($movimientos as $mov) {
            $idCuenta = (int) $mov['id_cuenta'];
            $codigo = (string) $mov['codigo_cuenta'];

            if (!isset($cuentas[$idCuenta])) {
                $cuentas[$idCuenta] = [
                    'id_cuenta' => $idCuenta,
                    'codigo' => $codigo,
                    'nombre' => $mov['nombre_cuenta'],
                    'movimientos' => [],
                    'saldo_arrastrado' => 0.0,
                    'subtotal_debe' => 0.0,
                    'subtotal_haber' => 0.0,
                ];
            }

            $debe = (float) $mov['debe'];
            $haber = (float) $mov['haber'];
            $prefijo = $codigo !== '' ? $codigo[0] : '1';
            // 1=Activo (Deudora), 2=Pasivo (Acreedora), 3=Patrimonio (Acreedora), 4=Ingresos (Acreedora), 5=Costos (Deudora), 6=Gastos (Deudora)
            $naturaleza = in_array($prefijo, ['1', '5', '6']) ? 'deudora' : 'acreedora';

            $cuentas[$idCuenta]['saldo_arrastrado'] += $naturaleza === 'deudora' ? ($debe - $haber) : ($haber - $debe);
            $cuentas[$idCuenta]['subtotal_debe'] += $debe;
            $cuentas[$idCuenta]['subtotal_haber'] += $haber;

            $cuentas[$idCuenta]['movimientos'][] = [
                'id_asiento' => $mov['id_asiento'],
                'fecha_asiento' => $mov['fecha_asiento'],
                'numero_comprobante' => $mov['numero_comprobante'],
                'documento_referencia' => $mov['documento_referencia'],
                'referencia_detalle' => $mov['referencia_detalle'],
                'concepto' => $mov['concepto'],
                'tercero' => $mov['nombre_entidad'],
                // Documento origen: con esto la vista enlaza "Documento Ref." a su modal.
                'modulo_documento' => $mov['modulo_documento'],
                'id_documento' => $mov['id_documento'],
                // Línea del asiento + si se puede abrir el desglose por líneas del documento.
                'id_linea' => (int) $mov['id_linea'],
                'desglosable' => $this->esDesglosable($mov, $desglosables),
                'debe' => $debe,
                'haber' => $haber,
                'saldo_acumulado' => $cuentas[$idCuenta]['saldo_arrastrado'],
            ];

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        $resultado = [];
        foreach ($cuentas as $c) {
            $resultado[] = [
                'id_cuenta' => $c['id_cuenta'],
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'movimientos' => $c['movimientos'],
                'subtotal_debe' => $c['subtotal_debe'],
                'subtotal_haber' => $c['subtotal_haber'],
                'saldo_final' => $c['saldo_arrastrado'],
            ];
        }

        return [
            'cuentas' => $resultado,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
            ],
        ];
    }

    // ── Desglose por líneas del documento ──────────────────────────────────────────────
    //
    // El asiento de un ingreso/egreso SUMA por cuenta las líneas del documento: cinco «otros
    // conceptos» al mismo gasto o diecisiete facturas de compra pagadas quedan en una sola línea
    // del asiento, con la glosa de la primera. El mayor lee el asiento, así que no puede mostrar
    // ese detalle por sí solo. El desglose lo reconstruye desde el documento, SOLO para mostrarlo:
    // el saldo y los totales siguen saliendo del asiento.

    /** Lado del asiento donde el documento pone su contrapartida (lo que se desglosa). */
    private const LADO_CONTRAPARTIDA = ['egreso' => 'debe', 'ingreso' => 'haber'];

    /** tipo_documento de las líneas manuales («otros conceptos»). */
    private const TIPO_MANUAL = ['egreso' => 'MANUAL', 'ingreso' => 'OTRO'];

    /** Cómo se lee cada tipo de línea en el desglose. */
    private const ETIQUETA_TIPO_LINEA = [
        'MANUAL'            => 'Otro concepto',
        'OTRO'              => 'Otro concepto',
        'COMPRA'            => 'Factura de compra',
        'LIQUIDACION'       => 'Liquidación de compra',
        'ROL'               => 'Rol de pagos',
        'IMPORTACION'       => 'Importación',
        'FACTURA'           => 'Factura de venta',
        'RECIBO'            => 'Recibo de venta',
        'FACTURA_REEMBOLSO' => 'Factura de reembolso',
    ];

    /** Tope de líneas por petición de desglose («Expandir todo» las pide juntas). */
    private const MAX_LINEAS_DESGLOSE = 1000;

    /**
     * Documentos (ingresos/egresos) del mayor con dos o más líneas: modulo => [id => true].
     * Una consulta por tipo de documento, no una por fila.
     */
    private function documentosDesglosables(int $idEmpresa, array $movimientos): array
    {
        $ids = [];
        foreach ($movimientos as $mov) {
            $modulo = (string) ($mov['modulo_documento'] ?? '');
            if (isset(self::LADO_CONTRAPARTIDA[$modulo]) && !empty($mov['id_documento'])) {
                $ids[$modulo][(int) $mov['id_documento']] = true;
            }
        }

        $res = [];
        foreach ($ids as $modulo => $lista) {
            try {
                $conteo = $this->repository->contarLineasDocumentos($idEmpresa, $modulo, array_keys($lista));
            } catch (\PDOException $e) {
                // Sin la tabla de detalle el mayor sale igual, solo que sin desglose.
                error_log('Mayores: no se pudo contar las líneas de ' . $modulo . '. ' . $e->getMessage());
                continue;
            }
            foreach ($conteo as $idDoc => $n) {
                if ($n >= 2) {
                    $res[$modulo][$idDoc] = true;
                }
            }
        }
        return $res;
    }

    /** La fila es la contrapartida de un documento con varias líneas (no la pata de banco/caja). */
    private function esDesglosable(array $mov, array $desglosables): bool
    {
        $modulo = (string) ($mov['modulo_documento'] ?? '');
        $lado = self::LADO_CONTRAPARTIDA[$modulo] ?? null;
        return $lado !== null
            && isset($desglosables[$modulo][(int) $mov['id_documento']])
            && (float) $mov[$lado] > 0;
    }

    /**
     * Desglose de varias líneas del mayor: id_linea => [
     *   'lineas'       => [[tipo, numero, descripcion, monto], …]  líneas del documento en esa cuenta
     *   'total_cuenta' => lo que el asiento lleva en esa cuenta (lado de la contrapartida)
     *   'ajuste'       => total_cuenta − suma de las líneas (redondeo, saldo sin línea propia…)
     *   'lineas_asiento' => cuántas líneas tiene esa cuenta en el asiento (normalmente 1)
     * ]. Las líneas que no son de un ingreso/egreso, o no son su contrapartida, no aparecen.
     *
     * @param int[] $idsLinea
     */
    public function getDesglose(int $idEmpresa, array $idsLinea): array
    {
        $idsLinea = array_slice(array_values(array_unique(array_filter(array_map('intval', $idsLinea)))), 0, self::MAX_LINEAS_DESGLOSE);
        if (!$idsLinea) {
            return [];
        }

        $lineas = array_values(array_filter(
            $this->repository->getLineasParaDesglose($idEmpresa, $idsLinea),
            static fn (array $l): bool => isset(self::LADO_CONTRAPARTIDA[(string) $l['modulo_doc']]) && !empty($l['id_doc'])
        ));
        if (!$lineas) {
            return [];
        }

        // Asientos y documentos involucrados: una consulta por cada cosa, no por fila.
        $asientos = [];
        foreach ($this->repository->getLineasAsientos($idEmpresa, array_column($lineas, 'id_asiento')) as $la) {
            $asientos[(int) $la['id_asiento']][] = $la;
        }
        $docs = [];
        foreach (array_keys(self::LADO_CONTRAPARTIDA) as $modulo) {
            $ids = array_column(array_filter($lineas, static fn (array $l): bool => $l['modulo_doc'] === $modulo), 'id_doc');
            foreach ($this->repository->getLineasDocumentos($idEmpresa, $modulo, $ids) as $d) {
                $docs[$modulo][(int) $d['id_doc']][] = $d;
            }
        }

        $res = [];
        $asignados = []; // cache por asiento+documento: varias filas del mismo asiento
        foreach ($lineas as $l) {
            $modulo   = (string) $l['modulo_doc'];
            $lado     = self::LADO_CONTRAPARTIDA[$modulo];
            $idAs     = (int) $l['id_asiento'];
            $idDoc    = (int) $l['id_doc'];
            $idCuenta = (int) $l['id_cuenta_contable'];
            if ((float) $l[$lado] <= 0) {
                continue; // pata de banco/caja: no es la que agrupa líneas del documento
            }

            $clave = $idAs . ':' . $modulo . ':' . $idDoc;
            $asignados[$clave] ??= $this->asignarLineasACuentas($modulo, $docs[$modulo][$idDoc] ?? [], $asientos[$idAs] ?? []);
            [$detalles, $porCuenta] = $asignados[$clave];

            $filas = [];
            $suma  = 0.0;
            foreach ($detalles as $d) {
                if (($d['cuenta_asignada'] ?? 0) !== $idCuenta) {
                    continue;
                }
                $monto  = round((float) $d['monto'], 2);
                $suma   = round($suma + $monto, 2);
                $tipo   = strtoupper(trim((string) $d['tipo_documento']));
                $filas[] = [
                    'tipo'        => self::ETIQUETA_TIPO_LINEA[$tipo] ?? ucfirst(strtolower(str_replace('_', ' ', $tipo))),
                    'numero'      => (string) ($d['numero_documento'] ?? ''),
                    'descripcion' => (string) ($d['descripcion'] ?? ''),
                    'monto'       => $monto,
                ];
            }

            $total = round((float) ($porCuenta[$idCuenta]['monto'] ?? (float) $l[$lado]), 2);
            $res[(int) $l['id_linea']] = [
                'lineas'         => $filas,
                'total_cuenta'   => $total,
                'ajuste'         => round($total - $suma, 2),
                'lineas_asiento' => (int) ($porCuenta[$idCuenta]['n'] ?? 1),
            ];
        }
        return $res;
    }

    /**
     * A qué cuenta del asiento fue a parar cada línea del documento (`cuenta_asignada`, 0 si no
     * se pudo saber). Mismo orden de criterios que usó el asiento al armarse:
     *   1. Línea manual con su cuenta guardada.
     *   2. Línea manual sin cuenta (documentos antiguos) cuya descripción es la glosa de una
     *      sola cuenta (el asiento guarda la descripción de la primera línea de cada grupo).
     *   3. Documento de cartera (factura, compra…): la cuenta cuya referencia lo nombra
     *      ("Pago facturas de compra 42869, 42870").
     *   4. Lo que quede, por saldo: si una sola cuenta tiene saldo sin explicar que lo cubra
     *      (o el asiento tiene una sola cuenta en ese lado), va ahí.
     *   5. Manuales aún sin cuenta: el cruce de CuentasDetalleDesdeAsiento (el del modal del
     *      documento), solo si cabe en el saldo de esa cuenta. Va al final porque empata por
     *      monto y, antes de mirar saldos, mandaba una línea a la cuenta equivocada.
     * Lo que no se resuelve no se inventa: queda fuera y su monto se ve como «Ajuste».
     *
     * @return array{0: array, 1: array<int,array{monto: float, n: int}>} [detalles, cuentas del lado]
     */
    private function asignarLineasACuentas(string $modulo, array $detalles, array $lineasAsiento): array
    {
        $lado = self::LADO_CONTRAPARTIDA[$modulo];
        $tipoManual = self::TIPO_MANUAL[$modulo];
        $colMonto = \App\repositories\modulos\MayoresRepository::DESGLOSE_DOCUMENTOS[$modulo]['monto'];

        $porCuenta = [];
        foreach ($lineasAsiento as $la) {
            $m = round((float) ($la[$lado] ?? 0), 2);
            $id = (int) ($la['id_cuenta_contable'] ?? 0);
            if ($m <= 0 || $id <= 0) {
                continue;
            }
            $porCuenta[$id] ??= ['monto' => 0.0, 'n' => 0, 'refs' => []];
            $porCuenta[$id]['monto'] = round($porCuenta[$id]['monto'] + $m, 2);
            $porCuenta[$id]['n']++;
            $porCuenta[$id]['refs'][] = (string) ($la['referencia_detalle'] ?? '');
        }
        if (!$porCuenta || !$detalles) {
            return [$detalles, $porCuenta];
        }

        $esManual = static fn (array $d): bool => strtoupper(trim((string) $d['tipo_documento'])) === $tipoManual;
        $norm = static fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        $sinCuentaGuardada = []; // índices de líneas manuales sin cuenta guardada (para el paso 5)

        foreach ($detalles as $i => $d) {
            $detalles[$i]['cuenta_asignada'] = 0;
            $cta = (int) ($d['id_cuenta_contable'] ?? 0);
            if ($esManual($d)) {
                if ($cta > 0 && isset($porCuenta[$cta])) {
                    // 1. Cuenta guardada en la línea.
                    $detalles[$i]['cuenta_asignada'] = $cta;
                    continue;
                }
                $sinCuentaGuardada[] = $i;
                // 2. La glosa del asiento es la descripción de la primera línea de cada cuenta.
                $desc = $norm((string) ($d['descripcion'] ?? ''));
                if ($desc !== '') {
                    $coinciden = array_keys(array_filter(
                        $porCuenta,
                        static fn (array $c): bool => in_array($desc, array_map($norm, $c['refs']), true)
                    ));
                    if (count($coinciden) === 1) {
                        $detalles[$i]['cuenta_asignada'] = $coinciden[0];
                    }
                }
            } else {
                // 3. Documento de cartera nombrado en la referencia de una sola cuenta.
                $num = \App\Services\modulos\AsientoBuilderService::secuencialCorto((string) ($d['numero_documento'] ?? ''));
                if ($num !== '') {
                    $patron = '/(?<!\d)' . preg_quote($num, '/') . '(?!\d)/u';
                    $coinciden = array_keys(array_filter(
                        $porCuenta,
                        static fn (array $c): bool => (bool) preg_grep($patron, $c['refs'])
                    ));
                    if (count($coinciden) === 1) {
                        $detalles[$i]['cuenta_asignada'] = $coinciden[0];
                    }
                }
            }
        }

        // 4. Por saldo sin explicar.
        $saldo = array_map(static fn (array $c): float => $c['monto'], $porCuenta);
        foreach ($detalles as $d) {
            if ($d['cuenta_asignada'] > 0) {
                $saldo[$d['cuenta_asignada']] = round($saldo[$d['cuenta_asignada']] - (float) $d['monto'], 2);
            }
        }
        // Se repite mientras haya avance: asignar una línea puede dejar a otra con una sola opción.
        do {
            $avance = false;
            foreach ($detalles as $i => $d) {
                if ($d['cuenta_asignada'] > 0) {
                    continue;
                }
                $monto = round((float) $d['monto'], 2);
                $candidatas = count($porCuenta) === 1
                    ? array_keys($porCuenta)
                    : array_keys(array_filter($saldo, static fn (float $s): bool => $s >= $monto - 0.005));
                if (count($candidatas) === 1) {
                    $detalles[$i]['cuenta_asignada'] = $candidatas[0];
                    $saldo[$candidatas[0]] = round($saldo[$candidatas[0]] - $monto, 2);
                    $avance = true;
                }
            }
        } while ($avance);

        // 5. Manuales que siguen sin cuenta: el cruce del modal del documento
        //    (CuentasDetalleDesdeAsiento: referencia que contiene la descripción, mismo monto…),
        //    aceptado solo si esa cuenta todavía tiene saldo que lo cubra.
        $pendientes = array_values(array_filter($sinCuentaGuardada, static fn (int $i): bool => $detalles[$i]['cuenta_asignada'] === 0));
        if ($pendientes) {
            $copia = $detalles;
            \App\Helpers\CuentasDetalleDesdeAsiento::aplicar($copia, $lineasAsiento, $lado, $tipoManual, $colMonto);
            foreach ($pendientes as $i) {
                $cta = (int) ($copia[$i]['id_cuenta_contable'] ?? 0);
                $monto = round((float) $detalles[$i]['monto'], 2);
                if ($cta > 0 && isset($saldo[$cta]) && $saldo[$cta] >= $monto - 0.005) {
                    $detalles[$i]['cuenta_asignada'] = $cta;
                    $saldo[$cta] = round($saldo[$cta] - $monto, 2);
                }
            }
        }

        return [$detalles, $porCuenta];
    }

    /**
     * Excel del Mayor con el formato común de los reportes (ReportService::construirSpreadsheet):
     * nombre de la empresa, filtros aplicados + totales + fecha de generación, un único
     * encabezado (fijo al desplazarse) y montos con formato numérico. Cada cuenta abre con
     * una fila de título resaltada y cierra con su subtotal; al final, el TOTAL GENERAL.
     */
    public function exportarExcel(int $idEmpresa, array $datos, array $filtros, string $empresaNombre): void
    {
        $headers = ['Fecha', 'Comprobante', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $fecha = static function ($v): string {
            $ts = strtotime((string) $v);
            return $ts ? date('d-m-Y', $ts) : (string) $v;
        };

        $data  = [];
        $tipos = []; // índice de fila de $data => 'grp' | 'sub' | 'tot'
        $nMovs = 0;
        foreach ($datos['cuentas'] as $k => $cuenta) {
            if ($k > 0) {
                $data[] = array_fill(0, 8, null);
            }
            $tipos[count($data)] = 'grp';
            $data[] = [$cuenta['codigo'] . ' - ' . $cuenta['nombre'], null, null, null, null, null, null, null];

            foreach ($cuenta['movimientos'] as $mov) {
                $nMovs++;
                $data[] = [
                    $fecha($mov['fecha_asiento']),
                    $mov['numero_comprobante'] ?: 'S/N',
                    (string) ($mov['documento_referencia'] ?: ''),
                    (string) ($mov['tercero'] ?: ''),
                    (string) ($mov['referencia_detalle'] ?: $mov['concepto'] ?: ''),
                    (float) $mov['debe'],
                    (float) $mov['haber'],
                    (float) $mov['saldo_acumulado'],
                ];
            }

            $tipos[count($data)] = 'sub';
            $data[] = [null, null, null, null, 'SUBTOTAL ' . $cuenta['codigo'],
                (float) $cuenta['subtotal_debe'], (float) $cuenta['subtotal_haber'], (float) $cuenta['saldo_final']];
        }
        if ($data) {
            $data[] = array_fill(0, 8, null);
            $tipos[count($data)] = 'tot';
            $data[] = [null, null, null, null, 'TOTAL GENERAL', (float) $datos['totales']['debe'], (float) $datos['totales']['haber'], null];
        }

        $debe   = (float) $datos['totales']['debe'];
        $haber  = (float) $datos['totales']['haber'];
        $dinero = static fn (float $v): string => ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);
        $periodo = 'Del ' . $fecha($filtros['fecha_inicio']) . ' al ' . $fecha($filtros['fecha_fin']);
        $info = ['Reporte' => 'Mayores']
            + $this->describirFiltros($idEmpresa, $datos, $filtros, $periodo)
            + [
                'Totales'  => sprintf('%d cuentas  |  %d movimientos  |  Debe %s  |  Haber %s  |  Diferencia %s',
                    count($datos['cuentas']), $nMovs, $dinero($debe), $dinero($haber), $dinero($debe - $haber)),
                'Generado' => date('d-m-Y H:i:s'),
            ];

        $formatos = [6 => '#,##0.00', 7 => '#,##0.00', 8 => '#,##0.00'];
        $libro = $this->reportService->construirSpreadsheet($headers, $data, 'Mayores', $empresaNombre, $info, $formatos);

        // Ajustes sobre las filas de datos (las últimas count($data) filas de la hoja).
        $hoja     = $libro->getActiveSheet();
        $ultima   = $hoja->getHighestRow();
        $primera  = $ultima - count($data) + 1;
        $hoja->freezePane('A' . $primera); // el encabezado queda visible al bajar

        // Glosa y Tercero: ancho fijo con ajuste de texto en lugar del ancho automático.
        foreach (['D' => 35, 'E' => 55] as $letra => $ancho) {
            $hoja->getColumnDimension($letra)->setAutoSize(false)->setWidth($ancho);
        }
        if ($data) {
            $hoja->getStyle("D{$primera}:E{$ultima}")->getAlignment()
                 ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            $hoja->getStyle("A{$primera}:C{$ultima}")->getAlignment()
                 ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $relleno = static fn (string $rgb): array => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb],
        ];
        foreach ($tipos as $i => $tipo) {
            $fila = $primera + $i;
            if ($tipo === 'grp') {
                // La fila de la cuenta ocupa todo el ancho (como en el PDF).
                $hoja->mergeCells("A{$fila}:H{$fila}");
                $hoja->getStyle("A{$fila}:H{$fila}")->applyFromArray([
                    'font' => ['bold' => true], 'fill' => $relleno('D9E2F3'),
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
                ]);
            } else {
                $hoja->getStyle("A{$fila}:H{$fila}")->applyFromArray([
                    'font' => ['bold' => true], 'fill' => $relleno($tipo === 'tot' ? 'BDD7EE' : 'F2F2F2'),
                    'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                ]);
                $hoja->getStyle("E{$fila}")->getAlignment()
                     ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }
        }

        $this->reportService->descargarSpreadsheet($libro, 'Mayores');
    }

    /**
     * PDF del Mayor con el formato común de los reportes (App\Helpers\ReportePdf): logo del
     * establecimiento, caja "Filtros aplicados", banda de indicadores y pie "Página x/y".
     *
     * El listado se escribe por bloques (App\Helpers\PdfPorBloques): una fila por writeHTML,
     * con la cabecera de columnas repetida en cada hoja. En un solo writeHTML el tiempo crece
     * al cuadrado de los movimientos. La cabecera de cada cuenta viaja con su primer
     * movimiento, para no quedar sola al pie de una hoja.
     */
    public function exportarPdf(int $idEmpresa, array $datos, array $filtros, string $empresaNombre): void
    {
        $pt = 7.0;
        $e  = static fn ($v): string => htmlspecialchars((string) $v);
        $m  = static fn ($v): string => number_format((float) $v, 2, '.', ',');
        $fecha = static function ($v): string {
            $ts = strtotime((string) $v);
            return $ts ? date('d-m-Y', $ts) : (string) $v;
        };

        // Fecha | Comprobante | Documento Ref. | Tercero | Glosa | Debe | Haber | Saldo
        $an = [7, 10, 13, 17, 26, 9, 9, 9];
        $texto = static fn (string $t, int $i): string => ReportePdf::texto($t, ReportePdf::anchoPt($an[$i], true), $pt);
        $anTexto = array_sum(array_slice($an, 0, 5));

        $th = '';
        foreach (['Fecha', 'Comprobante', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'] as $i => $lbl) {
            $th .= "<th style='width:{$an[$i]}%;'>{$lbl}</th>";
        }

        // Filas para PdfPorBloques: la cabecera de cada cuenta va junto con su primer
        // movimiento (nunca sola al pie de una hoja) y, si abre hoja, sin el separador.
        $filas = [];
        $nMovs = 0;
        foreach ($datos['cuentas'] as $k => $cuenta) {
            $grp = "<tr class='grp'><td style='width:100%;'>" . $e($cuenta['codigo'] . ' - ' . $cuenta['nombre']) . '</td></tr>';
            $sep = $k > 0 ? ["<tr class='sep'><td style='width:100%;'>&nbsp;</td></tr>"] : [];
            $pendiente = [$grp];

            $i = 0;
            foreach ($cuenta['movimientos'] as $mov) {
                $nMovs++;
                $z = (++$i % 2 === 0) ? 'background:#f6f8fa;' : '';
                $glosa = (string) ($mov['referencia_detalle'] ?: $mov['concepto']);
                $tr = '<tr>'
                    . "<td class='text-center' style='width:{$an[0]}%;{$z}'>" . $fecha($mov['fecha_asiento']) . '</td>'
                    . "<td class='text-center' style='width:{$an[1]}%;{$z}'>" . $texto((string) ($mov['numero_comprobante'] ?: 'S/N'), 1) . '</td>'
                    . "<td style='width:{$an[2]}%;{$z}'>" . $texto((string) $mov['documento_referencia'], 2) . '</td>'
                    . "<td style='width:{$an[3]}%;{$z}'>" . $texto((string) $mov['tercero'], 3) . '</td>'
                    . "<td style='width:{$an[4]}%;{$z}'>" . $texto($glosa, 4) . '</td>'
                    . "<td class='text-end' style='width:{$an[5]}%;{$z}'>" . $m($mov['debe']) . '</td>'
                    . "<td class='text-end' style='width:{$an[6]}%;{$z}'>" . $m($mov['haber']) . '</td>'
                    . "<td class='text-end' style='width:{$an[7]}%;{$z}font-weight:bold;'>" . $m($mov['saldo_acumulado']) . '</td>'
                    . '</tr>';
                if ($pendiente) {
                    $filas[] = ['html' => array_merge($sep, $pendiente, [$tr]), 'html_salto' => array_merge($pendiente, [$tr]), 'alto' => 21.0];
                    $pendiente = [];
                } else {
                    $filas[] = $tr;
                }
            }

            $sub = "<tr class='sub'>"
                . "<td class='text-end' style='width:{$anTexto}%;'>SUBTOTAL " . $e($cuenta['codigo']) . ':</td>'
                . "<td class='text-end' style='width:{$an[5]}%;'>" . $m($cuenta['subtotal_debe']) . '</td>'
                . "<td class='text-end' style='width:{$an[6]}%;'>" . $m($cuenta['subtotal_haber']) . '</td>'
                . "<td class='text-end' style='width:{$an[7]}%;'>" . $m($cuenta['saldo_final']) . '</td>'
                . '</tr>';
            $filas[] = $pendiente
                ? ['html' => array_merge($sep, $pendiente, [$sub]), 'html_salto' => array_merge($pendiente, [$sub]), 'alto' => 21.0]
                : $sub;
        }

        if (!$filas) {
            $filas[] = "<tr><td class='text-center' style='width:100%;padding:8px;'>Sin movimientos para los filtros aplicados.</td></tr>";
            $final   = '';
        } else {
            // El total general va en tabla aparte: un <tfoot> se repetiría al pie de cada hoja.
            $final = "<table class='tot'><tr>"
                . "<td class='text-end' style='width:{$anTexto}%;'>TOTAL GENERAL:</td>"
                . "<td class='text-end' style='width:{$an[5]}%;'>" . $m($datos['totales']['debe']) . '</td>'
                . "<td class='text-end' style='width:{$an[6]}%;'>" . $m($datos['totales']['haber']) . '</td>'
                . "<td style='width:{$an[7]}%;'></td>"
                . '</tr></table>';
        }

        $debe  = (float) $datos['totales']['debe'];
        $haber = (float) $datos['totales']['haber'];
        $kpis = [
            ['Cuentas', (string) count($datos['cuentas'])],
            ['Movimientos', (string) $nMovs],
            ['Total Debe', '$' . $m($debe), false, '#146c43'],
            ['Total Haber', '$' . $m($haber), false, '#b02a37'],
            ['Diferencia', '$' . $m($debe - $haber), true],
        ];

        $subtitulo = 'Del ' . $fecha($filtros['fecha_inicio']) . ' al ' . $fecha($filtros['fecha_fin']);
        $css = "tr.grp td { background:#eaf1fb; border:1px solid #9aa7b4; font-weight:bold; font-size:8pt; padding:3px 5px; color:#1b2a3a; }
                tr.sub td { background:#f1f4f8; font-weight:bold; color:#2c4a6b; }
                tr.sep td { border:none; background:#fff; padding:0; font-size:4.5pt; }";

        $inicio = ReportePdf::inicio($pt,
            ReportePdf::encabezado($idEmpresa, $empresaNombre, 'MAYORES', $subtitulo)
            . ReportePdf::filtros($this->describirFiltros($idEmpresa, $datos, $filtros, $subtitulo))
            . ReportePdf::indicadores($kpis),
            $css);

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
        $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
        // Por bloques: con un solo writeHTML el tiempo crece al cuadrado de los movimientos.
        \App\Helpers\PdfPorBloques::escribir($pdf, $inicio, "<tr>{$th}</tr>", $filas, $final);
        if (ob_get_length()) ob_end_clean();
        $pdf->output('Mayores_' . date('Ymd_His') . '.pdf', 'D');
        exit;
    }

    /**
     * Filtros en texto para el PDF. Los nombres de cuenta y tercero salen de los propios
     * movimientos (con el filtro puesto, todos son de esa cuenta / ese tercero); centro de
     * costo y proyecto, de sus catálogos de la empresa.
     */
    private function describirFiltros(int $idEmpresa, array $datos, array $filtros, string $periodo): array
    {
        $cuenta = 'Todas';
        if (!empty($filtros['id_cuenta'])) {
            $c = $datos['cuentas'][0] ?? null;
            $cuenta = $c ? $c['codigo'] . ' - ' . $c['nombre'] : 'Seleccionada (sin movimientos)';
        }

        $tipos   = ['cliente' => 'Cliente', 'proveedor' => 'Proveedor', 'empleado' => 'Empleado'];
        $tercero = 'Todos';
        if (!empty($filtros['tipo_entidad'])) {
            $tercero = $tipos[$filtros['tipo_entidad']] ?? $filtros['tipo_entidad'];
            if (!empty($filtros['id_entidad'])) {
                $nombre = $datos['cuentas'][0]['movimientos'][0]['tercero'] ?? '';
                $tercero .= ': ' . ($nombre !== '' ? $nombre : 'seleccionado (sin movimientos)');
            }
        }

        $buscar = static function (array $lista, $id): string {
            foreach ($lista as $r) {
                if ((int) $r['id'] === (int) $id) {
                    return $r['codigo'] . ' - ' . $r['nombre'];
                }
            }
            return 'Seleccionado';
        };
        $res = [
            'Período' => $periodo,
            'Cuenta'  => $cuenta,
            'Tercero' => $tercero,
        ];
        // Centro de costo y proyecto solo se listan si se filtró por ellos (muchas empresas no los usan).
        if (!empty($filtros['id_centro_costo'])) {
            $res['Centro de costo'] = $buscar($this->repository->getCentrosCostoActivos($idEmpresa), $filtros['id_centro_costo']);
        }
        if (!empty($filtros['id_proyecto'])) {
            $res['Proyecto'] = $buscar($this->repository->getProyectosActivos($idEmpresa), $filtros['id_proyecto']);
        }
        return $res;
    }
}

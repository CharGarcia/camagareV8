<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use Exception;

class SuscripcionesService
{
    private SuscripcionesRepository $repository;
    private SuscripcionesRules      $rules;
    private LogSistemaService       $logService;

    public function __construct(
        SuscripcionesRepository $repository,
        SuscripcionesRules      $rules,
        LogSistemaService       $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Valores usados por la empresa para los selects del modal de filtros del listado. */
    public function getOpcionesFiltro(int $idEmpresa): array
    {
        return $this->repository->getOpcionesFiltro($idEmpresa);
    }

    /** Búsqueda libre dentro de las suscripciones (ítems, cobros, info adicional) — pestaña Detalles del buscador. */
    public function buscarEnDetalles(int $idEmpresa, string $q, ?int $idUsuario = null, int $limit = 50): array
    {
        return $this->repository->buscarEnDetalles($idEmpresa, $q, $idUsuario, $limit);
    }

    public function getPeriodicidades(): array
    {
        return $this->repository->getPeriodicidades();
    }

    public function getDetalle(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getDetalle($idSuscripcion);
    }

    public function getPagosPorSuscripcion(int $idSuscripcion, int $idEmpresa): array
    {
        $susc = $this->repository->findById($idSuscripcion, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }
        return $this->repository->getPagosPorSuscripcion($idSuscripcion);
    }

    /** Cabecera de la suscripción (con created_by), o null si no es de la empresa o fue eliminada. */
    public function getSuscripcion(int $idSuscripcion, int $idEmpresa): ?array
    {
        return $idSuscripcion > 0 ? $this->repository->findById($idSuscripcion, $idEmpresa) : null;
    }

    /**
     * Pestaña "Facturas" del modal: facturas y recibos de venta emitidos al cliente elegido
     * en el formulario, o —con $soloSuscripcion— solo los que generó esta suscripción, con
     * su estado de cobro y el detalle de productos/servicios. Solo lectura.
     *
     * $fuentes llega resuelto por el controlador según los permisos del usuario sobre
     * Facturas y Recibos de Venta (ver SuscripcionesRepository::getFacturasCliente()).
     */
    public function getFacturasCliente(
        int $idEmpresa,
        int $idCliente,
        int $idSuscripcion,
        bool $soloSuscripcion,
        array $fuentes,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        if ($idSuscripcion > 0 && !$this->repository->findById($idSuscripcion, $idEmpresa)) {
            throw new Exception('La suscripción no existe o ha sido eliminada.');
        }
        // "Solo esta suscripción" necesita una suscripción ya guardada.
        $soloSuscripcion = $soloSuscripcion && $idSuscripcion > 0;

        $idsCliente = [];
        if (!$soloSuscripcion) {
            if ($idCliente <= 0 || !$this->repository->existeCliente($idCliente, $idEmpresa)) {
                throw new Exception('El cliente no existe o ha sido eliminado.');
            }
            // El mismo contribuyente cargado con cédula y con RUC es un solo cliente, igual
            // que en la ficha del cliente y en Cuentas por Cobrar.
            $idsCliente = (new \App\repositories\modulos\ReporteCarteraRepository())
                ->expandirEntidades($idEmpresa, 'CLIENTE', [$idCliente]);
        }

        return $this->repository->getFacturasCliente(
            $idEmpresa, $idsCliente, $idSuscripcion, $soloSuscripcion, $fuentes,
            $buscar, $page, $perPage, $ordenCol, $ordenDir
        );
    }

    public function crear(array $data): int
    {
        $detalle = $this->_extraerDetalle($data);
        $data['info_adicional'] = $this->_extraerInfoAdicional($data);
        $this->rules->validar($data, $detalle);
        $data['proximo_cobro'] = $data['proximo_cobro'] ?? $data['fecha_inicio'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'suscripciones', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $detalle = $this->_extraerDetalle($data);
        $data['info_adicional'] = $this->_extraerInfoAdicional($data);
        $this->rules->validar($data, $detalle);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ha sido eliminada.');
        }

        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);

            // Reemplazar detalle: soft-delete los existentes e insertar los nuevos
            $this->repository->deleteDetalle($id, $idUsuario);
            foreach ($detalle as $item) {
                $item['id_suscripcion'] = $id;
                $item['id_empresa']     = $idEmpresa;
                $item['created_by']     = $idUsuario;
                $this->repository->insertDetalle($item);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'suscripciones', $id, $antes, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $estadosValidos = ['activo', 'pausado', 'suspendido', 'cancelado'];
        if (!in_array($estado, $estadosValidos, true)) {
            throw new Exception("Estado '$estado' no válido.");
        }

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateEstado($id, $estado, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'cambiar_estado', 'suscripciones', $id, $antes, ['estado' => $estado]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function guardarTokenKushki(int $id, int $idEmpresa, array $tokenData, int $idUsuario): void
    {
        $susc = $this->repository->findById($id, $idEmpresa);
        if (!$susc) {
            throw new Exception('Suscripción no encontrada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateKushkiToken(
                $id,
                $idEmpresa,
                $tokenData['token'],
                $tokenData['last4'],
                $tokenData['brand'],
                $tokenData['card_holder_name'] ?? ''
            );
            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar_tarjeta', 'suscripciones', $id, null, ['last4' => $tokenData['last4']]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La suscripción no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'suscripciones', $id, $antes, ['eliminado' => true]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Cuántos cobros genera una periodicidad en un año (Diario/Semanal/Quincenal tienen meses = 1 en el catálogo). */
    public function cobrosPorAnio(int $meses, string $codigo = ''): float
    {
        return match (strtoupper($codigo)) {
            'DIARIO'    => 365.0,
            'SEMANAL'   => 52.0,
            'QUINCENAL' => 24.0,
            default     => $meses > 0 ? 12 / $meses : 0.0,
        };
    }

    /**
     * Resumen de valores de las suscripciones exportadas, listo para dibujar en
     * Excel o PDF: cuatro secciones (por periodicidad, por concepto, conceptos por
     * periodicidad y por tarifa de IVA). Los valores son los de UN cobro; la
     * proyección multiplica por los cobros que genera la periodicidad en un año.
     *
     * Cada sección: ['titulo', 'cabeceras' => [...], 'monedas' => [índices 0-based],
     * 'filas' => [['tipo' => 'dato'|'grupo'|'total', 'valores' => [...]]]].
     * Los centavos se redondean por línea, así las cuatro secciones suman lo mismo.
     *
     * @param array $rows Filas del listado ya filtradas (buscador + registros propios).
     */
    public function getResumenValores(int $idEmpresa, array $rows): array
    {
        $lineas = $this->repository->getResumenValores($idEmpresa, array_column($rows, 'id'));

        // Suscripciones por periodicidad (del listado: incluye las que no tienen ítems).
        $suscPorPer = [];
        foreach ($rows as $r) {
            $k = (int) ($r['id_periodicidad'] ?? 0);
            $suscPorPer[$k] = ($suscPorPer[$k] ?? 0) + 1;
        }

        $porPer = $porConcepto = $porTarifa = $conceptosPorPer = [];
        foreach ($lineas as $l) {
            $kPer  = (int) $l['id_periodicidad'];
            $anio  = $this->cobrosPorAnio((int) $l['meses'], (string) $l['codigo_periodicidad']);
            $base  = round((float) $l['base'], 2);
            $iva   = round((float) $l['iva'], 2);
            $kCon  = (int) $l['id_producto'];
            $kTar  = (string) $l['tarifa'] . '|' . $l['porcentaje_iva'];

            $porPer[$kPer] ??= ['nombre' => $l['periodicidad'], 'anio' => $anio, 'base' => 0.0, 'iva' => 0.0, 'anual' => 0.0];
            $porPer[$kPer]['base']  += $base;
            $porPer[$kPer]['iva']   += $iva;
            $porPer[$kPer]['anual'] += ($base + $iva) * $anio;

            $porConcepto[$kCon] ??= ['codigo' => $l['codigo'], 'nombre' => $l['concepto'], 'susc' => 0, 'cant' => 0.0, 'base' => 0.0, 'iva' => 0.0, 'anual' => 0.0];
            $porConcepto[$kCon]['susc']  += (int) $l['suscripciones'];
            $porConcepto[$kCon]['cant']  += (float) $l['cantidad'];
            $porConcepto[$kCon]['base']  += $base;
            $porConcepto[$kCon]['iva']   += $iva;
            $porConcepto[$kCon]['anual'] += ($base + $iva) * $anio;

            $porTarifa[$kTar] ??= ['nombre' => $l['tarifa'], 'pct' => (float) $l['porcentaje_iva'], 'base' => 0.0, 'iva' => 0.0, 'base_anual' => 0.0, 'iva_anual' => 0.0];
            $porTarifa[$kTar]['base']       += $base;
            $porTarifa[$kTar]['iva']        += $iva;
            $porTarifa[$kTar]['base_anual'] += $base * $anio;
            $porTarifa[$kTar]['iva_anual']  += $iva * $anio;

            $conceptosPorPer[$kPer][] = $l;
        }
        // Periodicidades de las suscripciones sin ítems (aparecen con valores en cero).
        foreach ($rows as $r) {
            $k = (int) ($r['id_periodicidad'] ?? 0);
            $porPer[$k] ??= [
                'nombre' => $r['nombre_periodicidad'] ?? 'Sin periodicidad',
                'anio'   => $this->cobrosPorAnio((int) ($r['periodicidad_meses'] ?? 0), (string) ($r['codigo_periodicidad'] ?? '')),
                'base'   => 0.0, 'iva' => 0.0, 'anual' => 0.0,
            ];
        }
        // Más frecuentes primero (Diario, Semanal, …, Mensual, …, Anual).
        uasort($porPer, static fn($a, $b) => $b['anio'] <=> $a['anio'] ?: strcmp((string) $a['nombre'], (string) $b['nombre']));
        uasort($porConcepto, static fn($a, $b) => ($b['base'] + $b['iva']) <=> ($a['base'] + $a['iva']));
        uasort($porTarifa, static fn($a, $b) => $b['pct'] <=> $a['pct']);

        $dato  = static fn(array $v) => ['tipo' => 'dato',  'valores' => $v];
        $total = static fn(array $v) => ['tipo' => 'total', 'valores' => $v];
        $secciones = [];

        // 1. Por periodicidad
        $filas = [];
        $t = ['susc' => 0, 'base' => 0.0, 'iva' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        foreach ($porPer as $k => $p) {
            $b = round($p['base'], 2);
            $i = round($p['iva'], 2);
            $filas[] = $dato([(string) $p['nombre'], $suscPorPer[$k] ?? 0, $b, $i, $b + $i, $p['anio'], round($p['anual'] / 12, 2), round($p['anual'], 2)]);
            $t['susc'] += $suscPorPer[$k] ?? 0;
            $t['base'] += $b;
            $t['iva']  += $i;
            $t['mes']  += round($p['anual'] / 12, 2);
            $t['anio'] += round($p['anual'], 2);
        }
        $filas[] = $total(['TOTAL', $t['susc'], $t['base'], $t['iva'], $t['base'] + $t['iva'], '', $t['mes'], $t['anio']]);
        $secciones[] = [
            'titulo'    => 'Por periodicidad',
            'cabeceras' => ['Periodicidad', 'Suscripciones', 'Subtotal', 'IVA', 'Total por cobro', 'Cobros al año', 'Proyección mensual', 'Proyección anual'],
            'monedas'   => [2, 3, 4, 6, 7],
            'filas'     => $filas,
        ];

        // 2. Por concepto
        $filas = [];
        $t = ['base' => 0.0, 'iva' => 0.0, 'anio' => 0.0];
        foreach ($porConcepto as $c) {
            $b = round($c['base'], 2);
            $i = round($c['iva'], 2);
            $filas[] = $dato([(string) $c['codigo'], (string) $c['nombre'], $c['susc'], round($c['cant'], 2), $b, $i, $b + $i, round($c['anual'], 2)]);
            $t['base'] += $b;
            $t['iva']  += $i;
            $t['anio'] += round($c['anual'], 2);
        }
        $filas[] = $total(['', 'TOTAL', '', '', $t['base'], $t['iva'], $t['base'] + $t['iva'], $t['anio']]);
        $secciones[] = [
            'titulo'    => 'Por concepto',
            'cabeceras' => ['Código', 'Concepto', 'Suscripciones', 'Cantidad', 'Subtotal', 'IVA', 'Total por cobro', 'Proyección anual'],
            'monedas'   => [4, 5, 6, 7],
            'filas'     => $filas,
        ];

        // 3. Conceptos por periodicidad
        $filas = [];
        foreach ($porPer as $k => $p) {
            if (empty($conceptosPorPer[$k])) {
                continue;
            }
            $filas[] = ['tipo' => 'grupo', 'valores' => [mb_strtoupper((string) $p['nombre'])]];
            $sb = $si = 0.0;
            foreach ($conceptosPorPer[$k] as $l) {
                $b = round((float) $l['base'], 2);
                $i = round((float) $l['iva'], 2);
                $filas[] = $dato([(string) $l['codigo'], (string) $l['concepto'], (string) $l['tarifa'], (int) $l['suscripciones'], round((float) $l['cantidad'], 2), $b, $i, $b + $i]);
                $sb += $b;
                $si += $i;
            }
            $filas[] = $total(['', 'Subtotal ' . $p['nombre'], '', '', '', $sb, $si, $sb + $si]);
        }
        $secciones[] = [
            'titulo'    => 'Conceptos por periodicidad',
            'cabeceras' => ['Código', 'Concepto', 'Tarifa IVA', 'Suscripciones', 'Cantidad', 'Subtotal', 'IVA', 'Total por cobro'],
            'monedas'   => [5, 6, 7],
            'filas'     => $filas,
        ];

        // 4. Por tarifa de IVA
        $filas = [];
        $t = ['base' => 0.0, 'iva' => 0.0, 'ba' => 0.0, 'ia' => 0.0];
        foreach ($porTarifa as $x) {
            $b  = round($x['base'], 2);
            $i  = round($x['iva'], 2);
            $ba = round($x['base_anual'], 2);
            $ia = round($x['iva_anual'], 2);
            $filas[] = $dato([(string) $x['nombre'], $x['pct'], $b, $i, $b + $i, $ba, $ia, $ba + $ia]);
            $t['base'] += $b; $t['iva'] += $i; $t['ba'] += $ba; $t['ia'] += $ia;
        }
        $filas[] = $total(['TOTAL', '', $t['base'], $t['iva'], $t['base'] + $t['iva'], $t['ba'], $t['ia'], $t['ba'] + $t['ia']]);
        $secciones[] = [
            'titulo'    => 'Por tarifa de IVA',
            'cabeceras' => ['Tarifa', '% IVA', 'Base imponible', 'IVA', 'Total por cobro', 'Base anual', 'IVA anual', 'Total anual'],
            'monedas'   => [2, 3, 4, 5, 6, 7],
            'filas'     => $filas,
        ];

        return $secciones;
    }

    /**
     * Agrega al libro del Excel del listado la hoja "Resumen" (ver getResumenValores()),
     * de las mismas suscripciones exportadas en la primera hoja.
     *
     * @param array $rows Filas del listado ya filtradas (buscador + registros propios).
     */
    public function agregarHojaResumenExcel(\PhpOffice\PhpSpreadsheet\Spreadsheet $libro, int $idEmpresa, array $rows, string $textoFiltro = ''): void
    {
        $secciones = $this->getResumenValores($idEmpresa, $rows);

        $h = $libro->createSheet();
        $h->setTitle('Resumen');
        $cab = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $tot = [
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $col = static fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);

        $h->setCellValue('A1', 'RESUMEN DE VALORES DE SUSCRIPCIONES');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $h->setCellValueExplicit('A2', 'Filtro de búsqueda: ' . $textoFiltro . ' — Suscripciones incluidas: ' . count($rows) . ' (las mismas de la hoja del listado).', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $h->setCellValue('A3', 'Subtotal, IVA y Total son el valor de UN cobro de cada suscripción; la proyección multiplica por los cobros que genera su periodicidad en un año.');
        $h->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('555555');
        $h->getStyle('A2')->getFont()->setBold(true);
        $fila = 5;

        foreach ($secciones as $s) {
            $h->setCellValue('A' . $fila, $s['titulo']);
            $h->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12);
            $fila++;

            $nCols = count($s['cabeceras']);
            foreach ($s['cabeceras'] as $i => $t) {
                $h->setCellValueExplicit($col($i + 1) . $fila, $t, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $h->getStyle("A{$fila}:" . $col($nCols) . $fila)->applyFromArray($cab);
            $fila++;

            foreach ($s['filas'] as $f) {
                if ($f['tipo'] === 'grupo') {
                    $h->setCellValue('A' . $fila, $f['valores'][0]);
                    $h->getStyle('A' . $fila)->getFont()->setBold(true)->getColor()->setRGB('1F4E79');
                    $fila++;
                    continue;
                }
                foreach ($f['valores'] as $i => $v) {
                    $celda = $col($i + 1) . $fila;
                    if (is_string($v)) {
                        $h->setCellValueExplicit($celda, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $h->setCellValue($celda, $v);
                    }
                    if (in_array($i, $s['monedas'], true)) {
                        $h->getStyle($celda)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }
                if ($f['tipo'] === 'total') {
                    $h->getStyle("A{$fila}:" . $col($nCols) . $fila)->applyFromArray($tot);
                }
                $fila++;
            }
            $fila++;
        }

        foreach (range(1, 8) as $i) {
            $h->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        // Las notas de A1:A3 no deben ensanchar la columna A.
        $h->getColumnDimension('A')->setAutoSize(false)->setWidth(22);
        $h->getColumnDimension('B')->setAutoSize(false)->setWidth(45);
    }

    /**
     * Detalle por cliente de las suscripciones exportadas, listo para dibujar en
     * Excel o PDF: clientes (alfabético) → suscripciones → ítems que se facturan en
     * cada cobro, con subtotal, IVA, total y proyección anual, más la información
     * adicional de cada suscripción y los totales por cliente y general.
     * Centavos redondeados por línea (igual que getResumenValores(): los totales coinciden).
     *
     * @param array $rows Filas del listado ya filtradas (buscador + registros propios).
     * @return array{clientes: array, general: array, max_info: int}
     */
    public function getDetalleClientes(int $idEmpresa, array $rows): array
    {
        $itemsPorSusc = [];
        foreach ($this->repository->getDetalleValores($idEmpresa, array_column($rows, 'id')) as $it) {
            $itemsPorSusc[(int) $it['id_suscripcion']][] = $it;
        }

        // Agrupado por cliente (alfabético), sin importar el orden del listado.
        usort($rows, static fn($a, $b) => strcasecmp((string) ($a['nombre_cliente'] ?? ''), (string) ($b['nombre_cliente'] ?? ''))
            ?: ((int) $a['id_cliente'] <=> (int) $b['id_cliente'])
            ?: ((int) $a['id'] <=> (int) $b['id']));

        $fecha    = static fn($v) => !empty($v) ? date('d-m-Y', strtotime((string) $v)) : '-';
        $clientes = [];
        $general  = ['susc' => 0, 'base' => 0.0, 'iva' => 0.0, 'anual' => 0.0];
        $maxInfo  = 0;

        foreach ($rows as $r) {
            $idCliente = (int) ($r['id_cliente'] ?? 0);
            $clientes[$idCliente] ??= [
                'nombre'         => (string) ($r['nombre_cliente'] ?? '') ?: '(cliente no encontrado)',
                'identificacion' => (string) ($r['identificacion_cliente'] ?? ''),
                'email'          => (string) ($r['email_cliente'] ?? ''),
                'suscripciones'  => [],
                'totales'        => ['susc' => 0, 'base' => 0.0, 'iva' => 0.0, 'anual' => 0.0],
            ];
            $c = &$clientes[$idCliente];

            // Información adicional (JSON [{concepto, detalle}]).
            $infoRaw = $r['info_adicional'] ?? null;
            $arr     = is_array($infoRaw) ? $infoRaw : json_decode((string) $infoRaw, true);
            $info    = [];
            foreach (is_array($arr) ? $arr : [] as $x) {
                if (!is_array($x)) {
                    continue;
                }
                $concepto = trim((string) ($x['concepto'] ?? ''));
                $detalle  = trim((string) ($x['detalle'] ?? ''));
                if ($concepto !== '' || $detalle !== '') {
                    $info[] = ['concepto' => $concepto, 'detalle' => $detalle];
                }
            }
            $maxInfo = max($maxInfo, count($info));

            $anio  = $this->cobrosPorAnio((int) ($r['periodicidad_meses'] ?? 0), (string) ($r['codigo_periodicidad'] ?? ''));
            $items = [];
            foreach ($itemsPorSusc[(int) $r['id']] ?? [] as $it) {
                $base  = round((float) $it['base'], 2);
                $iva   = round((float) $it['iva'], 2);
                $anual = round(($base + $iva) * $anio, 2);
                $desc  = trim((string) $it['descripcion']);
                $items[] = [
                    'codigo'      => (string) $it['codigo'],
                    'concepto'    => (string) $it['concepto'],
                    'descripcion' => $desc !== $it['concepto'] ? $desc : '',
                    'cantidad'    => round((float) $it['cantidad'], 6),
                    'precio'      => round((float) $it['precio_unitario'], 6),
                    'base'        => $base,
                    'tarifa'      => (string) $it['tarifa'],
                    'pct'         => (float) $it['porcentaje_iva'],
                    'iva'         => $iva,
                    'total'       => $base + $iva,
                    'anual'       => $anual,
                ];
                $c['totales']['base']  += $base;  $general['base']  += $base;
                $c['totales']['iva']   += $iva;   $general['iva']   += $iva;
                $c['totales']['anual'] += $anual; $general['anual'] += $anual;
            }

            $c['suscripciones'][] = [
                'periodicidad'  => (string) ($r['nombre_periodicidad'] ?? '-'),
                'estado'        => ucfirst((string) ($r['estado'] ?? 'activo')),
                'comprobante'   => ucfirst((string) ($r['tipo_comprobante'] ?? 'factura')),
                'proximo_cobro' => $fecha($r['proximo_cobro'] ?? null),
                'anio'          => $anio,
                'info'          => $info,
                'items'         => $items,
            ];
            $c['totales']['susc']++;
            $general['susc']++;
            unset($c);
        }

        return ['clientes' => array_values($clientes), 'general' => $general, 'max_info' => $maxInfo];
    }

    /**
     * Agrega al libro del Excel del listado la hoja "Detalle por cliente" (ver
     * getDetalleClientes()): una fila por cada ítem que se factura, con la info
     * adicional al final (un par Concepto/Detalle por línea), total por cliente y
     * total general.
     *
     * @param array $rows Filas del listado ya filtradas (buscador + registros propios).
     */
    public function agregarHojaDetalleClientesExcel(\PhpOffice\PhpSpreadsheet\Spreadsheet $libro, int $idEmpresa, array $rows, string $textoFiltro = ''): void
    {
        $det     = $this->getDetalleClientes($idEmpresa, $rows);
        $maxInfo = $det['max_info'];

        $h = $libro->createSheet();
        $h->setTitle('Detalle por cliente');
        $Fill   = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $Border = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $col    = static fn(int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);

        $cabeceras = [
            'Cliente', 'Identificación', 'Correo', 'Periodicidad', 'Estado', 'Comprobante', 'Próximo cobro',
            'Código', 'Concepto', 'Descripción', 'Cantidad', 'Precio unitario', 'Subtotal',
            'Tarifa IVA', '% IVA', 'IVA', 'Total por cobro', 'Cobros al año', 'Proyección anual',
        ];
        $nFijas = count($cabeceras);
        for ($i = 1; $i <= $maxInfo; $i++) {
            $sufijo      = $maxInfo > 1 ? " {$i}" : '';
            $cabeceras[] = "Info adicional{$sufijo} – Concepto";
            $cabeceras[] = "Info adicional{$sufijo} – Detalle";
        }
        $nCols   = count($cabeceras);
        $ultima  = $col($nCols);
        $monedas = [12, 13, 16, 17, 19];   // precio, subtotal, IVA, total, proyección

        $h->setCellValue('A1', 'DETALLE DE SUSCRIPCIONES POR CLIENTE');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $h->setCellValueExplicit('A2', 'Filtro de búsqueda: ' . $textoFiltro . ' — Suscripciones incluidas: ' . count($rows) . '. Una fila por cada ítem que se factura en cada cobro.', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $h->getStyle('A2')->getFont()->setItalic(true)->setBold(true)->getColor()->setRGB('555555');

        $fila = 4;
        foreach ($cabeceras as $i => $t) {
            $h->setCellValueExplicit($col($i + 1) . $fila, $t, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $h->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => $Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => $Border::BORDER_THIN]],
        ]);
        $h->freezePane('A' . ($fila + 1));
        $fila++;

        $estiloTotal = function (int $f, string $rgb) use ($h, $ultima, $Fill, $Border): void {
            $h->getStyle("A{$f}:{$ultima}{$f}")->applyFromArray([
                'font'    => ['bold' => true],
                'fill'    => ['fillType' => $Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
                'borders' => ['top' => ['borderStyle' => $Border::BORDER_THIN]],
            ]);
        };
        $escribir = function (array $valores) use ($h, $col, &$fila): void {
            foreach ($valores as $i => $v) {
                if ($v === null) {
                    continue;
                }
                $celda = $col($i + 1) . $fila;
                if (is_string($v)) {
                    $h->setCellValueExplicit($celda, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $h->setCellValue($celda, $v);
                }
            }
            $fila++;
        };
        $filaTotal = static fn(string $etiqueta, array $t) => [
            $etiqueta, null, null, null, null, null, null, null, null, null, null, null,
            $t['base'], null, null, $t['iva'], $t['base'] + $t['iva'], null, $t['anual'],
        ];

        $primeraDatos = $fila;
        foreach ($det['clientes'] as $c) {
            foreach ($c['suscripciones'] as $s) {
                $datosSusc = [$c['nombre'], $c['identificacion'], $c['email'], $s['periodicidad'], $s['estado'], $s['comprobante'], $s['proximo_cobro']];
                // Pares Concepto/Detalle de la info adicional, rellenos hasta $maxInfo pares.
                $info = [];
                foreach ($s['info'] as $x) {
                    $info[] = $x['concepto'];
                    $info[] = $x['detalle'];
                }
                $info = array_pad($info, $maxInfo * 2, '');

                if (!$s['items']) {
                    $escribir(array_merge($datosSusc, ['', 'Sin ítems registrados'], array_fill(0, $nFijas - count($datosSusc) - 2, null), $info));
                    continue;
                }
                foreach ($s['items'] as $it) {
                    $escribir(array_merge($datosSusc, [
                        $it['codigo'], $it['concepto'], $it['descripcion'], $it['cantidad'], $it['precio'], $it['base'],
                        $it['tarifa'], $it['pct'], $it['iva'], $it['total'], $s['anio'], $it['anual'],
                    ], $info));
                }
            }
            $n = $c['totales']['susc'];
            $escribir($filaTotal('Total ' . $c['nombre'] . ' (' . $n . ($n === 1 ? ' suscripción' : ' suscripciones') . ')', $c['totales']));
            $estiloTotal($fila - 1, 'D9E1F2');
        }
        $escribir($filaTotal('TOTAL GENERAL', $det['general']));
        $estiloTotal($fila - 1, 'B4C6E7');

        if ($fila > $primeraDatos) {
            foreach ($monedas as $i) {
                $h->getStyle($col($i) . $primeraDatos . ':' . $col($i) . ($fila - 1))
                  ->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        for ($i = 1; $i <= $nCols; $i++) {
            $h->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        // Título y nota de A1:A2 no deben ensanchar la columna del cliente.
        $h->getColumnDimension('A')->setAutoSize(false)->setWidth(38);
        // Detalles de info adicional largos: ancho acotado con ajuste de texto.
        for ($i = $nFijas + 2; $i <= $nCols; $i += 2) {
            $h->getColumnDimensionByColumn($i)->setAutoSize(false)->setWidth(50);
            $h->getStyle($col($i) . ($primeraDatos) . ':' . $col($i) . max($primeraDatos, $fila - 1))->getAlignment()->setWrapText(true);
        }
    }

    public function calcularProximoCobro(string $fechaActual, int $meses, string $codigo = ''): string
    {
        $dt = new \DateTime($fechaActual);
        if ($codigo === 'DIARIO') {
            $dt->modify('+1 day');
        } elseif ($codigo === 'SEMANAL') {
            $dt->modify('+7 days');
        } elseif ($codigo === 'QUINCENAL') {
            $dt->modify('+15 days');
        } else {
            $dt->modify("+{$meses} months");
        }
        return $dt->format('Y-m-d');
    }

    /**
     * Extrae las filas de detalle del array $data enviado por el formulario.
     * El frontend envía: detalle[0][id_producto], detalle[0][descripcion], etc.
     */
    private function _extraerDetalle(array &$data): array
    {
        $detalle = $data['detalle'] ?? [];
        unset($data['detalle']);

        // Filtrar filas vacías (sin producto)
        return array_values(array_filter($detalle, fn($item) => !empty($item['id_producto'])));
    }

    /**
     * Extrae las filas de información adicional (concepto/detalle) del formulario
     * y las devuelve como JSON listo para la columna info_adicional (o null si vacío).
     * El frontend envía: info_adicional[0][concepto], info_adicional[0][detalle], ...
     */
    private function _extraerInfoAdicional(array &$data): ?string
    {
        $filas = $data['info_adicional'] ?? [];
        unset($data['info_adicional']);

        $limpias = [];
        foreach ((array) $filas as $fila) {
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            $detalle  = trim((string) ($fila['detalle']  ?? ''));
            if ($concepto !== '' || $detalle !== '') {
                $limpias[] = ['concepto' => $concepto, 'detalle' => $detalle];
            }
        }

        return $limpias ? json_encode($limpias, JSON_UNESCAPED_UNICODE) : null;
    }
}

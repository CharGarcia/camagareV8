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
     * Agrega al libro del Excel del listado la hoja "Resumen": valores por
     * periodicidad, por concepto, conceptos dentro de cada periodicidad y por
     * tarifa de IVA, de las mismas suscripciones exportadas en la primera hoja.
     *
     * @param array $rows Filas del listado ya filtradas (buscador + registros propios).
     */
    public function agregarHojaResumenExcel(\PhpOffice\PhpSpreadsheet\Spreadsheet $libro, int $idEmpresa, array $rows): void
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
            // Redondeo por línea: así las cuatro secciones suman exactamente lo mismo.
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
                'anio'   => $this->cobrosPorAnio((int) ($r['periodicidad_meses'] ?? 0), ''),
                'base'   => 0.0, 'iva' => 0.0, 'anual' => 0.0,
            ];
        }
        // Más frecuentes primero (Diario, Semanal, …, Mensual, …, Anual).
        uasort($porPer, static fn($a, $b) => $b['anio'] <=> $a['anio'] ?: strcmp((string) $a['nombre'], (string) $b['nombre']));
        uasort($porConcepto, static fn($a, $b) => ($b['base'] + $b['iva']) <=> ($a['base'] + $a['iva']));
        uasort($porTarifa, static fn($a, $b) => $b['pct'] <=> $a['pct']);

        // ── Escritura de la hoja ─────────────────────────────────────────────
        $h = $libro->createSheet();
        $h->setTitle('Resumen');
        $fmtMoneda = '#,##0.00';
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
        $fila = 1;

        // Escribe una fila; $monedas = índices (1-based) con formato de moneda.
        $escribir = function (array $valores, array $monedas = [], ?array $estilo = null) use ($h, $col, &$fila, $fmtMoneda): void {
            foreach (array_values($valores) as $i => $v) {
                $celda = $col($i + 1) . $fila;
                if (is_string($v)) {
                    $h->setCellValueExplicit($celda, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $h->setCellValue($celda, $v);
                }
                if (in_array($i + 1, $monedas, true)) {
                    $h->getStyle($celda)->getNumberFormat()->setFormatCode($fmtMoneda);
                }
            }
            if ($estilo) {
                $h->getStyle('A' . $fila . ':' . $col(count($valores)) . $fila)->applyFromArray($estilo);
            }
            $fila++;
        };
        $titulo = function (string $texto) use ($h, &$fila): void {
            $h->setCellValue('A' . $fila, $texto);
            $h->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12);
            $fila++;
        };

        $h->setCellValue('A1', 'RESUMEN DE VALORES DE SUSCRIPCIONES');
        $h->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $h->setCellValue('A2', 'Suscripciones incluidas: ' . count($rows) . ' (las mismas del listado, según los filtros aplicados).');
        $h->setCellValue('A3', 'Subtotal, IVA y Total son el valor de UN cobro de cada suscripción; la proyección multiplica por los cobros que genera su periodicidad en un año.');
        $h->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('555555');
        $fila = 5;

        // 1. Por periodicidad
        $titulo('Por periodicidad');
        $escribir(['Periodicidad', 'Suscripciones', 'Subtotal', 'IVA', 'Total por cobro', 'Cobros al año', 'Proyección mensual', 'Proyección anual'], [], $cab);
        $t = ['susc' => 0, 'base' => 0.0, 'iva' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        foreach ($porPer as $k => $p) {
            $total = round($p['base'], 2) + round($p['iva'], 2);
            $anual = $p['anual'];
            $escribir([(string) $p['nombre'], $suscPorPer[$k] ?? 0, round($p['base'], 2), round($p['iva'], 2), $total, $p['anio'], round($anual / 12, 2), round($anual, 2)], [3, 4, 5, 7, 8]);
            $t['susc'] += $suscPorPer[$k] ?? 0;
            $t['base'] += round($p['base'], 2);
            $t['iva']  += round($p['iva'], 2);
            $t['mes']  += round($anual / 12, 2);
            $t['anio'] += round($anual, 2);
        }
        $escribir(['TOTAL', $t['susc'], $t['base'], $t['iva'], $t['base'] + $t['iva'], '', $t['mes'], $t['anio']], [3, 4, 5, 7, 8], $tot);
        $fila++;

        // 2. Por concepto
        $titulo('Por concepto');
        $escribir(['Código', 'Concepto', 'Suscripciones', 'Cantidad', 'Subtotal', 'IVA', 'Total por cobro', 'Proyección anual'], [], $cab);
        $t = ['base' => 0.0, 'iva' => 0.0, 'anio' => 0.0];
        foreach ($porConcepto as $c) {
            $escribir([(string) $c['codigo'], (string) $c['nombre'], $c['susc'], round($c['cant'], 2), round($c['base'], 2), round($c['iva'], 2), round($c['base'], 2) + round($c['iva'], 2), round($c['anual'], 2)], [5, 6, 7, 8]);
            $t['base'] += round($c['base'], 2);
            $t['iva']  += round($c['iva'], 2);
            $t['anio'] += round($c['anual'], 2);
        }
        $escribir(['', 'TOTAL', '', '', $t['base'], $t['iva'], $t['base'] + $t['iva'], $t['anio']], [5, 6, 7, 8], $tot);
        $fila++;

        // 3. Conceptos por periodicidad
        $titulo('Conceptos por periodicidad');
        $escribir(['Código', 'Concepto', 'Tarifa IVA', 'Suscripciones', 'Cantidad', 'Subtotal', 'IVA', 'Total por cobro'], [], $cab);
        foreach ($porPer as $k => $p) {
            if (empty($conceptosPorPer[$k])) {
                continue;
            }
            $h->setCellValue('A' . $fila, mb_strtoupper((string) $p['nombre']));
            $h->getStyle('A' . $fila)->getFont()->setBold(true)->getColor()->setRGB('1F4E79');
            $fila++;
            $sb = $si = 0.0;
            foreach ($conceptosPorPer[$k] as $l) {
                $b = round((float) $l['base'], 2);
                $i = round((float) $l['iva'], 2);
                $escribir([(string) $l['codigo'], (string) $l['concepto'], (string) $l['tarifa'], (int) $l['suscripciones'], round((float) $l['cantidad'], 2), $b, $i, $b + $i], [6, 7, 8]);
                $sb += $b;
                $si += $i;
            }
            $escribir(['', 'Subtotal ' . $p['nombre'], '', '', '', $sb, $si, $sb + $si], [6, 7, 8], $tot);
        }
        $fila++;

        // 4. Por impuesto
        $titulo('Por tarifa de IVA');
        $escribir(['Tarifa', '% IVA', 'Base imponible', 'IVA', 'Total por cobro', 'Base anual', 'IVA anual', 'Total anual'], [], $cab);
        $t = ['base' => 0.0, 'iva' => 0.0, 'ba' => 0.0, 'ia' => 0.0];
        foreach ($porTarifa as $x) {
            $b  = round($x['base'], 2);
            $i  = round($x['iva'], 2);
            $ba = round($x['base_anual'], 2);
            $ia = round($x['iva_anual'], 2);
            $escribir([(string) $x['nombre'], $x['pct'], $b, $i, $b + $i, $ba, $ia, $ba + $ia], [3, 4, 5, 6, 7, 8]);
            $t['base'] += $b; $t['iva'] += $i; $t['ba'] += $ba; $t['ia'] += $ia;
        }
        $escribir(['TOTAL', '', $t['base'], $t['iva'], $t['base'] + $t['iva'], $t['ba'], $t['ia'], $t['ba'] + $t['ia']], [3, 4, 5, 6, 7, 8], $tot);

        foreach (range(1, 8) as $i) {
            $h->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        // Las notas de A1:A3 no deben ensanchar la columna A.
        $h->getColumnDimension('A')->setAutoSize(false)->setWidth(22);
        $h->getColumnDimension('B')->setAutoSize(false)->setWidth(45);
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

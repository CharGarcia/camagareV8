<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ControlBancarioRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\ControlBancarioRules;
use App\Services\LogSistemaService;
use App\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use TCPDF;

class ControlBancarioService
{
    private EmpresaRepository $empresaRepo;

    public function __construct(
        private ControlBancarioRepository $repository,
        private ControlBancarioRules $rules,
        private LogSistemaService $logService,
        private ReportService $reportService,
        ?EmpresaRepository $empresaRepo = null
    ) {
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
    }

    public function getFormasBancarias(int $idEmpresa): array
    {
        return $this->repository->getFormasBancarias($idEmpresa);
    }

    // ── Grupo RUC (conciliación consolidada entre establecimientos) ─────────

    /** IDs de empresas del mismo RUC accesibles al usuario. Ver EmpresaRepository::getIdsGrupoRucAccesible(). */
    private function idsGrupoAccesible(int $idEmpresa, int $idUsuario): array
    {
        return $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
    }

    /** ¿$idUsuario puede operar (clasificar/quitar) sobre $idEmpresaObjetivo desde su sesión en $idEmpresaActiva? */
    public function empresaAccesibleDelGrupo(int $idEmpresaActiva, int $idEmpresaObjetivo, int $idUsuario): bool
    {
        if ($idEmpresaObjetivo === $idEmpresaActiva) {
            return true;
        }
        return in_array($idEmpresaObjetivo, $this->idsGrupoAccesible($idEmpresaActiva, $idUsuario), true);
    }

    /**
     * Todas las formas bancarias del grupo RUC accesible, agrupadas por cuenta real
     * (banco + número de cuenta). Sirve para que el selector muestre, junto a cada cuenta de
     * la empresa activa, cuántos establecimientos más comparten esa misma cuenta.
     *
     * @return array<int, array{id_banco:int, numero_cuenta:string, formas:array}> indexado por
     *         id_forma_pago de la empresa activa (para casar 1 a 1 con getFormasBancarias()).
     */
    public function getGruposDeCuentas(int $idEmpresa, int $idUsuario): array
    {
        $idsGrupo = $this->idsGrupoAccesible($idEmpresa, $idUsuario);
        if (count($idsGrupo) <= 1) {
            return [];
        }
        $todas = $this->repository->getFormasBancariasDeEmpresas($idsGrupo);

        // Agrupar por (id_banco, numero_cuenta normalizado).
        $porCuenta = [];
        foreach ($todas as $f) {
            if (empty($f['id_banco']) || trim((string) $f['numero_cuenta']) === '') {
                continue;
            }
            $clave = $f['id_banco'] . '|' . trim((string) $f['numero_cuenta']);
            $porCuenta[$clave][] = $f;
        }

        // Solo interesan los grupos con formas de MÁS DE UNA empresa (si no, no hay nada que consolidar).
        $out = [];
        foreach ($porCuenta as $formas) {
            $idsEmpresasDelGrupo = array_unique(array_map(static fn ($f) => (int) $f['id_empresa'], $formas));
            if (count($idsEmpresasDelGrupo) < 2) {
                continue;
            }
            foreach ($formas as $f) {
                if ((int) $f['id_empresa'] === $idEmpresa) {
                    $out[(int) $f['id']] = $formas;
                }
            }
        }
        return $out;
    }

    /**
     * Resuelve el grupo de formas bancarias (empresa+forma+cuenta contable) que representan la
     * MISMA cuenta real que $idFormaPago, dentro del RUC accesible por el usuario. Si no hay
     * ninguna otra empresa con esa cuenta, devuelve solo la propia (comportamiento idéntico al
     * caso no consolidado).
     */
    public function resolverGrupoCuenta(int $idEmpresa, int $idFormaPago, int $idUsuario): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $propia = $forma + ['id_empresa' => $idEmpresa, 'empresa_nombre' => null, 'establecimiento' => null];

        if (empty($forma['id_banco']) || trim((string) ($forma['numero_cuenta'] ?? '')) === '') {
            return [$propia];
        }

        $idsGrupo = $this->idsGrupoAccesible($idEmpresa, $idUsuario);
        if (count($idsGrupo) <= 1) {
            return [$propia];
        }

        $todas = $this->repository->getFormasBancariasDeEmpresas($idsGrupo);
        $numBase = trim((string) $forma['numero_cuenta']);
        $pares = array_values(array_filter($todas, static function ($f) use ($forma, $numBase) {
            return (int) $f['id_banco'] === (int) $forma['id_banco'] && trim((string) $f['numero_cuenta']) === $numBase;
        }));

        return $pares ?: [$propia];
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repository->getAniosDisponibles($idEmpresa);
    }

    private function getFormaBancariaOFallar(int $idFormaPago, int $idEmpresa): array
    {
        $forma = $this->repository->getFormaBancaria($idFormaPago, $idEmpresa);
        if (!$forma) {
            throw new \Exception('La cuenta bancaria seleccionada no es válida.');
        }
        return $forma;
    }

    /**
     * Resumen para las tarjetas KPI, en base al rango de fechas seleccionado:
     * saldo inicial del período, créditos (depósitos/entradas), débitos (pagos/salidas)
     * y saldo final = saldo inicial + créditos - débitos.
     */
    public function getResumenPeriodo(int $idEmpresa, int $idFormaPago, string $fechaInicio, string $fechaFin): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $saldoInicialCuenta = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);

        $resumen = $this->repository->getResumenPeriodo($idEmpresa, (int) $forma['id_cuenta_contable'], $fechaInicio, $fechaFin, $idFormaPago);

        $saldoInicial = $saldoInicialCuenta + $resumen['delta_antes'];
        $saldoFinal = $saldoInicial + $resumen['creditos'] - $resumen['debitos'];

        return [
            'saldo_inicial' => $saldoInicial,
            'creditos' => $resumen['creditos'],
            'debitos' => $resumen['debitos'],
            'saldo_final' => $saldoFinal,
        ];
    }

    public function getMovimientos(
        int $idEmpresa,
        int $idFormaPago,
        array $filtros,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $saldoInicial = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);

        $result = $this->repository->getMovimientos(
            $idEmpresa,
            $idFormaPago,
            (int) $forma['id_cuenta_contable'],
            $saldoInicial,
            $filtros,
            $page,
            $perPage,
            $ordenCol,
            $ordenDir
        );

        $hoy = date('Y-m-d');
        foreach ($result['rows'] as &$row) {
            $row['es_posfechado'] = ($row['tipo_transaccion'] === 'CHEQUE' && !empty($row['fecha_cheque']) && $row['fecha_cheque'] > $hoy);
        }
        unset($row);

        return $result;
    }

    public function getChequesPosfechados(int $idEmpresa, ?int $idFormaPago, string $direccion): array
    {
        return $this->repository->getChequesPosfechados($idEmpresa, $idFormaPago, $direccion);
    }

    /** Impide reclasificar/quitar un movimiento cuya fecha cae dentro de un período ya conciliado (bloqueado). */
    private function verificarNoConciliado(int $idFormaPago, int $idCuentaContable, int $idAsientoDetalle, int $idEmpresa): void
    {
        $fechaAsiento = $this->repository->getFechaAsientoDeDetalle($idAsientoDetalle, $idEmpresa, $idCuentaContable);
        if ($fechaAsiento === null) {
            return;
        }
        $this->verificarNoConciliadoPorFecha($idFormaPago, $fechaAsiento);
    }

    /**
     * Mismo bloqueo, pero partiendo de la fecha del movimiento — el camino de las cuentas sin
     * contabilidad, donde la fecha viene del cobro/pago y no de un asiento.
     */
    private function verificarNoConciliadoPorFecha(int $idFormaPago, string $fecha): void
    {
        $conciliacion = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fecha);
        if ($conciliacion) {
            $desde = date('d-m-Y', strtotime($conciliacion['fecha_inicio']));
            $hasta = date('d-m-Y', strtotime($conciliacion['fecha_fin']));
            throw new \Exception("Este movimiento pertenece a un período ya conciliado ({$desde} al {$hasta}). Debes reabrir esa conciliación para poder editarlo.");
        }
    }

    /**
     * Movimiento tal como lo muestra el listado (con su clasificación ya aplicada), buscado por
     * su anclaje: la línea del asiento o el cobro/pago. null si no se encuentra.
     */
    private function getMovimientoPorAnclaje(int $idEmpresa, int $idFormaPago, array $data): ?array
    {
        $forma = $this->repository->getFormaBancaria($idFormaPago, $idEmpresa);
        if (!$forma) {
            return null;
        }
        [$origenTipo, $origenId] = $this->resolverOrigen($data);
        $idAsientoDetalle = (int) ($data['id_asiento_detalle'] ?? 0);

        $filtros = $idAsientoDetalle > 0
            ? ['id_asiento_detalle' => $idAsientoDetalle]
            : ['origen_tipo' => $origenTipo, 'origen_id' => $origenId];
        if ($idAsientoDetalle <= 0 && $origenTipo === null) {
            return null;
        }

        $res = $this->repository->getMovimientos(
            $idEmpresa,
            $idFormaPago,
            (int) $forma['id_cuenta_contable'],
            0.0,
            $filtros,
            1,
            1,
            'fecha_asiento',
            'ASC'
        );
        return $res['rows'][0] ?? null;
    }

    /**
     * Cuando el movimiento está enlazado a un ingreso/egreso, deja en $data los valores reales
     * del documento (tipo, dirección y datos del cheque) y conserva la observación ya guardada:
     * desde Control Bancario solo se registra la Fecha Banco. Si no hay documento detrás
     * (asiento manual), $data queda como vino y todo sigue siendo editable.
     */
    private function fijarDatosDeDocumento(int $idEmpresa, array $data): array
    {
        $idFormaPago = (int) ($data['id_forma_pago'] ?? 0);
        if ($idFormaPago <= 0) {
            return $data;
        }

        $mov = $this->getMovimientoPorAnclaje($idEmpresa, $idFormaPago, $data);
        if (!$mov || !in_array($mov['tiene_documento'], [true, 't', '1', 1], true)) {
            return $data;
        }

        $data['tipo_transaccion'] = $mov['tipo_transaccion'];
        $data['cheque_direccion'] = $mov['cheque_direccion'];
        $data['numero_cheque'] = $mov['numero_cheque'];
        $data['fecha_cheque'] = $mov['fecha_cheque'];
        $data['observacion'] = $mov['observacion'];

        return $data;
    }

    /**
     * Normaliza el anclaje al cobro/pago que envía la vista para las cuentas sin contabilidad.
     * Devuelve [null, 0] si el payload no trae un origen válido.
     */
    private function resolverOrigen(array $data): array
    {
        $tipo = strtolower(trim((string) ($data['origen_tipo'] ?? '')));
        $id = (int) ($data['origen_id'] ?? 0);
        if (!in_array($tipo, ['ingreso', 'egreso'], true) || $id <= 0) {
            return [null, 0];
        }
        return [$tipo, $id];
    }

    /**
     * Marca el período [fechaInicio, fechaFin] de una cuenta como conciliado con el banco,
     * bloqueando la reclasificación de sus movimientos. Guarda el saldo final calculado por
     * el sistema en ese momento (y, si se indica, el saldo del estado de cuenta del banco).
     */
    public function conciliarPeriodo(int $idEmpresa, int $idUsuario, array $data): array
    {
        $this->rules->validarConciliacion($data);
        $idFormaPago = (int) $data['id_forma_pago'];
        $fechaInicio = (string) $data['fecha_inicio'];
        $fechaFin = (string) $data['fecha_fin'];

        $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);

        if ($this->repository->existeSolapamientoConciliacion($idFormaPago, $fechaInicio, $fechaFin)) {
            throw new \Exception('Ya existe una conciliación vigente que se superpone con ese rango de fechas.');
        }

        $resumen = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->crearConciliacion([
                'id_empresa' => $idEmpresa,
                'id_forma_pago' => $idFormaPago,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'saldo_inicial' => $resumen['saldo_inicial'],
                'saldo_final' => $resumen['saldo_final'],
                'saldo_banco' => ($data['saldo_banco'] ?? '') !== '' ? (float) $data['saldo_banco'] : null,
                'observaciones' => $data['observaciones'] ?? null,
                'usuario_id' => $idUsuario,
            ]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $conciliacion = $this->repository->getConciliacionPorId($id, $idEmpresa);
        $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'control_bancario_conciliaciones', $id, null, $conciliacion);

        return $conciliacion ?? [];
    }

    public function reabrirConciliacion(int $idEmpresa, int $idUsuario, int $idConciliacion): void
    {
        $antes = $this->repository->getConciliacionPorId($idConciliacion, $idEmpresa);
        if (!$antes || !empty($antes['eliminado'])) {
            throw new \Exception('La conciliación indicada no existe o ya fue reabierta.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->reabrirConciliacion($idConciliacion, $idEmpresa, $idUsuario);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'control_bancario_conciliaciones', $idConciliacion, $antes, null);
    }

    /**
     * Historial de conciliaciones de la cuenta. Marca 'desactualizada' cuando el saldo final
     * recalculado hoy ya no coincide con el que se guardó al momento de conciliar (indicio de
     * que algo se registró/editó después con fecha dentro de ese período).
     */
    public function getConciliaciones(int $idEmpresa, int $idFormaPago): array
    {
        $conciliaciones = $this->repository->listarConciliaciones($idEmpresa, $idFormaPago);
        foreach ($conciliaciones as &$c) {
            if (!empty($c['eliminado'])) {
                $c['desactualizada'] = false;
                continue;
            }
            $resumenActual = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $c['fecha_inicio'], $c['fecha_fin']);
            $c['desactualizada'] = abs($resumenActual['saldo_final'] - (float) $c['saldo_final']) > 0.005;
            $c['saldo_final_actual'] = $resumenActual['saldo_final'];
        }
        unset($c);
        return $conciliaciones;
    }

    /** Conciliación vigente que cubre por completo el rango indicado (para el badge del período mostrado). */
    public function getConciliacionDelRango(int $idFormaPago, string $fechaInicio, string $fechaFin): ?array
    {
        $ini = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fechaInicio);
        $fin = $this->repository->getConciliacionVigentePorFecha($idFormaPago, $fechaFin);
        if ($ini && $fin && $ini['id'] === $fin['id']) {
            return $ini;
        }
        return null;
    }

    /**
     * Arma todos los datos del reporte de Conciliación Bancaria para el período:
     * resumen (saldo inicial/créditos/débitos/saldo final), el detalle completo de
     * movimientos (mayor contable de la cuenta), separado en créditos y débitos, y
     * los cheques emitidos en circulación / cobrados en el período.
     */
    public function getReporteConciliacion(int $idEmpresa, int $idFormaPago, string $fechaInicio, string $fechaFin): array
    {
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);
        $idCuenta = (int) $forma['id_cuenta_contable'];

        foreach ($this->repository->getFormasBancarias($idEmpresa) as $f) {
            if ((int) $f['id'] === $idFormaPago) {
                $forma = $f;
                break;
            }
        }

        $resumen = $this->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);

        $saldoInicialCuenta = $this->repository->getSaldoInicial($idEmpresa, $idFormaPago);
        $mov = $this->repository->getMovimientos(
            $idEmpresa,
            $idFormaPago,
            $idCuenta,
            $saldoInicialCuenta,
            ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'buscar' => ''],
            1,
            1000000,
            'fecha_asiento',
            'ASC'
        );
        $movimientos = $mov['rows'];

        $creditos = array_values(array_filter($movimientos, fn ($r) => (float) $r['debe'] > 0));
        $debitos = array_values(array_filter($movimientos, fn ($r) => (float) $r['haber'] > 0));

        return [
            'forma' => $forma,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'resumen' => $resumen,
            'movimientos' => $movimientos,
            'creditos' => $creditos,
            'debitos' => $debitos,
            // Cuenta sin cuenta contable: el detalle sale de los cobros/pagos, no del mayor.
            'sin_contabilidad' => $idCuenta <= 0,
            'cheques_no_cobrados' => $this->repository->getChequesEmitidosNoCobrados($idEmpresa, $idFormaPago, $idCuenta, $fechaInicio, $fechaFin),
            'cheques_cobrados' => $this->repository->getChequesEmitidosCobradosEnPeriodo($idEmpresa, $idFormaPago, $idCuenta, $fechaInicio, $fechaFin),
        ];
    }

    // ── Variantes consolidadas (mismo dato, sumado/unido sobre $pares de resolverGrupoCuenta()) ──
    //
    // Cada $pares es la salida de resolverGrupoCuenta(): una fila por cada empresa_formas_pago
    // que representa la MISMA cuenta real (banco + número de cuenta) en el RUC accesible. Se
    // reutilizan los métodos de una sola cuenta (sin tocarlos) y se suman/unen los resultados en
    // PHP — el plan de cuentas es propio de cada empresa, así que nunca hay un solo
    // id_cuenta_contable que abarque el grupo.

    /** Resumen del período, sumado sobre todas las cuentas del grupo. */
    public function getResumenPeriodoGrupo(array $pares, string $fechaInicio, string $fechaFin): array
    {
        $saldoInicial = 0.0;
        $creditos = 0.0;
        $debitos = 0.0;
        foreach ($pares as $p) {
            $idEmpresa = (int) $p['id_empresa'];
            $idCuenta = (int) $p['id_cuenta_contable'];
            $saldoCuenta = $this->repository->getSaldoInicial($idEmpresa, (int) $p['id']);
            $r = $this->repository->getResumenPeriodo($idEmpresa, $idCuenta, $fechaInicio, $fechaFin, (int) $p['id']);
            $saldoInicial += $saldoCuenta + $r['delta_antes'];
            $creditos += $r['creditos'];
            $debitos += $r['debitos'];
        }
        return [
            'saldo_inicial' => $saldoInicial,
            'creditos' => $creditos,
            'debitos' => $debitos,
            'saldo_final' => $saldoInicial + $creditos - $debitos,
        ];
    }

    /**
     * Movimientos consolidados: une el mayor de cada cuenta del grupo, recalcula el saldo
     * acumulado en orden cronológico real (no el de cada cuenta por separado, que perdería
     * sentido al mezclarlos) y pagina en PHP sobre el conjunto ya unido.
     */
    public function getMovimientosGrupo(array $pares, array $filtros, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $fechaInicio = $filtros['fecha_inicio'] ?? null;
        $fechaFin = $filtros['fecha_fin'] ?? ($fechaInicio ?: null);

        $todas = [];
        $saldoInicioRango = 0.0;
        foreach ($pares as $p) {
            $idEmpresa = (int) $p['id_empresa'];
            $idForma = (int) $p['id'];
            $idCuenta = (int) $p['id_cuenta_contable'];
            $saldoCuenta = $this->repository->getSaldoInicial($idEmpresa, $idForma);

            if ($fechaInicio) {
                $r = $this->repository->getResumenPeriodo($idEmpresa, $idCuenta, $fechaInicio, $fechaFin, $idForma);
                $saldoInicioRango += $saldoCuenta + $r['delta_antes'];
            } else {
                $saldoInicioRango += $saldoCuenta;
            }

            // Sin paginar (perPage alto, mismo patrón que getReporteConciliacion): se necesita
            // TODO el rango filtrado para poder unir y recalcular el saldo acumulado consolidado.
            $res = $this->repository->getMovimientos($idEmpresa, $idForma, $idCuenta, $saldoCuenta, $filtros, 1, 1000000, 'fecha_asiento', 'ASC');
            foreach ($res['rows'] as $row) {
                $row['id_empresa'] = $idEmpresa;
                $row['id_forma_pago'] = $idForma;
                $row['empresa_nombre'] = $p['empresa_nombre'] ?? null;
                $row['establecimiento'] = $p['establecimiento'] ?? null;
                $todas[] = $row;
            }
        }

        // Cuando la cuenta no tiene contabilidad, id_asiento/id_asiento_detalle vienen NULL y el
        // desempate lo da la fila de pago (origen_id).
        usort($todas, static function ($a, $b) {
            return [$a['fecha_asiento'], (int) $a['id_asiento'], (int) $a['id_asiento_detalle'], (int) ($a['origen_id'] ?? 0)]
                <=> [$b['fecha_asiento'], (int) $b['id_asiento'], (int) $b['id_asiento_detalle'], (int) ($b['origen_id'] ?? 0)];
        });
        $acum = $saldoInicioRango;
        foreach ($todas as &$row) {
            $acum += (float) $row['debe'] - (float) $row['haber'];
            $row['saldo_acumulado'] = $acum;
        }
        unset($row);

        if (!in_array($ordenCol, self::COLUMNAS_ORDEN_GRUPO, true)) {
            $ordenCol = 'fecha_asiento';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? -1 : 1;
        usort($todas, static function ($a, $b) use ($ordenCol, $dir) {
            $va = $a[$ordenCol] ?? null;
            $vb = $b[$ordenCol] ?? null;
            if ($va == $vb) { return 0; }
            return ($va <=> $vb) * $dir;
        });

        $total = count($todas);
        $rows = array_slice($todas, max(0, ($page - 1) * $perPage), $perPage);

        $hoy = date('Y-m-d');
        foreach ($rows as &$row) {
            $row['es_posfechado'] = ($row['tipo_transaccion'] === 'CHEQUE' && !empty($row['fecha_cheque']) && $row['fecha_cheque'] > $hoy);
        }
        unset($row);

        return ['total' => $total, 'rows' => $rows];
    }

    private const COLUMNAS_ORDEN_GRUPO = [
        'fecha_asiento', 'fecha_banco', 'fecha_cheque', 'tipo_transaccion',
        'nombre_entidad', 'numero_comprobante', 'debe', 'haber',
        'numero_cheque', 'beneficiario_cheque', 'documento_referencia',
        'referencia_detalle', 'saldo_acumulado', 'empresa_nombre',
    ];

    /** Cheques posfechados de todas las cuentas del grupo, unidos y ordenados por fecha. */
    public function getChequesPosfechadosGrupo(array $pares, string $direccion): array
    {
        $todas = [];
        foreach ($pares as $p) {
            $rows = $this->repository->getChequesPosfechados((int) $p['id_empresa'], (int) $p['id'], $direccion);
            foreach ($rows as $r) {
                $r['empresa_nombre'] = $p['empresa_nombre'] ?? null;
                $todas[] = $r;
            }
        }
        usort($todas, static fn ($a, $b) => ($a['fecha_cheque'] ?? '') <=> ($b['fecha_cheque'] ?? ''));
        return $todas;
    }

    /**
     * Marca conciliado el período en TODAS las cuentas del grupo (una fila de conciliación por
     * cada empresa_formas_pago), en una sola transacción: o se concilian todas, o ninguna.
     */
    public function conciliarPeriodoGrupo(array $pares, int $idUsuario, array $data): array
    {
        $this->rules->validarConciliacion($data);
        $fechaInicio = (string) $data['fecha_inicio'];
        $fechaFin = (string) $data['fecha_fin'];

        foreach ($pares as $p) {
            if ($this->repository->existeSolapamientoConciliacion((int) $p['id'], $fechaInicio, $fechaFin)) {
                $nombre = $p['empresa_nombre'] ?? ('establecimiento ' . ($p['establecimiento'] ?? $p['id_empresa']));
                throw new \Exception("Ya existe una conciliación vigente para {$nombre} que se superpone con ese rango de fechas.");
            }
        }

        $creadas = [];
        $this->repository->beginTransaction();
        try {
            foreach ($pares as $p) {
                $idEmpresa = (int) $p['id_empresa'];
                $idForma = (int) $p['id'];
                $resumen = $this->getResumenPeriodo($idEmpresa, $idForma, $fechaInicio, $fechaFin);
                $id = $this->repository->crearConciliacion([
                    'id_empresa' => $idEmpresa,
                    'id_forma_pago' => $idForma,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'saldo_inicial' => $resumen['saldo_inicial'],
                    'saldo_final' => $resumen['saldo_final'],
                    'saldo_banco' => ($data['saldo_banco'] ?? '') !== '' ? (float) $data['saldo_banco'] : null,
                    'observaciones' => $data['observaciones'] ?? null,
                    'usuario_id' => $idUsuario,
                ]);
                $creadas[] = ['id_empresa' => $idEmpresa, 'id' => $id];
            }
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        foreach ($creadas as $c) {
            $conc = $this->repository->getConciliacionPorId($c['id'], $c['id_empresa']);
            $this->logService->registrar($idUsuario, $c['id_empresa'], 'crear', 'control_bancario_conciliaciones', $c['id'], null, $conc);
        }

        return ['ok' => true, 'creadas' => count($creadas)];
    }

    /** Historial de conciliaciones de todas las cuentas del grupo, unido y marcado por establecimiento. */
    public function getConciliacionesGrupo(array $pares): array
    {
        $todas = [];
        foreach ($pares as $p) {
            $idEmpresa = (int) $p['id_empresa'];
            $idForma = (int) $p['id'];
            foreach ($this->getConciliaciones($idEmpresa, $idForma) as $c) {
                $c['empresa_nombre'] = $p['empresa_nombre'] ?? null;
                $c['establecimiento'] = $p['establecimiento'] ?? null;
                $todas[] = $c;
            }
        }
        usort($todas, static fn ($a, $b) => $b['fecha_inicio'] <=> $a['fecha_inicio']);
        return $todas;
    }

    /** Vigente solo si TODAS las cuentas del grupo tienen una conciliación que cubre exactamente el mismo rango. */
    public function getConciliacionDelRangoGrupo(array $pares, string $fechaInicio, string $fechaFin): ?array
    {
        foreach ($pares as $p) {
            $c = $this->getConciliacionDelRango((int) $p['id'], $fechaInicio, $fechaFin);
            if (!$c) {
                return null;
            }
        }
        return ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'grupo' => true];
    }

    /** Igual que getReporteConciliacion() pero uniendo todas las cuentas del grupo. */
    public function getReporteConciliacionGrupo(array $pares, string $fechaInicio, string $fechaFin): array
    {
        $resumen = $this->getResumenPeriodoGrupo($pares, $fechaInicio, $fechaFin);
        $mov = $this->getMovimientosGrupo($pares, ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'buscar' => ''], 1, 1000000, 'fecha_asiento', 'ASC');
        $movimientos = $mov['rows'];

        $chequesNoCobrados = [];
        $chequesCobrados = [];
        foreach ($pares as $p) {
            $idEmpresa = (int) $p['id_empresa'];
            $idForma = (int) $p['id'];
            $idCuenta = (int) $p['id_cuenta_contable'];
            foreach ($this->repository->getChequesEmitidosNoCobrados($idEmpresa, $idForma, $idCuenta, $fechaInicio, $fechaFin) as $r) {
                $r['empresa_nombre'] = $p['empresa_nombre'] ?? null;
                $chequesNoCobrados[] = $r;
            }
            foreach ($this->repository->getChequesEmitidosCobradosEnPeriodo($idEmpresa, $idForma, $idCuenta, $fechaInicio, $fechaFin) as $r) {
                $r['empresa_nombre'] = $p['empresa_nombre'] ?? null;
                $chequesCobrados[] = $r;
            }
        }

        $nombres = array_unique(array_filter(array_map(static fn ($p) => $p['empresa_nombre'] ?? null, $pares)));
        $formaConsolidada = [
            'id' => null,
            'nombre' => 'Consolidado (' . count($pares) . ' establecimiento' . (count($pares) === 1 ? '' : 's') . ')',
            'tipo' => null,
            'tipo_cuenta' => $pares[0]['tipo_cuenta'] ?? null,
            'numero_cuenta' => $pares[0]['numero_cuenta'] ?? '',
            'id_cuenta_contable' => null,
            'cuenta_codigo' => null,
            'cuenta_nombre' => implode(' / ', $nombres),
            'nombre_banco' => $pares[0]['nombre_banco'] ?? null,
        ];

        return [
            'forma' => $formaConsolidada,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'resumen' => $resumen,
            'movimientos' => $movimientos,
            'creditos' => array_values(array_filter($movimientos, fn ($r) => (float) $r['debe'] > 0)),
            'debitos' => array_values(array_filter($movimientos, fn ($r) => (float) $r['haber'] > 0)),
            // Solo si NINGUNA cuenta del grupo lleva contabilidad.
            'sin_contabilidad' => !array_filter($pares, static fn ($p) => !empty($p['id_cuenta_contable'])),
            'cheques_no_cobrados' => $chequesNoCobrados,
            'cheques_cobrados' => $chequesCobrados,
        ];
    }

    /**
     * Crea o actualiza la clasificación manual de un movimiento (tipo, cheque, fechas).
     * No toca el asiento contable: solo la anotación propia de este módulo.
     */
    public function guardarClasificacion(int $idEmpresa, int $idUsuario, array $data): array
    {
        // Si el movimiento viene de un cobro/pago real, sus datos (tipo, cheque, glosa) los
        // manda ese documento: aquí solo se concilia. Se releen del origen y se ignora lo que
        // haya mandado el cliente, para que el bloqueo no dependa solo de la interfaz.
        $data = $this->fijarDatosDeDocumento($idEmpresa, $data);

        $data['tipo_transaccion'] = strtoupper((string) ($data['tipo_transaccion'] ?? ''));
        $data['cheque_direccion'] = !empty($data['cheque_direccion']) ? strtoupper((string) $data['cheque_direccion']) : null;
        $this->rules->validarClasificacion($data);

        $idFormaPago = (int) $data['id_forma_pago'];
        $idAsientoDetalle = (int) ($data['id_asiento_detalle'] ?? 0);
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);

        // Cuenta sin contabilidad: la anotación se ancla al cobro/pago, no a una línea de asiento.
        [$origenTipo, $origenId] = $this->resolverOrigen($data);
        $porOrigen = ($idAsientoDetalle <= 0);

        if ($porOrigen) {
            if ($origenTipo === null) {
                throw new \Exception('No se pudo identificar el movimiento a clasificar.');
            }
            $fecha = $this->repository->getFechaMovimientoTesoreria($origenTipo, $origenId, $idEmpresa, $idFormaPago);
            if ($fecha === null) {
                throw new \Exception('El movimiento indicado no pertenece a esta cuenta bancaria.');
            }
            $this->verificarNoConciliadoPorFecha($idFormaPago, $fecha);
            $antes = $this->repository->getClasificacionPorOrigen($origenTipo, $origenId, $idEmpresa);
        } else {
            if (!$this->repository->validarAsientoDetalle($idAsientoDetalle, $idEmpresa, (int) $forma['id_cuenta_contable'])) {
                throw new \Exception('El movimiento indicado no pertenece a esta cuenta bancaria.');
            }

            $this->verificarNoConciliado($idFormaPago, (int) $forma['id_cuenta_contable'], $idAsientoDetalle, $idEmpresa);
            $antes = $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->upsertClasificacion([
                'id_empresa' => $idEmpresa,
                'id_asiento_detalle' => $porOrigen ? null : $idAsientoDetalle,
                'origen_tipo' => $porOrigen ? $origenTipo : null,
                'origen_id' => $porOrigen ? $origenId : null,
                'id_forma_pago' => $idFormaPago,
                'tipo_transaccion' => $data['tipo_transaccion'],
                'cheque_direccion' => $data['cheque_direccion'],
                'numero_cheque' => $data['numero_cheque'] ?? null,
                'fecha_cheque' => $data['fecha_cheque'] ?? null,
                'fecha_banco' => $data['fecha_banco'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'usuario_id' => $idUsuario,
            ]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $despues = $porOrigen
            ? $this->repository->getClasificacionPorOrigen($origenTipo, $origenId, $idEmpresa)
            : $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);
        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            $antes ? 'actualizar' : 'crear',
            'control_bancario_movimientos',
            $id,
            $antes,
            $despues
        );

        return $despues ?? [];
    }

    public function quitarClasificacion(int $idEmpresa, int $idUsuario, int $idAsientoDetalle, array $data = []): void
    {
        [$origenTipo, $origenId] = $this->resolverOrigen($data);
        $porOrigen = ($idAsientoDetalle <= 0);

        $antes = $porOrigen && $origenTipo !== null
            ? $this->repository->getClasificacionPorOrigen($origenTipo, $origenId, $idEmpresa)
            : $this->repository->getClasificacionPorAsientoDetalle($idAsientoDetalle, $idEmpresa);
        if (!$antes) {
            throw new \Exception('Este movimiento no tiene una clasificación manual que quitar.');
        }

        $idFormaPago = (int) $antes['id_forma_pago'];
        $forma = $this->getFormaBancariaOFallar($idFormaPago, $idEmpresa);

        if ($porOrigen) {
            $fecha = $this->repository->getFechaMovimientoTesoreria($origenTipo, $origenId, $idEmpresa, $idFormaPago);
            if ($fecha !== null) {
                $this->verificarNoConciliadoPorFecha($idFormaPago, $fecha);
            }
        } else {
            $this->verificarNoConciliado($idFormaPago, (int) $forma['id_cuenta_contable'], $idAsientoDetalle, $idEmpresa);
        }

        $this->repository->beginTransaction();
        try {
            if ($porOrigen) {
                $this->repository->quitarClasificacionPorOrigen($origenTipo, $origenId, $idEmpresa, $idUsuario);
            } else {
                $this->repository->quitarClasificacion($idAsientoDetalle, $idEmpresa, $idUsuario);
            }
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'control_bancario_movimientos', (int) $antes['id'], $antes, null);
    }

    public function exportarExcel(array $rows, string $empresaNombre, string $cuentaNombre): void
    {
        $headers = ['Fecha', 'Fecha Banco', 'Comprobante', 'Tipo', 'Nº Cheque', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $dataExport = [];
        foreach ($rows as $r) {
            $dataExport[] = [
                !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '',
                $r['numero_comprobante'] ?: 'S/N',
                $r['tipo_transaccion'],
                $r['numero_cheque'] ?: '',
                $r['documento_referencia'] ?: '',
                $r['nombre_entidad'] ?: '',
                $r['referencia_detalle'] ?: $r['concepto'] ?: '',
                (float) $r['debe'],
                (float) $r['haber'],
                (float) $r['saldo_acumulado'],
            ];
        }

        $this->reportService->exportToExcel('ControlBancario', $headers, $dataExport, 'Control Bancario', "{$empresaNombre} - {$cuentaNombre}");
    }

    /**
     * Excel de Conciliación Bancaria: hoja "Conciliación" (resumen + detalle de créditos/débitos
     * + cheques emitidos no cobrados/cobrados en el período) y hoja "Mayor Contable" (todos los
     * movimientos de la cuenta contable asignada, para el mismo período).
     */
    public function exportarConciliacionExcel(array $reporte, string $empresaNombre): void
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        $forma = $reporte['forma'];
        $cuentaNombre = $forma['nombre'] . (!empty($forma['numero_cuenta']) ? ' (' . $forma['numero_cuenta'] . ')' : '');
        $periodo = date('d-m-Y', strtotime($reporte['fecha_inicio'])) . ' al ' . date('d-m-Y', strtotime($reporte['fecha_fin']));

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Conciliación ────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conciliación');
        $row = $this->xlsTitulo($sheet, 1, "{$empresaNombre} — CONCILIACIÓN BANCARIA", "{$cuentaNombre}  |  Período: {$periodo}");

        $row = $this->xlsSeccion($sheet, $row + 1, 'RESUMEN DEL PERÍODO');
        $sheet->fromArray(['Saldo Inicial', 'Créditos', 'Débitos', 'Saldo Final'], null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, "A{$row}:D{$row}");
        $row++;
        $sheet->fromArray([
            (float) $reporte['resumen']['saldo_inicial'],
            (float) $reporte['resumen']['creditos'],
            (float) $reporte['resumen']['debitos'],
            (float) $reporte['resumen']['saldo_final'],
        ], null, "A{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $row += 2;

        $row = $this->xlsTablaMovimientos($sheet, $row, 'DETALLE DE CRÉDITOS (entradas)', $reporte['creditos'], 'debe');
        $row = $this->xlsTablaMovimientos($sheet, $row + 1, 'DETALLE DE DÉBITOS (salidas)', $reporte['debitos'], 'haber');
        $row = $this->xlsTablaCheques($sheet, $row + 1, 'CHEQUES EMITIDOS EN CIRCULACIÓN (no cobrados por el banco)', $reporte['cheques_no_cobrados'], false);
        $row = $this->xlsTablaCheques($sheet, $row + 1, 'CHEQUES COBRADOS POR EL BANCO EN EL PERÍODO', $reporte['cheques_cobrados'], true);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Hoja 2: Mayor Contable (o Movimientos, si la cuenta no lleva contabilidad) ──
        $sheetMayor = $spreadsheet->createSheet();
        $cuentaCodigo = $forma['cuenta_codigo'] ?? '';
        $cuentaCtbNombre = $forma['cuenta_nombre'] ?? '';
        $sinContabilidad = !empty($reporte['sin_contabilidad']);
        $sheetMayor->setTitle($sinContabilidad ? 'Movimientos' : 'Mayor Contable');
        $tituloHoja = $sinContabilidad ? 'MOVIMIENTOS DE LA CUENTA' : 'MAYOR CONTABLE';
        $subtituloHoja = $sinContabilidad
            ? "{$cuentaNombre}  |  Período: {$periodo}"
            : "Cuenta: {$cuentaCodigo} - {$cuentaCtbNombre}  |  Período: {$periodo}";
        $rowM = $this->xlsTitulo($sheetMayor, 1, "{$empresaNombre} — {$tituloHoja}", $subtituloHoja);
        $rowM++;
        $headersMayor = ['Fecha', 'Fecha Banco', 'Comprobante', 'Tipo', 'Documento Ref.', 'Tercero', 'Glosa', 'Debe', 'Haber', 'Saldo'];
        $sheetMayor->fromArray($headersMayor, null, "A{$rowM}");
        $this->xlsEstiloEncabezado($sheetMayor, 'A' . $rowM . ':J' . $rowM);
        $rowM++;
        foreach ($reporte['movimientos'] as $m) {
            $sheetMayor->fromArray([
                !empty($m['fecha_asiento']) ? date('d-m-Y', strtotime($m['fecha_asiento'])) : '',
                !empty($m['fecha_banco']) ? date('d-m-Y', strtotime($m['fecha_banco'])) : '',
                $m['numero_comprobante'] ?: 'S/N',
                $m['tipo_transaccion'],
                $m['documento_referencia'] ?: '',
                $m['nombre_entidad'] ?: '',
                $m['referencia_detalle'] ?: $m['concepto'] ?: '',
                (float) $m['debe'],
                (float) $m['haber'],
                (float) $m['saldo_acumulado'],
            ], null, "A{$rowM}");
            $rowM++;
        }
        foreach (range('A', 'J') as $col) {
            $sheetMayor->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'ConciliacionBancaria_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function xlsTitulo($sheet, int $row, string $titulo, string $subtitulo): int
    {
        $sheet->setCellValue("A{$row}", mb_strtoupper($titulo));
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        $sheet->setCellValue("A{$row}", $subtitulo);
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return $row;
    }

    private function xlsSeccion($sheet, int $row, string $titulo): int
    {
        $sheet->setCellValue("A{$row}", $titulo);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        return $row + 1;
    }

    private function xlsEstiloEncabezado($sheet, string $rango): void
    {
        $sheet->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    /** Escribe una sección de créditos o débitos (fecha, comprobante, tercero, glosa, monto) con subtotal. */
    private function xlsTablaMovimientos($sheet, int $row, string $titulo, array $rows, string $campoMonto): int
    {
        $row = $this->xlsSeccion($sheet, $row, $titulo);
        $sheet->fromArray(['Fecha', 'Comprobante', 'Tercero', 'Glosa', 'Monto'], null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, "A{$row}:E{$row}");
        $row++;
        $subtotal = 0.0;
        if (empty($rows)) {
            $sheet->setCellValue("A{$row}", 'Sin movimientos.');
            return $row + 1;
        }
        foreach ($rows as $r) {
            $monto = (float) $r[$campoMonto];
            $subtotal += $monto;
            $sheet->fromArray([
                !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                $r['numero_comprobante'] ?: 'S/N',
                $r['nombre_entidad'] ?: '',
                $r['referencia_detalle'] ?: $r['concepto'] ?: '',
                $monto,
            ], null, "A{$row}");
            $row++;
        }
        $sheet->setCellValue("D{$row}", 'SUBTOTAL');
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("E{$row}", $subtotal);
        $sheet->getStyle("E{$row}")->getFont()->setBold(true);
        return $row + 1;
    }

    /** Escribe una sección de cheques (no cobrados o cobrados en el período). */
    private function xlsTablaCheques($sheet, int $row, string $titulo, array $rows, bool $conFechaBanco): int
    {
        $row = $this->xlsSeccion($sheet, $row, $titulo);
        $headers = $conFechaBanco
            ? ['Fecha Emisión', 'Fecha Cobro (Banco)', 'Nº Cheque', 'Beneficiario', 'Monto']
            : ['Fecha Emisión', 'Nº Cheque', 'Beneficiario', 'Monto'];
        $sheet->fromArray($headers, null, "A{$row}");
        $this->xlsEstiloEncabezado($sheet, 'A' . $row . ':' . ($conFechaBanco ? 'E' : 'D') . $row);
        $row++;
        if (empty($rows)) {
            $sheet->setCellValue("A{$row}", 'Sin cheques.');
            return $row + 1;
        }
        foreach ($rows as $r) {
            $monto = (float) $r['haber'];
            $vals = $conFechaBanco
                ? [
                    !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                    !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '',
                    $r['numero_cheque'] ?: '',
                    $r['nombre_entidad'] ?: '',
                    $monto,
                ]
                : [
                    !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '',
                    $r['numero_cheque'] ?: '',
                    $r['nombre_entidad'] ?: '',
                    $monto,
                ];
            $sheet->fromArray($vals, null, "A{$row}");
            $row++;
        }
        return $row + 1;
    }

    public function exportarPdf(array $rows, string $empresaNombre, string $cuentaNombre): void
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema Contable');
        $pdf->SetAuthor($empresaNombre);
        $pdf->SetTitle('Control Bancario');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, strtoupper($empresaNombre), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'CONTROL BANCARIO - ' . strtoupper($cuentaNombre), 0, 1, 'C');
        $pdf->Ln(2);

        $money = fn ($v) => number_format((float) $v, 2, '.', ',');

        $html = '<table border="1" cellpadding="3">
            <thead><tr style="background-color:#f8f9fa; font-weight:bold; font-size:8px;">
                <th width="8%">Fecha</th><th width="8%">F.Banco</th><th width="9%">Comprobante</th>
                <th width="8%">Tipo</th><th width="17%">Tercero</th><th width="22%">Glosa</th>
                <th width="9%" align="right">Debe</th><th width="9%" align="right">Haber</th><th width="10%" align="right">Saldo</th>
            </tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr style="font-size:8px;">
                <td>' . htmlspecialchars(!empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '') . '</td>
                <td>' . htmlspecialchars(!empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '') . '</td>
                <td>' . htmlspecialchars((string) ($r['numero_comprobante'] ?: 'S/N')) . '</td>
                <td>' . htmlspecialchars((string) $r['tipo_transaccion']) . '</td>
                <td>' . htmlspecialchars((string) ($r['nombre_entidad'] ?? '')) . '</td>
                <td>' . htmlspecialchars((string) ($r['referencia_detalle'] ?: $r['concepto'] ?: '')) . '</td>
                <td align="right">' . $money($r['debe']) . '</td>
                <td align="right">' . $money($r['haber']) . '</td>
                <td align="right">' . $money($r['saldo_acumulado']) . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'ControlBancario_' . date('YmdHis') . '.pdf';
        if (ob_get_length()) {
            ob_end_clean();
        }
        $pdf->Output($filename, 'D');
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ConciliacionCobrosRepository;
use App\repositories\modulos\IngresoRepository;
use App\Rules\modulos\ConciliacionCobrosRules;
use App\Rules\modulos\IngresoRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;

/**
 * Orquesta el flujo de Conciliación de Cobros Bancarios: perfiles de mapeo,
 * importación de un extracto bancario (Excel/PDF) con sugerencia automática
 * de cliente/factura, confirmación manual del usuario y generación en lote
 * de Ingresos reales (mismo payload/servicio que el "cobro rápido" de
 * Ingresos — ver IngresosController::registrarCobroRapidoAjax).
 */
class ConciliacionCobrosService
{
    private const STORAGE_DIR = 'storage/conciliacion_cobros';

    private IngresoService $ingresoService;

    public function __construct(
        private ConciliacionCobrosRepository $repository,
        private ConciliacionCobrosRules $rules,
        private ConciliacionImportService $importService,
        private ConciliacionMatchService $matchService,
        private IngresoRepository $ingresoRepository,
        private LogSistemaService $logService,
    ) {
        $this->ingresoService = new IngresoService($ingresoRepository, new IngresoRules(), $logService);
    }

    // ── Catálogos para el paso 1 del wizard ─────────────────────────────────

    public function getCuentasBancarias(int $idEmpresa): array
    {
        return $this->repository->getCuentasBancarias($idEmpresa);
    }

    public function getPuntosEmision(int $idEmpresa): array
    {
        return $this->repository->getPuntosEmision($idEmpresa);
    }

    public function getPerfiles(int $idEmpresa): array
    {
        return $this->repository->getPerfiles($idEmpresa);
    }

    public function getClientesActivos(int $idEmpresa): array
    {
        return $this->repository->getClientesActivos($idEmpresa);
    }

    /**
     * Clientes de la empresa actual que tienen al menos un documento de cuentas por cobrar
     * pendiente (factura de venta / recibo / saldo inicial). Reutiliza
     * IngresoRepository::getFacturasPendientes() para no duplicar el cálculo de saldos —
     * esa consulta ya filtra facturas y recibos por el ambiente (pruebas/producción) actual
     * de la empresa (los saldos iniciales no llevan ambiente, por diseño). Se usa tanto para
     * la lista de candidatos del matching automático como para el buscador manual, así ningún
     * cliente sin saldo pendiente ni una factura de otro ambiente aparece en ningún lado.
     */
    public function getClientesConSaldoPendiente(int $idEmpresa): array
    {
        $conSaldo = [];
        foreach ($this->repository->getClientesActivos($idEmpresa) as $cliente) {
            if (!empty($this->ingresoRepository->getFacturasPendientes((int) $cliente['id'], $idEmpresa))) {
                $conSaldo[] = $cliente;
            }
        }
        return $conSaldo;
    }

    // ── Perfiles de mapeo ────────────────────────────────────────────────────

    public function previsualizarArchivo(array $file, string $tipoArchivo, int $filaInicio = 0, ?string $regexPrueba = null, ?string $tipoCreditoPrueba = null): array
    {
        $tipoArchivo = strtoupper($tipoArchivo);
        $rutaTemporal = $this->validarYObtenerTmp($file, $tipoArchivo);
        return $this->importService->previsualizar($rutaTemporal, $tipoArchivo, $filaInicio, 60, $regexPrueba, $tipoCreditoPrueba);
    }

    /** Analiza un PDF de muestra y propone un patrón (regex) de línea de datos (ver ConciliacionImportService::sugerirRegexPdf). */
    public function sugerirRegexPdf(array $file): array
    {
        $rutaTemporal = $this->validarYObtenerTmp($file, 'PDF');
        return $this->importService->sugerirRegexPdf($rutaTemporal);
    }

    public function guardarPerfil(int $idEmpresa, int $idUsuario, array $data): array
    {
        $data['tipo_archivo'] = strtoupper((string) ($data['tipo_archivo'] ?? ''));
        $this->rules->validarPerfil($data);

        $data['id_empresa'] = $idEmpresa;
        $data['usuario_id'] = $idUsuario;

        if (!empty($data['id'])) {
            $antes = $this->repository->getPerfilPorId((int) $data['id'], $idEmpresa);
            if (!$antes) {
                throw new \Exception('El perfil indicado no existe.');
            }
            $this->repository->actualizarPerfil((int) $data['id'], $data);
            $id = (int) $data['id'];
            $accion = 'actualizar';
        } else {
            $id = $this->repository->crearPerfil($data);
            $accion = 'crear';
        }

        $perfil = $this->repository->getPerfilPorId($id, $idEmpresa);
        $this->logService->registrar($idUsuario, $idEmpresa, $accion, 'conciliacion_perfiles', $id, null, $perfil);

        return $perfil ?? [];
    }

    // ── Cargas (subir extracto → importar → sugerir) ────────────────────────

    public function crearCarga(int $idEmpresa, int $idUsuario, array $data, array $file): array
    {
        $this->rules->validarCarga($data);

        $perfil = $this->repository->getPerfilPorId((int) $data['id_perfil'], $idEmpresa);
        if (!$perfil) {
            throw new \Exception('El perfil de mapeo seleccionado no existe.');
        }

        $cuenta = $this->repository->getCuentaBancariaPorId((int) $data['id_forma_pago'], $idEmpresa);
        if (!$cuenta) {
            throw new \Exception('La cuenta bancaria seleccionada no es válida.');
        }

        $punto = $this->repository->getPuntoEmision((int) $data['id_punto_emision'], $idEmpresa);
        if (!$punto) {
            throw new \Exception('El punto de emisión seleccionado no es válido.');
        }

        $tipoArchivo = strtoupper((string) $perfil['tipo_archivo']);
        $this->validarYObtenerTmp($file, $tipoArchivo);
        $guardado = $this->guardarArchivoFisico($idEmpresa, $file, $tipoArchivo);

        $idCarga = $this->repository->crearCarga([
            'id_empresa' => $idEmpresa,
            'id_forma_pago' => $cuenta['id'],
            'id_punto_emision' => $punto['id'],
            'id_perfil' => $perfil['id'],
            'nombre_archivo' => $guardado['nombre_original'],
            'ruta_archivo' => $guardado['ruta_relativa'],
            'tipo_archivo' => $tipoArchivo,
            'usuario_id' => $idUsuario,
        ]);

        try {
            // Se parsea desde el archivo ya guardado en storage/ (el tmp_name original
            // dejó de existir en cuanto guardarArchivoFisico() lo movió con move_uploaded_file).
            $resultado = $this->importService->parsear($perfil, $guardado['ruta_absoluta']);
            $clientes = $this->getClientesConSaldoPendiente($idEmpresa);

            foreach ($resultado['filas'] as $fila) {
                $sugerencia = $this->matchService->sugerir($fila, $clientes, $idEmpresa);
                $this->repository->insertLinea([
                    'id_carga' => $idCarga,
                    'id_empresa' => $idEmpresa,
                    'fecha_movimiento' => $fila['fecha'],
                    'descripcion_original' => $fila['descripcion'],
                    'monto' => $fila['monto'],
                    'referencia_banco' => $fila['referencia'],
                    'estado' => $sugerencia['estado'],
                    'id_cliente_sugerido' => $sugerencia['id_cliente'],
                    'score_match' => $sugerencia['score'],
                    'tipo_documento_sugerido' => $sugerencia['tipo_documento'],
                    'id_documento_sugerido' => $sugerencia['id_documento'],
                    'usuario_id' => $idUsuario,
                ]);
            }

            $this->repository->actualizarEstadoCarga($idCarga, 'pendiente_revision', null, $resultado['total_validas']);
        } catch (\Throwable $e) {
            $this->repository->actualizarEstadoCarga($idCarga, 'error', $e->getMessage());
            throw new \Exception('El archivo se guardó pero no se pudo procesar: ' . $e->getMessage());
        }

        $carga = $this->repository->getCargaPorId($idCarga, $idEmpresa);
        $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'conciliacion_cargas', $idCarga, null, $carga);

        return $carga ?? [];
    }

    public function listarCargas(int $idEmpresa): array
    {
        return $this->repository->listarCargas($idEmpresa);
    }

    /** Líneas de una carga, enriquecidas con el número/saldo actual del documento sugerido (si lo hay). */
    public function listarLineas(int $idCarga, int $idEmpresa): array
    {
        $carga = $this->repository->getCargaPorId($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \Exception('La carga indicada no existe.');
        }

        $lineas = $this->repository->getLineasPorCarga($idCarga, $idEmpresa);

        $pendientesPorCliente = [];
        foreach ($lineas as &$linea) {
            if (empty($linea['id_cliente_sugerido']) || empty($linea['id_documento_sugerido'])) {
                continue;
            }
            $idCliente = (int) $linea['id_cliente_sugerido'];
            if (!isset($pendientesPorCliente[$idCliente])) {
                $pendientesPorCliente[$idCliente] = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
            }
            foreach ($pendientesPorCliente[$idCliente] as $doc) {
                if ($doc['tipo_documento'] === $linea['tipo_documento_sugerido'] && (int) $doc['id'] === (int) $linea['id_documento_sugerido']) {
                    $linea['documento_numero'] = $doc['numero_documento'];
                    $linea['documento_saldo_pendiente'] = (float) $doc['saldo_pendiente'];
                    break;
                }
            }
        }
        unset($linea);

        // Si el Ingreso de una línea APLICADO fue anulado o eliminado después (fuera de este
        // módulo), se marca para que la vista ofrezca reactivarla en vez de darla por hecha.
        foreach ($lineas as &$linea) {
            if ($linea['estado'] !== 'APLICADO' || empty($linea['id_ingreso_generado'])) {
                continue;
            }
            $ingreso = $this->ingresoRepository->getPorId((int) $linea['id_ingreso_generado'], $idEmpresa);
            $linea['ingreso_valido'] = $ingreso !== null && $ingreso['estado'] !== 'anulado';
        }
        unset($linea);

        return $lineas;
    }

    /** Reactiva una línea APLICADO cuyo Ingreso fue anulado/eliminado después, sin tener que resubir el extracto. */
    public function reactivarLineaAplicada(int $idEmpresa, int $idLinea): array
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            throw new \Exception('La línea indicada no existe.');
        }
        if ($linea['estado'] !== 'APLICADO') {
            throw new \Exception('Esta línea no está aplicada.');
        }

        if (!empty($linea['id_ingreso_generado'])) {
            $ingreso = $this->ingresoRepository->getPorId((int) $linea['id_ingreso_generado'], $idEmpresa);
            if ($ingreso !== null && $ingreso['estado'] !== 'anulado') {
                throw new \Exception('El Ingreso generado por esta línea sigue vigente; anúlalo o elimínalo primero en el módulo de Ingresos si quieres volver a conciliarla.');
            }
        }

        $this->repository->revertirLineaAplicada($idLinea);

        return $this->repository->getLineaPorId($idLinea, $idEmpresa) ?? [];
    }

    public function buscarDocumentosPendientes(int $idEmpresa, int $idCliente): array
    {
        return $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
    }

    /** Confirma (o corrige manualmente) la línea: el usuario marca el check de "sí es este cliente/esta factura". */
    public function confirmarLinea(int $idEmpresa, int $idUsuario, int $idLinea, array $data): array
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            throw new \Exception('La línea indicada no existe.');
        }
        if (in_array($linea['estado'], ['APLICADO', 'IGNORADO'], true)) {
            throw new \Exception('Esta línea ya fue ' . strtolower($linea['estado']) . ' y no se puede modificar.');
        }

        $data['tipo_documento'] = strtoupper((string) ($data['tipo_documento'] ?? ''));

        $idCliente = (int) ($data['id_cliente'] ?? 0);
        $idDocumento = (int) ($data['id_documento'] ?? 0);
        $saldoPendienteDocumento = null;
        if ($idCliente > 0 && $idDocumento > 0) {
            foreach ($this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa) as $doc) {
                if ($doc['tipo_documento'] === $data['tipo_documento'] && (int) $doc['id'] === $idDocumento) {
                    $saldoPendienteDocumento = (float) $doc['saldo_pendiente'];
                    break;
                }
            }
            if ($saldoPendienteDocumento === null) {
                throw new \Exception('El documento seleccionado ya no tiene saldo pendiente o ya no está disponible.');
            }
        }

        $this->rules->validarMatchLinea($data, (float) $linea['monto'], $saldoPendienteDocumento);

        $this->repository->actualizarMatchLinea($idLinea, [
            'estado' => 'CONFIRMADO',
            'id_cliente_sugerido' => (int) $data['id_cliente'],
            'tipo_documento_sugerido' => $data['tipo_documento'],
            'id_documento_sugerido' => (int) $data['id_documento'],
            'monto_aplicar' => round((float) $data['monto_aplicar'], 2),
        ]);

        return $this->repository->getLineaPorId($idLinea, $idEmpresa) ?? [];
    }

    /** Quita la confirmación de una línea marcada por error (vuelve a estado SUGERIDO, editable de nuevo). */
    public function desconfirmarLinea(int $idEmpresa, int $idLinea): array
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            throw new \Exception('La línea indicada no existe.');
        }
        if ($linea['estado'] !== 'CONFIRMADO') {
            throw new \Exception('Solo se puede quitar la confirmación de una línea que esté confirmada.');
        }

        $this->repository->desconfirmarLinea($idLinea);

        return $this->repository->getLineaPorId($idLinea, $idEmpresa) ?? [];
    }

    /** Reactiva una línea ignorada por error (vuelve a estado SUGERIDO, con su cliente/documento previos si los tenía). */
    public function reactivarLinea(int $idEmpresa, int $idLinea): array
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            throw new \Exception('La línea indicada no existe.');
        }
        if ($linea['estado'] !== 'IGNORADO') {
            throw new \Exception('Solo se puede reactivar una línea que esté ignorada.');
        }

        $this->repository->desconfirmarLinea($idLinea);

        return $this->repository->getLineaPorId($idLinea, $idEmpresa) ?? [];
    }

    public function ignorarLinea(int $idEmpresa, int $idLinea): void
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            throw new \Exception('La línea indicada no existe.');
        }
        if ($linea['estado'] === 'APLICADO') {
            throw new \Exception('Esta línea ya generó un ingreso y no se puede ignorar.');
        }
        $this->repository->marcarLineaIgnorada($idLinea);
    }

    /**
     * Genera un Ingreso por cada línea CONFIRMADO de la carga. Cada línea se procesa en su
     * propia transacción (vía IngresoService::crear) para que el error de una no bloquee
     * las demás; el resultado detalla qué líneas se aplicaron y cuáles fallaron.
     */
    public function generarIngresos(int $idEmpresa, int $idUsuario, int $idCarga): array
    {
        $carga = $this->repository->getCargaPorId($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \Exception('La carga indicada no existe.');
        }

        $punto = $this->repository->getPuntoEmision((int) $carga['id_punto_emision'], $idEmpresa);
        if (!$punto) {
            throw new \Exception('El punto de emisión de esta carga ya no es válido.');
        }
        $cuenta = $this->repository->getCuentaBancariaPorId((int) $carga['id_forma_pago'], $idEmpresa);
        $nombreCuenta = $cuenta['nombre'] ?? '';

        $lineas = array_filter(
            $this->repository->getLineasPorCarga($idCarga, $idEmpresa),
            fn ($l) => $l['estado'] === 'CONFIRMADO'
        );

        $resultados = [];
        foreach ($lineas as $linea) {
            try {
                $idIngreso = $this->crearIngresoDesdeLinea($idEmpresa, $idUsuario, $linea, (int) $carga['id_forma_pago'], $punto, $nombreCuenta);
                $this->repository->marcarLineaAplicada((int) $linea['id'], $idIngreso);
                $resultado = ['id_linea' => (int) $linea['id'], 'ok' => true, 'id_ingreso' => $idIngreso];

                // Pago parcial: lo recibido en el banco fue mayor a lo aplicado a este documento.
                // La diferencia se crea como una línea nueva en la misma carga, para seguir
                // conciliándola (p. ej. contra otra factura pendiente del mismo cliente).
                $diferencia = round((float) $linea['monto'] - (float) $linea['monto_aplicar'], 2);
                if ($diferencia > 0.01) {
                    $idLineaDiferencia = $this->crearLineaDiferencia($idEmpresa, $idUsuario, $idCarga, $linea, $diferencia);
                    $resultado['id_linea_diferencia'] = $idLineaDiferencia;
                    $resultado['diferencia'] = $diferencia;
                }

                $resultados[] = $resultado;
            } catch (\Throwable $e) {
                $this->repository->marcarLineaError((int) $linea['id'], $e->getMessage());
                $resultados[] = ['id_linea' => (int) $linea['id'], 'ok' => false, 'mensaje' => $e->getMessage()];
            }
        }

        $lineasActuales = $this->repository->getLineasPorCarga($idCarga, $idEmpresa);
        $quedanPendientes = !empty(array_filter(
            $lineasActuales,
            fn ($l) => in_array($l['estado'], ['SIN_MATCH', 'SUGERIDO', 'CONFIRMADO'], true)
        ));
        $this->repository->actualizarEstadoCarga($idCarga, $quedanPendientes ? 'pendiente_revision' : 'completado', null, count($lineasActuales));

        return $resultados;
    }

    private function crearIngresoDesdeLinea(int $idEmpresa, int $idUsuario, array $linea, int $idFormaPago, array $punto, string $nombreCuenta = ''): int
    {
        $idCliente = (int) $linea['id_cliente_sugerido'];
        $pendientes = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);

        $doc = null;
        foreach ($pendientes as $d) {
            if ($d['tipo_documento'] === $linea['tipo_documento_sugerido'] && (int) $d['id'] === (int) $linea['id_documento_sugerido']) {
                $doc = $d;
                break;
            }
        }
        if (!$doc) {
            throw new \Exception('El documento seleccionado ya no tiene saldo pendiente (puede haber sido cobrado por otro medio).');
        }

        $saldoAnterior = round((float) $doc['saldo_pendiente'], 2);
        $montoCobrar = round((float) $linea['monto_aplicar'], 2);
        if ($montoCobrar > $saldoAnterior + 0.01) {
            throw new \Exception('El monto a aplicar (' . number_format($montoCobrar, 2) . ') supera el saldo pendiente actual (' . number_format($saldoAnterior, 2) . ') del documento ' . $doc['numero_documento'] . '.');
        }

        $secuencialService = new SecuencialService();
        $secRes = $secuencialService->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos');

        $observaciones = $this->armarObservacionesConciliacion($linea, $nombreCuenta, $montoCobrar);

        $payload = [
            'id_empresa' => $idEmpresa,
            'id_establecimiento' => (int) $punto['id_establecimiento'],
            'id_punto_emision' => (int) $punto['id'],
            'id_cliente' => $idCliente,
            'id_usuario' => $idUsuario,
            'fecha_emision' => $linea['fecha_movimiento'],
            'establecimiento' => $punto['cod_establecimiento'],
            'punto_emision' => $punto['codigo_punto'],
            'secuencial' => $secRes['formateado'],
            'numero_ingreso' => str_pad((string) $punto['cod_establecimiento'], 3, '0', STR_PAD_LEFT)
                . '-' . str_pad((string) $punto['codigo_punto'], 3, '0', STR_PAD_LEFT)
                . '-' . $secRes['formateado'],
            'tipo_ingreso' => 'FACTURA_VENTA',
            'id_ingreso_concepto' => null,
            'monto_total' => $montoCobrar,
            'observaciones' => $observaciones,
            'recibo_de' => $linea['cliente_sugerido_nombre'] ?? '',
            'id_recibo_cliente' => $idCliente,
            'detalles' => [
                [
                    'tipo_documento' => $doc['tipo_documento'],
                    'id_referencia_documento' => (int) $doc['id'],
                    'numero_documento' => $doc['numero_documento'],
                    'descripcion' => 'Cobro de ' . $doc['numero_documento'],
                    'monto_documento' => (float) $doc['importe_total'],
                    'saldo_anterior' => $saldoAnterior,
                    'monto_cobrado' => $montoCobrar,
                    'saldo_actual' => max(0, $saldoAnterior - $montoCobrar),
                ],
            ],
            'pagos' => [
                [
                    'id_forma_cobro' => $idFormaPago,
                    'monto' => $montoCobrar,
                    'referencia' => $linea['referencia_banco'] ?? null,
                    'tipo_operacion_bancaria' => 'TRANSFERENCIA',
                    'numero_cheque' => null,
                    'fecha_cobro' => null,
                ],
            ],
        ];

        return $this->ingresoService->crear($payload);
    }

    /**
     * Crea, en la misma carga, la línea por la diferencia de un pago parcial (lo recibido en
     * el banco fue mayor a lo aplicado al documento ya cobrado) e intenta sugerirle otro
     * documento pendiente del MISMO cliente (ya identificado, no hace falta volver a buscarlo
     * por texto). Si no hay otro documento pendiente, queda sin sugerencia para que el usuario
     * decida manualmente (otro cliente, ignorarla, etc.).
     */
    private function crearLineaDiferencia(int $idEmpresa, int $idUsuario, int $idCarga, array $lineaOriginal, float $diferencia): int
    {
        $idCliente = (int) $lineaOriginal['id_cliente_sugerido'];
        $sugerencia = $this->matchService->sugerirParaClienteConocido(
            $idCliente,
            (string) $lineaOriginal['descripcion_original'],
            $diferencia,
            $idEmpresa
        );

        return $this->repository->insertLinea([
            'id_carga' => $idCarga,
            'id_empresa' => $idEmpresa,
            'fecha_movimiento' => $lineaOriginal['fecha_movimiento'],
            'descripcion_original' => $lineaOriginal['descripcion_original'] . ' (diferencia de pago parcial)',
            'monto' => $diferencia,
            'referencia_banco' => $lineaOriginal['referencia_banco'] ?? null,
            'estado' => $sugerencia['estado'],
            'id_cliente_sugerido' => $sugerencia['id_cliente'],
            'score_match' => $sugerencia['score'],
            'tipo_documento_sugerido' => $sugerencia['tipo_documento'],
            'id_documento_sugerido' => $sugerencia['id_documento'],
            'usuario_id' => $idUsuario,
        ]);
    }

    /**
     * Arma un texto de observaciones que deja trazabilidad hacia el extracto bancario de
     * origen: cuenta, descripción/concepto tal como la puso el banco, referencia o número de
     * documento bancario, fecha del movimiento y, si el monto aplicado no fue el total recibido
     * (pago parcial de la línea), también el monto original recibido.
     */
    private function armarObservacionesConciliacion(array $linea, string $nombreCuenta, float $montoCobrar): string
    {
        $partes = ['Cobro conciliado desde extracto bancario' . ($nombreCuenta !== '' ? " ({$nombreCuenta})" : '') . '.'];

        if (!empty($linea['descripcion_original'])) {
            $partes[] = 'Descripción banco: ' . $linea['descripcion_original'] . '.';
        }
        if (!empty($linea['referencia_banco'])) {
            $partes[] = 'Referencia/documento banco: ' . $linea['referencia_banco'] . '.';
        }
        if (!empty($linea['fecha_movimiento'])) {
            $partes[] = 'Fecha movimiento banco: ' . date('d-m-Y', strtotime((string) $linea['fecha_movimiento'])) . '.';
        }

        $montoLinea = round((float) ($linea['monto'] ?? 0), 2);
        if (abs($montoLinea - $montoCobrar) > 0.01) {
            $partes[] = 'Monto recibido en banco: ' . number_format($montoLinea, 2) . ' (aplicado a este documento: ' . number_format($montoCobrar, 2) . ').';
        }

        return implode(' ', $partes);
    }

    // ── Archivos ─────────────────────────────────────────────────────────────

    private function validarYObtenerTmp(array $file, string $tipoArchivo): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $extsValidas = $tipoArchivo === 'PDF' ? ['pdf'] : ['xlsx', 'xls', 'csv'];
        if (!in_array($ext, $extsValidas, true)) {
            throw new \InvalidArgumentException('El archivo debe ser de tipo: ' . implode(', ', $extsValidas) . '.');
        }

        return (string) $file['tmp_name'];
    }

    private function guardarArchivoFisico(int $idEmpresa, array $file, string $tipoArchivo): array
    {
        $dir = MVC_ROOT . '/' . self::STORAGE_DIR . '/' . $idEmpresa;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $nombreUnico = uniqid('extracto_', true) . '.' . $ext;
        $destino = $dir . '/' . $nombreUnico;

        if (!move_uploaded_file((string) $file['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return [
            'nombre_original' => (string) $file['name'],
            'ruta_relativa' => self::STORAGE_DIR . '/' . $idEmpresa . '/' . $nombreUnico,
            'ruta_absoluta' => $destino,
        ];
    }
}

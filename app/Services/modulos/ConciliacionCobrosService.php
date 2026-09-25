<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ConciliacionCobrosRepository;
use App\repositories\modulos\IngresoRepository;
use App\Rules\modulos\ConciliacionCobrosRules;
use App\Rules\modulos\IngresoRules;
use App\Services\ConciliacionPerfilService;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;

/**
 * Orquesta el flujo de Conciliación de Cobros Bancarios: importación de un
 * extracto bancario (Excel/PDF) leído con un perfil de mapeo del catálogo global
 * (config/conciliacion-perfiles, ver ConciliacionPerfilService) con sugerencia automática
 * de cliente/factura, confirmación manual del usuario y generación en lote
 * de Ingresos reales (mismo payload/servicio que el "cobro rápido" de
 * Ingresos — ver IngresosController::registrarCobroRapidoAjax).
 */
class ConciliacionCobrosService
{
    private const STORAGE_DIR = 'storage/conciliacion_cobros';

    private IngresoService $ingresoService;
    private ConciliacionPerfilService $perfilService;

    public function __construct(
        private ConciliacionCobrosRepository $repository,
        private ConciliacionCobrosRules $rules,
        private ConciliacionImportService $importService,
        private ConciliacionMatchService $matchService,
        private IngresoRepository $ingresoRepository,
        private LogSistemaService $logService,
    ) {
        $this->ingresoService = new IngresoService($ingresoRepository, new IngresoRules(), $logService);
        $this->perfilService = new ConciliacionPerfilService();
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

    /** Formatos de banco (perfiles de mapeo) activos del catálogo global; no dependen de la empresa. */
    public function getPerfiles(): array
    {
        return $this->perfilService->getActivos();
    }

    public function getClientesActivos(int $idEmpresa): array
    {
        return $this->repository->getClientesActivos($idEmpresa);
    }

    /**
     * Clientes de la empresa actual que tienen al menos un documento de cuentas por cobrar
     * pendiente (factura de venta / recibo / saldo inicial). Se resuelve en UNA sola consulta
     * con IngresoRepository::getClientesConDocumentosPendientes(), que aplica exactamente el
     * mismo cálculo de saldos que getFacturasPendientes() — incluido el filtro por el ambiente
     * (pruebas/producción) actual de la empresa en facturas y recibos; los saldos iniciales no
     * llevan ambiente, por diseño. Se usa tanto para la lista de candidatos del matching
     * automático como para el buscador manual, así ningún cliente sin saldo pendiente ni una
     * factura de otro ambiente aparece en ningún lado.
     *
     * OJO: antes esto recorría los clientes activos llamando a getFacturasPendientes() uno por
     * uno. Esa consulta tiene 7 CTE de agregación sobre toda la cartera, así que en una empresa
     * con varios cientos de clientes el módulo no llegaba a abrir (agotaba el tiempo de
     * ejecución y saturaba las conexiones a PostgreSQL). No volver a ese patrón.
     */
    public function getClientesConSaldoPendiente(int $idEmpresa): array
    {
        return $this->ingresoRepository->getClientesConDocumentosPendientes($idEmpresa);
    }

    // ── Cargas (subir extracto → importar → sugerir) ────────────────────────

    public function crearCarga(int $idEmpresa, int $idUsuario, array $data, array $file): array
    {
        $this->rules->validarCarga($data);

        $perfil = $this->perfilService->getActivoPorId((int) $data['id_perfil']);
        if (!$perfil) {
            throw new \Exception('El formato del banco seleccionado no existe o está inactivo.');
        }

        $cuenta = $this->repository->getCuentaBancariaPorId((int) $data['id_forma_pago'], $idEmpresa);
        if (!$cuenta) {
            throw new \Exception('La cuenta bancaria seleccionada no es válida.');
        }

        $punto = $this->repository->getPuntoEmision((int) $data['id_punto_emision'], $idEmpresa);
        if (!$punto) {
            throw new \Exception('La serie (punto de emisión) seleccionada no es válida o está inactiva.');
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

    /**
     * Reparte una línea del banco entre varios documentos pendientes, de uno o varios
     * clientes (p. ej. un solo depósito que paga facturas de distintos clientes). La línea
     * original pasa a ser la primera parte y se crea una línea nueva por cada documento
     * adicional, todas CONFIRMADO: cada una genera después su propio Ingreso con el flujo
     * normal de generarIngresos() (un Ingreso es de un solo cliente). Si lo asignado es menor
     * a lo recibido, el sobrante queda como otra línea sin documento, para seguir conciliándola.
     *
     * @param array $asignaciones [['id_cliente', 'tipo_documento', 'id_documento', 'monto_aplicar'], ...]
     * @return array Líneas resultantes (ids).
     */
    public function dividirLinea(int $idEmpresa, int $idUsuario, int $idLinea, array $asignaciones): array
    {
        $asignaciones = array_values(array_map(fn ($a) => [
            'id_cliente' => (int) ($a['id_cliente'] ?? 0),
            'tipo_documento' => strtoupper((string) ($a['tipo_documento'] ?? '')),
            'id_documento' => (int) ($a['id_documento'] ?? 0),
            'monto_aplicar' => round((float) ($a['monto_aplicar'] ?? 0), 2),
        ], is_array($asignaciones) ? $asignaciones : []));

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            // leer → repartir → escribir sobre la misma línea: candado antes de releerla (§8).
            $this->repository->lockLinea($idLinea);
            $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
            if (!$linea) {
                throw new \Exception('La línea indicada no existe.');
            }
            if (in_array($linea['estado'], ['APLICADO', 'IGNORADO'], true)) {
                throw new \Exception('Esta línea ya fue ' . strtolower($linea['estado']) . ' y no se puede repartir.');
            }

            $montoLinea = round((float) $linea['monto'], 2);
            $this->rules->validarDivision($asignaciones, $montoLinea);

            // Saldo pendiente ACTUAL de cada documento (cacheado por cliente: getFacturasPendientes
            // es la consulta más pesada del sistema, no repetirla por documento).
            $pendientesPorCliente = [];
            foreach ($asignaciones as &$a) {
                if ($a['id_cliente'] > 0 && !isset($pendientesPorCliente[$a['id_cliente']])) {
                    $pendientesPorCliente[$a['id_cliente']] = $this->ingresoRepository->getFacturasPendientes($a['id_cliente'], $idEmpresa);
                }
                $doc = null;
                foreach ($pendientesPorCliente[$a['id_cliente']] ?? [] as $d) {
                    if ($d['tipo_documento'] === $a['tipo_documento'] && (int) $d['id'] === $a['id_documento']) {
                        $doc = $d;
                        break;
                    }
                }
                if ($doc === null) {
                    throw new \Exception('Uno de los documentos seleccionados ya no tiene saldo pendiente o ya no está disponible.');
                }
                $a['numero_documento'] = $doc['numero_documento'];
                $this->rules->validarMatchLinea($a, $montoLinea, (float) $doc['saldo_pendiente']);
            }
            unset($a);

            $totalAsignado = round(array_sum(array_column($asignaciones, 'monto_aplicar')), 2);
            $sobrante = round($montoLinea - $totalAsignado, 2);
            $totalPartes = count($asignaciones) + ($sobrante > 0.01 ? 1 : 0);
            $descripcionBase = (string) $linea['descripcion_original'];
            $sufijo = fn (int $n) => " (parte {$n}/{$totalPartes} del depósito de $" . number_format($montoLinea, 2) . ')';

            $antes = $linea;
            // Todas las partes del mismo depósito comparten origen (si la línea ya era una parte,
            // conserva el de su depósito): así generarIngresos() las cobra en un solo ingreso por cliente.
            $idOrigen = !empty($linea['id_linea_origen']) ? (int) $linea['id_linea_origen'] : $idLinea;
            $ids = [];
            foreach ($asignaciones as $i => $a) {
                $datosParte = [
                    'descripcion_original' => $descripcionBase . $sufijo($i + 1),
                    'monto' => $a['monto_aplicar'],
                    'estado' => 'CONFIRMADO',
                    'id_cliente_sugerido' => $a['id_cliente'],
                    'tipo_documento_sugerido' => $a['tipo_documento'],
                    'id_documento_sugerido' => $a['id_documento'],
                    'monto_aplicar' => $a['monto_aplicar'],
                    'id_linea_origen' => $idOrigen,
                    'usuario_id' => $idUsuario,
                ];
                if ($i === 0) {
                    $this->repository->actualizarParteLinea($idLinea, $datosParte);
                    $ids[] = $idLinea;
                } else {
                    $ids[] = $this->repository->insertLinea($datosParte + [
                        'id_carga' => (int) $linea['id_carga'],
                        'id_empresa' => $idEmpresa,
                        'fecha_movimiento' => $linea['fecha_movimiento'],
                        'referencia_banco' => $linea['referencia_banco'] ?? null,
                        'score_match' => null,
                    ]);
                }
            }

            if ($sobrante > 0.01) {
                $ids[] = $this->repository->insertLinea([
                    'id_carga' => (int) $linea['id_carga'],
                    'id_empresa' => $idEmpresa,
                    'fecha_movimiento' => $linea['fecha_movimiento'],
                    'descripcion_original' => $descripcionBase . $sufijo($totalPartes) . ' — saldo sin asignar',
                    'monto' => $sobrante,
                    'referencia_banco' => $linea['referencia_banco'] ?? null,
                    'estado' => 'SIN_MATCH',
                    'id_linea_origen' => $idOrigen,
                    'usuario_id' => $idUsuario,
                ]);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'dividir', 'conciliacion_lineas', $idLinea, $antes, [
                'monto_banco' => $montoLinea,
                'asignaciones' => $asignaciones,
                'sobrante' => max(0, $sobrante),
                'lineas_resultantes' => $ids,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['ids' => $ids];
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
     * Genera los Ingresos de las líneas CONFIRMADO de la carga.
     *
     * Las partes de un mismo depósito repartido entre varios documentos (mismo id_linea_origen)
     * se cobran JUNTAS: un Ingreso por cliente con todos sus documentos y un solo pago por el
     * total, para que el cobro coincida con el depósito del banco. Por cliente y no uno solo,
     * porque un Ingreso es de un único cliente (cabecera y asiento de cartera van a su nombre).
     * Una línea que no viene de un reparto es un grupo de uno: se cobra sola, como siempre.
     *
     * Cada grupo va en su propia transacción, para que el error de uno no bloquee los demás;
     * el resultado detalla qué líneas se aplicaron y cuáles fallaron.
     */
    public function generarIngresos(int $idEmpresa, int $idUsuario, int $idCarga): array
    {
        $carga = $this->repository->getCargaPorId($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \Exception('La carga indicada no existe.');
        }

        $punto = $this->repository->getPuntoEmision((int) $carga['id_punto_emision'], $idEmpresa);
        if (!$punto) {
            throw new \Exception('La serie (punto de emisión) de esta carga ya no es válida o está inactiva. Actívela en Empresa → Puntos de Emisión para generar los ingresos.');
        }
        $cuenta = $this->repository->getCuentaBancariaPorId((int) $carga['id_forma_pago'], $idEmpresa);
        $nombreCuenta = $cuenta['nombre'] ?? '';

        $grupos = [];
        foreach ($this->repository->getLineasPorCarga($idCarga, $idEmpresa) as $l) {
            if ($l['estado'] !== 'CONFIRMADO') {
                continue;
            }
            $origen = !empty($l['id_linea_origen']) ? 'o' . (int) $l['id_linea_origen'] : 'l' . (int) $l['id'];
            $grupos[$origen . ':' . (int) $l['id_cliente_sugerido']][] = $l;
        }

        $resultados = [];
        foreach ($grupos as $lineasGrupo) {
            try {
                $idIngreso = $this->crearIngresoDesdeLineas($idEmpresa, $idUsuario, $lineasGrupo, (int) $carga['id_forma_pago'], $punto, $nombreCuenta);
            } catch (\Throwable $e) {
                foreach ($lineasGrupo as $linea) {
                    $this->repository->marcarLineaError((int) $linea['id'], $e->getMessage());
                    $resultados[] = ['id_linea' => (int) $linea['id'], 'ok' => false, 'mensaje' => $e->getMessage()];
                }
                continue;
            }

            foreach ($lineasGrupo as $linea) {
                $this->repository->marcarLineaAplicada((int) $linea['id'], $idIngreso);
                $resultado = ['id_linea' => (int) $linea['id'], 'ok' => true, 'id_ingreso' => $idIngreso];

                // Pago parcial: lo recibido en el banco fue mayor a lo aplicado a este documento.
                // La diferencia se crea como una línea nueva en la misma carga, para seguir
                // conciliándola (p. ej. contra otra factura pendiente del mismo cliente).
                $diferencia = round((float) $linea['monto'] - (float) $linea['monto_aplicar'], 2);
                if ($diferencia > 0.01) {
                    $resultado['id_linea_diferencia'] = $this->crearLineaDiferencia($idEmpresa, $idUsuario, $idCarga, $linea, $diferencia);
                    $resultado['diferencia'] = $diferencia;
                }
                $resultados[] = $resultado;
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

    /**
     * Crea UN Ingreso para una o varias líneas confirmadas del MISMO cliente: un detalle por
     * documento (si dos líneas apuntan al mismo documento, se suman) y un solo pago por el total.
     */
    private function crearIngresoDesdeLineas(int $idEmpresa, int $idUsuario, array $lineas, int $idFormaPago, array $punto, string $nombreCuenta = ''): int
    {
        $primera = $lineas[0];
        $idCliente = (int) $primera['id_cliente_sugerido'];
        $pendientes = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);

        // Monto a cobrar por documento (tipo:id), en el orden en que aparecen las líneas.
        $porDocumento = [];
        foreach ($lineas as $linea) {
            if ((int) $linea['id_cliente_sugerido'] !== $idCliente) {
                throw new \LogicException('Las líneas de un mismo ingreso deben ser del mismo cliente.');
            }
            $clave = $linea['tipo_documento_sugerido'] . ':' . (int) $linea['id_documento_sugerido'];
            $porDocumento[$clave] = round(($porDocumento[$clave] ?? 0) + (float) $linea['monto_aplicar'], 2);
        }

        $detalles = [];
        $docs = [];
        foreach ($porDocumento as $clave => $montoCobrar) {
            [$tipo, $id] = explode(':', $clave);
            $doc = null;
            foreach ($pendientes as $d) {
                if ($d['tipo_documento'] === $tipo && (int) $d['id'] === (int) $id) {
                    $doc = $d;
                    break;
                }
            }
            if (!$doc) {
                throw new \Exception('Un documento seleccionado ya no tiene saldo pendiente (puede haber sido cobrado por otro medio).');
            }
            $saldoAnterior = round((float) $doc['saldo_pendiente'], 2);
            if ($montoCobrar > $saldoAnterior + 0.01) {
                throw new \Exception('El monto a aplicar (' . number_format($montoCobrar, 2) . ') supera el saldo pendiente actual (' . number_format($saldoAnterior, 2) . ') del documento ' . $doc['numero_documento'] . '.');
            }
            $docs[] = $doc;
            $detalles[] = [
                'tipo_documento' => $doc['tipo_documento'],
                'id_referencia_documento' => (int) $doc['id'],
                'numero_documento' => $doc['numero_documento'],
                'descripcion' => 'Cobro de ' . $doc['numero_documento'],
                'monto_documento' => (float) $doc['importe_total'],
                'saldo_anterior' => $saldoAnterior,
                'monto_cobrado' => $montoCobrar,
                'saldo_actual' => max(0, $saldoAnterior - $montoCobrar),
            ];
        }

        $montoTotal = round(array_sum($porDocumento), 2);

        // Trazabilidad del extracto: con varias partes, la descripción del depósito sin el
        // "(parte i/n …)" de cada una, y como monto recibido la suma de las partes cobradas.
        $lineaTraza = $primera;
        if (count($lineas) > 1) {
            $lineaTraza['descripcion_original'] = $this->descripcionDeposito((string) $primera['descripcion_original']);
            $lineaTraza['monto'] = round(array_sum(array_map(fn ($l) => (float) $l['monto'], $lineas)), 2);
        }

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (IngresoService::crear()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8). Cada grupo va en su propia transacción, tal
        // como espera generarIngresos().
        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            $secRes = (new SecuencialService())->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos', $primera['fecha_movimiento']);

            $observaciones = $this->armarObservacionesIngreso($docs, $nombreCuenta, $montoTotal, $primera['referencia_banco'] ?? null)
                . '. ' . $this->armarObservacionesConciliacion($lineaTraza, $nombreCuenta, $montoTotal);

            $payload = [
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => (int) $punto['id_establecimiento'],
                'id_punto_emision' => (int) $punto['id'],
                'id_cliente' => $idCliente,
                'id_usuario' => $idUsuario,
                'fecha_emision' => $primera['fecha_movimiento'],
                'establecimiento' => $punto['cod_establecimiento'],
                'punto_emision' => $punto['codigo_punto'],
                'secuencial' => $secRes['formateado'],
                'numero_ingreso' => str_pad((string) $punto['cod_establecimiento'], 3, '0', STR_PAD_LEFT)
                    . '-' . str_pad((string) $punto['codigo_punto'], 3, '0', STR_PAD_LEFT)
                    . '-' . $secRes['formateado'],
                'tipo_ingreso' => 'FACTURA_VENTA',
                'id_ingreso_concepto' => null,
                'monto_total' => $montoTotal,
                'observaciones' => $observaciones,
                'recibo_de' => $primera['cliente_sugerido_nombre'] ?? '',
                'id_recibo_cliente' => $idCliente,
                'detalles' => $detalles,
                'pagos' => [
                    [
                        'id_forma_cobro' => $idFormaPago,
                        'monto' => $montoTotal,
                        'referencia' => $primera['referencia_banco'] ?? null,
                        'tipo_operacion_bancaria' => 'TRANSFERENCIA',
                        'numero_cheque' => null,
                        'fecha_cobro' => null,
                    ],
                ],
            ];

            $idIngreso = $this->ingresoService->crear($payload);
            if ($managedTransaction) {
                $db->commit();
                // Como la transacción es nuestra, IngresoService::crear() no genera el asiento ni
                // recalcula saldos: le toca al llamador, DESPUÉS del COMMIT (ver IngresoService).
                $this->ingresoService->tareasPostCommit($idIngreso, $payload);
            }
            return $idIngreso;
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Descripción del depósito sin el sufijo que dividirLinea() agrega a cada parte. */
    private function descripcionDeposito(string $descripcion): string
    {
        $texto = preg_replace('/ \(parte \d+\/\d+ del (depósito de \$[^)]+)\)/u', ' ($1)', $descripcion) ?? $descripcion;
        return str_replace(' — saldo sin asignar', '', $texto);
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
            // Sigue siendo parte del mismo depósito: si se cobra junto con sus hermanas, va al
            // mismo ingreso del cliente.
            'id_linea_origen' => !empty($lineaOriginal['id_linea_origen']) ? (int) $lineaOriginal['id_linea_origen'] : null,
            'usuario_id' => $idUsuario,
        ]);
    }

    /**
     * Mismo texto que arma el modal de Ingresos al registrar un cobro a mano
     * (ingGenerarObservaciones() en app/views/modulos/ingresos/index.php), para que un
     * ingreso conciliado se lea igual que uno manual:
     * "Cobro facturas de venta 501, 502; recibo de venta 7; Cobrado con BANCO PICHINCHA $90.00
     * (transferencia ref. 4455)". Agrupa por tipo con singular/plural; facturas y recibos van
     * con el secuencial corto (sin estab./punto ni ceros a la izquierda).
     *
     * @param array $docs Documentos cobrados (tipo_documento, numero_documento), en orden.
     */
    private function armarObservacionesIngreso(array $docs, string $nombreCuenta, float $montoCobrar, ?string $referencia): string
    {
        $etiquetas = [
            'FACTURA' => ['factura de venta', 'facturas de venta'],
            'RECIBO' => ['recibo de venta', 'recibos de venta'],
            'FACTURA_REEMBOLSO' => ['factura de reembolso', 'facturas de reembolso'],
            'SALDO_INICIAL' => ['saldo inicial', 'saldos iniciales'],
        ];
        $grupos = [];
        foreach ($docs as $doc) {
            $tipo = (string) ($doc['tipo_documento'] ?? 'FACTURA');
            $numero = trim((string) ($doc['numero_documento'] ?? ''));
            if (in_array($tipo, ['FACTURA', 'RECIBO'], true)) {
                $partes = explode('-', $numero);
                $corto = preg_replace('/^0+(?=\d)/', '', (string) end($partes));
                $numero = $corto !== '' ? $corto : $numero;
            }
            $grupos[$tipo][] = $numero;
        }
        $textosTipo = [];
        foreach ($grupos as $tipo => $numeros) {
            [$singular, $plural] = $etiquetas[$tipo] ?? ['documento', 'documentos'];
            $textosTipo[] = (count($numeros) > 1 ? $plural : $singular) . ' ' . implode(', ', $numeros);
        }

        $texto = 'Cobro ' . implode('; ', $textosTipo);

        if ($nombreCuenta !== '') {
            $detalle = ['transferencia'];
            $referencia = trim((string) $referencia);
            if ($referencia !== '') {
                $detalle[] = 'ref. ' . $referencia;
            }
            $texto .= '; Cobrado con ' . $nombreCuenta . ' $' . number_format($montoCobrar, 2, '.', '') . ' (' . implode(' ', $detalle) . ')';
        }

        return $texto;
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
            $partes[] = 'Monto recibido en banco: ' . number_format($montoLinea, 2) . ' (cobrado en este ingreso: ' . number_format($montoCobrar, 2) . ').';
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

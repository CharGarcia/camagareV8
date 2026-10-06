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
                // Mismo movimiento ya subido en otra carga de esta cuenta (extractos que se
                // solapan en fechas): entra IGNORADO para no cobrarlo dos veces. Si de verdad es
                // otro depósito idéntico, el usuario lo reactiva con ↺.
                $repetido = $this->repository->buscarMovimientoRepetido(
                    $idEmpresa, (int) $cuenta['id'], $idCarga, (string) $fila['fecha'], (float) $fila['monto'],
                    $fila['referencia'] ?? null, (string) $fila['descripcion']
                );
                if ($repetido) {
                    $this->repository->insertLinea([
                        'id_carga' => $idCarga,
                        'id_empresa' => $idEmpresa,
                        'fecha_movimiento' => $fila['fecha'],
                        'descripcion_original' => $fila['descripcion'],
                        'monto' => $fila['monto'],
                        'referencia_banco' => $fila['referencia'],
                        'estado' => 'IGNORADO',
                        'mensaje_error' => 'Movimiento repetido: ya está en la carga «' . $repetido['nombre_archivo'] . '» del '
                            . date('d-m-Y H:i:s', strtotime((string) $repetido['created_at']))
                            . (!empty($repetido['id_ingreso']) ? ', que ya generó su ingreso' : '')
                            . '. Si es otro depósito idéntico, reactívelo.',
                        'usuario_id' => $idUsuario,
                    ]);
                    continue;
                }

                $sugerencia = $this->matchService->sugerir($fila, $clientes, $idEmpresa);
                $this->insertarLineaSugerida($idCarga, $idEmpresa, $idUsuario, [
                    'fecha_movimiento' => $fila['fecha'],
                    'descripcion_original' => $fila['descripcion'],
                    'monto' => (float) $fila['monto'],
                    'referencia_banco' => $fila['referencia'],
                ], $sugerencia, null);
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

    /**
     * Inserta la línea de un movimiento del banco con su sugerencia. La línea conserva el
     * monto tal como vino en el archivo (nunca se divide): si el cliente quedó identificado,
     * el depósito se reparte por antigüedad entre sus documentos pendientes
     * (ConciliacionMatchService::repartirPorAntiguedad) y cada documento queda como un
     * DETALLE de la misma línea. Si el depósito supera la cartera del cliente, el resto queda
     * sin asignar (monto − suma de detalles): el usuario lo completa desde la lupa con
     * documentos de otros clientes o, al generar, se crea una línea nueva por la diferencia.
     *
     * $idOrigen: línea de la que proviene (diferencia de un pago parcial); null si viene del archivo.
     *
     * @param array $mov ['fecha_movimiento', 'descripcion_original', 'monto', 'referencia_banco']
     * @return int id de la línea insertada
     */
    private function insertarLineaSugerida(int $idCarga, int $idEmpresa, int $idUsuario, array $mov, array $sugerencia, ?int $idOrigen, string $sufijoFijo = ''): int
    {
        $monto = round((float) $mov['monto'], 2);

        $detalles = [];
        if (!empty($sugerencia['id_cliente']) && !empty($sugerencia['id_documento'])) {
            $reparto = $this->matchService->repartirPorAntiguedad((int) $sugerencia['id_cliente'], $monto, $idEmpresa);
            foreach ($reparto['asignaciones'] as $a) {
                $detalles[] = [
                    'id_cliente' => (int) $sugerencia['id_cliente'],
                    'tipo_documento' => $a['tipo_documento'],
                    'id_documento' => $a['id_documento'],
                    'numero_documento' => $a['numero_documento'],
                    'monto_aplicar' => $a['monto'],
                ];
            }
        }

        $resumen = ConciliacionCobrosRepository::resumenDeDetalles($detalles, $sugerencia['id_cliente'] !== null ? (int) $sugerencia['id_cliente'] : null);
        $idLinea = $this->repository->insertLinea([
            'id_carga' => $idCarga,
            'id_empresa' => $idEmpresa,
            'fecha_movimiento' => $mov['fecha_movimiento'],
            'descripcion_original' => (string) $mov['descripcion_original'] . $sufijoFijo,
            'monto' => $monto,
            'referencia_banco' => $mov['referencia_banco'] ?? null,
            'estado' => $sugerencia['estado'],
            'score_match' => $sugerencia['score'],
            'id_linea_origen' => $idOrigen,
            'usuario_id' => $idUsuario,
        ] + $resumen);

        if ($detalles) {
            $this->repository->reemplazarDetalles($idLinea, $idEmpresa, $detalles, $idUsuario);
        }
        return $idLinea;
    }

    public function listarCargas(int $idEmpresa): array
    {
        return $this->repository->listarCargas($idEmpresa);
    }

    /**
     * Líneas de una carga con sus documentos asignados (`detalles`), cada uno con el número y
     * el saldo actual de la cuenta por cobrar, más el resumen que pinta la grilla:
     * `monto_asignado` (suma de los detalles), `clientes_nombres` (distintos) y, por
     * compatibilidad con la fila de un solo documento, `documento_numero` /
     * `documento_saldo_pendiente` del primero.
     */
    public function listarLineas(int $idCarga, int $idEmpresa): array
    {
        $carga = $this->repository->getCargaPorId($idCarga, $idEmpresa);
        if (!$carga) {
            throw new \Exception('La carga indicada no existe.');
        }

        $lineas = $this->repository->getLineasPorCarga($idCarga, $idEmpresa);
        $detallesPorLinea = $this->repository->getDetallesPorLineas(array_column($lineas, 'id'), $idEmpresa);

        $pendientesPorCliente = [];
        foreach ($lineas as &$linea) {
            $this->enriquecerLinea($linea, $detallesPorLinea[(int) $linea['id']] ?? [], $idEmpresa, $pendientesPorCliente);
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

    /** Una línea con sus detalles y resumen, tal como la devuelve listarLineas() (respuesta de las acciones por línea). */
    private function lineaConDetalles(int $idLinea, int $idEmpresa): array
    {
        $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
        if (!$linea) {
            return [];
        }
        $cache = [];
        $this->enriquecerLinea($linea, $this->repository->getDetallesPorLinea($idLinea, $idEmpresa), $idEmpresa, $cache);
        return $linea;
    }

    /**
     * Completa la línea con sus detalles (número y saldo actual de cada documento, leídos de
     * los pendientes del cliente, cacheados por cliente porque getFacturasPendientes es la
     * consulta más pesada del sistema) y el resumen para la grilla.
     */
    private function enriquecerLinea(array &$linea, array $detalles, int $idEmpresa, array &$pendientesPorCliente): void
    {
        $nombres = [];
        foreach ($detalles as &$d) {
            $idCliente = (int) $d['id_cliente'];
            if (!isset($pendientesPorCliente[$idCliente])) {
                $pendientesPorCliente[$idCliente] = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
            }
            $d['saldo_pendiente'] = null;
            foreach ($pendientesPorCliente[$idCliente] as $doc) {
                if ($doc['tipo_documento'] === $d['tipo_documento'] && (int) $doc['id'] === (int) $d['id_documento']) {
                    $d['numero_documento'] = $d['numero_documento'] ?: $doc['numero_documento'];
                    $d['saldo_pendiente'] = round((float) $doc['saldo_pendiente'], 2);
                    break;
                }
            }
            if (!empty($d['cliente_nombre'])) {
                $nombres[$idCliente] = $d['cliente_nombre'];
            }
        }
        unset($d);

        $linea['detalles'] = $detalles;
        $linea['monto_asignado'] = round(array_sum(array_map(fn ($d) => (float) $d['monto_aplicar'], $detalles)), 2);
        $linea['clientes_nombres'] = array_values($nombres);
        if ($detalles) {
            $linea['documento_numero'] = $detalles[0]['numero_documento'];
            $linea['documento_saldo_pendiente'] = $detalles[0]['saldo_pendiente'];
        }
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

        return $this->lineaConDetalles($idLinea, $idEmpresa);
    }

    /**
     * Documentos por cobrar de un cliente para el buscador de la lupa, con el saldo que
     * realmente queda por conciliar: saldo de la cuenta por cobrar menos lo que otras líneas
     * confirmadas (aún sin ingreso) ya tienen apartado. Los que no tienen nada disponible no
     * se ofrecen. $idLinea es la línea que se está editando: lo suyo no cuenta como apartado.
     */
    public function buscarDocumentosPendientes(int $idEmpresa, int $idCliente, int $idLinea = 0): array
    {
        $apartados = $this->repository->getMontosApartados($idEmpresa, $idLinea > 0 ? [$idLinea] : []);

        $docs = [];
        foreach ($this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa) as $doc) {
            $apartado = $apartados[$doc['tipo_documento'] . ':' . (int) $doc['id']] ?? 0.0;
            $disponible = round((float) $doc['saldo_pendiente'] - $apartado, 2);
            if ($disponible <= 0.009) {
                continue;
            }
            $doc['saldo_cxc'] = round((float) $doc['saldo_pendiente'], 2);
            $doc['apartado'] = $apartado;
            $doc['saldo_pendiente'] = $disponible;
            $docs[] = $doc;
        }
        return $docs;
    }

    /**
     * Saldo de la cuenta por cobrar de un documento y lo que otras líneas confirmadas ya tienen
     * apartado de él. Llamar DENTRO de una transacción que ya tomó lockDocumento(): así ninguna
     * otra confirmación puede apartar ese saldo entre la lectura y la escritura.
     *
     * @return array{doc: array, saldo: float, apartado: float}
     */
    private function saldoDisponibleDocumento(int $idEmpresa, int $idCliente, string $tipo, int $idDocumento, array $excluirLineas, array &$cachePendientes = []): array
    {
        if (!isset($cachePendientes[$idCliente])) {
            $cachePendientes[$idCliente] = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
        }
        foreach ($cachePendientes[$idCliente] as $doc) {
            if ($doc['tipo_documento'] === $tipo && (int) $doc['id'] === $idDocumento) {
                $apartados = $this->repository->getMontosApartados($idEmpresa, $excluirLineas, $tipo, $idDocumento);
                return [
                    'doc' => $doc,
                    'saldo' => round((float) $doc['saldo_pendiente'], 2),
                    'apartado' => $apartados[$tipo . ':' . $idDocumento] ?? 0.0,
                ];
            }
        }
        throw new \Exception('El documento seleccionado ya no tiene saldo pendiente en la cuenta por cobrar o ya no está disponible.');
    }

    /**
     * Confirma la línea con los documentos que la completan (uno o varios, de uno o varios
     * clientes): el usuario marca el check de "sí son estos documentos". La línea nunca se
     * divide: conserva el monto del banco y los documentos quedan como sus detalles; al
     * generar, se cobra en UN solo ingreso. Si $asignaciones viene vacío se confirma con los
     * detalles que ya tenía (la sugerencia). Cada documento se valida contra el saldo ACTUAL
     * de la cuenta por cobrar menos lo que otras líneas confirmadas ya apartaron, con el
     * documento bloqueado, para que el mismo saldo no se pueda cobrar dos veces.
     *
     * @param array $asignaciones [['id_cliente', 'tipo_documento', 'id_documento', 'monto_aplicar'], ...]
     */
    public function confirmarLinea(int $idEmpresa, int $idUsuario, int $idLinea, array $asignaciones): array
    {
        $asignaciones = array_values(array_map(fn ($a) => [
            'id_cliente' => (int) ($a['id_cliente'] ?? 0),
            'tipo_documento' => strtoupper((string) ($a['tipo_documento'] ?? '')),
            'id_documento' => (int) ($a['id_documento'] ?? 0),
            'monto_aplicar' => round((float) ($a['monto_aplicar'] ?? 0), 2),
        ], $asignaciones));

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            // leer → validar → escribir sobre la misma línea: candado antes de releerla (§8).
            $this->repository->lockLinea($idLinea);
            $linea = $this->repository->getLineaPorId($idLinea, $idEmpresa);
            if (!$linea) {
                throw new \Exception('La línea indicada no existe.');
            }
            if (in_array($linea['estado'], ['APLICADO', 'IGNORADO'], true)) {
                throw new \Exception('Esta línea ya fue ' . strtolower($linea['estado']) . ' y no se puede modificar.');
            }

            $detallesAntes = $this->repository->getDetallesPorLinea($idLinea, $idEmpresa);
            if (!$asignaciones) {
                $asignaciones = array_map(fn ($d) => [
                    'id_cliente' => (int) $d['id_cliente'],
                    'tipo_documento' => (string) $d['tipo_documento'],
                    'id_documento' => (int) $d['id_documento'],
                    'monto_aplicar' => round((float) $d['monto_aplicar'], 2),
                ], $detallesAntes);
            }

            $montoLinea = round((float) $linea['monto'], 2);
            $this->rules->validarAsignaciones($asignaciones, $montoLinea);

            // Candado de cada documento en orden fijo (evita que dos confirmaciones simultáneas
            // se bloqueen entre sí) y luego el saldo ACTUAL de su cuenta por cobrar menos lo que
            // otras líneas confirmadas ya apartaron. Los pendientes se cachean por cliente:
            // getFacturasPendientes es la consulta más pesada del sistema.
            $claves = array_map(fn ($a) => $a['tipo_documento'] . ':' . $a['id_documento'], $asignaciones);
            sort($claves);
            foreach ($claves as $clave) {
                [$tipo, $idDoc] = explode(':', $clave);
                if ($tipo !== '' && (int) $idDoc > 0) {
                    $this->repository->lockDocumento($idEmpresa, $tipo, (int) $idDoc);
                }
            }
            $pendientesPorCliente = [];
            foreach ($asignaciones as &$a) {
                if ($a['id_cliente'] <= 0 || $a['tipo_documento'] === '' || $a['id_documento'] <= 0) {
                    $this->rules->validarMatchLinea($a, $montoLinea); // mensaje estándar de "falta cliente/documento"
                }
                $info = $this->saldoDisponibleDocumento($idEmpresa, $a['id_cliente'], $a['tipo_documento'], $a['id_documento'], [$idLinea], $pendientesPorCliente);
                $a['numero_documento'] = $info['doc']['numero_documento'];
                $this->rules->validarMatchLinea($a, $montoLinea, $info['saldo'], $info['apartado']);
            }
            unset($a);

            $this->repository->reemplazarDetalles($idLinea, $idEmpresa, $asignaciones, $idUsuario);
            $this->repository->actualizarMatchLinea($idLinea, ['estado' => 'CONFIRMADO', 'usuario_id' => $idUsuario]
                + ConciliacionCobrosRepository::resumenDeDetalles($asignaciones));

            $this->logService->registrar($idUsuario, $idEmpresa, 'confirmar', 'conciliacion_lineas', $idLinea,
                ['estado' => $linea['estado'], 'detalles' => $detallesAntes],
                ['estado' => 'CONFIRMADO', 'monto_banco' => $montoLinea, 'detalles' => $asignaciones]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->lineaConDetalles($idLinea, $idEmpresa);
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

        return $this->lineaConDetalles($idLinea, $idEmpresa);
    }

    /** Reactiva una línea ignorada por error (vuelve a estado SUGERIDO, con su cliente/documentos previos si los tenía). */
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

        return $this->lineaConDetalles($idLinea, $idEmpresa);
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
     * Genera los Ingresos de las líneas CONFIRMADO de la carga: UN Ingreso por línea (por
     * depósito), con todos los documentos que la completan en el detalle —aunque sean de
     * clientes distintos— y un solo pago por el total asignado, para que el cobro coincida
     * con el depósito del banco.
     *
     * Cada línea va en su propia transacción, para que el error de una no bloquee las demás;
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

        // Una sola generación a la vez por carga (doble clic, dos usuarios): sin esto, las dos
        // leerían las mismas líneas CONFIRMADO y cobrarían dos veces un pago parcial.
        if (!$this->repository->tomarCandadoGeneracion($idCarga)) {
            throw new \Exception('Los ingresos de esta carga ya se están generando en otra ventana o por otro usuario. Espere a que termine y recargue las líneas.');
        }
        try {
            $resultados = $this->generarIngresosDeGrupos($idEmpresa, $idUsuario, $idCarga, (int) $carga['id_forma_pago'], $punto, $nombreCuenta);
        } finally {
            $this->repository->soltarCandadoGeneracion($idCarga);
        }

        $lineasActuales = $this->repository->getLineasPorCarga($idCarga, $idEmpresa);
        $quedanPendientes = !empty(array_filter(
            $lineasActuales,
            fn ($l) => in_array($l['estado'], ['SIN_MATCH', 'SUGERIDO', 'CONFIRMADO'], true)
        ));
        $this->repository->actualizarEstadoCarga($idCarga, $quedanPendientes ? 'pendiente_revision' : 'completado', null, count($lineasActuales));

        return $resultados;
    }

    /** Cuerpo de generarIngresos(), ya con el candado de la carga tomado. */
    private function generarIngresosDeGrupos(int $idEmpresa, int $idUsuario, int $idCarga, int $idFormaPago, array $punto, string $nombreCuenta): array
    {
        $lineas = array_values(array_filter(
            $this->repository->getLineasPorCarga($idCarga, $idEmpresa),
            fn ($l) => $l['estado'] === 'CONFIRMADO'
        ));
        $detallesPorLinea = $this->repository->getDetallesPorLineas(array_column($lineas, 'id'), $idEmpresa);

        $resultados = [];
        foreach ($lineas as $linea) {
            $linea['detalles'] = $detallesPorLinea[(int) $linea['id']] ?? [];
            try {
                $idIngreso = $this->crearIngresoDesdeLinea($idEmpresa, $idUsuario, $linea, $idFormaPago, $punto, $nombreCuenta);
            } catch (LineaYaProcesadaException $e) {
                // Otra sesión ya la cobró o la cambió: no es un error de la línea, no se marca.
                $resultados[] = ['id_linea' => (int) $linea['id'], 'ok' => false, 'mensaje' => $e->getMessage()];
                continue;
            } catch (\Throwable $e) {
                $this->repository->marcarLineaError((int) $linea['id'], $e->getMessage());
                $resultados[] = ['id_linea' => (int) $linea['id'], 'ok' => false, 'mensaje' => $e->getMessage()];
                continue;
            }

            // La línea ya quedó APLICADO dentro de la misma transacción del ingreso.
            $resultado = ['id_linea' => (int) $linea['id'], 'ok' => true, 'id_ingreso' => $idIngreso];

            // Pago parcial: lo recibido en el banco fue mayor a lo asignado a los documentos.
            // La diferencia se crea como una línea nueva en la misma carga, para seguir
            // conciliándola (p. ej. contra otra factura pendiente del mismo cliente).
            $montoAsignado = round(array_sum(array_map(fn ($d) => (float) $d['monto_aplicar'], $linea['detalles'])), 2);
            $diferencia = round((float) $linea['monto'] - $montoAsignado, 2);
            if ($diferencia > 0.01) {
                $resultado['id_linea_diferencia'] = $this->crearLineaDiferencia($idEmpresa, $idUsuario, $idCarga, $linea, $diferencia);
                $resultado['diferencia'] = $diferencia;
            }
            $resultados[] = $resultado;
        }

        return $resultados;
    }

    /**
     * Crea UN Ingreso para una línea confirmada con todos sus documentos (de uno o varios
     * clientes): un detalle por documento y un solo pago por el total asignado. Con un único
     * cliente, el ingreso va a su nombre; con varios, la cabecera queda sin cliente —igual que
     * un cobro multi-cliente registrado a mano en Ingresos— y "Recibo de" lista los nombres;
     * el asiento pone el tercero en cada línea de cartera según el documento cobrado.
     * Dentro de la misma transacción del ingreso relee la línea con su candado (si otra sesión
     * ya la cobró o le cambió los documentos, no se cobra: LineaYaProcesadaException) y la deja
     * APLICADO, así el ingreso y la marca de la línea se graban juntos o no se graba ninguno.
     */
    private function crearIngresoDesdeLinea(int $idEmpresa, int $idUsuario, array $linea, int $idFormaPago, array $punto, string $nombreCuenta = ''): int
    {
        $detallesLinea = $linea['detalles'] ?? [];
        if (!$detallesLinea) {
            throw new \Exception('La línea no tiene documentos asignados; selecciónelos con la lupa y confírmela de nuevo.');
        }

        // Monto a cobrar por documento (tipo:id) y cliente de cada uno, en el orden de los detalles.
        $porDocumento = [];
        $clientePorDocumento = [];
        $nombresClientes = [];
        foreach ($detallesLinea as $d) {
            $clave = strtoupper((string) $d['tipo_documento']) . ':' . (int) $d['id_documento'];
            $porDocumento[$clave] = round(($porDocumento[$clave] ?? 0) + (float) $d['monto_aplicar'], 2);
            $clientePorDocumento[$clave] = (int) $d['id_cliente'];
            $nombresClientes[(int) $d['id_cliente']] = (string) ($d['cliente_nombre'] ?? '');
        }
        $idsClientes = array_keys($nombresClientes);
        $idClienteUnico = count($idsClientes) === 1 ? $idsClientes[0] : null;

        $pendientesPorCliente = [];
        $detalles = [];
        $docs = [];
        foreach ($porDocumento as $clave => $montoCobrar) {
            [$tipo, $id] = explode(':', $clave);
            $idCliente = $clientePorDocumento[$clave];
            if (!isset($pendientesPorCliente[$idCliente])) {
                $pendientesPorCliente[$idCliente] = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
            }
            $doc = null;
            foreach ($pendientesPorCliente[$idCliente] as $d) {
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

        // "Recibo de": el cliente, o los nombres de todos cuando el depósito paga a varios.
        $reciboDe = $idClienteUnico !== null
            ? (string) ($nombresClientes[$idClienteUnico] ?: ($linea['cliente_sugerido_nombre'] ?? ''))
            : implode(', ', array_filter($nombresClientes));
        $reciboDe = mb_substr($reciboDe, 0, 300);

        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (IngresoService::crear()): el lock de obtenerSiguienteSecuencial() se libera
        // solo al COMMIT/ROLLBACK (CLAUDE.md §8). Cada línea va en su propia transacción, tal
        // como espera generarIngresos().
        $db = \App\core\Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
            // Releer la línea y sus detalles con su candado: si entre la lectura del listado y
            // aquí otra sesión la cobró, la desconfirmó o le cambió los documentos, no se cobra.
            $this->repository->lockLinea((int) $linea['id']);
            $actual = $this->repository->getLineaPorId((int) $linea['id'], $idEmpresa);
            $firma = fn (array $dets) => implode('|', array_map(
                fn ($d) => strtoupper((string) $d['tipo_documento']) . ':' . (int) $d['id_documento'] . ':' . number_format((float) $d['monto_aplicar'], 2, '.', ''),
                $dets
            ));
            if (!$actual || $actual['estado'] !== 'CONFIRMADO' || !empty($actual['id_ingreso_generado'])
                || $firma($this->repository->getDetallesPorLinea((int) $linea['id'], $idEmpresa)) !== $firma($detallesLinea)) {
                throw new LineaYaProcesadaException('La línea cambió o ya fue cobrada por otra sesión; recargue las líneas para ver su estado actual.');
            }

            $secRes = (new SecuencialService())->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos', $linea['fecha_movimiento']);

            $observaciones = $this->armarObservacionesIngreso($docs, $nombreCuenta, $montoTotal, $linea['referencia_banco'] ?? null)
                . '. ' . $this->armarObservacionesConciliacion($linea, $nombreCuenta, $montoTotal);

            $payload = [
                'id_empresa' => $idEmpresa,
                'id_establecimiento' => (int) $punto['id_establecimiento'],
                'id_punto_emision' => (int) $punto['id'],
                'id_cliente' => $idClienteUnico,
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
                'monto_total' => $montoTotal,
                'observaciones' => $observaciones,
                'recibo_de' => $reciboDe,
                'id_recibo_cliente' => $idClienteUnico,
                'detalles' => $detalles,
                'pagos' => [
                    [
                        'id_forma_cobro' => $idFormaPago,
                        'monto' => $montoTotal,
                        'referencia' => $linea['referencia_banco'] ?? null,
                        'tipo_operacion_bancaria' => 'TRANSFERENCIA',
                        'numero_cheque' => null,
                        'fecha_cobro' => null,
                    ],
                ],
            ];

            $idIngreso = $this->ingresoService->crear($payload);
            $this->repository->marcarLineaAplicada((int) $linea['id'], $idIngreso);
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

    /**
     * Descripción del depósito sin el sufijo "(parte i/n del depósito de $…)" que llevaban las
     * líneas repartidas en partes antes de que existiera el detalle por línea (datos históricos).
     */
    private function descripcionDeposito(string $descripcion): string
    {
        $texto = preg_replace('/ \(parte \d+\/\d+ del (depósito de \$[^)]+)\)/u', ' ($1)', $descripcion) ?? $descripcion;
        return str_replace(' — saldo sin asignar', '', $texto);
    }

    /**
     * Crea, en la misma carga, la línea por la diferencia de un pago parcial (lo recibido en
     * el banco fue mayor a lo asignado a los documentos ya cobrados) e intenta sugerirle otro
     * documento pendiente del MISMO cliente (el de la línea; ya identificado, no hace falta
     * volver a buscarlo por texto). Si no hay otro documento pendiente, queda sin sugerencia
     * para que el usuario decida manualmente (otro cliente, ignorarla, etc.).
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

        // El sufijo "(diferencia de pago parcial)" va al final porque buscarMovimientoRepetido()
        // excluye estas líneas por ese texto; id_linea_origen apunta a la línea cobrada (trazabilidad).
        return $this->insertarLineaSugerida($idCarga, $idEmpresa, $idUsuario, [
            'fecha_movimiento' => $lineaOriginal['fecha_movimiento'],
            'descripcion_original' => $this->descripcionDeposito((string) $lineaOriginal['descripcion_original']),
            'monto' => $diferencia,
            'referencia_banco' => $lineaOriginal['referencia_banco'] ?? null,
        ], $sugerencia, !empty($lineaOriginal['id_linea_origen']) ? (int) $lineaOriginal['id_linea_origen'] : (int) $lineaOriginal['id'], ' (diferencia de pago parcial)');
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

/**
 * La línea ya no está como se leyó al empezar a generar (otra sesión la cobró, la desconfirmó
 * o le cambió el documento): no es un error de la línea, simplemente no se cobra de nuevo.
 */
final class LineaYaProcesadaException extends \RuntimeException
{
}

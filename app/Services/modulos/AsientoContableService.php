<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\AsientoContableCabecera;
use App\models\AsientoContableDetalle;
use App\repositories\modulos\AsientoContableRepository;
use App\repositories\modulos\PeriodosContablesRepository;
use App\repositories\modulos\RolPagoRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Rules\modulos\PeriodosContablesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PeriodosContablesService;

class AsientoContableService
{
    /**
     * Orígenes que legítimamente tienen VARIOS asientos vivos para el MISMO documento, así
     * que el candado de guardarAsiento() no debe reconvertir su INSERT en UPDATE: colapsaría
     * en uno solo los asientos que deben ser varios.
     *
     * Hoy solo 'nomina': un rol se contabiliza con un asiento por empleado
     * (ver RolAsientoService y AsientoContableRepository::getIdsAsientosPorOrigen()). El resto
     * de los orígenes —compra, factura_venta, ingreso, egreso, retenciones, notas de crédito
     * y débito, liquidaciones, traspasos, consignaciones, importaciones, activos fijos,
     * declaraciones— tiene exactamente uno, y por eso sí se les aplica.
     *
     * Al agregar un módulo que contabilice varios asientos por documento hay que sumarlo acá
     * (y excluirlo del índice único de database/asientos_unico_por_documento.sql).
     */
    private const ORIGENES_MULTIASIENTO = ['nomina'];

    private AsientoContableCabecera $modelCabecera;
    private AsientoContableDetalle $modelDetalle;
    private PeriodosContablesService $periodosService;

    public function __construct(
        private AsientoContableRepository $repository,
        private AsientoContableRules $rules,
        private LogSistemaService $logService
    ) {
        $this->modelCabecera = new AsientoContableCabecera();
        $this->modelDetalle = new AsientoContableDetalle();

        // Servicio de períodos: ningún asiento (manual ni automático) puede tocar un
        // período contable cerrado. Mismo patrón de instanciación que EgresoService.
        $this->periodosService = new PeriodosContablesService(
            new PeriodosContablesRepository(),
            new PeriodosContablesRules(),
            $this->logService
        );
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function getDetalleAsiento(int $id, int $idEmpresa): array
    {
        return $this->repository->getDetalleAsiento($id, $idEmpresa);
    }

    public function getAsientoPorOrigen(string $modulo, int $idRef, int $idEmpresa): ?array
    {
        return $this->repository->getAsientoPorOrigen($modulo, $idRef, $idEmpresa);
    }

    /** @return int[] Todos los ids de asientos activos de un origen (puede haber varios). */
    public function getIdsAsientosPorOrigen(string $modulo, int $idRef, int $idEmpresa): array
    {
        return $this->repository->getIdsAsientosPorOrigen($modulo, $idRef, $idEmpresa);
    }

    /** @return int[] id_entidad distintos que cubre un asiento ya guardado. */
    public function getEntidadesDeAsiento(int $idAsiento, string $tipoEntidad): array
    {
        return $this->repository->getEntidadesDeAsiento($idAsiento, $tipoEntidad);
    }

    /** updated_at más reciente entre varias cabeceras (ver AsientoContableRepository::getMaxUpdatedAt). */
    public function getMaxUpdatedAt(array $ids): ?string
    {
        return $this->repository->getMaxUpdatedAt($ids);
    }

    /**
     * ¿El asiento sigue cuadrando con el documento que lo originó?
     *
     * Un asiento de factura de venta tiene que reflejar el total de esa factura, uno de compra
     * el total de la compra, etc. Se comprueba al editar el asiento a mano desde el módulo de
     * Asientos Contables (store/update); los asientos que generan los propios módulos no pasan
     * por acá — su cuadre lo vigila Auditoría Contable.
     *
     * No aplica (devuelve null) cuando: el asiento es de Diario/manual o de un origen cuyo total
     * no es comparable (nómina, consignaciones, retornos, cambios, migrados), cuando se guarda
     * como borrador —igual que el cuadre Debe/Haber, se exige recién al registrarlo— o cuando el
     * documento origen ya no existe.
     *
     * @return array{cuadra: bool, base: string, monto_asiento: float, total_documento: float,
     *               diferencia: float, sin_linea_cartera: bool, etiqueta: string,
     *               numero_documento: ?string, mensaje: string}|null
     */
    public function evaluarCuadreDocumento(array $cabeceraData, array $detallesData, int $idEmpresa): ?array
    {
        if (strtolower(trim((string) ($cabeceraData['estado'] ?? ''))) === 'borrador') {
            return null;
        }

        $moduloOrigen = (string) ($cabeceraData['modulo_origen'] ?? 'manual');
        $idRefOrigen  = (int) ($cabeceraData['id_referencia_origen'] ?? 0);

        $cfg = \App\Helpers\CuadreDocumentoAsiento::paraModulo($moduloOrigen);
        if ($cfg === null || $idRefOrigen <= 0) {
            return null;
        }

        // Documento sin importe (o en 0): no hay nada con qué comparar. Mismo criterio que el
        // chequeo de montos de Auditoría Contable, que solo mira documentos con total > 0.
        $totalDocumento = $this->repository->getTotalDocumentoOrigen($moduloOrigen, $idRefOrigen, $idEmpresa);
        if ($totalDocumento === null || $totalDocumento <= 0) {
            return null;
        }

        $cuentasCartera = $this->repository->getCuentasSlotCartera($idEmpresa, (array) ($cfg['slots'] ?? []));

        $res = $this->rules->evaluarCuadreDocumento($cfg, $totalDocumento, $detallesData, $cuentasCartera);
        $res['numero_documento'] = $this->numeroDocumentoOrigen($moduloOrigen, $idRefOrigen, $idEmpresa);
        $res['mensaje'] = $this->mensajeCuadreDocumento($res);

        return $res;
    }

    /**
     * Datos para que el modal muestre en vivo el cuadre contra el documento mientras se edita:
     * el importe del documento y con qué parte del asiento se compara. Devuelve null cuando ese
     * origen no se compara con su documento (ver evaluarCuadreDocumento).
     *
     * @return array{etiqueta: string, numero_documento: ?string, total_documento: float,
     *               lado: string, cuentas_cartera: int[], tolerancia: float}|null
     */
    public function getContextoCuadreDocumento(?string $moduloOrigen, int $idRefOrigen, int $idEmpresa): ?array
    {
        $cfg = \App\Helpers\CuadreDocumentoAsiento::paraModulo($moduloOrigen);
        if ($cfg === null || $idRefOrigen <= 0) {
            return null;
        }

        $totalDocumento = $this->repository->getTotalDocumentoOrigen($moduloOrigen, $idRefOrigen, $idEmpresa);
        if ($totalDocumento === null || $totalDocumento <= 0) {
            return null;
        }

        return [
            'etiqueta' => (string) $cfg['etiqueta'],
            'numero_documento' => $this->numeroDocumentoOrigen($moduloOrigen, $idRefOrigen, $idEmpresa),
            'total_documento' => $totalDocumento,
            'lado' => ($cfg['lado'] ?? 'debe') === 'haber' ? 'haber' : 'debe',
            'cuentas_cartera' => $this->repository->getCuentasSlotCartera($idEmpresa, (array) ($cfg['slots'] ?? [])),
            'tolerancia' => \App\Helpers\CuadreDocumentoAsiento::TOLERANCIA,
        ];
    }

    /**
     * Número del documento origen para nombrarlo en los mensajes de cuadre. Sale del mapa general
     * (DocumentoOrigenAsiento) y, para los módulos que no están ahí, de la expresión propia del
     * mapa de cuadre — hoy, la factura de reembolso.
     */
    private function numeroDocumentoOrigen(string $moduloOrigen, int $idRefOrigen, int $idEmpresa): ?string
    {
        $datosDoc = $this->repository->getDatosDocumentoOrigen($moduloOrigen, $idRefOrigen, $idEmpresa);
        return $datosDoc['numero_documento']
            ?? $this->repository->getNumeroDocumentoCuadre($moduloOrigen, $idRefOrigen, $idEmpresa);
    }

    /** Texto que ve el usuario cuando el asiento no cuadra con su documento. */
    private function mensajeCuadreDocumento(array $res): string
    {
        $doc = $res['etiqueta'] . (!empty($res['numero_documento']) ? ' ' . $res['numero_documento'] : '');

        if ($res['cuadra'] && !$res['sin_linea_cartera']) {
            return 'El asiento coincide con el total de ' . $doc . '.';
        }

        if ($res['sin_linea_cartera']) {
            return 'El asiento no tiene ninguna línea con la cuenta de cartera configurada para '
                 . $res['etiqueta'] . ', así que no se puede comprobar que refleje el total del documento ('
                 . number_format($res['total_documento'], 2) . ').';
        }

        $base = $res['base'] === 'cartera'
            ? 'La cartera del asiento (cuenta por cobrar/pagar)'
            : 'El total Debe del asiento';

        return $base . ' es ' . number_format($res['monto_asiento'], 2)
             . ' y el total de ' . $doc . ' es ' . number_format($res['total_documento'], 2)
             . ' · diferencia: ' . number_format(abs($res['diferencia']), 2) . '.';
    }

    /**
     * Deja constancia de que el usuario guardó a sabiendas un asiento que no cuadra con su
     * documento (el sistema avisa, pero no lo impide).
     */
    public function registrarDescuadreConfirmado(int $idAsiento, array $cuadre, int $idEmpresa, int $idUsuario): void
    {
        $this->logService->registrar(
            idUsuario: $idUsuario,
            idEmpresa: $idEmpresa,
            accion: 'Guardar Asiento Descuadrado vs Documento',
            tabla: 'asientos_contables_cabecera',
            idRegistro: $idAsiento,
            antes: null,
            despues: [
                'documento' => trim($cuadre['etiqueta'] . ' ' . ($cuadre['numero_documento'] ?? '')),
                'total_documento' => $cuadre['total_documento'],
                'monto_asiento' => $cuadre['monto_asiento'],
                'base_comparacion' => $cuadre['base'],
                'diferencia' => $cuadre['diferencia'],
                'confirmado_por_usuario' => true,
            ]
        );
    }

    public function guardarAsiento(array $cabeceraData, array $detallesData, int $idEmpresa, int $idUsuario): int
    {
        // Ordenar detalles: cuentas con 'debe' > 0 primero
        usort($detallesData, function($a, $b) {
            $debeA = (float)($a['debe'] ?? 0);
            $debeB = (float)($b['debe'] ?? 0);
            if ($debeA > 0 && $debeB <= 0) return -1;
            if ($debeB > 0 && $debeA <= 0) return 1;
            return 0;
        });

        // 1. Recalcular totales desde los detalles para seguridad
        $totalDebe = 0.00;
        $totalHaber = 0.00;
        foreach ($detallesData as $det) {
            $totalDebe += round((float)($det['debe'] ?? 0), 2);
            $totalHaber += round((float)($det['haber'] ?? 0), 2);
        }
        
        $cabeceraData['total_debe'] = round($totalDebe, 2);
        $cabeceraData['total_haber'] = round($totalHaber, 2);

        // 2. Validaciones
        $this->rules->validarCabecera($cabeceraData);
        $this->rules->validarDetalles($detallesData, ($cabeceraData['estado'] ?? '') === 'borrador');

        // 2b. Período contable: ni se crea ni se modifica un asiento dentro de un período
        // cerrado. En una edición se validan AMBAS fechas —la nueva y la que tiene hoy el
        // asiento— para que no se pueda sacar un asiento de un período cerrado cambiándole
        // la fecha, ni meterlo a uno cerrado.
        $idAsientoPrevio = (int) ($cabeceraData['id'] ?? 0);
        if (!empty($cabeceraData['fecha_asiento'])) {
            $this->periodosService->validarFechaPermitida(
                (string) $cabeceraData['fecha_asiento'],
                $idEmpresa,
                'No se puede registrar el asiento: la fecha ' . $cabeceraData['fecha_asiento']
                    . ' corresponde a un período contable cerrado.'
            );
        }
        if ($idAsientoPrevio > 0) {
            $previo = $this->repository->getDetalleAsiento($idAsientoPrevio, $idEmpresa);
            if (!empty($previo['fecha_asiento'])) {
                $this->periodosService->validarFechaPermitida(
                    (string) $previo['fecha_asiento'],
                    $idEmpresa,
                    'No se puede modificar el asiento: su fecha actual (' . $previo['fecha_asiento']
                        . ') está en un período contable cerrado.'
                );
            }
        }

        // 3. Iniciar Transacción (solo si no hay una activa — puede ser llamado desde otro servicio)
        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();

        try {
            $idAsiento = (int)($cabeceraData['id'] ?? 0);

            // ── Concurrencia (§8): el id que llega es solo una PISTA ──────────────────
            // Los módulos resuelven "¿ya existe asiento?" con getAsientoPorOrigen() antes
            // de llamar acá, y varios lo hacen FUERA de toda transacción (p. ej.
            // ComprasService genera el asiento después de commitear la compra). Dos
            // procesos simultáneos sobre el mismo documento —dos corridas del
            // sincronizador desde Libro Diario y Mayores, o el sincronizador mientras
            // alguien guarda— leen ambos "no tiene" y ambos insertan.
            //
            // Acá, ya dentro de la transacción, se toma el candado del documento y se
            // vuelve a resolver: el segundo en entrar espera, ve el asiento que dejó el
            // primero y lo ACTUALIZA en vez de duplicarlo. La decisión autoritativa de
            // insertar-vs-actualizar es esta, no la del llamador.
            $moduloOrigen = (string) ($cabeceraData['modulo_origen'] ?? 'manual');
            $idRefOrigen  = !empty($cabeceraData['id_referencia_origen'])
                ? (int) $cabeceraData['id_referencia_origen']
                : null;

            if ($idAsiento === 0
                && $idRefOrigen !== null
                && $moduloOrigen !== 'manual'
                && !in_array($moduloOrigen, self::ORIGENES_MULTIASIENTO, true)
            ) {
                $this->repository->lockAsientoOrigen($idEmpresa, $moduloOrigen, $idRefOrigen);

                $previo = $this->repository->getAsientoPorOrigen($moduloOrigen, $idRefOrigen, $idEmpresa);
                if ($previo !== null) {
                    $idAsiento = (int) $previo['id'];
                }
            }

            $isUpdate = $idAsiento > 0;

            // Generar número de comprobante si es nuevo. El candado es aparte del de arriba:
            // el contador es de la empresa+tipo, no del documento, así que dos documentos
            // distintos contabilizados a la vez se pisaban el número (MAX+1 sin bloquear).
            if (!$isUpdate && empty($cabeceraData['numero_comprobante'])) {
                $this->repository->lockNumeroComprobante($idEmpresa, (string) ($cabeceraData['tipo_comprobante'] ?? 'diario'));
                $cabeceraData['numero_comprobante'] = $this->repository->generarNumeroComprobante($idEmpresa, $cabeceraData['tipo_comprobante']);
            }

            // Preparar data de cabecera (normalizar tipo y estado a minúsculas para consistencia)
            $saveData = [
                'id_empresa' => $idEmpresa,
                'fecha_asiento' => $cabeceraData['fecha_asiento'],
                'tipo_comprobante' => strtolower(trim($cabeceraData['tipo_comprobante'] ?? 'diario')),
                'numero_comprobante' => $cabeceraData['numero_comprobante'],
                'concepto' => $cabeceraData['concepto'],
                'estado' => strtolower(trim($cabeceraData['estado'] ?? 'contabilizado')),
                'modulo_origen' => $cabeceraData['modulo_origen'] ?? 'manual',
                'id_referencia_origen' => !empty($cabeceraData['id_referencia_origen']) ? (int)$cabeceraData['id_referencia_origen'] : null,
                'total_debe' => $cabeceraData['total_debe'],
                'total_haber' => $cabeceraData['total_haber'],
                'observaciones' => $cabeceraData['observaciones'] ?? null,
            ];

            if ($isUpdate) {
                $saveData['updated_by'] = $idUsuario;
                $saveData['updated_at'] = date('Y-m-d H:i:s');
                $this->repository->updateCabecera($idAsiento, $saveData);
                
                // Auditar
                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Actualizar Asiento',
                    tabla: 'asientos_contables_cabecera',
                    idRegistro: $idAsiento,
                    antes: null,
                    despues: $saveData
                );
            } else {
                $saveData['created_by'] = $idUsuario;
                $idAsiento = $this->repository->insertCabecera($saveData);
                
                // Auditar
                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Crear Asiento',
                    tabla: 'asientos_contables_cabecera',
                    idRegistro: $idAsiento,
                    antes: null,
                    despues: $saveData
                );
            }

            // 4. Manejar Detalles
            if ($isUpdate) {
                $this->repository->deleteDetalles($idAsiento);
            }

            // Tercero y número de documento del origen: los módulos que arman sus líneas a mano
            // ya los mandan, pero las que vienen del builder/sincronizador llegan sin ellos y
            // los reportes (Mayores, Libro Diario) quedan con las columnas Tercero y Documento
            // en blanco. Se resuelven una sola vez por asiento y solo rellenan lo que falte.
            $docOrigen = $this->repository->getDatosDocumentoOrigen(
                $saveData['modulo_origen'],
                (int) ($saveData['id_referencia_origen'] ?? 0),
                $idEmpresa
            );

            foreach ($detallesData as $det) {
                $detData = [
                    'id_empresa' => $idEmpresa,
                    'id_asiento' => $idAsiento,
                    'id_cuenta_contable' => (int)$det['id_cuenta_contable'],
                    'id_centro_costo' => !empty($det['id_centro_costo']) ? (int)$det['id_centro_costo'] : null,
                    'id_proyecto' => !empty($det['id_proyecto']) ? (int)$det['id_proyecto'] : null,
                    'debe' => round((float)($det['debe'] ?? 0), 2),
                    'haber' => round((float)($det['haber'] ?? 0), 2),
                    'referencia_detalle' => $det['referencia_detalle'] ?? null,
                    'documento_referencia' => $det['documento_referencia'] ?? null,
                    'id_entidad' => !empty($det['id_entidad']) ? (int)$det['id_entidad'] : null,
                    'tipo_entidad' => $det['tipo_entidad'] ?? null,
                    'created_by' => $idUsuario,
                ];

                if ($docOrigen !== null) {
                    if (empty($detData['id_entidad']) && !empty($docOrigen['id_entidad'])) {
                        $detData['id_entidad'] = $docOrigen['id_entidad'];
                        $detData['tipo_entidad'] = $docOrigen['tipo_entidad'];
                    }
                    if (trim((string) $detData['documento_referencia']) === '' && !empty($docOrigen['numero_documento'])) {
                        $detData['documento_referencia'] = $docOrigen['numero_documento'];
                    }
                }

                $this->repository->insertDetalle($detData);
            }

            if ($managedTransaction) $pdo->commit();
            return $idAsiento;

        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function anular(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $asiento = $this->repository->getDetalleAsiento($idAsiento, $idEmpresa);
        if (!$asiento) {
            throw new \Exception('Asiento no encontrado.');
        }
        if ($asiento['estado'] === 'anulado') {
            throw new \Exception('El asiento ya se encuentra anulado.');
        }
        if (!empty($asiento['fecha_asiento'])) {
            $this->periodosService->validarFechaPermitida(
                (string) $asiento['fecha_asiento'],
                $idEmpresa,
                'No se puede anular el asiento: su fecha (' . $asiento['fecha_asiento']
                    . ') está en un período contable cerrado.'
            );
        }

        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();
        try {
            $update = [
                'estado' => 'anulado',
                'updated_by' => $idUsuario,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->repository->updateEstado($idAsiento, 'anulado', $idUsuario);

            // Si el asiento pertenece a una factura de venta, desvincular el campo id_asiento_contable
            if (
                ($asiento['tipo_comprobante'] ?? '') === 'ventas' &&
                ($asiento['modulo_origen'] ?? '') === 'factura_venta' &&
                !empty($asiento['id_referencia_origen'])
            ) {
                $this->repository->desvincularAsientoVenta((int)$asiento['id_referencia_origen']);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Factura Venta',
                    tabla: 'ventas_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Si el asiento pertenece a una retención en ventas, desvincular su id_asiento_contable
            if (
                ($asiento['modulo_origen'] ?? '') === 'retencion_venta' &&
                !empty($asiento['id_referencia_origen'])
            ) {
                $this->repository->desvincularAsientoRetencionVenta((int)$asiento['id_referencia_origen']);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Retención Venta',
                    tabla: 'retencion_venta_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Si el asiento pertenece a una retención en compras, desvincular su id_asiento_contable
            if (
                ($asiento['modulo_origen'] ?? '') === 'retencion_compra' &&
                !empty($asiento['id_referencia_origen'])
            ) {
                $this->repository->desvincularAsientoRetencionCompra((int)$asiento['id_referencia_origen']);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Retención Compra',
                    tabla: 'retencion_compra_cabecera',
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Si el asiento pertenece a un ingreso, egreso o traspaso, desvincular su id_asiento_contable.
            // Así, si el documento sigue activo, el control de Estados Financieros lo regenerará.
            $origenDoc = $asiento['modulo_origen'] ?? '';
            if (in_array($origenDoc, ['ingreso', 'egreso', 'traspaso'], true) && !empty($asiento['id_referencia_origen'])) {
                $tablaOrigen = match ($origenDoc) {
                    'ingreso'  => 'ingresos_cabecera',
                    'egreso'   => 'egresos_cabecera',
                    'traspaso' => 'traspasos_cabecera',
                };
                match ($origenDoc) {
                    'ingreso'  => $this->repository->desvincularAsientoIngreso((int)$asiento['id_referencia_origen']),
                    'egreso'   => $this->repository->desvincularAsientoEgreso((int)$asiento['id_referencia_origen']),
                    'traspaso' => $this->repository->desvincularAsientoTraspaso((int)$asiento['id_referencia_origen']),
                };

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de ' . ucfirst($origenDoc),
                    tabla: $tablaOrigen,
                    idRegistro: (int)$asiento['id_referencia_origen'],
                    antes: ['id_asiento_contable' => $idAsiento],
                    despues: ['id_asiento_contable' => null]
                );
            }

            // Compras, Recibos de Venta, Notas de Crédito/Débito, Liquidaciones de Compra,
            // Importaciones, Consignaciones y sus derivados (Retorno, Cambio de Producto):
            // desvincular su columna de asiento (mismo hueco que tenía nómina — ver el bloque de
            // abajo y egresos-compras-cxp-contrapartida-faltante). Se reutiliza el mapa fijo
            // DocumentoOrigenAsiento (tabla + columna) en vez de repetir un método por módulo.
            $origenesConMapaGenerico = [
                'compra', 'recibo_venta', 'nota_credito', 'nota_debito',
                'liquidacion_compra', 'importacion', 'consignacion_venta', 'retorno_cv', 'cambio_producto_cv',
                'FACTURACION_CV',
            ];
            if (in_array($origenDoc, $origenesConMapaGenerico, true) && !empty($asiento['id_referencia_origen'])) {
                $doc = \App\Helpers\DocumentoOrigenAsiento::paraModulo($origenDoc);
                if ($doc !== null) {
                    $this->repository->desvincularAsientoGenerico($doc['tabla'], $doc['col_asiento'], (int) $asiento['id_referencia_origen']);

                    $this->logService->registrar(
                        idUsuario: $idUsuario,
                        idEmpresa: $idEmpresa,
                        accion: 'Desvincular Asiento de ' . ($doc['etiqueta'] ?? ucfirst($origenDoc)),
                        tabla: $doc['tabla'],
                        idRegistro: (int) $asiento['id_referencia_origen'],
                        antes: [$doc['col_asiento'] => $idAsiento],
                        despues: [$doc['col_asiento'] => null]
                    );
                }
            }

            // Si el asiento pertenece a un rol de nómina, desvincular rol_cabecera.id_asiento.
            // Un rol MENSUAL puede tener VARIOS asientos activos a la vez (modo por empleado —
            // ver RolAsientoService::contabilizar), así que se desvincula sin condicionar a que
            // este id sea justo el que tenía guardado: cualquier anulación de un asiento de este
            // rol debe dejarlo marcado como pendiente en SincronizadorAsientosService (que solo
            // mira si id_asiento es NULL o quedó desactualizado); al recontabilizar, el rol
            // reconcilia solo qué otros asientos siguen vigentes (no los duplica).
            if (($asiento['modulo_origen'] ?? '') === 'nomina' && !empty($asiento['id_referencia_origen'])) {
                (new RolPagoRepository())->setIdAsiento((int) $asiento['id_referencia_origen'], null);

                $this->logService->registrar(
                    idUsuario: $idUsuario,
                    idEmpresa: $idEmpresa,
                    accion: 'Desvincular Asiento de Rol de Pagos',
                    tabla: 'rol_cabecera',
                    idRegistro: (int) $asiento['id_referencia_origen'],
                    antes: ['id_asiento' => $idAsiento],
                    despues: ['id_asiento' => null]
                );
            }

            $this->logService->registrar(
                idUsuario: $idUsuario,
                idEmpresa: $idEmpresa,
                accion: 'Anular Asiento',
                tabla: 'asientos_contables_cabecera',
                idRegistro: $idAsiento,
                antes: ['estado' => $asiento['estado']],
                despues: $update
            );

            if ($managedTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Restablece un asiento anulado a 'contabilizado'. Solo permitido para asientos de
     * tipo Diario (los demás se regeneran desde su documento de origen, no se reactivan a mano).
     */
    public function restablecer(int $idAsiento, int $idEmpresa, int $idUsuario): void
    {
        $asiento = $this->repository->getDetalleAsiento($idAsiento, $idEmpresa);
        if (!$asiento) {
            throw new \Exception('Asiento no encontrado.');
        }
        if (($asiento['estado'] ?? '') !== 'anulado') {
            throw new \Exception('Solo se puede restablecer un asiento que esté anulado.');
        }
        if (strtolower(trim($asiento['tipo_comprobante'] ?? '')) !== 'diario') {
            throw new \Exception('Solo los asientos de tipo Diario se pueden restablecer a contabilizado.');
        }
        if (!empty($asiento['fecha_asiento'])) {
            $this->periodosService->validarFechaPermitida(
                (string) $asiento['fecha_asiento'],
                $idEmpresa,
                'No se puede restablecer el asiento: su fecha (' . $asiento['fecha_asiento']
                    . ') está en un período contable cerrado.'
            );
        }

        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();
        try {
            $this->repository->updateEstado($idAsiento, 'contabilizado', $idUsuario);

            $this->logService->registrar(
                idUsuario: $idUsuario,
                idEmpresa: $idEmpresa,
                accion: 'Restablecer Asiento',
                tabla: 'asientos_contables_cabecera',
                idRegistro: $idAsiento,
                antes: ['estado' => 'anulado'],
                despues: ['estado' => 'contabilizado']
            );

            if ($managedTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

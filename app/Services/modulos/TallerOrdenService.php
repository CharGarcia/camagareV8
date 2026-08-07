<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\Booleano;
use App\repositories\modulos\TallerDepartamentoRepository;
use App\repositories\modulos\TallerOrdenRepository;
use App\Rules\modulos\TallerOrdenRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Lógica de negocio del módulo Taller Mecánico.
 *
 * El ciclo de una orden de trabajo:
 *   1. RECEPCIÓN     — el asesor registra el vehículo, el checklist de ingreso,
 *                      las fotos y el motivo por el que el cliente lo trae.
 *   2. DIAGNÓSTICO   — el técnico revisa y sugiere repuestos y mano de obra.
 *   3. APROBACIÓN    — el cliente aprueba el presupuesto (obligatorio: sin esto
 *                      ningún departamento puede ejecutar trabajos).
 *   4. DEPARTAMENTOS — el vehículo recorre las estaciones; cada operario, desde
 *                      su tablet, registra el trabajo y lo que consumió.
 *   5. ENTREGA       — informe técnico, garantía y próximo mantenimiento.
 *   6. FACTURACIÓN   — Factura electrónica SRI o Recibo de venta.
 *
 * Inventario: el repuesto sale de bodega cuando la línea queda APROBADA
 * (referencia 'taller_orden' en el kardex). Al facturar se revierte todo y el
 * documento hace su propia salida, igual que el módulo Car-Wash.
 *
 * La orden no genera asiento contable propio: lo produce el documento de venta.
 */
class TallerOrdenService
{
    private TallerOrdenRepository $repository;
    private TallerDepartamentoRepository $departamentoRepository;
    private TallerOrdenRules $rules;
    private LogSistemaService $logService;
    private ?InventarioService $inventarioService = null;

    /** Tipo de referencia en el kardex para los movimientos de la orden. */
    private const REF_TIPO = 'taller_orden';

    /** Tipo de documento del secuencial, tal como se registra en Empresa. */
    private const TIPO_SECUENCIAL = 'Ordenes de taller';

    /** Carpeta bajo public/ donde se guardan las fotos de las órdenes. */
    private const DIR_FOTOS = 'uploads/taller/';

    /** Tipos de línea que descuentan inventario (la mano de obra no es stock). */
    private const TIPOS_CONSUMEN_STOCK = ['repuesto', 'insumo'];

    public function __construct(
        TallerOrdenRepository $repository,
        TallerDepartamentoRepository $departamentoRepository,
        TallerOrdenRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository             = $repository;
        $this->departamentoRepository = $departamentoRepository;
        $this->rules                  = $rules;
        $this->logService             = $logService;
    }

    private function getInventarioService(): InventarioService
    {
        if ($this->inventarioService === null) {
            $this->inventarioService = new InventarioService(
                new \App\repositories\modulos\InventarioRepository(),
                $this->logService
            );
        }
        return $this->inventarioService;
    }

    // ═══ LECTURAS ════════════════════════════════════════════════════════════

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Tablero del jefe de taller: órdenes activas agrupadas por departamento. */
    public function getTablero(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $departamentos = $this->departamentoRepository->getActivos($idEmpresa);
        $ordenes       = $this->repository->getTablero($idEmpresa, $idUsuarioFiltro);

        $columnas = [];
        foreach ($departamentos as $d) {
            $columnas[(int) $d['id']] = [
                'departamento' => $d,
                'ordenes'      => [],
            ];
        }
        // Las órdenes que aún no fueron enviadas a ningún departamento (recién
        // recibidas) se muestran aparte para que nadie las pierda de vista.
        $sinDepartamento = [];

        foreach ($ordenes as $o) {
            $idDep = (int) ($o['id_departamento_actual'] ?? 0);
            if ($idDep > 0 && isset($columnas[$idDep])) {
                $columnas[$idDep]['ordenes'][] = $o;
            } else {
                $sinDepartamento[] = $o;
            }
        }

        return [
            'columnas'         => array_values($columnas),
            'sin_departamento' => $sinDepartamento,
            'total'            => count($ordenes),
        ];
    }

    /** Lo que ve la tablet de un departamento. */
    public function getOrdenesPorDepartamento(int $idEmpresa, int $idDepartamento): array
    {
        return $this->repository->getOrdenesPorDepartamento($idEmpresa, $idDepartamento);
    }

    public function getDepartamentos(int $idEmpresa): array
    {
        return $this->departamentoRepository->getActivos($idEmpresa);
    }

    public function buscarVehiculos(int $idEmpresa, string $q): array
    {
        return $this->repository->buscarVehiculos($idEmpresa, $q);
    }

    /** Orden completa: cabecera + líneas + etapas + bitácora + checklist + fotos. */
    public function getDetalleCompleto(int $id, int $idEmpresa, bool $conHistorial = true): ?array
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) return null;

        $cab['detalles']       = $this->repository->getDetalles($id, $idEmpresa);
        $cab['etapas']         = $this->repository->getEtapas($id, $idEmpresa);
        $cab['bitacora']       = $this->repository->getBitacora($id, $idEmpresa);
        $cab['checklist']      = $this->repository->getChecklist($id, $idEmpresa);
        $cab['fotos']          = $this->repository->getFotos($id, $idEmpresa);
        $cab['info_adicional'] = $this->decodeInfoAdicional($cab['info_adicional'] ?? null);
        $cab['historial_vehiculo'] = $conHistorial
            ? $this->repository->getHistorialVehiculo((int) $cab['id_vehiculo'], $idEmpresa, $id)
            : [];

        return $cab;
    }

    /** Detalle acotado a un departamento (lo que necesita la tablet del operario). */
    public function getDetalleDepartamento(int $id, int $idEmpresa, int $idDepartamento): ?array
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) return null;

        $cab['detalles']  = $this->repository->getDetalles($id, $idEmpresa, $idDepartamento);
        $cab['etapa']     = $this->repository->findEtapaAbierta($id, $idDepartamento, $idEmpresa);
        $cab['checklist'] = $this->repository->getChecklist($id, $idEmpresa);
        $cab['fotos']     = $this->repository->getFotos($id, $idEmpresa);
        return $cab;
    }

    // ═══ RECEPCIÓN ═══════════════════════════════════════════════════════════

    /**
     * Registra el ingreso del vehículo al taller. No exige líneas: el
     * diagnóstico y el presupuesto vienen después.
     */
    public function crearRecepcion(array $data): int
    {
        $this->rules->validarRecepcion($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $empresaConfig = $data['empresa_config'] ?? [];
        $tipoAmbiente  = (string) ($empresaConfig['tipo_ambiente'] ?? '1');
        $idEstab       = (int) ($data['id_establecimiento'] ?? 0);
        $idPunto       = (int) ($data['id_punto_emision'] ?? 0);
        $secuencial    = str_pad((string) $data['secuencial'], 9, '0', STR_PAD_LEFT);

        // El secuencial se configura en Empresa → Secuenciales como el de
        // cualquier otro documento. Se comprueba también aquí y no solo en el
        // navegador, para que ninguna orden nazca con una numeración inventada.
        $this->validarSecuencialConfigurado($idPunto);

        if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial)) {
            throw new Exception('El secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }

        $numeroOrden = ($data['establecimiento'] ?? '') . '-' . ($data['punto_emision'] ?? '') . '-' . $secuencial;

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $cabecera = [
                'id_empresa'             => $idEmpresa,
                'id_establecimiento'     => $idEstab ?: null,
                'id_punto_emision'       => $idPunto ?: null,
                'establecimiento'        => $data['establecimiento'] ?? null,
                'punto_emision'          => $data['punto_emision'] ?? null,
                'secuencial'             => $secuencial,
                'tipo_ambiente'          => $tipoAmbiente,
                'numero_orden'           => $numeroOrden,
                'id_vehiculo'            => (int) $data['id_vehiculo'],
                'id_cliente'             => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'              => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'                  => $data['placa'] ?? null,
                'marca'                  => $data['marca'] ?? null,
                'modelo'                 => $data['modelo'] ?? null,
                'anio'                   => $data['anio'] ?? null,
                'color'                  => $data['color'] ?? null,
                'chasis'                 => $data['chasis'] ?? null,
                'motor'                  => $data['motor'] ?? null,
                'kilometraje'            => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible'      => $data['nivel_combustible'] ?? null,
                'nombre_usuario'         => $data['nombre_usuario'] ?? null,
                'telefono_contacto'      => $data['telefono_contacto'] ?? null,
                'correo_contacto'        => $data['correo_contacto'] ?? null,
                'id_asesor'              => $idUsuario,
                'id_empleado_asesor'     => empty($data['id_empleado_asesor']) ? null : (int) $data['id_empleado_asesor'],
                'id_empleado_jefe'       => empty($data['id_empleado_jefe']) ? null : (int) $data['id_empleado_jefe'],
                'fecha_ingreso'          => $data['fecha_ingreso'],
                'fecha_estimada_entrega' => empty($data['fecha_estimada_entrega']) ? null : $data['fecha_estimada_entrega'],
                'tipo_servicio'          => $data['tipo_servicio'] ?? 'correctivo',
                'prioridad'              => $data['prioridad'] ?? 'normal',
                'estado'                 => 'recepcion',
                'motivo_ingreso'         => $data['motivo_ingreso'] ?? null,
                'observaciones'          => $data['observaciones'] ?? null,
                'es_siniestro'           => Booleano::sql($data['es_siniestro'] ?? false),
                'aseguradora'            => $data['aseguradora'] ?? null,
                'numero_siniestro'       => $data['numero_siniestro'] ?? null,
                'deducible'              => (float) ($data['deducible'] ?? 0),
                'ajustador'              => $data['ajustador'] ?? null,
                'garantia_dias'          => (int) ($data['garantia_dias'] ?? 0),
                'garantia_km'            => (int) ($data['garantia_km'] ?? 0),
                'proxima_cita'           => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'info_adicional'         => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'created_by'             => $idUsuario,
                'updated_by'             => $idUsuario,
            ];
            $idOrden = $this->repository->create($cabecera);

            // Checklist de recepción (evidencia congelada del estado de ingreso).
            $this->guardarChecklist($idOrden, $idEmpresa, $data['checklist'] ?? []);

            // Si el asesor ya sabe a qué departamento va, se crea la primera etapa.
            $idDepInicial = (int) ($data['id_departamento'] ?? 0);
            if ($idDepInicial > 0) {
                $this->crearEtapa($idOrden, $idEmpresa, $idDepInicial, $idUsuario, null);
                $this->repository->updateDepartamentoActual($idOrden, $idEmpresa, $idDepInicial, $idUsuario);
            }

            $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, $idDepInicial ?: null, 'ingreso',
                'Ingreso del vehículo', trim((string) ($data['motivo_ingreso'] ?? '')));

            // Mantener actualizado el kilometraje del vehículo en su ficha.
            if (($data['kilometraje'] ?? '') !== '') {
                $this->actualizarKilometrajeVehiculo((int) $data['id_vehiculo'], $idEmpresa, (int) $data['kilometraje']);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR_ORDEN_TALLER', 'taller_ordenes', $idOrden, null, $cabecera);

            $db->commit();
            return $idOrden;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Actualiza los datos de recepción / cabecera de una orden abierta. */
    public function actualizarRecepcion(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validarRecepcion($data);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) {
            throw new Exception("Orden no encontrada.");
        }
        $this->rules->validarOrdenEditable($cab);

        $idUsuario = (int) $data['id_usuario'];
        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->updateCabecera($id, $idEmpresa, [
                'id_vehiculo'            => (int) $data['id_vehiculo'],
                'id_cliente'             => empty($data['id_cliente']) ? null : (int) $data['id_cliente'],
                'id_bodega'              => empty($data['id_bodega']) ? null : (int) $data['id_bodega'],
                'placa'                  => $data['placa'] ?? null,
                'marca'                  => $data['marca'] ?? null,
                'modelo'                 => $data['modelo'] ?? null,
                'anio'                   => $data['anio'] ?? null,
                'color'                  => $data['color'] ?? null,
                'chasis'                 => $data['chasis'] ?? null,
                'motor'                  => $data['motor'] ?? null,
                'kilometraje'            => ($data['kilometraje'] ?? '') === '' ? null : (int) $data['kilometraje'],
                'nivel_combustible'      => $data['nivel_combustible'] ?? null,
                'nombre_usuario'         => $data['nombre_usuario'] ?? null,
                'telefono_contacto'      => $data['telefono_contacto'] ?? null,
                'correo_contacto'        => $data['correo_contacto'] ?? null,
                'id_empleado_asesor'     => empty($data['id_empleado_asesor']) ? null : (int) $data['id_empleado_asesor'],
                'id_empleado_jefe'       => empty($data['id_empleado_jefe']) ? null : (int) $data['id_empleado_jefe'],
                'fecha_ingreso'          => $data['fecha_ingreso'],
                'fecha_estimada_entrega' => empty($data['fecha_estimada_entrega']) ? null : $data['fecha_estimada_entrega'],
                'tipo_servicio'          => $data['tipo_servicio'] ?? 'correctivo',
                'prioridad'              => $data['prioridad'] ?? 'normal',
                'motivo_ingreso'         => $data['motivo_ingreso'] ?? null,
                'diagnostico_texto'      => $data['diagnostico_texto'] ?? null,
                'observaciones'          => $data['observaciones'] ?? null,
                'recomendaciones'        => $data['recomendaciones'] ?? null,
                'es_siniestro'           => Booleano::sql($data['es_siniestro'] ?? false),
                'aseguradora'            => $data['aseguradora'] ?? null,
                'numero_siniestro'       => $data['numero_siniestro'] ?? null,
                'deducible'              => (float) ($data['deducible'] ?? 0),
                'ajustador'              => $data['ajustador'] ?? null,
                'garantia_dias'          => (int) ($data['garantia_dias'] ?? 0),
                'garantia_km'            => (int) ($data['garantia_km'] ?? 0),
                'proximo_mantenimiento_km' => ($data['proximo_mantenimiento_km'] ?? '') === '' ? null : (int) $data['proximo_mantenimiento_km'],
                'proxima_cita'           => empty($data['proxima_cita']) ? null : $data['proxima_cita'],
                'info_adicional'         => $this->encodeInfoAdicional($data['info_adicional'] ?? []),
                'updated_by'             => $idUsuario,
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);

            if (isset($data['checklist']) && is_array($data['checklist'])) {
                $this->repository->limpiarChecklist($id, $idEmpresa);
                $this->guardarChecklist($id, $idEmpresa, $data['checklist']);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_ORDEN_TALLER', 'taller_ordenes', $id, $cab, $data);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Guarda el diagnóstico técnico (lo que encontró el taller). */
    public function guardarDiagnostico(int $id, int $idEmpresa, int $idUsuario, string $diagnostico, ?int $idDepartamento = null): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        if (trim($diagnostico) === '') {
            throw new Exception("El diagnóstico no puede estar vacío.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $update = [
                'diagnostico_texto' => $diagnostico,
                'updated_by'        => $idUsuario,
                'updated_at'        => date('Y-m-d H:i:s'),
            ];
            // El diagnóstico mueve la orden hacia el presupuesto.
            if (in_array((string) $cab['estado'], ['recepcion', 'diagnostico'], true)) {
                $update['estado'] = 'presupuesto';
            }
            $this->repository->updateCabecera($id, $idEmpresa, $update);

            $this->registrarBitacora($id, $idEmpresa, $idUsuario, $idDepartamento, 'diagnostico',
                'Diagnóstico técnico', $diagnostico);

            $this->logService->registrar($idUsuario, $idEmpresa, 'DIAGNOSTICO_ORDEN_TALLER', 'taller_ordenes', $id,
                ['diagnostico_texto' => $cab['diagnostico_texto'] ?? null], ['diagnostico_texto' => $diagnostico]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ═══ LÍNEAS (repuestos y mano de obra) ═══════════════════════════════════

    /**
     * Agrega un repuesto o un trabajo a la orden desde un departamento.
     *
     * Si el presupuesto ya está aprobado, la línea nace aprobada y descuenta
     * stock de inmediato. Si todavía no lo está, queda como 'sugerida' y no
     * toca el inventario hasta que el cliente apruebe.
     */
    public function agregarLinea(int $idOrden, int $idEmpresa, int $idUsuario, array $d): int
    {
        $this->rules->validarLinea($d);

        $cab = $this->repository->find($idOrden, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $calc  = $this->calcularLinea($d, $idEmpresa);
            $aprob = Booleano::es($cab['aprobado'] ?? false);

            $idLinea = $this->repository->insertDetalle([
                'id_orden'            => $idOrden,
                'id_empresa'          => $idEmpresa,
                'id_departamento'     => empty($d['id_departamento']) ? null : (int) $d['id_departamento'],
                'id_usuario_registro' => $idUsuario,
                'id_empleado_tecnico' => empty($d['id_empleado_tecnico']) ? null : (int) $d['id_empleado_tecnico'],
                'tipo_linea'          => $d['tipo_linea'] ?? 'repuesto',
                'id_producto'         => empty($d['id_producto']) ? null : (int) $d['id_producto'],
                'es_libre'            => empty($d['id_producto']),
                'descripcion'         => trim((string) $d['descripcion']),
                'id_bodega'           => empty($d['id_bodega']) ? ($cab['id_bodega'] ?? null) : (int) $d['id_bodega'],
                'cantidad'            => $calc['cantidad'],
                'horas'               => (float) ($d['horas'] ?? 0),
                'precio_unitario'     => $calc['precio_unitario'],
                'costo_unitario'      => $calc['costo_unitario'],
                'descuento'           => $calc['descuento'],
                'porcentaje_iva'      => $calc['porcentaje_iva'],
                'valor_iva'           => $calc['valor_iva'],
                'total_linea'         => $calc['total_linea'],
                'id_tarifa_iva'       => $calc['id_tarifa_iva'],
                'estado_linea'        => $aprob ? 'aprobada' : 'sugerida',
                'facturable'          => Booleano::es($d['provisto_cliente'] ?? false) ? false : (!isset($d['facturable']) || Booleano::es($d['facturable'])),
                'provisto_cliente'    => Booleano::es($d['provisto_cliente'] ?? false),
                'observacion'         => $d['observacion'] ?? null,
            ]);

            // Con el presupuesto aprobado, el repuesto sale de bodega ya mismo.
            if ($aprob) {
                $linea = $this->repository->findDetalle($idLinea, $idEmpresa);
                if ($linea) {
                    $this->aplicarInventarioLinea($linea, $cab, $idEmpresa, $idUsuario);
                }
            }

            $this->recalcularTotales($idOrden, $idEmpresa);
            $this->marcarEnProceso($cab, $idEmpresa, $idUsuario);

            $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario,
                empty($d['id_departamento']) ? null : (int) $d['id_departamento'],
                'linea_agregada',
                $this->etiquetaTipoLinea((string) ($d['tipo_linea'] ?? 'repuesto')) . ': ' . trim((string) $d['descripcion']),
                'Cantidad: ' . $calc['cantidad'] . ' — Total: ' . number_format($calc['total_linea'], 2)
            );

            $this->logService->registrar($idUsuario, $idEmpresa, 'AGREGAR_LINEA_TALLER', 'taller_ordenes_detalle', $idLinea, null, $d);

            $db->commit();
            return $idLinea;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizarLinea(int $idLinea, int $idEmpresa, int $idUsuario, array $d): void
    {
        $this->rules->validarLinea($d);

        $linea = $this->repository->findDetalle($idLinea, $idEmpresa);
        if (!$linea) throw new Exception("Línea no encontrada.");

        $cab = $this->repository->find((int) $linea['id_orden'], $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // El stock se recalcula: se devuelve lo anterior y se vuelve a sacar.
            $this->revertirInventarioLinea($linea, $idEmpresa, $idUsuario);

            $calc = $this->calcularLinea($d, $idEmpresa, $linea);
            $this->repository->updateDetalle($idLinea, $idEmpresa, [
                'descripcion'         => trim((string) $d['descripcion']),
                'cantidad'            => $calc['cantidad'],
                'horas'               => (float) ($d['horas'] ?? 0),
                'precio_unitario'     => $calc['precio_unitario'],
                'descuento'           => $calc['descuento'],
                'porcentaje_iva'      => $calc['porcentaje_iva'],
                'valor_iva'           => $calc['valor_iva'],
                'total_linea'         => $calc['total_linea'],
                'id_tarifa_iva'       => $calc['id_tarifa_iva'],
                'id_bodega'           => empty($d['id_bodega']) ? ($linea['id_bodega'] ?? null) : (int) $d['id_bodega'],
                'id_empleado_tecnico' => empty($d['id_empleado_tecnico']) ? null : (int) $d['id_empleado_tecnico'],
                'facturable'          => Booleano::es($d['provisto_cliente'] ?? false) ? false : (!isset($d['facturable']) || Booleano::es($d['facturable'])),
                'provisto_cliente'    => Booleano::es($d['provisto_cliente'] ?? false),
                'observacion'         => $d['observacion'] ?? null,
                'id_usuario'          => $idUsuario,
            ]);

            $actualizada = $this->repository->findDetalle($idLinea, $idEmpresa);
            if ($actualizada && in_array((string) $actualizada['estado_linea'], ['aprobada', 'ejecutada'], true)) {
                $this->aplicarInventarioLinea($actualizada, $cab, $idEmpresa, $idUsuario);
            }

            $this->recalcularTotales((int) $linea['id_orden'], $idEmpresa);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_LINEA_TALLER', 'taller_ordenes_detalle', $idLinea, $linea, $d);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminarLinea(int $idLinea, int $idEmpresa, int $idUsuario): void
    {
        $linea = $this->repository->findDetalle($idLinea, $idEmpresa);
        if (!$linea) throw new Exception("Línea no encontrada.");

        $cab = $this->repository->find((int) $linea['id_orden'], $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->revertirInventarioLinea($linea, $idEmpresa, $idUsuario);
            $this->repository->eliminarDetalle($idLinea, $idEmpresa, $idUsuario);
            $this->recalcularTotales((int) $linea['id_orden'], $idEmpresa);

            $this->registrarBitacora((int) $linea['id_orden'], $idEmpresa, $idUsuario,
                empty($linea['id_departamento']) ? null : (int) $linea['id_departamento'],
                'linea_eliminada', 'Se quitó: ' . ($linea['descripcion'] ?? ''), null);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_LINEA_TALLER', 'taller_ordenes_detalle', $idLinea, $linea, null);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el estado de una línea: aprobar, rechazar o marcar como ejecutada.
     * Aprobar descuenta stock; rechazar lo devuelve.
     */
    public function cambiarEstadoLinea(int $idLinea, int $idEmpresa, int $idUsuario, string $estado, ?string $motivo = null): void
    {
        $this->rules->validarEstadoLinea($estado);

        $linea = $this->repository->findDetalle($idLinea, $idEmpresa);
        if (!$linea) throw new Exception("Línea no encontrada.");

        $cab = $this->repository->find((int) $linea['id_orden'], $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        if ($estado === 'ejecutada') {
            $this->rules->validarEjecucionLinea($cab, $linea);
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->updateEstadoLinea($idLinea, $idEmpresa, $estado, $idUsuario, $estado === 'rechazada' ? $motivo : null);

            $refrescada = $this->repository->findDetalle($idLinea, $idEmpresa);
            if ($refrescada) {
                if (in_array($estado, ['aprobada', 'ejecutada'], true)) {
                    $this->aplicarInventarioLinea($refrescada, $cab, $idEmpresa, $idUsuario);
                } else {
                    $this->revertirInventarioLinea($refrescada, $idEmpresa, $idUsuario);
                }
            }

            $this->recalcularTotales((int) $linea['id_orden'], $idEmpresa);

            $this->registrarBitacora((int) $linea['id_orden'], $idEmpresa, $idUsuario,
                empty($linea['id_departamento']) ? null : (int) $linea['id_departamento'],
                $estado === 'rechazada' ? 'rechazo' : 'aprobacion',
                ucfirst($estado) . ': ' . ($linea['descripcion'] ?? ''), $motivo);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ESTADO_LINEA_TALLER', 'taller_ordenes_detalle', $idLinea,
                ['estado_linea' => $linea['estado_linea']], ['estado_linea' => $estado, 'motivo' => $motivo]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ═══ APROBACIÓN DEL PRESUPUESTO (obligatoria) ════════════════════════════

    /**
     * Registra la aprobación del cliente. Deja constancia de quién aprobó,
     * cuándo y por qué medio, aprueba las líneas pendientes y descuenta de
     * bodega los repuestos aprobados.
     */
    public function aprobarPresupuesto(int $id, int $idEmpresa, int $idUsuario, array $d): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $this->rules->validarAprobacion($cab, $d);

        $detalles = $this->repository->getDetalles($id, $idEmpresa);
        if (empty($detalles)) {
            throw new Exception("La orden no tiene repuestos ni trabajos que aprobar. Registre primero el presupuesto.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->registrarAprobacion($id, $idEmpresa, [
                'aprobado_por'         => trim((string) $d['aprobado_por']),
                'aprobado_medio'       => (string) $d['aprobado_medio'],
                'aprobado_observacion' => $d['aprobado_observacion'] ?? null,
                'id_usuario'           => $idUsuario,
            ]);
            $this->repository->aprobarLineasPendientes($id, $idEmpresa, $idUsuario);

            // Ahora que están aprobadas, los repuestos salen de bodega.
            $cabAprobada = $this->repository->find($id, $idEmpresa);
            foreach ($this->repository->getDetalles($id, $idEmpresa) as $linea) {
                if (in_array((string) $linea['estado_linea'], ['aprobada', 'ejecutada'], true)) {
                    $this->aplicarInventarioLinea($linea, $cabAprobada ?? $cab, $idEmpresa, $idUsuario);
                }
            }

            $this->recalcularTotales($id, $idEmpresa);

            $this->registrarBitacora($id, $idEmpresa, $idUsuario, null, 'aprobacion',
                'Presupuesto aprobado por ' . trim((string) $d['aprobado_por']),
                'Medio: ' . $d['aprobado_medio'] . ($d['aprobado_observacion'] ?? '' ? ' — ' . $d['aprobado_observacion'] : ''));

            $this->logService->registrar($idUsuario, $idEmpresa, 'APROBAR_PRESUPUESTO_TALLER', 'taller_ordenes', $id,
                ['aprobado' => false], $d);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ═══ FLUJO POR DEPARTAMENTOS ═════════════════════════════════════════════

    /**
     * Envía el vehículo a un departamento: cierra la etapa abierta del
     * departamento donde estaba (si el operario no la cerró) y abre la nueva.
     */
    public function enviarADepartamento(int $id, int $idEmpresa, int $idUsuario, int $idDepartamento, ?array $cierre = null): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $this->rules->validarEnvioDepartamento($cab, $idDepartamento);

        $departamento = $this->departamentoRepository->find($idDepartamento, $idEmpresa);
        if (!$departamento) {
            throw new Exception("El departamento seleccionado no existe.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Cerrar la etapa del departamento actual, si sigue abierta.
            $depActual = (int) ($cab['id_departamento_actual'] ?? 0);
            if ($depActual > 0 && $depActual !== $idDepartamento) {
                $etapaAbierta = $this->repository->findEtapaAbierta($id, $depActual, $idEmpresa);
                if ($etapaAbierta) {
                    $this->repository->terminarEtapa((int) $etapaAbierta['id'], $idEmpresa, $idUsuario, [
                        'trabajo_realizado'       => $cierre['trabajo_realizado'] ?? ($etapaAbierta['trabajo_realizado'] ?? 'Enviado al siguiente departamento.'),
                        'observaciones'           => $cierre['observaciones'] ?? ($etapaAbierta['observaciones'] ?? null),
                        'id_empleado_responsable' => empty($cierre['id_empleado_responsable']) ? null : (int) $cierre['id_empleado_responsable'],
                    ]);
                }
            }

            // Abrir la etapa del departamento destino (o reutilizar la abierta).
            $etapaDestino = $this->repository->findEtapaAbierta($id, $idDepartamento, $idEmpresa);
            if (!$etapaDestino) {
                $this->crearEtapa($id, $idEmpresa, $idDepartamento, $idUsuario, null);
            }

            $this->repository->updateDepartamentoActual($id, $idEmpresa, $idDepartamento, $idUsuario);
            $this->marcarEnProceso($cab, $idEmpresa, $idUsuario);

            $this->registrarBitacora($id, $idEmpresa, $idUsuario, $idDepartamento, 'cambio_departamento',
                'Pasa a ' . ($departamento['nombre'] ?? ''),
                $depActual > 0 ? 'Desde: ' . ($cab['departamento_nombre'] ?? '') : null);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIO_DEPARTAMENTO_TALLER', 'taller_ordenes', $id,
                ['id_departamento_actual' => $depActual], ['id_departamento_actual' => $idDepartamento]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** El operario toma el trabajo: arranca el cronómetro de la etapa. */
    public function iniciarEtapa(int $idEtapa, int $idEmpresa, int $idUsuario, ?int $idEmpleado): void
    {
        $etapa = $this->repository->findEtapa($idEtapa, $idEmpresa);
        if (!$etapa) throw new Exception("Etapa no encontrada.");

        $cab = $this->repository->find((int) $etapa['id_orden'], $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $departamento = $this->departamentoRepository->find((int) $etapa['id_departamento'], $idEmpresa) ?? [];
        $this->rules->validarInicioEtapa($cab, $departamento);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->iniciarEtapa($idEtapa, $idEmpresa, $idUsuario, $idEmpleado);
            $this->marcarEnProceso($cab, $idEmpresa, $idUsuario);

            $this->registrarBitacora((int) $etapa['id_orden'], $idEmpresa, $idUsuario, (int) $etapa['id_departamento'],
                'cambio_estado', 'Inicia trabajo en ' . ($departamento['nombre'] ?? ''), null);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Guarda lo escrito por el operario sin cerrar la etapa. */
    public function guardarAvanceEtapa(int $idEtapa, int $idEmpresa, int $idUsuario, array $d): void
    {
        $etapa = $this->repository->findEtapa($idEtapa, $idEmpresa);
        if (!$etapa) throw new Exception("Etapa no encontrada.");

        $cab = $this->repository->find((int) $etapa['id_orden'], $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);

        $this->repository->guardarAvanceEtapa($idEtapa, $idEmpresa, $idUsuario, [
            'trabajo_realizado'       => $d['trabajo_realizado'] ?? null,
            'observaciones'           => $d['observaciones'] ?? null,
            'id_empleado_responsable' => empty($d['id_empleado_responsable']) ? null : (int) $d['id_empleado_responsable'],
        ]);
    }

    /**
     * El departamento termina su trabajo. Puede enviar el vehículo al siguiente
     * departamento en el mismo movimiento (lo normal desde la tablet).
     */
    public function terminarEtapa(int $idEtapa, int $idEmpresa, int $idUsuario, array $d): void
    {
        $etapa = $this->repository->findEtapa($idEtapa, $idEmpresa);
        if (!$etapa) throw new Exception("Etapa no encontrada.");

        $idOrden = (int) $etapa['id_orden'];
        $cab = $this->repository->find($idOrden, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");
        $this->rules->validarOrdenEditable($cab);
        $this->rules->validarCierreEtapa($cab, $etapa, $d);

        $departamento = $this->departamentoRepository->find((int) $etapa['id_departamento'], $idEmpresa) ?? [];
        $idSiguiente  = (int) ($d['id_departamento_siguiente'] ?? 0);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->terminarEtapa($idEtapa, $idEmpresa, $idUsuario, [
                'trabajo_realizado'       => trim((string) $d['trabajo_realizado']),
                'observaciones'           => $d['observaciones'] ?? null,
                'id_empleado_responsable' => empty($d['id_empleado_responsable']) ? null : (int) $d['id_empleado_responsable'],
            ]);

            // Las líneas de este departamento quedan ejecutadas.
            foreach ($this->repository->getDetalles($idOrden, $idEmpresa, (int) $etapa['id_departamento']) as $linea) {
                if ((string) $linea['estado_linea'] === 'aprobada') {
                    $this->repository->updateEstadoLinea((int) $linea['id'], $idEmpresa, 'ejecutada', $idUsuario);
                }
            }

            $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, (int) $etapa['id_departamento'],
                'cambio_estado', 'Termina ' . ($departamento['nombre'] ?? ''), trim((string) $d['trabajo_realizado']));

            if ($idSiguiente > 0) {
                $siguiente = $this->departamentoRepository->find($idSiguiente, $idEmpresa);
                if (!$siguiente) throw new Exception("El departamento de destino no existe.");

                if (!$this->repository->findEtapaAbierta($idOrden, $idSiguiente, $idEmpresa)) {
                    $this->crearEtapa($idOrden, $idEmpresa, $idSiguiente, $idUsuario, null);
                }
                $this->repository->updateDepartamentoActual($idOrden, $idEmpresa, $idSiguiente, $idUsuario);
                $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, $idSiguiente, 'cambio_departamento',
                    'Pasa a ' . ($siguiente['nombre'] ?? ''), null);
            } else {
                // Sin siguiente departamento: si no queda nada abierto, la OT
                // queda lista para la entrega.
                $this->repository->updateDepartamentoActual($idOrden, $idEmpresa, null, $idUsuario);
                if (!$this->repository->tieneEtapasAbiertas($idOrden, $idEmpresa)) {
                    $this->repository->updateEstado($idOrden, $idEmpresa, 'terminada', $idUsuario);
                    $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, null, 'cambio_estado',
                        'Vehículo listo para entrega', null);
                }
            }

            $this->recalcularTotales($idOrden, $idEmpresa);
            $this->logService->registrar($idUsuario, $idEmpresa, 'TERMINAR_ETAPA_TALLER', 'taller_ordenes_etapas', $idEtapa, $etapa, $d);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ═══ BITÁCORA Y FOTOS ════════════════════════════════════════════════════

    public function agregarNota(int $idOrden, int $idEmpresa, int $idUsuario, string $concepto, ?string $detalle, ?int $idDepartamento = null): int
    {
        if (trim($concepto) === '') {
            throw new Exception("La nota necesita un título.");
        }
        $cab = $this->repository->find($idOrden, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        return $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, $idDepartamento, 'nota', trim($concepto), $detalle);
    }

    /**
     * Valida y guarda en disco una foto subida, y la registra en la orden.
     *
     * Vive aquí y no en el controlador porque la usan tanto la pantalla del
     * asesor como la tablet de cada departamento, y las reglas de qué archivo
     * se acepta deben ser las mismas en ambas.
     *
     * @param array $file Entrada de $_FILES
     * @return array{id:int,url:string}
     */
    public function guardarFotoSubida(array $file, int $idOrden, int $idEmpresa, int $idUsuario, array $opciones = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('La imagen no se pudo subir. Intente nuevamente.');
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new Exception('Formato no permitido. Use JPG, PNG o WEBP.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new Exception('La imagen excede los 5 MB.');
        }
        // Que la extensión diga «jpg» no basta: se comprueba el contenido.
        if (@getimagesize($file['tmp_name']) === false) {
            throw new Exception('El archivo no es una imagen válida.');
        }

        $relDir = self::DIR_FOTOS . $idEmpresa . '/' . $idOrden . '/';
        $absDir = \MVC_ROOT . '/public/' . $relDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new Exception('No se pudo preparar la carpeta de fotos.');
        }

        $nombre = uniqid('ot_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $absDir . $nombre)) {
            throw new Exception('No se pudo guardar la imagen.');
        }

        $id = $this->agregarFoto($idOrden, $idEmpresa, $idUsuario, [
            'id_departamento' => $opciones['id_departamento'] ?? null,
            'momento'         => $opciones['momento'] ?? 'ingreso',
            'ruta_archivo'    => $relDir . $nombre,
            'nombre_original' => mb_substr((string) ($file['name'] ?? ''), 0, 200),
            'descripcion'     => $opciones['descripcion'] ?? null,
        ]);

        return ['id' => $id, 'url' => \BASE_URL . '/' . $relDir . $nombre];
    }

    public function agregarFoto(int $idOrden, int $idEmpresa, int $idUsuario, array $d): int
    {
        $cab = $this->repository->find($idOrden, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $id = $this->repository->insertFoto([
            'id_orden'        => $idOrden,
            'id_empresa'      => $idEmpresa,
            'id_departamento' => empty($d['id_departamento']) ? null : (int) $d['id_departamento'],
            'momento'         => in_array(($d['momento'] ?? 'ingreso'), ['ingreso', 'proceso', 'entrega'], true) ? $d['momento'] : 'ingreso',
            'ruta_archivo'    => $d['ruta_archivo'],
            'nombre_original' => $d['nombre_original'] ?? null,
            'descripcion'     => $d['descripcion'] ?? null,
            'id_usuario'      => $idUsuario,
        ]);

        $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario,
            empty($d['id_departamento']) ? null : (int) $d['id_departamento'],
            'foto', 'Foto agregada (' . ($d['momento'] ?? 'ingreso') . ')', $d['descripcion'] ?? null);

        return $id;
    }

    public function eliminarFoto(int $idFoto, int $idEmpresa, int $idUsuario): void
    {
        $foto = $this->repository->findFoto($idFoto, $idEmpresa);
        if (!$foto) throw new Exception("Foto no encontrada.");

        $this->repository->eliminarFoto($idFoto, $idEmpresa, $idUsuario);

        // Borrar el archivo físico solo después de marcarlo en la base.
        $ruta = \MVC_ROOT . '/public/' . ltrim((string) $foto['ruta_archivo'], '/');
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    // ═══ CIERRE DE LA ORDEN ══════════════════════════════════════════════════

    /** Entrega del vehículo al cliente. */
    public function entregar(int $id, int $idEmpresa, int $idUsuario, array $d): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $this->rules->validarEntrega($cab, $this->repository->tieneEtapasAbiertas($id, $idEmpresa));

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->updateCabecera($id, $idEmpresa, [
                'estado'                   => 'entregada',
                'fecha_entrega'            => date('Y-m-d H:i:s'),
                'entregado_a'              => $d['entregado_a'] ?? null,
                'kilometraje_salida'       => ($d['kilometraje_salida'] ?? '') === '' ? null : (int) $d['kilometraje_salida'],
                'recomendaciones'          => $d['recomendaciones'] ?? ($cab['recomendaciones'] ?? null),
                'proximo_mantenimiento_km' => ($d['proximo_mantenimiento_km'] ?? '') === '' ? ($cab['proximo_mantenimiento_km'] ?? null) : (int) $d['proximo_mantenimiento_km'],
                'proxima_cita'             => empty($d['proxima_cita']) ? ($cab['proxima_cita'] ?? null) : $d['proxima_cita'],
                'garantia_dias'            => (int) ($d['garantia_dias'] ?? $cab['garantia_dias'] ?? 0),
                'garantia_km'              => (int) ($d['garantia_km'] ?? $cab['garantia_km'] ?? 0),
                'id_departamento_actual'   => null,
                'updated_by'               => $idUsuario,
                'updated_at'               => date('Y-m-d H:i:s'),
            ]);

            if (($d['kilometraje_salida'] ?? '') !== '') {
                $this->actualizarKilometrajeVehiculo((int) $cab['id_vehiculo'], $idEmpresa, (int) $d['kilometraje_salida']);
            }

            $this->registrarBitacora($id, $idEmpresa, $idUsuario, null, 'entrega',
                'Vehículo entregado a ' . trim((string) ($d['entregado_a'] ?? '')), $d['recomendaciones'] ?? null);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ENTREGAR_ORDEN_TALLER', 'taller_ordenes', $id,
                ['estado' => $cab['estado']], ['estado' => 'entregada'] + $d);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id, int $idEmpresa, int $idUsuario, string $nuevoEstado): void
    {
        $this->rules->validarEstado($nuevoEstado);

        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $actual = (string) ($cab['estado'] ?? '');
        if ($actual === $nuevoEstado) return;

        if ($actual === 'facturada' || !empty($cab['id_documento'])) {
            throw new Exception("La orden ya fue facturada; su estado no puede cambiarse manualmente.");
        }
        if ($nuevoEstado === 'facturada') {
            throw new Exception("El estado 'facturada' se asigna automáticamente al generar el documento.");
        }
        if ($nuevoEstado === 'aprobada' && Booleano::no($cab['aprobado'] ?? false)) {
            throw new Exception("Para pasar a 'aprobada' registre la aprobación del cliente en la pestaña Presupuesto.");
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            $this->repository->updateEstado($id, $idEmpresa, $nuevoEstado, $idUsuario, $nuevoEstado === 'entregada');
            $this->registrarBitacora($id, $idEmpresa, $idUsuario, null, 'cambio_estado',
                'Estado: ' . $actual . ' → ' . $nuevoEstado, null);

            $this->logService->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ESTADO_ORDEN_TALLER', 'taller_ordenes', $id,
                ['estado' => $actual], ['estado' => $nuevoEstado]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repository->find($id, $idEmpresa);
        if (!$cab) throw new Exception("Orden no encontrada.");

        $this->rules->validarEliminacion($cab);

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Devolver al stock todo lo que la orden había consumido.
            $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $id, $idEmpresa, $idUsuario);
            $this->repository->eliminar($id, $idEmpresa, $idUsuario);

            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_ORDEN_TALLER', 'taller_ordenes', $id, $cab, null);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ═══ FACTURACIÓN ═════════════════════════════════════════════════════════

    /**
     * Genera el documento de venta (FACTURA o RECIBO) a partir de la orden,
     * reutilizando FacturaVentaService / ReciboVentaService. Solo entran las
     * líneas facturables aprobadas o ejecutadas: lo rechazado y lo que trajo el
     * cliente queda registrado en el informe pero no se cobra.
     *
     * @return array{tipo:string,id_documento:int,numero_documento:string}
     */
    public function generarDocumento(int $idOrden, int $idEmpresa, int $idUsuario, string $tipo, array $extra, array $empresaConfig): array
    {
        $tipo  = strtoupper($tipo);
        $orden = $this->getDetalleCompleto($idOrden, $idEmpresa, false);
        if (!$orden) {
            throw new Exception('Orden no encontrada.');
        }
        $this->rules->validarGeneracionDocumento($orden, $tipo, $extra);

        $facturables = array_values(array_filter(
            $orden['detalles'] ?? [],
            fn($d) => Booleano::es($d['facturable'] ?? false)
                   && in_array((string) $d['estado_linea'], ['aprobada', 'ejecutada'], true)
                   && (float) $d['cantidad'] > 0
        ));
        if (empty($facturables)) {
            throw new Exception('La orden no tiene repuestos ni trabajos aprobados para facturar.');
        }

        $idPunto = (int) ($orden['id_punto_emision'] ?? 0);
        $idEstab = (int) ($orden['id_establecimiento'] ?? 0);
        if ($idPunto <= 0) {
            throw new Exception('La orden no tiene punto de emisión para numerar el documento.');
        }
        $estCod    = (string) ($orden['establecimiento'] ?? '');
        $puntoCod  = (string) ($orden['punto_emision'] ?? '');
        $formaPago = (string) ($extra['forma_pago'] ?? '01');
        $idBodegaExtra = (int) ($orden['id_bodega'] ?? 0) ?: (int) ($extra['id_bodega'] ?? 0);

        // Detalles del documento con sus impuestos por línea.
        $det = [];
        $totalSinImp = 0.0; $totalDesc = 0.0; $ivaTotal = 0.0; $idBodega = 0;
        foreach ($facturables as $d) {
            $cant   = (float) $d['cantidad'];
            $precio = (float) $d['precio_unitario'];
            $dscto  = (float) $d['descuento'];
            $base   = round($precio * $cant - $dscto, 2);
            if ($base < 0) $base = 0.0;

            $tar = null;
            if (!empty($d['id_producto']))    $tar = $this->repository->getTarifaIvaProducto((int) $d['id_producto']);
            if (!$tar && !empty($d['id_tarifa_iva'])) $tar = $this->repository->getTarifaIvaById((int) $d['id_tarifa_iva']);
            if (!$tar) $tar = $this->repository->getTarifaIvaByPorcentaje((float) ($d['porcentaje_iva'] ?? 0));

            $pct      = $tar ? (float) $tar['porcentaje_iva'] : (float) ($d['porcentaje_iva'] ?? 0);
            $codPct   = $tar ? (string) $tar['codigo'] : '0';
            $idTar    = $tar ? (int) $tar['id'] : (!empty($d['id_tarifa_iva']) ? (int) $d['id_tarifa_iva'] : 0);
            $ivaLinea = round($base * $pct / 100, 2);

            $ivaTotal    += $ivaLinea;
            $totalSinImp += $base;
            $totalDesc   += $dscto;

            $bodegaLinea = (int) ($d['id_bodega'] ?? 0) ?: $idBodegaExtra;
            if ($idBodega === 0 && $bodegaLinea > 0) $idBodega = $bodegaLinea;

            $det[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'id_bodega'                 => $bodegaLinea ?: null,
                'descripcion'               => $d['descripcion'],
                'nombre'                    => $d['descripcion'],
                'cantidad'                  => $cant,
                'precio_unitario'           => $precio,
                'descuento'                 => $dscto,
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva'             => $idTar,
                'es_libre'                  => empty($d['id_producto']) ? '1' : 0,
                'porcentaje_iva'            => $pct,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa'            => $pct,
                    'base_imponible'    => $base,
                    'valor'             => $ivaLinea,
                ]],
            ];
        }

        $totalSinImp  = round($totalSinImp, 2);
        $totalDesc    = round($totalDesc, 2);
        $ivaTotal     = round($ivaTotal, 2);
        $importeTotal = round($totalSinImp + $ivaTotal, 2);
        if ($idBodegaExtra > 0) $idBodega = $idBodegaExtra;

        // El documento se numera con SU propio secuencial, que también debe estar
        // configurado en Empresa. Sin él la factura nacería con una numeración
        // inventada que después choca con la del módulo de ventas.
        // Se abre la transacción ANTES de calcular el secuencial y se mantiene hasta el INSERT
        // final (crear() más abajo): el lock de obtenerSiguienteSecuencial() se libera solo al
        // COMMIT/ROLLBACK (CLAUDE.md §8).
        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        $tipoDocSec = ($tipo === 'FACTURA') ? 'Facturas de venta' : 'Recibos de venta';
        $sec = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, $tipoDocSec);
        if (($sec['configurado'] ?? false) === false) {
            throw new Exception(
                'Esta serie no tiene configurado el secuencial "' . $tipoDocSec . '". '
                . 'Créelo en Empresa → Secuenciales antes de emitir el documento.'
            );
        }
        $secuencial = $sec['formateado'];
        $numeroDoc  = $estCod . '-' . $puntoCod . '-' . $secuencial;

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => $estCod,
            'punto_emision'       => $puntoCod,
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_cliente'          => (int) $orden['id_cliente'],
            'id_vendedor'         => null,
            'dias_credito'        => 0,
            'moneda'              => 'DOLAR',
            'observaciones'       => 'Generado desde la orden de taller ' . ($orden['numero_orden'] ?? ''),
            'id_bodega'           => $idBodega ?: null,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => $totalDesc,
            'total_ice'           => 0,
            'propina'             => 0,
            'importe_total'       => $importeTotal,
            'detalles'            => $det,
            'pagos'               => [[
                'forma_pago'    => $formaPago,
                'total'         => $importeTotal,
                'plazo'         => 0,
                'unidad_tiempo' => 'dias',
            ]],
            'info_adicional'      => $this->infoAdicionalDocumento($orden),
        ];

        // El documento hace su propia salida de inventario: devolvemos primero lo
        // que consumió la orden para no descontar dos veces. Las líneas quedan
        // marcadas como no aplicadas porque su movimiento de kardex ya no existe.
        $this->getInventarioService()->revertirMovimientosPorReferencia(self::REF_TIPO, $idOrden, $idEmpresa, $idUsuario);
        foreach ($orden['detalles'] ?? [] as $linea) {
            if (Booleano::es($linea['inventario_aplicado'] ?? false)) {
                $this->repository->marcarInventarioLinea((int) $linea['id'], $idEmpresa, false, null);
            }
        }

        try {
            if ($tipo === 'FACTURA') {
                $svc = new FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    $this->logService
                );
                $idDoc = $svc->crear($payload);
            } else {
                $payload['con_impuestos'] = true;
                $payload['estado']        = 'borrador';
                $payload['plazo']         = 0;
                $svc = new ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    $this->logService
                );
                $idDoc = $svc->crear($payload);
            }
            if ($managedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            // Si falla la emisión, restauramos el consumo de la orden.
            try {
                $this->reaplicarInventarioOrden($idOrden, $idEmpresa, $idUsuario);
            } catch (\Throwable $e2) {
                error_log("[Taller] No se pudo restaurar el inventario de la orden {$idOrden}: " . $e2->getMessage());
            }
            throw $e;
        }

        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repository->marcarDocumentoGenerado($idOrden, $idEmpresa, $tipo, (int) $idDoc, $numeroDoc, $idUsuario);
            $this->registrarBitacora($idOrden, $idEmpresa, $idUsuario, null, 'facturacion',
                ($tipo === 'FACTURA' ? 'Factura' : 'Recibo') . ' ' . $numeroDoc, null);
            $this->logService->registrar($idUsuario, $idEmpresa, 'GENERAR_DOCUMENTO_TALLER', 'taller_ordenes', $idOrden,
                ['estado' => $orden['estado'] ?? ''],
                ['tipo_documento' => $tipo, 'id_documento' => $idDoc, 'numero_documento' => $numeroDoc]);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw new Exception("El documento {$numeroDoc} se generó, pero la orden no pudo marcarse como facturada: " . $e->getMessage());
        }

        return ['tipo' => $tipo, 'id_documento' => (int) $idDoc, 'numero_documento' => $numeroDoc];
    }

    // ═══ INDICADORES ═════════════════════════════════════════════════════════

    public function getIndicadores(int $idEmpresa, string $desde, string $hasta): array
    {
        return [
            'departamentos' => $this->repository->getTiemposPorDepartamento($idEmpresa, $desde, $hasta),
            'tecnicos'      => $this->repository->getProductividadTecnicos($idEmpresa, $desde, $hasta),
        ];
    }

    // ═══ HELPERS INTERNOS ════════════════════════════════════════════════════

    /**
     * Calcula los importes de una línea en el backend (nunca se confía en lo
     * que llega del navegador).
     */
    private function calcularLinea(array $d, int $idEmpresa, ?array $original = null): array
    {
        $cant   = (float) ($d['cantidad'] ?? 0);
        $precio = (float) ($d['precio_unitario'] ?? 0);
        $dscto  = (float) ($d['descuento'] ?? 0);

        $idTarifa = empty($d['id_tarifa_iva']) ? null : (int) $d['id_tarifa_iva'];
        $porcIva  = (float) ($d['porcentaje_iva'] ?? 0);

        // La tarifa configurada en el producto manda sobre lo que llegue del front.
        if (!empty($d['id_producto'])) {
            $tar = $this->repository->getTarifaIvaProducto((int) $d['id_producto']);
            if ($tar) {
                $idTarifa = (int) $tar['id'];
                $porcIva  = (float) $tar['porcentaje_iva'];
            }
        } elseif ($idTarifa) {
            $tar = $this->repository->getTarifaIvaById($idTarifa);
            if ($tar) {
                $porcIva = (float) $tar['porcentaje_iva'];
            }
        }

        $base = round($precio * $cant - $dscto, 2);
        if ($base < 0) $base = 0.0;
        $valorIva = round($base * ($porcIva / 100), 2);

        $costo = (float) ($original['costo_unitario'] ?? 0);
        if (!empty($d['id_producto'])) {
            $costo = $this->repository->getCostoProducto((int) $d['id_producto'], $idEmpresa);
        }

        return [
            'cantidad'        => $cant,
            'precio_unitario' => $precio,
            'costo_unitario'  => $costo,
            'descuento'       => $dscto,
            'porcentaje_iva'  => $porcIva,
            'valor_iva'       => $valorIva,
            'total_linea'     => round($base + $valorIva, 2),
            'id_tarifa_iva'   => $idTarifa,
        ];
    }

    /** Recalcula y guarda los totales de la orden a partir de sus líneas. */
    private function recalcularTotales(int $idOrden, int $idEmpresa): void
    {
        $this->repository->updateTotales($idOrden, $idEmpresa, $this->repository->calcularTotales($idOrden, $idEmpresa));
    }

    /**
     * Descuenta de bodega el repuesto de una línea (una sola vez).
     * La mano de obra, los trabajos de terceros y lo que trae el cliente no
     * mueven inventario.
     */
    private function aplicarInventarioLinea(array $linea, array $orden, int $idEmpresa, int $idUsuario): void
    {
        if (Booleano::es($linea['inventario_aplicado'] ?? false)) return;
        if (!in_array((string) $linea['tipo_linea'], self::TIPOS_CONSUMEN_STOCK, true)) return;
        if (Booleano::es($linea['provisto_cliente'] ?? false)) return;

        $idProducto = (int) ($linea['id_producto'] ?? 0);
        $cantidad   = (float) ($linea['cantidad'] ?? 0);
        $idBodega   = (int) ($linea['id_bodega'] ?? 0) ?: (int) ($orden['id_bodega'] ?? 0);
        $idEstab    = (int) ($orden['id_establecimiento'] ?? 0);

        if ($idProducto <= 0 || $cantidad <= 0 || $idEstab <= 0) return;

        if ($idBodega <= 0) {
            throw new Exception('Seleccione la bodega del repuesto "' . ($linea['descripcion'] ?? '') . '".');
        }

        $res = $this->getInventarioService()->procesarSalidaPorVenta(
            (int) $linea['id_orden'],
            [[
                'id_producto' => $idProducto,
                'id_bodega'   => $idBodega,
                'cantidad'    => $cantidad,
                'nombre'      => $linea['descripcion'] ?? '',
            ]],
            $idEstab,
            $idEmpresa,
            $idUsuario,
            'Orden de taller # ' . ($orden['numero_orden'] ?? ''),
            false,
            self::REF_TIPO
        );

        $idKardex = null;
        if (!empty($res[0]) && is_array($res[0]) && !empty($res[0]['principal'])) {
            $idKardex = (int) $res[0]['principal'];
        }
        // Sin movimiento no hay nada que marcar: puede ser un producto no
        // inventariable o un establecimiento que no trabaja con inventario.
        if ($idKardex !== null) {
            $this->repository->marcarInventarioLinea((int) $linea['id'], $idEmpresa, true, $idKardex);
        }
    }

    /** Devuelve al stock lo que había consumido una línea. */
    private function revertirInventarioLinea(array $linea, int $idEmpresa, int $idUsuario): void
    {
        if (Booleano::no($linea['inventario_aplicado'] ?? false) || empty($linea['id_kardex'])) return;

        $this->getInventarioService()->eliminarMovimiento((int) $linea['id_kardex'], $idEmpresa, $idUsuario, true);
        $this->repository->marcarInventarioLinea((int) $linea['id'], $idEmpresa, false, null);
    }

    /** Vuelve a aplicar el consumo de toda la orden (rollback de facturación). */
    private function reaplicarInventarioOrden(int $idOrden, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repository->find($idOrden, $idEmpresa);
        if (!$cab) return;

        foreach ($this->repository->getDetalles($idOrden, $idEmpresa) as $linea) {
            if (!in_array((string) $linea['estado_linea'], ['aprobada', 'ejecutada'], true)) continue;
            // revertirMovimientosPorReferencia ya borró el kardex: limpiamos la
            // marca para que aplicarInventarioLinea vuelva a generarlo.
            $this->repository->marcarInventarioLinea((int) $linea['id'], $idEmpresa, false, null);
            $linea['inventario_aplicado'] = false;
            $linea['id_kardex'] = null;
            $this->aplicarInventarioLinea($linea, $cab, $idEmpresa, $idUsuario);
        }
    }

    /**
     * Exige que el punto de emisión tenga configurado el secuencial
     * 'Ordenes de taller'. Sin él la orden no puede numerarse.
     */
    private function validarSecuencialConfigurado(int $idPunto): void
    {
        if ($idPunto <= 0) {
            throw new Exception('Seleccione el punto de emisión de la orden.');
        }

        $res = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        if (($res['configurado'] ?? false) === false) {
            throw new Exception(
                'Esta serie no tiene configurado el secuencial "' . self::TIPO_SECUENCIAL . '". '
                . 'Créelo en Empresa → Secuenciales, en el selector "Agregar Tipo Documento", antes de registrar la orden.'
            );
        }
    }

    private function crearEtapa(int $idOrden, int $idEmpresa, int $idDepartamento, int $idUsuario, ?int $idEmpleado): int
    {
        return $this->repository->insertEtapa([
            'id_orden'                => $idOrden,
            'id_empresa'              => $idEmpresa,
            'id_departamento'         => $idDepartamento,
            'secuencia'               => $this->repository->haySiguienteSecuencia($idOrden, $idEmpresa),
            'estado'                  => 'pendiente',
            'id_empleado_responsable' => $idEmpleado,
            'id_usuario'              => $idUsuario,
        ]);
    }

    /** Mueve la orden a 'en_proceso' cuando empieza el trabajo real. */
    private function marcarEnProceso(array $cab, int $idEmpresa, int $idUsuario): void
    {
        $estado = (string) ($cab['estado'] ?? '');
        if (in_array($estado, ['aprobada', 'recepcion', 'diagnostico', 'presupuesto'], true) && Booleano::es($cab['aprobado'] ?? false)) {
            $this->repository->updateEstado((int) $cab['id'], $idEmpresa, 'en_proceso', $idUsuario);
        }
    }

    private function registrarBitacora(int $idOrden, int $idEmpresa, int $idUsuario, ?int $idDepartamento, string $tipo, string $concepto, ?string $detalle): int
    {
        return $this->repository->insertBitacora([
            'id_orden'        => $idOrden,
            'id_empresa'      => $idEmpresa,
            'id_departamento' => $idDepartamento,
            'id_usuario'      => $idUsuario,
            'tipo_evento'     => $tipo,
            'concepto'        => mb_substr($concepto, 0, 150),
            'detalle'         => $detalle,
        ]);
    }

    private function guardarChecklist(int $idOrden, int $idEmpresa, array $items): void
    {
        foreach ($items as $i => $item) {
            $texto = trim((string) ($item['item'] ?? ''));
            if ($texto === '') continue;
            $this->repository->insertChecklist([
                'id_orden'    => $idOrden,
                'id_empresa'  => $idEmpresa,
                'grupo'       => $item['grupo'] ?? 'accesorios',
                'item'        => $texto,
                'valor'       => $item['valor'] ?? 'no',
                'observacion' => $item['observacion'] ?? null,
                'orden'       => (int) ($item['orden'] ?? $i),
            ]);
        }
    }

    /** El kilometraje más reciente queda en la ficha del vehículo. */
    private function actualizarKilometrajeVehiculo(int $idVehiculo, int $idEmpresa, int $km): void
    {
        if ($idVehiculo <= 0 || $km <= 0) return;
        try {
            $st = Database::getConnection()->prepare(
                "UPDATE vehiculos SET kilometraje_actual = :km, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e AND eliminado = false
                   AND (kilometraje_actual IS NULL OR kilometraje_actual < :km2)"
            );
            $st->execute([':km' => $km, ':km2' => $km, ':id' => $idVehiculo, ':e' => $idEmpresa]);
        } catch (\Throwable $e) {
            // La columna se agrega con la migración del módulo; si aún no está,
            // no tiene sentido interrumpir el registro de la orden.
            error_log('[Taller] No se pudo actualizar el kilometraje del vehículo: ' . $e->getMessage());
        }
    }

    /**
     * Info adicional del documento: se arrastra la de la orden (incluido el
     * correo del cliente, que es el que usa el envío del comprobante) y se le
     * suman los datos del vehículo. Nada se duplica si el asesor ya lo escribió.
     */
    private function infoAdicionalDocumento(array $orden): array
    {
        $info = is_array($orden['info_adicional'] ?? null) ? $orden['info_adicional'] : [];

        $yaEsta = function (string $nombre) use ($info): bool {
            foreach ($info as $ia) {
                if (mb_strtolower(trim((string) ($ia['nombre'] ?? ''))) === mb_strtolower($nombre)) {
                    return true;
                }
            }
            return false;
        };

        $agregar = function (string $nombre, $valor) use (&$info, $yaEsta): void {
            if ((string) $valor === '' || $valor === null || $yaEsta($nombre)) return;
            $info[] = ['nombre' => $nombre, 'valor' => (string) $valor];
        };

        $agregar('Placa', $orden['placa'] ?? '');
        $agregar('Orden de taller', $orden['numero_orden'] ?? '');
        $agregar('Kilometraje', $orden['kilometraje'] ?? '');

        // Si la orden no llevaba la fila del correo, se toma la del cliente.
        $agregar('Correo del cliente', $orden['cliente_email'] ?? '');

        return $info;
    }

    private function etiquetaTipoLinea(string $tipo): string
    {
        return [
            'repuesto'  => 'Repuesto',
            'mano_obra' => 'Mano de obra',
            'insumo'    => 'Insumo',
            'tercero'   => 'Trabajo de terceros',
        ][$tipo] ?? 'Ítem';
    }

    private function encodeInfoAdicional($info): ?string
    {
        if (!is_array($info)) return null;
        $limpio = [];
        foreach ($info as $ia) {
            $nom = trim((string) ($ia['nombre'] ?? ''));
            $val = trim((string) ($ia['valor'] ?? ''));
            if ($nom !== '' && $val !== '') {
                $limpio[] = ['nombre' => $nom, 'valor' => $val];
            }
        }
        return empty($limpio) ? null : json_encode($limpio, JSON_UNESCAPED_UNICODE);
    }

    private function decodeInfoAdicional($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }
}

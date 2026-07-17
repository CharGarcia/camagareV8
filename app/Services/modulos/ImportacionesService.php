<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ImportacionesRepository;
use App\repositories\modulos\InventarioRepository;
use App\Rules\modulos\ImportacionesRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;

class ImportacionesService
{
    private ImportacionesRepository $repository;
    private ImportacionesRules $rules;
    private LogSistemaService $logService;

    public function __construct()
    {
        $this->repository = new ImportacionesRepository();
        $this->rules = new ImportacionesRules();
        $this->logService = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD CABECERA
    // ─────────────────────────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->validarGastosVinculados($data);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data = $this->asignarSecuencial($data);
            $idImportacion = $this->repository->insertCabecera($data);

            $this->guardarFacturasExterior($idImportacion, $data['facturas_exterior'] ?? [], $idUsuario);
            $this->guardarDetalles($idImportacion, $data['detalles'] ?? [], $idUsuario);
            $this->guardarGastos($idImportacion, $data['gastos'] ?? [], $idUsuario);
            $this->recalcularTotales($idImportacion);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR', 'importaciones_cabecera', $idImportacion,
                null, ['id_importacion' => $idImportacion]
            );

            if ($managed) $db->commit();
            return $idImportacion;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Importación no encontrada.');
        }
        if (in_array($cabecera['estado'], ['pendiente_aprobacion', 'nacionalizada', 'cerrada', 'anulada'], true)) {
            throw new \Exception('No se puede modificar una importación pendiente de aprobación, nacionalizada, cerrada o anulada.');
        }

        $this->rules->validar($data);
        $this->validarGastosVinculados($data);

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idUsuario = (int) $data['id_usuario'];

            $this->repository->updateCabecera($id, $data);

            $this->repository->deleteFacturasExterior($id);
            $this->guardarFacturasExterior($id, $data['facturas_exterior'] ?? [], $idUsuario);

            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles'] ?? [], $idUsuario);

            $this->repository->deleteGastos($id);
            $this->guardarGastos($id, $data['gastos'] ?? [], $idUsuario);

            $this->recalcularTotales($id);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'MODIFICAR', 'importaciones_cabecera', $id,
                $cabecera, ['id_importacion' => $id]
            );

            if ($managed) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            return null;
        }

        $importacion['detalles']          = $this->repository->getDetalles($id);
        $importacion['facturas_exterior'] = $this->repository->getFacturasExterior($id);
        $importacion['gastos']            = $this->repository->getGastos($id);

        return $importacion;
    }

    public function eliminar(int $id, int $idUsuario, int $idEmpresa): bool
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }
        if ($importacion['estado'] === 'nacionalizada') {
            throw new \Exception('No se puede eliminar una importación ya nacionalizada. Reviértala desde el kardex de inventario primero.');
        }
        if ($importacion['estado'] === 'pendiente_aprobacion') {
            throw new \Exception('No se puede eliminar una importación pendiente de aprobación. Recházela primero.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $this->repository->eliminarLogico($id, $idUsuario);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ELIMINAR', 'importaciones_cabecera', $id,
                ['id' => $id], null
            );
            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CARGA MASIVA DE LÍNEAS FOB DESDE EXCEL/CSV
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resuelve las filas ya parseadas de un Excel/CSV (una fila = un array
     * asociativo con las claves del encabezado, ver ImportacionesController::
     * importarProductosAjax) a líneas de detalle FOB, buscando el producto por
     * código en el catálogo. No persiste nada: las líneas resultantes se
     * agregan a la tabla de la pestaña Productos igual que al buscar un
     * producto manualmente; solo quedan guardadas cuando el usuario guarda
     * toda la importación (mismo patrón que el buscador del catálogo).
     */
    public function resolverLineasExcelProductos(array $filas, int $idEmpresa): array
    {
        $resultado = [];
        foreach ($filas as $i => $f) {
            $codigo      = trim((string) ($f['codigo_producto'] ?? ''));
            $descripcion = trim((string) ($f['descripcion'] ?? ''));
            $cantidad    = $this->parseNumeroExcel($f['cantidad'] ?? 0);
            $precioFob   = $this->parseNumeroExcel($f['precio_unitario_fob'] ?? 0);
            $pesoKg      = $this->parseNumeroExcel($f['peso_kg'] ?? 0);
            $volumenM3   = $this->parseNumeroExcel($f['volumen_m3'] ?? 0);

            $producto = $codigo !== '' ? $this->repository->getProductoPorCodigo($codigo, $idEmpresa) : null;

            $error = null;
            if ($codigo === '' && $descripcion === '') {
                $error = 'Falta el código o la descripción del producto.';
            } elseif ($codigo !== '' && !$producto) {
                $error = "El producto con código \"{$codigo}\" no existe en la empresa.";
            } elseif ($cantidad <= 0) {
                $error = 'La cantidad debe ser mayor a cero.';
            }

            $resultado[] = [
                'fila'                => $i + 2, // +2: la fila 1 del Excel es el encabezado
                'id_producto'         => $producto['id'] ?? null,
                'codigo_producto_raw' => $codigo,
                'descripcion'         => $producto['nombre'] ?? ($descripcion !== '' ? $descripcion : $codigo),
                'id_medida'           => $producto['id_medida'] ?? null,
                'cantidad'            => $cantidad,
                'precio_unitario_fob' => $precioFob,
                'precio_total_fob'    => round($cantidad * $precioFob, 2),
                'peso_kg'             => $pesoKg,
                'volumen_m3'          => $volumenM3,
                'numero_lote'         => ($f['numero_lote'] ?? '') !== '' ? trim((string) $f['numero_lote']) : null,
                'fecha_caducidad'     => ($f['fecha_caducidad'] ?? '') !== '' ? trim((string) $f['fecha_caducidad']) : null,
                'nup'                 => ($f['nup'] ?? '') !== '' ? trim((string) $f['nup']) : null,
                'valido'              => $error === null,
                'error'               => $error,
            ];
        }
        return $resultado;
    }

    /** Tolera coma o punto como separador decimal (celdas de Excel en formato texto). */
    private function parseNumeroExcel(mixed $v): float
    {
        if ($v === null || $v === '') return 0.0;
        if (is_int($v) || is_float($v)) return (float) $v;
        return (float) str_replace(',', '.', trim((string) $v));
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRORRATEO (landed cost)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcula el costo unitario nacionalizado por línea, repartiendo el total
     * capitalizable (FOB facturado + gastos capitalizables) según el criterio
     * elegido ('fob' | 'peso' | 'volumen' | 'cantidad'). El residual de redondeo
     * se ajusta en la línea de mayor peso para que la suma cuadre exacto contra
     * el total (mismo patrón que AsientoBuilderService::aplicarAjusteRedondeo).
     */
    public function calcularProrrateo(array $detalles, float $totalCapitalizable, string $criterio): array
    {
        if (empty($detalles)) {
            return [];
        }

        $pesos = array_map(function ($d) use ($criterio) {
            return match ($criterio) {
                'peso'     => (float) ($d['peso_kg'] ?? 0),
                'volumen'  => (float) ($d['volumen_m3'] ?? 0),
                'cantidad' => (float) ($d['cantidad'] ?? 0),
                default    => (float) ($d['precio_total_fob'] ?? 0),
            };
        }, $detalles);

        $totalPeso = array_sum($pesos);
        if ($totalPeso <= 0.0) {
            throw new \Exception("No se puede prorratear por '{$criterio}': el total de esa base es cero en las líneas.");
        }

        $acumulado = 0.0;
        $idxMayor  = 0;
        $mayorPeso = -1.0;
        $detalles  = array_values($detalles);

        foreach ($detalles as $i => $d) {
            $peso = $pesos[$i];
            if ($peso > $mayorPeso) { $mayorPeso = $peso; $idxMayor = $i; }
            $costoTotal = round($totalCapitalizable * ($peso / $totalPeso), 2);
            $detalles[$i]['costo_total_nacionalizado'] = $costoTotal;
            $acumulado += $costoTotal;
        }

        $residual = round($totalCapitalizable - $acumulado, 2);
        if ($residual !== 0.0) {
            $detalles[$idxMayor]['costo_total_nacionalizado'] = round($detalles[$idxMayor]['costo_total_nacionalizado'] + $residual, 2);
        }

        foreach ($detalles as $i => $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $detalles[$i]['costo_unitario_nacionalizado'] = $cantidad > 0
                ? round($d['costo_total_nacionalizado'] / $cantidad, 6)
                : 0.0;
        }

        return $detalles;
    }

    /**
     * Clasifica los gastos en los 4 baldes del landed cost. Un gasto VINCULADO
     * (ya registrado como Compra/Liquidación) siempre se trata como capitalizable:
     * su propio documento ya gestionó su IVA/CxP, aquí solo aporta al costo.
     * Público: también lo usa el Controller para la vista previa del prorrateo.
     */
    public function calcularTotalesGastos(array $gastos): array
    {
        $capitalizableManual    = 0.0;
        $capitalizableVinculado = 0.0;
        $iva                    = 0.0;
        $isd                    = 0.0;
        $otros                  = 0.0;

        foreach ($gastos as $g) {
            $monto        = (float) ($g['monto'] ?? 0);
            $origen       = $g['origen'] ?? 'dai_manual';
            $tipo         = $g['tipo_gasto'] ?? 'otro';
            $prorrateable = !empty($g['prorrateable']);

            if ($origen !== 'dai_manual') {
                $capitalizableVinculado += $monto;
                continue;
            }
            if ($prorrateable) {
                $capitalizableManual += $monto;
            } elseif ($tipo === 'iva_importacion') {
                $iva += $monto;
            } elseif ($tipo === 'isd') {
                $isd += $monto;
            } else {
                $otros += $monto;
            }
        }

        return [
            'capitalizable_manual'    => round($capitalizableManual, 2),
            'capitalizable_vinculado' => round($capitalizableVinculado, 2),
            'capitalizable_total'     => round($capitalizableManual + $capitalizableVinculado, 2),
            'iva'                     => round($iva, 2),
            'isd'                     => round($isd, 2),
            'otros'                   => round($otros, 2),
        ];
    }

    /**
     * Recalcula y persiste los totales de la cabecera a partir del estado actual
     * en BD (detalle + gastos + facturas del exterior). No prorratea a las líneas;
     * eso solo ocurre al procesar el inventario.
     */
    private function recalcularTotales(int $idImportacion): void
    {
        $detalles = $this->repository->getDetalles($idImportacion);
        $gastos   = $this->repository->getGastos($idImportacion);
        $facturas = $this->repository->getFacturasExterior($idImportacion);

        $subtotalFob          = array_sum(array_map(fn($d) => (float) $d['precio_total_fob'], $detalles));
        $totalFacturaExterior = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
        $tg = $this->calcularTotalesGastos($gastos);

        $this->repository->actualizarTotales($idImportacion, [
            'subtotal_fob'                => round($subtotalFob, 2),
            'total_gastos_capitalizables' => $tg['capitalizable_total'],
            'total_iva'                   => $tg['iva'],
            'total_isd'                   => $tg['isd'],
            'total_otros_gastos'          => $tg['otros'],
            'costo_total_nacionalizado'   => round($totalFacturaExterior, 2) + $tg['capitalizable_total'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROCESAR INVENTARIO (nacionalización — carga en lote al kardex)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Config de aprobación de inventario (empresa_establecimiento), mismo
     * mecanismo que CargaInventarioService::getConfigAprobacion() — se toma
     * del primer establecimiento de la empresa.
     */
    public function getConfigAprobacion(int $idEmpresa): array
    {
        $empRepo = new \App\repositories\modulos\EmpresaRepository();
        $idEst   = $empRepo->getPrimerEstablecimientoId($idEmpresa);
        $cfg     = $idEst ? ($empRepo->getEstablecimientoConfig($idEst) ?? []) : [];

        $requiere  = !empty($cfg['inv_requiere_aprobacion']) && $cfg['inv_requiere_aprobacion'] !== 'f';
        $notificar = !isset($cfg['inv_notificar_correo']) || ($cfg['inv_notificar_correo'] && $cfg['inv_notificar_correo'] !== 'f');
        $aprob     = json_decode($cfg['inv_usuarios_aprobadores'] ?? '[]', true);
        if (!is_array($aprob)) $aprob = [];
        $aprob = array_values(array_map('intval', $aprob));

        return ['requiere' => $requiere, 'notificar' => $notificar, 'aprobadores' => $aprob];
    }

    /** ¿El usuario puede aprobar/rechazar la nacionalización? (aprobador configurado o super admin). */
    public function esAprobador(int $idUsuario, int $idEmpresa, int $nivel = 1): bool
    {
        if ($nivel >= 3) return true;
        $cfg = $this->getConfigAprobacion($idEmpresa);
        return in_array($idUsuario, $cfg['aprobadores'], true);
    }

    /** Nombres de los usuarios aprobadores configurados (para mostrar quién debe aprobar). */
    public function getAprobadoresNombres(int $idEmpresa): array
    {
        $cfg = $this->getConfigAprobacion($idEmpresa);
        if (empty($cfg['aprobadores'])) return [];
        return array_column($this->repository->getNombresUsuarios($cfg['aprobadores']), 'nombre');
    }

    /**
     * Prorratea el costo entre las líneas. Si la empresa NO exige aprobación de
     * inventario, postea de inmediato al kardex (una entrada por línea,
     * referencia_tipo='importacion') y genera el asiento — equivalente a
     * ComprasController::procesarInventarioAjax pero para todas las líneas de
     * una vez. Si la exige, deja la importación en 'pendiente_aprobacion' (con
     * los costos ya calculados y guardados para revisión) y no toca el kardex
     * ni el asiento hasta que se apruebe — mismo patrón que Cargas de Inventario.
     */
    public function procesarInventario(int $id, int $idEmpresa, int $idUsuario): array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }

        $detalles = $this->repository->getDetalles($id);
        $gastos   = $this->repository->getGastos($id);
        $facturas = $this->repository->getFacturasExterior($id);

        $this->rules->validarParaNacionalizar($importacion, $gastos);

        foreach ($detalles as $d) {
            if (empty($d['id_producto'])) {
                $raw = $d['codigo_producto_raw'] ?? $d['descripcion'] ?? ('línea #' . $d['id']);
                throw new \Exception("La línea '{$raw}' no tiene producto homologado. Vincule el producto antes de procesar el inventario.");
            }
        }

        $totalFacturaExterior    = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
        $tg                      = $this->calcularTotalesGastos($gastos);
        $costoTotalNacionalizado = round($totalFacturaExterior + $tg['capitalizable_total'], 2);

        $detallesConCosto = $this->calcularProrrateo($detalles, $costoTotalNacionalizado, $importacion['criterio_prorrateo']);

        $cfgAprobacion = $this->getConfigAprobacion($idEmpresa);

        if ($cfgAprobacion['requiere']) {
            $db = Database::getConnection();
            $managed = !$db->inTransaction();
            if ($managed) $db->beginTransaction();
            try {
                foreach ($detallesConCosto as $d) {
                    $this->repository->actualizarCostoNacionalizado(
                        (int) $d['id'],
                        (float) $d['costo_unitario_nacionalizado'],
                        (float) $d['costo_total_nacionalizado']
                    );
                }
                $this->repository->actualizarTotales($id, [
                    'subtotal_fob'                => round(array_sum(array_map(fn($d) => (float) $d['precio_total_fob'], $detalles)), 2),
                    'total_gastos_capitalizables' => $tg['capitalizable_total'],
                    'total_iva'                   => $tg['iva'],
                    'total_isd'                   => $tg['isd'],
                    'total_otros_gastos'          => $tg['otros'],
                    'costo_total_nacionalizado'   => $costoTotalNacionalizado,
                ]);
                $this->repository->actualizarEstadoAprobacion($id, 'pendiente_aprobacion');
                $token = bin2hex(random_bytes(24));
                $this->repository->setToken($id, $token);
                $this->logService->registrar(
                    $idUsuario, $idEmpresa, 'SOLICITAR_NACIONALIZACION', 'importaciones_cabecera', $id,
                    ['estado' => $importacion['estado']],
                    ['estado' => 'pendiente_aprobacion', 'costo_total_nacionalizado' => $costoTotalNacionalizado]
                );
                if ($managed) $db->commit();
            } catch (\Throwable $e) {
                if ($managed && $db->inTransaction()) $db->rollBack();
                throw $e;
            }

            if ($cfgAprobacion['requiere'] && $cfgAprobacion['notificar']) {
                try {
                    $this->notificarAprobadores($idEmpresa, $id, $cfgAprobacion['aprobadores'], $token, $idUsuario);
                } catch (\Throwable $e) {
                    // Best-effort: un fallo de correo no revierte la solicitud de aprobación.
                }
            }

            return [
                'id'                        => $id,
                'costo_total_nacionalizado' => $costoTotalNacionalizado,
                'pendiente_aprobacion'      => true,
                'asiento_warning'           => null,
            ];
        }

        return $this->aplicarNacionalizacion($id, $idEmpresa, $idUsuario, $importacion, $detalles, $detallesConCosto, $tg, $costoTotalNacionalizado);
    }

    /**
     * Aprueba una importación 'pendiente_aprobacion': recalcula el prorrateo
     * (no puede haber cambiado, la edición queda bloqueada mientras está
     * pendiente) y aplica la nacionalización real (kardex + asiento).
     * Segregación de funciones: quien la solicitó no puede autoaprobarla,
     * salvo super admin — mismo patrón que CargaInventarioService::aprobar().
     */
    public function aprobarNacionalizacion(int $id, int $idEmpresa, int $idUsuario, int $nivel = 3): array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }
        if ($importacion['estado'] !== 'pendiente_aprobacion') {
            throw new \Exception('Solo se pueden aprobar importaciones pendientes de aprobación.');
        }
        if ($nivel < 3 && (int) ($importacion['created_by'] ?? 0) === $idUsuario) {
            throw new \Exception('No puede aprobar una importación que usted mismo solicitó nacionalizar. Debe aprobarla otro usuario autorizado.');
        }

        $detalles = $this->repository->getDetalles($id);
        $gastos   = $this->repository->getGastos($id);
        $facturas = $this->repository->getFacturasExterior($id);

        $totalFacturaExterior    = array_sum(array_map(fn($f) => (float) $f['monto_usd'], $facturas));
        $tg                      = $this->calcularTotalesGastos($gastos);
        $costoTotalNacionalizado = round($totalFacturaExterior + $tg['capitalizable_total'], 2);
        $detallesConCosto        = $this->calcularProrrateo($detalles, $costoTotalNacionalizado, $importacion['criterio_prorrateo']);

        $resultado = $this->aplicarNacionalizacion($id, $idEmpresa, $idUsuario, $importacion, $detalles, $detallesConCosto, $tg, $costoTotalNacionalizado, true);
        $this->repository->clearToken($id);
        return $resultado;
    }

    /**
     * Rechaza una importación 'pendiente_aprobacion': vuelve a 'borrador' (se
     * puede corregir y reintentar) guardando el motivo. Misma segregación de
     * funciones que aprobar().
     */
    public function rechazarNacionalizacion(int $id, int $idEmpresa, int $idUsuario, string $motivo, int $nivel = 3): array
    {
        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) {
            throw new \Exception('Importación no encontrada.');
        }
        if ($importacion['estado'] !== 'pendiente_aprobacion') {
            throw new \Exception('Solo se pueden rechazar importaciones pendientes de aprobación.');
        }
        if ($nivel < 3 && (int) ($importacion['created_by'] ?? 0) === $idUsuario) {
            throw new \Exception('No puede rechazar una importación que usted mismo solicitó nacionalizar.');
        }

        $this->repository->actualizarEstadoAprobacion($id, 'borrador', null, $motivo);
        $this->repository->clearToken($id);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'RECHAZAR_NACIONALIZACION', 'importaciones_cabecera', $id,
            ['estado' => 'pendiente_aprobacion'], ['estado' => 'borrador', 'motivo' => $motivo]
        );

        return ['id' => $id, 'estado' => 'borrador'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // APROBACIÓN DESDE EL ENLACE DEL CORREO (por token, sin sesión) — mismo
    // patrón que CargaInventarioService::getCargaPorToken/aprobarPorToken/
    // rechazarPorToken().
    // ─────────────────────────────────────────────────────────────────────

    /** Importación por token (para la página pública). Null si el token no es válido. */
    public function getImportacionPorToken(string $token): ?array
    {
        $importacion = $this->repository->getByToken($token);
        if (!$importacion) return null;
        $importacion['detalles']          = $this->repository->getDetalles((int) $importacion['id']);
        $importacion['facturas_exterior'] = $this->repository->getFacturasExterior((int) $importacion['id']);
        $importacion['gastos']            = $this->repository->getGastos((int) $importacion['id']);
        return $importacion;
    }

    public function aprobarPorToken(string $token): array
    {
        $importacion = $this->repository->getByToken($token);
        if (!$importacion) {
            throw new \Exception('Enlace inválido o ya utilizado.');
        }
        if ($importacion['estado'] !== 'pendiente_aprobacion') {
            throw new \Exception('Esta importación ya no está pendiente de aprobación (estado: ' . $importacion['estado'] . ').');
        }
        $idEmpresa = (int) $importacion['id_empresa'];
        $id        = (int) $importacion['id'];

        // Contexto de sistema para aplicar al kardex (ruta pública sin sesión de usuario).
        $_SESSION['id_empresa'] = $idEmpresa;
        $_SESSION['nivel']      = 3;
        if (!isset($_SESSION['id_usuario'])) $_SESSION['id_usuario'] = 0;

        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $res = $this->aprobarNacionalizacion($id, $idEmpresa, $aprobadaPor, 3);
        return $res + ['numero_importacion' => $importacion['numero_importacion']];
    }

    public function rechazarPorToken(string $token, string $motivo): array
    {
        $importacion = $this->repository->getByToken($token);
        if (!$importacion) {
            throw new \Exception('Enlace inválido o ya utilizado.');
        }
        if ($importacion['estado'] !== 'pendiente_aprobacion') {
            throw new \Exception('Esta importación ya no está pendiente de aprobación (estado: ' . $importacion['estado'] . ').');
        }
        $idEmpresa = (int) $importacion['id_empresa'];
        $id        = (int) $importacion['id'];
        $cfg = $this->getConfigAprobacion($idEmpresa);
        $aprobadaPor = $cfg['aprobadores'][0] ?? 0;

        $res = $this->rechazarNacionalizacion($id, $idEmpresa, $aprobadaPor, $motivo, 3);
        return $res + ['numero_importacion' => $importacion['numero_importacion']];
    }

    /**
     * Notifica por correo a los aprobadores que hay una importación pendiente.
     * Best-effort: cualquier fallo de correo no interrumpe el flujo.
     */
    private function notificarAprobadores(int $idEmpresa, int $id, array $idsAprobadores, string $token, int $creadorId): void
    {
        // Segregación: no se notifica (para aprobar) al usuario que solicitó la nacionalización.
        $idsAprobadores = array_values(array_filter($idsAprobadores, static fn($uid) => (int) $uid !== $creadorId));
        if (empty($idsAprobadores)) return;

        $usuarios = $this->repository->getNombresUsuarios($idsAprobadores);
        $correos  = array_values(array_filter(array_map(static fn($u) => trim((string) ($u['mail'] ?? '')), $usuarios)));
        if (empty($correos)) {
            $this->logService->registrar(0, $idEmpresa, 'notificar_pendiente_sin_correo', 'importaciones_cabecera', $id, null, ['aprobadores' => $idsAprobadores]);
            return;
        }

        $importacion = $this->repository->getPorId($id, $idEmpresa);
        if (!$importacion) return;

        $empRepo   = new \App\repositories\modulos\EmpresaRepository();
        $emp       = $empRepo->getEmisorConfig($idEmpresa) ?? [];
        $empNombre = $emp['nombre_comercial'] ?? ($emp['nombre'] ?? '');

        // El correo necesita una URL absoluta (con dominio); BASE_URL es solo la
        // ruta relativa del subdirectorio, no sirve fuera del navegador.
        $publicUrl = (defined('APP_URL') && APP_URL !== '') ? APP_URL : (defined('BASE_URL') ? BASE_URL : '');
        $url = rtrim($publicUrl, '/') . '/aprobar-importacion/' . $token;

        $creador = $this->repository->getNombresUsuarios([$creadorId]);

        $data = [
            'numero_importacion'        => $importacion['numero_importacion'],
            'proveedor'                 => $importacion['proveedor_nombre'],
            'costo_total_nacionalizado' => $importacion['costo_total_nacionalizado'],
            'empresa'                   => $empNombre,
            'creador'                   => $creador[0]['nombre'] ?? '',
            'url'                       => $url,
        ];

        require_once MVC_APP . '/helpers/mail.php';
        $ok = notificar_importacion_pendiente($correos, $data);

        $this->logService->registrar(0, $idEmpresa, $ok ? 'notificar_pendiente_ok' : 'notificar_pendiente_error', 'importaciones_cabecera', $id, null, [
            'correos' => $correos, 'error' => $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? null),
        ]);
    }

    /**
     * Postea cada línea al kardex, guarda los costos definitivos y genera el
     * asiento contable. Núcleo compartido entre procesarInventario() (sin
     * aprobación exigida) y aprobarNacionalizacion() (con aprobación).
     */
    private function aplicarNacionalizacion(
        int $id,
        int $idEmpresa,
        int $idUsuario,
        array $importacion,
        array $detalles,
        array $detallesConCosto,
        array $tg,
        float $costoTotalNacionalizado,
        bool $viaAprobacion = false
    ): array {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $inventarioService = new InventarioService(new InventarioRepository(), $this->logService);

            foreach ($detallesConCosto as $d) {
                $idBodega = !empty($d['id_bodega']) ? (int) $d['id_bodega'] : (int) $importacion['id_bodega_destino'];

                $idKardex = $inventarioService->ajusteManual([
                    'id_producto'     => (int) $d['id_producto'],
                    'id_bodega'       => $idBodega,
                    'tipo_movimiento' => 'entrada',
                    'referencia_tipo' => 'importacion',
                    'referencia_id'   => (int) $d['id'],
                    'costo_unitario'  => $d['costo_unitario_nacionalizado'],
                    'cantidad'        => $d['cantidad'],
                    'numero_lote'     => $d['numero_lote'] ?? null,
                    'fecha_caducidad' => $d['fecha_caducidad'] ?? null,
                    'nup'             => $d['nup'] ?? null,
                    'id_medida'       => $d['id_medida'] ?? null,
                    'observaciones'   => 'Importación #' . $importacion['numero_importacion'],
                ], $idEmpresa, $idUsuario);

                $this->repository->actualizarCostoNacionalizado(
                    (int) $d['id'],
                    (float) $d['costo_unitario_nacionalizado'],
                    (float) $d['costo_total_nacionalizado']
                );
                $this->repository->actualizarKardexDetalle((int) $d['id'], $idKardex);
            }

            $this->repository->actualizarTotales($id, [
                'subtotal_fob'                => round(array_sum(array_map(fn($d) => (float) $d['precio_total_fob'], $detalles)), 2),
                'total_gastos_capitalizables' => $tg['capitalizable_total'],
                'total_iva'                   => $tg['iva'],
                'total_isd'                   => $tg['isd'],
                'total_otros_gastos'          => $tg['otros'],
                'costo_total_nacionalizado'   => $costoTotalNacionalizado,
            ]);

            if ($viaAprobacion) {
                $this->repository->actualizarEstadoAprobacion($id, 'nacionalizada', $idUsuario);
                $this->repository->actualizarEstado($id, 'nacionalizada', $idUsuario, date('Y-m-d'));
            } else {
                $this->repository->actualizarEstado($id, 'nacionalizada', $idUsuario, date('Y-m-d'));
            }

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'NACIONALIZAR', 'importaciones_cabecera', $id,
                ['estado' => $importacion['estado']],
                ['estado' => 'nacionalizada', 'costo_total_nacionalizado' => $costoTotalNacionalizado]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo de configuración de
        // cuentas no revierte la nacionalización ya posteada al kardex (mismo patrón que Compras).
        $warning = null;
        try {
            $this->procesarAsientoContable($id, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            error_log("[Importaciones] Asiento no generado para importación $id: " . $e->getMessage());
            $warning = $e->getMessage();
        }

        return [
            'id'                        => $id,
            'costo_total_nacionalizado' => $costoTotalNacionalizado,
            'pendiente_aprobacion'      => false,
            'asiento_warning'           => $warning,
        ];
    }

    public function procesarAsientoContable(int $idImportacion, int $idEmpresa, int $idUsuario): void
    {
        $builder  = new AsientoBuilderService();
        $detalles = $builder->generarAsientoImportacion($idEmpresa, $idImportacion);
        if (empty($detalles)) {
            return;
        }

        $importacion = $this->repository->getPorId($idImportacion, $idEmpresa);

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('importacion', $idImportacion, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int) $asientoPrevio['id'] : 0;

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $importacion['fecha_nacionalizacion'] ?? date('Y-m-d'),
            'tipo_comprobante'     => 'importaciones',
            'numero_comprobante'   => '',
            'concepto'             => 'Importación #' . $importacion['numero_importacion'] . ' - Proveedor: ' . $importacion['proveedor_nombre'],
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'importacion',
            'id_referencia_origen' => $idImportacion,
            'observaciones'        => $importacion['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idImportacion, $idAsientoGenerado);
    }

    /**
     * Resuelve el código de establecimiento/punto de emisión y reserva el siguiente
     * secuencial consecutivo (mismo mecanismo que Órdenes de Compra/Traspasos:
     * SecuencialService + empresa_secuencial, tipo_documento = 'Importaciones').
     * No es un comprobante SRI: la serie solo numera por sucursal.
     */
    private function asignarSecuencial(array $data): array
    {
        $idEstablecimiento = (int) $data['id_establecimiento'];
        $idPuntoEmision    = (int) $data['id_punto_emision'];

        $serie = $this->repository->getDatosSerie($idEstablecimiento, $idPuntoEmision);
        if (!$serie) {
            throw new \Exception('No se encontraron datos del establecimiento o punto de emisión seleccionado.');
        }

        $secResult = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, 'Importaciones');

        $data['establecimiento'] = $serie['establecimiento'];
        $data['punto_emision']   = $serie['punto_emision'];
        $data['secuencial']      = $secResult['formateado'];

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PERSISTENCIA DE HIJOS
    // ─────────────────────────────────────────────────────────────────────

    private function guardarDetalles(int $idImportacion, array $detalles, int $idUsuario): void
    {
        foreach ($detalles as $det) {
            $det['id_importacion'] = $idImportacion;
            $det['id_usuario']     = $idUsuario;
            $cant   = (float) ($det['cantidad'] ?? 0);
            $precio = (float) ($det['precio_unitario_fob'] ?? 0);
            $det['precio_total_fob'] = $det['precio_total_fob'] ?? ($cant * $precio);
            $this->repository->insertDetalle($det);
        }
    }

    private function guardarFacturasExterior(int $idImportacion, array $facturas, int $idUsuario): void
    {
        foreach ($facturas as $f) {
            $f['id_importacion'] = $idImportacion;
            $f['id_usuario']     = $idUsuario;
            $this->repository->insertFacturaExterior($f);
        }
    }

    private function guardarGastos(int $idImportacion, array $gastos, int $idUsuario): void
    {
        foreach ($gastos as $g) {
            $g['id_importacion'] = $idImportacion;
            $g['id_usuario']     = $idUsuario;
            // Un gasto vinculado siempre es capitalizable (su propio documento ya
            // resolvió el IVA/CxP; aquí no puede ser un rubro tipo IVA/ISD).
            if (($g['origen'] ?? 'dai_manual') !== 'dai_manual') {
                $g['prorrateable'] = true;
            }
            $this->repository->insertGasto($g);
        }
    }

    /**
     * Verifica que las Compras/Liquidaciones vinculadas existan y sean de la misma
     * empresa (evita vincular documentos ajenos o inexistentes).
     */
    private function validarGastosVinculados(array $data): void
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        foreach ($data['gastos'] ?? [] as $idx => $g) {
            $origen = $g['origen'] ?? 'dai_manual';
            $num = $idx + 1;
            if ($origen === 'compra_vinculada') {
                $compra = $this->repository->getCompraParaVincular((int) ($g['id_compra'] ?? 0), $idEmpresa);
                if (!$compra) {
                    throw new \Exception("El gasto #{$num} referencia una Compra que no existe en esta empresa.");
                }
            } elseif ($origen === 'liquidacion_vinculada') {
                $liq = $this->repository->getLiquidacionParaVincular((int) ($g['id_liquidacion_compra'] ?? 0), $idEmpresa);
                if (!$liq) {
                    throw new \Exception("El gasto #{$num} referencia una Liquidación de Compra que no existe en esta empresa.");
                }
            }
        }
    }
}

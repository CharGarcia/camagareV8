<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ComprasRepository;
use App\repositories\modulos\OrdenCompraRepository;
use App\Rules\modulos\ComprasRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PeriodosContablesService;
use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\core\Database;

class ComprasService
{
    private ComprasRepository $repository;
    private OrdenCompraRepository $ordenCompraRepo;
    private ComprasRules $rules;
    private LogSistemaService $logService;
    private PeriodosContablesService $periodosService;
    private ?string $lastAsientoWarning = null;
    private ?AprobacionesService $aprobService = null;
    private ?\App\repositories\modulos\EmpresaRepository $empRepo = null;

    /** Estado de una compra que espera autorización (checkpoint 'aprobacion_compras'). */
    public const ESTADO_PENDIENTE = 'pendiente_aprobacion';
    public const ESTADO_REGISTRADO = 'registrado';
    public const ESTADO_RECHAZADA = 'rechazada';

    public function __construct()
    {
        $this->repository = new ComprasRepository();
        $this->ordenCompraRepo = new OrdenCompraRepository();
        $this->rules = new ComprasRules();
        $this->logService = new LogSistemaService();

        // Inicialización manual de dependencias del servicio de periodos
        $periodosRepo = new PeriodosContablesRepository();
        $periodosRules = new PeriodosContablesRules();
        $this->periodosService = new PeriodosContablesService($periodosRepo, $periodosRules, $this->logService);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data);
        $this->validarPeriodo($data, 'No se puede registrar la compra porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            // El documento del proveedor no trae "nuestro" establecimiento; si no viene ya
            // resuelto (ej. desde el registro automático del SRI), se atribuye al
            // establecimiento activo de la empresa (mismo criterio que el registro por XML).
            if (empty($data['id_establecimiento'])) {
                $data['id_establecimiento'] = (new \App\repositories\modulos\EmpresaRepository())->getPrimerEstablecimientoId($idEmpresa);
            }

            $data = $this->calcularTotales($data);

            // ¿La empresa exige aprobar las compras? Se consulta con el importe
            // total para respetar el monto mínimo configurado en el módulo
            // Aprobaciones. Si la exige, la compra nace pendiente: no se puede
            // pagar, ni procesar su inventario, ni se genera su asiento.
            //
            // Solo aplica a los documentos que crean obligación de pago: factura
            // (01) y liquidación de compra (03). Las notas de crédito (04) y
            // débito (05) comparten esta tabla pero son ajustes del saldo, y
            // dejarlas pendientes descuadraría la cartera (los cálculos de saldo
            // restan las NC sin mirar su estado).
            $cfgAprob = ['requiere' => false, 'aprobadores' => []];
            if (in_array((string) ($data['tipo_comprobante'] ?? '01'), ['01', '03'], true)) {
                $cfgAprob = $this->getConfigAprobacion($idEmpresa, (float) ($data['importe_total'] ?? 0));
            }
            $data['estado'] = $cfgAprob['requiere'] ? self::ESTADO_PENDIENTE : self::ESTADO_REGISTRADO;

            $idCompra = $this->repository->insertCabecera($data);

            $this->sincronizarDetalles($idCompra, $data['detalles'] ?? []);
            $this->guardarPagos($idCompra, $data['pagos'] ?? []);
            $this->guardarAdicionales($idCompra, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'CREAR', 'compras_cabecera', $idCompra,
                null, ['id_compra' => $idCompra, 'total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($idCompra, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            $this->relanzarSiDuplicado($e);
            throw $e;
        }

        if (($data['estado'] ?? '') === self::ESTADO_PENDIENTE) {
            // Pendiente: no se asienta todavía (el asiento lo genera la aprobación).
            // El token permite aprobar desde el enlace del correo, sin sesión.
            $token = bin2hex(random_bytes(24));
            $this->repository->setTokenAprobacion($idCompra, $token);
            try {
                $this->notificarAprobadores($idEmpresa, $idCompra, $cfgAprob['aprobadores'], $token, $idUsuario);
            } catch (\Throwable $e) {
                // Un fallo de correo no puede tumbar la compra ya guardada; queda
                // igualmente pendiente y visible en el listado.
            }
            return $idCompra;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($idCompra, $data);
        return $idCompra;
    }

    // ─── Aprobación de la compra (checkpoint 'aprobacion_compras') ─────────────

    private function aprobacionesService(): AprobacionesService
    {
        if ($this->aprobService === null) {
            $this->aprobService = new AprobacionesService();
        }
        return $this->aprobService;
    }

    private function empresaRepo(): \App\repositories\modulos\EmpresaRepository
    {
        if ($this->empRepo === null) {
            $this->empRepo = new \App\repositories\modulos\EmpresaRepository();
        }
        return $this->empRepo;
    }

    /**
     * @param float|null $monto Importe total de la compra. Si el checkpoint tiene
     *                          monto mínimo, por debajo de él no pide aprobación.
     */
    public function getConfigAprobacion(int $idEmpresa, ?float $monto = null): array
    {
        return $this->aprobacionesService()->getConfigResuelta(
            AprobacionesService::COMPRAS,
            $idEmpresa,
            $monto
        );
    }

    /** ¿El usuario puede aprobar compras? (aprobador configurado o super admin). */
    public function esAprobador(int $idUsuario, int $idEmpresa, int $nivel = 1): bool
    {
        return $this->aprobacionesService()->esAprobador(
            AprobacionesService::COMPRAS,
            $idEmpresa,
            $idUsuario,
            $nivel
        );
    }

    /** Nombres de los aprobadores configurados (para mostrar quién debe aprobar). */
    public function getAprobadoresNombres(int $idEmpresa): array
    {
        $cfg = $this->getConfigAprobacion($idEmpresa);
        if (empty($cfg['aprobadores'])) return [];
        return array_column($this->repository->getNombresUsuarios($cfg['aprobadores']), 'nombre');
    }

    public function getPorTokenAprobacion(string $token): ?array
    {
        $compra = $this->repository->getPorTokenAprobacion($token);
        // El token solo sirve mientras la compra siga esperando: una vez resuelta
        // el enlace del correo deja de ser válido.
        return ($compra && $compra['estado'] === self::ESTADO_PENDIENTE) ? $compra : null;
    }

    public function contarPendientesAprobacion(int $idEmpresa): int
    {
        return $this->repository->contarPendientesAprobacion($idEmpresa);
    }

    /**
     * Aprueba la compra: pasa a 'registrado' y recién entonces se genera su
     * asiento contable (a partir de ahí ya se puede pagar y procesar inventario).
     *
     * @param bool $auto true cuando la aprobación viene del enlace del correo con
     *                   token válido (la autorización ya la dio el token).
     */
    public function aprobarCompra(int $idCompra, int $idEmpresa, int $idUsuario, bool $auto = false, int $nivel = 1): array
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \InvalidArgumentException('Compra no encontrada.');
        }
        if (($compra['estado'] ?? '') !== self::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Esta compra ya no está pendiente de aprobación.');
        }
        if (!$auto && !$this->esAprobador($idUsuario, $idEmpresa, $nivel)) {
            throw new \InvalidArgumentException('No está autorizado para aprobar compras.');
        }
        // Segregación de funciones: quien registra la compra no la aprueba (salvo super admin).
        if (!$auto && $nivel < 3 && (int) ($compra['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede aprobar una compra que usted mismo registró. Debe aprobarla otro usuario autorizado.');
        }

        $this->repository->resolverAprobacion($idCompra, self::ESTADO_REGISTRADO, $idUsuario);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'APROBAR_COMPRA', 'compras_cabecera', $idCompra,
            ['estado' => self::ESTADO_PENDIENTE],
            ['estado' => self::ESTADO_REGISTRADO, 'total' => $compra['importe_total'] ?? 0]
        );

        // El asiento se posponía mientras estaba pendiente: se genera ahora.
        $numDoc = ($compra['establecimiento_prov'] ?? '') . '-'
                . ($compra['punto_emision_prov'] ?? '') . '-'
                . ($compra['secuencial_prov'] ?? '');
        try {
            $this->procesarAsientoContable($idCompra, $compra, $numDoc);
        } catch (\Throwable $e) {
            // Igual que en el registro directo: un fallo contable no revierte la
            // aprobación; queda el aviso para regenerar el asiento.
            $this->lastAsientoWarning = $e->getMessage();
        }

        return ['estado' => self::ESTADO_REGISTRADO, 'aviso_asiento' => $this->lastAsientoWarning];
    }

    /**
     * Rechaza la compra. No se elimina: queda registrada como 'rechazada' con el
     * motivo, para que quede rastro de que el documento llegó y se decidió no
     * aceptarlo. Sigue sin poder pagarse ni asentarse.
     */
    public function rechazarCompra(int $idCompra, int $idEmpresa, int $idUsuario, string $motivo, bool $auto = false, int $nivel = 1): array
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \InvalidArgumentException('Compra no encontrada.');
        }
        if (($compra['estado'] ?? '') !== self::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Esta compra ya no está pendiente de aprobación.');
        }
        if (!$auto && !$this->esAprobador($idUsuario, $idEmpresa, $nivel)) {
            throw new \InvalidArgumentException('No está autorizado para aprobar compras.');
        }
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Indique el motivo del rechazo.');
        }

        $this->repository->resolverAprobacion($idCompra, self::ESTADO_RECHAZADA, $idUsuario, $motivo);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'RECHAZAR_COMPRA', 'compras_cabecera', $idCompra,
            ['estado' => self::ESTADO_PENDIENTE],
            ['estado' => self::ESTADO_RECHAZADA, 'motivo' => $motivo]
        );

        return ['estado' => self::ESTADO_RECHAZADA];
    }

    /**
     * Notifica en UN solo correo varias compras que quedaron pendientes a la vez.
     * Lo usa el registro automático desde el SRI, que procesa un lote de XML: un
     * correo por comprobante sería inmanejable.
     *
     * @param array $pendientes Filas [id, id_empresa, token] de esta corrida.
     */
    public function notificarLotePendiente(array $pendientes): void
    {
        if (empty($pendientes)) return;

        // El lote siempre es de una misma empresa (el registro va por empresa activa).
        $idEmpresa = (int) ($pendientes[0]['id_empresa'] ?? 0);
        if (!$idEmpresa) return;

        $cfg = $this->getConfigAprobacion($idEmpresa);
        $correos = $this->correosAprobadores($cfg['aprobadores'], 0);
        if (empty($correos)) {
            $this->logService->registrar(0, $idEmpresa, 'notificar_compra_sin_correo', 'compras_cabecera', null, null, ['aprobadores' => $cfg['aprobadores']]);
            return;
        }

        $publicUrl = $this->urlPublica();
        $filas = [];
        foreach ($pendientes as $p) {
            $compra = $this->repository->getPorId((int) $p['id'], $idEmpresa);
            if (!$compra) continue;
            $filas[] = [
                'numero'    => ($compra['establecimiento_prov'] ?? '') . '-'
                             . ($compra['punto_emision_prov'] ?? '') . '-'
                             . ($compra['secuencial_prov'] ?? ''),
                'proveedor' => $compra['proveedor_nombre'] ?? '',
                'fecha'     => !empty($compra['fecha_emision']) ? date('d-m-Y', strtotime($compra['fecha_emision'])) : '',
                'total'     => number_format((float) ($compra['importe_total'] ?? 0), 2),
                'url'       => $publicUrl . '/aprobar-compra/' . $p['token'],
            ];
        }
        if (empty($filas)) return;

        $emp = $this->empresaRepo()->getEmisorConfig($idEmpresa) ?? [];

        require_once MVC_APP . '/helpers/mail.php';
        $ok = notificar_compras_pendientes_lote($correos, [
            'empresa' => $emp['nombre_comercial'] ?? ($emp['nombre'] ?? ''),
            'compras' => $filas,
        ]);

        $this->logService->registrar(
            0, $idEmpresa,
            $ok ? 'notificar_compra_ok' : 'notificar_compra_error',
            'compras_cabecera', null, null,
            ['correos' => $correos, 'compras' => count($filas), 'error' => $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? null)]
        );
    }

    /** URL absoluta del sistema (BASE_URL es relativa y no sirve en un correo). */
    private function urlPublica(): string
    {
        $url = (defined('APP_URL') && APP_URL !== '') ? APP_URL : (defined('BASE_URL') ? BASE_URL : '');
        return rtrim($url, '/');
    }

    /**
     * Correos de los aprobadores, excluyendo a quien registró el documento
     * (segregación de funciones). Con $creadorId = 0 no excluye a nadie.
     */
    private function correosAprobadores(array $idsAprobadores, int $creadorId): array
    {
        $ids = array_values(array_filter($idsAprobadores, static fn($id) => (int) $id !== $creadorId));
        if (empty($ids)) return [];

        return array_values(array_filter(array_map(
            static fn($u) => trim((string) ($u['mail'] ?? '')),
            $this->repository->getNombresUsuarios($ids)
        )));
    }

    /** Notifica por correo a los aprobadores que hay una compra esperando. */
    private function notificarAprobadores(int $idEmpresa, int $idCompra, array $idsAprobadores, ?string $token, int $creadorId): void
    {
        // Segregación: no se pide aprobación a quien registró la compra.
        $correos = $this->correosAprobadores($idsAprobadores, $creadorId);
        if (empty($correos)) {
            $this->logService->registrar(0, $idEmpresa, 'notificar_compra_sin_correo', 'compras_cabecera', $idCompra, null, ['aprobadores' => $idsAprobadores]);
            return;
        }

        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) return;

        $emp = $this->empresaRepo()->getEmisorConfig($idEmpresa) ?? [];

        $publicUrl = $this->urlPublica();
        $url = $token ? ($publicUrl . '/aprobar-compra/' . $token) : ($publicUrl . '/modulos/compras');

        require_once MVC_APP . '/helpers/mail.php';
        $ok = notificar_compra_pendiente($correos, [
            'numero'    => ($compra['establecimiento_prov'] ?? '') . '-'
                         . ($compra['punto_emision_prov'] ?? '') . '-'
                         . ($compra['secuencial_prov'] ?? ''),
            'proveedor' => $compra['proveedor_nombre'] ?? '',
            'fecha'     => !empty($compra['fecha_emision']) ? date('d-m-Y', strtotime($compra['fecha_emision'])) : '',
            'total'     => number_format((float) ($compra['importe_total'] ?? 0), 2),
            'empresa'   => $emp['nombre_comercial'] ?? ($emp['nombre'] ?? ''),
            'creador'   => $compra['usuario_nombre'] ?? '',
            'url'       => $url,
        ]);

        $this->logService->registrar(
            0, $idEmpresa,
            $ok ? 'notificar_compra_ok' : 'notificar_compra_error',
            'compras_cabecera', $idCompra, null,
            ['correos' => $correos, 'error' => $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? null)]
        );
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            return null;
        }

        $compra['detalles']    = $this->repository->getDetalles($id);
        $compra['pagos']       = $this->repository->getPagos($id);
        $compra['adicionales'] = $this->repository->getInfoAdicional($id);

        // Formatear adicionales para que sea un objeto clave:valor
        $adicionales = [];
        foreach ($compra['adicionales'] as $adj) {
            $adicionales[$adj['nombre']] = $adj['valor'];
        }
        $compra['adicionales'] = $adicionales;

        // Cargar impuestos de cada detalle
        foreach ($compra['detalles'] as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int)$det['id']);
        }

        $compra['egresos_vinculados'] = $this->repository->getEgresosVinculados($id);

        // Detalle de "Factura de Reembolso" recibida (bloque <reembolsos> del XML, solo si aplica).
        $terceros = $this->repository->getReembolsoTerceros($id);
        foreach ($terceros as &$t) {
            $t['impuestos'] = $this->repository->getImpuestosReembolsoTercero((int) $t['id']);
        }
        unset($t);
        $compra['terceros_reembolso'] = $terceros;

        return $compra;
    }

    /** Órdenes de compra del mismo proveedor disponibles para vincular con esta compra (admite varias entregas parciales). */
    public function buscarOrdenesAbiertas(int $idProveedor, int $idEmpresa): array
    {
        return $this->ordenCompraRepo->getAbiertasPorProveedor($idProveedor, $idEmpresa);
    }

    /**
     * Vincula una compra con una orden de compra del mismo proveedor (admite varias
     * compras contra la misma orden, para entregas parciales del proveedor). El estado
     * de la orden se recalcula según lo acumulado: Recibido Parcial si falta saldo,
     * Recibido si ya se cubrió todo. Reversible con desvincularOrden().
     */
    public function vincularOrden(int $idCompra, int $idEmpresa, int $idOrdenCompra, int $idUsuario): void
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }
        $orden = $this->ordenCompraRepo->getById($idOrdenCompra, $idEmpresa);
        if (!$orden) {
            throw new \Exception('Orden de compra no encontrada.');
        }
        if ((int) $orden['id_proveedor'] !== (int) $compra['id_proveedor']) {
            throw new \Exception('La orden de compra pertenece a otro proveedor.');
        }
        if (!in_array($orden['estado'], ['aprobado', 'parcial'], true)) {
            throw new \Exception('La orden de compra debe estar Aprobada o Recibida Parcialmente para vincularla (estado actual: ' . $orden['estado'] . ').');
        }
        if ((int) ($compra['id_orden_compra'] ?? 0) === $idOrdenCompra) {
            throw new \Exception('Esta compra ya está vinculada a esa orden.');
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repository->vincularOrdenCompra($idCompra, $idEmpresa, $idOrdenCompra, $idUsuario);
            $this->_recomputarEstadoOrden($idOrdenCompra, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'VINCULAR_ORDEN', 'compras_cabecera', $idCompra,
                ['id_orden_compra' => null], ['id_orden_compra' => $idOrdenCompra]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Quita el vínculo de esta compra con su orden y recalcula el estado de la orden a
     * partir de las compras que le queden vinculadas (puede quedar Aprobada si ya no
     * tiene ninguna, o seguir Recibida Parcial/Recibida si aún tiene otras).
     */
    public function desvincularOrden(int $idCompra, int $idEmpresa, int $idUsuario): void
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }
        $idOrdenCompra = (int) ($compra['id_orden_compra'] ?? 0);
        if (!$idOrdenCompra) {
            return;
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repository->vincularOrdenCompra($idCompra, $idEmpresa, null, $idUsuario);
            $this->_recomputarEstadoOrden($idOrdenCompra, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'DESVINCULAR_ORDEN', 'compras_cabecera', $idCompra,
                ['id_orden_compra' => $idOrdenCompra], ['id_orden_compra' => null]
            );

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Cierra manualmente una orden en Recibido Parcial cuando el proveedor ya no va a
     * entregar el saldo pendiente ("short close", igual que en los ERPs). La deja en
     * Recibido con cierre_forzado=true, para distinguirla de una recibida por cantidades.
     */
    public function cerrarOrdenManual(int $idCompra, int $idEmpresa, int $idUsuario): void
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }
        $idOrdenCompra = (int) ($compra['id_orden_compra'] ?? 0);
        if (!$idOrdenCompra) {
            throw new \Exception('Esta compra no está vinculada a ninguna orden de compra.');
        }
        $orden = $this->ordenCompraRepo->getById($idOrdenCompra, $idEmpresa);
        if (!$orden) {
            throw new \Exception('Orden de compra no encontrada.');
        }
        if ($orden['estado'] !== 'parcial') {
            throw new \Exception('Solo se puede cerrar manualmente una orden en Recibido Parcial (estado actual: ' . $orden['estado'] . ').');
        }

        $this->ordenCompraRepo->cambiarEstado($idOrdenCompra, $idEmpresa, 'recibido', $idUsuario, true, true);
        $this->logService->registrar(
            $idUsuario, $idEmpresa, 'CERRAR_ORDEN_MANUAL', 'ordenes_compra', $idOrdenCompra,
            ['estado' => 'parcial'], ['estado' => 'recibido', 'cierre_forzado' => true]
        );
    }

    /**
     * Recalcula el estado de una orden a partir de lo acumulado en TODAS las compras que
     * tenga vinculadas actualmente: Aprobado si no hay nada recibido, Recibido Parcial si
     * falta saldo en alguna línea (con producto identificado), Recibido si todas están
     * cubiertas. Las líneas sin producto vinculado no se pueden verificar automáticamente
     * y se ignoran para esta cuenta (quedan visibles aparte en compararConOrden()).
     */
    private function _recomputarEstadoOrden(int $idOrdenCompra, int $idEmpresa, int $idUsuario): void
    {
        $comprasVinculadas = $this->ordenCompraRepo->getComprasVinculadas($idOrdenCompra, $idEmpresa);
        if (empty($comprasVinculadas)) {
            // Se desvinculó la última compra: sin nada vinculado, vuelve a Aprobado.
            $this->ordenCompraRepo->cambiarEstado($idOrdenCompra, $idEmpresa, 'aprobado', $idUsuario, false, false);
            return;
        }

        $lineasOrden = $this->ordenCompraRepo->getDetalle($idOrdenCompra, $idEmpresa);
        $recibido    = $this->ordenCompraRepo->getRecibidoAcumuladoPorProducto($idOrdenCompra, $idEmpresa);

        $totalRecibido = 0.0;
        $completo = true;
        $huboLineaVerificable = false;
        foreach ($lineasOrden as $l) {
            $idProd = (int) ($l['id_producto'] ?? 0);
            if ($idProd <= 0) {
                continue; // no se puede rastrear automáticamente
            }
            $huboLineaVerificable = true;
            $cantPedida   = (float) ($l['cantidad'] ?? 0);
            $cantRecibida = $recibido[$idProd] ?? 0.0;
            $totalRecibido += min($cantRecibida, $cantPedida);
            if ($cantRecibida + 0.001 < $cantPedida) {
                $completo = false;
            }
        }

        if (!$huboLineaVerificable) {
            // Ningún ítem de la orden está vinculado a un producto del catálogo: no hay
            // forma de saber cuánto llegó. En cuanto tiene alguna compra vinculada, se da
            // por recibida completa (no se puede distinguir "parcial" en este caso).
            $nuevoEstado = 'recibido';
        } elseif ($totalRecibido <= 0.0) {
            $nuevoEstado = 'aprobado';
        } elseif ($completo) {
            $nuevoEstado = 'recibido';
        } else {
            $nuevoEstado = 'parcial';
        }

        $this->ordenCompraRepo->cambiarEstado($idOrdenCompra, $idEmpresa, $nuevoEstado, $idUsuario, $nuevoEstado !== 'aprobado', false);
    }

    /**
     * Compara línea a línea lo pedido (orden de compra) contra lo facturado, agrupando
     * por producto del catálogo. A diferencia de una comparación 1 a 1, "lo facturado"
     * es la SUMA de todas las compras que estén vinculadas a esa orden — una orden puede
     * recibirse en varias entregas parciales, así que la comparación siempre es del total
     * acumulado, no solo de esta compra. El emparejamiento usa id_producto de cada lado;
     * en cada compra, cuando la línea no está vinculada directamente a un producto, se
     * resuelve por la homologación código-proveedor → producto que ya calcula
     * ComprasRepository::getDetalles() (id_producto_vinculado). Líneas sin producto
     * resuelto en algún lado quedan aparte, sin comparar por texto.
     */
    public function compararConOrden(int $idCompra, int $idEmpresa): array
    {
        $compra = $this->repository->getPorId($idCompra, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }
        $idOrdenCompra = (int) ($compra['id_orden_compra'] ?? 0);
        if (!$idOrdenCompra) {
            return ['vinculada' => false];
        }

        $orden = $this->ordenCompraRepo->getById($idOrdenCompra, $idEmpresa);
        if (!$orden) {
            return ['vinculada' => false];
        }

        $comprasVinculadas = $this->ordenCompraRepo->getComprasVinculadas($idOrdenCompra, $idEmpresa);

        $lineasOrden  = $this->ordenCompraRepo->getDetalle($idOrdenCompra, $idEmpresa);
        $lineasCompra = [];
        foreach ($comprasVinculadas as $cv) {
            foreach ($this->repository->getDetalles((int) $cv['id']) as $linea) {
                $lineasCompra[] = $linea;
            }
        }

        $agrupar = function (array $lineas, string $kProducto, string $kDescripcion, string $kCantidad, string $kPrecio) {
            $agg = [];
            $sinProducto = [];
            foreach ($lineas as $l) {
                $idProd   = (int) ($l[$kProducto] ?? 0);
                $cantidad = (float) ($l[$kCantidad] ?? 0);
                $precio   = (float) ($l[$kPrecio] ?? 0);
                if ($idProd <= 0) {
                    $sinProducto[] = ['descripcion' => (string) ($l[$kDescripcion] ?? ''), 'cantidad' => $cantidad, 'precio_unitario' => $precio];
                    continue;
                }
                if (!isset($agg[$idProd])) {
                    $agg[$idProd] = ['descripcion' => (string) ($l[$kDescripcion] ?? ''), 'cantidad' => 0.0, 'subtotal' => 0.0];
                }
                $agg[$idProd]['cantidad']  += $cantidad;
                $agg[$idProd]['subtotal']  += $cantidad * $precio;
            }
            return [$agg, $sinProducto];
        };

        [$aggOrden, $sinProductoOrden]   = $agrupar($lineasOrden, 'id_producto', 'descripcion', 'cantidad', 'precio_unitario');
        [$aggCompra, $sinProductoCompra] = $agrupar($lineasCompra, 'id_producto_vinculado', 'producto_nombre', 'cantidad', 'precio_unitario');

        $idsProducto = array_unique(array_merge(array_keys($aggOrden), array_keys($aggCompra)));
        $filas = [];
        foreach ($idsProducto as $idProd) {
            $o = $aggOrden[$idProd]  ?? null;
            $c = $aggCompra[$idProd] ?? null;

            $cantPedida     = $o ? $o['cantidad'] : 0.0;
            $cantFacturada  = $c ? $c['cantidad'] : 0.0;
            $precioPedido   = $o && $o['cantidad'] > 0 ? $o['subtotal'] / $o['cantidad'] : 0.0;
            $precioFacturado = $c && $c['cantidad'] > 0 ? $c['subtotal'] / $c['cantidad'] : 0.0;

            $estado = 'ok';
            if (!$o) {
                $estado = 'extra';       // facturado pero no estaba en la orden
            } elseif (!$c) {
                $estado = 'pendiente';   // pedido pero aún no facturado
            } elseif (abs($cantFacturada - $cantPedida) > 0.001 || abs($precioFacturado - $precioPedido) > 0.005) {
                $estado = 'diferencia';
            }

            $filas[] = [
                'descripcion'       => $o['descripcion'] ?? $c['descripcion'] ?? '',
                'cantidad_pedida'   => $cantPedida,
                'cantidad_facturada'=> $cantFacturada,
                'precio_pedido'     => round($precioPedido, 4),
                'precio_facturado'  => round($precioFacturado, 4),
                'estado'            => $estado,
            ];
        }
        usort($filas, fn($a, $b) => strcmp($a['descripcion'], $b['descripcion']));

        $comprasVinculadas = array_map(fn($cv) => [
            'id'            => $cv['id'],
            'numero'        => $cv['numero'],
            'fecha_emision' => $cv['fecha_emision'],
            'importe_total' => $cv['importe_total'],
            'es_esta'       => (int) $cv['id'] === $idCompra,
        ], $comprasVinculadas);

        return [
            'vinculada'            => true,
            'orden'                => [
                'id'              => $orden['id'],
                'numero_orden'    => $orden['numero_orden'],
                'fecha_orden'     => $orden['fecha_orden'],
                'fecha_recepcion' => $orden['fecha_recepcion'],
                'estado'          => $orden['estado'],
                'cierre_forzado'  => (bool) ($orden['cierre_forzado'] ?? false),
            ],
            'filas'                => $filas,
            'sin_producto_orden'   => $sinProductoOrden,
            'sin_producto_compra'  => $sinProductoCompra,
            'compras_vinculadas'   => $comprasVinculadas,
        ];
    }

    /**
     * Flags de solo lectura de una compra para el modal:
     *  - es_migrado: proviene de una migración (no editable).
     *  - periodo_cerrado: su fecha cae en un período contable cerrado (no editable).
     */
    public function getFlagsSoloLectura(int $id, int $idEmpresa, ?string $fecha): array
    {
        return [
            'es_migrado'      => $this->repository->esMigrado($id, $idEmpresa),
            'periodo_cerrado' => $this->periodosService->esFechaEnPeriodoCerrado($fecha, $idEmpresa),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARSEO DEL XML (para generar el PDF a partir del comprobante electrónico)
    // ─────────────────────────────────────────────────────────────────────────

    /** Nombres de formas de pago SRI (código => descripción). */
    private const FORMAS_PAGO_SRI = [
        '01' => 'Sin utilización del sistema financiero',
        '15' => 'Compensación de deudas',
        '16' => 'Tarjeta de débito',
        '17' => 'Dinero electrónico',
        '18' => 'Tarjeta prepago',
        '19' => 'Tarjeta de crédito',
        '20' => 'Otros con utilización del sistema financiero',
        '21' => 'Endoso de títulos',
    ];

    /**
     * Convierte el XML autorizado de un comprobante de compra en las estructuras
     * que consume ComprasPdfService: cabecera, detalles, pagos, infoAdicional y
     * los datos del adquirente (comprador). Lanza excepción si el XML es inválido.
     */
    public function parsearComprobanteXml(string $xmlString): array
    {
        $xmlString = trim($xmlString);
        if ($xmlString === '') {
            throw new \RuntimeException('El comprobante no tiene XML.');
        }

        // La fecha de autorización solo existe en el sobre <autorizacion> del SRI
        // (no dentro del comprobante). Se captura ANTES de quitar el sobre.
        $fechaAutorizacion = '';
        if (strpos($xmlString, '<autorizacion>') !== false) {
            if (preg_match('#<fechaAutorizacion>(.*?)</fechaAutorizacion>#s', $xmlString, $mfa)) {
                $fechaAutorizacion = trim($mfa[1]);
            }
            // Quitar el sobre de autorización para quedarnos con el comprobante.
            if (preg_match('/<comprobante><!\[CDATA\[(.*?)\]\]><\/comprobante>/s', $xmlString, $m)) {
                $xmlString = $m[1];
            } elseif (preg_match('/<comprobante>(.*?)<\/comprobante>/s', $xmlString, $m)) {
                $xmlString = htmlspecialchars_decode($m[1]);
            }
        }

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlString);
        libxml_use_internal_errors($prev);
        if ($xml === false || !isset($xml->infoTributaria)) {
            throw new \RuntimeException('El XML del comprobante no tiene un formato válido del SRI.');
        }

        $t      = $xml->infoTributaria;
        $codDoc = (string) $t->codDoc;

        // Nodo de información según tipo de documento.
        $nodoInfo = match ($codDoc) {
            '01'    => 'infoFactura',
            '03'    => 'infoLiquidacionCompra',
            '04'    => 'infoNotaCredito',
            '05'    => 'infoNotaDebito',
            default => 'infoFactura',
        };
        $info = $xml->$nodoInfo ?? null;
        if ($info === null) {
            // Buscar el primer nodo "info..." distinto de infoTributaria/infoAdicional.
            foreach ($xml->children() as $child) {
                $n = $child->getName();
                if (str_starts_with($n, 'info') && $n !== 'infoTributaria' && $n !== 'infoAdicional') {
                    $info = $child;
                    break;
                }
            }
        }
        if ($info === null) {
            throw new \RuntimeException('El XML no contiene la información del comprobante.');
        }

        // ── Cabecera (emisor = proveedor; número y autorización del documento) ──
        $cabecera = [
            'tipo_comprobante'         => $codDoc,
            'establecimiento_prov'     => (string) $t->estab,
            'punto_emision_prov'       => (string) $t->ptoEmi,
            'secuencial_prov'          => (string) $t->secuencial,
            'numero_autorizacion'      => (string) $t->claveAcceso,
            'fecha_autorizacion'       => $fechaAutorizacion,
            'tipo_ambiente'            => (string) $t->ambiente,
            'fecha_emision'            => (string) ($info->fechaEmision ?? ''),
            'proveedor_nombre'         => (string) $t->razonSocial,
            'proveedor_ruc'            => (string) $t->ruc,
            'proveedor_direccion'      => (string) ($t->dirMatriz ?? ''),
            'proveedor_email'          => '',
            'proveedor_nombre_tipo_id' => 'R.U.C.',
            'total_sin_impuestos'      => (float) ($info->totalSinImpuestos ?? 0),
            'importe_total'            => (float) ($info->importeTotal ?? $info->valorModificacion ?? 0),
            'propina'                  => (float) ($info->propina ?? 0),
        ];

        // ── Adquirente (comprador / receptor) ─────────────────────────────────
        $comprador = [
            'nombre'    => (string) ($info->razonSocialComprador ?? ''),
            'ruc'       => (string) ($info->identificacionComprador ?? ''),
            'direccion' => (string) ($info->direccionComprador ?? ''),
        ];

        // ── Detalles ──────────────────────────────────────────────────────────
        $detalles = [];
        // El nodo de detalle puede ser <detalle> (factura) o venir bajo <detalles>.
        $listaDet = $xml->detalles->detalle ?? [];
        foreach ($listaDet as $d) {
            $impuestos = [];
            if (isset($d->impuestos->impuesto)) {
                foreach ($d->impuestos->impuesto as $imp) {
                    $impuestos[] = [
                        'codigo_impuesto'   => (string) $imp->codigo,
                        'codigo_porcentaje' => (string) $imp->codigoPorcentaje,
                        'tarifa'            => (float) $imp->tarifa,
                        'base_imponible'    => (float) $imp->baseImponible,
                        'valor'             => (float) $imp->valor,
                    ];
                }
            }
            $detalles[] = [
                'codigo_principal'          => (string) ($d->codigoPrincipal ?? ''),
                'descripcion'               => (string) ($d->descripcion ?? ''),
                'cantidad'                  => (float) ($d->cantidad ?? 0),
                'precio_unitario'           => (float) ($d->precioUnitario ?? 0),
                'descuento'                 => (float) ($d->descuento ?? 0),
                'precio_total_sin_impuesto' => (float) ($d->precioTotalSinImpuesto ?? 0),
                'impuestos'                 => $impuestos,
            ];
        }

        // ── Pagos ─────────────────────────────────────────────────────────────
        $pagos = [];
        if (isset($info->pagos->pago)) {
            foreach ($info->pagos->pago as $p) {
                $codFp = (string) $p->formaPago;
                $pagos[] = [
                    'forma_pago'        => $codFp,
                    'forma_pago_nombre' => self::FORMAS_PAGO_SRI[$codFp] ?? $codFp,
                    'total'             => (float) $p->total,
                    'plazo'             => (int) ($p->plazo ?? 0),
                    'unidad_tiempo'     => (string) ($p->unidadTiempo ?? 'dias'),
                ];
            }
        }

        // ── Información adicional ──────────────────────────────────────────────
        $infoAdicional = [];
        if (isset($xml->infoAdicional->campoAdicional)) {
            foreach ($xml->infoAdicional->campoAdicional as $campo) {
                $infoAdicional[] = [
                    'nombre' => (string) $campo['nombre'],
                    'valor'  => (string) $campo,
                ];
            }
        }

        return [
            'cabecera'      => $cabecera,
            'detalles'      => $detalles,
            'pagos'         => $pagos,
            'infoAdicional' => $infoAdicional,
            'comprador'     => $comprador,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASIENTO CONTABLE
    // ─────────────────────────────────────────────────────────────────────────

    public function getLastAsientoWarning(): ?string
    {
        return $this->lastAsientoWarning;
    }

    /**
     * Genera el asiento contable tras guardar una compra (fuera de la transacción principal).
     * Un fallo —p. ej. cuentas sin configurar— no revierte la compra: solo se registra el aviso.
     */
    private function generarAsientoTrasGuardar(int $idCompra, array $data): void
    {
        $this->lastAsientoWarning = null;
        try {
            $numDoc = ($data['establecimiento_prov'] ?? '') . '-'
                    . ($data['punto_emision_prov'] ?? '') . '-'
                    . ($data['secuencial_prov'] ?? '');
            $this->procesarAsientoContable($idCompra, $data, $numDoc);
        } catch (\Throwable $e) {
            error_log("[Compras] Asiento no generado para compra $idCompra: " . $e->getMessage());
            $this->lastAsientoWarning = $e->getMessage();
        }
    }

    /**
     * Punto de entrada del sincronizador (Estados Financieros) para compras sin asiento.
     */
    public function procesarAsientoContablePorSincronizacion(int $idCompra): void
    {
        $cabecera = $this->repository->getPorId($idCompra);
        if (!$cabecera) return;

        // Una compra que espera aprobación (o que fue rechazada) todavía no debe
        // llegar a la contabilidad: su asiento lo genera la aprobación. Sin esto,
        // la sincronización masiva de asientos lo crearía por detrás y anularía
        // el sentido del checkpoint.
        $estado = $cabecera['estado'] ?? '';
        if ($estado === self::ESTADO_PENDIENTE || $estado === self::ESTADO_RECHAZADA) {
            return;
        }

        $numDoc = ($cabecera['establecimiento_prov'] ?? '') . '-'
                . ($cabecera['punto_emision_prov'] ?? '') . '-'
                . ($cabecera['secuencial_prov'] ?? '');
        $this->procesarAsientoContable($idCompra, $cabecera, $numDoc);
    }

    /**
     * Arma (vía AsientoBuilderService, concepto 'adquisiciones_compras') y persiste el asiento
     * de una compra. Idempotente: si ya existe asiento para esta compra, lo actualiza.
     * El enrutamiento inventariable→Inventario / resto→Gasto y la dirección (factura vs NC)
     * los resuelve el builder, evitando duplicar costo/gasto con el costeo de la venta.
     */
    public function procesarAsientoContable(int $idCompra, array $data, string $numDoc): void
    {
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? $data['created_by'] ?? $_SESSION['id_usuario'] ?? 0);
        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        $proveedorNombre = $data['proveedor_nombre'] ?? 'Proveedor';

        // Siempre regenerar desde el builder con los valores actuales del documento.
        $data['id_compra'] = $idCompra;
        $builder = new \App\Services\modulos\AsientoBuilderService();
        $detallesSugeridos = $builder->generarAsientoSugerido($idEmpresa, 'adquisiciones_compras', $data);

        $detalles = [];
        foreach ($detallesSugeridos as $det) {
            $detalles[] = [
                'id_cuenta_contable'   => $det['id_cuenta_contable'],
                'debe'                 => $det['debe'],
                'haber'                => $det['haber'],
                'referencia_detalle'   => $det['referencia_detalle'] ?: "Compra # $numDoc",
                'documento_referencia' => "Compra # $numDoc",
                'id_entidad'           => (int)($data['id_proveedor'] ?? 0),
                'tipo_entidad'         => 'proveedor',
            ];
        }

        // Documento excluido (p. ej. retención) o sin cuentas configuradas: no se genera asiento.
        if (empty($detalles)) {
            return;
        }

        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        $asientoPrevio = $asientoService->getAsientoPorOrigen('compra', $idCompra, $idEmpresa);
        $idAsiento = $asientoPrevio ? (int)$asientoPrevio['id'] : 0;

        // Nota: si el asiento se corrigió a mano (pestaña «Asiento contable» o Libro Diario),
        // guardarAsiento() lo detecta y devuelve el asiento intacto — la corrección manda sobre
        // el builder. La regla vive allí para valer en todos los módulos por igual.

        $cabeceraData = [
            'id'                   => $idAsiento > 0 ? $idAsiento : null,
            'fecha_asiento'        => $fechaEmision,
            'tipo_comprobante'     => 'compras',
            'numero_comprobante'   => '',
            'concepto'             => "Compra # " . $numDoc . " - Proveedor: " . $proveedorNombre,
            'estado'               => 'contabilizado',
            'modulo_origen'        => 'compra',
            'id_referencia_origen' => $idCompra,
            'observaciones'        => $data['observaciones'] ?? null,
        ];

        $idAsientoGenerado = $asientoService->guardarAsiento($cabeceraData, $detalles, $idEmpresa, $idUsuario);
        $this->repository->updateAsientoContable($idCompra, $idAsientoGenerado);
    }

    /**
     * Anula el asiento contable de la compra. Se llama al ELIMINAR el documento: un asiento
     * que sobrevive a su compra queda huérfano y sigue sumando en el Balance —y si el mismo
     * documento del proveedor se vuelve a registrar, la compra termina contabilizada dos veces
     * (caso real: documento 003-002-000014325, asientos CO-000002 y CO-000038).
     *
     * `AsientoContableService::anular()` ya deja en NULL `compras_cabecera.id_asiento_contable`
     * (mapa `DocumentoOrigenAsiento`), así que no hay que desvincular aparte.
     *
     * Participa en la transacción del llamador a propósito: si el asiento no se puede anular
     * —p. ej. su fecha cae en un período contable cerrado— la eliminación se revierte entera,
     * en vez de dejar la compra borrada con su asiento vivo.
     */
    private function anularAsientoContable(int $idCompra, int $idEmpresa, int $idUsuario): void
    {
        $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
        $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
        $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $this->logService);

        // getAsientoPorOrigen ya excluye los anulados: si no devuelve nada, no hay asiento
        // vivo que anular (nunca se generó, o alguien lo anuló antes).
        $previo = $asientoService->getAsientoPorOrigen('compra', $idCompra, $idEmpresa);
        $idAsiento = $previo ? (int) $previo['id'] : 0;

        // Fallback (documentos migrados): el asiento histórico tiene modulo_origen='migracion',
        // así que getAsientoPorOrigen no lo halla; se resuelve por el enlace id_asiento_contable
        // directo de la compra (mismo criterio que el resto de módulos).
        if ($idAsiento <= 0) {
            $row = $this->repository->getPorId($idCompra, $idEmpresa);
            $idAsiento = (int) ($row['id_asiento_contable'] ?? 0);
        }
        if ($idAsiento <= 0) {
            return;
        }

        try {
            $asientoService->anular($idAsiento, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            // Si otro proceso lo anuló entremedio no hay nada que corregir; cualquier otro
            // motivo (período cerrado, error de BD) sí debe abortar la eliminación.
            if (stripos($e->getMessage(), 'ya se encuentra anulado') === false) {
                throw $e;
            }
        }

        // El desvinculado automático de anular() solo actúa si modulo_origen coincide con el
        // mapa de módulos nativos; un asiento migrado tiene modulo_origen='migracion' y no
        // dispara ese camino, así que se limpia el enlace aquí explícitamente (idempotente si
        // ya se limpió solo).
        $asientoRepo->desvincularAsientoGenerico('compras_cabecera', 'id_asiento_contable', $idCompra);
    }

    public function actualizar(int $id, array $data): int
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $cabecera = $this->repository->getPorId($id, $idEmpresa);
        if (!$cabecera) {
            throw new \Exception('Compra no encontrada.');
        }

        // Las compras migradas son de solo lectura (no se editan).
        if ($this->repository->esMigrado($id, $idEmpresa)) {
            throw new \Exception('Esta compra proviene de una migración y no puede editarse.');
        }

        // Factura de Reembolso recibida (codDocReembolso=41): el sustento tributario
        // SIEMPRE es código 08, sin importar lo que el usuario intente cambiar en el
        // formulario (el JS ya bloquea el selector; esto es el refuerzo del servidor).
        if ((string) ($cabecera['cod_doc_reembolso'] ?? '') === '41') {
            $idSustento08 = $this->getSustentoIdByCodigo('08');
            if ($idSustento08) {
                $data['id_sustento_tributario'] = $idSustento08;
            }
        }

        $this->rules->validar($data);
        $this->verificarSecuencialDuplicado($data, $id);
        
        // Validar tanto la fecha original como la nueva
        $this->periodosService->validarFechaPermitida(
            $cabecera['fecha_emision'], 
            $idEmpresa,
            'No se puede modificar el registro porque el periodo contable original está cerrado.'
        );
        $this->validarPeriodo($data, 'No se puede guardar el registro en la fecha seleccionada porque el periodo contable está cerrado.');

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        
        try {
            if ($managed) $db->beginTransaction();

            $idEmpresa = (int) $data['id_empresa'];
            $idUsuario = (int) $data['id_usuario'];

            $data = $this->calcularTotales($data);
            
            // 1. Actualizar cabecera. Si falla, el catch capturará el error REAL.
            $this->repository->updateCabecera($id, $data);

            // 2. Procesar el resto solo si la cabecera fue exitosa
            $this->sincronizarDetalles($id, $data['detalles'] ?? []);

            $this->repository->deletePagos($id);
            $this->guardarPagos($id, $data['pagos'] ?? []);

            $this->repository->deleteInfoAdicional($id);
            $this->guardarAdicionales($id, $data['adicionales'] ?? []);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'MODIFICAR', 'compras_cabecera', $id,
                $cabecera, ['total' => $data['importe_total'] ?? 0]
            );

            $this->sincronizarCasilleros($id, $data);

            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->relanzarSiDuplicado($e);
            throw $e;
        }

        // Asiento contable FUERA de la transacción: un fallo no revierte la compra ya guardada.
        $this->generarAsientoTrasGuardar($id, $data);
        return $id;
    }

    public function eliminar(int $id, int $idUsuario, int $idEmpresa): bool
    {
        $compra = $this->repository->getPorId($id, $idEmpresa);
        if (!$compra) {
            throw new \Exception('Compra no encontrada.');
        }

        // Validar periodo contable antes de eliminar
        $this->periodosService->validarFechaPermitida(
            $compra['fecha_emision'], 
            $idEmpresa,
            'No se puede eliminar el registro porque el periodo contable está cerrado.'
        );

        // Validar si tiene retenciones asociadas
        $retRepo = new \App\repositories\modulos\RetencionCompraRepository();
        if ($retRepo->existeRetencionParaCompra($id, $idEmpresa)) {
            throw new \Exception("No se puede eliminar la compra porque tiene una retención asociada. Debe eliminar la retención primero.");
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();

        try {
            // NUEVA REGLA: Anular automáticamente cualquier Egreso asociado que no esté anulado aún
            $egresoRepo   = new \App\repositories\modulos\EgresoRepository();
            $egresoRules  = new \App\Rules\modulos\EgresoRules();
            $egresoService = new \App\Services\modulos\EgresoService($egresoRepo, $egresoRules, $this->logService);

            $egresosIds = $this->repository->getEgresosAsociados($id, $idEmpresa);
            foreach ($egresosIds as $egresoId) {
                $egresoService->anular($egresoId, $idEmpresa, $idUsuario);
            }

            // El asiento se anula ANTES del borrado lógico: `anular()` lee el asiento y su
            // documento, y hacerlo después dejaría la compra ya marcada como eliminada.
            $this->anularAsientoContable($id, $idEmpresa, $idUsuario);

            $this->repository->eliminarLogico($id, $idUsuario);

            $this->logService->registrar(
                $idUsuario, $idEmpresa,
                'ELIMINAR', 'compras_cabecera', $id,
                ['id' => $id], null
            );

            $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
            $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $id);

            if ($managed) $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function getSustentoIdByCodigo(string $codigo): ?int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM sustento_tributario WHERE codigo = ? AND status = 1 LIMIT 1");
        $st->execute([$codigo]);
        $res = $st->fetch();
        return $res ? (int) $res['id'] : null;
    }

    /**
     * Sincroniza el detalle de la compra con lo que vino del formulario, PRESERVANDO el
     * id de las líneas existentes (UPDATE en su sitio en vez de borrar todo y reinsertar).
     * Necesario porque inventario_kardex.referencia_id apunta a compras_detalle.id: si el
     * id se regenerara en cada guardado, getInventarioStatusAjax "olvidaría" qué ya se
     * envió a inventario y dejaría reenviar cantidades ya procesadas o quitar la
     * vinculación de un producto que ya tiene inventario registrado.
     *
     * Reglas:
     *  - Línea nueva (sin id, o con id que ya no existe en esta compra): se inserta.
     *  - Línea existente: se actualiza EN SU SITIO (mismo id).
     *  - Línea existente con cantidad ya enviada a inventario: no se puede reducir la
     *    cantidad por debajo de lo ya enviado, ni cambiar el producto vinculado.
     *  - Línea que existía y ya no viene en $detalles (el usuario la quitó del form):
     *    se elimina si no tiene nada procesado; si tiene algo procesado, bloquea todo
     *    el guardado (debe usarse un Retorno de Compra, no borrar la línea).
     */
    private function sincronizarDetalles(int $idCompra, array $detalles): void
    {
        $actualesPorId = [];
        foreach ($this->repository->getDetalles($idCompra) as $a) {
            $actualesPorId[(int) $a['id']] = $a;
        }
        $procesado = $this->repository->getCantidadProcesadaPorDetalle($idCompra);

        $idsRecibidos = [];

        foreach ($detalles as $det) {
            $idExistente = !empty($det['id']) ? (int) $det['id'] : 0;
            $esExistente = $idExistente > 0 && isset($actualesPorId[$idExistente]);

            $det['precio_total_sin_impuesto'] = (float) ($det['precio_total_sin_impuesto']
                ?? ((float) ($det['cantidad'] ?? 1) * (float) ($det['precio_unitario'] ?? 0) - (float) ($det['descuento'] ?? 0)));

            if ($esExistente) {
                $idsRecibidos[] = $idExistente;
                $cantProcesada = (float) ($procesado[$idExistente] ?? 0.0);

                if ($cantProcesada > 0) {
                    $nuevaCantidad = (float) ($det['cantidad'] ?? 0);
                    if ($nuevaCantidad < $cantProcesada - 0.0001) {
                        throw new \Exception(sprintf(
                            'No se puede reducir la cantidad de "%s" a %s: ya se enviaron %s a inventario.',
                            $det['descripcion'] ?? '', $nuevaCantidad, $cantProcesada
                        ));
                    }

                    $idProductoActual = !empty($actualesPorId[$idExistente]['id_producto']) ? (int) $actualesPorId[$idExistente]['id_producto'] : null;
                    $idProductoNuevo  = !empty($det['id_producto']) ? (int) $det['id_producto'] : null;
                    if ($idProductoNuevo !== $idProductoActual) {
                        throw new \Exception(sprintf(
                            'No se puede cambiar el producto vinculado de "%s": ya tiene %s enviado a inventario.',
                            $det['descripcion'] ?? '', $cantProcesada
                        ));
                    }
                }

                $det['id'] = $idExistente;
                $this->repository->updateDetalle($det);

                $this->repository->deleteImpuestosDeDetalle($idExistente);
                foreach ($det['impuestos'] ?? [] as $imp) {
                    $imp['id_compra_detalle'] = $idExistente;
                    $this->repository->insertImpuesto($imp);
                }
            } else {
                $det['id_compra'] = $idCompra;
                $idDetalle = $this->repository->insertDetalle($det);
                foreach ($det['impuestos'] ?? [] as $imp) {
                    $imp['id_compra_detalle'] = $idDetalle;
                    $this->repository->insertImpuesto($imp);
                }
            }
        }

        // Líneas que existían y ya no vinieron: el usuario las quitó del formulario.
        $idsAEliminar = array_diff(array_keys($actualesPorId), $idsRecibidos);
        foreach ($idsAEliminar as $idElim) {
            $cantProcesada = (float) ($procesado[$idElim] ?? 0.0);
            if ($cantProcesada > 0) {
                $desc = $actualesPorId[$idElim]['descripcion'] ?? '';
                throw new \Exception(sprintf(
                    'No se puede quitar la línea "%s": ya tiene %s enviado a inventario.',
                    $desc, $cantProcesada
                ));
            }
        }
        if (!empty($idsAEliminar)) {
            $this->repository->deleteDetallesPorId($idsAEliminar);
        }
    }

    private function guardarPagos(int $idCompra, array $pagos): void
    {
        foreach ($pagos as $pago) {
            $pago['id_compra'] = $idCompra;
            $this->repository->insertPago($pago);
        }
    }

    private function guardarAdicionales(int $idCompra, array $adicionales): void
    {
        foreach ($adicionales as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $this->repository->insertInfoAdicional([
                    'id_compra' => $idCompra,
                    'nombre'    => $campo,
                    'valor'     => (string)$valor
                ]);
            }
        }

        // Valores recaudados por cuenta de terceros (planillas de luz/agua: contribución
        // bomberos, tasa de basura). Viven en la info adicional porque el SRI no tiene un
        // nodo propio para ellos, y quedan FUERA de importe_total —que es el valor
        // declarado— pero dentro de lo que se paga, así que se totalizan aparte.
        // Se recalcula siempre, también cuando la lista llega vacía: al editar una compra
        // primero se borran todos los adicionales, y sin este recálculo el total anterior
        // sobreviviría a la eliminación del rubro que lo originó.
        $this->repository->updateTotalTerceros(
            $idCompra,
            \App\Helpers\RubrosTerceros::total($adicionales)
        );
    }

    private function calcularTotales(array $data): array
    {
        $subtotal = 0;
        $descuento = 0;
        $total = 0;

        foreach ($data['detalles'] ?? [] as $det) {
            $cant = (float)($det['cantidad'] ?? 0);
            $prec = (float)($det['precio_unitario'] ?? 0);
            $desc = (float)($det['descuento'] ?? 0);
            
            $sub = $cant * $prec;
            $subtotal += $sub;
            $descuento += $desc;

            // Sumar impuestos del detalle
            if (!empty($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    $total += (float)($imp['valor'] ?? 0);
                }
            }
        }

        $data['total_sin_impuestos'] = $subtotal - $descuento;
        $data['total_descuento']     = $descuento;
        $data['importe_total']       = $data['total_sin_impuestos'] + ($total) + (float)($data['propina'] ?? 0);

        return $data;
    }

    private function verificarSecuencialDuplicado(array $data, ?int $excludeId = null): void
    {
        $existe = $this->repository->existeSecuencial(
            (int)$data['id_empresa'],
            (int)$data['id_proveedor'],
            $data['establecimiento_prov'] ?? '',
            $data['punto_emision_prov'] ?? '',
            $data['secuencial_prov'] ?? '',
            $data['tipo_comprobante'] ?? '01',
            $excludeId
        );

        if ($existe) {
            throw new \Exception('Ya existe una compra registrada con ese número de comprobante para este proveedor.');
        }

        // El numero_autorizacion solo es único por documento en comprobantes ELECTRÓNICOS
        // (es la clave de acceso, 49 dígitos). En comprobantes FÍSICOS, el SRI otorga UN
        // único número de autorización (10 dígitos) para todo un RANGO de secuenciales
        // (una chequera completa) — por diseño varias compras físicas del mismo proveedor
        // comparten el mismo numero_autorizacion con distinto secuencial_prov, y eso es
        // válido. Ahí el duplicado real ya lo cubre existeSecuencial() arriba. Mismo
        // criterio que el índice uq_compras_numaut_activo (ver
        // database/compras_numaut_unico_solo_electronicas.sql): solo aplica con 49 dígitos.
        $numAutorizacion = trim((string)($data['numero_autorizacion'] ?? ''));
        $esClaveElectronica = strlen(preg_replace('/\D/', '', $numAutorizacion)) === 49;
        if ($numAutorizacion !== '' && $esClaveElectronica) {
            $existeAutorizacion = $this->repository->existeNumeroAutorizacion(
                (int)$data['id_empresa'],
                $numAutorizacion,
                $excludeId
            );

            if ($existeAutorizacion) {
                throw new \Exception('Ya existe una compra registrada con ese número de autorización/clave de acceso.');
            }
        }
    }

    /**
     * Traduce la violación del índice único uq_compras_numaut_activo (23505) a un
     * mensaje amigable. Red de seguridad para la carrera entre el chequeo previo
     * (verificarSecuencialDuplicado) y el INSERT/UPDATE real.
     */
    private function relanzarSiDuplicado(\Throwable $e): void
    {
        $msg = $e->getMessage();
        if ($e->getCode() === '23505'
            || stripos($msg, 'uq_compras_numaut_activo') !== false
            || stripos($msg, 'duplicate key') !== false
            || stripos($msg, 'llave duplicada') !== false) {
            throw new \Exception('Ya existe una compra registrada con ese número de autorización/clave de acceso.');
        }
    }

    private function validarPeriodo(array $data, ?string $mensaje = null): void
    {
        $fecha = $data['fecha_emision'] ?? null;
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        
        if ($fecha && $idEmpresa) {
            $this->periodosService->validarFechaPermitida($fecha, $idEmpresa, $mensaje);
        }
    }

    public function sincronizarCasilleros(int $idCompra, array $data = null): void
    {
        $idEmpresa = $data ? (int)$data['id_empresa'] : 0;
        
        if (!$data) {
            $cabecera = $this->repository->getPorId($idCompra);
            if (!$cabecera) return;
            $idEmpresa = (int)$cabecera['id_empresa'];
            $data = $cabecera;
            
            // Get details and taxes if we fetched from DB
            $data['detalles'] = $this->repository->getDetalles($idCompra);
            foreach ($data['detalles'] as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);
        }

        $fechaEmision = $data['fecha_emision'] ?? date('Y-m-d');
        // 'deducible' determines the grouping key. Default to 'declaracion_iva' if empty.
        $deducible = $data['deducible'] ?? 'declaracion_iva';
        if ($deducible === '') $deducible = 'declaracion_iva';
        
        $decIvaRepo = new \App\repositories\modulos\DeclaracionIvaRepository();
        $decIvaRepo->limpiarCasillerosDocumento($idEmpresa, 'compras', $idCompra);

        // Obtener configuración de casilleros de la empresa
        $empresaConfigRepo = new \App\repositories\modulos\EmpresaRepository();
        $configDec = $empresaConfigRepo->getIvaCasilleros($idEmpresa);

        // 'compras_cabecera' recibe los 4 tipos de documento que llegan por descarga SRI
        // (DocumentoAutomatedRegisterService::insertarCompra): 01 factura, 02 nota de venta,
        // 03 liquidación de compra, 04 nota de crédito, 05 nota de débito. Cada uno tiene su
        // propia clave de casillero configurable en /config/empresa (pestaña Form 104 IVA).
        $tipoComprobante = (string) ($data['tipo_comprobante'] ?? '01');
        $keyDocumento = match ($tipoComprobante) {
            '02'    => 'nota_venta_compra',
            '03'    => 'liquidacion_compra',
            '04'    => 'nota_credito_compra',
            '05'    => 'nota_debito_compra',
            default => 'factura_compra',
        };
        // La nota de crédito de compra reduce el crédito tributario: sus valores van en
        // negativo al mismo casillero (igual que NotaCreditoService hace en el lado de ventas).
        $signo = $tipoComprobante === '04' ? -1 : 1;

        if (!$configDec || !isset($configDec[$keyDocumento])) return;
        $confCompras = $configDec[$keyDocumento];

        $tarifaMap = $decIvaRepo->getMapaTarifasIva();
        $detalles = $data['detalles'] ?? [];

        foreach ($detalles as $det) {
            $desc = !empty($det['producto_nombre']) ? $det['producto_nombre'] : (!empty($det['descripcion']) ? $det['descripcion'] : 'Sin concepto');
            $concepto = substr(trim($desc), 0, 255);
            $impuestos = $det['impuestos'] ?? [];
            foreach ($impuestos as $imp) {
                // Solo IVA (codigo_impuesto = 2)
                if ((int)$imp['codigo_impuesto'] !== 2) continue;

                $codigoPorcentaje = (string)($imp['codigo_porcentaje'] ?? '');
                $tarifaKey = $tarifaMap[$codigoPorcentaje] ?? '';
                if (!$tarifaKey || !isset($confCompras[$tarifaKey])) continue;

                $c = $confCompras[$tarifaKey];
                $bruto = $c['bruto'] ?? '';
                $neto = $c['neto'] ?? '';
                $impC = $c['impuesto'] ?? '';

                $base = (float)($imp['base_imponible'] ?? 0);
                $valorImp = (float)($imp['valor'] ?? 0);

                if ($bruto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $bruto, 'valor' => $signo * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($neto !== '' && $base > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $neto, 'valor' => $signo * $base, 'concepto' => $concepto . ' (Base)'
                    ]);
                }
                if ($impC !== '' && $valorImp > 0) {
                    $decIvaRepo->insertarCasilleroDeclaracion([
                        'id_empresa' => $idEmpresa, 'origen' => 'compras', 'id_origen' => $idCompra,
                        'fecha' => $fechaEmision, 'casillero' => $impC, 'valor' => $signo * $valorImp, 'concepto' => $concepto . ' (IVA)'
                    ]);
                }
            }
        }
    }
}

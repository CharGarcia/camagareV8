<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ProformaRepository;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\core\Database;

class ProformaService
{
    /**
     * Estados desde los que se puede generar un PEDIDO (además de 'convertida', que se
     * resuelve aparte por id_factura_convertida). Es a propósito más amplio que el de la
     * factura y el recibo —que exigen 'aprobada'— porque el pedido no factura ni emite
     * nada: organiza el despacho, y conviene poder prepararlo mientras la cotización
     * todavía se negocia. Quedan fuera 'rechazada' y 'anulada'.
     */
    private const ESTADOS_DESPACHABLES = ['borrador', 'aprobada'];

    private ProformaRepository $repository;
    private ProformaRules $rules;
    private LogSistemaService $log;

    public function __construct(
        ProformaRepository $repository,
        ProformaRules $rules,
        LogSistemaService $log
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->log        = $log;
    }

    /**
     * Crea una nueva proforma. Retorna el id creado.
     * @throws \RuntimeException si hay errores de validación o duplicado
     */
    public function crear(array $data): int
    {
        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        // Condiciones (HTML del editor): solo formato, nada ejecutable.
        $data['condiciones_html'] = ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null);

        $idEmpresa  = (int) $data['id_empresa'];
        $idUsuario  = (int) $data['id_usuario'];
        $idEstab    = (int) $data['id_establecimiento'];
        $idPunto    = (int) $data['id_punto_emision'];

        // Secuencial AUTORITATIVO del servidor: se recalcula aquí y NO se confía en el
        // valor enviado por el navegador (el campo es readonly / vista previa y puede
        // llegar desfasado, provocando números salteados o duplicados). Así lo mostrado
        // y lo guardado siempre coinciden con el verdadero "siguiente disponible".
        // Se abre la transacción ANTES de calcularlo y se mantiene hasta el INSERT final: el
        // lock de obtenerSiguienteSecuencial() se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        $secuencial = '';
        if ($managed) $db->beginTransaction();
        try {
            $secRes     = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Proformas', $data['fecha_emision'] ?? null);
            $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);
            $data['secuencial'] = $secuencial;

            if ($this->repository->existeSecuencial($idEmpresa, $idEstab, $idPunto, $secuencial)) {
                throw new \RuntimeException("El secuencial {$secuencial} ya está en uso. Recargue e intente nuevamente.");
            }

            $idProforma = $this->repository->insertCabecera($data);
            $this->guardarDetalles($idProforma, $data['detalles']);
            $this->guardarInfoAdicional($idProforma, $data['info_adicional'] ?? []);
            if ($managed) $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'crear',
                    'proformas_cabecera',
                    $idProforma,
                    null,
                    ['secuencial' => $secuencial, 'id_cliente' => $data['id_cliente']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $idProforma;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            // Último cinturón: el índice único uq_proformas_numero rechazó el número. No
            // debería ocurrir (el candado de obtenerSiguienteSecuencial serializa a los
            // emisores del mismo punto), pero si ocurre el usuario tiene que leer qué pasó,
            // no un volcado de PDO.
            if ($e instanceof \PDOException && (string) $e->getCode() === '23505'
                && stripos($e->getMessage(), 'uq_proformas_numero') !== false) {
                throw new \RuntimeException(
                    "El secuencial {$secuencial} acaba de ser tomado por otra proforma. Vuelva a guardar para que el sistema asigne el siguiente número."
                );
            }
            throw $e;
        }
    }

    /**
     * Actualiza una proforma existente. Solo se puede editar si está en borrador.
     * @throws \RuntimeException
     */
    public function actualizar(int $id, array $data): int
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador'], true)) {
            throw new \RuntimeException('Solo se pueden editar proformas en estado borrador.');
        }

        $errores = $this->rules->validar($data);
        if (!empty($errores)) {
            throw new \RuntimeException(implode(' | ', $errores));
        }

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        // En edición la serie (establecimiento/punto de emisión) y el secuencial NO
        // cambian: se conservan los ya asignados al crear. El select de serie está
        // deshabilitado en el modal de edición, pero esto es la validación real —
        // nunca confiar en que el cliente no haya alterado esos campos.
        $data['id_establecimiento'] = (int) $proforma['id_establecimiento'];
        $data['id_punto_emision']   = (int) $proforma['id_punto_emision'];
        $data['establecimiento']    = $proforma['establecimiento'];
        $data['punto_emision']      = $proforma['punto_emision'];
        $data['secuencial']         = $proforma['secuencial'];
        $data['condiciones_html']   = ProformaRules::sanitizarCondiciones($data['condiciones_html'] ?? null);

        if ($this->repository->existeSecuencial(
            $idEmpresa,
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            $data['secuencial'],
            $id
        )) {
            throw new \RuntimeException("El secuencial {$data['secuencial']} ya está en uso.");
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->updateCabecera($id, $data);
            $this->repository->deleteDetalles($id);
            $this->guardarDetalles($id, $data['detalles']);
            $this->repository->deleteInfoAdicional($id);
            $this->guardarInfoAdicional($id, $data['info_adicional'] ?? []);
            $db->commit();

            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'actualizar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    ['secuencial' => $data['secuencial']]
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }

            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Transiciones de estado que además del permiso de actualizar exigen un nivel
     * mínimo de usuario (2 = administrador, 3 = superadministrador).
     *
     * Reabrir una proforma aprobada la vuelve editable y deja obsoleta la aprobación
     * que el cliente ya vio, así que no es una acción de uso diario: se reserva a
     * administradores. La clave es "estadoActual>estadoNuevo".
     */
    private const NIVEL_MINIMO_TRANSICION = [
        'aprobada>borrador' => 2,
    ];

    /**
     * Cambia el estado de una proforma.
     * Transiciones permitidas:
     *   borrador → aprobada | anulada
     *   aprobada → rechazada | anulada | borrador (reabrir; solo nivel 2 o 3)
     *
     * @param int $nivelUsuario Nivel del usuario que ejecuta el cambio (1 usuario,
     *                          2 administrador, 3 superadministrador). Se valida aquí
     *                          y no solo en la vista: el endpoint es alcanzable directo.
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $idEmpresa, int $idUsuario, int $nivelUsuario = 1): void
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }

        $estadoActual = $proforma['estado'];
        $permitidas = [
            'borrador' => ['aprobada', 'anulada'],
            'aprobada' => ['rechazada', 'anulada', 'borrador'],
        ];

        if (!isset($permitidas[$estadoActual]) || !in_array($nuevoEstado, $permitidas[$estadoActual], true)) {
            throw new \RuntimeException("No se puede cambiar de '{$estadoActual}' a '{$nuevoEstado}'.");
        }

        $nivelMinimo = self::NIVEL_MINIMO_TRANSICION["{$estadoActual}>{$nuevoEstado}"] ?? 0;
        if ($nivelMinimo > 0 && $nivelUsuario < $nivelMinimo) {
            throw new \RuntimeException(
                'Solo un administrador o un superadministrador puede regresar una proforma aprobada a borrador.'
            );
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->actualizarEstado($id, $nuevoEstado, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'cambiar_estado',
                'proformas_cabecera',
                $id,
                ['estado' => $estadoActual],
                ['estado' => $nuevoEstado]
            );
        } catch (\Throwable $e) { /* log falla silenciosamente */ }
    }

    /**
     * Elimina lógicamente una proforma (no permitido si está convertida).
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if ($proforma['estado'] === 'convertida') {
            throw new \RuntimeException('No se puede eliminar una proforma ya convertida a factura.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $ok = $this->repository->eliminar($id, $idEmpresa, $idUsuario);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        if ($ok) {
            try {
                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'eliminar',
                    'proformas_cabecera',
                    $id,
                    $proforma,
                    null
                );
            } catch (\Throwable $e) { /* log falla silenciosamente */ }
        }
        return $ok;
    }

    /**
     * Duplica una proforma en una NUEVA proforma en borrador y devuelve su id.
     *
     * Se copia lo cotizable: cliente, vendedor, vigencia, observaciones, condiciones,
     * ítems (con sus impuestos) e información adicional. NO se arrastra nada propio del
     * documento original: el secuencial lo vuelve a asignar crear() (autoritativo del
     * servidor, con su bloqueo transaccional), la fecha de emisión pasa a ser la de hoy,
     * el estado vuelve a 'borrador' y quedan fuera el token/aprobación del cliente y la
     * factura convertida.
     *
     * Se permite duplicar en cualquier estado (incluidas anulada y rechazada): el sentido
     * del botón es "volver a cotizar lo mismo", no reabrir el documento original.
     */
    public function duplicar(int $id, int $idEmpresa, int $idUsuario, string $tipoAmbiente = '1'): int
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }

        // Impuestos de TODAS las líneas en una sola consulta (sin N+1).
        $detalles  = $this->repository->getDetalles($id);
        $impuestos = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));

        $items = [];
        foreach ($detalles as $d) {
            $items[] = [
                'id_producto'               => $d['id_producto'] ?? null,
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? null,
                'descripcion'               => $d['descripcion'],
                // En la línea la columna es info_adicional; insertDetalle la recibe como 'adicional'.
                'adicional'                 => $d['info_adicional'] ?? null,
                'cantidad'                  => $d['cantidad'],
                'precio_unitario'           => $d['precio_unitario'],
                'descuento'                 => $d['descuento'],
                'precio_total_sin_impuesto' => $d['precio_total_sin_impuesto'],
                'id_tarifa_iva'             => $d['id_tarifa_iva'] ?? 0,
                'impuestos'                 => array_map(static fn(array $i): array => [
                    'codigo_impuesto'   => $i['codigo_impuesto']   ?? '2',
                    'codigo_porcentaje' => $i['codigo_porcentaje'] ?? '2',
                    'tarifa'            => (float) ($i['tarifa'] ?? 0),
                    'base_imponible'    => (float) ($i['base_imponible'] ?? 0),
                    'valor'             => (float) ($i['valor'] ?? 0),
                ], $impuestos[(int) $d['id']] ?? []),
            ];
        }
        if (!$items) {
            throw new \RuntimeException('La proforma no tiene ítems que duplicar.');
        }

        $adicional = [];
        foreach ($this->repository->getInfoAdicional($id) as $a) {
            $adicional[] = ['nombre' => $a['nombre'] ?? '', 'valor' => $a['valor'] ?? ''];
        }

        $data = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            // Misma serie que la original: el secuencial nuevo sale de ese punto de emisión.
            'id_establecimiento'  => (int) $proforma['id_establecimiento'],
            'id_punto_emision'    => (int) $proforma['id_punto_emision'],
            'establecimiento'     => $proforma['establecimiento'],
            'punto_emision'       => $proforma['punto_emision'],
            // Solo para pasar la validación de Rules: crear() descarta este valor y asigna
            // el siguiente secuencial real de la serie.
            'secuencial'          => $proforma['secuencial'],
            'tipo_ambiente'       => $tipoAmbiente,
            'fecha_emision'       => date('Y-m-d'),
            'id_cliente'          => (int) $proforma['id_cliente'],
            'id_vendedor'         => $proforma['id_vendedor'] ?? null,
            'dias_vigencia'       => (int) ($proforma['dias_vigencia'] ?? 15),
            'observaciones'       => $proforma['observaciones'] ?? null,
            'condiciones_html'    => $proforma['condiciones_html'] ?? null,
            'moneda'              => $proforma['moneda'] ?? 'DOLAR',
            'total_sin_impuestos' => $proforma['total_sin_impuestos'] ?? 0,
            'total_descuento'     => $proforma['total_descuento'] ?? 0,
            'total_ice'           => $proforma['total_ice'] ?? 0,
            'importe_total'       => $proforma['importe_total'] ?? 0,
            'estado'              => 'borrador',
            'detalles'            => $items,
            'info_adicional'      => $adicional,
        ];

        // crear() abre la transacción, toma el secuencial con su candado y audita el alta.
        $idNueva = $this->crear($data);

        // Registro adicional para dejar rastro de DE QUÉ proforma salió esta copia.
        try {
            $nueva = $this->repository->getPorId($idNueva);
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'duplicar',
                'proformas_cabecera',
                $idNueva,
                ['id_origen' => $id, 'secuencial' => $proforma['secuencial']],
                ['secuencial' => $nueva['secuencial'] ?? null]
            );
        } catch (\Throwable $e) { /* log falla silenciosamente */ }

        return $idNueva;
    }

    /**
     * Retorna los datos de una proforma formateados para pre-llenar el formulario de ventas.
     */
    public function getForConversion(int $id, int $idEmpresa): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!in_array($proforma['estado'], ['borrador', 'aprobada'], true)) {
            throw new \RuntimeException('Solo se pueden convertir proformas en borrador o aprobadas.');
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$det) {
            $det['impuestos'] = $this->repository->getImpuestosDetalle((int) $det['id']);
        }
        unset($det);

        $adicional = $this->repository->getInfoAdicional($id);

        return [
            'proforma'       => $proforma,
            'detalles'       => $detalles,
            'info_adicional' => $adicional,
        ];
    }

    /**
     * Convierte una proforma en una factura de venta (borrador), copiando los datos
     * a las tablas de ventas en el servidor. Reutiliza FacturaVentaService::crear().
     *
     * Reglas:
     *  - No se convierten proformas rechazadas ni anuladas.
     *  - Si la proforma ya tiene una factura asociada, solo se crea otra cuando $forzar = true
     *    (de lo contrario se devuelve 'requiere_confirmacion' para que la UI confirme).
     *
     * @return array{id_factura?:int, requiere_confirmacion?:bool, mensaje?:string}
     * @throws \RuntimeException
     */
    public function convertirAFactura(int $id, int $idEmpresa, int $idUsuario, bool $forzar = false): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        // ¿Ya fue convertida alguna vez? (para no exigir 'aprobada' al refacturar)
        $yaConvertida = !empty($proforma['id_factura_convertida']) || $proforma['estado'] === 'convertida';

        // Solo se factura una proforma APROBADA (o una ya convertida, para permitir
        // generar otra factura tras confirmación del usuario).
        if (!$yaConvertida && $proforma['estado'] !== 'aprobada') {
            throw new \RuntimeException('La proforma debe estar aprobada para generar una factura.');
        }

        // Mostrar el mensaje de confirmación SOLO si existe alguna factura vigente
        // RELACIONADA con esta proforma (ventas.id_proforma). Si no hay (o todas fueron
        // eliminadas), se factura directo. Se usa el vínculo real con ventas.
        $facturaRepo      = new \App\repositories\modulos\FacturaVentaRepository();
        $facturasVigentes = $facturaRepo->getPorProforma($id, $idEmpresa);

        if (!empty($facturasVigentes) && !$forzar) {
            return [
                'requiere_confirmacion' => true,
                'mensaje' => 'Esta proforma ya tiene una factura asociada. ¿Desea crear otra factura de todos modos?',
            ];
        }

        // Cargar detalles + impuestos + info adicional de la proforma
        $detallesPf = $this->repository->getDetalles($id);
        foreach ($detallesPf as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $adicionalPf = $this->repository->getInfoAdicional($id);

        // ── Resolver el contexto de la factura (establecimiento, punto, secuencial, bodega) ──
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            throw new \RuntimeException('La empresa no tiene establecimientos configurados.');
        }
        $est     = $establecimientos[0];
        $idEstab = (int) $est['id'];

        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig($idEstab);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (\Throwable $e) { /* config opcional del establecimiento */ }

        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos  = [];
        foreach ($empresaModel->getPuntosEmision($idEstab) as $p) {
            $secConfig = $secRepo->getConfigSecuencial((int) $p['id'], 'Facturas de venta');
            if (empty($secConfig['id'])) {
                continue;
            }
            $puntos[] = $p;
        }
        if (empty($puntos)) {
            throw new \RuntimeException('El establecimiento no tiene un punto de emisión con secuencial configurado para Facturas de venta.');
        }
        $punto   = $puntos[0];
        $idPunto = (int) $punto['id'];

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $idBodega   = !empty($bodegas) ? (int) $bodegas[0]['id'] : 0;

        // ── Pre-chequeo de stock según la configuración del establecimiento ──
        // Solo se exige saldo cuando la empresa configuró que la facturación afecta
        // inventario (facturacion_inventario) Y que solo se factura con stock positivo
        // (factura_solo_stock_positivo). Si alguna está apagada, se factura sin validar.
        $faltantes = $this->verificarStockDisponible($detallesPf, $estConfig ?? [], $idEmpresa, $idBodega);
        if (!empty($faltantes)) {
            return [
                'stock_insuficiente' => true,
                'faltantes'          => $faltantes,
            ];
        }

        // Secuencial. Se abre la transacción ANTES de calcularlo y se mantiene hasta el INSERT
        // final (FacturaVentaService::crear() más abajo): el lock de obtenerSiguienteSecuencial()
        // se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
        $secRes     = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Facturas de venta');
        $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);

        // ── Mapear detalles (precios y descuentos tal cual de la proforma) ──
        $detallesFac = [];
        foreach ($detallesPf as $d) {
            $impuestos = [];
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impuestos[] = [
                    'codigo_impuesto'   => (string) ($imp['codigo_impuesto'] ?? '2'),
                    'codigo_porcentaje' => (string) ($imp['codigo_porcentaje'] ?? '0'),
                    'tarifa'            => (float) ($imp['tarifa'] ?? 0),
                    'base_imponible'    => (float) ($imp['base_imponible'] ?? 0),
                    'valor'             => (float) ($imp['valor'] ?? 0),
                ];
            }

            // Asignación automática de lote/caducidad/NUP (FEFO), igual que una
            // factura de venta: el más antiguo con saldo de la bodega destino. Necesario
            // para que pasen las reglas cuando el establecimiento exige lote/caducidad/NUP.
            $lote = $this->resolverLoteAutomatico($d, $estConfig ?? [], $idEmpresa, $idBodega);

            $detallesFac[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? '',
                'descripcion'               => $d['descripcion'] ?? '',
                'nombre'                    => $d['descripcion'] ?? '',
                'cantidad'                  => (float) ($d['cantidad'] ?? 0),
                'precio_unitario'           => (float) ($d['precio_unitario'] ?? 0),
                'descuento'                 => (float) ($d['descuento'] ?? 0),
                'precio_total_sin_impuesto' => (float) ($d['precio_total_sin_impuesto'] ?? 0),
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'id_bodega'                 => $idBodega,
                'es_libre'                  => empty($d['id_producto']) ? '1' : '0',
                'lote'                      => $lote['lote'],
                'caducidad'                 => $lote['caducidad'],
                'nup'                       => $lote['nup'],
                'impuestos'                 => $impuestos,
            ];
        }
        if (empty($detallesFac)) {
            throw new \RuntimeException('La proforma no tiene líneas de detalle para facturar.');
        }

        // ── Info adicional ──
        $infoAdic = [];
        foreach ($adicionalPf as $ia) {
            $infoAdic[] = ['nombre' => $ia['nombre'] ?? '', 'valor' => $ia['valor'] ?? ''];
        }

        // ── Pago por defecto: la factura exige al menos una forma de pago cuyo total
        //    cuadre con el importe. El usuario puede cambiarla al revisar el borrador. ──
        $importeTotal = (float) ($proforma['importe_total'] ?? 0);
        $pagos = [[
            'forma_pago'    => '01', // 01 = Sin utilización del sistema financiero (efectivo)
            'total'         => $importeTotal,
            'plazo'         => 0,
            'unidad_tiempo' => 'dias',
        ]];

        // ── Estructura que espera FacturaVentaService::crear() ──
        $dataFactura = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'id_proforma'         => $id,
            'id_cliente'          => (int) $proforma['id_cliente'],
            'id_vendedor'         => !empty($proforma['id_vendedor']) ? (int) $proforma['id_vendedor'] : null,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => (string) ($punto['cod_establecimiento'] ?? $est['codigo'] ?? '001'),
            'punto_emision'       => (string) ($punto['codigo_punto'] ?? $punto['codigo'] ?? '001'),
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_bodega'           => $idBodega,
            'moneda'              => $proforma['moneda'] ?? 'DOLAR',
            'total_sin_impuestos' => (float) ($proforma['total_sin_impuestos'] ?? 0),
            'total_descuento'     => (float) ($proforma['total_descuento'] ?? 0),
            'total_ice'           => (float) ($proforma['total_ice'] ?? 0),
            'importe_total'       => $importeTotal,
            'propina'             => 0,
            'observaciones'       => $proforma['observaciones'] ?? '',
            'empresa_config'      => $empresaData,
            'detalles'            => $detallesFac,
            'info_adicional'      => $infoAdic,
            'pagos'               => $pagos,
        ];

        // crear() detecta la transacción ya abierta arriba y se engancha a ella (no hace su
        // propio commit/rollback). La factura queda en estado 'borrador' para revisión/emisión.
        $facService = new FacturaVentaService(
            $facturaRepo,
            new \App\Rules\modulos\FacturaVentaRules(),
            new LogSistemaService()
        );
        $idFactura = $facService->crear($dataFactura);
        if ($managedTransaction) {
            $db->commit();
        }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Enlazar la proforma con la factura (estado = convertida)
        $this->repository->marcarConvertida($id, $idFactura, $idUsuario);

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_factura',
                'proformas_cabecera',
                $id,
                null,
                ['id_factura' => $idFactura, 'forzado' => $forzar]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        return ['id_factura' => $idFactura];
    }

    /**
     * Convierte una proforma en un recibo de venta (borrador), aplicando EXACTAMENTE
     * las mismas reglas de inventario que la factura: pre-chequeo de stock según la
     * config del establecimiento y asignación automática FEFO de lote/caducidad/NUP.
     * Reutiliza ReciboVentaService::crear().
     *
     * @return array{id_recibo?:int, stock_insuficiente?:bool, faltantes?:array}
     * @throws \RuntimeException
     */
    public function convertirARecibo(int $id, int $idEmpresa, int $idUsuario): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        $yaConvertida = !empty($proforma['id_factura_convertida']) || $proforma['estado'] === 'convertida';
        if (!$yaConvertida && $proforma['estado'] !== 'aprobada') {
            throw new \RuntimeException('La proforma debe estar aprobada para generar un recibo de venta.');
        }

        // Detalles + impuestos + info adicional de la proforma
        $detallesPf = $this->repository->getDetalles($id);
        foreach ($detallesPf as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $adicionalPf = $this->repository->getInfoAdicional($id);

        // ── Contexto (establecimiento, punto, secuencial de recibos, bodega, config) ──
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            throw new \RuntimeException('La empresa no tiene establecimientos configurados.');
        }
        $est     = $establecimientos[0];
        $idEstab = (int) $est['id'];

        $estConfig = null;
        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig($idEstab);
            if ($estConfig) {
                $empresaData = array_merge($empresaData, $estConfig);
            }
        } catch (\Throwable $e) { /* config opcional del establecimiento */ }

        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos  = [];
        foreach ($empresaModel->getPuntosEmision($idEstab) as $p) {
            $secConfig = $secRepo->getConfigSecuencial((int) $p['id'], 'Recibos de venta');
            if (empty($secConfig['id'])) {
                continue;
            }
            $puntos[] = $p;
        }
        if (empty($puntos)) {
            throw new \RuntimeException('El establecimiento no tiene un punto de emisión con secuencial configurado para Recibos de venta.');
        }
        $punto   = $puntos[0];
        $idPunto = (int) $punto['id'];

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas    = $bodegaRepo->getBodegasPermitidas($idUsuario, $idEmpresa, $nivel);
        $idBodega   = !empty($bodegas) ? (int) $bodegas[0]['id'] : 0;

        // ── Mismo pre-chequeo de stock que la factura ──
        $faltantes = $this->verificarStockDisponible($detallesPf, $estConfig ?? [], $idEmpresa, $idBodega);
        if (!empty($faltantes)) {
            return ['stock_insuficiente' => true, 'faltantes' => $faltantes];
        }

        // Secuencial. Se abre la transacción ANTES de calcularlo y se mantiene hasta el INSERT
        // final (ReciboVentaService::crear() más abajo): el lock de obtenerSiguienteSecuencial()
        // se libera solo al COMMIT/ROLLBACK (CLAUDE.md §8).
        $db = Database::getConnection();
        $managedTransaction = !$db->inTransaction();
        if ($managedTransaction) {
            $db->beginTransaction();
        }

        try {
        $secRes     = (new SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Recibos de venta', date('Y-m-d'));
        $secuencial = $secRes['formateado'] ?? str_pad((string) ($secRes['secuencial'] ?? 1), 9, '0', STR_PAD_LEFT);

        // ── Mapear detalles con asignación FEFO de lote/caducidad/NUP (igual que factura) ──
        $detallesRec = [];
        foreach ($detallesPf as $d) {
            $impuestos = [];
            foreach ($d['impuestos'] ?? [] as $imp) {
                $impuestos[] = [
                    'codigo_impuesto'   => (string) ($imp['codigo_impuesto'] ?? '2'),
                    'codigo_porcentaje' => (string) ($imp['codigo_porcentaje'] ?? '0'),
                    'tarifa'            => (float) ($imp['tarifa'] ?? 0),
                    'base_imponible'    => (float) ($imp['base_imponible'] ?? 0),
                    'valor'             => (float) ($imp['valor'] ?? 0),
                ];
            }

            $lote = $this->resolverLoteAutomatico($d, $estConfig ?? [], $idEmpresa, $idBodega);

            $detallesRec[] = [
                'id_producto'               => !empty($d['id_producto']) ? (int) $d['id_producto'] : null,
                'id_bodega'                 => $idBodega,
                'id_unidad_medida'          => $d['id_unidad_medida'] ?? null,
                'id_medida'                 => $d['id_unidad_medida'] ?? null,
                'codigo_principal'          => $d['codigo_principal'] ?? '',
                'codigo_auxiliar'           => $d['codigo_auxiliar'] ?? '',
                'descripcion'               => $d['descripcion'] ?? '',
                'nombre'                    => $d['descripcion'] ?? '',
                'cantidad'                  => (float) ($d['cantidad'] ?? 0),
                'precio_unitario'           => (float) ($d['precio_unitario'] ?? 0),
                'descuento'                 => (float) ($d['descuento'] ?? 0),
                'precio_total_sin_impuesto' => (float) ($d['precio_total_sin_impuesto'] ?? 0),
                'es_libre'                  => empty($d['id_producto']) ? '1' : '0',
                'lote'                      => $lote['lote'],
                'caducidad'                 => $lote['caducidad'],
                'nup'                       => $lote['nup'],
                'impuestos'                 => $impuestos,
            ];
        }
        if (empty($detallesRec)) {
            throw new \RuntimeException('La proforma no tiene líneas de detalle para el recibo.');
        }

        // ── Info adicional ──
        $infoAdic = [];
        foreach ($adicionalPf as $ia) {
            $infoAdic[] = ['nombre' => $ia['nombre'] ?? '', 'valor' => $ia['valor'] ?? ''];
        }

        // ── Pago por defecto (efectivo) que cuadra con el total ──
        $importeTotal = (float) ($proforma['importe_total'] ?? 0);
        $pagos = [[
            'forma_pago'    => '01',
            'total'         => $importeTotal,
            'plazo'         => 0,
            'unidad_tiempo' => 'dias',
        ]];

        $numProf = ($proforma['establecimiento'] ?? '') . '-' . ($proforma['punto_emision'] ?? '') . '-' . ($proforma['secuencial'] ?? '');

        $dataRecibo = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaData,
            'id_establecimiento'  => $idEstab,
            'id_punto_emision'    => $idPunto,
            'establecimiento'     => (string) ($punto['cod_establecimiento'] ?? $est['codigo'] ?? '001'),
            'punto_emision'       => (string) ($punto['codigo_punto'] ?? $punto['codigo'] ?? '001'),
            'secuencial'          => $secuencial,
            'fecha_emision'       => date('Y-m-d'),
            'id_cliente'          => (int) $proforma['id_cliente'],
            'id_vendedor'         => !empty($proforma['id_vendedor']) ? (int) $proforma['id_vendedor'] : null,
            'dias_credito'        => 0,
            'plazo'               => 0,
            'moneda'              => $proforma['moneda'] ?? 'DOLAR',
            'con_impuestos'       => true,
            'estado'              => 'borrador',
            'observaciones'       => trim('Generado desde proforma ' . $numProf . '. ' . ($proforma['observaciones'] ?? '')),
            'id_bodega'           => $idBodega,
            'total_sin_impuestos' => (float) ($proforma['total_sin_impuestos'] ?? 0),
            'total_descuento'     => (float) ($proforma['total_descuento'] ?? 0),
            'total_ice'           => (float) ($proforma['total_ice'] ?? 0),
            'propina'             => 0,
            'importe_total'       => $importeTotal,
            'detalles'            => $detallesRec,
            'pagos'               => $pagos,
            'info_adicional'      => $infoAdic,
        ];

        // crear() detecta la transacción ya abierta arriba y se engancha a ella (no hace su
        // propio commit/rollback). El recibo queda en 'borrador' para revisión/emisión.
        $recService = new ReciboVentaService(
            new \App\repositories\modulos\ReciboVentaRepository(),
            new \App\Rules\modulos\ReciboVentaRules(),
            new LogSistemaService()
        );
        $idRecibo = $recService->crear($dataRecibo);
        if ($managedTransaction) {
            $db->commit();
        }
        } catch (\Throwable $e) {
            if ($managedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_recibo',
                'proformas_cabecera',
                $id,
                null,
                ['id_recibo' => $idRecibo]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        return ['id_recibo' => $idRecibo];
    }

    /**
     * Genera un PEDIDO a partir de la proforma aprobada ("enviar a pedidos").
     *
     * A diferencia de la factura y del recibo, el pedido NO mueve inventario ni emite
     * documento electrónico: es la orden de despacho de lo cotizado. Nace en estado
     * "Pendiente" y el usuario completa desde el módulo Pedidos los datos de entrega
     * (fecha, horario y responsable), que la proforma no tiene.
     *
     * Reglas:
     *  - Desde una proforma en **borrador o aprobada** (o ya convertida, para volver a pedir
     *    lo mismo): ver ESTADOS_DESPACHABLES. Es más permisivo que convertirAFactura() y
     *    convertirARecibo(), que exigen 'aprobada'. Desde un borrador el pedido es una copia
     *    del momento: si la proforma se edita después, el pedido NO se actualiza (la UI lo
     *    advierte antes de generarlo).
     *  - TODAS las líneas deben apuntar a un producto del catálogo: pedidos_detalle.id_producto
     *    es NOT NULL y el consumo desde Consignaciones/Facturas cruza por producto, así que una
     *    proforma con ítems de concepto libre se rechaza entera —nombrando las líneas
     *    culpables— en vez de crear un pedido incompleto en silencio.
     *  - Si la proforma ya tiene pedidos vigentes se pide confirmación, como con la factura.
     *
     * La proforma NO cambia de estado: 'convertida' significa facturada, y un pedido no
     * factura. El vínculo queda en pedidos_cabecera.id_proforma.
     *
     * @return array{id_pedido?:int,numero?:string,requiere_confirmacion?:bool,mensaje?:string,items_sin_producto?:string[]}
     */
    public function convertirAPedido(int $id, int $idEmpresa, int $idUsuario, bool $forzar = false): array
    {
        $proforma = $this->repository->getPorId($id);
        if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        $yaConvertida = !empty($proforma['id_factura_convertida']) || $proforma['estado'] === 'convertida';
        if (!$yaConvertida && !in_array($proforma['estado'], self::ESTADOS_DESPACHABLES, true)) {
            throw new \RuntimeException('Solo se genera un pedido desde una proforma en borrador o aprobada.');
        }

        $detallesPf = $this->repository->getDetalles($id);
        if (empty($detallesPf)) {
            throw new \RuntimeException('La proforma no tiene líneas de detalle para el pedido.');
        }

        // ── Bloqueo por ítems de concepto libre (sin producto de catálogo) ──
        $sinProducto = [];
        foreach ($detallesPf as $d) {
            if (empty($d['id_producto'])) {
                $sinProducto[] = trim((string) ($d['descripcion'] ?? '')) ?: 'Línea sin descripción';
            }
        }
        if (!empty($sinProducto)) {
            return ['items_sin_producto' => $sinProducto];
        }

        // ── ¿Ya hay pedidos generados desde esta proforma? ──
        $pedidoRepo      = new \App\Repositories\Modulos\PedidoRepository();
        $pedidosVigentes = $pedidoRepo->getPorProforma($id, $idEmpresa);
        if (!empty($pedidosVigentes) && !$forzar) {
            return [
                'requiere_confirmacion' => true,
                'mensaje' => 'Esta proforma ya tiene un pedido asociado. ¿Desea crear otro de todos modos?',
            ];
        }

        // ── Contexto de la serie: primer establecimiento y primer punto con secuencial
        //    de "Pedidos" configurado (mismo criterio que factura y recibo) ──
        $empresaModel     = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            throw new \RuntimeException('La empresa no tiene establecimientos configurados.');
        }
        $est     = $establecimientos[0];
        $idEstab = (int) $est['id'];

        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos  = [];
        foreach ($empresaModel->getPuntosEmision($idEstab) as $p) {
            $secConfig = $secRepo->getConfigSecuencial((int) $p['id'], 'Pedidos');
            if (empty($secConfig['id'])) {
                continue;
            }
            $puntos[] = $p;
        }
        if (empty($puntos)) {
            throw new \RuntimeException('El establecimiento no tiene un punto de emisión con secuencial configurado para Pedidos.');
        }
        $punto   = $puntos[0];
        $idPunto = (int) $punto['id'];

        // ── Mapear las líneas ──
        // pedidos_detalle no tiene columna de descuento: el subtotal de la proforma
        // (precio_total_sin_impuesto) ya viene neto, así que el valor del pedido cuadra
        // con lo cotizado aunque el precio unitario se guarde bruto.
        $impuestosPorLinea = $this->repository->getImpuestosPorDetalles(array_column($detallesPf, 'id'));

        $detallesPed = [];
        foreach ($detallesPf as $d) {
            $iva        = 0.0;
            $impuestos  = 0.0;
            foreach ($impuestosPorLinea[(int) $d['id']] ?? [] as $imp) {
                $valor      = (float) ($imp['valor'] ?? 0);
                $impuestos += $valor;
                if ((string) ($imp['codigo_impuesto'] ?? '2') === '2') {
                    $iva += $valor;   // código 2 = IVA (el ICE suma al total, no al IVA)
                }
            }
            $subtotal = (float) ($d['precio_total_sin_impuesto'] ?? 0);

            $detallesPed[] = [
                'id_producto'     => (int) $d['id_producto'],
                'cantidad'        => (float) ($d['cantidad'] ?? 0),
                'precio_unitario' => (float) ($d['precio_unitario'] ?? 0),
                'subtotal'        => round($subtotal, 2),
                'iva'             => round($iva, 2),
                'total'           => round($subtotal + $impuestos, 2),
            ];
        }

        $numProf = ($proforma['establecimiento'] ?? '') . '-' . ($proforma['punto_emision'] ?? '') . '-' . ($proforma['secuencial'] ?? '');

        $cabecera = [
            'id_cliente'             => (int) $proforma['id_cliente'],
            // Fecha Y HORA del momento en que se envía a pedidos: pedidos_cabecera.fecha_pedido
            // es timestamp, y así queda constancia de cuándo salió, no solo del día (el modal
            // de Pedidos usa un <input type="date">, por eso los pedidos hechos a mano quedan
            // a las 00:00:00). Pasar la hora no afecta al secuencial por fecha: getPrefijoPeriodo()
            // resuelve el periodo con strtotime().
            'fecha_pedido'           => date('Y-m-d H:i:s'),
            'observaciones'          => trim('Generado desde proforma ' . $numProf . '. ' . ($proforma['observaciones'] ?? '')),
            'observaciones_internas' => '',
            // La proforma no tiene datos de entrega: los completa el usuario en Pedidos.
            'fecha_entrega'          => null,
            'hora_inicial_entrega'   => null,
            'hora_maxima_entrega'    => null,
            'id_responsable_entrega' => null,
            'id_establecimiento'     => $idEstab,
            'id_punto_emision'       => $idPunto,
            'establecimiento'        => (string) ($punto['cod_establecimiento'] ?? $est['codigo'] ?? '001'),
            'punto_emision'          => (string) ($punto['codigo_punto'] ?? $punto['codigo'] ?? '001'),
            'id_proforma'            => $id,
        ];

        // guardarPedido() abre y cierra su propia transacción, con el advisory lock del
        // secuencial dentro (CLAUDE.md §8). Por eso aquí NO se abre otra: se delega entero
        // para reutilizar su numeración autoritativa, su control de duplicados y su
        // auditoría, en vez de duplicar el INSERT (CLAUDE.md §3).
        $pedidoService = new \App\Services\Modulos\PedidoService();
        $idPedido      = (int) $pedidoService->guardarPedido($cabecera, $detallesPed, $idEmpresa, $idUsuario);

        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'convertir_a_pedido',
                'proformas_cabecera',
                $id,
                null,
                ['id_pedido' => $idPedido]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        $pedido = $pedidoRepo->obtenerPorId($idPedido, $idEmpresa) ?: [];

        return [
            'id_pedido' => $idPedido,
            'numero'    => (string) ($pedido['numero_pedido'] ?? ''),
        ];
    }

    /**
     * Verifica el stock disponible de los productos de la proforma según la
     * configuración del establecimiento. Devuelve la lista de productos sin saldo
     * suficiente (o [] si la config no exige stock o hay saldo para todos).
     *
     * Reglas (idénticas a FacturaVentaService::crear):
     *  - Solo aplica si facturacion_inventario Y factura_solo_stock_positivo están activas.
     *  - Solo productos de catálogo, inventariables y que no sean servicios (tipo_produccion '02').
     *  - Cantidades acumuladas por producto (varias líneas del mismo producto suman).
     *
     * @return array<int,array{producto:string,disponible:float,requerido:float}>
     */
    private function verificarStockDisponible(array $detalles, array $estConfig, int $idEmpresa, int $idBodega): array
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $afectaInv    = $toBool($estConfig['facturacion_inventario'] ?? false);
        $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);

        // La empresa no exige stock: se factura sin validar (permite negativos).
        if (!$afectaInv || !$soloStockPos) {
            return [];
        }

        $productoRepo = new \App\repositories\modulos\ProductoRepository();
        $invRepo      = new \App\repositories\modulos\InventarioRepository();

        // Acumular cantidades requeridas por producto inventariable.
        $requerido = [];
        foreach ($detalles as $d) {
            if (empty($d['id_producto'])) continue; // línea libre: no afecta inventario
            $idProd = (int) $d['id_producto'];

            if (!isset($requerido[$idProd])) {
                $info = $productoRepo->getInfoControlInventario($idProd, $idEmpresa);
                if (empty($info['inventariable']) || ($info['tipo_produccion'] ?? '') === '02') {
                    continue; // no inventariable o servicio: no valida stock
                }
                $requerido[$idProd] = ['nombre' => $d['descripcion'] ?? 'Producto', 'cantidad' => 0.0];
            }
            $requerido[$idProd]['cantidad'] += (float) ($d['cantidad'] ?? 0);
        }

        // Comparar contra el saldo actual en la bodega destino.
        $faltantes = [];
        foreach ($requerido as $idProd => $info) {
            $stock = $invRepo->getStockActual($idProd, $idBodega, $idEmpresa);
            if ($stock < $info['cantidad']) {
                $faltantes[] = [
                    'producto'   => $info['nombre'],
                    'disponible' => $stock,
                    'requerido'  => $info['cantidad'],
                ];
            }
        }
        return $faltantes;
    }

    /**
     * Resuelve el lote/caducidad/NUP a usar en una línea al convertir a factura,
     * replicando la selección automática de la factura de venta (FEFO: el lote más
     * antiguo por caducidad de la bodega destino). Solo aplica a productos de catálogo
     * inventariables que no sean servicios (tipo_produccion '02').
     *
     * Si el establecimiento no factura con inventario, o el producto no es inventariable,
     * devuelve valores nulos (la línea no lleva lote).
     *
     * @return array{lote:?string,caducidad:?string,nup:?string}
     */
    private function resolverLoteAutomatico(array $det, array $estConfig, int $idEmpresa, int $idBodega): array
    {
        $vacio = ['lote' => null, 'caducidad' => null, 'nup' => null];

        if (empty($det['id_producto'])) {
            return $vacio; // línea libre
        }

        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $afectaInv = $toBool($estConfig['facturacion_inventario'] ?? false);
        if (!$afectaInv) {
            return $vacio; // el establecimiento no factura con inventario
        }

        $productoRepo = new \App\repositories\modulos\ProductoRepository();
        $info = $productoRepo->getInfoControlInventario((int) $det['id_producto'], $idEmpresa);
        if (empty($info['inventariable']) || ($info['tipo_produccion'] ?? '') === '02') {
            return $vacio; // no inventariable o servicio
        }

        // FEFO: si el establecimiento exige stock positivo, solo lotes con saldo;
        // caso contrario, el más antiguo aunque no tenga saldo (permite negativo).
        $soloStockPos = $toBool($estConfig['factura_solo_stock_positivo'] ?? false);
        $invRepo  = new \App\repositories\modulos\InventarioRepository();
        $loteAuto = $invRepo->getLoteMasAntiguo((int) $det['id_producto'], $idBodega, $idEmpresa, null, $soloStockPos);

        if (!$loteAuto) {
            return $vacio; // sin lote disponible: lo resolverá el inventario o fallará la regla si es obligatorio
        }

        return [
            'lote'      => !empty($loteAuto['numero_lote'])    ? (string) $loteAuto['numero_lote']    : null,
            'caducidad' => !empty($loteAuto['fecha_caducidad']) ? (string) $loteAuto['fecha_caducidad'] : null,
            'nup'       => !empty($loteAuto['nup'])            ? (string) $loteAuto['nup']            : null,
        ];
    }

    /**
     * Obtiene el siguiente secuencial para proformas en un punto de emisión.
     */
    public function getSiguienteSecuencial(int $idPunto, ?string $fecha = null): array
    {
        $secService = new SecuencialService();
        return $secService->obtenerSiguienteSecuencial($idPunto, 'Proformas', $fecha);
    }

    /**
     * Devuelve (creándolo si no existe) el token de aprobación pública de la proforma.
     * El token viaja en el enlace del correo para que el cliente apruebe sin login.
     */
    public function obtenerTokenAprobacion(int $id, int $idEmpresa): string
    {
        $prof = $this->repository->getPorId($id);
        if (!$prof || (int) $prof['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException('Proforma no encontrada.');
        }
        if (!empty($prof['aprobacion_token'])) {
            return (string) $prof['aprobacion_token'];
        }
        $token = bin2hex(random_bytes(24));
        $this->repository->setTokenAprobacion($id, $token);
        return $token;
    }

    /** Proforma por token de aprobación (para la página pública). */
    public function getAprobacionPorToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;
        return $this->repository->getPorTokenAprobacion($token);
    }

    /**
     * Aprobación pública por el cliente (desde el correo). Solo si está en borrador.
     * @return array{numero:string}
     * @throws \RuntimeException
     */
    public function aprobarPorTokenCliente(string $token, string $comentario, string $ip): array
    {
        $prof = $this->getAprobacionPorToken($token);
        if (!$prof) {
            throw new \RuntimeException('El enlace no es válido o la proforma ya no está disponible.');
        }
        if ($prof['estado'] !== 'borrador') {
            $mapa = [
                'aprobada'   => 'Esta proforma ya fue aprobada.',
                'rechazada'  => 'Esta proforma fue rechazada y no puede aprobarse.',
                'anulada'    => 'Esta proforma está anulada.',
                'convertida' => 'Esta proforma ya fue facturada.',
            ];
            throw new \RuntimeException($mapa[$prof['estado']] ?? 'Esta proforma ya no puede aprobarse.');
        }

        $id        = (int) $prof['id'];
        $idEmpresa = (int) $prof['id_empresa'];
        $comentario = trim(mb_substr($comentario, 0, 1000));

        $this->repository->registrarAprobacionCliente($id, $comentario, $ip);

        try {
            $this->log->registrar(
                0, $idEmpresa, 'aprobar_cliente', 'proformas_cabecera', $id,
                ['estado' => 'borrador'],
                ['estado' => 'aprobada', 'comentario' => $comentario, 'ip' => $ip]
            );
        } catch (\Throwable $e) { /* log no crítico */ }

        $numero = ($prof['establecimiento'] ?? '') . '-' . ($prof['punto_emision'] ?? '') . '-'
                . str_pad((string) ($prof['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        return ['numero' => $numero];
    }

    private function guardarDetalles(int $idProforma, array $detalles): void
    {
        foreach ($detalles as $det) {
            $det['id_proforma'] = $idProforma;
            $idDetalle = $this->repository->insertDetalle($det);

            if (!empty($det['impuestos']) && is_array($det['impuestos'])) {
                foreach ($det['impuestos'] as $imp) {
                    if ((float) ($imp['valor'] ?? 0) == 0 && (float) ($imp['base_imponible'] ?? 0) == 0) continue;
                    $this->repository->insertImpuesto([
                        'id_proforma_detalle' => $idDetalle,
                        'codigo_impuesto'     => $imp['codigo_impuesto'] ?? '2',
                        'codigo_porcentaje'   => $imp['codigo_porcentaje'] ?? '2',
                        'tarifa'              => (float) ($imp['tarifa'] ?? 0),
                        'base_imponible'      => (float) ($imp['base_imponible'] ?? 0),
                        'valor'               => (float) ($imp['valor'] ?? 0),
                    ]);
                }
            }
        }
    }

    private function guardarInfoAdicional(int $idProforma, array $adicional): void
    {
        foreach ($adicional as $item) {
            $nombre = trim($item['nombre'] ?? '');
            $valor  = trim($item['valor'] ?? '');
            if ($nombre === '' || $valor === '') continue;
            $this->repository->insertInfoAdicional([
                'id_proforma' => $idProforma,
                'nombre'      => $nombre,
                'valor'       => $valor,
            ]);
        }
    }
}

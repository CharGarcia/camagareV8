<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\FacturaExpressQrRepository;
use App\Rules\modulos\FacturaExpressQrRules;
use App\Services\LogSistemaService;
use Exception;

class FacturaExpressQrService
{
    private FacturaExpressQrRepository $repo;
    private FacturaExpressQrRules      $rules;
    private LogSistemaService          $log;

    public function __construct(
        FacturaExpressQrRepository $repo,
        FacturaExpressQrRules      $rules,
        LogSistemaService          $log
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
    }

    // ═══════════════════════════════════════════════════════
    // PLANTILLAS
    // ═══════════════════════════════════════════════════════

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repo->getListadoPlantillas($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
    }

    public function crearPlantilla(array $data): int
    {
        $items = $this->extraerItems($data);
        $this->rules->validarPlantilla($data);
        $this->rules->validarItems($items);

        // Generar token UUID único
        do {
            $token = bin2hex(random_bytes(32));
        } while ($this->repo->tokenExiste($token));

        $data['token'] = $token;

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->insertPlantilla($data);

            foreach ($items as $idx => $item) {
                $item['id_plantilla'] = $id;
                $item['id_empresa']   = (int) $data['id_empresa'];
                $item['id_usuario']   = (int) $data['id_usuario'];
                $item['orden']        = $idx;
                $this->repo->insertItem($item);
            }

            $this->log->registrar((int)$data['id_usuario'], (int)$data['id_empresa'], 'crear', 'factura_express_plantillas', $id, null, $data);
            $this->repo->commit();
            return $id;
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function actualizarPlantilla(int $id, int $idEmpresa, array $data): void
    {
        $items = $this->extraerItems($data);
        $this->rules->validarPlantilla($data);
        $this->rules->validarItems($items);

        $antes = $this->repo->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('Plantilla no encontrada.');

        $idUsuario = (int) $data['id_usuario'];

        $this->repo->beginTransaction();
        try {
            $this->repo->updatePlantilla($id, $idEmpresa, $data);

            // Reemplazar ítems: soft-delete existentes e insertar nuevos
            $this->repo->deleteItemsPorPlantilla($id, $idUsuario);
            foreach ($items as $idx => $item) {
                $item['id_plantilla'] = $id;
                $item['id_empresa']   = $idEmpresa;
                $item['id_usuario']   = $idUsuario;
                $item['orden']        = $idx;
                $this->repo->insertItem($item);
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'factura_express_plantillas', $id, $antes, $data);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function eliminarPlantilla(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repo->findById($id, $idEmpresa);
        if (!$antes) throw new Exception('Plantilla no encontrada.');

        $this->repo->beginTransaction();
        try {
            $this->repo->deletePlantilla($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'factura_express_plantillas', $id, $antes, []);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function getPlantillaConItems(int $id, int $idEmpresa): array
    {
        $plantilla = $this->repo->findById($id, $idEmpresa);
        if (!$plantilla) throw new Exception('Plantilla no encontrada.');
        $plantilla['items'] = $this->repo->getItemsPorPlantilla($id);
        return $plantilla;
    }

    public function getUrlQr(int $id, int $idEmpresa): string
    {
        $plantilla = $this->repo->findById($id, $idEmpresa);
        if (!$plantilla) throw new Exception('Plantilla no encontrada.');
        return rtrim(BASE_URL, '/') . '/factura-express/' . $plantilla['token'];
    }

    // ═══════════════════════════════════════════════════════
    // SOLICITUDES (desde formulario público)
    // ═══════════════════════════════════════════════════════

    public function recibirSolicitudPublica(string $token, array $formData, array $itemsSeleccionados, string $ip, string $userAgent): array
    {
        $plantilla = $this->repo->getPlantillaByToken($token);
        if (!$plantilla) throw new Exception('Plantilla no válida o inactiva.');

        // Anti-spam
        $maxPorHora = (int) ($plantilla['max_solicitudes_hora'] ?? 10);
        $conteo     = $this->repo->contarSolicitudesPorIp($ip, (int)$plantilla['id'], 60);
        if ($conteo >= $maxPorHora) {
            throw new Exception('Has enviado demasiadas solicitudes. Por favor espera un momento antes de intentar nuevamente.');
        }

        // Validar datos del formulario
        $this->rules->validarSolicitudPublica($formData, $itemsSeleccionados);

        // Calcular monto
        $montoTotal = 0.0;
        foreach ($itemsSeleccionados as $item) {
            $base        = (float)$item['cantidad'] * (float)$item['precio_unitario'];
            $montoTotal += $base + $base * ((float)($item['porcentaje_iva'] ?? 0) / 100);
        }

        // Token único para que el cliente consulte el estado
        $tokenCliente = bin2hex(random_bytes(24));

        $data = [
            'id_plantilla'        => (int) $plantilla['id'],
            'id_empresa'          => (int) $plantilla['id_empresa'],
            'nombre_cliente'      => trim($formData['nombre_cliente']),
            'identificacion'      => preg_replace('/\D/', '', $formData['identificacion']),
            'tipo_identificacion' => $formData['tipo_identificacion'] ?? 'cedula',
            'correo_cliente'      => trim($formData['correo_cliente'] ?? ''),
            'telefono_cliente'    => trim($formData['telefono_cliente'] ?? ''),
            'direccion_cliente'   => trim($formData['direccion_cliente'] ?? ''),
            'items_json'          => json_encode($itemsSeleccionados, JSON_UNESCAPED_UNICODE),
            'monto_total'         => round($montoTotal, 2),
            'ip_origen'           => $ip,
            'user_agent'          => substr($userAgent, 0, 500),
            'token_cliente'       => $tokenCliente,
        ];

        $this->repo->beginTransaction();
        try {
            $idSolicitud = $this->repo->insertSolicitud($data);

            // Si la plantilla NO requiere aprobación, facturar directo. El flujo es
            // público (sin sesión), así que la operación se atribuye al dueño que creó
            // la plantilla; usar id 0 viola la FK de auditoría (created_by → usuarios).
            if (!$plantilla['requiere_aprobacion']) {
                $idUsuarioSistema = (int) ($plantilla['created_by'] ?? 0);
                $this->_facturarSolicitud($idSolicitud, (int)$plantilla['id_empresa'], $idUsuarioSistema, $plantilla);
            }

            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return [
            'id'            => $idSolicitud,
            'token_cliente' => $tokenCliente,
            'monto_total'   => round($montoTotal, 2),
            'plantilla'     => $plantilla,
            'data'          => $data,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // SOLICITUDES (gestión con login)
    // ═══════════════════════════════════════════════════════

    public function getListadoSolicitudes(int $idEmpresa, string $buscar, string $estado, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idPlantilla = null): array
    {
        return $this->repo->getListadoSolicitudes($idEmpresa, $buscar, $estado, $page, $perPage, $ordenCol, $ordenDir, $idPlantilla);
    }

    /** Nombre de una plantilla (para el encabezado del panel móvil). */
    public function getNombrePlantilla(int $idPlantilla, int $idEmpresa): ?string
    {
        $p = $this->repo->findById($idPlantilla, $idEmpresa);
        return $p['nombre'] ?? null;
    }

    public function getSolicitud(int $id, int $idEmpresa): array
    {
        $s = $this->repo->getSolicitudById($id, $idEmpresa);
        if (!$s) throw new Exception('Solicitud no encontrada.');
        $s['items'] = json_decode($s['items_json'] ?? '[]', true) ?: [];
        return $s;
    }

    public function aprobarYFacturar(int $idSolicitud, int $idEmpresa, int $idUsuario, array $datosEditados): void
    {
        $solicitud = $this->repo->getSolicitudById($idSolicitud, $idEmpresa);
        if (!$solicitud) throw new Exception('Solicitud no encontrada.');
        if ($solicitud['estado'] !== 'pendiente') throw new Exception('Esta solicitud ya fue procesada.');

        $plantilla = $this->repo->getPlantillaByToken(''); // not needed, use findById
        // Cargar plantilla por id
        $plantilla = $this->repo->findById((int)$solicitud['id_plantilla'], $idEmpresa);

        $this->repo->beginTransaction();
        $idFacturaCreada = null;
        try {
            $idFacturaCreada = $this->_facturarSolicitud($idSolicitud, $idEmpresa, $idUsuario, $plantilla ?? [], $datosEditados);
            $this->log->registrar($idUsuario, $idEmpresa, 'aprobar_factura_express', 'factura_express_solicitudes', $idSolicitud, $solicitud, []);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    public function rechazarSolicitud(int $idSolicitud, int $idEmpresa, int $idUsuario, string $nota): void
    {
        $solicitud = $this->repo->getSolicitudById($idSolicitud, $idEmpresa);
        if (!$solicitud) throw new Exception('Solicitud no encontrada.');
        if ($solicitud['estado'] !== 'pendiente') throw new Exception('Esta solicitud ya fue procesada.');

        $this->repo->beginTransaction();
        try {
            $this->repo->updateEstadoSolicitud($idSolicitud, 'rechazada', $idUsuario, $nota);
            $this->log->registrar($idUsuario, $idEmpresa, 'rechazar_factura_express', 'factura_express_solicitudes', $idSolicitud, $solicitud, ['nota' => $nota]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ═══════════════════════════════════════════════════════
    // FACTURACIÓN INTERNA
    // ═══════════════════════════════════════════════════════

    private function _facturarSolicitud(int $idSolicitud, int $idEmpresa, int $idUsuario, array $plantilla, array $datosEditados = []): int
    {
        $solicitud = $this->repo->getSolicitudById($idSolicitud, $idEmpresa);
        if (!$solicitud) throw new Exception('Solicitud no encontrada al facturar.');

        $items = json_decode($solicitud['items_json'] ?? '[]', true) ?: [];
        if (!empty($datosEditados['items'])) {
            $items = $datosEditados['items'];
        }

        // Buscar o crear cliente en el sistema
        $idClienteSys = $this->_buscarOCrearCliente($solicitud, $idEmpresa, $idUsuario);

        // Delegar a FacturaVentaService
        $factService = new FacturaVentaService(
            new \App\repositories\modulos\FacturaVentaRepository(),
            new \App\Rules\modulos\FacturaVentaRules(),
            new \App\Services\LogSistemaService()
        );

        $facturaData = $this->_construirDataFactura($items, $idClienteSys, $idEmpresa, $idUsuario, $plantilla);
        $idFactura   = $factService->crear($facturaData);

        $this->repo->marcarFacturada($idSolicitud, $idFactura, $idClienteSys, $idUsuario);

        return $idFactura;
    }

    private function _buscarOCrearCliente(array $solicitud, int $idEmpresa, int $idUsuario): int
    {
        $db = $this->repo->getDb();

        // Buscar cliente existente por identificación y empresa (incluyendo eliminados)
        $st = $db->prepare(
            "SELECT id FROM clientes
             WHERE identificacion = :ident AND id_empresa = :empresa
             LIMIT 1"
        );
        $st->execute([':ident' => $solicitud['identificacion'], ':empresa' => $idEmpresa]);
        $existing = $st->fetchColumn();

        if ($existing) {
            // Cliente ya registrado (por cédula/RUC): reutilizar y actualizar sus datos
            // Reactivamos si estaba eliminado y actualizamos con lo recibido
            $up = $db->prepare(
                "UPDATE clientes
                 SET nombre   = :nombre,
                     email    = COALESCE(NULLIF(:email, ''), email),
                     telefono = COALESCE(NULLIF(:tel, ''), telefono),
                     eliminado = false,
                     updated_at = NOW(), updated_by = :uid
                 WHERE id = :id"
            );
            $up->execute([
                ':nombre' => $solicitud['nombre_cliente'],
                ':email'  => trim((string) ($solicitud['correo_cliente'] ?? '')),
                ':tel'    => trim((string) ($solicitud['telefono_cliente'] ?? '')),
                ':uid'    => $idUsuario,
                ':id'     => (int) $existing,
            ]);
            return (int) $existing;
        }

        // Crear cliente nuevo mediante el repositorio canónico
        $tipoIdMap = ['cedula' => '05', 'ruc' => '04', 'pasaporte' => '06', 'sin_ruc' => '07'];
        $tipoId    = $tipoIdMap[$solicitud['tipo_identificacion'] ?? 'cedula'] ?? '05';

        $clienteRepo = new \App\repositories\modulos\ClienteRepository();
        return $clienteRepo->create([
            'id_empresa'     => $idEmpresa,
            'id_usuario'     => $idUsuario,
            'nombre'         => $solicitud['nombre_cliente'],
            'tipo_id'        => $tipoId,
            'identificacion' => $solicitud['identificacion'],
            'telefono'       => $solicitud['telefono_cliente'] ?: null,
            'email'          => $solicitud['correo_cliente'] ?: null,
            'direccion'      => $solicitud['direccion_cliente'] ?? null,
            'provincia'      => null,
            'ciudad'         => null,
            'id_vendedor'    => null,
            'plazo'          => 0,
            'status'         => 1,
        ]);
    }

    private function _construirDataFactura(array $items, int $idCliente, int $idEmpresa, int $idUsuario, array $plantilla): array
    {
        $db = $this->repo->getDb();

        // Validar que la plantilla tenga serie configurada
        $idEstablecimiento = !empty($plantilla['id_establecimiento']) ? (int)$plantilla['id_establecimiento'] : null;
        $idPuntoEmision    = !empty($plantilla['id_punto_emision'])   ? (int)$plantilla['id_punto_emision']   : null;
        $formaPago         = $plantilla['forma_pago'] ?? '20';

        if (!$idEstablecimiento || !$idPuntoEmision) {
            throw new \Exception('La plantilla no tiene una serie (establecimiento y punto de emisión) configurada. Por favor configúrela antes de aprobar.');
        }

        // Verificar que el establecimiento y punto de emisión existen, pertenecen a la empresa y están activos
        $st = $db->prepare(
            "SELECT ep.id, ep.codigo, pe.id AS id_punto_emision, pe.codigo_punto AS punto_codigo
             FROM empresa_establecimiento ep
             JOIN empresa_punto_emision pe ON pe.id = :id_pe AND pe.id_establecimiento = ep.id
             WHERE ep.id = :id_est AND ep.id_empresa = :id_emp
               AND ep.estado = 'activo' AND pe.estado = 'activo'
               AND ep.eliminado = false AND pe.eliminado = false"
        );
        $st->execute([':id_est' => $idEstablecimiento, ':id_pe' => $idPuntoEmision, ':id_emp' => $idEmpresa]);
        $estab = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$estab) {
            throw new \Exception('El establecimiento/punto de emisión configurado en la plantilla no está activo o no pertenece a esta empresa.');
        }

        // Obtener configuración de empresa (tabla empresas)
        $stEmp = $db->prepare("SELECT * FROM empresas WHERE id = :id LIMIT 1");
        $stEmp->execute([':id' => $idEmpresa]);
        $empresaConfig = $stEmp->fetch(\PDO::FETCH_ASSOC);
        if (!$empresaConfig) throw new \Exception('No se encontró la empresa.');

        $secService = new \App\Services\SecuencialService();
        $secResult  = $secService->obtenerSiguienteSecuencial((int)$estab['id_punto_emision'], 'Facturas de venta');
        $secuencial = $secResult['formateado'];

        // Pre-cargar códigos de los productos de catálogo referenciados por los ítems
        $idsProd = array_values(array_unique(array_filter(array_map(
            fn($i) => (int) ($i['id_producto'] ?? 0),
            $items
        ))));
        $prodInfo = [];
        if ($idsProd) {
            $in  = implode(',', array_fill(0, count($idsProd), '?'));
            $stP = $db->prepare(
                "SELECT p.id, p.codigo, p.codigo_auxiliar, p.id_medida,
                        ti.porcentaje_iva AS porcentaje_iva
                 FROM productos p
                 LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                 WHERE p.id IN ($in) AND p.id_empresa = ? AND p.eliminado = false"
            );
            $stP->execute([...$idsProd, $idEmpresa]);
            foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $prodInfo[(int) $p['id']] = $p;
            }
        }

        $detalles    = [];
        $totalSinImp = 0.0;
        $totalIva    = 0.0;

        foreach ($items as $det) {
            $idProd  = (int) ($det['id_producto'] ?? 0);
            $info    = $prodInfo[$idProd] ?? null;
            // Sin producto de catálogo válido → ítem libre (se creará en el catálogo al facturar)
            $esLibre = ($idProd <= 0 || $info === null);

            // El IVA de un ítem de catálogo SIEMPRE es la tarifa actual del producto;
            // solo los ítems libres usan el porcentaje enviado en la solicitud.
            $porcentajeIva = (!$esLibre && $info['porcentaje_iva'] !== null)
                ? (float) $info['porcentaje_iva']
                : (float) ($det['porcentaje_iva'] ?? 0);

            $base = round((float)$det['cantidad'] * (float)$det['precio_unitario'], 2);
            $iva  = round($base * ($porcentajeIva / 100), 2);
            $totalSinImp += $base;
            $totalIva    += $iva;

            $detalles[] = [
                'id_producto'               => $esLibre ? null : $idProd,
                'es_libre'                  => $esLibre ? '1' : '0',
                'nombre'                    => $det['descripcion'],
                'codigo_principal'          => $info['codigo'] ?? '000',
                'codigo_auxiliar'           => $info['codigo_auxiliar'] ?? null,
                'id_unidad_medida'          => $info['id_medida'] ?? null,
                'descripcion'               => $det['descripcion'],
                'cantidad'                  => $det['cantidad'],
                'precio_unitario'           => $det['precio_unitario'],
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $base,
                'porcentaje_iva'            => $porcentajeIva,
                // El SRI exige SIEMPRE una línea de impuesto IVA por detalle, incluso
                // con tarifa 0% (código '0', valor 0). Omitirla deja 'totalConImpuestos'
                // vacío y el comprobante es rechazado por estructura XML inválida.
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => \App\Helpers\SriIvaHelper::codigoPorcentaje($porcentajeIva),
                    'tarifa'            => $porcentajeIva,
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]],
            ];
        }

        return [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'id_cliente'          => $idCliente,
            'id_establecimiento'  => $estab['id'],
            'id_punto_emision'    => $estab['id_punto_emision'],
            'id_bodega'           => null,
            'fecha_emision'       => date('Y-m-d'),
            'establecimiento'     => $estab['codigo'],
            'punto_emision'       => $estab['punto_codigo'],
            'secuencial'          => $secuencial,
            'empresa_config'      => $empresaConfig,
            'detalles'            => $detalles,
            'pagos'               => [['forma_pago' => $formaPago, 'total' => round($totalSinImp + $totalIva, 2), 'plazo' => 0]],
            'info_adicional'      => [],
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => 0,
            'importe_total'       => round($totalSinImp + $totalIva, 2),
            'propina'             => 0,
            'observaciones'       => null,
        ];
    }

    // ═══════════════════════════════════════════════════════
    // UTILIDADES
    // ═══════════════════════════════════════════════════════

    public function getTarifasIva(): array
    {
        return $this->repo->getTarifasIva();
    }

    public function getEmpresaConfig(int $idEmpresa): array
    {
        return $this->repo->getEmpresaConfig($idEmpresa);
    }

    private function extraerItems(array &$data): array
    {
        $items = $data['items'] ?? [];
        unset($data['items']);
        return array_values(array_filter((array)$items, fn($i) => !empty(trim($i['descripcion'] ?? ''))));
    }
}

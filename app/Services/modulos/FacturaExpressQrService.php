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

            // Si la plantilla NO requiere aprobación, facturar directo
            if (!$plantilla['requiere_aprobacion']) {
                $this->_facturarSolicitud($idSolicitud, (int)$plantilla['id_empresa'], 0, $plantilla);
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

    public function getListadoSolicitudes(int $idEmpresa, string $buscar, string $estado, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        return $this->repo->getListadoSolicitudes($idEmpresa, $buscar, $estado, $page, $perPage, $ordenCol, $ordenDir);
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
        try {
            $this->_facturarSolicitud($idSolicitud, $idEmpresa, $idUsuario, $plantilla ?? [], $datosEditados);
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

    private function _facturarSolicitud(int $idSolicitud, int $idEmpresa, int $idUsuario, array $plantilla, array $datosEditados = []): void
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
    }

    private function _buscarOCrearCliente(array $solicitud, int $idEmpresa, int $idUsuario): int
    {
        $db = $this->repo->getDb();

        // Buscar cliente existente por identificación y empresa
        $st = $db->prepare(
            "SELECT id FROM clientes
             WHERE identificacion = :ident AND id_empresa = :empresa AND eliminado = false
             LIMIT 1"
        );
        $st->execute([':ident' => $solicitud['identificacion'], ':empresa' => $idEmpresa]);
        $existing = $st->fetchColumn();
        if ($existing) return (int) $existing;

        // Crear cliente nuevo
        $st2 = $db->prepare(
            "INSERT INTO clientes (id_empresa, nombre, identificacion, tipo_identificacion,
              email, telefono, estado, created_at, created_by)
             VALUES (:empresa, :nombre, :ident, :tipo, :email, :tel, 'activo', NOW(), :uid)
             RETURNING id"
        );
        $st2->execute([
            ':empresa' => $idEmpresa,
            ':nombre'  => $solicitud['nombre_cliente'],
            ':ident'   => $solicitud['identificacion'],
            ':tipo'    => $solicitud['tipo_identificacion'],
            ':email'   => $solicitud['correo_cliente'] ?: null,
            ':tel'     => $solicitud['telefono_cliente'] ?: null,
            ':uid'     => $idUsuario,
        ]);
        return (int) $st2->fetchColumn();
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
        $secResult  = $secService->obtenerSiguienteSecuencial((int)$estab['id_punto_emision'], 'factura');
        $secuencial = $secResult['formateado'];

        $detalles    = [];
        $totalSinImp = 0.0;
        $totalIva    = 0.0;

        foreach ($items as $det) {
            $base = round((float)$det['cantidad'] * (float)$det['precio_unitario'], 2);
            $iva  = round($base * ((float)($det['porcentaje_iva'] ?? 0) / 100), 2);
            $totalSinImp += $base;
            $totalIva    += $iva;

            $detalles[] = [
                'id_producto'               => $det['id_producto'] ?: null,
                'descripcion'               => $det['descripcion'],
                'cantidad'                  => $det['cantidad'],
                'precio_unitario'           => $det['precio_unitario'],
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $base,
                'impuestos'                 => $det['porcentaje_iva'] > 0 ? [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => \App\Helpers\SriIvaHelper::codigoPorcentaje($det['porcentaje_iva']),
                    'tarifa'            => $det['porcentaje_iva'],
                    'base_imponible'    => $base,
                    'valor'             => $iva,
                ]] : [],
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
            'info_adicional'      => [
                ['nombre' => 'Origen', 'valor' => 'Factura Express QR'],
                ['nombre' => 'Plantilla', 'valor' => $plantilla['nombre'] ?? ''],
            ],
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento'     => 0,
            'importe_total'       => round($totalSinImp + $totalIva, 2),
            'propina'             => 0,
            'observaciones'       => 'Factura generada por solicitud Express QR.',
        ];
    }

    // ═══════════════════════════════════════════════════════
    // UTILIDADES
    // ═══════════════════════════════════════════════════════

    private function extraerItems(array &$data): array
    {
        $items = $data['items'] ?? [];
        unset($data['items']);
        return array_values(array_filter((array)$items, fn($i) => !empty(trim($i['descripcion'] ?? ''))));
    }
}

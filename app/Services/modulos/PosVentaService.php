<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ReciboVentaRepository;
use App\Rules\modulos\ReciboVentaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use Exception;
use PDO;

/**
 * Venta rápida del Punto de Venta: arma el carrito y lo cobra reutilizando
 * ReciboVentaService::crear() — mismo patrón que
 * OrdenCarWashService::generarDocumento(). Exige una caja_sesiones abierta
 * para el punto de emisión; no genera SRI/XML (eso es de Factura de Venta).
 */
class PosVentaService
{
    private CajaSesionService $cajaService;
    private LogSistemaService $logService;
    private ReciboVentaRepository $reciboRepo;
    private PDO $db;

    public function __construct(CajaSesionService $cajaService, LogSistemaService $logService)
    {
        $this->cajaService = $cajaService;
        $this->logService = $logService;
        $this->reciboRepo = new ReciboVentaRepository();
        $this->db = Database::getConnection();
    }

    public function cobrar(array $data, array $empresaConfig): array
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $idPuntoEmision = (int) $data['id_punto_emision'];

        $sesion = $this->cajaService->getSesionAbierta($idEmpresa, $idPuntoEmision);
        if (!$sesion) {
            throw new Exception('No hay una caja abierta para este punto de emisión. Abre el turno antes de vender.');
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new Exception('El carrito está vacío.');
        }

        $puntoInfo = $this->getPuntoEmisionInfo($idPuntoEmision);
        if (!$puntoInfo) {
            throw new Exception('El punto de emisión no es válido.');
        }

        $idBodega = $this->getPrimeraBodegaActiva($idEmpresa);

        $det = [];
        $totalSinImp = 0.0;
        $totalDesc = 0.0;
        $ivaTotal = 0.0;
        foreach ($items as $it) {
            $idProducto = (int) ($it['id_producto'] ?? 0);
            $cant = (float) ($it['cantidad'] ?? 0);
            if ($idProducto <= 0 || $cant <= 0) {
                continue;
            }
            $precio = (float) ($it['precio_unitario'] ?? 0);
            $dscto = (float) ($it['descuento'] ?? 0);
            $base = round($precio * $cant - $dscto, 2);
            if ($base < 0) {
                $base = 0.0;
            }

            // Resolver IVA desde el producto (misma fuente de verdad que Recibos/Facturas).
            $tar = $this->reciboRepo->getTarifaIvaProducto($idProducto);
            $pct = $tar ? (float) $tar['porcentaje_iva'] : 0.0;
            $codPct = $tar ? (string) $tar['codigo'] : '0';
            $idTar = $tar ? (int) $tar['id'] : 0;
            $ivaLinea = round($base * $pct / 100, 2);

            $totalSinImp += $base;
            $totalDesc += $dscto;
            $ivaTotal += $ivaLinea;

            $descripcion = (string) ($it['descripcion'] ?? '');
            $det[] = [
                'id_producto' => $idProducto,
                'id_bodega' => $idBodega,
                'descripcion' => $descripcion,
                'nombre' => $descripcion,
                'cantidad' => $cant,
                'precio_unitario' => $precio,
                'descuento' => $dscto,
                'precio_total_sin_impuesto' => $base,
                'id_tarifa_iva' => $idTar,
                'es_libre' => 0,
                'porcentaje_iva' => $pct,
                'impuestos' => [[
                    'codigo_impuesto' => '2',
                    'codigo_porcentaje' => $codPct,
                    'tarifa' => $pct,
                    'base_imponible' => $base,
                    'valor' => $ivaLinea,
                ]],
            ];
        }

        if (empty($det)) {
            throw new Exception('No hay líneas válidas en el carrito.');
        }

        $totalSinImp = round($totalSinImp, 2);
        $totalDesc = round($totalDesc, 2);
        $ivaTotal = round($ivaTotal, 2);
        $importeTotal = round($totalSinImp + $ivaTotal, 2);

        $idCliente = $this->getClienteConsumidorFinal($idEmpresa);

        $sec = (new SecuencialService())->obtenerSiguienteSecuencial($idPuntoEmision, 'Recibos de venta');
        $secuencial = $sec['formateado'];
        $numeroDoc = $puntoInfo['cod_establecimiento'] . '-' . $puntoInfo['codigo_punto'] . '-' . $secuencial;

        $formaPago = (string) ($data['forma_pago'] ?? '01');

        $payload = [
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'empresa_config' => $empresaConfig,
            'id_establecimiento' => (int) $puntoInfo['id_establecimiento'],
            'id_punto_emision' => $idPuntoEmision,
            'establecimiento' => $puntoInfo['cod_establecimiento'],
            'punto_emision' => $puntoInfo['codigo_punto'],
            'secuencial' => $secuencial,
            'fecha_emision' => date('Y-m-d'),
            'id_cliente' => $idCliente,
            'id_vendedor' => null,
            'dias_credito' => 0,
            'moneda' => 'DOLAR',
            'observaciones' => 'Venta POS — turno #' . $sesion['id'],
            'id_bodega' => $idBodega,
            'total_sin_impuestos' => $totalSinImp,
            'total_descuento' => $totalDesc,
            'total_ice' => 0,
            'propina' => 0,
            'importe_total' => $importeTotal,
            'detalles' => $det,
            'pagos' => [[
                'forma_pago' => $formaPago,
                'total' => $importeTotal,
                'plazo' => 0,
                'unidad_tiempo' => 'dias',
            ]],
            'info_adicional' => [],
            'con_impuestos' => true,
            'estado' => 'borrador',
            'plazo' => 0,
        ];

        $svc = new ReciboVentaService(
            new ReciboVentaRepository(),
            new ReciboVentaRules(),
            $this->logService
        );
        $idRecibo = $svc->crear($payload);

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'VENTA_POS',
            'caja_sesiones',
            (int) $sesion['id'],
            null,
            ['id_recibo' => $idRecibo, 'numero_documento' => $numeroDoc, 'importe_total' => $importeTotal]
        );

        return [
            'id_recibo' => $idRecibo,
            'numero_documento' => $numeroDoc,
            'importe_total' => $importeTotal,
        ];
    }

    private function getPuntoEmisionInfo(int $idPuntoEmision): ?array
    {
        $sql = "SELECT p.id, p.codigo_punto, p.id_establecimiento, e.codigo AS cod_establecimiento
                FROM empresa_punto_emision p
                JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                WHERE p.id = :id AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idPuntoEmision]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getPrimeraBodegaActiva(int $idEmpresa): ?int
    {
        $empresaModel = new \App\models\Empresa();
        $bodegas = $empresaModel->getBodegas($idEmpresa);
        return !empty($bodegas) ? (int) $bodegas[0]['id'] : null;
    }

    private function getClienteConsumidorFinal(int $idEmpresa): int
    {
        $sql = "SELECT id FROM clientes
                WHERE id_empresa = :id_empresa AND tipo_id = '07' AND identificacion = '9999999999999' AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        $id = $st->fetchColumn();
        if (!$id) {
            throw new Exception('No se encontró el cliente Consumidor Final en el catálogo de clientes de esta empresa.');
        }
        return (int) $id;
    }
}

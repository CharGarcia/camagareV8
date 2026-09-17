<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\ReciboVentaRepository;
use App\Services\PlantillasPdfRendererService;

/**
 * PDF de una factura de venta o de un recibo de venta, el mismo que descargan los módulos
 * Facturas y Recibos de venta (plantilla activa de la empresa o formato por defecto, con los
 * datos del establecimiento), para los módulos que lo ofrecen sobre esos documentos —hoy el
 * botón PDF de cada fila de Cuentas por Cobrar—.
 *
 * No valida permisos: el llamador ya comprobó, con el permiso de SU módulo y el alcance del
 * usuario (§6), que puede ver el documento. Aquí solo se exige que sea de la empresa.
 */
class DocumentoVentaPdfService
{
    /**
     * Envía al navegador (descarga) el PDF de una factura ('FACTURA') o de un recibo
     * ('RECIBO'). Devuelve false, sin enviar nada, si el documento no existe, está
     * eliminado o no es de la empresa.
     */
    public function descargar(string $origen, int $idDocumento, int $idEmpresa): bool
    {
        $esRecibo = $origen === 'RECIBO';
        $repo     = $esRecibo ? new ReciboVentaRepository() : new FacturaVentaRepository();

        $cabecera = $repo->getPorId($idDocumento);
        if (!$cabecera || (int) ($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            return false;
        }

        $detalles = $repo->getDetalles($idDocumento);
        // Impuestos EN LOTE: una sola consulta para todas las líneas.
        $impuestos = $repo->getImpuestosPorDetalles(array_column($detalles, 'id'));
        foreach ($detalles as &$d) {
            $d['impuestos'] = $impuestos[(int) $d['id']] ?? [];
        }
        unset($d);

        $pagos         = $repo->getPagos($idDocumento);
        $infoAdicional = $repo->getInfoAdicional($idDocumento);
        $empresa       = $this->empresaParaPdf($idEmpresa, !$esRecibo);

        $renderer  = new PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, $esRecibo ? 'recibo_venta' : 'factura_venta');
        if ($plantilla) {
            $renderer->generar($plantilla, $cabecera, $detalles, $pagos, $infoAdicional, $empresa, 'D');
        } elseif ($esRecibo) {
            (new ReciboVentaPdfService())->generar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        } else {
            (new FacturaVentaPdfService())->generar($cabecera, $detalles, $pagos, $infoAdicional, $empresa);
        }
        return true;
    }

    /**
     * Datos de la empresa que imprime el PDF: los de la empresa más los del establecimiento
     * principal (logo, dirección, decimales, presentación de ítems…). Mismo armado que
     * FacturaVentaController::exportarPdfAjax() y ReciboVentaController::exportarPdfAjax();
     * las leyendas del PDF solo las lleva la factura, igual que en esos módulos.
     */
    private function empresaParaPdf(int $idEmpresa, bool $conLeyendas): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $est = $empresaModel->getEstablecimientos($idEmpresa)[0] ?? null;
        if (!$est) {
            return $empresa;
        }

        $propios = ['logo_ruta' => $est['logo_ruta'] ?? ''];
        if ($conLeyendas) {
            $propios['leyenda_pdf_titulo']  = $est['leyenda_pdf_titulo'] ?? '';
            $propios['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'] ?? '';
        }
        $propios = array_filter($propios, static fn ($v): bool => !empty($v));

        $empresa = array_merge($empresa, $propios);
        if (!empty($est['direccion'])) {
            $empresa['direccion_establecimiento'] = $est['direccion'];
        }

        try {
            $estConfig = (new EmpresaRepository())->getEstablecimientoConfig((int) $est['id']);
            if ($estConfig) {
                $estConfig['direccion_matriz']          = $empresa['direccion'] ?? '';
                $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                $empresa = array_merge($empresa, $estConfig, $propios);
            }
        } catch (\Throwable $e) {
            // El PDF se genera igual sin la configuración extendida del establecimiento.
        }
        return $empresa;
    }
}

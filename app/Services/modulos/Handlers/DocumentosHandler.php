<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

/**
 * Acciones de documentos electrónicos.
 *
 * Estado de implementación:
 *   - Facturas de venta: enviar_sri ✅ , enviar_correo ✅
 *   - Otros documentos (retención, NC, liquidación, guía): pendiente de implementar
 *     (sus métodos existen en SriEnvioService pero el envío en lote no está probado).
 */
class DocumentosHandler extends BaseHandler
{
    private const USUARIO_SISTEMA = 0; // proceso automático (auditoría)

    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, array $parametros): array
    {
        return match ($this->accion) {
            'enviar_sri'    => $this->enviarSri($idEmpresa, $idEstablecimiento, $parametros),
            'enviar_correo' => $this->enviarCorreo($idEmpresa, $idEstablecimiento, $parametros),
            default         => throw new \RuntimeException("Acción '{$this->accion}' no implementada en DocumentosHandler."),
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    //  ENVIAR AL SRI
    // ════════════════════════════════════════════════════════════════════════
    private function enviarSri(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        if ($this->modulo !== 'facturas_venta') {
            return ['registros' => 0, 'mensaje' => 'El envío automático al SRI por ahora solo está disponible para Facturas de venta.'];
        }

        $lote        = max(10, (int)($p['lote_interno'] ?? 50));
        $estabFilter = $idEstablecimiento !== null ? "AND id_establecimiento = {$idEstablecimiento}" : '';

        // Facturas pendientes de autorizar = estado 'borrador'.
        // Al autorizar, SriEnvioService cambia estado a 'autorizado' (sale del filtro).
        // Las que fallan quedan en 'borrador'; el keyset por id evita reprocesarlas en esta corrida.
        $stmt = $this->db->prepare("
            SELECT id
            FROM ventas_cabecera
            WHERE eliminado = false
              AND id_empresa = :id_empresa
              AND estado = 'borrador'
              AND id > :ultimo_id
              {$estabFilter}
            ORDER BY id ASC
            LIMIT :lote
        ");

        $svc = new \App\Services\Sri\SriEnvioService(esperaInicial: 3, maxIntentos: 4, intervaloReintentos: 3);

        $autorizadas = 0;
        $errores     = 0;
        $ultimoId    = 0;

        do {
            $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
            $stmt->bindValue(':ultimo_id',  $ultimoId,  \PDO::PARAM_INT);
            $stmt->bindValue(':lote',       $lote,      \PDO::PARAM_INT);
            $stmt->execute();
            $docs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($docs as $doc) {
                $id       = (int) $doc['id'];
                $ultimoId = $id;
                try {
                    $r      = $svc->enviarFacturaVenta($id, $idEmpresa, self::USUARIO_SISTEMA);
                    $estado = $r['estado'] ?? 'error';

                    if (($r['ok'] ?? false) || $estado === 'autorizado') {
                        $autorizadas++;
                    } else {
                        $errores++;
                    }
                } catch (\Throwable $e) {
                    $errores++;
                }
            }
        } while (count($docs) === $lote);

        $total = $autorizadas + $errores;
        $msg   = "Se procesaron {$total} factura(s): {$autorizadas} autorizada(s), {$errores} no autorizada(s)/con error.";
        return ['registros' => $autorizadas, 'mensaje' => $msg];
    }

    // ════════════════════════════════════════════════════════════════════════
    //  ENVIAR CORREO
    // ════════════════════════════════════════════════════════════════════════
    private function enviarCorreo(int $idEmpresa, ?int $idEstablecimiento, array $p): array
    {
        if ($this->modulo !== 'facturas_venta') {
            return ['registros' => 0, 'mensaje' => 'El envío de correo automático por ahora solo está disponible para Facturas de venta.'];
        }

        $reintentar   = !empty($p['reintentar_fallidos']);
        $estadoFilter = $reintentar
            ? "AND estado_correo IN ('pendiente', 'error')"
            : "AND estado_correo = 'pendiente'";
        $estabFilter  = $idEstablecimiento !== null ? "AND id_establecimiento = {$idEstablecimiento}" : '';
        $lote         = 50;

        // Solo facturas AUTORIZADAS con correo pendiente (no reenvía las ya enviadas)
        $stmt = $this->db->prepare("
            SELECT id
            FROM ventas_cabecera
            WHERE id_empresa = :id_empresa
              AND eliminado = false
              AND estado = 'autorizado'
              AND id > :ultimo_id
              {$estadoFilter}
              {$estabFilter}
            ORDER BY id ASC
            LIMIT :lote
        ");

        $repo = new \App\repositories\modulos\FacturaVentaRepository();

        $enviados = 0;
        $errores  = 0;
        $ultimoId = 0;

        do {
            $stmt->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
            $stmt->bindValue(':ultimo_id',  $ultimoId,  \PDO::PARAM_INT);
            $stmt->bindValue(':lote',       $lote,      \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $id       = (int) $row['id'];
                $ultimoId = $id;
                try {
                    $resultado = $this->enviarCorreoFactura($idEmpresa, $id, $repo);
                    $nuevoEstado = $resultado === true ? 'enviado' : ($resultado === null ? 'no_aplica' : 'error');
                    $this->db->prepare("UPDATE ventas_cabecera SET estado_correo = ?, updated_at = NOW() WHERE id = ?")
                             ->execute([$nuevoEstado, $id]);
                    if ($resultado === true) $enviados++;
                    elseif ($resultado === false) $errores++;
                } catch (\Throwable $e) {
                    $errores++;
                    try {
                        $this->db->prepare("UPDATE ventas_cabecera SET estado_correo = 'error', updated_at = NOW() WHERE id = ?")->execute([$id]);
                    } catch (\Throwable) {}
                }
            }
        } while (count($rows) === $lote);

        $msg = "Se enviaron {$enviados} correo(s) de facturas.";
        if ($errores > 0) $msg .= " ({$errores} con error)";
        return ['registros' => $enviados, 'mensaje' => $msg];
    }

    /**
     * Genera PDF + XML de una factura y la envía por correo (replica reenviarCorreoAjax).
     * @return bool|null  true=enviado, false=error de envío, null=sin destinatario válido
     */
    private function enviarCorreoFactura(int $idEmpresa, int $id, \App\repositories\modulos\FacturaVentaRepository $repo): ?bool
    {
        $factura = $repo->getPorId($id);
        if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            return false;
        }
        if (empty($factura['cliente_email']) && empty($factura['email'])) {
            return null; // sin correo: no_aplica
        }

        $detalles = $repo->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $repo->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $pagos         = $repo->getPagos($id);
        $infoAdicional = $repo->getInfoAdicional($id);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            $est = $establecimientos[0];
            if (!empty($est['logo_ruta']))           $empresa['logo_ruta'] = $est['logo_ruta'];
            if (!empty($est['direccion']))           $empresa['direccion_establecimiento'] = $est['direccion'];
            if (!empty($est['leyenda_pdf_titulo']))  $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
            if (!empty($est['leyenda_pdf_mensaje'])) $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int)$est['id']);
                if ($estConfig) {
                    $estConfig['direccion_matriz']          = $empresa['direccion'] ?? '';
                    $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                    if (!empty($est['logo_ruta']))           $estConfig['logo_ruta'] = $est['logo_ruta'];
                    if (!empty($est['leyenda_pdf_titulo']))  $estConfig['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
                    if (!empty($est['leyenda_pdf_mensaje'])) $estConfig['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
                    $empresa = array_merge($empresa, $estConfig);
                }
            } catch (\Throwable) {}
        }

        // PDF (plantilla activa o servicio por defecto)
        $renderer     = new \App\Services\PlantillasPdfRendererService();
        $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');
        if ($plantillaPdf) {
            $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
        } else {
            $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
            $pdfString  = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
        }

        // XML autorizado (o regenerar como fallback)
        $xmlString = $factura['detalle_xml'] ?? '';
        $numAut    = $factura['numero_autorizacion'] ?? '';
        if (empty($xmlString)) {
            $dirEst = null;
            if (!empty($factura['id_establecimiento'])) {
                foreach ($establecimientos as $e) {
                    if ((int)$e['id'] === (int)$factura['id_establecimiento']) { $dirEst = $e['direccion'] ?? null; break; }
                }
            }
            $xmlService = new \App\Services\Xml\XmlFacturaVentaService();
            $xmlString  = $xmlService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, $dirEst);
            try { $repo->updateDetalleXml($id, $xmlString); } catch (\Throwable) {}
        }

        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        // forzarEnvio=true: el cron siempre envía si la factura está autorizada
        return $emailSvc->enviarSiAplica($idEmpresa, 'factura_venta', $factura, $xmlString, $pdfString, $numAut, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Sri;

use App\core\Database;
use App\models\SriEnvioLog;
use App\Services\Xml\XmlFacturaVentaService;
use App\repositories\modulos\FacturaVentaRepository;

/**
 * Orquestador del proceso de envío al SRI:
 *   1. Genera el XML del comprobante
 *   2. Lo firma con XAdES-BES usando el .p12 de la empresa
 *   3. Lo envía al WS de recepción del SRI
 *   4. Consulta el estado de autorización (con reintentos)
 *   5. Actualiza el estado del comprobante en la base de datos
 *
 * Diseñado para ser reutilizable con cualquier tipo de comprobante
 * (facturas, notas de crédito, retenciones, etc.).
 */
class SriEnvioService
{
    private FirmadorXmlService  $firmador;
    private SriWebserviceService $ws;

    /** Segundos a esperar entre el envío y la primera consulta de autorización */
    private int $esperaInicial;
    /** Número máximo de intentos de consulta de autorización */
    private int $maxIntentos;
    /** Segundos entre reintentos de consulta */
    private int $intervaloReintentos;

    public function __construct(
        int $esperaInicial       = 3,
        int $maxIntentos         = 5,
        int $intervaloReintentos = 3
    ) {
        $this->firmador            = new FirmadorXmlService();
        $this->ws                  = new SriWebserviceService(30);
        $this->esperaInicial       = $esperaInicial;
        $this->maxIntentos         = $maxIntentos;
        $this->intervaloReintentos = $intervaloReintentos;
    }

    // ── API pública ────────────────────────────────────────────────────────────

    /**
     * Procesa el envío completo de una factura de venta al SRI.
     *
     * @param  int $idVenta    ID en ventas_cabecera
     * @param  int $idEmpresa  ID de la empresa
     * @param  int $idUsuario  ID del usuario que dispara el envío
     * @return array Resultado con estado, mensajes y datos de autorización
     */
    public function enviarFacturaVenta(int $idVenta, int $idEmpresa, int $idUsuario): array
    {
        $repo = new FacturaVentaRepository();

        $cabecera = $repo->getPorId($idVenta);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException("Factura #{$idVenta} no encontrada.");
        }
        if (empty($cabecera['clave_acceso'])) {
            throw new \RuntimeException("La factura no tiene clave de acceso generada.");
        }

        $tipoAmbiente = $cabecera['tipo_ambiente'] ?? '1';
        $claveAcceso  = $cabecera['clave_acceso'];

        // Verificar primero si ya está autorizado en el SRI (antes de validar fecha u otro requisito).
        // Cubre el caso donde el WS devolvió error de red pero el SRI sí procesó el comprobante.
        $preCheck = $this->preVerificarAutorizacion(
            'ventas_cabecera', $idVenta, $claveAcceso, $tipoAmbiente,
            'factura_venta', $idEmpresa, $idUsuario, 'autorizado',
            function (string $numAut, ?string $fechaAut, string $xmlDetalle) use ($repo, $idVenta, $idUsuario): void {
                $db = Database::getConnection();
                $db->prepare("UPDATE ventas_cabecera SET estado = 'autorizado', updated_by = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$idUsuario, $idVenta]);
                try { $repo->updateDetalleXml($idVenta, $xmlDetalle); } catch (\Throwable) {}
            }
        );
        if ($preCheck !== null) {
            return $preCheck;
        }

        // El SRI exige que la fecha de emisión sea la fecha actual del día del envío.
        $fechaEmision = (new \DateTime($cabecera['fecha_emision']))->format('Y-m-d');
        $hoy          = (new \DateTime())->format('Y-m-d');
        if ($fechaEmision !== $hoy) {
            $fechaFmt = (new \DateTime($cabecera['fecha_emision']))->format('d-m-Y');
            throw new \RuntimeException(
                "No se puede enviar al SRI: la fecha de emisión del comprobante ({$fechaFmt}) " .
                "debe ser la fecha actual ({$hoy}). " .
                "Edite el comprobante y actualice la fecha de emisión a hoy antes de enviar."
            );
        }

        $detalles = $repo->getDetalles($idVenta);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $repo->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $pagos         = $repo->getPagos($idVenta);
        $infoAdicional = $repo->getInfoAdicional($idVenta);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        // Dirección y logo del establecimiento
        $dirEstablecimiento = null;
        try {
            $estRepo = new \App\repositories\modulos\EmpresaRepository();
            $establecimientos = $estRepo->getEstablecimientos($idEmpresa);
            foreach ($establecimientos as $est) {
                $esElEstablecimiento = !empty($cabecera['id_establecimiento'])
                    ? (int)$est['id'] === (int)$cabecera['id_establecimiento']
                    : true; // si no hay id_establecimiento, usar el primero
                if ($esElEstablecimiento) {
                    $dirEstablecimiento = $est['direccion'] ?? null;
                    // Enriquecer $empresa con datos del establecimiento para el PDF
                    if (!empty($est['logo_ruta'])) {
                        $empresa['logo_ruta'] = $est['logo_ruta'];
                    }
                    if (!empty($est['direccion'])) {
                        $empresa['direccion_establecimiento'] = $est['direccion'];
                    }
                    if (!empty($est['leyenda_pdf_titulo'])) {
                        $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
                    }
                    if (!empty($est['leyenda_pdf_mensaje'])) {
                        $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
                    }
                    // Merge config del establecimiento (decimales, facturación, etc.)
                    $estConfig = $estRepo->getEstablecimientoConfig((int)$est['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                        if (!empty($est['logo_ruta'])) {
                            $estConfig['logo_ruta'] = $est['logo_ruta'];
                        }
                        $empresa = array_merge($empresa, $estConfig);
                    }
                    break;
                }
            }
        } catch (\Throwable) {}

        // 1. Generar XML
        $xmlService = new XmlFacturaVentaService();
        $xmlLimpio  = $xmlService->generar($cabecera, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

        // 2. Obtener configuración de firma de la empresa
        $firmaConfig = $this->getFirmaConfig($idEmpresa);
        if (!$firmaConfig) {
            throw new \RuntimeException(
                "La empresa no tiene un certificado de firma electrónica configurado. " .
                "Configure el certificado .p12 en Configuración → Firma Electrónica."
            );
        }

        // 3. Firmar XML
        $xmlFirmado = $this->firmador->firmar(
            $xmlLimpio,
            $firmaConfig['archivo_path'],
            $firmaConfig['p12_password']
        );

        // 4. Registrar inicio de envío
        $logBase = [
            'id_empresa'      => $idEmpresa,
            'tipo_comprobante'=> 'factura_venta',
            'id_comprobante'  => $idVenta,
            'clave_acceso'    => $claveAcceso,
            'tipo_ambiente'   => $tipoAmbiente,
            'created_by'      => $idUsuario,
        ];

        $this->actualizarEstadoSri($idVenta, 'enviando', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'enviando', 'mensaje' => 'Comprobante enviado al WS de recepción del SRI.']);

        // 5. Enviar al WS de recepción
        $recepcion = $this->ws->enviarRecepcion($xmlFirmado, $tipoAmbiente);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $erroresJson = json_encode($recepcion['errores'], JSON_UNESCAPED_UNICODE);
            $this->actualizarEstadoSri($idVenta, 'devuelta', null, null, $erroresJson, $idUsuario);
            $this->log($logBase + [
                'accion'       => 'devuelta',
                'estado_sri'   => 'DEVUELTA',
                'mensaje'      => 'El SRI devolvió el comprobante con errores.',
                'detalle_json' => $erroresJson,
            ]);
            return [
                'ok'      => false,
                'estado'  => 'devuelta',
                'mensaje' => 'El SRI devolvió el comprobante con errores.',
                'errores' => $recepcion['errores'],
            ];
        }

        $this->actualizarEstadoSri($idVenta, 'recibida', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'recibida', 'estado_sri' => 'RECIBIDA', 'mensaje' => 'Comprobante recibido por el SRI. Consultando autorización…']);

        // 6. Consultar autorización (con reintentos)
        $autResult   = $this->consultarConReintentos($claveAcceso, $tipoAmbiente);

        $erroresJson   = !empty($autResult['errores']) ? json_encode($autResult['errores'], JSON_UNESCAPED_UNICODE) : null;
        $fechaAut      = $autResult['fecha_autorizacion']  ?: null;
        $xmlAutorizado = $autResult['xml_autorizado']      ?: null;
        $numAut        = $autResult['numero_autorizacion'] ?? $claveAcceso;

        // Mapear estado SRI a estado interno
        $estadoInterno = match (strtoupper($autResult['estado'] ?? '')) {
            'AUTORIZADO'         => 'autorizado',
            'NO AUTORIZADO',
            'RECHAZADO'          => 'no_autorizado',
            'EN PROCESAMIENTO'   => 'en_procesamiento',
            default              => 'error',
        };

        $this->actualizarEstadoSri($idVenta, $estadoInterno, $fechaAut, $xmlAutorizado, $erroresJson, $idUsuario);
        $this->log($logBase + [
            'accion'              => $estadoInterno,
            'estado_sri'          => strtoupper($autResult['estado'] ?? ''),
            'mensaje'             => $estadoInterno === 'autorizado'
                                        ? 'Comprobante autorizado por el SRI.'
                                        : 'El SRI no autorizó el comprobante.',
            'detalle_json'        => $erroresJson,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        $estadoCorreo = null;
        if ($estadoInterno === 'autorizado') {
            $db = Database::getConnection();
            $db->prepare("UPDATE ventas_cabecera SET estado = 'autorizado', updated_by = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$idUsuario, $idVenta]);

            $comprobante = !empty($xmlAutorizado) ? $xmlAutorizado : $xmlFirmado;
            $xmlDetalleCompleto = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $comprobante);

            try {
                $repo->updateDetalleXml($idVenta, $xmlDetalleCompleto);
            } catch (\Throwable $eXml) {
                error_log('[SRI] Error guardando detalle_xml en factura #' . $idVenta . ': ' . $eXml->getMessage());
            }

            // --- ENVÍO AUTOMÁTICO DE CORREO ---
            try {
                $renderer = new \App\Services\PlantillasPdfRendererService();
                $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

                if ($plantillaPdf) {
                    $pdfString = $renderer->generar($plantillaPdf, $cabecera, $detalles, $pagos, $infoAdicional, $empresa, 'S');
                } else {
                    $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                    $pdfString = $pdfService->generar($cabecera, $detalles, $pagos, $infoAdicional, $empresa, 'S');
                }

                $emailSvc = new \App\Services\EnvioDocumentosSRIService();
                $enviado = $emailSvc->enviarSiAplica($idEmpresa, 'factura_venta', $cabecera, $xmlDetalleCompleto, $pdfString, $numAut);
                if ($enviado) {
                    $db->prepare("UPDATE ventas_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ?")
                       ->execute([$idVenta]);
                    $estadoCorreo = 'enviado';
                }
            } catch (\Throwable $eEmail) {
                error_log('[SRI] Error al procesar envío automático de correo: ' . $eEmail->getMessage());
            }
        }

        return [
            'ok'                  => $estadoInterno === 'autorizado',
            'estado'              => $estadoInterno,
            'estado_correo'       => $estadoCorreo,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => $estadoInterno === 'autorizado'
                ? 'Comprobante autorizado por el SRI.'
                : 'El SRI no autorizó el comprobante.',
            'errores'             => $autResult['errores'] ?? [],
        ];
    }

    public function enviarNotaCredito(int $idNC, int $idEmpresa, int $idUsuario): array
    {
        $repo = new \App\repositories\modulos\NotaCreditoRepository();

        $cabecera = $repo->getPorId($idNC);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException("Nota de crédito #{$idNC} no encontrada.");
        }
        if (empty($cabecera['clave_acceso'])) {
            throw new \RuntimeException("La nota de crédito no tiene clave de acceso generada.");
        }

        $tipoAmbiente = $cabecera['tipo_ambiente'] ?? '1';
        $claveAcceso  = $cabecera['clave_acceso'];

        $preCheck = $this->preVerificarAutorizacion(
            'notas_credito_cabecera', $idNC, $claveAcceso, $tipoAmbiente,
            'nota_credito', $idEmpresa, $idUsuario, 'autorizado',
            function (string $numAut, ?string $fechaAut, string $xmlDetalle) use ($repo, $idNC, $idUsuario): void {
                $db = Database::getConnection();
                $db->prepare("UPDATE notas_credito_cabecera SET estado = 'autorizado', updated_by = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$idUsuario, $idNC]);
                try { $repo->updateDetalleXml($idNC, $xmlDetalle); } catch (\Throwable) {}
            }
        );
        if ($preCheck !== null) {
            return $preCheck;
        }

        $detalles = $repo->getDetalles($idNC);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $repo->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        // 1. Generar XML
        $xmlService = new \App\Services\Xml\XmlNotaCreditoService();
        $xmlLimpio  = $xmlService->generar($cabecera, $detalles, $empresa);

        // 2. Obtener config firma
        $firmaConfig = $this->getFirmaConfig($idEmpresa);
        if (!$firmaConfig) {
            throw new \RuntimeException("La empresa no tiene firma electrónica configurada.");
        }

        // 3. Firmar XML
        $xmlFirmado = $this->firmador->firmar($xmlLimpio, $firmaConfig['archivo_path'], $firmaConfig['p12_password']);

        // 4. Enviar
        $logBase = [
            'id_empresa'      => $idEmpresa,
            'tipo_comprobante'=> 'nota_credito',
            'id_comprobante'  => $idNC,
            'clave_acceso'    => $claveAcceso,
            'tipo_ambiente'   => $tipoAmbiente,
            'created_by'      => $idUsuario,
        ];

        $this->actualizarEstadoDocumento('notas_credito_cabecera', $idNC, 'enviando', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'enviando', 'mensaje' => 'Comprobante enviado al WS de recepción del SRI.']);

        $recepcion = $this->ws->enviarRecepcion($xmlFirmado, $tipoAmbiente);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $erroresJson = json_encode($recepcion['errores'], JSON_UNESCAPED_UNICODE);
            $this->actualizarEstadoDocumento('notas_credito_cabecera', $idNC, 'devuelta', null, null, $erroresJson, $idUsuario);
            $this->log($logBase + [
                'accion' => 'devuelta', 'estado_sri' => 'DEVUELTA', 'mensaje' => 'El SRI devolvió el comprobante con errores.', 'detalle_json' => $erroresJson
            ]);
            return ['ok' => false, 'estado' => 'devuelta', 'mensaje' => 'El SRI devolvió el comprobante con errores.', 'errores' => $recepcion['errores']];
        }

        $this->actualizarEstadoDocumento('notas_credito_cabecera', $idNC, 'recibida', null, null, null, $idUsuario);
        
        // 5. Autorización
        $autResult = $this->consultarConReintentos($claveAcceso, $tipoAmbiente);
        $estadoInterno = match (strtoupper($autResult['estado'] ?? '')) {
            'AUTORIZADO' => 'autorizado',
            'NO AUTORIZADO', 'RECHAZADO' => 'no_autorizado',
            'EN PROCESAMIENTO' => 'en_procesamiento',
            default => 'error',
        };

        $erroresJson   = !empty($autResult['errores']) ? json_encode($autResult['errores'], JSON_UNESCAPED_UNICODE) : null;
        $fechaAut      = $autResult['fecha_autorizacion']  ?: null;
        $xmlAutorizado = $autResult['xml_autorizado']      ?: null;
        $numAut        = $autResult['numero_autorizacion'] ?? $claveAcceso;

        $this->actualizarEstadoDocumento('notas_credito_cabecera', $idNC, $estadoInterno, $fechaAut, $xmlAutorizado, $erroresJson, $idUsuario);

        $this->log($logBase + [
            'accion'              => $estadoInterno,
            'estado_sri'          => strtoupper($autResult['estado'] ?? ''),
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Autorizado.' : 'No autorizado.',
            'detalle_json'        => $erroresJson,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        if ($estadoInterno === 'autorizado') {
            $db = Database::getConnection();
            $db->prepare("UPDATE notas_credito_cabecera SET estado = 'autorizado', updated_by = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$idUsuario, $idNC]);

            $comprobante = !empty($xmlAutorizado) ? $xmlAutorizado : $xmlFirmado;
            $xmlDetalleCompleto = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $comprobante);

            try {
                $repo->updateDetalleXml($idNC, $xmlDetalleCompleto);
            } catch (\Throwable $eXml) {
                error_log('[SRI] Error guardando detalle_xml en NC #' . $idNC . ': ' . $eXml->getMessage());
            }

            // --- ENVÍO AUTOMÁTICO DE CORREO ---
            try {
                $pdfService = new \App\Services\modulos\NotaCreditoPdfService();
                $pdfString  = $pdfService->generarBytes($cabecera, $detalles, $empresa);

                $emailSvc = new \App\Services\EnvioDocumentosSRIService();
                $enviado = $emailSvc->enviarSiAplica($idEmpresa, 'nota_credito', $cabecera, $xmlDetalleCompleto, $pdfString, $numAut);
                if ($enviado) {
                    $db->prepare("UPDATE notas_credito_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ?")
                       ->execute([$idNC]);
                }
            } catch (\Throwable $eEmail) {
                error_log('[SRI] Error al procesar envío automático de correo (NC #' . $idNC . '): ' . $eEmail->getMessage());
            }
        }

        return [
            'ok'                  => $estadoInterno === 'autorizado',
            'estado'              => $estadoInterno,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Comprobante autorizado por el SRI.' : 'El SRI no autorizó el comprobante.',
            'errores'             => $autResult['errores'] ?? [],
        ];
    }

    // ── Retención en Compras ──────────────────────────────────────────────────

    /**
     * Procesa el envío completo de una retención en compras al SRI.
     *
     * @param  int $idRetencion  ID en retencion_compra_cabecera
     * @param  int $idEmpresa    ID de la empresa
     * @param  int $idUsuario    ID del usuario que dispara el envío
     * @return array Resultado con estado, mensajes y datos de autorización
     */
    public function enviarRetencionCompra(int $idRetencion, int $idEmpresa, int $idUsuario): array
    {
        $repo = new \App\repositories\modulos\RetencionCompraRepository();

        $cabecera = $repo->getPorIdSri($idRetencion, $idEmpresa);
        if (!$cabecera) {
            throw new \RuntimeException("Retención #{$idRetencion} no encontrada.");
        }
        if (empty($cabecera['clave_acceso'])) {
            throw new \RuntimeException("La retención no tiene clave de acceso generada.");
        }

        $tipoAmbiente = $cabecera['tipo_ambiente'] ?? '1';
        $claveAcceso  = $cabecera['clave_acceso'];

        $preCheck = $this->preVerificarAutorizacion(
            'retencion_compra_cabecera', $idRetencion, $claveAcceso, $tipoAmbiente,
            'retencion_compra', $idEmpresa, $idUsuario, 'autorizada',
            function (string $numAut, ?string $fechaAut, string $xmlDetalle) use ($repo, $idRetencion, $idUsuario): void {
                $db = Database::getConnection();
                $db->prepare("UPDATE retencion_compra_cabecera SET estado = 'autorizada', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$numAut, $idUsuario, $idRetencion]);
                try { $repo->updateDetalleXml($idRetencion, $xmlDetalle); } catch (\Throwable) {}
            }
        );
        if ($preCheck !== null) {
            return $preCheck;
        }

        $lineas = $repo->getDetalle($idRetencion);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        // Dirección del establecimiento
        $dirEstablecimiento = null;
        if (!empty($cabecera['id_establecimiento'])) {
            try {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        // 1. Generar XML
        $xmlService = new \App\Services\Xml\XmlRetencionCompraService();
        $xmlLimpio  = $xmlService->generar($cabecera, $lineas, $empresa, $dirEstablecimiento);

        // 2. Obtener firma
        $firmaConfig = $this->getFirmaConfig($idEmpresa);
        if (!$firmaConfig) {
            throw new \RuntimeException(
                "La empresa no tiene certificado de firma electrónica configurado. " .
                "Configure el certificado .p12 en Configuración → Firma Electrónica."
            );
        }

        // 3. Firmar XML
        $xmlFirmado = $this->firmador->firmar($xmlLimpio, $firmaConfig['archivo_path'], $firmaConfig['p12_password']);

        // 4. Registrar inicio
        $logBase = [
            'id_empresa'       => $idEmpresa,
            'tipo_comprobante' => 'retencion_compra',
            'id_comprobante'   => $idRetencion,
            'clave_acceso'     => $claveAcceso,
            'tipo_ambiente'    => $tipoAmbiente,
            'created_by'       => $idUsuario,
        ];

        $this->actualizarEstadoDocumento('retencion_compra_cabecera', $idRetencion, 'enviando', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'enviando', 'mensaje' => 'Retención enviada al WS de recepción del SRI.']);

        // 5. Enviar al WS de recepción
        $recepcion = $this->ws->enviarRecepcion($xmlFirmado, $tipoAmbiente);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $erroresJson = json_encode($recepcion['errores'], JSON_UNESCAPED_UNICODE);
            $this->actualizarEstadoDocumento('retencion_compra_cabecera', $idRetencion, 'devuelta', null, null, $erroresJson, $idUsuario);
            $this->log($logBase + [
                'accion' => 'devuelta', 'estado_sri' => 'DEVUELTA',
                'mensaje' => 'El SRI devolvió la retención con errores.', 'detalle_json' => $erroresJson,
            ]);
            return ['ok' => false, 'estado' => 'devuelta', 'mensaje' => 'El SRI devolvió la retención con errores.', 'errores' => $recepcion['errores']];
        }

        $this->actualizarEstadoDocumento('retencion_compra_cabecera', $idRetencion, 'recibida', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'recibida', 'estado_sri' => 'RECIBIDA', 'mensaje' => 'Retención recibida por el SRI. Consultando autorización…']);

        // 6. Consultar autorización
        $autResult = $this->consultarConReintentos($claveAcceso, $tipoAmbiente);

        $erroresJson   = !empty($autResult['errores']) ? json_encode($autResult['errores'], JSON_UNESCAPED_UNICODE) : null;
        $fechaAut      = $autResult['fecha_autorizacion']  ?: null;
        $xmlAutorizado = $autResult['xml_autorizado']      ?: null;
        $numAut        = $autResult['numero_autorizacion'] ?? $claveAcceso;

        $estadoInterno = match (strtoupper($autResult['estado'] ?? '')) {
            'AUTORIZADO'                      => 'autorizada',
            'NO AUTORIZADO', 'RECHAZADO'      => 'no_autorizada',
            'EN PROCESAMIENTO'                => 'en_procesamiento',
            default                           => 'error',
        };

        $this->actualizarEstadoDocumento('retencion_compra_cabecera', $idRetencion, $estadoInterno, $fechaAut, $xmlAutorizado, $erroresJson, $idUsuario);
        $this->log($logBase + [
            'accion'              => $estadoInterno,
            'estado_sri'          => strtoupper($autResult['estado'] ?? ''),
            'mensaje'             => $estadoInterno === 'autorizada' ? 'Retención autorizada por el SRI.' : 'El SRI no autorizó la retención.',
            'detalle_json'        => $erroresJson,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        if ($estadoInterno === 'autorizada') {
            $db = Database::getConnection();
            $db->prepare(
                "UPDATE retencion_compra_cabecera SET estado = 'autorizada', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$numAut, $idUsuario, $idRetencion]);

            $comprobante = !empty($xmlAutorizado) ? $xmlAutorizado : $xmlFirmado;
            $xmlDetalleCompleto = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $comprobante);

            try {
                $repo->updateDetalleXml($idRetencion, $xmlDetalleCompleto);
            } catch (\Throwable $eXml) {
                error_log('[SRI] Error guardando detalle_xml en retención #' . $idRetencion . ': ' . $eXml->getMessage());
            }

            // --- ENVÍO AUTOMÁTICO DE CORREO ---
            try {
                $pdfService = new \App\Services\modulos\RetencionCompraPdfService();
                $pdfString  = $pdfService->generarBytes($cabecera, $lineas, $empresa);

                $emailSvc = new \App\Services\EnvioDocumentosSRIService();
                $enviado = $emailSvc->enviarSiAplica($idEmpresa, 'retencion_compra', $cabecera, $xmlDetalleCompleto, $pdfString, $numAut);
                if ($enviado) {
                    $db->prepare("UPDATE retencion_compra_cabecera SET estado_correo = 'enviado', updated_at = NOW() WHERE id = ?")
                       ->execute([$idRetencion]);
                }
            } catch (\Throwable $eEmail) {
                error_log('[SRI] Error al procesar envío automático de correo (retención #' . $idRetencion . '): ' . $eEmail->getMessage());
            }
        }

        return [
            'ok'                  => $estadoInterno === 'autorizada',
            'estado'              => $estadoInterno,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => $estadoInterno === 'autorizada'
                ? 'Retención autorizada por el SRI.'
                : 'El SRI no autorizó la retención.',
            'errores' => $autResult['errores'] ?? [],
        ];
    }

    // ── API pública de consulta ────────────────────────────────────────────────

    /**
     * Consulta en el SRI si un comprobante ya está autorizado.
     * Útil para validar antes de enviar, al anular, o desde otros módulos.
     *
     * @param  string $claveAcceso   Clave de acceso de 49 dígitos
     * @param  string $tipoAmbiente '1' pruebas | '2' producción
     * @return array ['estado', 'numero_autorizacion', 'fecha_autorizacion', 'xml_autorizado', 'errores']
     */
    public function verificarAutorizacion(string $claveAcceso, string $tipoAmbiente = '1'): array
    {
        return $this->ws->consultarAutorizacion($claveAcceso, $tipoAmbiente);
    }

    // ── Helpers internos ───────────────────────────────────────────────────────

    /** Inserta una entrada en sri_envio_log. Silencia errores para no interrumpir el flujo. */
    private function log(array $data): void
    {
        try {
            (new SriEnvioLog())->registrar($data);
        } catch (\Throwable $e) {
            error_log('[SRI LOG] ' . $e->getMessage());
        }
    }

    private function consultarConReintentos(string $claveAcceso, string $tipoAmbiente): array
    {
        sleep($this->esperaInicial);

        for ($i = 0; $i < $this->maxIntentos; $i++) {
            $resultado = $this->ws->consultarAutorizacion($claveAcceso, $tipoAmbiente);
            $estado    = strtoupper($resultado['estado'] ?? '');

            // Si ya tiene resolución definitiva, no reintentar
            if ($estado !== 'EN PROCESAMIENTO' && $estado !== 'PPR' && $estado !== '') {
                return $resultado;
            }

            if ($i < $this->maxIntentos - 1) {
                sleep($this->intervaloReintentos);
            }
        }

        return $resultado ?? ['estado' => 'EN_PROCESAMIENTO', 'errores' => []];
    }

    /**
     * Actualiza las columnas de seguimiento SRI en ventas_cabecera.
     * Usa solo columnas que existan en la tabla (compatibilidad con migraciones).
     */
    private function actualizarEstadoDocumento(
        string  $tabla,
        int     $id,
        string  $estadoSri,
        ?string $fechaAutorizacion,
        ?string $xmlAutorizado,
        ?string $mensajesSri,
        int     $idUsuario
    ): void {
        $db = Database::getConnection();
        $sets = ['updated_by = ?', 'updated_at = NOW()'];
        $params = [$idUsuario];

        $colsCache = $this->columnasExistentes($db, $tabla);

        if (in_array('estado_sri', $colsCache)) { $sets[] = 'estado_sri = ?'; $params[] = $estadoSri; }
        if (in_array('fecha_envio_sri', $colsCache) && $estadoSri === 'enviando') { $sets[] = 'fecha_envio_sri = NOW()'; }
        if (in_array('fecha_autorizacion', $colsCache) && $fechaAutorizacion !== null) { $sets[] = 'fecha_autorizacion = ?'; $params[] = $fechaAutorizacion; }
        if (in_array('xml_autorizado', $colsCache) && $xmlAutorizado !== null) { $sets[] = 'xml_autorizado = ?'; $params[] = $xmlAutorizado; }
        if (in_array('mensajes_sri', $colsCache) && $mensajesSri !== null) { $sets[] = 'mensajes_sri = ?'; $params[] = $mensajesSri; }

        $params[] = $id;
        $sql = "UPDATE $tabla SET " . implode(', ', $sets) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
    }

    /**
     * Construye el XML de autorización completo para guardar en detalle_xml.
     */
    private function buildXmlDetalleCompleto(
        string $numAut,
        string $fechaAut,
        string $tipoAmbiente,
        string $comprobante
    ): string {
        $ambiente = $tipoAmbiente === '2' ? 'PRODUCCION' : 'PRUEBAS';
        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<autorizaciones>',
            '  <autorizacion>',
            '    <estado>AUTORIZADO</estado>',
            '    <numeroAutorizacion>' . htmlspecialchars($numAut,   ENT_XML1, 'UTF-8') . '</numeroAutorizacion>',
            '    <fechaAutorizacion>'  . htmlspecialchars($fechaAut, ENT_XML1, 'UTF-8') . '</fechaAutorizacion>',
            '    <ambiente>' . $ambiente . '</ambiente>',
            '    <comprobante><![CDATA[' . $comprobante . ']]></comprobante>',
            '    <mensajes/>',
            '  </autorizacion>',
            '</autorizaciones>',
        ]);
    }

    /**
     * Verifica si el documento ya está autorizado en el SRI antes de enviar.
     * Si está autorizado, actualiza la BD y retorna el array de resultado.
     * Si no está autorizado, retorna null (continuar con el envío normal).
     *
     * @param string   $tabla          Tabla principal del documento
     * @param int      $id             ID del documento
     * @param string   $claveAcceso    Clave de acceso de 49 dígitos
     * @param string   $tipoAmbiente   '1' pruebas | '2' producción
     * @param string   $tipoComprobante Nombre para log (ej: 'factura_venta')
     * @param int      $idEmpresa
     * @param int      $idUsuario
     * @param string   $estadoAutorizado  Estado interno a guardar ('autorizado' o 'autorizada')
     * @param callable $onAutorizado   Callback con ($numAut, $fechaAut, $xmlDetalle) para updates específicos de tabla
     */
    private function preVerificarAutorizacion(
        string   $tabla,
        int      $id,
        string   $claveAcceso,
        string   $tipoAmbiente,
        string   $tipoComprobante,
        int      $idEmpresa,
        int      $idUsuario,
        string   $estadoAutorizado,
        callable $onAutorizado
    ): ?array {
        try {
            $consulta = $this->ws->consultarAutorizacion($claveAcceso, $tipoAmbiente);
        } catch (\Throwable $e) {
            // Si falla la consulta previa, no bloqueamos el envío
            error_log("[SRI preVerificar] No se pudo consultar estado previo: " . $e->getMessage());
            return null;
        }

        if (strtoupper($consulta['estado'] ?? '') !== 'AUTORIZADO') {
            return null; // No está autorizado aún — continuar con envío normal
        }

        $numAut  = $consulta['numero_autorizacion'] ?: $claveAcceso;
        $fechaAut = $consulta['fecha_autorizacion'] ?: null;
        $xmlComp  = $consulta['xml_autorizado'] ?: '';

        $xmlDetalle = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $xmlComp);

        $this->actualizarEstadoDocumento($tabla, $id, $estadoAutorizado, $fechaAut, $xmlComp ?: null, null, $idUsuario);

        $this->log([
            'id_empresa'          => $idEmpresa,
            'tipo_comprobante'    => $tipoComprobante,
            'id_comprobante'      => $id,
            'clave_acceso'        => $claveAcceso,
            'tipo_ambiente'       => $tipoAmbiente,
            'created_by'          => $idUsuario,
            'accion'              => $estadoAutorizado,
            'estado_sri'          => 'AUTORIZADO',
            'mensaje'             => 'Comprobante ya autorizado en el SRI (verificación previa al envío).',
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        $onAutorizado($numAut, $fechaAut, $xmlDetalle);

        return [
            'ok'                  => true,
            'estado'              => $estadoAutorizado,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => 'El comprobante ya se encontraba autorizado en el SRI.',
            'errores'             => [],
        ];
    }

    private function actualizarEstadoSri(
        int     $idVenta,
        string  $estadoSri,
        ?string $fechaAutorizacion,
        ?string $xmlAutorizado,
        ?string $mensajesSri,
        int     $idUsuario
    ): void {
        $this->actualizarEstadoDocumento('ventas_cabecera', $idVenta, $estadoSri, $fechaAutorizacion, $xmlAutorizado, $mensajesSri, $idUsuario);
    }

    private array $colsCache = [];
    private function columnasExistentes(\PDO $db, string $tabla): array
    {
        if (!isset($this->colsCache[$tabla])) {
            $st = $db->prepare(
                "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'"
            );
            $st->execute([$tabla]);
            $this->colsCache[$tabla] = $st->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $this->colsCache[$tabla];
    }

    /**
     * Obtiene la configuración de firma electrónica activa de una empresa.
     * Usa la tabla empresa_firma (es_activo = true, eliminado = false).
     * Devuelve null si no hay certificado activo configurado.
     */
    public function getFirmaConfig(int $idEmpresa): ?array
    {
        $db = Database::getConnection();

        $st = $db->prepare(
            "SELECT id, id_empresa, archivo_nombre, archivo_ruta, password_firma,
                    fecha_emision, fecha_expiracion, es_activo
             FROM empresa_firma
             WHERE id_empresa = ? AND es_activo = TRUE AND eliminado = FALSE
             ORDER BY fecha_expiracion DESC NULLS LAST, created_at DESC
             LIMIT 1"
        );
        $st->execute([$idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Normalizar nombres de campo al contrato interno del firmador
        return [
            'archivo_path' => $row['archivo_ruta'],
            'p12_password' => $row['password_firma'],
            'archivo_nombre' => $row['archivo_nombre'] ?? '',
        ];
    }

    // ── Guía de Remisión ──────────────────────────────────────────────────────

    public function enviarGuiaRemision(int $idGuia, int $idEmpresa, int $idUsuario): array
    {
        $repo = new \App\repositories\modulos\GuiaRemisionRepository();

        $cabecera = $repo->getPorId($idGuia);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException("Guía de remisión #{$idGuia} no encontrada.");
        }
        if (empty($cabecera['clave_acceso'])) {
            throw new \RuntimeException("La guía de remisión no tiene clave de acceso generada.");
        }

        $tipoAmbiente = $cabecera['tipo_ambiente'] ?? '1';
        $claveAcceso  = $cabecera['clave_acceso'];

        $preCheck = $this->preVerificarAutorizacion(
            'guias_remision_cabecera', $idGuia, $claveAcceso, $tipoAmbiente,
            'guia_remision', $idEmpresa, $idUsuario, 'autorizado',
            function (string $numAut, ?string $fechaAut, string $xmlDetalle) use ($repo, $idGuia, $idUsuario): void {
                $db = Database::getConnection();
                $db->prepare("UPDATE guias_remision_cabecera SET estado = 'autorizado', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$numAut, $idUsuario, $idGuia]);
                try { $repo->updateDetalleXml($idGuia, $xmlDetalle); } catch (\Throwable) {}
            }
        );
        if ($preCheck !== null) {
            return $preCheck;
        }

        $fechaEmision = (new \DateTime($cabecera['fecha_emision']))->format('Y-m-d');
        $hoy          = (new \DateTime())->format('Y-m-d');
        if ($fechaEmision !== $hoy) {
            $fechaFmt = (new \DateTime($cabecera['fecha_emision']))->format('d-m-Y');
            throw new \RuntimeException(
                "No se puede enviar al SRI: la fecha de emisión ({$fechaFmt}) debe ser la fecha actual ({$hoy})."
            );
        }

        $detalles      = $repo->getDetalles($idGuia);
        $infoAdicional = $repo->getInfoAdicional($idGuia);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $dirEstablecimiento = null;
        if (!empty($cabecera['id_establecimiento'])) {
            try {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        // 1. Generar XML
        $xmlService = new \App\Services\Xml\XmlGuiaRemisionService();
        $xmlLimpio  = $xmlService->generar($cabecera, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);

        // 2. Obtener firma
        $firmaConfig = $this->getFirmaConfig($idEmpresa);
        if (!$firmaConfig) {
            throw new \RuntimeException(
                "La empresa no tiene certificado de firma electrónica configurado. " .
                "Configure el certificado .p12 en Configuración → Firma Electrónica."
            );
        }

        // 3. Firmar XML
        $xmlFirmado = $this->firmador->firmar($xmlLimpio, $firmaConfig['archivo_path'], $firmaConfig['p12_password']);

        // 4. Log inicio
        $logBase = [
            'id_empresa'      => $idEmpresa,
            'tipo_comprobante'=> 'guia_remision',
            'id_comprobante'  => $idGuia,
            'clave_acceso'    => $claveAcceso,
            'tipo_ambiente'   => $tipoAmbiente,
            'created_by'      => $idUsuario,
        ];

        $this->actualizarEstadoDocumento('guias_remision_cabecera', $idGuia, 'enviando', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'enviando', 'mensaje' => 'Guía enviada al WS de recepción del SRI.']);

        // 5. Enviar al WS
        $recepcion = $this->ws->enviarRecepcion($xmlFirmado, $tipoAmbiente);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $erroresJson = json_encode($recepcion['errores'], JSON_UNESCAPED_UNICODE);
            $this->actualizarEstadoDocumento('guias_remision_cabecera', $idGuia, 'devuelta', null, null, $erroresJson, $idUsuario);
            $this->log($logBase + [
                'accion' => 'devuelta', 'estado_sri' => 'DEVUELTA',
                'mensaje' => 'El SRI devolvió la guía con errores.', 'detalle_json' => $erroresJson,
            ]);
            return ['ok' => false, 'estado' => 'devuelta', 'mensaje' => 'El SRI devolvió la guía con errores.', 'errores' => $recepcion['errores']];
        }

        $this->actualizarEstadoDocumento('guias_remision_cabecera', $idGuia, 'recibida', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'recibida', 'estado_sri' => 'RECIBIDA', 'mensaje' => 'Guía recibida por el SRI. Consultando autorización…']);

        // 6. Consultar autorización
        $autResult = $this->consultarConReintentos($claveAcceso, $tipoAmbiente);

        $erroresJson   = !empty($autResult['errores']) ? json_encode($autResult['errores'], JSON_UNESCAPED_UNICODE) : null;
        $fechaAut      = $autResult['fecha_autorizacion'] ?: null;
        $numAut        = $autResult['numero_autorizacion'] ?? $claveAcceso;

        $estadoInterno = match (strtoupper($autResult['estado'] ?? '')) {
            'AUTORIZADO'                 => 'autorizado',
            'NO AUTORIZADO', 'RECHAZADO' => 'no_autorizado',
            'EN PROCESAMIENTO'           => 'en_procesamiento',
            default                      => 'error',
        };

        $this->actualizarEstadoDocumento('guias_remision_cabecera', $idGuia, $estadoInterno, $fechaAut, null, $erroresJson, $idUsuario);
        $this->log($logBase + [
            'accion'              => $estadoInterno,
            'estado_sri'          => strtoupper($autResult['estado'] ?? ''),
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Guía autorizada por el SRI.' : 'El SRI no autorizó la guía.',
            'detalle_json'        => $erroresJson,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        if ($estadoInterno === 'autorizado') {
            $db = Database::getConnection();
            $db->prepare(
                "UPDATE guias_remision_cabecera SET estado = 'autorizado', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$numAut, $idUsuario, $idGuia]);

            $xmlAutorizado = $autResult['xml_autorizado'] ?: null;
            $comprobante   = !empty($xmlAutorizado) ? $xmlAutorizado : $xmlFirmado;
            $xmlDetalleCompleto = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $comprobante);

            try {
                $repo->updateDetalleXml($idGuia, $xmlDetalleCompleto);
            } catch (\Throwable $eXml) {
                error_log('[SRI] Error guardando detalle_xml en guía #' . $idGuia . ': ' . $eXml->getMessage());
            }
        }

        return [
            'ok'                  => $estadoInterno === 'autorizado',
            'estado'              => $estadoInterno,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Guía autorizada por el SRI.' : 'El SRI no autorizó la guía.',
            'errores'             => $autResult['errores'] ?? [],
        ];
    }

    // ── Liquidación de Compra ─────────────────────────────────────────────────

    public function enviarLiquidacionCompra(int $idLiq, int $idEmpresa, int $idUsuario): array
    {
        $repo = new \App\repositories\modulos\LiquidacionCompraRepository();

        $cabecera = $repo->getPorId($idLiq);
        if (!$cabecera || (int)$cabecera['id_empresa'] !== $idEmpresa) {
            throw new \RuntimeException("Liquidación #{$idLiq} no encontrada.");
        }
        if (empty($cabecera['clave_acceso'])) {
            throw new \RuntimeException("La liquidación no tiene clave de acceso generada.");
        }

        $tipoAmbiente = $cabecera['tipo_ambiente'] ?? '1';
        $claveAcceso  = $cabecera['clave_acceso'];

        $preCheck = $this->preVerificarAutorizacion(
            'liquidaciones_cabecera', $idLiq, $claveAcceso, $tipoAmbiente,
            'liquidacion_compra', $idEmpresa, $idUsuario, 'autorizado',
            function (string $numAut, ?string $fechaAut, string $xmlDetalle) use ($repo, $idLiq, $idUsuario): void {
                $db = Database::getConnection();
                $db->prepare("UPDATE liquidaciones_cabecera SET estado = 'autorizado', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$numAut, $idUsuario, $idLiq]);
                try { $repo->updateDetalleXml($idLiq, $xmlDetalle); } catch (\Throwable) {}
            }
        );
        if ($preCheck !== null) {
            return $preCheck;
        }

        $fechaEmision = (new \DateTime($cabecera['fecha_emision']))->format('Y-m-d');
        $hoy          = (new \DateTime())->format('Y-m-d');
        if ($fechaEmision !== $hoy) {
            $fechaFmt = (new \DateTime($cabecera['fecha_emision']))->format('d-m-Y');
            throw new \RuntimeException(
                "No se puede enviar al SRI: la fecha de emisión ({$fechaFmt}) debe ser la fecha actual ({$hoy})."
            );
        }

        $detalles      = $repo->getDetalles($idLiq);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $repo->getImpuestosDetalle((int)$d['id']);
        }
        unset($d);

        $pagos         = $repo->getPagos($idLiq);
        $infoAdicional = $repo->getInfoAdicional($idLiq);

        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $dirEstablecimiento = null;
        if (!empty($cabecera['id_establecimiento'])) {
            try {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            } catch (\Throwable) {}
        }

        // 1. Generar XML
        $xmlService = new \App\Services\Xml\XmlLiquidacionCompraService();
        $xmlLimpio  = $xmlService->generar($cabecera, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

        // 2. Firma
        $firmaConfig = $this->getFirmaConfig($idEmpresa);
        if (!$firmaConfig) {
            throw new \RuntimeException(
                "La empresa no tiene certificado de firma electrónica configurado. " .
                "Configure el certificado .p12 en Configuración → Firma Electrónica."
            );
        }

        // 3. Firmar XML
        $xmlFirmado = $this->firmador->firmar($xmlLimpio, $firmaConfig['archivo_path'], $firmaConfig['p12_password']);

        // 4. Log inicio
        $logBase = [
            'id_empresa'       => $idEmpresa,
            'tipo_comprobante' => 'liquidacion_compra',
            'id_comprobante'   => $idLiq,
            'clave_acceso'     => $claveAcceso,
            'tipo_ambiente'    => $tipoAmbiente,
            'created_by'       => $idUsuario,
        ];

        $this->actualizarEstadoDocumento('liquidaciones_cabecera', $idLiq, 'enviando', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'enviando', 'mensaje' => 'Liquidación enviada al WS de recepción del SRI.']);

        // 5. Enviar al WS de recepción
        $recepcion = $this->ws->enviarRecepcion($xmlFirmado, $tipoAmbiente);

        if ($recepcion['estado'] !== 'RECIBIDA') {
            $erroresJson = json_encode($recepcion['errores'], JSON_UNESCAPED_UNICODE);
            $this->actualizarEstadoDocumento('liquidaciones_cabecera', $idLiq, 'devuelta', null, null, $erroresJson, $idUsuario);
            $this->log($logBase + [
                'accion'       => 'devuelta',
                'estado_sri'   => 'DEVUELTA',
                'mensaje'      => 'El SRI devolvió la liquidación con errores.',
                'detalle_json' => $erroresJson,
            ]);
            return ['ok' => false, 'estado' => 'devuelta', 'mensaje' => 'El SRI devolvió la liquidación con errores.', 'errores' => $recepcion['errores']];
        }

        $this->actualizarEstadoDocumento('liquidaciones_cabecera', $idLiq, 'recibida', null, null, null, $idUsuario);
        $this->log($logBase + ['accion' => 'recibida', 'estado_sri' => 'RECIBIDA', 'mensaje' => 'Liquidación recibida por el SRI. Consultando autorización…']);

        // 6. Consultar autorización
        $autResult = $this->consultarConReintentos($claveAcceso, $tipoAmbiente);

        $erroresJson   = !empty($autResult['errores']) ? json_encode($autResult['errores'], JSON_UNESCAPED_UNICODE) : null;
        $fechaAut      = $autResult['fecha_autorizacion'] ?: null;
        $numAut        = $autResult['numero_autorizacion'] ?? $claveAcceso;

        $estadoInterno = match (strtoupper($autResult['estado'] ?? '')) {
            'AUTORIZADO'                 => 'autorizado',
            'NO AUTORIZADO', 'RECHAZADO' => 'no_autorizado',
            'EN PROCESAMIENTO'           => 'en_procesamiento',
            default                      => 'error',
        };

        $this->actualizarEstadoDocumento('liquidaciones_cabecera', $idLiq, $estadoInterno, $fechaAut, null, $erroresJson, $idUsuario);
        $this->log($logBase + [
            'accion'              => $estadoInterno,
            'estado_sri'          => strtoupper($autResult['estado'] ?? ''),
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Liquidación autorizada por el SRI.' : 'El SRI no autorizó la liquidación.',
            'detalle_json'        => $erroresJson,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
        ]);

        if ($estadoInterno === 'autorizado') {
            $db = Database::getConnection();
            $db->prepare(
                "UPDATE liquidaciones_cabecera SET estado = 'autorizado', numero_autorizacion = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$numAut, $idUsuario, $idLiq]);

            $xmlAutorizado = $autResult['xml_autorizado'] ?: null;
            $comprobante   = !empty($xmlAutorizado) ? $xmlAutorizado : $xmlFirmado;
            $xmlDetalleCompleto = $this->buildXmlDetalleCompleto($numAut, (string)$fechaAut, $tipoAmbiente, $comprobante);

            try {
                $repo->updateDetalleXml($idLiq, $xmlDetalleCompleto);
            } catch (\Throwable $eXml) {
                error_log('[SRI] Error guardando detalle_xml en liquidación #' . $idLiq . ': ' . $eXml->getMessage());
            }
        }

        return [
            'ok'                  => $estadoInterno === 'autorizado',
            'estado'              => $estadoInterno,
            'numero_autorizacion' => $numAut,
            'fecha_autorizacion'  => $fechaAut,
            'mensaje'             => $estadoInterno === 'autorizado' ? 'Liquidación autorizada por el SRI.' : 'El SRI no autorizó la liquidación.',
            'errores'             => $autResult['errores'] ?? [],
        ];
    }

}

